<?php
ob_implicit_flush();
//set_time_limit(0);
ini_set('max_execution_time','0');
cli_set_process_title("NetVision-Announce");
include("core/config.php");
include("core/server.class.php");
include("core/client.class.php");
include("core/input.class.php");
include("core/response.class.php");
include("core/db.class.php");
include("core/nv.class.php");
include("core/runtime.class.php");
include("core/logging.class.php");
$database = new db($dsn);
$pdo = $database->getPDO();
if(!$database)
	$server["running"] = false;
$nv = new nv($pdo);
$nv->SetTrackerPath("localhost");
$nv->GetNvConfig();
$server = new SocketServer($config_server["ip"],$config_server["port"]);
//SocketServer::set_debugging(false);
$server->set_max_clients($config_server["max_clients"]);
$server->infinite_loop();
?>