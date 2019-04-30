<?php
$config_server = array();
// ip oder domain ohne http:// des SocketServers
$config_server["ip"] = "localhost";
$config_server["port"] = 81;

// Url des Trackers ohne http://
$config_server["trackerurl"] = "localhost";
$config_server["trackerport"] = 80;

$config_server["max_clients"] = 50;

// Datenbankeinstellungen
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