# BHELA — Website Owner's Manual

*ভেলা হাউসবোট ওয়েবসাইট চালানোর সম্পূর্ণ গাইড — বুকিং, ইনভয়েস, রেট, ব্লগ ও সেটিংস।*
For the site owner / admin · No coding needed · Updated August 2026 · Theme &amp; Plugin v2.15.0

A styled, shareable version of this manual is published as an Artifact:
**https://claude.ai/code/artifact/2aa5e859-c88b-4952-828d-d14fbbb9ba24**

---

## 1. Logging in

Everything is managed from the WordPress dashboard.

1. Open `yourdomain.com/wp-admin`.
2. Enter your **username and password** (keep these private).
3. You land on the WordPress home screen. The left menu holds everything below.

> 💡 The menu you'll use most is **🛶 Bookings**. Its first item, **📊 Dashboard**, is your control centre — booking counts, money in, upcoming trips, recent activity, a setup checklist, and one-click buttons for every task. Open it first each day. Everything else (trip calendar, gallery, reviews, settings, guide) lives in the same menu.

## 2. Managing bookings

Every website booking is saved automatically. Go to **Bookings → All Bookings**. Each row shows name, invoice number, travel date, and status. Click one to open it.

Inside a booking you can:
- See the guest's name, phone, email, travel date, cabins, guests, and price.
- Record how much they've paid + payment method / transaction ID.
- Change the **status** (see §3).
- Open the guest's **invoice**.

Add a phone/walk-in booking yourself: **Bookings → Add New Booking**.

## 3. Booking status & notifications

| Status | Use it when… |
|---|---|
| **Pending** | New request; nothing paid yet |
| **Advance Paid** | Guest paid the 50% advance; seat held |
| **Confirmed** | Locked in — sends the guest a confirmation |
| **Completed** | Trip is over |
| **Cancelled** | Booking called off |

Automatic behavior:
- Setting a booking to **Confirmed** emails the guest a confirmation (if they gave an email).
- If **SMS is on**, a status change also texts the guest.
- You (owner) get an email + SMS on **every new booking**.

You control which of these fire — see §7 (Email) and §8 (SMS).

## 4. Trip calendar & cabin availability

**Bookings → Trip Calendar**. Press **Add Trip** for a new row; each row is a departure:

| Column | What to put |
|---|---|
| Start Date | The departure date |
| End Date | Leave blank for a standard 2-day 1-night trip. For a **Full Boat** or longer charter, set the real return date |
| Display Label | Fills in automatically from the dates (e.g. `7 – 8 Aug 2026`) — override only if you want |
| Days | Bangla day names, fill in automatically (e.g. `শুক্র – শনি`) |
| **ছুটি** | Tick if this departure is a **holiday**. It then charges the Regular rate (no 20% weekday discount) and shows "ছুটির দিন" on the site |
| Status | Available / Filling Fast / Booked |
| **Booked Cabins** | How many of the 6 cabins are taken (0–6) |
| Delete | Tick to remove the row, then Save |

Fill Start Date and the label, days and duration set themselves. Press **Save** and the website updates instantly.

**How availability works — now automatic:** the moment a booking is **Advance Paid** or **Confirmed**, its cabins come out of that date's availability by themselves — you don't have to touch the calendar, and every manager plus the website sees the same live number. The **Booked Cabins** field is just a *manual hold (minimum)* — use it to block cabins for a phone booking or a full-boat charter. Setting it to **6** shows **Full Booked**. Each row shows the live count: *এখন বুকড x/6 · খালি y (অটো)*.

> 🎉 **Holidays live here now.** Marking a trip's **ছুটি** box is the only place you set holiday pricing — there is no separate holiday list in Settings anymore.

## 5. Invoices

Every booking has a branded invoice with a **private link** — safe to send.
- Invoice link is on the booking edit screen and inside the confirmation email.
- Shows each cabin, **per-person rate**, guests, total, advance, paid, due.
- Payment details (bKash, Nagad, bank, QR) come from Settings.
- The link has a private key, so only someone with it can open — safe to WhatsApp/email.

## 6. Cabin rates & pricing days

All in **Bookings → Settings**:
- **Cabin Rates** — each cabin has a Regular/Holiday rate and a cheaper Weekday rate (per person). Edit + Save.
- **Weekend Days** — tick days that charge the Regular rate (default Fri, Sat).
- Every other day uses the discounted Weekday rate.

> 🗓️ **Holidays** are no longer a list in Settings. Mark a departure as a holiday by ticking its **ছুটি** box in the **Trip Calendar** (§4) — it then charges the Regular rate with no weekday discount.

> ⚠️ Children: 0–4 free, 4–8 pay a flat **৳5,000** each (no weekday discount), 9+ full rate — automatic. The amount is editable at **Settings → শিশু (৪–৮) ফি**.

## 7. Email notifications

**Bookings → Settings → 📧 Email Notifications**:
- **Enable emails** master switch.
- Toggle each: new booking→you, new booking→customer, confirmed→customer.
- **Owner notification email** (blank = business email).
- **From name** and **Reply-To**.
- **Send Test Email** to verify.

> 📮 Delivery is handled by **FluentSMTP** (already set up). If emails stop, check it first.

## 8. SMS notifications (optional)

**Bookings → Settings → 📱 SMS Notifications** — off by default. To enable:
1. Buy an SMS package (e.g. **BulkSMSBD**); get API key + Sender ID.
2. Tick **Enable SMS**, choose **BulkSMSBD**.
3. Paste API Key + Sender ID.
4. Set the **Admin SMS number** (blank = Phone 1).
5. Save → **Send Test SMS**.

