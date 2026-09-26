<?php

/**
 * CLI 公共工具：路径、日志、PID、进程探测等。
 *
 * 运行目录 content/server/：
 *   server.pid        主进程 PID
 *   server.log        服务日志
 *   server.heartbeat  心跳文件（后台首页据此显示「运行中」）
 *   workers.json      当前各 worker PID
 *   reload.flag       存在则主进程重启全部 worker
 */
final class CliServer
{
    /**
     * 内置 worker 类型名（对应 --type=）。
     *
     * 核心已不再内置这些 worker 的实现，常量仅为向后兼容保留：
     * 插件用 `CliServer::WORKER_QUEUE` 之类的名字注册自己的实现，
     * 心跳文件名也沿用同一套 type，两边不会写岔。
     */
    public const WORKER_HEARTBEAT = 'heartbeat';
    public const WORKER_QUEUE = 'queue';
    public const WORKER_ORDER_POLL = 'order_poll';
    public const WORKER_GOODS_SYNC = 'goods_sync';

    /** @var list<array{type: string, label: string, class: string, plugin: string}>|null worker 定义缓存 */
    private static $workerDefs;

    /** 控制台编码适配是否已装载（防止重复叠加输出缓冲层） */
    private static $consoleEncodingAdapted = false;

    /**
     * 任务服务「代码版本」配置 key。
     *
     * 语义：一段新代码已落盘（pending），但运行中的 worker 内存里还是旧代码（applied）。
     * 管家每 5 秒比对两者，pending 更大就写 reload.flag 重启全部 worker 去加载新代码。
     *
     * 写入一律走 bumpPendingFileVersion()，不要直接 Config::set。
     */
    public const CONFIG_FILE_VERSION_APPLIED = 'server_file_version_applied';
    public const CONFIG_FILE_VERSION_PENDING = 'server_file_version_pending';

    /**
     * 历史遗留 key（名字来自早已不存在的 Swoole 后台进程方案）。
     *
     * **只能读、不该再写**：应用商店里的第三方插件在自己的 callback_update() 里
     * 直接写这些 key 来 bump 版本。一旦停止读取，那些插件的更新会**静默失效**
     * ——插件文件换新了，worker 却还在跑旧代码，且不报任何错。
     *
     * 仓库内所有写入点已改为 bumpPendingFileVersion()（它会双写新 key + 遗留 key），
     * 但这两行读取要长期保留，直到确认商店里再没有直接写遗留 key 的插件为止。
     */
    public const CONFIG_FILE_VERSION_APPLIED_LEGACY = 'local_swoole_file_version';
    public const CONFIG_FILE_VERSION_PENDING_LEGACY = 'new_swoole_file_version';

    /** @var string|null */
    private static $runtimeDir;

    public static function runtimeDir(): string
    {
        if (self::$runtimeDir === null) {
            self::$runtimeDir = EM_ROOT . '/content/server';
        }
        return self::$runtimeDir;
    }

    public static function pidFile(): string
    {
        return self::runtimeDir() . '/server.pid';
    }

    public static function logFile(): string
    {
        return self::runtimeDir() . '/server.log';
    }

    /**
     * 宿主心跳文件：由**管家进程**每轮循环刷新，表示 `php server` 这个进程还活着。
     *
     * 判断「某类任务有没有人在消费」请用 WorkerHeartbeat::isAlive($type)，
     * 那读的是每个 worker 自己刷的能力心跳 —— 管家活着不等于队列在消费。
     */
    public static function heartbeatFile(): string
    {
        return WorkerHeartbeat::hostFile();
    }

    /** 刷新宿主心跳（管家进程调用） */
    public static function touchHeartbeat(): void
    {
        WorkerHeartbeat::touchHost();
    }

    public static function workersStateFile(): string
    {
        return self::runtimeDir() . '/workers.json';
    }

    public static function reloadFlagFile(): string
    {
        return self::runtimeDir() . '/reload.flag';
    }

