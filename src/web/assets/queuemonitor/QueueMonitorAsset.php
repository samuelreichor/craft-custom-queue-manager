<?php

namespace samuelreichor\queueManager\web\assets\queuemonitor;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\vue\VueAsset;

class QueueMonitorAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
            VueAsset::class,
        ];

        $this->js = [
            'queue-monitor.js',
        ];

        $this->css = [
            'queue-monitor.css',
        ];

        parent::init();
    }
}
