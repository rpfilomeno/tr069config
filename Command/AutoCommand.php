<?php
/**
 * Created by PhpStorm.
 * User: RFilomeno
 * Date: 31/10/2014
 * Time: 7:05 PM
 *
 * Sample command: php tr069config.php --debug autoconfig admin admin123 Config-eSpace7910.xml Config-eSpace7910.csv ip-list.txt
 */


namespace Tr069Config\Command;

use CLIFramework\Command;

class AutoConfigCommand extends Command
{

    public function brief()
    {
        return 'Automatically configure a set of eSpace devices with the given IP address list';
    }

    function init()
    {
        // register your subcommand here ..
    }

    function options($opts)
    {
        // command options
        $opts->add('w|write', 'write the generated xml file.');
        $opts->add('i|insecure', 'force non-https connection.');
        $opts->add('h|hash-password', 'provide password as a hash rather than plain-text.');
    }

    public function arguments($args)
    {
        $args->add('eSpaceUsername')->desc('eSpace username');
        $args->add('eSpacePassword')->desc('eSpace password');
        $args->add('xmlConfigFilename')->desc('XML configuration file containing the configuration structures.')->isa('file')->glob('*.xml');
        $args->add('csvConfigFilename')->desc('CSV configuration file containing account credentials.')->isa('file')->glob('*.csv');
        $args->add('txtConfigFilename')->desc('Text file containing a list of target IP addresses, this can be generated by the "scan" command.')->isa('file')->glob('*.txt');
    }

