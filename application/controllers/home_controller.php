<?php 

class HomeController extends BaseController {
 
  function index( $args=array() ) {
  
    $this->renderTheme('home/dashboard');
    
  }
  
}

