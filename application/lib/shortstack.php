<?php
 
// ShortStack v0.9.5
// By M@ McCray
// http://github.com/darthapo/ShortStack


function __autoload($className) {
  $classPath = ShortStack::AutoLoadFinder($className);
  if(!file_exists($classPath)) {
    return eval("class {$className}{public function __construct(){throw new NotFoundException('Class not found!');}}");
  } else {
    require_once($classPath);
  }
}

class Redirect extends Exception { }
class FullRedirect extends Exception { }
class EmptyDbException extends Exception { }
class NotFoundException extends Exception { }

class ShortStack {
  public static function AutoLoadFinder($className) {
    global $shortstack_config;
    if(strpos($className, 'ontroller') > 0) {
      return self::ControllerPath( underscore($className) );
    } else if(strpos($className, 'elper') > 0) {
        return self::HelperPath( underscore($className) );
    } else { // Model
      return self::ModelPath( underscore($className) );
    }    
  }
  // Loads all models in the models/ folder and returns the model class names
  public static function LoadAllModels() {
    global $shortstack_config;
    $model_files = glob( self::ModelPath("*") );
    $classNames = array();
    foreach($model_files as $filename) {
      $className = str_replace($shortstack_config['models']['folder']."/", "", $filename);
      $className = str_replace(".php", "", $className);
      require_once($filename);
      $classNames[] = camelize($className);
    }
    return $classNames;    
  }
  // Create all the tables needs for the models and documentmodels...
  public static function InitializeDatabase() {
    $modelnames = ShortStack::LoadAllModels();
    $needDocInit = false;
    foreach($modelnames as $modelName) {
      $mdl = new $modelName;
      if($mdl instanceof Model) {
        $res = $mdl->_createTableForModel();
        $res->execute();
      
      } else if($mdl instanceof Document) {
        $res = $mdl->_defineDocumentFromModel();
        $needDocInit = true;
      }
    }
    if($needDocInit) Document::InitializeDatabase();
    return $modelnames;
  }
  // File paths...
  public static function ViewPath($path) {
    return self::GetPathFor('views', $path);
  }
  public static function ControllerPath($path) {
    return self::GetPathFor('controllers', $path);
  }
  public static function ModelPath($path) {
    return self::GetPathFor('models', $path);
  }
  public static function HelperPath($path) {
    return self::GetPathFor('helpers', $path);
  }
  public static function CachePath($path) {
    return self::GetPathFor('cacheing', $path);
  }
  protected static function GetPathFor($type, $path) {
    global $shortstack_config;
    return $shortstack_config[$type]['folder']."/".$path.".php";
  }
}


function url_for($controller) {
  return BASEURI . $controller;
}

function link_to($controller, $label, $className="") {
  return '<a href="'. url_for($controller) .'" class="'. $className .'">'. $label .'</a>';
}

function slugify($str) {
   // only take alphanumerical characters, but keep the spaces and dashes too...
   $slug = preg_replace("/[^a-zA-Z0-9 -]/", "", trim($str));
   $slug = preg_replace("/[\W]{2,}/", " ", $slug); // replace multiple spaces with a single space
   $slug = str_replace(" ", "-", $slug); // replace spaces by dashes
   $slug = strtolower($slug);  // make it lowercase
   return $slug;
}

function underscore($str) {  
  $str = str_replace("-", " ", $str);
  $str = preg_replace_callback('/[A-Z]/', "underscore_matcher", trim($str));
  $str = str_replace(" ", "", $str);
  $str = preg_replace("/^[_]?(.*)$/", "$1", $str);
  return $str;  
}
function underscore_matcher($match) { return "_" . strtolower($match[0]); }

function camelize($str) {		
  $str = str_replace("-", "", $str);
	$str = 'x '.strtolower(trim($str));
	$str = ucwords(preg_replace('/[\s_]+/', ' ', $str));
	return substr(str_replace(' ', '', $str), 1);
}

function use_helper($helper) {
  if(! strpos($helper, 'elper') > 0) $helper .= "_helper";
  require_once( ShortStack::HelperPath($helper) );
}

function getBaseUri() { // Used by the Dispatcher
	return str_replace("/".$_SERVER['QUERY_STRING'], "/", array_shift(explode("?", $_SERVER['REQUEST_URI'])));
}

function debug($obj) {
  echo "<pre>";
  print_r($obj);
  echo "</pre>";
}

function doc($doctype, $id=null) {// For use with documents
  if($id != null) {
    return Document::Get($doctype, $id);
  } else {
    return Document::Find($doctype);
  }
}

function mdl($objtype, $id=null) {// For use with documents
  if($id != null) {
    return Model::Get($objtype, $id);
  } else {
    return Model::Find($doctype);
  }
}

function get($modelName, $id=null) {
  $c = new ReflectionClass($modelName);
  if($c->isSubclassOf('Model')) {
    return mdl($modelName, $id);
  } else {
    // Do another subclass test??
    return doc($modelName, $id);
  }
}


class Dispatcher {
  static public $dispatched = false;
  static public $current = null;

