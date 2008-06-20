<?php

$path_extra = dirname(dirname(__FILE__)).'/classes/php-openid-2.0.0';
$path = ini_get('include_path');
$path = $path_extra . PATH_SEPARATOR . $path;
ini_set('include_path', $path);
unset ($path);

include_once( 'Auth/OpenID/Consumer.php');
include_once( 'Auth/OpenID/FileStore.php');
include_once( 'Auth/OpenID/SReg.php');
include_once( 'Auth/OpenID/PAPE.php');
include_once( 'Auth/OpenID/URINorm.php');

global $pape_policy_uris;
$pape_policy_uris = array(
        PAPE_AUTH_MULTI_FACTOR_PHYSICAL,
        PAPE_AUTH_MULTI_FACTOR,
        PAPE_AUTH_PHISHING_RESISTANT
        );

function &getStore() {
    $varDirPath = realpath( eZSys::varDirectory() );
    $store_path = $varDirPath . eZSys::fileSeparator() . 'openid_consumer' ;

    if (!file_exists($store_path) &&
        !mkdir($store_path)) {
        print "Could not create the FileStore directory '$store_path'. ".
            " Please check the effective permissions.";
        exit(0);
    }

    $store = new Auth_OpenID_FileStore($store_path);
    return $store;
}

function &getConsumer() {
    /**
     * Create a consumer object using the store object created
     * earlier.
     */
    $store = getStore();
    return new Auth_OpenID_Consumer($store);
}

function getScheme() {
    $scheme = 'http';
    if (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') {
        $scheme .= 's';
    }
    return $scheme;
}

function getReturnTo($function_name = 'login') {
    $returnTo = sprintf("%s://%s:%s%s/%s",
                   getScheme(), $_SERVER['SERVER_NAME'],
                   $_SERVER['SERVER_PORT'],
                   dirname($_SERVER['PHP_SELF']),
                   $function_name);
   return $returnTo;
}

function getTrustRoot() {
    $trustRoot = sprintf("%s://%s:%s%s/",
                   getScheme(), $_SERVER['SERVER_NAME'],
                   $_SERVER['SERVER_PORT'],
                   dirname($_SERVER['PHP_SELF']));
   return $trustRoot;
}

?>
