<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\FunctionalTests;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Keboola\OracleTransformation\Exception\ApplicationException;
use Keboola\OracleTransformation\Exception\UserException;
use Symfony\Component\Process\Process;

class DatadirTest extends DatadirTestCase
{
    /** @var resource|null $dbConnection */
    private $dbConnection;

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->dbConnection) {
            $this->getDbConnection();
        }

        $this->clearTables();
    }

    /**
     * @dataProvider provideDatadirSpecifications
     */
    public function testDatadir(DatadirTestSpecificationInterface $specification): void
    {
        $tempDatadir = $this->getTempDatadir($specification);

        $this->replacePartOfConfig($tempDatadir->getTmpFolder());

        $process = $this->runScript($tempDatadir->getTmpFolder());

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
    }

    private function replacePartOfConfig(string $tempDataDir): void
    {
        $configFile = $tempDataDir . '/config.json';
        $config = json_decode((string) file_get_contents($configFile), true);
        $config['parameters']['db'] = array_merge(
            $config['parameters']['db'],
            [
                'host' => getenv('ORACLE_DB_HOST'),
                'port' => getenv('ORACLE_DB_PORT'),
                'user' => getenv('ORACLE_DB_USER'),
                '#password' => getenv('ORACLE_DB_PASSWORD'),
                'database' => getenv('ORACLE_DB_DATABASE'),
                'schema' => getenv('ORACLE_DB_SCHEMA'),
            ]
        );
        file_put_contents($configFile, json_encode($config));
    }

    private function getDbConnection(): void
    {
        $dbString = sprintf(
            '//%s:%s/%s',
            getenv('ORACLE_DB_HOST'),
            getenv('ORACLE_DB_PORT'),
            getenv('ORACLE_DB_DATABASE')
        );

        $connection = oci_connect(
            (string) getenv('ORACLE_DB_USER'),
            (string) getenv('ORACLE_DB_PASSWORD'),
            $dbString
        );
        if (!$connection) {
            throw new ApplicationException(sprintf(
                'Cannot connect to host "%s"',
                getenv('ORACLE_DB_HOST')
            ));
        }

        $this->dbConnection = $connection;

        if (getenv('ORACLE_DB_SCHEMA')) {
            $sql = sprintf(
                'ALTER SESSION SET CURRENT_SCHEMA = %s',
                $this->escape((string) getenv('ORACLE_DB_SCHEMA'))
            );
            $this->queryExecute($sql);
        }
    }

    public function queryExecute(string $sql): void
    {
        if (!$this->dbConnection) {
            throw new ApplicationException('DB connection does not exists.');
        }
        $stmt = oci_parse($this->dbConnection, $sql);
        if (!$stmt) {
            throw new UserException(sprintf('Cannot parse sql "%s"', $sql));
        }
        oci_execute($stmt);
    }

    public function fetchAll(string $sql): array
    {
        if (!$this->dbConnection) {
            throw new ApplicationException('DB connection does not exists.');
        }
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

    protected function assertMatchesSpecification(
        DatadirTestSpecificationInterface $specification,
        Process $runProcess,
        string $tempDatadir
    ): void {
        if ($specification->getExpectedReturnCode() !== null) {
            $this->assertProcessReturnCode($specification->getExpectedReturnCode(), $runProcess);
        } else {
            $this->assertNotSame(0, $runProcess->getExitCode(), 'Exit code should have been non-zero');
        }
        if ($specification->getExpectedStdout() !== null) {
            if ($runProcess->getExitCode() === 0) {
                $this->assertStringContainsString(
                    'Finished writer job ',
                    trim($runProcess->getOutput()),
                    'Failed asserting stdout output'
                );
                $this->assertStringContainsString(
                    'Finished extractor job ',
                    trim($runProcess->getOutput()),
                    'Failed asserting stdout output'
                );
            }
            $this->assertStringContainsString(
                trim($specification->getExpectedStdout()),
                trim($runProcess->getOutput()),
                'Failed asserting stdout output'
            );
        }
        if ($specification->getExpectedStderr() !== null) {
            $this->assertStringMatchesFormat(
                trim($specification->getExpectedStderr()),
                trim($runProcess->getErrorOutput()),
                'Failed asserting stderr output'
            );
        }
        if ($specification->getExpectedOutDirectory() !== null) {
            $this->assertDirectoryContentsSame(
                $specification->getExpectedOutDirectory(),
                $tempDatadir . '/out'
            );
        }
    }
}
