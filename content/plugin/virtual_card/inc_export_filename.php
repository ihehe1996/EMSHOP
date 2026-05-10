<?php

declare(strict_types=1);

defined('EM_ROOT') || exit('Access Denied');

/**
 * 将订单/商品等时间字段规范为 yyyyMMdd（供文件名使用）。
 */
function virtual_card_datetime_to_ymd(?string $datetime): string
{
    if ($datetime === null || trim($datetime) === '') {
        return date('Ymd');
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return date('Ymd');
    }

    return date('Ymd', $ts);
}

/**
 * 导出 txt 文件名：{商品名}_{下单日期yyyyMMdd}_{数量}条_{导出时间yyyyMMdd_HHmmss}.txt
 *
 * @param string $placeDateYmd 下单日（订单导出为订单创建日；卡库导出为商品创建日），须为 8 位数字或空则按当天
 * @return string UTF-8 文件名（含扩展名）
 */
function virtual_card_build_export_filename(string $title, int $count, string $placeDateYmd = ''): string
{
    $t = trim($title);
    if ($t === '') {
        $t = '卡密';
    }
    $safe = preg_replace('/[\\\\\\/:\\*\\?"<>|]/u', '_', $t);
    $safe = preg_replace('/\\s+/u', ' ', $safe);
    if (function_exists('mb_substr')) {
        $safe = mb_substr($safe, 0, 50, 'UTF-8');
    } else {
        $safe = substr($safe, 0, 50);
    }
    $safe = trim($safe);
    if ($safe === '') {
        $safe = 'export';
    }

    $ymd = preg_match('/^\d{8}$/', $placeDateYmd) ? $placeDateYmd : date('Ymd');

    return $safe . '_' . $ymd . '_' . max(0, $count) . '条_' . date('Ymd_His') . '.txt';
}

/**
 * Content-Disposition：ASCII fallback + RFC5987 filename*
 */
function virtual_card_export_disposition(string $utf8Filename): string
{
    $ascii = preg_replace('/[^\x20-\x7E]/u', '_', $utf8Filename);
    $ascii = preg_replace('/_+/', '_', $ascii);
    $ascii = trim($ascii, '._');
    if ($ascii === '' || $ascii === '_') {
        $ascii = 'cards_' . date('YmdHis') . '.txt';
    }
    $quotedAscii = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $ascii) . '"';

    return 'attachment; filename=' . $quotedAscii . "; filename*=UTF-8''" . rawurlencode($utf8Filename);
}
