# ⚠️ BHELA Booking Engine — Deep Code Audit: All Bugs & Issues

**Plugin:** BHELA Booking Engine v2.24.0  
**Audit Date:** August 10, 2026  
**Auditor:** Systematic Code Review (All 21 `includes/` files)  
**Total Issues Found:** 9 (1 Critical, 3 High, 3 Medium, 2 Low)

---

## 🔴 CRITICAL

---

### BUG-001 — Staff Salary Sheet Not Deducted from Monthly or Yearly Financial Reports
**Files:** `includes/statement.php` (L121), `includes/yearly.php` (L141), `includes/salary.php`  
**Severity:** 🔴 Critical — Financial Overstatement  

**Description:**  
The `bhela_salary` Custom Post Type stores the monthly payroll disbursement, but `bhela_bm_statement_data()` never queries it. The Monthly Statement and Yearly Report both compute:

```php
// statement.php L121 — MISSING salary deduction
$out['gross'] = $out['profit'] - $out['expenses']['total'];
```

Fixed monthly salaries (Captain ৳30,000, Operations Manager ৳35,000) never appear as a deduction, causing gross profit to be overstated every month a salary sheet exists.

**Impact:**  
July 2026 gross profit is overstated by ~৳83,000 (fixed monthly wages). August overstated by ~৳83,000. Annual total overstated by ~৳996,000 (৳83,000 × 12 months).

**Root Cause:**  
The Salary Sheet module was added after the Statement and Yearly Report were built. No integration hook was ever written between them.

**Fix:**  
In `bhela_bm_statement_data()`, after fetching expenses, query `bhela_salary` for the month and add the `monthly` component of `bhela_bm_salary_totals()` as a dedicated `salary_total` key, then subtract from gross:

```php
// --- Insert after L119 ---
$sal_posts = get_posts( array(
    'post_type'      => 'bhela_salary',
    'post_status'    => 'publish',
    'posts_per_page' => 1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
    'meta_query'     => array( array( 'key' => '_bhela_salary_month', 'value' => $month ) ),
) );
$out['salary_total'] = 0;
if ( ! empty( $sal_posts ) && function_exists( 'bhela_bm_salary_rows' ) ) {
    $sal_totals = bhela_bm_salary_totals( bhela_bm_salary_rows( $sal_posts[0], $month ) );
    $out['salary_total'] = (int) $sal_totals['monthly']; // fixed monthly wages only
}
// --- Modify L121 ---
$out['gross'] = $out['profit'] - $out['expenses']['total'] - $out['salary_total'];
```

> **Decision required:** Deduct **total payable** (all staff including trip-based crew) or **fixed monthly wages only** (to avoid double-counting crew pay already in Trip Cost Sheet `staff_convency`).

---

## 🟠 HIGH

---

### BUG-002 — Trip Cost Sheet `staff_convency` and Salary Sheet Both Count Trip-Based Crew Pay
**Files:** `includes/costs.php` (L128, L140), `includes/salary.php` (L181–183)  
**Severity:** 🟠 High — Double Counting Risk  

**Description:**  
The cost sheet default heads include two overlapping heads:
- `staff_convency` → "Staff Convency" (line 128)
- `staff_bill` → "Staff Bill" (line 140)

Both are used in real trips to record crew daily allowances and trip-based wages. Simultaneously, the Salary Sheet module computes `rate × trips` for all Trip-based staff and includes them in `payable`. If the cost sheet records the same crew payments (e.g., deckhand ৳1,800 × 5 trips = ৳9,000 logged in `staff_convency` AND ৳9,000 in the Salary Sheet), these wages are counted twice in the system — once in Trip Operating Costs and once in Payroll.

**Impact:**  
Real-life July 2026 data (from `docs/2026-07-analysis.md`) shows 13 trips each paying ৳19,500–21,000 in "Staff Bill" = ~৳267,000 in trip costs, PLUS a separate salary sheet. If the salary sheet includes the same crew, the business is seeing the same expense in two places with no mechanism to reconcile.

**Fix Options:**
1. **Define clear boundaries in policy:** Trip Cost Sheet `staff_bill` = Daily food/incidental allowances only. Salary Sheet = Formal wages. Rename heads in settings to make this explicit.
2. **Retire `staff_bill` from Trip Cost heads** and make payroll the single source of truth for all crew compensation.

---

### BUG-003 — `_bhela_cost_earnings` Auto-Syncs at Save Time Only; Stale After Booking Changes
**Files:** `includes/costs.php` (`bhela_bm_cost_booking_earnings()` L533, save handler)  
**Severity:** 🟠 High — Financial Data Staleness  

