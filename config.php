<?php

// Server

$server["ip"] = "localhost";
$server["port"] = 81;
$server["running"] = true;
$server["admin"] = "sasser";
$server["max_clients"] = 50;

// Datenbank (MySQL)

$db["server"] = "";
$db["user"] = "";
$db["passwd"] = "";
$db["name"] = "";

// Log Files

$logfile["directory"] = "logs";
$logfile["server_pre"] = "server";
$logfile["request_pre"] = "request";
$logfile["extension"] = "txt";

// Mini-WebServer

$extern["directory"] = "files";
$extern["extensions"] = "html|htm|txt";

// Tracker

$tracker["maxips"] = "3";
$tracker["torrent_max_leechers"] = "1";
$tracker["torrent_max_seeders"] = "3";

// PlugIns

$plugins["floodprotect"] = false;
$plugins["clientban"] = false;

// Flood-Protection

$floodprotect["maxConPerMin"] = "100";
$floodprotect["bantime"] = "60";

?>