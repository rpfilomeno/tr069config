<?php
error_reporting(E_ALL);
require 'vendor/autoload.php';

if (!empty($_REQUEST['IPADDR'])) {
    $deviceIp=  $_REQUEST['IPADDR'];
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $deviceIp = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $deviceIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $deviceIp = $_SERVER['REMOTE_ADDR'];
}


$csvConfigFilename = 'accounts-list.csv';
$xmlConfigFilename = 'Config-eSpace7910.xml';
$eSpaceUsername = 'admin';
$eSpacePassword = 'admin123';
$csvConfigData = null;

try {

    $eSpace = new \Tr069Config\Espace\EspaceClass('https://' . $deviceIp, null, $eSpaceUsername);
    $response = $eSpace->requestSession($eSpaceUsername);
    if (!$response->success) {
        error_log('Unable to open secure session to client, trying insecure method on ' . $deviceIp);
        $eSpace = new \Tr069Config\Espace\EspaceClass('http://' . $deviceIp, null, $eSpaceUsername);
        $response = $eSpace->requestSession($eSpaceUsername);
        if (!$response->success) {
            error_log('Unable to open secure session to client ' . $deviceIp);
            exit;
        }
    }

    $eSpace->setUseHashPassword(true);

    $response = $eSpace->requestCertificate($eSpaceUsername, $eSpacePassword);
    if (!$response->success) {
        error_log('Unable to default login to client ' . $deviceIp);
        exit;
    }

    $response = $eSpace->requestVersionInfo();

    if (!$response->success) {
        error_log('Cannot get hardware information of client ' . $deviceIp);
        exit;
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
        exit;
    }

    //lookup if alternate xml config file if exist in xmlconfig directory
    $altXmlConfigFilenames=array();
    $altXmlConfigFilenames[] = 'Config-eSpace-'.$hardwareInfo->szSN.'xml';
    $altXmlConfigFilenames[] = 'Config-eSpace-'.$hardwareInfo->szBuildVersion.'xml';
    $altXmlConfigFilenames[] = 'Config-eSpace-'.$hardwareInfo->szHardWareVersion.'xml';
    $altXmlConfigFilenames[] = 'Config-eSpace-'.$hardwareInfo->szBootVersion.'xml';
    foreach($altXmlConfigFilenames as $altXmlConfigFilename) {
        $testAltXmlConfigFilename = realpath(dirname(__FILE__)).'/xmlconfig/'.$altXmlConfigFilename;
        if(file_exists($testAltXmlConfigFilename)) {
            $xmlConfigFilename = $testAltXmlConfigFilename;
            error_log('Using matching stored xml configuration file  ' . $altXmlConfigFilename  .' for '.$deviceIp);
        }
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
        return false;
    }


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
        $this->logger->debug("XML Configutation updated = \n" . $xmlConfig->saveXML($nodeUIEMUser));
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
    }
} catch (\Exception $e) {
    error_log($e->getMessage());
}


