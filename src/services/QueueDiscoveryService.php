<?php

namespace samuelreichor\customQueueManager\services;

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

            // Pre-check: only attempt to load components that look like a Queue
            if (!$this->isQueueDefinition($definition)) {
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
     * Check if a component definition looks like a Queue class before instantiating it.
     */
    private function isQueueDefinition(mixed $definition): bool
    {
        if ($definition instanceof Queue) {
            return true;
        }

        // Extract the class name from the definition
        $class = null;
        if (is_string($definition)) {
            $class = $definition;
        } elseif (is_array($definition) && isset($definition['class'])) {
            $class = $definition['class'];
        }

        if ($class === null) {
            return false;
        }

        return $class === Queue::class || is_subclass_of($class, Queue::class);
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
