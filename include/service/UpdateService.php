<?php

declare(strict_types=1);

/**
 * 在线升级服务。
 *
 * 升级流水线（由 admin/update.php 按顺序调度 AJAX 调用）：
 *   1. preflight       —— 预检：写权限 / 磁盘空间 / PHP 版本 / 锁文件 / 源版本兼容
 *   2. download        —— 下载升级包到 tmp/update/cache/，校验 SHA256
 *   3. backup          —— 把"本次会被替换的文件"拷贝到 tmp/update/backup/<ts>/
 *   4. extract         —— 解压 zip 到 tmp/update/extract/
 *   5. apply           —— 用解压出来的文件替换项目内容（白名单目录不覆盖）
 *   6. migrate         —— 扫 install/migrations/ 与 em_migrations 表求差集，跑新增 SQL
 *   7. finalize        —— 成功后清理临时文件；失败时由 rollback 善后
 *
 * 两级回滚：
 *   - apply 过程中任意拷贝失败 → 依 manifest 自动还原文件（用户无感）
 *   - migrate 中途失败 → 不自动回滚 DDL（MySQL 不可靠），前端弹窗请用户决策
 *     用户选择回滚时，从文件 backup + DB dump 同时还原
 *
 * 所有路径均使用 /（UNIX 风格），PHP 在 Windows 下也接受。
 */
final class UpdateService
{
    /** 升级流水线临时根目录（相对 EM_ROOT）—— 统一放在 content/ 下，避免污染项目根 */
    private const UPDATE_DIR    = '/content/tmp/update';
    /** 下载的 zip 存放处 */
    private const CACHE_DIR     = '/content/tmp/update/cache';
    /** zip 解压目标 */
    private const EXTRACT_DIR   = '/content/tmp/update/extract';
    /** 文件备份根（每次升级一个子目录） */
    private const BACKUP_DIR    = '/content/tmp/update/backup';
    /** DB dump 存放处 */
    private const DB_BACKUP_DIR = '/content/tmp/update/db_backup';
    /** 锁文件（升级进行中 = 存在） */
    private const LOCK_FILE     = '/content/tmp/update/lock';
    /** 当前批次的 apply 文件清单（回滚时用） */
    private const MANIFEST_FILE = '/content/tmp/update/manifest.json';

    /**
     * apply 时保留不覆盖的相对路径白名单。
     * 命中这些路径前缀的文件，即使升级包里带了也不会被写入。
     */
    private const PRESERVE_PATHS = [
        'content/uploads',     // 用户上传的图片/视频
        'content/cache',       // 运行时缓存
        'content/plugin',      // 用户安装的插件（来自应用商店）
        'content/template',    // 用户安装的模板
        'content/tmp',         // 升级流水线自己的临时目录（避免自覆盖）
        'tmp',                 // 项目根可能还有其它旧临时目录，一起保护
        '.env',                // 环境变量
        'config.php',          // DB 凭据
        '.claude',             // 开发工具配置
        '.idea',               // IDE 配置
        '.git',                // 版本控制
    ];

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
    public static function preflight(string $newVersion, string $minFromVersion = '', int $packageSize = 0): array
    {
        $errors = [];
        $warnings = [];

        // 锁文件检测：存在表示另一次升级正在进行，拒绝重复启动
        if (file_exists(EM_ROOT . self::LOCK_FILE)) {
            $lockAge = time() - filemtime(EM_ROOT . self::LOCK_FILE);
            if ($lockAge < 1800) {
                $errors[] = '检测到另一个升级正在进行（锁文件更新于 ' . $lockAge . ' 秒前）';
            } else {
                // 半小时以上的锁认为是异常遗留，允许覆盖
                $warnings[] = '发现过期锁文件（' . $lockAge . ' 秒），将被忽略';
            }
        }

        // PHP 版本：升级 1.2+ 起要求 7.4，这里保守检查
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = '当前 PHP 版本 ' . PHP_VERSION . ' 过低，升级需要 PHP 7.4 及以上';
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
            $errors[] = '以下目录没有写权限，请修改后重试：' . implode('、', $unwritable)
                . '（Linux：chmod -R 755；Windows：给 IIS 用户添加修改权限）';
        }

