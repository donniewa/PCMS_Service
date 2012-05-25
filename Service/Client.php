<?php
/**
 * Main PCMS Client
 */
use Guzzle\Common\Cache\Zf1CacheAdapter;
use Guzzle\Common\Event;
use Guzzle\Common\Log\Zf1LogAdapter;
use Guzzle\Service\Client;
use Guzzle\Http\Curl\CurlVersion;
use Guzzle\Http\Plugin\CachePlugin;
use Guzzle\Http\Plugin\ExponentialBackoffPlugin;
use Guzzle\Http\Plugin\LogPlugin;

class PCMS_Service_Client
{
    const VERSION = '1.0';

    /**
     * @var array
     */
    protected $_config;

    /**
     * @var array
     */
    protected $_clientConfig;

    /**
     * @var Zend_Cache $_serviceCache
     */
    protected $_serviceCache;

    /**
     * @var Zend_Cache_Core $_clientCache
     */
    protected $_clientCache;

    public $siteid;

    protected $_adapter = null;

    /**
     * @var array
     */
    protected $_defaultHeaders = array();

    public function __construct(Zend_Config $config)
    {
        $this->_config = $config->toArray();
        $this->_defaultHeaders = array(
            'accept' => 'application/json',
            'content-type' => 'application/json'
        );
    }

    public function addHeader($key, $value)
    {
        $this->_defaultHeaders[strtolower($key)] = $value;
    }

    public function addHeaders($array)
    {
        array_walk($array, array($this, 'addHeaders'));
    }

    public function getHeader($key)
    {
        return isset($this->_defaultHeaders[strtolower($key)]) ? $this->_defaultHeaders[strtolower($key)] : null;
    }

    public function getHeaders()
    {
        if (!$this->getHeader("user-agent")) {
            $curl = CurlVersion::getInstance();
            $this->_defaultHeaders["user-agent"] = sprintf('(PCMS) Public Connector/v.%s (PHP=%s; curl=%s; openssl=%s)',
                self::VERSION,
                \PHP_VERSION,
                $curl->get('version'),
                $curl->get('ssl_version')
            );
        }

        $this->_defaultHeaders = array_merge($this->_defaultHeaders, $this->_config['api']['headers']);
        return $this->_defaultHeaders;
    }

    /**
     * Processes a commands settings and returns a request for execution
     * @param PCMS_Service_Directive $command
     * @param array $config [Optional]
     * @return Guzzle\Message\Request $request
     */
    protected function _prepareRequest(PCMS_Service_Directive $directive, $config = array())
    {
        $uri = $directive->getUri();
        $body = $directive->getBody();
        $method = $directive->getMethod();
        $postdata = $directive->getPostData();
        $defaultHeaders = $this->getHeaders();

        $headers = array_merge($defaultHeaders, $directive->getHeaders());
        /**
         * @var Guzzle\Service\Client
         */
        $config = array_merge(array('curl.CURLOPT_FOLLOWLOCATION' => false), $config);
        $client = new Client($uri, $config);
        /**
         * @var Guzzle\Http\Message\Request
         */

        if (!empty($postdata)) {
            $headers['content-type'] = 'application/json';
            $body = json_encode($postdata);
        }

        /**
         * add a request id here to make sure to force no-cache
         */
        if (strtolower($method) !== 'get') {
            $headers['x-request-id'] = md5(time() . rand(0,999));
        }
        $request = $client->createRequest($method, $uri, $headers, $body);
        // Add cache controls here
        $this->addSubscribers($request);
        return $request;
    }

    /**
     * Allows for the addition of suscribers to each request globally
     * @param Guzzle\Http\Message\RequestInterface &$request [REFERENCE]
     */
    public function addSubscribers(Guzzle\Http\Message\RequestInterface &$request)
    {
        $this->_addRedirectSubscriber($request);
        $this->_addCacheSubscriber($request);
        $this->_addOauthSubscriber($request);
        $this->_addLogSubscriber($request);
    }


