<?php
class Smarthint_Recommendation_IndexController extends Mage_Core_Controller_Front_Action{

    public function indexAction()
    {
        //Processa produtos
        Mage::helper('smarthint')->processProduct();
        //Processa categoria
        Mage::helper('smarthint')->processCategory();
    }
}