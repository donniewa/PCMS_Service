<?php

class PCMS_Workflow
{
    const PUBLIC_CONFIGURATION_REGISTRY_KEY = 'PCMS_PUBLIC_CONFIGURATION';
    const SHARED_MEMORY = 'PCMS_SHARED_MEMORY';

    /**
     * @var Zend_Config $_config
     */
    protected $_config;

    /**
     * @var PCMS_Service_Directive_Json $_directive
     */
    protected $_directive;

    /**
     * @var PCMS_UriBuilder $_uriBuilder
     */
    protected $_uriBuilder;

    /**
     * @var PCMS_Service_Client
     */
    protected $_client;

    protected $_publicConfig = null;

    /**
     * Creates an instance of the Personal CMS Workflow, using the configuration passed, and sets the provided caches.
     * @param Zend_Config $config
     */
    public function __construct(Zend_Config $config)
    {
        Zend_Registry::set(self::SHARED_MEMORY, array());
        $this->_config = $config;
        $this->_directive = new PCMS_Service_Directive_Json();
        $this->_client = new PCMS_Service_Client($config);
        $this->_uriBuilder = $this->getUriBuilder();

        Zend_Registry::set('PCMS_CLIENT', $this->_client);
    }

    /**
     * Will pull the adapter from the client's cache and clean it.
     * @throws Zend_Exception
     * @return bool
     */
    public function clearCache()
    {
        $adapter = $this->getClient()->getCacheAdapter();
        $cacheObject = $adapter->getCacheObject();
        $clean = $cacheObject->clean();
        if (!$clean) {
            throw new Zend_Exception('Unable to clean cache');
        }
        return $clean;
    }

    /**
     * Sets the site id to access.
     * @param int $id
     */
    public function setSiteId($id)
    {
        if (empty($id)) {
            throw new Zend_Exception('the Site id cannot be null!');
        }
        $this->_client = clone $this->getClient();
        $this->_client->setSiteId($id);
    }

    /**
     * returns the instnce of the pcms_client
     * @return PCMS_Client
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * returns the public confguration data from PCMS. Holds all entry point urls.
     * @return PCMS_Service_DomainObject
     */
    public function getPublicConfiguration()
    {
        if (($memory = $this->inMemory('public-config')) !== null) {
            return $memory;
        }
        $result = $this->getClient()->fetch(new PCMS_Service_Directive_Json($this->_config->api->uri));
        if ($result) {
            $this->stashMemory('public-config', $result->services);
            return $result->services;
        }
    }

    public function inMemory($key)
    {
        $memory = Zend_Registry::get(self::SHARED_MEMORY);
        if (isset($memory[$key])) {
            return $memory[$key];
        }
        return null;
    }

    public function stashMemory($key, $value)
    {
        $memory = Zend_Registry::get(self::SHARED_MEMORY);
        $memory[$key] = $value;
        Zend_Registry::set(self::SHARED_MEMORY, $memory);
    }

    public function clearMemory()
    {
        Zend_Registry::set(self::SHARED_MEMORY, array());
    }

    /**
     * returns the uri builder instance
     * @return PCMS_UriBuilder
     */
    public function getUriBuilder()
    {
        $config = $this->getPublicConfiguration();
        $request = Zend_Controller_Front::getInstance()->getRequest();
        return new PCMS_UriBuilder($request, $config);
    }

