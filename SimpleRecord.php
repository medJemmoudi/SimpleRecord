<?php
/**
 * @author Mohammed Jemmoudi <med.jemmoudi@gmail.com>
 * Just a simple Database LAyer to handle all your queries
 */

namespace DB\SimpleRecord;


use Exception;
use PDO;

class SimpleRecord
{
    // class variables
    private static $config;
    private static $connection_link;
    private static $db_tables;

    // other variables
    private $table;
    private $relations;
    private $table_cols;
    protected $primaryKey;

    public function __construct( $table = null, $relations = null )
    {
        $this->table = $table;
        $this->relations = $relations;
    }

    /**
     * A magic function writing to handle dynamic calls
     * @param $method La méthode en question
     * @param $params Les arguments de la méthode
     * @return Mixed|Array
     */
    public function __call( $method, $params ) {

        if ( preg_match("#^findBy#i", $method) ) {
            return $this->handleFindByCalls($method);
        }

        if ( preg_match('#^find#i', $method) ) {
            return $this->handleFindCalls($method, $params);
        }

        if ( preg_match("#^joinWith#i", $method) ) {
            return $this->handleJoinWithCalls($method, $params);
        }

        if ( preg_match('#^Count#i', $method) ) {
            return $this->handleCountCalls($method, $params);
        }

        return null;
    }


    /**
     * Setup configuration
     * @param $host   your hostname
     * @param $dbname your database name
     * @param $user   database user, 'root' by default
     * @param $pass   database password, empty by default
     */
    public static function setConfig($host,  $dbname, $user = "root", $pass = "") {
        self::$config['Host']   = $host;
        self::$config['User']   = $user;
        self::$config['Pass']   = $pass;
        self::$config['Dbname'] = $dbname;
    }

    /**
     * La fonction qui permet de lancer la connexion avec la base de données
     * @return void
     */
    public static function getConnected() {
        if ( !self::$connection_link ) {
            try {
                $dns = 'mysql:host='. self::$config['Host'] .';dbname=' . self::$config['Dbname'];
                self::$connection_link = new PDO( $dns, self::$config['User'], self::$config['Pass'] );
            } catch (Exception $e) {
                echo "Connexion impossible" . $e->getMessage();
                die();
            }
        }
        if ( self::$connection_link ) self::getTables();
    }

    /**
     * Une fonction qui permet de renvoyer un message d'erreur
     * @param $msg Le message à renvoyer
     * @return boolean
     */
    protected function error_msg( $msg ) {
        echo "<h2>". $msg ."</h2>";
        return true;
    }

    /**
     * fetch all table from the current database
     * @return void
     */
    private static function getTables() {
        $statement = self::$connection_link->query("SHOW TABLES");
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $key = 'Tables_in_' . self::$config['Dbname'];
        while ( $result = $statement->fetch() ) {
            self::$db_tables[] = $result[ $key ];
        }
    }

    /**
     * define the current table in use
     * @param String $table_name - Your table name
     * @return void
     */
    public function setTable( $table_name ) {
        if ( !in_array($table_name, self::$db_tables) ) {
            $this->error_msg("Table doesn't exist");
        } elseif ( $this->table == $table_name ) {
            $this->error_msg("Table is already set");
        } else {
            $this->table = $table_name;
            $this->getTableColumns();
            $this->changed = false;
            $this->primaryKey = $this->getPKey();
        }
    }

    /**
     * get the primaryKey for the current table
     * @return String
     */
    public function getPKey() {
        $sh = $this->sqlQuery("SHOW INDEX FROM ". $this->table ." WHERE Key_name = 'PRIMARY'");
        return $sh['Column_name'];
    }

    /**
     * fetch all columns related to the current table
     * @return void
     */
    private function getTableColumns () {
        $statement = self::$connection_link->query("DESCRIBE " . $this->table);
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        while ( $result = $statement->fetch() ) {
            $this->table_cols[] = $result['Field'];
        }
    }

    /**
     * this function handle all INSERT operations
     * @param Array $data
     * @return boolean
     */
    public function in ( $data ) {
        if ( isset($data) and is_array($data) ) {
            if ( count($data) > count($this->table_cols) - 1 )
                $this->error_msg('table columns less than @data');
            else {
                $cols = array_slice($this->table_cols, 1);
                $cols = $this->stringFormat($cols, "`");
                $vals = $this->stringFormat($data);
                $query = "INSERT INTO ". $this->table ." (". $cols .") VALUES (". $vals .")";
                $pre = self::$connection_link->prepare( $query );
                return $pre->execute();
            }
        }
        return false;
    }

