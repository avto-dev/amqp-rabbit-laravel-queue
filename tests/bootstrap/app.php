<?php

use Illuminate\Cache\CacheManager;
use Illuminate\Foundation\Application;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app_base_path = dirname(__DIR__, 2) . '/vendor/laravel/laravel';

if (Application::VERSION < '11') {
    $app = new Application($app_base_path);

    $app->singleton(
        Illuminate\Contracts\Http\Kernel::class,
        App\Http\Kernel::class
    );

    $app->singleton(
        Illuminate\Contracts\Console\Kernel::class,
        App\Console\Kernel::class
    );

    $app->singleton(
        Illuminate\Contracts\Debug\ExceptionHandler::class,
        App\Exceptions\Handler::class
    );
} else {
    $app = Application::configure(basePath: $app_base_path)
        ->withRouting(
            web: $app_base_path.'/routes/web.php',
            commands: $app_base_path.'/routes/console.php',
            health: '/up',
        )
        ->withSingletons([
            'cache' => function ($app) {
                return new CacheManager($app);
            },
            'cache.store' => function ($app) {
                return $app['cache']->driver(env('CACHE_STORE'), 'array');
            }
        ])
        ->withMiddleware(function (Illuminate\Foundation\Configuration\Middleware $middleware) {
            //
        })
        ->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions) {
            //
        })->create();
}

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$app->register(\AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider::class);
$app->register(\AvtoDev\AmqpRabbitManager\ServiceProvider::class);

return $app;
