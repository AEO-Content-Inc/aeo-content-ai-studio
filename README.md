# AEO Content AI Studio

[![CI](https://github.com/AEO-Content-Inc/aeo-content-ai-studio/actions/workflows/ci.yml/badge.svg)](https://github.com/AEO-Content-Inc/aeo-content-ai-studio/actions/workflows/ci.yml)
[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/aeo-content-ai-studio)](https://wordpress.org/plugins/aeo-content-ai-studio/)
[![WordPress Plugin: Tested WP Version](https://img.shields.io/wordpress/plugin/tested/aeo-content-ai-studio)](https://wordpress.org/plugins/aeo-content-ai-studio/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

AI Engine Optimization for WordPress. Manages llms.txt, ai.txt, robots.txt rules, structured data, and semantic HTML to maximize your site's visibility to AI engines like ChatGPT, Claude, Perplexity, and Google AI Overviews.

## What It Does

AI engines are becoming major traffic sources. This plugin connects your WordPress site to the [AEO Content platform](https://www.aeocontent.ai) to automatically implement optimizations that help your content appear in AI-generated answers.

### Features

| Feature | Description |
|---------|-------------|
| **llms.txt / ai.txt** | Virtual files at site root for AI crawler discovery |
| **robots.txt AI Rules** | AI-specific crawler directives (GPTBot, ClaudeBot, etc.) |
| **Organization & WebSite Schema** | Site-wide JSON-LD structured data |
| **Article & Author Schema** | Per-post Article, Person, SpeakableSpecification markup |
| **FAQ Schema** | Auto-extracted FAQPage JSON-LD from content |
| **Canonical URLs** | Platform-managed canonical URL overrides |
| **Semantic HTML** | Article wrappers, time elements, lang attribute |
| **Content Freshness** | dateModified metadata and Open Graph tags |
| **Content Publishing** | Create/update posts with full optimization via API |
| **Activity Log** | Track all commands with filterable log and CSV export |

## Installation

### From WordPress.org (recommended)

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for **AEO Content AI Studio**
3. Click **Install Now**, then **Activate**
4. Go to **Settings > AEO Content** and enter your Site Token

### Manual Install

1. Download the latest release ZIP from [Releases](https://github.com/AEO-Content-Inc/aeo-content-ai-studio/releases)
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP and activate

### From Source

```bash
git clone https://github.com/AEO-Content-Inc/aeo-content-ai-studio.git
cd aeo-content-ai-studio
./build-zip.sh
# Upload aeo-content-ai-studio.zip via WordPress admin
```

## How It Works

```
AEO Content Platform ──HMAC-signed REST API──> WordPress Plugin
         ^                                           |
         └────── Heartbeat (every 6 hours) ──────────┘
```

1. Install the plugin and enter your **Site Token** from [aeocontent.ai](https://www.aeocontent.ai)
2. The platform sends optimization commands via authenticated REST API
3. A heartbeat every 6 hours ensures connectivity and delivers missed commands
4. All features are individually toggleable from the settings page

### Authentication

All communication uses **HMAC-SHA256** request signing with a shared secret. Requests older than 5 minutes are automatically rejected.

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

### Build

```bash
./build-zip.sh    # Creates aeo-content-ai-studio.zip
```

## Compatibility

- **WordPress:** 6.0+
- **PHP:** 7.4+
- **SEO Plugins:** Compatible with Yoast SEO, Rank Math, All in One SEO
- **Themes:** Works with any theme

## License

GPL v2 or later. See [license.txt](license.txt).

## Links

- [AEO Content Platform](https://www.aeocontent.ai)
- [WordPress.org Plugin Page](https://wordpress.org/plugins/aeo-content-ai-studio/)
- [AEORank Chrome Extension](https://github.com/AEO-Content-Inc/aeorank)
- [Report an Issue](https://github.com/AEO-Content-Inc/aeo-content-ai-studio/issues)
