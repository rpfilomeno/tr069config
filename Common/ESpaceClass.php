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

    const ESPACE_WEB_PASSWORD_MODE_MD5 = 'md5';
    const ESPACE_WEB_PASSWORD_MODE_BASE64 = 'base64';
    const ESPACE_WEB_PASSWORD_MODE_BASE64ALT = 'base64alt';


    private $client = null;
    private $cookiePlugin = null;

    private $isDebug = false;
    private $passwordMode = null;
    private $sessionId = null;

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

    public function setPasswordMode( $passwordMode)
    {
        $passwordModes = array(
            $this::ESPACE_WEB_PASSWORD_MODE_BASE64ALT,
            $this::ESPACE_WEB_PASSWORD_MODE_BASE64,
            $this::ESPACE_WEB_PASSWORD_MODE_MD5);
        if(in_array($passwordMode,$passwordModes)){
            $this->passwordMode = $passwordMode;
        } else {
            throw new \Exception('Invalid password mode.');
        }
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }

    public function requestSession($username='admin')
    {
        $request = $this->client->get(EspaceClass::ESPACE_WEB_RequestSessionID,['cookies' => ['login_username_='.$username]],[
            'debug' => $this->isDebug,
        ]);

        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $request->getCurlOptions()->set(CURLOPT_AUTOREFERER, true);
        $request->getCurlOptions()->set(CURLOPT_FOLLOWLOCATION, true);
        $request->getCurlOptions()->set(CURLOPT_UNRESTRICTED_AUTH, true);

        try {
            $response = $request->send();
        } catch (\Exception $e){
            $response = new \stdClass();
            $response->success = 0;
            $response->data = '';
            return $response;
        }

        $response = json_decode( $response->getBody(true));
        if (!$response->success) return $response;
        $this->sessionId =  (isset($response->data)) ? $response->data : null;
        $this->sessionId = json_decode($this->sessionId);
        $this->sessionId = $this->sessionId->szSessionID;
        return $response;
    }

    public function requestCertificate( $username = 'admin', $password = '' )
    {
        if($this->passwordMode == $this::ESPACE_WEB_PASSWORD_MODE_BASE64ALT) {
            $password = base64_encode($password);
            $password = substr($password, 0, -1).':';
        }elseif($this->passwordMode == $this::ESPACE_WEB_PASSWORD_MODE_BASE64) {
            $password = base64_encode($password);
        }elseif($this->passwordMode == $this::ESPACE_WEB_PASSWORD_MODE_MD5) {
            $password = md5($username.':'.$password.':'.$this->sessionId);
        }

        $request = $this->client->post(EspaceClass::ESPACE_WEB_RequestCertificate,[
            'Content-Type'=>'application/json',
            'debug' => $this->isDebug,
        ],'{"szUserName":"' . $username . '","szPassword":"' . $password . '"}');
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $request->getCurlOptions()->set(CURLOPT_AUTOREFERER, true);
        $request->getCurlOptions()->set(CURLOPT_FOLLOWLOCATION, true);
        $request->getCurlOptions()->set(CURLOPT_UNRESTRICTED_AUTH, true);
        try {
            $response = $request->send();
        } catch (\Exception $e){
            $response = new \stdClass();
            $response->success = 0;
            $response->data = '';
            return $response;
        }
        return json_decode( $response->getBody(true));
    }

    public function requestVersionInfo(){
        $request = $this->client->post(EspaceClass::ESPACE_WEB_RequestVersionInfo,[
            'X-Requested-With'=>'XMLHttpRequest',
            'debug' => $this->isDebug,
        ],'');
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $request->getCurlOptions()->set(CURLOPT_AUTOREFERER, true);
        $request->getCurlOptions()->set(CURLOPT_FOLLOWLOCATION, true);
        $request->getCurlOptions()->set(CURLOPT_UNRESTRICTED_AUTH, true);
        try {
            $response = $request->send();
        } catch (\Exception $e){
            $response = new \stdClass();
            $response->success = 0;
            $response->data = '';
            return $response;
        }
        return json_decode( $response->getBody(true));
    }

    public function requestImportConfig($filename) {
        $request = $this->client->post(EspaceClass::ESPACE_WEB_ImportConfig,null,['file' => '@'.$filename]);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $request->getCurlOptions()->set(CURLOPT_AUTOREFERER, true);
        $request->getCurlOptions()->set(CURLOPT_FOLLOWLOCATION, true);
        $request->getCurlOptions()->set(CURLOPT_UNRESTRICTED_AUTH, true);
        try{
            $response = $request->send();
        } catch (\Exception $e) {
            $response = new \stdClass();
            $response->success = 0;
            $response->data = '';
            return $response;
        }
        return json_decode( $response->getBody(true));
    }

    public function requestExportConfig($toFilename) {
        $handle = fopen($toFilename,'w');
        $request = $this->client->post(EspaceClass::ESPACE_WEB_ExportConfig,[
            'X-Requested-With'=>'XMLHttpRequest',
            'debug' => $this->isDebug,
        ],'');
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $request->getCurlOptions()->set(CURLOPT_AUTOREFERER, true);
        $request->getCurlOptions()->set(CURLOPT_FOLLOWLOCATION, true);
        $request->getCurlOptions()->set(CURLOPT_UNRESTRICTED_AUTH, true);
        $request->getCurlOptions()->set(CURLOPT_RETURNTRANSFER, true);
        $request->getCurlOptions()->set(CURLOPT_FILE, $handle);
        try {
            $response = $request->send();
        } catch (\Exception $e){
            $response = new \stdClass();
            $response->success = 0;
            $response->data = '';
            return $response;
        }
        return json_decode( $response->getBody(true));
    }

}