<?php

declare(strict_types=1);

/**
 * 前台路由分发器。
 *
 * 负责解析 URL 参数（c=控制器/a=动作），映射到对应的控制器类，
 * 加载控制器并调用对应方法输出页面。
 *
 * URL 格式（query string 模式）：
 *   ?c=index         → IndexController::_index()    首页
 *   ?c=goods_list    → GoodsController::_list()     商品列表
 *   ?c=goods&id=1   → GoodsController::_detail()   商品详情
 *   ?c=blog_list     → BlogController::_list()     文章列表
 *   ?c=blog&id=1    → BlogController::_detail()    文章详情
 *   ?c=blog_index    → BlogController::_index()     博客首页
 *   ?c=search&q=关键词 → SearchController::_list()  搜索结果
 *   ?c=notfound     → ErrorController::_index()    404
 *
 * URL 格式（pathinfo 模式）：
 *   /goods_list        → GoodsController::_list()
 *   /goods/1          → GoodsController::_detail()
 *   /blog_index       → BlogController::_index()
 *
 * 方法命名约定：
 *   _index()  首页
 *   _list()   列表页
 *   _detail() 详情页
 */
final class Dispatcher
{
    /** 默认控制器名 */
    private const DEFAULT_CONTROLLER = 'index';

    /** 默认动作名 */
    private const DEFAULT_ACTION = '_list';

    /** 单页控制器（默认动作为 _index） */
    private const INDEX_CONTROLLERS = [
        'index' => true,  // 首页
        'order' => true,
        'login' => true,
        'register' => true,
        'blog_index' => true,
        'blog_comment' => true,  // 评论 API
        'goods_index' => true,
        'search' => true,
        'password' => true,
        'plugin' => true,
        'callback' => true,
        'setting' => true,
        'coupon' => true,
        'rebate' => true,
        'recharge' => true,
        'withdraw' => true,
        'api' => true,
    ];

    /** 博客模式默认控制器 */
    private const BLOG_DEFAULT_CONTROLLER = 'blog_index';

    /**
     * 控制器映射表。
     *
     * key: URL 中的 c 参数值
     * value: 控制器类名（不含后缀 Controller）
     */
    private const CONTROLLER_MAP = [
        'index'       => 'Index',    // 首页 → IndexController::_index()
        'goods_list'  => 'Goods',
        'goods'       => 'Goods',
        'goods_index' => 'Goods',    // 商城首页（博客模式下独立入口）
        'blog_list'   => 'Blog',
        'blog_index'  => 'Blog',     // 博客首页
        'blog'        => 'Blog',
        'blog_comment' => 'BlogComment', // 博客评论 API
        'blog_tag'    => 'BlogTag',     // 博客标签页
        'goods_tag'   => 'GoodsTag',    // 商品标签页
        'page'        => 'Page',        // 自定义页面 /p/{slug}
        'search'      => 'Search',
        'order'       => 'Order',
        'login'       => 'Login',
        'register'    => 'Register',
        'password'    => 'Password',
        'plugin'      => 'Plugin',
        'callback'    => 'Callback',
        'setting'     => 'Setting',
        'coupon'      => 'Coupon',
        'rebate'      => 'Rebate',
        'recharge'    => 'Recharge',
        'withdraw'    => 'Withdraw',
        'api'         => 'Api',
    ];

    /** @var Dispatcher */
    private static $instance;

    /** @var string 当前控制器名 */
    private string $controller = self::DEFAULT_CONTROLLER;

    /** @var string 当前动作名 */
    private string $action = self::DEFAULT_ACTION;

    /** @var string 当前 URL 中的 c 参数（原始） */
    private string $rawController = '';

    /** @var bool 当前请求是否落在站点根 "/"（HOMEPAGE_MODE 替换前 controller 为 index） */
    private bool $isHomepage = false;

    /** @var array<string, mixed> URL 路径中的其余参数（pathinfo 模式） */
    private array $pathArgs = [];

    /** @var string 当前启用的主题名 */
    private string $theme = '';

    /** @var View 当前视图实例 */
    private View $view;

    private function __construct()
    {
        $this->view = View::getInstance();
    }

