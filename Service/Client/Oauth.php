<?php
/**
 * @deprecated
 */
class PCMS_Service_Client_Oauth extends Zend_Http_Client
{
    const REQUEST = 'request';
    const ACCESS = 'access';

    /**
     * @var Zend_Oauth_Consumer $_consumer
     */
    private $_consumer;

    /**
     * @var Zend_Config $_config
     */
    private $_config;

    /**
     * @var Zend_Cache $_cache
     */
    private $_cache;

    /**
     * @var integer $_requestCount
     */
    private $_requestCount = 0;

    /**
     * @var Array $_clientConfig
     */
    private $_clientConfig;

    /**
     * @var string $_uri
     */
    private $_uri;

    /**
     * Constructs the client executor
     * @param array $config
     * @param Zend_Cache $clientCache
     * @param string $uri
     * @param array $options
     */
    public function __construct($config, Zend_Cache $clientCache, $uri=null, $options=null)
    {
        parent::__construct($uri, $options);
        $this->_config = $config;
        $this->_cache = $clientCache;
        $this->_uri = $uri;
        $this->_options = $options;
        $this->_consumer = new Zend_Oauth_Consumer($config['client']['oauth']);
        $this->_consumer->setHttpClient($this);
        $this->_clientConfig = $config['client']['config'];

        if (isset($this->_config['api']['headers'])) {
            $this->setHeaders($this->_config['api']['headers']);
        }
    }

    /**
     * Returns this class or the OAuth Zend_Http_Client
     * @return Zend_Http_Client|PCMS_Service_Client_Oauth
     */
    public function getClient()
    {
        /**
        * Check for the access token. If that doesn't exist, well have to
        * do a request.
        */
        $token = $this->_loadToken(self::ACCESS);
        if ($token instanceof Zend_Oauth_Token_Access || $this->authorize() === true) {
            $client = $this->_loadToken(self::ACCESS)->getHttpClient(
                $this->_clientConfig, $this->_uri, $this->_options
            );

            /**
             * set the same headers as we did in the beginning
             */
            if (isset($this->_config['api']['headers'])) {
                $client->setHeaders($this->_config['api']['headers']);
            }
            return $client;
        }
    }

    /**
     * Returns the headers that have been set.
     * @return array
     */
    public function getHeaders() {
        return $this->headers;
    }

    /**
     * Authorizes the users token
     * @throws Zend_Exception
     * @return boolean
     */
    public function authorize()
    {
        $requestToken = $this->_loadToken(self::REQUEST);
        if ($requestToken instanceof Zend_Oauth_Token_Request) {
            try {
                $accessToken = $this->_consumer->getAccessToken($_GET, $requestToken);

                if ($accessToken instanceof Zend_Oauth_Token_Access) {
                    $this->_saveToken(self::ACCESS, $accessToken);
                    return true;
                }
            } catch (Zend_Exception $e) {
                $this->_requestCount = 0;
            }
        }

        /**
         * If this user doesn't have a valid token, let's request another.
         * and to prevent loops, in case the storage isn't being implemented
         * we will only allow one request here.
         */
        $this->_requestCount ++;
        if ($this->_requestCount === 1) {
            $token = $this->_consumer->getRequestToken();

            if ($token->oauth_error) {
                throw new Zend_Oauth_Exception($token->oauth_error);
            }

            if ($this->_saveToken(self::REQUEST, $token) === true || $this->_config['client']['redirect'] === true) {
                $this->_consumer->redirect();
            }
        } else {
            throw new Zend_Exception('We have detected too many requests in the same loop.');
        }
    }

    /**
     * Loads the token from cache
     * @param String $type
     * @throws Zend_Exception
     * @return Zend_Oauth_Token
     */
    private function _loadToken($type)
    {
        $id = $this->_getCacheId($type);
        $token = null;

        if ($this->_cache) {
            $token = $this->_cache->load($id);
        }

        if ($token instanceof Zend_Oauth_Token) {
            // @codingStandardsIgnoreStart
            if ($token->oauth_error) {
                // @codingStandardsIgnoreEnd
                throw new Zend_Oauth_Exception($token->oauth_error);
            }
        }

        return $token;
    }

    /**
     * Returns the cache id for the given type
     * @param string $type
     * @throws Zend_Exception
     * @return string
     */
    private function _getCacheId($type)
    {
        if (isset($this->_config['client']['cacheIds'][$type]) === false) {
            throw new Zend_Exception("unable to find the cache id for this token type: ({$type})");
        }
        return $this->_config['client']['cacheIds'][$type];
    }

    /**
     * Persists the given token to storage
     * @param string $type
     * @param Zend_Oauth_Token $token
     */
    private function _saveToken($type, $token)
    {
        $id = $this->_getCacheId($type);
        if ($this->_cache) {
            $this->_cache->save($token, $id);
            // Now that we have an Access Token, we can discard the Request Token
            if ($type === 'access') {
                $this->_saveToken('request', null);
            }
        }
        return($token);
    }
}