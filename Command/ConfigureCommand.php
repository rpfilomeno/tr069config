<?php
/**
 * Created by PhpStorm.
 * User: RFilomeno
 * Date: 31/10/2014
 * Time: 7:05 PM
 *
 * Sample command: php tr069config.php --debug configure --read Config-eSpace7910.csv --write Config-eSpace7910-10.55.54.170.xml --yes 10.55.54.170 admin admin123 Config-eSpace7910.xml
 */


namespace Tr069Config\Command;

use CLIFramework\Command;
use CLIFramework\Exception\InvalidCommandArgumentException;

class ConfigureCommand extends Command
{

    public function brief()
    {
        return 'Configure an  eSpace device with the given IP address';
    }

    function init()
    {
        // register your subcommand here ..
    }

    function options($opts)
    {
        // command options
        $opts->add('r|read::', 'use an auto configuration CSV file that contains the input parameters based on hardware serial number.')
            ->isa('File');
        $opts->add('w|write:', 'write the generated xml file.');
        $opts->add('y|yes', 'auto confirm prompts.');



    }

    public function arguments($args)
    {
        $args->add('deviceIp')->desc('eSpace device IP address');
        $args->add('eSpaceUsername')->desc('eSpace username');
        $args->add('eSpacePassword')->desc('eSpace password');
        $args->add('xmlConfigFilename')->desc('XML configuration file')->isa('file')->glob('*.xml');
    }

