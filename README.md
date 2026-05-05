# EMSHOP

基于 PHP 的开源电商 / CMS 系统，面向多商户、插件化商品类型、多币种展示等场景。

> 本项目当前处于开发阶段，还未正式上线。main 分支暂为占位。请勿将 develop 分支的测试代码用于生产环境。稳定版本发布后会在
> [Releases](../../releases) 提供下载。

---

## 功能概览

- 多商户后台：独立商品管理、订单处理、店铺余额 / 提现
- 商品类型插件化：虚拟卡密、实物商品等类型由插件注册，核心只管编排
- 订单状态机：pending / paid / delivering / delivered / completed / refunding / refunded
- Swoole 异步：发货队列、心跳检测、主站与分站隔离
- 多币种展示：主货币记账 + 访客侧换算显示（符号 + 汇率快照）
- 优惠券 / 满减规则 / 返佣体系
- 应用商店：插件上架与付费安装

## 运行环境

| 组件 | 版本要求 |
| --- | --- |
| PHP | 7.4+（兼容 8.0） |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Swoole | 4.8+ |
| Web Server | Nginx / Apache（需 mod_rewrite） |

## 分支策略

- `main` —— 稳定发布快照（当前暂未放代码）
- `develop` —— 开发主线，代码会频繁变动

贡献代码请基于 `develop` 切分支，PR 目标分支选 `develop`。

## 快速开始

```bash
# 克隆开发分支
git clone -b develop https://github.com/<your-user>/em_cc.git
cd em_cc

# 准备环境配置
# 安装（首次）
# 访问 http://your-domain/install/ 跟随引导完成（会生成/写入 config.php 与安装锁）

# 启动 Swoole
php swoole/server.php start
```


## 开源协议

见 [LICENSE](./license.txt)。
