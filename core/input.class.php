<?php

class Input
{
	private $o_input;
	protected $headerdata;
	protected $split_status;
	protected $method;
	protected $useragent;
	protected $info_hash;
	protected $peer_id;
	protected $peer_port;
	protected $passkey;
	protected $request;
	protected $request_mode;
	protected $log;

	public function __construct($input){
		$this->log = new log();
		$this->o_input = $input;
		$input = trim($input);
		$split = explode("\n", $input);
		//preg_match("=([a-z]{1,} /)(.*)( http/1.[01]{1})=i", $split[0], $temp);
		$this->headerdata = $this->http_parse_headers($input);
		//$split_2 = $this->http_parse_headers($input);
		$this->split_status = explode(" ", $this->headerdata["status"]);
		// $_GET
		$this->request = $this->parse_query_string($this->split_status[1]);
		$this->method = (isset($this->split_status[0]) && !empty($this->split_status[0])) ? trim($this->split_status[0]) : "GET";
		$this->useragent = (isset($this->headerdata["User-Agent"]) && !empty($this->headerdata["User-Agent"])) ? trim($this->headerdata["User-Agent"]) : "";
		if($this->method != "GET")
			$this->method = false;
		if(isset($this->split_status[1]) && substr($this->split_status[1],0,9) == "/announce")
			$this->request_mode = "announce";
		elseif(isset($this->split_status[1]) && substr($this->split_status[1],0,7) == "/scrape")
			$this->request_mode = "scrape";
		else
			$this->request_mode = false;
		$this->info_hash = $this->checkReq("info_hash", true);
		$this->peer_id = $this->checkReq("peer_id", true);
		$this->peer_port = $this->checkReq("port");
		$this->passkey = $this->checkReq("passkey");
		// anfrage loggen
		if(count($this->request) > 0){
			$this->log->msg("c", "REQUEST: ".var_export($this->request,true)."\r\n");
		}
		
	}

	private function checkReq($key, $strlen_check=false){
		if(!isset($this->request[$key]))
			return false;
		elseif(!is_string($this->request[$key]))
			return false;
		elseif($strlen_check && strlen($this->request[$key]) != 20)
			return false;
		elseif(strlen($this->request[$key]) > 128)
			return false;
		return $this->request[$key];
	}

	private function http_parse_headers($headers){
		$headers = str_replace("\r", "", trim($headers));
		$headers = explode("\n", $headers);
		foreach($headers AS $value){
			$header = explode(": ",$value);
			if($header[0] && empty($header[1])) {
				$headerdata['status'] = $header[0];
				if(preg_match("|^HTTP/[^\s]*\s(.*?)\s|", $header[0], $status)){
					$headerdata['statuscode'] = $status[1];
				}
			}elseif($header[0] && $header[1]){
				$headerdata[$header[0]] = $header[1];
			}
		}
		return $headerdata;
	}

	private function escape_string($string) {
		$replacements = array(
			"\x00"=>'\x00',
			"\n"=>'\n',
			"\r"=>'\r',
			"\\"=>'\\\\',
			"'"=>"\'",
			'"'=>'\"',
			"\x1a"=>'\x1a'
		);
		return strtr($string, $replacements);
	}

	private function parse_query_string($url) {
		$val = array();
		$ex = explode("?", $url);
		$str = (isset($ex[1])) ? $ex[1] : false;
		if($str) {
			$a = explode("&", $str);
			foreach($a AS $e) {
				if($e) {
					@list($k, $v) = @explode("=", $e);
					if($k == "info_hash" || $k == "peer_id")
						$val[$k] = urldecode($v);
					else
						$val[$k] = $this->escape_string(rawurldecode($v));
				}
			}
		}
		return $val;
	}

	function &__get($name){
		return $this->{$name};
	}
}
?>