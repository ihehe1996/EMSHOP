<?php

declare(strict_types=1);

/**
 * 插件回调控制器。
 *
 * 方法说明：
 * - _index() 插件回调页
 */
class PluginController extends BaseController
{
    /**
     * 插件回调页。
     */
    public function _index(): void
    {
        $this->view->setTitle('插件');
        $this->view->setData([]);
        $this->view->render('plugin');
    }
}
