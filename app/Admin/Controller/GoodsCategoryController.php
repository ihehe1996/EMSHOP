<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 商品分类管理（/admin/goods_category）。
 *
 * 仅支持 2 级分类：顶级 和 二级（归属某顶级）。
 * 与旧 admin/goods_category.php 行为一致：树形列表 / 新增 / 编辑 / 删除 /
 * 批量删除 / 状态切换 / 图片上传，以及 ?_popup=1 编辑弹窗。
 *
 * 动作参数以 GET 的 action 为主（旧视图用 ?action=add/edit/...）；
 * 状态切换的旧视图把 _action 放在 POST body 里，这里一并兼容。
 *
 * 过渡期列表页仍套用旧后台框架壳（admin/view/index.php）。
 */
final class GoodsCategoryController extends BaseAdminController
{
    public function index(): void
    {
        $model = new \GoodsCategoryModel();

        if (\Request::isPost()) {
            $this->handlePost($model);
            return;
        }

        if ((string) \Input::get('_popup', '') === '1') {
            $this->renderPopup($model);
            return;
        }

        $this->renderList();
    }

    /**
     * 处理后台 AJAX 动作。
     */
    private function handlePost(\GoodsCategoryModel $model): void
    {
        $action = (string) \Input::get('action', '');
        if ($action === '') {
            $action = (string) \Input::post('action', '');
        }
        if ($action === '') {
            // 兼容旧视图：状态切换把 _action 放在 POST body 里
            $action = (string) \Input::post('_action', '');
        }

        try {
            // CSRF 校验：写操作都带 action（增删改 / 批量删除 / 状态切换 / 图片），
            // 全部需要校验。action 为空是树形表格的**只读查询** —— 前端把令牌放在
            // csrf 自定义请求头里（见视图的 treeTable 配置），
            // Input::post('csrf_token') 取不到，所以必须跳过，否则分类列表直接空白。
            if ($action !== '' && !\Csrf::validate((string) \Input::post('csrf_token', ''))) {
                \Response::error('请求已失效，请刷新页面后重试');
            }

            if ($action === '') {
                $keyword = trim((string) \Input::post('keyword', ''));
                $allCategories = $model->getAll();

                $data = [];
                foreach ($allCategories as $item) {
                    if ($keyword !== '' && stripos((string) $item['name'], $keyword) === false) {
                        continue;
                    }
                    $data[] = $item;
                }

                // treeTable 直接读取 data 数组，无需嵌套格式
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'code' => 0,
                    'msg' => '',
                    'data' => array_values($data),
                    'count' => count($data),
                    'csrf_token' => \Csrf::token(),
                ], JSON_UNESCAPED_UNICODE);
                die;
            }

            if ($action === 'add') {
                $name = trim((string) \Input::post('name', ''));
                if ($name === '') {
                    \Response::error('分类名称不能为空');
                }
                if (mb_strlen($name) > 100) {
                    \Response::error('分类名称最多100个字符');
                }

                $parentId = (int) \Input::post('parent_id', 0);

                $slug = trim((string) \Input::post('slug', ''));
                if ($slug !== '' && $model->existsSlug($slug)) {
                    \Response::error('别名已存在');
                }

                $sort = (int) \Input::post('sort', 100);
                $status = \Input::post('status', '1') === '1' ? 1 : 0;

                $rebateJson = $this->buildRebateJson();

                $model->create([
                    'parent_id' => $parentId,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => (string) \Input::post('description', ''),
                    'icon' => (string) \Input::post('icon', ''),
                    'cover_image' => (string) \Input::post('cover_image', ''),
                    'sort' => $sort,
                    'seo_title' => (string) \Input::post('seo_title', ''),
                    'seo_keywords' => (string) \Input::post('seo_keywords', ''),
                    'seo_description' => (string) \Input::post('seo_description', ''),
                    'status' => $status,
                    'rebate_config' => $rebateJson,
                ]);

                $csrfToken = \Csrf::refresh();
                \Response::success('分类创建成功', ['csrf_token' => $csrfToken]);
            }