**Description:**  
When a Trip Cost Sheet is saved, the earnings field is populated by reading current bookings for that trip date. However, if a booking is subsequently **added, cancelled, or refunded** after the Cost Sheet has been saved, the `_bhela_cost_earnings` meta value is **not automatically updated**. The statement will then report incorrect earnings for that trip.

**Example:**
1. Trip 3 Jul: 5 bookings, Cost Sheet saved → `_bhela_cost_earnings = ৳204,000`
2. One booking is later cancelled → Trip actual earnings become ৳178,000
3. Monthly Statement still shows ৳204,000 (reading stale meta)

**Fix:**  
Either:  
- Re-derive earnings live from `bhela_bm_cost_booking_earnings()` inside `bhela_bm_statement_data()` instead of reading from saved meta (adds DB queries but is always accurate).
- OR add a `save_post` hook on `bhela_booking` that invalidates and recomputes `_bhela_cost_earnings` for any Cost Sheet whose trip date matches the changed booking's travel date.

---

### BUG-004 — `bhela_bm_salary_trip_count()` Counts Only *Approved* Trips; New Month May Show Zero
**Files:** `includes/salary.php` (L137–142)  
**Severity:** 🟠 High — Payroll Miscalculation Risk  

**Description:**  
```php
// salary.php L141
return count( bhela_bm_statement_data( $month )['trips'] );
```

`bhela_bm_statement_data()` only counts trips whose cost sheets are in `approved` status. A salary sheet created mid-month, before all cost sheets are approved, will default to `0 trips` for all trip-based staff — setting their payable subtotal to ৳0. An admin who does not notice the "Save the month first" tip could save a salary sheet with zero trip pay for the entire crew.

**Impact:**  
Crew members with `type = 'trip'` or `type = 'both'` appear to be owed ৳0 or only their monthly base. If the sheet is printed and used for disbursement before trips are approved, crew are underpaid.

**Fix:**  
Add a visual warning in the Salary Sheet meta box when `$trips === 0`:

```php
if ( 0 === $trips ) {
    echo '<p class="bha-callout bha-callout--attention">';
    esc_html_e( '⚠️ No approved cost sheets found for this month. Trip-based pay is currently ৳0. Approve cost sheets first, then re-save this sheet.', 'bhela-booking' );
    echo '</p>';
}
```

---

## 🟡 MEDIUM

---

### BUG-005 — Yearly Report CSV Missing Salary Column
**Files:** `includes/yearly.php` (L243)  
**Severity:** 🟡 Medium — Incomplete Export Data  

**Description:**  
The CSV export header (line 243) only outputs:
```php
fputcsv( $fh, array( 'Month', 'Trips', 'Guests', 'Earnings', 'Trip Cost', 'Trip Profit', 'Expenses', 'Gross Profit' ) );
```

There is no `Salary` or `Payroll` column. When BUG-001 is fixed and salary deductions appear in the statement, the CSV will still be missing that column, making the exported figures unexplainable (the Gross Profit column will be lower than `Trip Profit - Expenses` with no explanation of the difference).

**Fix:**  
Add `'Salary (Fixed)'` as a column header and include `$m['salary_total']` (once BUG-001 is resolved) in each row.

---

### BUG-006 — Expense `exp_date` Controls Statement Inclusion, but UI Shows Two Date Fields with No Clear Distinction
**Files:** `includes/expenses.php` (L274–276)  
**Severity:** 🟡 Medium — UX Confusion / Potential Misuse  

**Description:**  
The Expense edit screen shows two date fields:
- `Date` (exp_date) — what controls which month this lands in on the statement
- `Payment date` (exp_paid_on) — when it was actually paid

The only hint that `exp_date` (not `paid_on`) controls report inclusion is a `<p class="description">` note at the bottom of the form (line 274–276). This is easy to miss. A user who records the payment date in `exp_date` (intuitive, since that is the actual transaction date) may place the expense in the wrong month's statement.

**Example:**  
Renovation paid on July 31 but the work was done in June — booking the `exp_date` as June places it in June's statement, July's date places it in July's. The user may not understand which to use.

**Fix:**  
Move the `<p class="description">` note to immediately below the `exp_date` field, not at the bottom of the form, and label the field more explicitly: `"Accounting Date (determines which month this appears in)"`.

---

### BUG-007 — `_bhela_salary_total` Stores `payable` But Statement Would Need `monthly` to Avoid Double-Counting
**Files:** `includes/salary.php` (L364–365)  
**Severity:** 🟡 Medium — Architectural Ambiguity  

**Description:**  
When a Salary Sheet is saved, the stored total is `payable` (the full wage bill including both trip-based and fixed monthly wages):

