<?php
/**
 *
 * Sample: php tr069config.php --debug scan --write ip-list.txt 10.55.54.170 10.55.54.179 admin admin123
 *
 */

namespace Tr069Config\Command;

use CLIFramework\Command;
use CLIFramework\Exception\InvalidCommandArgumentException;

class ScanCommand extends Command
{

    public function brief()
    {
        return 'Scan an IP range for eSpace devices and returns the IP if detected';
    }

    function init()
    {
        // register your subcommand here ..
    }

    function options($opts)
    {
        // command options
        $opts->add('w|write:', 'write the IP addresses of detected eSpace devices.');
        $opts->add('i|insecure', 'force non-https connection.');
        $opts->add('s|secure', 'force non-https connection.');
        $opts->add('h|hash-password', 'provide password as a hash rather than plain-text.');

    }

    public function arguments($args)
    {
        $args->add('startIp')->desc('Starting IP');
        $args->add('endIp')->desc('Ending IP');
        $args->add('eSpaceUsername')->desc('eSpace username');
        $args->add('eSpacePassword')->desc('eSpace password');
    }

    function execute($startIp, $endIp, $eSpaceUsername, $eSpacePassword)
    {
        $e = false;

        if (!filter_var($startIp, FILTER_VALIDATE_IP)) {
            $e = new InvalidCommandArgumentException($this, 1, $startIp);
            $this->logger->error($e->getMessage());
        }


        if (!filter_var($endIp, FILTER_VALIDATE_IP)) {
            $e = new InvalidCommandArgumentException($this, 1, $endIp);
            $this->logger->error($e->getMessage());
        }

        $ipList = array();
        $startIpLong = ip2long($startIp);
        $endIpLong = ip2long($endIp);

        if ($endIpLong <= $startIpLong) {
            $this->logger->error('Invalid IP range.');
        }

        if($e !== false) return false;


        $this->logger->info('Scanning IP range "' . $startIp . ' -> ' . $endIp . '" ...');
        if($this->options->has('debug')) $this->logger->info('[Debugging is enabled]');

        $i = $startIpLong-1;
        while( $i <= $endIpLong) {
            try {
                $i++;
                $deviceIp = long2ip($i);




                if ($this->options->has('insecure')) { //force insecure
                    $this->logger->debug('Checking IP "' . $deviceIp . '" (force insecure).');
                    $eSpace = new \Tr069Config\Espace\EspaceClass('http://' . $deviceIp, null, $eSpaceUsername);
                    if ($this->options->has('hash-password')) $eSpace->setUseHashPassword(true);
                    if ($this->options->has('debug')) $eSpace->setDebug(true);
                    $response = $eSpace->requestSession($eSpaceUsername);
                }elseif ($this->options->has('secure')) { //force secure
                    $this->logger->debug('Checking IP "' . $deviceIp . '" (force insecure).');
                    $eSpace = new \Tr069Config\Espace\EspaceClass('https://' . $deviceIp, null, $eSpaceUsername);
                    if ($this->options->has('hash-password')) $eSpace->setUseHashPassword(true);
                    if ($this->options->has('debug')) $eSpace->setDebug(true);
                    $response = $eSpace->requestSession($eSpaceUsername);
                } else { //secure then fall back to insecure
                    $this->logger->debug('Checking IP "' . $deviceIp . '".');
                    $eSpace = new \Tr069Config\Espace\EspaceClass('https://' . $deviceIp, null, $eSpaceUsername);
                    if($this->options->has('hash-password')) $eSpace->setUseHashPassword(true);
                    if($this->options->has('debug')) $eSpace->setDebug(true);
                    $response = $eSpace->requestSession($eSpaceUsername);

                    if (!$response->success) {
                        $this->logger->debug('Unable to open secure session to client, trying insecure method on IP "' . $deviceIp . '".');
                        $eSpace = new \Tr069Config\Espace\EspaceClass('http://' . $deviceIp, null, $eSpaceUsername);
                        if($this->options->has('hash-password')) $eSpace->setUseHashPassword(true);
                        if($this->options->has('debug')) $eSpace->setDebug(true);
                        $response = $eSpace->requestSession($eSpaceUsername);
                    }
                }


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

                $this->logger->info('eSpace device found at '.$deviceIp);

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







                $ipList[] = $deviceIp . ','
                    . $hardwareInfo->szSN . ','
                    . $hardwareInfo->szMainSoftWareVersion . ','
                    . $hardwareInfo->szBootVersion . ','
                    . $hardwareInfo->szHardWareVersion . ','
                    . $hardwareInfo->szBuildVersion
                ;

            } catch (\Exception $e) {
                $this->logger->debug($e->getMessage());
            }
        }

        $this->logger->info('Finished. Scan found ' . count($ipList) . ' eSpace device(s).');

        if( count($ipList) && $this->options->has('write') ) {
            file_put_contents($this->options['write']->value,implode("\n",$ipList));
            $this->logger->info('Scan result written to file "' . $this->options['write']->value . '"');
        }

        return true;

    }
}