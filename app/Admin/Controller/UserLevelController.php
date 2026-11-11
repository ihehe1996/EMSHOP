<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 用户等级管理（/admin/user_level）。
 *
 * 与旧 admin/user_level.php 行为一致：
 *   - _action=list 列表、create / update / delete / toggle
 *   - ?_popup=1 添加/编辑弹窗
 *   - 无 action 时渲染列表页
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class UserLevelController extends BaseAdminController
{
    public function index(): void
    {
        if (\Request::isPost()) {
            $this->handlePost();
            return;
        }

        if ((string) \Input::get('_popup', '') === '1') {
            $this->renderPopup();
            return;
        }

        $this->renderList();
    }

    /**
     * 处理后台 AJAX 动作；除 list 外都校验 CSRF。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) \Input::post('_action', '');

            // list 请求不需要 CSRF 验证
            if ($action !== 'list') {
                $csrf = (string) \Input::post('csrf_token', '');
                if (!\Csrf::validate($csrf)) {
                    \Response::error('请求已失效，请刷新页面后重试');
                }
            }

            switch ($action) {
                case 'list':
                    $keyword = trim((string) \Input::post('keyword', ''));
                    $model = new \UserLevelModel();
                    $levels = $model->getAll($keyword);

                    \Response::success('', [
                        'data' => array_values($levels),
                        'total' => count($levels),
                        'csrf_token' => \Csrf::token(),
                    ]);
                    break;

                case 'create':
                    $name = trim((string) \Input::post('name', ''));
                    if ($name === '') {
                        \Response::error('等级名称不能为空');
                    }
                    if (mb_strlen($name) > 50) {
                        \Response::error('等级名称最多50个字符');
                    }

                    $level = (int) \Input::post('level', 0);
                    if ($level <= 0) {
                        \Response::error('等级不能为空');
                    }

                    $model = new \UserLevelModel();
                    if ($model->existsName($name)) {
                        \Response::error('等级名称已被占用');
                    }
                    if ($model->existsLevel($level)) {
                        \Response::error('等级数值已被占用');
                    }

                    $discount = (float) \Input::post('discount', 9.9);
                    if ($discount < 1 || $discount > 10) {
                        \Response::error('折扣率必须在 1 ~ 10 之间');
                    }

                    $selfOpenPrice = (float) \Input::post('self_open_price', 0.0);
                    if ($selfOpenPrice < 0) {
                        \Response::error('自助开通价格不能为负数');
                    }

                    $unlockExp = max(0, (int) \Input::post('unlock_exp', 0));

                    $model->create([
                        'name' => $name,
                        'level' => $level,
                        'discount' => $discount,
                        'self_open_price' => $selfOpenPrice,
                        'unlock_exp' => $unlockExp,
                        'remark' => trim((string) \Input::post('remark', '')),
                        'enabled' => \Input::post('enabled', 'y') === 'y' ? 'y' : 'n',
                    ]);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('等级创建成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'update':
                    $id = (int) \Input::post('id', 0);
                    if ($id <= 0) {
                        \Response::error('无效的等级ID');
                    }

                    $model = new \UserLevelModel();
                    $existing = $model->findById($id);
                    if ($existing === null) {
                        \Response::error('等级不存在');
                    }

                    $name = trim((string) \Input::post('name', ''));
                    if ($name === '') {
                        \Response::error('等级名称不能为空');
                    }
                    if (mb_strlen($name) > 50) {
                        \Response::error('等级名称最多50个字符');
                    }

                    $level = (int) \Input::post('level', 0);
                    if ($level <= 0) {
                        \Response::error('等级不能为空');
                    }

                    $discount = (float) \Input::post('discount', 9.9);
                    if ($discount < 1 || $discount > 10) {
                        \Response::error('折扣率必须在 1 ~ 10 之间');
                    }

                    $selfOpenPrice = (float) \Input::post('self_open_price', 0.0);
                    if ($selfOpenPrice < 0) {
                        \Response::error('自助开通价格不能为负数');
                    }

                    $unlockExp = max(0, (int) \Input::post('unlock_exp', 0));

                    if ($model->existsName($name, $id)) {
                        \Response::error('已存在同名等级');
                    }
                    if ($model->existsLevel($level, $id)) {
                        \Response::error('已存在同名等级数值');
                    }

                    $model->update($id, [
                        'name' => $name,
                        'level' => $level,
                        'discount' => $discount,
                        'self_open_price' => $selfOpenPrice,
                        'unlock_exp' => $unlockExp,
                        'remark' => trim((string) \Input::post('remark', '')),
                        'enabled' => \Input::post('enabled', 'y') === 'y' ? 'y' : 'n',
                    ]);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('等级更新成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'delete':
                    $id = (int) \Input::post('id', 0);
                    if ($id <= 0) {
                        \Response::error('无效的等级ID');
                    }

                    $model = new \UserLevelModel();
                    if ($model->findById($id) === null) {
                        \Response::error('等级不存在');
                    }

                    $model->delete($id);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('删除成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'toggle':
                    $id = (int) \Input::post('id', 0);
                    if ($id <= 0) {
                        \Response::error('无效的等级ID');
                    }

                    $model = new \UserLevelModel();
                    $level = $model->findById($id);
                    if ($level === null) {
                        \Response::error('等级不存在');
                    }

                    $newEnabled = $level['enabled'] === 'y' ? 'n' : 'y';
                    $model->update($id, ['enabled' => $newEnabled]);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('状态已更新', ['csrf_token' => $csrfToken]);
                    break;

                default:
                    \Response::error('未知操作');
            }
        } catch (\RuntimeException $e) {
            \Response::error($e->getMessage());
        } catch (\Throwable $e) {
            \Response::error('系统繁忙，请稍后再试');
        }
    }

    /**
     * 添加/编辑弹窗（iframe）。
     */
    private function renderPopup(): void
    {
        $editId = (int) \Input::get('id', 0);
        $editLevel = null;

        if ($editId > 0) {
            $editLevel = (new \UserLevelModel())->findById($editId);
        }

        $this->render('popup/user_level', [
            'editLevel' => $editLevel,
            'pageTitle' => $editLevel !== null ? '编辑等级' : '添加等级',
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        if (\Request::isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('user_level');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/user_level.php', $this->user(), get_defined_vars());
    }
}
