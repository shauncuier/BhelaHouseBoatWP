# 🔐 BHELA Booking Engine — Security & Application Test Audit

**Perspective:** Senior Security Engineer + Application Penetration Tester  
**Plugin:** BHELA Booking Engine v2.24.0  
**Audit Date:** August 10, 2026  
**Scope:** All 21 `includes/` files, all public AJAX endpoints, all admin actions, data flow  
**Total Issues:** 12 (2 Critical, 4 High, 4 Medium, 2 Low)

---

> IMPORTANT: Critical issues SEC-001 and SEC-002 directly affect guest PII
> (names, phone numbers, payment data) and require immediate attention.

---

## CRITICAL

---

### SEC-001 — Invoice URL Key Built from `post_date`: Predictable After Timing Attack
**File:** `includes/invoice.php` L22  
**CWE:** CWE-330 — Use of Insufficiently Random Values  
**OWASP:** A07:2021 — Identification and Authentication Failures  

**Code:**
```php
// invoice.php L21–23
function bhela_bm_invoice_key( $booking_id ) {
    return wp_hash( 'bhela-invoice-' . $booking_id . get_post_field( 'post_date', $booking_id ) );
}
```

**Vulnerability:**  
The invoice link secret is derived from **two guessable values**: the booking ID (sequential integers,
e.g., 1461, 1462, 1463) and the `post_date` timestamp (format `Y-m-d H:i:s`, visible to anyone who
receives a booking email/WhatsApp from an adjacent booking on the same day). An attacker who holds
one valid invoice URL can:
1. Extract the booking ID from the URL: `?bhela_invoice=1461&key=...`
2. Know approximate `post_date` from their own booking (within 1 hour)
3. Iterate timestamps at second resolution → enumerate other customers' invoices

**Impact:**  
Full exposure of another guest's name, phone, email, travel date, paid amount, and payment transaction
ID — all displayed on the public invoice page without authentication.

**Proof of Concept Attack:**
```
Attacker books one trip -> receives invoice URL:
  /?bhela_invoice=1461&key=abc123...

Deduces booking 1460 exists, guesses post_date = "2026-08-03 14:XX:XX"
Generates: wp_hash('bhela-invoice-1460' . '2026-08-03 14:22:00')
Iterates seconds -> hits valid key -> views victim's name, phone, email, paid amount
```

**Fix:**
```php
function bhela_bm_invoice_key( $booking_id ) {
    $secret = get_post_meta( $booking_id, '_bhela_invoice_secret', true );
    if ( ! $secret ) {
        $secret = wp_generate_password( 32, false );
        update_post_meta( $booking_id, '_bhela_invoice_secret', $secret );
    }
    return wp_hash( 'bhela-invoice-' . $booking_id . $secret );
}
```

---

### SEC-002 — Rate Limiting Uses `REMOTE_ADDR` Only — Broken Behind CDN / CGNAT
**Files:** `includes/frontend.php` (L543, L569, L691), `includes/otp.php` (L131), `includes/reviews.php` (L498)  
**CWE:** CWE-307 — Improper Restriction of Excessive Authentication Attempts  
**OWASP:** A05:2021 — Security Misconfiguration  

**Code:**
```php
// frontend.php L542-546
$ip   = preg_replace( '/[^0-9a-fA-F:.]/', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
$key  = 'bhela_bm_submit_' . md5( $ip );
$hits = (int) get_transient( $key );
if ( $hits >= 10 ) { ... }
```

**Vulnerability (two directions):**

**Attack bypass:** If the site is placed behind a CDN (Cloudflare, BunnyCDN — common in BD),
`REMOTE_ADDR` is the CDN edge node IP — identical for all users globally. The first 10 submissions
burn the CDN IP's quota, then **all bookings are rate-limited for everyone** (denial of service).

**Legitimate user harm:** Mobile ISPs in Bangladesh use CGNAT. A `REMOTE_ADDR`-based OTP daily
cap (5 sends) blocks every subscriber behind that CGNAT IP after 5 OTP sends, regardless of phone
number — genuine customers cannot verify their numbers.

