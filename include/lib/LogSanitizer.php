<?php

declare(strict_types=1);

/**
 * 日志脱敏。
 *
 * 背景：日志文件（content/server/server.log、worker.*.log）落在 web 根内，任何
 * 能访问站点的人都能直接下载。因此日志里不能出现「拿到就能冒充或接管」的东西 ——
 * 尤其是**订单号**：它是游客查单的凭据之一，配合联系方式即可读到订单详情与卡密。
 *
 * 设计原则：只脱敏真正敏感的字段，不把整个日志糊掉 ——
 * 订单 ID、任务 ID 这类内部主键保留（排障要靠它们定位订单），
 * 订单号、联系方式、哈希、令牌、签名、密钥则一律打码。
 *
 * 用法：CliServer::log() 会统一过一遍，调用方不需要各自处理。
 */
final class LogSanitizer
{
    /**
     * 对一条日志消息做脱敏。
     */
    public static function sanitize(string $message): string
    {
        if ($message === '') {
            return '';
        }

        // ① 先处理 key=value / key: value 形式的敏感赋值，
        //    否则密码、令牌会原样留下（后续规则认不出它们）
        $message = self::maskAssignments($message);

        // ② 订单号：20 位纯数字（YmdHis + 6 位随机），充值单是 R + 数字
        //    保留前 4 位与后 2 位，中间打码 —— 既不泄露凭据，又能肉眼对上是哪一单
        $message = preg_replace_callback(
            '/\b(R?)(\d{20})\b/',
            static function (array $m): string {
                $digits = $m[2];
                return $m[1] . substr($digits, 0, 4) . str_repeat('*', 14) . substr($digits, -2);
            },
            $message
        ) ?? $message;

        // ③ 邮箱：保留首字符与域名
        $message = preg_replace_callback(
            '/([A-Za-z0-9._%+\-])([A-Za-z0-9._%+\-]*)@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})/',
            static fn(array $m): string => $m[1] . '***@' . $m[3],
            $message
        ) ?? $message;

        // ④ 中国大陆手机号
        $message = preg_replace('/(?<!\d)(1[3-9]\d)\d{4}(\d{4})(?!\d)/', '$1****$2', $message) ?? $message;

        // ⑤ 身份证号（18 位，末位可为 X）
        $message = preg_replace('/(?<!\d)(\d{6})\d{8}(\d{3}[\dXx])(?!\d)/', '$1********$2', $message) ?? $message;

        // ⑥ 长 hex / base64 串：md5/sha 哈希、JWT、各类令牌与签名
        //    阈值取 32，避免误伤正常的短标识
        $message = preg_replace_callback(
            '/\b[A-Fa-f0-9]{32,}\b/',
            static fn(array $m): string => '***(' . strlen($m[0]) . '位哈希)',
            $message
        ) ?? $message;

        return $message;
    }

    /**
     * 把 `password=xxx` / `token: xxx` / `api_key=xxx` 这类赋值里的值打码。
     *
     * 值的匹配要么是带引号的串，要么是不含空白的连续串。
     */
    private static function maskAssignments(string $message): string
    {
        $keywords = 'password|passwd|pwd|secret|token|api_key|apikey|private_key|app_secret|sign|signature|card_pwd|key';

        return preg_replace(
            '/\b(' . $keywords . ')(\s*[=:]\s*)(\'[^\']*\'|"[^"]*"|[^\s,;&]+)/i',
            '$1$2***',
            $message
        ) ?? $message;
    }
}
