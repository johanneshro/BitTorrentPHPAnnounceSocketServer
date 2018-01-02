<?php
ob_implicit_flush();
set_time_limit(0);
ini_set('max_execution_time','0');

define("INTERVAL", 1800);
define("INTERVAL_MIN", 300);
define("CLIENT_TIMEOUT", 60);


/*
include("core/server.class.php");
$server = new SocketServer(null,9001); // Binds to determined IP
$server->max_clients();
$server->hook("connect","connect_function"); // On connect does connect_function($server,$client,"");
$server->hook("disconnect","disconnect_function"); // On disconnect does disconnect_function($server,$client,"");
$server->hook("input","handle_input"); // When receiving input does handle_input($server,$client,$input);
$server->infinite_loop(); // starts the loop.
*/

include("core/config.php");
include("core/functions.php");
//include("core/benc.php");
include("core/log.class.php");
include("core/db.class.php");
include("core/nv.class.php");
include("core/arraytotexttable.php");

$database = new db($dsn);
$pdo = $database->getPDO();

if(!$database)
	$server["running"] = false;

$log = new log($logfile);

// Von hier aus holen wir die daten aus der nv-source
$nv = new nv($pdo);
$nv->SetTrackerPath("localhost");

$sock = start_server();
$client = array();
$started = time();
$hits = 0;
$response = array();

$db = array();
$db["torrents"] = array();
$db["info_hash"] = array();


