<?php

namespace Io\Samk\Logging;

/**
 * Class TracingRequest
 * @package Igniter\TracingLogBundle
 *
 * Singleton to maintain the Trace state for a given Request
 */
class TracingRequest
{

    /**
     * @var string
     */
    private $requestTraceId = '----';

    /**
     * @return TracingRequest
     */
    public static function getInstance()
    {
        static $instance = null;
        if (null === $instance) {
            $instance = new static();
        }

        return $instance;
    }

    /**
     * Create to trace identifier for this request
     * @param string $previousTraceId This is intended to be a trace id from the inbound request, if one exists.
     */
    public function init($previousTraceId = '')
    {
        $previousTraceId = trim($previousTraceId) == '' ? '' : "{$previousTraceId}:";
        $this->requestTraceId = $previousTraceId . $this->generateTraceToken();
    }

    /**
     * @return string
     * @todo : determine an easy way for users of bundle to supply/override the token create algorithm.
     */
    protected function generateTraceToken()
    {
        return uniqid() . bin2hex(openssl_random_pseudo_bytes(2));
    }

    /**
     * @return string
     */
    public function getTraceId()
    {
        return $this->requestTraceId;
    }

    /**
     * Reduce scope to Enforce Singleton
     */
    protected function __construct()
    {
    }

    /**
     * Reduce scope to Enforce Singleton
     */
    private function __clone()
    {
    }

    /**
     * Reduce scope to Enforce Singleton
     */
    private function __wakeup()
    {
    }
}