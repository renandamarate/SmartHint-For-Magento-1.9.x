<?php 
class Smarthint_Recommendation_Model_Observer {

	public $server = "https://service.smarthint.co/";
    
    
    public function handleOrder($observer)
    {
		try{
			$order = $observer->getEvent()->getOrder();
			$payment = $order->getPayment();
			$customer_id = $order->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($customer_id)->getData();

			$SHOrderInfo = new stdClass();
			$SHOrderInfo->Date = date("Y-m-d H:i:s");
			$SHOrderInfo->anonymousConsumer = isset($_COOKIE["SmartHint-AnonymousConsumer"]) ? $_COOKIE["SmartHint-AnonymousConsumer"] : "";
			$SHOrderInfo->session = isset($_COOKIE["SmartHint-Session"]) ? $_COOKIE["SmartHint-Session"] : "";
			$SHOrderInfo->identifiedConsumer = $this->identifiedConsumer($customer, $key);
			
			$SHOrderInfo->Total = $order->getGrandTotal();
			
			if($order->getRealOrderId() != null )
			{
				$SHOrderInfo->OrderId = $order->getRealOrderId();
			}
			else{
				$SHOrderInfo->OrderId = $order->getEntityId();
			}
			$SHOrderInfo->Freight = $order->getShippingAmount();
			$SHOrderInfo->Discount = $order->getDiscountAmount();

			$SHOrderInfo->Billing->Mode = $payment->getMethodInstance()->getTitle();
			$SHOrderInfo->Billing->ModeId = $payment->getMethodInstance()->getCode();

			$SHOrderInfo->Items = array();
			foreach($order->getAllVisibleItems() as $value) {
				$item = new stdClass();
				$item->Name = $value->getName();
				$item->Quantity = $value->getQtyOrdered();
				$item->UnitPrice = $value->getPrice();
				$item->SKU = $value->getSku();
				$SHOrderInfo->Items[] = $item;
			}

            Mage::helper('smarthint')->send($SHOrderInfo, "handleOrder", "https://api.smarthint.co/api/Order/");
		}
		catch  (Exception $e) {
			$error = new stdClass();
            $error->OrderError = $e->getMessage();
            $JSON = json_encode($error);
            Mage::helper('smarthint')->log($JSON);
		}
    }
    
    public function handleProduct($observer)
    {
        //Comentado pois estava dando erro ao criar produto simple pelo configurable 

		// try{
        //     $product = $observer->getEvent()->getProduct();
            
        //     if($product->getTypeId() != Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE){
        //         $SHProductInfo = Mage::helper('smarthint')->getProduct($product);
        //         Mage::helper('smarthint')->send($SHProductInfo, "handleProduct", "https://api.smarthint.co/api/Product/");            
        //     }
		// }
		// catch  (Exception $e) {
		// 	$error = new stdClass();
        //     $error->OrderError = $e->getMessage();
        //     $JSON = json_encode($error);
		// }
    }

    public function handleCategory($observer)
    {
		try{
            $cath = $observer->getEvent()->getCategory();
            $category = Mage::getModel('catalog/category')->load($cath->getId());
            $SHCategoryInfo = new stdClass();
            $SHCategoryInfo->CategoryId        = $category->getData('entity_id');
            $SHCategoryInfo->CategoryParentId  = $category->getData('parent_id');
            $SHCategoryInfo->Name              = $category->getName();
            $SHCategoryInfo->Url               = $category->getUrl();
            $SHCategoryInfo->FullPath          = $category->getUrl();
            $SHCategoryInfo->Level             = $category->getData('level');
            Mage::helper('smarthint')->send($SHCategoryInfo, "handleCategory", "https://api.smarthint.co/api/Category/");
		}
		catch  (Exception $e) {
			$error = new stdClass();
            $error->OrderError = $e->getMessage();
            $JSON = json_encode($error);
		}
    }

