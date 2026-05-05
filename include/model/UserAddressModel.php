<?php

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 用户收货地址模型（核心通用数据）。
 *
 * 数据表：em_user_address
 * 定位：用户级资源（和头像 / 余额同层），供后续实物商品插件读用；不耦合具体商品类型。
 *
 * 约束：
 *   - 每个 user 最多一条 is_default=1，由 setDefault() 事务保证
 *   - 所有"按 id"方法都强制带 user_id 做 owner 校验，防越权改别人地址
 *   - 省市区以文本快照存，不引行政区划外键，避免后续地名调整污染历史数据
 *
 * @package EM\Core\Model
 */
class UserAddressModel
{
    /** 单用户最多地址数（防滥用）；前端 & 控制器双层限制 */
    public const MAX_PER_USER = 20;

    /**
     * 列某用户的所有地址。默认地址排前，其余按最近创建倒序。
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listByUserId(int $userId): array
    {
        $prefix = Database::prefix();
        return Database::query(
            "SELECT * FROM {$prefix}user_address WHERE user_id = ? ORDER BY is_default DESC, id DESC",
            [$userId]
        );
    }

    /**
     * 拿某用户的默认地址；没设默认时返回最近一条；无地址返回 null。
     *
     * @return array<string, mixed>|null
     */
    public static function getDefault(int $userId): ?array
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT * FROM {$prefix}user_address WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1",
            [$userId]
        );
        return $row ?: null;
    }

    /**
     * 按 id + user_id 取单条（owner 校验，越权访问返回 null）。
     *
     * @return array<string, mixed>|null
     */
    public static function findById(int $id, int $userId): ?array
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT * FROM {$prefix}user_address WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        return $row ?: null;
    }

    /**
     * 用户地址条数（给 Controller 做 MAX_PER_USER 限制时用）。
     */
    public static function countByUserId(int $userId): int
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$prefix}user_address WHERE user_id = ?",
            [$userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * 新建地址。$data 字段：recipient / mobile / province / city / district / detail / is_default
     *
     * 若 is_default=1：事务内先把该 user 其他地址 is_default 置 0，再插入当前为 1。
     *
     * @return int 新记录 id
     */
    public static function create(int $userId, array $data): int
    {
        $prefix = Database::prefix();
        $isDefault = (int) ($data['is_default'] ?? 0) === 1 ? 1 : 0;

        Database::begin();
        try {
            if ($isDefault === 1) {
                Database::execute(
                    "UPDATE {$prefix}user_address SET is_default = 0 WHERE user_id = ? AND is_default = 1",
                    [$userId]
                );
            }
            // Database::insert() 内部自动加前缀，这里传裸表名
            $id = Database::insert('user_address', [
                'user_id'   => $userId,
                'recipient' => (string) ($data['recipient'] ?? ''),
                'mobile'    => (string) ($data['mobile']    ?? ''),
                'province'  => (string) ($data['province']  ?? ''),
                'city'      => (string) ($data['city']      ?? ''),
                'district'  => (string) ($data['district']  ?? ''),
                'detail'    => (string) ($data['detail']    ?? ''),
                'is_default'=> $isDefault,
            ]);
            Database::commit();
            return $id;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * 更新地址。owner 校验：id 必须属于 $userId，否则 update 0 行返回 false。
     *
     * @return bool 是否命中
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        $prefix = Database::prefix();
        $isDefault = (int) ($data['is_default'] ?? 0) === 1 ? 1 : 0;

        Database::begin();
        try {
            if ($isDefault === 1) {
                // 升级为默认：先清空该用户所有默认标记
                Database::execute(
                    "UPDATE {$prefix}user_address SET is_default = 0 WHERE user_id = ? AND is_default = 1",
                    [$userId]
                );
            }
            $affected = Database::execute(
                "UPDATE {$prefix}user_address SET
                    recipient = ?, mobile = ?, province = ?, city = ?, district = ?, detail = ?, is_default = ?
                 WHERE id = ? AND user_id = ?",
                [
                    (string) ($data['recipient'] ?? ''),
                    (string) ($data['mobile']    ?? ''),
                    (string) ($data['province']  ?? ''),
                    (string) ($data['city']      ?? ''),
                    (string) ($data['district']  ?? ''),
                    (string) ($data['detail']    ?? ''),
                    $isDefault,
                    $id,
                    $userId,
                ]
            );
            Database::commit();
            return $affected > 0;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * 硬删除地址（owner 校验内置）。
     */
    public static function delete(int $id, int $userId): bool
    {
        $prefix = Database::prefix();
        $affected = Database::execute(
            "DELETE FROM {$prefix}user_address WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        return $affected > 0;
    }

    /**
     * 把指定地址设为默认：事务里把其他置 0，再把当前置 1。
     * owner 校验：id 不属于 user 时第二步 update 命中 0 行，自动失败。
     */
    public static function setDefault(int $id, int $userId): bool
    {
        $prefix = Database::prefix();
        Database::begin();
        try {
            Database::execute(
                "UPDATE {$prefix}user_address SET is_default = 0 WHERE user_id = ? AND is_default = 1",
                [$userId]
            );
            $affected = Database::execute(
                "UPDATE {$prefix}user_address SET is_default = 1 WHERE id = ? AND user_id = ?",
                [$id, $userId]
            );
            Database::commit();
            return $affected > 0;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
