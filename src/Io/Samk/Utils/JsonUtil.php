<?php
namespace Io\Samk\Utils;

/**
 * Class JsonUtil
 * @package Io\Samk\Utils
 */
class JsonUtil
{
    /**
     * Convert JSON decode issues into runtime exceptions
     *
     * @param string $jsonString
     * @param boolean $assoc
     * @return mixed
     * @throws \InvalidArgumentException
     */
    static function decode($jsonString, $assoc = true)
    {
        $decoded = json_decode($jsonString, $assoc);
        if (json_last_error()) {
            $message = json_last_error_msg()
                ? "Error decoding JSON: " . json_last_error_msg()
                : "There was an error decoding the JSON string";
            throw new \InvalidArgumentException($message);
        }

        return $decoded;
    }
}