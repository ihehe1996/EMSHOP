/**
 * 异步发货轮询：订单处于 paid / delivering 时定时请求 order_poll.php，
 * 状态变化后整页刷新以展示卡密 / 最新状态。
 */
(function (global) {
    var detailTimer = null;
    var listTimer = null;
    var findSnapshotTimer = null;
    var lastListHash = null;
    var lastFindHash = null;

    function stopDetail() {
        if (detailTimer) {
            clearInterval(detailTimer);
            detailTimer = null;
        }
    }

    function stopList() {
        if (listTimer) {
            clearInterval(listTimer);
            listTimer = null;
        }
    }

    function stopFindSnapshot() {
        if (findSnapshotTimer) {
            clearInterval(findSnapshotTimer);
            findSnapshotTimer = null;
        }
    }

    function awaiting(status) {
        return status === 'paid' || status === 'delivering';
    }

    function postPoll(payload, done) {
        if (typeof jQuery === 'undefined') return;
        jQuery.post('/user/order_poll.php', payload, function (res) {
            if (!res || res.code !== 200) return;
            done(res.data || {});
        }, 'json');
    }

    /**
     * 订单详情 / 查单详情页：轮询单号状态。
     * @param {{ orderNo: string, csrfToken: string, initialStatus: string, intervalMs?: number, maxMs?: number }} opts
     */
    function startDetail(opts) {
        stopDetail();
        var orderNo = opts.orderNo;
        var csrf = opts.csrfToken || '';
        var st0 = opts.initialStatus || '';
        if (!orderNo || !csrf || !awaiting(st0)) return;

        var intervalMs = opts.intervalMs || 3500;
        var maxMs = opts.maxMs || 900000;
        var t0 = Date.now();

        function tickDetail() {
            if (Date.now() - t0 > maxMs) {
                stopDetail();
                return;
            }
            postPoll({ order_no: orderNo, csrf_token: csrf }, function (data) {
                var st = data.status || '';
                if (!awaiting(st)) {
                    stopDetail();
                    global.location.reload();
                }
            });
        }
        tickDetail();
        detailTimer = setInterval(tickDetail, intervalMs);
    }

    /**
     * 我的订单列表：轮询「待异步发货」快照 hash，变化则刷新（PJAX 或整页均可）。
     */
    function startList(opts) {
        stopList();
        var csrf = opts.csrfToken || '';
        lastListHash = opts.initialHash;
        if (!csrf || lastListHash === null || lastListHash === undefined) return;
        if (lastListHash === 'skip') return;

        var intervalMs = opts.intervalMs || 5000;
        var maxMs = opts.maxMs || 900000;
        var t0 = Date.now();

        function tickList() {
            if (Date.now() - t0 > maxMs) {
                stopList();
                return;
            }
            postPoll({ action: 'pending_snapshot', csrf_token: csrf }, function (data) {
                var h = data.hash;
                if (h === undefined || h === null) return;
                if (String(h) !== String(lastListHash)) {
                    stopList();
                    global.location.reload();
                }
            });
        }
        tickList();
        listTimer = setInterval(tickList, intervalMs);
    }

    /**
     * 查单页结果列表（浏览器订单 / 凭据 / 订单号）：轮询待发货快照 hash，变化则刷新。
     * @param {{ orderNos: string[], csrfToken: string, initialHash: string, intervalMs?: number, maxMs?: number }} opts
     */
    function startFindSnapshot(opts) {
        stopFindSnapshot();
        var csrf = opts.csrfToken || '';
        var nos = opts.orderNos || [];
        lastFindHash = opts.initialHash;
        if (!csrf || lastFindHash === null || lastFindHash === undefined) return;
        if (lastFindHash === 'skip' || lastFindHash === 'empty') return;
        if (!nos.length) return;

        var intervalMs = opts.intervalMs || 5000;
        var maxMs = opts.maxMs || 900000;
        var t0 = Date.now();

        function tickFind() {
            if (Date.now() - t0 > maxMs) {
                stopFindSnapshot();
                return;
            }
            postPoll(
                {
                    action: 'find_pending_snapshot',
                    csrf_token: csrf,
                    order_nos: JSON.stringify(nos)
                },
                function (data) {
                    var h = data.hash;
                    if (h === undefined || h === null) return;
                    if (String(h) !== String(lastFindHash)) {
                        stopFindSnapshot();
                        global.location.reload();
                    }
                }
            );
        }
        tickFind();
        findSnapshotTimer = setInterval(tickFind, intervalMs);
    }

    global.EMSOrderPoll = {
        startDetail: startDetail,
        startList: startList,
        startFindSnapshot: startFindSnapshot,
        stopDetail: stopDetail,
        stopList: stopList,
        stopFindSnapshot: stopFindSnapshot
    };
})(typeof window !== 'undefined' ? window : this);
