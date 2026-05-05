<?php

declare(strict_types=1);

/**
 * Swoole 协程 HTTP 并发工具（隔离方案）。
 *
 * 设计前提：
 *   - 不依赖 swoole 全局 hook_flags（不污染 onRequest 处理 / 不动 Database 类的 mysqli 共享连接）
 *   - 仅靠 Swoole\Coroutine\Http\Client（显式协程客户端）做并发，跟其它阻塞 IO 共存
 *   - 自动适配"已在协程上下文"和"不在协程上下文"两种调用场景
 *
 * 典型场景：从插件里批量同步上游库存 / 批量推送通知 / 抓取多个第三方 API 等
 *
 * 用法 1 —— 简单批量 GET：
 *   $tasks = [1001 => 'https://api/stock?id=A', 1002 => 'https://api/stock?id=B'];
 *   $res = SwooleHttpClient::pool($tasks, function ($key, $url) {
 *       return SwooleHttpClient::fetch($url);
 *   }, 50);
 *   // $res = [1001 => ['ok'=>true,'code'=>200,'body'=>'{...}','error'=>''], 1002 => ...]
 *
 * 用法 2 —— 自定义请求（POST / 加头 / 鉴权）：
 *   $res = SwooleHttpClient::pool($goods, function ($id, $g) {
 *       return SwooleHttpClient::fetch('https://upstream.com/api/stock', [
 *           'method'  => 'POST',
 *           'headers' => ['Authorization' => 'Bearer ' . SECRET, 'Content-Type' => 'application/json'],
 *           'body'    => json_encode(['upstream_id' => $g['upstream_id']]),
 *           'timeout' => 8,
 *       ]);
 *   }, 50);
 */
final class SwooleHttpClient
{
    /**
     * 用信号量限流的并发执行器。
     *
     * 给一组任务（key => value）+ 一个 worker 回调（接收 key 和 value，返回任意结果），
     * 自动开 $concurrency 条协程并发跑，所有任务完成后返回 key 不变的结果数组。
     *
     * worker 抛异常时不会冒泡，被 catch 后写入 result['_error']，便于上层统一处理。
     *
     * @param iterable<int|string, mixed> $tasks 任务集合（key 用于关联结果）
     * @param callable(int|string, mixed): mixed $worker 单任务处理回调，签名 ($key, $value) => mixed
     * @param int $concurrency 最大并发数（信号量槽位）
     * @return array<int|string, mixed> 与 tasks 同 key 的结果数组
     */
    public static function pool(iterable $tasks, callable $worker, int $concurrency = 50): array
    {
        self::ensureCoroutine();
        if ($concurrency < 1) $concurrency = 1;

        // 先物化为数组，避免迭代器被消费多次（Channel 容量需要 count）
        $list = [];
        foreach ($tasks as $k => $v) {
            $list[$k] = $v;
        }
        if ($list === []) return [];

        $results = [];
        $run = function () use (&$results, $list, $worker, $concurrency) {
            // Channel 当信号量用：容量 = 最大同时跑的协程数
            $sem = new Swoole\Coroutine\Channel($concurrency);
            $wg  = new Swoole\Coroutine\WaitGroup();

            foreach ($list as $key => $value) {
                $sem->push(1);   // 占坑（满了就阻塞，等其它协程释放）
                $wg->add();
                Swoole\Coroutine::create(function () use ($key, $value, $worker, $sem, $wg, &$results) {
                    try {
                        $results[$key] = $worker($key, $value);
                    } catch (Throwable $e) {
                        // 单个任务失败不影响整体，记录到结果里让调用方决定怎么处理
                        $results[$key] = ['_error' => $e->getMessage()];
                    } finally {
                        $sem->pop();
                        $wg->done();
                    }
                });
            }
            $wg->wait();
        };

        // 已在协程里就直接跑（如 swoole timer tick 回调内）；否则用 Coroutine\run 包一层
        // 嵌套 Coroutine\run 在 swoole 4.5+ 会报错，必须先检测当前协程 cid
        if (Swoole\Coroutine::getCid() > 0) {
            $run();
        } else {
            Swoole\Coroutine\run($run);
        }

        return $results;
    }

    /**
     * 协程版单次 HTTP 请求。失败不抛异常，统一返回结构化结果让调用方判断。
     *
     * options 支持：
     *   - method:  GET / POST / PUT / DELETE 等，默认 GET
     *   - headers: ['Header-Name' => 'value', ...]
     *   - body:    POST/PUT 请求体（字符串）
     *   - timeout: 总超时秒，默认 10
     *   - connect_timeout: 连接超时秒，默认 3（短一点防 DNS/TCP 卡住）
     *
     * @param array{method?:string, headers?:array<string,string>, body?:string, timeout?:int, connect_timeout?:int} $options
     * @return array{ok:bool, code:int, body:string, error:string}
     */
    public static function fetch(string $url, array $options = []): array
    {
        self::ensureCoroutine();

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return ['ok' => false, 'code' => 0, 'body' => '', 'error' => '无效的 URL: ' . $url];
        }

        $ssl  = ($parts['scheme'] ?? 'http') === 'https';
        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? ($ssl ? 443 : 80));
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        $method = strtoupper($options['method'] ?? 'GET');
        $timeout = (int) ($options['timeout'] ?? 10);
        $connectTimeout = (int) ($options['connect_timeout'] ?? 3);
        $body = (string) ($options['body'] ?? '');

        // 默认头：上游若用 Host 路由，必须带正确 Host；UA 让上游可识别请求来源
        $headers = array_merge([
            'Host'       => $host,
            'User-Agent' => 'EMSHOP-Swoole/1.0',
            'Accept'     => 'application/json,*/*;q=0.9',
        ], (array) ($options['headers'] ?? []));

        try {
            $cli = new Swoole\Coroutine\Http\Client($host, $port, $ssl);
            $cli->set([
                'timeout'         => $timeout,
                'connect_timeout' => $connectTimeout,
            ]);
            $cli->setHeaders($headers);
            if ($method !== 'GET' && $body !== '') {
                $cli->setData($body);
            }
            $cli->setMethod($method);
            $ok = $cli->execute($path);
            $statusCode = (int) $cli->statusCode;
            $respBody = (string) $cli->body;

            $error = '';
            if (!$ok || $statusCode <= 0) {
                $error = sprintf(
                    'swoole http 失败 statusCode=%d errCode=%d (%s)',
                    $statusCode,
                    $cli->errCode,
                    function_exists('swoole_strerror') ? swoole_strerror($cli->errCode) : ''
                );
            } elseif ($statusCode < 200 || $statusCode >= 300) {
                $error = "HTTP {$statusCode}";
            }
            $cli->close();

            return [
                'ok'    => $ok && $statusCode >= 200 && $statusCode < 300,
                'code'  => max(0, $statusCode),
                'body'  => $respBody,
                'error' => $error,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'code' => 0, 'body' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * 校验 swoole 协程类是否可用，不可用直接抛 —— 工具仅在 swoole worker 里有意义。
     */
    private static function ensureCoroutine(): void
    {
        if (!class_exists('\\Swoole\\Coroutine\\Http\\Client') || !class_exists('\\Swoole\\Coroutine\\Channel')) {
            throw new RuntimeException(
                'Swoole 协程不可用：本工具必须在 swoole worker 进程中调用（如 swoole_worker_start 钩子内的 Timer 回调）'
            );
        }
    }
}
