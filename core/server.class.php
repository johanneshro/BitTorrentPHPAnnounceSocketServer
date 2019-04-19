<?php

class SocketServer
{
	protected $config;
	protected $ofswitch;
	public static $debugging = true;
	protected $master_socket;
	public $max_clients = 100;
	public $max_read = 2048;
	public $clients;
	public $rt;
	public static $rtr;

	public function __construct($bind_ip, $port){
		set_time_limit(0);
		//check for domain
		if(ip2long($bind_ip) === false){
			$bind_ip = gethostbyname($bind_ip);
			if(ip2long($bind_ip) === false)
				die("Fehler! - Es konnte keine gültige IP generiert werden!");
		}
		$this->config["ip"] = $bind_ip;
		$this->config["port"] = $port;
		$this->ofswitch = true;
		$this->master_socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_bind($this->master_socket,$this->config["ip"],$this->config["port"]);
		socket_getsockname($this->master_socket,$bind_ip,$port);
		socket_listen($this->master_socket);
		socket_set_nonblock($this->master_socket);
		SocketServer::debug("Server (" . $bind_ip . ":" . $port . ") gestartet");
	}

	public function infinite_loop(){
		//$test = true;
		do{
			$test = $this->loop_once();
		}
		while($this->ofswitch);
	}

	public function loop_once(){
		$read[0] = $this->master_socket;
		for($i = 0; $i < $this->max_clients; $i++){
			if(isset($this->clients[$i])){
				$read[$i + 1] = $this->clients[$i]->socket;
			}
		}
		$write = NULL;
		$except = NULL;
		$tv_sec = 5;
		if(socket_select($read, $write, $except, $tv_sec) < 1){
			return true;
		}

		// Neue Verbindung
		if(in_array($this->master_socket, $read)){
			for($i = 0; $i < $this->max_clients; $i++){
				if(empty($this->clients[$i])){
					$temp_sock = $this->master_socket;
					if(self::$debugging)
						$this->rt = new runtime();
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
						//SocketServer::debug($i . "@" . $this->clients[$i]->ip . " --> " . $input);
						$response = new Response($input,$this->clients[$i]->ip);
						$response_str = $response->get_response_string();
						$res_arr = explode(":|:", $response_str);
						if(isset($res_arr[1])){
							if($res_arr[0] == "kill" && $res_arr[1] == "admin"){
								SocketServer::send($this->clients[$i]->socket, "Sie haben Kenny getoetet!!!");
								$this->kill();
							}elseif($res_arr[0] == "avgping" && $res_arr[1] == "admin"){
								self::$rtr = $this->rt->get_result();
								SocketServer::send($this->clients[$i]->socket, Runtime::get_avg_ping_str());
								SocketServer::disconnect($this->clients[$i]->server_clients_index);
							}
						}else{
							self::$rtr = $this->rt->get_result();
							SocketServer::send($this->clients[$i]->socket, $response_str);
							SocketServer::disconnect($this->clients[$i]->server_clients_index);
						}
					}
				}
			}
		}
		return true;
	}

	public function disconnect($client_index){
		$i = $client_index;
		SocketServer::debug("Verbindung zu Benutzer (" . $this->clients[$i]->ip . ":" . $this->clients[$i]->port . ") getrennt");
		SocketServer::debug("----------------------------------------------------------");
		$this->clients[$i]->destroy();
		unset($this->clients[$i]);
	}

	public function set_max_clients($i){
		$this->max_clients = $i;
	}

	public static function set_debugging($bool){
		self::$debugging = $bool;
	}

	public static function debug($text){
		if(self::$debugging)
			echo($text . "\r\n");
		else
			echo "";
	}

	public static function send(&$sock, $x){
		SocketServer::debug("<-- " . $x . self::$rtr);
		cli_set_process_title("NetVision-Announce :: REQ" . self::$rtr);
		$header = "HTTP/1.1 200 OK\n";
		$header .= "Server: PHP Socket Server\n";
		$header .= "Content-Type: Text/Plain\n";
		$header .= "Pragma: no-cache\n";
		$header .= "Connection: close\n\n";
		$header .= trim($x);
		@socket_write($sock, $header, strlen($header));
	}

	private function kill(){
		foreach($this->clients as $k => $client){
			$client->destroy();
			unset($this->clients[$k]);
		}
		$this->ofswitch = false;
		@socket_close($this->master_socket);
	}

	function &__get($name){
		return $this->{$name};
	}
}
?>