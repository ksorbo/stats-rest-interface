<?php

error_reporting(E_ALL);
include('GoogleAnalyticsAPI.class.php');
use Symfony\Component\Yaml\Parser;

class networkAnalytics
{
    public $accessToken = null;
    public $ga;
    public $queryLog = false;
    public $profiles;
    public $cacheFolder;
    public $assetsFolder ;

    public function __construct()
    {
        try {
            $this->ga = new GoogleAnalyticsAPI('service');
            $this->ga->auth->setClientId(env('GOOGLE_CLIENT_ID')); // From the APIs console
            $this->ga->auth->setEmail(env('GOOGLE_CLIENT_EMAIL')); // From the APIs console
            $this->ga->auth->setPrivateKey(app_path() . '/' . env('GOOGLE_CLIENT_PRIVATE_KEY_URI'));
            $auth = $this->ga->auth->getAccessToken();
        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }

        if ($auth['http_code'] == 200) {
            $this->accessToken = $auth['access_token'];
            // $tokenExpires = $auth['expires_in'];
            // $tokenCreated = time();
        } else {
            return null;
        }
        $this->getSites();
        $this->ga->setAccessToken($this->accessToken);
        $this->cacheFolder = storage_path() . '/';
        $this->assetsFolder = public_path() . '/';
    }

    public function setQueryLog($setting = true)
    {
        if ($setting) {
            $this->ga->queryLog = storage_path() . '/queries.log';
        } else {
            $this->ga->queryLog = '';
        }
    }

    public function getStatsTotals($start=null, $end=null)
    {
        $begin =microtime(true);
        list($startdate, $enddate) = $this->massageDates($start, $end);
        $cacheFileName = $this->cacheFolder . "analytics-statstotals-$startdate-$enddate.json";
        if (file_exists($cacheFileName)) {
//            echo time() - filemtime($cacheFileName) . '<br>';
            if (time() - filemtime($cacheFileName) < env('STATS_TOTALS_CACHE_SECONDS')) {
                return json_decode(file_get_contents($cacheFileName), true);
            }
        }
        $dbconn = pg_connect(env('CONNECTIONSDB')) or die('Could not connect: ' . pg_last_error());
        $inqSQL = "select
            sum (case decisiontoday when 1 then 1  else 0 end) as Salvations ,
            sum (case decisiontoday when 2 then 1  else 0 end) as Recommitments,
            sum (case decisiontoday when 3 then 1  else 0 end) as Questions,
            sum (case decisiontoday when 4 then 1  else 0 end) as Prayers,
            sum (case decisiontoday when 6 then 1 else 0 end) as Subscriptions,
            sum(1) as TotalInquirers
            from userinquirerapplication
            where decisiontoday >0 and datesubmitted >= '$startdate'::timestamp::date
                and datesubmitted <= '$enddate'::timestamp::date ";
        $result = pg_query($dbconn, $inqSQL) or die('Could not query: ' . pg_last_error());
        $inquirers = pg_fetch_all($result);
        $resSQL = "select count(id) as count from visitors  where  visitationtime >= '$startdate'::timestamp::date
                and visitationtime <= '$enddate'::timestamp::date ";
        $respResult = pg_query($dbconn, $resSQL) or die('Could not query: ' . pg_last_error());
        $responses = pg_fetch_all($respResult);
        $siteTotals = $this->getVisitsBySite($startdate, $enddate, env('STATS_TOTALS_CACHE_SECONDS'));
        $ret = array('perioddescription'=>"From $startdate to $enddate",
            'visits'=>$siteTotals['total'],
            'responses'=>(int)$responses[0]['count'],
            'inquirers'=>(int)$inquirers[0]['totalinquirers'],
            'salvations'=>(int)$inquirers[0]['salvations'],
            'questions'=>(int)$inquirers[0]['questions'],
            'recommitments'=>(int)$inquirers[0]['recommitments'],
            'prayerrequests'=>(int)$inquirers[0]['prayers'],
            'subscriptions'=>(int)$inquirers[0]['subscriptions'],
            'time'=>date('Y-m-d H:i:s'),
            'elapsed'=>microtime(true)-$begin);
        file_put_contents($cacheFileName,json_encode($ret));
        return $ret;
    }

