# BHELA — The Haor Exclusive
## Project Delivery & Handover Document

> Source document for `BHELA-Project-Delivery.pdf`. Edit here, then regenerate the PDF.
> Document ref: **3SS-BHELA-DEL-2026-07**

| | |
|---|---|
| **Project** | BHELA – The Haor Exclusive : Custom WordPress Booking Platform |
| **Live website** | bhelahouseboat.com |
| **Developer** | 3s-Soft — Jashedul Islam Shaun, Founder (3s-soft.com) |
| **Client** | KeyToBD — Kaisar Hamid Apon, Owner |
| **Delivery date** | 21 July 2026 |
| **Free service until** | **21 August 2026** (see section 6) |
| **Components** | BHELA Theme v2.15.0 · BHELA Booking Engine Plugin v2.15.0 (single shared version) |
| **Project price** | **USD 200.00** (includes 1 month free service) |

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

## 7. Acceptance & Sign-off

Both parties confirm the platform described has been delivered and accepted, and that the commercial and support terms in sections 5 and 6 are agreed.

| Developer | Client |
|---|---|
| **3s-Soft** | **KeyToBD** |
| Jashedul Islam Shaun — Founder | Kaisar Hamid Apon — Owner |
| Signature: ______________________ | Signature: ______________________ |
| Date: ______________________ | Date: ______________________ |

---

*BHELA – The Haor Exclusive · Project delivery and handover document · 3SS-BHELA-DEL-2026-07*
*Designed and developed by **3s-Soft** — 3s-soft.com · © 2026 3s-Soft. All rights reserved.*
