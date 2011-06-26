<?php
/**************************************************
 * Original Author: Donald White
 * $Id: $
 * $Author: $
 * $Revision: $
 * $Change: $
 * $Date: $
 * @package Ocml_Service
 * @subpackage Pcms
 * @copyright Copyright (c) 2009-2011 Pixelrot Consulting
 *
 **************************************************/

/**
 * 
 * @author donniewa
 */
class Ocml_Service_Pcms 
{
    protected $_config;
    protected $_model;
    protected $_client;
    protected $_siteId;
    
    /**
     * loads configuration, and adds it as a protected variable for this class
     */
    public function __construct($siteId, $cacheModelObj = null, $cacheClientObj = null)
    {
        if (empty($siteId)) {
            throw new DomainException('You must pass the site id into the constructor.');
        }
        $this->_siteId  =   $siteId;
        $this->_config  =   new Zend_Config_ini(CONFIG_PATH . '/Services/Pcms.ini', APPLICATION_ENV);
        
        try {
            $this->_client  =   new Ocml_Service_Pcms_Client_Oauth($this->_config, $this->_siteId, $cacheClientObj);
        } catch (DomainException $e) {
            echo $e->getMessage();
        }
        
        $this->_model   =   new Ocml_Service_Pcms_Model_Rest($this->_config, $this->_client, $this->_siteId);

        if ($cacheModelObj) {
            $this->_model->setCache($cacheModelObj);
    }
    }
    
    /**
     * returns the Service Client
     */
    public function client()
    {
        return($this->_client);
    }
    
    /**
     * returns the Model
     * allows for chaining
     */
    public function model()
    {
        return($this->_model);
    }
}