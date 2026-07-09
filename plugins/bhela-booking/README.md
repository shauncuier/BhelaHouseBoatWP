# 🔌 BHELA Booking Engine (v1.1.0)

A custom-built WordPress plugin that acts as the core transactional pricing engine and booking dashboard for **BHELA – The Haor Exclusive**. 

The plugin provides a custom post type (`bhela_booking`), manages cabin booking requests, calculates pricing (applying weekday/weekend/holiday rules), generates PDF-printable invoices, and links customer notifications via email and WhatsApp.

---

## 🛠️ Database Options Schema

The plugin maintains settings and rate structures in the WordPress options table:

### 1. General Settings (`bhela_bm_settings`)
Stored as a serialized array containing:
*   `business_name` — Display name of the business ("BHELA – The Haor Exclusive").
*   `phone_1` / `phone_2` / `whatsapp` / `email` — Company contact details.
*   `bkash_number` / `nagad_number` — Payment accounts for receiving advances.
*   `advance_percent` — Deposit percentage required to lock a booking (default: `50%`).
*   `weekend_days` — Day indexes representing peak rates (default: `5` [Friday] and `6` [Saturday]).
*   `holidays` — Newline-separated dates (`YYYY-MM-DD`) treated as peak rate dates.
*   `invoice_note` — Custom instructions displayed on customer invoices.

### 2. Cabin Rates (`bhela_bm_rates`)
Key-value list mapping cabin classes to pricing schemas:
*   `budget` — Budget Friendly Cabin (৬ জন শেয়ারিং, peak: ৳8,000, weekday: ৳6,400)
*   `comfort` — Comfort Adjustment Cabin (৫ জন শেয়ারিং, peak: ৳9,000, weekday: ৳7,200)
*   `deluxe` — Double Deluxe Cabin (৪ জন শেয়ারিং, peak: ৳10,000, weekday: ৳8,000)
*   `luxury` — Luxury Triple Cabin (৩ জন শেয়ারিং, peak: ৳12,000, weekday: ৳9,600)
*   `couple` — Exclusive Couple Cabin (২ জন শেয়ারিং, peak: ৳13,000, weekday: ৳10,400)

---

## 💰 Pricing Engine Mechanics

For any selected date:
1.  **Holiday Match**: Checks if the target date matches any date list in `holidays`. If yes, returns **Holiday rate** (Full peak rate).
2.  **Weekend Match**: Checks if the weekday index matches `weekend_days` (Friday/Saturday). If yes, returns **Weekend rate** (Full peak rate).
3.  **Weekday Discount**: If neither is met, calculates the **Weekday rate** (a `-20%` discount applied to the base rate).
4.  **Math**: `Total = Per Person Rate * Number of Guests`. Deposit/Advance and Due amounts are then computed based on `advance_percent`.

---

## 📋 Booking Workflow & Statuses

Bookings move through the following lifecycle states:
*   `pending` (Yellow) — Submission received. Awaiting review and payment confirmation.
*   `advance_paid` (Teal) — Customer submitted bKash/Nagad Transaction ID for the deposit.
*   `confirmed` (Green) — Admin confirms receipt. Incurs an **automatic confirmation email** to the customer.
*   `completed` (Grey) — Trip concluded successfully.
*   `cancelled` (Red) — Canceled request.

---

## 📄 Automated Invoicing

*   **Format**: Unique sequential invoice numbers matching `BH-YYYY-XXXX` (e.g. `BH-2026-0001`).
*   **Customer Link**: Secure guest links allowing customers to view or print their invoice (`invoice.php` template style).
*   **Content**: Lists customer details, chosen cabin type, guests counts, date of trip, detailed billing breakdown (advance paid, balance due), and payment procedures.