    /**
     * Adapt and concat data using a separator
     * @param Array $var
     * @param String $sep
     * @return Array|Boolean
     */
    private function stringFormat ( $var, $sep = "'" ) {
        if ( is_array($var) ) {
            foreach ($var as $key => $value) {
                if ( is_string($value) ) {
                    $var[ $key ] = $sep . $value . $sep;
                }
            }
            $var = implode(",", $var);
            return $var;
        } else {
            $this->error_msg('@var must be an array !');
        }
        return false;
    }

    /**
     * Handle all your Select queries
     * @param String $cols
     * @param Array $cond - Where conditions
     * @param Array $options - Other options like offset or Limit
     * @return Array|Booealn
     */
    public function find( $cols = '*', $cond = null, $options = null) {
        $query = "SELECT ". $cols ." FROM " . $this->table;
        if ( $cond ) {
            $length = count($cond) - 1;
            $conditions = " WHERE ";
            foreach ($cond as $key => $value) {
                $conditions .= "`". $key ."`='". $value ."'";
                if ( $length != 0 ) $conditions .= ' AND ';
                $length--;
            }
            $cond = $conditions;
        }
        if ( $options && is_array($options) ) {
            $opString = "";
            foreach ($options as $key => $value) {
                $opString .= ' ' . $key . ' ' . $value;
            }
            $options = $opString;
        }
        $query .= $cond . $options;
        $statement = self::$connection_link->prepare( $query );
        $statement->execute();
        $count = $statement->rowCount();
        if ( $count > 0 ) {
            $Data = array();
            while ( $result = $statement->fetch(PDO::FETCH_ASSOC) ) {
                $Data[] = $result;
            }
            return $Data;
        } else {
            return false;
        }
    }

    /**
     * Handle all your Join queries
     * @param String $f_table - The foreign table
     * @param String $fk - The foreign key
     * @param Array $cond - Where conditions
     * @param Array $options - Other options like offset or Limit
     * @return Array|Boolean
     */
    private function joinWith( $f_table, $fk, $cond = null, $options = null ) {
        if ( !in_array($f_table, self::$db_tables) ) {
            $this->error_msg('@table('. $f_table .') not found in this database !');
            return false;
        }
        $cols = isset($options['fields']) ? $options['fields'] : '*';
        $query = "SELECT ".$cols." FROM ".$this->table." INNER JOIN ".$f_table." ON ".$this->table.".".$fk."=".$f_table.".".$fk;
        if ( $cond ) {
            $length = count($cond) - 1;
            $conditions = " WHERE ";
            foreach ($cond as $key => $value) {
                $conditions .= "`". $key ."`='". $value ."'";
                if ( $length != 0 ) $conditions .= ' AND ';
                $length--;
            }
            $cond = $conditions;
        }
        if ( $options && is_array($options) ) {
            $opString = "";
            foreach ($options as $key => $value) {
                if ( $options['fields'] ) continue;
                $opString .= ' ' . $key . ' ' . $value;
            }
            $options = $opString;
        }
        $query .= $cond . $options;
        $pre = self::$connection_link->prepare( $query );
        $pre->execute();
        $rowAffected = $pre->rowCount();
        if ( $rowAffected > 0 ) {
            while ( $result = $pre->fetch(PDO::FETCH_ASSOC) ) {
                $Data[] = $result;
            }
            return $Data;
        } else {
            return false;
        }
    }

    /**
     * Load one record and fetch it as SimpleResult
     * @param String $field
     * @param String $value
     * @return SimpleResult
     */
    private function findBy( $field, $value ) {
        if ( !empty($field) && !empty($value) ) {
            $cond = array($field => $value);
            $options = array("LIMIT" => "0,1");
            $result = $this->find('*', $cond, $options);
            $result = array_pop($result);
            $sResult = new SimpleRecord( $result, $this->primaryKey );
            return isset($sResult) ?  $sResult :  false;
        }
    }

    /**
     * Remove all records according to conditions
     * @param Array $cond
     * @return Integer
     */
    public function delete( $cond = null ) {
        $query = "DELETE FROM " . $this->table;
        if ( $cond ) {
            $length = count($cond) - 1;
            $conditions = " WHERE ";
            foreach ($cond as $key => $value) {
                $conditions .= "`". $key ."`='". $value ."'";
                if ( $length != 0 ) $conditions .= ' AND ';
                $length--;
            }
            $cond = $conditions;
        }
        $query .= $cond;
        $pre = self::$connection_link->prepare( $query );
        $pre->execute();
        $affectedRow = $pre->rowCount();
        return $affectedRow > 0 ? true : false;
    }

