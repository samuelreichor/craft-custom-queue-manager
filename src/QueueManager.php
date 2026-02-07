<?php

namespace samuelreichor\queueManager;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\queue\JobInterface;
use craft\queue\Queue as CraftQueue;
use craft\services\SystemMessages;
use craft\services\Utilities;
use samuelreichor\queueManager\models\Settings;
use samuelreichor\queueManager\services\QueueDiscoveryService;
use samuelreichor\queueManager\utilities\QueueMonitorUtility;
use yii\base\Event;
use yii\queue\ExecEvent;
use yii\queue\Queue as YiiQueue;

/**
 * Queue Manager plugin
 *
 * @method static QueueManager getInstance()
 * @method Settings getSettings()
 * @property-read QueueDiscoveryService $queueDiscovery
 * @author Samuel Reichör <samuelreichor@gmail.com>
 * @copyright Samuel Reichör
 * @license MIT
 */
class QueueManager extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'queueDiscovery' => QueueDiscoveryService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Register controller namespace
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'samuelreichor\queueManager\console\controllers';
        } else {
            $this->controllerNamespace = 'samuelreichor\queueManager\controllers';
        }

        $this->attachEventHandlers();
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('queue-manager/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register the Queue Monitor utility
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                if (!empty(QueueManager::getInstance()->queueDiscovery->getRegisteredQueues())) {
                    $event->types[] = QueueMonitorUtility::class;
                }
            }
        );

        // Register system message for job failure notifications
        Event::on(
            SystemMessages::class,
            SystemMessages::EVENT_REGISTER_MESSAGES,
            function(RegisterEmailMessagesEvent $event) {
                $event->messages[] = [
                    'key' => 'queue_manager_job_failed',
                    'heading' => 'When a queue job fails',
                    'subject' => 'Queue job failed: {{jobDescription}}',
                    'body' => "A queue job has failed.\n\n- **Job:** {{jobDescription}}\n- **Queue:** {{queueName}}\n- **Error:** {{errorMessage}}\n\n[Review in Control Panel]({{ queueName == 'default' ? cpUrl('utilities/queue-manager') : cpUrl('utilities/custom-queue-manager') }})",
                ];
            }
        );

        // Send email notification on final job failure
        Event::on(
            CraftQueue::class,
            YiiQueue::EVENT_AFTER_ERROR,
            function(ExecEvent $event) {
                // Only send an email if it is the first attempt
                if ((int)$event->attempt !== 1) {
                    return;
                }
                $settings = QueueManager::getInstance()->getSettings();

                if (!$settings->enableEmailNotifications || !$settings->notificationEmail) {
                    return;
                }

                $queue = $event->sender;
                $queueName = $queue->channel ?? 'default';

                try {
                    Craft::$app->getMailer()
                        ->composeFromKey('queue_manager_job_failed', [
                            'jobDescription' => $event->job instanceof JobInterface ? $event->job->getDescription() ?? 'Unknown' : 'Unknown',
                            'queueName' => $queueName,
                            'errorMessage' => $event->error?->getMessage() ?? 'Unknown error',
                        ])
                        ->setTo($settings->notificationEmail)
                        ->send();
                } catch (\Throwable $e) {
                    Craft::warning(
                        'Queue Manager: Failed to send notification email: ' . $e->getMessage(),
                        __METHOD__
                    );
                }
            }
        );
    }
}
