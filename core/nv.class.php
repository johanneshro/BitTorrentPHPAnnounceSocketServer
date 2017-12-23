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
	
	public function GetUsernameByPasskey($passkey){
		$passkey = hex2bin($passkey);
		$qry = $this->con->prepare("SELECT username FROM users WHERE passkey= :pk");
		$qry->bindParam(':pk', $passkey, PDO::PARAM_STR);
		$qry->execute();
		$data = $qry->Fetch(PDO::FETCH_ASSOC);
		return $data["username"];
	}

	// ann L:147: $res = mysql_query("SELECT id, name, category, banned, activated, seeders + leechers AS numpeers, UNIX_TIMESTAMP(added) AS ts FROM torrents WHERE " . hash_where("info_hash", $info_hash));
	public function GetTorrentDataByInfohash($hash){
	}
	
	// $res = mysql_query("SELECT seeder, peer_id, ip, port, uploaded, downloaded, userid FROM peers WHERE torrent = $torrentid AND connectable = 'yes' $limit");
	public function GetPeers(){
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
	public function hash_where($name, $hash){
		$shhash = preg_replace('/ *$/s', "", $hash);
		return "(" . $name . " = '" . $hash . "' OR " . $name . " = '" . $shhash . "')";
	}
}
?>