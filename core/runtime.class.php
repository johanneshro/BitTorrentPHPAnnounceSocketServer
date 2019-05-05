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

class runtime
{
	private $start_ts;
	private $end_ts;
	public static $data_received = 0;
	public static $data_sended = 0;
	public static $rcount = 0;
	public static $pingcount = 0;
	public static $avgping = 0;
	public static $socketstartts = 0;

	public function __construct(){
		$this->set_start_ts();
		self::$rcount++;
	}

	//https://www.tutorials.de/threads/verstrichene-zeit-in-millisekunden.247017/
	public static function unique_ts(){
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

	public static function set_socket_start_ts(){
		self::$socketstartts = time();
	}

	public static function count_data($type,$size){
		//to kilo
		$size = round($size/1024,3);
		if($type == "IN")
			self::$data_received = self::$data_received+$size;
		elseif($type == "OUT")
			self::$data_sended = self::$data_sended+$size;
	}

	public static function get_socket_start_ts(){
		return self::$socketstartts;
	}

	public static function get_count(){
		return self::$rcount;
	}

	public static function get_avg_ping_str(){
		return self::$avgping . "/" . self::$pingcount . "/" . self::$rcount . "/" . self::$data_received . "/" . self::$data_sended . "/" . self::$socketstartts . "";
	}

	private function set_start_ts(){
		$this->start_ts = self::unique_ts();
	}

	private function set_end_ts(){
		$this->end_ts = self::unique_ts();
	}
}
?>