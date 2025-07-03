<p align="center">
  <img src="https://laravel.com/assets/img/components/logo-laravel.svg" alt="Laravel" width="240" />
</p>

# RabbitMQ-based Laravel queue driver

[![Version][badge_packagist_version]][link_packagist]
[![PHP Version][badge_php_version]][link_packagist]
[![Build Status][badge_build_status]][link_build_status]
[![Coverage][badge_coverage]][link_coverage]
[![Downloads count][badge_downloads_count]][link_packagist]
[![License][badge_license]][link_license]

This package allows to use RabbitMQ queues for queued Laravel (prioritized) jobs. Fully configurable.

Installed php extension `ext-amqp` is required. Installation steps can be found in [Dockerfile](./Dockerfile).

For jobs delaying you also should install [`rabbitmq-delayed-message-exchange`][link_rabbitmq_delayed_message_exchange] plugin for RabbitMQ. Delaying is optional feature.

## Install

> **Important:** Before using this package you should install [`avto-dev/amqp-rabbit-manager`][link_amqp_rabbit_manager] into your application. Installation steps can be found [here][link_amqp_rabbit_manager_install].

Require this package with composer using the following command:

```shell
$ composer require avto-dev/amqp-rabbit-laravel-queue "^2.0"
```

> Installed `composer` is required ([how to install composer][getcomposer]). Also you need to fix the major version of package.

> You need to fix the major version of package.

After that you should modify your configuration files:

### `./config/rabbitmq.php`

RabbitMQ queues and exchanges configuration:

```php
<?php

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;

return [

    // ...

    'queues' => [

        'jobs' => [
            'name'         => env('JOBS_QUEUE_NAME', 'jobs'),
            'flags'        => AmqpQueue::FLAG_DURABLE, // Remain queue active when a server restarts
            'arguments'    => [
                'x-max-priority' => 255, // @link <https://www.rabbitmq.com/priority.html>
            ],
            'consumer_tag' => null,
        ],

        'failed' => [
            'name'         => env('FAILED_JOBS_QUEUE_NAME', 'failed-jobs'),
            'flags'        => AmqpQueue::FLAG_DURABLE,
            'arguments'    => [
                'x-message-ttl' => 604800000, // 7 days, @link <https://www.rabbitmq.com/ttl.html>
                'x-queue-mode'  => 'lazy', // @link <https://www.rabbitmq.com/lazy-queues.html>
            ],
            'consumer_tag' => null,
        ],

    ],

    // ...

    'exchanges' => [

        // RabbitMQ Delayed Message Plugin is required (@link: <https://git.io/fj4SE>)
        'delayed-jobs' => [
            'name'      => env('DELAYED_JOBS_EXCHANGE_NAME', 'jobs.delayed'),
            'type'      => 'x-delayed-message',
            'flags'     => AmqpTopic::FLAG_DURABLE, // Remain active when a server restarts
            'arguments' => [
                'x-delayed-type' => AmqpTopic::TYPE_DIRECT,
            ],
        ],

    ],

    // ...

    'setup' => [
        'rabbit-default' => [
            'queues' => [
                'jobs',
                'failed',
            ],
            'exchanges' => [
                'delayed-jobs'
            ],
        ],
    ],
];
```

### `./config/queue.php`

Laravel queue settings:

```php
<?php

use AvtoDev\AmqpRabbitLaravelQueue\Connector;

return [

    // ...

    'default' => env('QUEUE_DRIVER', 'rabbitmq'),

    // ...

    'connections' => [

        // ...

        'rabbitmq' => [
            'driver'              => Connector::NAME,
            'connection'          => 'rabbit-default',
            'queue_id'            => 'jobs',
            'delayed_exchange_id' => 'delayed-jobs',
            'timeout'             => (int) env('QUEUE_TIMEOUT', 0), // The timeout is in milliseconds
            'resume'              => (bool) env('QUEUE_RESUME', false), // Resume consuming when timeout is over
        ],
    ],

    // ...

    'failed' => [
        'connection' => 'rabbit-default',
        'queue_id'   => 'failed',
    ],
];
```

> `resume` can be used with non-zero `timeout` value for periodic connection reloading _(for example, if you set `'timeout' => 30000` and `'resume' => true`, queue worker will unsubscribe and subscribe back to the queue every 30 seconds **without** process exiting)_.

