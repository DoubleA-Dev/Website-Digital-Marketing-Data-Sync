<?php

namespace App\Modules;

if (! defined('ABSPATH')) {
    die('Access denied.'); // Exit if accessed directly
}

use App\Utils\LogTrait;

class Wappalyzer
{
    use LogTrait;

    public function getData($websites)
    {
        global $wappalyzer_api_key; // Set API key
        $websiteData = []; //Declare Website Data return variable

        // Set the URL of the API endpoint
        // https://www.wappalyzer.com/docs/api/v2/lookup/
        $apiEndpoint = 'https://api.wappalyzer.com/v2/lookup/';

        foreach ($websites as $index => $website) {
            //Initialize website data
            $websiteData[$website] = [
                'GA4' => 'Not Found',
                'GTM' => 'Not Found',
                'Marketing Automation' => 'Not Found',
                'Cookie Compliance Tool' => 'Not Found',
            ];

            // Set the target URL you want to analyze
            $targetUrl = $website;

            // Append the target URL and sets query parameters
            $queryParams = http_build_query(array(
                'urls' => $targetUrl,
                'sets' => 'all',
                // 'live' => true, //Scan websites in real-time. Not super reliable as a few high profile sites time out before a scan can be completed
                'recursive' => 'false', // Index multiple pages. This is turned off because we're only interested on info that can be ascertained during a shallow scan.
                // 'callback_url' => 'https://dra-sales-automa-1682617801118.ue.r.appspot.com/src/modules/wappalyzer/callback.php', //Will require an update function to be built as part of the app, which isn't a priority atm. The callback file itself is removed from source control but check the commits.
            ));

            // Construct the request URL with query parameters
            $requestUrl = $apiEndpoint . '?' . $queryParams;

            // Create a cURL resource
            $curl = curl_init();

            // Set the cURL options
            curl_setopt_array($curl, array(
                CURLOPT_URL => $requestUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array(
                    'x-api-key: ' . $wappalyzer_api_key,
                ),
                CURLOPT_TIMEOUT => 120, // Set the timeout value (in seconds). Wappalyzer has a max timeout of 30 seconds
            ));

            // Send the API request
            $response = curl_exec($curl);

            // Check for cURL errors
            if (curl_errno($curl)) {
                $this->log->error('Wappalyzer Error: ' . $website . ' cURL Error: ' . curl_error($curl));
                $response = false; //fallback to make sure app doesn't process results
            }

            // Check for request timeout
            if (curl_getinfo($curl, CURLINFO_TOTAL_TIME) >= 30) {
                $this->log->error('Wappalyzer Error: ' . $website . ': Request timeout exceeded.');
                $response = false; //fallback to make sure app doesn't process results
            }

            // Close the cURL resource
            curl_close($curl);

            // Handle the API response
            if ($response !== false) {
                $results = json_decode($response, true);

                // Process the results
                foreach ($results as $result) {
                    //If Technologies are in the response
                    if (isset($result['technologies'])) {
                        // Extract the detected technologies
                        $technologies = $result['technologies'];
                        $url = $result['url'];

                        // Output the detected technologies
                        foreach ($technologies as $technology) {
                            $technology_name = $technology['name'];

                            // Find GTM
                            if ($technology_name === 'Google Tag Manager') {
                                $websiteData[$website]['GTM'] = 'GTM Code Found';
                            }

                            // Find GA4
                            if ($technology_name === 'Google Analytics') {
                                foreach ($technology['versions'] as $version) {
                                    if ($version === 'GA4') {
                                        $websiteData[$website]['GA4'] = 'GA4 Code Found';
                                    }
                                }
                            }

                            // Find Marketing Automation and Cookie Compliance Tool
                            foreach ($technology['categories'] as $category) {
                                $category_name = $category['name'];

                                if ($category_name === 'Marketing automation') {
                                    $websiteData[$website]['Marketing Automation'] = ($websiteData[$website]['Marketing Automation'] !== 'Not Found') ? $websiteData[$website]['Marketing Automation'] . ', ' . $technology_name : $technology_name;
                                }

                                if ($category_name === 'Cookie compliance') {
                                    $websiteData[$website]['Cookie Compliance Tool'] = ($websiteData[$website]['Cookie Compliance Tool'] !== 'Not Found') ? $websiteData[$website]['Cookie Compliance Tool'] . ', ' . $technology_name : $technology_name;
                                }
                            }
                        }
                    } else {
                        $errorMessage = 'No technologies detected.';
                        if (is_string($result)) {
                            $errorMessage = $result;
                        } elseif (isset($result['errors'])) {
                            $errorMessage = $result['errors'][0];
                        }
                        $this->log->error('Wappalyzer Error: ' . $website . ' ' . $errorMessage);
                    }
                }
            } else {
                $this->log->error('Wappalyzer Error: ' . $website . ' Failed to fetch API response.');
            }

            $this->log->debug("[{$index}] {$website}: GTM {$websiteData[$website]['GTM']}, GA4 {$websiteData[$website]['GA4']}, Marketing Automation {$websiteData[$website]['Marketing Automation']}, Cookie compliance {$websiteData[$website]['Cookie Compliance Tool']}");
        }

        return $websiteData;
    }
}
