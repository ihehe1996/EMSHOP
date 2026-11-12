<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台页面（/user/merchant/blog）。
 */
final class BlogController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        $merchantId = (int) $currentMerchant['id'];

        if ($this->request->isPost()) {
            try {
                $action = (string) $this->input->post('_action', '');
                if ($action !== 'list') {
                    if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                        $this->response->error('请求已失效，请刷新页面后重试');
                    }
                }

                switch ($action) {
                    // 列表（layui table 协议：code=0）
                    case 'list': {
                        $page = max(1, (int) $this->input->post('page', 1));
                        $limit = max(1, min(100, (int) $this->input->post('limit', 20)));
                        $keyword = trim((string) $this->input->post('keyword', ''));
                        $categoryId = (int) $this->input->post('category_id', 0);
                        $status = $this->input->post('status', '');

                        $where = ['merchant_id' => $merchantId];
                        if ($keyword !== '')   $where['keyword'] = $keyword;
                        if ($categoryId > 0)   $where['category_id'] = $categoryId;
                        if ($status !== '')    $where['status'] = (int) $status;

                        $result = app(\App\Model\BlogModel::class)->getList($where, $page, $limit, 'a.is_top DESC, a.sort ASC, a.id DESC');

                        $countWhere = ['merchant_id' => $merchantId];
                        if ($keyword !== '')   $countWhere['keyword'] = $keyword;
                        if ($categoryId > 0)   $countWhere['category_id'] = $categoryId;

                        $countAll       = app(\App\Model\BlogModel::class)->getList($countWhere, 1, 1)['total'];
                        $countPublished = app(\App\Model\BlogModel::class)->getList(array_merge($countWhere, ['status' => 1]), 1, 1)['total'];
                        $countDraft     = app(\App\Model\BlogModel::class)->getList(array_merge($countWhere, ['status' => 0]), 1, 1)['total'];

                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'code'       => 0,
                            'msg'        => '',
                            'count'      => $result['total'],
                            'data'       => $result['list'],
                            'csrf_token' => $this->csrf->token(),
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
                        $id = (int) $this->input->post('id', 0);
                        $article = app(\App\Model\BlogModel::class)->getById($id);
                        if (!$article || (int) $article['merchant_id'] !== $merchantId) {
                            $this->response->error('文章不存在');
                        }
                        $next = (int) $article['status'] === 1 ? 0 : 1;
                        app(\App\Model\BlogModel::class)->update($id, ['status' => $next]);
                        app(\App\Model\BlogTagModel::class)->refreshAllCounts();
                        $this->response->success('状态已更新', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    // 切换置顶
                    case 'toggle_top': {
                        $id = (int) $this->input->post('id', 0);
                        $article = app(\App\Model\BlogModel::class)->getById($id);
                        if (!$article || (int) $article['merchant_id'] !== $merchantId) {
                            $this->response->error('文章不存在');
                        }
                        $next = (int) $article['is_top'] === 1 ? 0 : 1;
                        app(\App\Model\BlogModel::class)->update($id, ['is_top' => $next]);
                        $this->response->success('已更新', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    // 单条删除（逻辑删除）
                    case 'delete': {
                        $id = (int) $this->input->post('id', 0);
                        $article = app(\App\Model\BlogModel::class)->getById($id);
                        if (!$article || (int) $article['merchant_id'] !== $merchantId) {
                            $this->response->error('文章不存在');
                        }
                        if (app(\App\Model\BlogModel::class)->delete($id)) {
                            app(\App\Model\BlogTagModel::class)->refreshAllCounts();
                            $this->response->success('已删除', ['csrf_token' => $this->csrf->refresh()]);
                        } else {
                            $this->response->error('删除失败');
                        }
                        break;
                    }

                    // 批量操作（仅本店）
                    case 'batch': {
                        $batchAction = (string) $this->input->post('batch_action', '');
                        $rawIds = $this->input->post('ids', '');
                        $ids = is_array($rawIds) ? array_map('intval', $rawIds)
                            : (array) (json_decode((string) $rawIds, true) ?: []);
                        $ids = array_values(array_unique(array_map('intval', $ids)));
                        $ids = array_filter($ids, fn($v) => $v > 0);

                        if (empty($ids)) {
                            $this->response->error('请选择文章');
                        }

                        // 限定到本店
                        $prefix = $this->db->prefix();
                        $allowedRows = $this->db->query(
                            "SELECT id FROM {$prefix}blog WHERE merchant_id = ? AND id IN (" . implode(',', array_map('intval', $ids)) . ")",
                            [$merchantId]
                        );
                        $allowedIds = array_map(fn($r) => (int) $r['id'], $allowedRows);
                        if (empty($allowedIds)) {
                            $this->response->error('所选文章均无权操作');
                        }

                        $failed = count($ids) - count($allowedIds);
                        foreach ($allowedIds as $id) {
                            try {
                                if ($batchAction === 'publish') {
                                    app(\App\Model\BlogModel::class)->update($id, ['status' => 1]);
                                } elseif ($batchAction === 'draft') {
                                    app(\App\Model\BlogModel::class)->update($id, ['status' => 0]);
                                } elseif ($batchAction === 'delete') {
                                    app(\App\Model\BlogModel::class)->delete($id);
                                } else {
                                    $this->response->error('未知批量操作');
                                }
                            } catch (\Throwable $e) {
                                $failed++;
                            }
                        }
                        app(\App\Model\BlogTagModel::class)->refreshAllCounts();
                        if ($failed === 0) {
                            $this->response->success('批量操作成功', ['csrf_token' => $this->csrf->refresh()]);
                        } else {
                            $this->response->error('部分失败（' . $failed . '/' . count($ids) . '）');
                        }
                        break;
                    }

                    default:
                        $this->response->error('未知操作');
                }
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage() ?: '系统繁忙，请稍后再试');
            }
        }

        // 列表页：本店分类下拉
        $categories = app(\App\Model\BlogCategoryModel::class)->getAll($merchantId);

        \App\Merchant\MerchantShell::render(dirname(__DIR__) . '/View/blog.php', $frontUser, $currentMerchant, $merchantLevel, [
            'categories' => $categories,
        ]);
    }
}
