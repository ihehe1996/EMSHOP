<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
?>
<div class="uc-page">
    <div class="uc-page-header">
        <h2 class="uc-page-title">余额明细</h2>
        <p class="uc-page-desc">当前余额：<strong style="color:#fa5252;"><?= Currency::displayAmount((int) ($frontUser['money'] ?? 0)) ?></strong></p>
    </div>

    <?php if (!empty($logList)): ?>
    <div class="uc-form-card" style="padding:0; overflow:hidden;">
        <table class="uc-table">
            <thead>
                <tr>
                    <th>时间</th>
                    <th>类型</th>
                    <th>金额</th>
                    <th>变动前</th>
                    <th>变动后</th>
                    <th>备注</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logList as $log): ?>
                <tr>
                    <td class="uc-table-time"><?= htmlspecialchars(substr($log['created_at'], 0, 19)) ?></td>
                    <td>
                        <?php if ($log['type'] === 'increase'): ?>
                        <span class="uc-badge uc-badge--green">收入</span>
                        <?php else: ?>
                        <span class="uc-badge uc-badge--red">支出</span>
                        <?php endif; ?>
                    </td>
                    <td class="<?= $log['type'] === 'increase' ? 'uc-text-green' : 'uc-text-red' ?>">
                        <?= $log['type'] === 'increase' ? '+' : '-' ?><?= Currency::displayAmount((int) $log['amount']) ?>
                    </td>
                    <td><?= Currency::displayAmount((int) $log['before_balance']) ?></td>
                    <td><?= Currency::displayAmount((int) $log['after_balance']) ?></td>
                    <td class="uc-table-remark"><?= htmlspecialchars($log['remark'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="uc-pagination">
        <?php if ($page > 1): ?>
        <a href="/user/balance_log.php?page=<?= $page - 1 ?>" data-pjax="#userContent" class="uc-page-btn">&laquo; 上一页</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
        <a href="/user/balance_log.php?page=<?= $i ?>" data-pjax="#userContent" class="uc-page-btn<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="/user/balance_log.php?page=<?= $page + 1 ?>" data-pjax="#userContent" class="uc-page-btn">下一页 &raquo;</a>
        <?php endif; ?>

        <span class="uc-page-info">共 <?= $total ?> 条</span>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="uc-empty">
        <i class="fa fa-inbox"></i>
        <p>暂无余额变动记录</p>
    </div>
    <?php endif; ?>
</div>