  static public function Recognize($override_controller=false) {
    if(!defined('BASEURI')) {
      $base_uri = @ getBaseUri();
      define("BASEURI", $base_uri);
    }
    $uri = @  '/'.$_SERVER['QUERY_STRING']; //@ $_SERVER['PATH_INFO']; // was REQUEST_URI
    $path_segments = array_filter(explode('/', $uri), create_function('$var', 'return ($var != null && $var != "");'));
    $controller = array_shift($path_segments);
    // override is mainly only used for an 'install' controller... I'd imagine.
    if($override_controller != false) $controller = $override_controller;
    if($controller == '') {
      global $shortstack_config;
      $controller = @$shortstack_config['controllers']['index']; // From settings instead?
      if(!$controller) $controller = 'home_controller'; // ?
    } else { 
      $controller = $controller.'_controller';
    }
    return self::Dispatch($controller, $path_segments);
  }
  
  static public function Dispatch($controller_name, $route_data=array()) {
    if (!self::$dispatched) {
      $controller = self::getControllerClass($controller_name);
      self::$current = $controller;
      try {
        if(!Controller::IsAllowed($controller_name)) throw new NotFoundException();
        $ctrl = new $controller();
        $ctrl->execute($route_data);
        self::$dispatched = true;
      }
      catch( NotFoundException $e ) {
        global $shortstack_config;
        if(@ $notfound = $shortstack_config['controllers']['404_handler']) {
          $uri = @ '/'.$_SERVER['QUERY_STRING']; // $uri = @ $_SERVER['PATH_INFO']; // was REQUEST_URI
          $path_segments = array_filter(explode('/', $uri), create_function('$var', 'return ($var != null && $var != "");'));
          $hack_var = array_shift($path_segments); array_unshift($path_segments, $hack_var);
          $controller = self::getControllerClass( $notfound );
          self::Dispatch($controller, $path_segments);
        } else {
          echo "Not Found.";
        }
      }
      catch( Redirect $e ) {
        $route_data = explode('/', $e->getMessage());
        $controller = self::getControllerClass( array_shift($route_data) );
        self::Dispatch($controller, $route_data);
      }
      catch( FullRedirect $e ) {
        $url = $e->getMessage();
        header( 'Location: '.$url );
        exit(0);
      }
    }
  }
  
  static private function getControllerClass($controller_name) {
    $controller = underscore( $controller_name );
    if(! strpos($controller, 'ontroller') > 0) $controller .= "_controller";
    return camelize( $controller );
  }  
}
class Cache {
  
  public static function Exists($name) {
    if(!USECACHE || DEBUG) return false;
    else return file_exists( ShortStack::CachePath($name) );
  }

  public static function Get($name) {
    return file_get_contents( ShortStack::CachePath($name) );
  }

  public static function Save($name, $content) {
    return file_put_contents( ShortStack::CachePath($name), $content);
  }

  public static function Remove($name) {
    return @ unlink ( ShortStack::CachePath($name) );
  }

  public static function Clear() {
    $cache_files = glob( ShortStack::CachePath("*") );
    foreach($cache_files as $filename) {
      @unlink ( $filename );
    }
    return true;
  }

}
class Controller {
  protected $defaultLayout = "_layout";
  protected $cacheName = false;
  protected $cacheOutput = true;
  
  // Default execute method... Feel free to override.
  function execute($args=array()) {
    $this->cacheName = get_class($this)."-".join('_', $args);
    if(@ $this->secure) $this->ensureLoggedIn();
    $this->_preferCached();
    $this->dispatchAction($args);
  }
  
  function index($params=array()) {
    throw new NotFoundException("Action <code>index</code> not implemented.");
  }
  
  function render($view, $params=array(), $wrapInLayout=null) {
    $tmpl = new Template( ShortStack::ViewPath($view) );
    $content = $tmpl->fetch($params);
    $this->renderText($content, $params, $wrapInLayout);
  }
  
  function renderText($text, $params=array(), $wrapInLayout=null) {
    $layoutView = ($wrapInLayout == null) ? $this->defaultLayout : $wrapInLayout;
    $output = '';
    if($layoutView !== false) {
      $layout = new Template( ShortStack::ViewPath($layoutView) );
      $layout->contentForLayout = $text;
      $output = $layout->fetch($params);
    } else {
      $output = $text;
    }
    $this->_cacheContent($output);
    echo $output;
  }
  
  // TODO: ???Should this even be here???
  protected $sessionController = "session";
  protected $secured = false;
  
  // OVERRIDE THIS!!!!
  function authenticate($username, $password) {
    return false;
  }
  
