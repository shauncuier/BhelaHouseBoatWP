---
name: bhela-test
description: Run the BHELA regression suite (accounting, security, admin UI, OTP, SMS gateway) and report what broke. Use before any release, after touching roles, capabilities, pricing, cost sheets, the statement, salary or an admin screen, and whenever the user asks to run the tests.
---

# Run the BHELA regression suite

## The command

```bash
php tests/run.php
```

Run it from the wp-content root. Add name fragments to narrow it: `php tests/run.php security ui`.

Do **not** hand-build a `php -d extension=… ` invocation. `run.php` already works out which of
`mysqli`, `openssl`, `curl` and `mbstring` are missing from whichever binary you call it with and
re-launches each harness with them loaded. Passing them yourself is how this goes wrong.

If plain `php` is not on PATH, use LocalWP's bundled binary — it also matches the site's PHP version:

```bash
"$APPDATA/Local/lightning-services/php-8.5.1+1/bin/win64/php.exe" tests/run.php
```

Check the directory if that version has moved: `ls "$APPDATA/Local/lightning-services/"`.

## Reading the result

The runner exits non-zero if anything failed and prints only the failing harnesses' relevant lines.

| Output | Means | Do |
|---|---|---|
| `PASSED — 9 of 9` | Everything holds | Carry on |
| `[FAIL] <label>` | A real regression | Read the label — each one names the behaviour, not the mechanism |
| `DIED EARLY` | The harness stopped before finishing | **Not a test failure.** Almost always the site is not running — start it in LocalWP. Otherwise a PHP extension is missing, which means `run.php` was bypassed |
| `FATAL: …` | PHP error inside the harness | Fix the code or the harness |

`DIED EARLY` exists because a harness that stops halfway prints a screen of `PASS` lines and no
summary, which reads exactly like success. Treat any output without a final summary as a failure.

## When to run it

Always before a release — it is a pre-flight step of the release process.

Also after touching any of these, because each has a harness that exists to catch a
specific way it has already broken once:

| Changed | Harness that will catch it |
|---|---|
| `includes/roles.php`, capabilities, a new endpoint | `security-test` |
| `includes/costs.php`, cost heads, the save handler | `heads-test`, `roundtrip-test` |
| `includes/statement.php`, `expenses.php` | `july-test` — reproduces the owner's real July 2026 figures |
| `includes/salary.php`, staff rates | `salary-test` |
| Any admin screen, or `assets/admin.css` | `ui-test`, `contrast-test` |
| `includes/otp.php`, `sms.php` | `otp-test`, `balance-test` |

## Things worth knowing

- **The site must be running.** The harnesses talk to the real database.
- **`balance-test` makes one live API call** to the SMS gateway to read the credit balance. It
  sends no SMS and costs nothing, but it needs the network.
- **Fixtures are prefixed `ZZ`** and swept before every run, so a crashed run cannot skew the next.
- **`contrast-test` needs no database** — it parses `admin.css` and checks WCAG AA.

## If a harness needs to change

Read `tests/README.md` first. The rules that matter: call `bhela_test_done()` at the end (its
absence is a failure), prefix fixture posts with `ZZ`, and restore any option, role or roster
entry you modify — the suite shares state with the running site.

Do not weaken an assertion to make a run green. Each one encodes a behaviour that broke in
production at least once; if it now fails, either the code regressed or the behaviour genuinely
changed — and if it changed, say so explicitly rather than quietly editing the expectation.
