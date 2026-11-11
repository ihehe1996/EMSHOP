<?php

/**
 * 升级包专用脚本：把老格式 config.php（return 数组）迁移为常量定义格式。
 *
 * 由升级向导在「文件覆盖 + 数据库迁移」之后自动 include 执行一次，执行后删除本文件。
 * 直接通过 URL 访问时（EM_ROOT 未定义）返回 404，不做任何事。
 *
 * 注意：本文件运行在已经加载 init.php 的环境里，不要再 require init.php，
 * 也不要用 $this / exit。
 */

if (!defined('EM_ROOT')) {
    http_response_code(404);
    exit;
}

/**
 * 迁移 config.php。
 *
 * @return array{changed:bool, message:string, backup:string, ignored:string[]}
 */
function em_update_config_php(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('config.php 不存在或不可读');
    }

    $source = (string) file_get_contents($path);

    // 已经是常量格式：幂等跳过
    if (preg_match('/\bdefine\s*\(\s*[\'"]EM_DB_HOST[\'"]/', $source)) {
        return ['changed' => false, 'message' => '已是常量格式，无需迁移', 'backup' => '', 'ignored' => []];
    }

    // 读取老数组。老格式不会定义常量，所以这里 include 是安全的。
    try {
        $old = @include $path;
    } catch (Throwable $e) {
        throw new RuntimeException('读取旧配置失败：' . $e->getMessage());
    }
    if (!is_array($old)) {
        throw new RuntimeException('config.php 不是可识别的老格式（没有返回数组）');
    }

    $db   = is_array($old['db'] ?? null) ? $old['db'] : [];
    $auth = is_array($old['auth'] ?? null) ? $old['auth'] : [];
    $app  = is_array($old['app'] ?? null) ? $old['app'] : [];

    // 老站可能没有 secret（由 CsrfSecret 历史补写过，也可能从未补过）
    $secret = (string) ($auth['secret'] ?? ($app['secret'] ?? ''));
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
    }

    $values = [
        'EM_APP_NAME'        => (string) (($app['name'] ?? '') ?: 'EMSHOP'),
        'EM_APP_ADMIN_ENTRY' => (string) (($app['admin_entry'] ?? '') ?: 'admin'),

        'EM_DB_HOST'     => (string) ($db['host'] ?? '127.0.0.1'),
        'EM_DB_PORT'     => (int) ($db['port'] ?? 3306),
        'EM_DB_NAME'     => (string) ($db['dbname'] ?? ''),
        'EM_DB_USER'     => (string) ($db['username'] ?? ''),
        'EM_DB_PASSWORD' => (string) ($db['password'] ?? ''),
        'EM_DB_CHARSET'  => (string) (($db['charset'] ?? '') ?: 'utf8mb4'),
        'EM_DB_PREFIX'   => (string) (($db['prefix'] ?? '') ?: 'em_'),

        'EM_AUTH_SESSION_KEY'           => (string) ($auth['session_key'] ?? 'em_admin_auth'),
        'EM_AUTH_SECRET'                => $secret,
        'EM_AUTH_REMEMBER_COOKIE'       => (string) ($auth['remember_cookie'] ?? 'em_admin_remember'),
        'EM_AUTH_REMEMBER_DAYS_DEFAULT' => (int) ($auth['remember_days_default'] ?? 7),
        'EM_AUTH_REMEMBER_DAYS_CHECKED' => (int) ($auth['remember_days_checked'] ?? 365),
        'EM_AUTH_CSRF_KEY'              => (string) ($auth['csrf_key'] ?? 'em_admin_csrf'),

        'EM_AVATAR'          => (string) (($old['avatar'] ?? '') ?: '/content/static/img/default-admin-avatar.jpg'),
        'EM_PLACEHOLDER_IMG' => (string) (($old['placeholder_img'] ?? '') ?: '/content/static/img/img-1.png'),
    ];

    // 旧版已废弃 / 用户自定义的配置项：核心不再读取，列出来让人知道没有搬走
    $known = ['db', 'auth', 'app', 'avatar', 'placeholder_img'];
    $ignored = array_values(array_diff(array_keys($old), $known));

    $newSource = em_update_render_config($values);

    try {
        token_get_all($newSource, TOKEN_PARSE);
    } catch (Throwable $e) {
        throw new RuntimeException('生成的 config.php 语法校验失败：' . $e->getMessage());
    }

    // 备份放系统临时目录：不能放网站目录，否则含数据库密码的备份可被直接下载
    $backup = em_update_backup_config($source);

    em_update_replace_file($path, $newSource);

    return [
        'changed' => true,
        'message' => '已从数组格式转换为常量格式',
        'backup'  => $backup,
        'ignored' => array_map('strval', $ignored),
    ];
}

/**
 * @param array<string, string|int> $values
 */
