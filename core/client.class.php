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

class Client
{
	protected $socket;
	protected $ip;
	protected $port;
	protected $hostname;
	protected $server_clients_index;

	public function __construct(&$socket,$i){
		$this->server_clients_index = $i;
		$this->socket = socket_accept($socket);
		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_getpeername($this->socket, $ip ,$port);
		$this->ip = $ip;
		$this->port = $port;
		SocketServer::debug("----------------------------------------------------------");
		SocketServer::debug("Benutzer verbunden (" . $this->ip . ":" . $this->port . ")");
	}

	public function lookup_hostname(){
		$this->hostname = gethostbyaddr($this->ip);
		return $this->hostname;
	}

	public function destroy(){
		socket_close($this->socket);
	}

	function &__get($name){
		return $this->{$name};
	}

	function __isset($name){
		return isset($this->{$name});
	}
}
?>