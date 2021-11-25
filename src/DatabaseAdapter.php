<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation;

use Keboola\OracleTransformation\Config\Config;
use Keboola\OracleTransformation\Exception\UserException;
use Psr\Log\LoggerInterface;

class DatabaseAdapter
{
    private Config $config;

    private LoggerInterface $logger;

    /** @var resource */
    private $connection;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function createConnection(): self
    {
        $dbString = sprintf(
            '//%s:%s/%s',
            $this->config->getDbHost(),
            $this->config->getDbPort(),
            $this->config->getDbDatabase()
        );

        $this->logger->info(sprintf('Connecting to DSN "%s"', $dbString));

        $connection = oci_connect($this->config->getDbUser(), $this->config->getDbPassword(), $dbString);
        if (!$connection) {
            throw new UserException(sprintf('Cannot connect to host "%s"', $this->config->getDbHost()));
        }

        $this->connection = $connection;

        if (!empty($dbParams['schema'])) {
            $this->logger->info(sprintf('Switching schema to "%s"', $this->config->getDbSchema()));
            $sql = sprintf(
                'ALTER SESSION SET CURRENT_SCHEMA = %s',
                $this->escape($this->config->getDbSchema())
            );
            $this->queryExecute($sql);
        }

        return $this;
    }

    public function queryExecute(string $sql): void
    {
        $stmt = oci_parse($this->connection, $sql);
        if (!$stmt) {
            throw new UserException(sprintf('Cannot parse sql "%s"', $sql));
        }
        $result = oci_execute($stmt);
        if ($result === false) {
            $error = oci_error($stmt);
            if (!$error) {
                throw new UserException(sprintf('Query "%s" failed', $sql));
            } else {
                throw new UserException(sprintf('Query failed with message: "%s"', $error['message']));
            }
        }
    }

    private function escape(string $obj): string
    {
        return sprintf('"%s"', mb_strtoupper($obj));
    }
}
