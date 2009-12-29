<?php 

class MissingController extends BaseController {

  protected $cacheOutput = false;
  
  function index($args=array()) {
    $this->renderTheme('404', array('uri'=>"/".join('/', $args)));
  }
}
