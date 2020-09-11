<?php 
/* 
	2020 Adam Carpentieri, Web Ventures LLC
	Class for non-blocking server connection paired with ServerSocketConn class
	
	TODO
	1. Disconnect serversocketconn's after timeout period
	2. Make worker server and fax queue server OOP - no need for callback   
*/

class ServerSocket {
	
	private $null = NULL;
	
	private $socket;
	private $port;
	private $connections = array();
	
	private $read = array();
	private $write = array();
	
	private $serverOpen = true;
	private $requestHandler;
	
	public function __construct($port, $requestHandler) {
		
		if(!is_numeric($port) || !is_callable($requestHandler)) 
			throw new Exception("Bad parameters provided");
		
		$this->port = $port;
		$this->requestHandler = $requestHandler;
		$this->socket = stream_socket_server("tcp://0.0.0.0:$port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
		
		if(!$this->socket) {
			$this->serverOpen = false;
			throw new Exception("Could not connect");
		}
		
		stream_set_blocking($this->socket, 0);	
	}
	
	public function checkForIncoming($timeout=10) {
		
		$metaData = stream_get_meta_data($this->socket);
		if($metaData['timed_out'])
			error_log('server socket timed out: ' . $this->port);
		
		if ($c = @stream_socket_accept($this->socket, empty($this->connections) ? $timeout : 0, $peer)) {
		
			stream_set_blocking($c, 0);
			
			$this->connections[$peer] = new ServerSocketConn($c, $peer);
		}
	}
	
	public function readAndWrite($timeout=5) {
		
		$metaData = stream_get_meta_data($this->socket);
		if($metaData['timed_out'])
			error_log('server socket timed out: ' . $this->port);
		
		if(!count($this->connections))
			return;
		
		$read = $this->getConns('all');
		$write = $this->getConns('write');
		
		$timeout_sec = floor($timeout);
		$timeout_usec = ($timeout - $timeout_sec) * 1000000;
		
		if (stream_select($read, $write, $null, $timeout_sec, $timeout_usec) === false)
			return false;
		
		$this->readData($read);
		$this->writeData($write);
		
		
		
		return true;
	}
	
	private function readData($read) {
		
		foreach($read as $c) {
			
			$peer = stream_socket_get_name($c, true);

			if(!($connection = $this->getConnFromPeer($peer)))
				continue;
				
			if($connection->isClosed()) {
			
				unset($this->connections[$peer]);
				continue;
			}
			
			if($connection->isRequestReceived()) //already received everything - should just be waiting
				continue;
				
			$connection->getRequest();
			
			if($connection->isRequestReceived()) {
				
				$handler = $this->requestHandler;
				
				if($connection->isClosed()) { //async request
					
					$handler($connection->getRequest());
					unset($this->connections[$peer]);
				}
				else {
					if($handler($connection->getRequest(), $connection) === false)
						unset($this->connections[$peer]);
				}
			}
		}
	}
	
	private function writeData($write) {
		
		foreach($write as $c) {
			
			$peer = stream_socket_get_name($c, true);
			
			if(!($connection = $this->getConnFromPeer($peer)))
				continue;
				
			$connection->sendResponse();
			
			if($connection->isResponseSent())
				unset($this->connections[$peer]);
		}
	}
	
	private function getConns($type='all') {
		
		$conns = array();
		foreach($this->connections as $connection) {
		
			if($type == 'all' || ($type == 'write' && $connection->isResponseReady()))
				$conns[] = $connection->getConn();
		}
			
		return $conns;
	}
	
	private function getConnFromPeer($peer) {
		
		if(!isset($this->connections[$peer]))
			return false;
		
		return $this->connections[$peer];
	}

	public function closeServer() { 
		
		if(!$this->serverOpen)
			return;
		
		fclose($this->socket); 
		$this->serverOpen = false;
	}
	
	public function __destruct() {
		
		$this->closeServer();	
	}	
}

?>