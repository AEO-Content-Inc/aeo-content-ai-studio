# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [1.2.7] - 2026-04-14

### Added
- Direct Docs and Support Forum links on the plugin Connect screen for both connected and disconnected states
- Support and Settings links on the Plugins list page, plus Docs and Support Forum links in plugin row meta
- WordPress.org FAQ updates covering the Studio-first connection flow, Push as Draft troubleshooting, and what to include in support requests

## [1.2.6] - 2026-04-14

### Fixed
- Studio connect handoff now requires a nonce-protected admin confirmation instead of mutating plugin connection state on page load
- Local content index now omits posts the current dashboard user cannot edit
- Activity log CSV export now escapes spreadsheet formula-like values

## [1.2.5] - 2026-04-14

### Fixed
- Release lint issues so the Studio-first WordPress connect flow can be published to WordPress.org successfully

## [1.2.4] - 2026-04-13

### Added
- Studio-first WordPress handoff: the plugin can now consume one-time `aeo_connect` tokens issued by Studio and complete the connection without manual API-key copy/paste
- Public `/wp-json/aeo/v1/status` health endpoint with connection metadata for Studio install/connect detection

### Changed
- REST API now serves both `aeo/v1` and `aeocas/v1` namespaces during the migration window so old and new platform clients can coexist

### Fixed
- Studio and plugin contract mismatch that prevented direct draft-publish flows from recognizing an already connected WordPress site

## [1.2.2] - 2026-04-12

### Added
- AI Visibility stage with live daily monitoring from 5 AI engines (ChatGPT, Perplexity, Claude, Gemini, Google AI Overview)
- Visibility overview with score, 7d/30d deltas, citation count, and critical alerts
- Citations tab: recent mentions with engine, query, page URL, and snippet
- Competitors tab: side-by-side visibility comparison against competing domains
- Trends tab: historical score movement chart
- Workflow-based 4-stage UI: Connect, Diagnose, Fix, AI Visibility with status badges and next-best-action cards
- Discovery profile with deterministic site analysis (entities, topics, pages)
- Google sign-in connect screen with disconnect and feature toggle
- PHPUnit scaffold and tests for onboarding URLs, admin menu icon, visibility API, and normalize_visibility_payload
- Tests for monitor-format visibility responses with per-engine query_variants

### Changed
- Replaced the settings-page API-key-first onboarding with a connect-first flow for new installs
- Visibility now prefers the dedicated `/api/v1/visibility/[slug]` endpoint (backed by live monitor data) over stale audit-embedded visibility
- Platform API now normalizes slugs so both `helpsquad.com` and `helpsquad-com` resolve correctly
- Lowered capability requirement from `manage_options` to `edit_posts` so all WP dashboard users can use the plugin (re-audit, visibility, etc.)
- Rewritten wordpress.org description with research-backed marketing copy
- Removed all em-dashes from plugin copy

### Fixed
- Weakest pillar column in Rewrite Candidates was always empty: `getWeakestPagePillar()` now reads `pageRankPillars` (actual API field) in addition to legacy `pillarScores`
- Visibility data was stale (weeks old) because the API read from legacy one-off reports instead of daily monitoring runs
- Re-audit button returned "Unauthorized" because platform onboard endpoint required `write` permission but plugin tokens only have `read`
- Connect tab now shows logged-in user email and a warning when the user lacks admin privileges

## [1.0.0] - 2026-03-13

### Added
- Content Publishing module: create and update posts via platform REST API
- Audit Report page: 28-criteria AI visibility score with detailed findings and fix recommendations
- Activity Log: filterable command log with CSV export and 90-day auto-cleanup
- REST API endpoints: posts (list/single), publish, command dispatch, categories, tags, logs, status
- Credential authentication with constant-time hash comparison
- Periodic heartbeat for platform connectivity sync
- Admin dashboard with three pages: Audit Report, Settings, Activity Log
- Automatic category and tag creation when publishing posts
- Featured image download from external URLs
- FAQ schema extraction from post content
- Internationalization (i18n) support
