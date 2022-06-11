<?php

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\StaticContent\DocumentRoot;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\Server;
use Amp\Success;
use Amp\Websocket\Client;
use Amp\Websocket\Message;
use Amp\Websocket\Server\ClientHandler;
use Amp\Websocket\Server\Gateway;
use Amp\Websocket\Server\Websocket;
use Monolog\Logger;
use function Amp\ByteStream\getStdout;
use function Amp\call;

require __DIR__ . "/vendor/autoload.php";

$port = 6001;

$websocket = new Websocket(new class implements ClientHandler
{
    private const ALLOWED_ORIGINS = [
        "http://localhost:6001",
        "http://127.0.0.1:6001",
        "http://[::1]:6001"
    ];

    public function handleHandshake(Gateway $gateway, Request $request, Response $response): Promise
    {
        if (!\in_array($request->getHeader("origin"), self::ALLOWED_ORIGINS, true)) {
            return $gateway->getErrorHandler()->handleError(403);
        }

        return new Success($response);
    }

    public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): Promise
    {
        return call(function () use ($gateway, $client): \Generator {
            while ($message = yield $client->receive()) {
                \assert($message instanceof Message);
                $gateway->broadcast(\sprintf(
                    "%d: %s",
                    $client->getId(),
                    yield $message->buffer()
                ));
            }
        });
    }
});

Loop::run(function () use ($websocket, $port): Promise {

    $sockets = [
        Server::listen("127.0.0.1:$port"),
        Server::listen("[::1]:$port"),
    ];

    $router = new Router;
    $router->addRoute("GET", "/broadcast", $websocket);
    $router->setFallback(new DocumentRoot(__DIR__ . "/assets"));

    $logHandler = new StreamHandler(getStdout());
    $logHandler->setFormatter(new ConsoleFormatter);
    $logger = new Logger("server");
    $logger->pushHandler($logHandler);


    $server = new HttpServer($sockets, $router, $logger);

    return $server->start();
});
