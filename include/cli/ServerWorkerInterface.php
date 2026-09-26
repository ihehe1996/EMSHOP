<?php

/**
 * 后台任务 worker 契约。
 *
 * 核心只提供「进程宿主」：拉起子进程、监护、崩溃重拉、日志转发、能力心跳。
 * **具体干什么是插件的事** —— 插件通过 `server_worker_types` 过滤器注册实现类，
 * 核心按 type 找到类并循环调用 tick()。
 *
 * 这样核心对后台任务零依赖：没装任何注册 worker 的插件时，
 * `php server start` 起来之后不会跑任何业务任务，但网站照常工作
 * （发货走 FPM 同步路径，不依赖常驻进程）。
 *
 * 实现约定：
 *   - tick() 应当是「一轮能干完就返回」的短任务，不要在里面写死循环；
 *     循环由核心的 CliServerWorker 控制，这样子进程才能及时响应停止信号。
 *   - tick() 抛异常由核心捕获并记到 server.log，不会杀掉 worker 进程。
 *   - 一轮 tick() **正常返回后**核心才刷新该 type 的能力心跳，
 *     所以 tick 卡死会被心跳 TTL 如实暴露出来。
 */
interface ServerWorkerInterface
{
    /**
     * 两轮 tick 之间的间隔（秒）。
     *
     * 需要「每秒醒一次 + 内部按时间戳判断该干哪件子任务」的复杂 worker
     * （参考旧 queue worker），这里返回 1，在 tick() 内部自己记 nextXxxAt 即可。
     */
    public function interval(): int;

    /**
     * 跑一轮任务。
     *
     * @throws Throwable 抛出的异常会被核心捕获记录，不会终止 worker
     */
    public function tick(): void;
}
