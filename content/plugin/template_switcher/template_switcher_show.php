<?php
declare(strict_types=1);

if (!defined('EM_ROOT')) {
    $root = dirname(__DIR__, 3);
    $init = $root . '/init.php';
    if (is_file($init)) {
        require_once $init;
    }
}

defined('EM_ROOT') || exit('access denied!');

function template_switcher_normalize_redirect(string $target): string
{
    $target = trim($target);
    if ($target === '' || strpos($target, "\n") !== false || strpos($target, "\r") !== false) {
        return '/';
    }

    if (preg_match('#^https?://#i', $target)) {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $targetHost = (string) (parse_url($target, PHP_URL_HOST) ?? '');
        if ($host === '' || strcasecmp($host, $targetHost) !== 0) {
            return '/';
        }

        $path = (string) (parse_url($target, PHP_URL_PATH) ?? '/');
        $query = (string) (parse_url($target, PHP_URL_QUERY) ?? '');
        return $path . ($query !== '' ? '?' . $query : '');
    }

    return strncmp($target, '/', 1) === 0 ? $target : '/';
}

$model = new TemplateModel();
$scope = class_exists('MerchantContext') && MerchantContext::currentId() > 0
    ? 'merchant_' . MerchantContext::currentId()
    : 'main';
$client = TemplateModel::detectClientFromRequest();
$redirect = template_switcher_normalize_redirect((string) ($_GET['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/')));

if ((int) ($_GET['reset'] ?? 0) === 1) {
    $model->clearCookieOverrideTheme($client, $scope);
    Response::redirect($redirect);
}

$template = trim((string) ($_GET['template'] ?? ''));
if ($template !== '') {
    $model->setCookieOverrideTheme($client, $scope, $template);
}

Response::redirect($redirect);
