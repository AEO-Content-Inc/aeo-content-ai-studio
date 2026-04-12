# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Changed
- Replaced the settings-page API-key-first onboarding with a connect-first flow for new installs
- Added account, sign-in, and disconnect actions to the WordPress settings screen
- Switched the wp-admin sidebar menu icon to a dedicated transparent SVG mark that sits correctly on the dark admin background

### Added
- PHPUnit scaffold and helper tests for onboarding URL generation
- PHPUnit coverage for the admin menu icon data URI and transparent SVG contract

## [1.0.0] - 2026-03-13

### Added
- Content Publishing module — create and update posts via platform REST API
- Audit Report page — 28-criteria AI visibility score with detailed findings and fix recommendations
- Activity Log — filterable command log with CSV export and 90-day auto-cleanup
- REST API endpoints: posts (list/single), publish, command dispatch, categories, tags, logs, status
- Credential authentication with constant-time hash comparison
- Periodic heartbeat for platform connectivity sync
- Admin dashboard with three pages: Audit Report, Settings, Activity Log
- Automatic category and tag creation when publishing posts
- Featured image download from external URLs
- FAQ schema extraction from post content
- Internationalization (i18n) support
