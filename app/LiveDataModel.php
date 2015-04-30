<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Yaml\Parser;

class LiveDataModel extends Model
{

    private $delay;
    private $profiles;

    function __construct()
    {
        date_default_timezone_set('America/Chicago');
        $hour = (int)date('H');
        if ($hour > 7 and $hour <= 21):
            $this->delay = env('SHORTCACHETIME');
        else:
            $this->delay = env('LONGCACHETIME');
        endif;
        $yp = new Parser();
        $yml = file_get_contents(app_path() . '/MyFiles/profiles.yml');
        $this->profiles = $yp->parse($yml);
    }

    public function getData()
    {
        $cacheFile = storage_path() . '/' . env('CACHEFILE');
        if (file_exists($cacheFile) && ((time() - filemtime($cacheFile)) < $this->delay)) {
            return file_get_contents($cacheFile);
        }
        $loadedData = $this->getLiveData();
        file_put_contents($cacheFile, json_encode($loadedData));
        $this->imageMap(0, 0, $loadedData['countries']);
        return json_encode($loadedData);
    }

    private function getLiveData()
    {
// I would like to retrieve the pageTitle + URL sorted by most visitors
// More dimensions and metrics you can find in the Reference guide:
// https://developers.google.com/analytics/devguides/reporting/realtime/dimsmets/pagetracking
        $begin = microtime(true);
        $optParams = array(
            'dimensions' => 'rt:country,rt:pageTitle, rt:pagePath',
            'max-results' => 200,
            'sort' => '-rt:activeUsers' // the minus changes sorting from highest to lowest
        );
        define('COUNTRY', 0);
//define('REGION',1);
//define('CITY',2);
        define('PAGETITLE', 1);
        define('PAGEURL', 2);
        define('VISITORS', 3);

        $service = $this->getGoogleService();

        $pages = array();
        $activeUser = array();
        $i = 0;

        $current = array();
        $siteTotals = array();
        $grandTotal = 0;
        foreach ($this->profiles as $site => $account) {
            // I am using the website for linking the results
            // and add a website icon
            $str = explode('.', $site);
            $slug = $str[0];

            // defaults
            $userCount = 0;
            $rows = array();
            $result = false;

            // finally connecting to google api
            try {
                $result = $service->data_realtime->get(
                    'ga:' . $account,
                    'rt:activeUsers',
                    $optParams);
                // Success.
            } catch (apiServiceException $e) {
                // Handle API service exceptions.
                $error = $e->getMessage();
            }

            if ($result) {
                // actual count of active users on the website
                if ($result->totalsForAllResults) {
                    $userCount = $result->totalsForAllResults['rt:activeUsers'];
                }

                // result set of pages visited right now
                if ($result->rows) {
                    $rows = $result->rows;
                }
            }

            // build up pages list array with needed parameters like:
            // count, site, title, url, slug for image icon
            foreach ($rows as $row) {

                //  $location = $row[CITY]=='zz'? '':$row[CITY].', ';
                //  $location .=$row[REGION]=='zz'? '':$row[REGION].', ';
                $location = $row[COUNTRY] == 'zz' ? '' : $row[COUNTRY] . ', ';
                $location = trim($location, ' ,');
                $page = $row[PAGETITLE];
                $visitors = $row[VISITORS];
                $current[] = array('site' => $site, 'location' => $location, 'page' => $page, 'visitors' => $visitors);
            }

            // user active right now
            if ($userCount > 0) $siteTotals[$site] = $userCount;
            $grandTotal += $userCount;
        }
        $countries = array();
        $pages = array();
        foreach ($current as $hit):
            if (isset($countries[$hit['location']])):
                $countries[$hit['location']] += $hit['visitors'];
            else:
                $countries[$hit['location']] = $hit['visitors'];
            endif;
            $path = $hit['site'] . ' ' . $hit['page'];
            if (isset($pages[$path])):
                $pages[$path] += $hit['visitors'];
            else:
                $pages[$path] = $hit['visitors'];
            endif;
        endforeach;

        arsort($pages);
        arsort($countries);
        arsort($siteTotals);
        $return = array('total' => $grandTotal,
            'delay' => $this->delay,
            'sites' => $siteTotals,
            'countries' => $countries,
            'pages' => $pages,
            'hits' => $current,
            'time' => date('Y-m-d H:i:s'),
            'elapsed' => (microtime(true)-$begin)
        );
        return $return;
    }

    private function getGoogleService()
    {
//        echo __DIR__;die;
        require(__DIR__ . '/../vendor/google/apiclient/src/Google/Client.php');
//        require(__DIR__.'/../vendor/google/apiclient/src/Google/Service/Books.php');

        $privateKey = file_get_contents(__DIR__ . '/' . env('REAL_TIME_ANALYTICS_CERT'));
        $serviceAccountName = env('GOOGLE_SERVICE_ACCOUNT_NAME');
        $clientid = env('GOOGLE_CLIENT_ID');
        $scope = 'https://www.googleapis.com/auth/analytics.readonly';
        $accessToken = file_get_contents(app_path() . '/' . env('CLIENTS_SECRET'));

        $credentials = new \Google_Auth_AssertionCredentials($serviceAccountName, array($scope), $privateKey);

        try {
            $client = new \Google_Client();
            $client->setApplicationName("realtime");
            $client->addScope($scope);
            session_start();
            if (isset($_SESSION['token'])) {
                $client->setAccessToken($_SESSION['token']);
            }
            //$client->setAccessToken($accessToken);
            $client->setClientId($clientid);
            $client->setAssertionCredentials($credentials);
        } catch (Exception $e) {
            echo $e->getMessage();
            die;
        }
// the access token is part of the client_secrets.json
        if ($client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion($credentials);
        }
        $service = new \Google_Service_Analytics($client);
        return $service;
    }

    private function imageMap($lat, $long, $countryData)
    {
        $file = public_path() . '/' . env('MAP_IMAGE_FILE');
        $u = env('MAP_OPTIONS');
        $u .= "&center={$lat},{$long}";

        $markers = '';
        foreach ($countryData as $country => $visits):
            $markers .= '&markers=' . urlencode("color:blue | label:{$visits}|{$country}");
        endforeach;
        $u .= '&zoom=1&size=1100x400' . $markers;
        $u .= '&key=' . env('STATICAPIKEY');
        file_put_contents($file, file_get_contents($u));
        return;
    }

}
