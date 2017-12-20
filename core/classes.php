<?php

class log
{

	//public function log($logfile) {
	function __construct($logfile) {

		$this->logfile = $logfile["directory"]."/".$logfile["server_pre"]."_".date("Y_m_d",time()).".".$logfile["extension"];
		@fopen($this->logfile,"w+");

	}

	public function header($msg) {

		$temp = "WRITE: ".str_replace("\n","\r\n",$msg);
		return $temp;

	}

	public function msg($msg,$stop=false,$print=true) {

		$open = @fopen($this->logfile,"a");

		if($open) {

			fwrite($open,$msg);
			fclose($open);
		} else {
			die("ERROR (".date("d.m.Y H:i:s",time())."): logfile doesn't exist");
		}

		if($print) print trim($msg)."\n";

		if($stop) {

			$this->msg("\r\nSERVER (".date("d.m.Y H:i:s",time())."): Server stopped ...");
			exit;
		}
	}

}

?>