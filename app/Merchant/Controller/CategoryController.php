<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台页面（/user/merchant/category）。
 */
final class CategoryController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        $merchantId = (int) $currentMerchant['id'];

        $categoryTable = $this->db->prefix() . 'merchant_category';
        $mapTable = $this->db->prefix() . 'merchant_category_map';
        $masterCatTable = $this->db->prefix() . 'goods_category';

        if ($this->request->isPost()) {
            try {
                $action = (string) $this->input->post('_action', '');
                if ($action !== 'list' && $action !== 'list_map') {
                    if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                        $this->response->error('请求已失效，请刷新页面后重试');
                    }
                }

                switch ($action) {
                    // ================= 自定义分类 =================
                    case 'list': {
                        $rows = $this->db->query(
                            'SELECT * FROM `' . $categoryTable . '`
                              WHERE `merchant_id` = ?
                              ORDER BY `parent_id` ASC, `sort` ASC, `id` ASC',
                            [$merchantId]
                        );
                        $this->response->success('', ['data' => $rows, 'total' => count($rows), 'csrf_token' => $this->csrf->token()]);
                        break;
                    }

                    case 'save': {
                        $id = (int) $this->input->post('id', 0);
                        $name = trim((string) $this->input->post('name', ''));
                        if ($name === '' || mb_strlen($name) > 100) {
                            $this->response->error('分类名需在 1~100 字符');
                        }
                        $parentId = max(0, (int) $this->input->post('parent_id', 0));
                        // 防止深层：parent 必须是本店的 parent_id=0 分类
                        if ($parentId > 0) {
                            $parent = $this->db->fetchOne(
                                'SELECT `id`, `parent_id` FROM `' . $categoryTable . '`
                                  WHERE `id` = ? AND `merchant_id` = ? LIMIT 1',
                                [$parentId, $merchantId]
                            );
                            if ($parent === null) {
                                $this->response->error('父分类不存在');
                            }
                            if ((int) $parent['parent_id'] !== 0) {
                                $this->response->error('仅支持二级分类');
                            }
                        }
                        $icon = trim((string) $this->input->post('icon', ''));
                        $sort = (int) $this->input->post('sort', 100);
                        $status = (int) $this->input->post('status', 1) === 1 ? 1 : 0;

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
                            $exists = $this->db->fetchOne(
                                'SELECT `id` FROM `' . $categoryTable . '` WHERE `id` = ? AND `merchant_id` = ? LIMIT 1',
                                [$id, $merchantId]
                            );
                            if ($exists === null) {
                                $this->response->error('分类不存在');
                            }
                            unset($data['merchant_id']);
                            $this->db->update('merchant_category', $data, $id);
                            $this->response->success('已更新', ['csrf_token' => $this->csrf->refresh()]);
                        } else {
                            $this->db->insert('merchant_category', $data);
                            $this->response->success('已添加', ['csrf_token' => $this->csrf->refresh()]);
                        }
                        break;
                    }

                    case 'delete': {
                        $id = (int) $this->input->post('id', 0);
                        $exists = $this->db->fetchOne(
                            'SELECT `id`, `parent_id` FROM `' . $categoryTable . '`
                              WHERE `id` = ? AND `merchant_id` = ? LIMIT 1',
                            [$id, $merchantId]
                        );
                        if ($exists === null) {
                            $this->response->error('分类不存在');
                        }
                        // 有子分类不能删
                        $child = $this->db->fetchOne(
                            'SELECT `id` FROM `' . $categoryTable . '` WHERE `parent_id` = ? AND `merchant_id` = ? LIMIT 1',
                            [$id, $merchantId]
                        );
                        if ($child !== null) {
                            $this->response->error('请先删除子分类');
                        }
                        $this->db->execute(
                            'DELETE FROM `' . $categoryTable . '` WHERE `id` = ? AND `merchant_id` = ?',
                            [$id, $merchantId]
                        );
                        $this->response->success('已删除', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    // ================= 主站分类映射 =================
                    case 'list_map': {
                        // 读主站分类 + 本店映射（含别名 + 隐藏标记）
                        $cats = $this->db->query(
                            'SELECT `id`, `parent_id`, `name`, `sort`
                               FROM `' . $masterCatTable . '`
                              WHERE `status` = 1
                              ORDER BY `parent_id` ASC, `sort` ASC, `id` ASC'
                        );
                        $maps = $this->db->query(
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
                        $this->response->success('', ['data' => $cats, 'csrf_token' => $this->csrf->token()]);
                        break;
                    }

                    case 'save_map': {
                        $masterId = (int) $this->input->post('master_category_id', 0);
                        if ($masterId <= 0) {
                            $this->response->error('缺少主站分类 id');
                        }
                        $masterExists = $this->db->fetchOne(
                            'SELECT `id` FROM `' . $masterCatTable . '` WHERE `id` = ? LIMIT 1',
                            [$masterId]
                        );
                        if ($masterExists === null) {
                            $this->response->error('主站分类不存在');
                        }
                        $alias = trim((string) $this->input->post('alias_name', ''));
                        if (mb_strlen($alias) > 100) {
                            $this->response->error('别名长度不能超过 100 字符');
                        }

                        $existing = $this->db->fetchOne(
                            'SELECT `id`, `is_hidden` FROM `' . $mapTable . '`
                              WHERE `merchant_id` = ? AND `master_category_id` = ? LIMIT 1',
                            [$merchantId, $masterId]
                        );

                        if ($existing === null) {
                            if ($alias === '') {
                                // 既没别名又没隐藏过 → 完全跟随主站，不写空行
                                $this->response->success('已跟随主站', ['csrf_token' => $this->csrf->refresh()]);
                                break;
                            }
                            $this->db->insert('merchant_category_map', [
                                'merchant_id' => $merchantId,
                                'master_category_id' => $masterId,
                                'alias_name' => $alias,
                                'is_hidden' => 0,
                            ]);
                        } else {
                            // 别名清空 + 隐藏=0 → 行已经没用，直接删
                            if ($alias === '' && (int) $existing['is_hidden'] === 0) {
                                $this->db->execute(
                                    'DELETE FROM `' . $mapTable . '` WHERE `id` = ?',
                                    [(int) $existing['id']]
                                );
                            } else {
                                $this->db->update('merchant_category_map',
                                    ['alias_name' => $alias],
                                    (int) $existing['id']
                                );
                            }
                        }
                        $this->response->success('已保存', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    // 切换"在本店隐藏"开关
                    case 'toggle_hide_map': {
                        $masterId = (int) $this->input->post('master_category_id', 0);
                        if ($masterId <= 0) $this->response->error('缺少主站分类 id');
                        $masterExists = $this->db->fetchOne(
                            'SELECT `id` FROM `' . $masterCatTable . '` WHERE `id` = ? LIMIT 1',
                            [$masterId]
                        );
                        if ($masterExists === null) $this->response->error('主站分类不存在');

                        $existing = $this->db->fetchOne(
                            'SELECT `id`, `alias_name`, `is_hidden` FROM `' . $mapTable . '`
                              WHERE `merchant_id` = ? AND `master_category_id` = ? LIMIT 1',
                            [$merchantId, $masterId]
                        );
                        $cur = $existing ? (int) $existing['is_hidden'] : 0;
                        $next = $cur === 1 ? 0 : 1;

                        if ($existing === null) {
                            $this->db->insert('merchant_category_map', [
                                'merchant_id' => $merchantId,
                                'master_category_id' => $masterId,
                                'alias_name' => '',
                                'is_hidden' => $next,
                            ]);
                        } else {
                            // 取消隐藏 + 没别名 → 行已经没用，直接删
                            if ($next === 0 && (string) $existing['alias_name'] === '') {
                                $this->db->execute(
                                    'DELETE FROM `' . $mapTable . '` WHERE `id` = ?',
                                    [(int) $existing['id']]
                                );
                            } else {
                                $this->db->update('merchant_category_map',
                                    ['is_hidden' => $next],
                                    (int) $existing['id']
                                );
                            }
                        }
                        $this->response->success($next === 1 ? '已隐藏' : '已恢复显示', [
                            'is_hidden' => $next,
                            'csrf_token' => $this->csrf->refresh(),
                        ]);
                        break;
                    }

                    default:
                        $this->response->error('未知操作');
                }
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage() ?: '系统繁忙，请稍后再试');
            }
        }

        \App\Merchant\MerchantShell::render(dirname(__DIR__) . '/View/category.php', $frontUser, $currentMerchant, $merchantLevel);
    }
}
