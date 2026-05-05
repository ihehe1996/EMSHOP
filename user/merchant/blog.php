<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 - 文章列表
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
            // 列表（layui table 协议：code=0）
            case 'list': {
                $page = max(1, (int) Input::post('page', 1));
                $limit = max(1, min(100, (int) Input::post('limit', 20)));
                $keyword = trim((string) Input::post('keyword', ''));
                $categoryId = (int) Input::post('category_id', 0);
                $status = Input::post('status', '');

                $where = ['merchant_id' => $merchantId];
                if ($keyword !== '')   $where['keyword'] = $keyword;
                if ($categoryId > 0)   $where['category_id'] = $categoryId;
                if ($status !== '')    $where['status'] = (int) $status;

                $result = BlogModel::getList($where, $page, $limit, 'a.is_top DESC, a.sort ASC, a.id DESC');

                $countWhere = ['merchant_id' => $merchantId];
                if ($keyword !== '')   $countWhere['keyword'] = $keyword;
                if ($categoryId > 0)   $countWhere['category_id'] = $categoryId;

                $countAll       = BlogModel::getList($countWhere, 1, 1)['total'];
                $countPublished = BlogModel::getList(array_merge($countWhere, ['status' => 1]), 1, 1)['total'];
                $countDraft     = BlogModel::getList(array_merge($countWhere, ['status' => 0]), 1, 1)['total'];

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

            // 切换发布状态
            case 'toggle_status': {
                $id = (int) Input::post('id', 0);
                $article = BlogModel::getById($id);
                if (!$article || (int) $article['merchant_id'] !== $merchantId) {
                    Response::error('文章不存在');
                }
                $next = (int) $article['status'] === 1 ? 0 : 1;
                BlogModel::update($id, ['status' => $next]);
                BlogTagModel::refreshAllCounts();
                Response::success('状态已更新', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 切换置顶
            case 'toggle_top': {
                $id = (int) Input::post('id', 0);
                $article = BlogModel::getById($id);
                if (!$article || (int) $article['merchant_id'] !== $merchantId) {
                    Response::error('文章不存在');
                }
                $next = (int) $article['is_top'] === 1 ? 0 : 1;
                BlogModel::update($id, ['is_top' => $next]);
                Response::success('已更新', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 单条删除（逻辑删除）
            case 'delete': {
                $id = (int) Input::post('id', 0);
                $article = BlogModel::getById($id);
                if (!$article || (int) $article['merchant_id'] !== $merchantId) {
                    Response::error('文章不存在');
                }
                if (BlogModel::delete($id)) {
                    BlogTagModel::refreshAllCounts();
                    Response::success('已删除', ['csrf_token' => Csrf::refresh()]);
                } else {
                    Response::error('删除失败');
                }
                break;
            }

            // 批量操作（仅本店）
            case 'batch': {
                $batchAction = (string) Input::post('batch_action', '');
                $rawIds = Input::post('ids', '');
                $ids = is_array($rawIds) ? array_map('intval', $rawIds)
                    : (array) (json_decode((string) $rawIds, true) ?: []);
                $ids = array_values(array_unique(array_map('intval', $ids)));
                $ids = array_filter($ids, fn($v) => $v > 0);

                if (empty($ids)) {
                    Response::error('请选择文章');
                }

                // 限定到本店
                $prefix = Database::prefix();
                $allowedRows = Database::query(
                    "SELECT id FROM {$prefix}blog WHERE merchant_id = ? AND id IN (" . implode(',', array_map('intval', $ids)) . ")",
                    [$merchantId]
                );
                $allowedIds = array_map(fn($r) => (int) $r['id'], $allowedRows);
                if (empty($allowedIds)) {
                    Response::error('所选文章均无权操作');
                }

                $failed = count($ids) - count($allowedIds);
                foreach ($allowedIds as $id) {
                    try {
                        if ($batchAction === 'publish') {
                            BlogModel::update($id, ['status' => 1]);
                        } elseif ($batchAction === 'draft') {
                            BlogModel::update($id, ['status' => 0]);
                        } elseif ($batchAction === 'delete') {
                            BlogModel::delete($id);
                        } else {
                            Response::error('未知批量操作');
                        }
                    } catch (Throwable $e) {
                        $failed++;
                    }
                }
                BlogTagModel::refreshAllCounts();
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

// 列表页：本店分类下拉
$categories = (new BlogCategoryModel())->getAll($merchantId);

merchantRenderPage(__DIR__ . '/view/blog.php', [
    'categories' => $categories,
]);
