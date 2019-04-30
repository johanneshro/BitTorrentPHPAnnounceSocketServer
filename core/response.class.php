<?php

class Response
{
	protected $input_obj;
	protected $client_ip;
	protected $nv;
	public function __construct($input_str,$ip) {
		$this->input_obj = new Input($input_str);
		$this->client_ip = $ip;
		$this->nv = $GLOBALS["nv"];
	}

	private function track($list, $complete=0, $incomplete=0, $compact=false) {
		if(is_string($list)) {
			return "d14:failure reason".strlen($list).":".$list."e";
		}
		$peers = $peers6 = array();
		foreach($list AS $peer_id => $peer){
			if($this->input_obj->compact == 1){
				$longip = ip2long($peer["ip"]);
				if($longip) {
					$peers[] = pack("Nn", sprintf("%d", $longip), $peer["port"]);
				} else {
					$peers6[] = pack("H32n", $peer["ip"], $peer["port"]);
				}
			} else {
				$pid = ($this->input_obj->no_peer_id == 1) ? "7:peer id".strlen($peer_id).":".$peer_id : "";
				$peers[] = "d2:ip".strlen($peer["ip"]).":".$peer["ip"].$pid."4:porti".$peer["port"]."ee";
			}
		}
		$peers = (count($peers) > 0) ? @implode($peers) : "";
		$peers6 = (count($peers6) > 0) ? @implode($peers6) : "";
		$response = "d8:intervali" . $this->nv->pdonvtracker_conf_arr["config"]["ANNOUNCE_INTERVAL"] . "e12:min intervali" . ($this->nv->pdonvtracker_conf_arr["config"]["ANNOUNCE_INTERVAL"]/60) . "e8:completei".$complete."e10:incompletei".$incomplete."e5:peers".($compact ? strlen($peers).":".$peers."6:peers6".strlen($peers6).":".$peers6 : "l".$peers."e")."e";
		return $response;
	}
	

