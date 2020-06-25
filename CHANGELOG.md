# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog][keepachangelog] and this project adheres to [Semantic Versioning][semver].

## v2.4.0

### Changed

- Maximal `illuminate/*` package versions now is `7.*`
- Returning values in methods `serialize` and `unserialize` in `JobState` now type-hinted
- Method `put` in `JobState` now returns self instance (instead null)
- Minimal required PHP version now is `7.2`
- Version of `rabbitmq-c` lib in docker container updated up to `0.10.0`
- Version of `php-amqp` lib in docker container updated up to `1.10.2`
- Minimal required `symfony/console` version now is `^4.4` _(reason: <https://github.com/symfony/symfony/issues/32750>)_
- CI completely moved from "Travis CI" to "Github Actions" _(travis builds disabled)_

### Added

- PHP 7.4 is supported now

## v2.3.1

### Changed

- Disable `alpha_ordering_imports` rule for `StyleCI`

### Fixed

- Fixed bug with impossibility to retry or forget failed job by ID [#12]

[#12]:https://github.com/avto-dev/amqp-rabbit-laravel-queue/issues/12

## v2.3.0

### Changed

- Maximal `illuminate/*` packages version now is `6.*`

### Added

- GitHub actions for a tests running

## v2.2.1

### Fixed

- `$subscriber->unsubscribe($consumer);` disabled in `Worker` class (possible fix exception `AMQPEnvelopeException: Orphaned envelope`)

## v2.2.0

### Added

- Automatic Job state (`JobStateInterface`) binding into IoC container

## v2.1.0

### Added

- Feature for storing `job` state between `job` tries

## v2.0.2

### Fixed

- `\AvtoDev\AmqpRabbitLaravelQueue\Job::release()` `parent::release()` `$delay` casting into `int` before calling

## v2.0.1

### Fixed

- `\AvtoDev\AmqpRabbitLaravelQueue\Job::release()` annotation

## v2.0.0

### Added

- Queue driver `resume` option _(can be used for periodic connection reloading)_

### Changed

- `Queue` class constructor signature
- Option `sleep` for `queue:work` command marked as unused
- Option `timeout` for `queue:work` now `-1` by default. It means next - by default used timeout value from configuration file, but this value can be overridden by passing `--timeout` option with `0..+n` value

## v1.0.1

### Fixed

- Wrong worker class binding for `WorkCommand` (queue `Worker` class was not used because of this)

## v1.0.0

### Added

- Basic classes
- Events listeners (`CreateExchangeBind`, `RemoveExchangeBind`)
- Rabbit queue-based jobs failer
- A little bit extended commands `make:job` and `queue:work`
- `PrioritizedJobInterface` for prioritized jobs

[keepachangelog]:https://keepachangelog.com/en/1.0.0/
[semver]:https://semver.org/spec/v2.0.0.html
