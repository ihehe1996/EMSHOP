<?php

declare(strict_types=1);

/**
 * 货币弹窗控制器。
 *
 * 仅处理添加 / 编辑：
 *   GET  /admin/currency_popup.php?mode=add|edit[&id=] —— 渲染弹窗视图
 *   POST /admin/currency_popup.php  _action=add|edit   —— 保存
 *
 * ⚠️ 不再提供"设为主货币"能力 —— 主货币一旦设定不可切换（详见 include/lib/Currency.php 顶部）
 */
require __DIR__ . '/global.php';

adminRequireLogin();

$currencyModel = Currency::getInstance();

$mode = (string) Input::get('mode', 'add');
$editId = (int) Input::get('id', 0);
$csrfToken = Csrf::token();

// 旧的 $currencyDisplay（Config currency_display）已迁移到 em_currency.is_frontend_default
// 留空占位，避免引用该变量的老模板片段 undefined；新模板片段不再用它
$currencyDisplay = '';

$primaryCurrency = $currencyModel->getPrimary();
$primaryCode = $primaryCurrency ? (string) $primaryCurrency['code'] : 'CNY';
$primaryName = $primaryCurrency ? (string) $primaryCurrency['name'] : '人民币';

// 当前编辑的货币（添加模式下给默认空）
$currency = ['id' => 0, 'code' => '', 'name' => '', 'symbol' => '', 'rate' => 1000000, 'enabled' => 1];
$isPrimary = false;

if ($mode === 'edit') {
    if ($editId <= 0) {
        $mode = 'add';
    } else {
        $item = $currencyModel->getById($editId);
        if ($item !== null) {
            $currency = $item;
            $isPrimary = (int) $item['is_primary'] === 1;
        } else {
            $mode = 'add';
        }
    }
}

// ========== POST ==========
if (Request::isPost()) {
    $subAction = (string) Input::post('_action', '');
    $csrf = (string) Input::post('csrf_token', '');

    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试', ['csrf_token' => Csrf::refresh()]);
    }
    $newToken = Csrf::refresh();

    // ---------- 添加 ----------
    if ($subAction === 'add') {
        $code = strtoupper(trim((string) Input::post('code', '')));
        $name = trim((string) Input::post('name', ''));
        $symbol = trim((string) Input::post('symbol', ''));
        $rate = max(0.0, (float) Input::post('rate', 1));

        if ($code === '' || !preg_match('/^[A-Z]{3}$/', $code)) {
            Response::error('货币代码必须为 3 位大写字母，如 USD', ['csrf_token' => $newToken]);
        }
        if ($name === '') {
            Response::error('货币名称不能为空', ['csrf_token' => $newToken]);
        }
        if ($rate <= 0) {
            Response::error('汇率必须大于 0', ['csrf_token' => $newToken]);
        }
        if ($currencyModel->getByCode($code) !== null) {
            Response::error('货币代码已存在', ['csrf_token' => $newToken]);
        }
        if (!$currencyModel->add($code, $name, $symbol, $rate)) {
            Response::error('添加货币失败', ['csrf_token' => $newToken]);
        }
        // "前台默认"由列表页独立列管理（写 em_currency.is_frontend_default 字段）
        // 弹窗不再承担这个动作，所以这里不处理 set_display
        Response::success('货币已添加', ['csrf_token' => $newToken]);
    }

    // ---------- 编辑 ----------
    if ($subAction === 'edit') {
        $id = (int) Input::post('id', 0);
        $name = trim((string) Input::post('name', ''));
        $symbol = trim((string) Input::post('symbol', ''));
        $rate = max(0.0, (float) Input::post('rate', 1));
        $enabled = Input::post('enabled', '') !== '' ? 1 : 0;

        if ($id <= 0) {
            Response::error('无效的货币 ID', ['csrf_token' => $newToken]);
        }
        if ($name === '') {
            Response::error('货币名称不能为空', ['csrf_token' => $newToken]);
        }

        $editCurrency = $currencyModel->getById($id);
        if ($editCurrency === null) {
            Response::error('货币不存在', ['csrf_token' => $newToken]);
        }

        if ((int) $editCurrency['is_primary'] === 1) {
            // 主货币：只能改名称和符号；rate 恒为 1；始终启用
            $currencyModel->update($id, ['name' => $name, 'symbol' => $symbol]);
        } else {
            if ($rate <= 0) {
                Response::error('汇率必须大于 0', ['csrf_token' => $newToken]);
            }
            $currencyModel->update($id, [
                'name' => $name,
                'symbol' => $symbol,
                'rate' => $rate,
                'enabled' => $enabled,
            ]);
        }

        // "前台默认"由列表页独立列管理（写 em_currency.is_frontend_default 字段），弹窗不再处理
        Response::success('货币已更新', ['csrf_token' => $newToken]);
    }

    Response::error('未知操作', ['csrf_token' => $newToken]);
}

// ========== 渲染视图 ==========
$esc = function (string $s) use (&$esc): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

include __DIR__ . '/view/popup/header.php';
include __DIR__ . '/view/popup/currency.php';
include __DIR__ . '/view/popup/footer.php';
