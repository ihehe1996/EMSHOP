<?php

declare(strict_types=1);

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 游客查单模式配置模型。
 *
 * 统一管理游客查单模式的配置读取，提供语义化的接口。
 * 配置项全部存储在 em_config 表，通过 Config::get()/set() 访问。
 *
 * 配置项说明：
 * - guest_find_contact_enabled             联系方式查单开关 ('1'/'0')
 * - guest_find_password_enabled            订单密码查单开关 ('1'/'0')
 * - guest_find_contact_type                联系方式类型 ('any'/'phone'/'email'/'qq')
 * - guest_find_contact_checkout_placeholder 联系方式查单-下单页占位提示
 * - guest_find_contact_lookup_placeholder   联系方式查单-查单页占位提示
 * - guest_find_password_checkout_placeholder 订单密码查单-下单页占位提示
 * - guest_find_password_lookup_placeholder   订单密码查单-查单页占位提示
 */
class GuestFindModel
{
    // 联系方式类型（供前端显示/校验用）
    public const CONTACT_TYPE_ANY   = 'any';   // 不限类型（默认），任何联系方式均可
    public const CONTACT_TYPE_PHONE = 'phone';
    public const CONTACT_TYPE_EMAIL = 'email';
    public const CONTACT_TYPE_QQ    = 'qq';

    // 占位提示默认值 —— 跟 UI 文案保持一致
    private const DEFAULT_CONTACT_CHECKOUT_PLACEHOLDER  = '请输入您的联系方式';
    private const DEFAULT_CONTACT_LOOKUP_PLACEHOLDER    = '请输入您的联系方式';
    private const DEFAULT_PASSWORD_CHECKOUT_PLACEHOLDER = '请设置您的订单密码';
    private const DEFAULT_PASSWORD_LOOKUP_PLACEHOLDER   = '请设置您的订单密码';

    private static ?array $cache = null;

    // ============================================================
    // 开关状态
    // ============================================================

    /**
     * 联系方式查单是否开启
     */
    public static function isContactEnabled(): bool
    {
        return Config::get('guest_find_contact_enabled', '0') === '1';
    }

    /**
     * 订单密码查单是否开启。
     *
     * 兜底规则：若两种查单方式（联系方式/订单密码）都未开启，视为订单密码开启，
     * 保证查单功能至少有一条通路。用户已手动开启联系方式时则完全尊重其设置。
     */
    public static function isPasswordEnabled(): bool
    {
        $password = Config::get('guest_find_password_enabled', '0') === '1';
        if ($password) {
            return true;
        }
        // 联系方式也没开 → 兜底开启订单密码
        return Config::get('guest_find_contact_enabled', '0') !== '1';
    }

    /**
     * 是否有任意查单方式开启
     */
    public static function isEnabled(): bool
    {
        return self::isContactEnabled() || self::isPasswordEnabled();
    }

    // ============================================================
    // 联系方式类型
    // ============================================================

    /**
     * 获取联系方式类型（默认 any，表示不限类型）
     */
    public static function getContactType(): string
    {
        return Config::get('guest_find_contact_type', self::CONTACT_TYPE_ANY);
    }

    /**
     * 获取联系方式类型的前台展示 label。
     *
     * "任意"类型在后台选项里叫"任意"（getContactTypeOptions），
     * 但在前台作为 input label 显示时，"联系方式"更符合用户视角，故在此统一转换。
     */
    public static function getContactTypeLabel(): string
    {
        $map = [
            self::CONTACT_TYPE_ANY   => '联系方式',
            self::CONTACT_TYPE_PHONE => '手机号码',
            self::CONTACT_TYPE_EMAIL => '邮箱地址',
            self::CONTACT_TYPE_QQ    => 'QQ号码',
        ];
        return $map[self::getContactType()] ?? '联系方式';
    }

    /**
     * 获取联系方式输入框的 input type 属性
     */
    public static function getContactInputType(): string
    {
        static $map = [
            self::CONTACT_TYPE_PHONE => 'tel',
            self::CONTACT_TYPE_EMAIL => 'email',
            self::CONTACT_TYPE_QQ    => 'text',
            self::CONTACT_TYPE_ANY   => 'text',
        ];
        return $map[self::getContactType()] ?? 'text';
    }

    /**
     * 联系方式类型选项（供后台单选用；any 放首位作为默认）
     */
    public static function getContactTypeOptions(): array
    {
        return [
            self::CONTACT_TYPE_ANY   => '任意',
            self::CONTACT_TYPE_PHONE => '手机号码',
            self::CONTACT_TYPE_EMAIL => '邮箱地址',
            self::CONTACT_TYPE_QQ    => 'QQ号码',
        ];
    }

    // ============================================================
    // 占位提示文本
    // ============================================================

    /** 下单页：联系方式输入框占位 */
    public static function getContactCheckoutPlaceholder(): string
    {
        return Config::get('guest_find_contact_checkout_placeholder', self::DEFAULT_CONTACT_CHECKOUT_PLACEHOLDER);
    }

    /** 查单页：联系方式输入框占位 */
    public static function getContactLookupPlaceholder(): string
    {
        return Config::get('guest_find_contact_lookup_placeholder', self::DEFAULT_CONTACT_LOOKUP_PLACEHOLDER);
    }

    /** 下单页：订单密码输入框占位 */
    public static function getPasswordCheckoutPlaceholder(): string
    {
        return Config::get('guest_find_password_checkout_placeholder', self::DEFAULT_PASSWORD_CHECKOUT_PLACEHOLDER);
    }

    /** 查单页：订单密码输入框占位 */
    public static function getPasswordLookupPlaceholder(): string
    {
        return Config::get('guest_find_password_lookup_placeholder', self::DEFAULT_PASSWORD_LOOKUP_PLACEHOLDER);
    }

    // ============================================================
    // 批量获取（供视图一次性传递所有配置）
    // ============================================================

    /**
     * 获取所有配置的数组（用于视图一次性 assign）
     *
     * 返回字段：
     *   contact_enabled / password_enabled / is_enabled
     *   contact_type / contact_type_label / contact_input_type / contact_type_options
     *   contact_checkout_placeholder / contact_lookup_placeholder
     *   password_checkout_placeholder / password_lookup_placeholder
     */
    public static function getConfig(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [
            'contact_enabled'               => self::isContactEnabled(),
            'password_enabled'              => self::isPasswordEnabled(),
            'is_enabled'                    => self::isEnabled(),
            'contact_type'                  => self::getContactType(),
            'contact_type_label'            => self::getContactTypeLabel(),
            'contact_input_type'            => self::getContactInputType(),
            'contact_type_options'          => self::getContactTypeOptions(),
            'contact_checkout_placeholder'  => self::getContactCheckoutPlaceholder(),
            'contact_lookup_placeholder'    => self::getContactLookupPlaceholder(),
            'password_checkout_placeholder' => self::getPasswordCheckoutPlaceholder(),
            'password_lookup_placeholder'   => self::getPasswordLookupPlaceholder(),
        ];

        return self::$cache;
    }

    /**
     * 清除缓存（配置变更后调用）
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
