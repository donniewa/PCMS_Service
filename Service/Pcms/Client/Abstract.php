<?php
/**************************************************
 * Original Author: Donald White
 * $Id: $
 * $Author: $
 * $Revision: $
 * $Change: $
 * $Date: $
 * @package PCMS_Service
 * @subpackage Pcms
 * @copyright Copyright (c) 2009-2011 Pixelrot Consulting
 *
 **************************************************/

/**
 *
 * @author donniewa
 */
abstract class PCMS_Service_Pcms_Client_Abstract implements PCMS_Service_Pcms_Client
{
    protected $_config;
    protected $_siteId;
    protected $_cache;

    public function __construct(Zend_Config $config, $intSiteId, $cacheObj=null)
    {
        $this->_config  =   $config;
        $this->_siteId  =   $intSiteId;
        if ($cacheObj) $this->setCache($cacheObj);
    }

    public function setCache($cacheObj)
    {
        $this->_cache    =    $cacheObj;
    }
}