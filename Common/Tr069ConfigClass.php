<?php
/**
 * Created by PhpStorm.
 * User: RFilomeno
 * Date: 30/10/2014
 * Time: 11:41 PM
 */

namespace Tr069Config;

use CLIFramework\Application;


class Tr069ConfigClass extends Application
{
    const NAME = 'Tr069Config';
    const VERSION = '0.0.1';

    public function brief() { return 'Tr069Config allow configuration of eSpace IP Phones via XML file'; }



    /* register your command here */
    public function init()
    {
        parent::init();
        $this->command( 'scan', '\Tr069Config\Command\ScanCommand' );
        $this->command( 'configure', '\Tr069Config\Command\ConfigureCommand' );
        $this->command( 'autoconfig', '\Tr069Config\Command\AutoConfigCommand' );

    }

}