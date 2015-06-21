<?php

namespace Io\Samk\TracingDemo;

use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Application();

/**
 * Connect to Db
 */
$app->register(
    new DoctrineServiceProvider(),
    array(
        'db.options' => array(
            'driver' => 'pdo_mysql',
            'dbname' => 'tracing_app',
            'host' => '127.0.0.1',
            'user' => 'root',
            'password' => '',
            'charset' => 'UTF8'
        ),
    )
);


function resourceResponse($payload)
{
    $response = new Response();
    $response->headers->set('Content-Type', 'application/json');
    if($payload) {
        $response->setContent(json_encode($payload));
        $response->setStatusCode(200);
    } else {
        $response->setContent(json_encode([
            'statusCode' => 404,
            'error' => 'Not Found'
        ]));
        $response->setStatusCode(404);
    }
    return $response;
}


$app->get(
    '/',
    function () use ($app) {
        return 'Hello This is a Test App';
    }
);

$app->get(
    '/payloads/{payloadId}',
    function ($payloadId) use ($app) {

        $sql = "SELECT * FROM `payloads` WHERE `id` = ?";
        $payload = $app['db']->fetchAssoc($sql, array((int)$payloadId));

        return resourceResponse($payload);

    }
);

$app->get(
    '/hello/{name}',
    function ($name) use ($app) {
        return 'Hello ' . $app->escape($name);
    }
);

return $app;