	public function get_response_string(){
		if(!empty($this->input_obj)){
			if($this->input_obj->method !== false){
				if($this->input_obj->request_mode !== false){
					if($this->input_obj->request_mode == "announce"){
						// ----------------------------------------------->
						if(!$this->nv->isNotBrowser($this->input_obj->useragent))
							return $this->track("Browser-Clients sind verboten!");
						if(!property_exists($this->input_obj, 'peer_port') || $this->input_obj->peer_port < 1 && $this->input_obj->peer_port > 65535 && $this->nv->portblacklisted($this->input_obj->peer_port))
							return $this->track("ungültiger Port");
						if($this->nv->checkPeerID($this->input_obj->peer_id) === false)
							return $this->track("Du benutzt einen gebannten Client. Bitte lies die FAQ!");
						if($this->nv->checkUseragent($this->input_obj->useragent) === false)
							return $this->track("Du benutzt einen gebannten Client. Bitte lies die FAQ!");
						$torrent_info = $this->nv->GetTorrentDataByInfohash($this->input_obj->info_hash);
						if($torrent_info === false)
							return $this->track("Dieser Torrent ist dem Tracker nicht bekannt.");
						if($this->nv->isTorrentActivated($torrent_info["activated"]) === false)
							return $this->track("Dieser Torrent ist nicht aktiviert.");
						$user_info = $this->nv->GetUserDataByPasskey($this->input_obj->passkey);
						if($user_info === false)
							return $this->track("Diesem Passkey ist kein Nutzer zugeordnet.");
						if($this->nv->checkIPLimit($user_info["id"], $this->client_ip) === false)
							return $this->track("Zu viele unterschiedliche IPs fuer diesen Benutzer!");
						if($this->nv->canInsert($user_info, $this->input_obj->left) === false)
							return $this->track("Maximales Torrent-Limit erreicht!");
						$wait = $this->nv->hasToWait($user_info["id"], $torrent_info["id"], $this->input_obj->left);
						if($wait !== false)
							return $this->track("Wartezeit (noch " . ($wait) . "h) - Bitte lies die FAQ!");
						$this->nv->createTrafficLog($user_info["id"], $torrent_info["id"]);
						// <-----------------------------------------------
						if($this->input_obj->event !== false && $this->input_obj->event == "stopped"){
							$this->nv->insertStopLog($user_info["id"], $torrent_info["id"], $this->client_ip, $this->input_obj->peer_id, $this->input_obj->useragent);
							// buggy
							$this->nv->updatePeer($user_info, $torrent_info, $this->input_obj, $this->nv->checkIsPeerSeeder($torrent_info["id"], $this->input_obj->peer_id));
							$this->nv->DeletePeer($torrent_info["id"], $this->client_ip, $this->input_obj->left);
							return $this->track("Kein Fehler - Torrent gestoppt.");
						}elseif($this->input_obj->event !== false && $this->input_obj->event == "completed"){
							$this->nv->updatePeer($user_info, $torrent_info, $this->input_obj, $this->nv->checkIsPeerSeeder($torrent_info["id"], $this->input_obj->peer_id));
							$pdata = $this->nv->GetPeers($torrent_info["id"], $this->input_obj->numwant, $this->client_ip);
							$resp = $this->track($pdata["peers"], $pdata["seeders"], $pdata["leechers"], $this->input_obj->compact);
							return $resp;
						}elseif($this->input_obj->event !== false && $this->input_obj->event == "started"){
							$this->nv->insertStartLog($user_info["id"], $torrent_info["id"], $this->client_ip, $this->input_obj->peer_id, $this->input_obj->useragent);
							$this->nv->InsertPeer($torrent_info["id"], $this->input_obj->peer_id, $this->client_ip,$this->input_obj->peer_port,$this->input_obj->uploaded,$this->input_obj->downloaded,$this->input_obj->left,$user_info["id"],$this->input_obj->useragent, $torrent_info["visible"], $torrent_info["banned"]);
							$pdata = $this->nv->GetPeers($torrent_info["id"], $this->input_obj->numwant, $this->client_ip);
							$resp = $this->track($pdata["peers"], $pdata["seeders"], $pdata["leechers"], $this->input_obj->compact);
							return $resp;
						}elseif($this->input_obj->event !== false && $this->input_obj->event == "update"){
							// buggy
							$this->nv->updatePeer($user_info, $torrent_info, $this->input_obj, $this->nv->checkIsPeerSeeder($torrent_info["id"], $this->input_obj->peer_id));
							$pdata = $this->nv->GetPeers($torrent_info["id"], $this->input_obj->numwant, $this->client_ip);
							$resp = $this->track($pdata["peers"], $pdata["seeders"], $pdata["leechers"], $this->input_obj->compact);
							return $resp;
						}else{
							return $this->track("invalid event");
						}
					}elseif($this->input_obj->request_mode == "scrape"){
						if(!$this->nv->isNotBrowser($this->input_obj->useragent))
							return $this->track("Browser-Clients sind verboten!");
						return $this->nv->GetScrapeString($this->input_obj->info_hash);
					}elseif($this->input_obj->request_mode == "status"){
						return "Started: ".date("d.m.Y H:i:s", Runtime::get_socket_start_ts())."\nHits: ".Runtime::get_count();
					}elseif($this->input_obj->request_mode == "landing"){
						return $this->nv->getlandingpage();
					}elseif($this->input_obj->request_mode == "favicon"){
						return "HTTP/1.0 404 Not Found";
					}elseif($this->input_obj->request_mode == "control"){
						if($this->input_obj->c_action == "kill" || $this->input_obj->c_action == "avgping")
							return $this->input_obj->c_action . ":|:" . $this->input_obj->operator_pw;
					}elseif($this->input_obj->request_mode == "error"){
						return $this->nv->fakefourzerofour();
					}
				}else
					return $this->track("invalid request");
			}else
				return $this->track("method error");
		}else
			return $this->track("default");
	}
}
?>