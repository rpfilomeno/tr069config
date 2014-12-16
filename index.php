<?php

/*
 * set up for background processing
 */

ob_start();
error_reporting(E_ALL);
ignore_user_abort(true);
set_time_limit(0);
date_default_timezone_set('Asia/Singapore');

/*
 * check parameters
 */


if (!empty($_REQUEST['IPADDR'])) {
    $deviceIp = $_REQUEST['IPADDR'];
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $deviceIp = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $deviceIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $deviceIp = $_SERVER['REMOTE_ADDR'];
}

/*
 * close the http connection
 */

$size = ob_get_length();
header("Content-Length: $size");
header('Connection: close');
ob_end_flush();
ob_flush();
flush();
if (session_id()) session_write_close();

/*
 * run the rest in background processes
 */

require 'vendor/autoload.php';

try {


    /*
     * check options
     */

    $connectionModes = array('secure' => 'https', 'insecure' => 'http'); //use secure then fall back to insecure
    $passwordModes = array(true, false); //use hash then fallback to non-hash password


    /*
     * check config files
     */

    $csvConfigFilename = realpath(dirname(__FILE__)) . '/data/config-list.csv';
    if (file_exists($csvConfigFilename)) {
        $rows = array_map('str_getcsv', file($csvConfigFilename));
        $header = array_shift($rows);
        $csvConfigs = array();
        foreach ($rows as $row) {
            $csvConfigs[] = array_combine($header, $row);
        }
        error_log('Found ConfigFile="' . $csvConfigFilename . '" with RecordCount="' . count($csvConfigs) . '" for Device="' . $deviceIp . '".');
    } else {
        error_log('Missing ConfigFile="' . $csvConfigFilename . '" for Device="' . $deviceIp . '".');
        exit;
    }



    $defaultAccountsListFile = realpath(dirname(__FILE__)) . '/data/default-accounts.csv';
    if (file_exists($defaultAccountsListFile)) {
        $rows = array_map('str_getcsv', file($defaultAccountsListFile));
        $header = array_shift($rows);
        $csvDefaultAccountList = array();
        foreach ($rows as $row) {
            $csvDefaultAccountList[] = array_combine($header, $row);
        }
        error_log('Found AccountsFile="' . $defaultAccountsListFile . '" with RecordsCount="' . count($csvDefaultAccountList) . '" for Device="' . $deviceIp . '".');

    } else {
        error_log('Missing AccountsFile="' . $defaultAccountsListFile . '" for Device="' . $deviceIp .'".');
        exit;
    }

    /*
     * check other variables
     */

    $response = new \stdClass();
    $response->success = false;
    $response->data = null;

    $xmlConfigFilename = null;
    $lockFilename = null;
    $infoFilename = null;
    $eSpaceUsername = null;
    $eSpacePassword = null;
    $csvConfigData = null;
    $deviceMac = null;


    /*
     * main program block
     */

    $i = 0;
    foreach ($csvDefaultAccountList as $csvDefaultAccount) {
        $eSpaceUsername = $csvDefaultAccount['username'];
        $eSpacePassword = $csvDefaultAccount['password'];
        $i++;


        //** do connection modes */
        foreach($connectionModes as $connectionText => $connectionMode) {
            error_log('Checking Device="' . $deviceIp . ' using ConnMode="' . $connectionText . '".');
            $eSpace = new \Tr069Config\Espace\EspaceClass($connectionMode.'://' . $deviceIp, null, $eSpaceUsername);
            $response = $eSpace->requestSession($eSpaceUsername);
            if (!$response->success) {
                error_log('Failed to connect with ConnMode="' . $connectionText. '" to Device="' . $deviceIp . '" using Username="' . $eSpaceUsername.'" Attempt="' . $i . ' MaxAttempt="'. count($csvDefaultAccountList) . '".');
            } else {
                error_log('Successful to connect with ConnMode="' . $connectionText. '" to Device="' . $deviceIp . '" using Username="' . $eSpaceUsername.'" Attempt="' . $i . ' MaxAttempt="'. count($csvDefaultAccountList) . '".');
                break; //stop trying other connection mode
            }
        }//connection mode loop
        if (!$response->success) {
            continue; //cant connect, try next account
        } else {

            //** do logins */
            foreach ($passwordModes as $passwordMode) {

                $eSpace->setPasswordMode($passwordMode);

                $response = $eSpace->requestCertificate($eSpaceUsername, $eSpacePassword);
                if (!$response->success) {
                    error_log('Failed login to Device="' . $deviceIp .'"'
                        . ', Username="'    . $eSpaceUsername . '"'
                        . ', Password="'    .$eSpacePassword . '"'
                        . ', HashMode="'    .$passwordMode. '"'
                        . ', Attempt="'     . $i .'"'
                        . ', MaxAttempt="'    . count($csvDefaultAccountList) . '".');
                } else {
                    error_log('Success login to Device="' . $deviceIp .'"'
                        . ', Username="'    . $eSpaceUsername . '"'
                        . ', Password="'    .$eSpacePassword . '"'
                        . ', HashMode="'    .$passwordMode. '"'
                        . ', Attempt="'     . $i .'"'
                        . ', MaxAttempt="'  . count($csvDefaultAccountList) . '".');
                    break; //stop trying different password mode
                }
            }//Password mode loop
            if ($response->success) break; //stop looking for more accounts
        }
    }//Account loop

    if (!$response->success) {
        exit; //cant login, exit
    } else {

        //** do request info */
        $response = $eSpace->requestVersionInfo();
        if (!$response->success) {
            $this->logger->error('Cannot get hardware information of ' . $deviceIp . '.');
            exit; // cant get info, try next IP
        }

        //** do display result */
        $hardwareInfo = json_decode($response->data)->stMainVersionInfo;

        $msg = ''
            . 'Device="'                    . $deviceIp .'"'
            . ', Serial Number="'           . $hardwareInfo->szSN .'"'
            . ', Main SoftWare Version="'   . $hardwareInfo->szMainSoftWareVersion .'"'
            . ', Boot Version="'            . $hardwareInfo->szBootVersion .'"'
            . ', HardWare Version="'        . $hardwareInfo->szHardWareVersion .'"'
            . ', uild Version="'            . $hardwareInfo->szBuildVersion .'"';

        error_log('Hardware information for ' . $msg );

        //** Save the hardware information */
        $infoFilename = realpath(dirname(__FILE__)) . '/data/Config-eSpace-' . $hardwareInfo->szSN . '.info';
        file_put_contents($infoFilename, date("D M d H:i:s y")."\n" . str_replace(',',"\n",$msg));
    }

    /*
     * lookup if alternate xml config file if exist in xmlconfig directory
     */


    $altXmlConfigFileList = array();
    $altXmlConfigFileList[] = 'Config-eSpace-' . $hardwareInfo->szSN . '.xml';
    $altXmlConfigFileList[] = 'Config-eSpace-' . $hardwareInfo->szBuildVersion . '.xml';
    $altXmlConfigFileList[] = 'Config-eSpace-' . str_replace(' ', '-', $hardwareInfo->szHardWareVersion) . '.xml';
    $altXmlConfigFileList[] = 'Config-eSpace-' . str_replace(' ', '-', $hardwareInfo->szBootVersion) . '.xml';


    foreach ($altXmlConfigFileList as $altXmlConfigFilename) {
        $testAltXmlConfigFilename = realpath(dirname(__FILE__)) . '/xmlconfig/' . $altXmlConfigFilename;
        error_log('Looking for stored XMLConfigFile="' . $testAltXmlConfigFilename . '" for Device="' . $deviceIp .'".');
        if (file_exists($testAltXmlConfigFilename)) {
            $xmlConfigFilename = $testAltXmlConfigFilename;
            error_log('Using matching stored XMLConfigFile="' . $xmlConfigFilename . '" for Device="' . $deviceIp .'".');
            break;
        } else {
            error_log('XMLConfigFile="' . $testAltXmlConfigFilename . '" for Device="' . $deviceIp . '" was not found.');
        }
    }

    /*
     * generate a configuration xml
     */


    $xmlConfig = new \DOMDocument();
    $xmlConfig->load($xmlConfigFilename);


    foreach ($csvConfigs as $csvConfig) {
        if ($csvConfig['SerialNumber'] == $hardwareInfo->szSN) {
            $csvConfigData = $csvConfig;
            break;
        }
    }

    if (!$csvConfigData) {
        error_log('Device="' . $deviceIp . '" with SerialNumber="'.$hardwareInfo->szSN . '" was not found in ConfigFile="'.$xmlConfigFilename.'"');
        exit;
    }

    $msg=array();
    foreach($csvConfigData as $key=>$data) $msg[] = $key.'="' . $data .'"';
    $msglog = 'Device="' . $deviceIp . '" configuration data ' . implode(',',$msg);
    error_log($msglog);



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

    /*
     * save a copy of xml file
     */

    $xmlConfigFilename = realpath(dirname(__FILE__)) . '/data/Config-eSpace-' . $hardwareInfo->szSN . '.xml';
    file_put_contents($xmlConfigFilename, $xmlString);

    /*
     * check a lock file.
     * TODO: DHCP option 46 will always set the ACS url on boot even if the URL was changed in the configuration xml. A quick fix, we create a lock file to prevent tr069config from reconfiguring the phone on boot thus creating a indefinite reboot loop.
     */

    $lockFilename = realpath(dirname(__FILE__)) . '/data/Config-eSpace-' . $hardwareInfo->szSN . '.lock';
    if(file_exists($lockFilename)) {
        error_log('The Device="' . $deviceIp . '" has a LockFile="' . $lockFilename . '" that must be deleted to continue re-provision');
        exit;
    }


    /*
     * upload the configuration file
     */

    $temp_file = tempnam(sys_get_temp_dir(), 'eSp');
    file_put_contents($temp_file, $xmlString);
    $response = $eSpace->requestImportConfig($temp_file);
    if (!$response->success) {
        error_log('Failed upload XML configuration to Device="' . $deviceIp . "'");
        exit;
    }

    /*
     * create a lock file.
     *
     */

    file_put_contents($lockFilename, '{"timestamp":"' . date('m/d/Y h:i:s a', time()) . '","serial":"' . $hardwareInfo->szSN .'","ip":"' . $deviceIp . '"}');



} catch (\Exception $e) {
    error_log($e->getMessage());
}


