<?php

namespace App\Modules;

if (! defined('ABSPATH')) {
    die('Access denied.'); // Exit if accessed directly
}

use App\Utils\LogTrait;
use Exception;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

class Spreadsheet
{
    use LogTrait;

    private $googleClient;
    private $spreadSheet_id;
    private $start;
    private $count;
    private $labels;
    private $websiteJSONData = array();

    public function __construct($spreadSheet_id, $start, $count, $logger, $labels = [])
    {
        $this->setLogger($logger);
        $this->spreadSheet_id = $spreadSheet_id;
        $this->start = $start;
        $this->count = $count;
        $this->labels = $labels;
        $this->auth();
    }

    /**
     * Setup GoogleClient and spreadsheets authentication.
     */
    private function auth()
    {
        $this->googleClient = new Client();
        $this->googleClient->setApplicationName("DRA Sales Automation");
        $this->googleClient->setAuthConfig(ABSPATH . 'src/credentials.json');
        $this->googleClient->addScope('https://www.googleapis.com/auth/spreadsheets');
        $this->googleClient->setRedirectUri('https://example.com/oauth2callback'); //Set redirect URI
        $this->googleClient->setAccessType('offline');
        $this->googleClient->setPrompt('select_account consent');

        $tokenPath = ABSPATH . 'src/token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $this->googleClient->setAccessToken($accessToken);
        }

        if ($this->googleClient->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($this->googleClient->getRefreshToken()) {
                $this->googleClient->fetchAccessTokenWithRefreshToken($this->googleClient->getRefreshToken());
            } else {
                // Request authorization from the user.
                if (isset($_GET['code'])) {
                    // Exchange authorization code for an access token.
                    $accessToken = $this->googleClient->fetchAccessTokenWithAuthCode($_GET['code']);
                    $this->googleClient->setAccessToken($accessToken);

                    // Check to see if there was an error.
                    if (array_key_exists('error', $accessToken)) {
                        throw new Exception(join(', ', $accessToken));
                    }
                } else {
                    $authUrl = $this->googleClient->createAuthUrl();
                    printf("Need manual authorization and update token.json with the provided code. Open the following link in your browser:\n%s\n", $authUrl);
                }
            }

            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($this->googleClient->getAccessToken()));
        }
    }

    public function pullURLs()
    {
        $sheets = new Sheets($this->googleClient);

        // Get $count rows starting at $start from the spreadsheet
        // Use batchGet to retrieve the first row (header row) and the rows we want (data rows)
        $ranges = [
            'ranges' => [
                'Test Sheet!A1:Z1',
                'Test Sheet!A' . $this->start . ':Z' . ($this->start + $this->count - 1),
            ],
        ];
        $response = $sheets->spreadsheets_values->batchGet($this->spreadSheet_id, $ranges);
        $value_ranges = $response->getValueRanges();

        if (empty($value_ranges)) {
            return [];
        }

        // Headlines are the first row of the spreadsheet.
        $this->labels = $value_ranges[0]->getValues()[0];

        // Data rows are the rest of the rows.
        $rows = $value_ranges[1]->getValues();

        if (empty($rows)) {
            return [];
        }

        return $rows;
    }

    public function getLabels()
    {
        return $this->labels;
    }

    public function addWebsiteData($data)
    {
        //If the websiteJSONData object has already been populated
        if (!empty($this->websiteJSONData)) {
            // Loop over the websiteJSONData object and update values with the new data
            foreach ($this->websiteJSONData as $url => $value) {
                if (isset($data[$url])) {
                    // Merge the websiteJSONData data with the passed data object
                    $data[$url] = array_merge($value, $data[$url]);
                }
            }
        }
        $this->websiteJSONData = array_merge($this->websiteJSONData, $data);
    }

    public function syncWebsiteData()
    {
        $sheetData = [];
        $range_start = $this->start;

        // Include headline labels if first batch of data
        if ($range_start === 2) {
            // Append columns that is not in the sheet but in the data
            $first_website_data =  reset($this->websiteJSONData);
            foreach ($first_website_data as $label => $value) {
                if (!in_array($label, $this->labels)) {
                    array_push($this->labels, $label);
                }
            }

            $sheetData = array($this->labels);
            $range_start = 1;
        }

        //Set order of the data using the existing spreadsheet columns
        $labels = array();
        foreach ($this->labels as $orderNum => $label) {
            $labels[$label] = $orderNum;
        }
        asort($labels);
        array_shift($labels);//We don't need the url label for processing. url is manually inserted, so we don't want to include it in the automated looping.

        foreach ($this->websiteJSONData as $website => $dataset) {
            $san_array = array($website);
            foreach ($labels as $label => $order) {
                if (isset($dataset[$label])) {
                    array_push($san_array, $dataset[$label]);
                } else {
                    array_push($san_array, "");
                }
            }
            array_push($sheetData, $san_array);
        }
        $sheets = new Sheets($this->googleClient);
        $body = new ValueRange([
            'values' => $sheetData
        ]);
        $params = [
            'valueInputOption' => 'USER_ENTERED'
        ];

        $this->log->debug("Updating spreadsheet with new data " . count($sheetData) . " rows");

        // Update the spreadsheet with the new data starting $start rows down
        $range = 'Test Sheet!A' . $range_start . ':Z';
        $result = $sheets->spreadsheets_values->update($this->spreadSheet_id, $range, $body, $params);

        if (isset($result->updatedRows)) {
            $this->log->debug("{$result->updatedRows} rows x {$result->updatedColumns} columns updated. Range updated: {$result->updatedRange}");
        }
    }
}
