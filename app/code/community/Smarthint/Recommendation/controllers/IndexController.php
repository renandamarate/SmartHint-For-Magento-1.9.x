<?php
class Smarthint_Recommendation_IndexController extends Mage_Core_Controller_Front_Action{

    public function indexAction()
    {
        set_time_limit(0);
        $process = new stdClass();
        $process->status = "Atualização";
        $process->version = "1.1.5";
        $process->domain = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $process->email  = Mage::getStoreConfig('smarthint/identify/email', $observer->store);
        $JSON = json_encode($process);

        Mage::helper('smarthint')->log($JSON);
        Mage::helper('smarthint')->apiProcess(1);
        Mage::helper('smarthint')->processProduct();
        Mage::helper('smarthint')->processCategory();
        Mage::helper('smarthint')->apiProcess(2);
    }

    public function versionAction()
    {
        $version = new stdClass();
        $version->version = "1.1.5";
        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true);
        $this->getResponse()->setBody(json_encode($version));
    }

    public function productsAction()
    {
        $storeId = Mage::app()->getStore()->getStoreId();
        $ids = Mage::getResourceModel('catalog/product_collection')
                        ->addAttributeToFilter("status", 1)
                        ->setStoreId($storeId)
                        ->getAllIds();
        
        $page = $this->getRequest()->getParam('page');
        $limit = $this->getRequest()->getParam('limit');
        $hasnext = ((intval($page) * intval($limit)) < count($ids));

        if ($hasnext){

            Mage::helper('smarthint')->processProductPage($page, $limit, $storeId);

            $page = intval($page) + 1;
            $url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 
                "/smarthint/index/products" .
                "?page=" . $page . 
                "&limit=" .$limit;

            $link = new stdClass();
            $link->url = $url;
            $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true);
            $this->getResponse()->setBody(json_encode($link));
        }
        else{
            $link = new stdClass();
            $link->url = "";
            $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true);
            $this->getResponse()->setBody(json_encode($link));
        }
    }
}