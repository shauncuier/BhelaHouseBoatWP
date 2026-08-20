# BHELA — The Haor Exclusive
## Project Delivery & Handover Document

> Source document for `BHELA-Project-Delivery.pdf`. Edit here, then regenerate the PDF.
> Document ref: **3SS-BHELA-DEL-2026-07** · Addendum A: **3SS-BHELA-ADD-2026-08** (18 August 2026)

| | |
|---|---|
| **Project** | BHELA – The Haor Exclusive : Custom WordPress Booking Platform |
| **Live website** | bhelahouseboat.com |
| **Developer** | 3s-Soft — Jashedul Islam Shaun, Founder (3s-soft.com) |
| **Client** | KeyToBD — Kaisar Hamid Apon, Owner |
| **Delivery date** | 21 July 2026 |
| **Free service until** | **21 August 2026** (see section 6) |
| **Components at delivery** | BHELA Theme v2.15.0 · BHELA Booking Engine Plugin v2.15.0 (single shared version) |
| **Components now** | BHELA Theme v2.29.1 · BHELA Booking Engine Plugin v2.29.1 — see Addendum A |
| **Original project price** | **USD 200.00** (includes 1 month free service) |
| **Post-delivery development** | **USD 250.00** — new modules beyond original scope, Addendum A |
| **Revised total** | **USD 450.00** |

---

## 1. Project Summary

BHELA – The Haor Exclusive is a custom WordPress booking platform built for a premium six-cabin houseboat operating on Tanguar Haor, Sunamganj, Bangladesh. The platform replaces phone-only and social-media-only booking with a professional website that takes bookings around the clock, prices them automatically, issues invoices, and gives the owner a single dashboard to manage the entire operation.

The system is delivered as two purpose-built components — a bespoke theme and a booking engine plugin — written specifically for this business. No page builder, no rented platform, and no per-booking commission. The client owns the source code outright.

---

## 2. Delivered Features

### 2.1 Website & Design
| Capability | What it does |
|---|---|
| Custom design | Bespoke "Midnight Monsoon" dark-teal luxury design system built for this brand — not a purchased template |
| Complete page set | Home, Cabins & Rates, Trip Schedule, Food Menu, Gallery, FAQ, Booking Policies, Book Now, Contact, Blog — created automatically on activation |
| Bangla-first content | Entire guest-facing experience written in Bangla, with English keywords where useful |
| Mobile-first | Fully responsive with dedicated mobile action bar and optimised mobile booking flow |
| Editable content | Five Customizer panels: contact details, homepage text, photos, tracking IDs, custom code |
| Contact page & form | Quick-contact cards, address/hours, social icons and a contact form that emails the owner |
| Page-builder ready | Elementor-compatible — any page can be rebuilt visually later |

### 2.2 Booking Engine
| Capability | What it does |
|---|---|
| Owner dashboard | A single overview screen — bookings by status, revenue and money collected, upcoming trips, recent activity, a setup checklist and one-click actions |
| Booking wizard | Guided multi-step form with live price calculation |
| Six-cabin inventory | Five cabin types across six cabins, each with per-person rate and sharing capacity |
| Smart pricing | Automatic weekday / weekend / holiday rates, weekday discount up to 20% |
| Children pricing | Ages 9+ full rate; 4–8 a flat per-child fee (default ৳5,000, no weekday discount); 0–4 free — applied automatically |
| Live availability | Real-time availability that updates itself the moment a booking is confirmed — every manager and the public schedule see the same live count; prevents overbooking |
| Booking management | Full admin screen per booking, plus manual entry for phone/walk-in guests |
| Status workflow | Pending → Advance Paid → Confirmed → Completed / Cancelled, with auto guest notification |
| Guest self-service | Guests track their own booking by phone number or email |
| Discount panel | Percentage, flat, or custom counter-offer pricing |
| Trip calendar | Departure schedule with auto start/end dates, labels and duration, a per-trip holiday toggle, booked-cabin counts and automatic "Full Booked" |
| Photo gallery | Category-filtered gallery with one-click bulk upload of many photos at once |
| Guest reviews | Star-rated reviews managed from the dashboard, shown on the website |
| Activity log | Plain-language record of bookings, emails, SMS, trip and settings changes so the owner can confirm everything worked |
| WhatsApp integration | One-tap WhatsApp contact with booking details pre-filled |

