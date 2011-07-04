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
interface PCMS_Service_Pcms_Model
{
    public function __construct(Zend_Config $config, $client, $intSiteId);
    public function getPage($pageId, $strFormatType = 'xml');
}