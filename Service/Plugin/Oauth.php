<?php
/**
 * Oauth Plugin. Will automatically handle authentication for the guest.
 */
require_once 'PCMS/Oauth.php';
class PCMS_Service_Plugin_Oauth
{
    /**
     * @var Guzzle\Common\Cache\CacheAdapterInterface
     */
    private $_cache = null;

    /**
     * @var boolean
     */
    private $_serialize = false;

    private $_config = null;

    private $_requestCount = 0;

    public function __construct($config)
    {
        $this->_config = $config;
    }

    public function setCacheAdapter($adapter, $serialize = false)
    {
        $this->_cache = $adapter;
        $this->_serialize = $serialize;
    }

    /**
     * Loads an access token from cache
     * @return Zend_Oauth_Token_Access
     */
    public function loadAccessToken()
    {
        $token = $this->_loadTokenFromCache('access');
        return $token;
    }

    /**
     * Starts the process for a new token
     */
    public function getRequestToken()
    {
        $token = $this->_loadTokenFromCache('request');
        if (!empty($token)) {
            return $token;
        }

        $baseUrl = $this->_config['siteUrl'];
        $sigMethod = @$this->_config['signatureMethod'] ?: 'HMAC_SHA1';
        $signatureMethod = "OAuthSignatureMethod_{$sigMethod}";

        $client = new Guzzle\Service\Client;
        $consumer = new OAuthConsumer(
            $this->_config['consumerKey'], $this->_config['consumerSecret'], $this->_config['callbackUrl']
        );

        $oauthRequest = OAuthRequest::from_consumer_and_token(
            $consumer, NULL, 'GET', "{$baseUrl}/request_token/"
            ,array('oauth_callback' => $this->_config['callbackUrl'])
        );
        $oauthRequest->sign_request(new $signatureMethod, $consumer, null);

        /**
         * @var Guzzle\Http\Message\RequestInterface
         */
        $request = $client->createRequest(
            $oauthRequest->get_normalized_http_method(),
            $oauthRequest->get_normalized_http_url() . '?' . http_build_query($oauthRequest->get_parameters())
        );

        /**
         * @var Guzzle\Http\Message\Response
         */
        $response = $request->send();
        $oauthVars = null;
        parse_str((string) $response->getBody(), $oauthVars);
        if (!isset($oauthVars['oauth_token']) || !isset($oauthVars['oauth_token_secret'])) {
            throw new OAuthException('Invalid Request Token.');
        }
        $token = new OAuthToken($oauthVars['oauth_token'], $oauthVars['oauth_token_secret']);
        $this->_storeTokenCache('request', $token);
        return $token;
    }

    /**
     * The request token given here can only be turned into an access token
     * once it's been authorized by the ruling party.
     * @param OAuthToken $request
     * @return OAuthToken|null $access token
     */
    public function getAccessToken(OAuthToken $request)
    {
        $token = $this->_loadTokenFromCache('access');
        if (!empty($token)) {
            return $token;
        }

        $baseUrl = $this->_config['siteUrl'];
        $sigMethod = @$this->_config['signatureMethod'] ?: 'HMAC_SHA1';
        $signatureMethod = "OAuthSignatureMethod_{$sigMethod}";

        $client = new Guzzle\Service\Client;
        $consumer = new OAuthConsumer(
            $this->_config['consumerKey'], $this->_config['consumerSecret'], $this->_config['callbackUrl']
        );

        $oauthRequest = OAuthRequest::from_consumer_and_token(
            $consumer, $request, 'GET', "{$baseUrl}/access_token/"
            ,array('oauth_callback' => $this->_config['callbackUrl'])
        );
        $oauthRequest->sign_request(new $signatureMethod, $consumer, $request);

        try {
            /**
             * @var Guzzle\Http\Message\RequestInterface
             */
            $request = $client->createRequest(
                $oauthRequest->get_normalized_http_method(),
                $oauthRequest->get_normalized_http_url() . '?' . http_build_query($oauthRequest->get_parameters())
            );

            /**
             * @var Guzzle\Http\Message\Response
             */
            $response = $request->send();
            $oauthVars = null;
            parse_str((string) $response->getBody(), $oauthVars);
            if (!isset($oauthVars['oauth_token']) || !isset($oauthVars['oauth_token_secret'])) {
                throw new OAuthException('Invalid Access Token.');
            }

            $token = new OAuthToken($oauthVars['oauth_token'], $oauthVars['oauth_token_secret']);
            $this->_storeTokenCache('access', $token);
            return $token;
        } catch (Exception $e) {
            throw new OAuthException($e->getMessage());
        }
    }

    public function authorize()
    {
        $request = $this->getRequestToken();
        if ($request instanceof OAuthToken) {
            try {
                $access = $this->getAccessToken($request);
                if ($access instanceof OAuthToken) {
                    $this->_storeTokenCache('access', $access);
                    return true;
                }
            } catch (Exception $e) {
                $this->_requestCount = 0;
            }
        }
        $this->_requestCount ++;
        if ($this->_requestCount === 1) {
            $token = $this->getRequestToken();

            if (isset($token->oauth_error)) {
                throw new OAuthException($token->oauth_error);
            }

            $this->_storeTokenCache('request', $token);
            if ($this->_config['redirect'] === true || $this->_forceRedirect() === true) {
                $oauthVars = array(
                    'oauth_token' => $token->key
                );
                $url = $this->_config['siteUrl'] . "/authorize/?" . http_build_query($oauthVars);
                header("location: {$url}");
                exit;
            }
        } else {
            throw new Exception('We have detected too many requests in the same loop.');
        }
    }

    private function _forceRedirect()
    {
        if (Zend_Registry::isRegistered('REDIRECT_OAUTH')) {
            if (Zend_Registry::get('REDIRECT_OAUTH') === true) {
                return true;
            }
        }
        return false;
    }

    private function _getCacheId($type)
    {
        switch($type) {
            case "access":
                $id = "personalcms_access_token";
                break;
            case "request":
                $id = "personalcms_request_token";
                break;
        }
        return $id ?: null;
    }

    private function _loadTokenFromCache($type = 'access')
    {
        if (!empty($this->_cache)) {
            $token = $this->_cache->fetch($this->_getCacheId($type));
            if ($this->_serialize) {
                $token = unserialize($token);
            }
            return $token;
        }
        return null;
    }

    private function _storeTokenCache($type = 'access', $token)
    {
        if (!empty($this->_cache)) {
            if ($this->_serialize) {
                $token = serialize($token);
            }
            if ($type === 'access') {
                $this->_cache->delete($this->_getCacheId('request'));
            }
            return $this->_cache->save($this->_getCacheId($type), $token);
        }

        throw new Zend_Exception('Unable to store {$type} token to cache. Please check your settings.');
        return false;
    }
}