    /**
     * @param Guzzle\Http\Message\RequestInterface &$request [REFERENCE]
     */
    protected function _addLogSubscriber(Guzzle\Http\Message\RequestInterface &$request)
    {
        $logger = Ocml_Core_Log::getLog();
        $adapter = new Zf1LogAdapter($logger);
        $logPlugin = new LogPlugin($adapter, LogPlugin::LOG_HEADERS);
        $request->getEventDispatcher()->addSubscriber($logPlugin);
    }

    /**
     * Returns the CacheAdapter so we can have the admin clear their cache when necessary.
     * @return Guzzle\Common\Cache\AbstractCacheAdapter
     */
    public function getCacheAdapter()
    {
        if (($adapter = $this->_adapter) === null) {
            $options = array(
                'cache_dir' => CACHE_PATH,
                'file_name_prefix' => "gz_{$this->siteid}_",
                "hashed_directory_level" => "2",
                "hashed_directory_umask" => "0777"
            );
            $backend = new Zend_Cache_Backend_File($options);
            $adapter = new Zf1CacheAdapter($backend);
        }
        return $adapter;
    }

    /**
     * @param Guzzle\Http\Message\RequestInterface &$request [REFERENCE]
     */
    protected function _addCacheSubscriber(Guzzle\Http\Message\RequestInterface &$request)
    {
        $adapter = $this->getCacheAdapter();
        $cache = new CachePlugin($adapter, true);
        $request->getEventDispatcher()->addSubscriber($cache);
    }

    /**
     * @param Guzzle\Http\Message\RequestInterface &$request [REFERENCE]
     */
    protected function _addOauthSubscriber(Guzzle\Http\Message\RequestInterface &$request)
    {
        $options = array(
            'cache_dir' => CACHE_PATH,
            'file_name_prefix' => 'oauth',
            "hashed_directory_level" => "2",
            "hashed_directory_umask" => "0777",
        );
        $backend = new Zend_Cache_Backend_File($options);
        $backend->setDirectives(array('lifetime' => null));
        $adapter = new Zf1CacheAdapter($backend);

        $oauthPlugin = new PCMS_Service_Plugin_Oauth($this->_config['client']['oauth']);
        $oauthPlugin->setCacheAdapter($adapter, true);

        $token = $oauthPlugin->loadAccessToken();
        if ($token instanceof OAuthToken) {
            $oauth = new Guzzle\Http\Plugin\OauthPlugin(array(
                'consumer_key'    => $this->_config['client']['oauth']['consumerKey'],
                'consumer_secret' => $this->_config['client']['oauth']['consumerSecret'],
                'token'           => $token->key,
                'token_secret'    => $token->secret
            ));
            $request->getEventDispatcher()->addSubscriber($oauth);
        } else {
            // start the process of being authorized here.
            $token = $oauthPlugin->authorize();
            if ($token) {
                $this->_addOauthSubscriber($request);
            }
        }
    }

    /**
     * @param Guzzle\Http\Message\RequestInterface &$request [REFERENCE]
     */
    protected function _addRedirectSubscriber(Guzzle\Http\Message\RequestInterface &$request)
    {
        $self = $this;
        $request->getEventDispatcher()->addListener(
            'request.complete',
            function(Event $event) use (&$request, $self)
            {
                $redirectHeader = 'x-redirect-count';
                $response = $request->getResponse();
                if ($response instanceof Guzzle\Http\Message\ResponseInterface) {
                    $code = $response->getStatusCode();
                    // Only redirect the proper elements
                    $redirect = array(300, 301, 302, 303, 307, 308);
                    if ($response->isRedirect() && in_array($code, $redirect)) {
                        $count = (string) $response->getHeader($redirectHeader) ?: 0;
                        if ($count <= 0) {
                            $method = 'GET';
                            $uri = (string) $response->getHeader('location');
                            if (!empty($uri)) {
                                $headers = $self->getHeaders();
                                // Have to set the content lenghth back to zero.
                                $headers['content-length'] = 0;
                                $headers[$redirectHeader] = ++$count;
                                $config = $request->getClient()->getConfig();
                                $client = new Client(null, $config);

                                /**
                                 * Using a new request object here.  We'll follow the response
                                 * automatically, then update the original request reference
                                 * to contain the new response.
                                 *
                                 * @var Guzzle\Http\Message\RequestInterface
                                 */
                                $followRequest = $client->createRequest($method, $uri, $headers);
                                $self->addSubscribers($followRequest);
                                $followedResponse = $followRequest->send();
                                // Updating the original response to make it seamless.
                                $request->setResponse($followedResponse);
                            }
                        }
                    }
                }
            }
        );
    }

