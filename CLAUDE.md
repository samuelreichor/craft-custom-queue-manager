# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Craft CMS 5 plugin ("Queue Manager") that provides a Control Panel utility for monitoring and managing custom queue jobs. PHP 8.2+, namespace `samuelreichor\queueManager`.

## Commands

```bash
composer check-cs       # Check coding standards (ECS with Craft CMS 4 rules)
composer fix-cs         # Auto-fix coding standard violations
composer phpstan        # Run static analysis (level 4)
```

Seeding test data (run from the Craft project root, not the plugin directory):
```bash
php craft queue-manager/seed             # Seed all queues
php craft queue-manager/seed/failing     # Seed failing jobs
php craft queue-manager/seed/mixed       # Mixed jobs across queues
php craft queue-manager/seed --count=10  # Specify job count
```

## Architecture

**Plugin entry point:** `src/QueueManager.php` — registers the `QueueDiscoveryService` and `QueueMonitorUtility` via Craft's event system.

**Key layers:**
- **Controller** (`controllers/QueueMonitorController.php`) — REST API for job info, retry, release, and bulk actions. All endpoints require CP request + `utility:queue-monitor` permission. Returns JSON.
- **Service** (`services/QueueDiscoveryService.php`) — Discovers custom queues registered in Craft's app config (excludes the default `queue` component).
- **Utility** (`utilities/QueueMonitorUtility.php`) — Registers the CP utility page and computes the failed-job badge count.
- **Settings** (`models/Settings.php`) — `refreshInterval` (ms, 0–60000, default 2000) and `jobsPerPage` (10–500, default 50).

**Frontend:** Vue 2 app in `src/web/assets/queuemonitor/dist/queue-monitor.js` (uses Craft's bundled Vue, no npm build step). Templates are Twig files in `src/templates/`.

**Demo jobs** (`src/jobs/`) — `SimpleJob`, `FailingJob`, `EmailJob`, `LongRunningJob`, `ImportJob` — used for testing via the `SeedController`.

## Code Quality

- ECS config: `ecs.php` (Craft CMS 4 coding standard set)
- PHPStan config: `phpstan.neon` (level 4, analyzes `src/`)
- No unit test suite — quality checks are static analysis and coding standards only
