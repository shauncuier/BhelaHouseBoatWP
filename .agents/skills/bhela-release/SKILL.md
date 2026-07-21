---
name: bhela-release
description: >
  Performs a full versioned release for the BHELA WordPress project.
  Triggers on requests like: "make release", "do a release", "release the project",
  "publish a new version", "create release".
---

# BHELA Release Skill

This skill automates the full end-to-end release process for the **BHELA – The Haor Exclusive** WordPress project at:

```
c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content\
```

The repo contains two releasable components managed in one monorepo:

**Theme and plugin share ONE version number.** Every release bumps all five version fields below to the same `X.Y.Z`, even when only one component changed.

| Component | Path | Version file(s) |
|---|---|---|
| **Theme** `bhela` | `themes/bhela/` | `style.css` (line 7 `Version:`), `README.md` (line 1 heading), `functions.php` (line 12 `BHELA_VERSION`) |
| **Plugin** `bhela-booking` | `plugins/bhela-booking/` | `bhela-booking.php` (header line 5 AND constant line 16) |

---

## Step-by-Step Release Process

### 0. Pre-flight checks

```powershell
git -C "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content" status
git -C "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content" log --oneline -5
```

- If **working tree is clean** and no new commits since last tag → tell the user there is nothing new to release and stop.
- If there are **unstaged changes** → ask user to save files first, then re-check.
- If there are **staged/modified/untracked files** → proceed with release.

---

### 1. Decide version numbers

Ask the user (or infer from changes):
- **Major bump** (X.0.0): breaking changes, full redesign
- **Minor bump** (X.Y.0): new features, new templates, new shortcodes
- **Patch bump** (X.Y.Z): bug fixes, small style tweaks, copy changes

Theme and Plugin share **one** version number — pick a single `VERSION` and apply it to all five fields below. Do not version the components separately.

---

### 2. Bump version numbers in files

Set the **same** `X.Y.Z` in all five fields:

- `themes/bhela/style.css` line 7: `Version: X.Y.Z`
- `themes/bhela/README.md` line 1: `# 🎨 BHELA WordPress Theme (vX.Y.Z)`
- `themes/bhela/functions.php` line 12: `define( 'BHELA_VERSION', 'X.Y.Z' );`
- `plugins/bhela-booking/bhela-booking.php` line 5: ` * Version: X.Y.Z`
- `plugins/bhela-booking/bhela-booking.php` line 16: `define( 'BHELA_BM_VERSION', 'X.Y.Z' );`

> **WARNING**: All five must match. The two constants (`BHELA_VERSION`, `BHELA_BM_VERSION`) are asset cache-busters — a lagging constant ships stale CSS/JS while the header looks correct.

---

### 3. Stage and commit all changes

```powershell
git -C "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content" add -A

git -C "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content" commit -m "release: vVERSION (theme + plugin)

- <summary of theme changes>
- <summary of plugin changes>"
```

> Do **not** add a `Co-Authored-By: Claude` trailer (or any AI co-author) to release commits.

---

### 4. Create an annotated git tag

One tag for the whole release:

```powershell
git -C "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content" tag -a "vVERSION" -m "Release vVERSION — <one-line summary>"
```

---

### 5. Push commits and tag to GitHub

```powershell
git -C "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content" push origin main --tags
```

---

### 6. Build release ZIP files

> **CRITICAL — DO NOT use `Compress-Archive`**. PowerShell's `Compress-Archive` writes Windows backslashes (`\`) into ZIP entry paths. PHP's `ZipArchive::extractTo()` running inside LocalWP's Linux environment treats `bhela\style.css` as a flat filename, not a directory path — causing WordPress to report **"The theme is missing the style.css stylesheet"**.
>
> Always use .NET's `ZipFile` API directly, which lets us write Unix-style forward-slash paths.

Ship **runtime files only**. Skip dev-only files (`.gitignore`, `README.md`) and anything the WordPress runtime never loads (VCS/editor/build/backup artifacts). The shared filter below excludes by path pattern; adjust the list, not the loop.

