<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 旧后台目录（/admin）下线的延迟清理。
 *
 * 升级到新后台版本后，UpdateService::finalize() 会写一个待删除标记；
 * 之后任意一次 Web 请求（不要求登录）触发本类，把 admin/ 整目录删掉。
 *
 * 删除必须在"升级已经完成、且请求不在旧后台目录内执行"时进行：
 *   - 升级中（manifest / 解压目录还在）跳过，避免删到正在写入的站点
 *   - 从 admin/ 内部发起的请求跳过，避免删掉正在执行的代码
 *   - 只用 realpath 确认目标就是站点根下的 admin，拒绝符号链接
 *   - flock 串行化，多请求同时触发只删一次
 *   - 失败保留标记，下次请求重试
 *
 * 删除完成后写审计日志；审计失败不影响删除结果。
 */
final class LegacyAdminRemoval
{
    /** 待删除标记（相对站点根） */
    public const MARKER_FILE = '/content/tmp/admin_legacy_removal.pending';

    /** 互斥锁（相对站点根） */
    private const LOCK_FILE = '/content/tmp/admin_legacy_removal.lock';

    /** 升级进行中的标志文件 / 目录（相对站点根） */
    private const UPDATE_MANIFEST = '/content/tmp/update/manifest.json';
    private const UPDATE_EXTRACT  = '/content/tmp/update/extract';

    /**
     * 写待删除标记。由 UpdateService::finalize() 在升级收尾时调用。
     *
     * @return bool 标记是否落盘成功
     */
    public static function schedule(): bool
    {
        $dir = dirname(EM_ROOT . self::MARKER_FILE);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $payload = json_encode([
            'version' => defined('EM_VERSION') ? (string) EM_VERSION : '',
            'time'    => time(),
        ], JSON_UNESCAPED_UNICODE);

        return @file_put_contents(EM_ROOT . self::MARKER_FILE, (string) $payload, LOCK_EX) !== false;
    }

    /**
     * 当前请求是否应执行清理（init.php 每次请求调用一次，开销只有一次 is_file）。
     */
    public static function maybeRun(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (!is_file(EM_ROOT . self::MARKER_FILE)) {
            return;
        }

        self::run(EM_ROOT, $_SERVER);
    }

    /**
     * 执行一次清理尝试。
     *
     * @param string               $root   站点根目录
     * @param array<string, mixed> $server 当前请求的 $_SERVER（用于判断请求是否来自 admin/）
     * @return string 结果状态：skipped / removed / retry / not_found
     */
    public static function run(string $root, array $server): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $marker = $root . self::MARKER_FILE;
        if (!is_file($marker)) {
            return 'skipped';
        }

        $adminPath = $root . '/admin';
        if (is_link($adminPath)) {
            // 绝不跟随符号链接：目标可能是任意位置
            self::audit('fail', ['reason' => 'admin_is_symlink']);
            return 'retry';
        }

        $adminReal = realpath($adminPath);
        if ($adminReal === false || !is_dir($adminReal)) {
            // 已经不存在：清掉标记即可
            @unlink($marker);
            return 'not_found';
        }
        $adminReal = rtrim(str_replace('\\', '/', $adminReal), '/');
        if ($adminReal !== $root . '/admin') {
            // 只允许删除站点根下这一个确切目录
            self::audit('fail', ['reason' => 'unexpected_path', 'path' => $adminReal]);
            return 'retry';
        }

        // 请求本身来自旧后台目录：跳过本次，别删正在跑的代码
        $script = isset($server['SCRIPT_FILENAME']) ? realpath((string) $server['SCRIPT_FILENAME']) : false;
        if ($script !== false) {
            $script = str_replace('\\', '/', (string) $script);
            if ($script === $adminReal || strpos($script, $adminReal . '/') === 0) {
                return 'skipped';
            }
        }

        // 升级进行中：等下一次请求再删
        if (is_file($root . self::UPDATE_MANIFEST) || is_dir($root . self::UPDATE_EXTRACT)) {
            return 'skipped';
        }

        $lockDir = dirname($root . self::LOCK_FILE);
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
            self::audit('fail', ['reason' => 'lock_dir_unwritable']);
            return 'retry';
        }

        $fp = @fopen($root . self::LOCK_FILE, 'c');
        if ($fp === false) {
            self::audit('fail', ['reason' => 'lock_open_failed']);
            return 'retry';
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return 'skipped';
        }

        try {
            // 加锁后再确认一次，避免等待期间别的请求已经删掉
            if (!is_dir($adminReal) || is_link($adminPath)) {
                @unlink($marker);
                return 'not_found';
            }

            $ok = self::removeTree($adminReal);
            if (!$ok || is_dir($adminReal)) {
                self::audit('fail', ['reason' => 'remove_incomplete', 'path' => $adminReal]);
                return 'retry';
            }

            @unlink($marker);
            self::audit('success', ['path' => $adminReal]);
            return 'removed';
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * 递归删除目录；不跟随符号链接（链接本身按文件删除）。
     */
    private static function removeTree(string $path): bool
    {
        if (is_link($path)) {
            return @unlink($path);
        }
        if (!is_dir($path)) {
            return @unlink($path);
        }

        $items = @scandir($path);
        if ($items === false) {
            return false;
        }

        $ok = true;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $child = $path . '/' . $item;
            if (!self::removeTree($child)) {
                $ok = false;
            }
        }

        return $ok && @rmdir($path);
    }

    /**
     * 审计：写入系统日志，失败不抛出。
     *
     * @param array<string, mixed> $detail
     */
    private static function audit(string $result, array $detail): void
    {
        try {
            (new \SystemLogModel())->info(
                'admin_operation',
                'admin_legacy_removal',
                '旧后台目录清理：' . $result,
                ['result' => $result] + $detail,
                0,
                'system'
            );
        } catch (\Throwable $e) {
            // 审计失败不阻断清理
        }
    }
}
