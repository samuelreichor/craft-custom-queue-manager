<?php

namespace samuelreichor\queueManager\services;

use Craft;
use craft\queue\Queue;
use yii\base\Component;

class QueueDiscoveryService extends Component
{
    /**
     * Get all registered queue components.
     *
     * @return array<string, array{component: Queue, label: string, channel: string}>
     */
    public function getRegisteredQueues(): array
    {
        $queues = [];

        // Scan all components for custom queues (excluding the default 'queue' component)
        foreach (Craft::$app->getComponents(true) as $id => $definition) {
            // Skip the default queue - we only want custom queues
            if ($id === 'queue') {
                continue;
            }

            try {
                $component = Craft::$app->get($id);
                if ($component instanceof Queue) {
                    $queues[$id] = [
                        'component' => $component,
                        'label' => $this->generateLabel($id),
                        'channel' => $component->channel ?? $id,
                    ];
                }
            } catch (\Throwable $e) {
                Craft::warning("Queue discovery: Could not load '{$id}': " . $e->getMessage(), __METHOD__);
            }
        }

        return $queues;
    }

    /**
     * Get a specific queue by component ID (excludes default queue).
     */
    public function getQueue(string $id): ?Queue
    {
        // Don't allow access to the default queue through this service
        if ($id === 'queue') {
            return null;
        }

        if (!Craft::$app->has($id)) {
            return null;
        }

        try {
            $component = Craft::$app->get($id);
            return $component instanceof Queue ? $component : null;
        } catch (\Throwable $e) {
            Craft::warning("Queue discovery: Could not load '{$id}': " . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Generate a human-readable label from a component ID.
     * Converts camelCase to Title Case (e.g., emailQueue -> Email Queue)
     */
    private function generateLabel(string $id): string
    {
        // Insert spaces before uppercase letters
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $id);
        // Capitalize first letter of each word
        return ucwords($label ?? $id);
    }
}
