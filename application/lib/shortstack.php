<?php
 
// ShortStack v0.9.2
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
      return self::controllerPath( underscore($className) );
    } else if(strpos($className, 'elper') > 0) {
        return self::helperPath( underscore($className) );
    } else { // Model
      return self::modelPath( underscore($className) );
    }    
  }
  // Loads all models in the models/ folder and returns the model class names
  public static function LoadAllModels() {
    global $shortstack_config;
    $model_files = glob( self::modelPath("*") );
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
      
      } else if($mdl instanceof DocumentModel) {
        $res = $mdl->_defineDocumentFromModel();
        $needDocInit = true;
      }
    }
    if($needDocInit) Document::InitializeDatabase();
    return $modelnames;
  }
  // File paths...
  public static function viewPath($path) {
    return self::getPathFor('views', $path);
  }
  public static function controllerPath($path) {
    return self::getPathFor('controllers', $path);
  }
  public static function modelPath($path) {
    return self::getPathFor('models', $path);
  }
  public static function helperPath($path) {
    return self::getPathFor('helpers', $path);
  }
  protected static function getPathFor($type, $path) {
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
  require_once( ShortStack::helperPath($helper) );
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
    return Model::FindById($objtype, $id);
  } else {
    return new ModelFinder($doctype);
  }
}


class Dispatcher {
  static public $dispatched = false;
  static public $current = null;

  static public function recognize($override_controller=false) {
    if(!defined('BASEURI')) {
//      $base_uri = @ str_replace($_SERVER['PATH_INFO'], "/", array_shift(explode("?", $_SERVER['REQUEST_URI'])));
      $base_uri = @ getBaseUri();
      define("BASEURI", $base_uri);
    }
    //echo "base uri = ". BASEURI;
    $uri = @  '/'.$_SERVER['QUERY_STRING']; //@ $_SERVER['PATH_INFO']; // was REQUEST_URI
    $path_segments = array_filter(explode('/', $uri), create_function('$var', 'return ($var != null && $var != "");'));
    $controller = array_shift($path_segments);
    // override is mainly only used for an 'install' controller... I'd imagine.
    if($override_controller != false) $controller = $override_controller;
    if($controller == '') {
      $controller = 'home_controller';
    } else { 
      $controller = $controller.'_controller';
    }
    return self::dispatch($controller, $path_segments);
  }
  