            if ($action === 'edit') {
                $id = (int) \Input::post('id', 0);
                if ($id <= 0) {
                    \Response::error('无效的分类ID');
                }

                $existing = $model->findById($id);
                if ($existing === null) {
                    \Response::error('分类不存在');
                }

                $name = trim((string) \Input::post('name', ''));
                if ($name === '') {
                    \Response::error('分类名称不能为空');
                }
                if (mb_strlen($name) > 100) {
                    \Response::error('分类名称最多100个字符');
                }

                $parentId = (int) \Input::post('parent_id', 0);

                // 二级分类不能变成顶级（避免混乱），除非本身就没有子分类
                if ($existing['parent_id'] > 0 && $parentId === 0) {
                    if ($model->hasChildren($id)) {
                        \Response::error('该分类下存在子分类，不能设为顶级');
                    }
                }

                $slug = trim((string) \Input::post('slug', ''));
                if ($slug !== '' && $model->existsSlug($slug, $id)) {
                    \Response::error('别名已存在');
                }

                $sort = (int) \Input::post('sort', 100);
                $status = \Input::post('status', '1') === '1' ? 1 : 0;

                $rebateJson = $this->buildRebateJson();

                $model->update($id, [
                    'parent_id' => $parentId,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => (string) \Input::post('description', ''),
                    'icon' => (string) \Input::post('icon', ''),
                    'cover_image' => (string) \Input::post('cover_image', ''),
                    'sort' => $sort,
                    'seo_title' => (string) \Input::post('seo_title', ''),
                    'seo_keywords' => (string) \Input::post('seo_keywords', ''),
                    'seo_description' => (string) \Input::post('seo_description', ''),
                    'status' => $status,
                    'rebate_config' => $rebateJson,
                ]);

                $csrfToken = \Csrf::refresh();
                \Response::success('分类更新成功', ['csrf_token' => $csrfToken]);
            }

            if ($action === 'delete') {
                $id = (int) \Input::post('id', 0);
                if ($id <= 0) {
                    \Response::error('无效的分类ID');
                }
                if ($model->hasChildren($id)) {
                    \Response::error('该分类下存在子分类，请先删除子分类');
                }
                if (!$model->delete($id)) {
                    \Response::error('删除失败');
                }
                $csrfToken = \Csrf::refresh();
                \Response::success('删除成功', ['csrf_token' => $csrfToken]);
            }

            if ($action === 'batch_delete') {
                // 批量删除：支持 ids[] 或逗号分隔字符串
                $raw = \Input::post('ids', '');
                $ids = is_array($raw) ? $raw : array_filter(explode(',', (string) $raw));
                $ids = array_values(array_unique(array_map('intval', $ids)));
                $ids = array_filter($ids, fn($v) => $v > 0);
                if (empty($ids)) {
                    \Response::error('请选择要删除的分类');
                }

                // 最多 5 轮循环：每轮删无子的（或子已被删的），最终剩下的就是"有非选中子分类"的
                $prefix = \Database::prefix();
                $all = \Database::query("SELECT id, parent_id, name FROM {$prefix}goods_category WHERE id IN (" . implode(',', $ids) . ")");
                $byId = [];
                foreach ($all as $r) $byId[(int) $r['id']] = $r;

                $deleted = 0; $skipped = 0; $skippedNames = [];
                for ($round = 0; $round < 5 && !empty($ids); $round++) {
                    $remain = [];
                    foreach ($ids as $id) {
                        if ($model->hasChildren($id)) {
                            $remain[] = $id;
                            continue;
                        }
                        if ($model->delete($id)) {
                            $deleted++;
                        } else {
                            $skipped++;
                            $skippedNames[] = $byId[$id]['name'] ?? ('#' . $id);
                        }
                    }
                    if (count($remain) === count($ids)) break; // 没进展，剩下的都阻塞
                    $ids = $remain;
                }
                foreach ($ids as $id) {
                    $skipped++;
                    $skippedNames[] = ($byId[$id]['name'] ?? ('#' . $id)) . '（含子分类）';
                }

                $msg = "删除成功 {$deleted} 个";
                if ($skipped > 0) {
                    $msg .= "，跳过 {$skipped} 个：" . implode('、', array_slice($skippedNames, 0, 3)) . ($skipped > 3 ? '…' : '');
                }
                $csrfToken = \Csrf::refresh();
                \Response::success($msg, ['csrf_token' => $csrfToken, 'deleted' => $deleted, 'skipped' => $skipped]);
            }

