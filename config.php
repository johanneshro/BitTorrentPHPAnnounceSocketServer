<?php

/*
// +--------------------------------------------------------------------------+
// | Project:    pdonvtracker - NetVision BitTorrent Tracker 2019             |
// +--------------------------------------------------------------------------+
// | This file is part of pdonvtracker. NVTracker is based on BTSource,       |
// | originally by RedBeard of TorrentBits, extensively modified by           |
// | Gartenzwerg.                                                             |
// +--------------------------------------------------------------------------+
// | Obige Zeilen dürfen nicht entfernt werden!    Do not remove above lines! |
// +--------------------------------------------------------------------------+
 */

// PHP Settings
ob_implicit_flush();
//set_time_limit(0);
date_default_timezone_set('Europe/Berlin');
ini_set('date.timezone', 'Europe/Berlin');
ini_set('max_execution_time','0');

cli_set_process_title("NetVision-Announce");

$config_server = array();
// IP des SocketServers
//$config_server["ip"] = "127.0.0.1";
$config_server["ip"] = "[::1]";
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
	"db_charset" => "utf8"
);
//$dsn = ["mysql:host=".$db_info['db_host'].';port='.$db_info['db_port'].';dbname='.$db_info['db_name'],$db_info['db_user'], $db_info['db_pass']];
?>