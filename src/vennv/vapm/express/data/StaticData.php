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

namespace vennv\vapm\express\data;

use vennv\vapm\utils\Property;

final class StaticData
{

    use Property;

    public string $dotfiles = 'ignore';

    public bool $etag = true;

    /**
     * @var array<string> $extensions
     */
    public array $extensions = [];

    public bool $fallthrough = true;

    public bool $immutable = false;

    public bool $lastModified = true;

    public int|float $maxAge = 0;

    public bool $redirect = true;

    public mixed $setHeaders = null;

    public bool $enable = false;

}