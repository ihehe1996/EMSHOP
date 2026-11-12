<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心页面（/user/address）。
 */
final class AddressController extends UserBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $siteName = $this->config->get('sitename', 'EMSHOP');
        $csrfToken = $this->csrf->token();
        $displayMoney = '0.00';
        if (!empty($frontUser['money'])) {
            $displayMoney = bcdiv((string) $frontUser['money'], '1000000', 2);
        }
        $userId = (int) $frontUser['id'];

        // POST 分发
        if ($this->request->isPost()) {
            try {
                $csrf = (string) $this->input->post('csrf_token', '');
                if (!$this->csrf->validate($csrf)) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }

                $action = (string) $this->input->post('action', '');

                switch ($action) {
                    case 'save': {
                        $id        = (int) $this->input->post('id', 0);
                        $recipient = trim((string) $this->input->post('recipient', ''));
                        $mobile    = trim((string) $this->input->post('mobile', ''));
                        $province  = trim((string) $this->input->post('province', ''));
                        $city      = trim((string) $this->input->post('city', ''));
                        $district  = trim((string) $this->input->post('district', ''));
                        $detail    = trim((string) $this->input->post('detail', ''));
                        $isDefault = $this->input->post('is_default', '') !== '' ? 1 : 0;

                        // —— 校验 —— 所有长度/格式异常都走 \Response::error 立即终止
                        if ($recipient === '' || mb_strlen($recipient) > 50) {
                            $this->response->error('收件人姓名不能为空且长度 ≤ 50');
                        }
                        if (!preg_match('/^1\d{10}$/', $mobile)) {
                            $this->response->error('请输入 11 位有效手机号');
                        }
                        if ($province === '' || $city === '' || $district === '') {
                            $this->response->error('请完整选择省 / 市 / 区');
                        }
                        if ($detail === '' || mb_strlen($detail) > 255) {
                            $this->response->error('详细地址不能为空且长度 ≤ 255');
                        }

                        $payload = [
                            'recipient'  => $recipient,
                            'mobile'     => $mobile,
                            'province'   => $province,
                            'city'       => $city,
                            'district'   => $district,
                            'detail'     => $detail,
                            'is_default' => $isDefault,
                        ];

                        if ($id > 0) {
                            // 编辑：Model 层 owner 校验内置，非自己的 id 不会命中
                            if (\App\Model\UserAddressModel::findById($id, $userId) === null) {
                                $this->response->error('地址不存在或无权操作');
                            }
                            \App\Model\UserAddressModel::update($id, $userId, $payload);
                            $this->response->success('已保存', ['csrf_token' => $this->csrf->refresh()]);
                        } else {
                            // 新建：先卡数量上限，防止刷接口造大量脏数据
                            if (\App\Model\UserAddressModel::countByUserId($userId) >= \App\Model\UserAddressModel::MAX_PER_USER) {
                                $this->response->error('每个账户最多只能保存 ' . \App\Model\UserAddressModel::MAX_PER_USER . ' 个地址');
                            }
                            \App\Model\UserAddressModel::create($userId, $payload);
                            $this->response->success('已添加', ['csrf_token' => $this->csrf->refresh()]);
                        }
                        break;
                    }

                    case 'delete': {
                        $id = (int) $this->input->post('id', 0);
                        if ($id <= 0) $this->response->error('参数错误');
                        $ok = \App\Model\UserAddressModel::delete($id, $userId);
                        if (!$ok) $this->response->error('地址不存在或无权操作');
                        $this->response->success('已删除', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    case 'set_default': {
                        $id = (int) $this->input->post('id', 0);
                        if ($id <= 0) $this->response->error('参数错误');
                        $ok = \App\Model\UserAddressModel::setDefault($id, $userId);
                        if (!$ok) $this->response->error('地址不存在或无权操作');
                        $this->response->success('已设为默认', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    default:
                        $this->response->error('未知操作');
                }
            } catch (\Throwable $e) {
                $this->response->error('操作失败：' . $e->getMessage());
            }
        }

        // GET 渲染
        $addressList = \App\Model\UserAddressModel::listByUserId($userId);

        if ($this->request->isPjax()) {
            echo '<div id="userContent" class="uc-content">';
            $this->render('address', get_defined_vars());
            echo '</div>';
        } else {
            \App\User\UserShell::render(dirname(__DIR__) . '/View/address.php', $frontUser, get_defined_vars());
        }
    }
}