    /** 写 reload.flag，让主进程下一轮循环重启 worker */
    public static function requestReload(): bool
    {
        self::ensureRuntimeDir();
        return @file_put_contents(self::reloadFlagFile(), (string) time(), LOCK_EX) !== false;
    }

    /** 主进程调用：若有 reload.flag 则删掉并返回 true */
    public static function consumeReloadRequest(): bool
    {
        $file = self::reloadFlagFile();
        if (!is_file($file)) {
            return false;
        }
        @unlink($file);
        return true;
    }

    /**
     * 读取已生效（applied）文件版本：优先新 key，否则回退旧 key。
     */
    public static function getAppliedFileVersion(): string
    {
        return self::readConfigPrefer(
            self::CONFIG_FILE_VERSION_APPLIED,
            self::CONFIG_FILE_VERSION_APPLIED_LEGACY,
            '0.0.0'
        );
    }

    /**
     * 读取待生效（pending）文件版本；多 key 都有值时取较大者（避免漏掉只 bump 旧 key 的插件）。
     */
    public static function getPendingFileVersion(): string
    {
        $primary = trim((string) (Config::get(self::CONFIG_FILE_VERSION_PENDING, '') ?? ''));
        $legacy = trim((string) (Config::get(self::CONFIG_FILE_VERSION_PENDING_LEGACY, '') ?? ''));
        if ($primary === '') {
            return $legacy;
        }
        if ($legacy === '') {
            return $primary;
        }
        return @version_compare($primary, $legacy, '>=') ? $primary : $legacy;
    }

    /**
     * 写入已生效版本（applied + 旧 key 双写）。
     */
    public static function setAppliedFileVersion(string $version): void
    {
        $version = trim($version);
        Config::set(self::CONFIG_FILE_VERSION_APPLIED, $version);
        Config::set(self::CONFIG_FILE_VERSION_APPLIED_LEGACY, $version);
    }

    /**
     * 某个插件的代码变了：**只重启这个插件自己的 worker**。
     *
     * 插件更新时应该调用它，而不是 bumpPendingFileVersion() ——
     * 后者是全局信号，会把所有 worker 一起重启（打断别的插件正在干的任务）。
     *
     * 为什么现在可以只重启自己：这轮改造把跨插件的钩子调用都消掉了 ——
     * 发货由各商品类型插件自己的 worker 执行，通知改走事件落库由订阅方自己消费。
     * 所以一个插件的代码只在它自己的 worker 进程里跑，更新它不会影响别人。
     *
     * ⚠️ 前提：**插件的 worker 里不要 doAction 别人的钩子**。一旦越界，
     * 别人的代码就会住进你的 worker，那时只重启你自己的 worker 会漏掉那个副本。
     */
    public static function markPluginChanged(string $slug): void
    {
        $slug = trim($slug);
        if ($slug === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
            return;
        }

        try {
            Config::set(self::pluginReloadKey($slug), (string) time());
        } catch (Throwable $e) {
            self::log("异常：标记插件 {$slug} 需要重载失败，" . $e->getMessage());
        }
    }

    /** 插件变更标记的 config key */
    public static function pluginReloadKey(string $slug): string
    {
        return 'plugin_reload_' . $slug;
    }

    /**
     * 标记「有新代码已落盘，请重启 worker 加载」。
     *
     * **只用于核心升级 / 数据库迁移** —— 那些改动所有 worker 都受影响。
     * 单个插件更新请用 markPluginChanged()，只重启它自己的 worker。
     *
     * **代码更新后唯一的调用入口**（插件 callback_update / 程序升级 / 迁移脚本都用它）。
     * 双写新 key + 遗留 key：新 key 是本项目的正式命名，遗留 key 是为了让
     * 尚未迁移的老插件 bump 与我们对齐（见 CONFIG_FILE_VERSION_*_LEGACY 的注释）。
     */
    public static function bumpPendingFileVersion(?string $version = null): void
    {
        $version = trim((string) ($version !== null ? $version : time()));
        if ($version === '') {
            $version = (string) time();
        }
        Config::set(self::CONFIG_FILE_VERSION_PENDING, $version);
        Config::set(self::CONFIG_FILE_VERSION_PENDING_LEGACY, $version);
    }