### 2.3 Invoicing & Notifications
| Capability | What it does |
|---|---|
| Automatic invoices | Branded, print-ready invoice per booking with per-person breakdown, advance, paid, due |
| Secure invoice links | Private signed key per invoice — safe to send by WhatsApp or email |
| Payment details | bKash, Nagad, bank transfer and QR pulled from settings into every invoice |
| Email notifications | Owner on new booking; guest on request and on confirmation — each switchable |
| Email controls | Custom sender name, reply-to, owner notification address, one-click test |
| SMS notifications | Optional SMS on new booking and status change, editable Bangla templates |
| Any SMS provider | BulkSMSBD preset plus custom gateway — no code change to switch |

### 2.4 SEO & Performance
| Capability | What it does |
|---|---|
| On-page SEO | Meta descriptions, Open Graph, Twitter cards, canonicals, Bangla language signals |
| Structured data | JSON-LD graph: Organization, Website, LocalBusiness, TouristAttraction, Breadcrumbs, Articles, FAQ, aggregate rating |
| Search visibility | XML sitemap and robots.txt configured, Google Search Console set up and sitemap submitted |
| Speed optimisation | Theme image payload reduced ~74%, font preconnect, lazy loading, layout-shift protection |
| Lean codebase | Single stylesheet, no jQuery, no page-builder bloat |
| Content blog | Categories, tags, related posts, reading time, booking CTA per article |

### 2.5 Analytics, Custom Code & Security
| Capability | What it does |
|---|---|
| Analytics installed | Google Analytics 4 set up and live; Meta Pixel by ID only; owner's admin visits excluded from stats |
| Custom code panel | Inject code into head, after `<body>`, or footer — no theme file editing |
| Form protection | Honeypot field and per-visitor rate limiting stop spam |
| Request verification | Security tokens and capability checks on all form and admin actions |
| Private guest data | Booking records private — not exposed via public URL or API |
| Credential safety | API keys stored masked, never displayed or logged |

---

## 3. Technical Specification

| | |
|---|---|
| Platform | WordPress (custom theme + custom plugin) |
| Requirements | WordPress 6.0+, PHP 8.0+ |
| BHELA Theme | v2.15.0 — design, pages, blog, SEO, analytics, custom code, contact page |
| Booking Engine | v2.15.0 — dashboard, bookings, pricing, invoices, trips, gallery, reviews, activity log, email, SMS |
| Front-end | Vanilla JavaScript and CSS — no jQuery, no build step |
| Source control | Full Git history on GitHub, released with version tags |
| Third-party | FluentSMTP for email delivery; SMS gateway optional |

---

## 4. Deliverables & Handover

- **Complete source code** for theme and plugin, full version history on GitHub
- **Installable packages** — theme and plugin ZIP files
- **Live, configured website** at bhelahouseboat.com
- **Owner's Manual** — plain-language, Bangla-friendly operating guide
- **Project overview documentation**
- **Production go-live checklist** — caching, HTTPS, email deliverability, Search Console, Google Business Profile
- **Google Analytics 4 and Google Search Console** — both accounts created, verified and connected to the website, with the sitemap submitted to Google
- **All passwords and login credentials** — WordPress administrator account, hosting control panel, domain registrar, database, Google Analytics, Search Console and any service accounts created for this project, handed over in full
- **Full ownership** — client owns delivered code and all site data, no licence fee, no lock-in, no booking commission

> **Credentials handover.** The client receives every username and password associated with the project. 3s-Soft retains no exclusive access, and the client can transfer the site to any other developer or host at any time.

> **No recurring cost to 3s-Soft.** Once delivered, the platform runs without any subscription or per-booking fee payable to the developer. The client is responsible only for hosting, domain, and optional third-party services such as SMS credits.

---

## 5. Commercial Terms

| Description | Amount |
|---|---|
| Design, development and delivery of the BHELA custom WordPress booking platform (theme and booking engine), including deployment support and one month of free service per section 6 | USD 200.00 |
| Domain setup, hosting setup, WordPress installation and configuration, plus Google Analytics and Google Search Console setup — see 5.1 | **No charge** |
| **Total project price** | **USD 200.00** |

**Total project price: USD 200.00 (Two Hundred US Dollars).** Inclusive of everything in sections 2 and 4, and of the one month free service period in section 6.

### 5.1 Additional Services Provided Free of Charge

Carried out by 3s-Soft at no cost. **Not** included in the price above; listed at standard market value so the client can see the full scope delivered.

| Provided free of charge | Standard value |
|---|---|
| Domain setup and DNS configuration | USD 15.00 |
| Hosting setup and site deployment | USD 25.00 |
| WordPress installation and full configuration | USD 35.00 |
| Google Analytics 4 setup and verification | USD 20.00 |
| Google Search Console setup and sitemap submission | USD 20.00 |
| One month service and support period (section 6) | USD 50.00 |
| **Total value received free** | **USD 165.00** |

