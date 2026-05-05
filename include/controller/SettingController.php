<?php

declare(strict_types=1);

/**
 * 设置页控制器。
 *
 * 方法说明：
 * - _index() 设置页
 */
class SettingController extends BaseController
{
    /**
     * 设置页。
     */
    public function _index(): void
    {
        $this->view->setTitle('设置');
        $this->view->setData([]);
        $this->view->render('setting');
    }
}