Editable Bangla templates use placeholders auto-filled at send: `{name} {phone} {invoice} {date} {cabin} {guests} {total} {advance} {due} {status}`.

Different provider? Pick **Custom** and paste its API URL + field names — no code change.

## 9. Guest reviews

**Bookings → All Reviews → Add New**. Guest name = Title, their words = content, set a star rating + trip type. Published reviews appear on the homepage.

## 10. Gallery — গ্যালারি ছবি

Photos are managed from **Bookings → 🖼️ Gallery**.

**Add many photos at once (fastest):** **Bookings → 🖼️ Bulk Upload** → **ছবি বাছাই করুন** → select a whole batch from the media library (drag-drop new files in and pick them all) → optionally tick a category → **যোগ করুন**. Each photo becomes its own gallery item, added after the current last one. Caption and order can be refined later.

**Add one photo:** 🖼️ Gallery → *নতুন ছবি* → set the **Featured Image** (this is the photo itself) → type a **caption** in the title box → tick a **ক্যাটাগরি** → **Publish**.

| Field | What it does |
|---|---|
| Featured Image | The photo. Uses your normal media library |
| Title | The caption shown on hover and in the lightbox |
| ক্যাটাগরি | Groups the photo. Categories become the filter tabs on the page |
| Order | Lower numbers come first |

**First-time setup:** if the gallery is empty, an “ইমপোর্ট করুন” button appears offering the photos bundled with the theme. Click it once and all of them become editable items. Clicking again adds nothing — it never creates duplicates.

Add your own categories any time under **গ্যালারি → ক্যাটাগরি**; a tab appears automatically once a category has at least one photo.

> 💡 The page uses a masonry layout, so photos keep their real shape (tall photos stay tall). Because columns fill top-to-bottom, the **Order** number controls sequence rather than strict left-to-right position.

## 11. Blog — হাওর জার্নাল

**Posts → Add New** → write title + content → pick a **Category** (ভ্রমণ গাইড / হাওরের খবর / টিপস) + **Tags** → set a **Featured Image** → **Publish**. Blog is at `yourdomain.com/blog`. Category/tag pages, related posts, reading time, and a booking CTA per article are automatic. Comments are off by design (guides guests to WhatsApp instead).

## 12. Editing site text & images

**Appearance → Customize** → five BHELA panels:

| Panel | Change |
|---|---|
| BHELA Contact | Phone, WhatsApp, Messenger, email, address, and all social links — Facebook, Instagram, TikTok, LinkedIn, YouTube, X, Threads (icons appear in the footer; blank = hidden) |
| BHELA Homepage | Hero headline, badge, subtitle |
| BHELA Images | Hero, food, rooftop, cabin, spot photos (blank = defaults) |
| BHELA Tracking | Google Analytics ID, Facebook Pixel ID |
| BHELA Custom Code | Paste code into `<head>`, after `<body>`, or before `</body>` |

**Tracking setup (one-time):** paste just the **ID** — GA4 Measurement ID (`G-…`) from Google Analytics and the Pixel ID (numbers) from Meta Events Manager. The code is added automatically. Your own admin visits are never counted.

**Custom Code (advanced):** the **BHELA Custom Code** panel has three boxes — **Header** (`<head>`), **Body Top** (right after `<body>`), and **Footer** (before `</body>`). Paste any snippet — site-verification meta tags, Google Tag Manager, chat widgets, extra scripts — and it appears in the right place site-wide, no theme editing. Only administrators can save here.

Cabin names, prices, payment details are in **Bookings → Settings**. Your contact info set there is the source of truth for the whole site (footer, buttons, invoice, emails).

## 13. Contact page — যোগাযোগ

The **যোগাযোগ** page (`/contact/`) is created automatically and added to the menu. It shows quick-contact cards (phone, WhatsApp, Messenger, email), your address and hours, social icons, plus a **contact form**.

Form messages are **emailed to you** — the address from *Bookings → Settings → Owner notification email* (or your business email). Nothing is stored in the database. You can reply directly to the guest, because their email is set as Reply-To.

> 💡 To show the **Messenger** card, paste your `m.me/yourpage` link in **Customize → BHELA Contact → Messenger link**. Leave blank to hide that card.

## 14. Activity log — কী কী হচ্ছে

**Bookings → Activity Log** records what the plugin actually did, newest first — so you can answer "did that work?" without a developer.

- New bookings received, and emails / SMS sent or failed
- Status changes, settings saved, gallery imports and bulk uploads
- Every trip-calendar save, including which dates were removed — so a disappearing departure can always be traced

Each line is ✅ (worked) or ❌ (problem). Filter by type with the buttons at the top. Use **লগ মুছুন** to clear it; the latest 300 events are kept.

## 15. Troubleshooting

| Problem | Fix |
|---|---|
| Emails not arriving | Check FluentSMTP connected + Email Notifications enabled; Send Test Email |
| SMS not sending | Gateway has balance? API key/Sender ID correct? SMS enabled? Send Test SMS |
| Wrong availability | Update **Booked Cabins** for that trip |
| Prices wrong | Check Cabin Rates + Weekend Days/Holidays |
| Looks broken after update | Full refresh (Ctrl+Shift+R); else contact developer |

> 🛟 **Golden rule:** Settings, bookings, calendar, reviews, and blog posts are safe to edit yourself. Avoid **Plugins**, **Users**, and **Theme File Editor** — those are for your developer.

---

*BHELA – The Haor Exclusive · Website by 3s-Soft · "ভেলার আকর্ষণ ভেলা নয়, হাওর!"*