  protected function _handleHttpAuth($realm="Authorization Required", $force=false) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || $force == true) {
      header('WWW-Authenticate: Basic realm="'.$realm.'"');
      header('HTTP/1.0 401 Unauthorized');
      echo '401 Unauthorized';
      exit;
    } else {
      $auth = array(
        'username'=>$_SERVER['PHP_AUTH_USER'],
        'password'=>$_SERVER['PHP_AUTH_PW'] // When hashed: md5($_SERVER['PHP_AUTH_PW']); ???
      );
      if(! $this->doLogin($auth) ) {
          $this->_handleHttpAuth($realm, true);
        }
      }
  }

  protected function _preferCached($name=null) {
    if($this->cacheOutput) {
      $cname = ($name == null) ? $this->cacheName : $name;
      if(Cache::Exists($cname)) {
        echo Cache::Get($cname);
        exit(0);
      }
    }
  }
  
  protected function _cacheContent($content, $name=null) {
    if($this->cacheOutput) {
      $cname = ($name == null) ? $this->cacheName : $name;
      Cache::Save($cname, $content);
    }
  }
  
  protected function ensureLoggedIn($useHTTP=false) {
    if (!$this->isLoggedIn()) {
      if($useHTTP) {
        $this->_handleHttpAuth();
      } else {
//        throw new Redirect($this->sessionController);
      }
    }
  }
  
  protected function isLoggedIn() {
    @ session_start();
    return isset($_SESSION['CURRENT_USER']);
  }
  
  protected function doLogin($src=array()) {
    if($this->authenticate($src['username'], $src['password'])) {
      @ session_start();
      $_SESSION['CURRENT_USER'] = $src['username'];
      return true;
    } else {
      @ session_start();
      session_destroy();
      return false;
    }
  }

  protected function doLogout() {
    @ session_start();
    session_destroy();
  }
  // end???
  
  protected function isGet() {
    return $_SERVER['REQUEST_METHOD'] == 'GET';
  }

  protected function isPost() {
    return ($_SERVER['REQUEST_METHOD'] == 'POST' && @ !$_POST['_method']);
  }

  protected function isPut() {
    return (@ $_SERVER['REQUEST_METHOD'] == 'PUT' || @ $_POST['_method'] == 'put');
  }

  protected function isDelete() {
    return (@ $_SERVER['REQUEST_METHOD'] == 'DELETE' || @ $_POST['_method'] == 'delete' );
  }

  protected function isHead() {
    return (@ $_SERVER['REQUEST_METHOD'] == 'HEAD' || @ $_POST['_method'] == 'head');
  }
  
  protected function dispatchAction($path_segments) {
    $action = @ $path_segments[0]; //array_shift($path_segments);
    if( method_exists($this, $action) ) {
      array_shift($path_segments);
      $this->$action($path_segments);
    } else {
      // Index is a bit of a catchall...
      $this->index($path_segments);
    }
  }
 
 // Static methods
 private static $blacklisted_controllers = array();
 
 public static function Blacklist() {
   foreach (func_get_args() as $controller) {
    self::$blacklisted_controllers[] = $controller;
   }
 }
 
 public static function IsAllowed($controller) {
   return !in_array($controller, self::$blacklisted_controllers);
 }
}
class DB {
  
  // TODO: Change function names to TitleCase
  
  static protected $pdo;
  
  static public function Connect($conn, $user="", $pass="", $options=array()) {
    self::$pdo = new PDO($conn, $user, $pass, $options);
  }
  
  static public function Query($sql_string) {
    return self::$pdo->query($sql_string);
  }
  
  static public function GetLastError() {
    return self::$pdo->errorInfo();
  }

  static public function FetchAll($sql_string) {
    $statement = self::Query($sql_string);
    return $statement->fetchAll(); // PDO::FETCH_GROUP
  }
  
  static public function EnsureNotEmpty() {
    $statement = self::Query('SELECT name FROM sqlite_master WHERE type = \'table\'');
    $result = $statement->fetchAll();
        
    if( sizeof($result) == 0 ){
      define("EMPTYDB", true);
      throw new EmptyDbException("Database has no tables.");
    } else {
      define("EMPTYDB", false);
    }
  }
}
class CoreFinder implements IteratorAggregate {
  
  protected $objtype;
  protected $matcher = false;
  protected $finder = array();
  protected $or_finder = array();
  protected $order = array();
  protected $limit = false;
  private $__cache = false;
  
  public function __construct($objtype) {
    $this->objtype = $objtype;
  }
  
  public function where($index) {
    $this->__cache = false;
    if(! $this->matcher) $this->matcher = new FinderMatch($this, $index);
    $this->matcher->_updateIdxAndCls($index, 'and');
    return $this->matcher;
  }
  
  public function andWhere($index) {
    $this->__cache = false;
    if(! $this->matcher) $this->matcher = new FinderMatch($this, $index);
    $this->matcher->_updateIdxAndCls($index, 'and');
    return $this->matcher;
  }

  public function orWhere($index) {
    $this->__cache = false;
    if(! $this-matcher) $this->matcher = new FinderMatch($this, $index);
    $this->matcher->_updateIdxAndCls($index, 'or');
    return $this->matcher;
  }
  
  public function order($field, $dir='ASC') {
    $this->__cache = false;
    $this->order[$field] = $dir;
    return $this;
  }
  
  public function limit($count) {
    $this->__cache = false;
    $this->limit = $count;
    return $this;
  }
  
  public function count($ignoreCache=false) {
    return count($this->fetch($ignoreCache));
  }
  
