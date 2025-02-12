<?php

namespace App\Modules;

if (! defined('ABSPATH')) {
    die('Access denied.'); // Exit if accessed directly
}

use App\Utils\LogTrait;
use Exception;
use Google\Client;
use Google\Service\PagespeedInsights;

class Pagespeed
{
    use LogTrait;

    public function getData($websites)
    {
        global $google_api_key;
        $websiteData = [];

        $client = new Client();
        $client->setApplicationName("DRA Sales Automation - Page Speed");
        $client->setDeveloperKey($google_api_key);

        $service = new PagespeedInsights($client);
        foreach ($websites as $index => $website) {
            try {
                $psData = $service->pagespeedapi->runpagespeed($website);
            } catch (Exception $e) {
                $this->log->error('Page Speed ERROR: ' . $e->getMessage());
            }

            $websiteData[$website] = [
                'page_speed_performance' => isset($psData->lighthouseResult) ? $psData->lighthouseResult->categories->performance->score * 100 . " / 100" : '',
                'page_speed_first_content' => isset($psData->lighthouseResult) ? $psData->lighthouseResult->audits['metrics']['details']['items'][0]['firstContentfulPaint'] / 1000 . 's' : '',
                'page_speed_total_blocking_time' => isset($psData->lighthouseResult) ? $psData->lighthouseResult->audits['metrics']['details']['items'][0]['totalBlockingTime'] / 1000 . 's' : '',
                'page_speed_speed_index' => isset($psData->lighthouseResult) ? $psData->lighthouseResult->audits['metrics']['details']['items'][0]['speedIndex'] / 1000 . 's' : '',
            ];

            $this->log->debug("[{$index}] {$website}: performance {$websiteData[$website]['page_speed_performance']}, First Content {$websiteData[$website]['page_speed_first_content']}, Total Blocking Time {$websiteData[$website]['page_speed_total_blocking_time']}, Speed Index {$websiteData[$website]['page_speed_speed_index']}");

            unset($psData);
        }

        return $websiteData;
    }
}
