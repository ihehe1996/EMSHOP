<?php

declare(strict_types=1);

/**
 * 回调控制器。
 *
 * 方法说明：
 * - _index() 回调页
 */
class CallbackController extends BaseController
{
    /**
     * 回调页。
     */
    public function _index(): void
    {
        $this->view->setTitle('回调');
        $this->view->setData([]);
        $this->view->render('callback');
    }
}