    /**
     * @param string $start
     * @param string $end
     * @param int $items
     * @return string
     */
    public function createInquirerMap($start,$end,$items){
        list($start, $end) = $this->massageDates($start, $end);
        $file = $this->assetsFolder ."/inquirers-map-$start-$end-$items.jpg";
//        echo $file.'<br>';
        if(file_exists($file)) return $file;
        $ret = $this->getInquirerCountries($start,$end,$items);
//        echo '<pre>';print_r($ret);die;
        staticMapWithMarkers($file,$ret);
        cropit($file,85,70,75,80);
        return $file;
    }

    public function getInquireDetailByYearMonth($start, $end)
    {
        list($startdate, $enddate) = $this->massageDates($start, $end);
        $dbconn = pg_connect(env(CONNECTIONSDB)) or die('Could not connect: ' . pg_last_error());
        $inqSQL = "select
            to_char(datesubmitted,'YYYYMM') as yrMth,
            sum (case decisiontoday when 1 then 1  else 0 end) as Salvations ,
            sum (case decisiontoday when 2 then 1  else 0 end) as Recommitments,
            sum (case decisiontoday when 3 then 1  else 0 end) as Questions,
            sum (case decisiontoday when 4 then 1  else 0 end) as Prayers,
            sum(1) as TotalInquirers
            from userinquirerapplication
            where decisiontoday >0 and datesubmitted >= '$startdate'::timestamp::date
                and datesubmitted <= '$enddate'::timestamp::date
            group by yrMth
            order by yrMth";

        $result = pg_query($dbconn, $inqSQL) or die('Could not query: ' . pg_last_error());
        $inquirers = pg_fetch_all($result);
        //  echo '<pre>';print_r($inquirers);die;
        $c = array();
        //echo '<pre>';print_r($inquirers);die;
        foreach ($inquirers as $i):
            $c[$i['yrmth']] = array('salvations' => $i['salvations'],
                'recommitments' => $i['recommitments'],
                'questions' => $i['questions'],
                'prayers' => $i['prayers'],
                'total' => $i['totalinquirers']
            );
        endforeach;
        // echo '<pre>';print_r($c);die;
        $yrMonth = self::buildYearMonth($startdate, $enddate);
        // create an entry in the case where the database didn't return any data.
        foreach ($yrMonth as $key):
            if (!isset($c[$key])):
                $c[$key] = array('salvations' => 0, 'recommitments' => 0, 'questions' => 0, 'prayers' => 0, 'total' => 0);
            endif;
        endforeach;
        return $c;
    }

    public function getInquirerCountries($start,$end,$limit = 50){
//        $begin =microtime(true);
        list($startdate, $enddate) = $this->massageDates($start, $end);
        $cacheFileName = $this->cacheFolder . "analytics-inquirercountries-$startdate-$enddate-$limit.json";
        if(file_exists($cacheFileName)) return json_decode(file_get_contents($cacheFileName,true));
        $dbconn = pg_connect(env('CONNECTIONSDB')) or die('Could not connect: ' . pg_last_error());
        $sql = "select glcountryname as country,count(glcountryname) as count  from userinquirerextension uie join userinquirerapplication uia on uia.userid = uie.userid
              where glcountryname <> 'Not found' and glcountryname <> '' and uia.datesubmitted::timestamp::date >= '$start' and uia.datesubmitted::timestamp::date <= '$end'
              group by glcountryname order by count(glcountryname) desc limit $limit";
        $result = pg_query($dbconn, $sql) or die('Could not query: ' . pg_last_error());
        $countries = pg_fetch_all($result);
        $ret = array();
        foreach($countries as $country){
            $ret[] = trim($country['country']);
        }
        file_put_contents($cacheFileName,json_encode($ret));
        return $ret;
    }
    static public function buildYearMonth($start, $end)
    {
        $startDate = strtotime($start);
        $endDate = strtotime($end);

        $currentDate = $startDate;
        $ret = array();
        while ($currentDate <= $endDate) {
            $ret[] = date('Ym', $currentDate);
            $currentDate = strtotime(date('Y/m/01/', $currentDate) . ' +1 month');
        }
        return $ret;
    }