        // 磁盘空间：至少 packageSize * 3 倍（下载 + 解压 + 备份）
        $required = max($packageSize * 3, 50 * 1024 * 1024); // 保底 50MB
        $diskFree = @disk_free_space(EM_ROOT) ?: 0;
        if ($diskFree > 0 && $diskFree < $required) {
            $errors[] = '磁盘剩余空间不足：需 ' . self::formatBytes($required)
                . '，仅剩 ' . self::formatBytes((int) $diskFree);
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
     * 下载升级包到 cache 目录并校验 SHA256。
     *
     * @return array{ok:bool, path:string, size:int, sha256:string, error?:string}
     */
    public static function download(string $packageUrl, string $expectedSha256): array
    {
        if ($packageUrl === '') {
            return ['ok' => false, 'path' => '', 'size' => 0, 'sha256' => '', 'error' => '升级包 URL 为空'];
        }

        // 服务端可能返相对路径（如 /content/uploads/xxx.zip），自动补上授权服务器域名；
        // 不加这一层 cURL 对相对路径会直接 HTTP 0 失败
        $packageUrl = self::resolvePackageUrl($packageUrl);

        self::ensureDir(self::CACHE_DIR);
        self::writeLock();

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
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 600,  // 10 分钟，大包够了
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_USERAGENT      => 'emshop-update/' . (defined('EM_VERSION') ? EM_VERSION : 'dev'),
            CURLOPT_SSL_VERIFYPEER => false, // 兼容自签证书的线路
        ]);
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

        // SHA256 校验（服务端有给就严格比对，没给则跳过）
        if ($expectedSha256 !== '' && !hash_equals(strtolower($expectedSha256), $sha256)) {
            @unlink($localPath);
            return [
                'ok' => false, 'path' => '', 'size' => (int) $size, 'sha256' => $sha256,
                'error' => '升级包校验失败：SHA256 不匹配，包可能损坏',
            ];
        }

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
    public static function extract(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            return ['ok' => false, 'extract_path' => '', 'files' => 0, 'error' => '升级包文件不存在'];
        }

        // 每次解压前清空目录，避免残留
        self::removeDir(EM_ROOT . self::EXTRACT_DIR);
        self::ensureDir(self::EXTRACT_DIR);

        $zip = new ZipArchive();
        $open = $zip->open($zipPath);
        if ($open !== true) {
            return ['ok' => false, 'extract_path' => '', 'files' => 0, 'error' => '无法打开 zip 文件，错误码：' . $open];
        }

        $count = $zip->numFiles;
        $ok = $zip->extractTo(EM_ROOT . self::EXTRACT_DIR);
        $zip->close();

        if (!$ok) {
            self::removeDir(EM_ROOT . self::EXTRACT_DIR);
            return ['ok' => false, 'extract_path' => '', 'files' => 0, 'error' => '解压失败，可能磁盘空间不足或权限不够'];
        }

