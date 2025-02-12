<?php

namespace App\Modules;

if (! defined('ABSPATH')) {
    die('Access denied.'); // Exit if accessed directly
}

use App\Utils\LogTrait;
use Exception;
use GuzzleHttp\Client;

class SearchIndex
{
    use LogTrait;

    public function getData($websites)
    {
        global $google_api_key, $google_search_engine_id;

        $client = new Client();
        $websiteData = array();

        try {
            foreach ($websites as $index => $website) {
                $response = $client->get("https://www.googleapis.com/customsearch/v1?key={$google_api_key}&cx={$google_search_engine_id}&q=site:{$website}");

                if (200 === $response->getStatusCode()) {
                    $body    = $response->getBody();
                    $content = $body->getContents();
                    $json    = json_decode($content);
                }

                $websiteData[$website] = array( 'search_results_count' => $json->searchInformation->totalResults ?? '' );

                $this->log->debug("[{$index}] {$website}: {$websiteData[$website]['search_results_count']}");
            }
        } catch (Exception $e) {
            $this->log->error('Search Index ERROR: ' . $e->getMessage());
        }

        return $websiteData;
    }
}
