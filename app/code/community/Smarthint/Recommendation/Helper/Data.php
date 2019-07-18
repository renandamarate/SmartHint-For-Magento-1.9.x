<?php
class Smarthint_Recommendation_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function processProduct(){
        $prodCollection = Mage::getResourceModel('catalog/product_collection')
                        ->addAttributeToFilter("status", 1)
                        ->addAttributeToSelect('*')
                        ->addFinalPrice();
 
        Mage::getSingleton('catalog/product_visibility')
            ->addVisibleInCatalogFilterToCollection($prodCollection);
        
        $products = array();

        foreach ($prodCollection as $product) {
            echo $product->getTypeId() . " - " . $product->getData('entity_id') . " - " . $product->getData('name'). "<br>";

            try{
                $prd = self::getProduct($product);
                $products = array_merge($products, $prd);
            }
            catch (Exception $e) {
                echo ' Exceção capturada: ',  $e->getMessage(), "\n";
            }
        }
        $productsCount = count($products) / 50;
        $productsCount = ceil($productsCount);

        for ($i = 0; $i < $productsCount; $i++) {
            $offset = $i * 50;
            $length = $offset + 50;
            $output = array_slice($products, $offset, $length);
            self::send($output, "handleProduct", "https://api.smarthint.co/api/ProductList/");
        }
    }

    public function processProductPage($page, $limit, $storeId){

        $prodCollection = Mage::getResourceModel('catalog/product_collection')
                        ->addAttributeToFilter("status", 1)
                        ->setPageSize(intval($limit))
                        ->setCurPage(intval($page))
                        ->setStoreId($storeId)
                        ->addAttributeToSelect('*')
                        ->addFinalPrice();
 
        Mage::getSingleton('catalog/product_visibility')
            ->addVisibleInCatalogFilterToCollection($prodCollection);
        
        $products = array();

        foreach ($prodCollection as $product) {
            try{
                $prd = self::getProduct($product);
                $products = array_merge($products, $prd);
            }
            catch (Exception $e) {
                echo ' Exceção capturada: ',  $e->getMessage(), "\n";
            }
        }
        $productsCount = count($products) / 50;
        $productsCount = ceil($productsCount);

        for ($i = 0; $i < $productsCount; $i++) {
            $offset = $i * 50;
            $length = $offset + 50;
            $output = array_slice($products, $offset, $length);
            self::send($output, "handleProduct", "https://api.smarthint.co/api/ProductList/");
        }
    }

    public function getProduct($product){
        $products = array();
        $SHProductInfo = new stdClass();
        $SHProductInfo->ProductId       = $product->getData('entity_id');
        $SHProductInfo->Title           = $product->getData('name');
        $SHProductInfo->Sku             = $product->getData('sku');
        $SHProductInfo->Link            = $product->getUrlInStore();
        $SHProductInfo->Description     = $product->getData('short_description');
        $SHProductInfo->Brand           = $product->getData('brands');
        $SHProductInfo->Condition       = "new";
        $SHProductInfo->CreatedDate     = $product->getData('created_at');
        $SHProductInfo->Availability    = "in stock";
        $SHProductInfo->Price           = (float) $product->getData('price');
        $SHProductInfo->SalePrice       = (float) $product->getData('final_price');
        $SHProductInfo->ImageLink       = Mage::getModel('catalog/product_media_config')->getMediaUrl( $product->getSmallImage() );
        $SHProductInfo->Categories      = array();
        /*
        $SHProductInfo->Installments = [
            "Months" => 10, //Quantidade de parcelas  
            "Amount" => 99.9 //Valor de parcelas
        ];
        $SHProductInfo->AdditionalTag   = "5-10";
        */

        //Get secondary image
        $images = Mage::getModel('catalog/product')->load($product->getId())->getMediaGalleryImages();
        if ($images != null){
            foreach ($images as $image){
                if ($SHProductInfo->ImageLink != $image["url"]){
                    $SHProductInfo->AdicionalImageLink[] = $image["url"];
                }
                continue;
            }
        }

        //Stock
        if (!$product->getStockItem()->getIsInStock()){
            $SHProductInfo->Availability = "out of stock";
        }

        //Categories
        $categories = $product->getCategoryIds();
        foreach ($categories as $category_id) {
            $_cat = Mage::getModel('catalog/category')->load($category_id) ;
            $SHProductInfo->Categories[] = $_cat->getName();
        }
        $SHProductInfo->ProductType = end($SHProductInfo->Categories);
        
        //Price
        if ($product->getTypeId() == 'bundle'){
            $SHProductInfo->Price = Mage::getModel('bundle/product_price')->getTotalPrices($product,'min',0);
        }
        else if ( $product->getTypeId() == 'grouped' ){
            $SHProductInfo->Price = (float)$this->prepareGroupedProductPrice($product);
        }

        //Variations
        if ($product->isConfigurable()) {
            $SHProductInfo->Variations = array();
            $configurables = $this->getConfigurable($product, $SHProductInfo);
            $products = array_merge($products, $configurables);
        }else {
            
            array_push($products, $SHProductInfo);
            // self::send($SHProductInfo, "handleProduct", "https://api.smarthint.co/api/Product/");
        }
        return $products;
    }

    public function getConfigurable($product, $SHProductInfo){
        $products = array();
        $atts = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
        $childProducts = Mage::getModel('catalog/product_type_configurable')                        
            ->getUsedProductCollection($product)
            ->addAttributeToSelect('*')
            ->addFinalPrice();


        foreach($childProducts as $child) {
            $childProduct                   = clone $SHProductInfo;
            $childProduct->ItemGroupId      = $SHProductInfo->ProductId;
            $childProduct->ProductId        = $child->getData('entity_id');
            $childProduct->Price            = (float) $child->getData('price');
            $childProduct->SalePrice        = (float) $child->getData('final_price');
            $childProduct->Sku              = $child->getData('sku');
    
            foreach($atts as $att) {
                $elemento = $child->getData($att['attribute_code']);
                $opts = $att['values'];
                $found_key = array_search($elemento, array_column($opts, 'value_index'));
                $variations = new stdClass();
                $variations->NameId = $att['attribute_id'];
                $variations->Name = $att['attribute_code'];
                $variations->ValueId = $elemento;
                $variations->Value = $opts[$found_key]["store_label"];
                $childProduct->Variations[] = $variations;
            }

            if (!$child->getStockItem()->getIsInStock()){
                $childProduct->Availability = "out of stock";
            }
            array_push($products, $childProduct);
            // self::send($childProduct, "handleProduct", "https://api.smarthint.co/api/Product/");
        }
        return $products;
    }

    public function apiProcess($val) {
         $dateInit = array(
            'ProductNumber' => "9999",
            'Type' => $val
        );
        self::send($dateInit, "handleApiProcess", "https://api.smarthint.co/api/Process/");
    }
    
    public function prepareGroupedProductPrice($groupedProduct) {
        $aProductIds = $groupedProduct->getTypeInstance()->getChildrenIds($groupedProduct->getId());
    
        $prices = array();
        foreach ($aProductIds as $ids) {
            foreach ($ids as $id) {
                $aProduct = Mage::getModel('catalog/product')->load($id);
                $prices[] = $aProduct->getPriceModel()->getPrice($aProduct);
            }
        }
    
        krsort($prices);
        return array_shift($prices);
    }

    public function processCategory(){
		try{
            $categories = Mage::getModel('catalog/category')->getCollection()
            ->setPageSize(10000) 
            ->setCurPage(1);

            foreach ($categories as $categ) 
            {
                $category = Mage::getModel('catalog/category')->load($categ->getId());
                $SHCategoryInfo = new stdClass();
                $SHCategoryInfo->CategoryId        = $category->getData('entity_id');
                $SHCategoryInfo->CategoryParentId  = $category->getData('parent_id');
                $SHCategoryInfo->Url               = $category->getUrl();
                $SHCategoryInfo->Name               = $category->getName();
                $SHCategoryInfo->FullPath          = str_replace('/', '>', $category->getPath());
                $SHCategoryInfo->Level             = $category->getData('level');
                self::send($SHCategoryInfo, "handleCategory", "https://api.smarthint.co/api/Category/");
            }
		}
		catch  (Exception $e) {
            echo ' Categoria - Exceção capturada: ',  $e->getMessage(), "\n";
		}
    }
    
    public function log($JSON){
        $URL = "https://webhook.logentries.com/noformat/logs/9d75644f-e96e-45b9-8e75-74a730e9e868";
        $curl = new Varien_Http_Adapter_Curl();
        $header = array("Content-Type:application/json");
        $opts = array();
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        $opts[CURLOPT_SSL_VERIFYPEER] = 0;
        $curl->setOptions($opts);
        $curl->write(Zend_Http_Client::POST, $URL, '1.0', $header,addslashes($JSON));
        $data = $curl->read();
    }

    public function send($object, $func, $url){
        $curl = new Varien_Http_Adapter_Curl();
        $config= array();
        $config[CURLOPT_SSL_VERIFYPEER] = false;
        $curl->setConfig($config);

        $authA = Mage::getStoreConfig('Smarthint/identify/Token', Mage::app()->getStore());
        $authB = Mage::getStoreConfig('Smarthint/identify/Token');
        
        if ($authA != null){
            $auth = $authA;
        }else{
            $auth = $authB;
        }
        $myHeaders = array('Authorization: ' . $auth, "Content-Type:application/json");
        $feed_url = $url;
        $curl->write(Zend_Http_Client::POST, $feed_url, '1.0', $myHeaders,json_encode($object));
        $data = $curl->read();
        if ($curl->getInfo(CURLINFO_HTTP_CODE) != 200) {
            $error = new stdClass();
            $error->status = "Error";
            $error->function = $func;
            $error->auth = $auth;
            $error->object = $object;
            $error->response = $data;
            $JSON = json_encode($error);
            $this->log($JSON);
        }
    }
}