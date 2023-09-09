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

namespace vennv\vapm\mysql;

interface ResultQueryInterface
{

    /**
     * @return string
     *
     * This method will return status of query
     */
    public function getStatus(): string;

    /**
     * @return string
     *
     * This method will return reason of query
     */
    public function getReason(): string;

    /**
     * @return array<int|string, string>
     *
     * This method will return errors of query
     */
    public function getErrors(): array;

    /**
     * @return array<int|string, string>
     *
     * This method will return rejects of query
     */
    public function getRejects(): array;

    /**
     * @return mixed
     *
     * This method will return result of query
     */
    public function getResult(): mixed;

}

final class ResultQuery implements ResultQueryInterface
{

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    private string $status;

    private string $reason;

    private array $errors;

    private array $rejects;

    private mixed $result;

    public function __construct(string $status, string $reason, array $errors, array $rejects, mixed $result)
    {
        $this->status = $status;
        $this->reason = $reason;
        $this->errors = $errors;
        $this->rejects = $rejects;
        $this->result = $result;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getRejects(): array
    {
        return $this->rejects;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

}