<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class StatsModel extends Model
{

    /**
     * @var connection to the postgres Connections database
     */
    private $connections;

    /**
     * @var \mysqli mysqli connection to the Adwords database on romans
     */
    private $adwords;

    /**
     * @throws Exception
     */
    private function connectToConnections()
    {
        if ($this->connections == null) {
            $this->connections = pg_connect(env('CONNECTIONSDB'));
            if ($this->connection === false) throw new Exception('Failed to connect to Connections database');
        }
    }

    /**
     * @throws Exception if it cannot connect to the adWords database on romans.
     */
    private function connectToAdwords()
    {
        if ($this->adwords == null) {
            $this->adwords = mysqli_connect(env('ADWORDS_HOST'), env('ADWORDS_USER'), env('ADWORDS_PASS'), env('ADWORDS_DB'));
            if ($this->adwords->connect_error) {
                throw new Exception("Failed to connect to adwords database with error {$this->adwords->connect_error}");
            }
        }
    }


    /**
     * @param $type - responses, inquirers, salvations,recommitments,prayer,questions,subscriptions
     * @param $count
     * @param $startdate $startdate - beginning date or  all, thisyear, lastyear, lastmonth
     * @param $enddate
     * @param $order
     * @return array
     */
    public function getItems($type, $count, $startdate, $enddate, $order = 'desc')
    {
        $ret = array();
        $this->connectToConnections();
        $count = (int)$count;
        $count = $count > 500 ? 500 : $count;
        $where = dateWhereClause($startdate, $enddate, $field = 'datesubmitted::timestamp', $dateformat = 'Y-m-d');
        $whereClause = $where[0];
        $sql = "select uia.datesubmitted as datesubmitted,
                    case when u.gender=1 then 'female'
                         when u.gender=2 then 'male'
                    end as gender,
                    userinquirercomments as comments,glcity as city,glregionname as region,glcountryname as country,
                    case when decisiontoday=1 then 'salvation'
                         when decisiontoday=2 then 'recommitment'
                         when decisiontoday=3 then 'question'
                         when decisiontoday=4 then 'prayer'
                         when decisiontoday=6 then 'subscription'
                    end as decision,
                    l.referencename as language
                     from userinquirerapplication uia

                    join userinquirerextension uie on uie.userid=uia.userid
                    join users u on u.userid=uia.userid
                    join languages l on l.languagesid=u.preferredlanguageid
                    #whereclause#
                    order by datesubmitted $order
                    limit $count";
        switch ($type) {
            case 'prayerneeds':
                $sql = "select datesubmitted ,gender,userlocation,userinquirercomments as comments,decisiontoday as decision, agwmregion,agwmarea from testimonies " .
                    " #whereclause# order by datesubmitted $order limit $count";
                $sql = str_replace('#whereclause#', $whereClause . ' and  active=1 and prayerneed = 1 ', $sql);
                break;
            case 'testimonies':
                $sql = "select datesubmitted ,gender,userlocation,userinquirercomments as comments,decisiontoday as decision, agwmregion,agwmarea from testimonies " .
                    " #whereclause# order by datesubmitted  $order limit $count";
                $sql = str_replace('#whereclause#', $whereClause . ' and  active=1 and prayerneed = 0 ', $sql);
                break;
            case 'responses':
                $where = dateWhereClause($startdate, $enddate, $field = 'visitationtime::timestamp', $dateformat = 'Y-m-d');
                $whereClause = $where[0];
                $sql = "select referrerurl as SiteSource, visitationtime as VisitDate,trim(countryname) as Country,city,trim(region) as region from visitors $whereClause order by visitationtime $order limit $count";
                break;
            case 'countriesofresponse':
                $where = dateWhereClause($startdate, $enddate, $field = 'visitationtime::timestamp', $dateformat = 'Y-m-d');
                $whereClause = $where[0];
                $sql = "select count(countryname) as count,trim(countryname) as Country from visitors $whereClause group by countryname order by count desc  limit $count";
                break;
            case 'sitesofresponses':
                $where = dateWhereClause($startdate, $enddate, $field = 'visitationtime::timestamp', $dateformat = 'Y-m-d');
                $whereClause = $where[0];
                $sql = "select replace(substring( lower(documenturl) from '.*://([^/]*)'),'www.','' ) as hostname,
                    count(replace(substring( lower(documenturl) from '.*://([^/]*)'),'www.','' )) as count from visitors  $whereClause group by hostname
                     order by count desc limit 200";
                break;
            case 'inquirers':
                $sql = str_replace('#whereclause#', $whereClause, $sql);
                break;
            case 'countriesofinquirers':
                $where = dateWhereClause($startdate, $enddate, $field = 'datesubmitted::timestamp', $dateformat = 'Y-m-d');
                $whereClause = $where[0];
                $sql = "select count(glcountryname) as count,trim(glcountryname) as Country from userinquirerextension uie " .
                    " join userinquirerapplication uia on uia.userid=uie.userid $whereClause group by glcountryname order by count desc  limit $count";
                break;
            case 'sitesofinquirers':
                $where = dateWhereClause($startdate, $enddate, $field = 'datesubmitted::timestamp', $dateformat = 'Y-m-d');
                $whereClause = $where[0];
                $sql = "select replace(substring( firstcontacturl from '.*://([^/]*)'),'www.','' ) as hostname, count( replace(substring( firstcontacturl from '.*://([^/]*)'),'www.','' ) ) as count from userinquirerextension uie
                   join userinquirerapplication uia on uia.userid = uie.userid
                  $whereClause group by hostname order by count desc";
                break;
            case 'subscriptions':
                $sql = str_replace('#whereclause#', $whereClause . ' and decisiontoday=6 ', $sql);
                break;
            case 'recomitments':
                $sql = str_replace('#whereclause#', $whereClause . ' and decisiontoday=2 ', $sql);
                break;
            case 'question' :
            case 'questions':
                $sql = str_replace('#whereclause#', $whereClause . ' and decisiontoday=3 ', $sql);
                break;
            case 'prayer':
            case 'prayers' :
                $sql = str_replace('#whereclause#', $whereClause . ' and decisiontoday=4 ', $sql);
                break;
            case 'salvation' :
            case 'salvations' :
                $sql = str_replace('#whereclause#', $whereClause . ' and decisiontoday=1 ', $sql);
                break;
            default:
                return null;
        }
//echo $sql;
        $res = pg_query($this->connections, $sql);
        $ret = pg_fetch_all($res);
////        print_r($ret);
//        $ret2 = array();
//        foreach ($ret as $key => $data) {
//            print_r($data);
//            $h=array();
//            foreach($data as $thekey=> $oneItem){
//                if($oneItem) $h[$thekey]=$oneItem;
//            }
//            $ret2[] = $h;
//        }
//        dd($ret);
        error_log("$type: \n$sql\n", 3, '/var/www/html/App1/app/MyFiles/sql.log');
        return $ret;
    }

    /**
     * @param $type - visitors, responses,inquirers
     * @param $period1
     * @param $period2
     * @return array|null
     * @throws Exception
     */
    public function getStats($type, $period1, $period2)
    {
        $this->connectToAdwords();
        list($where, $periodDescription) = dateWhereClause($period1, $period2, 'startdate');
        switch ($type) {
            case 'visitors':
                $sql = "select sum(visits) as cnt from analytics $where";
                break;
            case 'responses':
                $sql = "select sum(visits) as cnt from connectionresults $where";
                break;
            case 'inquirers':
                $sql = "select sum(salvations)+sum(recommitments)+sum(questions)+sum(prayer)+sum(subscriptions) as cnt from connectionresults $where";
                break;
            case 'salvations':
                $sql = "select sum(salvations) as cnt from connectionresults $where";
                break;
            case 'recommitments':
                $sql = "select sum(recommitments) as cnt from connectionresults $where";
                break;
            case 'prayers':
                $sql = "select sum(prayer) as cnt from connectionresults $where";
                break;
            case 'subscriptions':
                $sql = "select sum(subscriptions) as cnt from connectionresults $where";
                break;

            default:
                $sql = '';
        }
        if ($sql) {
            $res = $this->adwords->query($sql);
            $row = $res->fetch_row();
            $cnt = $row[0];
            return array('type' => $type, 'count' => $cnt, 'daterange' => $periodDescription);
        }
        return null;
    }

    /**
     * @param $type - visitors, responses,inquirers
     * @param $period1
     * @param $period2
     * @return array|null
     * @throws Exception
     */
    public function getTotals($period1, $period2)
    {

        list($where, $periodDescription, $theDates) = dateWhereClause($period1, $period2, 'startdate');
        $cacheFileName = storage_path() . "/statstotals-{$theDates[0]}-{$theDates[1]}.json";
        if (file_exists($cacheFileName)) {
            echo time() - filemtime($cacheFileName) . '<br>';
            if (time() - filemtime($cacheFileName) < env('STATS_TOTALS_CACHE_SECONDS')) {
                return json_decode(file_get_contents($cacheFileName), true);
            }
        }

        $this->connectToAdwords();

        $visits = $this->getCount("select sum(visits) as cnt from analytics $where");
        $responses = $this->getCount("select sum(visits) as cnt from connectionresults $where");
        $inquirers = $this->getCount("select sum(salvations)+sum(recommitments)+sum(questions)+sum(prayer)+sum(subscriptions) as cnt from connectionresults $where");
        $salvations = $this->getCount("select sum(salvations) as cnt from connectionresults $where");
        $questions = $this->getCount("select sum(questions) as cnt from connectionresults $where");
        $recommitments = $this->getCount("select sum(recommitments) as cnt from connectionresults $where");
        $prayerrequests = $this->getCount("select sum(prayer) as cnt from connectionresults $where");
        $subscriptions = $this->getCount("select sum(subscriptions) as cnt from connectionresults $where");

        $ret = array('perioddescription' => $periodDescription, 'visits' => $visits, 'responses' => $responses, 'inquirers' => $inquirers,
            'salvations' => $salvations, 'questions' => $questions, 'recommitments' => $recommitments,
            'prayerrequests' => $prayerrequests, 'subscriptions' => $subscriptions, 'time' => date('Y-m-d H:i:s'));
        file_put_contents($cacheFileName, json_encode($ret));
        return $ret;
    }

    private function getCount($sql)
    {
        $res = $this->adwords->query($sql);
        $row = $res->fetch_assoc();
        return $row['cnt'];
    }

}
