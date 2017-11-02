<?php

////    Status OK.... 
set_time_limit(0);
require_once dirname(dirname(__FILE__)).'/config.php';
require_once $filepath.'functions.php';
require_once $filepath.'multi_curl.php';
require_once $filepath.'probase.php';
require_once $filepath.'simple_html_dom.php';

$count = 0;
$newUrl = 'http://www.antiquorum.com/price-list/?page=1';

//$newUrl = 'file:///E:/Projects/_delete/scraper/test.html';
$html = $newUrl;

foreach ($html->find('div[class=catalog]') as $selVal) {
    $sel = $selVal->find("a[class=view-btn]", 0);

    $selValue = $sel->href;
    preg_match('|([0-9\-]+)/price|', $selValue, $matches);
    if (empty($matches[1]))
        continue;

    $date = trim($matches[1], ' -');
    $date = date('d-m-Y', strtotime($date));

    $dates[] = array($date, $selValue);
}

foreach ($dates as $time => $date) {
    $sqlFetch = "SELECT `auction_date` FROM `auction_date` WHERE `auction_date` ='$date[0]'";
    $queryFetch = mysql_query($sqlFetch);
    $auction_date = date('Y-m-d', strtotime($date[0]));
    if (($auction_date <= date('Y-m-d')) && $date[1] != '' && !mysql_num_rows($queryFetch)) {    
        $runFor = $date[0];
        $selValue = $date[1];
        
        $sqlDate = "INSERT INTO `auction_date` (`auction_date`, `auction_value`) VALUES('$runFor', '$selValue')";
        $query = mysql_query($sqlDate) or die(mysql_error());
    }
}

function scrap($html, $date) {
    global $serverpath;
    global $filepath;
    global $imgwidth;
    global $imgheight;
    
    $ststus = '';
    $specification = '';
    $description = '';
    $shippingPrice = '';
    $shippingInfo = '';
    $line = array();

    $source = 'antiquorum';

    if (!$html)
        return;

    $lotURLs = array();
    foreach ($html->find('div[class="row"]') as $block) {
        $link = $block->find('a[class=lotnumber]', 0);
        if (!$link)
            continue;

        $price = $block->find('h3', 1)->plaintext;
        $currency = $block->find('span', 1)->plaintext;

        if (!(float)$price)
            continue;

        $lotURLs[] = array('link'=>'http://www.antiquorum.com'.$link->href, 'price'=>str_replace(',', '', $price), 'currency'=>$currency);
    }

    if (empty($lotURLs))
        return;

    list($d, $m, $y) = explode('-', $date);
    $date = $y.'-'.$m.'-'.$d;

    $urls = array();
    foreach ($lotURLs as $data) {
        $url = $data['link'];
        if (!checkExist(addslashes($url))) {
            $price = $data['price'];
            $currency = $data['currency'];

            $currency = currencyModifier($currency);

            if($currency=='USD') {
                $usdprice=$price;
            } else {
                $usdprice=convertCurrencyToUSD($currency,$price);
            }

            $sqlProduct = "INSERT INTO  products (product_url, product_price, currency, original_price, product_sold_date, source) 
               values('" . addslashes($url) . "','$usdprice','$currency','$price', '$date','$source')";

            mysql_query($sqlProduct) or die(mysql_error());
        } else {
            continue;
        }

        $urls[] = $serverpath.'scrapper/scrapper_antiquorum.php?sendUrl=' . urlencode($url).'&date='.$date;

        if (count($urls) == 10) {
            $mcurl = new Multicurl();
            $arr = $mcurl->multiple_threads_request($urls);

            sleep(rand(1,3));

            $urls = array();
        }
    }

    if (!empty($urls)) {
        $mcurl = new Multicurl();
        $arr = $mcurl->multiple_threads_request($urls);
    }
}

$sqlFetch = "SELECT * FROM `auction_date` WHERE auction_satatus = 0";
$queryFetch = mysql_query($sqlFetch);

if (!mysql_num_rows($queryFetch))
    exit;

while($auction = mysql_fetch_assoc($queryFetch)) {

    $runFor = $auction['auction_date'];
    $selValue = $auction['auction_value'];

    if ($runFor && !empty($selValue)) {

        $url = $selValue;
        //$url = 'http://localhost/soldprices/test.html';
        //getContent($url);
        $page = 1;
        do {
            //$html = getContent($url, 'action=search&s_searchtype=1&s_order=lotid&s_hideauctions=&s_keywords=&s_fromprice=&s_toprice=&s_auction=' . $selValue . '&s_grading=-1&s_batchstep=150&s_batch=' . $page);
            $html = $selValue;

            $totalPages = 1; //getTotalRec($html);
            scrap($html, $runFor);

            echo $page++;
            echo '<br>';

            sleep(5);
        } while ($page < $totalPages);
    }

    mysql_query("UPDATE auction_date SET auction_satatus = 1 WHERE auction_value='".$selValue."' LIMIT 1") or die(mysql_error());
    
    sleep(rand(10,20));    
}

echo "Process Completed Successfully..." . $selValue;
//error_log("\nExecuted On:" . date('l jS \of F Y h:i:s A'). "$count records inserted",3,"/var/www/vhosts/kronoindex.com/httpdocs/soldprices/antiquorum_error.txt");