<?php namespace App\Http\Controllers;

use App\LiveDataModel;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class LiveDataController extends Controller {

	public function livedata(){
        $live = new LiveDataModel();
        return $live->getData();
    }

}