    public function identifiedConsumer($customer,$key){
        $consumerInfo = new stdClass();
        $consumerInfo->anonymousConsumer = $_COOKIE["SmartHint-AnonymousConsumer"];
        $consumerInfo->email = $customer["email"];
        $consumerInfo->nome = $customer["firstname"]." ".$customer["middlename"]." ".$customer["lastname"];
        $config = array();

        $param =  "p=". urlencode(json_encode($consumerInfo)) . "&key=".$key;
        $config[CURLOPT_SSL_VERIFYPEER] = false;
        $curl = new Varien_Http_Adapter_Curl();
        $curl->setConfig($config);
        $feed_url = "https://service.smarthint.co/track/consumer?".$param;
        $curl->write(Zend_Http_Client::GET, $feed_url, '1.0' );
        $data = $curl->read();

        if ($curl->getInfo(CURLINFO_HTTP_CODE) != 200) {
            $error = new stdClass();
            $error->function = "identifiedConsumer";
            $error->param = $param;
            $error->resposne = $data ;
            $JSON = json_encode($error);
            Mage::helper('smarthint')->log($JSON);
        }
        else {
            $response = preg_split('/^\\r?$/m', $data, 2);
            $response = trim($response[1]);
            $response = preg_replace("/^callback\(/", "", $response);
            $response = preg_replace("/\)$/i", "", $response);
            return json_decode($response)->IdentifiedConsumerId;
        }
        return false;
    }

    public function saveIdentfy($observer){

        $Identfy = new stdClass();
        $Identfy->nome = Mage::getStoreConfig('smarthint/identify/nome', $observer->store);
        $Identfy->email  = Mage::getStoreConfig('smarthint/identify/email', $observer->store);
        $Identfy->domain = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $opts = array();
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        $opts[CURLOPT_SSL_VERIFYPEER] = 0;

        $curl = new Varien_Http_Adapter_Curl();
        $header = array("Content-Type:application/json");
        $curl->setOptions($opts);
        $feed_url = "https://admin.smarthint.co/Account/NewMagentoUserAPI";
        
        $JSON = json_encode($Identfy);
        Mage::helper('smarthint')->log($JSON);
        $curl->write(Zend_Http_Client::POST, $feed_url, '1.0', $header, $JSON);
        $data = $curl->read();
        if ($curl->getInfo(CURLINFO_HTTP_CODE) != 200) {
            $error = new stdClass();
            $error->Identfy = $Identfy;
            $error->response =$data ;
            $JSON = json_encode($error);
            Mage::helper('smarthint')->log($JSON);
        }
        else{
            $response = preg_split('/^\\r?$/m', $data, 2);
            $response = trim($response[1]);
            $json = json_decode($response, true);

            if($observer->store == null){
                Mage::getConfig()->saveConfig('Smarthint/identify/SHcode', $json["SHCode"], "default", 0);
                Mage::getConfig()->saveConfig('Smarthint/identify/Token', $json["ApiToken"], "default", 0);
            }
            else {
                Mage::getConfig()->saveConfig('Smarthint/identify/SHcode', $json["SHCode"], "stores", Mage::getModel('core/store')->load($observer->store, 'code')->getId());
                Mage::getConfig()->saveConfig('Smarthint/identify/Token', $json["ApiToken"], "stores", Mage::getModel('core/store')->load($observer->store, 'code')->getId());
            }
            $this->chageScript($json["SHCode"], $observer);
            $this->callAsyncUrl();
        }
    }

    public function callAsyncUrl(){

        $url = Mage::getBaseUrl() . "smarthint/index";

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array()); 
        $mh = curl_multi_init();
        curl_multi_add_handle($mh,$ch);
        $running = 'idc';
        curl_multi_exec($mh,$running);
    }

    public function chageScript($SHcode, $observer){

        $script  = Mage::getStoreConfig('design/head/includes' , $observer->store);
        $script = preg_replace("/<!--START SMARTHINT-->(.*)<!--END SMARTHINT-->/i"," ",$script);
        $script .=  $this->getScript($SHcode);
        if($observer->store == null){
            Mage::getConfig()->saveConfig('design/head/includes', $script,  "default", 0);
        }
        else {
            Mage::getConfig()->saveConfig('design/head/includes', $script,  "stores", Mage::getModel('core/store')->load($observer->store, 'code')->getId());
        }
    }
    public function getScript ($SHcode) {
        return  "<!--START SMARTHINT--><script type='text/javascript'> var smarthintkey = '$SHcode';(function () {var script = document.createElement('script');script.type = 'text/javascript';script.async = true;script.src = 'https://service.smarthint.co/Scripts/i/magento.min.js';var s = document.getElementsByTagName('script')[0];s.parentNode.insertBefore(script, s);})();</script><!--END SMARTHINT-->";
    }
}