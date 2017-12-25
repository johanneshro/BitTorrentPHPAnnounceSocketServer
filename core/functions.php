<?php

function start_server() {
	global $server, $log;
	//$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Initializing...\r\n");
	$sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if(!$sock)
		$log->msg("e", "Can't create socket! Reason: ".socket_strerror($sock)."\r\n",true);
	@socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
	$bind = @socket_bind($sock, $server["ip"], $server["port"]);
	if(!$bind)
		$log->msg("e", "Can't bind socket to given address! Reason: ".socket_strerror($bind)."\r\n",true);
	$listen = @socket_listen($sock, $server["max_clients"]);
	if(!$listen)
		$log->msg("e", "Can't listen on socket! Reason: ".socket_strerror($listen)."\r\n",true);
	@socket_set_nonblock($sock);
	$server["running"] = true;
	//$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Initialization OK!\r\n");
	//$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Server listening on ".$server["ip"].":".$server["port"]."...\r\n");
	$log->msg("s", "Server gestartet (".$server["ip"].":".$server["port"].")\r\n");
	return $sock;
}

function stop_server($sock) {
	global $server, $log, $client;
	$server["running"] = false;
	$log->msg("s", "Beende server..\r\n");
	foreach($client AS $key => $clients) {
		@socket_close($clients['sock']);
		unset($clients);
		$log->msg("c", "Client #".$key." getrennt.\r\n");
	}
	@socket_shutdown($sock);
	@socket_close($sock);
	$log->msg("s", "Server wurde beendet.\r\n");
}

function delete_value($array, $value) {
	global $db;
	if(($key = array_search($value, $db[$array])) !== false) {
		unset($db[$array][$key]);
	}
	return true;
}

function track($list, $complete=0, $incomplete=0, $compact=false) {
	global $_GET;
	if(is_string($list)) {
		return "d14:failure reason".strlen($list).":".$list."e";
	}
	$peers = $peers6 = array();
	foreach($list AS $peer_id => $peer) {
		if($compact) {
			$longip = ip2long($peer["ip"]);
			if($longip) {
				$peers[] = pack("Nn", sprintf("%d", $longip), $peer["port"]);
			} else {
				$peers6[] = pack("H32n", $peer["ip"], $peer["port"]);
			}
		} else {
			$pid = (!isset($_GET["no_peer_id"])) ? "7:peer id".strlen($peer_id).":".$peer_id : "";
			$peers[] = "d2:ip".strlen($peer["ip"]).":".$peer["ip"].$pid."4:porti".$peer["port"]."ee";
		}
	}
	$peers = (count($peers) > 0) ? @implode($peers) : "";
	$peers6 = (count($peers6) > 0) ? @implode($peers6) : "";
	$response = "d8:intervali".INTERVAL."e12:min intervali".INTERVAL_MIN."e8:completei".$complete."e10:incompletei".$incomplete."e5:peers".($compact ? strlen($peers).":".$peers."6:peers6".strlen($peers6).":".$peers6 : "l".$peers."e")."e";
	return $response;
}

function checkGET($key, $strlen_check=false){
	global $_GET, $client, $i;
	if(isset($client[$i]['sock'])){
		if(!isset($_GET[$key])){
			track_print($client[$i]['sock'], track("Missing key: " . $key));
			return false;
		}elseif(!is_string($_GET[$key])){
			track_print($client[$i]['sock'], track("Invalid types on one or more arguments"));
			return false;
		}elseif($strlen_check && strlen($_GET[$key]) != 20){
			track_print($client[$i]['sock'], track("Invalid length on ".$key." argument"));
			return false;
		}elseif(strlen($_GET[$key]) > 128){
			track_print($client[$i]['sock'], track("Argument ".$key." is too large to handle"));
			return false;
		}
		return $_GET[$key];
	}
	return false;
}

function track_print($socket, $x, $ctype="Text/Plain") {
	global $log, $client, $i;
	$header = "HTTP/1.1 200 OK\n";
	$header .= "Server: PHP Socket Server\n";
	$header .= "Content-Type: ".$ctype."\n";
	$header .= "Pragma: no-cache\n";
	$header .= "Connection: close\n\n";
	$header .= trim($x);
	@socket_write($socket, $header, strlen($header));
	@socket_close($client[$i]['sock']);
	@socket_close($socket);
	unset($socket);
	unset($client[$i]['sock']);
	unset($client[$i]);
	// silence
	//$log->msg("c", "Client #".$i." getrennt.\r\n");
}

function http_parse_headers($headers) {
	$headers = str_replace("\r", "", trim($headers));
	$headers = explode("\n", $headers);
	foreach($headers AS $value) {
		$header = explode(": ",$value);
		if($header[0] && empty($header[1])) {
			$headerdata['status'] = $header[0];
			if(preg_match("|^HTTP/[^\s]*\s(.*?)\s|", $header[0], $status)) {
				$headerdata['statuscode'] = $status[1];
			}
		}
		elseif($header[0] && $header[1]) {
			$headerdata[$header[0]] = $header[1];
		}
	}
	return $headerdata;
}

function escape_string($string) {
	$replacements = array(
		"\x00"=>'\x00',
     	"\n"=>'\n',
     	"\r"=>'\r',
     	"\\"=>'\\\\',
     	"'"=>"\'",
     	'"'=>'\"',
     	"\x1a"=>'\x1a'
	);
	return strtr($string, $replacements);
}

function parse_query_string($url) {
	$val = array();
	$ex = explode("?", $url);
	$str = (isset($ex[1])) ? $ex[1] : false;
	if($str) {
		$a = explode("&", $str);
		foreach($a AS $e) {
			if($e) {
				@list($k, $v) = @explode("=", $e);
				if($k == "info_hash" || $k == "peer_id")
					$val[$k] = urldecode($v);
				else
					$val[$k] = escape_string(rawurldecode($v));
			}
		}
	}
	return $val;
}

function formatBytes($size, $precision = 0) {
	$unit = ['Byte', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
	for($i = 0; $size >= 1024 && $i < count($unit)-1; $i++) {
		$size /= 1024;
	}
	return round($size, $precision)." ".$unit[$i];
}

function formatUpdate($timeago) {
	if($timeago < 60) {
		$value = $timeago.'s';
	}
	elseif($timeago >= 60 && $timeago < 3600) {
		$value = floor((($timeago)/60)).' Min.';
	}
	return $value;
}

function formatClient($useragent){
   	preg_match("/^([^;]*).*$/", $useragent, $client);
	return $client[1];
}
?>