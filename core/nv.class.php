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
	
	public function GetNvConfig(){
		$response = file_get_contents($this->pdonvtracker . "/saconf.php?socket=1&operator=admin");
		$res_arr = json_decode($response, true);
		$this->pdonvtracker_conf_arr = $res_arr;
		SocketServer::debug("PDONVTRACKER-CONFIG: ".var_export($this->pdonvtracker_conf_arr,true));
	}

	// USER
	public function GetUserDataByPasskey($passkey){
		if(!$this->CheckPasskey($passkey))
			return false;
		$passkey = hex2bin($passkey);
		$qry = $this->con->prepare("SELECT id, uploaded, downloaded, class, tlimitseeds, tlimitleeches, tlimitall FROM users WHERE passkey= :pk");
		$qry->bindParam(':pk', $passkey, PDO::PARAM_STR);
		$qry->execute();
		if($qry->rowCount())
			$data = $qry->Fetch(PDO::FETCH_ASSOC);
		else
			$data = false;
		return $data;
	}
	
	private function CheckPasskey($pk){
		if(strlen($pk) % 2 != 0)
			return false;
		return true;
	}

	// TORRENT
	public function GetTorrentDataByInfohash($hash){
		$hhash = bin2hex($hash);
		$qry = $this->con->prepare("SELECT id, name, category, visible, banned, activated, seeders + leechers AS numpeers, UNIX_TIMESTAMP(added) AS ts FROM torrents WHERE info_hash = :hash LIMIT 1");
		$qry->bindParam(':hash', $hhash, PDO::PARAM_STR);
		$qry->execute();
		if($qry->rowCount())
			$data = $qry->Fetch(PDO::FETCH_ASSOC);
		else
			$data = false;
		return $data;
	}

	private function makeVisible($torrent){
		if($torrent["visible"] != "yes"){
			$qry = $this->con->prepare("UPDATE torrents SET visible = 'yes' WHERE id = :tid");
			$qry->bindParam(':tid', $torrent["id"], PDO::PARAM_INT);
			$qry->execute();
		}
	}

	public function isTorrentActivated($activated){
		if($activated != "yes")
			return false;
		return true;
	}

	public function addSeeder($tid){
		$qry = $this->con->prepare("UPDATE torrents SET seeders = seeders + 1, last_action = NOW() WHERE id = :tid");
		$qry->bindParam(':tid', $tid, PDO::PARAM_INT);
		$qry->execute();
		if(!$qry->rowCount())
			return false;
		return true;
	}

	public function removeSeeder($tid){
		$qry = $this->con->prepare("UPDATE torrents SET seeders = seeders - 1 WHERE id = :tid");
		$qry->bindParam(':tid', $tid, PDO::PARAM_INT);
		$qry->execute();
		if(!$qry->rowCount())
			return false;
		return true;
	}

	public function removeLeecher($tid){
		$qry = $this->con->prepare("UPDATE torrents SET leechers = leechers - 1 WHERE id = :tid");
		$qry->bindParam(':tid', $tid, PDO::PARAM_INT);
		$qry->execute();
		if(!$qry->rowCount())
			return false;
		return true;
	}

	public function addLeecher($tid){
		$qry = $this->con->prepare("UPDATE torrents SET leechers = leechers + 1 WHERE id = :tid");
		$qry->bindParam(':tid', $tid, PDO::PARAM_INT);
		$qry->execute();
		if(!$qry->rowCount())
			return false;
		return true;
	}

	// PEERS
	public function InsertPeer($torrentid, $peer_id, $ip, $port, $uploaded, $downloaded, $left, $userid, $agent, $visible, $banned){
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
		if($qry->rowCount()){
			if($left == 0){
				$this->addSeeder($torrentid);
				if($banned == "no"){
					$t["id"] = $torrentid;
					$t["visible"] = $visible;
					$this->makeVisible($t);
				}
			}else
				$this->addLeecher($torrentid);
		}else
			return false;
		return true;
	}

	public function checkIsPeerSeeder($tid, $pid){
		$qry = $this->con->prepare("SELECT seeder FROM peers WHERE torrent = :tid AND peer_id = :pid");
		$qry->bindParam(':tid', $tid, PDO::PARAM_INT);
		$qry->bindParam(':pid', $pid, PDO::PARAM_STR);
		$qry->execute();
		if($qry->rowCount()){
			$d = $qry->Fetch(PDO::FETCH_ASSOC);
			if($d["seeder"] == "yes")
				return true;
			else
				return false;
		}else
			return false;
	}

	public function getPeerLastAccess($tid, $pid){
		$qry = $this->con->prepare("SELECT UNIX_TIMESTAMP(last_action) AS lastaction FROM peers WHERE torrent = :tid AND peer_id = :pid");
		$qry->bindParam(':tid', $tid, PDO::PARAM_INT);
		$qry->bindParam(':pid', $pid, PDO::PARAM_STR);
		$qry->execute();
		$d = $qry->Fetch(PDO::FETCH_ASSOC);
		return $d["lastaction"];
	}

	public function getInterval($tid, $pid){
		$interval = time() - $this->getPeerLastAccess($tid, $pid);
		if($interval == 0)
			$interval = 1;
		return $interval;
	}

	public function getPeerStats($tid, $pid){
		$qry = $this->con->prepare("SELECT uploaded, downloaded FROM peers WHERE torrent = :tid AND peer_id = :pid");
		$qry->bindParam(':tid', $tid, PDO::PARAM_INT);
		$qry->bindParam(':pid', $pid, PDO::PARAM_STR);
		$qry->execute();
		$d = $qry->Fetch(PDO::FETCH_ASSOC);
		return $d;
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

	public function updatePeer($user, $torrent, $input, $seeder){
		$uploaded = $input->uploaded;
		$downloaded = $input->downloaded;
		$left = $input->left;
		$new_seeder = "no";
		if($left == 0)
			$new_seeder = "yes";
		$peer_id = $input->peer_id;
		if($seeder === false && $left == 0){
			$seeder = "no";
			$attach = ",finishedat = " . time() . " ";
		}else{
			$seeder = "yes";
			$attach = "";
		}
		$sql = "UPDATE peers SET uploaded = :uploaded, downloaded = :downloaded, to_go = :left, last_action = NOW(), seeder = :new_seeder " . $attach . "WHERE torrent = :tid AND peer_id = :pid";
		$qry = $this->con->prepare($sql);
		$qry->bindParam(':uploaded', $uploaded, PDO::PARAM_INT);
		$qry->bindParam(':downloaded', $downloaded, PDO::PARAM_INT);
		$qry->bindParam(':left', $left, PDO::PARAM_INT);
		$qry->bindParam(':new_seeder', $new_seeder, PDO::PARAM_STR);
		$qry->bindParam(':tid', $torrent["id"], PDO::PARAM_INT);
		$qry->bindParam(':pid', $peer_id, PDO::PARAM_STR);
		$qry->execute();
		if($qry->rowCount() && $seeder != $new_seeder){
			if($new_seeder == "yes"){
				$this->addSeeder($torrent["id"]);
				$this->removeLeecher($torrent["id"]);
				$this->Completed($torrent, $user);
			}else{
				$this->removeSeeder($torrent["id"]);
				$this->addLeecher($torrent["id"]);
			}
			
		}
		$pstats = $this->getPeerStats($torrent["id"], $peer_id);
		$dthis = max(0, $user["downloaded"]-$pstats["downloaded"]);
		$uthis = max(0, $user["uploaded"]-$pstats["uploaded"]);
		$this->updateTraffic($dthis,$uthis,$seeder,$this->getInterval($torrent["id"], $peer_id),$user["id"],$torrent["id"]);
	}

	public function Completed($torrent_info, $user_info){
		$qry = $this->con->prepare("UPDATE torrents SET times_completed = times_completed + 1 WHERE id = :tid");
		$qry->bindParam(':tid', $torrentid, PDO::PARAM_INT);
		$qry->execute();
		if(!$qry->rowCount())
			return false;
		$qry = $this->con->prepare("INSERT INTO completed (user_id, torrent_id, torrent_name, torrent_category, complete_time) VALUES (:userid, :torrentid, :tname, :tcat, NOW())");
		$qry->bindParam(':userid', $user_info["id"], PDO::PARAM_INT);
		$qry->bindParam(':torrentid', $torrent_info["id"], PDO::PARAM_INT);
		$qry->bindParam(':tname', $torrent_info["name"], PDO::PARAM_STR);
		$qry->bindParam(':tcat', $torrent_info["category"], PDO::PARAM_STR);
		$qry->execute();
		if(!$qry->rowCount())
			return false;
		return true;
	}

	public function updateTraffic($download, $upload, $seeder, $interval, $userid, $torrentid){
		if($seeder == "yes")
			$qry = $this->con->prepare("UPDATE `traffic` SET `downloaded`=`downloaded`+:downthis, `uploaded`=`uploaded`+:upthis, `uploadtime`=`uploadtime`+:interval WHERE `userid`=:userid AND `torrentid`=:torrentid");
		else
			$qry = $this->con->prepare("UPDATE `traffic` SET `downloaded`=`downloaded`+:downthis, `uploaded`=`uploaded`+:upthis, `downloadtime`=`downloadtime`+:interval,`uploadtime`=`uploadtime`+:interval WHERE `userid`=:userid AND `torrentid`=:torrentid");
		$qry->bindParam(':downthis', $download, PDO::PARAM_INT);
		$qry->bindParam(':upthis', $upload, PDO::PARAM_INT);
		$qry->bindParam(':interval', $interval, PDO::PARAM_INT);
		$qry->bindParam(':userid', $userid, PDO::PARAM_INT);
		$qry->bindParam(':torrentid', $torrentid, PDO::PARAM_INT);
		$qry->execute();

		$qry = $this->con->prepare("UPDATE users SET uploaded = uploaded + :upthis, downloaded = downloaded + :downthis WHERE id=:userid");
		$qry->bindParam(':upthis', $upload, PDO::PARAM_INT);
		$qry->bindParam(':downthis', $download, PDO::PARAM_INT);
		$qry->bindParam(':userid', $userid, PDO::PARAM_INT);
		$qry->execute();
	}

	public function createTrafficLog($userid, $torrentid){
		$qry = $this->con->prepare("SELECT * FROM `traffic` WHERE `userid`= :userid AND `torrentid`= :torrentid");
		$qry->bindParam(':userid', $userid, PDO::PARAM_INT);
		$qry->bindParam(':torrentid', $torrentid, PDO::PARAM_INT);
		$qry->execute();
		if(!$qry->rowCount()){
			$qry = $this->con->prepare("INSERT INTO `traffic` (`userid`,`torrentid`) VALUES (:userid, :torrentid)");
			$qry->bindParam(':userid', $userid, PDO::PARAM_INT);
			$qry->bindParam(':torrentid', $torrentid, PDO::PARAM_INT);
			$qry->execute();
		}
	}
	
	public function DeletePeer($torrentid, $ip, $left){
		$qry = $this->con->prepare("DELETE FROM peers WHERE torrent = :tid AND ip = :ip LIMIT 1");
		$qry->bindParam(':tid', $torrentid, PDO::PARAM_STR);
		$qry->bindParam(':ip', $ip, PDO::PARAM_STR);
		$qry->execute();
		if($qry->rowCount()){
			if($left == 0)
				$this->removeSeeder($torrentid);
			else
				$this->removeLeecher($torrentid);			
		}else
			return false;
		return true;
	}

	public function insertStartLog($userid, $torrentid, $ip, $peer_id, $useragent){
		$qry = $this->con->prepare("INSERT INTO startstoplog (userid,event,`datetime`,torrent,ip,peerid,useragent) VALUES (:uid,'start',NOW(),:tid,:ip,:peer_id,:useragent)");
		$qry->bindParam(':uid', $userid, PDO::PARAM_INT);
		$qry->bindParam(':tid', $torrentid, PDO::PARAM_INT);
		$qry->bindParam(':ip', $ip, PDO::PARAM_STR);
		$qry->bindParam(':peer_id', $peer_id, PDO::PARAM_STR);
		$qry->bindParam(':useragent', $useragent, PDO::PARAM_STR);
		$qry->execute();
	}

	public function insertStopLog($userid, $torrentid, $ip, $peer_id, $useragent){
		$qry = $this->con->prepare("INSERT INTO startstoplog (userid,event,`datetime`,torrent,ip,peerid,useragent) VALUES (:uid,'stop',NOW(),:tid,:ip,:peer_id,:useragent)");
		$qry->bindParam(':uid', $userid, PDO::PARAM_INT);
		$qry->bindParam(':tid', $torrentid, PDO::PARAM_INT);
		$qry->bindParam(':ip', $ip, PDO::PARAM_STR);
		$qry->bindParam(':peer_id', $peer_id, PDO::PARAM_STR);
		$qry->bindParam(':useragent', $useragent, PDO::PARAM_STR);
		$qry->execute();
	}

	// scrape
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

	// functions
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

	public function isNotBrowser($agent){
		if (preg_match("/^Mozilla|^Opera|^Links|^Lynx/i", $agent))
			return false;
		return true;
	}

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