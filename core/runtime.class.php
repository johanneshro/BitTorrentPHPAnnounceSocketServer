<?php

class runtime
{
	private $start_ts;
	private $end_ts;

	public function __construct(){
		$this->set_start_ts();
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
		$differenz = (int)$differenz;
		return " in " . $differenz . "ms";
	}

	private function set_start_ts(){
		$this->start_ts = $this->unique_ts();
	}

	private function set_end_ts(){
		$this->end_ts = $this->unique_ts();
	}
}
?>