<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation;

use Keboola\OracleTransformation\JobRunner\JobRunnerFactory;
use Keboola\Component\BaseComponent;
use Keboola\OracleTransformation\Config\Config;
use Keboola\OracleTransformation\Config\ConfigDefinition;
use Keboola\OracleTransformation\Config\TestConnectionConfigDefinition;
use Keboola\OracleTransformation\Exception\UserException;
use Keboola\StorageApi\Client;

class Component extends BaseComponent
{
    private const ORACLE_WRITER_COMPONENT_NAME = 'keboola.wr-db-oracle';

    private const ORACLE_EXTRACTOR_COMPONENT_NAME = 'keboola.ex-db-oracle';

    private const ACTION_RUN = 'run';

    private const ACTION_TEST_CONNECTION = 'testConnection';

    protected function run(): void
    {
        $this->runWriterJob();

        $transformation = new OracleTransformation(
            $this->getLogger(),
            new DatabaseAdapter($this->getAppConfig(), $this->getLogger())
        );
        $transformation->processBlocks($this->getAppConfig()->getBlocks());

        $this->runExtractorJob();
    }

    public function testConnection(): array
    {
        $databaseAdapter = new DatabaseAdapter($this->getAppConfig(), $this->getLogger());
        $databaseAdapter->createConnection();
        $databaseAdapter->queryExecute('SELECT CURRENT_DATE FROM dual');

        return [
            'status' => 'success',
        ];
    }

    protected function getSyncActions(): array
    {
        return [
            self::ACTION_TEST_CONNECTION => 'testConnection',
        ];
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        $action = $this->getRawConfig()['action'] ?? self::ACTION_RUN;
        switch ($action) {
            case self::ACTION_RUN:
                return ConfigDefinition::class;
            case self::ACTION_TEST_CONNECTION:
                return TestConnectionConfigDefinition::class;
            default:
                throw new UserException(sprintf('Unexpected action "%s"', $action));
        }
    }

    private function runWriterJob(): void
    {
        $jobRunnerFactory = JobRunnerFactory::create($this->createStorageClient(), $this->getLogger());
        foreach ($this->getAppConfig()->getInputTables() as $inputTable) {
            $columns = [];
            foreach ($inputTable['column_types'] as $item) {
                $columns[] = [
                    'name' => $item['source'],
                    'dbName' => $item['destination'],
                    'type' => $item['type'],
                    'nullable' => $item['nullable'],
                    'size' => $item['length'],
                ];
            }
            $job = $jobRunnerFactory->runJob(
                self::ORACLE_WRITER_COMPONENT_NAME,
                [
                    'parameters' => [
                        'db' => $this->getDbParameters(true),
                        'export' => true,
                        'tableId' => $inputTable['source'],
                        'dbName' => $inputTable['destination'],
                        'items' => $columns,
                    ],
                    'storage' => [
                        'input' => [
                            'tables' => [$inputTable],
                        ],
                    ],
                ]
            );

            if ($job['status'] === 'error') {
                throw new UserException(sprintf(
                    'Writer job failed with following message: "%s"',
                    $job['result']['message']
                ));
            } else if ($job['status'] !== 'success') {
                throw new UserException(sprintf(
                    'Writer job failed with status "%s" and message: "%s"',
                    $job['status'],
                    $job['result']['message'] ?? 'No message'
                ));
            }
            $this->getLogger()->info(sprintf('Finished writer job "%d"', $job['id']));
        }
    }

    private function runExtractorJob(): void
    {
        $jobRunnerFactory = JobRunnerFactory::create($this->createStorageClient(), $this->getLogger());
        foreach ($this->getAppConfig()->getExpectedOutputTables() as $outputTable) {
            $job = $jobRunnerFactory->runJob(
                self::ORACLE_EXTRACTOR_COMPONENT_NAME,
                [
                    'parameters' => [
                        'db' => $this->getDbParameters(),
                        'id' => 1,
                        'name' => $outputTable['source'],
                        'table' => [
                            'schema' => $this->getAppConfig()->getDbSchema(),
                            'tableName' => $outputTable['source'],
                        ],
                        'outputTable' => $outputTable['destination'],
                    ],
                ]
            );

            if ($job['status'] === 'error') {
                throw new UserException(sprintf(
                    'Extractor job failed with following message: "%s"',
                    $job['result']['message']
                ));
            } else if ($job['status'] !== 'success') {
                throw new UserException(sprintf(
                    'Extractor job failed with status "%s" and message: "%s"',
                    $job['status'],
                    $job['result']['message'] ?? 'No message'
                ));
            }
            $this->getLogger()->info(sprintf('Finished extractor job "%d" succeeded', $job['id']));
        }
    }

    private function createStorageClient(): Client
    {
        $client = new Client([
            'token' => $this->getAppConfig()->getStorageApiToken(),
            'url' => $this->getAppConfig()->getStorageApiUrl(),
        ]);
        $client->setRunId($this->getAppConfig()->getRunId());
        return $client;
    }

    private function getAppConfig(): Config
    {
        /** @var Config $config */
        $config = $this->getConfig();
        return $config;
    }

    private function getDbParameters(bool $schema = false): array
    {
        $arr = [
            'host' => $this->getAppConfig()->getDbHost(),
            'port' => $this->getAppConfig()->getDbPort(),
            'database' => $this->getAppConfig()->getDbDatabase(),
            'user' => $this->getAppConfig()->getDbUser(),
            '#password' => $this->getAppConfig()->getDbPassword(),
        ];

        if ($schema) {
            $arr['schema'] = $this->getAppConfig()->getDbSchema();
        }
        return $arr;
    }
}
