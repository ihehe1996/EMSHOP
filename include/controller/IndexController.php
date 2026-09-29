<?php

declare(strict_types=1);

/**
 * 首页控制器。
 *
 * 方法说明：
 * - display() 首页（渲染哪张模板由后台「首页入口」配置决定）
 *
 * 注意动作名与多数单页控制器不同：这里叫 display() 而不是 _index()。
 * 路由侧的对应关系登记在 Dispatcher::DEFAULT_ACTIONS['index']，
 * 改方法名时**必须**同步改那张表，否则 / 与 ?c=index 会 404。
 */
class IndexController extends BaseController
{
    /**
     * 首页。
     *
     * 按后台「首页入口」（homepage_mode）分辨渲染哪张模板：
     *   mall（商城首页）        → index 模板
     *   goods_list（商品列表页） → goods_list 模板
     *
     * 注：goods_list 这一档走站点根 "/" 时，路由层已经把它换成 GoodsController::display()，
     * 这里再判一次是为了「直接访问 ?c=index」时两类入口表现一致。
     */
    public function display(): void
    {
        $mode = (string) Config::get('homepage_mode', 'mall');

        $this->view->render($mode === 'goods_list' ? 'goods_list' : 'index');
    }
}
