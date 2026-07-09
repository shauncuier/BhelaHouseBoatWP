# 🛶 BHELA — Houseboat WordPress Repository

Welcome to the main repository for the **Bhela Houseboat** website development. This repository holds the custom code components for the website, organized cleanly inside the WordPress `wp-content/` directory.

To maintain a clean version-controlled repository, all third-party dependencies, standard core files, and large media uploads are ignored.

---

## 📂 Repository Structure & Multi-Layer Setup

This repository is split into three main layers, each containing its own specific documentation and ignore rules:

1.  **Repository Root (Master)**
    *   This file: [README.md](file:///c:/Users/User/Local%20Sites/bhelahoureboat/app/public/wp-content/README.md) — Main landing page detailing structure and setup.
    *   [.gitignore](file:///c:/Users/User/Local%20Sites/bhelahoureboat/app/public/wp-content/.gitignore) — The main whitelisting filter that ensures only custom components and essential files are tracked.
    *   [BHELA-SETUP-README.md](file:///c:/Users/User/Local%20Sites/bhelahoureboat/app/public/wp-content/BHELA-SETUP-README.md) — Quick setup instructions for database variables and theme activation.

2.  **Custom Theme Layer (`themes/bhela/`)**
    *   [themes/bhela/README.md](file:///c:/Users/User/Local%20Sites/bhelahoureboat/app/public/wp-content/themes/bhela/README.md) — Documentation on page templates, Customizer fields, the "Midnight Monsoon" dark style system, and assets.
    *   [themes/bhela/.gitignore](file:///c:/Users/User/Local%20Sites/bhelahoureboat/app/public/wp-content/themes/bhela/.gitignore) — Ignore filters for local theme assets, caches, and build tools.

3.  **Booking Plugin Layer (`plugins/bhela-booking/`)**
    *   [plugins/bhela-booking/README.md](file:///c:/Users/User/Local%20Sites/bhelahoureboat/app/public/wp-content/plugins/bhela-booking/README.md) — Core mechanics of the Booking Engine: pricing calculations, booking statuses, billing details, and database structure.
    *   [plugins/bhela-booking/.gitignore](file:///c:/Users/User/Local%20Sites/bhelahoureboat/app/public/wp-content/plugins/bhela-booking/.gitignore) — Ignore filters for local database dumps, testing logs, and plugin-specific logs.

---

## 🚀 Setup & Installation

To run this project locally or deploy it to a staging/production server:

1.  Clone this repository or copy its contents into the `wp-content/` directory of a clean WordPress installation.
2.  Navigate to **Plugins** inside `wp-admin` and activate the **BHELA Booking Engine** plugin first.
3.  Navigate to **Appearance ➔ Themes** and activate the **BHELA** theme. On theme activation, all required pages (যেমন: কেবিন ও রেট, খাবার মেনু, গ্যালারি) are automatically generated and linked.
4.  Navigate to **Settings ➔ Reading** and set the homepage to your newly created landing page.
5.  Refer to [BHELA-SETUP-README.md](file:///c:/Users/User/Local%20Sites/bhelahoureboat/app/public/wp-content/BHELA-SETUP-README.md) for full configuration, holiday setup, and payment settings.

---

## ⚙️ Development & Git Guidelines

*   Only commit changes related directly to custom implementations in `/themes/bhela/` or `/plugins/bhela-booking/`.
*   Avoid staging automated third-party code (e.g. Gutenberg updates).
*   Media folders in uploads are git-ignored and should be synced outside of the codebase.
