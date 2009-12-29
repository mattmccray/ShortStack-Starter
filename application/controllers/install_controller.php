<?php 

class InstallController extends BaseController {
  
  protected $cacheOutput = false;

  function index($args=array()) {
    if(!EMPTYDB) {
//      echo "";
//      exit(0);
      throw new NotFoundException();
    }
    if(count($args) > 0 && $args[0] == 'make-it-so' && $this->isPost() ) {
      $modelnames = ShortStack::InitializeDatabase();
      global $config;
      
      $user = new User();
      $user->update($config['admin']); // Assign default values...
      $user->name = "Administrator";
      $user->email = "admin@mydomain.com";
      $user->save();
      
      $this->renderTheme('install/finished', array('models'=>$modelnames));

    } else if(count($args) > 0 && $args[0] == 'FORCE') { // Remove this clause... Probably
      $modelnames = ShortStack::InitializeDatabase();
      $this->renderTheme('install/finished', array('models'=>$modelnames));

    } else {
      $this->renderTheme('install/index');
    }
  }
  
  function clear_cache($args=array()) {
    Cache::Clear();
    throw new FullRedirect( url_for('home') );
  }
  
}