  public function get($ignoreCache=false) {   // Returns the first match
    $oldLimit = $this->limit;
    $this->limit = 1; // Waste not, want not.
    $docs = $this->_execQuery($ignoreCache);
    $this->limit = $oldLimit;
    return @$docs[0];
  }
  
  public function fetch($ignoreCache=false) { // Executes current query
    return $this->_execQuery($ignoreCache);
  }
  
  public function getIterator() { // For using the finder as an array in foreach() statements
    $docs = $this->_execQuery();
    return new ArrayIterator($docs);
  }
  
// Warning these modified the matched records!!

  public function destroy() {
    foreach ($this as $doc) {
      $doc->destroy();
    }
    $this->__cache = false;
  }

  public function update($values=array()) {
    foreach ($this as $doc) {
      $doc->update($values);
      $doc->save();
    }
    $this->__cache = false;
  }
  
  public function _addFilter($column, $comparision, $value, $clause) {
    $this->__cache = false;
    $finder_filter = array('col'=>$column, 'comp'=>$comparision, 'val'=>$value);
    if($clause == 'or') $this->or_finder[] = $finder_filter;
    else $this->finder[] = $finder_filter;
    return $this;
  }
  
  
  protected function _execQuery($ignoreCache=false) {
    if($ignoreCache == false && $this->__cache != false) return $this->__cache;
    $sql = $this->_buildSQL();
    $stmt = DB::Query($sql);
    $items = array();
    if($stmt) {
      $results = $stmt->fetchAll();
      $className = $this->objtype;
      foreach ($results as $rowdata) {
        $items[] = new $className($rowdata);
      }
      $this->__cache = $items;
    } // FIXME: ELSE THROW SQL ERROR??? Hmm...
    return $items;
  }
  
  protected function _buildSQL() {
    throw new Exception("_buildSQL() has not been implemented!");
  }
}

class FinderMatch {
  protected $finder;
  protected $index;
  protected $clause;
  
  public function __construct($finder) {
    $this->finder = $finder;
  }
  
  public function _updateIdxAndCls($idx, $clause) {
    $this->index = $idx;
    $this->clause = $clause;
    return $this;
  }
  
  public function eq($value) {
    return $this->finder->_addFilter($this->index, '=', $value, $this->clause);
  }
  public function neq($value) {
    return  $this->finder->_addFilter($this->index, '!=', $value, $this->clause);
  }
  public function gt($value) {
    return $this->finder->_addFilter($this->index, '>', $value, $this->clause);
  }
  public function lt($value) {
    return $this->finder->_addFilter($this->index, '<', $value, $this->clause);
  }
  public function gte($value) {
    return $this->finder->_addFilter($this->index, '>=', $value, $this->clause);
  }
  public function lte($value) {
    return $this->finder->_addFilter($this->index, '<=', $value, $this->clause);
  }
  public function like($value) {
    return $this->finder->_addFilter($this->index, 'like', $value, $this->clause);
  }
  public function in($value) {
    return $this->finder->_addFilter($this->index, 'in', $value, $this->clause);
  }
}

class CoreModel {

    public $modelName = null;
    
    protected $data = false;
    protected $isNew = false;
    protected $isDirty = false;
    protected $hasMany = array();
    protected $belongsTo = array();
    protected $changedFields = array();

    function __construct($dataRow=null) {
      $this->modelName = get_class($this);
      if($dataRow != null) {
        $this->data = $dataRow;
        $this->isNew = false;
      } else {
        $this->data = array();
        $this->isNew = true;
      }
      $this->isDirty = false;
    }

    function __get($key) {
      if($this->data) {
        return html_entity_decode($this->data[$key], ENT_QUOTES );
      }
    }

    function __set($key, $value) {
      // TODO: Validate against schema to ensure fields exist?
      $value = stripslashes($value);
      if(@ $this->data[$key] != htmlentities($value, ENT_QUOTES)) {
        $this->data[$key] = $value;
        $this->changedFields[] = $key;
        $this->isDirty = true;
      }
    }
    
    public function has($key) {
      return array_key_exists($key, $this->data);
    }
    
    function __call( $method, $args ) {
      // TODO: Support relationships here...
      if(array_key_exists($method, $this->hasMany)) {
        return $this->_handleHasMany($method, $args);
      }
      else if(array_key_exists($method, $this->belongsTo)) {
        return $this->_handleBelongsTo($method, $args);
      }
      // look for 'add'+hasManyName
      // look for 'set'+belongsToName
      else {
        return NULL; // FIXME: What to do here?
      }
    }
    
    protected function _handleHasMany($method, $args) {
      $def = $this->hasMany[$method];
      if(array_key_exists('document', $def)) {
        $mdlClass = $def['document'];
        $fk = strtolower($this->modelName)."_id";
        return Document::Find($mdlClass)->where($fk)->eq($this->id);
      }
      else if(array_key_exists('model', $def)) {
        $mdlClass = $def['model'];
        $fk = strtolower($this->modelName)."_id";
        return Model::Find($mdlClass, $fk." = ".$this->id);
      } else {
        throw new Exception("A relationship has to be defined as a model or document");
      }
    }

