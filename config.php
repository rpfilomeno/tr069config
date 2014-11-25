<?php
/*
 * Trigger device to configure using CSV file
 * http://10.55.54.190:8081/tr069config/config.php?deviceIp=10.55.54.170
 *
 * Trigger device to configure use CSV file and override the values for give parameters
 * http://10.55.54.190:8081/tr069config/config.php?deviceIp=10.55.54.170&Account1.Auth.UserName=3447&UIEMUser.UserNO=3477&UIEMUser.UserName=3447&Account1.Auth.Passwd=zFl8WBZE1
 *
 * available CSV parameters that can be overriden:
 * SerialNumber, Account1.Enable, Account1.Account, Account1.LogOut,
 * Account1.LabelName, Account1.PGMNumber, Account1.CorpID,
 * Account1.Auth.UserName, Account1.Auth.Passwd, UIEMUser.UserNO,
 * UIEMUser.UserName, UIEMUser.PassWord, ManagementServer.URL,
 * ManagementServer.Username, ManagementServer.Password,
 * ManagementServer.ConnectionRequestUsername, ManagementServer.ConnectionRequestPassword,
 * ManagementServer.ConnectionAuthType, Server.PrimaryRegServerAddress,
 * Server.PrimaryRegServerPort,Server.BackupRegServerAddress,
 * Server.BackupRegServerPort,WebAdminUser.UserName,
 * WebAdminUser.PassWord,WebAdminUserSecurity.UserName,
 * WebAdminUserSecurity.PassWord
 *
 * other parameters:
 * csvConfigFilename = specify a different CVS file to use. default: Config-eSpace7910.csv
 * xmlConfigFilename = specify a different XML file template to use. default: Config-eSpace7910.xml
 * eSpaceUsername = use a different username to connect to the eSpace device. default: admin
 * eSpacePassword = use a different password to connect to the eSpace device. default: admin123
 */
set_time_limit(60);
require 'vendor/autoload.php';

if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $clientIP = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $clientIP = $_SERVER['REMOTE_ADDR'];
}
//if ($clientIP !== '127.0.0.1') {  error_log ('Client IP not authorized.');  echo '-1'; exit; }

$deviceIp = isset($_REQUEST['deviceIp']) ? filter_var($_REQUEST['deviceIp'],FILTER_VALIDATE_IP) : false;
if($deviceIp === false) { error_log( 'no target device set' );  echo '-1'; exit; }

$csvConfigFilename = isset($_REQUEST['csvConfigFilename']) ? filter_var($_REQUEST['csvConfigFilename'],FILTER_SANITIZE_STRING) : 'Config-eSpace7910.csv';
$xmlConfigFilename = isset($_REQUEST['xmlConfigFilename']) ? filter_var($_REQUEST['xmlConfigFilename'],FILTER_SANITIZE_STRING) : 'Config-eSpace7910.xml';
$eSpaceUsername = isset($_REQUEST['eSpaceUsername']) ? filter_var($_REQUEST['eSpaceUsername'],FILTER_SANITIZE_STRING) : 'admin';
$eSpacePassword = isset($_REQUEST['eSpacePassword']) ? filter_var($_REQUEST['eSpacePassword'],FILTER_SANITIZE_STRING) : 'admin123';
$csvConfigData = null;



