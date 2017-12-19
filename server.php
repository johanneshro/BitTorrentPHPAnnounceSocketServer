<?php
ob_implicit_flush();
set_time_limit(0);
ini_set('max_execution_time','0');

include("config.php");

// mysql
$db_connect = @mysql_pconnect($db["server"],$db["user"],$db["passwd"]);
@mysql_select_db($db["name"],$db_connect);

if(!$db_connect)
	$server["running"] = false;

include("functions.php");
require_once("classes.php");

$log = new log($logfile);
$sock = start_server();
$plugins_loaded = array();
$include = load_plugins();
$files = files();
$client = array();
$started = time();
$hits = 0;
$response = array();

while($server["running"]) {

	$read[0] = $sock;

	for($i = 0; $i<$server["max_clients"]; $i++) {

		if($client[$i]['sock'] != NULL && is_resource($client[$i]['sock']) && get_resource_type($client[$i]['sock']) == "Socket") {
			$read[$i+1] = $client[$i]['sock'];
		}
		elseif(isset($read[$i+1])) {
			unset($read[$i+1]);
		}

	}

	$ready = @socket_select($read, $write = NULL, $except = NULL, $tv_sec = NULL);

	if($ready === false) $log->msg("ERROR (".date("d.m.Y H:i:s",time())."): Can't select socket! Reason: ".socket_strerror($ready)."\r\n",true);

	if(in_array($sock, $read)) {

		for($i = 0; $i<$server["max_clients"]; $i++) {

			if($client[$i]['sock'] == null) {

				if(($client[$i]['sock'] = @socket_accept($sock)) < 0) {

					$log->msg("ERROR (".date("d.m.Y H:i:s",time())."): Can't accept socket! Reason: ".socket_strerror($client[$i]['sock'])."\r\n",true);

				} else {

					@socket_set_option($client[$i]['sock'], SOL_SOCKET, SO_REUSEADDR, 1);
					@socket_getpeername($client[$i]['sock'], $getip, $getport);

					$log->msg("\r\nCLIENT (".date("d.m.Y H:i:s",time())."): New Client #".$i." connected: ".$getip.":".$getport);

					$hits++;

				}
				break;
			}
			elseif($i == $server["max_clients"] - 1) {
				benc_resp_raw($client[$i]['sock'],"Too many Clients!");
			}

		}

if(--$ready <= 0)
continue;

	}

	for($i=0; $i<$server["max_clients"]; $i++) {

		if(in_array($client[$i]['sock'], $read)) {

			$input = @socket_read($client[$i]['sock'], 1024);

			if($input == null) {
				@socket_close($client[$i]['sock']);
				unset($client[$i]['sock']);
				unset($client[$i]);
			}

			$input = trim($input);

			$split = split("\n",$input);
			preg_match("=([a-z]{1,} /)(.*)( http/1.[01]{1})=i",$split[0],$temp);

			$split_2 = http_parse_headers($input);
			$split_status = explode(" ",$split_2["status"]);

			$_GET = parse_query_string($split_status[1]);
			$method = trim($split_status[0]);
			$useragent = trim($split_2["User-Agent"]);

			if($method != "GET")
				benc_resp_raw($client[$i]['sock'],"Fertig!");

			ctracker($client[$i]['sock'],$split_status[1]);

			if($plugins_loaded["floodprotect"]) {

				$protect = new FloodProtect();
				$protect -> maxConPerMin = $floodprotect["maxConPerMin"];
				$protect -> bantime      = $floodprotect["bantime"];
				$protect -> check();

			}

			if(count($_GET) > 0) {

				$log_get = @fopen($logfile["directory"]."/".$logfile["request_pre"]."_".date("Y_m_d",time()).".".$logfile[extension],"a");

				if($log_get) {
					@fwrite($log_get,var_export($_GET,true)."\n");
					@fclose($log_get);
				}

			}

			if(substr($split_status[1],0,6) == "/files") {

				$file = $_GET[file];

				if(!$file) {

					$log->msg("\r\nCLIENT (".date("d.m.Y H:i:s",time())."): Request -> ! -> Output -> index.html");
					benc_resp_raw($client[$i]['sock'],$files["index.html"]);

				} else {

					if(count($files[$file]) > 0) {

						$log->msg("\r\nCLIENT (".date("d.m.Y H:i:s",time())."): Request -> ".$file." -> Output -> ".$file);
						benc_resp_raw($client[$i]['sock'],$files[$file]);

					} else {

						$log->msg("\r\nCLIENT (".date("d.m.Y H:i:s",time())."): Request -> ".$file." -> Output -> 404.html");
						benc_resp_raw($client[$i]['sock'],$files["404.html"]);

					}

				}

			}
			elseif(substr($split_status[1],0,7) == "/status") {

				benc_resp_raw($client[$i]['sock'],"Started: ".date("d.m.Y H:i:s",$started)."<br>Hits: ".$hits."<br>Clients: ".count($client));

			}
			elseif(substr($split_status[1],0,7) == "/update") {

				if(trim($_GET["admin"]) == $server["admin"]) {

					$is_update = checkupdate();

					if(!$is_update) {
						$is_update = "Version: ".release()."<br>Es stehen keine Updates zur Verfügung!<br>Das Produkt ist auf dem neuesten Stand!";
					}

					benc_resp_raw($client[$i]['sock'],$is_update);

				} else {
					benc_resp_raw($client[$i]['sock'],"Kein Zugriff!");
				}

			}
			elseif(substr($split_status[1],0,8) == "/clients") {

				foreach($client as $key => $client_user) {

					if($client_user['sock'] != null) {
						@socket_getpeername($client_user['sock'],$client_ip,$client_port);
						$client_list .= "<br>".$client_ip.":".$client_port;
					}

				}

				benc_resp_raw($client[$i]['sock'],"Client List:".$client_list);

				unset($client_list);

			}
			elseif(substr($split_status[1],0,8) == "/plugins") {

				if(count($plugins_loaded) > 0) {

					foreach($plugins_loaded as $plugin_loaded) {
						$plugin_list .= "<br>".$plugin_loaded;
					}

				} else {
					$plugin_list = "<br>Keine";
				}

				foreach($plugins as $plug_value => $plug_ins) {
					$plugins_list .= "<br>".$plug_value;
				}

				benc_resp_raw($client[$i]['sock'],"Geladene Plugins:".$plugin_list."<br><br>Verfügbare Plugins: ".$plugins_list);

				unset($plugin_list);
				unset($plugins_list);

			}
			elseif(substr($split_status[1],0,10) == "/clientban") {

				if($plugins_loaded["clientban"]) {

					$client_whitelist = array();
					$client_blacklist = array();

					$client_output = "<table>
					<tr valign=\"top\">
					<td><b>Erlaubte Clients</b></td><td><b>Gebannte Clients</b></td>
					</tr>\n";

					// mysql
        			$query_clients = mysql_query("SELECT * FROM agents ORDER BY agent_name DESC");

        			while($result_clients = mysql_fetch_array($query_clients)) {
            			$client_list[] = $result_clients;
        			}

        			foreach($client_list as $clientlist) {

        				if($clientlist["aktiv"] > 0) {
        					$client_whitelist[] = $clientlist;
        				} else {
        					$client_blacklist[] = $clientlist;
        				}

        			}

        			$client_output .= "<tr valign=\"top\">
					<td>".@implode("<br>\n",$client_whitelist)."</td><td>".@implode("<br>\n",$client_blacklist)."</td>
					</tr>
					</table>";

					unset($client_output);

				} else {
					benc_resp_raw($client[$i]['sock'],"Plugin nicht geladen!");
				}

				benc_resp_raw($client[$i]['sock'],"Client List:".$client_list);

			}
			elseif(substr($split_status[1],0,5) == "/kill") {

				if(trim($_GET["admin"]) == $server["admin"])
					stop_server($sock,$client,$key);
				else
					benc_resp_raw($client[$i]['sock'],"Kein Zugriff!");

			}
			elseif(substr($split_status[1],0,7) == "/scrape") {

				$info_hash = $_GET["info_hash"];
				$passkey = $_GET["passkey"];

				if(preg_match("/^Mozilla/", $useragent) || preg_match("/^MSIE/", $agent) || preg_match("/^Opera/", $useragent) || preg_match("/^Links/", $useragent) || preg_match("/^Lynx/", $useragent) || preg_match("/^curl/", $useragent) || preg_match("/^php/", $useragent)) {
					benc_resp_raw($client[$i]['sock'],"Du benutzt einen ungültigen Client!");
				}
				elseif(checkpasskey($passkey) == "0")
					err($client[$i]['sock'], "Ungueltiger PassKey (".strlen($passkey)." - $passkey). Re-Download the .torrent");

				$fields = "name, info_hash, times_completed, seeders, leechers";

				// mysql
				if(!isset($info_hash))
					$query = mysql_query("SELECT $fields FROM torrents ORDER BY 'info_hash'");
				else
					$query = mysql_query("SELECT $fields FROM torrents WHERE info_hash='".mysql_real_escape_string($info_hash)."' LIMIT 1");

				$r = "d" . benc_str("files") . "d";

				while($result = mysql_fetch_assoc($query)) {

					$r .= "20:" . hash_pad($result["info_hash"]) . "d" .benc_str("complete") . "i" . $result["seeders"] . "e" .benc_str("downloaded") . "i" . $result["times_completed"] . "e" .benc_str("incomplete") . "i" . $result["leechers"] . "e" . benc_str("name") . benc_str($result["name"]) . "e";

					$log->msg("\r\nCLIENT (".date("d.m.Y H:i:s",time())."): Scrape -> ".$result["name"]);

				}

				$r .= "ee";

				benc_resp_raw($client[$i]['sock'],$r,"text/plain");

				unset($r);

			}
			elseif(substr($split_status[1],0,9) == "/announce") {

				$ip = $getip;
				$port = $getport;

				// Tracker Begin

				if(preg_match("/^Mozilla/", $useragent) || preg_match("/^MSIE/", $agent) || preg_match("/^Opera/", $useragent) || preg_match("/^Links/", $useragent) || preg_match("/^Lynx/", $useragent) || preg_match("/^curl/", $useragent) || preg_match("/^php/", $useragent)) {
					benc_resp_raw($client[$i]['sock'],"Du benutzt einen ungültigen Client!");
				}
				elseif($plugins_loaded["clientban"])
					new UserAgent($useragent);

				foreach(array("passkey","info_hash","peer_id","port","downloaded","uploaded","left") as $x) {
					if(!isset($_GET[$x])) err($client[$i]['sock'], $client[$i]['sock'],"Fehlender Parameter fuer Announce: $x");
				}

				foreach(array("info_hash","peer_id") as $x) {
					if(strlen(hash_pad(hex_esc($_GET[$x]))) != 20) err($client[$i]['sock'], $client[$i]['sock'],"Ungueltiger Wert fuer $x (" . strlen(hash_pad(hex_esc($_GET[$x]))) . " - " . rawurlencode(hash_pad(hex_esc($_GET[$x]))) . ")");
				}

				foreach(array("info_hash","peer_id","event","ip","localip","ipv6","passkey","key") as $x) {
					$GLOBALS[$x] = "" .$_GET[$x];
				}

				foreach(array("port","downloaded","uploaded","left","compact","no_peer_id") as $x) {
					$GLOBALS[$x] = 0 + $_GET[$x];
				}

				if(checkpasskey($passkey) == "0")
					err($client[$i]['sock'], $client[$i]['sock'], "Ungueltiger PassKey (".strlen($passkey)." - $passkey). Re-Download the .torrent");
				elseif(!$port || $port > 0xffff)
					err($client[$i]['sock'], $client[$i]['sock'], "Ungueltiger TCP-Port");
				elseif(portblacklisted($port))
					err($client[$i]['sock'], $client[$i]['sock'], "Der TCP-Port $port ist nicht erlaubt.");

				$time = time();
				$ip = $getip;
				$host = gethostbyaddr($ip);
				$nick = nickfrompasskey($passkey);
				$announce_interval = 60*20;
				$rsize = 50;

				// mysql
				$query = mysql_query("SELECT id, name, seeders + leechers AS numpeers, added AS ts FROM torrents WHERE info_hash='".mysql_real_escape_string($info_hash)."' LIMIT 1");
				$row = mysql_num_rows($query);
				$result = mysql_fetch_array($query);

				$torrent_id = $result[id];
				$torrent_name = $result[name];

				check_ip_limit();

				if($row == "0")
					err($client[$i]['sock'], "Dieser Torrent ist dem Tracker nicht bekannt!");

				$port = 0 + $port;
				$downloaded = 0 + $downloaded;
				$uploaded = 0 + $uploaded;
				$downspeed = 0 + $downspeed;
				$upspeed = 0 + $upspeed;
				$left = 0 + $left;
				$seeder = ($left == 0) ? "yes" : "no";
				$updateset = array();

				if(!isset($event)) $event = "";

				if(!checkconnect($ip, $port)) {
					$connectable = "no";
					$active = "0";
				} else {
					$connectable = "yes";
					$active = "1";
				}

				if($connectable == "no")
					err($client[$i]['sock'], "Du darfst nicht saugen da du nicht erreichbar bist!");

				foreach(array("num want", "numwant", "num_want") as $k) {

					if(isset($_GET[$k])) {
						$rsize = 0 + $_GET[$k];
						break;
					}

				}

				if(trim($response[$torrent_id]) == "" || $event == "started" || $event == "stopped") {

					unset($response[$torrent_id]);

					$limit = "ORDER BY RAND() LIMIT $rsize";
					$fields = "seeder, peer_id, ip, port, uploaded, downloaded, nick";

					// mysql
					$query = mysql_query("SELECT $fields FROM peers WHERE torrent='$torrent_id' AND active='1' AND connectable='yes' $limit");
					$row = mysql_num_rows($query);

					if($compact != 1) {
						$resp = "d" . benc_str("interval") . "i" . $announce_interval . "e" . benc_str("private") . 'i1e' . benc_str("peers") . "l";
					} else {
						$resp = "d" . benc_str("interval") . "i" . $announce_interval . "e" . benc_str("min interval") . "i" . 300 ."e5:" . "peers";
					}

					$peer = array();
					$peer_num = 0;
					unset($self);

					// mysql
					while($result = mysql_fetch_assoc($query)) {

						$result["peer_id"] = hash_pad($result["peer_id"]);

						if($result["peer_id"] == $peer_id) {
							$self = $result;
							continue;
						}

						if($compact != 1) {

							$resp .= "d" .benc_str("ip") . benc_str($result["ip"]);
							if(!$_GET['no_peer_id']) $resp .= benc_str("peer id") . benc_str($result["peer_id"]);
							$resp .= benc_str("port") . "i" . $result["port"] . "e" . "e";

						} else {

							$peer_ip = explode('.', $result["ip"]);
							$peer_ip = pack("C*", $peer_ip[0], $peer_ip[1], $peer_ip[2], $peer_ip[3]);
							$peer_port = pack("n*", (int)$result["port"]);
							$time2 = intval(($time % 7680) / 60);

							if($left == "0") $time2 += 128;

							$time2 = pack("C", $time2);

							$peer[] = $time2 . $peer_ip . $peer_port;

							$peer_num++;

						}

					}

					if($compact != 1) {
						$resp .= "ee";
					} else {

						for($j=0; $j<$peer_num; $j++) {
							$o .= substr($peer[$j], 1, 6);
						}

						$resp .= strlen($o) . ":" . $o . "e";

					}

				} else {
					$resp = $response[$torrent_id];
				}

				if($event == "stopped") {

					// Stopped -> DELETE

				}
				elseif($event == "completed") {

					// Completed -> UPDATE

				} else {

					if(isset($self)) {

						// UPDATE

					} else {

						if($event != "started") {
							err($client[$i]['sock'], "Peer nicht gefunden! Restart the Torrent.");
						}

						// INSERT

					}

				}

				$log->msg("\r\nCLIENT (".date("d.m.Y H:i:s",time())."): Nickname: ".$nick." -> Announce -> Event: ".$event." -> ".$torrent_name);

				benc_resp_raw($client[$i]['sock'],$resp,"text/plain");

				$response[$torrent_id] = $resp;

				unset($resp);

			// Tracker Ende

			} else {

				benc_resp_raw($client[$i]['sock'],"Scream Labs PHP BitTorrent Announce Socket Server ".release()."<br>Copyright 2011-".date("Y")." <a href=\"mailto:stifler@chello.at\">Johannes Hrovat</a>, <a href=\"http://www.screamlabs.at\" target=\"_blank\">www.screamlabs.at</a>");

			}

		} else {

			if($client[$i]['sock'] != null) {

				@socket_close($client[$i]['sock']);
				unset($client[$i]['sock']);
				unset($client[$i]);

				$log->msg("\r\nCLIENT (".date("d.m.Y H:i:s",time())."): Disconnected Client #".$i);

			}

		}
	}
}

@socket_close($sock);

?>