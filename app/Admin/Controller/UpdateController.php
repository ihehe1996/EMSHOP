<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 在线升级接口（/admin/update）。
 *
 * 所有动作均为 POST AJAX，由升级向导按
 * preflight → download → extract → apply → migrate → run_script → finalize 调度。
 *
 * 动作（_action）：
 *   preflight / download / extract / apply / migrate / run_script / finalize / rollback
 */
final class UpdateController extends BaseAdminController
{
    public function index(): void
    {
        // 所有动作必须 POST + CSRF
        if (!$this->request->isPost()) {
            $this->response->error('仅支持 POST');
        }

        // 每个动作都是整站级写操作，必须校验 CSRF
        if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
            $this->response->error('请求已失效，请刷新页面后重试');
        }

        $action = (string) $this->input->post('_action', '');

        try {
            switch ($action) {
                case 'preflight': {
                    $version = (string) $this->input->post('version', '');
                    $minFrom = (string) $this->input->post('min_from_version', '');
                    $packageSize = (int) $this->input->post('package_size', 0);
                    $res = \App\Service\UpdateService::preflight($version, $minFrom, $packageSize);
                    // preflight 就算有 errors 也用 200 返回（让前端一次性拿到清单展示）
                    $this->response->success('', $res + ['csrf_token' => $this->csrf->token()]);
                }

                case 'download': {
                    $url = (string) $this->input->post('package_url', '');
                    $sha256 = (string) $this->input->post('package_sha256', '');
                    if ($url === '') {
                        $this->response->error('升级包 URL 不能为空');
                    }

                    $res = \App\Service\UpdateService::download($url, $sha256);
                    if (!$res['ok']) {
                        $this->response->error($res['error'] ?? '下载失败', $res);
                    }
                    $this->response->success('下载完成', $res);
                }

                case 'extract': {
                    $zipPath = (string) $this->input->post('zip_path', '');
                    if ($zipPath === '') {
                        $this->response->error('缺少 zip_path 参数');
                    }

                    $res = \App\Service\UpdateService::extract($zipPath);
                    if (!$res['ok']) {
                        $this->response->error($res['error'] ?? '解压失败', $res);
                    }
                    $this->response->success('解压完成', $res);
                }

                case 'apply': {
                    $extractPath = (string) $this->input->post('extract_path', '');
                    if ($extractPath === '') {
                        $this->response->error('缺少 extract_path 参数');
                    }
                    $res = \App\Service\UpdateService::apply($extractPath);
                    if (!$res['ok']) {
                        $this->response->error($res['error'] ?? '应用失败', $res);
                    }
                    $this->response->success('文件覆盖完成', $res);
                }

                case 'migrate': {
                    $res = \App\Service\UpdateService::migrate();
                    if (!$res['ok']) {
                        // 迁移失败不自动回滚 —— 返回详情让前端弹窗问用户
                        $this->response->error($res['error'] ?? '数据库迁移失败', $res);
                    }
                    $this->response->success('数据库迁移完成', $res);
                }

                case 'run_script': {
                    $extractPath = (string) $this->input->post('extract_path', '');
                    if ($extractPath === '') {
                        $this->response->error('缺少 extract_path 参数');
                    }
                    $res = \App\Service\UpdateService::runPackageScript($extractPath);
                    if (!$res['ok']) {
                        $this->response->error($res['error'] ?? '更新脚本执行失败', $res);
                    }
                    $msg = $res['ran'] ? '更新脚本执行完成' : '本次升级包无更新脚本';
                    if ($res['ran'] && !$res['deleted']) {
                        $msg .= '（注意：update.php 删除失败，请手动删除网站根目录的 update.php）';
                    }
                    $this->response->success($msg, $res);
                }

                case 'finalize': {
                    $res = \App\Service\UpdateService::finalize();
                    // 每次系统升级完成后 bump pending 版本，触发任务服务检测并重载 worker
                    \App\Cli\CliServer::bumpPendingFileVersion();
                    $this->response->success('升级完成', $res + ['csrf_token' => $this->csrf->refresh()]);
                }

                case 'rollback': {
                    $res = \App\Service\UpdateService::rollback();
                    $this->response->success('回滚完成', $res + ['csrf_token' => $this->csrf->refresh()]);
                }

                default:
                    $this->response->error('未知动作：' . $action);
            }
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error('升级异常：' . $e->getMessage());
        }
    }
}
