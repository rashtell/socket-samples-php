<?php
error_reporting(E_ALL);

/* Permitir al script esperar para conexiones. */
set_time_limit(0);

/* Activar el volcado de salida implícito, así veremos lo que estamo obteniendo
* mientras llega. */
ob_implicit_flush();

$address = '127.0.0.1';
$port = 5003;
$server = null;

//Create WebSocket
if (($server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
  echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
}

socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);

if (socket_bind($server, $address, $port) === false) {
  echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($server)) . "\n";
}

if (socket_listen($server, 5) === false) {
  echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($server)) . "\n";
}

//clients array
$clients = array();

do {
  $read = array();
  $read[] = $server;

  $read = array_merge($read, $clients);

  $write = null;
  $except = null;
  $tv_sec = 5;

  // Set up a blocking call to socket_select
  if (socket_select($read, $write, $except, $tv_sec) < 1) {
    //    SocketServer::debug("Problem blocking socket_select?");
    echo "socket_select() failed: reason: " . socket_strerror(socket_last_error($server)) . "\n";
    continue;
  }

  // Handle new Connections
  if (in_array($server, $read)) {

    $client = null;
    if (($client = socket_accept($server)) === false) {
      echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($server)) . "\n";
      break;
    }

    $request = socket_read($client, 5000);
    preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);

    $clients[] = $client;

    $key = array_keys($clients, $client);
var_dump($read, $clients, $key);
    $key = base64_encode(pack(
      'H*',
      sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
    ));
    
    $headers = "HTTP/1.1 101 Switching Protocols\r\n";
    $headers .= "Upgrade: websocket\r\n";
    $headers .= "Connection: Upgrade\r\n";
    $headers .= "Sec-WebSocket-Version: 13\r\n";
    $headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
    socket_write($clients[0], $headers, strlen($headers));

    /* Enviar instrucciones. */
    $msg = "\nBienvenido al Servidor De Prueba de PHP. \n" .
      "Usted es el cliente numero: {$key[0]}\n" .
      "Para salir, escriba 'quit'. Para cerrar el servidor escriba 'shutdown'.\n";

    socket_write($client, $msg, strlen($msg));
  }

  // Handle Input
  // foreach ($clients as $key => $client) { // for each client       
  //   if (in_array($client, $read)) {
  //     if (false === ($buf = socket_read($client, 2048, PHP_NORMAL_READ))) {
  //       echo "socket_read() failed: reason: " . socket_strerror(socket_last_error($client)) . "\n";
  //       break 2;
  //     }
  //     if (!$buf = trim($buf)) {
  //       continue;
  //     }
  //     if ($buf == 'quit') {
  //       unset($clients[$key]);
  //       socket_close($client);
  //       break;
  //     }
  //     if ($buf == 'shutdown') {
  //       socket_close($client);
  //       break 2;
  //     }
  //     $talkback = "Cliente {$key}: Usted dijo '$buf'.\n";
  //     socket_write($client, $talkback, strlen($talkback));
  //     echo "$buf\n";
  //   }
  // }

  sleep(10);
} while (true);

socket_close($server);
