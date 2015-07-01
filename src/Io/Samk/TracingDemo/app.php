<?php

namespace Io\Samk\TracingDemo;

use Io\Samk\Logging\RequestProcessor;
use Io\Samk\Logging\TracingEventListener;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Symfony\Component\HttpFoundation\Response;

$topDir = realpath(__DIR__ . '/../../../../');
require_once $topDir . '/vendor/autoload.php';

$app = new Application();
/**
 * Register the App Logger
 */
$app->register(
    new MonologServiceProvider(),
    array(
        'monolog.logfile' => $topDir . '/logs/development.log',
        'monolog.level' => Logger::INFO,
        'monolog.name' => 'FileExporterApp'
    )
);
//##############################//
//##### START LOG JUGGLING #####//
// see Io/Samk/Logging          //
/**
 * replace the Handler with one that has our Trace formatter
 * ?? got to be a better way to do this ??
 */
$app['monolog'] = $app->share($app->extend('monolog', function ($monolog) use ($topDir) {
    $handler = new StreamHandler($topDir . '/logs/development.log', Logger::INFO);
    $handler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% #TRACE#%extra%\n"));
    /** @var Logger $monolog */
    $monolog->popHandler();
    $monolog->pushHandler($handler);

    return $monolog;
}));
$app['monolog.listener'] = $app->share(function () use ($app) {
    return new TracingEventListener($app['logger']);
});
$app['logger']->pushProcessor(new RequestProcessor($app));
//###### END LOG JUGGLING ######//
//##############################//

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
    if ($payload) {
        $response->setContent(json_encode($payload));
        $response->setStatusCode(200);
    } else {
        $response->setContent(
            json_encode(
                [
                    'statusCode' => 404,
                    'error' => 'Not Found'
                ]
            )
        );
        $response->setStatusCode(404);
    }

    return $response;
}

$app->get(
    '/info',
    function () use ($app) {
        return phpinfo();
    }
);

$app->get(
    '/',
    function () use ($app) {
        return 'Hello This is a Test App';
    }
);

$app->get(
    '/payloads/{payloadId}',
    function ($payloadId) use ($app) {
        $app['monolog']->addDebug('Testing the Monolog logging.');
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