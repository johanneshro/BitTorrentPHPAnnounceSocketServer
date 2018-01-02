<?php

class SocketServer
{
	protected $config;
	protected $master_socket;
	public $max_clients = 100;
	public $max_read = 2048;
	public $clients;

	public function __construct($bind_ip,$port){
		set_time_limit(0);
		$this->config["ip"] = $bind_ip;
		$this->config["port"] = $port;
		$this->master_socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_bind($this->master_socket,$this->config["ip"],$this->config["port"]);
		socket_getsockname($this->master_socket,$bind_ip,$port);
		socket_listen($this->master_socket);
		socket_set_nonblock($this->master_socket);
		SocketServer::debug("Server (" . $bind_ip . ":" . $port . ") gestartet");
	}

	public function infinite_loop(){
		$test = true;
		do{
			$test = $this->loop_once();
		}
		while($test);
	}

	public function loop_once(){
		$read[0] = $this->master_socket;
		for($i = 0; $i < $this->max_clients; $i++){
			if(isset($this->clients[$i])){
				$read[$i + 1] = $this->clients[$i]->socket;
			}
		}

		if(socket_select($read,$write = NULL, $except = NULL, $tv_sec = 5) < 1){
			return true;
		}

		// Neue Verbindung
		if(in_array($this->master_socket, $read)){
			for($i = 0; $i < $this->max_clients; $i++){
				if(empty($this->clients[$i])){
					$temp_sock = $this->master_socket;
					$this->clients[$i] = new Client($this->master_socket,$i);
					break;
				}elseif($i == ($this->max_clients-1)){
					SocketServer::debug("Zu viele Benutzer!");
				}
			}
		}

		// Input
		for($i = 0; $i < $this->max_clients; $i++){
			if(isset($this->clients[$i])){
				if(in_array($this->clients[$i]->socket, $read)){
					$input = socket_read($this->clients[$i]->socket, $this->max_read);
					if($input == null){
							$this->disconnect($i);
					}else{
						SocketServer::debug("{$i}@{$this->clients[$i]->ip} --> {$input}");
						//$input_obj = new Input($input);
						//$response = new Response($input_obj);
						//SocketServer::track_print($this->clients[$i]->socket, $response);
						SocketServer::track_print($this->clients[$i]->socket);
					}
				}
			}
		}
		return true;
	}

	public function disconnect($client_index){
		$i = $client_index;
		SocketServer::debug("Verbindung zu Benutzer " . $i . " (" . this->clients[$i]->ip . ":" . $this->clients[$i]->port . ") getrennt");
		$this->clients[$i]->destroy();
		unset($this->clients[$i]);			
	}

	public static function debug($text){
		echo("{$text}\r\n");
	}

	public static function track_print(&$sock, $x = "d14:failure reason7:defaulte"){
		SocketServer::debug("<-- " . $x . "");
		$header = "HTTP/1.1 200 OK\n";
		$header .= "Server: PHP Socket Server\n";
		$header .= "Content-Type: Text/Plain\n";
		$header .= "Pragma: no-cache\n";
		$header .= "Connection: close\n\n";
		$header .= trim($x);
		return socket_write($sock, $header, strlen($header));
	}

	function &__get($name){
		return $this->{$name};
	}
}
?>