    public function getRespInqByYearMonth($start, $end)
    {
        $startdate = date('Y-m-d', strtotime($start));
        $enddate = date('Y-m-d', strtotime($end));
        $dbconn = pg_connect(env('CONNECTIONSDB')) or die('Could not connect: ' . pg_last_error());
        $inqSQL = "select count(*),date_part('year',datesubmitted) as yr,date_part('month',datesubmitted) as mth from userinquirerapplication
                where decisiontoday>=1 and decisiontoday<=4
                and datesubmitted >= '$startdate'::timestamp::date
                and datesubmitted <= '$enddate'::timestamp::date
                group by yr,mth";
        $respSQL = "select count(*),date_part('year',visitationtime) as yr,  date_part('month',visitationtime) as mth from visitors
                where visitationtime >= '$startdate'::timestamp::date and visitationtime <= '$enddate'::timestamp::date
                group by yr,mth";
        $result = pg_query($dbconn, $inqSQL) or die('Could not query: ' . pg_last_error());
        $inquirers = pg_fetch_all($result);
        $result = pg_query($dbconn, $respSQL) or die('Could not query: ' . pg_last_error());
        $responses = pg_fetch_all($result);
        //  echo '<pre>';print_r($inquirers);die;
        $c = array();
        foreach ($responses as $r):
            $key = $r['yr'] . str_pad($r['mth'], 2, '0', STR_PAD_LEFT);
            if (!isset($c[$key])):
                $c[$key] = array('responses' => $r['count'], 'inquirers' => 0);
            else:
                $c[$key]['responses'] = $r['count'];
            endif;
        endforeach;
        foreach ($inquirers as $r):
            $key = $r['yr'] . str_pad($r['mth'], 2, '0', STR_PAD_LEFT);
            if (!isset($c[$key])):
                $c[$key] = array('responses' => 0, 'inquirers' => $r['count']);
            else:
                $c[$key]['inquirers'] = $r['count'];
            endif;
        endforeach;
        return $c;
    }

    private function getSites()
    {
        $yp = new Parser();
        $yml = file_get_contents(app_path() . '/MyFiles/profiles.yml');
        $this->profiles = $yp->parse($yml);
    }

    private function getURLs()
    { // returns a list of sites in lowercase and alpha order
        $sites = $this->profiles;
        $aSites = array();
        foreach ($sites as $s):
            $aSites[] = strtolower($s[0]);
        endforeach;
        ksort($aSites);
        return $aSites;

    }

    /**
     * Gets visits from Google Analytics for all sites in Network's profile list
     * Returns in an array(site,profileid,total,start_date,end_date)
     *
     * @param $start date to star
     * @param $end date to end
     * @return array array of stats
     */
    public function getTotalVisits($start, $end)
    {
        // include(ANALYTICS_PHP);  // all profiles as $profiles

        $this->ga->setAccessToken($this->accessToken);

        // Set the default params. For example the start/end dates and max-results
        $defaults = array(
            'start-date' => date('Y-m-d', strtotime($start)),
            'end-date' => date('Y-m-d', strtotime($end)),
        );

        $this->ga->setDefaultQueryParams($defaults);

        $ret = array();
        $totalVisitors = 0;
        foreach ($this->profiles as $profile):
            $this->ga->setAccountId('ga:' . $profile[1]);
            $visits = $this->ga->getVisitsByDate();
            $totalVisitors += $visits['totalsForAllResults']['ga:visits'];
            $ret[] = array('site' => $profile[0], 'profileid' => $profile[1], 'total' => $visits['totalsForAllResults']['ga:visits'], 'start_date' => date('Y-m-d', strtotime($start)), 'end_date' => date('Y-m-d', strtotime($end)));

            // echo "{$profile[0]} {$visits['totalsForAllResults']['ga:visits']} $el<br>";
        endforeach;
        return array('totalvisits' => $totalVisitors, 'sites' => $ret);
    }