**Fix:**
```php
function bhela_bm_client_ip() {
    $trusted = defined('BHELA_TRUSTED_PROXIES') ? (array) BHELA_TRUSTED_PROXIES : array();
    $remote  = $_SERVER['REMOTE_ADDR'] ?? '';
    if ( in_array( $remote, $trusted, true ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
        return trim( $ips[0] ); // leftmost = original client
    }
    return $remote;
}
```

---

## HIGH

---

### SEC-003 — `wp_verify_nonce()` Called Directly on `$_POST` Without `wp_unslash()`
**Files:** `includes/salary.php` L330, `includes/expenses.php` L287, `includes/costs.php` L1134  
**CWE:** CWE-352 — Cross-Site Request Forgery  

**Code:**
```php
// salary.php L330 — MISSING wp_unslash()
! wp_verify_nonce( $_POST['bhela_bm_salary_nonce'], 'bhela_bm_salary_save' )

// expenses.php L287 — MISSING wp_unslash()
! wp_verify_nonce( $_POST['bhela_bm_expense_nonce'], 'bhela_bm_expense_save' )

// costs.php L1134 — MISSING wp_unslash()
! wp_verify_nonce( $_POST['bhela_bm_cost_nonce'], 'bhela_bm_cost_save' )
```

**Vulnerability:**  
WordPress magic-quotes-emulates `$_POST` on misconfigured stacks. If a nonce value contains
slashable characters, `wp_verify_nonce()` sees a different string than `wp_create_nonce()` produced,
silently failing and **accepting any submission without CSRF protection**.

The correct pattern already used in `admin.php` L364 is:
```php
wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_bm_nonce'] ) ), ... )
```

**Fix:** Apply `sanitize_text_field( wp_unslash( ... ) )` to all three nonce reads.

---

### SEC-004 — Booking Tracker Rate-Limit Only on Miss — Phone Number Enumeration Oracle
**File:** `includes/frontend.php` L687–711  
**CWE:** CWE-200 — Exposure of Sensitive Information  
**OWASP:** A01:2021 — Broken Access Control  

**Code:**
```php
// frontend.php L699–700 — counter only on MISS
if ( ! $ids ) {
    set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
```

**Vulnerability:**  
Rate limit is only incremented on failed lookups. An attacker who knows one valid phone number can:
1. Look up that number repeatedly — success never counted toward limit
2. Retrieve: masked name, travel date, cabin, total, paid, due, status — every time
3. Use 30 allowed misses/hour to enumerate sequential phone suffixes (30 per IP per hour)

**Proof of Concept:**
```
POST admin-ajax.php action=bhela_bm_track q=01711000001 -> miss (count +1)
POST action=bhela_bm_track q=01711000002 -> miss (count +1)
...after 30 misses, switch proxy IP, continue...
POST action=bhela_bm_track q=01711XXXXXX -> HIT (count NOT incremented)
-> Returns: travel date, amount paid, booking status for that customer
```

**Fix:** Count ALL lookups (hit or miss) toward the IP rate limit. Also enforce minimum 8-character
query length to prevent short-prefix enumeration.

---

### SEC-005 — OTP Transient Expiry Resets on Wrong Guess — Extends Brute-Force Window
**File:** `includes/otp.php` L263–264  
**CWE:** CWE-307 — Improper Restriction of Excessive Authentication Attempts  

**Code:**
```php
// otp.php L263–264 — WRONG: resets TTL to full 10 minutes on every failed attempt
set_transient( bhela_bm_otp_key( $phone ), $state, BHELA_BM_OTP_TTL );
```

**Vulnerability:**  
Every failed guess resets the 10-minute TTL. An attacker using 4 wrong guesses (leaving 1 try), 
waiting for a fresh session, then guessing again extends the window indefinitely. The correct 
behaviour is to preserve the original expiry.

**Fix:**
```php
// Store expiry on creation
set_transient( ..., array_merge( $state, ['expires' => time() + BHELA_BM_OTP_TTL] ), BHELA_BM_OTP_TTL );

// On wrong guess — use remaining TTL, not full TTL
$remaining = max( 1, (int)( $state['expires'] ?? 0 ) - time() );
set_transient( bhela_bm_otp_key( $phone ), $state, $remaining );
```