    /**
     * returns the page container by uri from PCMS.
     * @param string $pageUri
     * @return PCMS_Service_DomainObject
     */
    public function getPage($pageUri)
    {
        if (($memory = $this->inMemory($pageUri)) !== null) {
            return $memory;
        }

        $uri = $this->_uriBuilder->build('object|uri', array('id' => $pageUri));
        $directive = new PCMS_Service_Directive_Json($uri);
        $directive->setTags(array($pageUri));
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($pageUri, $result);
            return $result;
        }
        return null;
    }

    /**
     * Returns the assembled object by it's id from PCMS
     * @param string $id
     * @return PCMS_Service_DomainObject
     */
    public function getObjectById($id)
    {
        $cacheid = "object-{$id}";
        if (($memory = $this->inMemory($cacheid)) !== null) {
            return $memory;
        }
        $uri = $this->_uriBuilder->build('object|get', array('id' => $id));
        $directive = new PCMS_Service_Directive_Json($uri);
        $directive->setTags(array($id));
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($cacheid, $result);
            return $result;
        }
        return null;
    }

    /**
     * Returns the data about a given section id from the section service in PCMS
     * @param string $id
     * @return PCMS_Service_DomainObject
     */
    public function getSectionData($id)
    {
        $cacheid = "getSectionData-{$id}";
        if (($memory = $this->inMemory($cacheid)) !== null) {
            return $memory;
        }
        $uri = $this->_uriBuilder->build('section|get', array('id' => $id));
        $directive = new PCMS_Service_Directive_Json($uri);
        $directive->setTags(array($id, 'sectionTree'));
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($cacheid, $result);
            return $result;
        }
        return null;
    }

    /**
     * Returns the list information for the specific property id
     * @param string $id
     * @return PCMS_Service_DomainObject
     */
    public function getListData($id)
    {
        $cacheid = "getListData-{$id}";
        if (($memory = $this->inMemory($cacheid)) !== null) {
            return $memory;
        }
        $uri = $this->_uriBuilder->build('list|get', array('id' => $id));
        $directive = new PCMS_Service_Directive_Json($uri);
        $directive->setTags(array($id));
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($cacheid, $result);
            return $result;
        }
        return null;
    }

    /**
     * Returns the entire section tree in a flattened format
     * @return PCMS_Service_DomainObject
     */
    public function getFlatSectionTree()
    {
        $cacheid = "getFlatSectionTree";
        if (($memory = $this->inMemory($cacheid)) !== null) {
            return $memory;
        }
        $uri = $this->_uriBuilder->build('section|flatSectionTree');
        $directive = new PCMS_Service_Directive_Json($uri);
        $directive->setTags(array('sectionTree'));
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($cacheid, $result);
            return $result;
        }
        return null;
    }

    /**
     * Returns the entire section tree in a flattened format
     * @return PCMS_Service_DomainObject
     */
    public function reverseLookupSection($id)
    {
        $cacheid = "reverseLookupSection-{$id}";
        if (($memory = $this->inMemory($cacheid)) !== null) {
            return $memory;
        }
        $uri = $this->_uriBuilder->build('section|reverseLookup', array('id' => $id));
        $directive = new PCMS_Service_Directive_Json($uri);
        $directive->setTags(array($id, 'sectionTree'));
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($cacheid, $result);
            return $result;
        } else if ($result->getResponse()->getStatusCode() === 404) {
            return $this->reverseLookupSection(1);
        }
        return null;
    }

    /**
     * Returns The tagged items with the given tag
     * @return PCMS_Service_DomainObject
     */
    public function searchTags($id)
    {
        $cacheid = "searchTags-{$id}";
        if (($memory = $this->inMemory($cacheid)) !== null) {
            return $memory;
        }
        $uri = $this->_uriBuilder->build('search|tags', array('id' => $id));
        $directive = new PCMS_Service_Directive_Json($uri);
        $directive->setTags(array($id));
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($cacheid, $result);
            return $result;
        }
        return null;
    }

    /**
     * Returns the items with the given model name
     * @return PCMS_Service_DomainObject
     */
    public function searchModelName($id)
    {
        $cacheid = "searchModelName-{$id}";
        if (($memory = $this->inMemory($cacheid)) !== null) {
            return $memory;
        }
        $uri = $this->_uriBuilder->build('search|modelname', array('id' => $id));
        $directive = new PCMS_Service_Directive_Json($uri);
        $directive->setTags(array($id));
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($cacheid, $result);
            return $result;
        }
        return null;
    }

    /**
     * Returns All the tags in the system
     * @return PCMS_Service_DomainObject
     */
    public function getAllTags()
    {
        $cacheid = "getAllTags";
        if (($memory = $this->inMemory($cacheid)) !== null) {
            return $memory;
        }
        $uri = $this->_uriBuilder->build('search|alltags');
        $directive = new PCMS_Service_Directive_Json($uri);
        $directive->setTags(array('tags'));
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($cacheid, $result);
            return $result;
        }
        return null;
    }

    /**
     * Returns All the Images in the system
     * @return PCMS_Service_DomainObject
     */
    public function getAllImages()
    {
        $cacheid = "getAllImages";
        if (($memory = $this->inMemory($cacheid)) !== null) {
            return $memory;
        }
        $uri = $this->_uriBuilder->build('media');
        $directive = new PCMS_Service_Directive_Json($uri);
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($cacheid, $result);
            return $result;
        }
        return null;
    }

    /**
     * allows you to pull the passed url directly
     * @param string $uri
     */
    public function getUri($uri)
    {
        $cacheid = "geturi-{$uri}";
        if (($memory = $this->inMemory($cacheid)) !== null) {
            return $memory;
        }
        $directive = new PCMS_Service_Directive_Json($uri);
        $result = $this->getClient()->fetch($directive);
        if ($result->isSuccessful()) {
            $this->stashMemory($cacheid, $result);
            return $result;
        }
        return null;
    }


}