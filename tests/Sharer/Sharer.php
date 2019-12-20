<?php

declare(strict_types = 1);

namespace AvtoDev\AmqpRabbitLaravelQueue\Tests\Sharer;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Throwable;

class Sharer
{
    /**
     * @param string $key
     * @param mixed  $value
     */
    public static function put(string $key, $value)
    {
        (new Filesystem)->put(static::keyToPath($key), \serialize($value), true);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public static function has(string $key): bool
    {
        return (new Filesystem)->exists(static::keyToPath($key));
    }

    /**
     * @param string $key
     *
     * @throws Exception
     *
     * @return mixed
     */
    public static function get(string $key)
    {
        try {
            return \unserialize((new Filesystem)->get(static::keyToPath($key), true));
        } catch (Throwable $e) {
            throw new Exception("Key [{$key}] does not exists", $e->getCode(), $e);
        }
    }

    /**
     * @return void
     */
    public static function clear(): void
    {
        $fs = new Filesystem;

        foreach ($fs->allFiles(__DIR__) as $file) {
            if ($file->getExtension() === 'txt') {
                $fs->delete($file->getRealPath());
            }
        }
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected static function keyToPath(string $key): string
    {
        return __DIR__ . '/' . \sha1($key) . '.txt';
    }
}
