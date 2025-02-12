<?php

/**
 * 0 * * * * /usr/bin/php /srv/www/public_html/src/init.php BATCH_SIZE SPREADSHEET_URL >/dev/null 2>&
 *
 * Delete logs/cron_batch.json to restart batch.
 */

namespace App;

use App\Handler\Main;

define('ABSPATH', dirname(__FILE__) . '/../');

// Set up error reporting and log any errors.
set_time_limit(0);
error_reporting(E_ALL);
ini_set('max_execution_time', 0);
ini_set('display_errors', '1');
ini_set('display_startup_errors', 1);
ini_set('log_errors', '1');
ini_set('error_log', ABSPATH . 'logs/cron_temp.log');

require_once ABSPATH . 'vendor/autoload.php';
require_once ABSPATH . 'src/config.php';

$main = new Main();
$main->readBatchData();
if ($main->isContinue()) {
    if ($main->isBatchDataSet()) {
        // Process batch.
        $main->processBatch();
    } else {
        // Restart Batch.
        // Retrieve the passed parameters
        $batch_size = $argv[1] ?? 0;
        $spreadsheet_url = $argv[2] ?? 'https://docs.google.com/spreadsheets/'; //Insert default spreadsheet here

        // Modules.
        $modules = [ 'pagespeed', 'search_console', 'search_index', 'wappalyzer', 'ahrefs'];
    }

    $main->start($spreadsheet_url, $batch_size, false, [], $modules);
} else {
    exit('Batch Finished. Delete logs/cron_batch.json to restart batch. Exiting..');
}
