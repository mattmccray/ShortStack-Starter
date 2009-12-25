<?php

class CrudController extends Controller {
  protected $modelName = false;

  function __construct($modelName=false) {
    $this->modelName = $modelName;
  }
  
  function execute($params=array()) { 
    if(!$this->modelName) $this->modelName = array_shift($params);
    try{
      $id = array_shift($params);
      header('Content-Type: text/javascript');
//      header('Content-Type: application/json');
      // Read
      if($this->isGet()) { $this->_doGet($id, $params); }
      // Create
      else if($this->isPost()) { $this->_doPost($id, $params); }
      // Update
      else if($this->isPut()) { $this->_doPut($id, $params); }
      // Delete
      else if($this->isDelete()) { $this->_doDelete($id, $params); }
    } catch( Exception $e ) {
      $this->respondError($e->getMessage());
    }
  }
  
  protected function _doGet($id, $params) {
    $modelClass = camelize($this->modelName);
    if($id) {
      if($id == 'q') {
        $this->_handleFind($params, get($modelClass));
      } else {
        $mdl = get($modelClass, $id);
        if($mdl) {
          $this->respondSuccess($mdl);
        } else {
          throw new NotFoundException("No $modelClass found with an id of $id");
        }
      }
    } else {
      $this->respondSuccess(get($modelClass)->fetch());
    }
  }
  
  protected function _doPost($id, $params) {
    $modelClass = camelize($this->modelName);
//    print_r($_POST);
    $mdl = new $modelClass();
    $mdl->update($_POST[$this->modelName]);
    $mdl->save();
    $this->respondSuccess($mdl);
  }
  
  protected function _doPut($id, $params) {
    if($id) {
      $modelClass = camelize($this->modelName);
      $mdl = get($modelClass, $id);
      if($mdl) {
        $mdl->updateValues($_POST[$this->modelName]);
        $mdl->save();
        $this->respondSuccess($mdl);
      } else {
        throw new NotFoundException("No $modelClass found with an id of $id");
      }
    } else {
      throw new Exception("No ID specified");
    }
  }
  
  protected function _doDelete($id, $params) {
    if($id) {
      $modelClass = camelize($this->modelName);
      $mdl = get($modelClass, $id);
      if($mdl) {
        $mdl->destroy();
        $this->respondSuccess($mdl);
      } else {
        throw new NotFoundException("No $modelClass found with an id of $id");
      }
    } else {
      throw new Exception("No ID specified");
    }
  }
  
  // Tricky bit...
  protected function _handleFind($params, $finder) {
    $finder = $this->_parseQuery($params, $finder);
    if($finder->count() > 0) {
      $this->respondSuccess($finder->fetch());
    } else {
      $this->respondSuccess(array(), "No Results");
      //throw new NotFoundException("No Results");
    }
  }
  
  protected function _parseQuery($params, $finder) {
    $col = array_shift($params);
    $comp = array_shift($params); // Should only allow comparator functions (eq, neq, etc.)
    $value = array_shift($params);
    $finder->where($col)->{$comp}( $this->_getValue($value) );
    
    if(count($params) > 0) {
      $joiner = array_shift($params); // Only really accept 'and' ATM
      $finder = $this->_parseQuery($params, $finder);
    }
    return $finder;
  }
  
  
  protected function _getValue($val) {
    if($val == '_null') {
      return null;
    } else if($val == '_empty') {
      return "";
    } // else if(is_numeric($val)) {
     //      return $val;
     //    } else {
     //      return "'".$val."' ";
     //    }

     return $val;
  }
  
  protected function jsonResponse($status, $obj, $errors=null) {
    if(is_array($obj)) {
      $obj = array_map(create_function('$o', 'return $o->to_array();'), $obj);
    } else if($obj instanceof CoreModel) {
      $obj = $obj->to_array();
    }
    if(!is_array($errors)) {
      $errVal = $errors;
      $errors = array();
      if($errVal != null)
      $errors[] = $errVal;
    }
    echo json_encode(array(
      'status'=>$status,
      'errors'=>$errors,
      'content'=>$obj,
    ));
  }
  
  protected function respondSuccess($obj, $msg=null) {
    $this->jsonResponse('success', $obj, $msg);
    
  }
  protected function respondError($msg, $obj=array()) {
    $this->jsonResponse('error', $obj, $msg);
  }
  
  // Is this even used?
  protected function createJson($obj) {
    return CoreModel::toJSON($obj);
  }
}
