<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation;

use Keboola\Component\BaseComponent;
use Keboola\Component\Logger;
use Keboola\OracleTransformation\Config\Config;
use Keboola\OracleTransformation\Config\ConfigDefinition;
use Keboola\OracleTransformation\Config\TestConnectionConfigDefinition;
use Keboola\OracleTransformation\Exception\ApplicationException;
use Keboola\OracleTransformation\Exception\UserException;
use Keboola\Syrup\Client as SyrupClient;
use Keboola\StorageApi\Client as StorageApiClient;

class Component extends BaseComponent
{
    private const ORACLE_WRITER_COMPONENT_NAME = 'keboola.wr-db-oracle';

    private const ORACLE_EXTRACTOR_COMPONENT_NAME = 'keboola.ex-db-oracle';

    private const ACTION_RUN = 'run';

    private const ACTION_TEST_CONNECTION = 'testConnection';

    private ?array $services = null;

    protected function run(): void
    {
        $this->runWriterJob();

        $transformation = new OracleTransformation(
            $this->getAppConfig(),
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
        $syrupClient = $this->getSyrupClient();

        foreach ($this->getAppConfig()->getInputTables() as $inputTable) {
            $columns = [];
            foreach ($inputTable['column_types'] as $item) {
                $columns[] = [
                    'name' => $item['source'],
                    'dbName' => $item['destination'],
                    'type' => $item['type'],
                    'nullable' => $item['convert_empty_values_to_null'],
                    'size' => $item['length'],
                ];
            }
            $job = $syrupClient->runJob(
                self::ORACLE_WRITER_COMPONENT_NAME,
                [
                    'configData' => [
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
        $syrupClient = $this->getSyrupClient();
        foreach ($this->getAppConfig()->getExpectedOutputTables() as $outputTable) {
            $job = $syrupClient->runJob(
                self::ORACLE_EXTRACTOR_COMPONENT_NAME,
                [
                    'configData' => [
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

    private function getSyrupClient(): SyrupClient
    {
        $config = [
            'token' => $this->getAppConfig()->getStorageApiToken(),
            'url' => $this->getSyrupUrl(),
            'super' => 'docker',
            'runId' => $this->getAppConfig()->getRunId(),
        ];

        return new SyrupClient($config);
    }

    private function getSyrupUrl(): string
    {
        return $this->getServiceUrl('syrup');
    }

    private function getServiceUrl(string $serviceId): string
    {
        $foundServices = array_values(array_filter($this->getServices(), function ($service) use ($serviceId) {
            return $service['id'] === $serviceId;
        }));
        if (empty($foundServices)) {
            throw new ApplicationException(sprintf('%s service not found', $serviceId));
        }
        return $foundServices[0]['url'];
    }

    private function getServices(): array
    {
        if (!$this->services) {
            $storageClient = new StorageApiClient([
                'token' => $this->getAppConfig()->getStorageApiToken(),
                'url' => $this->getAppConfig()->getStorageApiUrl(),
            ]);
            $this->services = $storageClient->indexAction()['services'];
        }
        return $this->services;
    }

    private function getAppConfig(): Config
    {
        /** @var Config $config */
        $config = $this->getConfig();
        return $config;
    }

    public function getLogger(): Logger
    {
        /** @var Logger $logger */
        $logger = parent::getLogger();
        return $logger;
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
