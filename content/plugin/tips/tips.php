<?php
/**
Plugin Name: 小贴士
Version: 1.0.0
Plugin URL: 
Description: 在后台首页展示一句使用小提示，也可作为插件开发的demo。
Author: 驳手
Author URL:
Category: 功能扩展
*/

defined('EM_ROOT') || exit('access denied!');

$array_tips = [
    '为防数据丢失，请每日备份您的数据库',
    '多逛逛应用商店吧，总会有惊喜',
    'EMSHOP 支持自建页面，为您的网站建一个专属页面吧',
    '检查你的站点目录下是否存在安装文件：install.php，有的话请删除它',
    '推荐使用Edge、Chrome浏览器，更好的体验EMSHOP',
    '在未来的每一秒，你都将是全新的自己',
    '使用过程中发现问题，可以联系群主或管理员解决'
];

function tips_init() {
    global $array_tips;
    $i = mt_rand(0, count($array_tips) - 1);
    $tip = $array_tips[$i];
    echo "<div id=\"tip\"><span class=\"tip-icon\"></span><span class=\"tip-content\">$tip</span></div>";

}

addAction('adm_main_top', 'tips_init');

function tips_css() {
    echo "<style>
    @keyframes tipFadeIn {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    #tip{
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        margin-bottom: 13px;
        font-size: 13px;
        line-height: 1.5;
        color: #4a5568;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        transition: all 0.2s ease;
        animation: tipFadeIn 0.3s ease-out;
    }
    #tip:hover {
        border-color: #cbd5e0;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    }
    #tip .tip-icon {
        flex-shrink: 0;
        width: 16px;
        height: 16px;
        background: url('/content/plugin/tips/icon_tips.gif') no-repeat center;
        background-size: contain;
        opacity: 0.7;
    }
    #tip .tip-content {
        flex: 1;
    }
    </style>\n";
}
// EP EM ET
addAction('adm_main_top', 'tips_css');
