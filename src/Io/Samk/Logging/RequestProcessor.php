<?php

namespace Io\Samk\Logging;

use Silex\Application;

/**
 * Class RequestProcessor
 * @package Io\Samk\Logging
 */
class RequestProcessor
{
    /**
     * @var Application
     */
    private $app;
    /**
     * @var TracingRequest
     */
    private $requestTrace;

    /**
     * @param Application $app
     * @param null $previousToken
     */
    public function __construct(Application $app, $previousToken = null)
    {
        $this->app = $app;
        $this->requestTrace = TracingRequest::getInstance();
    }

    /**
     * @param array $record The Logging Record
     * @return array
     */
    public function __invoke(array $record)
    {
        $record['extra']['token'] = $this->requestTrace->getTraceId();
        $record['extra']['time'] = microtime(true);

        return $record;
    }
}