    protected function _handleBelongsTo($method, $args) {
      $def = $this->belongsTo[$method];
      if(array_key_exists('document', $def)) {
        $mdlClass = $def['document'];
        $fk = strtolower($mdlClass)."_id";
        return Document::Find($mdlClass)->where('id')->eq($this->$fk).get();
      }
      else if(array_key_exists('model', $def)) {
        $mdlClass = $def['model'];
        $fk = strtolower($mdlClass)."_id";
        return Model::Get($mdlClass, $this->$fk);
      } else {
        throw new Exception("A relationship has to be defined as a model or document");
      }
    }
    
    public function updateValues($values=array()) {
      foreach($values as $key=>$value) {
        $this->$key = $value;
      }
      return $this;
    }
    
    public function update($values=array()) {
      return $this->updateValues($values);
    }
    
    public function hasChanged($key) {
      return in_array($key, $this->changedFields);
    }

    // Override these two
    public function save() {}
    public function destroy(){}
    
    // Callbacks
    protected function beforeSave() {}
    protected function afterSave() {}
    protected function beforeCreate() {}
    protected function afterCreate() {}
    protected function beforeDestroy() {}
    protected function afterDestroy() {}
    protected function beforeSerialize() {}
    protected function afterSerialize() {}

    protected function getChangedValues() {
      $results = array();
      foreach($this->changedFields as $key=>$fieldname) {
        $results[$fieldname] = $this->$fieldname;
      }
      return $results;
    }
    
    // ????
    public function assignTo($modelOrName, $id=null) {
      if(is_string($modelOrName)) {
        $fk = strtolower($modelOrName)."_id";
        $this->$fk = $id;
      } else {
        $fk = strtolower( get_class($modelOrName))."_id";
        $this->$fk = $modelOrName->id;
      }
      return $this;
    }
    
    public function to_array($exclude=array()) {
      $attrs = array();
      foreach($this->data as $col=>$value) {
        if(!in_array($col, $exclude)) {
          $attrs[$col] = $this->$col;
        }
      }
      return $attrs;
    }
    
    public function to_json($exclude=array()) {
      return CoreModel::toJSON( $this->to_array($exclude) );
    }
      
    // = Static methods / variables =    
    static public function toJSON($obj) {
      if(is_array($obj)) {
        $data = array();
        foreach($obj as $idx=>$mdl) {
          if($mdl instanceof CoreModel)
            $data[] = $mdl->to_array();
          else
            $data[$idx] = $mdl;
        }
        return json_encode($data);
        
      } else if( $obj instanceof CoreModel ) {
        return json_encode($obj->to_array());
      
      } else {
        return json_encode($obj);
      }
    }
}


class Model extends CoreModel {
  
  protected $schema = array();
  
  public function updateValues($values=array()) {
    $valid_atts = array_keys( $this->schema );
    foreach($values as $key=>$value) {
      if(in_array($key, $valid_atts)) $this->$key = $value;
    }
    return $this;
  }
  
  protected function getChangedValues() {
    $results = array();
    foreach($this->changedFields as $key=>$fieldname) {
      $results[$fieldname] = '"'.htmlentities($this->$fieldname, ENT_QUOTES).'"';
    }
    return $results;
  }
  
  public function save() {
    if($this->isDirty) {
      $this->beforeSave();
      if($this->isNew) {
        // Create
        $this->beforeCreate();
        $values = $this->getChangedValues();
        $sql = "INSERT INTO ".$this->modelName." (".join($this->changedFields, ', ').") VALUES (".join($values, ', ').");";
        $statement = DB::Query($sql);
        // Get the record's generated ID...
        $result = DB::Query('SELECT last_insert_rowid() as last_insert_rowid')->fetch();
        $this->data['id'] = $result['last_insert_rowid'];
        $this->afterCreate();
      } else {
        // Update
        $values = $this->getChangedValues();
        $fields = array();
        foreach($values as $field=>$value) {
          $fields[] = $field." = ".$value;
        }
        $sql = "UPDATE ".$this->modelName." SET ". join($fields, ", ") ." WHERE id = ". $this->id .";";
        $statement = DB::Query($sql);
      }
      $this->changedFields = array();
      $this->isDirty = false;
      $this->isNew = false;
      $this->afterSave();
    }
    return $this;
  }
  
  // Warning: Like Han solo, this method doesn't fuck around, it will shoot first.
  public function destroy() {
    $this->beforeDestroy();
    Model::Remove($this->modelName, $this->id);
    $this->afterDestroy();
    return $this;
  }
  
  // = SQL Builder Helper Methods =
   public function _createTableForModel() {
     return self::CreateTableForModel($this->modelName, $this);
   }
   static public function CreateTableForModel($modelName, $mdlInst=false) {
     $sql = "CREATE TABLE IF NOT EXISTS ";
     $sql.= $modelName ." ( ";
     $cols = array();
     if(! $mdlInst) $mdlInst = new $modelName;

     $modelColumns = $mdlInst->schema;
     $modelColumns["id"] = 'INTEGER PRIMARY KEY';
     
     foreach($modelColumns as $name=>$def) {
       $cols[] = $name ." ". $def;
     }
     $sql.= join($cols, ', ');
     $sql.= " );";
     
     return DB::Query( $sql );
   }

