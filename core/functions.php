<?php

function start_server() {
	global $server, $log;

	$log->msg("Scream Labs PHP BitTorrent Announce Socket Server ".release());
	$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Initializing...");

	$sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if(!$sock)
		$log->msg("ERROR (".date("d.m.Y H:i:s",time())."): Can't create socket! Reason: ".socket_strerror($sock)."\r\n",true);

	@socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);

	$bind = @socket_bind($sock, $server["ip"], $server["port"]);
	if(!$bind) $log->msg("ERROR (".date("d.m.Y H:i:s",time())."): Can't bind socket to given address! Reason: ".socket_strerror($bind)."\r\n",true);

	$listen = @socket_listen($sock, $server["max_clients"]);
	if(!$listen) $log->msg("ERROR (".date("d.m.Y H:i:s",time())."): Can't listen on socket! Reason: ".socket_strerror($listen)."\r\n",true);

	@socket_set_nonblock($sock);

	$server["running"] = true;

	$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Initialization OK!");
	$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Server running on ".$server["ip"].":".$server["port"]." ...");

	return $sock;

}

function stop_server($sock) {
	global $server, $log, $client;

	$server["running"] = false;

	$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): shutting down server...");

	foreach($client as $key => $clients) {

		@socket_close($clients['sock']);
		unset($clients);

		$log->msg("\r\nCLIENT (".date("d.m.Y H:i:s",time())."): Disconnected Client #".$key);

	}

	@socket_shutdown($sock);
	@socket_close($sock);

	$log->msg("\r\nSERVER (".date("d.m.Y H:i:s",time())."): Server shut down");

}

function files() {
	global $extern, $log;

	$extensions = explode('|', $extern["extensions"]);

	if(@is_dir($extern["directory"])) {

		if($handle = @opendir($extern["directory"])) {

			while(false !== ($file = @readdir($handle))) {

				$pieces = explode('.', $file);

				if($file != "." && $file != ".." && !@is_dir($extern["directory"]."/".$file) && in_array(strtolower(end($pieces)),$extensions)) {

					$fp = @fopen($extern["directory"]."/".$file, "rb");
					$contents = @fread($fp, @filesize($extern["directory"]."/".$file));
					@fclose($fp);

					$array[$file] = $contents;

					$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Cache -> $file (".@filesize($extern["directory"]."/".$file)." Bytes)");

				}
			}

			@closedir($handle);

		}

	}

return $array;

}

function load_plugins() {
	global $plugins, $log;

	foreach($plugins as $key => $plugin) {

		if($plugin && @file_exists("plugins/".$key.".inc.php")) {

			include("plugins/".$key.".inc.php");

			$log->msg("SERVER (".date("d.m.Y H:i:s",time())."): Loading Plugin ".$plugin_name." OK!");

			$plugins_loaded[$key] = true;

		}

	}

}

function nickfrompasskey($passkey) {

	$query = mysql_query("SELECT * FROM chatusers WHERE passkey='$passkey' LIMIT 1");
	$result = mysql_fetch_array($query);

	$nick = $result[nick];

	return $nick;

}

function checkpasskey($passkey) {

	$query = mysql_query("SELECT * FROM chatusers WHERE passkey='$passkey' LIMIT 1");
	$row = mysql_num_rows($query);

	return $row;

}

function checkconnect($ip,$port) {

	$sockres = @fsockopen($ip, $port, $errno, $errstr, 1);

	if(!$sockres) {
		$connectable = false;
	} else {
		$connectable = true;
		@fclose($sockres);
	}

	return $connectable;

}

function portblacklisted($port) {

	if($port >= 411 && $port <= 413) return true;
	if($port >= 6881 && $port <= 6889) return true;
	if($port == 1214) return true;
	if($port >= 6346 && $port <= 6347) return true;
	if($port == 4662) return true;
	if($port == 6699) return true;

	return false;

}

