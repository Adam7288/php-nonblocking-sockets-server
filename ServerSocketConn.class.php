<?php 
/* 
	2020 Adam Carpentieri, Web Ventures LLC
	Class for non-blocking socket connections as used by server socket 
	Needs to run in a loop in order to handle connections properly.
	Recommended no blocking requests in the thread handling this loop or else the socket server can get into degraded state
*/
class ServerSocketConn {
	
	private $conn;
	private $connOpen = true;
	private $peer;
	
	private $requestData = '';
	private $responseData = '';
	private $bytesWritten = 0;

	public function __construct($conn, $peer) {
		
		$this->conn = $conn;
		$this->peer = $peer;
	}
	
	public function getRequest() {
		
		$this->requestData .= stream_get_contents($this->conn);
		
		$requestData = $this->getRequestData();
		if($requestData === false)
			return false;
			
		return $requestData;
	}
	
	private function getRequestDataLength() {
		
		if(empty($this->requestData) || strstr($this->requestData, "\n") === false)
			return false;
		
		$length = strtok($this->requestData, "\n");
		if(empty($length) || !is_numeric($length))
			return false;
			
		return $length;
	}
	
	private function getRequestData() {
		
		if(!($length = $this->getRequestDataLength()))
			return false;
			
		$requestData = substr($this->requestData, strlen($length . "\n"));
		
		if(strlen($requestData) != $length)
			return false;
			
		return $requestData;
	}
	
	public function isRequestReceived() { return $this->getRequestData() == false ? false: true; }
	
	public function setResponseData(string $data) { $this->responseData = $data; }
	
	public function isResponseReady() { return !empty($this->responseData); }
	
	public function sendResponse() { //need to keep track of how much written + use stream func
		
		if(!$this->isResponseReady() || !$this->connOpen)
			return false;
		
		if(($bytesWritten = @fwrite($this->conn, substr($this->responseString(), $this->bytesWritten))) !== false)
			$this->bytesWritten += $bytesWritten;
		
		return $this->isResponseSent();
	}
	
	public function isResponseSent() { return $this->bytesWritten == strlen($this->responseString()); }
	
	private function responseString() { return strlen($this->responseData) . "\n" . $this->responseData; }
	
	public function getPeer() { return $this->peer; }
	
	public function getConn() { return $this->conn; }
	
	public function isClosed() { return feof($this->conn); }
	
	public function closeConn() { 
		
		if(!$this->connOpen)
			return;
		
		fclose($this->conn); 
		$this->connOpen = false;
	}
	
	public function __destruct() {
		
		$this->closeConn();	
	}	
}

?>
