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
class PCMS_Service_Pcms_Client_Oauth extends PCMS_Service_Pcms_Client_Abstract
{
    private $_requestCount  =   0;

    /**
     * Returns the oauth client
     *
     * @return Zend_Oauth_Client
     * @author  donniewa
     */
    public function getClient()
    {
        /**
         * Get the necessary items from the global configuration and add them
         * to the client configuration.
         */
        $listClientConfig   =   array(
            'callbackUrl' => $this->_config->oauth->callback,
            'siteUrl' => $this->_config->oauth->siteUri,
            'requestTokenUrl' => $this->_config->oauth->requestTokenUrl.'site_id/' . $this->_siteId . '/',
            'accessTokenUrl' => $this->_config->oauth->accessTokenUrl,
            'authorizeUrl' => $this->_config->oauth->authorizeUrl,
            'consumerKey' => $this->_config->oauth->consumerKey,
            'consumerSecret' => $this->_config->oauth->consumerSecret,
            'requestMethod' => Zend_Oauth::GET,
            'requestScheme' => Zend_Oauth::REQUEST_SCHEME_QUERYSTRING
        );

        /**
         * @$objToken Zend_Oauth_Token
         */
        $objToken   =   $this->_loadAccessToken();

        if ($objToken instanceof Zend_Oauth_Token_Access) {
            $client = $objToken->getHttpClient($listClientConfig);
            return($client);
        } else {
            $objToken   =   $this->_loadRequestToken();
            if ($objToken instanceof Zend_Oauth_Token_Request) {
                $objToken   =   $this->_accessToken($listClientConfig);

                if ($objToken instanceof Zend_Oauth_Token_Access) {
                    $client = $objToken->getHttpClient($listClientConfig);
                    return($client);
            }
        }
        }

        /**
         * If all else fails, request the token.
         */
        $this->_requestCount++;
        if ($this->_requestCount == 1) {
            $this->_requestToken($listClientConfig);
        } else {
            throw new DomainException('Too many requests.');
        }

    }

    /**
     * Removes all tokens from cache and Session.
     *
     * @return void
     * @author donniewa
     */
    public function destroy()
    {
        if ($this->_cache) {
            $this->_cache->save(null, 'personalcms_access_token');
            $this->_cache->save(null, 'personalcms_request_token');
    }
    }

    /**
     * Returns the access token or null if the token contains
     * an error.  The flag passed to the function will allow
     * the token to be passed back even if there's an error.
     *
     * @param   Boolean $bPassBackOnError
     * @return Zend_Oauth_Token_Access
     * @author  donniewa
     */
    private function _loadAccessToken($bPassBackOnError = false)
    {
        /**
         * If the configuration allows for cached tokens, then try to pull the
         * token from cache.  This will be necessary if the same token is being
         * used all the time, rather than multiple tokens. For example, a page
         * run by multiple sites.
         */
        $objToken   =   null;
        if ($this->_cache) {
            if ($this->_cache->load('personalcms_access_token')) {
                $objToken   =   $this->_cache->load('personalcms_access_token');
        }
        }

        if ($bPassBackOnError === false && $objToken instanceof Zend_Oauth_Token) {
            // @codingStandardsIgnoreStart
            if ($objToken->oauth_error) {
                // @codingStandardsIgnoreEnd
                $objToken   =   null;
            }
        }

        return ($objToken);
    }

    /**
     * Returns the request token or null if the token contains
     * an error.  The flag passed to the function will allow
     * the token to be passed back even if there's an error.
     *
     * @param   Boolean $bPassBackOnError
     * @return Zend_Oauth_Token_Request
     * @author  donniewa
     */
    private function _loadRequestToken($bPassBackOnError = false)
    {
        /**
         * If the configuration allows for cached tokens, then try to pull the
         * token from cache.  This will be necessary if the same token is being
         * used all the time, rather than multiple tokens. For example, a page
         * run by multiple sites.
         */
        $objToken   =   null;
        if ($this->_cache) {
            if ($this->_cache->load('personalcms_request_token')) {
            /**
             * Using the ocml cache here, any cache protocol can be substituted.
             */
                $objToken   =   $this->_cache->load('personalcms_request_token');
        }
        }

        if ($bPassBackOnError === false && $objToken instanceof Zend_Oauth_Token) {
            // @codingStandardsIgnoreStart
            if ($objToken->oauth_error) {
                // @codingStandardsIgnoreEnd
                $objToken   =   null;
            }
        }

        return ($objToken);
    }

    /**
     * This method redirects the guest to the personalcms
     * OAuth authorization system.  It will re-direct the
     * guest back to the callback when authorization is
     * complete.
     *
     * @return void
     * @author  donniewa
     */
    private function _requestToken(array $listClientConfig)
    {
        /**
         * Attempt to create a new token from the client config
         */
        $objOauth   =   new Zend_Oauth_Consumer($listClientConfig);
        $objToken   =   $objOauth->getRequestToken();
        // persist the token to storage

        if ($this->_cache) {
            $this->_cache->save($objToken, 'personalcms_request_token');
        }
        if ($this->_config->oauth->redirectguest) {
            $objOauth->redirect();
        }
        throw new DomainException('No access token.');
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     */
    private function _accessToken(array $listClientConfig)
    {
        $consumer   =   new Zend_Oauth_Consumer($listClientConfig);
        $objToken   =   $this->_loadRequestToken();
        if (!empty($_GET) && !empty($objToken)) {
            try {
                $accessToken = $consumer->getAccessToken($_GET, $objToken);
                if ($accessToken) {
                    if ($this->_cache) {
                        $this->_cache->save($accessToken, 'personalcms_access_token');
                    // Now that we have an Access Token, we can discard the Request Token
                        $this->_cache->save(null, 'personalcms_request_token');
                    }
                    return($accessToken);
                }
            } catch (Exception $e) {
                /* Make a request for a new Token */
                $this->_requestToken($listClientConfig);
            }
        } else {
            // Mistaken request? Some malfeasant trying something?
            exit('error=OAuth Verification Failed');
        }

    }
}