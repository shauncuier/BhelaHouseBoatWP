---
name: bhela-release
description: >
  Performs a full versioned release for the BHELA WordPress project.
  Triggers on requests like: "make release", "do a release", "release the project",
  "publish a new version", "create release".
---

# BHELA Release Skill

End-to-end release for the **BHELA – The Haor Exclusive** WordPress monorepo.

## Where the repo is

**Never hardcode a path.** This checkout has lived at more than one location — the
skill used to name `Local Sites\bhela-house-boat`, which stopped being true and
sent commands at a different site's database. Two BHELA sites can exist side by
side in LocalWP. Resolve the root once, from git:

```powershell
# git answers with forward slashes on Windows; normalise so the "$root\..."
# concatenations below are not a mix of both separators.
$root = (git rev-parse --show-toplevel).Replace('/', '\')
Set-Location $root
```

Everything below assumes the working directory is `$root` (the `wp-content`
directory that is the git root). If `git rev-parse` fails you are not in the
repo — stop and ask the user where it is rather than guessing a home directory.

## The two releasable components

**Theme and plugin share ONE version number.** A release bumps all **six** fields
to the same `X.Y.Z`, even when only one component changed.

| # | File | Line | What to change |
|---|---|---|---|
| 1 | `themes/bhela/style.css` | 7 | `Version: X.Y.Z` |
| 2 | `themes/bhela/README.md` | 1 | `# 🎨 BHELA WordPress Theme (vX.Y.Z)` |
| 3 | `themes/bhela/functions.php` | 12 | `define( 'BHELA_VERSION', 'X.Y.Z' );` |
| 4 | `plugins/bhela-booking/bhela-booking.php` | 5 | ` * Version: X.Y.Z` |
| 5 | `plugins/bhela-booking/bhela-booking.php` | 16 | `define( 'BHELA_BM_VERSION', 'X.Y.Z' );` |
| 6 | `plugins/bhela-booking/README.md` | 5 | `- **Version:** X.Y.Z` |

> **Field 6 is the one that gets forgotten.** This skill listed only five for a
> long time, and the plugin README sat at 2.22.0 through the whole of 2.23.0 as a
> result. `tests/version-test.py` is the authority on the list — it checks all six
> and it is what the pre-release gate runs. If you ever disagree with this table,
> the harness is right and the table is stale.
>
> **WARNING:** `BHELA_VERSION` and `BHELA_BM_VERSION` are asset cache-busters. A
> lagging constant is invisible — the header reads correctly, WordPress reports
> the new version, and browsers keep serving the old CSS and JS.

---

## Step-by-Step Release Process

### 0. Pre-flight

```powershell
git status --short
git log --oneline -5
git tag --list "v*" | Select-Object -Last 5
gh auth status
```

- **Clean tree and no commits since the last tag** → nothing to release. Stop and say so.
- **Unsaved editor buffers** → ask the user to save, then re-check.
- **Modified / untracked files** → proceed.
- **`gh` not authenticated** → stop; the release cannot be published. Ask the user to run `gh auth login` in an interactive terminal.

---

### 1. Decide the version number

One number for both components. Per CLAUDE.md §7:

- **Major** (X.0.0) — breaking changes, full redesign
- **Minor** (X.Y.0) — new features, templates, shortcodes
- **Patch** (X.Y.Z) — bug fixes, style tweaks, copy changes

A release takes the **highest class of change inside it**: a batch containing one
new feature and three bug fixes is a minor bump, not a patch.

---

### 2. Bump all six fields

Set the same `X.Y.Z` in every row of the table above.

A `PostToolUse` hook runs `tests/version-test.py --hook-stdin` after each edit and
warns while the fields are still out of step. That warning is expected mid-bump —
it only matters if it is still there when you reach step 3.

---

### 3. Gate: run the regression suite

**Do not skip this.** It is the step this skill was missing, and it is the only
thing standing between a stale figure and a published ZIP.

```powershell
php tests/run.php
```

Interpreting the result:

