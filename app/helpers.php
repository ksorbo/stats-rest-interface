<?php

function dateWhereClause($period1,$period2,$field='startdate',$dateformat='Ymd'){
    $ret = array('','All available data',array('2008-10-01',date('Y-m-d')));
    switch ($period1){
        case 'all':
            return $ret;
        case 'thisyear':
            $y = date('Y');
            $start =  strtotime("first day of January $y");
            $end =strtotime('tomorrow');
            break;
        case 'lastyear' :
            $y = date('Y')-1;
            $start =  strtotime("first day of January $y");
            $end =strtotime("last day of december $y");
            break;
        default:
            $start = strtotime($period1);
            $end = strtotime($period2);
            if($start===false || $end ===false){
                return $ret;
            }
    }
    $where  = "where $field>='".date($dateformat,$start)."'";
    $where .= ' and ';
    $where .= " $field <='".date($dateformat,$end)."'";
    $datestring = 'Between '.date('Y-m-d',$start).' and '.date('Y-m-d',$end);
    return array($where, $datestring,array(date('Y-m-d',$start),date('Y-m-d',$end)));

}

/**
 * Generates a jpg file of a map with included points
 * @param string $filename - fully qualified name of file to save. - Assumed it is jpg
 * @param array $countryData - array of country names that will be points on the map
 * @param string $center - latitude,longitude of center fo map
 *
 */
function staticMapWithMarkers($filename, $countryData,$center='0,0',$markersize="mid",$markercolor="blue")
{
    $file = $filename;
    $u = env('MAP_OPTIONS');
//    $u .= "&center=$center";

    $markers = '';
    $markers .= '&markers=' . ("color:$markercolor|size:$markersize");
    foreach ($countryData as $country ):
        $markers .= "|".urlencode($country);
    endforeach;

    $u .= '&zoom=1&size=1100x400';
    $u .= $markers;
    $u .= '&key=' . env('STATICAPIKEY');

//    echo '<a target="_blank" href="'.$u.'">URL</a>';

    file_put_contents($file, file_get_contents($u));
    return;
}

/**
 * @param string $image - file folder/name
 * @param int $top - crop from top
 * @param int $bottom - crop from bottom
 * @param int $left - crop from left
 * @param int $right - crop from right
 * @return bool
 */
function cropit($image,$top,$bottom,$left,$right){

    $imageInfo = getimagesize($image);
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $dst_x = 0;   // X-coordinate of destination point
    $dst_y = 0;   // Y-coordinate of destination point
    $src_x = $top; // Crop Start X position in original image
    $src_y = $left; // Crop Srart Y position in original image
    $dst_w = $width - $left - $right; // Thumb width
    $dst_h = $height - $top - $bottom; // Thumb height
    $src_w = $dst_w; // $src_x + $dst_w Crop end X position in original image
    $src_h = $dst_h; // $src_y + $dst_h Crop end Y position in original image
// Creating an image with true colors having thumb dimensions (to merge with the original image)
    $dst_image = imagecreatetruecolor($dst_w, $dst_h);
// Get original image
    $src_image = imagecreatefromjpeg($image);
// Cropping
    imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
// Saving
    return imagejpeg($dst_image, $image);

}