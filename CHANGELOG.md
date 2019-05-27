# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog][keepachangelog] and this project adheres to [Semantic Versioning][semver].

## v2.0.0

### Added

- Queue driver `resume` option _(can be used for periodic connection reloading)_

### Changed

- `Queue` class constructor signature

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