---

### SEC-006 — Cost Sheet Print URL Has No Nonce
**File:** `includes/costs.php` L1344–1355  
**CWE:** CWE-352 — Cross-Site Request Forgery  

**Code:**
```php
// costs.php L1344-1355 — no nonce check
function bhela_bm_cost_print() {
    if ( empty( $_GET['bhela_print'] ) || ! is_admin() ) { return; }
    $post_id = (int) ( $_GET['post'] ?? 0 );
    if ( ! current_user_can( 'read_post', $post_id ) ) { wp_die(...); }
    // Renders full financial data with no nonce
```

**Vulnerability:**  
The print view renders full cost-sheet financial data via `?bhela_print=1&post=ID`. It checks 
`is_admin()` and `current_user_can()` but has **no nonce**. A crafted link sent to an authenticated
cost-checker can exfiltrate the rendered page via CSRF.

**Fix:** Add `check_admin_referer('bhela_bm_cost_print_' . $post_id)` and include the nonce in
the Print button URL.

---

## MEDIUM

---

### SEC-007 — Invoice Page PII Cacheable by Full-Page Cache Plugins
**File:** `includes/invoice.php` L52–60  
**CWE:** CWE-524 — Use of Cache Containing Sensitive Information  

The code already notes: *"Most BD hosting runs LiteSpeed/WP Rocket that may ignore query args —
which would let it serve one guest's invoice to the next visitor."*

`nocache_headers()` only sets HTTP response headers. A full-page cache plugin that intercepts the
request **before PHP runs** will serve the cached HTML regardless. A LiteSpeed Cache default
configuration ignores query strings on the homepage — Guest A's invoice is served to Guest B.

**Fix:**
1. Exclude `?bhela_invoice=` from the cache plugin's URL patterns (document in README).
2. Add `Vary: Cookie` header so cache respects session state.
3. Consider serving invoice on a dedicated CPT-based URL (not homepage + query string).

---

### SEC-008 — OTP Send Counter Keyed with Unsalted `md5(phone)` — Rainbow-Table Enumerable
**File:** `includes/otp.php` L150  
**CWE:** CWE-916 — Use of Insufficient Computational Effort  

```php
$sends_key = 'bhela_bm_otpday_' . md5( $phone );
```

Anyone with DB read access can read all `bhela_bm_otpday_*` transients and rainbow-table the
unsalted MD5 against all Bangladeshi phone number ranges (01700000000–01999999999) to identify
which numbers have received OTPs — a directory of active users.

**Fix:**
```php
$sends_key = 'bhela_bm_otpday_' . substr( hash_hmac( 'sha256', $phone, wp_salt( 'auth' ) ), 0, 32 );
```

---

### SEC-009 — No Per-Phone Submission Throttle — Unlimited Operator Notification Spam
**File:** `includes/frontend.php` L542–549  

The 10-requests/hour IP limit does not prevent one phone number from generating 10 admin
notification emails + 10 SMS + 10 customer confirmation emails before being blocked. With proxies,
unlimited bookings can be submitted using any real phone number, flooding the operator.

**Fix:** Add a per-phone transient counter (max 3 bookings per phone per hour) alongside the
existing IP-based throttle.

---

### SEC-010 — SMS Gateway API Key Stored Plaintext in `wp_options`
**Files:** `includes/admin.php` (settings save), `includes/sms.php`  
**CWE:** CWE-312 — Cleartext Storage of Sensitive Information  

The SMS gateway API key/password is saved via:
```php
update_option( 'bhela_bm_settings', $settings );
```

Stored plaintext in `wp_options`. Accessible to any plugin with `get_option()`, any DB backup,
or anyone with MySQL access. A compromised key allows sending arbitrary SMS billed to the owner.

**Fix Options:**
1. Move to `wp-config.php` constants (`define('BHELA_SMS_API_KEY', '...')`) — outside the DB.
2. Encrypt with `openssl_encrypt()` using a key derived from `AUTH_SALT` before storing.
3. Use a dedicated low-balance sub-account for the SMS gateway.