    public function getVisitsBySite($start = null, $end = null, $checkRefresh = false)
    {
        $begin = microtime(true);

        list($start, $end) = $this->massageDates($start, $end);

        $cacheFileName = $this->cacheFolder . "visitorsites-$start$end.json";
        if (file_exists($cacheFileName)) {
            if (!$checkRefresh || (time()-filemtime($cacheFileName) < env('STATS_TOTALS_CACHE_SECONDS'))) {
                $json = file_get_contents($cacheFileName);
                return json_decode($json, true);
            }
        }
        $this->ga->setAccessToken($this->accessToken);
        $defaults = array(
            'start-date' => date('Y-m-d', strtotime($start)),
            'end-date' => date('Y-m-d', strtotime($end)),
        );
        $this->ga->setDefaultQueryParams($defaults);
        $totals = array();

        $total = 0;
        foreach ($this->profiles as $site => $profileid):
            $this->ga->setAccountId('ga:' . $profileid);

            $visits = $this->ga->getVisitsByYear();
            $cnt = (int)$visits['totalsForAllResults']['ga:visits'];
            if ($cnt > 0) $totals[] = array($site, $cnt);
            $total += $cnt;
        endforeach;
        usort($totals, function ($a, $b) {
            return $b[1] - $a[1];
        });
        $ret = array('total' => $total, 'sites' => $totals, 'start_date' => $start, 'end_date' => $end, 'checked_at' => date('Y-m-d H:i:s'), 'elapsed' => (microtime(true) - $begin));
        file_put_contents($cacheFileName, json_encode($ret));
        return $ret;
    }

    public function getSiteVisitsByYearMonth($start, $end)
    {
        //include(ANALYTICS_PHP);  // all profiles as $profiles

        $this->ga->setAccessToken($this->accessToken);
        $defaults = array(
            'start-date' => date('Y-m-d', strtotime($start)),
            'end-date' => date('Y-m-d', strtotime($end)),
        );
        $this->ga->setDefaultQueryParams($defaults);

        $totalVisitors = 0;
        $totals = array();
        $aSite = array();
        $sites = $this->getURLs();
        foreach ($sites as $s):
            $aSite[$s] = 0;
        endforeach;
        //  print_r($aSite);
        //  print_r($aSite);die;
        foreach ($this->profiles as $profile):
            $this->ga->setAccountId('ga:' . $profile[1]);
            $visits = $this->ga->getVisitsByYearMonth();

            if ($visits['totalsForAllResults']['ga:visits'] > 0):
                foreach ($visits['rows'] as $row):
                    $key = $row[0] . $row[1];
                    if (!isset($totals[$key])):
                        $totals[$key] = $aSite;
                        // print_r($totals[$key]);die;
                    endif;
//echo $profile[0].' '.strtolower($profile[0]).'<br>';
                    $totals[$key][strtolower($profile[0])] = $row[2];

                endforeach;

                $totalVisitors += $visits['totalsForAllResults']['ga:visits'];
            endif;
        endforeach;
        //  echo '<pre>';print_r($totals);die;
        $c = array();
        foreach ($totals as $key => $t):
            if (array_sum($t) > 0):
                $c[$key] = $t;
            endif;
        endforeach;
        return array('totalvisits' => $totalVisitors, 'yearmonth' => $c);
    }

