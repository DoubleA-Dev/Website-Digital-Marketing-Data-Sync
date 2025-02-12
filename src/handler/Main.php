<?php

namespace App\Handler;

use App\Modules\Ahrefs;
use App\Modules\Pagespeed;
use App\Modules\SearchConsole;
use App\Modules\SearchIndex;
use App\Modules\Spreadsheet;
use App\Modules\Wappalyzer;
use App\Utils\Helper;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

if (! defined('ABSPATH')) {
    die('Access denied.'); // Exit if accessed directly
}

class Main
{
    private Logger $log;
    private $ajax_log_file = ABSPATH . 'logs/ajax_temp.log';
    private $json_file;
    private $spreadsheet_id;
    private $spreadsheet_url;
    private $modules;
    private $batch_size;
    private $debug_mode;
    private $test_data;
    private $batch_number;
    private $websites;
    private $start_time;
    private $continue = true;
    private $is_ajax = false;

    // Modules map.
    private $modules_map = [
        'pagespeed' => Pagespeed::class,
        'search_console' => SearchConsole::class,
        'search_index' => SearchIndex::class,
        'wappalyzer' => Wappalyzer::class,
        'ahrefs' => Ahrefs::class,
    ];

    private $spreadsheetClient;

    public function __construct($is_ajax = false)
    {
        $this->is_ajax = $is_ajax;
        $this->json_file = ABSPATH . 'logs/' . ($is_ajax ? 'ajax' : 'cron') . '_batch.json';
    }

    public function readBatchData()
    {
        // check if json file exists
        if (!file_exists($this->json_file)) {
            return;
        }

        // Retrieve the current batch data from file.
        $info = file_get_contents($this->json_file);

        if (false !== $info && $info) {
            try {
                $data = json_decode($info);

                $this->start_time = $data->start_time;
                $this->batch_number = $data->batch_number;
                $this->batch_size = $data->batch_size;
                $this->spreadsheet_id = $data->spreadsheet_id;
                $this->modules = $data->modules;
                $this->debug_mode = $data->debug_mode;
                $this->test_data = $data->test_data;
                $this->continue = $data->continue;
            } catch (Exception $e) {
                error_log('Error: ' . $e->getMessage());
            }
        }
    }

    public function isBatchDataSet()
    {
        return !empty($this->batch_number);
    }

    public function isContinue()
    {
        return $this->continue;
    }

    private function setBatchData()
    {
        // Save current batch data to file.
        $data = [
            'start_time' => $this->start_time,
            'batch_number' => $this->batch_number,
            'batch_size' => $this->batch_size,
            'spreadsheet_id' => $this->spreadsheet_id,
            'modules' => $this->modules,
            'debug_mode' => $this->debug_mode,
            'test_data' => $this->test_data,
            'continue' => $this->continue,
        ];

        file_put_contents($this->json_file, json_encode($data));
    }

    public function start($spreadsheet_url = '', $batch_size = 0, $debug_mode = false, $test_data = '', $modules = [])
    {
        // Reset temporary logs for Ajax.
        if ($this->is_ajax) {
            $this->resetAjaxLog();
        }

        // Get data.
        $this->spreadsheet_url = $spreadsheet_url;
        $this->batch_size = $batch_size;
        $this->debug_mode = $debug_mode;
        $this->debug_mode = $debug_mode;
        $this->test_data = $test_data;
        $this->modules = $modules;
        $this->start_time = date('Y-m-d_H-i-s');

        // Initialize logger that is shared across all modules.
        $this->initLogger();

        // Validate data.
        $this->log->debug('Checking if spreadsheet URL is provided.');
        if (empty($this->spreadsheet_url)) {
            $this->log->error('No spreadsheet provided.');
            $this->sendResponse(400);
        }

        $this->log->debug('Checking if modules are provided.');
        if (empty($this->modules)) {
            $this->log->error('No modules provided.');
            $this->sendResponse(400);
        }

        if ($this->debug_mode) {
            $this->log->debug('Debug mode is enabled. Checking if test data is provided.');
            if (empty($this->test_data)) {
                $this->log->error('No test data provided.');
                $this->sendResponse(400);
            }
        }

        $this->log->debug('Checking if batch size is provided.');
        if (empty($this->batch_size)) {
            $this->batch_size = 10;
            $this->log->warning('No batch size provided. Defaulted to 10.');
        }

        // Get spreadsheet ID from URL.
        $this->spreadsheet_id = preg_replace('/.*\/spreadsheets\/d\/(.*)\/edit.*/', '$1', $this->spreadsheet_url);

        // Check if spreadsheet ID is valid.
        if (empty($this->spreadsheet_id)) {
            $this->log->error('Invalid spreadsheet URL provided.');
            $this->sendResponse(400);
        }

        // Reset batch number and logs. Set session data so that next ajax calls can use it.
        $this->batch_number = 1;

        // Set index data for the next batches to use.
        $this->setBatchData();

        // Log.
        $this->log->debug('Batch Number : ' . $this->batch_number);
        $this->log->debug('Batch Size : ' . $this->batch_size);
        $this->log->debug('Spreadsheet ID : ' . $this->spreadsheet_id);
        $this->log->debug('Debug Mode : ' . ($this->debug_mode === 'on' ? 'true' : 'false'));
        $this->log->info('Batch Process started');

        if ($this->is_ajax) {
            // Return if ajax.
            $this->sendResponse();
        } else {
            // Process the first batch if cron.
            $this->processBatch();
        }
    }

