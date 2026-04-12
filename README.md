# AEO Content AI Studio

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/aeo-content-ai-studio)](https://wordpress.org/plugins/aeo-content-ai-studio/)
[![WordPress Plugin: Tested WP Version](https://img.shields.io/wordpress/plugin/tested/aeo-content-ai-studio)](https://wordpress.org/plugins/aeo-content-ai-studio/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Connects your WordPress site to [AEO Content AI Studio](https://www.aeocontent.ai) for AI-powered content publishing and 5-pillar site audit reports with per-page scoring and rewrite prioritization.

## What is AEO?

AI Engine Optimization (AEO) is the practice of structuring web content so AI answer engines. ChatGPT, Claude, Perplexity, and Google AI Overviews. can discover, parse, and cite it. While SEO focuses on search rankings, AEO focuses on getting your content into AI-generated answers.

## What This Plugin Does

| Feature | Description |
|---------|-------------|
| **Google Connect** | 1-click account creation and site connection via Google sign-in popup |
| **Content Publishing** | Read, create, and update WordPress posts from AEO Content AI Studio via REST API |
| **Audit Report** | Workflow-based audit UI with Connect, Diagnose, Fix, and AI Visibility stages |
| **Pages** | Full site page inventory with AEO Rank scores, categories, word counts, and inbound links |
| **Rewrite Candidates** | Prioritized list of pages needing content rewrites with tier classification and weakest pillar |
| **AI Visibility** | Dedicated visibility stage for citations, engines, competitors, and trend movement |
| **Full Site Audit** | Trigger and monitor a complete site audit with real-time progress from WordPress admin |
| **Admin Workspace Handoff** | Operational logs and deeper troubleshooting live in AEO admin, not inside WordPress |
| **Categories & Tags API** | Sync taxonomy data to AEO Content AI Studio for accurate content organization |
| **Heartbeat** | Periodic connectivity check keeps platform and plugin in sync |
| **Credential Auth** | Platform and plugin requests are secured with API keys and constant-time key comparison |

## How It Works

```
AEO Content AI Studio                         WordPress Plugin
    |                                               |
    |  1. GET /categories, /tags, /posts            |
    |---------------------------------------------->|
    |                                               |
    |  2. AI optimizes content in Studio            |
    |                                               |
    |  3. POST /publish (create or update post)     |
    |---------------------------------------------->|
    |                                               |
    |  4. GET /audits/{slug} (audit data)           |
    |<----------------------------------------------|
    |                                               |
    |  5. GET /visibility/{slug} (AI visibility)    |
    |<----------------------------------------------|
    |                                               |
    |  6. Heartbeat (periodic sync)                 |
    |<----------------------------------------------|
```

1. Install the plugin and click **Continue with Google** in **AEO Content > Settings**
2. A popup opens for Google sign-in. your account and site connection are created automatically
3. AEO Content AI Studio syncs your categories, tags, and posts via authenticated REST API
4. AEO Content AI Studio analyzes and optimizes your content
5. Optimized content is published back to WordPress
6. View your site's AEO audit score, opportunities, and AI visibility in the workflow-based **Audit Report** screen

## AI Visibility Data

The plugin's **AI Visibility** stage reads from the dedicated visibility endpoint, which now serves live daily monitoring data from the same source as Studio.

- Primary source: `GET /api/v1/visibility/{site-slug}?include=timeline`. returns per-engine reports built from `aeo_monitor_runs` / `aeo_monitor_results` (daily monitoring across 5 AI engines)
- Legacy fallback: one-off `aeo_visibility_reports` data, used only when no monitor runs exist for the domain
- Audit fallback: the latest audit payload, used only when the dedicated visibility endpoint returns `404`
- The endpoint normalizes slugs so both `helpsquad.com` and `helpsquad-com` resolve correctly

This ensures the WordPress plugin shows the same fresh, 5-engine visibility data that Studio displays.

## Installation

### From WordPress.org (recommended)

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for **AEO Content AI Studio**
3. Click **Install Now**, then **Activate**
4. Go to **AEO Content > Settings** and click **Get Started** to connect your site

### Manual Install

1. Download the latest release ZIP from [Releases](https://github.com/AEO-Content-Inc/aeo-content-ai-studio/releases)
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP and activate

## Onboarding

The plugin supports 1-click onboarding via Google sign-in:

1. Open **AEO Content > Settings**
2. Click **Continue with Google**. a popup opens on `studio.aeocontent.ai`
3. Sign in with your Google account (creates a new account if needed, or signs into an existing one)
4. The popup auto-closes and your site is connected immediately

Manual alternatives are also available:
- **Create Account Manually** / **I Already Have an Account**. redirects to the platform for email-based signup/login
- **Advanced: connect with an API key**. for direct support-driven setups

## REST API Endpoints

All authenticated endpoints require the `x-api-key` header.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/wp-json/aeo/v1/status` | No | Health check. version, features, URLs |
| GET | `/wp-json/aeo/v1/posts` | Yes | Paginated post list |
| GET | `/wp-json/aeo/v1/posts/{id}` | Yes | Full post with content, author, meta |
| POST | `/wp-json/aeo/v1/publish` | Yes | Create or update a post |
| POST | `/wp-json/aeo/v1/command` | Yes | Command dispatch |
| GET | `/wp-json/aeo/v1/categories` | Yes | All categories with hierarchy |
| GET | `/wp-json/aeo/v1/tags` | Yes | All tags |
| GET | `/wp-json/aeo/v1/logs` | Yes | Diagnostics log for AEO admin and secure support workflows |

## Development

### Prerequisites

- PHP 7.4+
- Composer

### Setup

```bash
composer install
```

### Linting

```bash
composer run phpcs    # Check coding standards
composer run phpcbf   # Auto-fix violations
```

### Testing

#### Unit tests (no WordPress required)

```bash
composer install
composer run test
```

The current unit tests cover:

- onboarding URL helpers in [`AEOCAS_Settings`](./includes/class-aeo-settings.php)
- the admin-menu icon contract for the dark WordPress sidebar
- visibility API behavior in [`AEOCAS_Audit_Api`](./includes/class-aeo-audit-api.php), including:
  - preferring the dedicated visibility endpoint over stale audit-embedded visibility
  - falling back to audit visibility only when the dedicated report returns `404`
  - normalizing monitor-format responses with per-engine `query_variants`
  - handling multi-engine monitor payloads and correct engine/citation counts

They run against the WordPress stubs in [`tests/bootstrap.php`](./tests/bootstrap.php), so no WP install is needed.

#### End-to-end testing in a local WordPress (Docker)

The fastest way to exercise the plugin against a real WordPress is to build the installable ZIP and upload it to a disposable WP container.

1. **Build the ZIP**

   ```bash
   ./build-zip.sh
   ```

   This produces `aeo-content-ai-studio.zip` in the repo root, identical to the artifact uploaded to WordPress.org. Move it wherever you like, e.g.:

   ```bash
   mv aeo-content-ai-studio.zip ~/Downloads/
   ```

2. **Spin up a local WordPress** (requires Docker)

   ```bash
   npx @wordpress/env start
   ```

   Admin: <http://localhost:8888/wp-admin>. user `admin`, password `password`.

3. **Install the ZIP** via **Plugins → Add New → Upload Plugin**, choose the ZIP, and activate. Open **AEO Content → Settings** to verify the onboarding flow.

4. **Check the admin branding surfaces**

   Confirm the two icon surfaces behave differently on purpose:
   - the left wp-admin menu uses the compact transparent icon directly on the dark sidebar
   - the plugin header uses the richer tiled logo on the light content background

5. **Smoke-test the REST API**

   ```bash
   curl http://localhost:8888/wp-json/aeo/v1/status
   curl -H "x-api-key: <key>" http://localhost:8888/wp-json/aeo/v1/posts
   ```

6. **Tear down** when finished:

   ```bash
   npx @wordpress/env stop      # stop containers
   npx @wordpress/env destroy   # nuke containers + DB
   ```

> **Note:** the connect-first onboarding redirects to `account.aeocontent.ai`, which cannot call back to `http://localhost:8888`. For local end-to-end testing of the connect flow, either use the manual API key fallback on the Settings page, or expose the local site with a tunnel (e.g. `ngrok http 8888`) and use the public URL.

Re-run `./build-zip.sh` after every code change and re-upload the ZIP to test fresh changes.

## Compatibility

- **WordPress:** 6.2+
- **PHP:** 7.4+
- **SEO Plugins:** Compatible with Yoast SEO, Rank Math, All in One SEO
- **Themes:** Works with any theme

## License

GPL v2 or later. See [license.txt](license.txt).

## Links

- [AEO Content AI Studio](https://www.aeocontent.ai)
- [WordPress.org Plugin Page](https://wordpress.org/plugins/aeo-content-ai-studio/)
- [AEORank Engine Docs](https://www.aeocontent.ai/docs/guide/aeorank)
- [Report an Issue](https://github.com/AEO-Content-Inc/aeo-content-ai-studio/issues)
