<?php
/**
 * Copyright 2015-2016 Twenty Four Colors @web twentyfourcolors.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Conn World Mysql PHP class
 *
 * @package conn.world
 * @version 2.0
 * @author Hugo Robles <hugorobles@twentyfourcolors.com>
 */

/*
 *
 * HOWTO GUIDE
 *
 * -
 * --
 * --- CONFIGURATION
 * -
 *
 *
 *
 *
 * -
 * --
 * --- SETTINGS
 * -
 *
 *-> $get_debug_mode | OPTIONS 'console' or 'chrome'
 *---> For option 'chrome' require https://chrome.google.com/webstore/detail/chrome-logger/noaneddfkdjfnfdakjjmocngnfkfehhd for show info in inspect log
 *
 * -
 * --
 * --- QUERY ACTIONS
 * -
 *
 * SELECT
 *-> get_results($list_results,$table_conn,$list_condition,$list_order,$list_limit);
 *
 * SELECT INNER RESULT
 *-> get_inner_results($list_results,$table_conn,$table_inner,$list_on_condition,$list_inner_condition,$list_order,$list_limit);
 *
 * SELECT OUTER RESULT
 *-> get_outer_results($list_results,$table_conn,$table_inner,$list_on_condition,$list_outer_condition,$list_order,$list_limit);
 *
 * SELECT COUNT(*)
 *-> get_count($table_conn,$list_condition);
 *
 * SELECT SUM
 *-> get_sum($table_conn,$list_sum,$list_condition);
 *
 * UPDATE
 *-> set_update($table_conn,$list_set,$list_condition);
 *
 * DELETED
 *-> set_delete($table_conn,$list_condition);
 *
 * INSERT
 *-> set_insert($table_conn,$list_insert,$list_condition);
 *
 * EXIST
 *-> exist($table_conn,$list_condition);
 *
 * -
 * --
 * --- SHOW
 * -
 *
 *
 */




/////////////////// ARTICLES FUNCTIONS MYSQL //////////////////

class WorkAction{

    // INIT VARIABLES
    public $db_select;
    public $get_query_num;
    public $get_query_key;
    public $get_sql;
    public $get_affected;
    public $get_insert_id;
    public $get_memcached_status;
    public $get_time_memcached;
    public $get_fetch_assoc;
    public $get_debug_mode;

    // MAIN CONSTRUCTOR
    function __construct($section_connection,$conn_server,$conn_user,$conn_pass,$active_memcached) {

        // Establishes db connect
        if ($section_connection == 'pepper') {
            $db_sel = "peppers";
        }
        // Select DB
        $this->db_select = $db_sel;

        // Database Connect Information
        $this->conn_server = $conn_server;
        $this->conn_user = $conn_user;
        $this->conn_pass = $conn_pass;

        // Start to connection
        mb_internal_encoding('UTF-8');
        mb_regex_encoding('UTF-8');
        $mysqli = new mysqli($this->conn_server,$this->conn_user,$this->conn_pass,$this->db_select);
        $mysqli->set_charset("utf8");

    }

    // MYSQL DATABASE CONNECT
    public function query($sql){

        $execution = $mysqli->query($sql);

        // Save number row affect
        $this->get_affected = $mysqli->affected_rows;

        // Save insert ID
        $this->get_insert_id = $mysqli->insert_id;

        return $execution;
    }

    // Set debug mode
    public function get_debug_mode($mode){
        $this->get_debug_mode = $mode;
        if(is_null($mode) && file_exists('mysql.php.log')){
            unlink('mysql.php.log');
        }
    }

    // Set MEMCACHED Active
    public function set_memcached($active){
        $this->get_memcached_status = $active;
    }

    // Set time MEMCACHED QUERY
    public function set_time_memcached($time){
        $this->get_time_memcached = $time;
    }

    // Set array number for specials SELECT (BETA)
    public function set_fetch_assoc($fetch){
        $this->get_fetch_assoc = $fetch;
    }

    // Show debug information
    public function show_log($info){
        if($this->get_debug_mode == 'console'){
            error_log($info,3,'mysql.php.log');
        }elseif(class_exists(ChromePhp) && $this->get_debug_mode == 'chrome'){
            ChromePhp::warn($info);
            if(file_exists('mysql.php.log')){
                unlink('mysql.php.log');
            }
        }
        return;
    }

    // Real Espape Filter for conflict charters in INSERT and UPDATES
    public function filter_escape($value){
        if(!is_array($value)){
            $value = trim($value);
            $value = $this->mysqli->real_escape_string($value);
        }else{
            $value = array_map(array('DB', 'filter'), $value);
        }
        return $value;
    }

