<?php

namespace App\Modules;

if (! defined('ABSPATH')) {
    die('Access denied.'); // Exit if accessed directly
}

use App\Utils\LogTrait;
use Exception;
use GuzzleHttp\Client;

class Ahrefs
{
    use LogTrait;

    private const BASE_URL = 'https://api.ahrefs.com/v3/site-explorer';

    public function getData($websites)
    {
        global $ahrefs_api_key;

        $today = gmdate('Y-m-d');

        $client = new Client();
        $params = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $ahrefs_api_key,
                'Accept'        => 'application/json',
            ),
        );

        $websiteData = array();

        foreach ($websites as $index => $website) {
            /**try {
                // Backlinks count.
                $response = $client->request('GET', self::BASE_URL . '/backlinks-stats?date=' . $today . '&output=json&target=' . urlencode($website), $params);
                if (200 === $response->getStatusCode()) {
                    $body          = $response->getBody();
                    $content       = $body->getContents();
                    $json          = json_decode($content);
                    $all_backlinks = $json->metrics->live ?? 0;
                }
            } catch (Exception $e) {
                $this->log->error('Ahrefs Backlinks Count Error: ' . $e->getMessage());
            }

            try {
                // Broken Backlinks count.
                $response = $client->request('GET', self::BASE_URL . '/broken-backlinks?limit=500&select=url_from&aggregation=all&target=' . urlencode($website), $params);
                if (200 === $response->getStatusCode()) {
                    $body             = $response->getBody();
                    $content          = $body->getContents();
                    $json             = json_decode($content);
                    $broken_backlinks = count($json->backlinks);
                    if ($broken_backlinks === 500) {
                        $broken_backlinks = '500+';
                    }
                }
            } catch (Exception $e) {
                $this->log->error('Ahrefs Broken Backlinks Error: ' . $e->getMessage());
            }

            try {
                // Domain rating.
                $response = $client->request('GET', self::BASE_URL . '/domain-rating?date=' . $today . '&output=json&target=' . urlencode($website), $params);
                if (200 === $response->getStatusCode()) {
                    $body          = $response->getBody();
                    $content       = $body->getContents();
                    $json          = json_decode($content);
                    $domain_rating = $json->domain_rating->domain_rating ?? -1;
                }
            } catch (Exception $e) {
                $this->log->error('Ahrefs Domain Rating Error: ' . $e->getMessage());
            }**/

            try {
                // Organic and Paid traffic for the last 30 days.
                $date = gmdate('Y-m-d', strtotime('-30 days'));
                $response = $client->request('GET', self::BASE_URL . '/metrics?date=' . $today . '&output=json&target=' . urlencode($website), $params);
                if (200 === $response->getStatusCode()) {
                    $body             = $response->getBody();
                    $content          = $body->getContents();
                    $json             = json_decode($content);
                    $org_traffic      = $json->metrics->org_traffic;
                    $paid_traffic     = $json->metrics->paid_traffic;
                }
            } catch (Exception $e) {
                $this->log->error('Ahrefs Broken Backlinks Error: ' . $e->getMessage());
            }

            $websiteData[ $website ] = array(
                /**'all_backlinks'    => $all_backlinks ?? '',
                'broken_backlinks' => $broken_backlinks ?? '',
                'domain_rating'    => $domain_rating ?? '',**/
                'org_traffic'      => $org_traffic ?? '',
                'paid_traffic'     => $paid_traffic ?? '',
            );
            //$this->log->debug("[{$index}] {$website}: Backlinks {$websiteData[$website]['all_backlinks']}, Broken Backlinks {$websiteData[$website]['broken_backlinks']}, Domain Rating {$websiteData[$website]['domain_rating']}, Organic Traffic {$websiteData[$website]['org_traffic']}, Paid Traffic {$websiteData[$website]['paid_traffic']}");
            $this->log->debug("[{$index}] {$website}: Organic Traffic {$websiteData[$website]['org_traffic']}, Paid Traffic {$websiteData[$website]['paid_traffic']}");
        }

        return $websiteData;
    }
}