    function execute($deviceIp, $eSpaceUsername, $eSpacePassword, $xmlConfigFilename)
    {

        $e = false;

        if (!filter_var($deviceIp, FILTER_VALIDATE_IP)) {
            $e = new InvalidCommandArgumentException($this, 1, $deviceIp);
            $this->logger->error($e->getMessage());
        }


        if (!filter_var($xmlConfigFilename, FILTER_SANITIZE_STRING)) {
            $e = new InvalidCommandArgumentException($this, 1, $xmlConfigFilename);
            $this->logger->error($e->getMessage());
        }


        if ($e !== false) return false;

        $this->logger->info('Configuring eSpace device with IP "' . $deviceIp . ' using XML ' . $xmlConfigFilename . '" ...');

        try {


            if($this->options->has('read')) {
                $csvConfigFilename = $this->options['read']->value;
                if(file_exists($csvConfigFilename)) {
                    $rows = array_map('str_getcsv', file($csvConfigFilename));
                    $header = array_shift($rows);
                    $csvConfigs = array();
                    foreach ($rows as $row) {
                        $csvConfigs[] = array_combine($header, $row);
                    }
                    $this->logger->info('Auto-configuration file "'. $csvConfigFilename .'" has been found with '.count($csvConfigs) .' record(s).');
                } else {
                    $this->logger->error('Auto-configuration flag (--auto) is set but file "'. $csvConfigFilename .'" does not exist.');
                    return false;
                }
            }


            $eSpace = new \Tr069Config\Espace\EspaceClass('https://' . $deviceIp, null, $eSpaceUsername);

            $response = $eSpace->requestSession($eSpaceUsername);
            $this->logger->debug('EspaceClass::requestSession = ' . var_export($response, true));
            if (!$response->success) {
                $this->logger->error('Unable to create new session.');
                return false;
            }

            $response = $eSpace->requestCertificate($eSpaceUsername, $eSpacePassword);
            $this->logger->debug('EspaceClass::requestCertificate = ' . var_export($response, true));
            if (!$response->success) {
                $this->logger->error('Invalid login.');
                return false;
            }

            $response = $eSpace->requestVersionInfo();
            $this->logger->debug('EspaceClass::requestVersionInfo = ' . var_export($response, true));
            if (!$response->success) {
                $this->logger->error('Cannot get hardware information.');
                return false;
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

            if($this->options->has('read')) {

                foreach($csvConfigs as $csvConfig ) {
                    if($csvConfig['SerialNumber'] == $hardwareInfo->szSN) {
                        $csvConfigData = $csvConfig;
                        $this->logger->info('Loading auto-configuration data for device serial number "'.$hardwareInfo->szSN.'"');
                        break;
                    }
                }

                if(!$csvConfigData) {

                    $input = strtolower($this->ask('Serial number "' . $hardwareInfo->szSN . '"' . " was not found in the auto configuration file. \nProceed with interactive configuration mode?", ['y', 'n']));
                    if ($input === 'n') return false;
                }
            }

            // Configuring UserCfg
            foreach ($UserCfg = $xmlConfig->getElementsByTagName('UserCfg') as $root) {
                foreach ($nodeAcoounts = $root->getElementsByTagName('*') as $nodeAccount) {
                    if (preg_match('/Account\d/', $nodeAccount->nodeName)) {
                        if(isset($csvConfigData[$nodeAccount->nodeName.'.Enable'])) {
                            $input = 'y';
                        } else $input = 'n'; //just say no if other accounts dont exists
                            //$input = strtolower($this->ask('Configure ' . $nodeAccount->getNodePath() . '?', ['y', 'n']));

                        if ($input === 'y') {
                            while (true) {

                                $this->logger->info("\nConfiguring " . $nodeAccount->getNodePath());

                                $input  = (isset($csvConfigData[$nodeAccount->nodeName.'.Enable']))
                                    ? $csvConfigData[$nodeAccount->nodeName.'.Enable']
                                    : $this->ask('Set Enable (' . $nodeAccount->getAttribute('Enable') . '):', ['1', '0']);
                                $input = filter_var($input, FILTER_VALIDATE_INT);
                                $nodeAccount->setAttribute('Enable', $input);

                                $input  = (isset($csvConfigData[$nodeAccount->nodeName.'.Account']))
                                    ? $csvConfigData[$nodeAccount->nodeName.'.Account']
                                    : $this->ask('Set Account (' . $nodeAccount->getAttribute('Account') . '):');
                                $input = filter_var($input, FILTER_SANITIZE_STRING);
                                $nodeAccount->setAttribute('Account', $input);

                                $input  = (isset($csvConfigData[$nodeAccount->nodeName.'.LogOut']))
                                    ? $csvConfigData[$nodeAccount->nodeName.'.LogOut']
                                    : $this->ask('Set LogOut (' . $nodeAccount->getAttribute('LogOut') . '):', ['1', '0']);
                                $input = filter_var($input, FILTER_VALIDATE_INT);
                                $nodeAccount->setAttribute('LogOut', $input);

                                $input  = (isset($csvConfigData[$nodeAccount->nodeName.'.LabelName']))
                                    ? $csvConfigData[$nodeAccount->nodeName.'.LabelName']
                                    : $this->ask('Set LabelName (' . $nodeAccount->getAttribute('LabelName') . '):');
                                $input = filter_var($input, FILTER_SANITIZE_STRING);
                                $nodeAccount->setAttribute('LabelName', $input);

                                $input  = (isset($csvConfigData[$nodeAccount->nodeName.'.PGMNumber']))
                                    ? $csvConfigData[$nodeAccount->nodeName.'.PGMNumber']
                                    : $this->ask('Set PGMNumber (' . $nodeAccount->getAttribute('PGMNumber') . '):');
                                $input = filter_var($input, FILTER_SANITIZE_STRING);
                                $nodeAccount->setAttribute('PGMNumber', $input);

                                $input  = (isset($csvConfigData[$nodeAccount->nodeName.'.CorpID']))
                                    ? $csvConfigData[$nodeAccount->nodeName.'.CorpID']
                                    : $this->ask('Set CorpID (' . $nodeAccount->getAttribute('CorpID') . '):');
                                $input = filter_var($input, FILTER_SANITIZE_STRING);
                                $nodeAccount->setAttribute('CorpID', $input);


                                foreach ($nodeAuths = $nodeAccount->getElementsByTagName('Auth') as $nodeAuth) {

                                    $input  = (isset($csvConfigData[$nodeAccount->nodeName.'.Auth.UserName']))
                                        ? $csvConfigData[$nodeAccount->nodeName.'.Auth.UserName']
                                        : $this->ask('Set UserName (' . $nodeAuth->getAttribute('UserName') . '):');
                                    if ($input) {
                                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                                        $nodeAuth->setAttribute('UserName', $input);
                                    }

                                    $input  = (isset($csvConfigData[$nodeAccount->nodeName.'.Auth.Passwd']))
                                        ? $csvConfigData[$nodeAccount->nodeName.'.Auth.Passwd']
                                        : $this->ask('Set Passwd (' . $nodeAuth->getAttribute('Passwd') . '):');
                                    if ($input) {
                                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                                        $nodeAuth->removeAttribute('Passwd');
                                        $nodeAuth->setAttribute('Passwd', $input);
                                    }
                                }
                                $this->logger->debug("XML Configutation updated = \n" . $xmlConfig->saveXML($nodeAccount));
                                $input  = ($this->options->has('yes') && $this->options->has('read'))
                                    ? 'y'
                                    : strtolower($this->ask('Is this correct? ', ['y', 'n']));
                                if ($input === 'y') break;
                            }
                        }
                    }
                }
            }

            //UIEMUser
            foreach ($nodeUIEMUsers = $xmlConfig->getElementsByTagName('UIEMUser') as $nodeUIEMUser) {
                $this->logger->info("\nConfiguring " . $nodeUIEMUser->getNodePath());

                while (true) {
                    $input  = (isset($csvConfigData['UIEMUser.UserNO']))
                        ? $csvConfigData['UIEMUser.UserNO']
                        : $this->ask('Set UserNO (' . $nodeUIEMUser->getAttribute('UserNO') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeUIEMUser->setAttribute('UserNO', $input);
                    }
                    $input  = (isset($csvConfigData['UIEMUser.UserName']))
                        ? $csvConfigData['UIEMUser.UserName']
                        : $this->ask('Set UserName (' . $nodeUIEMUser->getAttribute('UserName') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeUIEMUser->setAttribute('UserName', $input);
                    }
                    $input  = (isset($csvConfigData['UIEMUser.PassWord']))
                        ? $csvConfigData['UIEMUser.PassWord']
                        : $this->ask('Set PassWord (' . $nodeUIEMUser->getAttribute('PassWord') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeUIEMUser->setAttribute('PassWord', $input);
                    }
                    $this->logger->debug("XML Configutation updated = \n" . $xmlConfig->saveXML($nodeUIEMUser));
                    $input  = ($this->options->has('yes') && $this->options->has('read'))
                        ? 'y'
                        : strtolower($this->ask('Is this correct? ', ['y', 'n']));
                    if ($input === 'y') break;
                }
            }

            //ManagementServer
            foreach ($nodeManagementServers = $xmlConfig->getElementsByTagName('ManagementServer') as $nodeManagementServer) {
                $this->logger->info("\nConfiguring " . $nodeManagementServer->getNodePath());

                while (true) {
                    $input  = (isset($csvConfigData['ManagementServer.URL']))
                        ? $csvConfigData['ManagementServer.URL']
                        : $this->ask('Set URL (' . $nodeManagementServer->getAttribute('URL') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_VALIDATE_URL);
                        $nodeManagementServer->setAttribute('URL', $input);
                    }
                    $input  = (isset($csvConfigData['ManagementServer.Username']))
                        ? $csvConfigData['ManagementServer.Username']
                        : $this->ask('Set Username (' . $nodeManagementServer->getAttribute('Username') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeManagementServer->setAttribute('Username', $input);
                    }
                    $input  = (isset($csvConfigData['ManagementServer.Password']))
                        ? $csvConfigData['ManagementServer.Password']
                        : $this->ask('Set Password (' . $nodeManagementServer->getAttribute('Password') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeManagementServer->setAttribute('Password', $input);
                    }
                    $input  = (isset($csvConfigData['ManagementServer.ConnectionRequestUsername']))
                        ? $csvConfigData['ManagementServer.ConnectionRequestUsername']
                        : $this->ask('Set ConnectionRequestUsername (' . $nodeManagementServer->getAttribute('ConnectionRequestUsername') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeManagementServer->setAttribute('ConnectionRequestUsername', $input);
                    }
                    $input  = (isset($csvConfigData['ManagementServer.ConnectionRequestPassword']))
                        ? $csvConfigData['ManagementServer.ConnectionRequestPassword']
                        : $this->ask('Set ConnectionRequestPassword (' . $nodeManagementServer->getAttribute('ConnectionRequestPassword') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeManagementServer->setAttribute('ConnectionRequestPassword', $input);
                    }
                    $input  = (isset($csvConfigData['ManagementServer.ConnectionAuthType']))
                        ? $csvConfigData['ManagementServer.ConnectionAuthType']
                        : $this->ask('Set ConnectionAuthType (' . $nodeManagementServer->getAttribute('ConnectionAuthType') . '):',['0','1','2']);
                    if ($input) {
                        $input = filter_var($input, FILTER_VALIDATE_INT);
                        $nodeManagementServer->setAttribute('ConnectionAuthType', $input);
                    }
                    $this->logger->debug("XML Configutation updated = \n" . $xmlConfig->saveXML($nodeManagementServer));
                    $input  = ($this->options->has('yes') && $this->options->has('read'))
                        ? 'y'
                        : strtolower($this->ask('Is this correct? ', ['y', 'n']));
                    if ($input === 'y') break;
                }
            }

            //Server (Phone)
            foreach ($nodeServers = $xmlConfig->getElementsByTagName('Server') as $nodeServer) {
                $this->logger->info("\nConfiguring " . $nodeServer->getNodePath());

                while (true) {
                    $input  = (isset($csvConfigData['Server.PrimaryRegServerAddress']))
                        ? $csvConfigData['Server.PrimaryRegServerAddress']
                        : $this->ask('Set PrimaryRegServerAddress (' . $nodeServer->getAttribute('PrimaryRegServerAddress') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_VALIDATE_IP);
                        $nodeServer->setAttribute('PrimaryRegServerAddress', $input);
                    }
                    $input  = (isset($csvConfigData['Server.PrimaryRegServerPort']))
                        ? $csvConfigData['Server.PrimaryRegServerPort']
                        : $this->ask('Set PrimaryRegServerPort (' . $nodeServer->getAttribute('PrimaryRegServerPort') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_VALIDATE_INT);
                        $nodeServer->setAttribute('PrimaryRegServerPort', $input);
                    }
                    $input  = (isset($csvConfigData['Server.BackupRegServerAddress']))
                        ? $csvConfigData['Server.BackupRegServerAddress']
                        : $this->ask('Set BackupRegServerAddress (' . $nodeServer->getAttribute('BackupRegServerAddress') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_VALIDATE_IP);
                        $nodeServer->setAttribute('BackupRegServerAddress', $input);
                    }
                    $input  = (isset($csvConfigData['Server.BackupRegServerPort']))
                        ? $csvConfigData['Server.BackupRegServerPort']
                        : $this->ask('Set BackupRegServerPort (' . $nodeServer->getAttribute('BackupRegServerPort') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_VALIDATE_INT);
                        $nodeServer->setAttribute('BackupRegServerPort', $input);
                    }

