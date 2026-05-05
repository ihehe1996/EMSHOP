<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 - 分类管理
 *
 * 两部分：
 *   - 自定义分类：em_merchant_category，支持二级，仅作用于本店
 *   - 主站分类映射：em_merchant_category_map，给主站分类在本店起别名
 */
merchantRequireLogin();

$merchantId = (int) $currentMerchant['id'];

$categoryTable = Database::prefix() . 'merchant_category';
$mapTable = Database::prefix() . 'merchant_category_map';
$masterCatTable = Database::prefix() . 'goods_category';

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');
        if ($action !== 'list' && $action !== 'list_map') {
            if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        switch ($action) {
            // ================= 自定义分类 =================
            case 'list': {
                $rows = Database::query(
                    'SELECT * FROM `' . $categoryTable . '`
                      WHERE `merchant_id` = ?
                      ORDER BY `parent_id` ASC, `sort` ASC, `id` ASC',
                    [$merchantId]
                );
                Response::success('', ['data' => $rows, 'total' => count($rows), 'csrf_token' => Csrf::token()]);
                break;
            }

            case 'save': {
                $id = (int) Input::post('id', 0);
                $name = trim((string) Input::post('name', ''));
                if ($name === '' || mb_strlen($name) > 100) {
                    Response::error('分类名需在 1~100 字符');
                }
                $parentId = max(0, (int) Input::post('parent_id', 0));
                // 防止深层：parent 必须是本店的 parent_id=0 分类
                if ($parentId > 0) {
                    $parent = Database::fetchOne(
                        'SELECT `id`, `parent_id` FROM `' . $categoryTable . '`
                          WHERE `id` = ? AND `merchant_id` = ? LIMIT 1',
                        [$parentId, $merchantId]
                    );
                    if ($parent === null) {
                        Response::error('父分类不存在');
                    }
                    if ((int) $parent['parent_id'] !== 0) {
                        Response::error('仅支持二级分类');
                    }
                }
                $icon = trim((string) Input::post('icon', ''));
                $sort = (int) Input::post('sort', 100);
                $status = (int) Input::post('status', 1) === 1 ? 1 : 0;

                $data = [
                    'merchant_id' => $merchantId,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'icon' => $icon,
                    'sort' => $sort,
                    'status' => $status,
                ];
                if ($id > 0) {
                    // 校验归属
                    $exists = Database::fetchOne(
                        'SELECT `id` FROM `' . $categoryTable . '` WHERE `id` = ? AND `merchant_id` = ? LIMIT 1',
                        [$id, $merchantId]
                    );
                    if ($exists === null) {
                        Response::error('分类不存在');
                    }
                    unset($data['merchant_id']);
                    Database::update('merchant_category', $data, $id);
                    Response::success('已更新', ['csrf_token' => Csrf::refresh()]);
                } else {
                    Database::insert('merchant_category', $data);
                    Response::success('已添加', ['csrf_token' => Csrf::refresh()]);
                }
                break;
            }

            case 'delete': {
                $id = (int) Input::post('id', 0);
                $exists = Database::fetchOne(
                    'SELECT `id`, `parent_id` FROM `' . $categoryTable . '`
                      WHERE `id` = ? AND `merchant_id` = ? LIMIT 1',
                    [$id, $merchantId]
                );
                if ($exists === null) {
                    Response::error('分类不存在');
                }
                // 有子分类不能删
                $child = Database::fetchOne(
                    'SELECT `id` FROM `' . $categoryTable . '` WHERE `parent_id` = ? AND `merchant_id` = ? LIMIT 1',
                    [$id, $merchantId]
                );
                if ($child !== null) {
                    Response::error('请先删除子分类');
                }
                Database::execute(
                    'DELETE FROM `' . $categoryTable . '` WHERE `id` = ? AND `merchant_id` = ?',
                    [$id, $merchantId]
                );
                Response::success('已删除', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // ================= 主站分类映射 =================
            case 'list_map': {
                // 读主站分类 + 本店映射（含别名 + 隐藏标记）
                $cats = Database::query(
                    'SELECT `id`, `parent_id`, `name`, `sort`
                       FROM `' . $masterCatTable . '`
                      WHERE `status` = 1
                      ORDER BY `parent_id` ASC, `sort` ASC, `id` ASC'
                );
                $maps = Database::query(
                    'SELECT `master_category_id`, `alias_name`, `is_hidden`
                       FROM `' . $mapTable . '`
                      WHERE `merchant_id` = ?',
                    [$merchantId]
                );
                $mapIdx = [];
                foreach ($maps as $m) {
                    $mapIdx[(int) $m['master_category_id']] = $m;
                }
                foreach ($cats as &$c) {
                    $hit = $mapIdx[(int) $c['id']] ?? null;
                    $c['alias_name'] = $hit ? (string) $hit['alias_name'] : '';
                    $c['is_hidden']  = $hit ? (int) $hit['is_hidden'] : 0;
                }
                unset($c);
                Response::success('', ['data' => $cats, 'csrf_token' => Csrf::token()]);
                break;
            }

            case 'save_map': {
                $masterId = (int) Input::post('master_category_id', 0);
                if ($masterId <= 0) {
                    Response::error('缺少主站分类 id');
                }
                $masterExists = Database::fetchOne(
                    'SELECT `id` FROM `' . $masterCatTable . '` WHERE `id` = ? LIMIT 1',
                    [$masterId]
                );
                if ($masterExists === null) {
                    Response::error('主站分类不存在');
                }
                $alias = trim((string) Input::post('alias_name', ''));
                if (mb_strlen($alias) > 100) {
                    Response::error('别名长度不能超过 100 字符');
                }

                $existing = Database::fetchOne(
                    'SELECT `id`, `is_hidden` FROM `' . $mapTable . '`
                      WHERE `merchant_id` = ? AND `master_category_id` = ? LIMIT 1',
                    [$merchantId, $masterId]
                );

                if ($existing === null) {
                    if ($alias === '') {
                        // 既没别名又没隐藏过 → 完全跟随主站，不写空行
                        Response::success('已跟随主站', ['csrf_token' => Csrf::refresh()]);
                        break;
                    }
                    Database::insert('merchant_category_map', [
                        'merchant_id' => $merchantId,
                        'master_category_id' => $masterId,
                        'alias_name' => $alias,
                        'is_hidden' => 0,
                    ]);
                } else {
                    // 别名清空 + 隐藏=0 → 行已经没用，直接删
                    if ($alias === '' && (int) $existing['is_hidden'] === 0) {
                        Database::execute(
                            'DELETE FROM `' . $mapTable . '` WHERE `id` = ?',
                            [(int) $existing['id']]
                        );
                    } else {
                        Database::update('merchant_category_map',
                            ['alias_name' => $alias],
                            (int) $existing['id']
                        );
                    }
                }
                Response::success('已保存', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 切换"在本店隐藏"开关
            case 'toggle_hide_map': {
                $masterId = (int) Input::post('master_category_id', 0);
                if ($masterId <= 0) Response::error('缺少主站分类 id');
                $masterExists = Database::fetchOne(
                    'SELECT `id` FROM `' . $masterCatTable . '` WHERE `id` = ? LIMIT 1',
                    [$masterId]
                );
                if ($masterExists === null) Response::error('主站分类不存在');

                $existing = Database::fetchOne(
                    'SELECT `id`, `alias_name`, `is_hidden` FROM `' . $mapTable . '`
                      WHERE `merchant_id` = ? AND `master_category_id` = ? LIMIT 1',
                    [$merchantId, $masterId]
                );
                $cur = $existing ? (int) $existing['is_hidden'] : 0;
                $next = $cur === 1 ? 0 : 1;

                if ($existing === null) {
                    Database::insert('merchant_category_map', [
                        'merchant_id' => $merchantId,
                        'master_category_id' => $masterId,
                        'alias_name' => '',
                        'is_hidden' => $next,
                    ]);
                } else {
                    // 取消隐藏 + 没别名 → 行已经没用，直接删
                    if ($next === 0 && (string) $existing['alias_name'] === '') {
                        Database::execute(
                            'DELETE FROM `' . $mapTable . '` WHERE `id` = ?',
                            [(int) $existing['id']]
                        );
                    } else {
                        Database::update('merchant_category_map',
                            ['is_hidden' => $next],
                            (int) $existing['id']
                        );
                    }
                }
                Response::success($next === 1 ? '已隐藏' : '已恢复显示', [
                    'is_hidden' => $next,
                    'csrf_token' => Csrf::refresh(),
                ]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (Throwable $e) {
        Response::error($e->getMessage() ?: '系统繁忙，请稍后再试');
    }
}

merchantRenderPage(__DIR__ . '/view/category.php');