            if ($action === 'toggle_status') {
                $id = (int) \Input::post('id', 0);
                if ($id <= 0) {
                    \Response::error('无效的分类ID');
                }

                $existing = $model->findById($id);
                if ($existing === null) {
                    \Response::error('分类不存在');
                }

                $newStatus = ((int) $existing['status'] === 1) ? 0 : 1;
                $model->update($id, ['status' => (string) $newStatus]);

                $csrfToken = \Csrf::refresh();
                \Response::success($newStatus === 1 ? '已启用' : '已禁用', ['csrf_token' => $csrfToken]);
            }

            if ($action === 'image') {
                if (empty($_FILES['file'])) {
                    \Response::error('请选择图片文件');
                }
                $uploader = new \UploadService();
                $result = $uploader->upload($_FILES['file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'goods_category');
                $csrfToken = \Csrf::refresh();
                \Response::success('上传成功', [
                    'csrf_token' => $csrfToken,
                    'url' => $result['url'],
                ]);
            }
        } catch (\RuntimeException $e) {
            \Response::error($e->getMessage());
        } catch (\Throwable $e) {
            \Response::error($e->getMessage());
        }
    }

    /**
     * 分类级返佣配置：前端按百分比录入（5 = 5%），落库转成万分位（500）。
     *
     * 空 = 未设置（不记该字段），0 = 明确"不返佣"（记 0），区分两种含义。
     * 总返佣上限 30%（l1 + l2），与商品级校验一致。
     *
     * @return string|null JSON 字符串；全部为空时返回 null
     */
    private function buildRebateJson(): ?string
    {
        $rebateArr = [];
        $l1Raw = trim((string) \Input::post('rebate_l1', ''));
        $l2Raw = trim((string) \Input::post('rebate_l2', ''));
        $l1Pct = 0.0; $l2Pct = 0.0;
        if ($l1Raw !== '') {
            $l1Pct = max(0.0, min(100.0, (float) $l1Raw));
            $rebateArr['l1'] = (int) round($l1Pct * 100);
        }
        if ($l2Raw !== '') {
            $l2Pct = max(0.0, min(100.0, (float) $l2Raw));
            $rebateArr['l2'] = (int) round($l2Pct * 100);
        }
        if (($l1Pct + $l2Pct) > 30.0) {
            \Response::error('返佣配置错误：一级 + 二级总返佣不得超过订单金额的 30%（当前 '
                . rtrim(rtrim(number_format($l1Pct + $l2Pct, 2), '0'), '.') . '%）');
        }
        $rebateMode = (string) \Input::post('rebate_mode', '');
        if (!in_array($rebateMode, ['amount', 'profit', ''], true)) {
            $rebateMode = '';
        }
        if ($rebateMode !== '') {
            $rebateArr['mode'] = $rebateMode;
        }

        // 全空则整行 null；任一字段被显式设置就落库
        return $rebateArr !== [] ? json_encode($rebateArr, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * 添加/编辑弹窗（iframe）。
     */
    private function renderPopup(\GoodsCategoryModel $model): void
    {
        $editId = (int) \Input::get('id', 0);
        $editCat = null;

        if ($editId > 0) {
            $editCat = $model->findById($editId);
        }

        $this->render('popup/goods_category', [
            'editCat'      => $editCat,
            'topLevelCats' => $model->getTopLevel(),
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        if (\Request::isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('goods_category');
            echo '</div>';
            return;
        }

        require_once EM_ROOT . '/include/lib/AdminLang.php';

        $user = $this->user();
        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $languages = (new \LanguageModel())->getEnabled();
        $emServerHardRestartPending = is_file(EM_ROOT . '/.server') && \WorkerHeartbeat::hostAlive();
        $adminContentView = dirname(__DIR__) . '/View/goods_category.php';

        require EM_ROOT . '/admin/view/index.php';
    }
}