    /**
     * 获取单例实例。
     */
    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 执行路由分发。
     *
     * 流程：
     * 1. 解析 URL 参数，获取控制器名、动作名、路径参数
     * 2. 校验控制器是否在白名单中
     * 3. 加载控制器类，调用对应动作方法
     * 4. 若控制器或动作不存在，渲染 404 页面
     */
    public function dispatch(): void
    {
        // 0. 站点总开关：关闭后前台所有请求硬拦截（/admin 走独立入口，不受影响）
        //    放在最顶：不查表、不选主题、不过钩子，彻底避免升级中产生脏写入
        if ((string) Config::get('site_enabled', '1') === '0') {
            $this->renderSiteClosedNotice();
            return;
        }

        // 0.1 Swoole 存活检测已移除：即使 Swoole 未运行也允许前台访问。

        // 0.15 商户入口等级门控：host 命中了一个真实商户但商户等级不允许该入口方式（二级域名 /
        //      自定义顶级域名），显式渲染"店铺暂未开放"页，避免静默把主站内容露给访客。
        //      一般由管理员事后降了等级 / 员工越权开了门而等级不匹配触发。
        $blocked = MerchantContext::blockedReason();
        if ($blocked !== '') {
            $this->renderShopUnavailableNotice($blocked);
            return;
        }

        // 0.2 前台插件展示页：?plugin=xxx 直接交给插件的 {slug}_show.php 全权输出
        //     （主题/控制器/钩子流程全部跳过，插件自己输出完整 HTML；常见用法：公开回调页、插件 demo 页）
        $pluginSlug = (string) ($_GET['plugin'] ?? '');
        if ($pluginSlug !== '') {
            $this->dispatchPluginShow($pluginSlug);
            return;
        }

        // 1. 解析路由参数
        $this->parseRoute();

        // 2. 获取当前主题
        $this->theme = $this->getActiveTheme();

        // var_dump('当前路由：控制器=' . $this->controller . ', 方法=' . $this->action . ', 主题=' . $this->theme);

        // 未启用任何模板 → 直接提示管理员去启用，不再自动兜底到某个主题
        if ($this->theme === '') {
            $this->renderNoThemeNotice();
            return;
        }

        $this->view->setTheme($this->theme);

        // 3. 注册全局视图数据（module.php 依赖 _controller 和 homepage_mode 生成导航）
        // 货币展示三件套：符号 / 汇率因子 / code，前台模板与 JS 都通过它们把主货币数值换成访客币种
        //   - $currency_symbol：当前访客币种的符号（Cookie > is_frontend_default > 主货币）
        //   - $currency_rate  ：1 主货币 = N 访客币种的 float 因子；JS 端用它做动态计算（详情页数量 × 单价）
        //   - 静态展示直接用 Currency::displayMain($floatInMain)，自动换算 + 加符号
        $primaryCurrency = Currency::getInstance()->getPrimary();
        $visitorCode = Currency::visitorCode();
        $visitorRow = $visitorCode !== '' ? Currency::getInstance()->getByCode($visitorCode) : null;
        $currencySymbol = $visitorRow
            ? ((string) ($visitorRow['symbol'] ?? ''))
            : ($primaryCurrency ? ($primaryCurrency['symbol'] ?? '¥') : '¥');
        if ($currencySymbol === '') {
            $currencySymbol = '¥';
        }
        // rate 语义：1 访客币种 = rate/1000000 主货币。因此 1 主货币 = 1000000/rate 访客币种。
        // 当访客币种 = 主货币时 rate=1000000（或无 visitorRow），factor=1。
        $currencyRate = 1.0;
        if ($visitorRow !== null) {
            $rateRaw = (int) ($visitorRow['rate'] ?? 0);
            if ($rateRaw > 0) {
                $currencyRate = 1000000 / $rateRaw;
            }
        }

        // 商户上下文：模板里的站名 / Logo 切换成商户店铺名 / Logo
        $siteName = (string) (Config::get('sitename') ?? 'EMSHOP');
        $siteLogo = (string) (Config::get('site_logo') ?? '');
        $siteLogoType = (string) (Config::get('site_logo_type') ?? 'text');
        $merchant = MerchantContext::current();
        if ($merchant !== null) {
            if (!empty($merchant['name'])) {
                $siteName = (string) $merchant['name'];
            }
            if (!empty($merchant['logo'])) {
                $siteLogo = (string) $merchant['logo'];
                $siteLogoType = 'image';
            } else {
                // 商户没设置 Logo → 回退到文字模式展示店铺名（避免继承主站的 image 空指针）
                $siteLogoType = 'text';
                $siteLogo = '';
            }
        }

        $this->view->assign([
            'site_name'       => $siteName,
            'site_logo'       => $siteLogo,
            'site_logo_type'  => $siteLogoType,
            'site_url'        => $this->getSiteUrl(),
            'currency_symbol' => $currencySymbol,
            'currency_rate'   => $currencyRate,
            'currency_code'   => $visitorCode,
            '_controller'     => $this->controller,
            '_action'         => $this->action,
            '_theme'          => $this->theme,
            'homepage_mode'   => defined('HOMEPAGE_MODE') ? HOMEPAGE_MODE : 'mall',
            // 模板用：站点根 "/" 入口（HOMEPAGE_MODE 替换前的原始判断），用于"首页"导航高亮
            'is_homepage'     => $this->isHomepage,
            // 商户上下文信息：模板可直接用（未设则 null / 0）
            '_merchant'       => $merchant,
            '_merchant_id'    => $merchant ? (int) $merchant['id'] : 0,
        ]);

        // 4. 执行前置钩子
        doAction('front_dispatch_before');

        // 5. 加载控制器并调用动作方法
        $this->callController();
    }

