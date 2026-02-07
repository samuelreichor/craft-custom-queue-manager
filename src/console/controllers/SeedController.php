<?php

namespace samuelreichor\queueManager\console\controllers;

use Craft;
use craft\console\Controller;
use craft\queue\Queue;
use samuelreichor\queueManager\jobs\EmailJob;
use samuelreichor\queueManager\jobs\FailingJob;
use samuelreichor\queueManager\jobs\ImportJob;
use samuelreichor\queueManager\jobs\LongRunningJob;
use samuelreichor\queueManager\jobs\SimpleJob;
use yii\console\ExitCode;

class SeedController extends Controller
{
    public int $count = 5;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'count';
        return $options;
    }

    public function actionIndex(): int
    {
        $this->stdout("Seeding all queues with test jobs...\n\n");

        $this->actionDefault();
        $this->actionEmail();
        $this->actionImport();
        $this->actionSlow();

        $this->stdout("\nDone! All queues have been seeded.\n");
        return ExitCode::OK;
    }

    public function actionDefault(): int
    {
        $this->stdout("Seeding default queue...\n");
        $queue = Craft::$app->getQueue();

        for ($i = 1; $i <= $this->count; $i++) {
            $queue->push(new SimpleJob([
                'message' => "Default queue job #{$i}",
                'sleepSeconds' => rand(1, 3),
            ]));
        }

        // Add a long-running job
        $queue->push(new LongRunningJob([
            'steps' => 5,
            'sleepPerStep' => 1,
        ]));

        // Add a failing job
        $queue->push(new FailingJob([
            'errorMessage' => 'Default queue test failure',
        ]));

        $this->stdout("  Added " . ($this->count + 2) . " jobs to default queue\n");
        return ExitCode::OK;
    }

    public function actionEmail(): int
    {
        $this->stdout("Seeding email queue...\n");

        $emailQueue = $this->getQueue('emailQueue');
        if (!$emailQueue) {
            $this->stderr("Email queue not found. Make sure 'emailQueue' is configured in app.php\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $recipients = ['user@example.com', 'admin@example.com', 'support@example.com'];
        $subjects = ['Welcome!', 'Password Reset', 'Newsletter', 'Order Confirmation', 'Reminder'];

        for ($i = 1; $i <= $this->count; $i++) {
            $emailQueue->push(new EmailJob([
                'to' => $recipients[array_rand($recipients)],
                'subject' => $subjects[array_rand($subjects)] . " #{$i}",
            ]));
        }

        // Add a failing email job
        $emailQueue->push(new FailingJob([
            'errorMessage' => 'SMTP connection failed: Could not connect to mail server',
        ]));

        $this->stdout("  Added " . ($this->count + 1) . " jobs to email queue\n");
        return ExitCode::OK;
    }

    public function actionImport(): int
    {
        $this->stdout("Seeding import queue...\n");

        $importQueue = $this->getQueue('importQueue');
        if (!$importQueue) {
            $this->stderr("Import queue not found. Make sure 'importQueue' is configured in app.php\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $sources = ['users.csv', 'products.csv', 'orders.csv', 'inventory.csv'];

        for ($i = 1; $i <= min($this->count, 3); $i++) {
            $importQueue->push(new ImportJob([
                'source' => $sources[array_rand($sources)],
                'totalRecords' => rand(50, 200),
            ]));
        }

        // Add a failing import job
        $importQueue->push(new FailingJob([
            'errorMessage' => 'Import failed: Invalid CSV format on line 42',
            'failAfterSeconds' => 2,
        ]));

        $this->stdout("  Added " . (min($this->count, 3) + 1) . " jobs to import queue\n");
        return ExitCode::OK;
    }

    public function actionSlow(): int
    {
        $this->stdout("Seeding slow queue...\n");

        $slowQueue = $this->getQueue('slowQueue');
        if (!$slowQueue) {
            $this->stderr("Slow queue not found. Make sure 'slowQueue' is configured in app.php\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Add long-running jobs
        for ($i = 1; $i <= min($this->count, 3); $i++) {
            $slowQueue->push(new LongRunningJob([
                'steps' => rand(10, 20),
                'sleepPerStep' => rand(1, 3),
            ]));
        }

        // Add some simple background jobs
        for ($i = 1; $i <= $this->count; $i++) {
            $slowQueue->push(new SimpleJob([
                'message' => "Background task #{$i}",
                'sleepSeconds' => rand(5, 15),
            ]));
        }

        $this->stdout("  Added " . (min($this->count, 3) + $this->count) . " jobs to slow queue\n");
        return ExitCode::OK;
    }

    public function actionMixed(): int
    {
        $this->stdout("Seeding mixed jobs across all queues...\n");

        $queues = [
            'queue' => Craft::$app->getQueue(),
            'emailQueue' => $this->getQueue('emailQueue'),
            'importQueue' => $this->getQueue('importQueue'),
            'slowQueue' => $this->getQueue('slowQueue'),
        ];

        $queues = array_filter($queues);

        foreach ($queues as $name => $queue) {
            // Random mix of job types
            $queue->push(new SimpleJob(['message' => "Quick task on {$name}"]));
            $queue->push(new LongRunningJob(['steps' => rand(3, 8)]));

            if (rand(0, 1)) {
                $queue->push(new FailingJob(['errorMessage' => "Random failure on {$name}"]));
            }
        }

        $this->stdout("  Added mixed jobs to all available queues\n");
        return ExitCode::OK;
    }

    public function actionFailing(): int
    {
        $this->stdout("Seeding failing jobs to all queues...\n");

        $queues = [
            'queue' => Craft::$app->getQueue(),
            'emailQueue' => $this->getQueue('emailQueue'),
            'importQueue' => $this->getQueue('importQueue'),
            'slowQueue' => $this->getQueue('slowQueue'),
        ];

        $errors = [
            'Connection timeout after 30 seconds',
            'Memory limit exceeded',
            'File not found: /tmp/missing.csv',
            'Invalid JSON response from API',
            'Database connection lost',
            'Permission denied: /var/log/app.log',
        ];

        foreach (array_filter($queues) as $name => $queue) {
            for ($i = 1; $i <= $this->count; $i++) {
                $queue->push(new FailingJob([
                    'errorMessage' => $errors[array_rand($errors)],
                    'failAfterSeconds' => rand(1, 3),
                ]));
            }
        }

        $this->stdout("  Added {$this->count} failing jobs to each queue\n");
        return ExitCode::OK;
    }

    private function getQueue(string $componentId): ?Queue
    {
        if (!Craft::$app->has($componentId)) {
            return null;
        }

        $component = Craft::$app->get($componentId);
        return $component instanceof Queue ? $component : null;
    }
}
