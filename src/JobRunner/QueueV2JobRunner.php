<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\JobRunner;

use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\JobData;

class QueueV2JobRunner extends JobRunner
{
    public function runJob(string $componentId, array $data): array
    {
        $jobData = new JobData($componentId, null, $data);
        $response = $this->getQueueClient()->createJob($jobData);

        $finished = false;
        while (!$finished) {
            $job = $this->getQueueClient()->getJob($response['id']);
            $finished = $job['isFinished'];
            sleep(10);
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
