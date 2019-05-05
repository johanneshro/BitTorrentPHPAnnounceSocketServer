<?php

/*
// +--------------------------------------------------------------------------+
// | Project:    pdonvtracker - NetVision BitTorrent Tracker 2019             |
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
	public $pdonvtracker_conf_arr;

	function __construct($path,$port){
		$this->con = DB::getInstance();
		if(!$this->con)
			die("Datenbankfehler!");
		$this->pdonvtracker = "";
		$this->pdonvtracker_conf_arr = array();
		$this->SetTrackerPath($path, $port);
		$this->GetNvConfig();
	}

	public function SetTrackerPath($url = "", $port = 80){
		$this->pdonvtracker = "http://" . $url . ":" . $port;
	}

	public function checkUseragent($ua){
		if(in_array($ua, $this->pdonvtracker_conf_arr["config"]["BAN_USERAGENTS"]))
			return false;
		return true;
	}

	public function checkPeerID($pid){
		foreach($this->pdonvtracker_conf_arr["config"]["BAN_PEERIDS"] as $banned_id){
			if(substr($pid,0,strlen($banned_id)) == $banned_id){
				return false;
			}
		}
		return true;
	}

	public function hasToWait($uid, $tid, $left){
		$wait = $this->get_wait_time($uid, $tid, FALSE, $left);
		if(($left > 0 || $this->pdonvtracker_conf_arr["config"]["ONLY_LEECHERS_WAIT"]) == "no" && $wait > 0)
			return $wait;
		return false;
	}

	public function canInsert($user, $left){
		if($user["tlimitall"] >= 0){
			$numtorrents = 0;
			$seeds = 0;
			$leeches = 0;
			$data = $this->con->table('peers')
								->select('seeder')
								->where('userid',$user["id"])
								->get()
								->toArray();
			$logdata = var_export($data,true);
			new Logging("_QUERY_","Query: " . $this->con->getSQL() . "\n" . 
						"Vars: userid: " . $user["id"] . "\n" .
						"Result: " . $logdata
						);
			if($this->con->rowCount() > 0){
				foreach($data as $row){
					$numtorrents++;
					if($row["seeder"] == "yes")
						$seeds++;
					else
						$leeches++;
				}
			}else
				return true;
			$limit = $this->get_torrent_limits($user);
			if(($limit["total"] > 0) &&(($numtorrents >= $limit["total"]) || ($left == 0 && $seeds >= $limit["seeds"]) || ($left > 0 && $leeches >= $limit["leeches"])))
				return false;
		}else
			return true;
	}

	public function checkRatioFake($upthis, $interval, $uid){
		if(($upthis / $interval) > $this->pdonvtracker_conf_arr["config"]["RATIOFAKER_THRESH"]){
			$moduid = 0;
			$txt = "Socket-Announce: Ratiofaker-Tool verwendet!";
			$this->con->insert('modcomments',['added' => date("Y-m-d H:i:s"),
												'userid' => $uid,
												'moduid' => $moduid,
												'txt'	=> $txt
											]
										);
			new Logging("_QUERY_",$this->con->getSQL());
			return true;
		}
		return false;
	}

	public function checkIPLimit($userid, $ip){
		$sql = "SELECT DISTINCT(ip) AS ip FROM peers WHERE userid= ?";
		$data = $this->con->query($sql, [$userid])
							->toArray();
		new Logging("_QUERY_",$this->con->getSQL());
		
		if($this->con->rowCount() == 0)
			return true;

		$count = 0;
		$found = FALSE;
		foreach($data as $row){
			$count++;
			if($row["ip"] == $ip){
				$found = TRUE;
				break;
			}
		}
		if(!$found && $count >= $this->pdonvtracker_conf_arr["config"]["MAX_PASSKEY_IPS"])
			return false;
		return true;
	}

	public function GetNvConfig(){
		$response = file_get_contents($this->pdonvtracker . "/saconf.php?socket=1&operator=admin");
		if($response === FALSE)
			die("Fehler - Die Konfiguration des Trackers konnte nicht geladen werden!");
		$res_arr = json_decode($response, true);
		$na = array();
		foreach($res_arr["config"]["BAN_PEERIDS"] as $banc)
			$na[] = urldecode($banc);
		unset($res_arr["config"]["BAN_PEERIDS"]);
		$res_arr["config"]["BAN_PEERIDS"] = $na;
		$this->pdonvtracker_conf_arr = $res_arr;
	}

	// USER
	public function GetUserDataByPasskey($passkey){
		if(!$this->CheckPasskey($passkey))
			return false;
		$passkey = hex2bin($passkey);
		$data = $this->con->table('users')
						->select('id, uploaded, downloaded, class, tlimitseeds, tlimitleeches, tlimitall')
						->where('passkey',$passkey)
						->get()
						->toArray();
		new Logging("_QUERY_",$this->con->getSQL());
		if($this->con->rowCount() == 0)
			$data = false;
		return $data[0];
	}
	
	private function CheckPasskey($pk){
		if(strlen($pk) % 2 != 0)
			return false;
		return true;
	}

	// TORRENT
	public function GetTorrentDataByInfohash($hash){
		$hhash = bin2hex($hash);
		$sql = "SELECT id, name, category, visible, banned, activated, seeders + leechers AS numpeers, UNIX_TIMESTAMP(added) AS ts FROM torrents WHERE info_hash = ? LIMIT 1";
		$data = $this->con->query($sql, [$hhash])
							->toArray();
		new Logging("_QUERY_",$this->con->getSQL());
		if($this->con->rowCount() == 0)
			$data = false;
		return $data[0];
	}

	private function makeVisible($torrent){
		if($torrent["visible"] != "yes"){
			$this->con->update('torrents',['visible' => 'yes'],['id',$torrent["id"]])->exec();
			new Logging("_QUERY_",$this->con->getSQL());
		}
	}

	public function isTorrentActivated($activated){
		if($activated != "yes")
			return false;
		return true;
	}

/*	private function isTorrentOnlyUpload($tid){
		$qry = $this->con->prepare("SELECT free FROM torrents WHERE id = :id");
		$qry->bindParam(':id', $tid, PDO::PARAM_INT);
		$qry->execute();
		if($qry->rowCount()){
			$data = $qry->Fetch(PDO::FETCH_ASSOC);
			if($data["free"] == "yes")
				return true;
			else
				return false;
		}else
			return false;
	}*/

	public function addSeeder($tid){
		$this->con->update('torrents',['seeders' => 'seeders + 1'],['id',$tid])->exec();
		new Logging("_QUERY_",$this->con->getSQL());
		if($this->con->rowCount() == 0)
			return false;
		return true;
	}

	public function removeSeeder($tid){
		$this->con->update('torrents',['seeders' => 'seeders - 1'],['id',$tid])->exec();
		new Logging("_QUERY_",$this->con->getSQL());
		if($this->con->rowCount() == 0)
			return false;
		return true;
	}

	public function removeLeecher($tid){
		$this->con->update('torrents',['leechers' => 'leechers - 1'],['id',$tid])->exec();
		new Logging("_QUERY_",$this->con->getSQL());
		if($this->con->rowCount() == 0)
			return false;
		return true;
	}

	public function addLeecher($tid){
		$this->con->update('torrents',['leechers' => 'leechers + 1'],['id',$tid])->exec();
		new Logging("_QUERY_",$this->con->getSQL());
		if($this->con->rowCount() == 0)
			return false;
		return true;
	}

	// PEERS
	public function InsertPeer($torrentid, $peer_id, $ip, $port, $uploaded, $downloaded, $left, $userid, $agent, $visible, $banned){
		$connectable = ($this->IsConnectable($ip, $port)) ? "yes" : "no";
		$seeder = ($left == 0) ? "yes" : "no";
		$this->con->insert('peers',
									[
									'torrent' => $torrentid,
									'peer_id' => '' . $peer_id . '',
									'ip' => $ip,
									'port' => '' . $port . '',
									'uploaded' => '' . $uploaded . '',
									'downloaded' => '' . $downloaded . '',
									'to_go' => '' . $left . '',
									'seeder' => $seeder,
									'started' => date("Y-m-d H:i:s"),
									'last_action' => date("Y-m-d H:i:s"),
									'connectable' => $connectable,
									'userid' => $userid,
									'agent' => $agent,
									//'finishedat' => '0',
									'uploadoffset' => '' . $uploaded . '',
									'downloadoffset' => '' . $downloaded . ''
									]);
		new Logging("_QUERY_",$this->con->getSQL());
		// INSERT INTO `peers` (`connectable`, `torrent`, `peed_id`, `ip`, `port`, `uploaded`, `downloaded`, `to_go`, `started`, `last_action`, `seeder`, `userid`, `agent`, `uploadoffset`, `downloadoffset`) 
		// VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		if($this->con->rowCount()){
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
		$d = $this->con->table('peers')
						->select('seeder')
						->where('torrent',$tid)
						->where('peer_id',$pid)
						->get()
						->toArray();
		new Logging("_QUERY_",$this->con->getSQL());
		if($this->con->rowCount()){
			if($d[0]["seeder"] == "yes")
				return true;
			else
				return false;
		}else
			return false;
	}

	public function getPeerLastAccess($tid, $pid){
		$sql = "SELECT UNIX_TIMESTAMP(last_action) AS lastaction FROM peers WHERE torrent = ? AND peer_id = ?";
		$d = $this->con->query($sql, [$tid, $pid])
								->toArray();
		new Logging("_QUERY_",$this->con->getSQL());
		return $d[0]["lastaction"];
	}

	public function getInterval($tid, $pid){
		$interval = time() - $this->getPeerLastAccess($tid, $pid);
		if($interval == 0)
			$interval = 1;
		return $interval;
	}

	public function getPeerStats($tid, $pid){
		$d = $this->con->table('peers')
						->select('uploaded, downloaded')
						->where('torrent',$tid)
						->where('peer_id',$pid)
						->get()
						->toArray();
		new Logging("_QUERY_",$this->con->getSQL());
		return $d[0];
	}

	public function GetPeers($tid, $rsize, $ownip){
		//$sql = "SELECT seeder, peer_id, ip, port FROM peers WHERE torrent = ? AND connectable = 'yes' AND ip != ? ORDER BY RAND() LIMIT ?";
		//$sql = "SELECT seeder, peer_id, ip, port FROM peers WHERE torrent = ? AND ip != ? ORDER BY RAND() LIMIT ?";
		//$data = $this->con->query($sql, [$tid, '' . $ownip . '', $rsize])
		//						->toArray();
		$data = $this->con->table('peers')
					->select('peer_id, ip, port, seeder')
					->where('torrent',$tid)
					->limit($rsize)
					->get()
					->toArray();
		$logdata = var_export($data,true);
		new Logging("_QUERY_","Query: " . $this->con->getSQL() . "\n" . 
					"Vars: torrentid: " . $tid . ", ownip: " . $ownip . ", rsize: " . $rsize . "\n" .
					"Result: " . $logdata
					);
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

	//buggy
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
		$sql = "UPDATE peers SET uploaded = ?, downloaded = ?, to_go = ?, last_action = ?, seeder = ? " . $attach . "WHERE torrent = ? AND peer_id = ?";
		$items = [$uploaded,$downloaded,$left,date("Y-m-d H:i:s"),$new_seeder,$torrent["id"],$peer_id];
		$this->con->query($sql, $items);
		new Logging("_QUERY_",$this->con->getSQL());
		if($this->con->rowCount() && $seeder != $new_seeder){
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
		$interval = $this->getInterval($torrent["id"], $peer_id);
		if($this->checkRatioFake($uthis, $interval, $user["id"]) !== false){
			$dthis += $uthis;
			$uthis = 0;
		}
		$this->updateTraffic($dthis,$uthis,$seeder,$interval,$user["id"],$torrent["id"]);
	}

	public function Completed($torrent_info, $user_info){
		$this->con->update('torrents',['times_completed' => 'times_completed + 1'])
										->where('id', $torrentid)
										->exec();
		new Logging("_QUERY_",$this->con->getSQL());
		if(!$this->con->rowCount())
			return false;
		$this->con->insert('completed',
								['user_id' => $user_info["id"],
								'torrent_id' => $torrent_info["id"],
								'torrent_name' => $torrent_info["name"],
								'torrent_category' => $torrent_info["category"],
								'complete_time' => date("Y-m-d H:i:s")
							]);
		new Logging("_QUERY_",$this->con->getSQL());
		if(!$this->con->rowCount())
			return false;
		return true;
	}

	public function updateTraffic($download, $upload, $seeder, $interval, $userid, $torrentid){
		if($seeder == "yes"){
			$sql = "UPDATE `traffic` SET `downloaded`=`downloaded`+ ?, `uploaded`=`uploaded`+ ?, `uploadtime`=`uploadtime`+ ? WHERE `userid`= ? AND `torrentid`= ?";
			$items = [$download,$upload,$interval,$userid,$torrentid];
		}else{
			$sql = "UPDATE `traffic` SET `downloaded`=`downloaded`+ ?, `uploaded`=`uploaded`+ ?, `downloadtime`=`downloadtime`+ ?,`uploadtime`=`uploadtime`+ ? WHERE `userid`= ? AND `torrentid`= ?";
			$items = [$download,$upload,$interval,$interval,$userid,$torrentid];
		}

		$this->con->query($sql, $items);
		new Logging("_QUERY_",$this->con->getSQL());
//		if($this->isTorrentOnlyUpload($torrent["id"]) !== false)
//			$download = 0;
		$this->con->update('users',[
									'uploaded' => 'uploaded + ' . $upload,
									'downloaded' => 'downloaded + ' . $download
									])
								->where('id', $userid)
								->exec();
		new Logging("_QUERY_",$this->con->getSQL());
	}

	public function createTrafficLog($userid, $torrentid){
		$this->con->table('traffic')
						->where('userid', $userid)
						->where('torrentid', $torrentid)
						->get();
		new Logging("_QUERY_",$this->con->getSQL());
		if(!$this->con->rowCount()){
			$this->con->insert('traffic',
									['userid' => $userid,
									'torrentid' => $torrentid
									]);
			new Logging("_QUERY_",$this->con->getSQL());
		}
	}
	
	public function DeletePeer($torrentid, $ip, $left){
		$this->con->delete('peers')
				->where('torrent',$torrentid)
				->where('ip',$ip)
				->exec();
		if($this->con->rowCount()){
			if($left == 0)
				$this->removeSeeder($torrentid);
			else
				$this->removeLeecher($torrentid);			
		}else
			return false;
		return true;
	}

	public function insertStartLog($userid, $torrentid, $ip, $peer_id, $useragent){
		$this->con->insert('startstoplog',
									['userid' => $userid,
									'event' => 'start',
									'datetime' => date("Y-m-d H:i:s"),
									'torrent' => $torrentid,
									'ip' => $ip,
									'peerid' => '' . $peer_id . '',
									'useragent' => $useragent
									]);
		$logdata = var_export([$userid, $torrentid, $ip, $peer_id, $useragent], true);
		new Logging("_QUERY_","Query: " . $this->con->getSQL() . "\n" .
					"Result: " . $logdata .
					"Lastid: " . $this->con->lastId()
					);
	}

	public function insertStopLog($userid, $torrentid, $ip, $peer_id, $useragent){
		$this->con->insert('startstoplog',
									['userid' => $userid,
									'event' => 'stop',
									'datetime' => date("Y-m-d H:i:s"),
									'torrent' => $torrentid,
									'ip' => $ip,
									'peerid' => '' . $peer_id . '',
									'useragent' => $useragent
									]);
		$logdata = var_export([$userid, $torrentid, $ip, $peer_id, $useragent], true);
		new Logging("_QUERY_","Query: " . $this->con->getSQL() . "\n" .
					"Result: " . $logdata .
					"Lastid: " . $this->con->lastId()
					);
	}

	// scrape
	public function GetScrapeString($hash){
		$hexhash = bin2hex($hash);
		$row = $this->con->table('torrents')
					->select('times_completed, leechers, seeders')
					->where('info_hash',$hexhash)
					->limit(1)
					->get()
					->toArray();
		new Logging("_QUERY_",$this->con->getSQL());
		if($this->con->rowCount() == 1)
			$row = $row[0];
		else
			return false;
		$r = "d5:filesd20:".$hash."d8:completei".$row["seeders"]."e10:downloadedi".$row["times_completed"]."e10:incompletei".$row["leechers"]."eeee";
		return $r;
	}

	// functions
	private function get_wait_time($userid, $torrentid, $only_wait_check = false, $left = -1){
		$sql = "SELECT users.class, users.downloaded, users.uploaded, UNIX_TIMESTAMP(users.added) AS u_added, UNIX_TIMESTAMP(torrents.added) AS t_added, nowait.`status` AS `status` FROM users LEFT JOIN torrents ON torrents.id = ? LEFT JOIN nowait ON nowait.user_id = ? AND nowait.torrent_id = ? WHERE users.id = ?";
		$arr = $this->con->query($sql, [$torrentid, $userid, $torrentid, $userid])
								->toArray();
		new Logging("_QUERY_",$this->con->getSQL());
		$arr = $arr[0];
		if(($arr["status"] != "granted" || ($arr["status"] == "granted" && $left > 0 && $this->pdonvtracker_conf_arr["config"]["NOWAITTIME_ONLYSEEDS"] == "yes")) && $arr["class"] < 5){
			$gigs = $arr["uploaded"] / 1073741824;
			$elapsed = floor((time() - $arr["t_added"]) / 3600);
			$regdays = floor((time() - $arr["u_added"]) / 86400);
			$ratio = (($arr["downloaded"] > 0) ? ($arr["uploaded"] / $arr["downloaded"]) : 1);
			$wait_times = explode("|", $this->pdonvtracker_conf_arr["config"]["WAIT_TIME_RULES"]);
			$wait = 0;
			foreach($wait_times as $rule){
				$rule = explode(":", $rule, 4);
				preg_match("/([0-9]+w)?([0-9]+d)?|([\\*0])?/", $rule[2], $regrule);
				$regruledays = intval($regrule[1])*7 + intval($regrule[2]);
				if(($ratio < $rule[0] || $gigs < $rule[1]) && ($regruledays==0 || ($regruledays>0 && $regdays < $regruledays)))
					$wait = max($wait, $rule[3], 0);
			}
			if($only_wait_check)
				return ($wait > 0);
			return max($wait - $elapsed, 0);
		} 
		return 0;
	}

	private function get_torrent_limits($userinfo){
		$limit = array("seeds" => -1, "leeches" => -1, "total" => -1);
		if($userinfo["tlimitall"] == 0){
			$ruleset = explode("|", $this->pdonvtracker_conf_arr["config"]["TORRENT_RULES"]);
			$ratio = (($userinfo["downloaded"] > 0) ? ($userinfo["uploaded"] / $userinfo["downloaded"]) : (($userinfo["uploaded"] > 0) ? 1 : 0));
			$gigs = $userinfo["uploaded"] / 1073741824;
			$limit = array("seeds" => 0, "leeches" => 0, "total" => 0);
			foreach($ruleset as $rule){
				$rule_parts= explode(":", $rule);
				if($ratio >= $rule_parts[0] && $gigs >= $rule_parts[1] && $limit["total"] <= $rule_parts[4]){
					$limit["seeds"] = $rule_parts[2];
					$limit["leeches"] = $rule_parts[3];
					$limit["total"] = $rule_parts[4];
				}
			}
		}elseif($userinfo["tlimitall"] > 0){
			$limit["seeds"] = $userinfo["tlimitseeds"];
			$limit["leeches"] = $userinfo["tlimitleeches"];
			$limit["total"] = $userinfo["tlimitall"];
		}
		return $limit;
	}

	private function IsConnectable($ip, $port){
		if(isset($ip, $port) && !$this->portblacklisted($port)){
			$ipversion = strpos($ip, ":") === false ? 4 : 6;
            if($ipversion == 4)
				$sockres = fsockopen($ip, $port, $errno, $errstr, 5);
			elseif($ipversion == 6)
				$sockres = fsockopen('[' . $ip . ']', $port, $errno, $errstr, 5);
			if(!$sockres)
				return false;
			else{
				fclose($sockres);
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
		$page = "Was willst Du hier?\n+\n+\n+\n+\n+\n";
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