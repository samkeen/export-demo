<?php

namespace Io\Samk\TracingDemo;

use Doctrine\DBAL\Connection;
use Io\Samk\Logging\RequestProcessor;
use Io\Samk\Logging\TracingEventListener;
use Io\Samk\Utils\JsonUtil;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Symfony\Component\HttpFoundation\Request;
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

function errorResponse($message, $statusCode)
{
    return new Response(
        json_encode(
            [
                "code" => $statusCode,
                "message" => $message
            ]
        ),
        400,
        ['Content-Type' => 'application/json']
    );
}

function resourceResponse($payload, $statusCode = 200)
{
    $response = new Response();
    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode($payload));
    $response->setStatusCode($statusCode);

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
        $sql = "SELECT * FROM `payloads` WHERE `id` = ?";
        $app['monolog']->addInfo(
            "Attempting retrieval of Payload id={$payloadId}"
             . '#TRACE#{"event":"boundary.enter:persistence:select:payload"}');
        $payload = $app['db']->fetchAssoc($sql, array((int)$payloadId));
        $app['monolog']->addInfo(
            'retrieval of Payload complete #TRACE#{"event":"boundary.return:persistence:select:payload"}');
        if (!$payload) {
            return errorResponse('Not Found', 404);
        }

        return resourceResponse($payload);
    }
);

$app->post(
    '/payloads',
    function (Request $request) use ($app) {
        $payloadJsonString = $request->getContent();
        $sql = "INSERT INTO `payloads` (`payload`, `request_headers`) VALUES (? , ?)";
        $requestHeadersString = json_encode($request->headers->all());
        try {
            $app['monolog']->addInfo('Persisting Payload #TRACE#{"event":"boundary.enter:persistence:insert:payload"}');
            $payload = JsonUtil::decode($payloadJsonString);
            /** @var Connection $conn */
            $conn = $app['db'];
            $conn->executeUpdate($sql, array(json_encode($payload), $requestHeadersString));
            $app['monolog']->addInfo(
                'Persisting Payload complete #TRACE#{"event":"boundary.return:persistence:insert:payload"}');
        } catch (\InvalidArgumentException $e) {
            return errorResponse("The request payload was not valid JSON", 400);
        } catch (\Exception $e) {
            $app['monolog']->addError($e);

            return errorResponse("There was a problem persisting your request payload", 500);
        }

        return resourceResponse("", 204);
    }
);

return $app;