| Total value delivered | Amount charged | Client receives free |
|---|---|---|
| **USD 365.00** | **USD 200.00** | **USD 165.00** |

Any third-party fees payable directly to providers — domain registration, hosting plan and SMS credits — remain the client's own cost and are not part of this document.

---

## 6. One Month Free Service & Review Period

A **one month free service and review period** is included at no additional cost, allowing the client to use the platform in real operating conditions and raise anything that does not work as intended.

| | |
|---|---|
| Period | 21 July 2026 → 21 August 2026 (one calendar month from delivery) |
| Cost | Free — included in the project price |
| Raise an issue via | Direct contact with 3s-Soft (Jashedul Islam Shaun) |

| PERIOD STARTS | **DEADLINE — FREE SERVICE PERIOD ENDS** |
|---|---|
| 21 July 2026 | ## 21 August 2026 |

**Please report any issue on or before 21 August 2026.** Anything raised within the period is fixed free of charge. After this date the free service period expires and further work is chargeable or arranged by mutual agreement.

### 6.1 Covered
- **Any bug or defect found in the delivered scope will be fixed free of charge.** If something in this document does not work as described, 3s-Soft will correct it at no cost.
- Errors in booking calculation, invoices, notifications, availability, or any delivered feature
- Display or layout problems on desktop and mobile
- Configuration assistance and dashboard guidance
- Minor settings and content adjustments

### 6.2 Not covered
- New features or changes beyond section 2 scope — quoted separately
- Redesign or restructure of the website
- Third-party costs: hosting, domain renewal, SMS credits, paid plugins
- Content writing, photography, translation
- Problems caused by client/third-party code changes or conflicting plugins

> **Commitment.** During the review period, if the client finds any issue in the delivered platform, 3s-Soft will fix it free of charge. The period ends on **21 August 2026**; after that date continued support and any new development can be arranged by mutual agreement.

---

# Addendum A — Development After Delivery

**Period covered:** 22 July 2026 → 18 August 2026 · **Document ref:** 3SS-BHELA-ADD-2026-08

The platform was delivered on 21 July 2026 at version 2.15.0. In the four weeks since, it has been developed considerably further at the client's request, and separately corrected wherever something in the original delivery did not behave as documented.

Those are two different things commercially, and this addendum keeps them apart:

| | |
|---|---|
| **Section 7 — new modules beyond original scope** | Chargeable. **USD 250.00** |
| **Section 8 — fixes inside the delivered scope** | **No charge**, under the section 6 free service period |

**Scale of the work:** 35 tagged releases, 44 commits, 15 new plugin modules and approximately 23,600 lines of new code across 85 files — taking the platform from v2.15.0 to v2.29.1.

---

## 7. New Modules Beyond Original Scope — Chargeable

None of the following appears in section 2 of the original delivery. Each is a new module built after handover.

### 7.1 Accounting & Financial Control Suite

The largest single addition. The platform now answers *"did this trip make money, and did this month?"* rather than only *"who booked?"*

| Module | What it does |
|---|---|
| **Trip Cost Sheets** | One sheet per trip covering every cost head — fuel, groceries, meat, fish, gas, jetty, staff transport, electricity and more — each with three payment columns, a remark and spare rows for one-offs. Cost heads are owner-editable, so the list follows the business rather than the code. |
| **Approval workflow** | Prepare → Check → Approve, each step stamped with **who** did it and **when**. An approved sheet locks: figures can no longer be edited, and an administrator must deliberately unlock it before anything changes. |
| **Expenses** | Spending not tied to a trip — advertising, boosting, renovation, one-off purchases — with date, type, amount, payment method, means of verification and remark. Types and payment methods are owner-editable. |
| **Monthly Statement** | The month's **approved** trips as rows, then one deduction line per expense type, then staff payroll, then gross profit. Also cost per person and profit per person, with the three sign-offs carried through from the sheets. Print/PDF ready. |
| **Yearly Report** | Twelve monthly statements rolled up, in **financial-year (July–June)** or calendar mode, with season totals, margin and the year's expense mix. Built by calling the monthly statement twelve times, so a yearly figure can never disagree with the month it summarises. |
| **Staff roster & payroll** | Staff recorded as trip-based, monthly-salaried or both, with per-trip rate, monthly salary and payment account. A salary sheet per month prices trip-based crew automatically from the number of trips that actually ran, and records advances, settlement and adjustments. |
| **Payroll in the bottom line** | Gross profit is now *trip profit − expenses − payroll*. Wages are a real cost and are treated as one. |

