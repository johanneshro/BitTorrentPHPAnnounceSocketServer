<?php

/*
// +--------------------------------------------------------------------------+
// | Project:    pdonvtracker - NetVision BitTorrent Tracker 2017             |
// +--------------------------------------------------------------------------+
// | This file is part of pdonvtracker. NVTracker is based on BTSource,       |
// | originally by RedBeard of TorrentBits, extensively modified by           |
// | Gartenzwerg.                                                             |
// +--------------------------------------------------------------------------+
// | Obige Zeilen dürfen nicht entfernt werden!    Do not remove above lines! |
// +--------------------------------------------------------------------------+
 */

class nv
{
	private $con;
	private $pdonvtracker;
	private $pdonvtracker_conf_arr;

	function __construct($db){
		$this->con = $db;
		$this->pdonvtracker = "";
		$this->pdonvtracker_conf_arr = array();
	}

	public function SetTrackerPath($url = "", $port = 80){
		$this->pdonvtracker = "http://" . $url . ":" . $port;
	}
	
	private function CheckPasskey($pk){
		if(strlen($pk) % 2 != 0)
			return false;
		return true;
	}
	
	private function IsConnectable($ip, $port){
		if(isset($ip, $port) && !$this->portblacklisted($port)){
            $sockres = @fsockopen($ip, $port, $errno, $errstr, 5);
			if(!$sockres)
				return false;
			else{
				@fclose($sockres);
				return true;
			}
		}else
			return false;
	}

	public function GetUserDataByPasskey($passkey){
		if(!$this->CheckPasskey($passkey))
			return false;
		$passkey = hex2bin($passkey);
		$qry = $this->con->prepare("SELECT id, username FROM users WHERE passkey= :pk");
		$qry->bindParam(':pk', $passkey, PDO::PARAM_STR);
		$qry->execute();
		if($qry->rowCount())
			$data = $qry->Fetch(PDO::FETCH_ASSOC);
		else
			$data = false;
		return $data;
	}

	
	public function GetTorrentDataByInfohash($hash){
		$hhash = bin2hex($hash);
		$qry = $this->con->prepare("SELECT id, name, banned, activated, seeders + leechers AS numpeers, UNIX_TIMESTAMP(added) AS ts FROM torrents WHERE info_hash = :hash LIMIT 1");
		$qry->bindParam(':hash', $hhash, PDO::PARAM_STR);
		$qry->execute();
		if($qry->rowCount())
			$data = $qry->Fetch(PDO::FETCH_ASSOC);
		else
			$data = false;
		return $data;
	}
	
	public function GetPeers($tid, $rsize, $ownip){
		$qry = $this->con->prepare("SELECT seeder, peer_id, ip, port FROM peers WHERE torrent = :tid AND connectable = 'yes' AND ip != :ownip ORDER BY RAND() LIMIT :limit");
		$qry->bindParam(':tid', $tid, PDO::PARAM_STR);
		$qry->bindParam(':limit', $rsize, PDO::PARAM_INT);
		$qry->bindParam(':ownip', $ownip, PDO::PARAM_STR);
		$qry->execute();
		$data = $qry->FetchAll(PDO::FETCH_ASSOC);
		$r["seeders"] = 0;
		$r["leechers"] = 0;
		$r["peers"] = array();
		foreach($data as $peer){
			$r["peers"][$peer["peer_id"]] = array("ip" => $peer["ip"], "port" => $peer["port"]);
			if($peer["seeder"] == "yes")
				$r["seeders"]++;
			else
				$r["leechers"]++;
		}
		return $r;
	}

	/*public function getip(){		
		$client  = @$_SERVER['HTTP_CLIENT_IP'];		
		$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];		
		$remote  = @$_SERVER['REMOTE_ADDR'];		
		if(filter_var($client, FILTER_VALIDATE_IP))		
			$ip = $client;		
		elseif(filter_var($forward, FILTER_VALIDATE_IP))		
			$ip = $forward;		
		else		
			$ip = $remote;		
		return $ip;		
	}*/

	public function InsertPeer($torrentid, $peer_id, $ip, $port, $uploaded, $downloaded, $left, $userid, $agent){
		$connectable = ($this->IsConnectable($ip, $port)) ? "yes" : "no";
		$seeder = ($left == 0) ? "yes" : "no";
		$qry = $this->con->prepare("INSERT INTO peers (connectable, torrent, peer_id, ip, port, uploaded, downloaded, to_go, started, last_action, seeder, userid, agent, uploadoffset, downloadoffset)VALUES (:connectable, :tid, :pid, :ip, :port, :uploaded, :downloaded, :left, NOW(), NOW(), :seeder, :uid, :agent, :uldd, :dldd)");
		$qry->bindParam(':connectable', $connectable, PDO::PARAM_STR);
		$qry->bindParam(':tid', $torrentid, PDO::PARAM_INT);
		$qry->bindParam(':pid', $peer_id, PDO::PARAM_STR);
		$qry->bindParam(':ip', $ip, PDO::PARAM_STR);
		$qry->bindParam(':port', $port, PDO::PARAM_INT);
		$qry->bindParam(':uploaded', $uploaded, PDO::PARAM_INT);
		$qry->bindParam(':downloaded', $downloaded, PDO::PARAM_INT);
		$qry->bindParam(':left', $left, PDO::PARAM_INT);
		$qry->bindParam(':seeder', $seeder, PDO::PARAM_STR);
		$qry->bindParam(':uid', $userid, PDO::PARAM_INT);
		$qry->bindParam(':agent', $agent, PDO::PARAM_STR);
		$qry->bindParam(':uldd', $uploaded, PDO::PARAM_INT);
		$qry->bindParam(':dldd', $downloaded, PDO::PARAM_INT);
		$qry->execute();
		if(!$qry->rowCount())
			return false;
		
		if($left == 0)
			$qry = $this->con->prepare("UPDATE torrents SET seeders = seeders + 1 WHERE id = :tid");
		else
			$qry = $this->con->prepare("UPDATE torrents SET leechers = leechers + 1 WHERE id = :tid");
		$qry->bindParam(':tid', $torrentid, PDO::PARAM_INT);
		$qry->execute();
		if(!$qry->rowCount())
			return false;
		return true;
	}
	
