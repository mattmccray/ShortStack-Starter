<?php

class Theme {
  
  public $path = '';
  
  protected $full_path = '';

  public function __constructor($path) {
    $this->path = $path;
    $this->full_path = APPROOT."/themes/".$path;
  }

  public static function All() {
    // FIXME: Return a list of all available themes (from the themes/* folder)
    return array();
  }
  
}