You can remove `delayed_exchange_id` for disabling delayed jobs feature.

At the end, don't forget to execute command `php ./artisan rabbit:setup`.

### How jobs delaying works?

Very simple:

<div align="center">
  <img src="https://cdn.jsdelivr.net/gh/avto-dev/amqp-rabbit-laravel-queue/.github/queue-delay.svg" width="100%" alt="" />
</div>

## Usage

You can dispatch your jobs as usual (`dispatch(new Job)` or `dispatch(new Job)->delay(10)`), commands like `queue:work`, `queue:failed`, `queue:retry` and others works fine.

#### Additional features:

- Jobs delaying (plugin `rabbitmq_delayed_message_exchange` for RabbitMQ server is required);
- Jobs priority (job should implements `PrioritizedJobInterface` interface);
- Automatically delayed messages exchanges bindings (only if you use command `rabbit:setup` for queues and exchanges creation);
- The ability to store the state of `job`

#### State storing

Using this package you can store any variables (except resources and callable entities) between job restarts (just use trait `WithJobStateTrait` in your job class). But you should remember - state is available only inside job `handle` method.

#### Consumer custom tag prefix

Every consumer has an identifier that is used by client libraries to determine what handler to invoke for a given delivery. Their names vary from protocol to protocol. Consumer tags and subscription IDs are two most commonly used terms.

If you want to add custom prefix to the consumer tag, you can specify it with an additional argument in the `AvtoDev\AmqpRabbitLaravelQueue\Worker::__construct` method.

### :warning: Warning

**Be careful with commands `queue:failed` and `queue:retry`**. If during command execution something happens (lost connection, etc) you may loose all failed jobs!

You should avoid to use next method _(broker does not guarantee operations order, so calling results may be wrong)_:

- `\AvtoDev\AmqpRabbitLaravelQueue\Queue::size()`
- `\AvtoDev\AmqpRabbitLaravelQueue\Failed\RabbitQueueFailedJobProvider::count()`

### Testing

For package testing we use `phpunit` framework and `docker` with `compose` plugin as develop environment. So, just write into your terminal after repository cloning:

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
[badge_build_status]:https://img.shields.io/github/actions/workflow/status/avto-dev/amqp-rabbit-laravel-queue/tests.yml
[badge_coverage]:https://img.shields.io/codecov/c/github/avto-dev/amqp-rabbit-laravel-queue/master.svg?maxAge=60
[badge_downloads_count]:https://img.shields.io/packagist/dt/avto-dev/amqp-rabbit-laravel-queue.svg?maxAge=180
[badge_license]:https://img.shields.io/packagist/l/avto-dev/amqp-rabbit-laravel-queue.svg?longCache=true
[badge_release_date]:https://img.shields.io/github/release-date/avto-dev/amqp-rabbit-laravel-queue.svg?style=flat-square&maxAge=180
[badge_commits_since_release]:https://img.shields.io/github/commits-since/avto-dev/amqp-rabbit-laravel-queue/latest.svg?style=flat-square&maxAge=180
[badge_issues]:https://img.shields.io/github/issues/avto-dev/amqp-rabbit-laravel-queue.svg?style=flat-square&maxAge=180
[badge_pulls]:https://img.shields.io/github/issues-pr/avto-dev/amqp-rabbit-laravel-queue.svg?style=flat-square&maxAge=180
[link_releases]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/releases
[link_packagist]:https://packagist.org/packages/avto-dev/amqp-rabbit-laravel-queue
[link_build_status]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/actions
[link_coverage]:https://codecov.io/gh/avto-dev/amqp-rabbit-laravel-queue/
[link_changes_log]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/blob/master/CHANGELOG.md
[link_issues]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/issues
[link_create_issue]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/issues/new/choose
[link_commits]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/commits
[link_pulls]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/pulls
[link_license]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/blob/master/LICENSE
[getcomposer]:https://getcomposer.org/download/
[link_amqp_rabbit_manager]:https://github.com/avto-dev/amqp-rabbit-manager
[link_amqp_rabbit_manager_install]:https://github.com/avto-dev/amqp-rabbit-manager/blob/master/README.md#install
[link_rabbitmq_delayed_message_exchange]:https://github.com/rabbitmq/rabbitmq-delayed-message-exchange
