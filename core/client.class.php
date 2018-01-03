<?php

class Client
{
	protected $socket;
	protected $ip;
	public static $static_client_ip;
	protected $port;
	protected $hostname;
	protected $server_clients_index;

	public function __construct(&$socket,$i){
		$this->server_clients_index = $i;
		$this->socket = socket_accept($socket);
		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_getpeername($this->socket, $ip ,$port);
		$this->ip = $ip;
		self::set_client_ip($ip);
		$this->port = $port;
		SocketServer::debug("----------------------------------------------------------");
		SocketServer::debug("Benutzer verbunden (" . $this->ip . ":" . $this->port . ")");
	}

	public function lookup_hostname(){
		$this->hostname = gethostbyaddr($this->ip);
		return $this->hostname;
	}

	public static function get_client_ip(){
		return self::$static_client_ip;
	}

	public static function set_client_ip($ip){
		self::$static_client_ip = $ip;
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