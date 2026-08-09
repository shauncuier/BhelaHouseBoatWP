# 🚢 BHELA Houseboat Booking Engine — Comprehensive Test Report

**Target URL:** `http://bhela-house-boat.local/`  
**Plugin Version:** BHELA Booking Engine v2.24.0  
**Test Execution Date:** August 10, 2026  
**Environment:** LocalWP / WordPress 6.0+ / PHP 8.0+  
**Tested By:** Pair Programming Agentic QA  
**Overall Result:** **100% PASS (Zero Discrepancies)**

---

## 📌 1. Executive Summary & Scope

An end-to-end quality assurance, financial audit, and mathematical validation test suite was executed across all user-facing and administrator-facing features of the **BHELA Booking Engine** plugin.

### Key Verification Areas:
1. **Pricing Engine & Rate Tiers:** Regular Weekend vs Weekday (-20%) pricing, occupancy-based tiers (2 to 6 persons), flat child fees, and free infant tiers.
2. **Frontend Booking Stepper Wizard:** Live multi-cabin pricing calculations, 50% advance computation, savings calculation, customer info submission.
3. **Admin Booking Management:** CPT fields, status workflows (Pending, Advance Paid, Confirmed, Completed, Cancelled), manual overrides, and real-time Due balance calculation.
4. **Public Invoice System:** Tokenized hash security, line item details, Bangla QR / Bank instructions, dynamic Bangla policy note with placeholder interpolation.
5. **Trip Calendar & Inventory:** Capacity management (6 cabins max), per-trip occupancy status, holiday flags.
6. **Trip Cost Sheets & Workflow:** Operating expense tracking (13 default heads + custom items), auto-linked trip revenues, 4-stage signature gate (Draft ➔ Prepared ➔ Checked ➔ Approved).
7. **Business Expenses Management:** Categorized expenses (Boosting, Renovation, Website, Other) and payment reconciliation.
8. **Monthly Statements (July & August 2026):** Aggregating approved trip cost sheets, fixed monthly expense deductions, net gross profit derivation.
9. **Yearly Financial Report (2026–2027 Financial Year):** 12-month rollup, gross profit margin %, expense mix breakdown.
10. **Staff Roster & Salary Sheets:** 3 payroll employment models (Monthly, Trip-based, Hybrid), dynamic trip count sync, advance deductions, and manual override recalculations.
11. **Frontend Booking Tracker:** Phone number lookup, Bengali status badges, and privacy masking.

---

## 💰 2. Pricing Engine & Rate Matrix

The booking engine calculates rates based on the number of paying adult occupants sharing each cabin, with flat fees for children (4–8 yrs) and free admission for infants (0–4 yrs).

### Rate Table
| Cabin Class | Occupancy | Regular Weekend Rate (Per Person) | Weekday Rate (-20% Discount) | Child Fee (4–8 yrs) | Infant (0–4 yrs) |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Exclusive Couple** | 2 Persons | ৳13,000 | ৳10,400 | ৳5,000 | Free (৳0) |
| **Luxury Triple** | 3 Persons | ৳12,000 | ৳9,600 | ৳5,000 | Free (৳0) |
| **Double Deluxe** | 4 Persons | ৳10,000 | ৳8,000 | ৳5,000 | Free (৳0) |
| **Comfort Adjustment**| 5 Persons | ৳9,000 | ৳7,200 | ৳5,000 | Free (৳0) |
| **Budget Friendly** | 6 Persons | ৳8,000 | ৳6,400 | ৳5,000 | Free (৳0) |

---

## 🧪 3. Mathematical Verification & Test Scenarios

### Test Scenario A: Frontend Weekend Multi-Cabin Booking
- **Date:** Weekend (Friday departure)
- **Cabin 1:** 3 Adults + 1 Infant (0–4 yrs Free)
  - Rate: $3 \times \text{৳}12,000 = \text{৳}36,000$
- **Cabin 2:** 2 Adults + 1 Child (4–8 yrs)
  - Rate: $(2 \times \text{৳}13,000) + \text{৳}5,000 = \text{৳}31,000$
- **Total Expected:** $\text{৳}36,000 + \text{৳}31,000 = \mathbf{\text{৳}67,000}$
- **Advance Expected (50%):** $\mathbf{\text{৳}33,500}$
- **Actual UI & API Result:** Total = **৳67,000**, Advance = **৳33,500** ✅ **PASS**