```php
// salary.php L364-365
$totals = bhela_bm_salary_totals( bhela_bm_salary_rows( $post_id, $month ) );
update_post_meta( $post_id, '_bhela_salary_total', $totals['payable'] );
```

But `bhela_bm_salary_totals()` returns both `sub` (trip wages) and `monthly` (fixed wages) separately (line 207). If BUG-001's fix reads only `$totals['payable']`, it will double-count trip crew wages that are also logged in Trip Cost Sheets under `staff_convency`. The stored meta does not preserve which portion is which — so any integration code will need to re-compute from the rows, not from the stored total.

**Fix:**  
Also store `monthly` and `sub` totals separately:
```php
update_post_meta( $post_id, '_bhela_salary_monthly', $totals['monthly'] );
update_post_meta( $post_id, '_bhela_salary_sub', $totals['sub'] );
```

---

## 🔵 LOW / INFORMATIONAL

---

### BUG-008 — OTP Transient Keys Use `md5()` of Phone Number (Non-Collision-Resistant)
**Files:** `includes/otp.php` (L41–47)  
**Severity:** 🔵 Low — Security Informational  

**Description:**  
```php
// otp.php L41-42
function bhela_bm_otp_key( $phone ) {
    return 'bhela_bm_otp_' . md5( (string) $phone );
}
```

MD5 is not collision-resistant (two different inputs can produce the same hash). While a practical collision for an 11-digit phone number is extremely unlikely, the HMAC used for the OTP code itself (`hash_hmac( 'sha256', ... )` on L92) is appropriately secure. Using `sha256` for both keys would be fully consistent.

**Fix:**  
```php
return 'bhela_bm_otp_' . substr( hash_hmac( 'sha256', (string) $phone, wp_salt( 'auth' ) ), 0, 32 );
```

---

### BUG-009 — `bhela_bm_cost_max_custom_rows()` Returns a Fixed Constant with No Admin Override
**Files:** `includes/costs.php` (L309–311)  
**Severity:** 🔵 Low — Usability Limitation  

**Description:**  
```php
// costs.php L309-311
function bhela_bm_cost_max_custom_rows() {
    return 30;
}
```

The cap of 30 custom rows per sheet is hardcoded. The comment (L305–307) notes it was raised from 15 because one July trip used 14. A particularly complex trip (e.g., major engine overhaul with 25+ receipt lines) could hit the cap without warning to the user.

**Fix:**  
Either raise to 50 for safety, or add a `bhela_bm_cost_max_custom_rows` filter so operators can override it per deployment.

---

## 📋 Summary Table

| ID | File | Severity | Issue |
| :--- | :--- | :---: | :--- |
| **BUG-001** | `statement.php`, `yearly.php` | 🔴 Critical | Salary Sheet totals never deducted from Gross Profit |
| **BUG-002** | `costs.php`, `salary.php` | 🟠 High | `staff_convency` in cost sheets + salary sheet → risk of double counting crew wages |
| **BUG-003** | `costs.php` | 🟠 High | `_bhela_cost_earnings` goes stale after a booking is changed post-save |
| **BUG-004** | `salary.php` | 🟠 High | Trip count defaults to 0 until cost sheets are approved; salary sheets created early show ৳0 payable |
| **BUG-005** | `yearly.php` | 🟡 Medium | Yearly CSV export has no Salary/Payroll column |
| **BUG-006** | `expenses.php` | 🟡 Medium | Two date fields on Expense form; accounting date note is too far from the field to prevent mistakes |
| **BUG-007** | `salary.php` | 🟡 Medium | `_bhela_salary_total` stores full `payable`, not broken-down sub-totals; integration will need to recompute |
| **BUG-008** | `otp.php` | 🔵 Low | OTP transient keys use `md5()` instead of `sha256` |
| **BUG-009** | `costs.php` | 🔵 Low | 30-row cap per cost sheet is hardcoded with no admin override |

---

## 🎯 Recommended Priority Order

1. **BUG-001** — Fix salary deduction in `statement.php` and `yearly.php` (financial accuracy)
2. **BUG-002** — Establish formal policy on what goes in `staff_bill` vs Salary Sheet to prevent double-counting
3. **BUG-007** — Store `monthly` and `sub` separately on salary sheet save (enables BUG-001 fix cleanly)
4. **BUG-004** — Add a ⚠️ warning when salary sheet trip count is 0
5. **BUG-003** — Add a booking-changed hook to invalidate stale cost sheet earnings
6. **BUG-005** — Update CSV export once BUG-001 is resolved
7. **BUG-006** — Move accounting date hint to inline position
8. **BUG-008** — Switch OTP key from `md5` to `sha256`
9. **BUG-009** — Raise or make configurable the 30-row custom row cap
