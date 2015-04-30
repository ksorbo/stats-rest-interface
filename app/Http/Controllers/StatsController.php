<?php namespace App\Http\Controllers;

use App\Http\Requests;
use App\StatsModel;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class StatsController extends Controller
{

    public function getVisitors($period = 'all')
    {
        $stats = new StatsModel();

        $v = $stats->getVisitors($period);

        return "Visitor page: " . number_format($v, 0);
    }

}