    /**
     * Process the batch.
     */
    public function processBatch(): void
    {
        // Read current batch data from file.
        $this->readBatchData();

        // Initialize logger that is shared across all modules.
        $this->initLogger();

        $this->log->info('Processing Batch ' . $this->batch_number . '...');

        // Get website data.
        $this->setWebsites();

        if ($this->websites) {
            // Process the data for each module.
            foreach ($this->modules as $module) {
                $this->log->info('Processing module: ' . $module);
                $moduleClass = $this->modules_map[$module] ?? '';
                if (empty($moduleClass)) {
                    $this->log->warning('Invalid module provided. Skipping');
                    continue;
                }

                try {
                    $module = new $moduleClass($this->log);
                    $data = $module->getData($this->websites);

                    // If not debug mode, set data to send to spreadsheet.
                    if (!$this->debug_mode) {
                        $this->spreadsheetClient->addWebsiteData($data);
                    }
                } catch (\Exception $e) {
                    $this->log->error($e->getMessage());
                }
            }

            // If not debug mode, sync the data to spreadsheet.
            if (!$this->debug_mode) {
                $this->log->debug('Syncing data to spreadsheet...');
                $this->spreadsheetClient->syncWebsiteData();
            }
        }

        $this->log->info('Processing Batch ' . $this->batch_number .  ' Finished.');

        if (!$this->continue) {
            $this->log->info('Done. All batches are processed');
        }

        // Increase batch number.
        $this->batch_number += 1;

        // Update batch data.
        $this->setBatchData();

        // Return.
        $this->sendResponse(200, ['continueProcessing' => $this->continue]);
    }

    /**
     * Prepare websites for the batch.
     */
    private function setWebsites(): void
    {
        $start = ($this->batch_number - 1) * $this->batch_size + 1;
        $this->log->debug("Getting {$this->batch_size} websites for the batch starting from offset {$start}...");

        if ($this->debug_mode) {
            $this->log->debug('Debug mode enabled. Get from test data.');
            $rows = explode("\n", $this->test_data);
            $rows = array_slice($rows, $start - 1, $this->batch_size);
        } else {
            $this->log->debug('Debug mode disabled. Pull spreadsheet data.');
            $this->spreadsheetClient = new Spreadsheet($this->spreadsheet_id, $start + 1, $this->batch_size, $this->log);

            // Fetch one more for the header row for the first batch.
            $rows = $this->spreadsheetClient->pullURLs();
        }

        // Check if there are any more websites left for the next batch.
        $this->log->debug(count($rows) . " websites found.");
        if (count($rows) < $this->batch_size) {
            $this->continue = false;
        }

        $this->websites = $rows ? $this->filterValidWebsites($rows) : [];
    }