   public static function Get($modelName, $id) {
     $sql = "SELECT * FROM ".$modelName." WHERE id = ". $id ." LIMIT 1;";
     $results = DB::FetchAll($sql);
     $mdls = array();
     foreach ($results as $row) {
       $mdls[] = new $modelName($row);
     }
     return @$mdls[0];
   }
   
   public static function Find($modelName) {
     return new ModelFinder($modelName);
   }

   // Does NOT fire callbacks...
   public static function Remove($modelName, $id) {
     $sql = "DELETE FROM ".$modelName." WHERE id = ". $id .";";
     DB::Query($sql);
   }

   static public function Count($className) {
     $sql = "SELECT count(id) as count FROM ".$className.";";
     $statement = DB::Query($sql);
     if($statement) {
       $results = $statement->fetchAll(); // PDO::FETCH_ASSOC ???
       return @(integer)$results[0]['count'];
     } else { // Throw an ERROR?
       return 0;
     }
   }
}


class ModelFinder extends CoreFinder {

  // Model _buildSQL
  protected function _buildSQL() {
    $sql = "SELECT * FROM ". $this->objtype ." ";
    // TODO: Implment OR logic...
    if(count($this->finder) > 0) {
      $sql .= "WHERE ";
      $finders = array();
      foreach($this->finder as $qry) {
        $finders []= $qry['col']." ".$qry['comp'].' "'. htmlentities($qry['val'],ENT_QUOTES).'"';
      }
      $sql .= join(" AND ", $finders);
    }
    if(count($this->order) > 0) {
      $sql .= " ORDER BY ";
      $order_params = array();
      foreach ($this->order as $field => $dir) {
        $order_params[]= $field." ".$dir;
      }
      $sql .= join(", ", $order_params);
    }
    if($this->limit != false && $this->limit > 0) {
      $sql .= " LIMIT ". $this->limit ." ";
    }
    $sql .= " ;";
    return $sql;
  }

} 
class Document extends CoreModel {

  public $id = null;
  protected $rawData = null;
  protected $indexes = array();

  function __construct($dataRow=null) {
    parent::__construct($dataRow);
    if($dataRow != null) {
      $this->rawData = $dataRow['data'];
      $this->data = false;
      $this->id = $dataRow['id'];
    } else {
      $this->rawData = null;
      $this->data = array();
      $this->id = null;
    }
  }

  function __get($key) {
    if(!$this->data) { $this->_deserialize(); }
    return $this->data[$key];
  }

  function __set($key, $value) {
    if(!$this->data) { $this->_deserialize(); }
    $value = stripslashes($value);
    if(@ $this->data[$key] != $value) {
      $this->data[$key] = $value;
      $this->changedFields[] = $key;
      $this->isDirty = true;
    }
  }
  
  public function has($key) {
    if(!$this->data) $this->_deserialize();
    return array_key_exists($key, $this->data);
  }

  public function save() {
    if($this->isDirty) {
      $this->beforeSave();
      if($this->isNew) { // Insert
        $this->beforeCreate();
        $this->_serialize();
        $sql = 'INSERT INTO '.$this->modelName.' ( data ) VALUES ( "'. $this->rawData .'" );';
        $statement = DB::Query($sql);
        $result = DB::Query('SELECT last_insert_rowid() as last_insert_rowid')->fetch(); // Get the record's generated ID...
        $this->id = $result['last_insert_rowid'];
        Document::Reindex($this->modelName, $this->id);
        $this->afterCreate();
      } else { // Update
        $this->_serialize();
        $sql = "UPDATE ".$this->modelName.' SET data="'.htmlentities( json_encode($this->data), ENT_QUOTES).'" WHERE id = '. $this->id .';';
        $statement = DB::Query($sql);
        $index_changed = array_intersect($this->changedFields, array_keys(Document::GetIndexesFor($this->modelName)));
        if(count($index_changed) > 0)  // Only if an indexed field has changed
          Document::Reindex($this->modelName, $this->id);
      }
      $this->changedFields = array();
      $this->isDirty = false;
      $this->isNew = false;
      $this->afterSave();
    }
    return $this;
  }

  // Warning: Like Han solo, this method doesn't fuck around, it will shoot first.
  public function destroy() {
    $this->beforeDestroy();
    Document::Remove($this->modelName, $this->id);
    $this->afterDestroy();
    return $this;
  }
  
  public function _defineDocumentFromModel() {
    Document::Define( $this->modelName, $this->indexes, false);
  }
  
  protected function beforeDeserialize() {}
  protected function afterDeserialize() {}
  
  // If you'd rather store the data as something else (XML, say) you can override these methods
  protected function deserialize($source) { // Must return an associative array
    return json_decode($source, true);
  }
  protected function serialize($source) { // Must return a string
    return json_encode($source);
  }

