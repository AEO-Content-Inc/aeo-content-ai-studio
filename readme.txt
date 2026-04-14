=== AEO Content AI Studio ===
Contributors: aeocontent
Tags: ai, content, publishing, audit, optimization
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find out why AI engines skip your content and start getting cited by ChatGPT, Perplexity, Claude, Gemini, and Google AI Overviews.

== Description ==

When someone asks ChatGPT or Perplexity a question your website can answer, do they cite you or a competitor? Most websites never show up in AI-generated answers because their content is not structured for how AI engines read, extract, and decide what to quote.

AEO Content AI Studio is the most comprehensive AI Engine Optimization solution available for WordPress. The scoring methodology is built on published research into how large language models select sources, not on SEO heuristics or opinions. It is the only plugin that combines a research-backed content audit with real-time AI citation monitoring across all major engines.

It connects your site to [aeocontent.ai](https://www.aeocontent.ai) and gives you everything you need to get cited.

= Know Your Score =

The plugin audits your entire site against 48 criteria developed from research into how large language models choose which sources to cite. Every page gets a score from 0 to 100 and a letter grade (A through F) so you know exactly where you stand.

The audit looks at what actually matters for AI citation: whether your content says something original, whether it can be cleanly extracted as an answer, whether it carries enough entities and data to be trustworthy, and whether it is structured in a way AI engines can parse. Content that just restates what ten other sites say gets penalized. Content with original insights, named frameworks, and clear answer patterns gets rewarded.

= See Who Is Getting Cited Instead of You =

The AI Visibility stage monitors your most important queries across five engines every day. You see exactly which prompts return your pages, which ones cite a competitor, and how your visibility changes over time. No more guessing whether your optimization work is paying off.

= Fix What Matters First =

The audit does not just tell you what is wrong. It ranks every opportunity by impact so you know which pages to fix first. Rewrite candidates show the weakest pillar for each page. Opportunities show the highest-leverage fixes across your site. You focus your time where it moves the needle the most.

= Publish Optimized Content =

The platform can read your existing posts, optimize them for AI citation readiness, and publish the updated content back to WordPress. Categories, tags, featured images, and FAQ schema are handled automatically.

= Features =

* One-click Google sign-in to create your account and connect your site
* 48-criteria site audit with per-page scoring and letter grades
* AI Visibility tracking across ChatGPT, Perplexity, Claude, Gemini, and Google AI Overviews
* Daily visibility monitoring with citation tracking and competitor comparison
* Rewrite candidates ranked by priority with weakest-pillar breakdown
* Opportunity list with impact scores and fix guidance
* Full page inventory with AEO Rank, word count, and inbound links
* Run site audits from WordPress admin with real-time progress
* Content publishing with AI optimization back to WordPress
* Zero frontend footprint. Nothing added to your public pages

= How It Works =

1. Install the plugin and click **Continue with Google**
2. Your account and site connection are created in one click
3. The platform crawls your site and scores every page against the full criteria set
4. Review your audit, fix the highest-impact issues, and track visibility improvements
5. Optimized content can be published back to WordPress directly from the platform

= Onboarding =

Start from **AEO Content** in your WordPress admin sidebar:

1. Click **Continue with Google** to sign in or create an account
2. The popup closes automatically and your site is connected
3. Or use **Create Account Manually** / **I Already Have an Account** for email-based setup

= External Service =

This plugin connects to the AEO Content platform at `aeocontent.ai` for the following purposes:

* **Registration** (on initial setup): When you connect your site from WordPress admin, the plugin sends your site URL to `https://www.aeocontent.ai/api/v1/plugin/register` to register the connection. No content or personal data is transmitted.
* **Heartbeat** (periodic): The plugin sends your site URL, plugin version, WordPress version, PHP version, and enabled features to `https://www.aeocontent.ai/api/v1/plugin/heartbeat`. This keeps the platform in sync and delivers any pending updates. No personal data or site content is transmitted.
* **Audit Report** (on demand): When you view the Audit Report page, the plugin fetches your site's audit data from `https://www.aeocontent.ai/api/v1/audits/{site-slug}`. This returns scoring data and recommendations generated by the platform. Audit results are cached locally for 1 hour.
* **AI Visibility** (on demand): When you open the AI Visibility stage, the plugin fetches your site's visibility snapshot from `https://www.aeocontent.ai/api/v1/visibility/{site-slug}?include=timeline`. This returns citations, engine coverage, competitors, alerts, and score trend history. The plugin prefers this dedicated visibility report over stale audit-embedded visibility data.

All communication uses HTTPS and authenticated platform credentials. The platform's [Terms of Service](https://www.aeocontent.ai/terms) and [Privacy Policy](https://www.aeocontent.ai/privacy) apply to data processed on the platform side.

== Installation ==

1. Upload the `aeo-content-ai-studio` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **AEO Content > Settings** and click **Continue with Google** to connect your site
4. Enable the **Content Publishing** feature
5. View your audit score under **AEO Content > Audit Report**

Alternatively, search for "AEO Content AI Studio" in the WordPress plugin directory and install directly.

== Frequently Asked Questions ==

= Do I need an AEO Content account? =

Yes. The plugin connects to the AEO Content platform which manages content optimization and audit scoring. Visit [aeocontent.ai](https://www.aeocontent.ai) to get started with a free audit.

= What does the Audit Report show? =

The site audit evaluates 48 criteria across five pillars: Content Originality (25%), Content Uniqueness (25%), Extractability (25%), Entity and Data Richness (15%), and Structural Signals (10%). Each page also gets a per-page AEO Page Rank score from 0 to 100 with letter grades (A through F) based on 17 content-specific checks. The workflow screen is organized into Connect, Diagnose, Fix, and AI Visibility stages. Most websites score between 30 and 60. Above 70 indicates strong AI citation readiness.

= What is AI Visibility? =

The AI Visibility stage tracks whether AI engines actually cite your site in their responses. It monitors your key prompts daily across ChatGPT, Perplexity, Claude, Gemini, and Google AI Overviews. You see which queries return your pages, which cite competitors instead, and how your visibility score trends over time.

= How does content publishing work? =

AEO Content AI Studio reads your posts via the plugin's REST API, optimizes them using AI, and sends updated content back. The plugin creates or updates WordPress posts with the optimized content, including categories, tags, featured images, and FAQ structured data. You control which posts are updated from the platform.

= Is it compatible with Yoast SEO / Rank Math? =

Yes. The plugin does not inject schema or modify meta tags on the frontend. It manages post content and metadata through standard WordPress functions, so it works alongside any SEO plugin without conflicts.

= What data does the plugin send to aeocontent.ai? =

The heartbeat sends only technical metadata: site URL, plugin version, WordPress version, PHP version, and enabled features. Post content is only transmitted when you explicitly use AEO Content AI Studio to read or publish posts. All communication is encrypted via HTTPS.

= Does the plugin slow down my site? =

No. The plugin has zero frontend footprint:it does not add scripts, styles, or markup to your public pages. The heartbeat runs via WP-Cron in the background, and audit data is cached locally.

= Can I use this without the platform? =

The plugin requires a valid site connection to the AEO Content platform. Without it, the REST API endpoints remain inactive. The plugin does not break your site if disconnected:it simply has no data to display.

== Screenshots ==

1. AI Visibility:live visibility score, 7-day and 30-day deltas, citations count across 5 AI engines
2. Site Audit:Diagnose stage with critical criteria count, weakest pillar, and AEO Rank summary
3. Pages Audit:per-page AEO Rank scores with search, filters, word count, and inbound links
4. Opportunities:prioritized fix list with impact scores, pillar tags, and recovery potential
5. Rewrite Candidates:pages ranked by rewrite priority with weakest pillar and word count

== Changelog ==

= 1.2.2 =
* AI Visibility stage:live daily monitoring from 5 AI engines (ChatGPT, Perplexity, Claude, Gemini, Google AI Overview)
* Visibility overview with score, 7d/30d deltas, citation count, and critical alerts
* Citations tab:recent mentions with engine, query, page URL, and snippet
* Competitors tab:side-by-side visibility comparison against competing domains
* Trends tab:historical score movement chart
* Workflow-based 4-stage UI: Connect, Diagnose, Fix, AI Visibility with status badges
* Stage hero cards with contextual next-best-action recommendations
* Dedicated visibility API endpoint preferred over stale audit-embedded data
* Discovery profile with deterministic site analysis (entities, topics, pages)
* Google sign-in connect screen with disconnect and feature toggle
* Improved caching with short TTL during pending/refreshing states

= 1.1.0 =
* 1-click Google sign-in for account creation and site connection
* 5-pillar audit scoring (Answer Readiness, Content Structure, Trust & Authority, Technical Foundation, AI Discovery)
* Pages tab:full site inventory with per-page AEO Rank scores, categories, word counts, inbound links
* Rewrite Candidates tab:prioritized list of pages needing rewrites with tier classification and weakest pillar
* Run Full Site Audit button with real-time progress tracking (pending → discovering → auditing → seeding → completed)
* Support for up to 48 audit criteria with current and legacy slug mappings

= 1.0.0 =
* Initial release
* Content Publishing:create and update posts via AEO Content AI Studio
* Audit Report:28-criteria AI visibility score with detailed findings
* Activity Log:filterable command log with CSV export and auto-cleanup
* REST API:posts, categories, tags, publish, command dispatch, and health check
* Credential authentication with constant-time comparison
* Periodic heartbeat for platform connectivity
* Admin dashboard with three pages: Audit Report, Settings, Activity Log

== Privacy ==

This plugin connects to the external service at aeocontent.ai. See the "External Service" section in the Description for full details on what data is transmitted and when.

The plugin stores the IP address of incoming API requests in the activity log for security and diagnostic purposes. Log entries are automatically deleted after 90 days. No cookies are set, no users are tracked, and no analytics are collected on the frontend.

Local logging remains available for secure platform-to-plugin diagnostics, but user-facing operational logs are no longer rendered inside the WordPress admin UI.

== Upgrade Notice ==

= 1.2.2 =
Adds AI Visibility stage with live daily monitoring from 5 AI engines, workflow-based 4-stage UI, and discovery profiles.

= 1.1.0 =
Adds 1-click Google sign-in, 5-pillar audit scoring, per-page analysis, rewrite candidates, and full site audit from WordPress admin.

= 1.0.0 =
Initial release. Install the plugin and click Get Started to connect to the AEO Content platform.