    /**
     * SEO pretty URL 识别器。
     *
     * 命中下列任一模式即直接设置 $this->controller/action/pathArgs 并返回 true：
     *   query string   ?post=N  ?blog=N
     *   文件格式       /post-1.html /blog-1.html /post-list.html /tag-1.html ...
     *   目录格式       /post/1 /buy/1 /blog/1 /post/list /post/c/slug /blog/tag/1 ...
     *   静态页面       /coupon.html /search.html /blog.html
     *   静态目录       /coupon/ /search/xxx /blog/
     *
     * 不匹配时返回 false，回落到原有 parseRoute 逻辑（保留老 URL 兼容）。
     */
    private function tryPrettyRoute(): bool
    {
        // 显式 ?c=xxx 的请求是接口调用（如前端 $.post('?c=order&a=create')）。
        // 当前页面 URL 可能是 pretty 形式（/post-1.html、/post/1），
        // jQuery 把相对的 ?c=order&a=create 拼到当前 path 上 → /post-1.html?c=order&a=create。
        // 如果不在这里早退，下面的 path 解析会先命中 /post-1.html → 'goods/_detail'，
        // 直接 return true，把 ?c=order&a=create 这两个参数静默吞掉，前端就只能看到非 JSON 响应"网络异常"。
        // 已知合法 controller 时强制走 query string 模式，让接口请求不受当前页面 URL 形式干扰。
        $cParam = trim((string) ($_GET['c'] ?? ''));
        if ($cParam !== '' && isset(self::CONTROLLER_MAP[$this->sanitize($cParam)])) {
            return false;
        }

        $extraQuery = array_diff_key($_GET, array_flip(['c', 'a', 'post', 'blog']));

        // —— Query string 模式：?post=1 / ?blog=1
        $postId = (int) ($_GET['post'] ?? 0);
        if ($postId > 0) {
            $this->controller = 'goods'; $this->action = '_detail';
            $this->pathArgs = ['id' => $postId] + $extraQuery;
            return true;
        }
        $blogId = (int) ($_GET['blog'] ?? 0);
        if ($blogId > 0) {
            $this->controller = 'blog'; $this->action = '_detail';
            $this->pathArgs = ['id' => $blogId] + $extraQuery;
            return true;
        }

        // —— 路径模式
        // 优先从 REQUEST_URI 解析（最通用，标准 Nginx try_files + Apache rewrite 都能工作），
        // 回落到 PATH_INFO（老配置兼容）。去掉 query string 和前后斜杠。
        $path = '';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $uri = (string) $_SERVER['REQUEST_URI'];
            $qPos = strpos($uri, '?');
            $path = $qPos === false ? $uri : substr($uri, 0, $qPos);
        }
        if ($path === '' || $path === '/') {
            $path = $_SERVER['PATH_INFO'] ?? $_SERVER['ORIG_PATH_INFO'] ?? '';
        }
        if ($path === '' || $path === '/') return false;
        $path = trim($path, '/');

        // 请求的就是 index.php 本身（比如 /index.php 或 /index.php/xxx），不当 pretty 路径
        if ($path === 'index.php' || strpos($path, 'index.php/') === 0) return false;

