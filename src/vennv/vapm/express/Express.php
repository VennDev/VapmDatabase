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

namespace vennv\vapm\express;

use vennv\vapm\express\application\App;
use vennv\vapm\express\router\Router;
use Exception;

/**
 * This is version 1.0.0-ALPHA16 of Express
 * This is version still in development, so it is not recommended to use it in production
 */
interface ExpressInterface
{

    /**
     * @return App
     *
     * This method returns the application
     */
    public function getApplication(): App;

    /**
     * @return Router
     *
     * This method will return the new router of the server
     */
    public function router(): Router;

}

final class Express implements ExpressInterface
{

    private ?App $app;

    public function __construct()
    {
        $this->app = new App();
    }

    public function getApplication(): App
    {
        return $this->app;
    }

    /**
     * @param array<string, mixed> $options
     * @throws Exception
     */
    public function router(array $options = []): Router
    {
        return new Router($options);
    }

}