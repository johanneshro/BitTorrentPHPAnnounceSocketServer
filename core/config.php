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

// Mini-WebServer
$extern["directory"] = "files";
$extern["extensions"] = "html|htm|txt";

?>