  static public function dispatch($controller_name, $route_data=array()) {
    if (!self::$dispatched) {
      $controller = self::getControllerClass($controller_name);
      self::$current = $controller;
      try {
        $ctrl = new $controller();
        $ctrl->execute($route_data);
        // echo "CONTROLLER:<pre>$controller\n";
        // print_r($route_data);
        // echo "</pre>";
        self::$dispatched = true;
      }
      catch( NotFoundException $e ) {
        global $shortstack_config;
        if(@ $notfound = $shortstack_config['controllers']['404_handler']) {
          $uri = @ '/'.$_SERVER['QUERY_STRING']; // $uri = @ $_SERVER['PATH_INFO']; // was REQUEST_URI
          $path_segments = array_filter(explode('/', $uri), create_function('$var', 'return ($var != null && $var != "");'));
          $hack_var = array_shift($path_segments); array_unshift($path_segments, $hack_var);
          $controller = self::getControllerClass( $notfound );
          self::dispatch($controller, $path_segments);
        } else {
          echo "Not Found.";
        }
      }
      catch( Redirect $e ) {
        $route_data = explode('/', $e->getMessage());
        $controller = self::getControllerClass( array_shift($route_data) );
        self::dispatch($controller, $route_data);
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
class Controller {
  protected $defaultLayout = "_layout";
  
  // Default execute method... Feel free to override.
  function execute($args=array()) {
    if(@ $this->secure) $this->ensureLoggedIn();
    $this->dispatchAction($args);
  }
  
  function index($params=array()) {
    throw new NotFoundException("Action <code>index</code> not implemented.");
  }
  
  function render($view, $params=array(), $wrapInLayout=null) {
    $tmpl = new Template( ShortStack::viewPath($view) );
    $content = $tmpl->fetch($params);
    $this->renderText($content, $params, $wrapInLayout);
  }
  
  function renderText($text, $params=array(), $wrapInLayout=null) {
    $layoutView = ($wrapInLayout == null) ? $this->defaultLayout : $wrapInLayout;
    
    if($layoutView !== false) {
      $layout = new Template( ShortStack::viewPath($layoutView) );
      $layout->contentForLayout = $text;
      $layout->display($params);
    } else {
      echo $text;
    }
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
  
}
class DB
{
  static protected $pdo; // Public for now...
  
  static public function connect($conn, $user="", $pass="", $options=array()) {
    self::$pdo = new PDO($conn, $user, $pass, $options);
  }
  
  static public function query($sql_string) {
    return self::$pdo->query($sql_string);
  }
  
  static public function getLastError() {
    return self::$pdo->errorInfo();
  }

  static public function fetchAll($sql_string) {
    $statement = self::query($sql_string);
    return $statement->fetchAll(); // PDO::FETCH_GROUP
  }
  
  static public function ensureNotEmpty() {
    $statement = self::query('SELECT name FROM sqlite_master WHERE type = \'table\'');
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
  
  public function count() {
    return count($this->fetch());
  }
  
  public function get() {   // Returns the first match
    $oldLimit = $this->limit;
    $this->limit = 1; // Waste not, want not.
    $docs = $this->_execQuery();
    $this->limit = $oldLimit;
    return @$docs[0];
  }
  
  public function fetch() { // Executes current query
    return $this->_execQuery();
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
  
  
  protected function _execQuery() {
    if($this->__cache != false) return $this->__cache;
    $sql = $this->_buildSQL();
    $stmt = DB::query($sql);
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
        return Model::FindById($mdlClass, "id = ".$this->$fk);
      } else {
        throw new Exception("A relationship has to be defined as a model or document");
      }
    }
    
    public function updateValues($values=array()) {
      foreach($values as $key=>$value) {
        $this->$key = $value;
      }
    }
    
    public function update($values=array()) {
      $this->updateValues($values);
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
        $statement = DB::query($sql);
        // Get the record's generated ID...
        $result = DB::query('SELECT last_insert_rowid() as last_insert_rowid')->fetch();
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
        $statement = DB::query($sql);
      }
      $this->changedFields = array();
      $this->isDirty = false;
      $this->isNew = false;
      $this->afterSave();
    }
  }
  
  // Warning: Like Han solo, this method doesn't fuck around, it will shoot first.
  public function destroy() {
    $this->beforeDestroy();
    $sql = "DELETE FROM ".$this->modelName." WHERE id = ". $this->id .";";
    $statement = DB::query($sql);
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
     
     return DB::query( $sql );
   }

   public function _count($whereClause=null, $sqlPostfix=null) {
     return self::Count($this->modelName, $whereClause, $sqlPostfix);
   }
   static public function Count($className, $whereClause=null, $sqlPostfix=null) {
     $sql = "SELECT count(id) as count FROM ".$className;
     if($whereClause != null) $sql .= " WHERE ".$whereClause;
     if($sqlPostfix != null)  $sql .= " ".$sqlPostfix;
     $sql .= ';';
     $statement = DB::query($sql);
     if($statement) {
       $results = $statement->fetchAll(); // PDO::FETCH_ASSOC ???
       return (integer)$results[0]['count'];
     } else { // Throw an ERROR?
       return 0;
     }
   }

   public function _query($whereClause=null, $sqlPostfix='', $selectClause='*') {
     if(@ strpos(strtolower(' '.$sqlPostfix), 'order') < 1 && isset($this->defaultOrderBy)) {
       $sqlPostfix .= " ORDER BY ".$this->defaultOrderBy;
     }
     return self::Query($this->modelName, $whereClause, $sqlPostfix, $selectClause);
   }
   static public function Query($className, $whereClause=null, $sqlPostfix='', $selectClause='*') {
     $sql = "SELECT ". $selectClause ." FROM ".$className." ";
     if($whereClause != null) {
       $sql .= " WHERE ". $whereClause;
     }
     if(@ strpos(strtolower(' '.$sqlPostfix), 'order') < 1) {
       // God I hate this... WILL BE REMOVED SOON!!!
       $mdl = new $className();
       if(isset($mdl->defaultOrderBy)) {
         $sql .= " ORDER BY ".$mdl->defaultOrderBy." ";
       }
     }
     if($sqlPostfix != '') {
       $sql .= " ". $sqlPostfix;
     }
     $sql .= ';';
     $statement = DB::query($sql);
     if(!$statement) {
       $errInfo = DB::getLastError();
       throw new Exception("DB Error: ".$errInfo[2] ."\n". $sql);
     }
     return $statement->fetchAll(PDO::FETCH_ASSOC);
   }

   public function _find($whereClause=null, $sqlPostfix='', $selectClause='*') {
     return self::Find($this->modelName, $whereClause, $sqlPostfix, $selectClause);
   }
   static public function Find($className, $whereClause=null, $sqlPostfix='', $selectClause='*') {
     $results = self::Query($className, $whereClause, $sqlPostfix, $selectClause);
     $models = array();
     foreach($results as $row) {
       $models[] = new $className($row);
     }
     return $models;
   }

   public function _findFirst($whereClause=null, $sqlPostfix='') {
     return self::FindFirst($this->modelName, $whereClause, $sqlPostfix);
   }
   static public function FindFirst($className, $whereClause=null, $sqlPostfix='') {
     $models = self::Find($className, $whereClause, $sqlPostfix." LIMIT 1");
     return @ $models[0];
   }

   public function _findById($id) {
     return self::FindById($this->modelName);
   }
   static public function FindById($className, $id) {
     return self::FindFirst($className, "id = ".$id);
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
class Document {
  private static $_document_indexes_ = array();
  
  public static function Register($class) {
    $doc = new $class();
    $doc->_defineDocumentFromModel();
  }
  
  public static function Define($name, $indexes=array(), $createClass=true) {
    Document::$_document_indexes_[$name] = array_merge($indexes, array()); // a hokey way to clone an array
    if($createClass)
      eval("class ". $name ." extends DocumentModel {  }");
  }
  
  public static function InitializeDatabase() {
    // Loop through all the docs and create tables and index tables...
    foreach( Document::$_document_indexes_ as $docType => $indexes) {
      $tableSQL = "CREATE TABLE IF NOT EXISTS ". $docType ." ( id INTEGER PRIMARY KEY, data TEXT, created_on TIMESTAMP, updated_on TIMESTAMP );";
      DB::query( $tableSQL );
      foreach ($indexes as $field=>$fType) {
        $indexSQL = "CREATE TABLE IF NOT EXISTS ". $docType ."_". $field ."_idx ( id INTEGER PRIMARY KEY, docid INTEGER, ". $field ." ". $fType ." );";
        DB::query( $indexSQL );
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
      $results = DB::fetchAll($sql);
      foreach ($results as $row) {
        $docs[] = new $doctype($row);
      }
    } else {
      // Do 'em all!
      foreach( Document::$_document_indexes_ as $docType => $indexes) {
        $sql = "SELECT * FROM ".$doctype."";
        $results = DB::fetchAll($sql);
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
          $results = DB::query($sql);
          $sql = "INSERT INTO ". $indexTable ." ( docid, ". $field ." ) VALUES (".$doc->id.', "'.htmlentities($doc->{$field}, ENT_QUOTES).'" );';
          $results = DB::query($sql);
        }
      } else {
        echo "! No indexes for ". $doc->modelName ."\n";
      }
    }
  }
  
  public static function Get($doctype, $id) {
    $sql = "SELECT * FROM ".$doctype." WHERE id = ". $id .";";
    $results = DB::fetchAll($sql);
    $docs = array();
    foreach ($results as $row) {
      $docs[] = new $doctype($row);
    }
    return @$docs[0];
  }
 
  public static function Find($doctype) {
    return new DocumentFinder($doctype);
  }
  
  public static function Destroy($doctype, $id) {
    $sql = "DELETE FROM ".$doctype." WHERE id = ". $id .";";
    DB::query($sql);
    foreach ( Document::GetIndexesFor($doctype) as $field=>$fType) {
      $sql = "DELETE FROM ".$doctype."_".$field."_idx WHERE docid = ". $id .";";
      DB::query($sql);
    }
  }

  public static function GetIndexesFor($doctype) {
    return Document::$_document_indexes_[$doctype];
  }
}

class DocumentModel extends CoreModel {

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
      $this->beforeSave(); // Cannot cancel events... yet.
      if($this->isNew) { // Insert
        $this->beforeCreate(); // Cannot cancel events... yet.
        $this->_serialize();
        $sql = 'INSERT INTO '.$this->modelName.' ( data ) VALUES ( "'. $this->rawData .'" );';
        $statement = DB::query($sql);
        $result = DB::query('SELECT last_insert_rowid() as last_insert_rowid')->fetch(); // Get the record's generated ID...
        $this->id = $result['last_insert_rowid'];
        Document::Reindex($this->modelName, $this->id);
        $this->afterCreate(); // Cannot cancel events... yet.
      } else { // Update
        $this->serialize();
        $sql = "UPDATE ".$this->modelName.' SET data="'.htmlentities( json_encode($this->data), ENT_QUOTES).'" WHERE id = '. $this->id .';';
        $statement = DB::query($sql);
        $index_changed = array_intersect($this->changedFields, array_keys(Document::GetIndexesFor($this->modelName)));
        if(count($index_changed) > 0)  // Only if an indexed field has changed
          Document::Reindex($this->modelName, $this->id);
      }
      $this->changedFields = array();
      $this->isDirty = false;
      $this->isNew = false;
      $this->afterSave(); // Cannot cancel events... yet.
    }
    return $this;
  }

  // Warning: Like Han solo, this method doesn't fuck around, it will shoot first.
  public function destroy() {
    $this->beforeDestroy(); // Cannot cancel events... yet.
    Document::Destroy($this->modelName, $this->id);
    $this->afterDestroy(); // Cannot cancel events... yet.
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
      '404_handler'=>'home',
    ),
    'helpers' => array(
      'folder' => 'helpers',
      'autoload'=> array(),
    ),
  );
}

if( isset($shortstack_config) ) {
  define('FORCESHORTTAGS', @$shortstack_config['views']['force_short_tags']);
  if(@ is_array($shortstack_config['helpers']['autoload']) ) {
    foreach($shortstack_config['helpers']['autoload'] as $helper) {
      require_once( ShortStack::helperPath($helper."_helper"));
    }
  }
  if(@ $shortstack_config['db']['autoconnect'] ) {
    DB::connect( $shortstack_config['db']['engine'].":".$shortstack_config['db']['database'] ); 
  }
  if(@ $shortstack_config['db']['verify'] ) {
    DB::ensureNotEmpty();
  }
} else {
  throw new NotFoundException("ShortStack configuration missing!");
}