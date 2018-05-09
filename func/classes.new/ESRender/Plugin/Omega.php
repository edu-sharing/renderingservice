<?php

/**
 *Handle Omega Materials. Not only Videos as the plugin name suggests.
 *
 *
 */
class ESRender_Plugin_Omega
    extends ESRender_Plugin_Abstract
{

    private $url = '';
    private $proxy = '';

    /**
     *
     * @param string $Url
     */
    public function __construct($url, $proxy = '') {
        $this->url = $url;
        $this->proxy = $proxy;
    }

    /**
     * (non-PHPdoc)
     * @see ESRender_Plugin_Abstract::postRetrieveObjectProperties()
     */
    public function postRetrieveObjectProperties(EsApplication &$remote_rep, &$app_id,ESContentNode &$contentNode, &$course_id, &$resource_id, &$username) {
        $logger = $this->getLogger();
        $logger->info('Replicationsource: ' . $contentNode->getProperty('{http://www.campuscontent.de/model/1.0}replicationsource') . ', format: ' .
            $contentNode->getProperty('{http://www.campuscontent.de/model/lom/1.0}format') .', replicationsourceid: ' . $contentNode->getProperty('{http://www.campuscontent.de/model/1.0}replicationsourceid'));

        if(Config::get('renderInfoLMSReturn') -> hasContentLicense === false) {
            $logger->info('hasContentLicense is false');
            return;
        }

        if(Config::get('renderInfoLMSReturn') -> contentHash > -1) {
            $logger->info('contentHash > -1 handle as local object');
            return;
        }

        $role = 'learner';
        if(Config::get('renderInfoLMSReturn') -> eduSchoolPrimaryAffiliation === 'teacher') {
            $role = 'teacher';
        }

        if ($contentNode->getProperty('{http://www.campuscontent.de/model/1.0}replicationsource') == 'DE.FWU')  {

            if($contentNode->getProperty('{http://www.campuscontent.de/model/lom/1.0}format') == '')
                $logger->info('Format is empty!');

            $response = $this->callAPI($contentNode, $role);
            $response = $this->evaluateResponse($response, $contentNode);
            $prop = new stdClass();
            $prop -> key = '{http://www.campuscontent.de/model/1.0}wwwurl';
            $prop -> value = $response -> get -> streamURL;
            $contentNode -> setProperties(array($prop));
        }
    }

    protected function evaluateResponse($response = null, $contentNode) {
        $logger = $this->getLogger();
        if(empty($response))
            $logger->error('Sodis repsonse is empty');
        $response = json_decode($response);

        if($response->get->identifier !== $contentNode->getProperty('{http://www.campuscontent.de/model/1.0}replicationsourceid'))
            $logger->error('Wrong identifier');

        if(!empty($response->get->error)) {
            $logger->error($response->get->error);
        }

        if($response -> get -> downloadURL) {
            Config::set('downloadUrl', $response -> get -> downloadURL);
        }

        return $response;
    }

    protected function callAPI($contentNode, $role) {
        $logger = $this->getLogger();
        $replicationSourceId = $contentNode->getProperty('{http://www.campuscontent.de/model/1.0}replicationsourceid');
        if(empty($replicationSourceId)) {
            $logger->error('Replicationsourceid is empty');
        }
        $url = $this->url . '?token_id=' . $replicationSourceId . '&role=' . $role . '&user=dabiplus';        $curlhandle = curl_init($url);

        curl_setopt($curlhandle, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curlhandle, CURLOPT_HEADER, 0);
        curl_setopt($curlhandle, CURLOPT_PROXY, $this->proxy);
        curl_setopt($curlhandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlhandle, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($curlhandle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curlhandle, CURLOPT_SSL_VERIFYHOST, false);
        $resp = curl_exec($curlhandle);
        $logger = $this->getLogger();
        $logger->debug('Called ' . $url . ' got ' . $resp);
        return $resp;
    }
}
