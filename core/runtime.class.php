<?php

class runtime
{
	private $start_ts;
	private $end_ts;
	public static $rcount = 0;
	public static $pingcount = 0;
	public static $avgping = 0;

	public function __construct(){
		$this->set_start_ts();
		self::$rcount++;
	}

	//https://www.tutorials.de/threads/verstrichene-zeit-in-millisekunden.247017/
	private function unique_ts(){
		$milliseconds = microtime();
		$timestring = explode(" ", $milliseconds);
		$sg = $timestring[1];
		$mlsg = substr($timestring[0], 2, 4);
		$timestamp = $sg.$mlsg;
		return $timestamp;
	}

	public function get_result(){
		$this->set_end_ts();
		$differenz = $this->end_ts-$this->start_ts;
		$differenz = $differenz/10;
		//$differenz = (int)$differenz;
		// die Messung wird vor der Übertragung beendet d.h. der Rückweg wird nicht mitgezählt.
		// "Fix" +30ms
		$differenz = (int)$differenz+30;
		// für 32bit den overflow vermeiden.
		if(self::$pingcount >= 2000000000){
			self::$pingcount = 0;
			self::$rcount = 1;
		}
		self::$pingcount = self::$pingcount+$differenz;
		self::$avgping = round(self::$pingcount/self::$rcount);
		return " in " . $differenz . "ms";
	}

	public static function get_avg_ping(){
		return self::$avgping;
	}

	public static function get_avg_ping_str(){
		return self::$avgping . "/" . self::$pingcount . "/" . self::$rcount;
	}

	private function set_start_ts(){
		$this->start_ts = $this->unique_ts();
	}

	private function set_end_ts(){
		$this->end_ts = $this->unique_ts();
	}
}
?>