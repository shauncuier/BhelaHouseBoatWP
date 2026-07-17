# BHELA — Website Owner's Manual

*ভেলা হাউসবোট ওয়েবসাইট চালানোর সম্পূর্ণ গাইড — বুকিং, ইনভয়েস, রেট, ব্লগ ও সেটিংস।*
For the site owner / admin · No coding needed · Updated July 2026

A styled, shareable version of this manual is published as an Artifact:
**https://claude.ai/code/artifact/2aa5e859-c88b-4952-828d-d14fbbb9ba24**

---

## 1. Logging in

Everything is managed from the WordPress dashboard.

1. Open `yourdomain.com/wp-admin`.
2. Enter your **username and password** (keep these private).
3. You land on the **Dashboard**. The left menu holds everything below.

> 💡 The menu you'll use most is **🛶 Bookings** — bookings, trip calendar, reviews, and all settings live there.

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

**Bookings → Trip Calendar**. Each row is a departure:

| Column | What to put |
|---|---|
| Start Date | The departure date |
| Display Label | e.g. `7 – 8 Aug 2026` |
| Days | Bangla day names, e.g. `শুক্র – শনি` |
| Status | Available / Filling Fast / Booked |
| **Booked Cabins** | How many of the 6 cabins are taken (0–6) |

**How availability works:** set *Booked Cabins = 4* → site shows "২টি কেবিন খালি" and blocks booking more than 2 cabins that date. Set **6** → shows **Full Booked** automatically. The hint "বুকিং থেকে: N" under each row shows how many cabins your real confirmed bookings use, to double-check.

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
- **Holidays** — one date per line (`YYYY-MM-DD`); charge Regular rate.
- Every other day uses the discounted Weekday rate.

> ⚠️ Children: 0–4 free, 4–8 pay 50%, 9+ full rate — automatic.

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

## 10. Blog — হাওর জার্নাল

**Posts → Add New** → write title + content → pick a **Category** (ভ্রমণ গাইড / হাওরের খবর / টিপস) + **Tags** → set a **Featured Image** → **Publish**. Blog is at `yourdomain.com/blog`. Category/tag pages, related posts, reading time, and a booking CTA per article are automatic. Comments are off by design (guides guests to WhatsApp instead).

## 11. Editing site text & images

**Appearance → Customize** → four BHELA panels:

| Panel | Change |
|---|---|
| BHELA Contact | Phone, WhatsApp, email, Facebook/Instagram/YouTube/TikTok, address (site-wide) |
| BHELA Homepage | Hero headline, badge, subtitle |
| BHELA Images | Hero, food, rooftop, cabin, spot photos (blank = defaults) |
| BHELA Tracking | Google Analytics ID, Facebook Pixel ID, custom header code |

**Tracking setup (one-time):** paste just the **ID** — GA4 Measurement ID (`G-…`) from Google Analytics and the Pixel ID (numbers) from Meta Events Manager. The code is added automatically. Other snippets (site verification, extra pixels) go in **Custom Header Code**. Your own admin visits are never counted.

Cabin names, prices, payment details are in **Bookings → Settings**. Your contact info set there is the source of truth for the whole site (footer, buttons, invoice, emails).

## 12. Troubleshooting

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
