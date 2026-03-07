<?php

/**
 * Recommended cron entry (every minute):
 *   * * * * * /usr/bin/php /path/to/your/app/cron/process_email_queue.php >> /path/to/logs/email_queue.log 2>&1
 */

declare(strict_types=1);

// Guard against running this through a browser
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script runs via cron only.');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use KSG\Mailer;

$start   = microtime(true);
$mailer  = new Mailer();
$mailer->processQueue(batchSize: 20);
$elapsed = round(microtime(true) - $start, 2);

echo '[' . date('Y-m-d H:i:s') . "] Queue processed in {$elapsed}s\n";