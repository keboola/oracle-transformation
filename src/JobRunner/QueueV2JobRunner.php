<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\JobRunner;

use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\JobData;

class QueueV2JobRunner extends JobRunner
{
    private const MAX_DELAY = 10;

    public function runJob(string $componentId, array $data): array
    {
        $jobData = new JobData($componentId, null, $data);
        $response = $this->getQueueClient()->createJob($jobData);

        $finished = false;
        $attempt = 0;
        while (!$finished) {
            $job = $this->getQueueClient()->getJob($response['id']);
            $finished = $job['isFinished'];
            $attempt++;
            sleep(min(pow(2, $attempt), self::MAX_DELAY));
        }

        return $job;
    }

    private function getQueueClient(): Client
    {
        return new Client(
            $this->logger,
            $this->getServiceUrl('queue'),
            $this->storageApiClient->getTokenString()
        );
    }
}
