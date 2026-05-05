<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 用户列表管理控制器。
 *
 * 只管理 role='user' 的普通用户，不涉及管理员账号。
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

require EM_ROOT . '/include/model/UserListModel.php';

// ============================================================
// POST 请求处理
// ============================================================
if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');

        if ($action !== 'list') {
            $csrf = (string) Input::post('csrf_token', '');
            if (!Csrf::validate($csrf)) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        switch ($action) {
            case 'list':
                $page = (int) Input::post('page', 1);
                $limit = (int) Input::post('limit', 15);
                $keyword = trim((string) Input::post('keyword', ''));

                if ($page < 1) $page = 1;
                if ($limit < 1 || $limit > 100) $limit = 15;

                $model = new UserListModel();
                $result = $model->getAll($page, $limit, $keyword);

                Response::success('', [
                    'data' => array_values($result['data']),
                    'total' => $result['total'],
                    'csrf_token' => Csrf::token(),
                ]);
                break;

            case 'create':
                $username = trim((string) Input::post('username', ''));
                if ($username === '') {
                    Response::error('用户名不能为空');
                }
                if (mb_strlen($username) > 50) {
                    Response::error('用户名最多50个字符');
                }
                if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
                    Response::error('用户名只能包含字母、数字、下划线和中文字符');
                }

                $password = (string) Input::post('password', '');
                if (mb_strlen($password) < 6) {
                    Response::error('密码至少6个字符');
                }
                if (mb_strlen($password) > 50) {
                    Response::error('密码最多50个字符');
                }

                $email = trim((string) Input::post('email', ''));
                if ($email === '') {
                    Response::error('邮箱不能为空');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Response::error('邮箱格式不正确');
                }

                $nickname = trim((string) Input::post('nickname', ''));
                if (mb_strlen($nickname) > 100) {
                    Response::error('昵称最多100个字符');
                }

                $avatar = trim((string) Input::post('avatar', ''));

                $status = Input::post('status', '1');
                $status = $status === '1' ? 1 : 0;

                // 用户等级（user_levels.id）：0 = 不设等级；> 0 时校验等级存在且未禁用
                $levelId = (int) Input::post('level_id', 0);
                if ($levelId > 0) {
                    require_once EM_ROOT . '/include/model/UserLevelModel.php';
                    $lvRow = (new UserLevelModel())->findById($levelId);
                    if ($lvRow === null || (string) ($lvRow['enabled'] ?? 'y') !== 'y') {
                        Response::error('用户等级不存在或已被禁用');
                    }
                }

                $model = new UserListModel();

                if ($model->existsUsername($username)) {
                    Response::error('该用户名已被占用');
                }
                if ($model->existsEmail($email)) {
                    Response::error('该邮箱已被占用');
                }
                $mobile = trim((string) Input::post('mobile', ''));
                if ($mobile !== '' && $model->existsMobile($mobile)) {
                    Response::error('该手机号已被占用');
                }

                $hasher = new PasswordHash(8, true);
                $hashedPassword = $hasher->HashPassword($password);

                $model->create([
                    'username' => $username,
                    'password' => $hashedPassword,
                    'email' => $email,
                    'mobile' => $mobile,
                    'nickname' => $nickname,
                    'avatar' => $avatar,
                    'status' => $status,
                    'level_id' => $levelId,
                ]);

                $csrfToken = Csrf::refresh();
                Response::success('用户创建成功', ['csrf_token' => $csrfToken]);
                break;

            case 'update':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的用户ID');
                }

                $model = new UserListModel();
                $existing = $model->findById($id);
                if ($existing === null) {
                    Response::error('用户不存在');
                }

                $email = trim((string) Input::post('email', ''));
                if ($email === '') {
                    Response::error('邮箱不能为空');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Response::error('邮箱格式不正确');
                }

                $nickname = trim((string) Input::post('nickname', ''));
                if (mb_strlen($nickname) > 100) {
                    Response::error('昵称最多100个字符');
                }

                $avatar = trim((string) Input::post('avatar', ''));

                $status = Input::post('status', '1');
                $status = $status === '1' ? 1 : 0;

                // 用户等级（user_levels.id）：0 = 不设等级 → 买家折扣 1.0；
                // 校验合法 id 必须存在于 user_levels 且 enabled='y'，避免写入幽灵 id
                $levelId = (int) Input::post('level_id', 0);
                if ($levelId > 0) {
                    require_once EM_ROOT . '/include/model/UserLevelModel.php';
                    $lvRow = (new UserLevelModel())->findById($levelId);
                    if ($lvRow === null || (string) ($lvRow['enabled'] ?? 'y') !== 'y') {
                        Response::error('用户等级不存在或已被禁用');
                    }
                }

                if ($model->existsEmail($email, $id)) {
                    Response::error('该邮箱已被其他用户占用');
                }
                $mobile = trim((string) Input::post('mobile', ''));
                if ($mobile !== '' && $model->existsMobile($mobile, $id)) {
                    Response::error('该手机号已被其他用户占用');
                }

                $model->update($id, [
                    'email' => $email,
                    'mobile' => $mobile,
                    'nickname' => $nickname,
                    'avatar' => $avatar,
                    'status' => $status,
                    'level_id' => $levelId,
                ]);

                $csrfToken = Csrf::refresh();
                Response::success('用户更新成功', ['csrf_token' => $csrfToken]);
                break;

            case 'toggle':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的用户ID');
                }

                $model = new UserListModel();
                $user = $model->findById($id);
                if ($user === null) {
                    Response::error('用户不存在');
                }

                $model->toggleStatus($id);

                $csrfToken = Csrf::refresh();
                Response::success('状态已更新', ['csrf_token' => $csrfToken]);
                break;

            case 'delete':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的用户ID');
                }

                $model = new UserListModel();
                if ($model->findById($id) === null) {
                    Response::error('用户不存在');
                }

                $model->delete($id);

                $csrfToken = Csrf::refresh();
                Response::success('删除成功', ['csrf_token' => $csrfToken]);
                break;

            case 'batch_delete':
                $idsRaw = Input::post('ids', '');
                $ids = array_filter(array_map('intval', explode(',', $idsRaw)), function ($id) {
                    return $id > 0;
                });
                if ($ids === []) {
                    Response::error('请选择要删除的用户');
                }

                $model = new UserListModel();
                $deleted = $model->deleteBatch($ids);

                $csrfToken = Csrf::refresh();
                Response::success('已删除 ' . $deleted . ' 个用户', ['csrf_token' => $csrfToken, 'deleted' => $deleted]);
                break;

            case 'image':
                if (empty($_FILES['file'])) {
                    Response::error('请选择图片文件');
                }
                $uploader = new UploadService();
                $result = $uploader->upload($_FILES['file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'avatar');
                $csrfToken = Csrf::refresh();
                Response::success('上传成功', [
                    'csrf_token' => $csrfToken,
                    'url' => $result['url'],
                ]);
                break;

            // 余额调整
            case 'balance_adjust': {
                $userId = (int) Input::post('user_id', 0);
                $type = (string) Input::post('type', '');
                $amountStr = trim((string) Input::post('amount', ''));
                $remark = trim((string) Input::post('remark', ''));

                if ($userId <= 0) {
                    Response::error('用户不存在');
                }
                if (!in_array($type, ['increase', 'decrease'], true)) {
                    Response::error('请选择操作类型');
                }
                if ($amountStr === '' || !is_numeric($amountStr) || (float) $amountStr <= 0) {
                    Response::error('请输入有效的金额');
                }
                if ($remark === '') {
                    $remark = '客服操作';
                }

                // 金额转为数据库存储格式（×1000000）
                $amount = (int) bcmul($amountStr, '1000000', 0);
                if ($amount <= 0) {
                    Response::error('金额必须大于0');
                }

                $balanceLog = new UserBalanceLogModel();
                $operatorId = (int) ($adminUser['id'] ?? 0);
                $operatorName = (string) ($adminUser['nickname'] ?? $adminUser['username'] ?? '管理员');

                if ($type === 'increase') {
                    $ok = $balanceLog->increase($userId, $amount, $remark, $operatorId, $operatorName);
                } else {
                    $ok = $balanceLog->decrease($userId, $amount, $remark, $operatorId, $operatorName);
                }

                if (!$ok) {
                    Response::error($type === 'decrease' ? '余额不足或操作失败' : '操作失败');
                }

                // 返回最新余额
                $freshUser = (new UserListModel())->findById($userId);
                $newBalance = $freshUser ? bcdiv((string) ($freshUser['money'] ?? 0), '1000000', 2) : '0.00';

                Response::success('操作成功', [
                    'balance'    => $newBalance,
                    'csrf_token' => Csrf::refresh(),
                ]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error($e->getMessage());
    }
}

// ============================================================
// 弹窗模式：渲染编辑弹窗
// ============================================================
$popupType = (string) Input::get('_popup', '');

// 余额调整弹窗
if ($popupType === 'balance') {
    $balanceUserId = (int) Input::get('id', 0);
    $model = new UserListModel();
    $balanceUser = $model->findById($balanceUserId);
    if (!$balanceUser) {
        exit('用户不存在');
    }
    $currentBalance = bcdiv((string) ($balanceUser['money'] ?? 0), '1000000', 2);
    $pageTitle = '余额调整';

    include __DIR__ . '/view/popup/user_balance.php';
    return;
}

// 商户详情弹窗（按 user_id 找商户，用于用户列表"商户"列点击查看）
if ($popupType === 'merchant') {
    $userId = (int) Input::get('user_id', 0);
    require_once EM_ROOT . '/include/model/MerchantModel.php';
    $merchantDetail = (new MerchantModel())->findByUserId($userId);
    if (!$merchantDetail) {
        exit('该用户未开通商户');
    }
    // 附加 merchant_level / 用户基础信息
    $levelTable = Database::prefix() . 'merchant_level';
    $userTable = Database::prefix() . 'user';
    $merchantLevel = Database::fetchOne(
        'SELECT * FROM `' . $levelTable . '` WHERE `id` = ? LIMIT 1',
        [(int) $merchantDetail['level_id']]
    );
    $merchantOwner = Database::fetchOne(
        'SELECT `id`, `username`, `nickname`, `email`, `mobile`, `shop_balance` FROM `' . $userTable . '` WHERE `id` = ? LIMIT 1',
        [(int) $merchantDetail['user_id']]
    );
    // 店铺前台 URL（自定义域名已验证 > 二级域名 > 空串）
    $storefrontUrl = MerchantContext::storefrontUrl($merchantDetail);
    $pageTitle = '商户详情';

    include __DIR__ . '/view/popup/user_merchant_detail.php';
    return;
}

// 用户编辑弹窗
if ($popupType === '1') {
    $editId = (int) Input::get('id', 0);
    $editUser = null;

    if ($editId > 0) {
        $model = new UserListModel();
        $editUser = $model->findById($editId);
    }

    $isEdit = $editUser !== null;
    $pageTitle = $isEdit ? '编辑用户' : '添加用户';

    // 用户等级下拉选项：只列 enabled='y' 的；提供 0=不设等级 兜底
    require_once EM_ROOT . '/include/model/UserLevelModel.php';
    $allLevels = (new UserLevelModel())->getAll();
    $userLevels = array_values(array_filter($allLevels, static fn($l) => (string) ($l['enabled'] ?? 'y') === 'y'));

    $esc = function (string $str): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    };

    include __DIR__ . '/view/popup/user_list.php';
    return;
}

// ============================================================
// 正常模式：渲染完整后台页面
// ============================================================
$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/user_list.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/user_list.php';
    require __DIR__ . '/index.php';
}
