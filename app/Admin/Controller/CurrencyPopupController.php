<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 货币添加/编辑弹窗（{入口}/currency_popup）。
 *
 * GET  ?mode=add|edit[&id=] —— 渲染弹窗视图
 * POST  _action=add|edit   —— 保存
 *
 * 不再提供"设为主货币"能力 —— 主货币一旦设定不可切换。
 */
final class CurrencyPopupController extends BaseAdminController
{
    public function index(): void
    {
        $currencyModel = $this->currency;

        $mode = (string) $this->input->get('mode', 'add');
        $editId = (int) $this->input->get('id', 0);

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
        if ($this->request->isPost()) {
            $this->handlePost($currencyModel);
            return;
        }

        // ========== 渲染视图 ==========
        $this->render('popup/currency', [
            'mode'             => $mode,
            'currency'         => $currency,
            'isPrimary'        => $isPrimary,
            'primaryCode'      => $primaryCode,
            'primaryName'      => $primaryName,
            'currencyDisplay'  => '',
            'csrfToken'        => $this->csrf->token(),
            'pageTitle'        => $mode === 'edit' ? '编辑货币' : '添加货币',
            'esc'              => function (string $s): string {
                return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
            },
        ]);
    }

    /**
     * 保存添加 / 编辑。
     */
    private function handlePost($currencyModel): void
    {
        $subAction = (string) $this->input->post('_action', '');
        $csrf = (string) $this->input->post('csrf_token', '');

        if (!$this->csrf->validate($csrf)) {
            $this->response->error('请求已失效，请刷新页面后重试', ['csrf_token' => $this->csrf->refresh()]);
        }
        $newToken = $this->csrf->refresh();

        // ---------- 添加 ----------
        if ($subAction === 'add') {
            $code = strtoupper(trim((string) $this->input->post('code', '')));
            $name = trim((string) $this->input->post('name', ''));
            $symbol = trim((string) $this->input->post('symbol', ''));
            $rate = max(0.0, (float) $this->input->post('rate', 1));

            if ($code === '' || !preg_match('/^[A-Z]{3}$/', $code)) {
                $this->response->error('货币代码必须为 3 位大写字母，如 USD', ['csrf_token' => $newToken]);
            }
            if ($name === '') {
                $this->response->error('货币名称不能为空', ['csrf_token' => $newToken]);
            }
            if ($rate <= 0) {
                $this->response->error('汇率必须大于 0', ['csrf_token' => $newToken]);
            }
            if ($currencyModel->getByCode($code) !== null) {
                $this->response->error('货币代码已存在', ['csrf_token' => $newToken]);
            }
            if (!$currencyModel->add($code, $name, $symbol, $rate)) {
                $this->response->error('添加货币失败', ['csrf_token' => $newToken]);
            }
            $this->response->success('货币已添加', ['csrf_token' => $newToken]);
        }

        // ---------- 编辑 ----------
        if ($subAction === 'edit') {
            $id = (int) $this->input->post('id', 0);
            $name = trim((string) $this->input->post('name', ''));
            $symbol = trim((string) $this->input->post('symbol', ''));
            $rate = max(0.0, (float) $this->input->post('rate', 1));
            $enabled = $this->input->post('enabled', '') !== '' ? 1 : 0;

            if ($id <= 0) {
                $this->response->error('无效的货币 ID', ['csrf_token' => $newToken]);
            }
            if ($name === '') {
                $this->response->error('货币名称不能为空', ['csrf_token' => $newToken]);
            }

            $editCurrency = $currencyModel->getById($id);
            if ($editCurrency === null) {
                $this->response->error('货币不存在', ['csrf_token' => $newToken]);
            }

            if ((int) $editCurrency['is_primary'] === 1) {
                // 主货币：只能改名称和符号；rate 恒为 1；始终启用
                $currencyModel->update($id, ['name' => $name, 'symbol' => $symbol]);
            } else {
                if ($rate <= 0) {
                    $this->response->error('汇率必须大于 0', ['csrf_token' => $newToken]);
                }
                $currencyModel->update($id, [
                    'name' => $name,
                    'symbol' => $symbol,
                    'rate' => $rate,
                    'enabled' => $enabled,
                ]);
            }

            $this->response->success('货币已更新', ['csrf_token' => $newToken]);
        }

        $this->response->error('未知操作', ['csrf_token' => $newToken]);
    }
}
