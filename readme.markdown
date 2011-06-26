# Personal CMS Service

This service will allow a connection to your account on personalcms.com. You'll be able to get your CMS data via JSON or XML.

Take the Pcms.ini file from the service/pcms folder and place it in your configuration folder. You will also need to modify the following:

oauth.consumerKey       =   
oauth.consumerSecret    =   

Set them to the id's you receive from the following location: http://www.personalcms.com/account.

Once complete you'll be able to use your connection like this:

Add this function to your Zend Framework Bootstrap.php file.

    protected function _initPcmsService()
    {
        $frontendOauth = array(
            'lifetime' => null,
            'automatic_serialization' => true
        );

        $backendOauth = array(
            'file_name_prefix' => 'oauth',
            'cache_dir' => CACHE_PATH
        );

        $frontendPcms = array(
            'lifetime' => 7200,
            'automatic_serialization' => true
        );

        $backendPcms = array(
            'file_name_prefix' => 'pcms',
            'cache_dir' => CACHE_PATH
        );

        $cachePcms = Zend_Cache::factory('Core', 'File', $frontendPcms, $backendPcms);
        $cacheOauth = Zend_Cache::factory('Core', 'File', $frontendOauth, $backendOauth);

        $service = new Ocml_Service_Pcms(3, $cachePcms, $cacheOauth);

        Zend_Registry::set('PCMS', $service);
        Zend_Registry::set('pcmsCache', $cachePcms);
    }