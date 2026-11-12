<?php

declare(strict_types=1);

namespace App\Service;





/**
 * 在线升级服务。
 *
 * 升级流水线（由 admin/update.php 按顺序调度 AJAX 调用）：
 *   1. preflight       —— 预检：写权限 / 磁盘空间 / PHP 版本 / 源版本兼容
 *   2. download        —— 下载升级包到临时 cache（校验 SHA256）
 *   3. extract         —— 解压到 tmp/update/extract/，成功后删除 zip
 *   4. apply           —— 用解压出来的文件替换项目内容（不备份旧文件）
 *   5. migrate         —— 扫 install/migrations/ 与 em_migrations 表求差集，跑新增 SQL
 *   6. run_script      —— 升级包根目录若带 update.php，执行一次后删除
 *   7. finalize        —— 清理临时文件
 *
 * 回滚策略（无文件备份）：
 *   - apply 失败 → 仅删除本次新增的文件；已覆盖的文件需手动恢复
 *   - migrate 失败 → 不自动回滚数据库
 *
 * 所有路径均使用 /（UNIX 风格），PHP 在 Windows 下也接受。
 */
final class UpdateService extends BaseService
{
    /** 下载的 zip 存放处 */
    private const CACHE_DIR     = '/content/tmp/update/cache';
    /** zip 解压目标 */
    private const EXTRACT_DIR   = '/content/tmp/update/extract';
    /** 当前批次的 apply 文件清单（回滚时用） */
    private const MANIFEST_FILE = '/content/tmp/update/manifest.json';

    /**
     * apply 时保留不覆盖的相对路径白名单。
     * 命中这些路径前缀的文件，即使升级包里带了也不会被写入。
     */
    private const PRESERVE_PATHS = [
        'content/uploads',     // 用户上传的图片/视频
        'content/cache',       // 运行时缓存
        'content/tmp',         // 升级流水线自己的临时目录（避免自覆盖）
        'tmp',                 // 项目根可能还有其它旧临时目录，一起保护
        '.env',                // 环境变量
        'config.php',          // DB 凭据
        '.claude',             // 开发工具配置
        '.idea',               // IDE 配置
        '.git',                // 版本控制
        'base.php',            // EMSHOP代理配置文件
    ];

    /**
     * 随包发布的 Web 服务器配置：相对路径 => 文件内的归属标识。
     *
     * 这两份必须能被升级覆盖 —— 否则伪静态规则以后有改动（新增回调路径、修 bug），
     * 已经装好的站点永远收不到，只能让站长手动去改，等于白发布。
     *
     * 但「覆盖」不能无条件做：站长很可能自己写了一份 .htaccess（SSL 强制跳转、
     * 防盗链、同目录其它程序的规则都堆在里面），覆盖等于直接抹掉人家的配置。
     *
     * 所以按归属判断：文件里带我们的标识 → 是我们放的，照常覆盖（覆盖前留一份 .emshop-bak）；
     * 不带 → 是人家的文件，一个字都不动。
     *
     * 只认根目录这一层；子目录里的配置文件不在此列，随功能走的文件照常覆盖。
     */
    private const MANAGED_SERVER_CONFIG = [
        '.htaccess'  => '@emshop-managed',
        'web.config' => '@emshop-managed',
    ];

    /** 归属判定的三种处置 */
    private const CONFIG_AS_USUAL   = 'usual';    // 不在清单里 → 按常规覆盖
    private const CONFIG_OVERWRITE  = 'overwrite';// 我们放的 → 覆盖，覆盖前备份
    private const CONFIG_LEAVE      = 'leave';    // 人家的文件 → 跳过

    /** 写权限抽查的关键目录（preflight 校验） */
    private const CHECK_WRITABLE = ['admin', 'include', 'install', 'user', 'content/static'];

    // ================================================================
    // Step 1: preflight — 预检
    // ================================================================

    /**
     * 预检升级环境是否就绪。
     *
     * @return array{ok:bool, errors:string[], warnings:string[], php_version:string, disk_free:int}
     */
    public function preflight(string $newVersion, string $minFromVersion = '', int $packageSize = 0): array
    {
        $errors = [];
        $warnings = [];

        // PHP 版本：升级 1.2+ 起要求 7.4，这里保守检查
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = '当前 PHP 版本 ' . PHP_VERSION . ' 过低，升级需要 PHP 7.4 及以上';
        }

