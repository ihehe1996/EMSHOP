<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 会员列表（/admin/user_list）。
 *
 * 管理 em_user 里的所有账号（含管理员）。当前登录账号自己不能禁用/删除。
 *
 * 动作：list / create / update / toggle / delete / batch_delete / image /
 *       open_merchant / set_level / balance_adjust
 * 弹窗：?_popup=1 编辑用户、balance 余额、user_level 等级、merchant_open 开通分站、
 *       merchant 商户详情
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class UserListController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        $popupType = (string) $this->input->get('_popup', '');
        if ($popupType === 'balance') {
            $this->renderBalancePopup();
            return;
        }
        if ($popupType === 'user_level') {
            $this->renderLevelPopup();
            return;
        }
        if ($popupType === 'merchant_open') {
            $this->renderMerchantOpenPopup();
            return;
        }
        if ($popupType === 'merchant') {
            $this->renderMerchantDetailPopup();
            return;
        }
        if ($popupType === '1') {
            $this->renderEditPopup();
            return;
        }

        $this->renderList();
    }

    /**
     * 处理后台 AJAX 动作；除 list 外都校验 CSRF。
     */
    private function handlePost(): void
    {
        $admin = $this->user();
        $currentAdminId = (int) ($admin['id'] ?? 0);

        try {
            $action = (string) $this->input->post('_action', '');

            if ($action !== 'list') {
                $csrf = (string) $this->input->post('csrf_token', '');
                if (!$this->csrf->validate($csrf)) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
            }

            switch ($action) {
                case 'list':
                    $page = (int) $this->input->post('page', 1);
                    $limit = (int) $this->input->post('limit', 15);
                    $keyword = trim((string) $this->input->post('keyword', ''));
                    $sortField = (string) $this->input->post('field', 'id');
                    $sortOrder = strtolower((string) $this->input->post('order', 'desc'));

                    if ($page < 1) {
                        $page = 1;
                    }
                    if ($limit < 1 || $limit > 100) {
                        $limit = 15;
                    }

                    $model = new \App\Model\UserListModel();
                    $result = $model->getAll($page, $limit, $keyword, $sortField, $sortOrder);

                    $this->response->success('', [
                        'data' => array_values($result['data']),
                        'total' => $result['total'],
                        'csrf_token' => $this->csrf->token(),
                    ]);
                    break;

                case 'create':
                    $username = trim((string) $this->input->post('username', ''));
                    if ($username === '') {
                        $this->response->error('用户名不能为空');
                    }
                    if (mb_strlen($username) > 50) {
                        $this->response->error('用户名最多50个字符');
                    }
                    if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
                        $this->response->error('用户名只能包含字母、数字、下划线和中文字符');
                    }

                    $password = (string) $this->input->post('password', '');
                    if (mb_strlen($password) < 6) {
                        $this->response->error('密码至少6个字符');
                    }
                    if (mb_strlen($password) > 50) {
                        $this->response->error('密码最多50个字符');
                    }

                    $email = trim((string) $this->input->post('email', ''));
                    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->response->error('邮箱格式不正确');
                    }

                    $nickname = trim((string) $this->input->post('nickname', ''));
                    $nicknameErr = \DisplayName::validate($nickname, '昵称');
                    if ($nicknameErr !== null) {
                        $this->response->error($nicknameErr);
                    }

                    $avatar = trim((string) $this->input->post('avatar', ''));

                    $status = $this->input->post('status', '1');
                    $status = $status === '1' ? 1 : 0;

                    $model = new \App\Model\UserListModel();

                    if ($model->existsUsername($username)) {
                        $this->response->error('该用户名已被占用');
                    }
                    if ($email !== '' && $model->existsEmail($email)) {
                        $this->response->error('该邮箱已被占用');
                    }
                    $mobile = trim((string) $this->input->post('mobile', ''));
                    if ($mobile !== '' && $model->existsMobile($mobile)) {
                        $this->response->error('该手机号已被占用');
                    }

                    $hasher = new \PasswordHash(8, true);
                    $hashedPassword = $hasher->HashPassword($password);

                    $userId = $model->create([
                        'username' => $username,
                        'password' => $hashedPassword,
                        'email' => $email,
                        'mobile' => $mobile,
                        'nickname' => $nickname,
                        'avatar' => $avatar,
                        'status' => $status,
                    ]);

                    if ($userId > 0) {
                        try {
                            \App\Service\UserExperienceService::applyRegisterBonus($userId);
                        } catch (\Throwable $e) {
                            // 初始经验发放失败不阻断创建
                        }
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('用户创建成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'update':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的用户ID');
                    }

                    $model = new \App\Model\UserListModel();
                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('用户不存在');
                    }

                    $email = trim((string) $this->input->post('email', ''));
                    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->response->error('邮箱格式不正确');
                    }

                    $nickname = trim((string) $this->input->post('nickname', ''));
                    $nicknameErr = \DisplayName::validate($nickname, '昵称');
                    if ($nicknameErr !== null) {
                        $this->response->error($nicknameErr);
                    }

                    $avatar = trim((string) $this->input->post('avatar', ''));

                    $status = $this->input->post('status', '1');
                    $status = $status === '1' ? 1 : 0;

                    // 编辑弹窗的状态开关会随表单一起提交，所以这里也是「禁用自己」的一条路径
                    if ($id === $currentAdminId && $status === 0) {
                        $this->response->error('不能禁用当前登录的账号');
                    }

                    if ($email !== '' && $model->existsEmail($email, $id)) {
                        $this->response->error('该邮箱已被其他用户占用');
                    }
                    $mobile = trim((string) $this->input->post('mobile', ''));
                    if ($mobile !== '' && $model->existsMobile($mobile, $id)) {
                        $this->response->error('该手机号已被其他用户占用');
                    }

                    $updateData = [
                        'email' => $email,
                        'mobile' => $mobile,
                        'nickname' => $nickname,
                        'avatar' => $avatar,
                        'status' => $status,
                    ];

                    $password = (string) $this->input->post('password', '');
                    if ($password !== '') {
                        if (mb_strlen($password) < 6) {
                            $this->response->error('密码至少6个字符');
                        }
                        if (mb_strlen($password) > 50) {
                            $this->response->error('密码最多50个字符');
                        }
                        $hasher = new \PasswordHash(8, true);
                        $updateData['password'] = $hasher->HashPassword($password);
                    }

                    $model->update($id, $updateData);

                    // 改的是自己：同步刷新当前会话（含密码指纹），避免下一次请求被踢下线
                    if ($id === $currentAdminId) {
                        $freshUser = (new \App\Model\UserModel())->findById($id);
                        if ($freshUser !== null) {
                            $auth = new \App\Service\AuthService();
                            $auth->refreshSession($freshUser);
                        }
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('操作成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'toggle':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的用户ID');
                    }

                    if ($id === $currentAdminId) {
                        $this->response->error('不能禁用当前登录的账号');
                    }

                    $model = new \App\Model\UserListModel();
                    if ($model->findById($id) === null) {
                        $this->response->error('用户不存在');
                    }

                    if (!$model->toggleStatus($id, $currentAdminId)) {
                        $this->response->error('状态更新失败，请刷新后重试');
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('状态已更新', ['csrf_token' => $csrfToken]);
                    break;

                case 'delete':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的用户ID');
                    }
                    if ($id === $currentAdminId) {
                        $this->response->error('不能删除当前登录的账号');
                    }

                    $model = new \App\Model\UserListModel();
                    if ($model->findById($id) === null) {
                        $this->response->error('用户不存在');
                    }

                    if (!$model->delete($id, $currentAdminId)) {
                        $this->response->error('删除失败，请刷新后重试');
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('删除成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'batch_delete':
                    $idsRaw = $this->input->post('ids', '');
                    $ids = array_filter(array_map('intval', explode(',', (string) $idsRaw)), function ($id) {
                        return $id > 0;
                    });
                    if ($ids === []) {
                        $this->response->error('请选择要删除的用户');
                    }

                    // 剔除自己：批量删除里混进当前登录账号时，删掉其余的更少意外
                    $skippedSelf = in_array($currentAdminId, $ids, true);
                    $ids = array_values(array_diff($ids, [$currentAdminId]));
                    if ($ids === []) {
                        $this->response->error('不能删除当前登录的账号');
                    }

                    $model = new \App\Model\UserListModel();
                    $deleted = $model->deleteBatch($ids, $currentAdminId);

                    $csrfToken = $this->csrf->refresh();
                    $msg = '已删除 ' . $deleted . ' 个用户';
                    if ($skippedSelf) {
                        $msg .= '，已跳过当前登录账号';
                    }
                    $this->response->success($msg, ['csrf_token' => $csrfToken, 'deleted' => $deleted]);
                    break;

                case 'image':
                    if (empty($_FILES['file'])) {
                        $this->response->error('请选择图片文件');
                    }
                    $uploader = new \App\Service\UploadService();
                    $result = $uploader->upload($_FILES['file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'avatar');
                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('上传成功', [
                        'csrf_token' => $csrfToken,
                        'url' => $result['url'],
                    ]);
                    break;

                case 'open_merchant':
                    $userId = (int) $this->input->post('user_id', 0);
                    if ($userId <= 0) {
                        $this->response->error('无效的用户ID');
                    }

                    $userModel = new \App\Model\UserListModel();
                    $u = $userModel->findById($userId);
                    if ($u === null) {
                        $this->response->error('用户不存在');
                    }
                    // 分站站长只给普通用户开
                    if ((string) ($u['role'] ?? '') !== 'user') {
                        $this->response->error('管理员账号不能开通分站');
                    }
                    if ((int) ($u['merchant_id'] ?? 0) > 0) {
                        $this->response->error('该用户已开通商户分站');
                    }

                    $levelModel = new \App\Model\MerchantLevelModel();
                    $levelId = (int) $this->input->post('level_id', 0);
                    $merchantLevelRow = $levelId > 0 ? $levelModel->findById($levelId) : null;
                    if ($merchantLevelRow === null) {
                        $this->response->error('请选择商户等级');
                    }

                    $name = trim((string) $this->input->post('name', ''));
                    $nameErr = \DisplayName::validate($name, '店铺名');
                    if ($nameErr !== null) {
                        $this->response->error($nameErr);
                    }

                    $subdomain = strtolower(trim((string) $this->input->post('subdomain', '')));
                    if ($subdomain === '') {
                        $this->response->error('请填写二级域名前缀');
                    }
                    if (!preg_match('/^[a-z0-9]([a-z0-9\-]{1,30})[a-z0-9]$/', $subdomain)) {
                        $this->response->error('二级域名格式不合法');
                    }

                    $customDomain = strtolower(trim((string) $this->input->post('custom_domain', '')));
                    $customDomain = $customDomain !== '' ? $customDomain : null;
                    if ($customDomain !== null) {
                        if (!preg_match('/^[a-z0-9]([a-z0-9\-\.]{1,199})$/', $customDomain)) {
                            $this->response->error('自定义域名格式不合法');
                        }
                        if ((int) ($merchantLevelRow['allow_custom_domain'] ?? 0) !== 1) {
                            $this->response->error('所选商户等级不允许绑定自定义域名');
                        }
                    }

                    $merchantModel = new \App\Model\MerchantModel();
                    if ($merchantModel->existsSubdomain($subdomain)) {
                        $this->response->error('二级域名已被占用');
                    }
                    $domainErr = \MerchantDomainGuard::subdomainError($subdomain);
                    if ($domainErr !== '') {
                        $this->response->error($domainErr);
                    }
                    if ($customDomain !== null && $merchantModel->existsCustomDomain($customDomain)) {
                        $this->response->error('该自定义域名已被占用');
                    }
                    if ($customDomain !== null) {
                        $domainErr = \MerchantDomainGuard::customDomainError($customDomain);
                        if ($domainErr !== '') {
                            $this->response->error($domainErr);
                        }
                    }

                    $openData = [
                        'user_id'    => $userId,
                        'parent_id'  => 0,
                        'level_id'   => $levelId,
                        'name'       => $name,
                        'subdomain'  => $subdomain,
                        'opened_via' => 'admin',
                        'status'     => 1,
                    ];
                    if ($customDomain !== null) {
                        $openData['custom_domain'] = $customDomain;
                    }

                    $merchantId = $merchantModel->openMerchant($openData);

                    $this->response->success('商户分站开通成功', [
                        'merchant_id' => $merchantId,
                        'csrf_token'  => $this->csrf->refresh(),
                    ]);
                    break;

                case 'set_level':
                    $userId = (int) $this->input->post('user_id', 0);
                    if ($userId <= 0) {
                        $this->response->error('无效的用户ID');
                    }

                    $model = new \App\Model\UserListModel();
                    $existing = $model->findById($userId);
                    if ($existing === null) {
                        $this->response->error('用户不存在');
                    }

                    $levelId = (int) $this->input->post('level_id', 0);
                    if ($levelId > 0) {
                        $lvRow = (new \App\Model\UserLevelModel())->findById($levelId);
                        if ($lvRow === null || (string) ($lvRow['enabled'] ?? 'y') !== 'y') {
                            $this->response->error('用户等级不存在或已被禁用');
                        }
                    }

                    $model->update($userId, ['level_id' => $levelId]);

                    $this->response->success('用户等级已更新', ['csrf_token' => $this->csrf->refresh()]);
                    break;

                // 余额调整
                case 'balance_adjust': {
                    $userId = (int) $this->input->post('user_id', 0);
                    $type = (string) $this->input->post('type', '');
                    $amountStr = trim((string) $this->input->post('amount', ''));
                    $remark = trim((string) $this->input->post('remark', ''));

                    if ($userId <= 0) {
                        $this->response->error('用户不存在');
                    }
                    if (!in_array($type, ['increase', 'decrease'], true)) {
                        $this->response->error('请选择操作类型');
                    }
                    if ($remark === '') {
                        $remark = '客服操作';
                    }

                    try {
                        $amount = $this->money->parse($amountStr);
                    } catch (\InvalidArgumentException $e) {
                        $this->response->error('请输入有效的金额');
                    }

                    $balanceLog = new \App\Model\UserBalanceLogModel();
                    $operatorId = (int) ($admin['id'] ?? 0);
                    $operatorName = (string) ($admin['nickname'] ?? $admin['username'] ?? '管理员');

                    if ($type === 'increase') {
                        $ok = $balanceLog->increase($userId, $amount, $remark, $operatorId, $operatorName);
                    } else {
                        $ok = $balanceLog->decrease($userId, $amount, $remark, $operatorId, $operatorName);
                    }

                    if (!$ok) {
                        $this->response->error($type === 'decrease' ? '余额不足或操作失败' : '操作失败');
                    }

                    // 返回最新余额
                    $freshUser = (new \App\Model\UserListModel())->findById($userId);
                    $newBalance = $freshUser ? bcdiv((string) ($freshUser['money'] ?? 0), '1000000', 2) : '0.00';

                    $this->response->success('操作成功', [
                        'balance'    => $newBalance,
                        'csrf_token' => $this->csrf->refresh(),
                    ]);
                    break;
                }

                default:
                    $this->response->error('未知操作');
            }
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage());
        }
    }

    /**
     * 余额调整弹窗。
     */
    private function renderBalancePopup(): void
    {
        $userId = (int) $this->input->get('id', 0);
        $balanceUser = (new \App\Model\UserListModel())->findById($userId);
        if (!$balanceUser) {
            exit('用户不存在');
        }

        $this->render('popup/user_balance', [
            'balanceUser'    => $balanceUser,
            'currentBalance' => bcdiv((string) ($balanceUser['money'] ?? 0), '1000000', 2),
            'pageTitle'      => '余额调整',
        ]);
    }

    /**
     * 修改用户等级弹窗。
     */
    private function renderLevelPopup(): void
    {
        $userId = (int) $this->input->get('id', 0);
        $levelUser = (new \App\Model\UserListModel())->findById($userId);
        if ($levelUser === null) {
            exit('用户不存在');
        }

        $allLevels = (new \App\Model\UserLevelModel())->getAll();
        $userLevels = array_values(array_filter($allLevels, static fn($l) => (string) ($l['enabled'] ?? 'y') === 'y'));

        $this->render('popup/user_level_change', [
            'levelUser'  => $levelUser,
            'userLevels' => $userLevels,
            'pageTitle'  => '修改用户等级',
        ]);
    }

    /**
     * 为用户开通商户分站弹窗。
     */
    private function renderMerchantOpenPopup(): void
    {
        $userId = (int) $this->input->get('user_id', 0);
        $presetUser = (new \App\Model\UserListModel())->findById($userId);
        if ($presetUser === null) {
            exit('用户不存在');
        }
        if ((int) ($presetUser['merchant_id'] ?? 0) > 0) {
            exit('该用户已开通商户');
        }

        $levels = (new \App\Model\MerchantLevelModel())->getEnabledList();
        if ($levels === []) {
            exit('请先在「商户等级」中创建并启用至少一个等级');
        }

        $this->render('popup/user_merchant_open', [
            'presetUser' => $presetUser,
            'levels'     => $levels,
            'pageTitle'  => '开通商户分站',
        ]);
    }

    /**
     * 商户详情弹窗。
     */
    private function renderMerchantDetailPopup(): void
    {
        $userId = (int) $this->input->get('user_id', 0);
        $merchantDetail = (new \App\Model\MerchantModel())->findByUserId($userId);
        if (!$merchantDetail) {
            exit('该用户未开通商户');
        }

        $levelTable = $this->db->prefix() . 'merchant_level';
        $userTable = $this->db->prefix() . 'user';
        $merchantLevel = $this->db->fetchOne(
            'SELECT * FROM `' . $levelTable . '` WHERE `id` = ? LIMIT 1',
            [(int) $merchantDetail['level_id']]
        );
        $merchantOwner = $this->db->fetchOne(
            'SELECT `id`, `username`, `nickname`, `email`, `mobile`, `shop_balance` FROM `' . $userTable . '` WHERE `id` = ? LIMIT 1',
            [(int) $merchantDetail['user_id']]
        );

        $this->render('popup/user_merchant_detail', [
            'merchantDetail' => $merchantDetail,
            'merchantLevel'  => $merchantLevel,
            'merchantOwner'  => $merchantOwner,
            'storefrontUrl'  => $this->merchantContext->storefrontUrl($merchantDetail),
            'pageTitle'      => '商户详情',
        ]);
    }

    /**
     * 用户编辑弹窗。
     */
    private function renderEditPopup(): void
    {
        $editId = (int) $this->input->get('id', 0);
        $editUser = null;

        if ($editId > 0) {
            $editUser = (new \App\Model\UserListModel())->findById($editId);
        }

        $this->render('popup/user_edit', [
            'editUser'  => $editUser,
            'pageTitle' => $editUser !== null ? '编辑用户' : '添加用户',
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        $admin = $this->user();
        $currentAdminId = (int) ($admin['id'] ?? 0);

        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('user_list', ['currentAdminId' => $currentAdminId]);
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/user_list.php', $admin, get_defined_vars());
    }
}
