<?php

require_once '../config.php';
require_once '../functions.php';
require_once '../simple_html_dom.php';
require_once '../probase.php';

$line = array();
if ($_GET['sendUrl'] != '') {

    $screpperurl = urldecode($_GET['sendUrl']);
    //$screpperurl = 'file:///E:/Projects/_delete/scraper/test.html';
    $date = $_GET['date'];
    list($d, $m, $y) = explode('-', $date);
    $date = $y.'-'.$m.'-'.$d;

    //echo "Antiquorum Scrapper Url=".$screpperurl; die('ffff');
    $html = $screpperurl;

    if ($html) {
        $title = $html->find('span[class=titleLot]', 0)->plaintext;
        $description = $html->find('div[class=introProduct]', 0)->plaintext;
        $image = $html->find('div[class=colBig]', 0)->find('img', 0)->src;

        $image_path = basename($image);
        $image_details = explode('.', $image_path);
        $destImage = (substr(time(), 5) . '-' . rand(9999, 999999)) . '.' . end($image_details);
        $destImage2 = basename($destImage);
        $destImage2 = str_replace('.', '-lg.', $destImage2);
        copy($image, $filepath.'Temp/' . $destImage2);

        $fpath=$serverpath.'Temp/'.$destImage2;
        $rzpath=$filepath.'images/'.$destImage2;
        //echo 'Fpath='.$fpath.' Rzpath='.$rzpath;
        $myimage = resizeImage($fpath, $imgwidth, $imgheight,$rzpath);
        unlink($filepath.'Temp/'.$destImage2);
        //echo '<br><br>Large Image='.$lgimage.' Large Destination Image='.$destImage2;

        // copy image to krono server
        if(is_file($rzpath) && upload_image($rzpath))
            unlink($rzpath);

        $sqlProduct = 'UPDATE products AS p SET `description`="' . mysql_real_escape_string(trim($description)) . '", `product_title`="' . mysql_real_escape_string(trim($title)) . '", `product_image`="'.$destImage2.'" WHERE `product_url`="' . $screpperurl . '"';
        mysql_query($sqlProduct) or die(mysql_error());
        //echo $sqlProduct . '<br>';
    }
}
?>