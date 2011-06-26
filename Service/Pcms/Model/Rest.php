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
class Ocml_Service_Pcms_Model_Rest extends Ocml_Service_Pcms_Model_Abstract 
{
    /**
     * Returns a formatted string from the PCMS system
     * that will contain the datamodel for the page id
     * passed into it.  You may also pass in the type
     * as xml or json formatted types.
     *
     * @param String $pageId
     * @param String [$strFormatType]
     * @return String
     * @author  donniewa
     */
    public function getPage($strPageId, $strFormatType = 'xml')
    {
        if ($this->_client) {
            $strPageId  =   str_replace('/', '-s-', $strPageId);
            $strPageId  =   htmlspecialchars($strPageId);

            $result     =    $this->getItemFromCache($strPageId.$strFormatType);
            if ($result) {
                return($this->returnObject($result, $strFormatType));
            } else {
                $response   =    $this->request('getPage', $strFormatType, array('{pageid}' => $strPageId));
            $result     =   $response->getBody();

                $this->saveItemInCache($result, $strPageId.$strFormatType);
                return($this->_replyToRequest($result, $response, $strFormatType));
        }
        }
        
        return($this->returnError('No Client', $format));
    }
}