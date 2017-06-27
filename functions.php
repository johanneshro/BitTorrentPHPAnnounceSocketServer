<?php

function start_server() {
	global $server, $log;

	$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Initializing...\r\n");

	$sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

	if(!$sock) $log->msg("ERROR (".date("d.m.Y H:i:s",time())."): Can't create socket! Reason: ".socket_strerror($sock)."\r\n",true);

	@socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);

	$bind = @socket_bind($sock, $server[ip], $server[port]);

	if(!$bind) $log->msg("ERROR (".date("d.m.Y H:i:s",time())."): Can't bind socket to given address! Reason: ".socket_strerror($bind)."\r\n",true);

	$listen = @socket_listen($sock, $server["max_clients"]);

	if(!$listen) $log->msg("ERROR (".date("d.m.Y H:i:s",time())."): Can't listen on socket! Reason: ".socket_strerror($listen)."\r\n",true);

	@socket_set_nonblock($sock);

	$server["running"] = true;

	$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Initialization OK!\r\n");
	$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Server running on ".$server[ip].":".$server[port]." ...\r\n");

	return $sock;

}

function stop_server($sock) {
	global $server, $log, $client;

	$server["running"] = false;

	$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): shutting down server...\r\n");

	foreach($client AS $key => $clients) {

		@socket_close($clients['sock']);

		unset($clients);

		$log->msg("CLIENT (".date("d.m.Y H:i:s",time())."): Disconnected Client #".$key."\r\n");

	}

	@socket_shutdown($sock);
	@socket_close($sock);

	$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Server shut down\r\n");

}

function delete_value($array, $value) {
	global $db;

	if(($key = array_search($value, $db[$array])) !== false) {
		unset($db[$array][$key]);
	}

	return true;

}

function files() {
	global $extern, $log;

	$extensions = explode('|', $extern["extensions"]);

	if(@is_dir($extern["directory"])) {

		if($handle = @opendir($extern["directory"])) {

			while(false !== ($file = @readdir($handle))) {

				$pieces = explode('.', $file);

				if($file != "." && $file != ".." && !@is_dir($extern["directory"]."/".$file) && in_array(strtolower(end($pieces)), $extensions)) {

					$fp = @fopen($extern["directory"]."/".$file, "rb");
					$contents = @fread($fp, @filesize($extern["directory"]."/".$file));
					@fclose($fp);

					$array[$file] = $contents;

					$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Cache -> $file (".@filesize($extern["directory"]."/".$file)." Bytes)\r\n");

				}
			}

			@closedir($handle);

		}

	}

	return $array;

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

function checkGET($key, $strlen_check=false) {
	global $_GET, $client, $i;

	if(!isset($_GET[$key])) {
		track_print($client[$i]['sock'], track("Missing key: $key"));
	}
	elseif(!is_string($_GET[$key])) {
		track_print($client[$i]['sock'], track("Invalid types on one or more arguments"));
	}
	elseif($strlen_check && strlen($_GET[$key]) != 20) {
		track_print($client[$i]['sock'], track("Invalid length on ".$key." argument"));
	}
	elseif(strlen($_GET[$key]) > 128) {
		track_print($client[$i]['sock'], track("Argument ".$key." is too large to handle"));
	}

	return $_GET[$key];

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

	if($client[$i]['sock'] != null) {

		@socket_close($client[$i]['sock']);

		unset($client[$i]['sock']);
		unset($client[$i]);

		$log->msg("CLIENT (".date("d.m.Y H:i:s",time())."): Disconnected Client #".$i."\r\n");

	}

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

function parse_query_string($url) {

	$ex = explode("?", $url);
	$str = $ex[1];

	if($str) {

		$a = explode("&", $str);

		foreach($a AS $e) {

			if($e) {

				list($k,$v) = explode("=", $e);

				$val[$k] = mysql_real_escape_string(rawurldecode($v));

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

function formatClient($useragent) {

   	preg_match("/^([^;]*).*$/", $useragent, $client);

	return $client[1];

}

?>