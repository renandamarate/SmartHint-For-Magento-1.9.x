<?php
class Smarthint_Recommendation_Helper_Data extends Mage_Core_Helper_Abstract
{
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
        $headers = array('Authorization: ' . $auth, "Content-Type:application/json");
        $feed_url = $url;
        $curl->write(Zend_Http_Client::POST, $feed_url, '1.0', $headers,json_encode($object));
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

    public function getProduct($product){
        //Simple data
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
        if ($SHProductInfo->Price == 0){
            $SHProductInfo->Price = (float) $product->getPrice();
        }
        if ($SHProductInfo->SalePrice == 0 || 
            $SHProductInfo->Price == (float) $SHProductInfo->SalePrice){
            $SHProductInfo->SalePrice = (float) $product->getFinalPrice();
        }
        if ($product->getTypeId() == 'configurable' && $SHProductInfo->Price == 0 ){
            $SHProductInfo->Price = (float) self::getSimpleProductPrice(null, $product->getData('entity_id'));
            $SHProductInfo->SalePrice = (float) self::getSimpleProductFinalPrice(null, $product->getData('entity_id'));
        
        }
        else if($product->getTypeId() == 'bundle'){
            //Pode se usar o max, para obter o maior valor de combinação
            $SHProductInfo->Price = Mage::getModel('bundle/product_price')->getTotalPrices($product,'min',0);
        }
        if (is_null($SHProductInfo->Price)){
            $SHProductInfo->Price = 0;
        }
        if (is_null($SHProductInfo->SalePrice)){
            $SHProductInfo->SalePrice = 0;
        }

        //Description
        if (! is_string($SHProductInfo->Description)){
            $SHProductInfo->Description = "";
        }
        
        //Stock
        if ($product->getTypeId() == 'simple'){
            $stockItem = Mage::getModel('cataloginventory/stock_item')
                    ->loadByProduct($product->getData('entity_id'));
            if ($stockItem == "0"){
                $SHProductInfo->Availability    = "out of stock";
            }
        }
        if ($product->getTypeId() == 'configurable'){
            $stock_qty = 0;
            $simple_ids = Mage::getResourceSingleton('catalog/product_type_configurable')->getChildrenIds($product->getId());
            foreach ($simple_ids[0] as $simple_id) {
                $simple_model = Mage::getModel('cataloginventory/stock_item')->loadByProduct($simple_id);
                $stock_qty = $stock_qty + (int) $simple_model->getQty();
            }
            if ($stock_qty == 0) {
                $SHProductInfo->Availability = "out of stock";
            }
        }

        //Get primary and secondary image
        $SHProductInfo->ImageLink = Mage::getModel('catalog/product_media_config')->getMediaUrl( $product->getSmallImage() );
        
        $images = Mage::getModel('catalog/product')->load($product->getId())->getMediaGalleryImages();
        
        if (! is_null($images)){
            //Primary img
            $PrimaryLinkImage = $images->getItemByColumnValue('position', 1);
            if (! is_null($PrimaryLinkImage) && empty($SHProductInfo->ImageLink)){
                $SHProductInfo->ImageLink = $PrimaryLinkImage->getUrl();
            }else if (! is_null($PrimaryLinkImage)){
                if ($SHProductInfo->ImageLink != $PrimaryLinkImage->getUrl())
                    $SHProductInfo->AdicionalImageLink[] = $PrimaryLinkImage->getUrl();
            }
            //Second img
            $SecondLinkImage = $images->getItemByColumnValue('position', 2);
            if (! is_null($SecondLinkImage) && empty($SHProductInfo->ImageLink)){
                $SHProductInfo->ImageLink = $SecondLinkImage->getUrl();
            }else if (! is_null($SecondLinkImage)){
                $empty = "";
                if (! is_null($PrimaryLinkImage))
                    $empty = $PrimaryLinkImage->getUrl();
                if ($SHProductInfo->ImageLink != $empty)
                    $SHProductInfo->AdicionalImageLink[] = $SecondLinkImage->getUrl();
            }
        }
        
        if (empty($SHProductInfo->ImageLink)) {
            foreach ($images as $image){
                $SHProductInfo->ImageLink = $image["url"];
                continue;
            }
        }
        if (empty($SHProductInfo->ImageLink)) {
            $SHProductInfo->ImageLink = $product->getImageUrl();
        }

        //Get Categories
        $cats = $product->getCategoryIds();
        $SHProductInfo->Categories = array();
        foreach ($cats as $category_id) {
            $_cat = Mage::getModel('catalog/category')->load($category_id) ;
            $SHProductInfo->Categories[] = $_cat->getName();
        } 
        $SHProductInfo->ProductType = end($SHProductInfo->Categories);
        return $SHProductInfo;
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
			$error = new stdClass();
            $error->OrderError = $e->getMessage();
            $JSON = json_encode($error);
		}
    }

    public function processProduct(){
        $_categories = Mage::helper('catalog/category')->getStoreCategories();
		self::getChildCategories($_categories, true);
    }

    public function getChildCategories($category, $isFirst = false) {

        $children = $isFirst ? $category : $category->getChildren();

        foreach ($children as $child) {
            $category = Mage::getModel('catalog/category')->load($child->getId());

            $prodCollection = Mage::getResourceModel('catalog/product_collection')
                        ->addCategoryFilter($category)
                        ->addAttributeToSelect('*');
             
            Mage::getSingleton('catalog/product_status')
                ->addVisibleFilterToCollection($prodCollection);    
                
            Mage::getSingleton('catalog/product_visibility')
                ->addVisibleInCatalogFilterToCollection($prodCollection);

            // Mage::getSingleton('cataloginventory/stock')
            //     ->addInStockFilterToCollection($prodCollection);

            foreach ($prodCollection as $product) {
                $SHProductInfo = self::getProduct($product);
                self::send($SHProductInfo, "handleProduct", "https://api.smarthint.co/api/Product/");
            }

            self::getChildCategories($child);
        }
    }
    
    public function getSimpleProductPrice($qty=null, $cfgId)
    {
        $price = 0;
        $childProducts = Mage::getModel('catalog/product_type_configurable')
                            ->getChildrenIds($cfgId);

        foreach($childProducts as $childId) {
            foreach($childId as $child) 
            {
                $product = Mage::getModel('catalog/product')->load($child);
                $childPrice = (float) $product->getData('price');

                if ($childPrice == 0){
                    $price = $childPrice; 
                }
                else if ($price < $childPrice){
                    $price = $childPrice; 
                }
            }
        }
        return $price;
    }

    public function getSimpleProductFinalPrice($qty=null, $cfgId)
    {
        $price = 0;
        $childProducts = Mage::getModel('catalog/product_type_configurable')
                            ->getChildrenIds($cfgId);

        foreach($childProducts as $childId) {
            foreach($childId as $child) 
            {
                $product = Mage::getModel('catalog/product')->load($child);
                $childPrice = (float) $product->getData('final_price');
                if ($childPrice == 0){
                    $price = $childPrice; 
                }
                else if ($price > $childPrice){
                    $price = $childPrice; 
                }
            }
        }
        return $price;
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
}