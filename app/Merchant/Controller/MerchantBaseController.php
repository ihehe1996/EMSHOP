<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

use App\User\Controller\UserBaseController;

/**
 * 商户后台控制器基类。
 *
 * 与原 user/merchant/global.php 的 merchantRequireLogin() 行为一致：
 *   - 先要求前台用户登录
 *   - 未开通商户 → 跳开通申请页；商户被禁用 → 跳回用户中心
 *   - 当前请求固定 merchant_{id} scope，并记录最近访问的店铺
 */
abstract class MerchantBaseController extends UserBaseController
{
    /** @var array<string, mixed> */
    private $merchant = [];

    /** @var array<string, mixed>|null */
    private $level;

    public function __construct()
    {
        parent::__construct();

        $frontUser = $this->user();
        $merchantId = (int) ($frontUser['merchant_id'] ?? 0);

        if ($merchantId <= 0) {
            $this->deny('尚未开通商户', '/user/merchant/apply.php');
        }

        $merchant = (new \MerchantModel())->findById($merchantId);
        if ($merchant === null || (int) ($merchant['status'] ?? 0) !== 1) {
            $this->deny('商户已停用', '/user/home.php');
        }

        $this->merchant = (array) $merchant;

        $GLOBALS['__em_current_scope'] = 'merchant_' . $merchantId;
        \MerchantContext::rememberLastMerchant($this->merchant);

        $level = (new \MerchantLevelModel())->findById((int) ($merchant['level_id'] ?? 0));
        $this->level = is_array($level) ? $level : null;
    }

    /**
     * 当前商户行。
     *
     * @return array<string, mixed>
     */
    protected function merchant(): array
    {
        return $this->merchant;
    }

    /**
     * 当前商户等级行（可能为 null）。
     *
     * @return array<string, mixed>|null
     */
    protected function level(): ?array
    {
        return $this->level;
    }

    /**
     * 拒绝访问：Pjax / AJAX 返回带跳转地址的 JSON，普通请求 302。
     */
    private function deny(string $message, string $redirect): void
    {
        if (\Request::isPjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            \Response::error($message, ['redirect' => $redirect]);
        }
        \Response::redirect($redirect);
    }

    /**
     * 渲染 app/Merchant/View 下的视图（不套框架壳）。
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $viewName, array $data = []): void
    {
        $relative = str_replace('\\', '/', trim($viewName, '/'));
        if ($relative === '' || preg_match('#[^a-zA-Z0-9_/-]#', $relative)) {
            throw new \InvalidArgumentException('非法视图名：' . $viewName);
        }

        $file = dirname(__DIR__) . '/View/' . $relative . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('视图不存在：' . $relative);
        }

        extract($data, EXTR_SKIP);
        require $file;
    }
}
