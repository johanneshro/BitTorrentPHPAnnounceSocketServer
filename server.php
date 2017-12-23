<?php
ob_implicit_flush();
set_time_limit(0);
ini_set('max_execution_time','0');

define("INTERVAL", 1800);
define("INTERVAL_MIN", 300);
define("CLIENT_TIMEOUT", 60);

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
$plugins_loaded = array();
$files = files();
$client = array();
$started = time();
$hits = 0;
$response = array();

$db = array();
$db["torrents"] = array();
$db["info_hash"] = array();


while($server["running"]) {
	$read[0] = $sock;

	for($i = 0; $i<$server["max_clients"]; $i++) {
		if(isset($client[$i]['sock']) && $client[$i]['sock'] != NULL && is_resource($client[$i]['sock']) && get_resource_type($client[$i]['sock']) == "Socket") {
			$read[$i+1] = $client[$i]['sock'];
		}
		elseif(isset($read[$i+1])) {
			unset($read[$i+1]);
		}
	}

	$ready = @socket_select($read, $write = NULL, $except = NULL, $tv_sec = NULL);

	if($ready === false)
		$log->msg("ERROR (".date("d.m.Y H:i:s",time())."): Can't select socket! Reason: ".socket_strerror($ready)."\r\n",true);

	if(in_array($sock, $read)) {
		for($i = 0; $i<$server["max_clients"]; $i++) {
			if(!isset($client[$i]['sock'])) {
				if(($client[$i]['sock'] = @socket_accept($sock)) < 0) {
					$log->msg("ERROR (".date("d.m.Y H:i:s",time())."): Can't accept socket! Reason: ".socket_strerror($client[$i]['sock'])."\r\n",true);
				} else {
					@socket_set_option($client[$i]['sock'], SOL_SOCKET, SO_REUSEADDR, 1);
					@socket_getpeername($client[$i]['sock'], $ip, $port);
					$log->msg("CLIENT (".date("d.m.Y H:i:s",time())."): New Client #".$i." connected: ".$ip.":".$port."\r\n");
					$hits++;
				}
				break;
			}
			elseif($i == $server["max_clients"] - 1)
				track_print($client[$i]['sock'], "Too many Clients!");
		}
		if(--$ready <= 0)
			continue;
	}

	for($i=0; $i<$server["max_clients"]; $i++) {
		if(isset($client[$i]['sock']) && in_array($client[$i]['sock'], $read)) {
			$input = @socket_read($client[$i]['sock'], 1024);
			if($input == null) {
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

			if(count($_GET) > 0) {
				$log_get = @fopen($logfile["directory"]."/".$logfile["request_pre"]."_".date("Y_m_d",time()).".".$logfile[extension],"a");
				if($log_get) {
					@fwrite($log_get,var_export($_GET,true)."\n");
					@fclose($log_get);
				}
			}

			if(isset($split_status[1]) && substr($split_status[1],0,6) == "/files") {
				$file = isset($_GET["file"]) ? $_GET["file"] : false;
				if(!$file) {
					$log->msg("CLIENT (".date("d.m.Y H:i:s",time())."): Request -> ! -> Output -> index.html\r\n");
					track_print($client[$i]['sock'], $files["index.html"], "text/html");
				} else {
					if(isset($files[$file])) {
						$log->msg("CLIENT (".date("d.m.Y H:i:s",time())."): Request -> ".$file." -> Output -> ".$file."\r\n");
						track_print($client[$i]['sock'], $files[$file], "text/html");
					} else {
						$log->msg("CLIENT (".date("d.m.Y H:i:s",time())."): Request -> ".$file." -> Output -> 404.html\r\n");
						track_print($client[$i]['sock'], $files["404.html"], "text/html");
					}
				}
			}elseif(isset($split_status[1]) && substr($split_status[1],0,7) == "/status") {
				track_print($client[$i]['sock'], "Started: ".date("d.m.Y H:i:s",$started)."\nHits: ".$hits."\nClients: ".count($client));
			}elseif(isset($split_status[1]) && substr($split_status[1],0,8) == "/clients") {
				$clients = array();
				foreach($client AS $key => $client_user) {
					if($client_user['sock'] != null) {
						@socket_getpeername($client_user['sock'], $client_ip, $client_port);
						$clients[] = $client_ip.":".$client_port;
					}
				}
				track_print($client[$i]['sock'], @implode("\n", $clients));
				unset($clients);
			}elseif(isset($split_status[1]) && substr($split_status[1],0,5) == "/kill") {
				if(trim($_GET["admin"]) == $server["admin"]) {
					track_print($client[$i]['sock'], "OK!");
					stop_server($sock, $client, $key);
				} else {
					track_print($client[$i]['sock'], "Kein Zugriff!");
				}
			}elseif(isset($split_status[1]) && substr($split_status[1],0,3) == "/db") {
				if(isset($_GET["admin"]) && trim($_GET["admin"]) == $server["admin"]) {
					$data = array();
					$z = 0;
					if(count($db["torrents"]) > 0) {
						foreach($db["torrents"] AS $info_hash) {
							if(count($db[$info_hash]) > 0) {
								foreach($db[$info_hash] AS $peer_id) {
									$map = $info_hash.":".$peer_id;
									if(isset($db[$map])) {
										$data[$z]["info_hash"] = bin2hex($info_hash);
										$data[$z]["peer_id"] = bin2hex($peer_id);
										$data[$z] = array_merge($data[$z], $db[$map]);
										$data[$z]["useragent"] = formatClient($data[$z]["useragent"]);
										$data[$z]["downloaded"] = formatBytes($data[$z]["downloaded"]);
										$data[$z]["uploaded"] = formatBytes($data[$z]["uploaded"]);
										$data[$z]["left"] = formatBytes($data[$z]["left"]);
										$data[$z]["date"] = formatUpdate(time()-$data[$z]["date"]);
										$z++;
									}
								}
							}
						}
					}

					if(isset($_GET["format"]) && trim($_GET["format"]) == "array") {
						$torrents = print_r($data, true);
					}elseif(isset($_GET["format"]) && trim($_GET["format"]) == "json") {
						$torrents = json_encode($data, true);
					}else{
						$torrents = new ArrayToTextTable($data);
						$torrents->showHeaders(true);
						$torrents = $torrents->render(true);
					}
					track_print($client[$i]['sock'], $torrents);
					unset($data);
				} else {
					track_print($client[$i]['sock'], "Kein Zugriff!");
				}
			}elseif(isset($split_status[1]) && substr($split_status[1],0,9) == "/announce") {
				// Tracker Begin
				$info_hash = checkGET("info_hash", true);
				$peer_id = checkGET("peer_id", true);
				$port = checkGET("port");
				$passkey = checkGET("passkey");
				$username = $nv->GetUsernameByPasskey($passkey);
				if(!$info_hash || !$peer_id || !$port) break;
				if(isset($port) && (!ctype_digit($port) || $port < 1 || $port > 65535)) {
					track_print($client[$i]['sock'], track("Invalid client port"));
					break;
				}
				$map = $info_hash.":".$peer_id;
				if(isset($_GET["event"]) && $_GET["event"] === "stopped") {
					unset($db[$map]);
					if(isset($db[$info_hash])) {
						if(in_array($peer_id, $db[$info_hash])) {
							delete_value($info_hash, $peer_id);
						}
					}
					if(count($db[$info_hash]) == 0) {
						if(in_array($info_hash, $db["torrents"])) {
							delete_value("torrents", $info_hash);
						}
						unset($db[$info_hash]);
					}
					$log->msg("CLIENT (".date("d.m.Y H:i:s",time())."): IP: ".$ip." Nick: " . $username . " -> Announce -> Peer_ID: ".$peer_id." -> ".$info_hash."\r\n");
					track_print($client[$i]['sock'], track(array()));
				} else {
					if(!array_key_exists($info_hash, $db)) {
						$db[$info_hash] = array();
					}
					if(!in_array($info_hash, $db["torrents"])) {
						$db["torrents"][] = $info_hash;
					}
					if(!in_array($peer_id, $db[$info_hash])) {
						$db[$info_hash][] = $peer_id;
					}
					$downloaded = (isset($_GET["downloaded"])) ? intval($_GET["downloaded"]) : 0;
					$uploaded = (isset($_GET["uploaded"])) ? intval($_GET["uploaded"]) : 0;
					$left = (isset($_GET["left"])) ? intval($_GET["left"]) : 0;
					$is_seed = ($left == 0) ? 1 : 0;
					$numwant = (isset($_GET["numwant"])) ? intval($_GET["numwant"]) : 50;
					$compact = (isset($_GET["compact"]) && intval($_GET["compact"]) == 1) ? true : false;
					$db[$map] = (isset($db[$map]) && count($db[$map]) > 0) ? array_replace($db[$map], array("ip" => $ip, "port" => $port, "seed" => $is_seed, "downloaded" => $downloaded, "uploaded" => $uploaded, "left" => $left, "date" => time(), "useragent" => $useragent)) : array("ip" => $ip, "port" => $port, "seed" => $is_seed, "downloaded" => $downloaded, "uploaded" => $uploaded, "left" => $left, "date" => time(), "useragent" => $useragent);
					$pid_list = $db[$info_hash];
					$peers = array();
					$count = $seeder = $leecher = 0;
					foreach($pid_list AS $pid) {
						if($pid == $peer_id)
							continue;
						$temp_map = $info_hash.":".$pid;
						$temp = $db[$temp_map];
						if(!$temp["ip"]) {
							if(in_array($pid, $db[$info_hash])) {
								delete_value($info_hash, $pid);
							}
						} else {
							if($temp["seed"]) {
								$seeder++;
							} else {
								$leecher++;
							}
							if($temp["seed"] && $is_seed) continue;
							if($count < $numwant) {
								$peers[$pid] = array("ip" => $temp["ip"], "port" => $temp["port"]);
								$count++;
							}
						}
					}
					if($is_seed) {
						$seeder++;
					} else {
						$leecher++;
					}
					
					$log->msg("CLIENT (".date("d.m.Y H:i:s",time())."): IP: ".$ip." Nick: " . $username . " -> Announce -> Peer_ID: ".$peer_id." -> ".$info_hash."\r\n");
					track_print($client[$i]['sock'], track($peers, $seeder, $leecher, $compact));
					unset($peers);
				}
			// Tracker Ende
			}elseif(isset($split_status[1]) && substr($split_status[1],0,9) == "/scrape"){
				// scrape
				/*
				$r = "d" . benc_str("files") . "d";
				$r .= "20:" . hash_pad($row["info_hash"]) . "d" .
					benc_str("complete") . "i" . $row["seeders"] . "e" .
					benc_str("downloaded") . "i" . $row["times_completed"] . "e" .
					benc_str("incomplete") . "i" . $row["leechers"] . "e" .
					"e";
				$r .= "ee";
				*/
				//
			} else {
				if(isset($client[$i]['sock'])) {
					track_print($client[$i]['sock'], "BitTorrent PHP Announce Socket Server");
				}
			}
		} else {
			if(isset($client[$i]['sock'])) {
				@socket_close($client[$i]['sock']);
				unset($client[$i]['sock']);
				unset($client[$i]);
				$log->msg("CLIENT (".date("d.m.Y H:i:s",time())."): Disconnected Client #".$i."\r\n");
			}
		}
	}
}
@socket_close($sock);
?>

?>