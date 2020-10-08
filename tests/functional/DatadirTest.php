<?php

declare(strict_types=1);

namespace OracleTransformation\FunctionalTests;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\OracleTransformation\Exception\UserException;

class DatadirTest extends DatadirTestCase
{
    /** @var resource $dbConnection */
    private $dbConnection;

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->dbConnection) {
            $this->getDbConnection();
        }

        $this->clearTables();
    }

    private function getDbConnection(): void
    {
        $dbString = sprintf(
            '//%s:%s/%s',
            getenv('ORACLE_DB_HOST'),
            getenv('ORACLE_DB_PORT'),
            getenv('ORACLE_DB_DATABASE')
        );

        $this->dbConnection = oci_connect(getenv('ORACLE_DB_USER'), getenv('ORACLE_DB_PASSWORD'), $dbString);

        if (getenv('ORACLE_DB_SCHEMA')) {
            $sql = sprintf(
                'ALTER SESSION SET CURRENT_SCHEMA = %s',
                $this->escape(getenv('ORACLE_DB_SCHEMA'))
            );
            $this->queryExecute($sql);
        }
    }

    public function queryExecute(string $sql): void
    {
        $stmt = oci_parse($this->dbConnection, $sql);
        if (!$stmt) {
            throw new UserException(sprintf('Cannot parse sql "%s"', $sql));
        }
        oci_execute($stmt);
    }

    public function fetchAll(string $sql): array
    {
        $stmt = oci_parse($this->dbConnection, $sql);
        if (!$stmt) {
            throw new UserException(sprintf('Cannot parse sql "%s"', $sql));
        }
        oci_execute($stmt);

        $rows = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function escape(string $obj): string
    {
        return sprintf('"%s"', mb_strtoupper($obj));
    }

    private function clearTables(): void
    {
        $sql = <<<SQL
SELECT TABLE_NAME, TABLESPACE_NAME, OWNER 
FROM all_tables 
WHERE all_tables.TABLESPACE_NAME != 'SYSAUX' AND 
      all_tables.TABLESPACE_NAME != 'SYSTEM' AND 
      all_tables.TABLESPACE_NAME != 'RDSADMIN' AND 
      all_tables.OWNER != 'SYS' AND 
      all_tables.OWNER != 'SYSTEM'
SQL;

        $tables = $this->fetchAll($sql);

        foreach ($tables as $table) {
            $dropSql = <<<SQL
DROP TABLE "%s"."%s"
SQL;
            $this->queryExecute(sprintf($dropSql, $table['OWNER'], $table['TABLE_NAME']));
        }
    }
}