```powershell
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

# Path fragments (forward-slash, lowercased) that must NEVER enter a release ZIP.
$exclude = @(
    '/.gitignore', '/.gitattributes', '/readme.md',      # dev docs / VCS
    '/.git/', '/node_modules/', '/vendor/', '/dist/',     # build / deps
    '/.ds_store', '/thumbs.db', '/.vscode/', '/.idea/',   # OS / editor
    '.log', '.sql', '.backup', '.map', '.zip'             # logs / dumps / maps
)
function Should-Skip($rel) {
    $r = ('/' + $rel).ToLower()
    foreach ($p in $exclude) { if ($r.Contains($p)) { return $true } }
    return $false
}

# === THEME ZIP ===  (keeps style.css, screenshot.png, theme.json, php, assets)
$themePath = "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content\themes\bhela"
$themeZip  = "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content\bhela-theme-vVERSION.zip"
if (Test-Path $themeZip) { Remove-Item $themeZip }
$zip = [System.IO.Compression.ZipFile]::Open($themeZip, [System.IO.Compression.ZipArchiveMode]::Create)
Get-ChildItem -Path $themePath -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($themePath.Length + 1).Replace('\', '/')
    if (Should-Skip $rel) { return }
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, "bhela/" + $rel, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
}
$zip.Dispose()

# === PLUGIN ZIP ===
$pluginPath = "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content\plugins\bhela-booking"
$pluginZip  = "c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content\bhela-booking-vVERSION.zip"
if (Test-Path $pluginZip) { Remove-Item $pluginZip }
$zip = [System.IO.Compression.ZipFile]::Open($pluginZip, [System.IO.Compression.ZipArchiveMode]::Create)
Get-ChildItem -Path $pluginPath -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($pluginPath.Length + 1).Replace('\', '/')
    if (Should-Skip $rel) { return }
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, "bhela-booking/" + $rel, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
}
$zip.Dispose()
```

Verify the ZIP entries use forward slashes before uploading:
```powershell
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($themeZip)
$zip.Entries | Where-Object { $_.FullName -like "*style*" } | Select-Object FullName
$zip.Dispose()
# Expected: bhela/style.css   (forward slash — if you see bhela\style.css the install WILL fail)
```

---

### 7. Create the GitHub Release via gh CLI

**Always use `gh` CLI — never the browser.**

```powershell
gh release create vVERSION `
  --title "vVERSION — <short description>" `
  --notes "## What's New in vVERSION

### Theme (vVERSION) — bhela
- <bullet point changes>

### Plugin (vVERSION) — bhela-booking
- <bullet point changes>

---
*Built by [3s-Soft](https://3s-soft.com) for BHELA – The Haor Exclusive*" `
  --latest
```

Then upload the ZIP assets:

```powershell
gh release upload vVERSION `
  "bhela-theme-vVERSION.zip" `
  "bhela-booking-vVERSION.zip" `
  --clobber
```

The `--clobber` flag safely overwrites if the asset was already uploaded.

---

### 8. Confirm success

Show the user:
```
Release vX.Y.Z published!
URL: https://github.com/shauncuier/BhelaHouseBoatWP/releases/tag/vX.Y.Z

Assets:
  bhela-theme-vX.Y.Z.zip       → WP Admin > Appearance > Themes
  bhela-booking-vX.Y.Z.zip     → WP Admin > Plugins
```

---

## Quick Reference — Version File Locations

| File | Line | What to change |
|---|---|---|
| `themes/bhela/style.css` | 7 | `Version: X.Y.Z` |
| `themes/bhela/README.md` | 1 | `# 🎨 BHELA WordPress Theme (vX.Y.Z)` |
| `themes/bhela/functions.php` | 12 | `define( 'BHELA_VERSION', 'X.Y.Z' );` |
| `plugins/bhela-booking/bhela-booking.php` | 5 | ` * Version: X.Y.Z` |
| `plugins/bhela-booking/bhela-booking.php` | 16 | `define( 'BHELA_BM_VERSION', 'X.Y.Z' );` |

All five carry the **same** number.

## Project Info

- **Monorepo root:** `c:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content\`
- **GitHub remote:** `https://github.com/shauncuier/BhelaHouseBoatWP.git`
- **Branch:** `main`
- **Release tool:** `gh` CLI (already authenticated)
- **ZIP output location:** monorepo root (safe to leave old ZIPs, they are version-named)