function check_ip_limit() {
    global $client, $i, $nick, $tracker, $torrent_id, $ip;

    $query = mysql_query("SELECT DISTINCT(ip) AS ip FROM peers WHERE nick='$nick'");
    $row = mysql_num_rows($query);

    $found = false;

    while($result = mysql_fetch_assoc($query)) {

    	if($result["ip"] == $ip) {
    		$found = true;
   			break;
    	}

    }

    $peerseed = @mysql_fetch_row(@mysql_query("SELECT COUNT(*) FROM peers WHERE torrent='$torrent_id' AND nick='$nick' AND seeder='yes'"));
    $peerleech = @mysql_fetch_row(@mysql_query("SELECT COUNT(*) FROM peers WHERE torrent='$torrent_id' AND nick='$nick' AND seeder='no'"));

	if(!$found && $row >= $tracker["maxips"])
    	err($client[$i]['sock'], "Zu viele unterschiedliche IPs fuer diesen Benutzer (max ".$tracker["maxips"].").");
	elseif(($peerleech[0] >= $tracker["torrent_max_leechers"]) || ($peerseed[0] >= $tracker["torrent_max_seeders"]) || ($peerseed[0] >= 1 && $peerleech[0] >= 1))
		err($client[$i]['sock'], "Connection Limit ueberschritten!");

}

function benc($obj) {

	if (!is_array($obj) || !isset($obj["type"]) || !isset($obj["value"]))
		return;
	$c = $obj["value"];
	switch ($obj["type"]) {
		case "string":
			return benc_str($c);
		case "integer":
			return benc_int($c);
		case "list":
			return benc_list($c);
		case "dictionary":
			return benc_dict($c);
		default:
			return;
	}

}

function benc_str($s) {
	return strlen($s) . ":$s";
}

function benc_int($i) {
	return "i" . $i . "e";
}

function benc_list($a) {
	$s = "l";
	foreach ($a as $e) {
		$s .= benc($e);
	}
	$s .= "e";
	return $s;
}

function benc_dict($d) {
	$s = "d";
	$keys = array_keys($d);
	sort($keys);
	foreach ($keys as $k) {
		$v = $d[$k];
		$s .= benc_str($k);
		$s .= benc($v);
	}
	$s .= "e";
	return $s;
}

function bdec_file($f, $ms) {
	$fp = @fopen($f, "rb");
	if(!$fp)
	return;
	$e = @fread($fp, $ms);
	@fclose($fp);
	return bdec($e);
}

function bdec($s) {
	if (preg_match('/^(\d+):/', $s, $m)) {
		$l = $m[1];
		$pl = strlen($l) + 1;
		$v = substr($s, $pl, $l);
		$ss = substr($s, 0, $pl + $l);
		if (strlen($v) != $l)
			return;
		return array(type => "string", value => $v, strlen => strlen($ss), string => $ss);
	}
	if (preg_match('/^i(\d+)e/', $s, $m)) {
		$v = $m[1];
		$ss = "i" . $v . "e";
		if ($v === "-0")
			return;
		if ($v[0] == "0" && strlen($v) != 1)
			return;
		return array(type => "integer", value => $v, strlen => strlen($ss), string => $ss);
	}
	switch ($s[0]) {
		case "l":
			return bdec_list($s);
		case "d":
			return bdec_dict($s);
		default:
			return;
	}
}

function bdec_list($s) {
	if ($s[0] != "l")
		return;
	$sl = strlen($s);
	$i = 1;
	$v = array();
	$ss = "l";
	for (;;) {
		if ($i >= $sl)
			return;
		if ($s[$i] == "e")
			break;
		$ret = bdec(substr($s, $i));
		if (!isset($ret) || !is_array($ret))
			return;
		$v[] = $ret;
		$i += $ret["strlen"];
		$ss .= $ret["string"];
	}
	$ss .= "e";
	return array(type => "list", value => $v, strlen => strlen($ss), string => $ss);
}

function bdec_dict($s) {
	if ($s[0] != "d")
		return;
	$sl = strlen($s);
	$i = 1;
	$v = array();
	$ss = "d";
	for (;;) {
		if ($i >= $sl)
			return;
		if ($s[$i] == "e")
			break;
		$ret = bdec(substr($s, $i));
		if (!isset($ret) || !is_array($ret) || $ret["type"] != "string")
			return;
		$k = $ret["value"];
		$i += $ret["strlen"];
		$ss .= $ret["string"];
		if ($i >= $sl)
			return;
		$ret = bdec(substr($s, $i));
		if (!isset($ret) || !is_array($ret))
			return;
		$v[$k] = $ret;
		$i += $ret["strlen"];
		$ss .= $ret["string"];
	}
	$ss .= "e";
	return array(type => "dictionary", value => $v, strlen => strlen($ss), string => $ss);
}

