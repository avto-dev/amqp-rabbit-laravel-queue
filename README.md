<p align="center">
  <img src="https://laravel.com/assets/img/components/logo-laravel.svg" alt="Laravel" width="240" />
</p>

# RabbitMQ-based Laravel queue driver

[![Version][badge_packagist_version]][link_packagist]
[![Version][badge_php_version]][link_packagist]
[![Build Status][badge_build_status]][link_build_status]
[![Coverage][badge_coverage]][link_coverage]
[![Code quality][badge_code_quality]][link_code_quality]
[![Downloads count][badge_downloads_count]][link_packagist]
[![License][badge_license]][link_license]

This package allows to use RabbitMQ queues for queued Laravel jobs.

> Installed php extension `ext-amqp` is required. Installation steps can be found in [Dockerfile](./docker/app/Dockerfile).

> **Important:** Before using this package you should install [`amqp-rabbit-manager`][link_amqp_rabbit_manager] into your application. Installation steps can be found [here][link_amqp_rabbit_manager_install].

## Install

Require this package with composer using the following command:

```shell
$ composer require avto-dev/amqp-rabbit-laravel-queue "^1.0"
```

> Installed `composer` is required ([how to install composer][getcomposer]).

> You need to fix the major version of package.

Laravel 5.5 and above uses Package Auto-Discovery, so doesn't require you to manually register the service-provider. Otherwise you must add the service provider to the `providers` array in `./config/app.php`:

```php
'providers' => [
    // ...
    AvtoDev\AmqpRabbitLaravelQueue\ServiceProvider::class,
]
```

> If you wants to disable package service-provider auto discover, just add into your `composer.json` next lines:
>
> ```json
> {
>     "extra": {
>         "laravel": {
>             "dont-discover": [
>                 "avto-dev/amqp-rabbit-laravel-queue"
>             ]
>         }
>     }
> }
> ```

After that you should modify your configuration files:

@todo: Write more info here

## Usage

@todo: Write more info here

### Testing

For package testing we use `phpunit` framework and `docker-ce` + `docker-compose` as develop environment. So, just write into your terminal after repository cloning:

```shell
$ make build
$ make latest # or 'make lowest'
$ make test
```

## Changes log

[![Release date][badge_release_date]][link_releases]
[![Commits since latest release][badge_commits_since_release]][link_commits]

Changes log can be [found here][link_changes_log].

## Support

[![Issues][badge_issues]][link_issues]
[![Issues][badge_pulls]][link_pulls]

If you will find any package errors, please, [make an issue][link_create_issue] in current repository.

## License

This is open-sourced software licensed under the [MIT License][link_license].

[badge_packagist_version]:https://img.shields.io/packagist/v/avto-dev/amqp-rabbit-laravel-queue.svg?maxAge=180
[badge_php_version]:https://img.shields.io/packagist/php-v/avto-dev/amqp-rabbit-laravel-queue.svg?longCache=true
[badge_build_status]:https://travis-ci.org/avto-dev/amqp-rabbit-laravel-queue.svg?branch=master
[badge_code_quality]:https://img.shields.io/scrutinizer/g/avto-dev/amqp-rabbit-laravel-queue.svg?maxAge=180
[badge_coverage]:https://img.shields.io/codecov/c/github/avto-dev/amqp-rabbit-laravel-queue/master.svg?maxAge=60
[badge_downloads_count]:https://img.shields.io/packagist/dt/avto-dev/amqp-rabbit-laravel-queue.svg?maxAge=180
[badge_license]:https://img.shields.io/packagist/l/avto-dev/amqp-rabbit-laravel-queue.svg?longCache=true
[badge_release_date]:https://img.shields.io/github/release-date/avto-dev/amqp-rabbit-laravel-queue.svg?style=flat-square&maxAge=180
[badge_commits_since_release]:https://img.shields.io/github/commits-since/avto-dev/amqp-rabbit-laravel-queue/latest.svg?style=flat-square&maxAge=180
[badge_issues]:https://img.shields.io/github/issues/avto-dev/amqp-rabbit-laravel-queue.svg?style=flat-square&maxAge=180
[badge_pulls]:https://img.shields.io/github/issues-pr/avto-dev/amqp-rabbit-laravel-queue.svg?style=flat-square&maxAge=180
[link_releases]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/releases
[link_packagist]:https://packagist.org/packages/avto-dev/amqp-rabbit-laravel-queue
[link_build_status]:https://travis-ci.org/avto-dev/amqp-rabbit-laravel-queue
[link_coverage]:https://codecov.io/gh/avto-dev/amqp-rabbit-laravel-queue/
[link_changes_log]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/blob/master/CHANGELOG.md
[link_code_quality]:https://scrutinizer-ci.com/g/avto-dev/amqp-rabbit-laravel-queue/
[link_issues]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/issues
[link_create_issue]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/issues/new/choose
[link_commits]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/commits
[link_pulls]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/pulls
[link_license]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/blob/master/LICENSE
[getcomposer]:https://getcomposer.org/download/
[link_amqp_rabbit_manager]:https://github.com/avto-dev/amqp-rabbit-manager
[link_amqp_rabbit_manager_install]:https://github.com/avto-dev/amqp-rabbit-manager/blob/master/README.md#install
