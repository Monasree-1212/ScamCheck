<?php

namespace ScamCheck;

use Predis\Client;
use Throwable;

class Database
{
    private static ?Client $client = null;
    private static ?string $lastError = null;

    public static function getClient(): Client
    {
        if (self::$client !== null) {
            return self::$client;
        }

        Config::load();

        $parameters = [
            'scheme'   => Config::get('REDIS_SCHEME', 'tcp'),
            'host'     => Config::get('REDIS_HOST', '127.0.0.1'),
            'port'     => (int)Config::get('REDIS_PORT', 6379),
            'database' => (int)Config::get('REDIS_DATABASE', 0),
            'timeout'  => (float)Config::get('REDIS_TIMEOUT', 2.5),
        ];

        $password = Config::get('REDIS_PASSWORD');
        if (!empty($password)) {
            $parameters['password'] = $password;
        }

        $prefix = Config::get('REDIS_PREFIX', 'scamcheck_redis:');
        $options = [
            'prefix' => $prefix,
        ];

        try {
            self::$client = new Client($parameters, $options);
            // Verify connection
            self::$client->ping();
            self::$lastError = null;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            throw new \RuntimeException("Unable to connect to Redis/Memurai: " . $e->getMessage(), 0, $e);
        }

        return self::$client;
    }

    public static function ping(): bool
    {
        try {
            $client = self::getClient();
            $res = $client->ping();
            return (string)$res === 'PONG' || $res === true;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    public static function getPrefix(): string
    {
        return (string)Config::get('REDIS_PREFIX', 'scamcheck_redis:');
    }
}
