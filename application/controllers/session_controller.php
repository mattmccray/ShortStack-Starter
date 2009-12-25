<?php 

class SessionController extends BaseController {
  
  function index($args=null) {
    @ $redirectTo = $_SERVER['REQUEST_URI'];
    if($redirectTo == url_for('session')) $redirectTo = url_for('home');
    if($this->isPost())
      $this->login(array('redirectTo'=>$redirectTo));
    else
      $this->renderTheme('session/login', array('redirectTo'=>$redirectTo));
  }
  
  function login($args=array()) {
    if( $this->isPost() && $this->doLogin($_POST) ) {
      throw new FullRedirect( $_POST['redirectTo'] );
    }
    $this->renderTheme('session/login', array(
      'redirectTo' => ((@ $_POST['redirectTo']) ? @ $_POST['redirectTo'] : @ $args['redirectTo']),
      'errorMsg'   => 'Invalid credentials, please try again.'
    ));
  }
  
  function logout($args=array()) {
    $this->doLogout();
    throw new FullRedirect( url_for('home') );
  }
}