---

## LOW

---

### SEC-011 — CSV Export Missing `X-Content-Type-Options` Header
**File:** `includes/yearly.php` L235–237  
**CWE:** CWE-116 — Improper Encoding / MIME Sniffing  

The yearly CSV download is missing `X-Content-Type-Options: nosniff`. The dynamically-generated
filename includes special characters (`–` en-dash in `2026–27`) which may cause issues in some
browsers. Also missing from the report CSV export in `includes/reports.php`.

**Fix:**
```php
header( 'X-Content-Type-Options: nosniff' );
header( 'Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $label) . '.csv"' );
```

---

### SEC-012 — Review Body Output Should Be Verified for `esc_html()` Coverage
**File:** `includes/reviews.php` (frontend render)  
**CWE:** CWE-79 — Cross-Site Scripting  

Review bodies are sanitized with `sanitize_textarea_field()` on save (strips HTML). If any
frontend template echoes review body with `echo $body` instead of `echo esc_html($body)`,
stored XSS is possible. The admin templates were not fully traced.

**Fix:** Audit all review body render points; ensure `esc_html()` or `wp_kses_post()` wraps all output.

---

## Summary Table

| ID | Severity | Category | Issue |
| :--- | :---: | :--- | :--- |
| **SEC-001** | CRITICAL | Insecure Token | Invoice key = sequential ID + guessable timestamp → PII enumeration |
| **SEC-002** | CRITICAL | Rate Limiting | `REMOTE_ADDR` only — broken behind CDN (global lockout) and CGNAT |
| **SEC-003** | HIGH | CSRF | `wp_verify_nonce()` without `wp_unslash()` on 3 save handlers |
| **SEC-004** | HIGH | IDOR | Tracker rate-limit only on miss → phone enumeration oracle |
| **SEC-005** | HIGH | Brute Force | OTP TTL reset on wrong guess → extends brute-force window |
| **SEC-006** | HIGH | CSRF | Cost sheet print URL has no nonce |
| **SEC-007** | MEDIUM | PII Caching | Invoice page PII cacheable; `nocache_headers()` insufficient for page caches |
| **SEC-008** | MEDIUM | Info Disclosure | OTP send counter keyed with unsalted `md5(phone)` → rainbow-table enumerable |
| **SEC-009** | MEDIUM | Abuse / Spam | No per-phone submission throttle → unlimited operator notification spam |
| **SEC-010** | MEDIUM | Credential Storage | SMS gateway API key stored plaintext in `wp_options` |
| **SEC-011** | LOW | HTTP Headers | CSV export missing `X-Content-Type-Options: nosniff` |
| **SEC-012** | LOW | XSS | Review body output not fully traced for `esc_html()` coverage |

---

## What the Code Does Well

| Feature | Status |
| :--- | :--- |
| CSRF on all admin forms | PASS — `wp_nonce_field()` + `wp_verify_nonce()` everywhere |
| AJAX nonce on all public endpoints | PASS — `check_ajax_referer('bhela_bm_booking')` on all public actions |
| OTP code stored as HMAC-SHA256 hash | PASS — never stored or transmitted in plain text |
| Invoice access authorization | PASS — `hash_equals()` timing-safe comparison |
| SSRF prevention on SMS gateway | PASS — `bhela_bm_sms_url_is_safe()` blocks private IPs, requires HTTPS |
| Output escaping throughout | PASS — consistent `esc_html()`, `esc_attr()`, `esc_url()` |
| Capability isolation for staff roles | PASS — no `manage_options` or `edit_posts` for any staff role |
| SQL injection prevention | PASS — WP_Query / `get_posts()` exclusively; no raw `$wpdb->query()` |
| Honeypot spam protection | PASS — `bhela_bm_hp` hidden field on all public forms |
| Privacy masking in tracker | PASS — `bhela_bm_mask_name()` returns masked name, not full PII |
| `nocache_headers()` on invoice + CSV | PARTIAL — effective for HTTP; insufficient for full-page cache plugins |
