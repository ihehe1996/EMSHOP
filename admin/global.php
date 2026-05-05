<?php

declare(strict_types=1);

require dirname(__DIR__) . '/init.php';

/**
 * 后台公共文件。
 *
 * 后台其它页面都可以直接引入本文件，统一获得初始化、登录校验、当前管理员信息等能力，
 * 避免每个后台页面重复写相同的认证代码。
 */
$auth = new AuthService();

/**
 * 当前已登录管理员信息。
 *
 * @var array<string, mixed>|null $adminUser
 */
$adminUser = null;

/**
 * 执行后台登录校验。
 */
function adminRequireLogin(): void
{
    global $auth, $adminUser;

    if (!$auth->check()) {
        Response::redirect(adminSignUrl());
    }

    $adminUser = $auth->user();

    // 当前请求作用域：主站后台固定 'main'
    // TemplateStorage / Storage / PluginModel 等"按 scope 存取"的组件会读这个全局
    $GLOBALS['__em_current_scope'] = 'main';
}

/**
 * 构造登录页 URL，自动携带安全入口参数。
 * 设置了 admin_entry_key 时跳转 /admin/sign.php?s=xxx；否则无参数。
 */
function adminSignUrl(): string
{
    $key = trim((string) Config::get('admin_entry_key', ''));
    return $key === '' ? '/admin/sign.php' : '/admin/sign.php?s=' . urlencode($key);
}

/**
 * 安全入口守卫：用于 sign.php 等未登录也能访问的后台入口页。
 *
 * 行为：
 *   - 未配置 admin_entry_key 时：放行
 *   - 已配置时：从 GET.s（POST 表单也兼容）读 key，不匹配则输出 403 提示页并 exit
 *
 * 安全关键点：提示页 HTML 里**绝不**回显用户传入或配置里的 key，避免侧信道泄露。
 */
function adminEnforceEntryKey(): void
{
    $configKey = trim((string) Config::get('admin_entry_key', ''));
    if ($configKey === '') return;

    $provided = trim((string) Input::get('s', ''));
    if ($provided === '' && Request::isPost()) {
        $provided = trim((string) Input::post('s', ''));
    }
    if (hash_equals($configKey, $provided)) return;

    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><title>访问受限</title>
<style>
body{margin:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;}
.box{max-width:420px;padding:40px 36px;background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;}
.ico{width:64px;height:64px;margin:0 auto 18px;border-radius:16px;background:linear-gradient(135deg,#fef3c7,#fde68a);display:flex;align-items:center;justify-content:center;color:#d97706;font-size:28px;}
h1{font-size:18px;color:#111827;margin:0 0 8px;}
p{font-size:13px;color:#6b7280;line-height:1.7;margin:0;}
</style></head><body>
<div class="box">
    <div class="ico">🛡</div>
    <h1>请使用正确的入口登录面板</h1>
    <p>此后台已启用安全入口保护。<br>请通过设置的安全地址访问，不要直接访问此页面。</p>
</div></body></html><?php
    exit;
}

// ================================================================
// 翻译系统
// ================================================================


/**
 * 当前后台语言的翻译映射表。
 * key = translate (翻译键), value = content (翻译内容)
 *
 * @var array<string, string>
 */
$adminTranslations = [];

/**
 * 加载后台翻译数据。
 */
function loadAdminTranslations(): void
{
    global $adminTranslations;

    $langCode = $_COOKIE['admin_lang'] ?? '';

    $langModel = new LanguageModel();
    $lang = null;

    if ($langCode !== '') {
        $lang = $langModel->findByCode($langCode);
    }
    
    if ($lang === null) {
        $lang = $langModel->getDefault();
    }

    if ($lang === null) {
        return;
    }

    $translator = new LangModel();
    $rows = $translator->getByLangId((int) $lang['id']);

    foreach ($rows as $row) {
        $key = trim((string) ($row['translate'] ?? ''));
        $val = trim((string) ($row['content'] ?? ''));
        if ($key !== '' && $val !== '') {
            $adminTranslations[$key] = $val;
        }
    }
}

/**
 * 获取翻译。
 * 传入原文，找到则返回译文，找不到返回原文。
 *
 * @param string $text 原文（也是翻译键）
 */
function t(string $text): string
{
    global $adminTranslations;
    return $adminTranslations[$text] ?? $text;
}

// 加载翻译（后台所有页面都会用到）
loadAdminTranslations();