    function execute($eSpaceUsername, $eSpacePassword, $xmlConfigFilename, $csvConfigFilename, $txtConfigFilename)
    {


        $this->logger->info('Configuring eSpace devices defined in "' . $txtConfigFilename . '"  using XML "' . $xmlConfigFilename . '" with  account details from "' . $csvConfigFilename . '"');
        if($this->options->has('debug')) $this->logger->info('[Debugging is enabled]');

        if (file_exists($csvConfigFilename)) {
            $rows = array_map('str_getcsv', file($csvConfigFilename));
            $header = array_shift($rows);
            $csvConfigs = array();
            foreach ($rows as $row) {
                $csvConfigs[] = array_combine($header, $row);
            }
            $this->logger->info('Auto-configuration file "' . $csvConfigFilename . '" has been found with ' . count($csvConfigs) . '  record(s).');
        } else {
            $this->logger->error('Auto-configuration "' . $csvConfigFilename . '" does not exist.');
            return false;
        }

        if (file_exists($txtConfigFilename)) {
            $rows = file_get_contents($txtConfigFilename);
            $rows = str_replace("\n\r", "\n", $rows);
            $rows = str_replace("\r\n", "\n", $rows);
            $txtConfigs = array();
            $txtConfigs = explode("\n", $rows);

            $this->logger->info('IP scan list file "' . $txtConfigFilename . '" has been found with ' . count($txtConfigs) . ' record(s).');
        } else {
            $this->logger->error('IP scan list file "' . $txtConfigFilename . '" does not exist.');
            return false;
        }

        foreach ($txtConfigs as $deviceIpDetailsLine) {
            try {
                $deviceIpDetails = explode(',',$deviceIpDetailsLine);
                $deviceIp = $deviceIpDetails[0];


                $schema = ($this->options->has('insecure')) ? 'http' : 'https';
                $eSpace = new \Tr069Config\Espace\EspaceClass($schema.'://' . $deviceIp, null, $eSpaceUsername);
                if($this->options->has('hash-password')) $eSpace->setUseHashPassword(true);
                if($this->options->has('debug')) $eSpace->setDebug(true);

                $response = $eSpace->requestSession($eSpaceUsername);
                $this->logger->debug('EspaceClass::requestSession = ' . var_export($response, true));
                if (!$response->success) {
                    $this->logger->error('Unable to create new session.');
                    continue;
                }

                $response = $eSpace->requestCertificate($eSpaceUsername, $eSpacePassword);
                $this->logger->debug('EspaceClass::requestCertificate = ' . var_export($response, true));
                if (!$response->success) {
                    $this->logger->error('Invalid login.');
                    continue;
                }

                $response = $eSpace->requestVersionInfo();
                $this->logger->debug('EspaceClass::requestVersionInfo = ' . var_export($response, true));
                if (!$response->success) {
                    $this->logger->error('Cannot get hardware information.');
                    continue;
                }

                $hardwareInfo = json_decode($response->data)->stMainVersionInfo;
                $this->logger->debug('EspaceClass::requestVersionInfo->data(StdClass) = ' . var_export($hardwareInfo, true));
                $this->logger->info("Hardware Information = \n\tMain SoftWare Version: " . $hardwareInfo->szMainSoftWareVersion
                    . "\n\tBoot Version:          " . $hardwareInfo->szBootVersion
                    . "\n\tHardWare Version:      " . $hardwareInfo->szHardWareVersion
                    . "\n\tSerial Number:         " . $hardwareInfo->szSN
                    . "\n\tBuild Version:         " . $hardwareInfo->szBuildVersion
                );

                $xmlConfig = new \DOMDocument();
                $xmlConfig->load($xmlConfigFilename);
                $csvConfigData = null;

                foreach ($csvConfigs as $csvConfig) {
                    if ($csvConfig['SerialNumber'] == $hardwareInfo->szSN) {
                        $csvConfigData = $csvConfig;
                        $this->logger->info('Loading auto-configuration data for device serial number "' . $hardwareInfo->szSN . '"');
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

                                $this->logger->info("\nConfiguring " . $nodeAccount->getNodePath());

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
                                $this->logger->debug("XML Configutation updated = \n" . $xmlConfig->saveXML($nodeAccount));
                            }
                        }
                    }
                }

                //UIEMUser
                foreach ($nodeUIEMUsers = $xmlConfig->getElementsByTagName('UIEMUser') as $nodeUIEMUser) {
                    $this->logger->info("\nConfiguring " . $nodeUIEMUser->getNodePath());
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
                    $this->logger->info("\nConfiguring " . $nodeManagementServer->getNodePath());

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
                    $this->logger->debug("XML Configutation updated = \n" . $xmlConfig->saveXML($nodeManagementServer));
                }

                //Server (Phone)
                foreach ($nodeServers = $xmlConfig->getElementsByTagName('Server') as $nodeServer) {
                    $this->logger->info("\nConfiguring " . $nodeServer->getNodePath());

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

                    $this->logger->debug("XML Configutation updated = \n" . $xmlConfig->saveXML($nodeServer));
                }

                //WebAdminUser
                foreach ($nodeWebAdminUsers = $xmlConfig->getElementsByTagName('WebAdminUser') as $nodeWebAdminUser) {
                    $this->logger->info("\nConfiguring " . $nodeWebAdminUser->getNodePath());

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
                    $this->logger->debug("XML Configuration updated = \n" . $xmlConfig->saveXML($nodeWebAdminUser));

                }

                //WebAdminUserSecurity
                foreach ($nodeWebAdminUserSecuritys = $xmlConfig->getElementsByTagName('WebAdminUserSecurity') as $nodeWebAdminUserSecurity) {
                    $this->logger->info("\nConfiguring " . $nodeWebAdminUserSecurity->getNodePath());

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
                    $this->logger->debug("XML Configuration updated = \n" . $xmlConfig->saveXML($nodeWebAdminUserSecurity));
                }

                $xmlString = $xmlConfig->saveXML();
                $this->logger->debug("XML Configuration updated = \n" . $xmlString);

                if ($this->options->has('write')) {
                    $xmlSaveFilename = $xmlConfigFilename . '-' . $hardwareInfo->szSN . '.xml';
                    $this->logger->info("\nSaving XML to " . $xmlSaveFilename);
                    file_put_contents($xmlSaveFilename, $xmlString);
                }

                $temp_file = tempnam(sys_get_temp_dir(), 'eSp');
                file_put_contents($temp_file, $xmlString);

                $response = $eSpace->requestImportConfig($temp_file);
                $this->logger->debug('EspaceClass::requestImportConfig = ' . var_export($response, true));
                if (!$response->success) {
                    $this->logger->error('Failed upload XML configuration to device.');
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());

            }


        } //deviceIp


        return true;


    }
}