<?php

class PCMS_Service_Directive_Json implements PCMS_Service_Directive
{
    private $_uri;
    private $_className;
    private $_method = 'GET';
    private $_body = null;
    private $_postData = array();
    private $_tags = array();
    private $_headers = array();

    /**
     * Creates a Directive that the client can execute.
     * @param String $uri
     * @param String $className
     */
    public function __construct($uri = null, $className = PCMS_Service_Fetch::DEFAULT_CLASS, $method = 'GET')
    {
        $this->setUri($uri);
        $this->setClassName($className);
        $this->setMethod($method);
    }

    /**
     * (non-PHPdoc)
     * @see PCMS_Service_Directive_Interface::getMethod()
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * (non-PHPdoc)
     * @see PCMS_Service_Directive_Interface::setMethod()
     */
    public function setMethod($method)
    {
        $this->_method = $method;
    }

    /**
     * (non-PHPdoc)
     * @see PCMS_Service_Directive_Interface::getClassName()
     */
    public function getClassName()
    {
        return $this->_className;
    }

    /**
     * (non-PHPdoc)
     * @see PCMS_Service_Directive_Interface::setClassName()
     */
    public function setClassName($className)
    {
        $this->_className = $className;
    }

    /**
     * (non-PHPdoc)
     * @see PCMS_Service_Directive_Interface::getUri()
     */
    public function getUri()
    {
        return $this->_uri;
    }

    /**
     * (non-PHPdoc)
     * @see PCMS_Service_Directive_Interface::setUri()
     */
    public function setUri($uri)
    {
        $this->_uri = $uri;
    }

    /**
     * (non-PHPdoc)
     * @see PCMS_Service_Directive_Interface::getBody()
     */
    public function getBody()
    {
        return $this->_body;
    }

    /**
     * (non-PHPdoc)
     * @see PCMS_Service_Directive_Interface::setBody()
     */
    public function setBody($body)
    {
        $this->_body = $body;
    }

    /**
     * Sets the post data for this directive
     * @param array $postData
     */
    public function setPostData(array $postData)
    {
        $this->_postData = $postData;
    }

    /**
     * Returns an associative array of the post data we need
     * to send when this directive is called.
     * @return array
     */
    public function getPostData()
    {
        return $this->_postData;
    }

    /**
     * Sets the tags for caching this directive
     * @param array $tags
     */
    public function setTags(array $tags)
    {
        $this->_tags = array_merge($this->_tags, $tags);
    }

    /**
     * returns the tags for caching this directive
     * @return array
     */
    public function getTags()
    {
        return $this->_tags;
    }

    /**
     * returns the headers
     * @return array
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * (non-PHPdoc)
     * @see PCMS_Service_Directive_Interface::processResponse()
     */
    public function processResponse($response)
    {
        $className = $this->getClassName();
        $model = new $className($response);

        if ($response->isSuccessful()) {
            $json = @json_decode($response->getBody());
            if ($json !== null) {
                foreach ($json as $key => $value) {
                    $model->{$key} = $value;
                }
            } else {
                if (APPLICATION_ENV === 'local' || APPLICATION_ENV === 'development') {
                    echo 'Bad JSON Response:<br />';
                    echo '---------------------------------------';
                    echo $response->getBody();
                    var_dump($response);
                    echo '---------------------------------------';
                    exit;
                }
                throw new Zend_Exception('Unable to load JSON', 500);
            }

        } else {
            return $model;
        }
        return $model;
    }
}