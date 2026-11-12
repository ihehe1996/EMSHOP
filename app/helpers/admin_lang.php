<?php

declare(strict_types=1);

/**
 * 后台翻译函数（t / loadAdminTranslations）。
 *
 * 原实现内联在 admin/global.php 里。新后台（app/Admin）需要复用旧后台框架壳时
 * 也会用到 t()，但不能再 require admin/global.php（它会重复执行 init.php）。
 * 这里提供同一份实现，并用 function_exists 防止与旧后台同时加载时重复定义。
 *
 * 与旧实现唯一的区别：状态存在 $GLOBALS['adminTranslations']，
 * 这样无论本文件在全局作用域还是方法内被 include，t() 都能读到同一份映射。
 */

if (!function_exists('loadAdminTranslations')) {
    if (!isset($GLOBALS['adminTranslations']) || !is_array($GLOBALS['adminTranslations'])) {
        $GLOBALS['adminTranslations'] = [];
    }

    /**
     * 加载后台翻译数据。
     */
    function loadAdminTranslations(): void
    {
        $langCode = (string) ($_COOKIE['admin_lang'] ?? '');

        $langModel = new \App\Model\LanguageModel();
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

        $translator = new \App\Model\LangModel();
        $rows = $translator->getByLangId((int) $lang['id']);

        $map = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['translate'] ?? ''));
            $val = trim((string) ($row['content'] ?? ''));
            if ($key !== '' && $val !== '') {
                $map[$key] = $val;
            }
        }

        $GLOBALS['adminTranslations'] = $map;
    }

    /**
     * 获取翻译；找不到时返回原文。
     */
    function t(string $text): string
    {
        return $GLOBALS['adminTranslations'][$text] ?? $text;
    }

    loadAdminTranslations();
}
