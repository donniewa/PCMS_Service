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
abstract class PCMS_Service_Pcms_Model_Abstract implements PCMS_Service_Pcms_Model
{
    protected $_config;
    protected $_client;
    protected $_siteId;
    protected $_cache;

    public function __construct(Zend_Config $config, $client, $intSiteId)
    {
        $this->_config      =   $config;
        $this->_client      =   $client->getClient();
        $this->_clientObj   =   $client;
        $this->_siteId      =   $intSiteId;
    }

    public function setCache($cacheObj)
    {
        $this->_cache    =    $cacheObj;
    }

    protected function getItemFromCache($id)
    {
        $return    =    null;
        if ($this->_cache) {
            $return    =    $this->_cache->load($this->_sanitizeIdForCache($id));
        }
        return($return);
    }

    protected function saveItemInCache($value, $id)
    {
        $return    =    null;
        if ($this->_cache) {
            $return    =    $this->_cache->save($value, $this->_sanitizeIdForCache($id));
        }
        return($return);
    }

    protected function _replyToRequest($result, $response, $format)
    {
        if (APPLICATION_ENV == 'local') {
            $headers    =    $response->getHeaders();
            if (isset($headers['Content-type']) && $headers['Content-type'] == 'text/html') {
                exit($result);
            }
        }

        switch ($response->getStatus()) {
            case 403:
                $this->_clientObj->destroy();
                break;
            case 404:
                return($this->returnError('Not Found', $format));
                break;
            default:
                return($this->returnObject($result, $format));
                break;
        }
        return($this->returnError('Not Found', $format));
    }

    protected function _sanitizeIdForCache($id)
    {
        return(preg_replace("'[^a-zA-Z0-9_]'", '', str_replace('/', '_', $id)));
    }

    protected function returnObject($result, $format)
    {
        $methodName    =    '_reply' . strtoupper($format);
        if (method_exists($this, $methodName) === false) {
            throw new DomainException('Format not valid. ('.$format.')');
        }
        return($this->$methodName($result));
    }

    protected function returnError($strError, $format)
    {
        switch (strtolower($format)) {
            case 'xml':
                return(new SimpleXMLElement('<error>'.$strError.'</error>'));
                break;
            case 'json':
                return(json_decode('{"error":"'.$strError.'"}'));
                break;
        }
    }

    private function _replyJSON($result)
    {
        $json    =    json_decode($result);
        return $json->response;
    }

    private function _replyXML($result)
    {
        return new SimpleXMLElement($result);
    }

    private function _getUrls()
    {
        $json    =    $this->getItemFromCache('Model_Abstract_getUrls');
        if ($json) {
            return(json_decode($json));
        } else {
            $config = array(
                'adapter'      => 'Zend_Http_Client_Adapter_Curl',
                'curloptions' => array(CURLOPT_FOLLOWLOCATION => true)
            );

            // Instantiate a client object
            $client   =    new Zend_Http_Client($this->_config->rest->serviceuri . 'configuration/', $config);

            // The following request will be sent over a TLS secure connection.
            $response =    $client->request()->getBody();
            $json     =    json_decode($response);
            $this->saveItemInCache($response, 'Model_Abstract_getUrls');
            return($json);
        }
    }

    protected function setUri($functionName, $strFormatType='xml', array $objReplace = array())
    {
        $urls    =    $this->_getUrls();
        if (!isset($urls->restUrls->$functionName)) {
            throw new DomainException('Missing URL Configuration');
        }
        $objReplace['{format}'] = $strFormatType;
        $urlPart    =    str_replace(
            array_keys($objReplace), array_values($objReplace), $urls->restUrls->$functionName
        );
        $this->_client->setUri($this->_config->rest->serviceuri . $urlPart);
    }

    protected function request($functionName, $strFormatType='xml', array $objReplace = array())
    {
        $this->setUri($functionName, $strFormatType, $objReplace);
        return($this->_client->request());
    }
}