try {

    $eSpace = new \Tr069Config\Espace\EspaceClass('https://' . $deviceIp, null, $eSpaceUsername);

    $response = $eSpace->requestSession($eSpaceUsername);
    if (!$response->success) {
        error_log('Unable to open session to client ' . $deviceIp);
        echo '-1'; exit;
    }

    $response = $eSpace->requestCertificate($eSpaceUsername, $eSpacePassword);
    if (!$response->success) {
        error_log('Unable to default login to client ' . $deviceIp);
        echo '-1'; exit;
    }

    $response = $eSpace->requestVersionInfo();

    if (!$response->success) {
        error_log('Cannot get hardware information of client ' . $deviceIp);
        echo '-1'; exit;
    }
    $hardwareInfo = json_decode($response->data)->stMainVersionInfo;


    if (file_exists($csvConfigFilename)) {
        $rows = array_map('str_getcsv', file($csvConfigFilename));
        $header = array_shift($rows);
        $csvConfigs = array();
        foreach ($rows as $row) {
            $csvConfigs[] = array_combine($header, $row);
        }
    } else {
        echo '-1'; exit;
    }

    $xmlConfig = new \DOMDocument();
    $xmlConfig->load($xmlConfigFilename);
    $csvConfigData = null;

    foreach ($csvConfigs as $csvConfig) {
        if ($csvConfig['SerialNumber'] == $hardwareInfo->szSN) {
            $csvConfigData = $csvConfig;
            break;
        }
    }

    if (!$csvConfigData) {
        echo '-1'; exit;
    }


    $overrideParams = $_REQUEST;
    foreach ($_REQUEST as $key=>$params)  {  $csvConfigData[ str_replace('_','.',$key)] = $params;  }
    //var_dump($_REQUEST);
    //var_dump($csvConfigData);
    //exit;

// Configuring UserCfg
    foreach ($UserCfg = $xmlConfig->getElementsByTagName('UserCfg') as $root) {
        foreach ($nodeAcoounts = $root->getElementsByTagName('*') as $nodeAccount) {
            if (preg_match('/Account\d/', $nodeAccount->nodeName)) {
                if (isset($csvConfigData[$nodeAccount->nodeName . '.Enable'])) {

                    $input = (isset($csvConfigData[$nodeAccount->nodeName . '.Enable']))
                        ? $csvConfigData[$nodeAccount->nodeName . '.Enable']
                        : 0;
                    $input = filter_var($input, FILTER_VALIDATE_INT);
                    $nodeAccount->setAttribute('Enable', $input);

                    $input = (isset($csvConfigData[$nodeAccount->nodeName . '.Account']))
                        ? $csvConfigData[$nodeAccount->nodeName . '.Account']
                        : '';
                    $input = filter_var($input, FILTER_SANITIZE_STRING);
                    $nodeAccount->setAttribute('Account', $input);

                    $input = (isset($csvConfigData[$nodeAccount->nodeName . '.LogOut']))
                        ? $csvConfigData[$nodeAccount->nodeName . '.LogOut']
                        : 0;
                    $input = filter_var($input, FILTER_VALIDATE_INT);
                    $nodeAccount->setAttribute('LogOut', $input);

                    $input = (isset($csvConfigData[$nodeAccount->nodeName . '.LabelName']))
                        ? $csvConfigData[$nodeAccount->nodeName . '.LabelName']
                        : '';
                    $input = filter_var($input, FILTER_SANITIZE_STRING);
                    $nodeAccount->setAttribute('LabelName', $input);

                    $input = (isset($csvConfigData[$nodeAccount->nodeName . '.PGMNumber']))
                        ? $csvConfigData[$nodeAccount->nodeName . '.PGMNumber']
                        : '';
                    $input = filter_var($input, FILTER_SANITIZE_STRING);
                    $nodeAccount->setAttribute('PGMNumber', $input);

                    $input = (isset($csvConfigData[$nodeAccount->nodeName . '.CorpID']))
                        ? $csvConfigData[$nodeAccount->nodeName . '.CorpID']
                        : '';
                    $input = filter_var($input, FILTER_SANITIZE_STRING);
                    $nodeAccount->setAttribute('CorpID', $input);

                    foreach ($nodeAuths = $nodeAccount->getElementsByTagName('Auth') as $nodeAuth) {
                        $input = (isset($csvConfigData[$nodeAccount->nodeName . '.Auth.UserName']))
                            ? $csvConfigData[$nodeAccount->nodeName . '.Auth.UserName']
                            : '';
                        if ($input) {
                            $input = filter_var($input, FILTER_SANITIZE_STRING);
                            $nodeAuth->setAttribute('UserName', $input);
                        }
                        $input = (isset($csvConfigData[$nodeAccount->nodeName . '.Auth.Passwd']))
                            ? $csvConfigData[$nodeAccount->nodeName . '.Auth.Passwd']
                            : '';
                        if ($input) {
                            $input = filter_var($input, FILTER_SANITIZE_STRING);
                            $nodeAuth->removeAttribute('Passwd');
                            $nodeAuth->setAttribute('Passwd', $input);
                        }
                    }
                }
            }
        }
    }


//UIEMUser
    foreach ($nodeUIEMUsers = $xmlConfig->getElementsByTagName('UIEMUser') as $nodeUIEMUser) {
        $input = (isset($csvConfigData['UIEMUser.UserNO']))
            ? $csvConfigData['UIEMUser.UserNO']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeUIEMUser->setAttribute('UserNO', $input);
        }
        $input = (isset($csvConfigData['UIEMUser.UserName']))
            ? $csvConfigData['UIEMUser.UserName']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeUIEMUser->setAttribute('UserName', $input);
        }
        $input = (isset($csvConfigData['UIEMUser.PassWord']))
            ? $csvConfigData['UIEMUser.PassWord']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeUIEMUser->setAttribute('PassWord', $input);
        }

    }

