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
		foreach($list AS $peer_id => $peer) {
			if($compact) {
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
				if($this->input_obj->request_mode !== false){
					if($this->input_obj->request_mode == "announce"){
						$user_info = $this->nv->GetUserDataByPasskey($this->input_obj->passkey);
						$torrent_info = $this->nv->GetTorrentDataByInfohash($this->input_obj->info_hash);
						$this->nv->InsertPeer($torrent_info["id"], $this->input_obj->peer_id, $this->nv->getip(), $this->input_obj->peer_port, $this->input_obj->uploaded, $this->input_obj->downloaded, $this->input_obj->left, $user_info["id"], $this->input_obj->useragent);
						$pdata = $this->nv->GetPeers($torrent_info["id"], $this->input_obj->numwant, $this->nv->getip());
						$resp = $this->track($pdata["peers"], $pdata["seeders"], $pdata["leechers"], $this->input_obj->compact);
						return $resp;
					}elseif($this->input_obj->request_mode == "scrape"){
						return $this->nv->GetScrapeString($this->input_obj->info_hash);
					}
				}else
					return "d14:failure reason15:invalid request";
			}else
				return "d14:failure reason11:methoderrore";
		}else
			return "d14:failure reason7:defaulte";
	}
}
?>