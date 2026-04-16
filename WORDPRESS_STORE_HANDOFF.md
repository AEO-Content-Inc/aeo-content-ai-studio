# WordPress.org Handoff

This file is the fast resume point for the WordPress.org plugin-store work.

## Snapshot

- Date: 2026-04-16
- Repo: `aeo-content-ai-studio`
- Branch: `main`
- Current `HEAD`: `4516be2`
- Current public WordPress.org release: `1.2.7`
- Current local target release: `1.2.8`

## What Is Already Done

### Publicly Live

- plugin `1.2.7` is already live on WordPress.org
- support and docs links were previously added to the plugin UI
- WordPress.org banners, icons, and screenshots already exist

### Local Only, Not Released Yet

The following WordPress.org conversion work is implemented locally but not yet released:

- review prompt state and rendering in `includes/class-aeo-settings.php`
- review milestone recording in:
  - `includes/class-aeo-settings.php`
  - `includes/class-aeo-audit-api.php`
  - `includes/class-aeo-command-runner.php`
- new test coverage in:
  - `tests/AEOCASSettingsTest.php`
  - `tests/AEOCASAuditApiTest.php`
  - `tests/AEOCASCommandRunnerTest.php`
- public preview blueprint in:
  - `assets/blueprints/blueprint.json`
- long-form strategy doc in:
  - `WORDPRESS_STORE_FULL_PLAN.md`

## Current Worktree State

Tracked modified files:

- `includes/class-aeo-audit-api.php`
- `includes/class-aeo-command-runner.php`
- `includes/class-aeo-settings.php`
- `tests/AEOCASAuditApiTest.php`
- `tests/AEOCASSettingsTest.php`

Untracked files/directories:

- `WORDPRESS_STORE_FULL_PLAN.md`
- `WORDPRESS_STORE_HANDOFF.md`
- `assets/`
- `tests/AEOCASCommandRunnerTest.php`

## What The Local Changes Do

### Review Prompt

The plugin now has a lightweight review prompt that is intentionally conservative.

It only becomes eligible after:

- a real connection milestone
- and at least one real success milestone:
  - audit completed
  - publish succeeded

Available prompt actions:

- `Leave a Review`
- `Already Reviewed`
- `Not Now`

The prompt is designed to avoid manipulative review behavior.

### Preview Blueprint

The file:

- `assets/blueprints/blueprint.json`

is ready for WordPress.org Playground preview support.

Important:

- this file must be published to WordPress.org SVN root `assets/blueprints/blueprint.json`
- it is not enough to release it only inside plugin trunk or git

## Validation History

These checks already passed against the current local unreleased work:

```bash
php composer.phar run test
php composer.phar run phpcs
```

Result:

- PHPUnit: `OK (185 tests, 506 assertions)`
- PHPCS: passed

Blueprint validation also passed:

```bash
php -r '$json = file_get_contents("assets/blueprints/blueprint.json"); json_decode($json, true); if (JSON_ERROR_NONE !== json_last_error()) { fwrite(STDERR, json_last_error_msg()); exit(1);} echo "blueprint ok\n";'
```

## Most Important Constraint

The plugin is still version `1.2.7` in:

- `aeo-content-ai-studio.php`
- `readme.txt`
- `tests/bootstrap.php`
- `CHANGELOG.md`

So the next task is still to do the actual `1.2.8` release bump and publish.

## Tomorrow Resume Checklist

### 1. Confirm Local State

Run:

```bash
git status --short --branch
```

### 2. Bump To 1.2.8

Update:

- `aeo-content-ai-studio.php`
- `readme.txt`
- `tests/bootstrap.php`
- `CHANGELOG.md`

Add `1.2.8` notes for:

- review prompt after real success milestones
- public preview blueprint
- WordPress.org conversion improvements

### 3. Re-validate

Run:

```bash
php composer.phar run test
php composer.phar run phpcs
./build-zip.sh
```

Confirm the built ZIP reports:

- `Version: 1.2.8`

### 4. Commit And Push

Suggested commit:

```bash
git add .
git commit -m "chore: release wordpress plugin v1.2.8"
git push origin main
```

### 5. Publish To WordPress.org SVN

Do all of the following:

1. sync SVN trunk from the built ZIP contents
2. create `tags/1.2.8`
3. publish `assets/blueprints/blueprint.json` to WordPress.org SVN assets

Then verify:

- trunk is `1.2.8`
- stable tag is `1.2.8`
- tag `1.2.8` exists
- public asset `assets/blueprints/blueprint.json` exists

### 6. Use Chrome For WordPress.org Admin Tasks

In Advanced View:

- add 2 to 3 real human Support Representatives
- enable public preview if the toggle exists
- confirm the preview uses the blueprint

In the support forum:

- create the 3 starter topics
- sticky 2 topics:
  - `Start Here: Install, connect, and run your first audit`
  - `Before You Post: What we support here`

The exact copy for those topics is already in:

- `WORDPRESS_STORE_FULL_PLAN.md`

### 7. Final Verification

Confirm:

- plugin page shows the new version
- support forum topics exist
- support reps are assigned
- Preview button appears publicly, or the blocker is documented exactly

## If You Need The Full Strategy

Use:

- `WORDPRESS_STORE_FULL_PLAN.md`

That file contains:

- the long-form WordPress.org strategy
- support topic drafts
- metadata guidance
- preview plan
- review prompt policy
- 30-day follow-up plan
