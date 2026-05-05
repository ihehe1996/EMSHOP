<?php

declare(strict_types=1);

/**
 * 密码保护页控制器。
 *
 * 方法说明：
 * - _index() 密码验证页
 */
class PasswordController extends BaseController
{
    /**
     * 密码验证页。
     */
    public function _index(): void
    {
        $this->view->setTitle('密码保护');
        $this->view->setData([
            'is_post' => Request::isPost(),
            'error' => '',
        ]);
        $this->view->render('password');
    }
}
