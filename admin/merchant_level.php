<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 商户等级管理。
 *
 * 输入输出约定：
 *   - 费率字段（self_goods_fee_rate / withdraw_fee_rate）
 *     前端以百分比展示/录入（例 "5"），库里存万分位（500）
 *   - price 前端以"元"录入（例 "99.00"），库里存 ×1000000
 *   - 布尔开关（allow_*）前端 checkbox 勾选为 "1"，未勾选不提交 —— 后端统一兜底为 0
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

require EM_ROOT . '/include/model/MerchantLevelModel.php';

/**
 * 把前端 "5"（百分数）转换成数据库万分位 "500"。
 */
function mlRate2db($input): int
{
    $v = (float) $input;
    if ($v < 0) $v = 0;
    if ($v > 100) $v = 100;
    return (int) round($v * 100);
}

/**
 * 数据库万分位 "500" → 前端百分数 "5"
 */
function mlRate2view(int $raw): string
{
    return rtrim(rtrim(number_format($raw / 100, 2, '.', ''), '0'), '.');
}

/**
 * "99.50" 元 → 数据库 ×1000000
 */
function mlPrice2db($input): int
{
    $v = (float) $input;
    if ($v < 0) $v = 0;
    return (int) round($v * 1000000);
}

function mlPrice2view(int $raw): string
{
    return number_format($raw / 1000000, 2, '.', '');
}

/**
 * 展开一条记录，把 DB 格式转成视图格式，追加 *_view 字段。
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function mlRowForView(array $row): array
{
    $row['price_view'] = mlPrice2view((int) $row['price']);
    $row['self_goods_fee_rate_view'] = mlRate2view((int) $row['self_goods_fee_rate']);
    $row['withdraw_fee_rate_view'] = mlRate2view((int) $row['withdraw_fee_rate']);
    return $row;
}

// ============================================================
// POST
// ============================================================
if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');

        if ($action !== 'list') {
            if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        $model = new MerchantLevelModel();

        switch ($action) {
            case 'list':
                $keyword = trim((string) Input::post('keyword', ''));
                $rows = $model->getAll($keyword);
                $rows = array_map('mlRowForView', $rows);
                Response::success('', [
                    'data' => array_values($rows),
                    'total' => count($rows),
                    'csrf_token' => Csrf::token(),
                ]);
                break;

            case 'create':
            case 'update':
                $id = (int) Input::post('id', 0);
                $isEdit = $action === 'update';

                $name = trim((string) Input::post('name', ''));
                if ($name === '' || mb_strlen($name) > 64) {
                    Response::error('等级名称长度需在 1~64 字符');
                }
                if ($model->existsName($name, $isEdit ? $id : 0)) {
                    Response::error('等级名称已被占用');
                }

                $data = [
                    'name' => $name,
                    'price' => mlPrice2db(Input::post('price', 0)),
                    'self_goods_fee_rate' => mlRate2db(Input::post('self_goods_fee_rate', 0)),
                    'withdraw_fee_rate' => mlRate2db(Input::post('withdraw_fee_rate', 0)),
                    'allow_subdomain' => (int) Input::post('allow_subdomain', 0) === 1 ? 1 : 0,
                    'allow_custom_domain' => (int) Input::post('allow_custom_domain', 0) === 1 ? 1 : 0,
                    'allow_self_goods' => (int) Input::post('allow_self_goods', 0) === 1 ? 1 : 0,
                    'sort' => (int) Input::post('sort', 100),
                    'is_enabled' => (int) Input::post('is_enabled', 1) === 1 ? 1 : 0,
                ];

                if ($isEdit) {
                    if ($model->findById($id) === null) {
                        Response::error('等级不存在');
                    }
                    $model->update($id, $data);
                    Response::success('更新成功', ['csrf_token' => Csrf::refresh()]);
                } else {
                    $newId = $model->create($data);
                    Response::success('创建成功', [
                        'id' => $newId,
                        'csrf_token' => Csrf::refresh(),
                    ]);
                }
                break;

            case 'delete':
                $id = (int) Input::post('id', 0);
                if ($id <= 0 || $model->findById($id) === null) {
                    Response::error('等级不存在');
                }
                if ($model->countMerchants($id) > 0) {
                    Response::error('该等级下还有商户，无法删除');
                }
                $model->softDelete($id);
                Response::success('删除成功', ['csrf_token' => Csrf::refresh()]);
                break;

            case 'toggle':
                $id = (int) Input::post('id', 0);
                $newVal = $model->toggle($id);
                if ($newVal < 0) {
                    Response::error('等级不存在');
                }
                Response::success('状态已更新', ['csrf_token' => Csrf::refresh(), 'enabled' => $newVal]);
                break;

            // 单个权限字段的点击切换（列表页标签互动）
            // field 必须在白名单内，防止写入任意列
            case 'toggle_perm':
                $id = (int) Input::post('id', 0);
                $field = (string) Input::post('field', '');
                $allowedFields = ['allow_subdomain', 'allow_custom_domain', 'allow_self_goods'];
                if (!in_array($field, $allowedFields, true)) {
                    Response::error('不支持的权限字段');
                }
                $row = $model->findById($id);
                if ($row === null) {
                    Response::error('等级不存在');
                }
                $new = (int) ($row[$field] ?? 0) === 1 ? 0 : 1;
                $model->update($id, [$field => $new]);
                Response::success('已更新', [
                    'csrf_token' => Csrf::refresh(),
                    'field' => $field,
                    'value' => $new,
                ]);
                break;

            default:
                Response::error('未知操作');
        }
    } catch (Throwable $e) {
        Response::error($e->getMessage() ?: '系统繁忙，请稍后再试');
    }
}

// ============================================================
// 弹窗模式
// ============================================================
$isPopup = Input::get('_popup', '') === '1';
if ($isPopup) {
    $editId = (int) Input::get('id', 0);
    $editLevel = null;
    if ($editId > 0) {
        $model = new MerchantLevelModel();
        $editLevel = $model->findById($editId);
        if ($editLevel !== null) {
            $editLevel = mlRowForView($editLevel);
        }
    }
    $isEdit = $editLevel !== null;
    $pageTitle = $isEdit ? '编辑商户等级' : '添加商户等级';

    $esc = function (string $str): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    };

    include __DIR__ . '/view/popup/merchant_level.php';
    return;
}

// ============================================================
// 正常模式
// ============================================================
$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/merchant_level.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/merchant_level.php';
    require __DIR__ . '/index.php';
}