        return ['ok' => true, 'extract_path' => EM_ROOT . self::EXTRACT_DIR, 'files' => $count];
    }

    // ================================================================
    // Step 4: backup — 备份"将被替换的文件"
    // ================================================================

    /**
     * 以 extract 目录为基准，把项目中即将被覆盖的文件拷贝到 backup 目录。
     * 注意：这一步只备份"会被覆盖的"，不备份整个项目，省空间也更快。
     *
     * @return array{ok:bool, backup_path:string, backed_up:int, error?:string}
     */
    public static function backup(string $extractPath): array
    {
        if (!is_dir($extractPath)) {
            return ['ok' => false, 'backup_path' => '', 'backed_up' => 0, 'error' => '解压目录不存在'];
        }

        $stamp = date('YmdHis');
        $backupRel = self::BACKUP_DIR . '/' . $stamp;
        $backupAbs = EM_ROOT . $backupRel;
        self::ensureDir($backupRel);

        // 找到解压目录里升级代码的真实根（应对 zip 里可能套一层目录的情况）
        $srcRoot = self::detectPackageRoot($extractPath);

        $count = 0;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iter as $fileInfo) {
            if ($fileInfo->isDir()) continue;
            $rel = ltrim(str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($srcRoot))), '/');
            if (self::isPreserved($rel)) continue;

            $existing = EM_ROOT . '/' . $rel;
            if (!is_file($existing)) continue; // 新增文件不用备份

            $dst = $backupAbs . '/' . $rel;
            if (!is_dir(dirname($dst))) {
                @mkdir(dirname($dst), 0755, true);
            }
            if (@copy($existing, $dst) === false) {
                return [
                    'ok' => false, 'backup_path' => $backupAbs, 'backed_up' => $count,
                    'error' => '备份文件失败：' . $rel,
                ];
            }
            $count++;
        }

        return ['ok' => true, 'backup_path' => $backupAbs, 'backed_up' => $count];
    }

    // ================================================================
    // Step 5: apply — 应用升级
    // ================================================================

    /**
     * 把解压目录里的文件逐个覆盖到项目根（保留白名单路径）。
     * 任一文件写入失败 → 立即用本步已写入的 manifest 回滚。
     *
     * @return array{ok:bool, replaced:int, added:int, skipped:int, manifest_file:string, error?:string}
     */
    public static function apply(string $extractPath, string $backupPath): array
    {
        if (!is_dir($extractPath)) {
            return ['ok' => false, 'replaced' => 0, 'added' => 0, 'skipped' => 0, 'manifest_file' => '', 'error' => '解压目录不存在'];
        }
        if (!is_dir($backupPath)) {
            return ['ok' => false, 'replaced' => 0, 'added' => 0, 'skipped' => 0, 'manifest_file' => '', 'error' => '备份目录不存在，禁止应用'];
        }

        $srcRoot = self::detectPackageRoot($extractPath);
        $manifest = ['applied' => [], 'backup_path' => $backupPath, 'started_at' => date('c')];

        $replaced = 0; $added = 0; $skipped = 0;

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS)
        );

        try {
            foreach ($iter as $fileInfo) {
                if ($fileInfo->isDir()) continue;
                $rel = ltrim(str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($srcRoot))), '/');
                if (self::isPreserved($rel)) { $skipped++; continue; }

                $dst = EM_ROOT . '/' . $rel;
                $isNew = !is_file($dst);

                if (!is_dir(dirname($dst))) {
                    if (!@mkdir(dirname($dst), 0755, true) && !is_dir(dirname($dst))) {
                        throw new RuntimeException('无法创建目录：' . dirname($dst));
                    }
                }
                if (@copy($fileInfo->getPathname(), $dst) === false) {
                    throw new RuntimeException('写入失败：' . $rel);
                }

                $manifest['applied'][] = $rel;
                if ($isNew) $added++; else $replaced++;
            }
        } catch (Throwable $e) {
            // 自动回滚：从 backup 还原；新文件直接删
            self::rollbackFromManifest($manifest);
            return [
                'ok' => false, 'replaced' => $replaced, 'added' => $added, 'skipped' => $skipped,
                'manifest_file' => '', 'error' => '应用失败已自动回滚：' . $e->getMessage(),
            ];
        }

        // 落 manifest 到磁盘，供后续 migrate 失败时回滚用
        $manifestPath = EM_ROOT . self::MANIFEST_FILE;
        @file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return [
            'ok' => true, 'replaced' => $replaced, 'added' => $added, 'skipped' => $skipped,
            'manifest_file' => $manifestPath,
        ];
    }

    // ================================================================
    // Step 6: migrate — 数据库迁移
    // ================================================================

    /**
     * 扫 install/migrations/ 与 em_migrations 表求差集，按文件名顺序执行。
     * 执行前先 dump 数据库到 tmp/update/db_backup/，任一 SQL 失败停止后续。
     *
     * @return array{ok:bool, applied:string[], pending:string[], batch:int, db_dump:string, error?:string}
     */
    public static function migrate(): array
    {
        // 保证追踪表存在（本次升级首次引入追踪时，可能是 apply 阶段带来的新 .sql 创建）
        self::ensureMigrationsTable();

        $dir = EM_ROOT . '/install/migrations';
        if (!is_dir($dir)) {
            return ['ok' => true, 'applied' => [], 'pending' => [], 'batch' => 0, 'db_dump' => ''];
        }

        $allFiles = glob($dir . '/*.sql') ?: [];
        sort($allFiles, SORT_STRING);

        $prefix = Database::prefix();
        $done = Database::query('SELECT `filename` FROM `' . $prefix . 'migrations`');
        $doneSet = array_flip(array_column($done, 'filename'));

        $pending = [];
        foreach ($allFiles as $f) {
            $name = basename($f);
            if (!isset($doneSet[$name])) $pending[] = $f;
        }

        if ($pending === []) {
            return ['ok' => true, 'applied' => [], 'pending' => [], 'batch' => 0, 'db_dump' => ''];
        }

        // 跑之前先 dump 数据库（只备份结构 + 本次会动到的表的数据，太多全库备份太慢）
        // 为保守起见，这里只备份表结构，数据让 SQL 脚本自己用 `CREATE TABLE IF NOT EXISTS` 兜底
        $dumpPath = self::dumpSchema();

        $batchRow = Database::fetchOne('SELECT COALESCE(MAX(`batch`),0)+1 AS `n` FROM `' . $prefix . 'migrations`');
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
                foreach (self::splitSqlStatements($sql) as $stmt) {
                    if (trim($stmt) === '') continue;
                    Database::statement($stmt);
                }
                Database::insert('migrations', [
                    'filename' => $name,
                    'batch'    => $batch,
                    'checksum' => $checksum,
                ]);
                $applied[] = $name;
            } catch (Throwable $e) {
                return [
                    'ok' => false,
                    'applied' => $applied,
                    'pending' => array_map('basename', array_slice($pending, count($applied))),
                    'batch'   => $batch,
                    'db_dump' => $dumpPath,
                    'error'   => '迁移失败：' . $name . ' —— ' . $e->getMessage(),
                ];
            }
        }

        return ['ok' => true, 'applied' => $applied, 'pending' => [], 'batch' => $batch, 'db_dump' => $dumpPath];
    }

    // ================================================================
    // Step 7: finalize — 收尾
    // ================================================================

    /**
     * 收尾：清理临时文件，记录 last_update_at。
     * backup 目录保留最近 3 份，db_dump 同样保留 3 份。
     */
    public static function finalize(): array
    {
        // 清解压目录、cache 里的 zip（backup/db_backup 保留）
        self::removeDir(EM_ROOT . self::EXTRACT_DIR);
        self::cleanDir(EM_ROOT . self::CACHE_DIR);

        // backup 只保留最近 3 份
        self::keepRecentDirs(EM_ROOT . self::BACKUP_DIR, 3);
        self::keepRecentFiles(EM_ROOT . self::DB_BACKUP_DIR, 3);

        // 清 manifest
        @unlink(EM_ROOT . self::MANIFEST_FILE);

        // 记录一下最后升级时间
        if (class_exists('Config')) {
            Config::set('last_update_at', (string) time());
        }

        self::releaseLock();

        return ['ok' => true];
    }

    // ================================================================
    // 回滚（由前端在 migrate 失败时调用）
    // ================================================================

    /**
     * 从 manifest 还原文件 + 从 db_dump 还原数据库。
     *
     * @return array{ok:bool, restored_files:int, restored_db:bool, error?:string}
     */
    public static function rollback(bool $restoreDb = false, string $dbDumpPath = ''): array
    {
        $manifestPath = EM_ROOT . self::MANIFEST_FILE;
        $restored = 0;
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
            $restored = self::rollbackFromManifest($manifest);
        }

        $dbOk = false;
        if ($restoreDb && $dbDumpPath !== '' && is_file($dbDumpPath)) {
            $dbOk = self::restoreDump($dbDumpPath);
        }

        self::releaseLock();
        return ['ok' => true, 'restored_files' => $restored, 'restored_db' => $dbOk];
    }

    // ================================================================
    // 内部工具
    // ================================================================

    /**
     * 根据 manifest 把被替换的文件从 backup 还原、新增的文件直接删除。
     *
     * @param array{applied?:string[], backup_path?:string} $manifest
     */
    private static function rollbackFromManifest(array $manifest): int
    {
        $applied = $manifest['applied'] ?? [];
        $backupPath = (string) ($manifest['backup_path'] ?? '');
        $count = 0;

        foreach ($applied as $rel) {
            $projectFile = EM_ROOT . '/' . $rel;
            $backupFile  = $backupPath . '/' . $rel;

            if (is_file($backupFile)) {
                // 原本就存在 → 从备份还原
                if (!is_dir(dirname($projectFile))) @mkdir(dirname($projectFile), 0755, true);
                @copy($backupFile, $projectFile);
            } else {
                // 备份里没有 = 本次新增的文件 → 直接删
                @unlink($projectFile);
            }
            $count++;
        }
        return $count;
    }

    /**
     * 保证 em_migrations 表存在（老站第一次进入升级流程时走这里）。
     */
    private static function ensureMigrationsTable(): void
    {
        $table = Database::prefix() . 'migrations';
        Database::statement(
            'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `filename`   VARCHAR(255) NOT NULL,
                `batch`      INT UNSIGNED NOT NULL DEFAULT 0,
                `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `checksum`   CHAR(64)     NOT NULL DEFAULT \'\',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_filename` (`filename`),
                KEY `idx_batch` (`batch`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'数据库迁移追踪表\''
        );
    }

    /**
     * 规范化升级包 URL：相对路径自动补授权服务器域名。
     *
     * 服务端返的 package_url 有两种常见形态：
     *   1. 完整 URL（http:// 或 https:// 开头）—— 直接用
     *   2. 相对路径（/content/uploads/...）—— 前面补上当前授权服务器域名
     * 没这一层，cURL 对相对路径直接 HTTP 0 失败。
     */
    private static function resolvePackageUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return $url;

        // 完整 URL（含协议相对 //xxx）不动
        if (preg_match('#^(https?:)?//#i', $url)) return $url;

        // 相对路径：补当前授权服务器的域名做前缀
        if (!class_exists('LicenseClient')) return $url;
        $base = rtrim(LicenseClient::currentBaseUrl(), '/');
        if ($base === '') return $url;

        return $base . '/' . ltrim($url, '/');
    }

    /**
     * 解压目录可能是 emshop-v1.2.0/ 这样套一层的，也可能是直接铺平的。
     * 看解压根下有没有 init.php 或 admin/ 来判断，找不到就返回原目录。
     */
    private static function detectPackageRoot(string $extractPath): string
    {
        if (is_file($extractPath . '/init.php') || is_dir($extractPath . '/admin')) {
            return rtrim($extractPath, '/');
        }

        foreach (glob($extractPath . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
            if (is_file($sub . '/init.php') || is_dir($sub . '/admin')) {
                return rtrim($sub, '/');
            }
        }
        return rtrim($extractPath, '/');
    }

    /**
     * 相对路径是否命中保留白名单（升级时不动这些路径）。
     */
    private static function isPreserved(string $rel): bool
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
     * 拆分 SQL 语句：按 `;` 切，但尊重字符串内的分号和转义。
     * 去掉 `/* *\/` 块注释和 `--` 单行注释。
     * 不支持存储过程里的 DELIMITER 语法。
     *
     * @return string[]
     */
    private static function splitSqlStatements(string $sql): array
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

    /**
     * Dump 数据库（结构 + 数据）到 tmp/update/db_backup/。
     * 仅导出项目前缀的表；跳过表名不以 prefix 开头的"外部表"。
     *
     * 写入流程：DROP TABLE → CREATE TABLE → INSERT...（分批，避免单条 SQL 过大）
     * 用于 migrate 失败时的回滚：完整还原到 migrate 开始前的数据库状态。
     * 注意：只适合小-中型数据库（十万级行以内）；大型生产环境建议走 mysqldump。
     */
    private static function dumpSchema(): string
    {
        self::ensureDir(self::DB_BACKUP_DIR);
        $file = EM_ROOT . self::DB_BACKUP_DIR . '/dump_' . date('YmdHis') . '.sql';
        $prefix = Database::prefix();

        $fp = fopen($file, 'wb');
        if ($fp === false) return '';

        fwrite($fp, "-- Database dump before migration\n");
        fwrite($fp, "-- generated: " . date('c') . "\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        $rows = Database::query('SHOW TABLES');
        foreach ($rows as $r) {
            $tableName = (string) reset($r);
            // 只备份带项目前缀的表，外部表不碰
            if ($prefix !== '' && strpos($tableName, $prefix) !== 0) continue;

            $create = Database::fetchOne('SHOW CREATE TABLE `' . $tableName . '`');
            $createSql = $create['Create Table'] ?? ($create['Create View'] ?? '');
            if ($createSql === '') continue;

            fwrite($fp, "-- ==================== {$tableName} ====================\n");
            fwrite($fp, "DROP TABLE IF EXISTS `{$tableName}`;\n");
            fwrite($fp, $createSql . ";\n\n");

            // 导出数据：每 500 行一批 INSERT，避免单语句过长
            $offset = 0;
            $batchSize = 500;
            while (true) {
                $data = Database::query('SELECT * FROM `' . $tableName . '` LIMIT ' . $batchSize . ' OFFSET ' . $offset);
                if ($data === []) break;

                $columns = array_keys($data[0]);
                $cols = '`' . implode('`, `', $columns) . '`';
                $values = [];
                foreach ($data as $row) {
                    $cells = [];
                    foreach ($row as $v) {
                        if ($v === null) { $cells[] = 'NULL'; continue; }
                        if (is_int($v) || is_float($v)) { $cells[] = (string) $v; continue; }
                        // 字符串转义：用 addslashes 而不是 real_escape_string（后者依赖连接）
                        $cells[] = "'" . addslashes((string) $v) . "'";
                    }
                    $values[] = '(' . implode(', ', $cells) . ')';
                }
                fwrite($fp, "INSERT INTO `{$tableName}` ({$cols}) VALUES\n" . implode(",\n", $values) . ";\n");

                if (count($data) < $batchSize) break;
                $offset += $batchSize;
            }
            fwrite($fp, "\n");
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);
        return $file;
    }

    /**
     * 从 DDL dump 还原（重建空表）。注意：不会恢复表数据！
     */
    private static function restoreDump(string $dumpPath): bool
    {
        if (!is_file($dumpPath)) return false;
        $sql = (string) file_get_contents($dumpPath);
        try {
            foreach (self::splitSqlStatements($sql) as $s) {
                Database::statement($s);
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    // ---------- 目录/文件工具 ----------

    private static function ensureDir(string $relPath): void
    {
        $abs = EM_ROOT . $relPath;
        if (!is_dir($abs)) {
            @mkdir($abs, 0755, true);
        }
    }

    private static function removeDir(string $abs): void
    {
        if (!is_dir($abs)) return;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var SplFileInfo $f */
        foreach ($iter as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($abs);
    }

    private static function cleanDir(string $abs): void
    {
        if (!is_dir($abs)) return;
        foreach (glob($abs . '/*') ?: [] as $f) {
            if (is_dir($f)) self::removeDir($f);
            else @unlink($f);
        }
    }

    /**
     * 保留目录下最近 N 个子目录（按名字字典序倒序）。
     */
    private static function keepRecentDirs(string $parentAbs, int $keep): void
    {
        if (!is_dir($parentAbs)) return;
        $dirs = array_filter(glob($parentAbs . '/*', GLOB_ONLYDIR) ?: []);
        if (count($dirs) <= $keep) return;
        rsort($dirs, SORT_STRING);
        foreach (array_slice($dirs, $keep) as $d) self::removeDir($d);
    }

    private static function keepRecentFiles(string $parentAbs, int $keep): void
    {
        if (!is_dir($parentAbs)) return;
        $files = array_filter(glob($parentAbs . '/*') ?: [], 'is_file');
        if (count($files) <= $keep) return;
        rsort($files, SORT_STRING);
        foreach (array_slice($files, $keep) as $f) @unlink($f);
    }

    private static function writeLock(): void
    {
        self::ensureDir(self::UPDATE_DIR);
        @file_put_contents(EM_ROOT . self::LOCK_FILE, (string) time());
    }

    private static function releaseLock(): void
    {
        @unlink(EM_ROOT . self::LOCK_FILE);
    }

    private static function formatBytes(int $bytes): string
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
