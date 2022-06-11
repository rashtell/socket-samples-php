<?php

//Socket Amphp (Non-blocking)
require __DIR__ . "/vendor/autoload.php";

// Non-blocking server implementation based on amphp/socket keeping track of connections.

use Amp\Loop;
use Amp\Socket\Socket;
use function Amp\asyncCall;


#region Basic TCP Echo Server
Loop::run(function () {
    $uri = "tcp://127.0.0.1:5022";

    $clientHandler = function (Socket $socket) {
        while (null !== $chunk = yield $socket->read()) {
            yield $socket->write($chunk);
        }
    };

    $server = Amp\Socket\Server::listen($uri);

    while ($socket = yield $server->accept()) {
        asyncCall($clientHandler, $socket);
    }
});
#endregion
