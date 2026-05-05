<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 在线升级控制器。
 *
 * 所有动作均为 POST AJAX，由前端升级向导按 preflight → download → extract → backup
 *   → apply → migrate → finalize 的顺序调度；任一步失败均可调 rollback 收尾。
 *
 * 每步独立返回 JSON，后端不记状态——让前端持有"当前到哪一步"的数据（zip path /
 * extract path / backup path / db dump path），避免会话依赖。
 *
 * 动作（_action）：
 *   preflight   —— 预检环境
 *   download    —— 下载升级包（服务端返回 path）
 *   extract     —— 解压（返回 extract_path）
 *   backup      —— 备份将被替换的文件（返回 backup_path）
 *   apply       —— 覆盖文件（返回 manifest_file；失败自动回滚）
 *   migrate     —— 跑 install/migrations 新增 SQL（失败返回 db_dump 路径供回滚）
 *   finalize    —— 成功收尾，清临时文件
 *   rollback    —— 手动回滚（用户在 migrate 失败时触发）
 */
adminRequireLogin();

// 所有动作必须 POST + CSRF
if (!Request::isPost()) {
    Response::error('仅支持 POST');
}
$csrf = (string) Input::post('csrf_token', '');
if (!Csrf::validate($csrf)) {
    Response::error('请求已失效，请刷新页面后重试');
}

$action = (string) Input::post('_action', '');

try {
    switch ($action) {
        // ------------------------------------------------------------
        case 'preflight': {
            $version     = (string) Input::post('version', '');
            $minFrom     = (string) Input::post('min_from_version', '');
            $packageSize = (int) Input::post('package_size', 0);
            $res = UpdateService::preflight($version, $minFrom, $packageSize);
            // preflight 就算有 errors 也用 200 返回（让前端一次性拿到清单展示），
            // 只有真正的异常才走 400
            Response::success('', $res + ['csrf_token' => Csrf::token()]);
        }

        // ------------------------------------------------------------
        case 'download': {
            $url    = (string) Input::post('package_url', '');
            $sha256 = (string) Input::post('package_sha256', '');
            if ($url === '') Response::error('升级包 URL 不能为空');

            $res = UpdateService::download($url, $sha256);
            if (!$res['ok']) Response::error($res['error'] ?? '下载失败', $res);
            Response::success('下载完成', $res);
        }

        // ------------------------------------------------------------
        case 'extract': {
            $zipPath = (string) Input::post('zip_path', '');
            if ($zipPath === '') Response::error('缺少 zip_path 参数');

            $res = UpdateService::extract($zipPath);
            if (!$res['ok']) Response::error($res['error'] ?? '解压失败', $res);
            Response::success('解压完成', $res);
        }

        // ------------------------------------------------------------
        case 'backup': {
            $extractPath = (string) Input::post('extract_path', '');
            if ($extractPath === '') Response::error('缺少 extract_path 参数');

            $res = UpdateService::backup($extractPath);
            if (!$res['ok']) Response::error($res['error'] ?? '备份失败', $res);
            Response::success('备份完成', $res);
        }

        // ------------------------------------------------------------
        case 'apply': {
            $extractPath = (string) Input::post('extract_path', '');
            $backupPath  = (string) Input::post('backup_path', '');
            if ($extractPath === '' || $backupPath === '') {
                Response::error('缺少 extract_path / backup_path 参数');
            }
            $res = UpdateService::apply($extractPath, $backupPath);
            if (!$res['ok']) Response::error($res['error'] ?? '应用失败', $res);
            Response::success('文件覆盖完成', $res);
        }

        // ------------------------------------------------------------
        case 'migrate': {
            $res = UpdateService::migrate();
            if (!$res['ok']) {
                // 迁移失败不自动回滚 —— 返回详情让前端弹窗问用户
                Response::error($res['error'] ?? '数据库迁移失败', $res);
            }
            Response::success('数据库迁移完成', $res);
        }

        // ------------------------------------------------------------
        case 'finalize': {
            $res = UpdateService::finalize();
            Response::success('升级完成', $res + ['csrf_token' => Csrf::refresh()]);
        }

        // ------------------------------------------------------------
        case 'rollback': {
            $restoreDb = (string) Input::post('restore_db', '0') === '1';
            $dbDump    = (string) Input::post('db_dump', '');
            $res = UpdateService::rollback($restoreDb, $dbDump);
            Response::success('回滚完成', $res + ['csrf_token' => Csrf::refresh()]);
        }

        // ------------------------------------------------------------
        default:
            Response::error('未知动作：' . $action);
    }
} catch (RuntimeException $e) {
    Response::error($e->getMessage());
} catch (Throwable $e) {
    Response::error('升级异常：' . $e->getMessage());
}
