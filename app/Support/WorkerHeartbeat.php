<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 后台任务心跳服务（实例实现）。
 *
 * 有状态：进程内缓存运行目录。兼容门面见 app/Facade/WorkerHeartbeat.php。
 *
 * 两层心跳语义不同，**不要混用**：
 *
 *   - 宿主心跳 `server.heartbeat`：由管家进程（CliServerManager）每轮循环刷新，
 *     表示「php server 这个进程还活着」。后台首页用它显示服务总开关状态。
 *
 *   - 能力心跳 `worker.{type}.heartbeat`：由每个 worker 跑完一轮任务后刷新，
 *     表示「这一类任务真的有人在消费」。
 *
 * 为什么必须分层：管家活着 ≠ 发货队列在消费。worker 卡死、被 OOM kill、
 * 或者守护进程插件根本没注册这个 worker —— 宿主心跳照样是新鲜的。
 * 所以「发货走同步还是异步」这类判定只能问能力心跳。
 *
 * 实现上用 mtime 而不是文件内容：进程被 kill 后文件仍然存在，
 * 只看 file_exists 必然假阳性；只有 mtime + TTL 才反映「最近还在干活」。
 */
final class WorkerHeartbeat
{
    /**
     * 「有发货消费者在线」这个**能力**的心跳。
     *
     * ⚠️ 注意这是**能力**不是 worker 类型名。拆 worker 之后，发货 worker 的类型
     * 是各插件自己起的（`deliver_virtual_card` / `deliver_physical` …），
     * 核心不该、也无法硬编码某一个类型名去判断"有没有人在发货"。
     *
     * 所有发货 worker（都继承 DeliveryTaskWorker）跑完一轮都会刷这个心跳，
     * 所以 OrderModel::triggerDelivery() 只要看它就能决定走异步还是同步。
     */
    public const CAPABILITY_DELIVERY = 'delivery';

    /** @deprecated 拆 worker 之前的老类型名，仅兼容期保留，判"有没有人在发货"请用 CAPABILITY_DELIVERY */
    public const WORKER_QUEUE = 'queue';
    /** @deprecated order_poll worker 已下线 */
    public const WORKER_ORDER_POLL = 'order_poll';
    /** @deprecated goods_sync worker 已下线 */
    public const WORKER_GOODS_SYNC = 'goods_sync';

    /**
     * 心跳新鲜度阈值（秒）。
     *
     * 现有 worker 的心跳周期都在 6 秒内（queue 每秒醒一次），15 秒足够容错，
     * 又不至于让一个刚崩掉的 worker 长时间被误判为存活。
     *
     * 注意：这里必须是 15。此前写成 5，而心跳周期最长 6 秒 —— 也就是两次心跳之间
     * worker 会被判定为「已死」。后果是支付回调时系统误认为「发货队列没有消费者」
     * 而回退到同步发货（失败也不重试），以及管家反复重启其实健康的 worker。
     */
    public const TTL = 15;

    /** @var string|null 运行目录缓存 */
    private $runtimeDir;

    /**
     * 运行目录：与 CliServer 共用 content/server，心跳文件和日志/PID 放一起。
     */
    public function runtimeDir(): string
    {
        if ($this->runtimeDir === null) {
            $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
            $this->runtimeDir = rtrim($root, '/\\') . '/content/server';
        }
        return $this->runtimeDir;
    }

    /** 创建运行目录（写心跳前调用） */
    private function ensureDir(): void
    {
        $dir = $this->runtimeDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    // ---------------------------------------------------------------------
    // 宿主心跳
    // ---------------------------------------------------------------------

    public function hostFile(): string
    {
        return $this->runtimeDir() . '/server.heartbeat';
    }

    /** 由管家进程调用，标记「任务服务主进程存活」 */
    public function touchHost(): void
    {
        $this->ensureDir();
        @touch($this->hostFile());
    }

    /** 宿主进程是否在跑 */
    public function hostAlive(int $ttl = self::TTL): bool
    {
        return $this->fileFresh($this->hostFile(), $ttl);
    }

    /** 宿主心跳距今秒数；文件不存在返回 null */
    public function hostAge(): ?int
    {
        return $this->fileAge($this->hostFile());
    }

    // ---------------------------------------------------------------------
    // 能力心跳
    // ---------------------------------------------------------------------

    /** worker 心跳文件路径。type 会做白名单过滤，防止拼出目录穿越 */
    public function workerFile(string $type): string
    {
        return $this->runtimeDir() . '/worker.' . $this->safeType($type) . '.heartbeat';
    }

    /**
     * 由 worker 自己调用：一轮任务跑完后刷新自己的心跳。
     *
     * 必须在 tick() **正常返回之后**调用。放在 tick 之前的话，
     * tick 卡死时心跳依然新鲜，能力心跳就失去了「这活有人在干」的意义。
     */
    public function touchWorker(string $type): void
    {
        $this->ensureDir();
        @touch($this->workerFile($type));
    }

    /** 指定类型的 worker 最近是否还在干活 */
    public function isAlive(string $type, int $ttl = self::TTL): bool
    {
        return $this->fileFresh($this->workerFile($type), $ttl);
    }

    /** 指定类型 worker 的心跳距今秒数；从未跑过返回 null */
    public function age(string $type): ?int
    {
        return $this->fileAge($this->workerFile($type));
    }

    /**
     * 批量查询各 worker 的存活情况。
     *
     * @param list<string> $types
     * @return array<string, array{alive: bool, age: int|null}>
     */
    public function status(array $types): array
    {
        $out = [];
        foreach ($types as $type) {
            $age = $this->age($type);
            $out[$type] = [
                'alive' => $age !== null && $age <= self::TTL,
                'age'   => $age,
            ];
        }
        return $out;
    }

    /** 清掉所有能力心跳（停服时调用，避免下次启动前被误判为存活） */
    public function clearAll(array $types = []): void
    {
        foreach ($types as $type) {
            $file = $this->workerFile($type);
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    // ---------------------------------------------------------------------
    // 内部
    // ---------------------------------------------------------------------

    /** type 只允许字母数字下划线，避免拼出 ../ */
    private function safeType(string $type): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_]/', '', $type);
        return $safe === '' ? 'unknown' : $safe;
    }

    private function fileAge(string $file): ?int
    {
        if (!is_file($file)) {
            return null;
        }
        $mtime = @filemtime($file);
        if ($mtime === false) {
            return null;
        }
        return max(0, time() - $mtime);
    }

    private function fileFresh(string $file, int $ttl): bool
    {
        $age = $this->fileAge($file);
        return $age !== null && $age <= max(1, $ttl);
    }
}
