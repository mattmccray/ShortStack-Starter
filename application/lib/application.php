<?php

class App {
  
  public static function ThemePath($path) {
    return APPROOT."/themes/".CURRENTTHEME."/$path";
  }
  
  public static function ThemeTemplatePath($template) {
//    if(strpos($template, '.php') < 1) $template .= ".php";
    return self::ThemePath("views/$template.php");
  }
  
}
