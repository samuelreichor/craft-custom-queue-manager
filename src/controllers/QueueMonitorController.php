<?php

namespace samuelreichor\queueManager\controllers;

use Craft;
use craft\db\Query;
use craft\queue\Queue;
use craft\web\Controller;
use samuelreichor\queueManager\QueueManager;
use yii\web\Response;

class QueueMonitorController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();
        $this->requirePermission('utility:queue-monitor');

        return true;
    }

    /**
     * Get jobs for a specific queue.
     */
    public function actionGetJobInfo(): Response
    {
        $this->requireAcceptsJson();

        $queueId = $this->request->getRequiredQueryParam('queueId');
        $settings = QueueManager::getInstance()->getSettings();
        $limit = $this->request->getQueryParam('limit', $settings->jobsPerPage);

        $queue = $this->getQueueById($queueId);
        if (!$queue) {
            return $this->asFailure(Craft::t('queue-manager', 'Queue not found.'));
        }

        $tableName = $queue->tableName;
        $channel = $queue->channel ?? $queueId;

        // Order: reserved (running) first, then waiting, then failed
        // Within each group, order by timePushed DESC
        $jobs = (new Query())
            ->from($tableName)
            ->where(['channel' => $channel])
            ->orderBy([
                new \yii\db\Expression('CASE WHEN [[dateReserved]] IS NOT NULL AND [[fail]] = 0 THEN 0 WHEN [[fail]] = 0 THEN 1 ELSE 2 END'),
                'timePushed' => SORT_DESC,
            ])
            ->limit((int)$limit)
            ->all();

        $formattedJobs = array_map(function($job) {
            return $this->formatJob($job);
        }, $jobs);

        return $this->asJson([
            'jobs' => $formattedJobs,
            'stats' => $this->getQueueStats($tableName, $channel),
        ]);
    }

    /**
     * Get details for a single job.
     */
    public function actionGetJobDetails(): Response
    {
        $this->requireAcceptsJson();

        $queueId = $this->request->getRequiredQueryParam('queueId');
        $jobId = $this->request->getRequiredQueryParam('jobId');

        $queue = $this->getQueueById($queueId);
        if (!$queue) {
            return $this->asFailure(Craft::t('queue-manager', 'Queue not found.'));
        }

        $channel = $this->getQueueChannel($queue, $queueId);

        $job = (new Query())
            ->from($queue->tableName)
            ->where(['id' => $jobId, 'channel' => $channel])
            ->one();

        if (!$job) {
            return $this->asFailure(Craft::t('queue-manager', 'Job not found.'));
        }

        return $this->asJson([
            'job' => $this->formatJob($job, true),
        ]);
    }

    /**
     * Retry a failed job.
     */
    public function actionRetry(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $queueId = $this->request->getRequiredBodyParam('queueId');
        $jobId = $this->request->getRequiredBodyParam('jobId');

        $queue = $this->getQueueById($queueId);
        if (!$queue) {
            return $this->asFailure(Craft::t('queue-manager', 'Queue not found.'));
        }

        $channel = $this->getQueueChannel($queue, $queueId);

        // Verify the job belongs to this queue's channel
        $job = (new Query())
            ->from($queue->tableName)
            ->where(['id' => $jobId, 'channel' => $channel])
            ->one();

        if (!$job) {
            return $this->asFailure(Craft::t('queue-manager', 'Job not found.'));
        }

        $queue->retry((string)$jobId);

        return $this->asSuccess(Craft::t('queue-manager', 'Job queued for retry.'));
    }

    /**
     * Release a job (remove it from the queue).
     */
    public function actionRelease(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $queueId = $this->request->getRequiredBodyParam('queueId');
        $jobId = $this->request->getRequiredBodyParam('jobId');

        $queue = $this->getQueueById($queueId);
        if (!$queue) {
            return $this->asFailure(Craft::t('queue-manager', 'Queue not found.'));
        }

        $channel = $this->getQueueChannel($queue, $queueId);

        // Verify the job belongs to this queue's channel
        $job = (new Query())
            ->from($queue->tableName)
            ->where(['id' => $jobId, 'channel' => $channel])
            ->one();

        if (!$job) {
            return $this->asFailure(Craft::t('queue-manager', 'Job not found.'));
        }

        $queue->release((string)$jobId);

        return $this->asSuccess(Craft::t('queue-manager', 'Job released.'));
    }

    /**
     * Retry all failed jobs in a queue.
     */
    public function actionRetryAll(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $queueId = $this->request->getRequiredBodyParam('queueId');

        $queue = $this->getQueueById($queueId);
        if (!$queue) {
            return $this->asFailure(Craft::t('queue-manager', 'Queue not found.'));
        }

        $channel = $this->getQueueChannel($queue, $queueId);

        // Get all failed jobs for this queue's channel and retry them
        $failedJobs = (new Query())
            ->select(['id'])
            ->from($queue->tableName)
            ->where(['channel' => $channel, 'fail' => true])
            ->column();

        foreach ($failedJobs as $jobId) {
            $queue->retry((string)$jobId);
        }

        return $this->asSuccess(Craft::t('queue-manager', 'All failed jobs queued for retry.'));
    }

    /**
     * Release all jobs in a queue.
     */
    public function actionReleaseAll(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $queueId = $this->request->getRequiredBodyParam('queueId');

        $queue = $this->getQueueById($queueId);
        if (!$queue) {
            return $this->asFailure(Craft::t('queue-manager', 'Queue not found.'));
        }

        $channel = $this->getQueueChannel($queue, $queueId);

        // Get all jobs for this queue's channel and release them
        $jobs = (new Query())
            ->select(['id'])
            ->from($queue->tableName)
            ->where(['channel' => $channel])
            ->column();

        foreach ($jobs as $jobId) {
            $queue->release((string)$jobId);
        }

        return $this->asSuccess(Craft::t('queue-manager', 'All jobs released.'));
    }

    /**
     * Get a queue by its component ID.
     */
    private function getQueueById(string $queueId): ?Queue
    {
        return QueueManager::getInstance()->queueDiscovery->getQueue($queueId);
    }

    /**
     * Get the channel name for a queue, with fallback to queue ID.
     */
    private function getQueueChannel(Queue $queue, string $queueId): string
    {
        return $queue->channel ?? $queueId;
    }

    /**
     * Format a job record for JSON response.
     */
    private function formatJob(array $job, bool $includeDetails = false): array
    {
        $status = $this->getJobStatus($job);

        $formatted = [
            'id' => $job['id'],
            'description' => $job['description'] ?? 'Unknown Job',
            'status' => $status,
            'statusLabel' => $this->getStatusLabel($status),
            'progress' => (int)($job['progress'] ?? 0),
            'progressLabel' => $job['progressLabel'] ?? null,
            'timePushed' => $job['timePushed'] ? date('Y-m-d H:i:s', (int)$job['timePushed']) : null,
            'attempt' => (int)($job['attempt'] ?? 1),
            'fail' => (bool)$job['fail'],
        ];

        if ($includeDetails) {
            $formatted['ttr'] = (int)($job['ttr'] ?? 300);
            $formatted['delay'] = (int)($job['delay'] ?? 0);
            $formatted['priority'] = (int)($job['priority'] ?? 1024);
            $formatted['error'] = $job['error'] ?? null;
            $formatted['dateReserved'] = $job['dateReserved'] ?? null;
            $formatted['dateFailed'] = $job['dateFailed'] ?? null;
            $formatted['timeUpdated'] = $job['timeUpdated'] ? date('Y-m-d H:i:s', (int)$job['timeUpdated']) : null;

            // Get job class and data from serialized job
            if (!empty($job['job'])) {
                try {
                    $jobObject = unserialize($job['job']);
                    if ($jobObject !== false) {
                        $formatted['class'] = get_class($jobObject);
                        $formatted['job'] = json_encode($this->getJobProperties($jobObject), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }
                } catch (\Throwable) {
                    $formatted['class'] = null;
                    $formatted['job'] = null;
                }
            }
        }

        return $formatted;
    }

    /**
     * Get public properties of a job object for display.
     */
    private function getJobProperties(object $job): array
    {
        $properties = [];
        $reflection = new \ReflectionObject($job);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($job);

            // Skip complex objects that can't be serialized to JSON
            if (is_object($value) && !($value instanceof \Stringable)) {
                $properties[$property->getName()] = '[' . get_class($value) . ']';
            } elseif (is_resource($value)) {
                $properties[$property->getName()] = '[resource]';
            } else {
                $properties[$property->getName()] = $value;
            }
        }

        return $properties;
    }

    /**
     * Determine the status of a job.
     */
    private function getJobStatus(array $job): string
    {
        if ($job['fail']) {
            return 'failed';
        }

        if ($job['dateReserved']) {
            return 'reserved';
        }

        return 'waiting';
    }

    /**
     * Get human-readable status label.
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'waiting' => Craft::t('queue-manager', 'Pending'),
            'reserved' => Craft::t('queue-manager', 'Reserved'),
            'failed' => Craft::t('queue-manager', 'Failed'),
            default => Craft::t('queue-manager', 'Unknown'),
        };
    }

    /**
     * Get queue statistics with a single aggregated query.
     */
    private function getQueueStats(string $tableName, string $channel): array
    {
        $stats = (new Query())
            ->select([
                'COUNT(*) as total',
                'SUM(CASE WHEN [[fail]] = 0 AND [[dateReserved]] IS NULL THEN 1 ELSE 0 END) as waiting',
                'SUM(CASE WHEN [[fail]] = 0 AND [[dateReserved]] IS NOT NULL THEN 1 ELSE 0 END) as reserved',
                'SUM(CASE WHEN [[fail]] = 1 THEN 1 ELSE 0 END) as failed',
            ])
            ->from($tableName)
            ->where(['channel' => $channel])
            ->one();

        return [
            'total' => (int)($stats['total'] ?? 0),
            'waiting' => (int)($stats['waiting'] ?? 0),
            'reserved' => (int)($stats['reserved'] ?? 0),
            'failed' => (int)($stats['failed'] ?? 0),
        ];
    }
}
