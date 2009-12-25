<?php

class App {
  
  public static function themePath($path) {
    return APPROOT."/themes/".CURRENTTHEME."/$path";
  }
  
  public static function themeTemplatePath($template) {
//    if(strpos($template, '.php') < 1) $template .= ".php";
    return self::themePath("views/$template.php");
  }
  
}
