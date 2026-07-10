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
c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\
```

The repo contains two releasable components managed in one monorepo:

| Component | Path | Version file(s) |
|---|---|---|
| **Theme** `bhela` | `themes/bhela/` | `themes/bhela/style.css` (line 7: `Version: X.Y.Z`) and `themes/bhela/README.md` (line 1 heading) |
| **Plugin** `bhela-booking` | `plugins/bhela-booking/` | `plugins/bhela-booking/bhela-booking.php` (header line 5 AND constant line 16) |

---

## Step-by-Step Release Process

### 0. Pre-flight checks

```powershell
git -C "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content" status
git -C "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content" log --oneline -5
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

Theme and Plugin are versioned **independently**. Only bump the component that has changes.

---

### 2. Bump version numbers in files

#### Theme — `themes/bhela/style.css`
Update line 7: `Version: X.Y.Z`

#### Theme — `themes/bhela/README.md`
Update line 1 heading: `# 🎨 BHELA WordPress Theme (vX.Y.Z)`

#### Plugin — `plugins/bhela-booking/bhela-booking.php`
Update **two** places — they must always match:
- Line 5 (plugin header): ` * Version: X.Y.Z`
- Line 16 (PHP constant): `define( 'BHELA_BM_VERSION', 'X.Y.Z' );`

> **WARNING**: Always keep the plugin header version and the `BHELA_BM_VERSION` constant in sync.

---

### 3. Stage and commit all changes

```powershell
git -C "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content" add -A

git -C "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content" commit -m "release: vTHEME_VERSION theme / vPLUGIN_VERSION plugin

Theme (vTHEME_VERSION):
- <summary of theme changes>

Plugin (vPLUGIN_VERSION):
- <summary of plugin changes>"
```

---

### 4. Create an annotated git tag

Use the theme version as the umbrella release tag:

```powershell
git -C "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content" tag -a "vTHEME_VERSION" -m "Release vTHEME_VERSION — <one-line summary>"
```

---

### 5. Push commits and tag to GitHub

```powershell
git -C "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content" push origin main --tags
```

---

### 6. Build release ZIP files

> **CRITICAL — DO NOT use `Compress-Archive`**. PowerShell's `Compress-Archive` writes Windows backslashes (`\`) into ZIP entry paths. PHP's `ZipArchive::extractTo()` running inside LocalWP's Linux environment treats `bhela\style.css` as a flat filename, not a directory path — causing WordPress to report **"The theme is missing the style.css stylesheet"**.
>
> Always use .NET's `ZipFile` API directly, which lets us write Unix-style forward-slash paths.

```powershell
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

# === THEME ZIP ===
$themePath = "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\themes\bhela"
$themeZip  = "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\bhela-theme-vTHEME_VERSION.zip"
if (Test-Path $themeZip) { Remove-Item $themeZip }
$zip = [System.IO.Compression.ZipFile]::Open($themeZip, [System.IO.Compression.ZipArchiveMode]::Create)
Get-ChildItem -Path $themePath -Recurse -File | ForEach-Object {
    $rel   = $_.FullName.Substring($themePath.Length + 1).Replace('\', '/')
    $entry = "bhela/" + $rel
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entry, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
}
$zip.Dispose()

# === PLUGIN ZIP ===
$pluginPath = "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\plugins\bhela-booking"
$pluginZip  = "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\bhela-booking-vPLUGIN_VERSION.zip"
if (Test-Path $pluginZip) { Remove-Item $pluginZip }
$zip = [System.IO.Compression.ZipFile]::Open($pluginZip, [System.IO.Compression.ZipArchiveMode]::Create)
Get-ChildItem -Path $pluginPath -Recurse -File | ForEach-Object {
    $rel   = $_.FullName.Substring($pluginPath.Length + 1).Replace('\', '/')
    $entry = "bhela-booking/" + $rel
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entry, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
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
gh release create vTHEME_VERSION `
  --title "vTHEME_VERSION — <short description>" `
  --notes "## What's New in vTHEME_VERSION

### Theme (vTHEME_VERSION) — bhela
- <bullet point changes>

### Plugin (vPLUGIN_VERSION) — bhela-booking
- <bullet point changes>

---
*Built by [3s-Soft](https://3s-soft.com) for BHELA – The Haor Exclusive*" `
  --latest
```

Then upload the ZIP assets:

```powershell
gh release upload vTHEME_VERSION `
  "bhela-theme-vTHEME_VERSION.zip" `
  "bhela-booking-vPLUGIN_VERSION.zip" `
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
| `plugins/bhela-booking/bhela-booking.php` | 5 | ` * Version: X.Y.Z` |
| `plugins/bhela-booking/bhela-booking.php` | 16 | `define( 'BHELA_BM_VERSION', 'X.Y.Z' );` |

## Project Info

- **Monorepo root:** `c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\`
- **GitHub remote:** `https://github.com/shauncuier/BhelaHouseBoatWP.git`
- **Branch:** `main`
- **Release tool:** `gh` CLI (already authenticated)
- **ZIP output location:** monorepo root (safe to leave old ZIPs, they are version-named)
