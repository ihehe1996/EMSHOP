<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 在线升级接口（{入口}/update）。
 *
 * 所有动作均为 POST AJAX，由升级向导按
 * preflight → download → extract → apply → migrate → run_script → finalize 调度。
 *
 * 动作（_action）：
 *   preflight / download / download_stream / extract / extract_stream / check_permissions / check_permissions_stream / apply / migrate / run_script / finalize / rollback
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
                    $res = app(\App\Service\UpdateService::class)->preflight($version, $minFrom, $packageSize);
                    // preflight 就算有 errors 也用 200 返回（让前端一次性拿到清单展示）
                    $this->response->success('', $res + ['csrf_token' => $this->csrf->token()]);
                }

                case 'download': {
                    $url = (string) $this->input->post('package_url', '');
                    $sha256 = (string) $this->input->post('package_sha256', '');
                    if ($url === '') {
                        $this->response->error('升级包 URL 不能为空');
                    }

                    $res = app(\App\Service\UpdateService::class)->download($url, $sha256);
                    if (!$res['ok']) {
                        $this->response->error($res['error'] ?? '下载失败', $res);
                    }
                    $this->response->success('下载完成', $res);
                }

                // 流式下载：边下边输出 NDJSON 进度，前端用 XHR onprogress 读取
                case 'download_stream': {
                    $url = (string) $this->input->post('package_url', '');
                    $sha256 = (string) $this->input->post('package_sha256', '');

                    @set_time_limit(0);
                    @ini_set('zlib.output_compression', '0');
                    header('Content-Type: application/x-ndjson; charset=utf-8');
                    header('Cache-Control: no-cache, no-store');
                    // 让 Nginx 不要缓冲，否则进度会攒到最后一次性下发
                    header('X-Accel-Buffering: no');

                    while (ob_get_level() > 0) {
                        @ob_end_flush();
                    }
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }

                    try {
                        $res = app(\App\Service\UpdateService::class)->download(
                            $url,
                            $sha256,
                            static function (int $total, int $downloaded): void {
                                echo json_encode([
                                    'type'       => 'progress',
                                    'total'      => $total,
                                    'downloaded' => $downloaded,
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                                @flush();
                            }
                        );
                    } catch (\Throwable $e) {
                        $res = ['ok' => false, 'path' => '', 'size' => 0, 'sha256' => '', 'error' => $e->getMessage()];
                    }

                    echo json_encode(['type' => 'done'] + $res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    @flush();
                    exit;
                }

                case 'extract': {
                    $zipPath = (string) $this->input->post('zip_path', '');
                    if ($zipPath === '') {
                        $this->response->error('缺少 zip_path 参数');
                    }

                    $res = app(\App\Service\UpdateService::class)->extract($zipPath);
                    if (!$res['ok']) {
                        $this->response->error($res['error'] ?? '解压失败', $res);
                    }
                    $this->response->success('解压完成', $res);
                }

                // 流式解压：逐条解压并输出 NDJSON 进度
                case 'extract_stream': {
                    $zipPath = (string) $this->input->post('zip_path', '');

                    @set_time_limit(0);
                    @ini_set('zlib.output_compression', '0');
                    header('Content-Type: application/x-ndjson; charset=utf-8');
                    header('Cache-Control: no-cache, no-store');
                    // 让 Nginx 不要缓冲，否则进度会攒到最后一次性下发
                    header('X-Accel-Buffering: no');

                    while (ob_get_level() > 0) {
                        @ob_end_flush();
                    }
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }

                    try {
                        $res = app(\App\Service\UpdateService::class)->extract(
                            $zipPath,
                            static function (int $done, int $total): void {
                                echo json_encode([
                                    'type'  => 'progress',
                                    'done'  => $done,
                                    'total' => $total,
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                                @flush();
                            }
                        );
                    } catch (\Throwable $e) {
                        $res = ['ok' => false, 'extract_path' => '', 'files' => 0, 'error' => $e->getMessage()];
                    }

                    echo json_encode(['type' => 'done'] + $res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    @flush();
                    exit;
                }

                case 'check_permissions': {
                    $extractPath = (string) $this->input->post('extract_path', '');
                    if ($extractPath === '') {
                        $this->response->error('缺少 extract_path 参数');
                    }
                    $res = app(\App\Service\UpdateService::class)->checkApplyPermissions($extractPath);
                    if (!$res['ok']) {
                        $this->response->error(
                            '文件权限校验未通过：共 ' . count($res['errors'])
                            . ' 项不可写，请将网站目录权限设置为755（为www用户设置），详见运行日志',
                            $res
                        );
                    }
                    $this->response->success('文件权限校验通过', $res);
                }

                // 流式权限校验：逐文件检查并输出 NDJSON 进度
                case 'check_permissions_stream': {
                    $extractPath = (string) $this->input->post('extract_path', '');

                    @set_time_limit(0);
                    @ini_set('zlib.output_compression', '0');
                    header('Content-Type: application/x-ndjson; charset=utf-8');
                    header('Cache-Control: no-cache, no-store');
                    // 让 Nginx 不要缓冲，否则进度会攒到最后一次性下发
                    header('X-Accel-Buffering: no');

                    while (ob_get_level() > 0) {
                        @ob_end_flush();
                    }
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }

                    try {
                        $res = app(\App\Service\UpdateService::class)->checkApplyPermissions(
                            $extractPath,
                            static function (int $checked, int $total): void {
                                echo json_encode([
                                    'type'    => 'progress',
                                    'checked' => $checked,
                                    'total'   => $total,
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                                @flush();
                            }
                        );
                    } catch (\Throwable $e) {
                        $res = ['ok' => false, 'checked' => 0, 'errors' => [['path' => '', 'reason' => $e->getMessage()]], 'warnings' => []];
                    }

                    if (!$res['ok']) {
                        $res['msg'] = '文件权限校验未通过：共 ' . count($res['errors'])
                            . ' 项不可写，请将网站目录权限设置为755（为www用户设置），详见运行日志';
                    }

                    echo json_encode(['type' => 'done'] + $res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    @flush();
                    exit;
                }

                case 'apply': {
                    $extractPath = (string) $this->input->post('extract_path', '');
                    if ($extractPath === '') {
                        $this->response->error('缺少 extract_path 参数');
                    }
                    $res = app(\App\Service\UpdateService::class)->apply($extractPath);
                    if (!$res['ok']) {
                        $this->response->error($res['error'] ?? '应用失败', $res);
                    }
                    $msg = !empty($res['dry_run']) ? '已跳过文件覆盖（联调模式）' : '文件覆盖完成';
                    $this->response->success($msg, $res);
                }

                case 'migrate': {
                    $res = app(\App\Service\UpdateService::class)->migrate();
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
                    $res = app(\App\Service\UpdateService::class)->runPackageScript($extractPath);
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
                    $res = app(\App\Service\UpdateService::class)->finalize();
                    // 每次系统升级完成后 bump pending 版本，触发任务服务检测并重载 worker
                    \App\Cli\CliServer::bumpPendingFileVersion();
                    $this->response->success('升级完成', $res + ['csrf_token' => $this->csrf->refresh()]);
                }

                case 'rollback': {
                    $res = app(\App\Service\UpdateService::class)->rollback();
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
