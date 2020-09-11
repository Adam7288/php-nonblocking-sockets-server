<?php 
/* 
	2020 Adam Carpentieri, Web Ventures LLC
	Class for non-blocking socket connections as used by client  
*/
class ClientSocket {
	
	private $null = NULL;
	
	private $waitTimeout = 300; //default - should be set
	private $replyData;
	private $requestData;
	private $conn;
	private $timer;
	
	private $connOpen = true;
	
	public function __construct($server, $port, $connectionTimeout=30) {
		
		$this->timer = new Timer();
		
		$this->conn = stream_socket_client("tcp://$server:$port", $errno, $errstr, $connectionTimeout);
		
		if(!$this->conn) {
			$this->connOpen = false;
			throw new Exception("Could not connect");
		}
		
		stream_set_blocking($this->conn, 0);
	}
	
	public function sendRequest(string $request, $raw=false) {
		
		$this->requestData = $request;
		
		if(!$this->connOpen)
			return;
		
		$requestString = $raw ? $request : strlen($request) . "\n" . $request;
		$totalBytesWritten = 0;
		
		while(true) {
			
			$timeout = round($this->waitTimeout - $this->timer->getTotalTime());
			if($timeout < 1)
				return false;
				
			$timeout_sec = floor($timeout);
			$timeout_usec = ($timeout - $timeout_sec) * 1000000;
			
			$write = array($this->conn);
			
			if(($numStreams = stream_select($null, $write, $null, $timeout_sec, $timeout_usec)) === false)
				return false;
			
			else if($numStreams) {
				
				if(($bytesWritten = @fwrite($this->conn, substr($requestString, $bytesWritten))) !== false)
					$totalBytesWritten += $bytesWritten;
				
				if($totalBytesWritten == strlen($requestString))
					break;
			} 				
		}
		
		return true;
	}
	
	public function getDataOnLine($timeout) {
		
		$timeout = !empty($timeout) ? $timeout : round($this->waitTimeout - $this->timer->getTotalTime());
		if($timeout < 1)
			return false;

		$timeout_sec = floor($timeout);
		$timeout_usec = ($timeout - $timeout_sec) * 1000000;

		$read = array($this->conn);

		$numStreams = @stream_select($read, $null, $null, $timeout_sec, $timeout_usec);

		if($numStreams === false)
			return false;

		if($numStreams)
			return stream_get_contents($this->conn);
		
		return false;
	}
	
	public function getReply() { 
		
		$replyData = $this->getReplyData();
		if($replyData !== false)
			return $replyData;
		
		while(true) {
		
			$timeout = round($this->waitTimeout - $this->timer->getTotalTime());
			if($timeout < 1)
				return false;
				
			$timeout_sec = floor($timeout);
			$timeout_usec = ($timeout - $timeout_sec) * 1000000;
			
			$read = array($this->conn);
			
			for($i=0; $i<10; $i++) {
				
				if(($numStreams = @stream_select($read, $null, $null, $timeout_sec, $timeout_usec)) !== false)
					break;
			}
			
			if($numStreams === false)
				return false;
			
			if($numStreams) {
				
				$this->replyData .= stream_get_contents($this->conn);
				if($this->getReplyData() !== false)
					break;
			} 				
		}
			
		$this->closeConn();	

		return $this->getReplyData();
	}
	
	private function getReplyDataLength() {
		
		if(empty($this->replyData) || strstr($this->replyData, "\n") === false)
			return false;
		
		$length = strtok($this->replyData, "\n");
		if(empty($length) || !is_numeric($length))
			return false;
			
		return $length;
	}
	
	private function getReplyData() {
		
		if(!($length = $this->getReplyDataLength()))
			return false;
			
		$replyData = substr($this->replyData, strlen($length . "\n"));
		
		if(strlen($replyData) != $length)
			return false;
			
		return $replyData;
	}
	
	public function closeConn() { 
		
		if(!$this->connOpen)
			return false;
	
		fclose($this->conn); 
		$this->connOpen = false;
	}
	
	public function isClosed() { 
		
		if(!$this->connOpen)
			return true;
		
		if(feof($this->conn)) {
			$this->closeConn();
			return true;
		}
		
		return false;	
	}
	
	public function setWaitTimeout($waitTimeout) { 
	
		$this->waitTimeout = $waitTimeout;
	}
	
	public function setPeer($peer) { $this->peer = $peer; }
	
	public function getPeer() { return $this->peer; }
	
	public function __destruct() {
		
		$this->closeConn();
	}	
}

?>