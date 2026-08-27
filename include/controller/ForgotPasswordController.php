<?php

declare(strict_types=1);

/**
 * 找回密码旧路由兼容：重定向到 LoginController。
 *
 * @deprecated 请使用 ?c=login&a=forgot 与 ?c=login&a=reset
 */
class ForgotPasswordController extends BaseController
{
    public function _index(): void
    {
        header('Location: ?c=login&a=forgot');
        exit;
    }

    public function reset(): void
    {
        $token = trim((string) $this->getArg('token', ''));
        $qs = 'c=login&a=reset';
        if ($token !== '') {
            $qs .= '&token=' . urlencode($token);
        }
        header('Location: ?' . $qs);
        exit;
    }
}
