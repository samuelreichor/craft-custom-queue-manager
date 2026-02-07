<?php

namespace samuelreichor\customQueueManager\models;

use craft\base\Model;

/**
 * Custom Queue Manager settings
 */
class Settings extends Model
{
    /**
     * Auto-refresh interval in milliseconds (0 to disable).
     */
    public int $refreshInterval = 2000;

    /**
     * Maximum number of jobs to display per queue.
     */
    public int $jobsPerPage = 50;

    /**
     * Whether to send email notifications when a job fails.
     */
    public bool $enableEmailNotifications = false;

    /**
     * Email address to send failure notifications to.
     */
    public string $notificationEmail = '';

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['refreshInterval', 'jobsPerPage'], 'required'],
            [['refreshInterval'], 'integer', 'min' => 0, 'max' => 60000],
            [['jobsPerPage'], 'integer', 'min' => 10, 'max' => 500],
            [['enableEmailNotifications'], 'boolean'],
            [['notificationEmail'], 'email', 'when' => fn($model) => $model->enableEmailNotifications],
            [['notificationEmail'], 'required', 'when' => fn($model) => $model->enableEmailNotifications],
        ];
    }
}
