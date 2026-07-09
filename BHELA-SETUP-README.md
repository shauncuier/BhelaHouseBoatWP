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
