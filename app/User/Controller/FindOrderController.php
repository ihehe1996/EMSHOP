<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 游客查单页（/user/find_order）。
 *
 * 页面自带最简 HTML 壳，不套用户中心布局。
 */
final class FindOrderController extends UserPublicBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        /**
         * 查单页（独立页面）：游客与登录用户均可使用。
         *
         * 不继承 user 中心模板：自带最简 HTML 框架，仅引入 layui / 站点基础 CSS 与 guest_find.js。
         *
         * 查询模式：
         *   1. token       —— 用浏览器 Cookie 里的 guest_token 列出最近 10 条订单（含游客单与登录用户在本机下的单）
         *   2. credentials —— 凭联系方式 / 订单密码查单：后台开启哪几项就必须填哪几项，都填时 AND 精确匹配
         *   3. orderno     —— 凭订单编号查单
         *
         * 响应：所有 POST 都返回 JSON（列白名单脱敏，不带 order_password、admin_remark、内部 payment_*）。
         *      查单成功 200；未查到订单 422（便于访问日志按状态码聚合，且不会被站点 404.html 拦截）；其余业务错误仍 200。
         * 列表结果里的 order_goods 只带列表展示所需字段，不含发货内容（卡密）——发货内容可能上百万字符，
         * 列表页不展示它，带上会让响应体随卡密数量线性膨胀（详情接口按需取完整内容）。
         */

        $siteName = $this->config->get('sitename', 'EMSHOP');
        $siteLogoType = (string) ($this->config->get('site_logo_type') ?? 'text');
        $siteLogo     = (string) ($this->config->get('site_logo') ?? '');
        // 访客当前展示币种符号；下方 JS 里用它拼金额
        $currencySymbol = $this->currency->visitorSymbol();

        // 游客查单模式配置（开关/占位提示等）
        $gfConfig = app(\App\Model\GuestFindModel::class)->getConfig();
        $prefix = $this->db->prefix();

        /**
         * 订单列白名单：对外返回的字段，避免泄漏 order_password / admin_remark / payment 内部字段。
         * guest_token 属于身份凭证，不能随订单数据下发：否则一个订单号就能换到该游客的所有订单。
         */
        $orderWhiteList = [
            'id', 'order_no', 'user_id',
            'goods_amount', 'discount_amount', 'pay_amount',
            'payment_code', 'payment_name',
            'status', 'pay_time', 'delivery_time', 'complete_time',
            'remark', 'created_at', 'updated_at',
            'display_currency_code', 'display_rate',
            'shipping_address_snapshot',
        ];

        /**
         * 对一行订单做字段白名单 + 金额显示字段拼接。
         *
         * $goods 由调用方取好传入：列表走 getOrderGoodsBatch(..., false)（不带发货内容），
         * 详情走 getOrderGoods()（带发货内容）。详情页要展示卡密，列表不展示，
         * 所以不能在列表路径上顺手把发货内容查出来 —— 那会让响应体随卡密数量暴涨。
         *
         * @param array<string, mixed> $row
         * @param array<int, array<string, mixed>>|null $goods
         * @return array<string, mixed>
         */
        $sanitizeOrder = function (array $row, ?array $goods = null) use ($orderWhiteList): array {
            $out = [];
            foreach ($orderWhiteList as $k) {
                if (array_key_exists($k, $row)) $out[$k] = $row[$k];
            }
            // 金额按下单时冻结的展示货币 + 汇率渲染，历史不漂移
            $dispCode = (string) ($row['display_currency_code'] ?? '');
            $dispRate = (int) ($row['display_rate'] ?? 0);
            $out['pay_amount_display'] = $this->currency->displaySnapshot((int) ($row['pay_amount'] ?? 0), $dispCode, $dispRate, false);
            $out['goods_amount_display'] = $this->currency->displaySnapshot((int) ($row['goods_amount'] ?? 0), $dispCode, $dispRate, false);
            // 供视图层单独取符号拼接用
            $out['_disp_code'] = $dispCode;
            $out['_disp_rate'] = $dispRate;

            // 关联的订单商品（商品字段对外安全，保持原样即可）
            $out['order_goods'] = $goods ?? [];
            return $out;
        };

        // ------------------------- AJAX 查询处理 -------------------------
        if ($this->request->isPost()) {
            header('Content-Type: application/json; charset=utf-8');

            $mode = (string) $this->input->post('mode', '');
            $action = (string) $this->input->post('action', '');

            // —— 子动作：刷新算术验证码（用户点"换一题"时调用）
            // 不计入限流（仅签发新题，不查订单）
            if ($action === 'refresh_captcha') {
                echo json_encode([
                    'code' => 200,
                    'data' => ['expr' => $this->captcha->issue('find_order')],
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $orderNo = trim((string) $this->input->post('order_no', ''));
            $contactQuery = trim((string) $this->input->post('contact_query', ''));
            $orderPassword = trim((string) $this->input->post('order_password', ''));
            $captchaInput = trim((string) $this->input->post('captcha', ''));

            try {
                $result = [];

                // —— credentials / orderno 模式必须过两层防护：
                //   1) IP 限流（撞库的真正防线）：每分钟 ≤ 10 次
                //   2) 算术 captcha（人类友好门槛，过滤无脑脚本）
                // token 模式跳过：只认 v2 签名 Cookie，身份由服务端签名保证
                if ($mode === 'credentials' || $mode === 'orderno') {
                    $rlKey = 'find_order:' . $this->rateLimit->clientIp();
                    if ($this->rateLimit->tooManyAttempts($rlKey, 10)) {
                        $wait = $this->rateLimit->availableIn($rlKey);
                        // 限流命中也刷新一次 captcha，避免攻击者用同一个答案做 retry burst
                        $this->captcha->issue('find_order');
                        throw new \RuntimeException("请求过于频繁，请 {$wait} 秒后再试");
                    }
                    // 先记 hit（即使 captcha 错也算一次，这样答错+重试也吃配额，逼着攻击者降速）
                    $this->rateLimit->hit($rlKey, 60);

                    if (!$this->captcha->verify($captchaInput, 'find_order')) {
                        // 答错时返回一道新题，前端要刷新显示
                        echo json_encode([
                            'code' => 400,
                            'msg' => '验证码错误，请重试',
                            'data' => ['captcha_expr' => $this->captcha->issue('find_order')],
                        ], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                }

                if ($mode === 'token') {
                    // —— tab1：通过浏览器 guest_token 列近 10 单
                    // 只认 v2 签名 Cookie，忽略 POST 里的 guest_token：
                    // 旧 token 没有签名，无法再借此列出订单。
                    $guestToken = \GuestToken::get();
                    $rows = $this->db->query(
                        "SELECT * FROM {$prefix}order WHERE guest_token = ? ORDER BY id DESC LIMIT 10",
                        [$guestToken]
                    );
                    if (empty($rows)) {
                        throw new \RuntimeException('未找到订单');
                    }
                    foreach ($rows as $r) $result[] = $sanitizeOrder($r);

                } elseif ($mode === 'credentials') {
                    // —— tab2：凭据查单（无订单号）
                    // 必填口径与下单页一致：后台开启了哪几项，查单就必须填哪几项；
                    // 两项都开启时两项都必填，且都填时 AND 精确匹配。
                    if ($contactQuery !== '' && !$gfConfig['contact_enabled']) {
                        throw new \RuntimeException('联系方式查单未开启');
                    }
                    if ($orderPassword !== '' && !$gfConfig['password_enabled']) {
                        throw new \RuntimeException('订单密码查单未开启');
                    }
                    if (!empty($gfConfig['contact_enabled']) && $contactQuery === '') {
                        throw new \RuntimeException('请输入' . $gfConfig['contact_type_label']);
                    }
                    if (!empty($gfConfig['password_enabled']) && $orderPassword === '') {
                        throw new \RuntimeException('请输入订单密码');
                    }

                    // 已填项按 AND 精确过滤：order_password 精确匹配；联系方式同样精确匹配
                    $where = [];
                    $params = [];
                    if ($orderPassword !== '') {
                        $where[] = 'order_password = ?';
                        $params[] = $orderPassword;
                    }
                    if ($contactQuery !== '') {
                        $where[] = 'guest_contact = ?';
                        $params[] = $contactQuery;
                    }
                    $sql = "SELECT * FROM {$prefix}order WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 50";

                    // echo $sql;die;

                    $rows = $this->db->query($sql, $params);
                    if (empty($rows)) {
                        throw new \RuntimeException('未找到匹配订单');
                    }
                    foreach ($rows as $r) $result[] = $sanitizeOrder($r);

                    // 凭据验证通过：给本次命中的订单发 60 秒一次性卡密授权。
                    // 只影响卡密查看/导出，订单状态页仍走 session 白名单。
                    if (session_status() === PHP_SESSION_NONE) @session_start();
                    foreach ($rows as $r) {
                        $oid = (int) ($r['id'] ?? 0);
                        if ($oid > 0) {
                            if (!isset($_SESSION['em_card_grant']) || !is_array($_SESSION['em_card_grant'])) {
                                $_SESSION['em_card_grant'] = [];
                            }
                            $_SESSION['em_card_grant'][$oid] = time() + 60;
                        }
                    }

                } elseif ($mode === 'orderno') {
                    // —— tab3：仅订单编号查单（无需凭据）
                    if ($orderNo === '') {
                        throw new \RuntimeException('请输入订单编号');
                    }
                    $row = $this->db->fetchOne(
                        "SELECT * FROM {$prefix}order WHERE order_no = ? LIMIT 1",
                        [$orderNo]
                    );
                    if (!$row) {
                        throw new \RuntimeException('未找到该订单');
                    }
                    $result[] = $sanitizeOrder($row);

                } else {
                    throw new \RuntimeException('无效的查询方式');
                }

                // 统一批量补订单商品行：一次 IN 查询覆盖本次所有命中订单（替代原先每单一次的
                // getOrderGoods）。第二个参数 false = 不查发货内容（卡密），列表不展示它，
                // 带上会让响应体随卡密数量线性膨胀；详情分支会单独按需取完整的。
                $goodsMap = app(\App\Model\OrderModel::class)->getOrderGoodsBatch(array_column($result, 'id'), false);
                foreach ($result as &$r) {
                    $r['order_goods'] = $goodsMap[(int) ($r['id'] ?? 0)] ?? [];
                }
                unset($r);

                // 查询成功：把命中订单号加入 session"已授权可看详情"白名单。
                // 详情页 GET ?order_no=xxx 必须命中白名单才能访问，避免攻击者绕过 POST 路径上的
                // captcha + ratelimit，直接遍历订单号偷数据。
                if (session_status() === PHP_SESSION_NONE) @session_start();
                $visible = $_SESSION['em_find_order_visible'] ?? [];
                foreach ($result as $r) {
                    $no = (string) ($r['order_no'] ?? '');
                    if ($no !== '') $visible[$no] = time();
                }
                // 防 session 无限膨胀：只保留 30 分钟内访问过的，过期的清掉
                $cutoff = time() - 1800;
                foreach ($visible as $no => $t) {
                    if ($t < $cutoff) unset($visible[$no]);
                }
                $_SESSION['em_find_order_visible'] = $visible;

                // 业务要求：订单号查单也允许查看发货内容。
                // 与可见性白名单保持同样的 30 分钟窗口，避免客户打开详情页时授权已过期。
                if ($mode === 'orderno') {
                    if (!isset($_SESSION['em_card_grant']) || !is_array($_SESSION['em_card_grant'])) {
                        $_SESSION['em_card_grant'] = [];
                    }
                    foreach ($result as $r) {
                        $oid = (int) ($r['id'] ?? 0);
                        if ($oid > 0) {
                            $_SESSION['em_card_grant'][$oid] = time() + 1800;
                        }
                    }
                }

                // 查单结果列表轮询用：与 order_poll.php action=find_pending_snapshot 的 hash 算法一致
                $pendingPairs = [];
                $pollOrderNos = [];
                foreach ($result as $r) {
                    $no = (string) ($r['order_no'] ?? '');
                    if ($no !== '') {
                        $pollOrderNos[] = $no;
                    }
                    $st = (string) ($r['status'] ?? '');
                    if ($no !== '' && in_array($st, ['paid', 'delivering'], true)) {
                        $pendingPairs[] = $no . '|' . $st;
                    }
                }
                sort($pendingPairs);
                $pollOrderNos = array_values(array_unique($pollOrderNos));
                if (count($pollOrderNos) > 50) {
                    $pollOrderNos = array_slice($pollOrderNos, 0, 50);
                }
                $pendingHash = $pendingPairs === [] ? 'empty' : md5(implode("\n", $pendingPairs));

                echo json_encode([
                    'code' => 200,
                    'data' => $result,
                    'meta' => [
                        'pending_delivery_hash' => $pendingHash,
                        'poll_order_nos'        => $pollOrderNos,
                    ],
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } catch (\Throwable $e) {
                // 未查到订单按 HTTP 422 返回，便于访问日志按状态码聚合回溯。
                // 不用 404：站点把 404 统一替换成 /404.html，JSON body 会被丢掉。
                // 验证码/参数错误仍保持 200 + 业务 code，兼容现有前端约定。
                $notFound = ['未找到订单', '未找到该订单', '未找到匹配订单'];
                if (in_array((string) $e->getMessage(), $notFound, true)) {
                    http_response_code(422);
                }

                // 失败时给 captcha 模式补发一道新题（已 consume，不发新的会让用户误以为还能用旧值）
                $errPayload = ['code' => 400, 'msg' => $e->getMessage()];
                if ($mode === 'credentials' || $mode === 'orderno') {
                    $errPayload['data'] = ['captcha_expr' => $this->captcha->issue('find_order')];
                }
                echo json_encode($errPayload, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // ------------------------- 页面渲染 -------------------------
        // 注意：不使用 index_public.php（公共页头页尾），这里自带最简壳
        $guestToken = \GuestToken::get();
        $esc = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        // 当前 tab：?tab=token / credentials / orderno；缺省 = token（浏览器订单）
        // 走 URL 参数而不是单页 tab，是为了让"刷新页面"能停在用户当前所在的查询方式，
        // 同时配合 PJAX 切 tab 不整页刷新
        $validTabs = ['token', 'credentials', 'orderno'];
        $currentTab = (string) $this->input->get('tab', 'token');
        if (!in_array($currentTab, $validTabs, true)) $currentTab = 'token';

        // credentials/orderno tab 没在配置开启时降级到 token
        $contactOn = !empty($gfConfig['contact_enabled']);
        $passwordOn = !empty($gfConfig['password_enabled']);
        if (($currentTab === 'credentials' || $currentTab === 'orderno') && !$contactOn && !$passwordOn) {
            $currentTab = 'token';
        }

        // 初始 captcha：只有进入 credentials/orderno 时才签发，token tab 不要 captcha
        // 注意：每次页面渲染（含 PJAX 切 tab）都会重新签发，自然替换旧题
        $captchaExpr = '';
        if ($currentTab === 'credentials' || $currentTab === 'orderno') {
            $captchaExpr = $this->captcha->issue('find_order');
        }

        // GET ?order_no=xxx → 渲染单订单详情（游客场景，无侧边栏）
        // 安全约束：必须先经过 POST 查询（受 captcha + ratelimit 保护）拿到这个订单号，
        // 才会被写入 session 白名单，详情页才允许显示。
        // 直接拼链接访问 / 复制别人的订单号 → 白名单不命中 → 走拒绝分支，
        // 拒绝时不区分"订单不存在"和"订单存在但你无权看"，避免被用作存在性探测。
        $detailOrder = null;
        $detailGoods = [];
        $detailExtraPairs = [];
        $detailDenied = false;
        $detailOrderNo = trim((string) $this->input->get('order_no', ''));
        if ($detailOrderNo !== '') {
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $visible = $_SESSION['em_find_order_visible'] ?? [];
            if (!isset($visible[$detailOrderNo])) {
                $detailDenied = true;
            } else {
                $row = $this->db->fetchOne(
                    "SELECT * FROM {$prefix}order WHERE order_no = ? LIMIT 1",
                    [$detailOrderNo]
                );
                if ($row) {
                    $detailExtraPairs = app(\App\Model\OrderModel::class)->parseBuyerContactFields($row)['extra_pairs'];

                    // 卡密内容：登录本人 / v2 签名 Cookie 匹配 / 凭据换来的 60 秒 grant /
                    // 订单号查单换来的 30 分钟 grant（业务要求订单号也能看发货内容）。
                    $cardAllowed = false;
                    if (!empty($frontUser['id']) && (int) ($row['user_id'] ?? 0) === (int) $frontUser['id']) {
                        $cardAllowed = true;
                    } elseif (empty($frontUser) && \GuestToken::hasValidCookie()
                        && (string) ($row['guest_token'] ?? '') !== ''
                        && hash_equals((string) $row['guest_token'], \GuestToken::get())) {
                        $cardAllowed = true;
                    } else {
                        if (session_status() === PHP_SESSION_NONE) @session_start();
                        $grant = (int) ($_SESSION['em_card_grant'][(int) ($row['id'] ?? 0)] ?? 0);
                        if ($grant >= time()) {
                            $cardAllowed = true;
                        }
                    }

                    $detailGoodsRaw = app(\App\Model\OrderModel::class)->getOrderGoods((int) $row['id']);
                    if (!$cardAllowed) {
                        // 未验证身份：剥离发货内容，模板/插件就不会渲染卡密和导出按钮
                        foreach ($detailGoodsRaw as &$g) {
                            unset($g['delivery_content']);
                        }
                        unset($g);
                    }

                    $detailOrder = $sanitizeOrder($row, $detailGoodsRaw);
                    $detailGoods = $detailOrder['order_goods'] ?? [];
                    if ($cardAllowed && app(\App\Model\OrderModel::class)->isPurchasedStatus((string) ($detailOrder['status'] ?? ''))) {
                        $detailGoods = app(\App\Model\OrderModel::class)->attachGoodsGuides($detailGoods);
                    }
                } else {
                    // 白名单写入后订单又被删除的边角情况：也走拒绝分支，对外表现一致
                    $detailDenied = true;
                }
            }
        }

        // 订单状态中文 + 配色
        $statusMap = [
            'pending'    => ['label' => '待付款', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
            'paid'       => ['label' => '已付款', 'color' => '#2563eb', 'bg' => '#dbeafe'],
            'delivering' => ['label' => '发货中', 'color' => '#6366f1', 'bg' => '#e0e7ff'],
            'delivered'  => ['label' => '已发货', 'color' => '#8b5cf6', 'bg' => '#ede9fe'],
            'completed'  => ['label' => '已完成', 'color' => '#059669', 'bg' => '#d1fae5'],
            'refunding'  => ['label' => '退款中', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
            'refunded'   => ['label' => '已退款', 'color' => '#6b7280', 'bg' => '#f3f4f6'],
            'cancelled'  => ['label' => '已取消', 'color' => '#9ca3af', 'bg' => '#f3f4f6'],
            'expired'    => ['label' => '已过期', 'color' => '#9ca3af', 'bg' => '#f3f4f6'],
            'failed'     => ['label' => '失败',   'color' => '#e11d48', 'bg' => '#ffe4e6'],
        ];

        $this->render('find_order_page', get_defined_vars());
    }
}
