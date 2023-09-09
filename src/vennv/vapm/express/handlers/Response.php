<?php

/**
 * Vapm - A library support for PHP about Async, Promise, Coroutine, Thread, GreenThread
 *          and other non-blocking methods. The library also includes some Javascript packages
 *          such as Express. The method is based on Fibers & Generator & Processes, requires
 *          you to have php version from >= 8.1
 *
 * Copyright (C) 2023  VennDev
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

declare(strict_types=1);

namespace vennv\vapm\express\handlers;

use vennv\vapm\express\application\App;
use vennv\vapm\express\Express;
use vennv\vapm\http\Protocol;
use vennv\vapm\http\Status;
use vennv\vapm\simultaneous\Async;
use vennv\vapm\simultaneous\AsyncInterface;
use Socket;
use Throwable;
use Exception;
use function socket_write;
use function implode;
use function is_array;
use function is_string;
use function array_merge;
use function str_replace;
use function json_encode;
use function mime_content_type;
use function pathinfo;
use function ob_start;
use function ob_end_clean;
use function file_get_contents;
use function gmdate;
use function time;
use function is_dir;
use function stat;
use function md5;
use const PATHINFO_EXTENSION;

interface ResponseInterface
{

    public function getClient(): Socket;

    public function getMethod(): string;

    public function getPath(): string;

    public function getProtocol(): string;

    public function getStatus(): int;

    public function status(int $status): ResponseInterface;

    /**
     * @param string $key
     * @param string $value
     * @return void;
     */
    public function setHeader(string $key, string $value): void;

    /**
     * @param string $path
     * @param bool $usePath
     * @param bool $justActive
     * @param array<int|float|string, mixed> $options
     * @return AsyncInterface
     * @throws Throwable
     */
    public function render(string $path, bool $usePath = true, bool $justActive = false, array $options = ['Content-Type: text/html']): AsyncInterface;

    /**
     * @throws Throwable
     */
    public function active(string $path): AsyncInterface;

    /**
     * @throws Throwable
     */
    public function redirect(string $path, int $status = Status::FOUND): AsyncInterface;

    /**
     * @throws Throwable
     */
    public function send(string $data, int $status = Status::OK): AsyncInterface;

    /**
     * @param array<int|float|string, mixed> $data
     * @throws Throwable
     */
    public function json(array $data, int $status = Status::OK): AsyncInterface;

    /**
     * @throws Throwable
     */
    public function download(string $path, int $status = Status::OK): AsyncInterface;

    /**
     * @throws Throwable
     */
    public function file(string $path, int $status = Status::OK): AsyncInterface;

    /**
     * @throws Throwable
     */
    public function image(string $path, int $status = Status::OK): AsyncInterface;

    /**
     * @throws Throwable
     */
    public function video(string $path, int $status = Status::OK): AsyncInterface;

    /**
     * @param string $key
     * @param string $value
     * @param array<int|float|string, int|float|string> $options
     */
    public function cookie(string $key, string $value, array $options = []): void;

    /**
     * @param string $key
     * @param array<int|float|string, int|float|string> $options
     */
    public function clearCookie(string $key, array $options = []): void;

    public function attachment(string $filename): void;

}

final class Response implements ResponseInterface
{

    protected App $app;

    private Socket $client;

    private string $method;

    private string $path;

    private string $protocol = Protocol::HTTP_1_1;

    private int $status = Status::OK;

    /**
     * @var array<int, string>
     */
    private array $headers = [];

    /**
     * @var array<int|float|string, mixed>
     */
    private array $params;

    /**
     * @param App $app
     * @param Socket $client
     * @param string $path
     * @param string $method
     * @param array<int|float|string, mixed> $params
     */
    public function __construct(
        App    $app,
        Socket $client,
        string $path,
        string $method = '',
        array  $params = []
    )
    {
        $this->app = $app;
        $this->client = $client;
        $this->method = $method;
        $this->path = $path;
        $this->params = $params;
    }

