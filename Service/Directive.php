<?php
/**
 * Directive Interface
 * @author donwhite
 */
interface PCMS_Service_Directive
{
    /**
     * Returns the set uri
     */
    public function getUri();

    /**
     * Sets the uri to fetch
     * @param string $serviceurl
     */
    public function setUri($serviceurl);

    /**
     * Returns the retrieve method
     */
    public function getMethod();

    /**
     * Sets the retrieve method
     * @param string $method
     */
    public function setMethod($method);

    /**
     * Returns the return className
     */
    public function getClassName();

    /**
     * Sets the retrieve class name
     * @param string $className
     */
    public function setClassName($className);

    /**
     * Returns tags for caching
     */
    public function getTags();

    /**
     * Sets the tags for caching
     * @param array $tags
     */
    public function setTags(array $tags);

    /**
     * Returns the body of the request
     */
    public function getBody();

    /**
     * Sets the body for the request
     * @param string $body
     */
    public function setBody($body);

    /**
     * Returns the processed response
     * @param Zend_Http_Response $response
     */
    public function processResponse($response);
}