<?php

class log {

	var $logfile = "";

	function log($logfile) {

		if(!is_dir($logfile["directory"])) {
			@mkdir($logfile["directory"], 0755);
		}

		$this->logfile = $logfile["directory"]."/".$logfile["server_pre"]."_".date("Y_m_d",time()).".".$logfile["extension"];

		@fopen($this->logfile, "ab");

	}

	function header($msg) {

		$temp = "WRITE: ".str_replace("\n","\r\n",$msg);

		return $temp;

	}

	function msg($msg, $stop=false, $print=true) {

		$open = @fopen($this->logfile, "ab");

		if($open) {

			fwrite($open, $msg);
			fclose($open);

		} else {

			die("ERROR (".date("d.m.Y H:i:s",time())."): logfile doesn't exist");

		}

		if($print) {
			print trim($msg)."\n";
		}

		if($stop) {

			$this->msg("SERVER (".date("d.m.Y H:i:s",time())."): Server stopped ...\r\n");
			exit;

		}

	}

}

?>