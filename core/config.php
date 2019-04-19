<?php
$config_server = array();
// ip oder domain ohne http://
$config_server["ip"] = "localhost";
$config_server["port"] = 81;
$config_server["max_clients"] = 50;
$db_info = array( 
	"db_host" => "localhost", 
	"db_port" => "3306",
	"db_user" => "root",
	"db_pass" => "",
	"db_name" => "nvtracker",
	"db_charset" => "UTF-8"
);
$dsn = ["mysql:host=".$db_info['db_host'].';port='.$db_info['db_port'].';dbname='.$db_info['db_name'],$db_info['db_user'], $db_info['db_pass']];
?>