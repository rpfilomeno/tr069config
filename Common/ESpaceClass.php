<?php
/**
 * Created by PhpStorm.
 * User: RFilomeno
 * Date: 31/10/2014
 * Time: 7:16 AM
 */

namespace Tr069Config\Espace;



use Guzzle\Plugin\Cookie\Cookie;

class EspaceClass
{
    const ESPACE_WEB_RequestCertificate = 'action.cgi?ActionID=WEB_RequestCertificate';
    const ESPACE_WEB_RequestSessionID = 'action.cgi?ActionID=WEB_RequestSessionID';
    const ESPACE_WEB_RequestVersionInfo = 'action.cgi?ActionID=WEB_GetVersionInfo';
    const ESPACE_WEB_ExportConfig = 'exportfile?Type=xml';
    const ESPACE_WEB_ImportConfig = 'importconfig?filename=Config-eSpace7910.xml';

    private $client = null;
    private $cookiePlugin = null;

    private $isDebug = false;

    public function __construct($baseUrl = '', $config = null)
    {
        #if($config == null) $config = array(
        #    'request.options' => array(
        #        'proxy'   => 'tcp://localhost:8080'
        #    )
        #);
        $this->client = new \Guzzle\Http\Client($baseUrl,$config);
        $this->cookiePlugin = new \Guzzle\Plugin\Cookie\CookiePlugin( new  \Guzzle\Plugin\Cookie\CookieJar\FileCookieJar( tempnam(sys_get_temp_dir(), 'ESpace') ) );
        $this->client->addSubscriber($this->cookiePlugin);
    }
    public function setDebug( $isDebug=false )
    {
        $this->isDebug = $isDebug;
    }

    public function requestSession($username)
    {
        $request = $this->client->get(EspaceClass::ESPACE_WEB_RequestSessionID,['cookies' => ['login_username_=admin']],[
            'debug' => $this->isDebug,
        ]);

        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);

        $response = $request->send();
        return json_decode( $response->getBody(true));
    }

    public function requestCertificate( $username = 'admin', $password = '' )
    {
        $request = $this->client->post(EspaceClass::ESPACE_WEB_RequestCertificate,[
            'Content-Type'=>'application/json',
            'debug' => $this->isDebug,
        ],'{"szUserName":"' . $username . '","szPassword":"' . base64_encode($password) . '"}');
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $response = $request->send();
        return json_decode( $response->getBody(true));
    }

    public function requestVersionInfo(){
        $request = $this->client->post(EspaceClass::ESPACE_WEB_RequestVersionInfo,[
            'X-Requested-With'=>'XMLHttpRequest',
            'debug' => $this->isDebug,
        ],'');
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $response = $request->send();
        return json_decode( $response->getBody(true));
    }

    public function requestImportConfig($filename) {
        $request = $this->client->post(EspaceClass::ESPACE_WEB_ImportConfig,null,['file' => '@'.$filename]);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $response = $request->send();
        return json_decode( $response->getBody(true));
    }

}