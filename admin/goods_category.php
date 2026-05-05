<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 商品分类管理控制器。
 *
 * 仅支持2级分类：顶级 和 二级（归属某顶级）。
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

$model = new GoodsCategoryModel();

$isPopup = Input::get('_popup', '') === '1';

// POST 请求处理
if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');

        // list 请求不需要 CSRF 验证
        if ($action !== 'list') {
            $csrf = (string) Input::post('csrf_token', '');
            if (!Csrf::validate($csrf)) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        switch ($action) {
            case 'list':
                $keyword = trim((string) Input::post('keyword', ''));
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
                    'csrf_token' => Csrf::token(),
                ], JSON_UNESCAPED_UNICODE);
                return;

            case 'create': {
                $name = trim((string) Input::post('name', ''));
                if ($name === '') {
                    Response::error('分类名称不能为空');
                }
                if (mb_strlen($name) > 100) {
                    Response::error('分类名称最多100个字符');
                }

                $parentId = (int) Input::post('parent_id', 0);

                $slug = trim((string) Input::post('slug', ''));
                if ($slug !== '' && $model->existsSlug($slug)) {
                    Response::error('别名已存在');
                }

                $sort = (int) Input::post('sort', 100);
                $status = Input::post('status', '1') === '1' ? 1 : 0;

                // 分类级返佣：前端按百分比录入（5 = 5%），落库转成万分位（500）
                // 空 = 未设置（不记该字段），0 = 明确"不返佣"（记 0），区分两种含义
                // 总返佣上限 30%（l1 + l2），与商品级校验一致
                $rebateArr = [];
                $l1Raw = trim((string) Input::post('rebate_l1', ''));
                $l2Raw = trim((string) Input::post('rebate_l2', ''));
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
                    Response::error('返佣配置错误：一级 + 二级总返佣不得超过订单金额的 30%（当前 '
                        . rtrim(rtrim(number_format($l1Pct + $l2Pct, 2), '0'), '.') . '%）');
                }
                $rebateMode = (string) Input::post('rebate_mode', '');
                if (!in_array($rebateMode, ['amount', 'profit', ''], true)) $rebateMode = '';
                if ($rebateMode !== '') $rebateArr['mode'] = $rebateMode;
                // 全空则整行 null；任一字段被显式设置就落库
                $rebateJson = $rebateArr !== [] ? json_encode($rebateArr, JSON_UNESCAPED_UNICODE) : null;

                $id = $model->create([
                    'parent_id' => $parentId,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => (string) Input::post('description', ''),
                    'icon' => (string) Input::post('icon', ''),
                    'cover_image' => (string) Input::post('cover_image', ''),
                    'sort' => $sort,
                    'seo_title' => (string) Input::post('seo_title', ''),
                    'seo_keywords' => (string) Input::post('seo_keywords', ''),
                    'seo_description' => (string) Input::post('seo_description', ''),
                    'status' => $status,
                    'rebate_config' => $rebateJson,
                ]);

                $csrfToken = Csrf::refresh();
                Response::success('分类创建成功', ['csrf_token' => $csrfToken]);
                break;
            }

            case 'update': {
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的分类ID');
                }

                $existing = $model->findById($id);
                if ($existing === null) {
                    Response::error('分类不存在');
                }

                $name = trim((string) Input::post('name', ''));
                if ($name === '') {
                    Response::error('分类名称不能为空');
                }
                if (mb_strlen($name) > 100) {
                    Response::error('分类名称最多100个字符');
                }

                $parentId = (int) Input::post('parent_id', 0);

                // 二级分类不能变成顶级（避免混乱），除非本身就没有子分类
                if ($existing['parent_id'] > 0 && $parentId === 0) {
                    if ($model->hasChildren($id)) {
                        Response::error('该分类下存在子分类，不能设为顶级');
                    }
                }

                $slug = trim((string) Input::post('slug', ''));
                if ($slug !== '' && $model->existsSlug($slug, $id)) {
                    Response::error('别名已存在');
                }

                $sort = (int) Input::post('sort', 100);
                $status = Input::post('status', '1') === '1' ? 1 : 0;

                // 分类级返佣：前端按百分比录入（5 = 5%），落库转成万分位（500）
                // 空 = 未设置（不记该字段），0 = 明确"不返佣"（记 0），区分两种含义
                // 总返佣上限 30%（l1 + l2），与商品级校验一致
                $rebateArr = [];
                $l1Raw = trim((string) Input::post('rebate_l1', ''));
                $l2Raw = trim((string) Input::post('rebate_l2', ''));
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
                    Response::error('返佣配置错误：一级 + 二级总返佣不得超过订单金额的 30%（当前 '
                        . rtrim(rtrim(number_format($l1Pct + $l2Pct, 2), '0'), '.') . '%）');
                }
                $rebateMode = (string) Input::post('rebate_mode', '');
                if (!in_array($rebateMode, ['amount', 'profit', ''], true)) $rebateMode = '';
                if ($rebateMode !== '') $rebateArr['mode'] = $rebateMode;
                // 全空则整行 null；任一字段被显式设置就落库
                $rebateJson = $rebateArr !== [] ? json_encode($rebateArr, JSON_UNESCAPED_UNICODE) : null;

                $model->update($id, [
                    'parent_id' => $parentId,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => (string) Input::post('description', ''),
                    'icon' => (string) Input::post('icon', ''),
                    'cover_image' => (string) Input::post('cover_image', ''),
                    'sort' => $sort,
                    'seo_title' => (string) Input::post('seo_title', ''),
                    'seo_keywords' => (string) Input::post('seo_keywords', ''),
                    'seo_description' => (string) Input::post('seo_description', ''),
                    'status' => $status,
                    'rebate_config' => $rebateJson,
                ]);

                $csrfToken = Csrf::refresh();
                Response::success('分类更新成功', ['csrf_token' => $csrfToken]);
                break;
            }

            case 'delete': {
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的分类ID');
                }

                if ($model->hasChildren($id)) {
                    Response::error('该分类下存在子分类，请先删除子分类');
                }

                if (!$model->delete($id)) {
                    Response::error('删除失败');
                }

                $csrfToken = Csrf::refresh();
                Response::success('删除成功', ['csrf_token' => $csrfToken]);
                break;
            }

            case 'batch_delete': {
                // 批量删除：支持 ids[] 或逗号分隔字符串
                $raw = Input::post('ids', '');
                $ids = is_array($raw) ? $raw : array_filter(explode(',', (string) $raw));
                $ids = array_values(array_unique(array_map('intval', $ids)));
                $ids = array_filter($ids, fn($v) => $v > 0);
                if (empty($ids)) {
                    Response::error('请选择要删除的分类');
                }

                // 最多 5 轮循环：每轮删无子的（或子已被删的），最终剩下的就是"有非选中子分类"的
                $prefix = Database::prefix();
                $all = Database::query("SELECT id, parent_id, name FROM {$prefix}goods_category WHERE id IN (" . implode(',', $ids) . ")");
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
                $csrfToken = Csrf::refresh();
                Response::success($msg, ['csrf_token' => $csrfToken, 'deleted' => $deleted, 'skipped' => $skipped]);
                break;
            }

            case 'toggle_status': {
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的分类ID');
                }

                $existing = $model->findById($id);
                if ($existing === null) {
                    Response::error('分类不存在');
                }

                $newStatus = ((int) $existing['status'] === 1) ? 0 : 1;
                $model->update($id, ['status' => (string) $newStatus]);

                $csrfToken = Csrf::refresh();
                Response::success($newStatus === 1 ? '已启用' : '已禁用', ['csrf_token' => $csrfToken]);
                break;
            }

            case 'image': {
                if (empty($_FILES['file'])) {
                    Response::error('请选择图片文件');
                }
                $uploader = new UploadService();
                $result = $uploader->upload($_FILES['file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'goods_category');
                $csrfToken = Csrf::refresh();
                Response::success('上传成功', [
                    'csrf_token' => $csrfToken,
                    'url' => $result['url'],
                ]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error('系统繁忙，请稍后再试');
    }
}

// 弹窗模式
if ($isPopup) {
    $editId = (int) Input::get('id', 0);
    $editCat = null;

    if ($editId > 0) {
        $editCat = $model->findById($editId);
    }

    $topLevelCats = $model->getTopLevel();

    $csrfToken = Csrf::token();
    include __DIR__ . '/view/popup/goods_category.php';
    return;
}

// 正常模式
$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/goods_category.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/goods_category.php';
    require __DIR__ . '/index.php';
}