  // Used internally only... Triggers callbacks.
  private function _serialize() {
    $this->beforeSerialize();
    $this->rawData = htmlentities( $this->serialize( $this->data ), ENT_QUOTES );
    $this->afterSerialize(); // ??: Should the results be passed in to allow massaging?
    return $this;
  }
  private function _deserialize() {
    $this->beforeDeserialize();
    $this->data = $this->deserialize( html_entity_decode($this->rawData, ENT_QUOTES) );
    $this->afterDeserialize(); // ??: Should the results be passed in to allow massaging?
    return $this;
  }
  
  public function to_array($exclude=array()) {
    $attrs = array( 'id'=>$this->id );
    foreach($this->data as $col=>$value) {
      if(!in_array($col, $exclude)) {
        $attrs[$col] = $this->$col;
      }
    }
    return $attrs;
  }
  
  // Static Methods
  
  private static $_document_indexes_ = array();
  
  public static function Register($class) {
    $doc = new $class();
    $doc->_defineDocumentFromModel();
  }
  
  public static function Define($name, $indexes=array(), $createClass=true) {
    Document::$_document_indexes_[$name] = array_merge($indexes, array()); // a hokey way to clone an array
    if($createClass)
      eval("class ". $name ." extends Document {  }");
  }
  
  public static function InitializeDatabase() {
    // Loop through all the docs and create tables and index tables...
    foreach( Document::$_document_indexes_ as $docType => $indexes) {
      $tableSQL = "CREATE TABLE IF NOT EXISTS ". $docType ." ( id INTEGER PRIMARY KEY, data TEXT, created_on TIMESTAMP, updated_on TIMESTAMP );";
      DB::Query( $tableSQL );
      foreach ($indexes as $field=>$fType) {
        $indexSQL = "CREATE TABLE IF NOT EXISTS ". $docType ."_". $field ."_idx ( id INTEGER PRIMARY KEY, docid INTEGER, ". $field ." ". $fType ." );";
        DB::Query( $indexSQL );
      }
    }
  }
  
  public static function Reindex($doctype=null, $id=null) {
    // Loop through all the records, deserialize the data column and recreate the index rows
    $docs = array();
    // Step one: Fetch all the documents to update...
    if($doctype != null) {
      if(!array_key_exists($doctype, Document::$_document_indexes_)) {
        $tmp = new $doctype();
        $tmp->_defineDocumentFromModel();
      }
      if($id != null) {
        $sql = "SELECT * FROM ".$doctype." WHERE id = ". $id .";";
      } else {
        $sql = "SELECT * FROM ".$doctype.";";
      }
      $results = DB::FetchAll($sql);
      foreach ($results as $row) {
        $docs[] = new $doctype($row);
      }
    } else {
      // Do 'em all!
      foreach( Document::$_document_indexes_ as $docType => $indexes) {
        $sql = "SELECT * FROM ".$doctype."";
        $results = DB::FetchAll($sql);
        foreach ($results as $row) {
          $docs[] = new $doctype($row);
        }
      }
    }
    // Step two: Loop through them and delete, then rebuild index rows
    foreach ($docs as $doc) {
      $indexes = Document::GetIndexesFor($doc->modelName);
      if(count($indexes) > 0) {
        foreach ($indexes as $field=>$fType) {
          // TODO: Optimize as single transactions?
          $indexTable = $doc->modelName ."_". $field ."_idx";
          $sql = "DELETE FROM ". $indexTable ." WHERE docid = ". $doc->id .";";
          $results = DB::Query($sql);
          $sql = "INSERT INTO ". $indexTable ." ( docid, ". $field ." ) VALUES (".$doc->id.', "'.htmlentities($doc->{$field}, ENT_QUOTES).'" );';
          $results = DB::Query($sql);
        }
      } else {
        echo "! No indexes for ". $doc->modelName ."\n";
      }
    }
  }
  
  public static function Get($doctype, $id) {
    $sql = "SELECT * FROM ".$doctype." WHERE id = ". $id ." LIMIT 1;";
    $results = DB::FetchAll($sql);
    $docs = array();
    foreach ($results as $row) {
      $docs[] = new $doctype($row);
    }
    return @$docs[0];
  }
 
  public static function Find($doctype) {
    return new DocumentFinder($doctype);
  }
  
  // Doesn't fire callbacks!
  public static function Remove($doctype, $id) {
    $sql = "DELETE FROM ".$doctype." WHERE id = ". $id .";";
    DB::Query($sql);
    foreach ( Document::GetIndexesFor($doctype) as $field=>$fType) {
      $sql = "DELETE FROM ".$doctype."_".$field."_idx WHERE docid = ". $id .";";
      DB::Query($sql);
    }
  }
  
  public static function Count($doctype) {
    $sql = "SELECT count(id) as count FROM ".$doctype.";";
    $statement = DB::Query($sql);
    if($statement) {
      $results = $statement->fetchAll(); // PDO::FETCH_ASSOC ???
      return @(integer)$results[0]['count'];
    } else { // Throw an ERROR?
      return 0;
    }
  }

  public static function GetIndexesFor($doctype) {
    return Document::$_document_indexes_[$doctype];
  }
  
}


class DocumentFinder extends CoreFinder {