    // General function to running QUERY
    public function action($consult_type,$sql){

        // Running SELECT
        if ($consult_type == 'SELECT') {

            // Init variables
            $row_result  = NULL;

            // Set Memcached status
            $memcached_status = $this->get_memcached_status;

            // Set Memcached time
            $time_memcached = $this->get_time_memcached;

            // Set Fetch Assoc
            $fetch_assoc = $this->get_fetch_assoc;

            // Set Memcached active by default
            if (is_null($memcached_status)) {
                $memcached_status = 'active';
            }
            // Check if Memcached is active
            if($memcached_status == 'active') {
                if ($time_memcached != 0 || is_null($time_memcached)) {

                    // Set default Memcached time (6 hour) if set_time_memcached is null
                    if (is_null($time_memcached)) {
                        $time_memcached = 21600;
                    }

                    // Start MEMCACHED
                    $key_memcached = md5('key' . $sql);
                    $servers = array(array('localhost', 11211));
                    $memcache = new Memcached;
                    $memcache->addServers($servers);

                    // Load Memcached QUERY
                    $row_result = $memcache->get($key_memcached);
                    $memcached_action = 1;
                }
            }

            // MEMCACHED OR NOT MEMCACHED
            if(!$row_result || !isset($memcached_action)){
                $execution = $this->query($sql);

                // Check number result SELECT
                $num_result = $execution->num_rows;

                if ($num_result == 0) {
                    $row_result = NULL;
                    if($memcached_status == 'active') {
                        if (isset($memcached_action)) {
                            $memcache->set($key_memcached, $row_result, $time_memcached);
                        }
                    }
                }else{
                    // Make array with SELECT result
                    if ($num_result == 1 && !isset($fetch_assoc)) {
                        $row_result = $execution->fetch_assoc();
                    }else{
                        while($result = $execution->fetch_assoc()){
                            $row_result[] = $result;
                        }
                    }
                    if($memcached_status == 'active') {
                        if (isset($memcached_action)) {
                            $memcache->set($key_memcached, $row_result, $time_memcached);
                        }
                    }
                }
            }

            // Save result number
            if (!isset($num_result)) {
                $num_result = NULL;
            }elseif ($num_result >= 1) {
                $num_result = count($row_result);
            }

            // If not active Memcached
            if (!isset($memcached_action)) {
                $key_memcached = 'not_key';
            }

            // Save number of results
            $this->get_query_num = $num_result;

            // Save Memcached Key
            $this->get_query_key = $key_memcached;

            // Save Memcached time set
            $this->get_time_memcached = $time_memcached;

            // Delete Fetch Assoc
            if (isset($this->get_fetch_assoc)) {
                unset($this->get_fetch_assoc);
            }

            // Return results log
            if (!is_null($this->get_debug_mode)) {
                $this->show_log("\n -- ".$num_result." RESULTS FOR THIS QUERY -- \n\n");
            }

            //$this->disconnect();
            return $row_result;
        }

        // Running UPDATE
        if ($consult_type == 'UPDATE') {

            $this->query($sql);

            $affected_rows = $this->get_affected;
            if ($affected_rows > 0 && !is_null($this->get_debug_mode)) {
                $this->show_log("\n -- ".$affected_rows." UPDATES FOR THIS QUERY -- \n\n");
            }elseif(!is_null($this->get_debug_mode)){
                $this->show_log("\n -- 0 UPDATES FOR THIS QUERY -- \n\n");
            }
        }

        // Running DELETE
        if ($consult_type == 'DELETE') {

            $this->query($sql);

            $affected_rows = $this->get_affected;
            if ($affected_rows > 0 && !is_null($this->get_debug_mode)) {
                $this->show_log("\n -- ".$affected_rows." UPDATES FOR THIS QUERY -- \n\n");
            }elseif(!is_null($this->get_debug_mode)){
                $this->show_log("\n -- 0 UPDATES FOR THIS QUERY -- \n\n");
            }
        }

        // Running INSERT
        if ($consult_type == 'INSERT') {

            $this->query($sql);

            // Return ID assigned to INSERT
            $id_insert = $this->get_insert_id;
            if ($id_insert) {
                return $id_insert;
            }else{
                if (!is_null($this->get_debug_mode)) {
                    $this->show_log("\n -- ERROR IN THIS QUERY -- \n\n");
                }
            }
        }
    }