function em_update_render_config(array $values): string
{
    $directAccessGuard = <<<'PHP'
if (!defined('EM_ROOT')) {
    http_response_code(404);
    exit;
}
PHP;

    $export = static function ($value): string {
        return var_export($value, true);
    };

    $lines = [
        '<?php',
        '',
        $directAccessGuard,
        '',
        '// CLI 启动探测会先 include 一次 config.php，init.php 随后还会再 require 一次；',
        '// 新格式重复 include 会触发 "Constant already defined"，这里直接返回。',
        "if (defined('EM_CONFIG_FILE_LOADED')) {",
        '    return;',
        '}',
        "define('EM_CONFIG_FILE_LOADED', true);",
        '',
        '// 配置值优先取环境变量；开发时可在项目根目录 .env 覆盖（见 .env.example）',
        '',
        '// 应用配置（当前版本仅预留，功能后续接入）',
        "define('EM_APP_NAME', Env::get('EM_APP_NAME', " . $export($values['EM_APP_NAME']) . '));   // 应用名',
        "define('EM_APP_ADMIN_ENTRY', Env::get('EM_APP_ADMIN_ENTRY', " . $export($values['EM_APP_ADMIN_ENTRY']) . '));  // 后台安全入口 key，空 = 不限制',
    ];

    $lines[] = '';
    $lines[] = '// 数据库连接';
    foreach (['EM_DB_HOST', 'EM_DB_PORT', 'EM_DB_NAME', 'EM_DB_USER', 'EM_DB_PASSWORD', 'EM_DB_CHARSET', 'EM_DB_PREFIX'] as $name) {
        $lines[] = "define('" . $name . "', Env::get('" . $name . "', " . $export($values[$name]) . '));';
    }

    $lines[] = '';
    $lines[] = '// 后台认证';
    foreach (['EM_AUTH_SESSION_KEY', 'EM_AUTH_SECRET', 'EM_AUTH_REMEMBER_COOKIE', 'EM_AUTH_REMEMBER_DAYS_DEFAULT', 'EM_AUTH_REMEMBER_DAYS_CHECKED', 'EM_AUTH_CSRF_KEY'] as $name) {
        $lines[] = "define('" . $name . "', " . $export($values[$name]) . ');';
    }

    $lines[] = '';
    $lines[] = '// 默认图片';
    foreach (['EM_AVATAR', 'EM_PLACEHOLDER_IMG'] as $name) {
        $lines[] = "define('" . $name . "', " . $export($values[$name]) . ');';
    }

    return implode("\n", $lines) . "\n";
}

/**
 * 把旧配置备份到系统临时目录（不落网站目录）。
 */
function em_update_backup_config(string $source): string
{
    $dir = sys_get_temp_dir();
    if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
        return '';
    }

    $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR
        . 'emshop_config_backup_' . date('YmdHis') . '_' . substr(md5($source . microtime(true)), 0, 8) . '.php';

    if (@file_put_contents($path, $source) === strlen($source)) {
        @chmod($path, 0600);
        return $path;
    }

    return '';
}

/**
 * 原子替换 config.php：临时文件写完后 rename，避免写一半把站点写坏。
 */
function em_update_replace_file(string $path, string $content): void
{
    $dir = dirname($path);
    $tmp = @tempnam($dir, '.emcfg');
    if ($tmp === false) {
        throw new RuntimeException('无法在 config.php 目录创建临时文件（权限不足）');
    }

    $tmpDir = realpath(dirname($tmp));
    $targetDir = realpath($dir);
    if ($tmpDir === false || $targetDir === false || strcasecmp($tmpDir, $targetDir) !== 0) {
        @unlink($tmp);
        throw new RuntimeException('临时文件不在 config.php 同目录，已中止');
    }

    if (@file_put_contents($tmp, $content) !== strlen($content)) {
        @unlink($tmp);
        throw new RuntimeException('写入临时配置文件失败（磁盘空间或权限不足）');
    }

    $mode = @fileperms($path);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('替换 config.php 失败（请检查文件权限）');
    }

    if ($mode !== false) {
        @chmod($path, $mode & 0777);
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    clearstatcache(true, $path);
}

$emUpdateConfigPath = EM_ROOT . '/config.php';
try {
    $emUpdateResult = em_update_config_php($emUpdateConfigPath);
    echo 'config.php：' . $emUpdateResult['message'] . PHP_EOL;
    if ($emUpdateResult['backup'] !== '') {
        echo '旧配置已备份到：' . $emUpdateResult['backup'] . PHP_EOL;
    }
    if (!empty($emUpdateResult['ignored'])) {
        echo '未迁移的旧配置项（核心已不再使用，请按需手动处理）：'
            . implode(', ', $emUpdateResult['ignored']) . PHP_EOL;
    }
} catch (Throwable $e) {
    throw new RuntimeException('config.php 迁移失败：' . $e->getMessage(), 0, $e);
}
