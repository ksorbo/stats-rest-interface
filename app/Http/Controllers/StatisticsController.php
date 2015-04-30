<?php namespace App\Http\Controllers;

use App\Http\Requests;
use App\StatsModel;
use App\LiveDataModel;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class StatisticsController extends Controller
{

    public function __construct()
    {
        date_default_timezone_set('America/Chicago');
    }

   public function testAnalytics($func, $start = null, $end = null)
    {

        echo '<h1>Testing Analytics Function</h1>';
        $analytics = new \networkAnalytics();
        $stats = new StatsModel();
        list($start, $end) = $analytics->massageDates($start, $end);
        switch ($func) {
            case 'inquirercountries':
                $ret = $analytics->getInquirerCountries('2015-04-01','2015-04-30',20);
                break;
            case 'inquirermap':
                $ret = $analytics->getInquirerCountries('2015-04-01','2015-04-30',20);
                $file = public_path()."/test.jpg";
                pointMap($file,$ret);
                header('Content-type: image/jpeg ');
                echo file_get_contents($file);
                die;
                break;
            case 'inquirers':
                $ret = $stats->getItems('inquirers', 20, $start, $end, $order = 'desc');
                break;
            case 'stats':
                $ret = ($analytics->getStatsTotals($start, $end));
                break;
            case 'fullstats':
                $ret = ($this->fullstats($start, $end));
                break;
            case 'countriesofresponse':
                $ret = $stats->getItems('countriesofresponse',300,'2008-01-01',date('Y-m-d'));
                break;
            case 'visitssites':
            case 'visitsites':
                $ret = ($analytics->getVisitsBySite($start, $end));
                break;
            case 'livedata':
                $live = new LiveDataModel();
                $ret = $live->getData();
        }
        dd($ret);
    }

    public function inquirermap($days=1,$toShow=20,$startdate=null,$enddate=null){
        $analytics = new \networkAnalytics();
        if(!($startdate && $enddate)) {
            $enddate = date('Y-m-d', strtotime('yesterday'));
            $startdate = date('Y-m-d', strtotime('yesterday') - (($days - 1) * 24 * 60 * 60));
        }
        $fileName = $analytics->createInquirerMap($startdate,$enddate,$toShow);
        header('Content-Type: image/jpeg ');
        header('Content-Length:' .filesize($fileName));
        header('Cache-control: max-age='.(60*60*24));
        header('Expires: '.gmdate(DATE_RFC1123,time()+60*60*24));
        readfile($fileName);
        exit();

    }

    public function fullstats($startdate = 'all', $enddate = null)
    {
        $dates = dateWhereClause($startdate, $enddate, 'Y-m-d');

        $cacheFileName = storage_path() . "/fullstats{$dates[2][0]}-{$dates[2][1]}.json";
        if (file_exists($cacheFileName)) {
            $json = file_get_contents($cacheFileName);
            return $json;
        }
        $stats = new StatsModel();
        $analytics = new \networkAnalytics();
        $totals = $analytics->getStatsTotals($startdate, $enddate);
        $inquirerSites = $stats->getItems('sitesofinquirers', 300, $startdate, $enddate);
        $inquirerCountries = $stats->getItems('countriesofinquirers', 300, $startdate, $enddate);
        $responseCountries = $stats->getItems('countriesofresponse', 300, $startdate, $enddate);
        $responseSites = $stats->getItems('sitesofresponses', 300, $startdate, $enddate);

        $h = $analytics->getTotalCountryRegionVisits($startdate, $enddate, false);
        $visitorCountries = array();
        foreach ($h['countryregion'] as $country) {
            $visitorCountries[] = array('country' => $country['country'], 'count' => $country['total']);
        }

        $visitorSites = array();
        $h = $analytics->getVisitsBySite($startdate, $enddate);
        foreach ($h['sites'] as $site) {
            $visitorSites[] = array('hostname' => $site[0], 'count' => $site[1]);
        }

        $ret = $totals;
        $ret['inquirersites'] = $inquirerSites;
        $ret['inquirercountries'] = $inquirerCountries;
        $ret['responsecountries'] = $responseCountries;
        $ret['responsesites'] = $responseSites;
        $ret['visitscountries'] = $visitorCountries;
        $ret['visitssites'] = $visitorSites;
//        echo '<pre>';print_r($ret);die;
        file_put_contents($cacheFileName, json_encode($ret));
        return json_encode($ret);
    }

    /**
     * @param $type - responses, inquirers, salvations,recommitments,prayer,questions,subscriptions
     * @param $count - how many you want
     * @param string $startdate - beginning date or  all, thisyear, lastyear, lastmonth
     * @param $enddate - ending date
     * @param string $order - asc, desc random
     * @return array
     */
    public function items($type, $order = 'desc', $count, $startdate = 'all', $enddate = null)
    {
        $stats = new StatsModel();
        $ret = $stats->getItems($type, $count, $startdate, $enddate, $order);

        return json_encode($ret);
    }

    public function stats($type, $period1 = 'all', $period2 = null)
    {
        $stats = new StatsModel();

//      list($type,$count,$daterange) = $stats->getStats($type,$period1,$period2);
        $ret = $stats->getStats($type, $period1, $period2);
//        dd($ret);
        return "Stat type: {$ret['type']}, Count: {$ret['count']}, Date Range: {$ret['daterange']}";
    }

    public function totals($period1 = 'all', $period2 = null)
    {
//        $stats = new StatsModel();
        $analytics = new \networkAnalytics();
        $ret = $analytics->getStatsTotals($period1, $period2);
        return $ret;

    }

    public function news($number = 5)
    {
        $articles = array(
            array('title' => '"Connector" Mobile App Released',
                'newsdate' => '2015-04-04',
                'content' => 'Network211 has released a beta version of a new mobile app called "Connector." This app is designed to provide up to date information about the results of ' .
                    'Project100Million, the evangelism ministry of Network211. Initial beta testing is currently underway. When the app is released, it will be available for ' .
                    ' Android, iOS and Windows phones.'),
            array('title' => 'Torquiss ire1', 'newsdate' => '2015-01-01', 'content' => 'Bromium cantares, tanquam peritus canis. Brevis, fortis exemplars superbe attrahendam de varius, castus genetrix. Tata lotus frondator est.'),
            array('title' => 'Be inner1', 'newsdate' => '2015-02-01', 'content' => 'The affirmation of your stigmas will balance harmoniously when you study that extend is the monkey. Chaos of control will silently witness a magical ego.If you rise or ease with a shining core, solitude loves you.'),
            array('title' => 'With raspberries drink ketchup1', 'newsdate' => '2015-03-01', 'content' => 'All children like scraped asparagus in sweet chili sauce and parsley. Meatballs taste best with maple syrup and lots of celery. Brush each side of the watermelon with twelve teaspoons of eggs.'),
            array('title' => 'Beauty, adventure, and fortune1', 'newsdate' => '2015-04-01', 'content' => 'The scrawny shipmate smartly fights the comrade. Life ho! fight to be breaked. Jolly, yellow fever! Avast, rough life! Golly gosh! Pieces o\' horror are forever cold.'),
            array('title' => 'Oddly control a star1', 'newsdate' => '2015-04-03', 'content' => 'Transporters warp with understanding! Sun of a real faith, examine the collision course! Why does the collective yell?Particles are the transformators of the final vision. The rumour is a small green people. This adventure has only been imitated by a most unusual nanomachine.'),
        );
        $number = $number > count($articles) ? count($articles) : $number;
        $ret = array();
        for ($i = 0; $i < $number; $i++) {
            $ret[] = $articles[$i];
        }
        return json_encode($ret);


    }


}
