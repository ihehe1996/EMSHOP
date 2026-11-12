<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 商户（分站）管理（{入口}/merchant）。
 *
 * 页面动作：
 *   list         列表
 *   search_user  按 username/email 搜索候选商户主（open 弹窗用）
 *   level_options 可选等级
 *   open         手动开通商户
 *   update       编辑已有商户
 *   toggle       启/禁
 *   delete       软删
 *
 * 弹窗：?_popup=open / ?_popup=edit
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class MerchantController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        $popup = (string) $this->input->get('_popup', '');
        if ($popup === 'open') {
            $this->renderOpenPopup();
            return;
        }
        if ($popup === 'edit') {
            $this->renderEditPopup();
            return;
        }

        $this->renderList();
    }

    /**
     * 处理后台 AJAX 动作；list / search_user 免 CSRF。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) $this->input->post('_action', '');

            if ($action !== 'list' && $action !== 'search_user') {
                if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
            }

            $model = app(\App\Model\MerchantModel::class);
            $levelModel = app(\App\Model\MerchantLevelModel::class);

            switch ($action) {
                case 'list':
                    $filter = [
                        'keyword'   => (string) $this->input->post('keyword', ''),
                        'status'    => $this->input->post('status', ''),
                        'level_id'  => (int) $this->input->post('level_id', 0),
                        'page'      => (int) $this->input->post('page', 1),
                        'page_size' => (int) $this->input->post('limit', 20),
                    ];
                    $result = $model->paginate($filter);
                    // 店铺余额转成元显示
                    foreach ($result['data'] as &$row) {
                        $row['shop_balance_view'] = number_format(((int) ($row['user_shop_balance'] ?? 0)) / 1000000, 2, '.', '');
                    }
                    unset($row);
                    $this->response->success('', [
                        'data' => $result['data'],
                        'total' => $result['total'],
                        'csrf_token' => $this->csrf->token(),
                    ]);
                    break;

                case 'search_user':
                    $kw = trim((string) $this->input->post('keyword', ''));
                    if ($kw === '') {
                        $this->response->success('', ['data' => []]);
                    }
                    $userTable = $this->db->prefix() . 'user';
                    $sql = 'SELECT `id`, `username`, `nickname`, `email`, `merchant_id`
                              FROM `' . $userTable . '`
                             WHERE (`username` LIKE ? OR `email` LIKE ? OR `nickname` LIKE ?)
                               AND `role` = \'user\'
                             ORDER BY `id` DESC LIMIT 20';
                    $rows = $this->db->query($sql, ['%' . $kw . '%', '%' . $kw . '%', '%' . $kw . '%']);
                    $this->response->success('', ['data' => $rows, 'csrf_token' => $this->csrf->token()]);
                    break;

                case 'level_options':
                    $levels = $levelModel->getEnabledList();
                    $this->response->success('', ['data' => $levels]);
                    break;

                case 'open':
                    $userId = (int) $this->input->post('user_id', 0);
                    if ($userId <= 0) {
                        $this->response->error('请选择要开通的用户');
                    }
                    $userTable = $this->db->prefix() . 'user';
                    $u = $this->db->fetchOne('SELECT `id`, `role` FROM `' . $userTable . '` WHERE `id` = ? LIMIT 1', [$userId]);
                    if ($u === null) {
                        $this->response->error('用户不存在');
                    }
                    if (($u['role'] ?? '') !== 'user') {
                        $this->response->error('只能为普通用户开通商户');
                    }

                    $levelId = (int) $this->input->post('level_id', 0);
                    if ($levelId <= 0 || $levelModel->findById($levelId) === null) {
                        $this->response->error('请选择商户等级');
                    }

                    $name = trim((string) $this->input->post('name', ''));
                    $nameErr = \DisplayName::validate($name, '店铺名');
                    if ($nameErr !== null) {
                        $this->response->error($nameErr);
                    }

                    $parentId = (int) $this->input->post('parent_id', 0);
                    if ($parentId > 0 && $model->findById($parentId) === null) {
                        $this->response->error('上级商户不存在');
                    }

                    $merchantId = $model->openMerchant([
                        'user_id'    => $userId,
                        'parent_id'  => $parentId,
                        'level_id'   => $levelId,
                        'name'       => $name,
                        'opened_via' => 'admin',
                        'status'     => 1,
                    ]);

                    $this->response->success('商户开通成功', [
                        'id' => $merchantId,
                        'csrf_token' => $this->csrf->refresh(),
                    ]);
                    break;

                case 'update':
                    $id = (int) $this->input->post('id', 0);
                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('商户不存在');
                    }

                    $data = [
                        'name' => trim((string) $this->input->post('name', '')),
                        'logo' => trim((string) $this->input->post('logo', '')),
                        'slogan' => trim((string) $this->input->post('slogan', '')),
                        'description' => trim((string) $this->input->post('description', '')),
                        'icp' => trim((string) $this->input->post('icp', '')),
                        'level_id' => (int) $this->input->post('level_id', (int) $existing['level_id']),
                        'subdomain' => strtolower(trim((string) $this->input->post('subdomain', ''))) ?: null,
                        'custom_domain' => strtolower(trim((string) $this->input->post('custom_domain', ''))) ?: null,
                    ];

                    $nameErr = \DisplayName::validate($data['name'], '店铺名');
                    if ($nameErr !== null) {
                        $this->response->error($nameErr);
                    }
                    $merchantLevelRow = $data['level_id'] > 0 ? $levelModel->findById($data['level_id']) : null;
                    if ($merchantLevelRow === null) {
                        $this->response->error('请选择商户等级');
                    }
                    if ($data['subdomain'] !== null) {
                        if (!preg_match('/^[a-z0-9]([a-z0-9\-]{1,30})[a-z0-9]$/', $data['subdomain'])) {
                            $this->response->error('二级域名格式不合法');
                        }
                        if ($model->existsSubdomain($data['subdomain'], $id)) {
                            $this->response->error('二级域名已被占用');
                        }
                        $domainErr = \MerchantDomainGuard::subdomainError($data['subdomain'], $id);
                        if ($domainErr !== '') {
                            $this->response->error($domainErr);
                        }
                    }
                    if ($data['custom_domain'] !== null) {
                        if (!preg_match('/^[a-z0-9]([a-z0-9\-\.]{1,199})$/', $data['custom_domain'])) {
                            $this->response->error('自定义域名格式不合法');
                        }
                        if ($model->existsCustomDomain($data['custom_domain'], $id)) {
                            $this->response->error('该域名已被占用');
                        }
                        $domainErr = \MerchantDomainGuard::customDomainError($data['custom_domain'], $id);
                        if ($domainErr !== '') {
                            $this->response->error($domainErr);
                        }
                        if ((int) ($merchantLevelRow['allow_custom_domain'] ?? 0) !== 1) {
                            $this->response->error('当前商户等级不允许绑定自定义域名');
                        }
                    }
                    $model->updateMerchant($id, $data);
                    $this->response->success('更新成功', ['csrf_token' => $this->csrf->refresh()]);
                    break;

                case 'toggle':
                    $id = (int) $this->input->post('id', 0);
                    $m = $model->findById($id);
                    if ($m === null) {
                        $this->response->error('商户不存在');
                    }
                    $newStatus = ((int) $m['status']) === 1 ? 0 : 1;
                    $model->setStatus($id, $newStatus);
                    $this->response->success('状态已更新', [
                        'status' => $newStatus,
                        'csrf_token' => $this->csrf->refresh(),
                    ]);
                    break;

                case 'delete':
                    $id = (int) $this->input->post('id', 0);
                    if ($model->findById($id) === null) {
                        $this->response->error('商户不存在');
                    }
                    $model->softDelete($id);
                    $this->response->success('删除成功', ['csrf_token' => $this->csrf->refresh()]);
                    break;

                default:
                    $this->response->error('未知操作');
            }
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage() ?: '系统繁忙，请稍后再试');
        }
    }

    /**
     * 开通商户弹窗。
     */
    private function renderOpenPopup(): void
    {
        $levels = app(\App\Model\MerchantLevelModel::class)->getEnabledList();

        $this->render('popup/merchant_open', [
            'levels'    => $levels,
            'pageTitle' => '开通商户',
        ]);
    }

    /**
     * 编辑商户弹窗。
     */
    private function renderEditPopup(): void
    {
        $editId = (int) $this->input->get('id', 0);
        $editRow = $editId > 0 ? app(\App\Model\MerchantModel::class)->findById($editId) : null;
        if ($editRow === null) {
            echo '<div style="padding:30px;text-align:center;color:#999">商户不存在</div>';
            return;
        }

        $this->render('popup/merchant_edit', [
            'levels'    => app(\App\Model\MerchantLevelModel::class)->getEnabledList(),
            'editRow'   => $editRow,
            'pageTitle' => '编辑商户：' . $editRow['name'],
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        $levels = app(\App\Model\MerchantLevelModel::class)->getEnabledList();

        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('merchant', ['levels' => $levels]);
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/merchant.php', $this->user(), get_defined_vars());
    }
}
