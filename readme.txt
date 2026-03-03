=== AEO Content AI Studio ===
Contributors: aeocontent
Tags: seo, ai, schema, structured-data, llms-txt
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI Engine Optimization for WordPress. Manages llms.txt, ai.txt, robots.txt rules, structured data, and semantic HTML to maximize your site's visibility to AI engines like ChatGPT, Claude, Perplexity, and Google AI Overviews.

== Description ==

AEO Content AI Studio connects your WordPress site to the [AEO Content platform](https://www.aeocontent.ai) to automatically implement AI visibility optimizations.

AI engines like ChatGPT, Claude, and Perplexity are becoming major traffic sources. This plugin ensures your site is optimized to appear in AI-generated answers by implementing structured data, machine-readable content files, and semantic HTML that AI engines understand.

= Features =

* **llms.txt / ai.txt** - Serve virtual files at your site root for AI crawler discovery
* **robots.txt AI Rules** - Append AI-specific crawler directives (GPTBot, ClaudeBot, PerplexityBot, etc.)
* **Organization & WebSite Schema** - Site-wide JSON-LD structured data
* **Article & Author Schema** - Per-post Article, Person, and SpeakableSpecification markup
* **FAQ Schema** - Automatic FAQPage JSON-LD from content patterns or explicit data
* **Canonical URLs** - Platform-managed canonical URL overrides
* **Semantic HTML** - Article wrappers, time elements, and lang attribute
* **Content Freshness** - dateModified metadata and Open Graph tags
* **Content Publishing** - Create and update posts with full AEO optimization via API
* **Activity Log** - Track all optimization commands with filterable log and CSV export

= How It Works =

1. Install the plugin and enter your Site Token from the [AEO Content dashboard](https://www.aeocontent.ai)
2. The platform sends optimization commands to your site via HMAC-authenticated REST API
3. A heartbeat every 6 hours ensures connectivity and delivers any missed commands
4. All features are individually toggleable from Settings > AEO Content

= External Service =

This plugin connects to the AEO Content platform at `aeocontent.ai` for the following:

* **Heartbeat** (every 6 hours): Sends your site URL, plugin version, and enabled features list to `https://www.aeocontent.ai/api/v1/plugin/heartbeat`. This allows the platform to verify connectivity and deliver any pending optimization commands. No personal data or site content is transmitted.
* **Registration** (on initial setup): When you enter a Site Token, the plugin validates it against the platform.

The platform's terms of service are available at [aeocontent.ai/terms](https://www.aeocontent.ai/terms) and privacy policy at [aeocontent.ai/privacy](https://www.aeocontent.ai/privacy).

All communication uses HMAC-SHA256 request signing. Requests older than 5 minutes are rejected.

== Installation ==

1. Upload the `aeo-content-ai-studio` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > AEO Content to enter your Site Token
4. Enable or disable individual features as needed

Alternatively, search for "AEO Content AI Studio" in the WordPress plugin directory and install directly.

== Frequently Asked Questions ==

= Do I need an AEO Content account? =

Yes. The plugin connects to the AEO Content platform which provides the optimization data and commands. Visit [aeocontent.ai](https://www.aeocontent.ai) to get started with a free audit.

= Is it compatible with Yoast SEO / Rank Math? =

Yes. The plugin checks for existing schema and canonical tags before adding its own. You can disable individual features from the settings page to avoid any conflicts.

= How is authentication handled? =

All API communication between the platform and your site uses HMAC-SHA256 request signing with a shared secret (your Site Token). Requests older than 5 minutes are automatically rejected.

= What data does the plugin send to aeocontent.ai? =

The heartbeat (every 6 hours) sends only: your site URL, home URL, plugin version, and the list of enabled features. No personal data, user information, or site content is transmitted. All communication is encrypted via HTTPS.

= Can I use this without the platform? =

The plugin is designed to work with the AEO Content platform. Without a valid Site Token, the optimization features will not receive data. However, the plugin does not break your site if disconnected - features simply remain in their last configured state.

= Does the plugin slow down my site? =

No. The plugin adds minimal overhead. Schema markup is injected via WordPress hooks (no additional database queries on the frontend), and the heartbeat runs via WP-Cron in the background.

== Screenshots ==

1. Settings page with feature toggles and connection status
2. Activity log showing all optimization commands

== Changelog ==

= 1.1.0 =
* Added bulk speakable CSS selector assignment
* Added post meta query command for diagnostics
* Added internationalization (i18n) support
* Added recursive schema data sanitization
* Improved security: removed server version info from public endpoint
* Improved security: SSRF prevention on featured image URLs
* Fixed deprecated `current_time('timestamp')` usage
* Fixed missing `$wpdb->prepare()` in uninstall cleanup

= 1.0.0 =
* Initial release
* Virtual llms.txt and ai.txt files
* robots.txt AI crawler rules
* Organization, WebSite, Article, and FAQ schema
* Speakable and Breadcrumb schema
* Canonical URL management
* Semantic HTML improvements
* Content freshness metadata
* REST API with HMAC-SHA256 authentication
* 6-hour heartbeat connectivity check
* Admin settings page with feature toggles
* Activity log with CSV export

== Privacy ==

This plugin connects to the external service at aeocontent.ai. See the "External Service" section in the Description for full details on what data is transmitted and when.

No personal user data is collected, stored, or transmitted by this plugin. The plugin does not use cookies, does not track users, and does not collect analytics.

== Upgrade Notice ==

= 1.1.0 =
Security improvements and WordPress.org compliance updates. Recommended for all users.
