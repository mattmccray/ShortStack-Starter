<?php 

class HomeController extends BaseController {
 
  function index( $args=array() ) {
    
    debug($this->cacheName);
    
    $this->renderTheme('home/dashboard');
    
  }
  
}