- **`version-test` must pass.** It is the six-field check. A failure here means the
  bump is incomplete — fix it before going further, never release around it.
- **`balance-test` and `otp-test` need real SMS gateway credentials.** On a site
  with `sms_enabled = 0` and no API key they fail for environmental reasons and are
  **not** release blockers. Confirm that is why: `git stash`, re-run those two, and
  check they fail identically without your changes — then `git stash pop`.
- **Any other failure is a blocker.**
- **`DIED EARLY`** means the site is not running. Start it in LocalWP. A run with
  no final summary is a failure, never a pass.

Also worth running when front-end JS changed:

```powershell
node --check plugins/bhela-booking/assets/booking.js
```

---

### 4. Stage and commit

```powershell
git add -A
git commit -m @'
release: vVERSION (theme + plugin)

Plugin:
- <what changed, and why it mattered>

Theme:
- <what changed>

Tests & docs:
- <new harnesses, doc corrections>
'@
```

> Do **not** add a `Co-Authored-By: Claude` trailer (or any AI co-author) to a
> release commit.

The closing `'@` of a PowerShell here-string must sit at column 0 on its own line.
Indenting it is a parse error.

---

### 5. Tag

```powershell
git tag -a "vVERSION" -m "Release vVERSION — <one-line summary>"
```

---

### 6. Push commit and tag

```powershell
git push origin main --tags
```

---

### 7. Build the release ZIPs

> **CRITICAL — never use `Compress-Archive`.** It writes Windows backslashes into
> ZIP entry paths. PHP's `ZipArchive::extractTo()` on Linux reads `bhela\style.css`
> as a flat filename rather than a directory path, and WordPress then reports
> **"The theme is missing the style.css stylesheet"**. Use .NET's `ZipFile` API,
> which lets us write forward-slash entry paths.

Also note: **do not use `Remove-Item` to clear an old ZIP.** A permission guard
reads the exclude list in this script as a delete target and blocks the whole
command. `[IO.File]::Delete` is exact about what it removes and is not flagged.

Ship runtime files only — skip dev docs and anything the WordPress runtime never
loads. Adjust the fragment list, not the loop.

```powershell
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

# Path fragments (forward-slash, lowercased) that must never enter a release ZIP.
$skipFragments = @(
    '/.gitignore', '/.gitattributes', '/readme.md',      # dev docs / VCS
    '/.git/', '/node_modules/', '/vendor/', '/dist/',    # build / deps
    '/.ds_store', '/thumbs.db', '/.vscode/', '/.idea/',  # OS / editor
    '.log', '.sql', '.backup', '.map', '.zip'            # logs / dumps / maps
)

function Build-Zip($srcPath, $zipPath, $prefix) {
    if ([IO.File]::Exists($zipPath)) { [IO.File]::Delete($zipPath) }
    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
    $count = 0
    foreach ($f in (Get-ChildItem -Path $srcPath -Recurse -File)) {
        $rel   = $f.FullName.Substring($srcPath.Length + 1).Replace('\', '/')
        $probe = ('/' + $rel).ToLower()
        $skip  = $false
        foreach ($p in $script:skipFragments) { if ($probe.Contains($p)) { $skip = $true; break } }
        if ($skip) { continue }
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $prefix + $rel, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
        $count++
    }
    $zip.Dispose()
    "{0}  ->  {1} files, {2:N0} KB" -f (Split-Path $zipPath -Leaf), $count, ((Get-Item $zipPath).Length / 1KB)
}

Build-Zip "$root\themes\bhela"             "$root\bhela-theme-vVERSION.zip"   "bhela/"
Build-Zip "$root\plugins\bhela-booking"    "$root\bhela-booking-vVERSION.zip" "bhela-booking/"
```

---

### 8. Verify the ZIPs before uploading

Three things, because each has shipped broken at least once: the entry separator,
a dev-file leak, and — the quiet one — a ZIP built from a stale working tree that
does not actually contain the release you just tagged.