    /**
     * Filter valid websites.
     *
     * @param array $rows List of websites.
     */
    private function filterValidWebsites(array $rows): array
    {
        $this->log->debug("Filtering valid websites.");

        $filtered_websites = [];

        if (!$this->debug_mode) {
            // Save data we do not want to affect already in the spreadsheet.
            $extra_save_data = [];
        }

        foreach ($rows as $row) {
            $website = $this->debug_mode ? $row : $row[0];

            // Trim \r \n and any spacing.
            $website = preg_replace('/\s+/', '', Helper::sanitizeText($website));
            $result = $this->validateWebsite($website);

            if (!is_numeric($result)) {
                $filtered_websites[] = $result;
            }

            if (!$this->debug_mode) {
                $labels = $this->spreadsheetClient->getLabels();
                if (is_numeric($result)) {
                    // If website responds with an error code, don't pass it to modules.
                    // Instead set cell data to error code.
                    foreach ($labels as $column) {
                        $extra_save_data[$website][$column] = 'Err: ' . $result;
                    }
                } else {
                    // Save data that is not the URLs to be inserted before the JSON Data from modules
                    foreach ($row as $key => $column) {
                        // Don't include website URLs.
                        if ($key !== 0) {
                            // Save data along with the labels for that data.
                            $extra_save_data[$result][$labels[$key]] = $column;
                        }
                    }
                }
            }
        }

        if (!$this->debug_mode) {
            $this->spreadsheetClient->addWebsiteData($extra_save_data);
        }

        $this->log->debug(count($filtered_websites) . " valid websites.");
        return $filtered_websites;
    }

    /**
     * Validate Website
     * @param string $website : the website to be validated
     * @return int|string : HTTP response code if the website is not valid, the website otherwise
     */
    private function validateWebsite(string $website): int|string
    {
        if (!$website) {
            return 0;
        }

        // Add schema if not present.
        if (!preg_match('/^https?:\/\//', $website)) {
            $website = "https://" . $website;
        }

        // Test the URL and only include URLs that are valid.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $website);
        curl_setopt($ch, CURLOPT_TIMEOUT, '20'); // in seconds
        curl_setopt($ch, CURLOPT_HEADER, 1); // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, 1); // we don't need body
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        $http_code = curl_getinfo($ch)['http_code'];

        $this->log->debug("$website: $http_code");

        // Error codes not to include.
        $error_codes = [401,402,404,406,407,408,409,410,411,429,500,501,502,503,504,505,506,507,508,510,511];

        // If website URL does not respond with an error code, add the website to the list of URLs
        if ($http_code !== 0 && !in_array($http_code, $error_codes)) {
            // If domain is redirected, include the redirected URL instead.
            if (curl_getinfo($ch)['url'] !== $website) {
                $website = strtok(curl_getinfo($ch)['url'], '?');
            }

            return $website;
        }

        return $http_code;
    }

    public function sendResponse(int $status_code = 200, array $data = null): void
    {
        // If not ajax, just exit.
        if (!$this->is_ajax) {
            exit;
        }

        http_response_code($status_code);
        if (200 !== $status_code) {
            $this->log->error('Exit');
        }

        $response = [
            'data' => $data,
            'logs' => $this->getLogs()
        ];

        $this->resetAjaxLog();

        echo json_encode($response);

        die();
    }

    private function initLogger(): void
    {
        $this->log = new Logger('BATCH');

        // Permanent logger to store the logs for each report run (across all batches).
        $this->log->pushHandler(new StreamHandler(ABSPATH . 'logs/' . ($this->is_ajax ? 'ajax_' : 'cron_') .  $this->start_time . '.log'));

        if ($this->is_ajax) {
            // Temporary logger to store the logs and retrieve for ajax.
            $this->log->pushHandler(new StreamHandler($this->ajax_log_file));
        }
    }

    private function resetAjaxLog(): void
    {
        // Clear Log file
        $file = fopen($this->ajax_log_file, 'w');
        fwrite($file, '');
        fclose($file);
    }

    private function getLogs(): array
    {
        // Read from log file
        $logs = [];
        $file = fopen($this->ajax_log_file, 'r');
        $size = filesize($this->ajax_log_file);
        if (0 === $size) {
            return $logs;
        }
        $content = fread($file, $size);
        fclose($file);

        // Parse Log file content. Each line is a log entry.
        $lines = explode(PHP_EOL, $content);
        $last_valid_line = 0;
        foreach ($lines as $index => $line) {
            if (!$line) {
                continue;
            }
            $arr = explode(' ', $line, 3);
            if (count($arr) < 3) {
                // Handle multi line logs.
                $logs[$last_valid_line]['message'] .= $line;
                continue;
            } else {
                $time = array_shift($arr);
                $type = array_shift($arr);
                $message = array_shift($arr);
                $logs[$index] = [ 'time' => substr($time, 0, 20) . ']', 'type' => substr($type, 6, -1), 'message' => $message];
                $last_valid_line = $index;
            }
        }

        return $logs;
    }
}
