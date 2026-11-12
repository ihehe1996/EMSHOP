<?php

declare(strict_types=1);

/**
 * 后台翻译函数（t / loadAdminTranslations）。
 *
 * 后台视图和 app/Admin 的框架壳都会用到 t()；这里用 function_exists 防止重复定义。
 *
 * 翻译状态存在 $GLOBALS['adminTranslations']：无论本文件在全局作用域还是方法内被 include，
 * t() 都能读到同一份映射。
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

        $langModel = app(\App\Model\LanguageModel::class);
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

        $translator = app(\App\Model\LangModel::class);
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
