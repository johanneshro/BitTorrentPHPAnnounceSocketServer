<?php

class Response
{
	protected $input_obj;
	protected $nv;
	public function __construct($input_str) {
		$this->input_obj = new Input($input_str);
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
		$response = "d8:intervali1800e12:min intervali30e8:completei".$complete."e10:incompletei".$incomplete."e5:peers".($compact ? strlen($peers).":".$peers."6:peers6".strlen($peers6).":".$peers6 : "l".$peers."e")."e";
		return $response;
	}
	

	public function get_response_string(){
		if(isset($this->input_obj)){
			if($this->input_obj->method !== false){
				if(isset($this->input_obj->peer_port) && ctype_digit($this->input_obj->peer_port) && $this->input_obj->peer_port >= 1 && $this->input_obj->peer_port <= 65535 && !$nv->portblacklisted($this->input_obj->peer_port)){
					if($this->input_obj->request_mode !== false){
						if($this->input_obj->request_mode == "announce"){
							$torrent_info = $this->nv->GetTorrentDataByInfohash($this->input_obj->info_hash);
							if($torrent_info === false)
								return $this->track("Dieser Torrent ist dem Tracker nicht bekannt.");
							$user_info = $this->nv->GetUserDataByPasskey($this->input_obj->passkey);
							if($user_info === false)
								return $this->track("Diesem Passkey ist kein Nutzer zugeordnet.");
							if($this->input_obj->event !== false && $this->input_obj->event == "stopped"){
								$this->nv->DeletePeer($torrent_info["id"], Client::get_client_ip(), $this->input_obj->left);
								return $this->track("Kein Fehler - Torrent gestoppt.");
							}elseif($this->input_obj->event !== false && $this->input_obj->event == "completed"){
								$this->nv->Completed($torrent_info, $user_info);
								$pdata = $this->nv->GetPeers($torrent_info["id"], $this->input_obj->numwant, Client::get_client_ip());
								$resp = $this->track($pdata["peers"], $pdata["seeders"], $pdata["leechers"], $this->input_obj->compact);
								return $resp;
							}elseif($this->input_obj->event !== false && $this->input_obj->event == "started"){
								$this->nv->InsertPeer($torrent_info["id"],$this->input_obj->peer_id,Client::get_client_ip(),$this->input_obj->peer_port,$this->input_obj->uploaded,$this->input_obj->downloaded,$this->input_obj->left,$user_info["id"],$this->input_obj->useragent);
								$pdata = $this->nv->GetPeers($torrent_info["id"], $this->input_obj->numwant, Client::get_client_ip());
								$resp = $this->track($pdata["peers"], $pdata["seeders"], $pdata["leechers"], $this->input_obj->compact);
								return $resp;
							}elseif($this->input_obj->event !== false && $this->input_obj->event == "update"){
								//update peerdata
								$pdata = $this->nv->GetPeers($torrent_info["id"], $this->input_obj->numwant, Client::get_client_ip());
								$resp = $this->track($pdata["peers"], $pdata["seeders"], $pdata["leechers"], $this->input_obj->compact);
								return $resp;
							}else{
								return $this->track("invalid event");
							}
						}elseif($this->input_obj->request_mode == "scrape"){
							return $this->nv->GetScrapeString($this->input_obj->info_hash);
						}elseif($this->input_obj->request_mode == "status"){
							//return "Started: ".date("d.m.Y H:i:s",$started)."\nHits: ".$hits."\nClients: ".count($client));
						}elseif($this->input_obj->request_mode == "landing"){
							return $this->nv->getlandingpage();
						}elseif($this->input_obj->request_mode == "favicon"){
							return "HTTP/1.0 404 Not Found";
						}elseif($this->input_obj->request_mode == "error"){
							return $this->nv->fakefourzerofour();
						}
					}else
						return $this->track("invalid request");
						
				}else
					return $this->track("ungültiger Port");
			}else
				return $this->track("method error");
		}else
			return $this->track("default");
	}
}
?>