//ManagementServer
    foreach ($nodeManagementServers = $xmlConfig->getElementsByTagName('ManagementServer') as $nodeManagementServer) {
        $input = (isset($csvConfigData['ManagementServer.URL']))
            ? $csvConfigData['ManagementServer.URL']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_VALIDATE_URL);
            $nodeManagementServer->setAttribute('URL', $input);
        }
        $input = (isset($csvConfigData['ManagementServer.Username']))
            ? $csvConfigData['ManagementServer.Username']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeManagementServer->setAttribute('Username', $input);
        }
        $input = (isset($csvConfigData['ManagementServer.Password']))
            ? $csvConfigData['ManagementServer.Password']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeManagementServer->setAttribute('Password', $input);
        }
        $input = (isset($csvConfigData['ManagementServer.ConnectionRequestUsername']))
            ? $csvConfigData['ManagementServer.ConnectionRequestUsername']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeManagementServer->setAttribute('ConnectionRequestUsername', $input);
        }
        $input = (isset($csvConfigData['ManagementServer.ConnectionRequestPassword']))
            ? $csvConfigData['ManagementServer.ConnectionRequestPassword']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeManagementServer->setAttribute('ConnectionRequestPassword', $input);
        }
        $input = (isset($csvConfigData['ManagementServer.ConnectionAuthType']))
            ? $csvConfigData['ManagementServer.ConnectionAuthType']
            : '2';
        if ($input) {
            $input = filter_var($input, FILTER_VALIDATE_INT);
            $nodeManagementServer->setAttribute('ConnectionAuthType', $input);
        }
    }

//Server (Phone)
    foreach ($nodeServers = $xmlConfig->getElementsByTagName('Server') as $nodeServer) {
        $input = (isset($csvConfigData['Server.PrimaryRegServerAddress']))
            ? $csvConfigData['Server.PrimaryRegServerAddress']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_VALIDATE_IP);
            $nodeServer->setAttribute('PrimaryRegServerAddress', $input);
        }
        $input = (isset($csvConfigData['Server.PrimaryRegServerPort']))
            ? $csvConfigData['Server.PrimaryRegServerPort']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_VALIDATE_INT);
            $nodeServer->setAttribute('PrimaryRegServerPort', $input);
        }
        $input = (isset($csvConfigData['Server.BackupRegServerAddress']))
            ? $csvConfigData['Server.BackupRegServerAddress']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_VALIDATE_IP);
            $nodeServer->setAttribute('BackupRegServerAddress', $input);
        }
        $input = (isset($csvConfigData['Server.BackupRegServerPort']))
            ? $csvConfigData['Server.BackupRegServerPort']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_VALIDATE_INT);
            $nodeServer->setAttribute('BackupRegServerPort', $input);
        }
    }

//WebAdminUser
    foreach ($nodeWebAdminUsers = $xmlConfig->getElementsByTagName('WebAdminUser') as $nodeWebAdminUser) {
        $input = (isset($csvConfigData['WebAdminUser.UserName']))
            ? $csvConfigData['WebAdminUser.UserName']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeWebAdminUser->setAttribute('UserName', $input);
        }
        $input = (isset($csvConfigData['WebAdminUser.PassWord']))
            ? $csvConfigData['WebAdminUser.PassWord']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeWebAdminUser->setAttribute('PassWord', $input);
        }
    }

//WebAdminUserSecurity
    foreach ($nodeWebAdminUserSecuritys = $xmlConfig->getElementsByTagName('WebAdminUserSecurity') as $nodeWebAdminUserSecurity) {
        $input = (isset($csvConfigData['WebAdminUserSecurity.UserName']))
            ? $csvConfigData['WebAdminUserSecurity.UserName']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeWebAdminUserSecurity->setAttribute('UserName', $input);
        }
        $input = (isset($csvConfigData['WebAdminUserSecurity.PassWord']))
            ? $csvConfigData['WebAdminUserSecurity.PassWord']
            : '';
        if ($input) {
            $input = filter_var($input, FILTER_SANITIZE_STRING);
            $nodeWebAdminUserSecurity->setAttribute('PassWord', $input);
        }
    }




    $xmlString = $xmlConfig->saveXML();

    $temp_file = tempnam(sys_get_temp_dir(), 'eSp');
    file_put_contents($temp_file, $xmlString);

    $response = $eSpace->requestImportConfig($temp_file);
    if (!$response->success) {
        error_log('Failed upload XML configuration to device '.$deviceIp);
        echo '-1'; exit;
    }
    error_log('eSpace device "'.$deviceIp.'" configured successfully, restarting device now');
    echo '0';

} catch (\Exception $e) {
    error_log($e->getMessage());
    echo '-1';
}