                    $this->logger->debug("XML Configutation updated = \n" . $xmlConfig->saveXML($nodeServer));
                    $input  = ($this->options->has('yes') && $this->options->has('read'))
                        ? 'y'
                        : strtolower($this->ask('Is this correct? ', ['y', 'n']));
                    if ($input === 'y') break;
                }
            }

            //WebAdminUser
            foreach ($nodeWebAdminUsers = $xmlConfig->getElementsByTagName('WebAdminUser') as $nodeWebAdminUser) {
                $this->logger->info("\nConfiguring " . $nodeWebAdminUser->getNodePath());

                while (true) {
                    $input  = (isset($csvConfigData['WebAdminUser.UserName']))
                        ? $csvConfigData['WebAdminUser.UserName']
                        : $this->ask('Set UserName (' . $nodeWebAdminUser->getAttribute('UserName') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeWebAdminUser->setAttribute('UserName', $input);
                    }
                    $input  = (isset($csvConfigData['WebAdminUser.PassWord']))
                        ? $csvConfigData['WebAdminUser.PassWord']
                        : $this->ask('Set PassWord (' . $nodeWebAdminUser->getAttribute('PassWord') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeWebAdminUser->setAttribute('PassWord', $input);
                    }
                    $this->logger->debug("XML Configuration updated = \n" . $xmlConfig->saveXML($nodeWebAdminUser));
                    $input  = ($this->options->has('yes') && $this->options->has('read'))
                        ? 'y'
                        : strtolower($this->ask('Is this correct? ', ['y', 'n']));
                    if ($input === 'y') break;
                }
            }

            //WebAdminUserSecurity
            foreach ($nodeWebAdminUserSecuritys = $xmlConfig->getElementsByTagName('WebAdminUserSecurity') as $nodeWebAdminUserSecurity) {
                $this->logger->info("\nConfiguring " . $nodeWebAdminUserSecurity->getNodePath());

                while (true) {
                    $input  = (isset($csvConfigData['WebAdminUserSecurity.UserName']))
                        ? $csvConfigData['WebAdminUserSecurity.UserName']
                        : $this->ask('Set UserName (' . $nodeWebAdminUserSecurity->getAttribute('UserName') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeWebAdminUserSecurity->setAttribute('UserName', $input);
                    }
                    $input  = (isset($csvConfigData['WebAdminUserSecurity.PassWord']))
                        ? $csvConfigData['WebAdminUserSecurity.PassWord']
                        : $this->ask('Set PassWord (' . $nodeWebAdminUserSecurity->getAttribute('PassWord') . '):');
                    if ($input) {
                        $input = filter_var($input, FILTER_SANITIZE_STRING);
                        $nodeWebAdminUserSecurity->setAttribute('PassWord', $input);
                    }
                    $this->logger->debug("XML Configuration updated = \n" . $xmlConfig->saveXML($nodeWebAdminUserSecurity));
                    $input  = ($this->options->has('yes') && $this->options->has('read'))
                        ? 'y'
                        : strtolower($this->ask('Is this correct? ', ['y', 'n']));
                    if ($input === 'y') break;
                }
            }

            $xmlString = $xmlConfig->saveXML();
            $this->logger->debug("XML Configuration updated = \n" . $xmlString);

            if($this->options->has('write')) {
                //$xmlSaveFilename = $xmlConfigFilename.'-'.$hardwareInfo->szSN.'.xml';
                $xmlSaveFilename = $this->options['write']->value;
                $this->logger->info("\nSaving XML to " . $xmlSaveFilename);
                file_put_contents($xmlSaveFilename,$xmlString);
            }

            $temp_file = tempnam(sys_get_temp_dir(), 'eSp');
            file_put_contents($temp_file,$xmlString);

            $response = $eSpace->requestImportConfig($temp_file);
            $this->logger->debug('EspaceClass::requestImportConfig = ' . var_export($response, true));
            if (!$response->success) {
                $this->logger->error('Failed upload XML configuration to device.');
                return false;
            }





            return true;

        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }

    }
}