### 7.2 Inventory & Asset Register

A complete stock and asset control system — the second major addition.

| Module | What it does |
|---|---|
| **Item register** | A permanent record per item: category, sub-category, location, unit, brand, model, serial, supplier, purchase date and bill, warranty, unit value, responsible person, photos. Every item receives a **frozen Item ID** built from its category — `BHELA-KIT-0001` — that never changes and is never reused. |
| **Monthly stock sheet** | Each month **opens on the previous month's closing**, automatically. Records what came in, what went out, what was used, lost or scrapped, and splits what is on board by condition: good, repairable, under repair, damaged. |
| **Physical verification** | A counted figure per item against the system figure. Any variance must be explained before the month can be closed — a discrepancy is reported, never quietly corrected. |
| **Month close & lock** | A closed month is **genuinely closed**. Its figures cannot be edited through the form, cannot be changed directly in the database, and cannot be deleted — not even by an administrator. Reopening is possible and is itself recorded. |
| **Audit Trail** | An append-only record of who changed which figure, what it was before, what it became and why. It has no delete path and no "clear" button, by design — it is the register's memory, not a log to tidy up. |
| **CSV importer** | Four steps — upload, map your columns, dry run, commit. It reads the spreadsheet you already keep rather than demanding a fixed format, tells you what it *would* do before doing anything, blocks a row it cannot resolve instead of guessing, and is safe to run twice: re-importing the same file changes nothing. |
| **Inventory & Asset Reports** | Two reports with print and CSV export — consumables by movement and value, assets by location and condition. |

### 7.3 Staff Roles & Access Control

| Module | What it does |
|---|---|
| **Six staff roles** | Manager, booking staff, cost preparer, cost checker and storekeeper, alongside the administrator — so office staff, kitchen and store can each be given their own login. |
| **Editable permission matrix** | A Team screen with a checkbox per permission per role, saved from the page. Access is configured by the owner, not hard-coded. |
| **Role-aware admin** | Each person sees only the menus they can actually use. A storekeeper sees the store; a cost preparer sees accounts. |

### 7.4 Mobile Verification (OTP)

| Module | What it does |
|---|---|
| **Verified phone numbers** | Optional SMS code on the booking form, so a booking carries a number that has been proven to work. Email fallback, with per-number and per-IP throttles. |
| **One-part messages** | Codes are forced into GSM-7 so a verification SMS never silently costs double. |
| **SMS credit on the dashboard** | Live gateway balance with a low-balance warning, so credit never runs out mid-season unnoticed. |

### 7.5 Operational Reporting & Admin Redesign

| Module | What it does |
|---|---|
| **Trip Report** | Every booking for a date or range with advance, due and totals — printable, exportable to CSV, and shareable as a WhatsApp message for the crew. |
| **Admin design system** | A single consistent design across every admin screen — headers, summary cards, ledger tables, status pills, print styles — replacing screen-by-screen styling. |
| **Four admin menus** | The admin sidebar had grown to 22 rows under one "Bookings" menu. It is now four menus grouped by job — **Bookings · Accounts · Store · Setup** — each hidden from anyone who cannot use it. Old links and bookmarks continue to work. |
| **Full Boat bookings** | Whole-boat reservations are bookable from the admin as well as the form, take all six cabins, and now arrive **priced automatically** at the standard 36-person rate (৳288,000 weekend / ৳230,400 weekday) for the owner to adjust after negotiating — instead of arriving blank. |

### 7.6 Guest-Facing Additions

| Module | What it does |
|---|---|
| **Reviews with photos** | Guests submit star-rated reviews with trip photos after travelling; the owner moderates them, and approved reviews appear in a carousel on the website. |
| **Booking Guide page** | A step-by-step guide for first-time houseboat guests, plus two-level primary navigation to carry the larger page set. |
| **Manageable Spots** | The included and optional sightseeing spots are now editable content rather than fixed text. |

### 7.7 Quality Assurance Programme

Not a feature, and the reason the modules above can be trusted with money.

| | What it does |
|---|---|
| **Automated regression suite** | **14 test harnesses**, run from a single command, covering pricing, the July statement reproduced to the taka, salary, cost sheets, the booking save path, the stock register, every admin screen, colour contrast, front-end behaviour behind a page cache, OTP, the SMS gateway, version consistency and the yearly rollup. |
| **What it is for** | Every future change is checked against all of it before release. The suite has caught real defects — including several during this period — before they reached the live site. |

