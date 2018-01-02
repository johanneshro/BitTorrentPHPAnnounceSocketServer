<?php

class Response
{
	protected $input_obj;
	public function __construct($input_str) {
		$this->input_obj = new Input($input_str);
	}

	private function track($list, $complete=0, $incomplete=0, $compact=false) {
		global $_GET;
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
				$pid = (!isset($_GET["no_peer_id"])) ? "7:peer id".strlen($peer_id).":".$peer_id : "";
				$peers[] = "d2:ip".strlen($peer["ip"]).":".$peer["ip"].$pid."4:porti".$peer["port"]."ee";
			}
		}
		$peers = (count($peers) > 0) ? @implode($peers) : "";
		$peers6 = (count($peers6) > 0) ? @implode($peers6) : "";
		$response = "d8:intervali".INTERVAL."e12:min intervali".INTERVAL_MIN."e8:completei".$complete."e10:incompletei".$incomplete."e5:peers".($compact ? strlen($peers).":".$peers."6:peers6".strlen($peers6).":".$peers6 : "l".$peers."e")."e";
		return $response;
	}

	public function get_response_string(){
		if(isset($this->input_obj)){
			if($this->input_obj->method !== false){
				return "d14:failure reason4:worxe";
			}else
				return "d14:failure reason11:methoderrore";
		}else
			return "d14:failure reason7:defaulte";
	}
}
?>