    /**
     * Performs a basic search using Like statement
     * @param Array $specifications - What are you looking for.
     * @param String $attach - attach your specifications using OR|AND, default is OR.
     * @param Array $options
     * @return Mixed|Boolean
     */
    public function searchFor( $specifications , $attach = "OR", $options = null) {
        array('fields' => '*', 'usr' => 'moh', "pwd" => "044");
        if ( isset($specifications['fields']) ) $fields = array_shift($specifications);
        else $fields = '*';
        $query = "SELECT ". $fields ." FROM ". $this->table ." WHERE";
        $_string = "";
        $_count = count($specifications);
        $_index = 0;

        foreach ($specifications as $key => $value) {
            $_string .= " " . $key . " LIKE '%" . strip_tags( addslashes($value) ) . "%'";
            if ( $_index < $_count-1 ) {
                $_string .= " " . $attach ;
            }
            $_index++;
        }

        if ( $options && is_array($options) ) {
            $opString = "";
            foreach ($options as $key => $value) {
                $opString .= ' ' . $key . ' ' . $value;
            }
            $options = $opString;
        }

        $query .= $_string . $options;
        $statement = self::$connection_link->prepare( $query );
        $statement->execute();
        $count = $statement->rowCount();

        if ( $count > 0 ) {
            $Data = array();
            while ( $result = $statement->fetch(PDO::FETCH_ASSOC) ) {
                $Data[] = $result;
            }
            return $Data;
        } else {
            return false;
        }
    }

    /**
     * Update records according to conditions
     * @param $data
     * @param null $cond
     * @return boolean
     */
    public function update ($data, $cond = null) {
        if ( !empty($data) && is_array($data) ) {
            if ( $this->findAll($cond) ) {
                $query = "UPDATE " . $this->table . " SET ";
                $length = count($data) - 1;
                foreach ($data as $key => $value) {
                    $query .= "`". $key ."`='". $value ."'";
                    if ( $length != 0 ) $query .= ',';
                    $length--;
                }
                if ( $cond ) {
                    $length = count($cond) - 1;
                    $conditions = " WHERE ";
                    foreach ($cond as $key => $value) {
                        $conditions .= "`". $key ."`='". $value ."'";
                        if ( $length != 0 ) $conditions .= ' AND ';
                        $length--;
                    }
                    $cond = $conditions;
                }
                $query .= $cond;
                $pre = self::$connection_link->prepare( $query );
                $pre->execute();
                $rowAffected = $pre->rowCount();
                return $rowAffected > 0 ? true : false;
            } else {
                $this->error_msg('Not found @data');
            }
        }
    }

    /**
     * Performs SQL COUNT according to conditions
     * @param Array $cond
     * @return Integer
     */
    public function Count( $cond = null ) {
        $r = $this->find('count(*) AS num', $cond);
        return (int) array_shift($r)['num'];
    }

    /**
     * Performs custom SQL query
     * @param String $query
     * @return Mixed|bool
     */
    public function sqlQuery( $query ) {
        $pre = self::$connection_link->prepare( $query );
        $pre->execute();
        if ( $pre->rowCount() > 0 ) {
            if ( $result = $pre->fetchAll(PDO::FETCH_ASSOC) ) {
                if ( count($result) == 1 ) return array_shift($result);
                else return $result;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $method
     * @return bool|simpleResult
     */
    private function handleFindByCalls($method)
    {
        $field = str_replace('findBy', '', $method);
        $field = strtolower($field);
        if (stripos('id', $field) !== false) $field = $this->primaryKey;
        return $this->findBy($field, (string)array_shift($params));
    }

    /**
     * @param $method
     * @param $params
     * @return array|bool
     */
    private function handleFindCalls($method, $params)
    {
        $field = strtolower(str_replace('find', '', $method));
        if ($field === 'all') $field = '*';
        else $this->error_msg("Not found method");
        $cond = isset($params[0]) ? $params[0] : null;
        $options = isset($params[1]) ? $params[1] : null;
        return $this->find($field, $cond, $options);
    }

    /**
     * @param $method
     * @param $params
     * @return array|bool
     */
    private function handleJoinWithCalls($method, $params)
    {
        $f_table = strtolower(str_replace('joinWith', '', $method));
        $fk = $params[0];
        $cond = isset($params[1]) ? $params[1] : null;
        $options = isset($params[2]) ? $params[2] : null;
        return $this->joinWith($f_table, $fk, $cond, $options);
    }

    /**
     * @param $method
     * @param $params
     * @return int
     */
    private function handleCountCalls($method, $params)
    {
        $fields = strtolower(str_replace('Count', '', $method));
        if ($fields === 'all') return $this->Count();
        else {
            $fields = explode('and', $fields);
            $data = [];
            for ($i = 0; $i < count($fields); $i++) {
                $data[$fields[$i]] = $params[$i];
            }
            return $this->Count($data);
        }
    }
}