        // 文件格式：*.html
        if (substr($path, -5) === '.html') {
            $base = substr($path, 0, -5);

            // 静态页：cart / coupon / search / blog / post（商城首页）
            // post 显式指向 goods_index，避免被 HOMEPAGE_MODE 替换成博客首页
            $staticMap = ['coupon' => ['coupon', '_index'],
                          'search' => ['search', '_index'],
                          'blog' => ['blog_index', '_index'],
                          'post' => ['goods_index', '_index']];
            if (isset($staticMap[$base])) {
                [$this->controller, $this->action] = $staticMap[$base];
                $this->pathArgs = $extraQuery;
                return true;
            }
            // blog-tag-N / blog-tag-N-M（带分页，放在 blog-* 前面匹配）
            // BlogTagController 只有 _detail（按 id 列出该标签下文章），故直接派发到 _detail
            if (preg_match('/^blog-tag-(\d+)(?:-(\d+))?$/', $base, $m)) {
                $this->controller = 'blog_tag'; $this->action = '_detail';
                $this->pathArgs = ['id' => (int) $m[1]] + $extraQuery;
                if (!empty($m[2])) $this->pathArgs['page'] = (int) $m[2];
                return true;
            }
            // blog-*
            if (preg_match('/^blog-(.+)$/', $base, $m)) {
                $tail = $m[1];

                // blog-list（== 全部第 1 页）/ blog-list-all / blog-list-all-N
                if ($tail === 'list' || $tail === 'list-all') {
                    $this->controller = 'blog_list'; $this->action = '_list';
                    $this->pathArgs = $extraQuery;
                    return true;
                }
                if (preg_match('/^list-all-(\d+)$/', $tail, $mm)) {
                    $this->controller = 'blog_list'; $this->action = '_list';
                    $this->pathArgs = ['page' => (int) $mm[1]] + $extraQuery;
                    return true;
                }
                // blog-list-ID 或 blog-list-ID-N（分类 + 可选分页）
                if (preg_match('/^list-(\d+)(?:-(\d+))?$/', $tail, $mm)) {
                    $this->controller = 'blog_list'; $this->action = '_list';
                    $this->pathArgs = ['category_id' => (int) $mm[1]] + $extraQuery;
                    if (!empty($mm[2])) $this->pathArgs['page'] = (int) $mm[2];
                    return true;
                }

                // blog-N（详情）
                if (ctype_digit($tail)) {
                    $this->controller = 'blog'; $this->action = '_detail';
                    $this->pathArgs = ['id' => (int) $tail] + $extraQuery;
                    return true;
                }
                // blog-slug-N（slug + 分页，末段纯数字视为页码）
                if (preg_match('/^(.+)-(\d+)$/', $tail, $mm)) {
                    $this->controller = 'blog_list'; $this->action = '_list';
                    $this->pathArgs = ['slug' => $mm[1], 'page' => (int) $mm[2]] + $extraQuery;
                    return true;
                }
                // blog-slug
                $this->controller = 'blog_list'; $this->action = '_list';
                $this->pathArgs = ['slug' => $tail] + $extraQuery;
                return true;
            }
            // post-* / buy-*（商品 prefix 两种别名）
            if (preg_match('/^(post|buy)-(.+)$/', $base, $m)) {
                $tail = $m[2];

                // post-list / post-list-all / post-list-all-N（全部 + 可选分页）
                if ($tail === 'list' || $tail === 'list-all') {
                    $this->controller = 'goods_list'; $this->action = '_list';
                    $this->pathArgs = $extraQuery;
                    return true;
                }
                if (preg_match('/^list-all-(\d+)$/', $tail, $mm)) {
                    $this->controller = 'goods_list'; $this->action = '_list';
                    $this->pathArgs = ['page' => (int) $mm[1]] + $extraQuery;
                    return true;
                }
                // post-list-ID / post-list-ID-N（分类 id + 可选分页）
                if (preg_match('/^list-(\d+)(?:-(\d+))?$/', $tail, $mm)) {
                    $this->controller = 'goods_list'; $this->action = '_list';
                    $this->pathArgs = ['category_id' => (int) $mm[1]] + $extraQuery;
                    if (!empty($mm[2])) $this->pathArgs['page'] = (int) $mm[2];
                    return true;
                }

                // post-N（详情）
                if (ctype_digit($tail)) {
                    $this->controller = 'goods'; $this->action = '_detail';
                    $this->pathArgs = ['id' => (int) $tail] + $extraQuery;
                    return true;
                }
                // post-slug-N（slug + 分页）
                if (preg_match('/^(.+)-(\d+)$/', $tail, $mm)) {
                    $this->controller = 'goods_list'; $this->action = '_list';
                    $this->pathArgs = ['slug' => $mm[1], 'page' => (int) $mm[2]] + $extraQuery;
                    return true;
                }
                // post-slug
                $this->controller = 'goods_list'; $this->action = '_list';
                $this->pathArgs = ['slug' => $tail] + $extraQuery;
                return true;
            }
            // tag-N 或 tag-N-M（带分页）/ search-xxx
            // GoodsTagController 只有 _detail（按 id 列出该标签下商品），故直接派发到 _detail
            if (preg_match('/^tag-(\d+)(?:-(\d+))?$/', $base, $m)) {
                $this->controller = 'goods_tag'; $this->action = '_detail';
                $this->pathArgs = ['id' => (int) $m[1]] + $extraQuery;
                if (!empty($m[2])) $this->pathArgs['page'] = (int) $m[2];
                return true;
            }
            if (preg_match('/^search-(.+)$/', $base, $m)) {
                $this->controller = 'search'; $this->action = '_index';
                $this->pathArgs = ['q' => $m[1]] + $extraQuery;
                return true;
            }
            return false; // *.html 但没匹配
        }

        // 目录格式
        $segments = explode('/', $path);
        $first = $segments[0];
        $n = count($segments);

        // /coupon/（单段静态）
        if ($n === 1 && in_array($first, ['coupon'], true)) {
            $this->controller = $first; $this->action = '_index';
            $this->pathArgs = $extraQuery;
            return true;
        }
        // /search 或 /search/xxx
        if ($first === 'search') {
            $this->controller = 'search'; $this->action = '_index';
            if ($n >= 2 && $segments[1] !== '') {
                $this->pathArgs = ['q' => $segments[1]] + $extraQuery;
            } else {
                $this->pathArgs = $extraQuery;
            }
            return true;
        }
        // /tag/N 或 /tag/N/M（带分页）
        if ($first === 'tag' && $n >= 2 && ctype_digit($segments[1])) {
            $this->controller = 'goods_tag'; $this->action = '_detail';
            $this->pathArgs = ['id' => (int) $segments[1]] + $extraQuery;
            if ($n >= 3 && ctype_digit($segments[2])) {
                $this->pathArgs['page'] = (int) $segments[2];
            }
            return true;
        }
        // /p/{slug} —— 自定义页面（WordPress 式 Pages）
        if ($first === 'p' && $n >= 2 && $segments[1] !== '') {
            $this->controller = 'page';
            $this->action = '_detail';
            $this->pathArgs = ['slug' => $segments[1]] + $extraQuery;
            return true;
        }

