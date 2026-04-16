# WordPress.org Full Plan

This document consolidates the full WordPress.org plugin growth, support, conversion, and release plan for `aeo-content-ai-studio`.

## Current Implementation Status

As of 2026-04-16:

- latest git commit on `main`: `4516be2`
- latest public plugin release already live on WordPress.org: `1.2.7`
- current local repo has unreleased WordPress.org growth work for the next release
- target next release: `1.2.8`

### Unreleased Local Work Already Implemented

- review prompt infrastructure after real success milestones
- review milestone recording on:
  - successful Studio or Google connect
  - completed audit fetch
  - successful publish
- public Playground blueprint file prepared at:
  - `assets/blueprints/blueprint.json`
- test coverage for the new review-prompt and publish milestone behavior

### Validation Already Completed For The Unreleased Work

- `php composer.phar run test` passed
- `php composer.phar run phpcs` passed
- `assets/blueprints/blueprint.json` validated as JSON

### Important Release Caveat

The public WordPress.org `Preview` button will not appear until both of these are done:

1. `assets/blueprints/blueprint.json` is published to WordPress.org SVN `assets/blueprints/`
2. public preview is enabled in the plugin's WordPress.org Advanced View

## Objective

Improve plugin visibility and conversion on the WordPress.org plugin directory by:

- increasing trust signals
- improving install-to-activation conversion
- improving support discoverability
- enabling a public Preview flow
- collecting legitimate reviews only after real product value
- pushing more install traffic through the official WordPress.org page

## Current Baseline

As of April 2026, the main constraints are:

- very low active installs
- zero or very few reviews
- an empty or near-empty support forum
- WordPress.org assets already exist, so banners and screenshots are not the primary bottleneck
- the plugin page may not yet show a public `Preview` button if the blueprint is not live in WordPress.org SVN assets

## Highest-Impact Priorities

1. Get the first real reviews.
2. Seed and actively maintain the support forum.
3. Enable a public WordPress.org Preview experience.
4. Tighten readme metadata for real search intent.
5. Add a short demo video.
6. Route install traffic through the official WordPress.org page instead of relying on direct ZIP installs.

## Important Rules

- Do not fake reviews.
- Do not fabricate support topics from fake users.
- Do not offer incentives, pressure, or gated asks for reviews.
- Do not keyword-stuff the readme or tags.
- Do not use competitor brand names as tags.
- Do not ship meaningless release churn just to appear frequently updated.
- Use real human WordPress.org support-rep accounts, not only a brand account.

## Local Code Already Prepared

The following local changes are already prepared in the plugin repo:

- `includes/class-aeo-settings.php`
- `includes/class-aeo-command-runner.php`
- `includes/class-aeo-audit-api.php`
- `tests/AEOCASSettingsTest.php`
- `tests/AEOCASAuditApiTest.php`
- `tests/AEOCASCommandRunnerTest.php`
- `assets/blueprints/blueprint.json`

These changes add:

- a lightweight review prompt after real success milestones
- a WordPress.org Playground preview blueprint
- review milestones triggered from successful audit completion and successful publish

## Release Plan

### Version

Release these changes as `1.2.8`.

### Files to bump

- `aeo-content-ai-studio.php`
- `readme.txt`
- `tests/bootstrap.php`
- `CHANGELOG.md`

### Changelog bullets for 1.2.8

- Added a review prompt after real plugin success milestones
- Added a public WordPress.org preview blueprint
- Improved WordPress.org conversion and support discoverability

### Validation commands

Run:

```bash
php composer.phar run test
php composer.phar run phpcs
./build-zip.sh
```

Confirm the built ZIP reports:

- `Version: 1.2.8`

### Git

Commit and push:

```bash
git add ...
git commit -m "chore: release wordpress plugin v1.2.8"
git push origin main
```

### WordPress.org SVN

Publish manually using local SVN:

1. sync trunk from the built ZIP contents
2. create `tags/1.2.8` from trunk
3. publish the preview blueprint to WordPress.org SVN root assets:
   - `assets/blueprints/blueprint.json`

Important:

- the blueprint must be published in WordPress.org SVN `assets/`
- it is not enough for the blueprint to exist only in the local git repo or plugin trunk

### Post-release verification

Verify via public SVN:

- trunk plugin file shows `Version: 1.2.8`
- trunk readme shows `Stable tag: 1.2.8`
- `tags/1.2.8` exists
- `assets/blueprints/blueprint.json` exists publicly

## WordPress.org UI Tasks

These tasks should be done in Chrome while logged into the plugin owner account.

### Advanced View

URL:

- `https://wordpress.org/plugins/aeo-content-ai-studio/advanced/`

Tasks:

1. Add 2 to 3 real human `Support Representatives`.
2. Confirm plugin owner and committers are correct.
3. Find the preview or Playground controls and enable public preview if available.
4. Confirm the preview configuration references the published blueprint.

### Support Forum

URL:

- `https://wordpress.org/support/plugin/aeo-content-ai-studio/`

Create these 3 official topics from a support-rep account:

1. `Start Here: Install, connect, and run your first audit`
2. `Troubleshooting: WordPress connection and Push as Draft`
3. `Before You Post: What we support here`

Sticky:

- `Start Here: Install, connect, and run your first audit`
- `Before You Post: What we support here`

If sticky controls are missing, verify the poster account has support-rep privileges.

## Forum Topic Drafts

### Topic 1

Title:

`Start Here: Install, connect, and run your first audit`

Body:

```text
Welcome to support for AEO Content AI Studio.

If you are just getting started:

1. Install and activate the plugin
2. Open AEO Content in WordPress admin
3. Connect your site to AEO Content AI Studio
4. Run your first audit
5. Review opportunities and visibility data
6. Use Push as Draft from Studio when you are ready to publish optimized content

If you need help, please open a new topic and include:

- Your WordPress version
- Your PHP version
- Plugin version
- Whether you started from WordPress admin or from Studio
- The exact error message
- Whether the issue is about connection, audits, or publishing

Please do not post API keys, tokens, admin passwords, or private site details.

For account, billing, or private data issues, contact us through aeocontent.ai instead of posting publicly here.
```

### Topic 2

Title:

`Troubleshooting: WordPress connection and Push as Draft`

Body:

```text
Common checks for connection and publishing issues:

1. Make sure the plugin is updated to the latest version
2. Confirm the site is connected in AEO Content AI Studio
3. Confirm the plugin is active on the same domain you connected in Studio
4. Refresh the Studio page and try Push as Draft again
5. Check whether your site uses www or non-www and reconnect if needed
6. Confirm your WordPress REST API is reachable
7. Disable security or cache layers temporarily if they block wp-json requests

If you open a support topic, include:

- The Studio URL you are using
- The WordPress site URL
- Whether this is a new draft or an update to an existing post
- The exact error text
- Screenshots only if they do not expose secrets

If the issue involves account-specific credentials or private logs, use private support through aeocontent.ai.
```

### Topic 3

Title:

`Before You Post: What we support here`

Body:

```text
We use this forum for:

- Plugin installation issues
- WordPress connection issues
- Audit page issues
- Publishing and Push as Draft issues
- Compatibility questions with WordPress plugins or themes

We do not handle these publicly in the forum:

- Billing
- Account access
- Private site credentials
- Security disclosures
- Anything that requires secret keys or private logs

For faster help, include your plugin version, WordPress version, PHP version, and the exact steps to reproduce the problem.

Please keep one issue per topic.
```

## Readme and Metadata Improvements

### Keep

- display name: `AEO Content AI Studio`

Do not chase a slug or display-name change just for ranking.

### Short Description

Recommended short description:

`Audit AI visibility, track citations, optimize content, and publish WordPress drafts from AEO Content AI Studio.`

