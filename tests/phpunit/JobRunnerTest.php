<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\Tests;

use Generator;
use Keboola\OracleTransformation\JobRunner\JobRunnerFactory;
use Keboola\OracleTransformation\JobRunner\QueueV2JobRunner;
use Keboola\OracleTransformation\JobRunner\SyrupJobRunner;
use Keboola\StorageApi\Client as StorageClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Throwable;

class JobRunnerTest extends TestCase
{

    /**
     * @dataProvider jobRunnerFactoryDataProvider
     */
    public function testJobRunnerFactory(array $features, string $expectedClass): void
    {
        $storageClient = self::getMockBuilder(StorageClient::class)
            ->onlyMethods(['verifyToken'])
            ->disableOriginalConstructor()
            ->getMock();

        $storageClient
            ->expects($this->once())
            ->method('verifyToken')
            ->willReturn([
                'owner' => [
                    'features' => $features,
                ],
            ]);

        $jobRunner = JobRunnerFactory::create($storageClient, new NullLogger());

        self::assertEquals($expectedClass, get_class($jobRunner));
    }

    /**
     * @dataProvider serviceUrlDataProvider
     */
    public function testServiceUrl(string $service, string $expectedUrl): void
    {
        $storageClient = self::getMockBuilder(StorageClient::class)
            ->onlyMethods(['indexAction'])
            ->disableOriginalConstructor()
            ->getMock();

        $storageClient
            ->method('indexAction')
            ->willReturn([
                'services' => [
                    [
                        'id' => 'scheduler',
                        'url' => 'https://scheduler.keboola.com',
                    ],
                    [
                        'id' => 'queue',
                        'url' => 'https://queue.keboola.com',
                    ],
                    [
                        'id' => 'syrup',
                        'url' => 'https://syrup.keboola.com',
                    ],
                ],
            ]);

        $queueV2Runner = new QueueV2JobRunner($storageClient, new NullLogger());
        self::assertEquals($expectedUrl, $queueV2Runner->getServiceUrl($service));

        $syrupRunner = new SyrupJobRunner($storageClient, new NullLogger());
        self::assertEquals($expectedUrl, $syrupRunner->getServiceUrl($service));
    }

    public function testServiceUrlNotFound(): void
    {
        $storageClient = self::getMockBuilder(StorageClient::class)
            ->onlyMethods(['indexAction'])
            ->disableOriginalConstructor()
            ->getMock();

        $storageClient
            ->method('indexAction')
            ->willReturn([
                'services' => [],
            ]);

        $queueV2Runner = new QueueV2JobRunner($storageClient, new NullLogger());

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('notFound service not found');
        $queueV2Runner->getServiceUrl('notFound');
    }
    public function jobRunnerFactoryDataProvider(): Generator
    {
        yield 'syrup-runner' => [
            [],
            SyrupJobRunner::class,
        ];

        yield 'queuev2-runner' => [
            [
                'queuev2',
            ],
            QueueV2JobRunner::class,
        ];
    }

    public function serviceUrlDataProvider(): Generator
    {
        yield 'syrup-url' => [
            'syrup',
            'https://syrup.keboola.com',
        ];

        yield 'queue' => [
            'queue',
            'https://queue.keboola.com',
        ];
    }
}