	public function DeletePeer($torrentid, $ip, $left){
		$qry = $this->con->prepare("DELETE FROM peers WHERE torrent = :tid AND ip = :ip LIMIT 1");
		$qry->bindParam(':tid', $torrentid, PDO::PARAM_STR);
		$qry->bindParam(':ip', $ip, PDO::PARAM_STR);
		$qry->execute();
		if(!$qry->rowCount())
			return false;

		if($left == 0)
			$qry = $this->con->prepare("UPDATE torrents SET seeders = seeders - 1 WHERE id = :tid");
		else
			$qry = $this->con->prepare("UPDATE torrents SET leechers = leechers - 1 WHERE id = :tid");
		$qry->bindParam(':tid', $torrentid, PDO::PARAM_INT);
		$qry->execute();
		if(!$qry->rowCount())
			return false;
		return true;
	}
	
	public function GetScrapeString($hash){
		$hhash = bin2hex($hash);
		$qry = $this->con->prepare("SELECT times_completed, leechers, seeders FROM torrents WHERE info_hash = :hash LIMIT 1");
		$qry->bindParam(':hash', $hhash, PDO::PARAM_STR);
		$qry->execute();
		if($qry->rowCount())
			$row = $qry->Fetch(PDO::FETCH_ASSOC);
		else
			return false;
		$r = "d5:filesd20:".$hash."d8:completei".$row["seeders"]."e10:downloadedi".$row["times_completed"]."e10:incompletei".$row["leechers"]."eeee";
		return $r;
	}
	
	//$res = mysql_query("SELECT * FROM `traffic` WHERE `userid`=$userid AND `torrentid`=$torrentid");
    //if (@mysql_num_rows($res) == 0)
    //    mysql_query("INSERT INTO `traffic` (`userid`,`torrentid`) VALUES ($userid, $torrentid)");
	public function InsertIntoTraffic(){
	}

	public function UpdatePeers($hash){
	}

	public function UpdateTorrent($data){
	}

	 //mysql_query("SELECT id, uploaded, downloaded, class, tlimitseeds, tlimitleeches, tlimitall FROM users WHERE passkey=".hex2bin($passkey)." AND enabled = 'yes' ORDER BY last_access DESC LIMIT 1") or err("Tracker error 2");
	public function UpdateUser($data){
	}

	public function GetNvConfig(){
		$response = file_get_contents($this->pdonvtracker . "/index.php?action=getnvconfig");
	}


	// ann L:192: $res = mysql_query("SELECT seeder, peer_id, ip, port, uploaded, downloaded, userid FROM peers WHERE torrent = $torrentid AND " . hash_where("peer_id", $peer_id));
	private function hash_where($name, $hash){
		$shhash = preg_replace('/ *$/s', "", $hash);
		return "(" . $name . " = '" . $hash . "' OR " . $name . " = '" . $shhash . "')";
	}
	
	private function unesc($x){
		if (get_magic_quotes_gpc())
			return stripslashes($x);
		return $x;
	}
	
	public function getlandingpage(){
		$page = "+-------------------------------+------------------------------------------------------------------+\n";
		$page .= "| pdonvtracker's                | https://github.com/kaitokid222/pdonvtracker                      |\n";
		$page .= "| Socket-Announce by Stifler    | https://github.com/johanneshro/BitTorrentPHPAnnounceSocketServer |\n";
		$page .= "| NetVision-Fork by kaitokid    | https://github.com/kaitokid222/BitTorrentPHPAnnounceSocketServer |\n";
		$page .= "+-------------------------------+------------------------------------------------------------------+\n";
		$page .= "|    Webcommands                |\n";
		$page .= "|        /status                |\n";
		$page .= "+-------------------------------+\n";
		return $page;
	}
	
	public function fakefourzerofour(){
		$page = "+-------------------------------------------------------------------------------+\n";
		$page .= "| Redet der mit mir? Callt der mich etwa an? Will der mich ficken?              |\n";
		$page .= "| Der labert tatsaechlich mit mir, der Pisser, was glaubt der eigentlich?       |\n";
		$page .= "| Oder was wolltest Du Wichser? Was glaubst Du, wer Du bist, Du scheiss Wichser?|\n";
		$page .= "+-------------------------------------------------------------------------------+\n";
		return $page;
	}

	public function portblacklisted($port){
		if($port >= 411 && $port <= 413)
			return true;
		if($port >= 6881 && $port <= 6889)
			return true;
		if($port == 1214)
			return true;
		if($port >= 6346 && $port <= 6347)
			return true;
		if($port == 4662)
			return true;
		if($port == 6699)
			return true;
		return false;
	}

}
?>