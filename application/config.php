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

// ==========================
// = Admin user information =
// ==========================
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
    'cache' => 'application/data/cache',
    'force_short_tags'=>false,
  ),
  'controllers' => array(
    'folder' => 'application/controllers',
    '404_handler'=>'home',
  ),
  'helpers' => array(
    'folder' => 'application/helpers',
    'autoload'=> array('theme'),
  ),
);

// ==============================
// = Not Supported, Use Caution =
// ==============================
$config['core'] = array(
  'bin_dir'     => "/usr/bin/",
  'admin_theme' => "admin"
);
