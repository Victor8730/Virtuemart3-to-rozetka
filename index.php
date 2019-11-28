<?php
if($_GET['download'] == '1'){//check command
//header('Content-Type: text/html; charset=utf-8');
include 'config.php'; // get config variables $db_prefix, $db_user, $db_pass, $db_host, $db_name, $urlSite, $nameSite, $nameCompany
$priceChange = (intval(isset($_GET['p']))) ? $_GET['p'] : 1; // if need update price
spl_autoload_register(function ($class_name) {
   include 'class/'.$class_name . '.php';
});
$db = new Db($db_prefix, $db_user, $db_pass, $db_host, $db_name);
$xml = new Xml('');
$connect  = $db->connect();
//SHOP/NAME/COMPANY/URL
    $shopToXml = [];
    $shopToXml[0]['name'] = $nameSite;
    $shopToXml[0]['company'] = $nameCompany;
    $shopToXml[0]['url'] = $urlSite;
    $xmlShop = new SimpleXMLElement('<shop></shop>');
    $xmlShop = $xml->arrayToXml($shopToXml,$xmlShop,'1');
    //echo $xmlShop->asXML();
//CURRENCIES
    $currenciesInfo = $db->load_data( $connect , 'virtuemart_currencies', 'published' , 1);
    $curToXml = [];
    foreach($currenciesInfo as $cur){
        $curToXml[$cur['currency_code_3']]['name'] = $cur['currency_exchange_rate'];
    }
    $xmlCurrencies = new SimpleXMLElement('<currencies></currencies>');
    $xmlCurrencies = $xml->arrayToXml($curToXml,$xmlCurrencies,'currency');
    $xml->appendXmlToXml($xmlShop,$xmlCurrencies);
//CATEGORY
    $categoryInfo = $db->load_data( $connect , 'virtuemart_categories_ru_ru');
    $catToXml = [];
    foreach($categoryInfo as $cat){
        $categoryInfoParent = $db->load_data( $connect , 'virtuemart_category_categories', 'id',$cat['virtuemart_category_id']);
        $catToXml[$cat['virtuemart_category_id']]['name'] = $cat['category_name'];
        $catToXml[$cat['virtuemart_category_id']]['parent'] = $categoryInfoParent[0]['category_parent_id'];
    }
    $xmlCategory = new SimpleXMLElement('<categories></categories>');
    $xmlCategory = $xml->arrayToXml($catToXml,$xmlCategory,'category');
    $xml->appendXmlToXml($xmlShop,$xmlCategory);
//PRODUCT
    $productInfo = $db->load_data( $connect , 'virtuemart_products_ru_ru');//data
    $prodToXml = [];
    foreach($productInfo as $prod){
        if ($prod['virtuemart_product_id'] != 0){ //skipping 0 id, if exist
            $prodFirstData = $db->load_data($connect, 'virtuemart_products', 'virtuemart_product_id', $prod['virtuemart_product_id']);
            $prodPriceData = $db->load_data($connect, 'virtuemart_product_prices', 'virtuemart_product_id', $prod['virtuemart_product_id']);
            //manufacturer
            $prodManufacturerData = $db->load_data($connect, 'virtuemart_product_manufacturers', 'virtuemart_product_id', $prod['virtuemart_product_id']);
            $ManufacturerInfo = $db->load_data($connect, 'virtuemart_manufacturers_ru_ru', 'virtuemart_manufacturer_id', $prodManufacturerData[0]['virtuemart_manufacturer_id']);
            $prodMedia = $db->load_data($connect, 'virtuemart_product_medias', 'virtuemart_product_id', $prod['virtuemart_product_id']);
            //category info, for create url
            $prodCatData = $db->load_data($connect, 'virtuemart_product_categories', 'virtuemart_product_id', $prod['virtuemart_product_id'], 'virtuemart_category_id');
            $catCatSlug = $db->load_data($connect, 'virtuemart_categories_ru_ru', 'virtuemart_category_id', $prodCatData[0]['virtuemart_category_id']);
            //currency
            $prodCurrencyData = $db->load_data($connect, 'virtuemart_currencies', 'virtuemart_currency_id', $prodPriceData[0]['product_currency']);
            $i = 0;
            foreach ($prodMedia as $img) {
                if ($i < 2) { //how much image load
                    $prodMedia = $db->load_data($connect, 'virtuemart_medias', 'virtuemart_media_id', $img['virtuemart_media_id']);
                    $prodToXml[$prod['virtuemart_product_id']]['picture'][] = $urlSite . $prodMedia[0]['file_url'];
                }
                $i++;
            }
            $prodToXml[$prod['virtuemart_product_id']]['name'] = $prod['product_name'];
            $prodToXml[$prod['virtuemart_product_id']]['url'] = $urlSite . $catCatSlug[0]['slug'] . '/' . $prod['slug'] . '-detail';
            $prodToXml[$prod['virtuemart_product_id']]['price'] = $prodPriceData[0]['product_price'] * $priceChange;
            $prodToXml[$prod['virtuemart_product_id']]['currencyId'] = $prodCurrencyData[0]['currency_code_3'];
            $prodToXml[$prod['virtuemart_product_id']]['categoryId'] = $prodCatData[0]['virtuemart_category_id'];
            $prodToXml[$prod['virtuemart_product_id']]['product_desc'] = $prod['product_desc'];
            $prodToXml[$prod['virtuemart_product_id']]['product_sku'] = $prodFirstData[0]['product_sku'];
            $prodToXml[$prod['virtuemart_product_id']]['stock_quantity'] = $prodFirstData[0]['product_in_stock'];
            $prodToXml[$prod['virtuemart_product_id']]['vendor'] = $ManufacturerInfo[0]['mf_name'];
            $prodToXml[$prod['virtuemart_product_id']]['available'] = ($prodFirstData[0]['product_in_stock'] > 0) ? 'true' : 'false';
        }
    }
    $xmlProduct = new SimpleXMLElement('<offers></offers>');
    $xmlProduct = $xml->arrayToXml($prodToXml,$xmlProduct,'offer');
    $xml->appendXmlToXml($xmlShop,$xmlProduct);
$xmlData = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE yml_catalog SYSTEM "shops.dtd"><yml_catalog date="'.date("Y-m-d H:i:s").'"></yml_catalog>');
$xml->appendXmlToXml($xmlData,$xmlShop);
/*header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=rozetka.xml');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
//header('Content-Length: ' . filesize($file));*/

echo $xmlData->asXML();

}else{
    echo 'ERROR!';
}
?>