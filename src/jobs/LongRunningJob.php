<?php

namespace samuelreichor\customQueueManager\jobs;

use craft\queue\BaseJob;

class LongRunningJob extends BaseJob
{
    public int $steps = 10;
    public int $sleepPerStep = 2;

    public function execute($queue): void
    {
        for ($i = 1; $i <= $this->steps; $i++) {
            sleep($this->sleepPerStep);
            $this->setProgress($queue, $i / $this->steps, "Step {$i} of {$this->steps}");
        }
    }

    protected function defaultDescription(): ?string
    {
        return "Long running job ({$this->steps} steps)";
    }
}