### Test Scenario B: Frontend Weekday Multi-Cabin Booking (-20% Discount)
- **Date:** Weekday (Monday departure)
- **Cabin 1:** 3 Adults + 1 Infant (0–4 yrs Free)
  - Rate: $3 \times \text{৳}9,600 = \text{৳}28,800$
- **Cabin 2:** 2 Adults + 1 Child (4–8 yrs)
  - Rate: $(2 \times \text{৳}10,400) + \text{৳}5,000 = \text{৳}25,800$
- **Total Expected:** $\text{৳}28,800 + \text{৳}25,800 = \mathbf{\text{৳}54,600}$
- **Savings Expected:** $\text{৳}67,000 - \text{৳}54,600 = \mathbf{\text{৳}12,400}$
- **Advance Expected (50%):** $\mathbf{\text{৳}27,300}$
- **Actual UI & API Result:** Total = **৳54,600**, Advance = **৳27,300**, Savings = **৳12,400** ✅ **PASS**

### Test Scenario C: Booking Status & Payment Math
- **Formula:** $\text{Due Balance} = \text{Total} - \text{Paid Amount}$
- **Input:** Total = ৳20,800, Paid = ৳10,400
- **Computed Due:** **৳10,400**
- **Status Change:** Successfully transitions `Pending` ➔ `Advance Paid` ➔ `Confirmed` ➔ `Completed` ✅ **PASS**

---

## 🧾 4. Public Invoice System

- **Public Access Link:** Tested via tokenized hash URL (`/invoice/?id=...&token=...`).
- **Security Check:** Unauthenticated users can view only valid hashed tokens (`wp_hash` + `hash_equals`); guessing invoice numbers directly is prevented.
- **Line Items Breakdown:** Displays itemized cabin types, individual guest counts, per-person rates, subtotal, advance paid, and balance due.
- **Dynamic Bangla Terms Note:**
  $$\text{Placeholder Interpolation: } \{total\}, \{advance\}, \{advance\_pct\}, \{due\}$$
  - Output rendered: *"বুকিং নিশ্চিত করতে ৳১০,৪০০ (৫০%) অগ্রিম প্রদান করতে হবে। বাকি টাকা অনবোর্ড হওয়ার সময় পরিশোধযোগ্য।..."*
- **Payment Instructions:** Correctly renders bKash, Nagad, Bank details, and Bangla QR code blocks. ✅ **PASS**

---

## 📊 5. Financial Reports & Monthly Statements

Operational dummy data for July and August 2026 was generated to validate all financial aggregation formulas.

### 📅 July 2026 Monthly Statement
*Location: Bookings ➔ Monthly Statement (`month=2026-07`)*

| # | Trip Date | Trip ID | Guests | Earnings | Operating Cost | Operating Profit |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| 1 | 3 Jul 2026 | `TRIP-260703` | 19 | ৳204,000 | ৳56,700 | ৳147,300 |
| 2 | 10 Jul 2026 | `TRIP-260710` | 20 | ৳207,000 | ৳58,900 | ৳148,100 |
| 3 | 17 Jul 2026 | `TRIP-260717` | 16 | ৳173,000 | ৳53,300 | ৳119,700 |
| 4 | 24 Jul 2026 | `TRIP-260724` | 21 | ৳211,000 | ৳62,200 | ৳148,800 |
| 5 | 31 Jul 2026 | `TRIP-260731` | 19 | ৳209,000 | ৳58,700 | ৳150,300 |
| **Total** | **5 Trips** | — | **95** | **৳1,004,000** | **৳289,800** | **৳714,200** |

- **Monthly Fixed Deductions:**
  - Boosting (Facebook Ads): `-৳35,000`
  - Renovation (Teak Polish & Canopy Repairs): `-৳55,000`
  - Website (Server & Domain Hosting): `-৳12,000`
  - Other (Docking Lease & License): `-৳28,000`
- **July 2026 Net Gross Profit:** **৳584,200** *(Margin: 58.2%)* ✅ **PASS**

---

### 📅 August 2026 Monthly Statement
*Location: Bookings ➔ Monthly Statement (`month=2026-08`)*

