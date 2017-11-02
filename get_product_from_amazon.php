<?php

require_once('include/simple_html_dom.php');
set_time_limit(0);


$amu = new Products();
$amu->getAllData();

class Products {

    public $domain = "";
    public $allData = array();

    function __construct() {
        
    }

    function getPagingLinks($url) {
        $links = array();
        
        $html = $this->curl_getContent($url);

        $html_base = new simple_html_dom();
        $html_base->load($html);

        $tmp = $html_base->find("#zg_paginationWrapper a");
        for ($i = 1; $i < count($tmp); $i++) {
            $links[] = $tmp[$i]->attr['ajaxurl'];
        }
        
        return $links;
    }
    
    function getAllFirstChildrenLinks($url){
        $links = array();

        $html = $this->curl_getContent($url);

        $html_base = new simple_html_dom();
        $html_base->load($html);

        $tmp = $html_base->find("#zg_browseRoot .zg_selected");
        $tmp = $tmp[0]->parent()->next_sibling()->find("a");
        for ($i = 0; $i < count($tmp); $i++) {
            $links[] = $tmp[$i]->href;
        }

        return $links;
    }

    function getAllData() {

        $url = 'https://www.amazon.com/Best-Sellers-Books-Childrens/zgbs/books/4/ref=zg_bs_unv_b_2_3371_1';
        $links= $this->getAllFirstChildrenLinks($url);
        foreach ($links as $link){
            //get page 1
            $this->getProducts($link);
            //get page 2,3,4,5
            $linkPagings= $this->getPagingLinks($link);
            foreach ($linkPagings as $linkPaging){
                $this->getProducts($linkPaging);
            }
        }

        // create csv
        $file = fopen("products.csv", "w");
        fputcsv($file, array('id', 'url', 'name','rating','customer_reviews','price','price_type','image','prime','release_date','author'));
        foreach ($this->allData as $k => $row) {
            $row = array_map("utf8_decode", $row);
            fputcsv($file, $row);
        }
        fclose($file);
    }

    function getProducts($url) {
        $html = $this->curl_getContent($url);

        $html_base = new simple_html_dom();
        $html_base->load($html);

        $tmp = $html_base->find("div.zg_itemImmersion");
        foreach ($tmp as $div) {
            $image = $div->find('img')[0]->src;
            $image= str_replace("//", "", $image);
            $image = explode('/', $image);
            $image = $image[count($image)-1];
            file_put_contents('images/'.$image, file_get_contents($div->find('img')[0]->src));

            $price=$div->find('.p13n-sc-price')[0]->plaintext;
            
            if(count($div->find('i.a-icon-star span.a-icon-alt'))>0){
                $nodeRating=$div->find('i.a-icon-star span.a-icon-alt')[0];
                $rating= explode(" ", $nodeRating->plaintext);
                $rating= $rating[0];
                
                $customer_reviews=$div->find('i.a-icon-star span.a-icon-alt')[0]->parent()->parent()->next_sibling()->plaintext;
            }
            else{
                $rating='';
                $customer_reviews='';
            }
            
            $nodeTypePrice=$div->find('span.a-size-small');
            if(is_array($nodeTypePrice)&&count($nodeTypePrice)>0){
                if ($nodeTypePrice[0]->class == 'a-size-small a-color-base') {
                    $typePrice = $nodeTypePrice[1]->plaintext;
                } else {
                    $typePrice = $nodeTypePrice[0]->plaintext;
                }
            }
            else{
                $typePrice='';
            }

            $id = $div->find('div.a-section')[0]->attr['data-p13n-asin-metadata'];
            $temp = explode('asin', $id);
            $id = $temp[1];
            $id = str_replace(array("'", "\"", "&quot;"), "", $id);
            $id = str_replace('}', '', $id);
            $id = str_replace(':', '', $id);
            
            $release_date=(is_array($div->find('div.zg_releaseDate'))&&count($div->find('div.zg_releaseDate'))>0)?$div->find('div.zg_releaseDate')[0]->plaintext:'';
            $release_date= str_replace("Release Date:", "", $release_date);

            //data
            $data = array('id' => '', 'url' => '', 'name' => '');
            $data['url'] = 'https://www.amazon.com' . trim($div->find('a.a-link-normal')[0]->href);
            $data['name'] = trim($div->find('div.p13n-sc-truncated-hyphen')[0]->plaintext);
            $data['id'] = $id;
            $data['rating'] = $rating;
            $data['customer_reviews'] = $customer_reviews;
            $data['price'] = $price;
            $data['price_type'] = $typePrice;
            $data['image'] = $image;
            $data['prime'] = (is_array($div->find('i.a-icon-prime'))&&count($div->find('i.a-icon-prime'))>0)?'true':'false';
            $data['release_date']=$release_date;
            $data['author']=$div->find('div.p13n-sc-truncated-hyphen')[0]->parent()->next_sibling()->find('.a-size-small')[0]->plaintext;
            $this->allData[$data['id']] = $data;

        }
        // clear html_base
        $html_base->clear();
        unset($html_base);
    }

    // @curl_getContent
    function curl_getContent($url) {

        $headers = array();
        $headers[] = 'Host: www.amazon.com';
        $headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64; rv:55.0) Gecko/20100101 Firefox/55.0';
        $headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $headers[] = 'Accept-Language: vi-VN,vi;q=0.8,en-US;q=0.5,en;q=0.3';
        $headers[] = 'Accept-Encoding: gzip, deflate, br';
        $headers[] = 'Connection: keep-alive';
        $headers[] = 'Upgrade-Insecure-Requests: 1';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        $content = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status == 0) {
            sleep(30);
            return $this->curl_getContent($url);
        }

        return $content;
    }

}

?>