    /**
     * @param string $primary 新 key
     * @param string $legacy  旧 key
     */
    private static function readConfigPrefer(string $primary, string $legacy, string $default = ''): string
    {
        $value = trim((string) (Config::get($primary, '') ?? ''));
        if ($value !== '') {
            return $value;
        }
        $value = trim((string) (Config::get($legacy, '') ?? ''));
        if ($value !== '') {
            return $value;
        }
        return $default;
    }

    /**
     * 要拉起哪些 worker —— **由插件注册，核心不再内置**。
     *
     * 插件通过 `server_worker_types` 过滤器追加定义：
     *
     *   addFilter('server_worker_types', function (array $types): array {
     *       $types[] = [
     *           'type'   => 'queue',                             // 唯一标识，同时是心跳文件名
     *           'label'  => '发货队列',                           // 后台/日志里显示的名字
     *           'class'  => 'ServerDaemon\\Worker\\QueueWorker',  // 实现 ServerWorkerInterface
     *           'plugin' => 'server_daemon',                     // 贡献者插件名（用于启停监控）
     *       ];
     *       return $types;
     *   });
     *
     * 返回值里**总是**包含一条核心自有的 `heartbeat`（能力心跳维护，见 HeartbeatWorker）。
     * 除它以外没有业务 worker 时，`php server start` 会正常驻留但不跑任何业务任务，
     * 发货改走 OrderModel::triggerDelivery() 的 FPM 同步路径。
     *
     * 结果按 type 去重（先注册的赢），并过滤掉格式非法的条目。
     * 核心自有的 type 在插件之前注册，所以插件无法覆盖它们。
     *
     * @return list<array{type: string, label: string, class: string, plugin: string}>
     */
    public static function workerDefinitions(): array
    {
        if (self::$workerDefs !== null) {
            return self::$workerDefs;
        }

        self::loadSiteContext();

        $raw = function_exists('applyFilter') ? applyFilter('server_worker_types', []) : [];
        if (!is_array($raw)) {
            $raw = [];
        }

        // 核心自有的 worker 先占位：插件注册的 type 与它同名时会被下面的 isset 挡掉。
        // 它是能力心跳的唯一写入者，不能由插件决定有没有、跑不跑。
        $defs = [
            'heartbeat' => [
                'type'       => 'heartbeat',
                'label'      => '心跳维护',
                'class'      => HeartbeatWorker::class,
                'plugin'     => '',
                'claim'      => [],
                'hook'       => '',
                'subscribe'  => [],
                'capability' => '',
            ],
        ];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? '');
            $class = (string) ($item['class'] ?? '');
            // type 会拼进心跳文件名和 --type= 参数，必须是安全标识符
            if ($type === '' || !preg_match('/^[a-z0-9_]+$/i', $type)) {
                continue;
            }
            if ($class === '' || !class_exists($class)) {
                continue;
            }
            if (isset($defs[$type])) {
                continue;
            }
            $defs[$type] = [
                'type'   => $type,
                'label'  => trim((string) ($item['label'] ?? '')) !== '' ? (string) $item['label'] : $type,
                'class'  => $class,
                'plugin' => trim((string) ($item['plugin'] ?? '')),
                // 核心提供的通用 worker 从定义里读这几个字段：
                //   claim     — 只领哪些条件匹配的任务，如 ['goods_type' => 'virtual_card']
                //   hook      — 领到之后执行哪个钩子          （见 DeliveryTaskWorker）
                //   subscribe — 订阅哪些事件，按游标取走处理    （见 EventSubscriberWorker）
                'claim'     => is_array($item['claim'] ?? null) ? $item['claim'] : [],
                'hook'      => trim((string) ($item['hook'] ?? '')),
                'subscribe' => is_array($item['subscribe'] ?? null) ? $item['subscribe'] : [],
                // 能力名：描述这个 worker 提供的是哪一类能力（如 delivery）。
                //
                // 注意它**不驱动心跳** —— 能力心跳由核心的 HeartbeatWorker 按核心自己
                // 声明的清单维护，与插件装没装无关。这个字段是描述性的：worker-defs.json、
                // 后台的 worker 列表、以及将来按能力归纳任务时用。
                //
                // 默认由核心按实现类推导，插件不用手写字符串（错字会让「谁提供什么能力」
                // 变得不可信）。显式声明优先，可覆盖推导结果。
                'capability' => self::resolveCapability($item, $class),
            ];
        }

        self::$workerDefs = array_values($defs);
        return self::$workerDefs;
    }

    /**
     * 解析一个 worker 定义「提供哪种能力」。
     *
     * 插件显式写的 capability 优先（未来可能有核心认不出来的能力）；
     * 没写就按实现类推导 —— 目前唯一的能力是「有人在做发货」，
     * 而发货 worker 用的都是核心的 DeliveryTaskWorker，认得出来。
     *
     * @param array<string, mixed> $item
     */
    private static function resolveCapability(array $item, string $class): string
    {
        $declared = trim((string) ($item['capability'] ?? ''));
        if ($declared !== '') {
            return $declared;
        }

        if (is_a($class, DeliveryTaskWorker::class, true)) {
            return WorkerHeartbeat::CAPABILITY_DELIVERY;
        }

        return '';
    }

    /** 清掉 worker 定义缓存（测试/调试用；正常流程不需要） */
    public static function resetWorkerDefinitions(): void
    {
        self::$workerDefs = null;
    }

    /**
     * 主站已启用的插件名（去空白、去重、排序）。
     *
     * @return list<string>
     */
    public static function enabledPluginNames(): array
    {
        $raw = (string) (Config::get('enabled_plugins', '') ?? '');

        $names = [];
        foreach (explode(',', $raw) as $one) {
            $name = trim($one);
            if ($name !== '') {
                $names[$name] = true;
            }
        }
        $names = array_keys($names);
        sort($names);

        return $names;
    }

    /**
     * 启用插件名单的指纹，管家用它判断「要不要重新探测 worker」。
     *
     * 只读配置字符串，不碰插件代码，所以每 5 秒调用一次也很便宜。
     * 归一化（去重排序）是为了避免「顺序变了但集合没变」触发无谓的探测。
     */
    public static function enabledPluginsSignature(): string
    {
        return implode(',', self::enabledPluginNames());
    }

    /**
     * worker 定义探测结果文件（由 `php server probe-workers` 子进程写入）。
     */
    public static function workerDefsFile(): string
    {
        return self::runtimeDir() . '/worker-defs.json';
    }

    /**
     * 探测入口（**短命子进程**）：把当前「应运行的 worker」写成 JSON 文件后退出。
     *
     * 为什么要有这个独立进程：管家是常驻进程，插件代码一旦加载就无法卸载、
     * 也无法加载新启用的插件（Hooks 没有 remove、init.php 有 EM_INITIALIZED 守卫）。
     * 所以「插件变化后该跑哪些 worker」只能让一个**全新进程**来回答 ——
     * 它正常加载 init.php，拿到的就是最新最准的注册结果。
     *
     * 结果写文件而不是 stdout：init.php 和插件都可能往 stdout 输出，JSON 会被污染。
     *
     * @return int 退出码（0=成功）
     */
    public static function probeWorkers(): int
    {
        $out = [];
        foreach (self::workerDefinitions() as $def) {
            $out[] = [
                'type'   => (string) $def['type'],
                'label'  => (string) $def['label'],
                'class'  => (string) $def['class'],
                // 归属插件：管家靠它把 worker 按插件归组，
                // 收到某个插件的「要重载」标记时才知道该重启哪几个 worker
                'plugin' => (string) $def['plugin'],
                // 能力名：管家 spawn 时要把它传下去，停 worker 时好连能力心跳一起清
                'capability' => (string) $def['capability'],
            ];
        }

        self::ensureRuntimeDir();
        $json = json_encode($out, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return 1;
        }

        return @file_put_contents(self::workerDefsFile(), $json, LOCK_EX) === false ? 1 : 0;
    }

    /**
     * 加载站点上下文（init.php），让插件有机会注册 worker。
     *
     * 主进程原先刻意不 init 整站（"不需要连数据库"），但现在 worker 列表来自插件，
     * 不加载插件就无从知道该拉几个子进程。init.php 内部对 CLI 已做适配（跳过 session），
     * 且插件加载失败是被它 try/catch 吞掉的，不会阻断启动。
     */
    public static function loadSiteContext(): void
    {
        if (defined('EM_INITIALIZED')) {
            return;
        }
        $init = EM_ROOT . '/init.php';
        if (!is_file($init)) {
            return;
        }
        try {
            require_once $init;
        } catch (Throwable $e) {
            self::log('加载站点上下文失败：' . $e->getMessage());
        }

        // init.php 开头会 ob_start()（防止插件在 session/setcookie 之前产生输出）。
        // 这在 Web 请求里是对的，但管家是**常驻进程**：echo 全憋在缓冲区里，
        // 进程不退出就永不 flush，终端一个字都看不到（子进程没这问题，
        // 因为 CliServerWorker::run() 里有同样的清缓冲处理，管家原先漏了）。
        //
        // 只对 CLI 恢复直出 —— 不能无条件 flush，Web 下提前输出会破坏响应头。
        if (PHP_SAPI === 'cli') {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            if (function_exists('ob_implicit_flush')) {
                ob_implicit_flush(1);
            }
            // 上面的 flush 把之前那层编码适配缓冲也一起关掉了，这里重新装上
            self::$consoleEncodingAdapted = false;
            self::adaptConsoleEncoding();
        }
    }

    /**
     * 让控制台输出适配宿主环境的编码（Windows 下转 GBK）。
     *
     * **背景**：宝塔的 Supervisor（server_guardian）对子进程输出做
     * `sec.decode('gbk')`，而我们的控制台文案是 UTF-8。UTF-8 汉字按 GBK 解会出现
     * 「落单的续字节」—— 例如「动」= e5 8a a8，末字节 0xa8 找不到合法配对 ——
     * Python 直接抛 UnicodeDecodeError，guardian 于是判定「任务已停止」：
     * 服务明明在跑，却被面板显示成挂了，还会被反复重启。
     *
     * 所以 Windows 下把控制台输出转成 GBK：cmd / PowerShell 5.1 的中文代码页本来
     * 就是 936，显示正常；宝塔 guardian 也能正常解码。
     *
     * **只转控制台**。`server.log` 走 file_put_contents，保持 UTF-8 不受影响
     * （后台和编辑器读它，UTF-8 才是对的）。
     *
     * 只应在**管家进程**调用：worker 的输出经管家转发，在管家这一层统一转码；
     * 两边都转会变成二次转换（GBK 当 UTF-8 解）而乱码。
     *
     * 幂等：重复调用不会叠加缓冲层。
     */
    public static function adaptConsoleEncoding(): void
    {
        if (self::$consoleEncodingAdapted) {
            return;
        }
        self::$consoleEncodingAdapted = true;

        // 记一条决策日志：控制台编码出问题时，这是唯一能自证的信息
        // （宝塔面板日志本身可能就是被错误编码的，看不出来）
        self::log(sprintf(
            '控制台编码：%s（控制台输出代码页=%s，UTF-8=%s，强制=%s）',
            self::shouldConvertConsoleToGbk() ? 'GBK' : 'UTF-8',
            function_exists('sapi_windows_cp_get') ? (string) sapi_windows_cp_get() : '未知',
            function_exists('sapi_windows_cp_is_utf8') ? (sapi_windows_cp_is_utf8() ? '是' : '否') : '未知',
            defined('EM_CONSOLE_ENCODING') ? (string) EM_CONSOLE_ENCODING : '未设置'
        ));

        if (!self::shouldConvertConsoleToGbk()) {
            return;
        }
        if (!function_exists('mb_convert_encoding') && !function_exists('iconv')) {
            // 两个扩展都没有：保持 UTF-8 原样输出，至少不比改造前更差
            return;
        }
        if (!function_exists('ob_start')) {
            return;
        }

        // 输出是按行 echo 的（relayStream 攒够 \n 才输出），每次 echo 是完整字符串，
        // 所以不会把多字节字符拦腰截断后再转码。
        ob_start(static function (string $chunk): string {
            return CliServer::utf8ToGbk($chunk);
        });

        // 注意：这里**不能**靠 ob_implicit_flush(1) 来实时输出 ——
        // 它只对不带回调的缓冲生效，带回调时输出会一直攒到脚本结束才吐出来
        // （管家永不退出，等于什么都看不到）。实时性由调用方在主循环里
        // 显式 ob_flush() 保证，见 CliServerManager::cmdStart()。
    }

    /**
     * 控制台输出要不要转成 GBK。
     *
     * **不要**用系统 ANSI 代码页（`GetACP()`，中文 Windows 恒为 936）来判断 ——
     * 它跟"终端期望什么编码"是两回事。PowerShell 7 / Windows Terminal / mintty
     * 这类现代终端把**控制台输出代码页**设成 65001，它们的 ANSI 代码页仍是 936，
     * 按 ANSI 判断就会错误地转成 GBK，终端显示一片乱码。
     *
     * 正确的判据是 `sapi_windows_cp_is_utf8()`：它问的就是
     * "当前控制台输出代码页是不是 UTF-8"。
     *
     *   - 是 UTF-8（PS7 / Windows Terminal / mintty）→ 不转，原样输出 UTF-8
     *   - 不是（cmd / PowerShell 5.1 的 936）      → 转 GBK
     *   - 被 Supervisor 以管道捕获、没有控制台时，GetConsoleOutputCP() 返回 0，
     *     同样落到"不是 UTF-8"→ 转 GBK。宝塔的 guardian 正是按 GBK 解码的，对得上。
     *
     * 想强制指定时，在 config.php 里定义 `EM_CONSOLE_ENCODING`：
     *   'gbk' / 'utf8' —— 强制；其它值（含未定义）= 按上面的规则自动判断。
     */
    private static function shouldConvertConsoleToGbk(): bool
    {
        // 非 Windows 一律 UTF-8（Linux 宝塔、终端都是 UTF-8）
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        $forced = defined('EM_CONSOLE_ENCODING') ? strtolower(trim((string) EM_CONSOLE_ENCODING)) : '';
        if ($forced === 'gbk') {
            return true;
        }
        if ($forced === 'utf8' || $forced === 'utf-8') {
            return false;
        }

        if (function_exists('sapi_windows_cp_is_utf8')) {
            return !sapi_windows_cp_is_utf8();
        }

        // 老 PHP 没有这个 API：按中文 Windows 的传统取 GBK
        return true;
    }

    /**
     * 把控制台缓冲里的内容立刻吐出去并完成转码。
     *
     * 长驻进程每轮循环调一次；短命令不用管，进程退出时 PHP 会自动 flush。
     */
    public static function flushConsole(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
    }

    /**
     * UTF-8 → GBK。失败时原样返回，绝不因为转码问题把输出吞掉。
     */
    public static function utf8ToGbk(string $text): string
    {
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($text, 'GBK', 'UTF-8');
            if (is_string($converted)) {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            // //TRANSLIT 把 GBK 表达不了的字符转成近似形式，避免整段丢弃
            $converted = @iconv('UTF-8', 'GBK//TRANSLIT', $text);
            if (is_string($converted)) {
                return $converted;
            }
        }

        return $text;
    }

    public static function ensureRuntimeDir(): void
    {
        $dir = self::runtimeDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    public static function log(string $message): void
    {
        self::ensureRuntimeDir();

        // 统一脱敏后再落盘：日志文件在 web 根内，可被直接下载，
        // 里面不能出现订单号（游客查单凭据）、联系方式、哈希令牌、密钥等。
        // 详见 LogSanitizer 的类注释。
        $safeMessage = class_exists('LogSanitizer')
            ? LogSanitizer::sanitize($message)
            : $message;

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $safeMessage . "\n";
        @file_put_contents(self::logFile(), $line, FILE_APPEND | LOCK_EX);
    }

    public static function phpBinary(): string
    {
        return PHP_BINARY !== '' ? PHP_BINARY : 'php';
    }

    public static function readPid(string $pidFile = ''): int
    {
        $file = $pidFile !== '' ? $pidFile : self::pidFile();
        if (!is_file($file)) {
            return 0;
        }
        return (int) trim((string) file_get_contents($file));
    }

    public static function writePid(int $pid): void
    {
        self::ensureRuntimeDir();
        @file_put_contents(self::pidFile(), (string) $pid, LOCK_EX);
    }

    public static function clearPid(): void
    {
        $file = self::pidFile();
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * 本平台运行任务服务所必需的「进程管理」函数。
     *
     * 这些函数经常出现在 php.ini 的 disable_functions 里（虚拟主机尤甚），
     * 少了任何一个都会让 start/stop/status 中的某一环直接致命错误 ——
     * 所以启动前要先查，缺了就明确报错退出，而不是起一个半死不活的进程。
     *
     * @return array<string, string> 函数名 => 用途说明
     */
    public static function requiredProcessFunctions(): array
    {
        $need = self::requiredProcFunctions();
        if (PHP_OS_FAMILY === 'Windows') {
            $need['shell_exec'] = '探测进程是否存活（tasklist）';
            $need['exec'] = '停止遗留进程（taskkill）';
        }
        return $need;
    }

    /**
     * 拉起/监护/停止子进程这条链直接依赖的 proc_* 函数。
     *
     * 注意 proc_get_status 和 proc_terminate 也在 disable_functions 的常见名单里
     * （很多环境只放行 proc_open，把其余 proc_* 一并禁掉）——
     * 只看 proc_open 是不够的，四个都要查。
     *
     * @return array<string, string> 函数名 => 用途说明
     */
    public static function requiredProcFunctions(): array
    {
        return [
            'proc_open'       => '拉起 worker 子进程',
            'proc_get_status' => '获取子进程 PID、判断是否存活',
            'proc_terminate'  => '向子进程发送停止信号',
            'proc_close'      => '回收子进程句柄',
        ];
    }

    /**
     * 缺失的 proc_* 函数（不含平台的 shell_exec/exec/posix_kill）。
     *
     * @return array<string, string>
     */
    public static function missingProcFunctions(): array
    {
        $missing = [];
        foreach (self::requiredProcFunctions() as $fn => $purpose) {
            if (!function_exists($fn)) {
                $missing[$fn] = $purpose;
            }
        }
        return $missing;
    }

    /**
     * 调用进程管理函数的统一切口。
     *
     * 静态的启动前检查只能挡住「已知」的函数，漏一个就是一发致命错误 ——
     * 所以每个调用点也要走这里兜底：函数不存在或调用抛异常时记日志并返回
     * $default，绝不让服务因为环境差异直接挂掉。
     *
     * @param string   $function 函数名（用于日志与存在性判断）
     * @param callable $callback 真正调用（内部不要再判断 function_exists）
     * @param mixed    $default  不可用时返回的兜底值
     * @return mixed
     */
    public static function procCall(string $function, callable $callback, $default = null)
    {
        if (!function_exists($function)) {
            self::log("进程管理函数 {$function} 不可用（可能被 disable_functions 禁用），本次调用已跳过");
            return $default;
        }
        try {
            return $callback();
        } catch (Throwable $e) {
            self::log("进程管理函数 {$function} 调用异常：" . $e->getMessage());
            return $default;
        }
    }

    /**
     * 缺失的进程管理函数；返回空数组表示环境可用。
     *
     * @return array<string, string> 函数名 => 用途说明（用于报错时逐条解释）
     */
    public static function missingProcessFunctions(): array
    {
        $missing = [];
        foreach (self::requiredProcessFunctions() as $fn => $purpose) {
            if (!function_exists($fn)) {
                $missing[$fn] = $purpose;
            }
        }

        // Linux 探测/停止进程有三条路可选：posix_kill、/proc 目录、exec(kill)。
        // 至少要有一条，否则 start/stop/reload 全部失效。
        if (PHP_OS_FAMILY !== 'Windows'
            && !function_exists('posix_kill')
            && !function_exists('exec')
            && !is_dir('/proc')) {
            $missing['posix_kill 或 exec'] = '探测并停止 worker 进程';
        }

        return $missing;
    }

    /**
     * 进程是否仍存活。
     *
     * 探测手段按平台逐级降级；全部不可用时返回 false（当作「没在跑」），
     * 真正的阻断由 CliServerManager::cmdStart() 的能力预检负责 ——
     * 这里绝不能因为函数被禁用而抛致命错误。
     */
    public static function isProcessAlive(int $pid): bool
    {
        if ($pid < 1) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows 只能靠 tasklist，而 tasklist 只能靠 shell_exec
            if (!function_exists('shell_exec')) {
                return false;
            }
            $cmd = 'tasklist /FI "PID eq ' . (int) $pid . '" /NH 2>nul';
            $out = (string) @shell_exec($cmd);
            return strpos($out, (string) $pid) !== false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        if (is_dir('/proc')) {
            return file_exists('/proc/' . $pid);
        }

        if (function_exists('exec')) {
            $out = [];
            $code = 1;
            @exec('kill -0 ' . (int) $pid . ' 2>/dev/null', $out, $code);
            return $code === 0;
        }

        return false;
    }

    /**
     * 向进程发送停止信号（Windows 使用 taskkill）。
     *
     * 同样逐级降级；所有手段都不可用时记日志并返回 false，不抛致命错误。
     */
    public static function stopProcess(int $pid): bool
    {
        if ($pid < 1) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            if (!function_exists('exec')) {
                self::log("停止进程失败：exec 被禁用，无法执行 taskkill（PID {$pid}）");
                return false;
            }
            $out = [];
            $code = 1;
            @exec('taskkill /PID ' . (int) $pid . ' /T /F 2>nul', $out, $code);
            return $code === 0;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
        }

        if (function_exists('exec')) {
            $out = [];
            $code = 1;
            @exec('kill -TERM ' . (int) $pid . ' 2>/dev/null', $out, $code);
            return $code === 0;
        }

        self::log("停止进程失败：posix_kill 与 exec 都被禁用（PID {$pid}）");
        return false;
    }

    /**
     * 等待进程退出，超时返回 false。
     */
    public static function waitForProcessExit(int $pid, int $timeoutSeconds = 15): bool
    {
        if ($pid < 1) {
            return true;
        }

        $deadline = time() + max(1, $timeoutSeconds);
        while (time() < $deadline) {
            if (!self::isProcessAlive($pid)) {
                return true;
            }
            usleep(200000);
        }

        return !self::isProcessAlive($pid);
    }

    /**
     * 启动前探测数据库：临时连接，成功后立即关闭。
     */
    public static function probeMysql(): void
    {
        $cfg = require EM_ROOT . '/config.php';
        $db = (array) ($cfg['db'] ?? []);

        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (int) ($db['port'] ?? 3306);
        $dbname = (string) ($db['dbname'] ?? '');
        $username = (string) ($db['username'] ?? '');
        $password = (string) ($db['password'] ?? '');
        $charset = (string) ($db['charset'] ?? 'utf8mb4');

        if ($dbname === '' || $username === '') {
            throw new RuntimeException('数据库配置不完整');
        }

        if (extension_loaded('mysqli')) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $mysqli = mysqli_init();
            $mysqli->real_connect($host, $username, $password, $dbname, $port);
            $mysqli->set_charset($charset);
            $rs = $mysqli->query('SELECT 1');
            if ($rs instanceof mysqli_result) {
                $rs->free();
            }
            $mysqli->close();
            return;
        }

        if (extension_loaded('pdo_mysql')) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbname, $charset);
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->query('SELECT 1');
            $pdo = null;
            return;
        }

        throw new RuntimeException('当前 PHP 环境既不支持 mysqli，也不支持 pdo_mysql，无法探测数据库连接');
    }
}
