<?php

$server = array();
$logfile = array();
$extern = array();

// Server
$server["ip"] = "localhost";
$server["port"] = 81;
$server["running"] = true;
$server["admin"] = "admin";
$server["max_clients"] = 50;

// Log Files
$logfile["directory"] = "logs";
$logfile["server_pre"] = "server";
$logfile["request_pre"] = "request";
$logfile["extension"] = "txt";

// mysql
$db_info = array( 
	"db_host" => "localhost", 
	"db_port" => "3306",
	"db_user" => "root",
	"db_pass" => "",
	"db_name" => "nvtracker",
	"db_charset" => "UTF-8"
);
$dsn = ["mysql:host=".$db_info['db_host'].';port='.$db_info['db_port'].';dbname='.$db_info['db_name'],$db_info['db_user'], $db_info['db_pass']];

// Mini-WebServer
$extern["directory"] = "files";
$extern["extensions"] = "html|htm|txt";

?>