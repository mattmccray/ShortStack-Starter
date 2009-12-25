<?php 

class BaseController extends Controller {

  function authenticate($username, $password) {
    return ( doc('User')->where('username')->eq($username)->andWhere('password')->eq($password)->count() > 0 );
  }
  
  protected function renderTheme($view, $params=array(), $wrapInLayout=null) {
    $tmpl_file = App::ThemeTemplatePath($view);
    $params = $this->defaultData($params);
    if(file_exists($tmpl_file))
      $tmpl = new Template( $tmpl_file );
    else
      $tmpl = new Template( ShortStack::ViewPath($view) );
    $content = $tmpl->fetch($params);
    $this->renderThemeText($content, $params, $wrapInLayout);
  }
  
  protected function renderThemeText($text, $params=array(), $wrapInLayout=null) {
    $layoutView = ($wrapInLayout == null) ? $this->defaultLayout : $wrapInLayout;
    if($layoutView !== false) {
      $layout_file = App::ThemeTemplatePath($layoutView);
      $params = $this->defaultData($params);
      if(file_exists($layout_file))
        $layout = new Template( $layout_file );
      else
        $layout = new Template( ShortStack::ViewPath($layoutView) );
      $layout->contentForLayout = $text;
      $layout->display($params);
    } else {
      echo $content;
    }
  }
  
  protected function defaultData($params=array()) {
    global $config;
    if(!array_key_exists('site',$params)) {
      $params['site'] = (object)$config['site'];
    }
    if(!array_key_exists('title',$params)) {
      $params['title'] = false;
    }
    return $params;
  }
}