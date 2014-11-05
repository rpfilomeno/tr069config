<?php
/**
 * Created by PhpStorm.
 * User: RFilomeno
 * Date: 30/10/2014
 * Time: 11:38 PM
 */
//error_reporting(0);
require 'vendor/autoload.php';


try{
    $logger = new CLIFramework\Logger();
    $app = new Tr069Config\Tr069ConfigClass();
    $app->run( $argv );
} catch (Exception $e) {
    $logger->error( $e->getMessage());
}