    // Public function to create semantics WHERE
    public function where($list_condition){
        $i=0;
        $a=0;
        $all_where = '';
        foreach ($list_condition as $key) {

            if ($i%2!=0) {
                $all_where = $all_where.$key;
            }else{
                if ($a%2!=0) {
                    // Search now() condition or null for put ''
                    $find_now = strpos($key, 'now()');
                    $find_null = strpos($key, 'null');
                    if ($find_now === FALSE && $find_null === FALSE) {
                        $all_where = $all_where." '".$key."' ";
                    }else{
                        $all_where = $all_where." ".$key." ";
                    }
                }else{
                    // Search INNER condition
                    $find_inner = strpos($key, '.');
                    if ($find_inner === FALSE) {
                        $all_where = $all_where." `".$key."` ";
                    }else{
                        $j=0;
                        $all_inner = explode('.', $key);
                        foreach ($all_inner as $key) {
                            if ($j==0) {
                                $all_where = $all_where."`".$key."`";
                            }else{
                                $all_where = $all_where.".`".$key."`";
                            }$j++;
                        }
                    }
                }
                $a++;
            }
            $i++;
        }
        return 'WHERE '.$all_where;
    }

    // Public function to create semantics ORDER for INSERT
    public function order($list_order){
        if ($list_order == 'rand') {
            $work_order = 'RAND()';
        }else{
            $list_order_created = explode(" - ", $list_order);
            $count_order = count($list_order_created);
            $mod_order_end = strtoupper($list_order_created[$count_order - 1]);

            $work_order = str_replace(' -','',$list_order);
            $work_order = str_replace($list_order_created[$count_order - 1],$mod_order_end,$work_order);
        }

        return 'ORDER BY '.$work_order;
    }

    // Public function to create semantics LIMIT for SELECT
    public function limit($list_limit){
        return 'LIMIT '.$list_limit;
    }

    // Public function to create semantics LIST for SELECT
    public function listing($list_results){
        if ($list_results == 'all') {
            return '*';
        }else{
            return $list_results;
        }

    }

    // Public function to create semantics SET for UPDATE
    public function set($list_set){
        foreach( $list_set as $field => $value )
        {
            $value = $this->filter_escape($value);
            $updates[] = "`$field` = '$value'";
        }
        return implode(', ', $updates);
    }

    // Public function to create semantics SET for INSERT
    public function insert($list_insert){

        $fields = array();
        $values = array();

        foreach( $list_insert as $field => $value )
        {
            $fields[] = "`".$field."`";
            $value = $this->filter_escape($value);
            $values[] = "'".$value."'";
        }
        $fields = ' ('.implode(', ',$fields).')';
        $values = '('.implode(', ',$values).')';

        return $fields .' VALUES '. $values;
    }

    // Public function to create semantics SELECT
    public function get_results($list_results,$table_conn,$list_condition,$list_order,$list_limit){

        // Write list process result
        $write_results = $this->listing($list_results);

        // Write WHERE conditions
        if (!empty($list_condition)) {
            $write_conditions = $this->where($list_condition);
        }else{$write_conditions = NULL;}

        // Write ORDER
        if (!empty($list_order)) {
            $write_order = $this->order($list_order);
        }else{$write_order = NULL;}

        // Write LIMIT
        if (!empty($list_limit)) {
            $write_limit = $this->limit($list_limit);
        }else{$write_limit = NULL;}

        // Make SELECT
        $result = $this->action("SELECT","SELECT $write_results FROM $table_conn $write_conditions $write_order $write_limit");

        // Save SELECT consult in get_sql
        $this->get_sql = "SELECT $write_results FROM $table_conn $write_conditions $write_order $write_limit";

        // Show debug log
        if (!is_null($this->get_debug_mode)) {
            $this->show_log("$this->get_sql \n\n");
        }

        // Show clean valor if SELECT not is all fields
        if($write_results != '*'){
            return array_shift(array_shift($result));
        }else{
            return $result;
        }

    }

    // Public function to create semantics SELECT INNER JOIN
    public function get_inner_results($list_results,$table_conn,$table_inner,$list_on_condition,$list_inner_condition,$list_order,$list_limit){

        $write_results = $this->listing($list_results);

        if (!empty($list_inner_condition)) {
            $write_inner_conditions = $this->where($list_inner_condition);
        }

        if (!empty($list_order)) {
            $write_order = $this->order($list_order);
        }else{$write_order = NULL;}

        if (!empty($list_limit)) {
            $write_limit = $this->limit($list_limit);
        }else{$write_limit = NULL;}

        $result = $this->action("SELECT","SELECT $write_results FROM $table_conn INNER JOIN $table_inner ON  ($list_on_condition) $write_inner_conditions $write_order $write_limit");
        $this->get_sql = "SELECT $write_results FROM $table_conn INNER JOIN $table_inner ON  ($list_on_condition) $write_inner_conditions $write_order $write_limit";
        if (!is_null($this->get_debug_mode)) {
            $this->show_log("$this->get_sql \n\n");
        }
        return $result;
    }

