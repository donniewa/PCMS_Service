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
class PCMS_Service_Pcms_Model_Rest extends PCMS_Service_Pcms_Model_Abstract
{
    protected function _sanitizeForServiceUrl($stringItem)
    {
        $stringItem     =    str_replace('/', '-s-', $stringItem);
        $stringItem     =    htmlspecialchars($stringItem);
        return($stringItem);
    }

    protected function getData($serviceMethod, $itemId, $format, $options = array())
    {
        if ($this->_client) {
            $itemId     =    $this->_sanitizeForServiceUrl($itemId);
            $options    =    array_map(array($this, '_sanitizeForServiceUrl'), $options);

            $cacheId    =    $serviceMethod.$itemId.$format;
            $result     =    $this->getItemFromCache($cacheId);
            if ($result) {
                return($this->returnObject($result, $format));
            } else {
                $response   =    $this->request($serviceMethod, $format, $options);
                $result     =    $response->getBody();

                $this->saveItemInCache($result, $cacheId);
                return($this->_replyToRequest($result, $response, $format));
            }
        }

        return($this->returnError('No Client', $format));
    }

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
        return($this->getData('getPage', $strPageId, $strFormatType, array('{pageid}' => $strPageId)));
    }

    /**
     * Returns a list of elements that match the given tag.
     * @param string $strTag
     * @param string $strFormatType
     * @return Object
     * @author donniewa
     */
    public function searchTag($strTag, $strFormatType = 'xml')
    {
        return($this->getData('searchTag', $strTag, $strFormatType, array('{tag}' => $strTag)));
    }

    /**
     * Returns a list of elements that match the given uuid
     * @param string $strObjectUuid
     * @param string $strFormatType
     * @return Object
     * @author donniewa
     */
    public function getObjectById($strObjectUuid, $strFormatType = 'xml')
    {
        return(
            $this->getData(
            	'getObjectByModelName', $strObjectUuid, $strFormatType, array('{objectid}' => $strObjectUuid)
            )
        );
    }

    /**
     * Returns a list of elements that match the given modelname
     * @param string $strModelName
     * @param string $strFormatType
     * @return Object
     * @author donniewa
     */
    public function getObjectByModelName($strModelName, $strFormatType = 'xml')
    {
        return(
            $this->getData(
            	'getObjectByModelName', $strModelName, $strFormatType, array('{modelname}' => $strModelName)
            )
        );
    }

}