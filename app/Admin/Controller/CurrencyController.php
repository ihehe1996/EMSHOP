<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 货币配置（/admin/currency）。
 *
 * 设计说明（方案 A 多币种）：
 *   - 主货币一旦设定不可切换（数据库里所有金额都以主货币 ×1000000 存储）
 *   - 其他货币仅用于前台展示换算（rate 约定：1 目标 = rate 主货币）
 *
 * 动作：list / add / edit / delete / toggle / set_frontend_default / init_primary
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class CurrencyController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        $this->renderList();
    }

    /**
     * API / POST 处理。
     */
    private function handlePost(): void
    {
        $currencyModel = $this->currency;

        // 统一用 _action 参数（和项目其他页面保持一致）
        $apiAction = (string) $this->input->post('_action', '');
        $csrf = (string) $this->input->post('csrf_token', '');

        // list 请求不需要 CSRF 校验（layui 表格刷新时不带 csrf）
        if ($apiAction === 'list') {
            $keyword = trim((string) $this->input->post('keyword', ''));
            $items = $currencyModel->all();
            $primary = $currencyModel->getPrimary();
            $primaryCode = $primary ? (string) $primary['code'] : '';
            $primaryLocked = $primary !== null;

            $formatted = [];
            foreach ($items as $item) {
                // 关键词模糊匹配代码或名称
                if ($keyword !== '') {
                    $code = strtoupper((string) $item['code']);
                    $name = mb_strtolower((string) $item['name'], 'UTF-8');
                    $kw = mb_strtolower($keyword, 'UTF-8');
                    if (strpos($code, strtoupper($keyword)) === false && strpos($name, $kw) === false) {
                        continue;
                    }
                }
                $formatted[] = [
                    'id' => (int) $item['id'],
                    'code' => htmlspecialchars((string) $item['code'], ENT_QUOTES, 'UTF-8'),
                    'name' => htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8'),
                    'symbol' => htmlspecialchars((string) $item['symbol'], ENT_QUOTES, 'UTF-8'),
                    'rate' => (float) $item['rate'] / 1000000,
                    'is_primary' => (int) $item['is_primary'] === 1,
                    'is_frontend_default' => (int) ($item['is_frontend_default'] ?? 0) === 1,
                    'enabled' => (int) ($item['enabled'] ?? 1) === 1 ? 'y' : 'n',
                ];
            }
            $this->response->success('ok', [
                'csrf_token' => $this->csrf->refresh(),
                'primary_code' => $primaryCode,
                'primary_locked' => $primaryLocked,
                'data' => $formatted,
            ]);
        }

        // 其它操作需要完整 CSRF 校验
        if (!$this->csrf->validate($csrf)) {
            $this->response->error('请求已失效，请刷新页面后重试', ['csrf_token' => $this->csrf->refresh()]);
        }
        $newToken = $this->csrf->refresh();

        // ---------- 添加货币 ----------
        if ($apiAction === 'add') {
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

            if ($currencyModel->add($code, $name, $symbol, $rate)) {
                $this->response->success('货币已添加', ['csrf_token' => $newToken]);
            }
            $this->response->error('添加货币失败', ['csrf_token' => $newToken]);
        }

        // ---------- 更新货币 ----------
        if ($apiAction === 'edit') {
            $id = (int) $this->input->post('id', 0);
            $name = trim((string) $this->input->post('name', ''));
            $symbol = trim((string) $this->input->post('symbol', ''));
            $rate = max(0.0, (float) $this->input->post('rate', 1));

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
            // 主货币只允许改名称和符号；rate 恒为 1 不能改
            if ((int) $editCurrency['is_primary'] === 1) {
                $data = ['name' => $name, 'symbol' => $symbol];
            } else {
                if ($rate <= 0) {
                    $this->response->error('汇率必须大于 0', ['csrf_token' => $newToken]);
                }
                $data = ['name' => $name, 'symbol' => $symbol, 'rate' => $rate];
            }

            if ($currencyModel->update($id, $data)) {
                $this->response->success('货币已更新', ['csrf_token' => $newToken]);
            }
            $this->response->error('更新货币失败', ['csrf_token' => $newToken]);
        }

        // ---------- 删除货币 ----------
        if ($apiAction === 'delete') {
            $id = (int) $this->input->post('id', 0);
            if ($id <= 0) {
                $this->response->error('无效的货币 ID', ['csrf_token' => $newToken]);
            }
            if (!$currencyModel->delete($id)) {
                $this->response->error('删除失败，主货币不可删除', ['csrf_token' => $newToken]);
            }
            $this->response->success('货币已删除', ['csrf_token' => $newToken]);
        }

        // ---------- 切换启用状态 ----------
        if ($apiAction === 'toggle') {
            $id = (int) $this->input->post('id', 0);
            if ($id <= 0) {
                $this->response->error('无效的货币 ID', ['csrf_token' => $newToken]);
            }
            try {
                if (!$currencyModel->toggle($id)) {
                    $this->response->error('切换状态失败，主货币不可禁用', ['csrf_token' => $newToken]);
                }
            } catch (\RuntimeException $e) {
                $this->response->error($e->getMessage(), ['csrf_token' => $newToken]);
            }
            $this->response->success('状态已更新', ['csrf_token' => $newToken]);
        }

        // ---------- 设置"前台默认" ----------
        // 访客首次进来没选过币种时展示这个币；和主货币（记账基准）解耦
        if ($apiAction === 'set_frontend_default') {
            $id = (int) $this->input->post('id', 0);
            if ($id <= 0) {
                $this->response->error('无效的货币 ID', ['csrf_token' => $newToken]);
            }
            try {
                if (!$currencyModel->setFrontendDefault($id)) {
                    $this->response->error('设置失败', ['csrf_token' => $newToken]);
                }
            } catch (\RuntimeException $e) {
                $this->response->error($e->getMessage(), ['csrf_token' => $newToken]);
            }
            $this->response->success('已设为前台默认', ['csrf_token' => $newToken]);
        }

        // ---------- 初始化主货币 ----------
        if ($apiAction === 'init_primary') {
            $id = (int) $this->input->post('id', 0);
            if ($id <= 0) {
                $this->response->error('无效的货币 ID', ['csrf_token' => $newToken]);
            }
            if ($currencyModel->getPrimary() !== null) {
                $this->response->error('主货币已初始化，不可再切换', ['csrf_token' => $newToken]);
            }
            if (!$currencyModel->setPrimary($id)) {
                $this->response->error('初始化主货币失败', ['csrf_token' => $newToken]);
            }
            $this->response->success('主货币已初始化', ['csrf_token' => $newToken]);
        }

        $this->response->error('未知操作', ['csrf_token' => $newToken]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        $currencyModel = $this->currency;

        // 兜底：如果表里还没有主货币（InstallService 会写，理论上不会进这里），补一条 CNY
        $primary = $currencyModel->getPrimary();
        if ($primary === null && $currencyModel->all() === []) {
            $currencyModel->add('CNY', '人民币', '¥', 1);
            $cny = $currencyModel->getByCode('CNY');
            if ($cny !== null) {
                $currencyModel->setPrimary((int) $cny['id']);
            }
        }

        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('currency');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/currency.php', $this->user(), get_defined_vars());
    }
}