    public function getAllVisitsByYearMonth($start, $end)
    {
        $this->ga->setAccessToken($this->accessToken);
        $defaults = array(
            'start-date' => date('Y-m-d', strtotime($start)),
            'end-date' => date('Y-m-d', strtotime($end)),
        );
        $this->ga->setDefaultQueryParams($defaults);

        $totalVisitors = 0;
        $totals = array();
        foreach ($this->profiles as $profile):
            $this->ga->setAccountId('ga:' . $profile[1]);
            $visits = $this->ga->getVisitsByYearMonth();
            if ($visits['totalsForAllResults']['ga:visits'] > 0):
                foreach ($visits['rows'] as $row):
                    $cr = $row[0] . $row[1];
                    if (!isset($totals[$cr])):
                        $totals[$cr] = $row[2];
                    else:
                        $totals[$cr] += $row[2];
                    endif;
                endforeach;

                $totalVisitors += $visits['totalsForAllResults']['ga:visits'];
            endif;
        endforeach;

        $c = array();
        foreach ($totals as $cr => $t):
            $a = explode('^', $cr);
            $c[] = array('yearmonth' => $a[0], 'total' => $t, 'start_date' => date('Y-m-d', strtotime($start)), 'end_date' => date('Y-m-d', strtotime($end)));
        endforeach;
        return array('totalvisits' => $totalVisitors, 'yearmonth' => $c);
    }

    /**
     * @param $start - starting date
     * @param $end - ending date
     * @param bool $includeRegion - should the region be included
     * @param $countryFilter - limit to certain countries?
     * @return array
     */
    public function getTotalCountryRegionVisits($start = null, $end = null, $includeRegion = false, $countryFilter = '')
    {
        //ga:country==United States
        //include(ANALYTICS_PHP);  // all profiles as $profiles
        list($start, $end) = $this->massageDates($start, $end);

        $cacheFileName = $this->cacheFolder . "countrydata-$start$end" . ($includeRegion ? 'Regions' : 'NoRegions') . $countryFilter . '.json';
        if (file_exists($cacheFileName)) {
            $json = file_get_contents($cacheFileName);
            return json_decode($json, true);
        }
        $begin = microtime(true);
        $this->ga->setAccessToken($this->accessToken);
        $defaults = array(
            'start-date' => $start,
            'end-date' => $end,
        );
        if ($countryFilter) $defaults['filter'] = $countryFilter;
        $this->ga->setDefaultQueryParams($defaults);

        $totalVisitors = 0;
        $totals = array();
        foreach ($this->profiles as $site => $profile):
            $this->ga->setAccountId('ga:' . $profile);
            if ($includeRegion) {
                $visits = $this->ga->getVisitsByCountryRegion();
            } else {
                $visits = $this->ga->getVisitsByCountries();
            }
            if ($visits['totalsForAllResults']['ga:visits'] > 0):
                foreach ($visits['rows'] as $row):
                    if ($includeRegion) {
                        $cr = $row[0] . '^' . $row[1];
                        if (!isset($totals[$cr])):
                            $totals[$cr] = $row[2];
                        else:
                            $totals[$cr] += $row[2];
                        endif;
                    } else {
                        $cr = $row[0];
                        if (!isset($totals[$cr])):
                            $totals[$cr] = $row[1];
                        else:
                            $totals[$cr] += $row[1];
                        endif;
                    }
                endforeach;

                $totalVisitors += $visits['totalsForAllResults']['ga:visits'];
            endif;
        endforeach;

        $c = array();
        foreach ($totals as $cr => $t):
            if ($includeRegion) {
                $a = explode('^', $cr);
                $c[] = array('country' => $a[0], 'region' => $a[1], 'total' => $t, 'start_date' => date('Y-m-d', strtotime($start)), 'end_date' => date('Y-m-d', strtotime($end)));
            } else {
                $c[] = array('country' => $cr, 'total' => $t, 'start_date' => date('Y-m-d', strtotime($start)), 'end_date' => date('Y-m-d', strtotime($end)));
            }
        endforeach;
//        echo microtime(true).' '.$begin.' '.(microtime(true)-$begin).'<br>';
        $ret = array('totalvisits' => $totalVisitors, 'countryregion' => $c,
            'start_date' => $start, 'end_date' => $end, 'checked_at' => date('Y-m-d H:i:s'), 'elapsed' => number_format((microtime(true) - $begin), 3));
        file_put_contents($cacheFileName, json_encode($ret));
        return $ret;
    }