### Tags

Recommended tags:

- `ai`
- `ai visibility`
- `content optimization`
- `content publishing`
- `seo audit`
- `site audit`
- `answer engine optimization`
- `citation tracking`
- `content strategy`
- `llm visibility`
- `geo`
- `wordpress publishing`

Notes:

- prioritize the first 5 tags carefully
- keep tags natural and directly relevant
- do not use unique internal jargon that no one searches for
- do not use competitor names

### FAQ additions

Make sure the readme includes:

- Studio-first connection flow
- Push as Draft troubleshooting
- where to get support
- what to include in a support request

## Demo Video Plan

Add a short demo video to the plugin page.

### Video target

60 to 90 seconds

### Flow to show

1. connect plugin
2. run or view audit
3. Push as Draft from Studio

### Publishing

- upload to YouTube or Vimeo
- embed the video URL in the readme description where WordPress.org can render it
- if the readme changes after the `1.2.8` release, publish a follow-up release

## Traffic Routing Plan

Push more install volume through WordPress.org, not through private ZIP distribution.

### Audit these surfaces

- aeocontent.ai marketing site
- Studio onboarding
- docs / help center
- install guides
- support replies
- onboarding emails

### Update messaging

Primary CTA should be:

- install from the official WordPress.org plugin page

Fallback only when necessary:

- direct ZIP install

### If no access exists

Document:

- exact URLs needing change
- exact copy replacement needed
- owner/team who must update it

## Review Prompt Strategy

The in-plugin review prompt should only appear after genuine product value.

### Trigger milestones

- `connected`
- `audit_completed`
- `publish_success`

### Prompt logic

Show the prompt only when:

- the site is connected
- and at least one meaningful outcome has happened:
  - completed audit
  - successful publish

### Prompt actions

- `Leave a Review`
- `Already Reviewed`
- `Not Now`

### Never do

- show the review prompt immediately on install
- block users behind review asks
- exchange incentives for reviews

## Preview Plan

Enable public Preview on the plugin page using WordPress.org Playground.

### Blueprint file

Path:

- `assets/blueprints/blueprint.json`

### Landing page

Preview should open directly to:

- the plugin Connect screen in wp-admin

### Requirements

- blueprint must be published to WordPress.org SVN assets
- public preview must be enabled in Advanced View

### Verify

After WordPress.org processes the assets:

- plugin page should show `Preview`
- clicking Preview should land inside the plugin admin screen

If Preview still does not appear:

- document the exact blocker from Advanced View
- capture whether the blueprint path is recognized
- confirm any missing WordPress.org toggle or validation issue

## 30-Day Execution Plan

### Week 1

- release `1.2.8`
- push `assets/blueprints/blueprint.json` to WordPress.org SVN assets
- add 2 to 3 support reps
- create and sticky the starter forum topics

### Week 2

- refine short description and tags
- add the demo video
- make all install guides point to the official WordPress.org plugin page

### Week 3

- let review prompts begin collecting legitimate feedback
- monitor support response time
- monitor whether Preview appears and works

### Week 4

- update screenshots only if current UI no longer matches
- answer all support threads promptly
- convert repeated support issues into readme and docs improvements

## Final Verification Checklist

Confirm all of the following:

- plugin page shows current version
- support forum has the 3 starter topics
- 2 topics are sticky
- support reps are assigned
- preview button appears publicly
- review prompt is present in the released codebase
- active install growth is measured from WordPress.org, not just private installs

## Expected Deliverables

- git commit hash
- SVN revision number
- confirmation that `assets/blueprints/blueprint.json` is live
- URLs of the 3 forum topics
- names of the added support reps
- whether Preview is visible publicly
- exact short description and tags now live
- list of any blockers still remaining

## Recommended Next Action

Cut and publish `1.2.8`, including the WordPress.org SVN `assets/blueprints/blueprint.json` asset, then complete Advanced View and support-forum setup in Chrome the same day.
