<?php

// Code inspired by old phpBB 2 MySQL layer

if ($_SERVER['MYSQL_CREDENTIALS_FILE']) {
	require $_SERVER['MYSQL_CREDENTIALS_FILE'];
} else {
	require 'mysql-credentials.php';
}

if (!defined('SQL_LAYER')) {
    /**
	 * Defines the SQL engine layer implented for our SQL abstraction class:
	 * MySQL
	 */
	define('SQL_LAYER', 'mysql');
    
    /**
     * SQL database class
     *
     * This is the MySQL implementation of our SQL abstraction layer
     */
    class sql_db {
        /*
         * @var int the connection identifier
         */
        private $id;
                
        /**
         * Initializes a new instance of the database abstraction class, for MySQL engine
         *
         * @param string $host the SQL server to connect [optionnal, by default localhost]
         * @param string $username the SQL username [optionnal, by default root]
         * @param string $password the SQL password [optionnal, by default blank]
         * @param string $database the database to select [optionnal]
         */
        function __construct($host = 'localhost', $username = 'root', $password = '' , $database = '') {
            //Connects to the MySQL server
            $this->id = @mysql_connect($host, $username, $password) or $this->sql_die(); //or die ("Can't connect to SQL server.");
            
            //Selects database
            if ($database != '') {
                mysql_select_db($database, $this->id);
            }
        }
        
        
        /**
         * Outputs a can't connect to the SQL server message and exits.
         * It's called on connect failure
         */
        function sql_die () {
            //You can custom here code when you can't connect to SQL server
            //e.g. in a demo or appliance context, include('start.html'); exit;
            //die ("Can't connect to SQL server.");
            include('start.html');
            exit;
        }
        
        /**
         * Sends a unique query to the database
         *
         * @param string $query the query to execute
         * @return resource if the query is successful, a resource identifier ; otherwise, false
         */
        function sql_query ($query) {
            return mysql_query($query, $this->id);
        }
        
        /**
         * Fetches a row of result into an associative array
         *
         * @param resource $result The result that is being evaluated, from sql_query
         * @return array an associative array with columns names as keys and row values as values
         */
        function sql_fetchrow ($result) {
            return mysql_fetch_array($result);
        }
        
        /**
         * Gets last SQL error information
         * 
         * @return array an array with two keys, code and message, containing error information
         */
        function sql_error () {
            $error['code'] = mysql_errno($this->id);
            $error['message'] = mysql_error($this->id);
            return $error;
        }
        
        
        /**
         * Gets the number of rows affected or returned by a query
         * 
         * @return int the number of rows affected (delete/insert/update) or the number of rows in query result
         */
        function sql_numrows ($result) {
            return mysql_num_rows($result);
        }
        
        /**
         * Gets the primary key value of the last query (works only in INSERT context)
         * 
         * @return int  the primary key value
         */
        function sql_nextid () {
            return mysql_insert_id($this->id);
        }
        
        /**
         * Escapes a SQL expression
         *
         * @param string $expression The expression to escape
         * @return string The escaped expression
         */
        function sql_escape ($expression) {
            return mysql_real_escape_string($expression);
        }
        
        /*
         * Sets the client character set (requires MySQL 5.0.7+).
         *
         * @param string $encoding the charset encoding to set
         */
        function set_charset ($encoding) {
            if (function_exists('mysql_set_charset')) {
                //>=PHP 5.2.3
                mysql_set_charset($encoding, $this->id);
            } else {
                //Old PHP version
                $this->sql_query("SET NAMES '$encoding'");
            }
        }
    }
    
    /**
     * The main sql_db instance
     * 
     * @global sql_db $db
     */
    $db = new sql_db($Config['sql']['host'], $Config['sql']['username'], $Config['sql']['password'], $Config['sql']['database']);
    $db->set_charset('utf8');
    
    //By security, we unset the SQL parameters, so you can safely output Zed
    //config parts (there's still the problem of the secret key, but it's less
    //a security problem than database password)
    unset($Config['sql']); 
}
?>