    /**
     * @param $start  starting date
     * @param $end  ending date
     * @param array $sites array of sites array(sitename, site number). If empty return all sites
     * @return array
     */
    public function getTotalCountryVisits($start, $end, $sites = array())
    {
        //include(ANALYTICS_PHP);  // all profiles as $profiles
        $siteIds = array();
        $allSites = true;
        foreach ($sites as $id):
            $siteIds[] = $id[1];
            $allSites = false;
        endforeach;

        $this->ga->setAccessToken($this->accessToken);

        // Set the default params. For example the start/end dates and max-results
        $defaults = array(
            'start-date' => date('Y-m-d', strtotime($start)),
            'end-date' => date('Y-m-d', strtotime($end)),
        );
        $t = microtime(true);
        $this->ga->setDefaultQueryParams($defaults);

        $ret = array();
        $totalVisitors = 0;
        $totals = array();
        foreach ($this->profiles as $profile):
            if ($allSites or in_array($profile[1], $siteIds)):
                $this->ga->setAccountId('ga:' . $profile[1]);
                $visits = $this->ga->getVisitsByCountries();
                // echo '<pre>'; print_r($visits);
                if (isset($visits['rows']) && count($visits['rows']) > 0):
                    foreach ($visits['rows'] as $row):
                        if (!isset($totals[$row[0]])):
                            $totals[$row[0]] = $row[1];
                        else:
                            $totals[$row[0]] += $row[1];
                        endif;
                    endforeach;
                    $totalVisitors += $visits['totalsForAllResults']['ga:visits'];
                endif;
                $el = microtime(true) - $t;
            endif;
        endforeach;
        $c = array();
        foreach ($totals as $cntry => $t):
            $c[] = array('country' => $cntry, 'total' => $t, 'start_date' => date('Y-m-d', strtotime($start)), 'end_date' => date('Y-m-d', strtotime($end)));
        endforeach;
        return array('totalvisits' => $totalVisitors, 'countries' => $c);
    }

    public function readAllAnalyticsAccounts()
    {
        $theProfiles = $this->ga->getProfiles();
        $anaSites = array();
        $fp = fopen(storage_path() . '/analytics.csv', 'w');
        foreach ($theProfiles['items'] as $site):
            $anaSites[$site['name']] = array('id' => $site['id'], 'accountid' => $site['accountId'], 'websiteUrl' => $site['websiteUrl'], 'webPropertyId' => $site['webPropertyId']);
            fputcsv($fp, array($site['name'], $site['id'], $site['accountId'], $site['websiteUrl'], $site['webPropertyId']));
        endforeach;

        fclose($fp);
        return $anaSites;
    }

    /**
     * Takes a variety of date formats and returns an array of 2 Y-m-d dates
     * @param $start
     * @param $end
     * @return array
     */
    public function massageDates($start, $end)
    {
        if ($start == 'all') $start = env('CONNECTIONS_START_DATE');
        $start = $start ? $start : env('CONNECTIONS_START_DATE');
        $end = $end ? $end : date('Y-m-d');
        $start = date('Y-m-d', strtotime($start));
        $end = date('Y-m-d', strtotime($end));
        return array($start, $end);
    }

}