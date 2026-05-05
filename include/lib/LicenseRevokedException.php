<?php

declare(strict_types=1);

/**
 * 服务端明确返回"站点未激活 / 激活码不存在"等业务性错误时抛出。
 *
 * 与普通 RuntimeException（网络异常 / 格式错误）区分开：
 *   捕获本异常 = 服务端认定当前激活码 + 域名组合无效 → 调用方应删除本地授权记录
 *   捕获 RuntimeException = 网络或其他暂时性问题 → 保守保留本地状态
 */
class LicenseRevokedException extends RuntimeException
{
}