while($server["running"]){
	$read[0] = $sock;

	for($i = 0; $i<$server["max_clients"]; $i++) {
		if(isset($client[$i]['sock']) && $client[$i]['sock'] != NULL && is_resource($client[$i]['sock']) && get_resource_type($client[$i]['sock']) == "Socket") {
			$read[$i+1] = $client[$i]['sock'];
		}elseif(isset($read[$i+1])) {
			unset($read[$i+1]);
		}
	}

	$ready = @socket_select($read, $write = NULL, $except = NULL, $tv_sec = NULL);

	if($ready === false)
		$log->msg("e", "Can't select socket! Reason: ".socket_strerror($ready)."\r\n",true);

	if(in_array($sock, $read)) {
		for($i = 0; $i<$server["max_clients"]; $i++) {
			if(!isset($client[$i]['sock'])) {
				if(($client[$i]['sock'] = @socket_accept($sock)) < 0) {
					$log->msg("e", "Can't accept socket! Reason: ".socket_strerror($client[$i]['sock'])."\r\n",true);
				} else {
					@socket_set_option($client[$i]['sock'], SOL_SOCKET, SO_REUSEADDR, 1);
					@socket_getpeername($client[$i]['sock'], $ip, $port);
					//$ip = $nv->GetIP();
					// silence
					//$log->msg("c", "Client #".$i." verbunden: ".$ip.":".$port."\r\n");
					$hits++;
				}
				break;
			}
			elseif($i == $server["max_clients"] - 1)
				track_print($client[$i]['sock'], "Zu viele Clients!");
		}
		if(--$ready <= 0)
			continue;
	}

	//hauptschleife
	for($i=0; $i<$server["max_clients"]; $i++){
		if(isset($client[$i]['sock']) && in_array($client[$i]['sock'], $read)){
			$input = @socket_read($client[$i]['sock'], 1024);
			if($input == null){
				@socket_close($client[$i]['sock']);
				unset($client[$i]['sock']);
				unset($client[$i]);
			}
	
			$input = trim($input);
			$split = explode("\n", $input);
			preg_match("=([a-z]{1,} /)(.*)( http/1.[01]{1})=i", $split[0], $temp);
			$split_2 = http_parse_headers($input);
			$split_status = explode(" ", $split_2["status"]);
			$_GET = parse_query_string($split_status[1]);
			$method = (isset($split_status[0]) && !empty($split_status[0])) ? trim($split_status[0]) : "GET";
			$useragent = (isset($split_2["User-Agent"]) && !empty($split_2["User-Agent"])) ? trim($split_2["User-Agent"]) : "";
			if($method != "GET")
				track_print($client[$i]['sock'], "Fertig!");

			// anfrage loggen
			if(count($_GET) > 0){
				$log_get = @fopen($logfile["directory"]."/".$logfile["request_pre"]."_".date("Y_m_d",time()).".".$logfile["extension"],"a");
				if($log_get) {
					@fwrite($log_get,var_export($_GET,true)."\n");
					@fclose($log_get);
				}
			}

			if(isset($split_status[1]) && substr($split_status[1],0,9) == "/announce"){
				socket_set_block($sock);
				/////////////// Tracker Begin
				$info_hash = checkGET("info_hash", true);
				$peer_id = checkGET("peer_id", true);
				$port = checkGET("port");
				$passkey = checkGET("passkey");

				$user_info = $nv->GetUserDataByPasskey($passkey);
				if($user_info === false){
					track_print($client[$i]['sock'], track("Diesem Passkey ist kein Nutzer zugeordnet."));
					break;
				}
				/*
				$user_info['id']
				$user_info['username']
				*/

				$torrent_info = $nv->GetTorrentDataByInfohash($info_hash);
				if($torrent_info === false){
					track_print($client[$i]['sock'], track("Dieser Torrent ist dem Tracker nicht bekannt."));
					break;
				}
				/*
				$torrent_info['id']
				$torrent_info['name']
				$torrent_info['banned']
				$torrent_info['activated']
				$torrent_info['numpeers']
				$torrent_info['ts'] // added
				*/

				if(!$info_hash || !$peer_id || !$port){
					track_print($client[$i]['sock'], track("Unzureichende Daten."));
					break;
				}

				if(isset($port)){
					if(!ctype_digit($port) || $port < 1 || $port > 65535){
						track_print($client[$i]['sock'], track("Ungueltiger Port."));
						break;
					}
					if($nv->portblacklisted($port)){
						track_print($client[$i]['sock'], track("Der TCP-Port " . $port . " ist nicht erlaubt."));
						break;
					}
				}

				if(isset($_GET["event"]) && $_GET["event"] === "stopped"){
					$nv->DeletePeer($torrent_info["id"], $ip);
					$log->msg("c", $user_info["username"]." (".$user_info["id"].")(".$ip.")(".$peer_id.") getrennt. ".$torrent_info["name"]." (".$torrent_info["id"].")(".$info_hash.")\r\n");
					track_print($client[$i]['sock'], track(array()));
				//}elseif(isset($_GET["event"]) && $_GET["event"] === "completed"){
				}else{
					$downloaded = (isset($_GET["downloaded"])) ? intval($_GET["downloaded"]) : 0;
					$uploaded = (isset($_GET["uploaded"])) ? intval($_GET["uploaded"]) : 0;
					$left = (isset($_GET["left"])) ? intval($_GET["left"]) : 0;
					$is_seed = ($left == 0) ? 1 : 0;
					$numwant = (isset($_GET["numwant"])) ? intval($_GET["numwant"]) : 50;
					$compact = (isset($_GET["compact"]) && intval($_GET["compact"]) == 1) ? true : false;

					$nv->InsertPeer($torrent_info["id"], $peer_id, $ip, $port, $uploaded, $downloaded, $left, $user_info["id"], $useragent);
					$pdata = $nv->GetPeers($torrent_info["id"], $numwant, $ip);
					
					$log->msg("c", $user_info["username"]." (".$user_info["id"].")(".$ip.")(".$peer_id.") verbunden. ".$torrent_info["name"]." (".$torrent_info["id"].")(".$info_hash.")\r\n");
					track_print($client[$i]['sock'], track($pdata["peers"], $pdata["seeders"], $pdata["leechers"], $compact));
					unset($pdata);
				}
			// Tracker Ende
				socket_set_nonblock($sock);
			}elseif(isset($split_status[1]) && substr($split_status[1],0,7) == "/scrape"){
				track_print($client[$i]['sock'], $nv->GetScrapeString(checkGET("info_hash", true)));
			}elseif(isset($split_status[1]) && substr($split_status[1],0,7) == "/status"){
				track_print($client[$i]['sock'], "Started: ".date("d.m.Y H:i:s",$started)."\nHits: ".$hits."\nClients: ".count($client));
			}elseif(isset($split_status[1]) && substr($split_status[1],0,5) == "/kill"){
				if(trim($_GET["admin"]) == $server["admin"]) {
					track_print($client[$i]['sock'], "OK!");
					stop_server($sock, $client, $key);
				} else {
					track_print($client[$i]['sock'], $nv->fakefourzerofour());
				}
			}else{
				if(isset($client[$i]['sock'])) {
					track_print($client[$i]['sock'], $nv->getlandingpage());
				}
			}
		} else {
			if(isset($client[$i]['sock'])) {
				@socket_close($client[$i]['sock']);
				unset($client[$i]['sock']);
				unset($client[$i]);
				// silence
				//$log->msg("c", "Client #".$i." getrennt.\r\n");
			}
		}
	}
}
@socket_close($sock);
?>