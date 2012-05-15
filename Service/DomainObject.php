<?php

class PCMS_Service_DomainObject extends stdClass
{

    /**
     * @var Zend_Http_Response
     */
    private $_response;

    public function __construct($response)
    {
        $this->_response = $response;
    }

    public function __call($name, $arguments)
    {
        if (isset($this->$name) === true && $this->$name instanceof Closure) {
            $function = $this->$name;
            return call_user_func_array($function, $arguments);
        }

        throw new Zend_Exception('Call to undefined method: ' . $name, 500);
    }

    /**
     * Returns a boolean for the response error.
     * @see Zend_Http_Response::isError
     * @return boolean
     */
    public function hasErrors()
    {
        return $this->_response->isError();
    }

    /**
     * Returns a boolean for the response successful.
     * @see Zend_Http_Response::isError
     * @return boolean
     */
    public function isSuccessful()
    {
        return $this->_response->isSuccessful();
    }

    /**
     * Returns the response object
     * @return Zend_Http_Response
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * Will use the given linkname to fetch the object specified from the service
     * @param string $linkName
     * @param string $className [optional] defaults to: PCMS_Service_Fetch::DEFAULT_CLASS
     */
    public function fetch($linkName, $className = PCMS_Service_Fetch::DEFAULT_CLASS)
    {
        $fetchModel = new PCMS_Service_Fetch;
        $fetchModel->fetch($this, $linkName, $className);
    }

    /**
     * Will use the given linkname to fetch an array of objects from the serivce
     * @param string $linkName
     * @param string $className [optional] defaults to: PCMS_Service_Fetch::DEFAULT_CLASS
     */
    public function fetchAll($linkName, $className = PCMS_Service_Fetch::DEFAULT_CLASS)
    {
        $fetchModel = new PCMS_Service_Fetch;
        $fetchModel->fetchAll($this, $linkName, $className);
    }

    /**
     * Will use the given linkname to fetch an array of objects from the serivce
     * @param string $linkName
     * @param string $className [optional] defaults to: PCMS_Service_Fetch::DEFAULT_CLASS
     */
    public function fetchMultiple(array &$models, $linkName, $className = PCMS_Service_Fetch::DEFAULT_CLASS)
    {
        $fetchModel = new PCMS_Service_Fetch;
        $models = $fetchModel->fetchMultiple($models, $linkName, $className);
        return $models;
    }

    /**
     * Returns the objects in the given array that match the objectType and modelName passed
     * @param string $objectType [optional]
     * @param string $modelName [optional]
     * @param array $reference [optional]
     */
    public function get($objectType = null, $modelName = null, array $reference = null)
    {
        $return = array();
        if (empty($reference)) {
            $reference = $this->objects;
        }

        foreach ($reference as $key => $object) {
            if (!empty($objectType) && isset($object->objectType) &&  $object->objectType === $objectType) {
                // Maintain keys
                $return[$key] = $object;
            }

            if (!empty($modelName) && isset($object->modelName) &&  $object->modelName === $modelName) {
                // Maintain keys
                $return[$key] = $object;
            }
        }

        return $return;
    }
}