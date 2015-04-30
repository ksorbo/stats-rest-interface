<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

header('Access-Control-Allow-Origin: *');

Route::get('/test/{func}/{start?}/{end?}','StatisticsController@testAnalytics');

Route::get('/', 'WelcomeController@index');

Route::get('/prayersignup','FormPostController@prayerTaskForceSignup');

//Route::get('/prayersignup',function(){
//    print_r($_REQUEST);die;
//});

Route::get('/fullstats/{period1?}/{period2?}','StatisticsController@fullstats');

Route::get('/inquirermap/{days?}/{toshow?}/{start?}/{end?}','StatisticsController@inquirermap');

Route::get('/totals/{period1?}/{period2?}','StatisticsController@totals');

Route::get('/stats/{type}/{period1?}/{period2?}','StatisticsController@stats');

Route::get('/items/{type}/{order}/{count}/{startdate?}/{enddate?}','StatisticsController@items');

Route::get('/livedata','LiveDataController@livedata');

Route::get('/news/{number?}','StatisticsController@news');

Route::any('(.*)', 'HomeController@index');

Route::controllers([
	'auth' => 'Auth\AuthController',
	'password' => 'Auth\PasswordController',
]);