| # | Trip Date | Trip ID | Guests | Earnings | Operating Cost | Operating Profit |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| 1 | 2 Aug 2026 | `TRIP-260802` | 16 | ৳138,400 | ৳51,200 | ৳87,200 |
| 2 | 7 Aug 2026 | `TRIP-260807` | 20 | ৳212,000 | ৳59,800 | ৳152,200 |
| 3 | 9 Aug 2026 | `TRIP-260809` | 18 | ৳159,200 | ৳54,500 | ৳104,700 |
| 4 | 14 Aug 2026 | `TRIP-260814` | 24 | ৳247,000 | ৳66,100 | ৳180,900 |
| 5 | 21 Aug 2026 | `TRIP-260821` | 22 | ৳221,000 | ৳58,700 | ৳162,300 |
| 6 | 28 Aug 2026 | `TRIP-260828` | 19 | ৳209,000 | ৳58,700 | ৳150,300 |
| **Total** | **6 Trips** | — | **119** | **৳1,186,600** | **৳349,000** | **৳837,600** |

- **Monthly Fixed Deductions:**
  - Boosting (Reels Video Ads): `-৳42,000`
  - Renovation (Life Jackets & Safety Gear): `-৳25,000`
  - Website (SMS Gateway Credits): `-৳8,000`
  - Other (Local Haor Guide Association Fund): `-৳15,000`
- **August 2026 Net Gross Profit:** **৳747,600** *(Margin: 63.0%)* ✅ **PASS**

---

### 📈 Yearly Financial Report (2026–2027 Financial Year)
*Location: Bookings ➔ Yearly Report*

| Metric | Amount | Description |
| :--- | :--- | :--- |
| **Total Executed Trips** | **11 Trips** | 5 in July + 6 in August |
| **Total Passengers / Guests** | **214 Guests** | Full & group cabin bookings |
| **Total Gross Revenue** | **৳2,190,600** | 100% collected, 0 due |
| **Total Trip Operating Costs** | **৳638,800** | Fuel, food, chef & deckhand allowance |
| **Total Fixed General Expenses** | **৳220,000** | Marketing, boat upkeep, docking, web |
| **Net Gross Profit** | **৳1,331,800** | Clean, positive seasonal profit |
| **Net Season Profit Margin** | **60.8%** | Industry benchmark healthy margin |
| **Net Profit per Guest** | **৳6,223** | Calculated gross / total guests |

✅ **PASS**

---

## 👷 6. Staff Roster & Salary Sheets

The payroll engine manages 3 employment models and connects directly with approved trip counts.

### Staff Roster Setup
1. **Master Kader Ali:** Boat Captain (Monthly: `৳30,000`)
2. **Abdul Gafur:** Head Chef (Hybrid: `৳18,000/mo` + `৳1,500/trip`)
3. **Rahmat Ullah:** Assistant Chef (Trip-based: `৳2,000/trip`)
4. **Sohel Mia:** Senior Deckhand (Trip-based: `৳1,800/trip`)
5. **Jamal Hossain:** Cabin Steward (Trip-based: `৳1,800/trip`)
6. **Anwar Parvez:** Naturalist Guide (Trip-based: `৳2,500/trip`)
7. **Uttam Kumar:** Operations Manager (Monthly: `৳35,000`)

### Mathematical Payroll Validation:

$$\text{Subtotal} = \text{Rate} \times \text{Trips}$$
$$\text{Payable} = \text{Subtotal} + \text{Monthly Fixed}$$
$$\text{After Advance (Net Disbursed)} = \text{Payable} - \text{Advance Taken}$$

- **July 2026 Sheet (5 Trips):**
  - Gross Wage Bill: **৳131,000**
  - Advance Deductions: **-৳10,000**
  - Net Disbursed: **৳121,000**
- **August 2026 Sheet (6 Trips with Attendance Mutation):**
  - Standard baseline: Gross = ৳140,600, Net = ৳125,600.
  - Tested modifying *Sohel Mia* from `6` trips to `5` trips (1 missed trip).
  - Sohel Mia trip wages recalculated from ৳10,800 to **৳9,000**.
  - Month Gross Wage Bill updated to **৳138,800**; Net Disbursed updated to **৳123,800**. ✅ **PASS**

---

## 🔍 7. Public Booking Tracker & Privacy Verification

- **Lookup Interface:** Tested with phone `01711223344`.
- **Privacy Masking:** Guest name displayed as `Jo•••e`.
- **Bengali Status Badge:** Displays `নিশ্চিত` (Confirmed), trip departure date, cabin allocation, and paid vs due breakdown. ✅ **PASS**

---

## 🏁 8. Test Conclusion & Certification

All functional modules, mathematical algorithms, currency formatters, and accounting reports of the **BHELA Booking Engine (v2.24.0)** were tested in the live environment. All formulas and workflows passed with **100% accuracy and zero defects**.
