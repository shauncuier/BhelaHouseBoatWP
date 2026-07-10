# 🛶 BHELA — Theme + Booking Engine Setup Guide

Totally redesigned build ("Midnight Monsoon" design). Old theme & plugin were removed and replaced.

## What's Installed

| Component | Location | What it does |
|---|---|---|
| **BHELA theme** (v2.0) | `themes/bhela/` | Complete redesigned site: hero with live rate estimator, cabins, spots, food, reviews, FAQ, schedule, gallery, policies |
| **BHELA Booking Engine** (plugin) | `plugins/bhela-booking/` | Booking form + pricing engine + statuses + **invoices** + emails |

## 🚀 Activation (do these once)

1. **wp-admin → Plugins → Activate "BHELA Booking Engine"** (activate plugin FIRST)
2. **wp-admin → Appearance → Themes → Activate "BHELA"**
   - On activation the theme auto-creates these pages with the right templates: কেবিন ও রেট, ট্রিপ সিডিউল, খাবার মেনু, গ্যালারি, FAQ, বুক করুন, নীতিমালা — plus a primary menu.
3. **Settings → Reading**: set "Your homepage displays" → the front page design shows automatically (front-page.php).
4. Visit **Bookings → Settings** to review: phones, WhatsApp, bKash/Nagad numbers, holiday dates, cabin rates.

## 💰 How the Booking Engine works

- **Pricing:** Fri/Sat + holiday dates = Regular rate; other days = Weekday rate (−20%). Rates & holidays editable in Bookings → Settings.
- **Customer flow:** Book Now page → form calculates জনপ্রতি/মোট/৫০% অগ্রিম live → submits → gets Booking No + WhatsApp deep-link + invoice link.
- **Admin flow:** Bookings menu → each booking has editable details, payment fields (method, TXN ID, paid amount) and status: Pending → Advance Paid → Confirmed → Completed / Cancelled.
- **Invoices:** Auto-numbered (BH-2026-0001). "View / Print Invoice" button in admin; customers get a secure link (print → Save as PDF). Shows advance due, paid, balance, bKash/Nagad info, terms.
- **Emails:** Admin notified on every request. Customer emailed on request + automatically when status becomes Confirmed (if email given). Local sites don't send real mail — use an SMTP plugin (e.g. WP Mail SMTP) in production.

## 🖼️ Content you can edit

- **Rates/holidays/payment numbers:** Bookings → Settings
- **Contacts shown in theme:** Appearance → Customize → BHELA Contact (falls back to plugin settings)
- **Gallery images:** drop files into `themes/bhela/assets/images/{spots,boat,cabins,food,hero}/` — gallery page auto-lists them
- **Schedule table:** edit `themes/bhela/page-templates/template-schedule.php` (or ask to make it admin-editable later)

## ✅ Post-launch checklist

- [ ] Install an SMTP plugin so emails actually deliver
- [ ] Replace placeholder reviews on homepage with real guest reviews
- [ ] Set real bKash/Nagad numbers in Bookings → Settings
- [ ] Add 2027+ holiday dates as needed
- [ ] Test one full booking + view its invoice + confirm status email

---

## ✏️ How to Edit Pages (3 ways)

The 7 auto-created pages (Cabins, Schedule, Food, FAQ, Gallery, Book Now, Policies) get their design from **theme templates** — that's why the editor looks "empty". You have 3 editing options:

### 1. Elementor (now fully supported)
- Install Elementor → edit ANY page with Elementor → **the moment a page is built with Elementor, it takes over the full layout** (theme hero/sections disappear, your Elementor design shows edge-to-edge with the theme header/footer).
- For blank-canvas pages choose template **"BHELA — Full Width (Elementor)"**, or Elementor's own Canvas template (hides header/footer too).
- Works on the homepage as well — design it in Elementor and it replaces the default homepage.
- Elementor Pro Theme Builder can even override the header/footer (locations registered).

### 2. Gutenberg (default editor)
- On templated pages: anything you add in the editor appears in a styled section **below the page hero, above the template content**. Use the 🛶 BHELA block patterns.
- On the **হোম (front) page**: adding any blocks REPLACES the default homepage design entirely. Leave it empty to keep the designed homepage.
- To fully control a page with Gutenberg: in the page editor sidebar → **Template → Default**, then the page shows only your editor content.

### 3. Keep the built-in design
- Leave page content empty → the premium coded design shows. Rates/trips/contacts are edited in **Bookings → Settings** and **Bookings → Trip Calendar**, not in page editors.
