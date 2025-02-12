<?php

namespace App\Modules;

if (! defined('ABSPATH')) {
    die('Access denied.'); // Exit if accessed directly
}

use App\Utils\LogTrait;
use Exception;
use Google\Client;
use Google\Service\SearchConsole as GoogleSearchConsole;
use Google\Service\SearchConsole\RunMobileFriendlyTestRequest;

class SearchConsole
{
    use LogTrait;

    public function getData($websites)
    {
        global $google_api_key;
        $websiteData = [];

        $client = new Client();
        $client->setApplicationName("DRA Sales Automation - Search Console");
        $client->setDeveloperKey($google_api_key);

        //urlTestingTools_mobileFriendlyTest
        $service = new GoogleSearchConsole($client);

        foreach ($websites as $index => $website) {
            try {
                // Construct the request object
                $request = new RunMobileFriendlyTestRequest();
                $request->setUrl($website);

                // Create an instance of the UrlTestingToolsMobileFriendlyTest
                $mobileFriendlyTest = $service->urlTestingTools_mobileFriendlyTest;

                // Run the mobile-friendly test
                $result = $mobileFriendlyTest->run($request);

                // Process the result
                $mobileFriendliness = ($result->mobileFriendliness == "MOBILE_FRIENDLY") ? "Passed" : "Failed";

                // Parse Inividual Mobile Issues
                // $mobileFriendlyIssues = $result->mobileFriendlyIssues;
                // if ($mobileFriendlyIssues) {
                //     $issues = array();
                //     foreach($mobileFriendlyIssues as $mobileFriendlyIssue) {
                //         $issues[] = $mobileFriendlyIssue->rule;
                //     }
                // }
                // implode(", ", $issues);
                // unset($issues);
            } catch (Exception $e) {
                $this->log->error("Search Console ERROR: " . $e->getMessage());
            }

            $websiteData[$website] = [
                'mobile_friendly_test' => isset($mobileFriendliness) ?  $mobileFriendliness : "",
            ];

            $this->log->debug("[{$index}] {$website}: Mobile Friendly Test {$websiteData[$website]['mobile_friendly_test']}");

            unset($mobileFriendliness);
        }

        return $websiteData;
    }
}
