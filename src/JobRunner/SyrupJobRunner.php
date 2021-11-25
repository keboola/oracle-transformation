<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\JobRunner;

use Keboola\Syrup\Client;

class SyrupJobRunner extends JobRunner
{
    public function runJob(string $componentId, array $data): array
    {
        return $this->getSyrupClient()->runJob(
            $componentId,
            ['configData' => $data]
        );
    }

    private function getSyrupClient(?int $backoffMaxTries = null): Client
    {
        $config = [
            'token' => $this->storageApiClient->getTokenString(),
            'url' => $this->getServiceUrl('syrup'),
            'super' => 'docker',
            'runId' => $this->storageApiClient->getRunId(),
        ];

        if ($backoffMaxTries) {
            $config['backoffMaxTries'] = $backoffMaxTries;
        }

        return new Client($config);
    }
}
