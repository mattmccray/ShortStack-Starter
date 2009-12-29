<?php
 
// ShortStack v0.9.6
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
class DbException extends Exception { }
class StaleCache extends Exception { }

class ShortStack {
  public static function AutoLoadFinder($className) {
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
    $model_files = glob( self::ModelPath("*") );
    $classNames = array();
    foreach($model_files as $filename) {
      $path = explode('/', $filename);
      $className = array_slice($path, -1);
      $className = str_replace(".php", "", $className);
      require_once($filename);
      $classNames[] = camelize($className);
    }
    return $classNames;    
  }
  // Create all the tables needs for the models and documentmodels...
  public static function InitializeDatabase() {
    $modelnames = ShortStack::LoadAllModels();
    foreach($modelnames as $modelName) {
      $mdl = new $modelName;
      if($mdl instanceof Model) {
        $mdl->createTableForModel();
      }
    }
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
    return self::GetPathFor('cacheing', $path, '.html');
  }
  protected static function GetPathFor($type, $path, $suffix=".php") {
    global $shortstack_config;
    return $shortstack_config[$type]['folder']."/".$path.$suffix;
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
  echo "</pre>\n";
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
    return Model::Find($objtype);
  }
}

function get($modelName, $id=null) {
  if($modelName::$IsDocument) {
    return doc($modelName, $id);
  }
  else {
    return mdl($modelName, $id);
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
    $cacheContent = file_get_contents( ShortStack::CachePath($name) );
    $splitter = strpos($cacheContent, "\n"); 
    $contents = substr($cacheContent, $splitter+1, strlen($cacheContent) - $splitter);
    $timeSinseCached = time() - intVal(substr($cacheContent, 0, $splitter));;
    if($timeSinseCached > CACHELENGTH) {
      Cache::Expire($name);
      throw new StaleCache('Cache expired.');
    } else {
      return $contents;
    }
  }

  public static function Save($name, $content) {
    $cacheContent = time() ."\n". $content;
    return file_put_contents( ShortStack::CachePath($name), $cacheContent);
  }

  public static function Expire($name) {
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
        try {
          echo Cache::Get($cname);
          exit(0);
        } catch(StaleCache $e) {  }  // Do nothing!
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
        throw new Redirect($this->sessionController);
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
  
  protected function isGet() { return $_SERVER['REQUEST_METHOD'] == 'GET'; }
  protected function isPost() { return ($_SERVER['REQUEST_METHOD'] == 'POST' && @ !$_POST['_method']); }
  protected function isPut() { return (@ $_SERVER['REQUEST_METHOD'] == 'PUT' || @ $_POST['_method'] == 'put'); }
  protected function isDelete() { return (@ $_SERVER['REQUEST_METHOD'] == 'DELETE' || @ $_POST['_method'] == 'delete' ); }
  protected function isHead() { return (@ $_SERVER['REQUEST_METHOD'] == 'HEAD' || @ $_POST['_method'] == 'head'); }
  
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
  static protected $pdo;
  
  static public function Connect($conn, $user="", $pass="", $options=array()) {
    self::$pdo = new PDO($conn, $user, $pass, $options);
    return self::$pdo;
  }
  
  static public function Query($sql_string) {
    return self::$pdo->query($sql_string);
  }
  
  static public function GetLastError() {
    return self::$pdo->errorInfo();
  }

  static public function FetchAll($sql_string) {
    $statement = self::Query($sql_string);
    if($statement != false) {
      return $statement->fetchAll(); // PDO::FETCH_GROUP
    } else {
      $err = self::GetLastError();
      throw new DbException("Error:\n\t".$err[2]."\nWas thrown by SQL:\n\t".$sql_string);
    }
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
class ModelFinder implements IteratorAggregate {
  protected $objtype;
  protected $matcher = false;
  protected $finder = array();
  protected $or_finder = array();
  protected $order = array();
  protected $limit = false;
  protected $offset = false;
  private $__cache = false;
  
  public function __construct($objtype) {
    $this->objtype = $objtype;
    $this->matcher = new FinderMatch($this);
  }
  
  public function where($index) {
    $this->__cache = false;
    $this->matcher->_updateIdxAndCls($index, 'and');
    return $this->matcher;
  }
  
  public function andWhere($index) {
    $this->__cache = false;
    $this->matcher->_updateIdxAndCls($index, 'and');
    return $this->matcher;
  }

  public function orWhere($index) {
    $this->__cache = false;
    $this->matcher->_updateIdxAndCls($index, 'or');
    return $this->matcher;
  }
  
  public function order($field, $dir='ASC') {
    // TODO: Change this to replace the order array, and possibly use func_get_args()
    $this->__cache = false;
    $this->order[$field] = $dir;
    return $this;
  }
  
  public function limit($count) {
    $this->__cache = false;
    $this->limit = $count;
    return $this;
  }

  public function offset($count) {
    $this->__cache = false;
    $this->offset = $count;
    return $this;
  }
  
  public function count() {
    $sql = $this->_buildSQL(true);
    $res = DB::FetchAll($sql);
    return intVal( $res[0]['count'] );  
  }
  
  public function get($ignoreCache=false) {   // Returns the first match
    $oldLimit = $this->limit;
    $this->limit = 1; // Waste not, want not.
    $docs = $this->_execQuery($ignoreCache);
    $this->limit = $oldLimit;
    if(count($docs) == 0)
      return null;
    else
      return @$docs[0];
  }
  
  public function fetch($ignoreCache=false) { // Executes current query
    return $this->_execQuery($ignoreCache);
  }
    
  public function raw($ignoreCache=true) { // Returns the raw resultset...
    $sql = $this->_buildSQL();
    $stmt = DB::Query($sql);
    return $stmt->fetchAll();
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
    } // FIXME: ELSE THROW SQL ERROR??? Hmm..
//    else { echo "STATEMENT ERROR on $sql\nError: ". DB::GetLastError() ."\n"; }
    return $items;
  }
  
  protected function _buildSQL($isCount=false) {
    if($isCount)
      $sql = "SELECT count(id) as count FROM ". $this->objtype ." ";
    else
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
    if($isCount) return $sql.";";

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
    if($this->offset != false && $this->offset > 0) {
      $sql .= " OFFSET ". $this->offset ." ";
    }
    $sql .= " ;";
    return $sql;
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

class Model {
    public $modelName = null;
    protected $data = false;
    protected $isNew = false;
    protected $isDirty = false;
    protected $schema = array();
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
      $value = stripslashes($value);
      if(@ $this->data[$key] != htmlentities($value, ENT_QUOTES)) {
        $this->data[$key] = $value;
        $this->changedFields[] = $key;
        $this->isDirty = true;
      }
    }
    
    function __call( $method, $args ) {
      if(array_key_exists($method, $this->hasMany)) {
        return $this->_handleHasMany($method, $args);
      }
      else if(array_key_exists($method, $this->belongsTo)) {
        return $this->_handleBelongsTo($method, $args);
      }
      else if(preg_match('/^(new|add|set)(.*)/', $method, $matches)) {
        list($full, $mode, $modelName) = $matches;
        return $this->_handleRelationshipBuilder($mode, $modelName, $args);
      }
      else {
        return NULL; // FIXME: What to do here? Throw exception when __call is bad?
      }
    }
    
    public function has($key) {
      return array_key_exists($key, $this->data);
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
    
    public function getChangedValues() {
      $valid_atts = array_keys( $this->schema );
      $cleanChangedFields = array();
      $results = array();
      foreach($this->changedFields as $fieldname) {
        if(in_array($fieldname, $valid_atts)) { // Stringifies everything, is this really ideal?
          $results[$fieldname] = '"'.htmlentities($this->$fieldname, ENT_QUOTES).'"';
          $cleanChangedFields[] = $fieldname;
        }
      }
      $this->changedFields = $cleanChangedFields;
      return $results;
    }

    public function save() {
      $result = true;
      if($this->isDirty) {
        $this->beforeSave();
        if($this->isNew) { // Create
          $this->beforeCreate();
          $result = $this->_handleSqlInsert();
          $this->afterCreate();
        }
        else { // Update
          $result = $this->_handleSqlUpdate();
        }
        $this->changedFields = array();
        $this->isDirty = false;
        $this->isNew = false;
        $this->afterSave();
      }
      return $result;
    }
    
    public function destroy(){
      $this->beforeDestroy();
      $thisDel = $this->_handleSqlDelete();
      $relDel = $this->_handleRelatedSqlDelete();
      $this->afterDestroy();
      return ($thisDel && $relDel);
    }

    public function kill(){ // WARNING: This doesn't trigger the callbacks!
      $thisDel = $this->_handleSqlDelete();
      $relDel = $this->_handleRelatedSqlDelete();
      return ($thisDel && $relDel);
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
      return Model::toJSON( $this->to_array($exclude) );
    }
    
    public function createTableForModel() {
      return $this->_handleSqlCreate();
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
      }
      else if(array_key_exists('through', $def)) {
        $thruCls = $def['through'];
        $thru = new $thruCls(null, $this);
        return $thru->getRelated($method);
      }
      else {
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
        return Model::Get($mdlClass, $this->{$fk});
      } else {
        throw new Exception("A relationship has to be defined as a model or document");
      }
    }
    
    protected function _handleRelationshipBuilder($mode, $modelName, $args) {
      //TODO: Type and schema checking...
      $fk = strtolower($this->modelName)."_id";
      if($mode == 'new') {
        $mdl = new $modelName();
        $mdl->{$fk} = $this->id;
        if(count($args) > 0 && is_array($args[0])) {
          $mdl->update($args[0]);
        }
        return $mdl;
      }
      else if($mode == 'add') {
        list($mdl) = $args;
        $mdl->{$fk} = $this->id;
        $mdl->save();
      }
      else if($mode == 'set') {
        list($mdl) = $args;
        $this->{$fk} = $mdl->id;
      }
      else {
        throw new Exception("Unknown relationship mode: ".$mode);
      }
      return $this;
    }
    
    protected function _handleSqlCreate() {
      $sql = "CREATE TABLE IF NOT EXISTS ";
      $sql.= $this->modelName ." ( ";
      $cols = array();
      $modelColumns = $this->schema;
      if(!array_key_exists('id',$this->schema))
        $modelColumns["id"] = 'INTEGER PRIMARY KEY';
      foreach($modelColumns as $name=>$def) {
        $cols[] = $name ." ". $def;
      }
      $sql.= join($cols, ', ');
      $sql.= " );";
      $statement = DB::Query( $sql );
      // Trigger to auto-insert created_on
      if(array_key_exists('created_on', $this->schema)) {
        $triggerSQL = "CREATE TRIGGER generate_". $this->modelName ."_created_on AFTER INSERT ON ". $this->modelName ." BEGIN UPDATE ". $this->modelName ." SET created_on = DATETIME('NOW') WHERE rowid = new.rowid; END;";
        if(DB::Query( $triggerSQL ) == false) $statement = false;
      }
      // Trigger to auto-insert updated_on
      if(array_key_exists('updated_on', $this->schema)) {
        $triggerSQL = "CREATE TRIGGER generate_". $this->modelName ."_updated_on AFTER UPDATE ON ". $this->modelName ." BEGIN UPDATE ". $this->modelName ." SET updated_on = DATETIME('NOW') WHERE rowid = new.rowid; END;";
        if(DB::Query( $triggerSQL ) == false) $statement = false;
      }
      return ($statement != false);
    }
    
    protected function _handleSqlInsert() {
      $values = $this->getChangedValues();
      $sql = "INSERT INTO ".$this->modelName." (".join($this->changedFields, ', ').") VALUES (".join($values, ', ').");";
      $statement = DB::Query($sql);
      if($statement == false) return false;
      // Get the record's generated ID...
      $result = DB::Query('SELECT last_insert_rowid() as last_insert_rowid')->fetch();
      $this->data['id'] = $result['last_insert_rowid'];
      return true;
    }
    
    protected function _handleSqlUpdate() {
      $values = $this->getChangedValues();
      $fields = array();
      foreach($values as $field=>$value) {
        $fields[] = $field." = ".$value;
      }
      $sql = "UPDATE ".$this->modelName." SET ". join($fields, ", ") ." WHERE id = ". $this->id .";";
      $statement = DB::Query($sql);
      return ($statement != false);
    }
    
    protected function _handleSqlDelete() {
      $sql = "DELETE FROM ".$this->modelName." WHERE id = ". $this->id .";";
      $stmt = DB::Query($sql);
      return ($stmt != false);
    }
    
    protected function _handleRelatedSqlDelete() {
      $fk = strtolower($this->modelName)."_id";
      foreach ($this->hasMany as $methodName => $relDef) {
        $rule = (array_key_exists('cascade', $relDef)) ? $relDef['cascade'] : 'delete';
        if(array_key_exists('through', $relDef)) {
          $joinerCls = $relDef['through'];
          get($joinerCls)->where($fk)->eq($this->id)->destroy();
        }
        else {
          $mdlCls = (array_key_exists('document', $relDef)) ? $relDef['document'] : $relDef['model'];
          $matches = get($mdlCls)->where($fk)->eq($this->id);
          if($rule == 'delete')
            $matches->destroy();
          else// if($rule == 'nullify') // Could also use 'cascade'=>'ignore'???
            $matches->update(array($fk=>null)); // nullifies
        }
      }
      return true;
    }
    
    // Callbacks
    protected function beforeSave() {}
    protected function afterSave() {}
    protected function beforeCreate() {}
    protected function afterCreate() {}
    protected function beforeDestroy() {}
    protected function afterDestroy() {}

    public static $IsModel = true;
    public static $IsDocument = false;

    public static function Get($modelName, $id) {
      $sql = "SELECT * FROM ".$modelName." WHERE id = ". $id ." LIMIT 1;";
      $results = DB::FetchAll($sql);
      return new $modelName( $results[0] );
    }

    public static function Find($modelName) {
      return new ModelFinder($modelName);
    }

    // Does NOT fire callbacks...
    public static function Remove($modelName, $id) {
      $inst = self::Get($modelName, $id);
      return $inst->kill();
    }

    static public function Count($className) {
      $sql = "SELECT count(id) as count FROM ".$className.";";
      $results = DB::FetchAll($sql);
      return @(integer)$results[0]['count'];
    }

    static public function toJSON($obj) {
      if(is_array($obj)) {
        $data = array();
        foreach($obj as $idx=>$mdl) {
          if($mdl instanceof Model)
            $data[] = $mdl->to_array();
          else
            $data[$idx] = $mdl;
        }
        return json_encode($data);
        
      } else if( $obj instanceof Model ) {
        return json_encode($obj->to_array());
      
      } else {
        return json_encode($obj);
      }
    }
}

class Joiner extends Model {
  protected $joins = array(); // OVERRIDE ME!
  private $srcModel;
  private $toModel;
  
  public function __construct($dataRow=null, $from=null) {
    parent::__construct($dataRow);
    $this->srcModel = $from;
    if(count($this->joins) == 2) {
      list($left, $right) = $this->joins;
      $left = strtolower($left)."_id";
      $right = strtolower($right)."_id";
      if(! array_key_exists($left, $this->schema)) $this->schema[$left] = "INTEGER";
      if(! array_key_exists($right, $this->schema)) $this->schema[$right] = "INTEGER";
    }
  }
  
  public function getRelated($to) {
    list($a, $b) = $this->joins;
    $srcMdlCls = $this->srcModel->modelName;
    $toMdlCls = ($a == $srcMdlCls) ? $b : $a;
    $srcId = strtolower($srcMdlCls)."_id";
    $toId = strtolower($toMdlCls)."_id";
    $id = $this->srcModel->id;
    $sql = "SELECT * FROM $toMdlCls WHERE id IN (SELECT $toId FROM $this->modelName WHERE $srcId = $id);";
    //echo $sql;
    $stmt = DB::Query($sql);
    $mdls = array();
    if($stmt) {
      $results = $stmt->fetchAll();
      foreach ($results as $row) {
        $mdls[] = new $toMdlCls($row);
      }
    } // else {
    //       debug("No Statement Object for: $sql\nError: ");
    //       debug(DB::GetLastError());
    //     }
    return $mdls;
  }
    
}
 
class Document extends Model {
  public $id = null;
  public $created_on = null;
  public $updated_on = null;
  protected $rawData = null;
  protected $indexes = array();
  
  // It's best if you don't override this...
  protected $schema = array(
    'id'          => 'INTEGER PRIMARY KEY',
    'data'        => 'TEXT',
    'created_on'  => 'DATETIME',
    'updated_on'  => 'DATETIME'
  );

  function __construct($dataRow=null) {
    parent::__construct($dataRow);
    if($dataRow != null) {
      $this->rawData = $dataRow['data'];
      $this->data = false;
      $this->id = $dataRow['id'];
      $this->created_on = $dataRow['created_on'];
      $this->updated_on = $dataRow['updated_on'];
    } else {
      $this->rawData = null;
      $this->data = array();
      $this->id = null;
    }
    // Force the $schema ??? To prevent overrides?
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
  
  public function getChangedValues() {
    $results = array();
    foreach($this->changedFields as $key=>$fieldname) {
      $results[$fieldname] = $this->$fieldname;
    }
    return $results;
  }
  
  public function reindex() {
    $wasSuccessful = true;
    foreach ($this->indexes as $field=>$fType) { // TODO: Optimize as single transactions?
      $indexTable = $this->modelName ."_". $field ."_idx";
      $sql = "DELETE FROM ". $indexTable ." WHERE docid = ". $this->id .";";
      if(DB::Query($sql) == false) $wasSuccessful = false;
      $sql = "INSERT INTO ". $indexTable ." ( docid, ". $field ." ) VALUES (".$this->id.', "'.htmlentities($this->{$field}, ENT_QUOTES).'" );';
      if(DB::Query($sql) == false) $wasSuccessful = false;
    }
    return $wasSuccessful;
  }
  
  public function to_array($exclude=array()) {
    $attrs = array(
      'id'=>$this->id,
      'created_on'=>$this->created_on,
      'updated_on'=>$this->updated_on,
    );
    foreach($this->data as $col=>$value) {
      if(!in_array($col, $exclude)) {
        $attrs[$col] = $this->$col;
      }
    }
    return $attrs;
  }
  
  protected function _handleSqlCreate() {
    $mdlRes = parent::_handleSqlCreate();
    $idxRes = true;
    foreach ($this->indexes as $field=>$fType) { // Create index tables...
      $indexSQL = "CREATE TABLE IF NOT EXISTS ". $this->modelName ."_". $field ."_idx ( id INTEGER PRIMARY KEY, docid INTEGER, ". $field ." ". $fType ." );";
      if(DB::Query( $indexSQL ) == false) $idxRes = false;
    }
    return ($mdlRes != false && $idxRes != false);
  }
   
  protected function _handleSqlInsert() {
    $this->_serialize();
    $sql = 'INSERT INTO '.$this->modelName.' ( data ) VALUES ( "'. $this->rawData .'" );';
    $this->created_on = $this->updated_on = gmdate('Y-m-d H:i:s'); // Not official
    $statement = DB::Query($sql);
    if($statement == false) return false;
    $result = DB::Query('SELECT last_insert_rowid() as last_insert_rowid')->fetch(); // Get the record's generated ID...
    $this->id = $result['last_insert_rowid'];
    $this->reindex();
    return true;
  }
   
  protected function _handleSqlUpdate() {
    $this->_serialize();
    $sql = "UPDATE ".$this->modelName.' SET data="'. $this->rawData .'" WHERE id = '. $this->id .';';
    $statement = DB::Query($sql);
    if($statement == false) return false;
    $this->updated_on = gmdate('Y-m-d H:i:s'); // Not official
    $index_changed = array_intersect($this->changedFields, array_keys($this->indexes));
    if(count($index_changed) > 0) { // Only if an indexed field has changed
      $this->reindex();
    }
    return true;
  }
   
  protected function _handleRelatedSqlDelete() {
    $removedIndexes = true;
    foreach ( $this->indexes as $field=>$fType) {
      $sql = "DELETE FROM ".$this->modelName."_".$field."_idx WHERE docid = ". $this->id .";";
      if(DB::Query($sql) == false) $removedIndexes = false;
    }
    return (parent::_handleRelatedSqlDelete() && $removedIndexes);
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

  private function _serialize() { // Used internally only... Triggers callbacks.
    $this->beforeSerialize();
    $this->rawData = htmlentities( $this->serialize( $this->data ), ENT_QUOTES );
    $this->afterSerialize(); // ??: Should the results be passed in to allow massaging?
    return $this;
  }
  private function _deserialize() { // Used internally only... Triggers callbacks.
    $this->beforeDeserialize();
    $this->data = $this->deserialize( html_entity_decode($this->rawData, ENT_QUOTES) );
    $this->afterDeserialize(); // ??: Should the results be passed in to allow massaging?
    return $this;
  }
    
  public static $IsModel = false;
  public static $IsDocument = true;

  public static function ReindexAll($doctype) {
    doc($doctype)->reindex();
  }
  
  public static function Find($doctype) {
    return new DocumentFinder($doctype);
  }
}


class DocumentFinder extends ModelFinder {
  private $nativeFields = array('id','created_on','updated_on');
  
  public function reindex() {
    foreach ($this as $doc) {
      $doc->reindex();
    }
  }
  
  // FIXME: All these loops really need an optimization pass...
  protected function _buildSQL($isCount=false) {
    // TODO: Implement OR logic...
    $all_order_cols = array();
    $native_order_cols = array();
    foreach($this->order as $field=>$other) {
      if(in_array($field, $this->nativeFields))
        $native_order_cols[] = $field;
      else
        $all_order_cols[] = $this->_getIdxCol($field, false);
    }
    
    $all_finder_cols = array();
    $native_finder_cols = array();
    foreach($this->finder as $qry) {
      $colname = $qry['col'];
      if(in_array($colname, $this->nativeFields))
        $native_finder_cols[] = $colname;
      else
        $all_finder_cols[] = $this->_getIdxCol($colname, false);
    }
    // Also for OR?

    $tables = array_merge(array($this->objtype), $all_order_cols);
    //TODO: Should it select the id, data, and datetime(created_on, 'localtime')???
    if($isCount)
      $sql = "SELECT count(". $this->objtype .".id) as count FROM ". join(', ', $tables) ." ";
    else
      $sql = "SELECT ". $this->objtype .".* FROM ". join(', ', $tables) ." ";
    
    if(count($all_finder_cols) > 0) {
      $sql .= "WHERE ". $this->objtype .".id IN (";
      $sql .= "SELECT ". $all_finder_cols[0] .".docid FROM ". join(', ', $all_finder_cols). " ";
      $sql .= " WHERE ";
      $finders = array();
      foreach($this->finder as $qry) {
        if(!in_array($qry['col'], $this->nativeFields))
          $finders []= " ". $this->_getIdxCol($qry['col'])  ." ". $qry['comp'] .' "'. htmlentities($qry['val'], ENT_QUOTES) .'" ';
      }
      $sql .= join(' AND ', $finders);
      $sql .= ") ";
    }
    if(count($native_finder_cols) > 0) {
      $sql .= (count($all_finder_cols) > 0) ? " AND " : " WHERE ";
      $finders = array();
      foreach($this->finder as $qry) {
        if(in_array($qry['col'], $this->nativeFields))
          $finders []= " ". $this->objtype .".". $qry['col']  ." ". $qry['comp'] .' "'. htmlentities($qry['val'], ENT_QUOTES) .'" ';
      }
      $sql .= join(' AND ', $finders);
    }
    if($isCount) return $sql.";";

    if(count($this->order) > 0) {
      $sql .= " AND ";
      $sortJoins = array();
      $order_params = array();
      foreach ($this->order as $field => $dir) {
        if(!in_array($field, $this->nativeFields)) {
          $sortJoins[] = $this->_getIdxCol($field, false) .".docid = ". $this->objtype .".id ";
          $order_params[]= $this->_getIdxCol($field) ." ". $dir;
        } else {
          $order_params[]= $this->objtype .".". $field ." ". $dir;
        }
      }
      $sql .= join(" AND ", $sortJoins);
      $sql .= " ORDER BY ";
      $sql .= join(", ", $order_params);
    }

    if($this->limit != false && $this->limit > 0) {
      $sql .= " LIMIT ". $this->limit ." ";
    }
    if($this->offset != false && $this->offset > 0) {
      $sql .= " OFFSET ". $this->offset ." ";
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
class Pager implements IteratorAggregate {
  protected $finder = null;
  public $pageSize = 10;
  public $currentPage = 0;
  public $pages = 0;
  
  function __construct($finder, $pageSize=10, $params=array()) {
    if($finder instanceof ModelFinder) {
      $this->finder = $finder;
    } else if(is_string($finder)) {
      $this->finder = get($finder);
    } else {
      throw new Exception("You must specify a model name or finder object.");
    }
    $this->pageSize = $pageSize;
    $this->pages = $this->count(); // ???
    if(count($params) > 0) {
      $this->fromParams($params);
    }
  }
  
  public function fromParams($params) {
    list($key, $page) = array_slice($params, -2);
    if($key == 'page' && is_numeric($page))
      $this->currentPage = intVal($page);
  }
  
  public function count() { // Count the number of pages...
    $this->finder->limit(0)->offset(0);
    $total = $this->finder->count();
    return ceil( $total / $this->pageSize );
  }
  
  public function items() {
    $this->finder->limit($this->pagesize)->offset($this->currentPage);
    return $this->finder->fetch();
  }
  
  public function getIterator() { // For using the finder as an array in foreach() statements
    return new ArrayIterator( $this->item() );
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
      'expires' => 60*60, // In Seconds (60*60 == 1h)
    ),
  );
}

if( isset($shortstack_config) ) {
  define('FORCESHORTTAGS', @$shortstack_config['views']['force_short_tags']);
  define('USECACHE', @$shortstack_config['cacheing']['enabled']);
  define('CACHELENGTH', @$shortstack_config['cacheing']['expires']);

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