    /**
     * Will fetch the given directive. or the array of directives
     * @param PCMS_Service_Directive | array $directive
     */
    public function fetch($directive)
    {

        if ($directive instanceof PCMS_Service_Directive) {
            return $this->_executeSingle($directive);
        } elseif (is_array($directive)) {
            return $this->_executeMultiple($directive);
        }

        throw new Zend_Exception('Unable to execute directive type: ' . get_class($directive));
    }

    /**
     * Will set the site id for the client
     * @param string $id
     */
    public function setSiteId($id)
    {
        $this->siteid = $id;
        $this->_config['api']['headers']['X-pcms-api-siteid'] = $id;
    }

    /**
     * Sets the parameters for this directive so we can pass data to the system
     * @param Zend_Http_Client $client [REFERENCE OBJECT]
     * @param PCMS_Service_Directive $directive
     */
    protected function _setParams(Zend_Http_Client &$client, PCMS_Service_Directive $directive)
    {
        $postData = $directive->getPostData();
        if (!empty($postData) && is_array($postData)) {
            /**
             * The post data is always an array, however we can't just set all the parameters
             * at once.  We need to loop through and check for any parameters that are themselves
             * arrays.  That way we can set those at once.
             */
            $methodName = ($directive->getMethod() !== Zend_Http_Client::GET) ? 'setParameterPost' : 'setParameterGet';
            foreach ($postData as $key => $value) {
                if (is_array($value)) {
                    $count = count($value);
                    for ($i = 0; $i < $count; $i++) {
                        $client->$methodName("{$key}[{$i}]", $value[$i]);
                    }
                } else {
                    $client->$methodName($key, $value);
                }
            }
        }
    }

    /**
     * Executes one directive
     * @param PCMS_Service_Directive $directive
     */
    protected function _executeSingle(PCMS_Service_Directive $directive)
    {
        try {
            $request = $this->_prepareRequest($directive);
            $response = $request->send();
            $result = $directive->processResponse($response);
        } catch(Exception $e) {
            $response = $e->getResponse();
            if ($response === null) {
                throw new \Zend_Exception($e->getMessage());
            }
            $result = $directive->processResponse($response);
        }
        return $result;
    }

    /**
     * Executes multiple directives of type PCMS_Service_Directive
     * @param array $directives
     */
    protected function _executeMultiple(array $directives)
    {
        try {
            $requests = array();
            $client = new Client();
            /**
             * Turn directives into requests.
             */
            $requests = $directives;
            array_walk($requests, array($this, '_walkDirectives'));

            /**
             * Send the requests. and walk the responses via the directives.
             */
            $client->send($requests);
        } catch (ExceptionCollection $e) {
        }

        $responses = array();
        foreach ($requests as $result) {
            $responses[] = $result->getResponse();
        }
        array_walk($responses, array($this, '_walkResponses'), $directives);

        return $responses;
    }

    protected function _walkDirectives(&$value, $key, $data = null)
    {
        $value = $this->_prepareRequest($value);
    }

    protected function _walkResponses(&$value, $key, $directives = null)
    {
        $value = $directives[$key]->processResponse($value);
    }
}