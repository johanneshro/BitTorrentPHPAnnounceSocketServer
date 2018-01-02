<?php

class log
{
	public $logfile = "";

	public function __construct() {
		$logfile["directory"] = "logs";
		$logfile["extension"] = "txt";
		if(!is_dir($logfile["directory"])) {
			@mkdir($logfile["directory"], 0755);
		}
		$this->logfile = $logfile["directory"]."/log_".date("Y_m_d",time()).".".$logfile["extension"];
		@fopen($this->logfile, "ab");
	}

	public function header($msg) {
		$temp = "WRITE: ".str_replace("\n","\r\n",$msg);
		return $temp;
	}
	
	private function LogCase($c = "s"){
		switch($c){
			case "s":
				$r = "SERVER";
				break;
			case "c":
				$r = "CLIENT";
				break;
			case "e":
				$r = "ERROR";
				break;
		}
		return $r;
	}

	public function msg($lc, $msg, $stop=false, $print=true) {
		$open = @fopen($this->logfile, "ab");
		if($open) {
			fwrite($open, $msg);
			fclose($open);
		} else {
			die($this->LogCase("e") . $this->GetLogTime(). "logfile doesn't exist");
		}

		if($print) {
			print $this->LogCase($lc) . $this->GetLogTime() . trim($msg)."\n";
		}

		if($stop) {
			$this->msg($this->LogCase("s") ,$this->GetLogTime(). "Server stopped ...\r\n");
			exit;
		}
	}
	
	public function GetLogTime($full = false){
		if($full)
			return " (" . date("d.m.Y H:i:s",time()) . "): ";
		else
			return " (" . date("H:i:s",time()) . "): ";
	}
}

?>