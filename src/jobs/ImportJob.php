<?php

namespace samuelreichor\customQueueManager\jobs;

use craft\queue\BaseJob;

class ImportJob extends BaseJob
{
    public string $source = 'data.csv';
    public int $totalRecords = 100;

    public function execute($queue): void
    {
        for ($i = 1; $i <= $this->totalRecords; $i++) {
            // Simulate importing a record
            usleep(50000); // 50ms per record
            $this->setProgress($queue, $i / $this->totalRecords, "Importing record {$i} of {$this->totalRecords}");
        }
    }

    protected function defaultDescription(): ?string
    {
        return "Import {$this->totalRecords} records from {$this->source}";
    }
}
