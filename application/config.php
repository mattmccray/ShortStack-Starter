<?php

// ====================
// = Site information =
// ====================
$config['site'] = array(
  'title'   => '',
  'tagline' => "",
  'author'  => "",
  'email'   => '',
  'base_url'=> 'http://sss.local',
  'theme'   => 'default',
  'timezone'=> 'America/Chicago'
);

// ===========================
// = Default Admin user info =
// ===========================
$config['admin'] = array(
  'username' => 'admin',
  'password' => 'admin'
);


// =======================================
// = Shortstack (MVC) Framework Settings =
// =======================================
$config['shortstack'] = array(
  'db' => array(
    'engine'   => 'sqlite', // Only one supported as yet
    'database' => 'application/data/database.sqlite3',
    'autoconnect' => true,
    'verify' => true,
  ),
  'models' => array(
    'folder' => 'application/models',
  ),
  'views' => array(
    'folder' => 'application/views',
    'force_short_tags'=>false,
  ),
  'controllers' => array(
    'folder' => 'application/controllers',
    '404_handler'=>'missing',
    'index'=>'home',
  ),
  'helpers' => array(
    'folder' => 'application/helpers',
    'autoload'=> array('theme'),
  ),
  'cacheing' => array(
    'folder' => 'application/data/cache',
    'enabled' => false,
    'expires' => 60, // Seconds...
  ),
);

// ==============================
// = Not Supported, Use Caution =
// ==============================
$config['core'] = array(
  'bin_dir'     => "/usr/bin/",
  'admin_theme' => "admin"
);
