# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-14

Initial release of `tims/laravel-amazon-spapi`: Laravel integration for Amazon’s official Selling Partner API PHP SDK (`amzn-spapi/sdk`).

### Added

- Service provider, config publish (`amazon-spapi-config`), and `AmazonSpApi` facade
- Single-seller LWA credentials via `.env` / config
- Multi-seller `Seller` and `Credentials` models with publishable migrations (`amazon-spapi-multi-seller`)
- Laravel-backed LWA access-token cache and Restricted Data Token (RDT) cache
- Automatic RDT middleware for restricted SP-API paths
- Manual RDT helpers on `SpApiManager` / credentials
- OAuth helpers for Seller Central authorize URL and code exchange
- Grantless client helpers (`makeGrantless`, `GrantlessScope`)
- Endpoint, region, and marketplace enums
- Feed and report `Document` download, upload, gunzip, and parse helpers
- HTTP retry middleware for 429 and transient 5xx responses
- Queue jobs and events for report/feed create → poll → download
- `SpApiFake` test helper for Mockery-backed API clients
- PHPUnit suite (Orchestra Testbench) and Laravel Pint (PSR-12)

[1.0.0]: https://github.com/timslabs/laravel-amazon-spapi/releases/tag/v1.0.0
