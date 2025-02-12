<?php

namespace App;

use App\Handler\Main;
use App\Utils\Helper;

// Check if the request is an AJAX request.
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
if (!$is_ajax) {
    die('Access denied'); // Exit if not AJAX request
}

define('ABSPATH', dirname(dirname(__FILE__)) . '/');

require_once ABSPATH . '/vendor/autoload.php';
require_once ABSPATH . '/src/config.php';

$main = new Main(true);

// Start Batch Processing.
if (!empty($_GET['start'])) {
    $spreadsheet_url = Helper::sanitizeText($_POST['spreadsheet_url'] ?? '');
    $batch_size = Helper::sanitizeText($_POST['batch_size'] ?? 0);
    $debug_mode = Helper::sanitizeText($_POST['debug_mode'] ?? false);
    $test_data = Helper::sanitizeText($_POST['test_data'] ?? '');
    $modules = $_POST['modules'] ?? [];

    $main->start($spreadsheet_url, $batch_size, $debug_mode, $test_data, $modules);
}

// Fetch Logs.
if (!empty($_GET['fetch_logs'])) {
    $main->sendResponse();
}

// Process Batch.
if (!empty($_GET['process_batch'])) {
    $main->processBatch();
}
