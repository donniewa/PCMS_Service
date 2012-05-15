# Personal CMS Service

This service will allow a connection to your account on personalcms.com. You'll be able to get your CMS data in the format: application/JSON.

An exaample configuration file has been provided in the main folder of the library called pcms.json.  You will need to provide a few pieces of information to the workflow object:

* consumerKey, replace the {consumerkey} with your consumer key
* consumerSecret, replace {consumersecret} with your secret token

location of this information is: http://www.personalcms.com/account/


Once complete you'll be able to use your connection like this:

Add this function to your Zend Framework Bootstrap.php file.

    protected function _initPcmsService()
    {
        $config  = new Zend_Config_Json(LIBRARY . 'PCMS/pcms.json', APPLICATION_ENV);
        $service = new PCMS_Workflow($config);
        Zend_Registry::set('PCMS_SERVICE', $service);
    }

You will need a cache buster for the service to connect to.  Add the following to one of your controllers, (then replace the "callbackUrl" in pcms.json with that url.):
    public function cacheBustAction()
    {
        $this->getResponse()->setHeader('Access-Control-Allow-Origin', '*');
        $contextHelper  =   $this->_helper->getHelper('ContextSwitch');
        $contextHelper->addActionContext('cache-bust', 'json')->initContext('json');

        $service = Zend_Registry::get('PCMS_SERVICE');
        try {
            $service->clearCache();
            $this->view->response   =    '200';
            $this->view->message    =    'Cleared Cache';
        } catch (Exception $e) {
            $this->getResponse()->setHttpResponseCode(500);
            $this->view->response   =    '500';
            $this->view->message    =    'Unable to Clear Cache';
        }
    }
