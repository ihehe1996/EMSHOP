<?php

declare(strict_types=1);

/**
 * 错误控制器（404 等错误页面）。
 *
 * 方法说明：
 * - _index() 404 页面
 */
class ErrorController extends BaseController
{
    /**
     * 404 页面。
     */
    public function _index(): void
    {
        http_response_code(404);
        $this->view->setTitle('页面不存在');
        $this->view->setData([
            '_404_reason' => $this->dispatcher->getArg('_404_reason', ''),
            '_404_controller' => $this->getControllerName(),
            '_404_action' => $this->getActionName(),
        ]);
        $this->view->render('404');
    }
}
