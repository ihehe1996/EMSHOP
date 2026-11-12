<?php

declare(strict_types=1);

namespace App\Service;





/**
 * 免责协议签署状态。
 *
 * 协议正文在后台框架壳 app/Admin/View/shell.php 中渲染；本类只负责版本号与签署记录，
 * 记录写入 config 表的 disclaimer_accepted 键（JSON），全站保留最近一次签署。
 */
final class DisclaimerService extends BaseService
{
    /**
     * 当前协议版本。
     *
     * 修改协议正文时必须同步递增；版本变化后，已签署用户会重新看到签署弹窗。
     */
    public const VERSION = '1';

    private const CONFIG_KEY = 'disclaimer_accepted';

    /**
     * 当前站点是否已签署当前版本的协议。
     */
    public function accepted(): bool
    {
        $record = $this->record();

        return $record !== null && (string) ($record['version'] ?? '') === self::VERSION;
    }

    /**
     * 读取最近一次签署记录。
     *
     * @return array<string, mixed>|null
     */
    public function record(): ?array
    {
        $raw = $this->config->get(self::CONFIG_KEY, '');
        if ($raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * 写入签署记录。
     */
    public function accept(int $adminId, string $account, string $ip): bool
    {
        $payload = json_encode([
            'version'  => self::VERSION,
            'admin_id' => $adminId,
            'account'  => $account,
            'ip'       => $ip,
            'time'     => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return $this->config->set(self::CONFIG_KEY, (string) $payload);
    }
}
