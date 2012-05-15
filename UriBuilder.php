<?php
/**
 * Builds a url based on the configuration from the pcms service
 * @author donwhite
 */
class PCMS_UriBuilder
{

    /**
     * @var Zend_Config $_config
     */
    protected $_config;

    /**
     * @var Zend_Controller_Request_Abstract $_request
     */
    protected $_request;

    public function __construct(Zend_Controller_Request_Abstract $request, $config)
    {
        $this->_config = $config;
        $this->_request = $request;
    }

    /**
     * Returns the existing request object
     * @return Zend_Controller_Request_Abstract
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * Builds a url with the tag library located: /rest/public_config_v2.json
     * @param string $uri
     * @param array $replacements
     * @param array $parameters
     * @param boolean $addExistingParams
     * @param string $delimiter
     * @return string
     */
    public function build(
        $uri, array $replacements = array(), array $parameters = array(), $addExistingParams = false, $delimiter = '|'
    )
    {
        $pieces = explode($delimiter, $uri);
        $lookup = $this->_config;
        $base = '';

        if (count($pieces) > 1) {
            foreach ($pieces as $piece) {
                if (isset($lookup->$piece)) {
                    if (isset($lookup->_base)) {
                        $base = $lookup->_base;
                    }
                    $lookup = $lookup->$piece;
                }
            }
        } else {
            if (isset($lookup->{$pieces[0]}->_base)) {
                $uri = $lookup->{$pieces[0]}->_base;
            }
        }

        if (is_string($lookup)) {
            $uri = $base . $lookup;
        }

        $uri = $this->doReplacements($uri, $replacements);
        $uri = $this->addParameters($uri, $parameters, $addExistingParams);

        return $uri;
    }

    /**
     * Returns the host of the site perfectly, and will append the absolute url if you pass true for the second param
     * @param Zend_Controller_Request_Abstract $request
     * @param boolean $appendUrl
     * @param array $urlOptions
     * @param string $name
     * @param boolean $reset
     */
    public function getHost($appendUrl = false, array $urlOptions = array(), $name = null, $reset = false
    )
    {
        $server = $this->_request->getServer();
        $host = $server['HTTP_HOST'];
        if (isset($objServer['SERVER_PROTOCOL'])) {
            $protocol = $objServer['SERVER_PROTOCOL'];
            $protocol = explode('/', $strProtocol);
            $protocol = array_shift($strProtocol);
            $protocol = trim($strProtocol);
            $protocol = strtolower($strProtocol);
        } else {
            $protocol = 'http';
        }

        $return = "{$protocol}://{$host}";
        if ($appendUrl === true) {
            $fullUrl = parent::url($urlOptions, $name, $reset);
            $return .= $fullUrl;
        }

        return $return;
    }

    /**
     * Does a preg_replace on the url passed for the key value pairs passed.
     * @param string $uri
     * @param array $replacements
     * @return string
     */
    public function doReplacements($uri, array $replacements)
    {
        $searchParams = array_keys($replacements);
        if (count($searchParams) > 0) {
            $searchParams = array_map(array($this, '_bracketReplaceCallback'), $searchParams);
            $replacements = array_map(array($this,'sanitizeForUrl'), $replacements);
            $uri = preg_replace($searchParams, $replacements, $uri);
        }
        return $uri;
    }

    /**
     * Returns the string in brackets for easy replacements
     * @param String $item
     * @return String
     */
    private function _bracketReplaceCallback($item)
    {
        return '\'{' . $item . '}\'';
    }

    /**
     * Builds an http query string for the url based on the parameters passed
     * @param string $uri
     * @param array $parameters
     * @param boolean $addExistingParams
     * @return string
     */
    public function addParameters($uri, array $parameters, $addExistingParams = false)
    {
        if ($addExistingParams === true) {
            $query = $this->_request->getQuery();
            if (isset($query['route'])) {
                unset($query['route']);
            }
            $parameters = array_merge($parameters, $query);
        }

        if (!empty($parameters)) {
            $parameters = array_map(array($this,'sanitizeForUrl'), $parameters);
            $filteredKeys = array_filter(array_keys($parameters), array($this,'removeOauth'));
            $parameters = array_intersect_key($parameters, array_flip($filteredKeys));
            $uri .= '?' . http_build_query($parameters);
        }
        return $uri;
    }

    public function sanitizeForUrl($value)
    {
        return rawurlencode(preg_replace("'/'", "-s-", $value));
    }

    public function removeOauth($input)
    {
        if (preg_match("'oauth_*'", $input)) {
            return false;
        }
        return true;
    }

    /**
     * Creates the linkname in the links collection for the given object.
     * @param Mixed $object
     * @param String $linkName
     * @param String $uri
     */
    public function assignLink(&$object, $linkName, $uri)
    {
        if (isset($object['links']) === false) {
            $object['links'] = array();
        }

        $object['links'][$linkName] = array('href' => $uri);
    }
}