  // Document _buildSQL
  protected function _buildSQL() {
    // TODO: Implment OR logic...

    $all_order_cols = array();
    foreach($this->order as $field=>$other) {
      $all_order_cols[] = $this->_getIdxCol($field, false);
    }
    $all_finder_cols = array();
    foreach($this->finder as $qry) {
      $all_finder_cols []= $this->_getIdxCol($qry['col'], false);
    }
    // Also for OR?

    $tables = array_merge(array($this->objtype), $all_order_cols);
    $sql = "SELECT ". $this->objtype .".* FROM ". join(', ', $tables) ." ";

    if(count($this->finder) > 0) {
      $sql .= "WHERE ". $this->objtype .".id IN (";
      $sql .= "SELECT ". $all_finder_cols[0] .".docid FROM ". join(', ', $all_finder_cols). " ";
      $sql .= "WHERE ";
      $finders = array();
      foreach($this->finder as $qry) {
        $finders []= " ". $this->_getIdxCol($qry['col'])  ." ". $qry['comp'] .' "'. htmlentities($qry['val'], ENT_QUOTES) .'" ';
      }
      $sql .= join(' AND ', $finders);
      $sql .= ") ";
    }
    if(count($this->order) > 0) {
      $sql .= "AND ";
      $sortJoins = array();
      foreach ($this->order as $field => $dir) {
        $sortJoins[] = $this->_getIdxCol($field, false) .".docid = ". $this->objtype .".id ";
      }
      $sql .= join(" AND ", $sortJoins);
      $sql .= " ORDER BY ";
      $order_params = array();
      foreach ($this->order as $field => $dir) {
        $order_params[]= $this->_getIdxCol($field) ." ". $dir;
      }
      $sql .= join(", ", $order_params);
    }
    if($this->limit != false && $this->limit > 0) {
      $sql .= " LIMIT ". $this->limit ." ";
    }
    $sql .= ";";
//    print_r($sql);
    return $sql;
  }
    
  protected function _getIdxCol($column, $appendCol=true) {
    $col = $this->objtype ."_". $column ."_idx";
    if($appendCol) {
      $col .= ".". $column;
    }
    return $col; 
  }  
}

class Template {
  
  private $path;
  private $context;
  private $silent;
  
  function __construct($templatePath, $vars=array(), $swallowErrors=true) {
    $this->path = $templatePath;
    $this->context = $vars;
    $this->silent = $swallowErrors;
  }
  
  function __set($key, $value) {
    $this->context[$key] = $value;
  }
  
  function __get($key) {
    if(array_key_exists($key, $this->context)) {
      return $this->context[$key];
    } else {
      if($this->silent)
        return "";
      else
        throw new Exception("$key does not exist in template: ".$this->templatePath);
    }
  }
  
  function __call($name, $args) {
    if($this->silent)
      return "";
    else
      throw new Exception("Method $name doesn't exist!!");
  }
  
  function assign($key, $value) {
    $this->context[$key] = $value;
  }
  
  function display($params=array()) {
    echo $this->fetch($params);
  }
  
  function fetch($params=array()) {
//    set_include_path($templathPath); // May be needed... Sometimes
    extract(array_merge($params, $this->context)); // Make variables local!
    ob_start();
    if (FORCESHORTTAGS) { // If the PHP installation does not support short tags we'll do a little string replacement, changing the short tags to standard PHP echo statements.
      echo eval('?>'.preg_replace("/;*\s*\?>/", "; ?>", str_replace('<?=', '<?php echo ', file_get_contents($this->path))));
    } else {
      include($this->path); // include() vs include_once() allows for multiple views with the same name
    }
    $buffer = ob_get_contents();
    @ob_end_clean();
    return $buffer;
  }
  
}
if(!isset($shortstack_config)) {
  $shortstack_config = array(
    'db' => array(
      'engine'   => 'sqlite', // Only one supported as yet
      'database' => 'database.sqlite3',
      'autoconnect' => true,
      'verify' => true,
    ),
    'models' => array(
      'folder' => 'models',
    ),
    'views' => array(
      'folder' => 'views',
      'force_short_tags'=>false,
    ),
    'controllers' => array(
      'folder' => 'controllers',
      'index' => 'home',
      '404_handler'=>'home',
    ),
    'helpers' => array(
      'folder' => 'helpers',
      'autoload'=> array(),
    ),
    'cacheing' => array(
      'folder' => 'caches',
      'enabled' => true,
    ),
  );
}

if( isset($shortstack_config) ) {
  define('FORCESHORTTAGS', @$shortstack_config['views']['force_short_tags']);
  define('USECACHE', @$shortstack_config['cacheing']['enabled']);
  if(@ is_array($shortstack_config['helpers']['autoload']) ) {
    foreach($shortstack_config['helpers']['autoload'] as $helper) {
      require_once( ShortStack::HelperPath($helper."_helper"));
    }
  }
  if(@ $shortstack_config['db']['autoconnect'] ) {
    DB::Connect( $shortstack_config['db']['engine'].":".$shortstack_config['db']['database'] ); 
  }
  if(@ $shortstack_config['db']['verify'] ) {
    DB::EnsureNotEmpty();
  }
} else {
  throw new NotFoundException("ShortStack configuration missing!");
}