function err($socket,$msg)
{
	benc_resp($socket, array("failure reason" => array(type => "string", value => $msg)));
}

function benc_resp($socket,$d)
{
	benc_resp_raw($socket, benc(array(type => "dictionary", value => $d)),"text/plain");
}

function benc_resp_raw($socket,$x,$content_type="text/html")
{
global $log, $client, $i;

	$header = "HTTP/1.1 200 OK\n";
	$header .= "Server: PHP Socket Server\n";
	$header .= "Content-Type: $content_type\n";
	$header .= "Pragma: no-cache\n";
	$header .= "Connection: close\n\n";
	$header .= trim($x);

	@socket_write($socket,$header,strlen($header));

	if($client[$i]['sock'] != null) {
		@socket_close($client[$i]['sock']);
		unset($client[$i]['sock']);
		unset($client[$i]);
		$log->msg("\r\nCLIENT (".date("d.m.Y H:i:s",time())."): Disconnected Client #".$i);
	}

}

function http_parse_headers($headers) {

	$headers = str_replace("\r","",trim($headers));
	$headers = explode("\n",$headers);

	foreach($headers as $value) {

		$header = explode(": ",$value);

		if($header[0] && empty($header[1])) {

			$headerdata['status'] = $header[0];

			if(preg_match("|^HTTP/[^\s]*\s(.*?)\s|",$header[0], $status))
				$headerdata['statuscode'] = $status[1];

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

			foreach($a as $e) {

				if($e) {
					list($k,$v) = explode("=", $e);
					$val[$k] = mysql_real_escape_string(rawurldecode($v));
				}

			}
	}

	return $val;

}

function hex_esc($matches) {
	return sprintf("%02x", ord($matches[0]));
}

function hash_pad($hash) {
	return str_pad($hash, 20);
}

function release() {

	$version = "1.1";
	$build = "20110504";
	$comment = "Beta";

	$output = $version;

	if(trim($build) != "")
		$output .= " [Build: ".$build."]";
	if(trim($comment) != "")
		$output .= " (".$comment.")";

	return $output;

}

function checkupdate() {

	$severurl = "http://www.screamlabs.at/chatcommunity/index.php?service=status&checkupdate=announceserver";

	$file = @file_get_contents($severurl);

	if(!$file || preg_match("/\Error/",$file)) return false;

	$current_version = release();
	$current_exp = explode(" ", $current_version);
	$current_version = $current_exp[0];
	$current_version2 = explode(".", $current_version);
	$current_version2 = sprintf("%d%02d%02d", $current_version2[0], $current_version2[1], intval($current_version2[2]));
	$current_build = str_replace("]", "", $current_exp[2]);
	$current_comment = $current_exp[3];

	$server_exp = explode(" ", $file);
	$server_version = $server_exp[0];
	$server_version2 = explode(".", $server_version);
	$server_version2 = sprintf("%d%02d%02d", $server_version2[0], $server_version2[1], intval($server_version2[2]));
	$server_build = str_replace("]", "", $server_exp[2]);
	$server_comment = $server_exp[3];

	if($current_version2 < $server_version2 || $current_build < $server_build) {
		return "Version: ".$current_version." [Build: ".$current_build."] $current_comment<br>Neues Update verfügbar: <a href=\"http://www.screamlabs.at\" target=\"_blank\">".$file."</a>";
	}

	return false;

}

function ctracker($socket,$request) {
	global $getip, $useragent;

	$cracktrack = rawurldecode($request);

	$wormprotector = array('chr(', 'chr=', 'chr%20', '%20chr', 'wget%20', '%20wget', 'wget(',
	'cmd=', '%20cmd', 'cmd%20', 'rush=', '%20rush', 'rush%20',
	'union%20', '%20union', 'union(', 'union=', 'echr(', '%20echr', 'echr%20', 'echr=',
	'esystem(', 'esystem%20', 'cp%20', '%20cp', 'cp(', 'mdir%20', '%20mdir', 'mdir(',
	'mcd%20', 'mrd%20', 'rm%20', '%20mcd', '%20mrd', '%20rm',
	'mcd(', 'mrd(', 'rm(', 'mcd=', 'mrd=', 'mv%20', 'rmdir%20', 'mv(', 'rmdir(',
	'chmod(', 'chmod%20', '%20chmod', 'chmod(', 'chmod=', 'chown%20', 'chgrp%20', 'chown(', 'chgrp(',
	'locate%20', 'grep%20', 'locate(', 'grep(', 'diff%20', 'kill%20', 'kill(', 'killall',
	'passwd%20', '%20passwd', 'passwd(', 'telnet%20', 'vi(', 'vi%20',
	'insert%20into', 'select%20', 'nigga(', '%20nigga', 'nigga%20', 'fopen', 'fwrite', '%20like', 'like%20',
	'$_request', '$_get', '$request', '$get', '.system', 'HTTP_PHP', '&aim', '%20getenv', 'getenv%20',
	'new_password', '&icq','/etc/password','/etc/shadow', '/etc/groups', '/etc/gshadow',
	'HTTP_USER_AGENT', 'HTTP_HOST', '/bin/ps', 'wget%20', 'uname\x20-a', '/usr/bin/id',
	'/bin/echo', '/bin/kill', '/bin/', '/chgrp', '/chown', '/usr/bin', 'g\+\+', 'bin/python',
	'bin/tclsh', 'bin/nasm', 'perl%20', 'traceroute%20', 'ping%20', '.pl', '/usr/X11R6/bin/xterm', 'lsof%20',
	'/bin/mail', '.conf', 'motd%20', 'HTTP/1.', '.inc.php', 'config.php', 'cgi-', '.eml',
	'file\://', 'window.open', '<script>', 'javascript\://','img src', 'img%20src','.jsp','ftp.exe','%20','iframe','%77','%6','%2E',
	'xp_enumdsn', 'xp_availablemedia', 'xp_filelist', 'xp_cmdshell', 'nc.exe', '.htpasswd',
	'servlet', '/etc/passwd', 'wwwacl', '~root', '~ftp', '.js', '.jsp', 'admin_', '.history',
	'bash_history', '.bash_history', '~nobody', 'server-info', 'server-status', 'reboot%20', 'halt%20',
	'powerdown%20', '/home/ftp', '/home/www', 'secure_site, ok', 'chunked', 'org.apache', '/servlet/con',
	'<script', '/robot.txt' ,'/perl' ,'mod_gzip_status', 'db_mysql.inc', '.inc', 'select%20from',
	'select from', 'drop%20', '.system', 'getenv', 'http_', '_php', 'php_', 'phpinfo()', '<?php', '?>', 'sql=',
	'UPDATE', 'DELETE', 'DROP', 'INSERT', '$mysql_', 'java script:');


	$checkworm = str_replace($wormprotector, 'X*X', $cracktrack);
	$checkworm = str_replace($wormprotector, '*', strtolower($cracktrack));
	$cracktrack = strtolower($cracktrack);

	if($cracktrack != $checkworm) {

		$expl = explode("X*X" ,$checkworm);
		$manipulated = str_replace($expl[0] , "" ,$cracktrack);
		foreach($expl as $delete)
		$manipulated = str_replace($delete , "'" ,$manipulated);
		$cremotead = $_SERVER['REMOTE_ADDR'];
		$cuseragent = $_SERVER['HTTP_USER_AGENT'];

		$fp = @fopen("logs/attack.txt", "a");
     	@fwrite($fp, date("d.m.Y H:i:s"). " - Angriff geblockt: IP - " . $getip . " User Agent - " . $useragent . " String - " . $manipulated . "\n");
      	@fclose ($fp);

		benc_resp_raw($socket,"<font color=\"red\">Attack detected! <br /><br /><b>Du bist soooooo SEXY das wir diesen Hackversuch gleich geloggt haben !! Rechne mit Konsequenzen !!:<br/>$getip - $useragent</font>");

	}

}

?>