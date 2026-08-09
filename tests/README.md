# BHELA regression suite

Two suites live here. They test different things and run in different places.

| | `bhela-tests.php` | `run.php` + `*-test.php` |
|---|---|---|
| Runs in | a browser, logged in as an administrator | the command line |
| Covers | pricing rules, availability, SMS templates | accounting, security, admin UI, OTP, gateway |
| Use when | you want a quick visual check | before a release, or after touching roles/money/admin |

Everything here sits outside `themes/` and `plugins/`, so the release ZIPs never carry it.

## Running the CLI suite

```bash
php tests/run.php
```

That is the whole command. Any PHP 8.x binary works — `run.php` detects which of
`mysqli`, `openssl`, `curl` and `mbstring` are missing and re-launches each harness with
them loaded. Getting that wrong by hand is not a loud failure: without `curl` the SMS
balance check reports "no working transports" and reads like a broken gateway, and
without `mbstring` the OTP harness stops after 27 passes and prints no summary at all.

On LocalWP, use the bundled binary so the version matches the site:

```bash
"$APPDATA/Local/lightning-services/php-8.5.1+1/bin/win64/php.exe" tests/run.php
```

Run a subset by name fragment:

```bash
php tests/run.php security ui
```

**The site must be running.** These harnesses talk to the real database.

## What each harness covers

| Harness | Asserts |
|---|---|
| `security-test` | No staff role holds a core capability; `upload_files` is revoked from legacy installs; the Team screen cannot grant `manage_options`; every privileged endpoint has a nonce and a capability check; the invoice link is timing-safe, uncacheable and noindexed; no gateway credential is in a tracked file |
| `july-test` | July 2026 reproduces the owner's own statement to the taka — 13 trips, 335 guests, ৳498,214 gross |
| `salary-test` | Payroll maths, and that a later pay rise cannot rewrite a month already paid |
| `heads-test` | Owner-edited cost heads, and that a legacy positional sheet converts correctly on read |
| `roundtrip-test` | The cost-sheet save handler survives a full save → read → save cycle |
| `ui-test` | Every admin screen renders with no PHP notice, no inline `<style>`, no hand-typed hex; the stylesheet loads on our screens only |
| `contrast-test` | Every colour pair in `admin.css` meets WCAG AA. No database needed |
| `otp-test` | Send, verify, throttles, the server-side submission gate, and that an OTP stays one GSM-7 segment |
| `balance-test` | Live gateway balance, the cache, and the low-credit threshold. Makes one real API call; sends no SMS |
| `version-test` | All six version fields agree. Python, not PHP — see below. No database needed |
| `yearly-test` | The yearly rollup agrees with the twelve statements it summarises; undated sheets reach no year and cannot be approved |

## The version harness is Python

`version-test.py` has a second caller: a `PostToolUse` hook in `.claude/settings.json` runs it
after any edit to one of the version files, so drift surfaces while you are still editing
rather than at release time. That hook runs in a shell where neither `php` nor `jq` exists, which
is why the check is Python and why it does its own path filtering.

`run.php` picks up `*-test.py` alongside the PHP harnesses when `python3` is on PATH, and prints
a SKIPPED line when it is not. One implementation, two triggers.

The hook only ever warns. Blocking would fire on every release — the fields are necessarily out of
step between the first edit of a bump and the last.

## Isolation

A run sees only the records it created. `bootstrap.php` restricts every query against the
plugin's post types to titles beginning `ZZ`, which is the prefix all fixtures use.

This is not tidiness. The harnesses assert on aggregates the plugin computes — "July totals
৳498,214", "13 approved trips" — so they are only meaningful while the harness owns everything
those queries can see. When a demo dataset was seeded into this site, eleven real cost sheets
landed in the same months and six harnesses went red: July reported 430 guests instead of 335.
Nothing was broken; the tests were reading someone else's data.

Reproduce that with:

```bash
BHELA_TEST_NO_ISOLATE=1 php tests/run.php july yearly salary
```

Against a site with real records that goes 0 of 3; with isolation on, 3 of 3.

Two consequences worth knowing when writing a harness:

- **`get_posts()` sets `suppress_filters => true`**, which skips `posts_where`. Isolation works
  by flipping that off in `pre_get_posts` first. A filter alone would have done nothing, silently.
- **Reads by ID are untouched** — `get_post()` and `get_post_meta()` are direct lookups, so a
  fixture can always be read back even though aggregate queries cannot see past the prefix.

## Writing another one

```php
<?php
/** One line on what this proves. */
require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'costs' );   // includes/ files, minus .php

echo "\n=== 1. what this group is about ===\n";
ok( 2 + 2 === 4, 'arithmetic holds' );

bhela_test_done();   // required — see below
```

`bootstrap.php` finds WordPress, resolves the database port, loads the admin includes,
and provides `ok()`. Name the file `*-test.php` and `run.php` picks it up.

Three rules:

1. **Call `bhela_test_done()` at the end.** Its absence is a failure. A harness that
   stops early otherwise looks identical to one that passed — which is exactly how a
   dead database once produced a green 9-of-9.
2. **Prefix fixture posts with `ZZ`.** `sweep.php` removes them before every run, so a
   crash cannot leave state that skews the next one. Thirteen orphaned cost sheets once
   turned "13 approved trips" into 26 and failed eight correct assertions.
3. **Restore whatever you change.** Options, roles and the staff roster are shared with
   the running site.

## Database connection

`bootstrap.php` defines `DB_HOST` before `wp-config.php` is read, because LocalWP serves
MySQL on a per-site TCP port while `wp-config.php` says `localhost` — which resolves to a
socket only the web server has. The port is read from LocalWP's own `sites.json`, matched
to this checkout by path, so the suite works unchanged on another machine.

Override it if you need to:

```bash
BHELA_TEST_DB_HOST=127.0.0.1:10028 php tests/run.php
```
