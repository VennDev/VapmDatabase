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

namespace vennv\vapm\database;

use vennv\vapm\mysql\MySQL;
use vennv\vapm\sqlite\SQLite;
use vennv\vapm\simultaneous\Async;
use vennv\vapm\simultaneous\FiberManager;
use Exception;
use Throwable;

interface SQLPoolInterface
{

    /**
     * @return string
     *
     * Get type of Database connection
     */
    public function getType(): string;

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
     * @throws Throwable
     *
     * This function is used to execute query
     */
    public function execute(string $query, array $namedArgs = []): Async;

}

final class SQLPool implements SQLPoolInterface
{

    private string $type;

    private string $host;

    private string $username;

    private string $password;

    private string $database;

    private int $port;

    /** @var array<int, MySQL|SQLite> */
    protected array $handler = [];

    /**
     * @throws Exception
     */
    public function __construct(
        string $type,
        int    $numberConnections,
        string $host,
        string $username,
        string $password,
        string $database,
        int    $port = 3306
    )
    {
        $this->type = $type;
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port;

        $type = match ($type) {
            "mysql" => MySQL::class,
            "sqlite" => SQLite::class,
            default => throw new Exception("Invalid type of SQL connection")
        };

        for ($i = 0; $i < $numberConnections; ++$i) $this->handler[] = new $type($host, $username, $password, $database, $port);
    }

    /**
     * @return string
     *
     * Get type of Database connection
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     *
     * Get host of MySQL connection
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return string
     *
     * Get username of MySQL connection
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     *
     * Get password of MySQL connection
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string
     *
     * Get database of MySQL connection
     */
    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * @return int
     *
     * Get port of MySQL connection
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @throws Throwable
     */
    public function execute(string $query, array $namedArgs = []): Async
    {
        return new Async(function () use ($query, $namedArgs): mixed {
            $result = null;

            while ($result === null) {
                foreach ($this->handler as $handler) {
                    if ($handler->isBusy()) continue;

                    $result = Async::await($handler->execute($query, $namedArgs));
                    break;
                }

                if ($result === null) FiberManager::wait();
            }

            return $result;
        });
    }

}