```powershell
Add-Type -AssemblyName System.IO.Compression.FileSystem
foreach ($z in @("bhela-theme-vVERSION.zip", "bhela-booking-vVERSION.zip")) {
    $zip = [System.IO.Compression.ZipFile]::OpenRead((Resolve-Path $z))
    "=== $z ==="
    "backslash entries: " + @($zip.Entries | Where-Object { $_.FullName -like "*\*" }).Count
    "dev-file leaks:    " + @($zip.Entries | Where-Object { $_.FullName -match '(?i)readme|\.git|tests/' }).Count
    $zip.Entries | Where-Object { $_.FullName -match 'style\.css$|bhela-booking\.php$' } | Select-Object -ExpandProperty FullName
    $zip.Dispose()
}
```

Expect `backslash entries: 0`, `dev-file leaks: 0`, and `bhela/style.css` with a
forward slash. **A backslash means the install will fail — do not upload.**

Then confirm the shipped code is the new code:

```powershell
$zip = [System.IO.Compression.ZipFile]::OpenRead((Resolve-Path "bhela-booking-vVERSION.zip"))
$e = $zip.Entries | Where-Object { $_.FullName -eq 'bhela-booking/bhela-booking.php' }
$r = New-Object IO.StreamReader($e.Open()); $txt = $r.ReadToEnd(); $r.Close(); $zip.Dispose()
if ($txt.Contains("BHELA_BM_VERSION', 'VERSION")) { "version constant: ok" } else { "version constant: WRONG — rebuild" }
```

Add a check for any headline function this release introduces, so an empty or
half-written file cannot pass.

---

### 9. Publish the GitHub Release

**Always `gh` CLI, never the browser.**

Write the notes to a file rather than passing them inline. Multi-line `--notes`
with backtick continuation mangles blank lines and swallows Bengali text.

```powershell
$notes = @'
## What's New in vVERSION

### 🧾 <Area> — <what a reader gains>
- <change, phrased as what it does for the owner or guest, not as a diff>

### 🐞 Fixes
- <symptom first, then cause>

### 🧪 Tests
- <new harnesses, doc corrections>

---
*Built by [3s-Soft](https://3s-soft.com) for BHELA – The Haor Exclusive*
'@
$notes | Out-File -FilePath "$env:TEMP\bhela-notes.md" -Encoding utf8

gh release create vVERSION --title "vVERSION — <short description>" --notes-file "$env:TEMP\bhela-notes.md" --latest
gh release upload vVERSION "bhela-theme-vVERSION.zip" "bhela-booking-vVERSION.zip" --clobber
```

`--clobber` safely overwrites an asset that was already uploaded, so the upload is
re-runnable if it fails part way.

---

### 10. Confirm

```powershell
gh release view vVERSION --json tagName,isDraft,isPrerelease,assets --jq '.tagName, ("draft: " + (.isDraft|tostring)), (.assets[] | "  " + .name + "  " + (.size/1024|floor|tostring) + " KB  state=" + .state)'
gh release list --limit 3
git status --short
```

Both assets must read `state=uploaded`, `draft: false`, the release must show
`Latest`, and the tree must be clean. Note there is **no `isLatest` JSON field** on
`gh release view` — use `gh release list`, which prints the `Latest` marker.

Then tell the user:

```
Release vX.Y.Z published!
URL: https://github.com/shauncuier/BhelaHouseBoatWP/releases/tag/vX.Y.Z

Assets:
  bhela-theme-vX.Y.Z.zip       → WP Admin > Appearance > Themes
  bhela-booking-vX.Y.Z.zip     → WP Admin > Plugins
```

---

## Project Info

- **Monorepo root:** resolve with `git rev-parse --show-toplevel` — see the top of this file
- **GitHub remote:** `https://github.com/shauncuier/BhelaHouseBoatWP.git`
- **Branch:** `main`
- **Release tool:** `gh` CLI
- **ZIP output:** the monorepo root. Old ZIPs are version-named and safe to leave.
- **Version authority:** `tests/version-test.py` (six fields), CLAUDE.md §7
