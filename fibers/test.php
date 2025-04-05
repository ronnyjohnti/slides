<?php

class Loop
{
    private static array $callStack = [];

    public static function defer(callable $callable): void
    {
        self::$callStack[] = new Fiber($callable);
    }

    public static function run(): void
    {
        while (self::$callStack !== []) {
            foreach (self::$callStack as $key => $fiber) {
                self::callFiber($key, $fiber);
            }
        }
    }

    public static function callFiber(int $id, Fiber $fiber): mixed
    {
        if ($fiber->isStarted() === false) {
            return $fiber->start($id);
        }

        if ($fiber->isTerminated() === false) {
            return $fiber->resume();
        }

        unset(self::$callStack[$id]);
        return $fiber->getReturn();
    }
}


class UrlFetcher {
    public string $content = '';
    private $fp;
    public function __construct(
        private readonly string  $url,
        private readonly Closure $onError,
        private readonly Closure $done,
    ) {}

    public function start(): void {
        $this->fp = stream_socket_client("tcp://{$this->url}:80", $errno, $errstr, 30);
        if (!$this->fp) {
            ($this->onError)($errstr);
        }
        stream_set_blocking($this->fp, false);
        fwrite($this->fp, "GET / HTTP/1.0\r\nHost: {$this->url}\r\nAccept: */*\r\n\r\n");
        Loop::defer($this->tick(...));
    }

    public function tick(): void
    {
        if ($this->isDone()) {
            fclose($this->fp);
            ($this->done)($this->content);
        } else {
            $this->readSomeBytes();
            Loop::defer($this->tick(...));
        }
    }

    public function readSomeBytes(): void {
        $this->content .= fgets($this->fp, 100);
    }

    public function isDone(): bool {
        return feof($this->fp);
    }
}

function fetchUrl(string $url, callable $onError, callable $done): void {
    $fetcher = new UrlFetcher($url, $onError(...), $done(...));

    Loop::defer($fetcher->start(...));
}

Loop::defer(function () {
    echo 'Starting loop...' . "\n";

    echo 'Fetching URL: ' . 'www.google.com' . "\n";
    fetchUrl(
        'www.google.com',
        function ($err) {
            echo 'Error fetching Google: ' . $err . "\n";
        },
        function ($content) {
            echo "Got Google.\n";
            echo 'Size is: ' . strlen($content) . "\n";
        }
    );

    echo 'Fetching URL: ' . 'www.dir.bg' . "\n";
    fetchUrl(
        'www.dir.bg',
        function ($err) {
            echo 'Error fetching dir.bg: ' . $err . "\n";
        },
        function ($content) {
            echo "Got dir.bg.\n";
            echo 'Size is: ' . $content . "\n";
        }
    );
});

Loop::run();
