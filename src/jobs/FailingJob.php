<?php

namespace samuelreichor\customQueueManager\jobs;

use craft\queue\BaseJob;

class FailingJob extends BaseJob
{
    public string $errorMessage = 'This job intentionally failed for testing purposes.';
    public int $failAfterSeconds = 1;

    public function execute($queue): void
    {
        sleep($this->failAfterSeconds);
        throw new \RuntimeException($this->errorMessage);
    }

    protected function defaultDescription(): ?string
    {
        return 'Failing job (will throw exception)';
    }
}
