<?php

class Theme {
  
  $path = '';
  $full_path = '';

  public function __constructor($path) {
    $this->path = $path;
    $this->full_path = APPROOT."/themes/".$path;
  }

  public static All() {
    // FIXME: Return a list of all available themes (from the themes/* folder)
    return array();
  }
  
}