        // /blog 及 /blog/...
        if ($first === 'blog') {
            if ($n === 1) {
                $this->controller = 'blog_index'; $this->action = '_index';
                $this->pathArgs = $extraQuery;
                return true;
            }
            $seg = $segments[1];
            // /blog/list（全部 p1）/ /blog/list/all[/N] / /blog/list/ID[/N]
            if ($seg === 'list') {
                $this->controller = 'blog_list'; $this->action = '_list';
                $this->pathArgs = $extraQuery;
                if ($n >= 3) {
                    $third = $segments[2];
                    if ($third === 'all') {
                        if ($n >= 4 && ctype_digit($segments[3])) {
                            $this->pathArgs['page'] = (int) $segments[3];
                        }
                    } elseif (ctype_digit($third)) {
                        $this->pathArgs['category_id'] = (int) $third;
                        if ($n >= 4 && ctype_digit($segments[3])) {
                            $this->pathArgs['page'] = (int) $segments[3];
                        }
                    }
                }
                return true;
            }
            // /blog/N（详情）
            if (ctype_digit($seg)) {
                $this->controller = 'blog'; $this->action = '_detail';
                $this->pathArgs = ['id' => (int) $seg] + $extraQuery;
                return true;
            }
            // /blog/c/slug 或 /blog/c/slug/N
            if ($seg === 'c' && isset($segments[2])) {
                $this->controller = 'blog_list'; $this->action = '_list';
                $this->pathArgs = ['slug' => $segments[2]] + $extraQuery;
                if ($n >= 4 && ctype_digit($segments[3])) {
                    $this->pathArgs['page'] = (int) $segments[3];
                }
                return true;
            }
            // /blog/tag/N 或 /blog/tag/N/M
            if ($seg === 'tag' && isset($segments[2]) && ctype_digit($segments[2])) {
                $this->controller = 'blog_tag'; $this->action = '_detail';
                $this->pathArgs = ['id' => (int) $segments[2]] + $extraQuery;
                if ($n >= 4 && ctype_digit($segments[3])) {
                    $this->pathArgs['page'] = (int) $segments[3];
                }
                return true;
            }
            return false; // blog/其它 → fall through
        }
        // /post 及 /post/...，以及 /buy/...（dir2 模式商品前缀）
        if (in_array($first, ['post', 'buy'], true)) {
            if ($n === 1) {
                // 单独 /post/ 视为商城首页 —— 显式 goods_index，避免被 HOMEPAGE_MODE 替换
                $this->controller = 'goods_index'; $this->action = '_index';
                $this->pathArgs = $extraQuery;
                return true;
            }
            $seg = $segments[1];
            // /post/list（全部 p1）/ /post/list/all[/N] / /post/list/ID[/N]
            if ($seg === 'list') {
                $this->controller = 'goods_list'; $this->action = '_list';
                $this->pathArgs = $extraQuery;
                if ($n >= 3) {
                    $third = $segments[2];
                    if ($third === 'all') {
                        if ($n >= 4 && ctype_digit($segments[3])) {
                            $this->pathArgs['page'] = (int) $segments[3];
                        }
                    } elseif (ctype_digit($third)) {
                        $this->pathArgs['category_id'] = (int) $third;
                        if ($n >= 4 && ctype_digit($segments[3])) {
                            $this->pathArgs['page'] = (int) $segments[3];
                        }
                    }
                }
                return true;
            }
            // /post/N（详情）
            if (ctype_digit($seg)) {
                $this->controller = 'goods'; $this->action = '_detail';
                $this->pathArgs = ['id' => (int) $seg] + $extraQuery;
                return true;
            }
            // /post/c/slug 或 /post/c/slug/N
            if ($seg === 'c' && isset($segments[2])) {
                $this->controller = 'goods_list'; $this->action = '_list';
                $this->pathArgs = ['slug' => $segments[2]] + $extraQuery;
                if ($n >= 4 && ctype_digit($segments[3])) {
                    $this->pathArgs['page'] = (int) $segments[3];
                }
                return true;
            }
            return false;
        }

