# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog][keepachangelog] and this project adheres to [Semantic Versioning][semver].

## Unreleased

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