        // 数据库版本：建表 DDL 依赖 DATETIME DEFAULT CURRENT_TIMESTAMP，MySQL 5.6.5 起才支持
        if (!$this->db->mysqlVersionOk()) {
            $errors[] = '数据库版本过低：需要 MySQL ' . $this->db->MIN_MYSQL_VERSION
                . ' 及以上（或 MariaDB），当前 ' . $this->db->serverVersion();
        }

        // 必需扩展
        foreach (['zip', 'curl'] as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = '缺少 PHP 扩展：' . $ext . '（升级功能依赖）';
            }
        }

        // 源版本兼容性：客户端当前版本必须 >= min_from_version
        if ($minFromVersion !== '' && defined('EM_VERSION')) {
            if (version_compare(EM_VERSION, $minFromVersion, '<')) {
                $errors[] = '当前版本 ' . EM_VERSION . ' 过低，无法直接升级到 ' . $newVersion
                    . '，需先手动升级到 ' . $minFromVersion . ' 或更高版本';
            }
        }

        // 写权限抽查
        $unwritable = [];
        foreach (self::CHECK_WRITABLE as $rel) {
            $path = EM_ROOT . '/' . $rel;
            if (!is_dir($path)) continue;
            if (!is_writable($path)) {
                $unwritable[] = $rel;
            }
        }
        // 根目录也要能写（替换根目录下的 init.php 等）
        if (!is_writable(EM_ROOT)) {
            $unwritable[] = '项目根目录';
        }
        if ($unwritable) {
            $errors[] = '网站目录权限不足，请将网站目录权限设置为755';
        }

        // 磁盘空间：至少 packageSize * 2 倍（下载 + 解压；解压后会删 zip）
        $required = max($packageSize * 2, 50 * 1024 * 1024); // 保底 50MB
        $diskFree = @disk_free_space(EM_ROOT) ?: 0;
        if ($diskFree > 0 && $diskFree < $required) {
            $errors[] = '磁盘剩余空间不足：需 ' . $this->formatBytes($required)
                . '，仅剩 ' . $this->formatBytes((int) $diskFree);
        }

        return [
            'ok'          => $errors === [],
            'errors'      => $errors,
            'warnings'    => $warnings,
            'php_version' => PHP_VERSION,
            'disk_free'   => (int) $diskFree,
        ];
    }

    // ================================================================
    // Step 2: download — 下载升级包
    // ================================================================

    /**
     * 下载升级包到 cache 目录；给了 SHA256 就比对，没给就跳过。
     *
     * 注意：base-data 接口（含 patch_package_url）不提供 SHA256，因此正常链路下这里
     * 白名单。若服务端将来补上校验值，传进来即可自动生效，不需要改调用方。
     *
     * @return array{ok:bool, path:string, size:int, sha256:string, error?:string}
     */
    public function download(string $packageUrl, string $expectedSha256 = ''): array
    {
        if ($packageUrl === '') {
            return ['ok' => false, 'path' => '', 'size' => 0, 'sha256' => '', 'error' => '升级包 URL 为空'];
        }

        // 服务端可能返相对路径（如 /content/uploads/xxx.zip），自动补上授权服务器域名；
        // 不加这一层 cURL 对相对路径会直接 HTTP 0 失败
        $packageUrl = $this->resolvePackageUrl($packageUrl);

        // package_url 来自客户端：只允许授权线路的主机，否则等于允许把任意 zip
        // 覆盖到站点根（RCE），以及用 http://127.0.0.1 之类地址探测内网（SSRF）。
        if (!$this->isTrustedPackageUrl($packageUrl)) {
            return [
                'ok' => false, 'path' => '', 'size' => 0, 'sha256' => '',
                'error' => '升级包地址非法（必须与授权服务器同一域名）',
            ];
        }

        // 有校验值才比对；空值 / 格式不合法一律按「没给」处理
        $expectedSha256 = strtolower(trim($expectedSha256));
        $hasSha256 = preg_match('/^[a-f0-9]{64}$/', $expectedSha256) === 1;

        

        $this->ensureDir(self::CACHE_DIR);

        $filename = 'package_' . date('YmdHis') . '_' . substr(md5($packageUrl), 0, 8) . '.zip';
        $localPath = EM_ROOT . self::CACHE_DIR . '/' . $filename;

        // 用 cURL 流式下载，避免大文件撑爆内存
        $fp = fopen($localPath, 'wb');
        if ($fp === false) {
            return ['ok' => false, 'path' => '', 'size' => 0, 'sha256' => '', 'error' => '无法创建本地文件：' . $localPath];
        }

        $ch = curl_init($packageUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            // 不跟随重定向：否则上面的 host 白名单可被授权主机的 302 绕过
            // （第一次请求确实去了白名单主机，最终内容却来自任意地址）
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 120,  // 120秒下载不下来就退出，正常网络足够了
            CURLOPT_CONNECTTIMEOUT => 10,  // 10秒建立连接时间
            CURLOPT_USERAGENT      => 'emshop-update/' . (defined('EM_VERSION') ? \EM_VERSION : 'dev'),
            // 证书与主机名校验默认开启；自签线路由 config 的 license_insecure_tls=1 显式降级
        ] + (class_exists('LicenseClient') ? $this->licenseClient->tlsOptions() : [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]));
        $ok = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $httpCode !== 200) {
            @unlink($localPath);
            return [
                'ok' => false, 'path' => '', 'size' => 0, 'sha256' => '',
                'error' => '下载失败：HTTP ' . $httpCode . ($curlErr ? '（' . $curlErr . '）' : ''),
            ];
        }

        $size = filesize($localPath);
        $sha256 = hash_file('sha256', $localPath);

        // SHA256 校验（有有效校验值才比对）
        if ($hasSha256 && !hash_equals($expectedSha256, $sha256)) {
            @unlink($localPath);
            return [
                'ok' => false, 'path' => '', 'size' => (int) $size, 'sha256' => $sha256,
                'error' => '升级包校验失败：SHA256 不匹配，包可能损坏',
            ];
        }

        $this->audit('update_download', [
            'host'   => (string) parse_url($packageUrl, PHP_URL_HOST),
            'size'   => (int) $size,
            'sha256' => $sha256,
        ]);

        return ['ok' => true, 'path' => $localPath, 'size' => (int) $size, 'sha256' => $sha256];
    }

    

    // ================================================================
    // Step 3: extract — 解压升级包
    // ================================================================

    /**
     * 解压升级包到 extract 目录。
     *
     * @return array{ok:bool, extract_path:string, files:int, error?:string}
     */
    public function extract(string $zipPath): array
    {
        // zip_path 来自客户端：只允许临时下载目录里的 .zip，防止拿服务器上的任意文件来解压
        $realZip = str_replace('\\', '/', (string) realpath($zipPath));
        $cacheRoot = str_replace('\\', '/', (string) realpath(EM_ROOT . self::CACHE_DIR));
        $cachePrefix = rtrim($cacheRoot, '/') . '/';
        if ($realZip === '' || $cacheRoot === ''
            || ($realZip !== $cacheRoot && strpos($realZip, $cachePrefix) !== 0)
            || strtolower(pathinfo($realZip, PATHINFO_EXTENSION)) !== 'zip'
        ) {
            return ['ok' => false, 'extract_path' => '', 'files' => 0, 'error' => '升级包路径非法'];
        }
        if (!is_file($zipPath)) {
            return ['ok' => false, 'extract_path' => '', 'files' => 0, 'error' => '升级包文件不存在'];
        }

        // 每次解压前清空目录，避免残留
        $this->removeDir(EM_ROOT . self::EXTRACT_DIR);
        $this->ensureDir(self::EXTRACT_DIR);

        $zip = new \ZipArchive();
        $open = $zip->open($zipPath);
        if ($open !== true) {
            return ['ok' => false, 'extract_path' => '', 'files' => 0, 'error' => '无法打开 zip 文件，错误码：' . $open];
        }

        $count = $zip->numFiles;
        $ok = $zip->extractTo(EM_ROOT . self::EXTRACT_DIR);
        $zip->close();

        if (!$ok) {
            $this->removeDir(EM_ROOT . self::EXTRACT_DIR);
            @unlink($zipPath);
            return ['ok' => false, 'extract_path' => '', 'files' => 0, 'error' => '解压失败，可能磁盘空间不足或权限不够'];
        }

        // 解压完成后删除 zip，不在服务器保留升级包
        @unlink($zipPath);

        $this->audit('update_extract', ['files' => $count]);

        return ['ok' => true, 'extract_path' => EM_ROOT . self::EXTRACT_DIR, 'files' => $count];
    }

    // ================================================================
    // Step 4: apply — 应用升级
    // ================================================================

    /**
     * 把解压目录里的文件逐个覆盖到项目根（保留白名单路径）。
     * 任一文件写入失败 → 删除 manifest 中记录的新增文件（不备份旧文件，无法自动还原覆盖项）。
     *
     * @return array{ok:bool, replaced:int, added:int, skipped:int, manifest_file:string, error?:string}
     */
    public function apply(string $extractPath): array
    {
        // extract_path 来自客户端：只允许固定的解压目录，否则等于允许把服务器上
        // 任意目录（/etc、其它站点、备份目录）复制到网站根目录。
        $realExtract = str_replace('\\', '/', (string) realpath($extractPath));
        $extractRoot = str_replace('\\', '/', (string) realpath(EM_ROOT . self::EXTRACT_DIR));
        $extractPrefix = rtrim($extractRoot, '/') . '/';
        if ($realExtract === '' || $extractRoot === ''
            || ($realExtract !== $extractRoot && strpos($realExtract, $extractPrefix) !== 0)
        ) {
            return ['ok' => false, 'replaced' => 0, 'added' => 0, 'skipped' => 0, 'manifest_file' => '', 'error' => '解压目录非法'];
        }
        if (!is_dir($extractPath)) {
            return ['ok' => false, 'replaced' => 0, 'added' => 0, 'skipped' => 0, 'manifest_file' => '', 'error' => '解压目录不存在'];
        }

        $srcRoot = $this->detectPackageRoot($extractPath);
        $manifest = ['applied' => [], 'added' => [], 'replaced' => [], 'started_at' => date('c')];

        $replaced = 0; $added = 0; $skipped = 0;

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcRoot, \FilesystemIterator::SKIP_DOTS)
        );

        try {
            foreach ($iter as $fileInfo) {
                if ($fileInfo->isDir()) continue;
                if ($fileInfo->isLink()) { $skipped++; continue; }
                $rel = ltrim(str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($srcRoot))), '/');
                if ($this->isPreserved($rel)) { $skipped++; continue; }

                $dst = EM_ROOT . '/' . $rel;

                // 服务器配置类文件按归属决定：不是我们放的就跳过（详见 MANAGED_SERVER_CONFIG）
                $disposition = $this->serverConfigDisposition($rel, $dst);
                if ($disposition === self::CONFIG_LEAVE) { $skipped++; continue; }
                if ($disposition === self::CONFIG_OVERWRITE) {
                    // 覆盖前留一份，万一是站长在我们这份基础上追加过自己的规则
                    @copy($dst, $dst . '.emshop-bak');
                }

                $isNew = !is_file($dst);

                if (!is_dir(dirname($dst))) {
                    if (!@mkdir(dirname($dst), 0755, true) && !is_dir(dirname($dst))) {
                        throw new \RuntimeException('无法创建目录：' . dirname($dst));
                    }
                }
                if (@copy($fileInfo->getPathname(), $dst) === false) {
                    throw new \RuntimeException('写入失败：' . $rel);
                }

                $manifest['applied'][] = $rel;
                if ($isNew) {
                    $added++;
                    $manifest['added'][] = $rel;
                } else {
                    $replaced++;
                    $manifest['replaced'][] = $rel;
                }
            }
        } catch (\Throwable $e) {
            $this->rollbackFromManifest($manifest);
            return [
                'ok' => false, 'replaced' => $replaced, 'added' => $added, 'skipped' => $skipped,
                'manifest_file' => '', 'error' => '应用失败已自动回滚：' . $e->getMessage(),
            ];
        }

        // 落 manifest 到磁盘，供后续 migrate 失败时回滚用
        $manifestPath = EM_ROOT . self::MANIFEST_FILE;
        @file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT));

        $this->audit('update_apply', [
            'replaced' => $replaced,
            'added'    => $added,
            'skipped'  => $skipped,
        ]);

        return [
            'ok' => true, 'replaced' => $replaced, 'added' => $added, 'skipped' => $skipped,
            'manifest_file' => $manifestPath,
        ];
    }

    // ================================================================
    // Step 5: migrate — 数据库迁移
    // ================================================================

    /**
     * 扫 install/migrations/ 与 em_migrations 表求差集，按文件名顺序执行。
     * 任一 SQL 失败立即停止后续。
     *
     * @return array{ok:bool, applied:string[], pending:string[], batch:int, error?:string}
     */
    public function migrate(): array
    {
        // 保证追踪表存在（本次升级首次引入追踪时，可能是 apply 阶段带来的新 .sql 创建）
        $this->ensureMigrationsTable();

        $dir = EM_ROOT . '/install/migrations';
        if (!is_dir($dir)) {
            return ['ok' => true, 'applied' => [], 'pending' => [], 'batch' => 0];
        }

        $allFiles = glob($dir . '/*.sql') ?: [];
        sort($allFiles, SORT_STRING);

        $prefix = $this->db->prefix();
        $done = $this->db->query('SELECT `filename` FROM `' . $prefix . 'migrations`');
        $doneSet = array_flip(array_column($done, 'filename'));

        $pending = [];
        foreach ($allFiles as $f) {
            $name = basename($f);
            if (!isset($doneSet[$name])) $pending[] = $f;
        }

        if ($pending === []) {
            return ['ok' => true, 'applied' => [], 'pending' => [], 'batch' => 0];
        }

        $batchRow = $this->db->fetchOne('SELECT COALESCE(MAX(`batch`),0)+1 AS `n` FROM `' . $prefix . 'migrations`');
        $batch = (int) ($batchRow['n'] ?? 1);

        $applied = [];
        foreach ($pending as $file) {
            $name = basename($file);
            $sql = (string) file_get_contents($file);
            // checksum 基于"原始文件内容"，与表前缀无关（换前缀不会导致重跑）
            $checksum = hash('sha256', $sql);
            // 把 __PREFIX__ 占位符替换为当前数据库实际前缀
            // 约定：迁移 SQL 里应写 `__PREFIX__user` 而不是硬编码 `em_user`
            $sql = str_replace('__PREFIX__', $prefix, $sql);

            try {
                foreach ($this->splitSqlStatements($sql) as $stmt) {
                    if (trim($stmt) === '') continue;
                    $this->db->statement($stmt);
                }
                $this->db->insert('migrations', [
                    'filename' => $name,
                    'batch'    => $batch,
                    'checksum' => $checksum,
                ]);
                $applied[] = $name;
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'applied' => $applied,
                    'pending' => array_map('basename', array_slice($pending, count($applied))),
                    'batch'   => $batch,
                    'error'   => '迁移失败：' . $name . ' —— ' . $e->getMessage(),
                ];
            }
        }

        return ['ok' => true, 'applied' => $applied, 'pending' => [], 'batch' => $batch];
    }

    /**
     * 新装站点：把 install/migrations 里当前随包自带的 SQL 记入 em_migrations（不执行 SQL）。
     * 安装程序建表已包含同等结构，避免用户首次在线升级重复跑迁移报错。
     */
    public function markBundledMigrationsApplied(): void
    {
        $dir = EM_ROOT . '/install/migrations';
        if (!is_dir($dir)) {
            return;
        }

        $this->ensureMigrationsTable();

        $done = $this->db->query('SELECT `filename` FROM `' . $this->db->prefix() . 'migrations`');
        $doneSet = array_flip(array_column($done, 'filename'));

        $files = glob($dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($doneSet[$name])) {
                continue;
            }
            $raw = (string) file_get_contents($file);
            $this->db->insert('migrations', [
                'filename' => $name,
                'batch'    => 0,
                'checksum' => hash('sha256', $raw),
            ]);
        }
    }

    // ================================================================
    // Step 6: run_script — 执行升级包自带的 update.php
    // ================================================================

    /**
     * 升级包根目录若带 update.php，在文件覆盖与数据库迁移完成后执行一次，随即删除。
     *
     * 该步骤在新文件已经落盘后的独立请求里执行，脚本拿到的是升级后的代码与表结构，
     * 可以直接使用核心类 / Database；但它在本方法的局部作用域里被 include：
     *   - 不能再写 `$this`，也不需要自己 require init.php（核心已初始化）
     *   - echo 输出会被捕获并回传给前端日志，不会破坏 JSON 响应
     *   - 脚本内 exit / 致命错误不会阻止删除（register_shutdown_function 兜底），
     *     避免在网站根目录留下一个可被公网直接执行的入口
     *
     * @return array{ok:bool, ran:bool, deleted:bool, output:string, error?:string}
     */
    public function runPackageScript(string $extractPath): array
    {
        // extract_path 来自客户端，和 apply 一样只允许固定的解压目录
        $realExtract = str_replace('\\', '/', (string) realpath($extractPath));
        $extractRoot = str_replace('\\', '/', (string) realpath(EM_ROOT . self::EXTRACT_DIR));
        $extractPrefix = rtrim($extractRoot, '/') . '/';
        if ($realExtract === '' || $extractRoot === ''
            || ($realExtract !== $extractRoot && strpos($realExtract, $extractPrefix) !== 0)
        ) {
            return ['ok' => false, 'ran' => false, 'deleted' => false, 'output' => '', 'error' => '解压目录非法'];
        }

        // 是否带脚本看的是「升级包里有没有」，不是站点根目录有没有遗留文件
        $srcRoot = $this->detectPackageRoot($extractPath);
        if (!is_file($srcRoot . '/update.php')) {
            return ['ok' => true, 'ran' => false, 'deleted' => true, 'output' => ''];
        }

        $script = EM_ROOT . '/update.php';
        if (!is_file($script) || !is_readable($script)) {
            return [
                'ok' => false, 'ran' => false, 'deleted' => false, 'output' => '',
                'error' => '升级包带有 update.php，但文件未被成功应用或不可读',
            ];
        }

        $delete = function () use ($script): void {
            if (is_file($script)) {
                @unlink($script);
            }
        };
        // 脚本里 exit / 触发致命错误时 finally 不执行，这里兜底
        register_shutdown_function($delete);

        // 用独立闭包执行：脚本里的局部变量（$output / $error / $script 等）
        // 不会覆盖本方法的同名变量，把状态维护和脚本作用域隔开
        $runner = function (string $__packageScript): void {
            include $__packageScript;
        };

        $level  = ob_get_level();
        $output = '';
        $error  = '';
        ob_start();
        try {
            $runner($script);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        } finally {
            if (ob_get_level() > $level) {
                $output = (string) ob_get_clean();
            }
            // 脚本自己开的输出缓冲一并清掉，避免把 JSON 响应挤坏
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            $delete();
        }

        // 输出/异常信息必须能 json_encode（脚本可能是 GBK 编码或 echo 了二进制）
        $output = $this->normalizeScriptOutput($output);
        if ($error !== '') {
            $error = $this->normalizeScriptOutput($error);
        }

        $deleted = !is_file($script);

        $this->audit($error === '' ? 'update_script' : 'update_script_fail', array_filter([
            'error'         => $error,
            'output_length' => strlen($output),
        ], function ($v) { return $v !== '' && $v !== 0; }));

        if ($error !== '') {
            return [
                'ok' => false, 'ran' => true, 'deleted' => $deleted, 'output' => $output,
                'error' => '更新脚本执行失败：' . $error,
            ];
        }

        return ['ok' => true, 'ran' => true, 'deleted' => $deleted, 'output' => $output];
    }

    /**
     * 把脚本输出整理成可安全 json_encode 的文本：按 4000 字符截断 + 保证合法 UTF-8。
     *
     * 脚本文件本身可能是 GBK 编码，或脚本里 var_dump 了二进制内容；一旦输出非法
     * UTF-8，Response::json 的 json_encode 会返回 false，整个 AJAX 响应变成空串，
     * 前端只报解析错误，而脚本其实已经执行成功并被删除了 —— 很容易误判成升级失败。
     */
    private function normalizeScriptOutput(string $output): string
    {
        if ($output === '') {
            return '';
        }

        if (strlen($output) > 4000) {
            $output = (function_exists('mb_substr') ? \mb_substr($output, 0, 4000) : \substr($output, 0, 4000))
                . "\n…（输出过长已截断）";
        }

        if (@preg_match('//u', $output) === 1) {
            return $output;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = (string) mb_convert_encoding($output, 'UTF-8', 'UTF-8');
            if (@preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }
        if (function_exists('iconv')) {
            $converted = (string) @iconv('UTF-8', 'UTF-8//IGNORE', $output);
            if (@preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        // 兜底：mbstring / iconv 都不可用时只保留 ASCII 可打印字符与换行
        return (string) preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $output);
    }

    // ================================================================
    // Step 7: finalize — 收尾
    // ================================================================

    /**
     * 收尾：清理临时文件，记录 last_update_at。
     */
    public function finalize(): array
    {
        $this->removeDir(EM_ROOT . self::EXTRACT_DIR);
        $this->cleanDir(EM_ROOT . self::CACHE_DIR);
        // 清理旧版升级遗留的备份目录（当前版本已不再创建备份）
        $legacyBackup = EM_ROOT . '/content/tmp/update/backup';
        if (is_dir($legacyBackup)) {
            $this->removeDir($legacyBackup);
        }

        @unlink(EM_ROOT . self::MANIFEST_FILE);

        // 记录一下最后升级时间
        if (class_exists('Config')) {
            $this->config->set('last_update_at', (string) time());
        }

        // 上报更新成功（官方站版本管理的「更新数」+1）。
        // finalize 是独立的一次请求，此时新文件已经覆盖完，init.php 里的
        // EM_VERSION 就是这次升级到的目标版本（不是升级前的旧版本）。
        // 尽力而为：网络 / 接口异常都不影响升级结果，方法内部已吞掉异常。
        $reported = false;
        if (defined('EM_VERSION') && class_exists('LicenseClient')) {
            try {
                $reported = $this->licenseClient->reportUpdateSuccess((string) EM_VERSION);
            } catch (\Throwable $e) {
                $reported = false;
            }
        }

        return ['ok' => true, 'update_reported' => $reported];
    }

    // ================================================================
    // 回滚（由前端在 migrate 失败时调用）
    // ================================================================

    /**
     * 从 manifest 还原文件。
     *
     * @return array{ok:bool, restored_files:int, error?:string}
     */
    public function rollback(): array
    {
        $manifestPath = EM_ROOT . self::MANIFEST_FILE;
        $restored = 0;
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
            $restored = $this->rollbackFromManifest($manifest);
        }

        return ['ok' => true, 'restored_files' => $restored];
    }

    // ================================================================
    // 内部工具
    // ================================================================

    /**
     * 根据 manifest 回滚：仅删除本次新增的文件（不还原已覆盖文件）。
     *
     * @param array{applied?:string[], added?:string[], replaced?:string[]} $manifest
     */
    private function rollbackFromManifest(array $manifest): int
    {
        $count = 0;
        foreach ($manifest['added'] ?? [] as $rel) {
            @unlink(EM_ROOT . '/' . $rel);
            $count++;
        }
        return $count;
    }

    /**
     * 保证 em_migrations 表存在（首次进入升级流程时走这里）。
     */
    private function ensureMigrationsTable(): void
    {
        $table = $this->db->prefix() . 'migrations';

        // 防御性兜底：MySQL < 5.6.5 不支持 DATETIME DEFAULT CURRENT_TIMESTAMP。
        // 正常流程里 install/upgrade 的版本守卫已先拦截，这里仅兜底。本表只有一个
        // applied_at 自增列，换成 TIMESTAMP 在 5.5 下也安全。
        $appliedAtType = $this->db->mysqlVersionOk() ? 'DATETIME' : 'TIMESTAMP';

        $this->db->statement(
            'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `filename`   VARCHAR(255) NOT NULL,
                `batch`      INT UNSIGNED NOT NULL DEFAULT 0,
                `applied_at` ' . $appliedAtType . ' NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `checksum`   CHAR(64)     NOT NULL DEFAULT \'\',
                PRIMARY KEY (`id`),
                KEY `idx_batch` (`batch`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'数据库迁移追踪表\''
        );
    }

    /**
     * 规范化升级包 URL：相对路径自动补授权服务器域名。
     *
     * 服务端返的 URL 有两种常见形态：
     *   1. 完整 URL（http:// 或 https:// 开头）—— 直接用
     *   2. 相对路径（/api/versions/em/3/download、/content/uploads/...）—— 前面补当前授权服务器域名
     * 没这一层，cURL 对相对路径直接 HTTP 0 失败。
     *
     * public 是给 app(\App\Service\LicenseService::class)->fetchBaseData() 用的（base-data 的 patch_package_url 就是相对路径）。
     */
    public function resolvePackageUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return $url;

        // 完整 URL（含协议相对 //xxx）不动
        if (preg_match('#^(https?:)?//#i', $url)) return $url;

        // 相对路径：补当前授权服务器的域名做前缀
        if (!class_exists('LicenseClient')) return $url;
        $base = rtrim($this->licenseClient->currentBaseUrl(), '/');
        if ($base === '') return $url;

        return $base . '/' . ltrim($url, '/');
    }

    /**
     * 升级包地址是否属于授权线路主机。
     */
    private function isTrustedPackageUrl(string $url): bool
    {
        if (!class_exists('DownloadUrlGuard') || !class_exists('LicenseClient')) {
            return false;
        }
        foreach ($this->licenseClient->lines() as $line) {
            $base = rtrim((string) ($line['url'] ?? ''), '/');
            if ($base !== '' && \DownloadUrlGuard::isAllowed($url, $base, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 解压目录可能是 emshop-v1.2.0/ 这样套一层的，也可能是直接铺平的。
     * 看解压根下有没有 init.php 或 app/ 来判断，找不到就返回原目录。
     */
    private function detectPackageRoot(string $extractPath): string
    {
        if (is_file($extractPath . '/init.php') || is_dir($extractPath . '/app')) {
            return rtrim($extractPath, '/');
        }

        foreach (glob($extractPath . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
            if (is_file($sub . '/init.php') || is_dir($sub . '/app')) {
                return rtrim($sub, '/');
            }
        }
        return rtrim($extractPath, '/');
    }

    /**
     * 相对路径是否命中保留白名单（升级时不动这些路径）。
     */
    private function isPreserved(string $rel): bool
    {
        $rel = str_replace('\\', '/', $rel);
        foreach (self::PRESERVE_PATHS as $p) {
            if ($rel === $p || strpos($rel, $p . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 服务器配置文件的覆盖处置（见 MANAGED_SERVER_CONFIG）。
     *
     * 只读文件开头一段做归属判定就够了，标识本来就在最前面的注释里；
     * 顺带避免把一个几 MB 的文件整个读进来。
     */
    private function serverConfigDisposition(string $rel, string $dst): string
    {
        $rel = str_replace('\\', '/', $rel);
        $marker = self::MANAGED_SERVER_CONFIG[$rel] ?? null;
        if ($marker === null) {
            return self::CONFIG_AS_USUAL;
        }
        // 还没有这份文件 → 直接放，不存在覆盖谁的问题
        if (!is_file($dst)) {
            return self::CONFIG_AS_USUAL;
        }
        $head = (string) @file_get_contents($dst, false, null, 0, 1024);
        return strpos($head, $marker) !== false ? self::CONFIG_OVERWRITE : self::CONFIG_LEAVE;
    }

    /**
     * 拆分 SQL 语句：按 `;` 切，但尊重字符串内的分号和转义。
     * 去掉 `/* *\/` 块注释和 `--` 单行注释。
     * 不支持存储过程里的 DELIMITER 语法。
     *
     * @return string[]
     */
    private function splitSqlStatements(string $sql): array
    {
        // 先去块注释 / 单行注释，避免正文里的分号被误拆
        $sql = preg_replace('#/\*.*?\*/#s', '', (string) $sql);
        $sql = preg_replace('/^\s*--[^\n]*$/m', '', (string) $sql);

        $out = [];
        $cur = '';
        $inStr = null;              // 当前处于哪种引号里：null / "'" / '"'
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($inStr !== null) {
                $cur .= $c;
                // 反斜杠转义：下一个字符整体跟进（处理 \n \' \" 等）
                if ($c === '\\' && $i + 1 < $len) {
                    $cur .= $sql[++$i];
                    continue;
                }
                // SQL 单引号转义风格 '' 也要跳过（MySQL dump 用这种）
                if ($c === $inStr && $i + 1 < $len && $sql[$i + 1] === $inStr) {
                    $cur .= $sql[++$i];
                    continue;
                }
                if ($c === $inStr) $inStr = null;
                continue;
            }
            if ($c === "'" || $c === '"') { $inStr = $c; $cur .= $c; continue; }
            if ($c === ';') {
                $t = trim($cur);
                if ($t !== '') $out[] = $t;
                $cur = '';
                continue;
            }
            $cur .= $c;
        }
        $t = trim($cur);
        if ($t !== '') $out[] = $t;
        return $out;
    }

    // ---------- 目录/文件工具 ----------

    private function ensureDir(string $relPath): void
    {
        $abs = EM_ROOT . $relPath;
        if (!is_dir($abs)) {
            @mkdir($abs, 0755, true);
        }
    }

    private function removeDir(string $abs): void
    {
        if (!is_dir($abs)) return;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var SplFileInfo $f */
        foreach ($iter as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($abs);
    }

    private function cleanDir(string $abs): void
    {
        if (!is_dir($abs)) return;
        foreach (glob($abs . '/*') ?: [] as $f) {
            if (is_dir($f)) $this->removeDir($f);
            else @unlink($f);
        }
    }

    /**
     * 升级操作审计：记录操作人、动作与关键参数。写日志失败不阻断升级。
     */
    private function audit(string $action, array $detail): void
    {
        try {
            $admin = $_SESSION['em_admin_auth'] ?? null;
            $operator = is_array($admin) ? $admin : [];
            (new \App\Model\SystemLogModel())->info(
                'admin_operation',
                $action,
                '在线升级：' . $action,
                $detail,
                (int) ($operator['id'] ?? 0),
                (string) ($operator['username'] ?? '')
            );
        } catch (\Throwable $e) {
            // 审计失败不阻断升级
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $val = $bytes;
        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }
        return round($val, 2) . ' ' . $units[$i];
    }
}
