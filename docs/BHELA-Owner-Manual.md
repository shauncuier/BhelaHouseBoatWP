# BHELA — Owner's Manual

*ভেলা হাউসবোট — ওয়েবসাইট ও ব্যবসা পরিচালনার সম্পূর্ণ গাইড।*
Bookings · Accounts · Store · Investors & Capital · Website · Settings · Staff
For the owner, managers and office staff · No coding needed · Updated September 2026 · Theme & Plugin **v2.41.1**

A styled, shareable version of this manual is published at:
**https://claude.ai/artifact/Mf3ATEqjbo7mE197PovfG6**

---

## Contents

- [0. Read this first — seven rules](#0-read-this-first--seven-rules)
- [1. Signing in and your menus](#1-signing-in-and-your-menus)
- [2. Your routine — daily, monthly, each season](#2-your-routine--daily-monthly-each-season)
- **Part A — Bookings**
  - [3. The Dashboard](#3-the-dashboard)
  - [4. Bookings — taking, confirming, collecting payment](#4-bookings--taking-confirming-collecting-payment)
  - [5. Prices — rates, children, Full Boat, offers, coupons](#5-prices--rates-children-full-boat-offers-coupons)
  - [6. Trip Calendar and availability](#6-trip-calendar-and-availability)
  - [7. Invoices and the WhatsApp confirmation](#7-invoices-and-the-whatsapp-confirmation)
  - [8. B2B travel agencies and referral links](#8-b2b-travel-agencies-and-referral-links)
  - [9. Trip Report](#9-trip-report)
  - [10. Guest reviews](#10-guest-reviews)
- **Part B — Accounts**
  - [11. Trip cost sheets](#11-trip-cost-sheets)
  - [12. Expenses](#12-expenses)
  - [13. Staff salary](#13-staff-salary)
  - [14. Monthly Statement](#14-monthly-statement)
  - [15. Yearly Report, B2B Report, Trip P&L, Revenue by Source](#15-yearly-report-b2b-report-trip-pl-revenue-by-source)
- **Part C — Store (inventory)**
  - [16. Item register and the monthly stock count](#16-item-register-and-the-monthly-stock-count)
- **Part D — Investors and Capital**
  - [17. How investor money works here](#17-how-investor-money-works-here)
  - [18. Investor records and portal logins](#18-investor-records-and-portal-logins)
  - [19. Agreements and Investment Records](#19-agreements-and-investment-records)
  - [20. Profit — calculate, approve, and what it does to the books](#20-profit--calculate-approve-and-what-it-does-to-the-books)
  - [21. Paying an investor — two signatures](#21-paying-an-investor--two-signatures)
  - [22. Certificates, receipts and the account statement](#22-certificates-receipts-and-the-account-statement)
  - [23. The investor portal and self-registration](#23-the-investor-portal-and-self-registration)
  - [24. Settlement, importing old payments, Cash Flow](#24-settlement-importing-old-payments-cash-flow)
  - [25. The share model screens (Distribution, Valuation, Share Issue, Funds)](#25-the-share-model-screens-distribution-valuation-share-issue-funds)
- **Part E — The website**
  - [26. Pages, text and images](#26-pages-text-and-images)
  - [27. Gallery, Spots and the Blog](#27-gallery-spots-and-the-blog)
- **Part F — Setup**
  - [28. Settings — every section explained](#28-settings--every-section-explained)
  - [29. Team — staff accounts and permissions](#29-team--staff-accounts-and-permissions)
  - [30. Activity Log and Audit Trail](#30-activity-log-and-audit-trail)
- **Part G — Keeping it safe**
  - [31. Backups, updates and security](#31-backups-updates-and-security)
  - [32. Troubleshooting](#32-troubleshooting)
  - [33. Words you will see](#33-words-you-will-see)

---

## 0. Read this first — seven rules

These seven rules protect your money records. Every screen in this system is built around them, so once you know them, nothing it does will surprise you.

1. **Money records are never deleted — they are reversed.** A wrong payment, profit entry or fund entry is corrected with a **Reverse** button, which writes an opposite entry and keeps both. The investor's balance comes out right, and a year later anyone can still see what happened and why. There is no delete button on purpose.
2. **Nothing is owed until somebody approves it.** A calculated profit is just a calculation. It becomes money owed only when a person approves it on the ➗ Profit screen.
3. **Money leaving BHELA needs two different people.** One person raises a payment; a different person approves it. The system refuses if the same person tries to do both — even the owner.
4. **Approved and closed things are locked.** An approved cost sheet, a closed stock month, an active investment, an issued certificate: the figures cannot be changed. To fix one, reopen it (and give a reason) or issue a new version.
5. **Certificates are frozen photographs.** The moment you issue one, its figures and signatures are stored forever. If a figure later changes, issue a new version (V2) — the old one stays valid-looking but says clearly that it was replaced.
6. **Everything you change is recorded.** The 📋 Activity Log answers "did the email/SMS go out?", and the 🔩 Audit Trail answers "who changed this figure, from what, and why?" The Audit Trail cannot be cleared by anyone.
7. **Take a backup before anything big** — before updating the plugin or theme, before a new season, before switching the investor model. See §31.

---

## 1. Signing in and your menus

Open **`yourdomain.com/wp-admin`** and sign in with your own username and password. Every staff member should have **their own** account (see §29) — never share one login, because every approval records *who* did it.

Down the left side you will see six BHELA menus. Each one groups one kind of work:

| Menu | What lives there |
|---|---|
| **Bookings** | All Bookings · Add New Booking · 📊 Dashboard · 📄 Trip Report · 📅 Trip Calendar · ⭐ Reviews |
| **Accounts** | 🧾 Cost Sheets · 💸 Expenses · 👷 Salary · 📈 Monthly Statement · 📚 Yearly Report · 🤝 B2B Report · 🧮 Trip P&L · 💹 Revenue by Source |
| **Store** | 📦 Item Register · 🚚 Import Register · 🔧 Monthly Stock · 📐 Inventory Report · 🏷️ Asset Report · 🔩 Audit Trail |
| **Investors** | 👤 Investors · 🧭 Dashboard · 📇 Investor Report · ⚖️ Settlement · 📝 Registrations · 📜 Certificates · 📥 Import Payments · 💵 Cash Flow |
| **Capital** | 💠 Investments · 📑 Agreements · 💼 Contributions · ➗ Profit · 🏦 Funds *(plus 💰 Distribution, 💎 Valuation and 🪙 Share Issue when the share model is on — §25)* |
| **Setup** | ⚙️ Settings · 👥 Team · 🗺️ Spots · 🖼️ Gallery · ⬆️ Bulk Upload · 📋 Activity Log · 🎯 Quick Guide |

> 💡 A staff member only sees the menus their role allows. A storekeeper sees Store; booking staff see Bookings; and so on (§29).

> 🎯 **Setup → Quick Guide** is a short, clickable version of this manual inside the dashboard.

---

## 2. Your routine — daily, monthly, each season

### Every day
- [ ] **Bookings → 📊 Dashboard.** New bookings, money in, upcoming trips, anything that needs you.
- [ ] Open each **Pending** booking. When the advance arrives, record it and set **Advance Paid** or **Confirmed** (§4).
- [ ] **Bookings → ⭐ Reviews** — approve new guest reviews waiting for you (§10).
- [ ] **Investors → 📝 Registrations** — approve or reject new investor sign-ups (§23).
- [ ] If you approve payments: check the **payment requests** waiting on the Investors 🧭 Dashboard (§21).

### After every trip
- [ ] The trip's cost sheet is filled in and submitted (§11).
- [ ] The booking is set to **Completed**.

### Every month (first week)
- [ ] All last month's **cost sheets** are checked and **approved** (§11). The month's profit is built only from approved sheets.
- [ ] Last month's **expenses** are entered (§12).
- [ ] The **salary sheet** for last month is saved (§13).
- [ ] **Capital → ➗ Profit** — approve last month's investor profit periods (§20).
- [ ] **Accounts → 📈 Monthly Statement** — read the month's profit. Print or save it as PDF.
- [ ] **Store → 🔧 Monthly Stock** — the count is done, checked and **closed** (§16).
- [ ] Pay investors what is due — raise and approve the payments (§21).

### Each season
- [ ] Check **Settings → Seasons** has this season's dates (§28).
- [ ] Update the **Trip Calendar** with all departures and holidays (§6).
- [ ] Review **cabin rates**, the **offer** and any **coupons** (§5).
- [ ] At season end: **Accounts → 📚 Yearly Report**, **Investors → ⚖️ Settlement**, and issue **Profit Certificates** (§22).

---

# Part A — Bookings

## 3. The Dashboard

**Bookings → 📊 Dashboard** is your control centre. It shows booking counts, money in, upcoming trips, recent activity, SMS balance (if SMS is on), a **Setup Checklist** (anything still showing ⬜ needs finishing), and one-click buttons for every common task. Open it first each day.

## 4. Bookings — taking, confirming, collecting payment

Every booking made on the website is saved automatically. **Bookings → All Bookings** lists them with name, invoice number, travel date and status. Click a name to open it.

**Add a phone or walk-in booking yourself:** **Bookings → Add New Booking**. Fill in the guest, travel date, cabins and guests — the price is calculated for you.

### Inside a booking you can
- See and edit the guest's name, phone, email, address, travel date, cabins, guest count (adults and children with ages) and price.
- Record **payment**: amount paid, **payment method** (bKash, Nagad, Bank Transfer, Cash), transaction ID and payment date.
- See the **balance due** — it updates as payments are recorded.
- Change the **status** (below).
- Open or copy the **invoice** link, and the ready-made **WhatsApp confirmation** message (§7).
- See the **agency** if a B2B partner brought the booking, and confirm their commission (§8).

### Statuses

| Status | Use it when… |
|---|---|
| **Pending** | A new request; nothing paid yet |
| **Advance Paid** | The advance arrived; the cabins are now held |
| **Confirmed** | Fully locked in — the guest is emailed a confirmation |
| **Completed** | The trip is over |
| **Cancelled** | Called off — the cabins are released |

- Cabins come out of the Trip Calendar's availability **automatically** once a booking is **Advance Paid** or **Confirmed** (§6).
- You are emailed (and texted, if SMS is on) about **every new booking**. Which emails/SMS go to the guest is set in **Settings** (§28).

### Mobile verification on the booking form
If **OTP** is switched on (Settings → SMS section), a guest must enter a one-time code sent to their mobile before the booking is sent. It stops fake and mistyped numbers. If SMS fails, the code falls back to email.

> ⚠️ A booking at **৳0** is *not* paid — it is waiting for a price (a Full Boat request, for example). Price it before marking it paid.

## 5. Prices — rates, children, Full Boat, offers, coupons

All prices are set in **Setup → ⚙️ Settings**.

- **Cabin Rates.** Each cabin size has a **Regular** rate (weekends and holidays) and a lower **Weekday** rate, both **per person**.
- **Pricing Days.** Tick the days that charge the Regular rate (default Friday and Saturday). Every other day uses the Weekday rate. **Holidays** are set on the Trip Calendar, not here (§6).
- **Advance %.** How much of the total is asked for up front.
- **Children** (automatic): **0–4 free** (share bed and food with parents); **4–8 pay a flat child fee** (default **৳5,000**, no weekday discount — editable in Settings); **9+ pay full rate**. Every cabin needs at least 2 adults; children never push a booking into a bigger cabin.
- **Full Boat.** A whole-boat request arrives already priced at the standard rate for every cabin at full occupancy (6 cabins × 6 people). Adjust the total on the booking after you agree a price with the guest.

### 🎉 Discount Offer (a promotion)
**Settings → Discount Offer.** Turn it on, give it a label, set the **percentage off** for weekends and for weekdays, and the **dates** it runs. The website shows the original price struck through with the offer price.
- An offer never *raises* a price: if the offer is smaller than the normal weekday discount, the weekday price stays as it was.
- Your cabin rates are never changed by an offer — switch it off and the normal prices are back.

### Coupon codes
**Settings → Coupon Codes.** Create a code (e.g. `HAOR10`) as a **percentage** or a **fixed taka amount**, with an optional **expiry date** and a **maximum number of uses** (blank/0 = unlimited). Guests type it on the booking form.
- An offer and a coupon **never add together** — the guest gets whichever single discount is larger.
- Pressing "Apply" on the form never uses up a coupon; it is counted only when the booking is actually made.

## 6. Trip Calendar and availability

**Bookings → 📅 Trip Calendar.** Each row is one departure. Press **Add Trip** for a new one.

| Column | What to put |
|---|---|
| **Start Date** | The departure date |
| **End Date** | Leave blank for a standard 2-day/1-night trip. Set the real return date for a Full Boat or longer charter |
| **Display Label** | Fills in by itself (e.g. `7 – 8 Aug 2026`) — change only if you want |
| **Days** | Bangla day names, fill in by themselves (e.g. `শুক্র – শনি`) |
| **ছুটি (Holiday)** | Tick for a holiday departure — it then charges the Regular rate with no weekday discount, and shows "ছুটির দিন" on the site |
| **Status** | Available / Filling Fast / Booked |
| **Booked Cabins** | A *manual hold*: cabins you want blocked (e.g. a phone booking not yet entered). Set **6** to show **Full Booked** |
| **Delete** | Tick to remove the row, then Save |

Press **Save** — the website updates immediately.

**Availability is automatic.** As soon as a booking is **Advance Paid** or **Confirmed**, its cabins come off that date. Each row shows the live count, e.g. *এখন বুকড 3/6 · খালি 3 (অটো)*. The Booked Cabins field is only a minimum you can force on top.

## 7. Invoices and the WhatsApp confirmation

- **Invoice.** Every booking has a branded invoice with a **private link** (it contains a secret key, so only someone you send it to can open it). It shows each cabin, per-person rate, guests, total, advance, paid and due, plus your payment details (bKash, Nagad, bank, QR codes) from Settings. Open it from the booking and use **Print → Save as PDF**, or copy the link to WhatsApp or email.
- **WhatsApp confirmation.** The booking screen builds a ready-to-send confirmation message (boarding ghat, check-in/out times, what to bring, the invoice link). Its wording is a template in **Settings → Booking Confirmation Message**; lines with nothing to show are left out automatically.

> 🤝 A B2B partner's commission **never** appears on the invoice, the guest's email or the confirmation message.

## 8. B2B travel agencies and referral links

Partners who send you guests are set up in **Settings → B2B Travel Agencies** (name, contact, commission). Each agency gets its own **referral link** (a normal website link ending in `?ref=…`). A guest who books after arriving through that link is **attributed** to the agency automatically.

- A referred booking is marked **unconfirmed**. Its commission is only *suggested* — open the booking and **confirm** the commission once you have checked it. Unconfirmed commission is not deducted anywhere.
- You can also set the agency on a booking by hand.
- A confirmed commission is deducted **once**: it appears on the Monthly Statement and fills the trip cost sheet's *B2B Partner* line automatically. Don't type it on the cost sheet yourself.
- If a link leaks, generate a new one for that agency in Settings — the old one stops working.

See **Accounts → 🤝 B2B Report** (§15) for every agency booking, including ones still waiting for confirmation.

## 9. Trip Report

**Bookings → 📄 Trip Report.** Pick a date range to see every booking per departure, with advance and due. Print it, send it on **WhatsApp**, or download **CSV**. Useful for the crew before a trip.

## 10. Guest reviews

- **Guests can submit reviews** (with photos) on the website. They arrive as **Awaiting approval** — nothing appears on the site until you approve it. Open **Bookings → ⭐ Reviews**; the menu shows a count of reviews waiting.
- To add one yourself: **Reviews → Add New** — guest name as the Title, their words below, set the star rating and trip type, **Publish**.
- The maximum number and size of photos a guest may attach are set in Settings.

---

# Part B — Accounts

## 11. Trip cost sheets

One cost sheet per trip records what the trip **earned** and what it **cost**. The month's profit — and every investor figure built on it — comes only from **approved** sheets.

**Accounts → 🧾 Cost Sheets → Add New.**

1. Set the **trip date** — the earnings can be filled from that date's bookings.
2. **Income** by head: Cabin booking, Food, BBQ, Extra guest, Extra service, Transportation, Special service, Other income. When you fill income heads, the sheet's earnings become their total automatically.
3. **Costs** by head: Engine Fuel, Electricity, Groceries, Meat, Fish, Kitchen Market, Gas, Staff Convency, Jetty Charge, Water, Fruits, Dry Fish, Local Bill, Laundry, Ice, Movement, Guest see-off, Minor Repair, **B2B Partner** (fills itself from confirmed commissions), Staff Bill, Others. There are spare rows for one-off costs.
4. The sheet shows total cost, profit, and cost/profit per guest.

Heads can be renamed, added or retired in **Settings → Trip Income Heads / Trip Cost Heads**. A retired head disappears from new sheets but stays on old ones.

### The approval workflow

| Status | Who | What happens |
|---|---|---|
| **Draft** | Preparer | Being filled in |
| **Prepared** | Preparer presses **Submit** | Waiting for a checker |
| **Checked** | Checker presses **Check** (or **Return** it to Draft with a note) | Waiting for approval |
| **Approved** | Approver presses **Approve** | **Locked.** Counted in the Monthly Statement |

- An approved sheet cannot be edited or deleted by anyone. To correct it, the approver presses **Unlock** (it goes back to Prepared), fixes it, and it is approved again.
- Who may prepare, check and approve is set per role in **👥 Team** (§29).

## 12. Expenses

**Accounts → 💸 Expenses** — spending that isn't part of one trip: Facebook boosting, renovation, website, and so on. Add the date, type, amount, method and a note. Types and payment methods are editable in Settings. Expenses are deducted in the Monthly Statement for the month they fall in.

## 13. Staff salary

1. **Settings → Staff Roster** — each staff member once: name, designation, pay type (**per trip** or **monthly**), rate, and account details.
2. **Accounts → 👷 Salary → Add New** — one sheet per month. Staff paid per trip are counted from the month's approved trips (you can adjust the number). Record any **advance** already given; the sheet shows what is still payable.
3. **Save** the sheet. Only a **saved** salary sheet is deducted in the Monthly Statement — if a month has no saved sheet, the statement says so in red.

> Hiring someone new later never changes a month that already has a salary sheet.

## 14. Monthly Statement

**Accounts → 📈 Monthly Statement.** Pick a month. You see every **approved** trip with guests, earnings, cost and profit, then the deductions, then **Gross Profit**:

```
Trip profit (approved cost sheets)
 − Expenses (one line per type)
 − Staff salary (saved salary sheet)
 − B2B commission (one line per agency, confirmed only)
 − Investor profit (one line per investment, approved periods only)
 = Gross Profit
```

- Unapproved cost sheets are listed as a warning — they are **not** in the figures until approved.
- **Print / PDF** gives a clean printed statement.
- Cost/profit per person is shown at the top.

## 15. Yearly Report, B2B Report, Trip P&L, Revenue by Source

- **📚 Yearly Report** — twelve months side by side, with Trips, Guests, Earnings, Trip Cost, Trip Profit, Expenses, Salary, **B2B Commission**, **Investor Profit** and **Gross Profit**. Every row adds up. Choose a **financial year (July–June)** or **calendar year**. Shows best and worst months, a chart, and where the money went. **Download CSV** for your accountant.
- **🤝 B2B Report** — every agency booking in a date range (blank dates = everything), including unconfirmed referrals and cancellations, and what each agency is owed. CSV download.
- **🧮 Trip P&L** — one line per trip with earnings, cost and profit; open one trip to see it end to end. Under the share model it also shows the trip's proportional share of what that month distributed.
- **💹 Revenue by Source** — earnings by income head (cabin, food, BBQ…) by day, month or year.

---

# Part C — Store (inventory)

## 16. Item register and the monthly stock count

### Setting up
1. **Settings → Categories, Sub-categories & Locations** — your lists (e.g. Kitchen, Cabin, Deck; storeroom, upper deck). A category's short **code** becomes part of every Item ID (e.g. `KIT-0042`) and cannot be changed once used.
2. **Store → 📦 Item Register → Add New** — one item per thing you own: name, category, location, unit, value, and optionally the purchase bill and a photo. The Item ID is made for you.
3. **Store → 🚚 Import Register** — to load many items from a spreadsheet: **upload** the CSV → **match** its columns → **dry run** (see exactly what would happen) → **commit**.

### The monthly count — Store → 🔧 Monthly Stock
Each month has one stock sheet. Its **opening** quantities come automatically from last month's closing.

1. Record the month's movement (received, used, lost) and the **physical count**, split into **Good / Repairable / Unrepairable / Damaged**. The four must add up to the closing count — the sheet warns you if they don't.
2. **Submit** → the checker **Checks** it (or **Returns** it for a recount) → the approver **Closes** it.
3. A **Closed** month is locked, and becomes next month's opening. Reopening a closed month needs its own permission because every later month must then be re-taken.

### Reports
- **📐 Inventory Report** — quantities and values by category and location for any month.
- **🏷️ Asset Report** — what you own and what it's worth.
- **🔩 Audit Trail** — every change to any stock figure: who, when, old value, new value, reason. Permanent, read-only (§30).

---

# Part D — Investors and Capital

## 17. How investor money works here

The system supports two ways of paying investors. **Only one is active at a time** — set in **Settings → Investor model**.

| | **Fixed return** (*`fixed` — current*) | **Share model** (`shares`) |
|---|---|---|
| What an investor has | One or more **Investment Records** with agreed terms (amount, rate, term) | A number of **shares** out of the total |
| How profit is worked out | From the investment's terms, period by period | The month's gross profit, split by shares |
| Where you approve it | **Capital → ➗ Profit** | **Capital → 💰 Distribution** |
| Effect on the Monthly Statement | Investor profit is a **cost**, deducted before Gross Profit | Paid **out of** Gross Profit |

> ⚠️ Switching the model is a business decision — take a backup first, and don't switch mid-month. When the model is **fixed**, the share-model screens are hidden from the menu (their old records are kept and still viewable). The system refuses to run a share distribution while the fixed model is on, so no investor can be paid twice.

> 📜 Whether a fixed-return arrangement needs any regulatory approval in Bangladesh is a question for BHELA's legal and accounting advisers. Agreement wording is theirs to approve — the system stores the signed agreement; it never writes one.

## 18. Investor records and portal logins

**Investors → 👤 Investors → Add New.** The title is the investor's full name. Then fill **Investor Details** (click the panel title if it is folded):

- **Section A — Investor details:** Investor ID (e.g. `BHL-INV-001`), father's and mother's name, date of birth, NID / passport, TIN, present and permanent address, **mobile** (important — it is how they sign in to the portal), email.
- **Section B — Payment and bank:** how they want to be paid, bank name, branch, account number, routing number.
- **Section C — Nominee:** name, relation, date of birth, ID, mobile, address.
- **Section D — Declaration:** signed yes/no, date, and scans of the investor's and nominee's signatures, photo and KYC/agreement document (choose a file or paste a Media Library link).

The right-hand side shows the investor's **Position** (invested, profit declared, received, outstanding, ROI), and — only if they hold shares — their share value.

**Portal Login** (right-hand box): type the investor's email and **Update** to create their portal login. There is no password to hand over — they sign in with a code sent to the **mobile** on the record, so make sure it is right. The account can do nothing but read their own figures on the website.

> 🔒 Changes to bank details, NID and similar fields are recorded in the Audit Trail as *changed*, without showing the numbers themselves.

## 19. Agreements and Investment Records

### Step 1 — Record the agreement: Capital → 📑 Agreements
Choose the investor, the **signing date**, the **parties**, attach the **signed copy** (PDF or photo), add a note, **চুক্তি রেকর্ড করুন**. It gets a reference like `BHELA-AGR-2026-0001`. The file is stored under a random name so nobody can guess its link.

### Step 2 — Create the investment: Capital → 💠 Investments → + নতুন বিনিয়োগ

| Field | What to put |
|---|---|
| বিনিয়োগকারী (Investor) | Who |
| বিনিয়োগের তারিখ | The investment date |
| মেয়াদ (Term) | Start and maturity dates — the number of months is shown |
| ধরন (Type) | নির্দিষ্ট মেয়াদি বিনিয়োগ (fixed term) or লাভ-বণ্টন ভিত্তিক (profit share) |
| লাভ হিসাবের পদ্ধতি (Method) | **নির্দিষ্ট বার্ষিক হার** — principal × yearly rate × months ÷ 12 · **মাসিক হার** — principal × monthly rate × months · **দিনভিত্তিক** — principal × yearly rate × actual days ÷ day basis · **লাভ-বণ্টন** — a share of BHELA's distributable profit |
| হার % (Rate) | The yearly rate, monthly rate, or the investor's share, depending on the method. **Nothing is assumed** — leave it blank and the investment cannot be activated |
| লাভ পরিশোধের সময়সূচি | Monthly / quarterly / half-yearly / yearly / at maturity |
| চুক্তি (Agreement) | Pick the agreement from Step 1 |

**সংরক্ষণ** saves it as a **draft**. It gets an ID like `BHELA-IN-2026-0001`.

### Step 3 — Record the money received (প্রাপ্তি — মূলধন)
On the same screen, add each receipt: **date, amount, method, reference**. The investment's **principal is the total of these receipts** — there is no box to type a principal, so the receipt and the certificate can never disagree. Each receipt row has a **রসিদ** button that prints the investor's receipt.

### Step 4 — Activate: সক্রিয় করুন
The screen lists anything missing (no receipt, no rate, a term shorter than a month on a monthly method…) and the **Activate** button stays disabled until it's all done. Once **active**, the terms are **locked**. To correct a mistake, press **খসড়া করুন** (back to draft) and give a reason — it is recorded.

### Money added later (a top-up)
Add another receipt to an active investment. The extra money earns **only from the day it arrives** (part-month for the month it arrives in). Periods already approved are never re-priced.

### Statuses
**Draft → Active → Matured (মেয়াদ পূর্ণ) → Closed**, or **Cancelled**.

## 20. Profit — calculate, approve, and what it does to the books

**Capital → ➗ Profit** lists every period of every active investment that has **finished**, up to the date in **এই তারিখ পর্যন্ত হিসাব করুন**, with the calculated profit and its status: **অপেক্ষমাণ** (waiting) or **অনুমোদিত** (approved).

1. **Tick** the periods you have checked (nothing is ticked for you; the box at the top ticks all).
2. Press **টিক দেওয়া সময়কালগুলো অনুমোদন করুন**.
3. Each approved period becomes a **Profit** entry in the investor's ledger — money BHELA now owes them — and a deduction in that month's Monthly Statement.

- **The same period can never be approved twice**, even if you press the button again or go back in the browser.
- **A period that hasn't ended can't be approved.**
- If a receipt is later back-dated into an approved period, the period keeps the amount that was approved and shows **"শর্ত এখন ৳… বলে — প্রয়োজনে সমন্বয় করুন"**. Decide whether to correct it with an **adjustment** on the Investor Report (§21).
- A wrongly approved period is corrected with **Reverse** on the Investor Report.

## 21. Paying an investor — two signatures

**Investors → 📇 Investor Report** → pick the investor. The top shows **Invested, Declared, Received, Outstanding, ROI**, and below it every ledger entry with a running balance.

### Raise a payment
**Record a movement** → Type **Payment** (or **Advance** for money paid before it was earned) → amount, date, method (bKash, bank…), reference, optional document link, note → **Submit**.

It becomes a **request** — *Awaiting approval*. It moves no money and does **not** change Outstanding yet.

### Approve it — a different person
Someone with the approve permission (a Manager or the owner, but **not** the person who raised it) opens the same investor and presses **Approve** on the request (pending requests also show on the Investors 🧭 Dashboard). Only then is the payment written to the ledger and the investor's Received/Outstanding updated. A request can also be **rejected**.

### Corrections
- **Adjustment** — a correction (plus or minus) recorded straight away, with a note. Use it for a genuine correction, not for a payment.
- **Reverse** — undoes a wrong ledger line by writing an opposite line. Give a reason. The original stays visible, struck through.

Each payment line has a **Receipt** button; the top of the page has **Account statement** and **Download CSV**.

## 22. Certificates, receipts and the account statement

### Issue a certificate — Investors → 📜 Certificates
1. **ধাপ ১:** choose the type — **বিনিয়োগ সনদ (Investment Certificate)** or **লাভের সনদ (Profit Certificate)** — and the investment. For a profit certificate you may set a date range; blank means the whole term. Press **দেখুন**.
2. **ধাপ ২:** check the preview — these exact figures will be frozen. Add an optional note.
3. Press **সনদ ইস্যু করুন**. It gets a number like `BHELA-IC-2026-0001-V1` / `BHELA-PC-2026-0001-V1`.

- **Investment Certificate** — the investor, principal, term, rate, method, frequency, status, agreement reference, and every receipt behind the principal.
- **Profit Certificate** — the calculation basis, profit by period (only **approved** periods), gross, adjustments, net, paid, due and status (Paid / Partially paid / Unpaid). It states the **exact period** the profit was earned in.
- Every certificate carries the **Prepared / Verified / Approved by** names, a **QR code**, and the line *"এটি BHELA কর্তৃক ইস্যুকৃত financial supporting document; NBR-ইস্যুকৃত Tax Certificate নয়।"*

> ✍️ Set **Settings → Certificates** (authorised signatory, their role, a standing note) and make sure each staff member's **display name** in their profile is their real name — it is printed on certificates.

### Correct a certificate — a new version
Issue again, and under **পুরোনো সনদ সংশোধন** pick the certificate it replaces and write **why**. The new one is `…-V2`. The old one still opens but shows *"এই সনদটি প্রতিস্থাপিত — বর্তমান সংস্করণ …-V2"*. Only a certificate for the **same investment** can be replaced.

### Share and verify
- **প্রিন্ট** opens the certificate with a private link — safe to send to the investor or their bank. **Print → Save as PDF**.
- **যাচাই** opens the public verification page `yourdomain.com/verify/<number>` (the QR code points there). It shows only the number, type, issue date, status (**VALID / SUPERSEDED / NOT FOUND**) and a masked name — never any amount.

### Receipts and the account statement
- **Investment Receipt** — the **রসিদ** button on each receipt on the Investments screen.
- **Profit Payment Receipt** — the **Receipt** button on each payment in the Investor Report.
- **Account statement** — button at the top of the Investor Report: every receipt, profit and payment with a running balance of what BHELA owes the investor.

Unlike certificates, receipts and statements always show the **current** records, so they are safe to reprint any time.

## 23. The investor portal and self-registration

Two pages are created on your website automatically:

| Page | Address | Who uses it |
|---|---|---|
| **Investor Portal** | `yourdomain.com/investor/` | Investors sign in with their mobile number and a one-time code |
| **বিনিয়োগকারী নিবন্ধন (Registration)** | `yourdomain.com/investor-register/` | New investors apply |

**In the portal, an investor sees only their own figures:** invested, profit declared, received, what BHELA owes them, ROI, monthly profit, their investments, their certificates (including replaced ones, marked), their agreements, their account statement and their ledger. They cannot change anything. A disputed figure is corrected by the office (§21).

### Registrations — Investors → 📝 Registrations
A person who registers proves they hold the phone (by code), then fills in their details and uploads their NID and signature scans. That is an **application** — nothing more.

- **Approve** creates their portal login. If the mobile already belongs to an investor record, the application is linked to it, and only **empty** fields are filled from the application — what the office typed always wins.
- If the code went by **email** (SMS off) and the number matches an existing investor, approval asks you to tick that you **confirmed the person by phone** — so nobody can claim someone else's investment with just an email.
- A new applicant is created with **no investment** until the office records their receipts. Until then they see no company figures.
- **Reject** with a reason, or delete a settled application.

> 🔐 The portal must run on **HTTPS** (padlock in the browser) on the live site.

## 24. Settlement, importing old payments, Cash Flow

- **⚖️ Settlement** — per investor, for a season or any date range: what was declared, adjusted and paid, and which way the balance runs. **Owed to the investor** and **owed to BHELA** are shown separately, never netted.
- **📥 Import Payments** — for payments made *before* the system existed. Upload a spreadsheet (investor, date, amount, method, reference) → the system matches columns → **dry run** shows what it would write → **commit**. This skips the two-signature rule because the money already moved, so it is a separate permission — give it only to someone you trust to record payments unchecked.
- **💵 Cash Flow** — money actually **moved** in and out by month (guest payments, costs, expenses, salary, investor payments). Different from profit: a month can be profitable and still short of cash. CSV download.
- **🧭 Dashboard** (Investors) — every investor's position, pending requests, totals, and export of the register and ledger.

## 25. The share model screens (Distribution, Valuation, Share Issue, Funds)

These appear under **Capital** only when **Settings → Investor model** is **shares**:

- **💰 Distribution** — once a month, commits the month's gross profit: a reserve %, a management %, and the investors' pool split by shares. **Once per month, ever.** A wrong run is reversed, not deleted.
- **💎 Valuation** — record what BHELA is worth; a second person approves it. Share value = valuation ÷ total shares.
- **🪙 Share Issue** — new shares for new money, priced from an approved valuation, so existing holders' value is not diluted unfairly. The only way the total number of shares changes.
- **🏦 Funds** — the reserve and management funds: allocations (from distributions only) and spending.
- **💼 Contributions** (always visible) — dated history of what each shareholder paid in and when; used by share-era certificates.

---

# Part E — The website

## 26. Pages, text and images

These pages are created automatically: হোম, কেবিন ও রেট, ট্রিপ সিডিউল, ট্রিপ ম্যাপ, খাবার মেনু, গ্যালারি, সাধারণ প্রশ্ন (FAQ), বুকিং গাইড, বুক করুন, বুকিং নীতিমালা, যোগাযোগ, ব্লগ, Investor Portal and বিনিয়োগকারী নিবন্ধন.

> ⚠️ **Don't add blocks to the Home page** in the editor — anything you add there replaces the designed homepage. Leave it empty.

**Appearance → Customize** has the BHELA panels:

| Panel | Change |
|---|---|
| BHELA Contact | Phone, WhatsApp, Messenger, email, address, social links (blank = hidden) |
| BHELA Homepage | Hero headline, badge, subtitle |
| BHELA Images | Hero, food, rooftop, cabin, spot photos (blank = defaults) |
| BHELA Tracking | Google Analytics ID (`G-…`), Facebook Pixel ID — your own visits are never counted |
| BHELA Custom Code | Code for `<head>`, top of `<body>`, or before `</body>` (verification tags, chat widgets) — admins only |

Business name, phone numbers, WhatsApp, payment details and the vessel registration are set once in **Setup → ⚙️ Settings** and used everywhere (footer, buttons, invoice, emails).

**Contact page** (`/contact/`) — contact cards, address, hours, social icons and a form. Messages are **emailed** to your notification address; reply directly to the guest.

**Elementor** — a page built with Elementor takes over its layout completely; use the *Full Width* template for edge-to-edge designs.

## 27. Gallery, Spots and the Blog

- **Gallery — Setup → 🖼️ Gallery.** Many photos at once: **⬆️ Bulk Upload → ছবি বাছাই করুন** → select → optionally a category → **যোগ করুন**. One photo: **Gallery → নতুন ছবি** → Featured Image = the photo, Title = caption, pick a **ক্যাটাগরি**, set **Order**, Publish. Categories become filter tabs. First time: an **ইমপোর্ট করুন** button offers the photos bundled with the theme (never duplicates).
- **Spots — Setup → 🗺️ Spots.** Featured Image = the spot photo, Bangla name, one-line description, Type (included in the package / optional), Order.
- **Blog — Posts → Add New.** Title, content, category (ভ্রমণ গাইড / হাওরের খবর / টিপস), tags, Featured Image, Publish. Lives at `/blog`. Comments are off by design (guests are sent to WhatsApp).

---

# Part F — Setup

## 28. Settings — every section explained

**Setup → ⚙️ Settings.** Press **Save** at the bottom after any change.

| Section | What it controls |
|---|---|
| **Business Information** | Business name, tagline, address, **vessel registration** (printed on invoices and the site; left blank = hidden), phones, WhatsApp, email, invoice prefix |
| **Trip Logistics** | Operations manager, support WhatsApp, boarding ghat, check-in and check-out times, package label, notes for the confirmation |
| **Office Locations** | Offices shown on the Contact page |
| **Payment Details** | bKash and Nagad numbers, bank details, the two **payment QR images** (upload to Media first, then choose/paste them here) |
| **Pricing Days** | Weekend days, advance %, child fee, invoice note |
| **🎉 Discount Offer** | Promotion on/off, label, weekend %, weekday %, dates (§5) |
| **Cabin Rates** | Regular and weekday per-person rate per cabin size |
| **Email Notifications** | Master switch; which emails go to you and to the guest (new request, confirmed, completed); notification address; From name; Reply-To; **Send Test Email** |
| **SMS Notifications** | Master switch; gateway (**BulkSMSBD** or **Custom**); API key and Sender ID; admin number; low-balance warning; Bangla templates; **OTP** (mobile verification on the booking form); **Send Test SMS** |
| **Booking Confirmation Message** | The WhatsApp confirmation template |
| **Share Structure** | Share-model figures (per-share price, reserve %, investor %). The total number of shares is read-only — only a Share Issue changes it |
| **Investor model** | **fixed** or **shares** (§17); day basis for day-based profit (365 or 360) |
| **Certificates** | Document number prefix, authorised signatory, their role, standing note |
| **Seasons** | Named date ranges (e.g. *Monsoon 2026*) used by Settlement, certificates and reports. Overlapping seasons are warned about |
| **Trip Income Heads / Trip Cost Heads** | The rows on every cost sheet (§11) |
| **Expense types and methods** | The lists on the Expenses screen |
| **Categories, Sub-categories & Locations** | The Store lists (§16) |
| **Staff Roster** | Staff for salary sheets (§13) |
| **B2B Travel Agencies** | Partners, commission, referral links (§8) |
| **Coupon Codes** | Discount codes (§5) |

SMS placeholders you can use in templates: `{name} {phone} {invoice} {date} {cabin} {guests} {total} {advance} {due} {status}`.

## 29. Team — staff accounts and permissions

1. **Users → Add New** — create an account for each person, with their real name and email, and choose a **BHELA** role.
2. **Setup → 👥 Team** — see what each role can do, and switch individual permissions on or off per role.

| Role | Meant for |
|---|---|
| **BHELA Manager** | Day-to-day operations: bookings, calendar, reports, cost sheets (check), expenses, salary, statement, the store, investors (view), and **approving** investor payments, registrations, certificates and profit. Cannot approve cost sheets, close a stock month or change settings |
| **BHELA Booking Staff** | Takes and updates bookings, sees the trip report. Nothing else |
| **BHELA Cost Preparer** | Fills in their own cost sheets and submits them |
| **BHELA Cost Checker** | Checks or returns cost sheets and stock counts |
| **BHELA Storekeeper** | Counts stock and fills in the monthly sheet |
| **BHELA Investor Relations** | Keeps the investor register, records capital and valuations, **raises** payments. Cannot approve them |
| **BHELA Investor** | Portal only — sees their own figures on the website, nothing in the dashboard |
| **Administrator** (you) | Everything, including approving cost sheets, closing stock months and settings |

> 🤝 Keep **raising** and **approving** in different hands. The system already refuses a person approving their own payment request.

## 30. Activity Log and Audit Trail

- **Setup → 📋 Activity Log** — what the system *did*: bookings received, emails and SMS sent or failed (✅ / ❌), status changes, settings saved, gallery imports, trip-calendar saves (including removed dates). Newest first, filter by type. Keeps the latest 300 events; **লগ মুছুন** clears it.
- **Store → 🔩 Audit Trail** — who changed which **figure**, from what to what, and why: cost sheets, stock, investors, capital, certificates, approvals. **Permanent** — no one can clear or edit it.

---

# Part G — Keeping it safe

## 31. Backups, updates and security

### Backups
- Keep an automatic daily backup of the **database and the uploads folder** on your hosting (ask your host, or use a backup plugin). Keep at least 14 days.
- Also take a manual backup **before**: updating the theme or plugin, switching the investor model, the start of a season, and any bulk import.
- Keep the two **payment QR images** and your **signed agreements** somewhere outside the website too.

### Updating BHELA
Updates come as two ZIP files from your developer (`bhela-theme-vX.Y.Z.zip`, `bhela-booking-vX.Y.Z.zip`).
1. Take a backup.
2. **Plugins → Add New → Upload Plugin** → choose the booking ZIP → **Replace current with uploaded**.
3. **Appearance → Themes → Add New → Upload Theme** → the theme ZIP → **Replace current with uploaded**.
4. Open the site and press **Ctrl + Shift + R** once.

Always update the **plugin first**, then the theme.

### Security
- The live site must use **HTTPS** (the padlock). The investor portal and all sign-ins depend on it.
- Each person has their own account; remove a departed staff member's account promptly (their past approvals stay recorded).
- Don't install unknown plugins, and don't use **Theme File Editor** or **Plugin File Editor**.
- The **Users** screen, **Plugins** and **Tools** are for you and your developer only.

> 🛟 **Safe to do yourself:** bookings, calendar, rates, offers, coupons, reviews, gallery, spots, blog, cost sheets, expenses, salary, stock, investors, investments, profit approvals, payments, certificates, settings. **Leave to your developer:** plugin and theme files, database, hosting and server settings.

## 32. Troubleshooting

| Problem | What to check |
|---|---|
| Emails not arriving | Email Notifications switched on? SMTP plugin (FluentSMTP / WP Mail SMTP) connected? Press **Send Test Email**; check the Activity Log |
| SMS not sending | SMS switched on? Gateway balance (shown on the Dashboard)? API key and Sender ID right? **Send Test SMS**; Activity Log |
| Guests can't get the booking OTP | SMS balance; the code falls back to email if SMS fails. Switch OTP off temporarily if the gateway is down |
| Availability looks wrong | Is the booking **Advance Paid / Confirmed**? Check **Booked Cabins** (manual hold) on the Trip Calendar |
| Price looks wrong | Cabin Rates, Weekend days, the date's **ছুটি** tick, a running **Offer**, a coupon, children's ages |
| A month's profit looks too high/low | Are all its cost sheets **Approved**? Is the **salary sheet** saved? Are investor profit periods approved? Read the warnings at the top of the Monthly Statement |
| Can't edit a cost sheet | It's approved — the approver presses **Unlock** |
| Can't change a stock month | It's closed — reopen it (needs the reopen permission) |
| Can't activate an investment | The screen lists what's missing — usually no receipt or no rate |
| An investor sees ৳0 or the wrong figure | Check their Investment Record receipts and the Investor Report ledger; correct with an adjustment or reverse |
| Investor can't sign in to the portal | Is the **mobile** on their record right? Does their record have a portal login (right-hand box)? SMS balance |
| A certificate figure is wrong | Issue a **new version** (§22) — never delete |
| "You approved your own request" refusal | Correct — someone else must approve it |
| Site looks broken after an update | **Ctrl + Shift + R**; if still broken, restore the backup and contact your developer |
| A page vanished from the menu | **Pages → Trash** — restore it |

## 33. Words you will see

| On screen | Meaning |
|---|---|
| বিনিয়োগ / বিনিয়োগকারী | Investment / investor |
| মূলধন | Principal (capital) |
| প্রাপ্তি | A receipt of money |
| চুক্তি | Agreement |
| মেয়াদ / মেয়াদ পূর্ণ | Term / matured |
| খসড়া · সক্রিয় · সমাপ্ত · বাতিল | Draft · active · closed · cancelled |
| অপেক্ষমাণ · অনুমোদিত | Waiting · approved |
| সনদ / সংস্করণ | Certificate / version |
| প্রতিস্থাপিত | Replaced (superseded) |
| যাচাই | Verify |
| রসিদ | Receipt |
| হিসাব বিবরণী | Account statement |
| বকেয়া · পরিশোধিত · আংশিক পরিশোধিত | Due · paid · partially paid |
| সমন্বয় | Adjustment |
| ছুটি | Holiday |

---

*BHELA – The Haor Exclusive · Website and system by [3s-Soft](https://3s-soft.com) · "ভেলার আকর্ষণ ভেলা নয়, হাওর!"*
