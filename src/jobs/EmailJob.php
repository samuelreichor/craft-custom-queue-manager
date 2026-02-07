<?php

namespace samuelreichor\customQueueManager\jobs;

use craft\queue\BaseJob;

class EmailJob extends BaseJob
{
    public string $to = 'test@example.com';
    public string $subject = 'Test Email';

    public function execute($queue): void
    {
        // Simulate email sending
        sleep(2);
    }

    protected function defaultDescription(): ?string
    {
        return "Send email to {$this->to}: {$this->subject}";
    }
}
