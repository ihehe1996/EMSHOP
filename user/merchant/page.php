<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 - 页面管理（WordPress 式自定义页面）
 *
 * 商户的页面与主站完全分离，slug 在本店唯一（uk_merchant_slug）。
 * 前台访问 /p/{slug} 时按当前 MerchantContext 解析。
 */
merchantRequireLogin();

$merchantId = (int) $currentMerchant['id'];

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');
        if ($action !== 'list') {
            if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        switch ($action) {
            case 'list': {
                $page = max(1, (int) Input::post('page', 1));
                $limit = max(1, min(100, (int) Input::post('limit', 20)));
                $keyword = trim((string) Input::post('keyword', ''));
                $status = Input::post('status', '');

                $where = ['merchant_id' => $merchantId];
                if ($keyword !== '') $where['keyword'] = $keyword;
                if ($status !== '')  $where['status'] = (int) $status;

                $result = PageModel::getList($where, $page, $limit);

                $countWhere = ['merchant_id' => $merchantId];
                if ($keyword !== '') $countWhere['keyword'] = $keyword;
                $countAll       = PageModel::getList($countWhere, 1, 1)['total'];
                $countPublished = PageModel::getList(array_merge($countWhere, ['status' => 1]), 1, 1)['total'];
                $countDraft     = PageModel::getList(array_merge($countWhere, ['status' => 0]), 1, 1)['total'];

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'code'       => 0,
                    'msg'        => '',
                    'count'      => $result['total'],
                    'data'       => $result['list'],
                    'csrf_token' => Csrf::token(),
                    'tab_counts' => [
                        'all'       => $countAll,
                        'published' => $countPublished,
                        'draft'     => $countDraft,
                    ],
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 设为本店首页
            case 'set_homepage': {
                $id = (int) Input::post('id', 0);
                $row = PageModel::getById($id);
                if (!$row || (int) $row['merchant_id'] !== $merchantId) {
                    Response::error('页面不存在');
                }
                if (PageModel::setHomepage($id, $merchantId)) {
                    Response::success('已设为本店首页', ['csrf_token' => Csrf::refresh()]);
                } else {
                    Response::error('设置失败');
                }
                break;
            }

            // 取消本店首页
            case 'clear_homepage': {
                $id = (int) Input::post('id', 0);
                $row = PageModel::getById($id);
                if (!$row || (int) $row['merchant_id'] !== $merchantId) {
                    Response::error('页面不存在');
                }
                PageModel::clearHomepage($merchantId, $id);
                Response::success('已取消本店首页', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            case 'toggle_status': {
                $id = (int) Input::post('id', 0);
                $row = PageModel::getById($id);
                if (!$row || (int) $row['merchant_id'] !== $merchantId) {
                    Response::error('页面不存在');
                }
                $next = (int) $row['status'] === 1 ? 0 : 1;
                PageModel::update($id, ['status' => $next]);
                Response::success('状态已更新', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            case 'delete': {
                $id = (int) Input::post('id', 0);
                $row = PageModel::getById($id);
                if (!$row || (int) $row['merchant_id'] !== $merchantId) {
                    Response::error('页面不存在');
                }
                if (PageModel::delete($id)) {
                    Response::success('已删除', ['csrf_token' => Csrf::refresh()]);
                } else {
                    Response::error('删除失败');
                }
                break;
            }

            case 'batch': {
                $batchAction = (string) Input::post('batch_action', '');
                $rawIds = Input::post('ids', '');
                $ids = is_array($rawIds) ? array_map('intval', $rawIds)
                    : (array) (json_decode((string) $rawIds, true) ?: []);
                $ids = array_values(array_unique(array_map('intval', $ids)));
                $ids = array_filter($ids, fn($v) => $v > 0);
                if (empty($ids)) Response::error('请选择页面');

                $prefix = Database::prefix();
                $allowed = Database::query(
                    "SELECT id FROM {$prefix}page WHERE merchant_id = ? AND id IN (" . implode(',', array_map('intval', $ids)) . ")",
                    [$merchantId]
                );
                $allowedIds = array_map(fn($r) => (int) $r['id'], $allowed);
                if (empty($allowedIds)) Response::error('所选页面均无权操作');

                $failed = count($ids) - count($allowedIds);
                foreach ($allowedIds as $id) {
                    try {
                        if ($batchAction === 'publish')   PageModel::update($id, ['status' => 1]);
                        elseif ($batchAction === 'draft') PageModel::update($id, ['status' => 0]);
                        elseif ($batchAction === 'delete') PageModel::delete($id);
                        else { Response::error('未知批量操作'); }
                    } catch (Throwable $e) {
                        $failed++;
                    }
                }
                if ($failed === 0) {
                    Response::success('批量操作成功', ['csrf_token' => Csrf::refresh()]);
                } else {
                    Response::error('部分失败（' . $failed . '/' . count($ids) . '）');
                }
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (Throwable $e) {
        Response::error($e->getMessage() ?: '系统繁忙，请稍后再试');
    }
}

merchantRenderPage(__DIR__ . '/view/page.php');
