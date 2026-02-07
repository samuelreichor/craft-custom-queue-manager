<?php

namespace samuelreichor\queueManager\jobs;

use craft\queue\BaseJob;

class SimpleJob extends BaseJob
{
    public string $message = 'Processing...';
    public int $sleepSeconds = 1;

    public function execute($queue): void
    {
        sleep($this->sleepSeconds);
    }

    protected function defaultDescription(): ?string
    {
        return $this->message;
    }
}
