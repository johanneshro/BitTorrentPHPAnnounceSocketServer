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
	
	public function GetUserDataByPasskey($passkey){
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
	
	public function GetPeers($tid, $tnp, $rsize){
		if ($tnp > $rsize)
			$limit = $rsize;
		else
			$limit = $tnp;
		$qry = $this->con->prepare("SELECT seeder, peer_id, ip, port, uploaded, downloaded, userid FROM peers WHERE torrent = :tid AND connectable = 'yes' ORDER BY RAND() LIMIT :limit");
		$qry->bindParam(':tid', $tid, PDO::PARAM_STR);
		$qry->bindParam(':limit', $limit, PDO::PARAM_INT);
		$qry->execute();
		$data = $qry->FetchAll(PDO::FETCH_ASSOC);
		return $data;
	}

	/*$ret = mysql_query("
	INSERT INTO peers (
	connectable, 
	torrent, 
	peer_id, 
	ip, 
	port, 
	uploaded, 
	downloaded, 
	to_go, 
	started, 
	last_action, 
	seeder, 
	userid, 
	agent, 
	uploadoffset, 
	downloadoffset)
	VALUES (
	'$connectable', 
	$torrentid, 
	" . sqlesc($peer_id) . ", 
	" . sqlesc($ip) . ", 
	$port, 
	$uploaded, 
	$downloaded, 
	$left, 
	NOW(), 
	NOW(), 
	'$seeder', 
	$userid, 
	" . sqlesc($agent) . ", 
	$uploaded, 
	$downloaded)");*/
	public function InsertPeer(){
		
	}
	
	// $query = "SELECT info_hash, times_completed, seeders, leechers FROM torrents WHERE " . hash_where("info_hash", unesc($_GET["info_hash"]));
	public function GetScrapeData(){
	}
	
	//$res = mysql_query("SELECT passkey,id FROM users WHERE id=$userid AND enabled = 'yes'");
    //$pkrow = mysql_fetch_assoc($res);
    //$passkey = hex2bin($passkey);
    //if ($passkey != $pkrow["passkey"])
    //    err("Ungueltiger PassKey. Lies das FAQ!");
	public function CheckPasskey(){
		
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
		$page .= "|        /db     (as Operator)  |\n";
		$page .= "|        /kill   (as Operator)  |\n";
		$page .= "|        /status                |\n";
		$page .= "+-------------------------------+\n";
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