<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\Config;

use Keboola\Component\Config\BaseConfig;
use Keboola\OracleTransformation\Exception\ApplicationException;

class Config extends BaseConfig
{
    public function getStorageApiToken(): string
    {
        $token = getenv('KBC_TOKEN');
        if (!$token) {
            throw new ApplicationException('KBC_TOKEN environment variable must be set');
        }
        return $token;
    }

    public function getRunId(): string
    {
        $runId = getenv('KBC_RUNID');
        if (!$runId) {
            throw new ApplicationException('KBC_RUNID environment variable must be set');
        }
        return $runId;
    }

    public function getStorageApiUrl(): string
    {
        $url = getenv('KBC_URL');
        if (!$url) {
            throw new ApplicationException('KBC_URL environment variable must be set');
        }
        return $url;
    }

    public function getDbHost(): string
    {
        return $this->getValue(['parameters', 'db', 'host']);
    }

    public function getDbPort(): string
    {
        return (string) $this->getValue(['parameters', 'db', 'port']);
    }

    public function getDbDatabase(): string
    {
        return $this->getValue(['parameters', 'db', 'database']);
    }

    public function getDbSchema(): string
    {
        return $this->getValue(['parameters', 'db', 'schema']);
    }

    public function getDbUser(): string
    {
        return $this->getValue(['parameters', 'db', 'user']);
    }

    public function getDbPassword(): string
    {
        return $this->getValue(['parameters', 'db', '#password']);
    }

    public function getBlocks(): array
    {
        return array_map(
            fn(array $data) => new Block($data),
            $this->getValue(['parameters', 'blocks'])
        );
    }
}
