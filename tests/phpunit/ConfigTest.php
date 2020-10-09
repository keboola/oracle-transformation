<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\Tests;

use Keboola\OracleTransformation\Config\Config;
use Keboola\OracleTransformation\Config\ConfigDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigTest extends TestCase
{
    public function testConfig(): void
    {
        $configArray = [
            'parameters' => [
                'db' => [
                    'host' => 'xxx',
                    'port' => 'xxx',
                    'user' => 'xxx',
                    '#password' => 'xxx',
                    'database' => 'xxx',
                    'schema' => 'xxx',
                ],
                'blocks' => [
                    [
                        'name' => 'first block',
                        'codes' => [
                            [
                                'name' => 'first code',
                                'script' => [
                                    'CREATE TABLE "output" ("id" INT)',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $configDefinition = new ConfigDefinition();

        $config = new Config($configArray, $configDefinition);

        $this->assertEquals($configArray['parameters'], $config->getParameters());
    }

    public function testMissingDb(): void
    {
        $configArray = [
            'parameters' => [],
        ];

        $configDefinition = new ConfigDefinition();
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child node "db" at path "root.parameters" must be configured.');
        new Config($configArray, $configDefinition);
    }

    public function testMissingBlock(): void
    {
        $configArray = [
            'parameters' => [
                'db' => [
                    'host' => 'xxx',
                    'port' => 'xxx',
                    'user' => 'xxx',
                    '#password' => 'xxx',
                    'database' => 'xxx',
                    'schema' => 'xxx',
                ],
            ],
        ];

        $configDefinition = new ConfigDefinition();
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child node "blocks" at path "root.parameters" must be configured.');
        new Config($configArray, $configDefinition);
    }

    public function testMissingCode(): void
    {
        $configArray = [
            'parameters' => [
                'db' => [
                    'host' => 'xxx',
                    'port' => 'xxx',
                    'user' => 'xxx',
                    '#password' => 'xxx',
                    'database' => 'xxx',
                    'schema' => 'xxx',
                ],
                'blocks' => [
                    [
                        'name' => 'first block',
                    ],
                ],
            ],
        ];

        $configDefinition = new ConfigDefinition();
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child node "codes" at path "root.parameters.blocks.0" must be configured.');
        new Config($configArray, $configDefinition);
    }

    public function testMissingScript(): void
    {
        $configArray = [
            'parameters' => [
                'db' => [
                    'host' => 'xxx',
                    'port' => 'xxx',
                    'user' => 'xxx',
                    '#password' => 'xxx',
                    'database' => 'xxx',
                    'schema' => 'xxx',
                ],
                'blocks' => [
                    [
                        'name' => 'first block',
                        'codes' => [
                            [
                                'name' => 'first code',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $configDefinition = new ConfigDefinition();
        $expectedMessage = 'The child node "script" at path "root.parameters.blocks.0.codes.0" must be configured.';
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);
        new Config($configArray, $configDefinition);
    }
}
