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

```powershell
# Theme ZIP
Compress-Archive `
  -Path "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\themes\bhela" `
  -DestinationPath "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\bhela-theme-vTHEME_VERSION.zip" `
  -Force

# Plugin ZIP
Compress-Archive `
  -Path "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\plugins\bhela-booking" `
  -DestinationPath "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\bhela-booking-vPLUGIN_VERSION.zip" `
  -Force
```

Verify:
```powershell
Get-Item "c:\Users\User\Local Sites\bhelahoureboat\app\public\wp-content\bhela-*.zip" |
  Select-Object Name, @{N='Size(KB)';E={[math]::Round($_.Length/1KB,1)}}
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
