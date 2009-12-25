<?php

function theme_url_for($path) {
  return url_for("themes/".CURRENTTHEME."/".$path);
}
