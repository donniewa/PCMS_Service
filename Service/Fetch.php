<?php
/**
 * This is the main fetch class for PCMS.  It will allow you to create a client to fetch data from the service layer
 * and return it in the class type that you desire.
 *
 * @author donwhite
 */
class PCMS_Service_Fetch
{
    const DEFAULT_CLASS = 'PCMS_Service_DomainObject';

    /**
     * Requires a cache instance, so that each call to the service layer is
     * as optimal as possible.
     * @param Zend_Cache $cache
     * @param String $uri
     * @param String $config
     */
    public function __construct(Zend_Cache $cache = null, $uri = null, $config = null)
    {
    }

    /**
     * returns the main pcms client
     * @throws Zend_Exception
     * @return PCMS_Service_Client
     */
    public function getClient()
    {
        if (Zend_Registry::isRegistered('PCMS_CLIENT')) {
            return Zend_Registry::get('PCMS_CLIENT');
        }

        throw new Zend_Exception('PCMS Client is not available');
    }

    /**
     * Creates a directive and returns it based on the criteria you pass in.
     * @param Object $instance
     * @param string $linkName
     * @param string $className
     * @throws Zend_Exception
     * @return PCMS_Service_Directive_Json
     */
    protected function _createDirective(&$instance, $linkName, $className = 'stdClass')
    {
        $serviceUri = null;
        if (isset($instance->{$linkName}->links->self->href)) {
            $serviceUri = $instance->{$linkName}->links->self->href;
        } elseif (isset($instance->links->{$linkName}->href)) {
            $serviceUri = $instance->links->{$linkName}->href;
        }

        if (empty($serviceUri)) {
            throw new Zend_Exception('Service URL is undefined.');
        }
        $directive = new PCMS_Service_Directive_Json($serviceUri, $className);
        return $directive;
    }


    /**
     * Fetches a single object from the service based on the url structure that
     * exists within the returned data.  The linkName will be used to look up the
     * urls.
     * @param Mixed|Object $instance
     * @param String $linkName
     * @param String $className
     */
    public function fetch(&$instance, $linkName, $className = self::DEFAULT_CLASS)
    {
        $directive = $this->_createDirective($instance, $linkName, $className);
        $instance->{$linkName} = $this->getClient()->fetch($directive);
        return $instance->$linkName;
    }

    /**
     * Fetches an array of items from the instance passed. Uses the LinkName with the url structure
     * to look up each of the items requested.  Returns each item as an instance of the classname
     * provided
     * @param array $instance
     * @param String $linkName
     * @param String $className
     */
    public function fetchAll(&$instance, $linkName, $className = self::DEFAULT_CLASS)
    {
        $directives = array();
        if (!empty($instance->{$linkName})) {
            foreach ($instance->{$linkName} as $value) {
                $directives[] = $this->_createDirective($value, 'self', $className);
            }

            $results = $this->getClient()->fetch($directives);
            $i = 0;
            /**
             * The results are returned in the exact same order the commands were issued, so since the executor
             * does not maintain the keys, we need to return the model exactly as it was given to us, with the
             * same keys or parameters.
             */
            foreach ($instance->{$linkName} as $key => $value) {
                if (is_array($instance->{$linkName})) {
                    $instance->{$linkName}[$key] = isset($results[$i]) ? $results[$i] : null;
                } else {
                    $instance->{$linkName}->$key = isset($results[$i]) ? $results[$i] : null;
                }
                $i++;
            }
        }
        return $instance->{$linkName};
    }

    /**
     * Fetches an array of items from the instance passed. Uses the LinkName with the url structure
     * to look up each of the items requested.  Returns each item as an instance of the classname
     * provided
     * @param array $models
     * @param String $linkName
     * @param String $className
     */
    public function fetchMultiple(&$models, $linkName, $className = self::DEFAULT_CLASS) {
        $directives = array();
        foreach ($models as $instance) {
            $directives[] = $this->_createDirective($instance, 'self', $className);
        }

        $models = $this->getClient()->fetch($directives);
        return $models;
    }
}