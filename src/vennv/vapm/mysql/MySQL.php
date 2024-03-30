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

namespace venndev\restapi\provider\mysql;

use venndev\restapi\provider\database\ResultQuery;
use vennv\vapm\simultaneous\Error;
use vennv\vapm\simultaneous\FiberManager;
use vennv\vapm\simultaneous\Promise;
use vennv\vapm\utils\Utils;
use mysqli;
use RuntimeException;
use Throwable;
use function time;
use function count;
use function mysqli_report;
use const MYSQLI_ASYNC;
use const MYSQLI_STORE_RESULT;
use const MYSQLI_REPORT_ERROR;
use const MYSQLI_REPORT_STRICT;

interface MySQLInterface
{

    /**
     * @return string
     *
     * Get host of MySQL connection
     */
    public function getHost(): string;

    /**
     * @return string
     *
     * Get username of MySQL connection
     */
    public function getUsername(): string;

    /**
     * @return string
     *
     * Get password of MySQL connection
     */
    public function getPassword(): string;

    /**
     * @return string
     *
     * Get database of MySQL connection
     */
    public function getDatabase(): string;

    /**
     * @return int
     *
     * Get port of MySQL connection
     */
    public function getPort(): int;

    /**
     * @return mysqli
     *
     * Get mysqli object of MySQL connection
     */
    public function getMysqli(): mysqli;

    /**
     * @return bool
     *
     * Check MySQL connection is busy or not
     */
    public function isBusy(): bool;

    /**
     * @return int
     *
     * Get query timeout of MySQL connection
     */
    public function getQueryTimeout(): int;

    /**
     * @param int $queryTimeout
     *
     * Set query timeout of MySQL connection
     */
    public function setQueryTimeout(int $queryTimeout): void;

    /**
     * @param string $query
     * @param array<string, mixed> $namedArgs
     * @return Promise
     * @throws Throwable
     *
     * Execute MySQL query
     */
    public function execute(string $query, array $namedArgs = []): Promise;

    /**
     * @return void
     *
     * Close MySQL connection
     */
    public function close(): void;

}

final class MySQL implements MySQLInterface
{

    private string $host;

    private string $username;

    private string $password;

    private string $database;

    private int $port;

    private mysqli $mysqli;

    private bool $isBusy = false;

    private int $queryTimeout = 10;

    public function __construct(
        string $host,
        string $username,
        string $password,
        string $database,
        int    $port = 3306
    )
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port;
        $this->mysqli = new mysqli($host, $username, $password, $database, $port);
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getMysqli(): mysqli
    {
        return $this->mysqli;
    }

    public function isBusy(): bool
    {
        return $this->isBusy;
    }

    public function getQueryTimeout(): int
    {
        return $this->queryTimeout;
    }

    public function setQueryTimeout(int $queryTimeout): void
    {
        $this->queryTimeout = $queryTimeout;
    }

    /**
     * @throws Throwable
     */
    public function execute(string $query, array $namedArgs = []): Promise
    {
        if ($this->isBusy) throw new RuntimeException('This MySQL connection is busy!');
        $this->isBusy = true;

        // Example: SELECT * FROM `user` WHERE `id` = :id
        $buildQueryByNamedArgs = function(string $query, array $namedArgs) : string {
            $keys = array_keys($namedArgs);
            $values = array_values($namedArgs);
            $keys = array_map(function ($key) {
                return ':' . $key;
            }, $keys);
            return str_replace($keys, $values, $query);
        };

        return new Promise(function ($resolve, $reject) use ($query, $namedArgs, $buildQueryByNamedArgs): void {
            if (count($namedArgs) > 0) $query = $buildQueryByNamedArgs($query, $namedArgs);

            $this->mysqli->query($query, MYSQLI_STORE_RESULT | MYSQLI_ASYNC);

            $poll = [$this->mysqli];
            $errors = [];
            $rejects = [];
            $begin = time();
            $numQueries = 0;

            while (time() - $begin <= $this->queryTimeout) {
                $numQueries = (int)mysqli::poll($poll, $errors, $rejects, 0, $this->queryTimeout);
                if ($numQueries > 0) break;
                FiberManager::wait();
            }

            $this->isBusy = false;

            if ($numQueries === 0) {
                $reject(new ResultQuery(ResultQuery::FAILED, "Query error!", $errors, $rejects, null));
            } else {
                $result = $this->mysqli->reap_async_query();
                $result === false ? $reject(new ResultQuery(ResultQuery::FAILED, $this->mysqli->error, $errors, $rejects, null)) : $resolve(new ResultQuery(ResultQuery::SUCCESS, '', $errors, $rejects, is_bool($result) ? $result : iterator_to_array($result->getIterator())));
            }
        });
    }

    public function close(): void
    {
        $this->mysqli->close();
    }

    public function __destruct()
    {
        $this->close();
    }

}
