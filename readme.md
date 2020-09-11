## Server Usage
```php
  try {
    $socketServer = new ServerSocket($port, $handleRequest);
  } catch (Exception $e) {
     //exception handling
  }
  while(true) {

    $socketServer->checkForIncoming(.1);
    $socketServer->readAndWrite(.1);

    //do stuff
  }
```
## Client Usage
```php
    try { 
      $connection = new ClientSocket('localhost', $serverPort, 10);
    } catch(Exception $e) {
      //exception handling
    }

    $connection->setWaitTimeout($timeout);
    $sendResult = $connection->sendRequest(json_encode($data));

    if($sendResult === false) {

      $connection->closeConn();
      return false;
    }

    $reply = $connection->getReply();
    $connection->closeConn();
```