        return false;
    }

    /**
     * 解析 URL 路由参数。
     */
    private function parseRoute(): void
    {
        // 先尝试识别 SEO pretty URL（?post=1 / /post-1.html / /post/1 / /buy/1 等）
        // 命中即直接返回，不走下面的 pathinfo/querystring 通用解析
        if ($this->tryPrettyRoute()) {
            $this->rawController = $this->controller;
            if (!isset(self::CONTROLLER_MAP[$this->controller])) {
                $this->controller = 'notfound';
                $this->action = '_index';
            }
            return;
        }

        $pathinfo = $_SERVER['PATH_INFO'] ?? $_SERVER['ORIG_PATH_INFO'] ?? '';
        if ($pathinfo !== '' && $pathinfo !== '/') {
            // pathinfo 模式：/goods_list、/goods/1、/blog_index
            $segments = array_values(array_filter(explode('/', trim($pathinfo, '/')), 'strlen'));
            $this->rawController = !empty($segments[0]) ? $this->sanitize($segments[0]) : self::DEFAULT_CONTROLLER;
            $this->controller = $this->rawController;

            // pathinfo 中的其余段作为动作和路径参数
            if (count($segments) >= 2) {
                // segments[1] 是动作或 ID
                if (is_numeric($segments[1])) {
                    $this->action = '_detail';
                    $this->pathArgs = ['id' => (int) $segments[1]];
                    // segments[2+] 作为额外参数
                    for ($i = 2; $i < count($segments); $i++) {
                        $this->pathArgs[$segments[$i]] = $segments[$i + 1] ?? true;
                        $i++;
                    }
                } else {
                    $this->action = $this->sanitize($segments[1]);
                    // segments[2+] 作为路径参数
                    for ($i = 2; $i < count($segments); $i++) {
                        $this->pathArgs[$segments[$i]] = $segments[$i + 1] ?? true;
                        $i++;
                    }
                }
            } else {
                // 无第二段：单页控制器 → _index，其他 → _list
                $this->action = isset(self::INDEX_CONTROLLERS[$this->controller]) ? '_index' : self::DEFAULT_ACTION;
            }
        } else {
            // query string 模式：?c=goods_list&a=_list&id=1
            $this->rawController = trim(Input::get('c', self::DEFAULT_CONTROLLER));
            $this->controller = $this->sanitize($this->rawController);
            $this->action = $this->sanitize(trim(Input::get('a', self::DEFAULT_ACTION)));

            // 从 query string 构建 pathArgs（排除 c 和 a）
            $this->pathArgs = array_diff_key($_GET, array_flip(['c', 'a']));
        }

        // 安全过滤
        $this->controller = $this->sanitize($this->controller);
        $this->action = $this->sanitize($this->action);

        // 站点根 "/" 的入口替换：按"页面首页 → homepage_mode"两级优先分流
        //   优先级 1（最高）：当前 scope 在 em_page 表里设了 is_homepage=1 的页面 → 走 PageController
        //   优先级 2：settings.homepage_mode（mall / goods_list / blog）
        //     mall（默认）：保持 controller='index'（IndexController → goods_index 模板）
        //     blog       ：替换成 blog_index（博客首页）
        //     goods_list ：替换成 goods_list（商品列表页）
        // 注意：只替换显式 controller='index'（即 "/" 入口）；用户访问 /post/ 等显式路径时
        // 控制器已被 parseRoute 设成 goods_index 等，不会走到这里。
        //
        // 替换会丢失"是首页"这个语义（替换后 controller 看起来就是 goods_list / blog_index 等），
        // 模板里"首页"导航的高亮就不知道该不该亮。所以替换前先记录一下原始判断。
        $this->isHomepage = ($this->controller === self::DEFAULT_CONTROLLER);
        if ($this->isHomepage) {
            // 优先级 1：页面首页 —— 主站和商户各自的 em_page.is_homepage 行
            $homepagePage = null;
            if (class_exists('PageModel')) {
                $homepagePage = PageModel::getHomepage(MerchantContext::currentId());
            }
            if ($homepagePage !== null && !empty($homepagePage['slug'])) {
                $this->controller = 'page';
                $this->rawController = 'page';
                $this->action = '_detail';
                // PageController::_detail 用 getArg('slug') 解析；pathArgs 兼容 query 模式
                $this->pathArgs['slug'] = (string) $homepagePage['slug'];
                $_GET['slug'] = (string) $homepagePage['slug'];
            } elseif (defined('HOMEPAGE_MODE')) {
                // 优先级 2：homepage_mode 配置
                if (HOMEPAGE_MODE === 'blog') {
                    $this->controller = self::BLOG_DEFAULT_CONTROLLER;
                    $this->rawController = self::BLOG_DEFAULT_CONTROLLER;
                } elseif (HOMEPAGE_MODE === 'goods_list') {
                    $this->controller = 'goods_list';
                    $this->rawController = 'goods_list';
                    $this->action = '_list';
                }
            }
        }

        // 首页/单页控制器默认动作为 _index，列表/详情控制器默认动作为 _list
        if ($this->action === self::DEFAULT_ACTION) {
            $this->action = isset(self::INDEX_CONTROLLERS[$this->controller]) ? '_index' : '_list';
        }

        // query string 模式下，如果有 id 参数，自动使用 _detail action
        // （pathinfo 模式在 parseRoute() 中已处理）
        if (!empty($_GET['id']) && $this->action === '_list') {
            $this->action = '_detail';
        }

        // 校验控制器是否在白名单中，不存在则 404
        if (!isset(self::CONTROLLER_MAP[$this->controller])) {
            $this->controller = 'notfound';
            $this->action = '_index';
        }
    }

    /**
     * 安全过滤：只允许字母、数字、下划线、连字符。
     */
    private function sanitize(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $value);
    }

    /**
     * 获取当前请求的启用主题。
     * 按当前 scope 取：商户上下文 merchant_{id}，否则主站 main；
     * scope 下未启用任何模板时返回空串，由 dispatch() 渲染"未启用模板"提示，不再回退兜底。
     */
    private function getActiveTheme(): string
    {
        $deviceType = $this->detectDeviceType();
        try {
            $scope = MerchantContext::currentId() > 0
                ? 'merchant_' . MerchantContext::currentId()
                : 'main';
            $templateModel = new TemplateModel();
            return (string) ($templateModel->getEffectiveTheme($deviceType, $scope) ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * 检测设备类型。
     */
    private function detectDeviceType(): string
    {
        return TemplateModel::detectClientFromRequest();
    }

    /**
     * 加载控制器并调用动作方法。
     */
    private function callController(): void
    {
        $mapKey = $this->controller;

        // notfound 特殊处理
        if ($this->controller === 'notfound') {
            $ctrlClass = 'ErrorController';
        } else {
            $ctrlClass = self::CONTROLLER_MAP[$mapKey] . 'Controller';
        }

        $method = $this->action;
        $ctrlFile = EM_ROOT . '/include/controller/' . $ctrlClass . '.php';

        // 控制器文件不存在
        if (!is_file($ctrlFile)) {
            $this->render404('控制器文件不存在: ' . $ctrlClass);
            return;
        }

        // 加载控制器类
        require_once $ctrlFile;
        if (!class_exists($ctrlClass, false)) {
            $this->render404('控制器类不存在: ' . $ctrlClass);
            return;
        }

        // 实例化控制器，注入视图、调度器引用、当前控制器名
        $ctrl = new $ctrlClass($this->view, $this, $this->controller);

        // 动作方法不存在
        if (!method_exists($ctrl, $method)) {
            $this->render404('动作方法不存在: ' . $ctrlClass . '::' . $method);
            return;
        }

        // 调用动作方法
        $ctrl->$method();
    }

    /**
     * 渲染 404 页面。
     * 公开，供控制器在"资源不存在"时调用（如 PageController 找不到 slug）。
     */
    public function render404(string $reason = ''): void
    {
        http_response_code(404);
        $this->view->setTitle('页面不存在');
        $this->view->setData('_404_reason', $reason);
        $this->view->render('404');
    }

    /**
     * 前台插件展示页：?plugin=xxx 时调用。
     *
     * 规则：
     *   1) slug 必须符合 ^[a-z0-9_-]+$，防路径穿越
     *   2) 插件必须在当前 scope（main / merchant_{id}）下已启用
     *   3) 加载 content/plugin/{slug}/{slug}_show.php，插件自行输出完整 HTML
     * 不满足任一条件 → 404。
     */
    private function dispatchPluginShow(string $slug): void
    {
        // slug 白名单过滤，防止 ../ 等注入
        if (!preg_match('/^[a-z0-9_\-]+$/i', $slug)) {
            $this->render404('非法的插件名');
            return;
        }

        $showFile = EM_ROOT . '/content/plugin/' . $slug . '/' . $slug . '_show.php';
        if (!is_file($showFile)) {
            $this->render404('插件未提供前台展示页');
            return;
        }

        // 启用校验：主站访问用 main，商户域名下用 merchant_{id}
        // 注意：商户站会继承主站 SYSTEM_PLUGINS（支付插件/商品类型/共享店铺），
        // 因此前台展示页也要按“运行时名单”校验，而不是仅看商户显式 enabled 列表。
        $merchantId = class_exists('MerchantContext') ? MerchantContext::currentId() : 0;
        $scope = $merchantId > 0 ? 'merchant_' . $merchantId : 'main';
        $model = new PluginModel();
        $runtimeNames = $model->getRuntimeNames($scope);
        if (!in_array($slug, $runtimeNames, true)) {
            $this->render404('插件未启用');
            return;
        }

        // 插件自己输出完整 HTML；核心不做任何包装
        require $showFile;
    }

    /**
     * 渲染"站点升级中"停机页（site_enabled=0 时）。
     *
     * 503 + Retry-After，让搜索引擎理解为临时不可用、不扣权重。
     * 和 renderNoThemeNotice 同款自包含 HTML，避免走模板渲染。
     */
    private function renderSiteClosedNotice(): void
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('Retry-After: 300');
        $siteName = (string) (Config::get('sitename') ?? 'EMSHOP');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . ' · 站点升级中</title>'
           . '<style>'
           . 'body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f9fafb;color:#111827;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}'
           . '.wrap{max-width:520px;width:100%;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:40px 36px;box-shadow:0 4px 20px rgba(0,0,0,.05);text-align:center;}'
           . '.ico{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,rgba(245,158,11,.14),rgba(251,191,36,.08));color:#d97706;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 20px;}'
           . 'h1{font-size:20px;font-weight:600;color:#111827;margin:0 0 10px;}'
           . '.desc{font-size:14px;color:#6b7280;line-height:1.7;margin-bottom:8px;}'
           . '.sub{font-size:12px;color:#9ca3af;line-height:1.7;}'
           . '.brand{margin-top:22px;padding-top:16px;border-top:1px solid #f3f4f6;font-size:12px;color:#9ca3af;}'
           . '</style></head><body>'
           . '<div class="wrap">'
           . '<div class="ico">&#128295;</div>'
           . '<h1>站点升级中</h1>'
           . '<p class="desc">当前站点正在维护升级，请稍后再来访问。</p>'
           . '<p class="sub">给您带来不便，敬请谅解。</p>'
           . '<div class="brand">' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</div>'
           . '</div></body></html>';
    }

    /**
     * 渲染"店铺暂未开放"页（商户入口被等级门控拦截）。
     *
     * 触发：host 命中了某个真实商户，但商户等级关了对应入口（allow_subdomain=0 / allow_custom_domain=0）。
     * 用 403 + noindex：是策略拒绝而非临时故障，也不应被搜索引擎收录。
     * 文案对访客保持中性（"暂未开放"），不泄漏"等级"这类后台概念。
     *
     * @param string $reason 'subdomain' 或 'custom_domain'，当前只用来区分日志 / 后续埋点
     */
    private function renderShopUnavailableNotice(string $reason): void
    {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        $siteName = (string) (Config::get('sitename') ?? 'EMSHOP');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<meta name="robots" content="noindex,nofollow">'
           . '<title>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . ' · 店铺暂未开放</title>'
           . '<style>'
           . 'body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f9fafb;color:#111827;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}'
           . '.wrap{max-width:520px;width:100%;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:40px 36px;box-shadow:0 4px 20px rgba(0,0,0,.05);text-align:center;}'
           . '.ico{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,rgba(99,102,241,.14),rgba(129,140,248,.08));color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 20px;}'
           . 'h1{font-size:20px;font-weight:600;color:#111827;margin:0 0 10px;}'
           . '.desc{font-size:14px;color:#6b7280;line-height:1.7;margin-bottom:8px;}'
           . '.sub{font-size:12px;color:#9ca3af;line-height:1.7;}'
           . '.brand{margin-top:22px;padding-top:16px;border-top:1px solid #f3f4f6;font-size:12px;color:#9ca3af;}'
           . '</style></head><body>'
           . '<div class="wrap">'
           . '<div class="ico">&#128274;</div>'
           . '<h1>店铺暂未开放</h1>'
           . '<p class="desc">本店铺当前不可访问，请稍后再来或联系店主确认。</p>'
           . '<p class="sub">如果您是店主，请联系平台管理员确认店铺等级权限。</p>'
           . '<div class="brand">' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</div>'
           . '</div></body></html>';
    }

    private function renderNoThemeNotice(): void
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $device = $this->detectDeviceType();
        $scope  = MerchantContext::currentId() > 0 ? '商户（merchant_' . MerchantContext::currentId() . '）' : '主站（main）';
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>未启用模板</title>'
           . '<style>'
           . 'body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f9fafb;color:#111827;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}'
           . '.wrap{max-width:520px;width:100%;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:40px 36px;box-shadow:0 4px 20px rgba(0,0,0,.05);text-align:center;}'
           . '.ico{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(129,140,248,.08));color:#6366f1;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 20px;}'
           . 'h1{font-size:20px;font-weight:600;color:#111827;margin:0 0 10px;}'
           . '.desc{font-size:14px;color:#6b7280;line-height:1.7;margin-bottom:24px;}'
           . '.meta{display:inline-block;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:10px 16px;font-size:13px;color:#374151;line-height:1.8;text-align:left;margin-bottom:24px;}'
           . '.meta code{background:#eef2ff;color:#4f46e5;padding:2px 8px;border-radius:4px;font-size:12px;font-family:Menlo,Consolas,monospace;}'
           . '.btn{display:inline-flex;align-items:center;gap:6px;background:#4f46e5;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;transition:background .15s;}'
           . '.btn:hover{background:#4338ca;}'
           . '</style></head><body>'
           . '<div class="wrap">'
           . '<div class="ico">&#9888;</div>'
           . '<h1>未启用任何模板</h1>'
           . '<p class="desc">当前站点未在后台启用可用于渲染前台的模板，因此无法显示页面。</p>'
           . '<div class="meta">当前范围：<code>' . htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') . '</code><br>设备类型：<code>' . htmlspecialchars($device, ENT_QUOTES, 'UTF-8') . '</code></div>'
           . '<div><a class="btn" href="/admin/template.php">前往后台「模板管理」启用模板</a></div>'
           . '</div></body></html>';
    }

    /**
     * 获取站点 URL。
     */
    private function getSiteUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $port = $_SERVER['SERVER_PORT'] ?? 80;
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $basePath = rtrim(dirname($scriptName), '/\\');

        if (($protocol === 'http' && $port == 80) || ($protocol === 'https' && $port == 443)) {
            return $protocol . '://' . $host . $basePath;
        }
        return $protocol . '://' . $host . ':' . $port . $basePath;
    }

    // ============================================================
    // 公开访问器（供控制器使用）
    // ============================================================

    /**
     * 获取当前控制器名（URL 中的 c 参数）。
     */
    public function getController(): string
    {
        return $this->rawController;
    }

    /**
     * 获取当前动作名。
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * 获取 URL 路径参数（pathinfo 段或 query string 中除 c/a 外的参数）。
     *
     * @return array<string, mixed>
     */
    public function getPathArgs(): array
    {
        return $this->pathArgs;
    }

    /**
     * 获取指定路径参数。
     *
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    public function getArg($key, $default = null)
    {
        return $this->pathArgs[$key] ?? $default;
    }

    /**
     * 获取当前主题名。
     */
    public function getTheme(): string
    {
        return $this->theme;
    }

    /**
     * 获取当前视图实例。
     */
    public function getView(): View
    {
        return $this->view;
    }
}
