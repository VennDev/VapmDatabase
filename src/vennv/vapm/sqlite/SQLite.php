<?php

namespace vennv\vapm\sqlite;

use vennv\vapm\database\ResultQuery;
use vennv\vapm\simultaneous\Error;
use vennv\vapm\simultaneous\FiberManager;
use vennv\vapm\simultaneous\Promise;
use vennv\vapm\utils\Utils;
use RuntimeException;
use SQLite3;
use Throwable;
use function time;
use function count;
use function mysqli_poll;

interface SQLiteInterface
{

    /**
     * @return string
     *
     * Get host of SQLite connection
     */
    public function getHost(): string;

    /**
     * @return string
     *
     * Get username of SQLite connection
     */
    public function getUsername(): string;

    /**
     * @return string
     *
     * Get password of SQLite connection
     */
    public function getPassword(): string;

    /**
     * @return string
     *
     * Get database of SQLite connection
     */
    public function getDatabase(): string;

    /**
     * @return int
     *
     * Get port of SQLite connection
     */
    public function getPort(): int;

    /**
     * @return SQLite3
     *
     * Get SQLite3 instance
     */
    public function getSQLite3(): SQLite3;

    /**
     * @param string $query
     * @param array<int|string, mixed> $namedArgs
     * @throws Throwable
     * @return Promise
     *
     * Execute a query
     */
    public function execute(string $query, array $namedArgs = []): Promise;

    /**
     * Close SQLite connection
     */
    public function close(): void;

}

final class SQLite implements SQLiteInterface
{

    private string $host;

    private string $username;

    private string $password;

    private string $database;

    private int $port;

    private SQLite3 $sqlite3;

    private bool $isBusy = false;

    private int $queryTimeout = 10;

    public function __construct(string $host, string $username, string $password, string $database, int $port)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port;

        $this->sqlite3 = new SQLite3($this->database);
    }

    public function getSQLite3(): SQLite3
    {
        return $this->sqlite3;
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

    /**
     * @throws Throwable
     */
    public function execute(string $query, array $namedArgs = []): Promise
    {
        if ($this->isBusy) throw new RuntimeException('This SQLite connection is busy!');
        $this->isBusy = true;

        return new Promise(function ($resolve, $reject) use ($query, $namedArgs): void {
            if (count($namedArgs) > 0) $query = Utils::buildQueryByNamedArgs($query, $namedArgs);

            $this->sqlite3->query($query);

            $poll = [$this->sqlite3];
            $begin = time();

            while (count($poll) > 0) {
                $links = $errors = $rejects = [];
                foreach ($poll as $key => $link) $links[$key] = $link;

                if (!mysqli_poll($links, $errors, $rejects, 0, 1000)) continue;
                foreach ($links as $link) {
                    if ($result = $link->reap_async_query()) {
                        $resultQuery = new ResultQuery(ResultQuery::SUCCESS, '', $errors, $rejects, $result);
                        $resolve($resultQuery);
                        $this->isBusy = false;
                        return;
                    }
                }

                if (time() - $begin > $this->queryTimeout) {
                    $reject(new ResultQuery(ResultQuery::FAILED, Error::QUERY_TIMEOUT, $errors, $rejects, null));
                    $this->isBusy = false;
                    return;
                }

                FiberManager::wait();
            }
        });
    }

    public function close(): void
    {
        $this->sqlite3->close();
    }

    public function __destruct()
    {
        $this->close();
    }

}