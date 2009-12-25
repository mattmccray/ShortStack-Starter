<?php

class User extends Document {
  protected $indexes = array(
    'username'=>'STRING', 
    'password'=>'STRING'
  );
}