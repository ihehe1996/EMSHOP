<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 优惠券管理（后台）。
 *
 * 路由：
 *   GET  /admin/coupon.php            列表页
 *   GET  /admin/coupon.php?_popup=1[&id=N]   编辑弹窗（新增/编辑共用）
 *   POST /admin/coupon.php            AJAX 操作（_action = list|create|update|delete|toggle）
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

$model = new CouponModel();

// ============================================================
// POST 请求处理
// ============================================================
if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');

        // list 接口不校验 CSRF（GET 幂等查询）
        if ($action !== 'list') {
            $csrf = (string) Input::post('csrf_token', '');
            if (!Csrf::validate($csrf)) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        switch ($action) {
            case 'list':
                $keyword = trim((string) Input::post('keyword', ''));
                $type    = trim((string) Input::post('type', ''));
                $enabled = Input::post('enabled', '');
                $page    = max(1, (int) Input::post('page', 1));
                $perPage = max(1, min(100, (int) Input::post('limit', 20)));

                $result = $model->paginate(
                    ['keyword' => $keyword, 'type' => $type, 'enabled' => $enabled],
                    $page,
                    $perPage
                );
                Response::success('', [
                    'data'       => $result['list'],
                    'total'      => $result['total'],
                    'csrf_token' => Csrf::token(),
                ]);
                break;

            case 'create':
            case 'update':
                $id = (int) Input::post('id', 0);
                $data = collectCouponInput();

                // 通用校验
                if ($data['name'] === '')        Response::error('名称不能为空');
                if ($data['type'] === '')        Response::error('请选择类型');
                if (!in_array($data['type'], array_keys(CouponModel::typeOptions()), true))   Response::error('未知类型');
                if (!in_array($data['apply_scope'], array_keys(CouponModel::scopeOptions()), true)) Response::error('未知适用范围');

                if ($action === 'create') {
                    // 新增：用 code_prefix + generate_count 批量生成随机后缀的券
                    $prefix = trim((string) Input::post('code_prefix', 'EM'));
                    if ($prefix === '') $prefix = 'EM';
                    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $prefix)) Response::error('券码前缀只能含字母/数字/下划线/短横线');
                    if (strlen($prefix) > 16) Response::error('券码前缀过长（最多 16 个字符）');

                    $countInput = trim((string) Input::post('generate_count', ''));
                    $count = $countInput === '' ? 1 : max(1, (int) $countInput);
                    if ($count > 1000) Response::error('单次最多生成 1000 张');

                    $generated = [];
                    $createdIds = [];
                    // 逐张尝试生成；后缀碰撞时重试，总尝试上限避免死循环
                    $attempts = 0;
                    $maxAttempts = $count * 10 + 20;
                    while (count($createdIds) < $count && $attempts++ < $maxAttempts) {
                        $code = $prefix . generateRandomCouponSuffix();
                        if ($model->existsCode($code)) continue;
                        $row = $data;
                        $row['code'] = $code;
                        $createdIds[] = $model->create($row);
                        $generated[] = $code;
                    }
                    if (count($createdIds) < $count) {
                        Response::error('生成失败：码空间可能已耗尽，请更换前缀或减少数量');
                    }
                    $csrfToken = Csrf::refresh();
                    Response::success('已生成 ' . count($createdIds) . ' 张优惠券', [
                        'codes'      => $generated,
                        'csrf_token' => $csrfToken,
                    ]);
                } else {
                    // 编辑：code 来自只读隐藏字段；不允许改 code
                    if ($data['code'] === '')        Response::error('券码不能为空');
                    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $data['code'])) Response::error('券码格式非法');
                    if ($model->existsCode($data['code'], $id)) Response::error('券码已存在');
                    if ($id <= 0 || $model->findById($id) === null) Response::error('优惠券不存在');
                    $model->update($id, $data);
                    $csrfToken = Csrf::refresh();
                    Response::success('更新成功', ['csrf_token' => $csrfToken]);
                }
                break;

            case 'delete':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) Response::error('无效的 ID');
                if ($model->findById($id) === null) Response::error('优惠券不存在');
                $model->softDelete($id);
                $csrfToken = Csrf::refresh();
                Response::success('删除成功', ['csrf_token' => $csrfToken]);
                break;

            // 批量软删除：接收 ids[]（或 csv 字符串兜底），逐条调 softDelete
            //   - 不存在 / 已软删的 id 静默跳过，不让一两条失败阻塞整批
            //   - 只返回实际删除成功的条数给前端
            case 'batch_delete': {
                $raw = Input::post('ids', '');
                $ids = [];
                if (is_array($raw)) {
                    foreach ($raw as $v) { $v = (int) $v; if ($v > 0) $ids[] = $v; }
                } else {
                    foreach (explode(',', (string) $raw) as $v) {
                        $v = (int) trim($v); if ($v > 0) $ids[] = $v;
                    }
                }
                $ids = array_values(array_unique($ids));
                if (!$ids) Response::error('请选择要删除的优惠券');

                $deleted = 0;
                foreach ($ids as $id) {
                    if ($model->findById($id) !== null && $model->softDelete($id)) {
                        $deleted++;
                    }
                }
                $csrfToken = Csrf::refresh();
                Response::success('已删除 ' . $deleted . ' 条', ['csrf_token' => $csrfToken]);
                break;
            }

            case 'toggle':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) Response::error('无效的 ID');
                if ($model->findById($id) === null) Response::error('优惠券不存在');
                $model->toggle($id);
                $csrfToken = Csrf::refresh();
                Response::success('状态已更新', ['csrf_token' => $csrfToken]);
                break;

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error('系统繁忙，请稍后再试');
    }
}

/**
 * 生成 8 位随机券码后缀（大写字母+数字，排除易混淆字符）。
 */
function generateRandomCouponSuffix(int $len = 8): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // 去掉 I O L 1 0
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/**
 * 收集编辑表单输入（新增/编辑共用）。
 */
function collectCouponInput(): array
{
    return [
        'code'              => trim((string) Input::post('code', '')),
        'name'              => trim((string) Input::post('name', '')),
        'title'             => trim((string) Input::post('title', '')),
        'description'       => trim((string) Input::post('description', '')),
        'type'              => trim((string) Input::post('type', 'fixed_amount')),
        'value'             => (string) Input::post('value', '0'),
        'min_amount'        => (string) Input::post('min_amount', '0'),
        'max_discount'      => (string) Input::post('max_discount', '0'),
        'apply_scope'       => trim((string) Input::post('apply_scope', 'all')),
        'apply_ids'         => Input::post('apply_ids', []),
        'start_at'          => trim((string) Input::post('start_at', '')) ?: null,
        'end_at'            => trim((string) Input::post('end_at', '')) ?: null,
        'total_usage_limit' => (int) Input::post('total_usage_limit', -1),
        'is_enabled'        => (int) Input::post('is_enabled', 1),
        'sort'              => (int) Input::post('sort', 100),
    ];
}

// ============================================================
// 弹窗模式：渲染新增/编辑弹窗
// ============================================================
$isPopup = Input::get('_popup', '') === '1';
if ($isPopup) {
    $editId = (int) Input::get('id', 0);
    $editRow = $editId > 0 ? $model->findById($editId) : null;
    $isEdit = $editRow !== null;
    $pageTitle = $isEdit ? '编辑优惠券' : '添加优惠券';
    $esc = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    include __DIR__ . '/view/popup/coupon_edit.php';
    return;
}

// ============================================================
// 列表页
// ============================================================
$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/coupon.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/coupon.php';
    require __DIR__ . '/index.php';
}
