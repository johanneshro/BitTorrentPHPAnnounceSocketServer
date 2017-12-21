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

	function __construct($db){
		$this->con = $db;
	}
	
	public function getUsername($passkey){
		//$passkey = hex2bin($passkey);
		$qry = $this->con->prepare("SELECT username FROM users WHERE passkey= :pk");
		$qry->bindParam(':pk', $passkey, PDO::PARAM_STR);
		$qry->execute();
		$data = $qry->Fetch(PDO::FETCH_ASSOC);
		return $data["username"];
	}
}
?>