    // Public function to create semantics SELECT OUTER JOIN
    public function get_outer_results($list_results,$table_conn,$table_inner,$list_on_condition,$list_outer_condition,$list_order,$list_limit){

        $write_results = $this->listing($list_results);

        if (!empty($list_outer_condition)) {
            $write_outer_conditions = $this->where($list_outer_condition);
        }

        if (!empty($list_order)) {
            $write_order = $this->order($list_order);
        }else{$write_order = NULL;}

        if (!empty($list_limit)) {
            $write_limit = $this->limit($list_limit);
        }else{$write_limit = NULL;}

        $result = $this->action("SELECT","SELECT $write_results FROM $table_conn LEFT OUTER JOIN $table_inner ON  ($list_on_condition) $write_outer_conditions $write_order $write_limit");
        $this->get_sql = "SELECT $write_results FROM $table_conn LEFT OUTER JOIN $table_inner ON  ($list_on_condition) $write_outer_conditions $write_order $write_limit";
        if (!is_null($this->get_debug_mode)) {
            $this->show_log("$this->get_sql \n\n");
        }
        return $result;
    }

    // Public function to create semantics SELECT COUNT (*)
    public function get_count($table_conn,$list_condition){

        if (!empty($list_condition)) {
            $write_conditions = $this->where($list_condition);
        }

        $result = array_shift($this->action("SELECT","SELECT COUNT(*) FROM $table_conn $write_conditions"));
        $this->get_sql = "SELECT COUNT(*) FROM $table_conn $write_conditions";
        if (!is_null($this->get_debug_mode)) {
            $this->show_log("$this->get_sql \n\n");
        }
        return $result;
    }

    // Public function to create semantics SELECT SUM (*)
    public function get_sum($table_conn,$list_sum,$list_condition){

        if (!empty($list_condition)) {
            $write_conditions = $this->where($list_condition);
        }

        $result = array_shift($this->action("SELECT","SELECT sum($list_sum) FROM $table_conn $write_conditions"));
        $this->get_sql = "SELECT sum($list_sum) FROM $table_conn $write_conditions";
        if (!is_null($this->get_debug_mode)) {
            $this->show_log("$this->get_sql \n\n");
        }
        return $result;
    }

    // Public function to create semantics UPDATE
    public function set_update($table_conn,$list_set,$list_condition){

        $write_set = $this->set($list_set);

        if (!empty($list_condition)) {
            $write_conditions = $this->where($list_condition);
        }

        $result = $this->action("UPDATE","UPDATE $table_conn SET $write_set $write_conditions");
        $this->get_sql = "UPDATE $table_conn SET $write_set $write_conditions";
        if (!is_null($this->get_debug_mode)) {
            $this->show_log("$this->get_sql \n\n");
        }
        return $result;
    }

    // Public function to create semantics DELETE
    public function set_delete($table_conn,$list_condition){

        if (!empty($list_condition)) {
            $write_conditions = $this->where($list_condition);
        }

        $result = $this->action("DELETE","DELETE FROM $table_conn $write_conditions");
        $this->get_sql = "DELETE FROM $table_conn $write_conditions";
        if (!is_null($this->get_debug_mode)) {
            $this->show_log("$this->get_sql \n\n");
        }
        return $result;
    }

    // Public function to create semantics INSERT
    public function set_insert($table_conn,$list_insert,$list_condition){

        $write_insert = $this->insert($list_insert);

        if (!empty($list_condition)) {
            $write_conditions = $this->where($list_condition);
        }else{$write_conditions = NULL;}

        $result = $this->action("INSERT","INSERT INTO $table_conn $write_insert $write_conditions");
        $this->get_sql = "INSERT INTO $table_conn $write_insert $write_conditions";
        if (!is_null($this->get_debug_mode)) {
            $this->show_log("$this->get_sql \n\n");
        }
        return $result;
    }

    // Public function to create semantics SELECT EXIST
    public function exist($table_conn,$list_condition){

        if (!empty($list_condition)) {
            $write_conditions = $this->where($list_condition);
        }

        $result_query = $this->query("SELECT * FROM $table_conn $write_conditions");
        $this->get_sql = "SELECT * FROM $table_conn $write_conditions";

        if (!is_null($this->get_debug_mode)) {
            $this->show_log("$this->get_sql \n\n");
        }

        $exist_result = $result_query->num_rows;

        if ($exist_result) {
            return 'EXIST';
        }else{
            return NULL;
        }
    }

    //Función  de desconexión de CONN
    public function disconnect(){
        $execution->close;
    }

}