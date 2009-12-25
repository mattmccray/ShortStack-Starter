<?php

class User extends DocumentModel {
  protected $indexes = array( 'username'=>'STRING', 'password'=>'STRING' );
}