    public function getClient(): Socket
    {
        return $this->client;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function status(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @param string $path
     * @param array<int|float|string, mixed> $options
     */
    private function buildHeader(string $path, array $options = ['Content-Type: text/html']): void
    {
        $protocol = $this->protocol;
        $status = $this->status;
        $statusName = Status::getStatusName($status);
        $optionsStatic = $this->app->getOptionsStatic();

        $hasDirect = false;
        if ($optionsStatic->enable) {
            $file = $this->app->path() . $path;

            if (is_callable($optionsStatic->setHeaders)) call_user_func($optionsStatic->setHeaders, $this, $path, stat($file));
            if ($optionsStatic->immutable) $options[] = 'Cache-Control: immutable';

            if ($optionsStatic->lastModified) {
                $date = gmdate('D, d M Y H:i:s', time());
                $options[] = 'Last-Modified: ' . $date . ' GMT';
            }

            if ($optionsStatic->etag) {
                $md5 = md5($this->path);
                $options[] = 'ETag: ' . $md5;
            }

            $options[] = 'Cache-Control: max-age=' . $optionsStatic->maxAge;

            if ($optionsStatic->redirect && is_dir($file)) {
                $options[] = 'Location: ' . $this->app->getUrl() . '/';
                $options[] = 'Connection: close';
                $hasDirect = true;
            }
        }

        if ($status === Status::FOUND && !$hasDirect) {
            $options[] = 'Location: ' . $this->app->getUrl() . $path;
            $options[] = 'Connection: close';
        }

        if ($status === Status::OK) {
            is_dir($this->path) ? $mime = mime_content_type($this->path) : $mime = 'text/html';
            $options[] = 'Content-Type: ' . $mime;
        }

        $options = array_merge($options, $this->headers);

        $data = "$protocol $status $statusName\r\n" . implode("\r\n", $options) . "\r\n\r\n";

        socket_write($this->client, $data);
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function setHeader(string $key, string $value): void
    {
        $this->headers[] = $key . ': ' . $value;
    }

    /**
     * @param string $path
     * @param bool $usePath
     * @param bool $justActive
     * @param array<int|float|string, mixed> $options
     * @return Async
     * @throws Throwable
     */
    public function render(
        string $path,
        bool   $usePath = true,
        bool   $justActive = false,
        array  $options = ['Content-Type: text/html']
    ): Async
    {
        if (!$justActive) $this->buildHeader($path, $options);

        return new Async(function () use ($path, $usePath, $justActive): void {
            ob_start();

            if ($usePath) {
                require_once $this->path . $path;
                $function = str_replace(['/', '.php'], '', $path);
                function_exists($function) ? $body = Async::await($function($this->params)) : $body = file_get_contents($this->path . $path);
            } else {
                $body = Async::await($path);
            }

            if (is_array($body)) {
                foreach ($body as $value) {
                    /** @var string $data */
                    $data = Async::await($value);

                    if (!$justActive) socket_write($this->client, $data);
                }
            } else {
                if (!is_string($body)) throw new Exception('Body must be string');
                if (!$justActive) socket_write($this->client, $body);
            }

            ob_end_clean();
        });
    }

    /**
     * @throws Throwable
     */
    public function active(string $path): AsyncInterface
    {
        return $this->render($path, true, true);
    }

    /**
     * @throws Throwable
     */
    public function redirect(string $path, int $status = Status::FOUND): AsyncInterface
    {
        $this->status = $status;
        return $this->render($path, false);
    }

    /**
     * @throws Throwable
     */
    public function send(string $data, int $status = Status::OK): AsyncInterface
    {
        $this->status = $status;
        return $this->render($data, false);
    }

    /**
     * @param array<int|float|string, mixed> $data
     * @throws Throwable
     */
    public function json(array $data, int $status = Status::OK): AsyncInterface
    {
        $this->status = $status;
        $encode = json_encode($data);

        if ($encode === false) throw new Exception('JSON encode error');

        return $this->render($encode, false, false, ['Content-Type: application/json']);
    }

    /**
     * @throws Throwable
     */
    public function download(string $path, int $status = Status::OK): AsyncInterface
    {
        $this->status = $status;
        return $this->render($path, true, false, ['Content-Type: application/octet-stream']);
    }

    /**
     * @throws Throwable
     */
    public function file(string $path, int $status = Status::OK): AsyncInterface
    {
        $this->status = $status;
        return $this->render($path, true, false, ['Content-Type: ' . mime_content_type($path)]);
    }

    /**
     * @throws Throwable
     */
    public function image(string $path, int $status = Status::OK): AsyncInterface
    {
        $this->status = $status;
        return $this->render($path, true, false, ['Content-Type: image/' . pathinfo($path, PATHINFO_EXTENSION)]);
    }

    /**
     * @throws Throwable
     */
    public function video(string $path, int $status = Status::OK): AsyncInterface
    {
        $this->status = $status;
        return $this->render($path, true, false, ['Content-Type: video/' . pathinfo($path, PATHINFO_EXTENSION)]);
    }

    /**
     * @param string $key
     * @param string $value
     * @param array<int|float|string, int|float|string> $options
     */
    public function cookie(string $key, string $value, array $options = []): void
    {
        $cookie = $key . '=' . $value;

        if (isset($options['expires'])) $cookie .= '; expires=' . gmdate('D, d M Y H:i:s', $options['expires']) . ' GMT';
        if (isset($options['maxAge'])) $cookie .= '; Max-Age=' . $options['maxAge'];
        if (isset($options['domain'])) $cookie .= '; Domain=' . $options['domain'];
        if (isset($options['path'])) $cookie .= '; Path=' . $options['path'];
        if (isset($options['secure'])) $cookie .= '; Secure';
        if (isset($options['httpOnly'])) $cookie .= '; HttpOnly';
        if (isset($options['sameSite'])) $cookie .= '; SameSite=' . $options['sameSite'];

        $this->setHeader('Set-Cookie', $cookie);
    }

    /**
     * @param string $key
     * @param array<int|float|string, int|float|string> $options
     */
    public function clearCookie(string $key, array $options = []): void
    {
        $options['maxAge'] = 0;
        $this->cookie($key, '', $options);
    }

    public function attachment(string $filename): void
    {
        $this->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function end(): void
    {
        socket_close($this->client);
    }

}