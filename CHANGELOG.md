# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-03-03

### Added
- Bulk speakable CSS selector assignment across posts
- Post meta query command for diagnostics
- Internationalization (i18n) support with `.pot` file
- Recursive schema data sanitization
- PHPCS configuration with WordPress-Extra ruleset
- CI/CD workflows for linting and WordPress.org deployment

### Changed
- Moved cron scheduling from constructor to activation hook
- Removed WordPress and PHP version from public status endpoint
- Renamed `NAMESPACE` constant to `REST_NAMESPACE` (reserved keyword)
- Replaced deprecated `current_time('timestamp')` with `time()`

### Fixed
- Removed unnecessary `flush_rewrite_rules()` from API content handlers
- Added `$wpdb->prepare()` to uninstall meta cleanup query
- Added `wp_unslash()` before `sanitize_text_field()` on `$_GET` values
- Added `esc_url_raw()` validation for featured image URLs (SSRF prevention)
- Escaped all hardcoded status strings in admin views
- Wrapped `wp_die()` messages with `esc_html__()`
- Added nonce sanitization in CSV export handler

### Security
- Schema arrays (Organization, WebSite, Post) are now recursively sanitized before storage
- Featured image URL restricted to `http` and `https` protocols only
- WordPress and PHP versions removed from unauthenticated endpoint

## [1.0.0] - 2026-02-15

### Added
- Initial release
- Virtual llms.txt and ai.txt files
- robots.txt AI crawler rules
- Organization, WebSite, Article, and FAQ schema
- Breadcrumb and Speakable schema
- Canonical URL management
- Semantic HTML improvements
- Content freshness metadata
- REST API with HMAC-SHA256 authentication
- 6-hour heartbeat connectivity check
- Admin settings page with feature toggles
- Activity log with CSV export
- Content publishing module with auto FAQ extraction
