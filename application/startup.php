<?php

define("SYSROOT", dirname(__FILE__));

$config = array();
include_once('config.php');

date_default_timezone_set($config['site']['timezone']);

$shortstack_config = $config['shortstack'];

define('CURRENTTHEME', $config['site']['theme']);

include_once('lib/application.php');

function use_lib($file) {
  if(! strpos($file, '.php') > 0) $file .= ".php";
  require_once( SYSROOT."/lib/".$file );
}

try {
  include_once('lib/shortstack.php');
} catch (EmptyDbException $e) {
  $uri = (@$_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "";
  if(strpos($uri, 'install') < 1 ) {
    Dispatcher::Recognize('install');
  }
}
 
if(! Dispatcher::$dispatched ) {
  Dispatcher::Recognize();
}
