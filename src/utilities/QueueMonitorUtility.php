<?php

namespace samuelreichor\queueManager\utilities;

use Craft;
use craft\base\Utility;
use craft\queue\Queue;
use samuelreichor\queueManager\QueueManager;
use samuelreichor\queueManager\web\assets\queuemonitor\QueueMonitorAsset;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidConfigException;

class QueueMonitorUtility extends Utility
{
    public static function id(): string
    {
        return 'custom-queue-manager';
    }

    public static function displayName(): string
    {
        return Craft::t('queue-manager', 'Custom Queues');
    }

    public static function icon(): ?string
    {
        return dirname(__DIR__) . '/icon-mask.svg';
    }

    public static function badgeCount(): int
    {
        $totalFailed = 0;
        $queues = QueueManager::getInstance()->queueDiscovery->getRegisteredQueues();

        foreach ($queues as $queueData) {
            /** @var Queue $queue */
            $queue = $queueData['component'];
            $totalFailed += $queue->getTotalFailed();
        }

        return $totalFailed;
    }

    /**
     * @throws SyntaxError
     * @throws InvalidConfigException
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(QueueMonitorAsset::class);

        $discoveryService = QueueManager::getInstance()->queueDiscovery;
        $queues = $discoveryService->getRegisteredQueues();

        // Prepare queue data for the template
        $queueData = [];
        foreach ($queues as $id => $queue) {
            $queueData[$id] = [
                'id' => $id,
                'label' => $queue['label'],
                'channel' => $queue['channel'],
            ];
        }

        return $view->renderTemplate('queue-manager/_components/utilities/QueueMonitor/content.twig', [
            'queues' => $queueData,
            'settings' => QueueManager::getInstance()->getSettings(),
        ]);
    }

    /**
     * @throws SyntaxError
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function toolbarHtml(): string
    {
        return Craft::$app->getView()->renderTemplate(
            'queue-manager/_components/utilities/QueueMonitor/toolbar.twig'
        );
    }

    /**
     * @throws SyntaxError
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function footerHtml(): string
    {
        return Craft::$app->getView()->renderTemplate(
            'queue-manager/_components/utilities/QueueMonitor/footer.twig'
        );
    }
}