---

## 8. Fixes Inside the Delivered Scope — No Charge

Everything below was corrected **free of charge** under the section 6 free service period, which runs to **21 August 2026**. Listed for completeness, so the client can see what was done.

### 8.1 Booking, pricing and invoices
- Homepage price estimator corrected until it matched the booking engine exactly
- Weekend rate calculation corrected
- Guest count drift between the form, the admin and the invoice
- Advance amount made owner-owned, with correctly derived percentages
- Invoice discount row, note placeholders, contact details and footer duplication
- Taka symbol rendering fixed everywhere, including print and PDF
- Duplicate WhatsApp numbers and duplicate confirmation emails
- **Day type on invoices** — a booking moved to a weekday kept printing "Weekend"; the label is now derived when read rather than cached
- **PAID stamp** — a settled invoice now carries a clear paid mark, drawn as an outline so it survives printing with backgrounds switched off

### 8.2 Availability and stock
- Additive cabin availability (held + sold), with a manual "Booked" status honoured across the engine
- Booking form made safe behind a page cache, and the real visitor IP detected correctly behind a CDN so rate limiting cannot bucket every guest together

### 8.3 Mobile and layout
- Trip schedule invisible on mobile
- WhatsApp button intercepting touches on mobile
- Homepage image payload reduced with correctly sized renditions and hero preload

### 8.4 Security
- Staff file-upload capability revoked where it was not needed
- Invoice links marked no-store and noindex, so they cannot be cached or indexed
- Booking stock guard and general request hardening

### 8.5 Defects found in the new modules and corrected before release

Found by operating the platform with real data rather than test data, and fixed at no charge:

- Stock valuation reading ৳0 permanently, because the first save froze each item's unit value at zero
- A newly opened stock month reporting itself empty, above rows that clearly were not
- A closed stock month becoming unreachable after a maintenance routine cleared its index
- A cleared "trips" field paying a crew member for no trips, and under-deducting that month's payroll
- Hiring a staff member retroactively changing the payroll of months already paid
- Clicking "Bookings" opening the dashboard instead of the booking list

---

## 8A. Revised Commercial Terms

| Description | Amount |
|---|---|
| Original project — design, development and delivery of the BHELA platform, per sections 2, 4 and 5 | USD 200.00 |
| **Post-delivery development** — new modules beyond original scope, per section 7 | **USD 250.00** |
| Fixes inside the delivered scope, per section 8 | No charge |
| **Revised total** | **USD 450.00** |

**Post-delivery development: USD 250.00 (Two Hundred and Fifty US Dollars).** This covers every module in section 7 — the accounting and financial control suite, the inventory and asset register, staff roles and access control, mobile verification, the operational reporting and admin redesign, the guest-facing additions, and the automated test suite.

For context, the accounting suite and the inventory register would each ordinarily be quoted as a project in its own right. They are charged here as a single continuation of the original engagement.

| Original project | Post-delivery development | Revised total |
|---|---|---|
| **USD 200.00** | **USD 250.00** | **USD 450.00** |

Section 5.1 of the original document still applies: domain setup, hosting setup, WordPress installation and configuration, Google Analytics and Google Search Console setup were all provided free of charge, at a standard value of USD 165.00.

Third-party fees payable directly to providers — domain registration, hosting and SMS credits — remain the client's own cost and are not part of this document.

### 8A.1 Support on the new modules

Defects found in the section 7 modules were corrected free of charge as they were built, and that continues to the end of the original free service period on **21 August 2026**. Support and development after that date is arranged by mutual agreement, as set out in section 6.

---

## 9. Acceptance & Sign-off

Both parties confirm the platform described has been delivered and accepted, and that the commercial and support terms in sections 5, 6, 7, 8 and 8A — including Addendum A and the revised total of **USD 450.00** — are agreed.

| Developer | Client |
|---|---|
| **3s-Soft** | **KeyToBD** |
| Jashedul Islam Shaun — Founder | Kaisar Hamid Apon — Owner |
| Signature: ______________________ | Signature: ______________________ |
| Date: ______________________ | Date: ______________________ |

---

*BHELA – The Haor Exclusive · Project delivery and handover document · 3SS-BHELA-DEL-2026-07 · Addendum A 3SS-BHELA-ADD-2026-08 (18 August 2026)*
*Designed and developed by **3s-Soft** — 3s-soft.com · © 2026 3s-Soft. All rights reserved.*
