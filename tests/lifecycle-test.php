<?php
/**
 * One investment, end to end, through the screens a person actually uses.
 *
 * Every other investor harness calls data functions. This one replays the browser run
 * that took v2.41.0 to production-ready — investor, agreement, investment, receipts,
 * approval, statement, yearly report, payment, certificates, documents, portal — and
 * RENDERS each screen, because nine of the eleven defects that run found were invisible
 * to a data-function assertion:
 *
 *  - the statement subtracted investor profit and never printed a line saying so;
 *  - the Yearly Report had no column for it, and drew an accrual-only month as "—";
 *  - "Invested ৳0" for every investor whose money came through an Investment Record;
 *  - a top-up re-priced months that were already approved and paid;
 *  - a profit certificate stated the whole term as the period ৳15,000 was earned in;
 *  - its signatures were looked up live, so renaming a user rewrote issued paper;
 *  - receipts and the account statement were built and linked from nowhere;
 *  - the portal's month column was blank and it told a ৳5,00,000 investor "0 shares";
 *  - every period on the approval screen arrived pre-ticked.
 *
 * Figures are fixtures this harness created (§13.38), and the model is stated, not
 * inherited (§13.32 / §13.94).
 */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'admin', 'statement', 'yearly', 'investment-admin', 'profit-admin', 'investor-admin', 'certificates-admin' );
require_once ABSPATH . 'wp-admin/includes/user.php';

/**
 * Everything this harness creates hangs off one investor titled "ZZ Lifecycle Rahim"
 * and two users. Removed at the END of a run — and at the START too, because a run that
 * fatals never reaches its cleanup (the bootstrap's shutdown handler exits first), and
 * the next run then fails at step one on a user that already exists. Reverting a single
 * file to verify a guard is exactly how a run fatals.
 */
function lc_purge() {
	global $wpdb;
	wp_set_current_user( 0 );
	wp_set_current_user( 1 );
	foreach ( get_posts( array(
		'post_type' => 'bhela_investor', 'post_status' => 'any', 'posts_per_page' => -1,
		'fields' => 'ids', 'title' => 'ZZ Lifecycle Rahim',
	) ) as $inv ) {
		$kids = array();
		foreach ( array( '_bhela_led_investor', '_bhela_cap_investor', '_bhela_ivm_investor', '_bhela_agr_investor', '_bhela_crt_investor' ) as $k ) {
			$kids = array_merge( $kids, $wpdb->get_col( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value=%d", $k, $inv
			) ) );
		}
		// Contra rows point at their row, not at the investor, so pick them up too.
		foreach ( $kids as $kid ) {
			$kids = array_merge( $kids, $wpdb->get_col( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_bhela_led_reverses' AND meta_value=%d", $kid
			) ) );
		}
		foreach ( array_unique( array_map( 'intval', $kids ) ) as $kid ) {
			bhela_test_delete( $kid );
		}
		bhela_test_delete( (int) $inv );
	}
	// Orphans: rows whose investor is already gone are unreachable through the
	// investor, but every one of them is titled after it. A leftover row keyed on a
	// re-minted investment code makes the next run's posting refuse as "already
	// posted" — correctly — so it has to go.
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE 'ZZ Lifecycle Rahim%'" ) as $orphan ) {
		bhela_test_delete( (int) $orphan );
	}
	foreach ( array( 'zz_lc_admin', 'zz_lc_investor' ) as $login ) {
		$uid = username_exists( $login );
		if ( $uid ) {
			wp_delete_user( (int) $uid );
		}
	}
}
lc_purge();

$lc_made     = array();
$lc_users    = array();
$lc_seq_was  = get_option( 'bhela_bm_doc_seq', array() );
$lc_cfg_was  = bhela_test_settings_set( array( 'inv_model' => 'fixed' ) );

// A throwaway administrator, so the signature test can rename a user without touching
// the owner's real account.
$lc_admin = wp_insert_user( array(
	'user_login'   => 'zz_lc_admin',
	'user_pass'    => wp_generate_password( 32 ),
	'user_email'   => 'zz_lc_admin@example.invalid',
	'display_name' => 'ZZ Signer One',
	'role'         => 'administrator',
) );
// NEVER `(int)` this before checking it: (int) of a WP_Error is 1, the site's real
// administrator — which is exactly how this harness once deleted it.
if ( is_wp_error( $lc_admin ) || (int) $lc_admin <= 1 ) {
	printf( "\n*** could not create the throwaway admin: %s ***\n",
		is_wp_error( $lc_admin ) ? esc_html( $lc_admin->get_error_message() ) : 'unexpected id' );
	exit( 1 );
}
$lc_users[] = (int) $lc_admin;
wp_set_current_user( 0 );
wp_set_current_user( (int) $lc_admin );
bhela_bm_install_roles();

/** Render an admin screen the way the browser gets it. */
function lc_render( $fn, $get ) {
	$_GET = $get;
	ob_start();
	try {
		$fn();
	} catch ( Throwable $e ) {
		echo 'THREW: ' . $e->getMessage();
	}
	return (string) ob_get_clean();
}

/* ---------- 1. the record ---------- */
echo "=== 1. investor, agreement, investment, receipt, activation ===\n";

$lc_inv = wp_insert_post( array( 'post_type' => 'bhela_investor', 'post_status' => 'publish', 'post_title' => 'ZZ Lifecycle Rahim' ) );
update_post_meta( $lc_inv, '_bhela_inv_status', 'active' );
update_post_meta( $lc_inv, '_bhela_inv_code', 'ZZL-001' );
$lc_made[] = (int) $lc_inv;

$lc_agr = bhela_bm_agreement_add( array( 'investor' => $lc_inv, 'date' => '2025-06-25', 'parties' => 'ZZ parties' ) );
$lc_made[] = (int) $lc_agr;

$lc_id = bhela_bm_investment_add( array(
	'investor' => $lc_inv, 'date' => '2025-07-01', 'start' => '2025-07-01', 'maturity' => '2026-06-30',
	'type' => 'term', 'method' => 'fixed_annual', 'rate' => '12', 'frequency' => 'monthly', 'agreement' => $lc_agr,
) );
$lc_made[] = (int) $lc_id;
$lc_cap1 = bhela_bm_capital_add( array( 'investor' => $lc_inv, 'investment' => $lc_id, 'date' => '2025-07-01', 'amount' => 500000, 'ref' => 'ZZ-REC-1' ) );
$lc_made[] = (int) $lc_cap1;
ok( ! is_wp_error( bhela_bm_investment_transition( $lc_id, 'active' ) ), 'the investment activates once money is recorded' );
$lc_r = bhela_bm_investment( $lc_id );
ok( 60000 === bhela_bm_profit_term_total( $lc_r ), 'the term is worth ৳60,000', (string) bhela_bm_profit_term_total( $lc_r ) );

/* ---------- 2. approval screen ---------- */
echo "\n=== 2. the approval screen asks, it does not assume ===\n";

$lc_html = lc_render( 'bhela_bm_profit_page', array( 'page' => 'bhela-bm-profit' ) );
ok( false !== strpos( $lc_html, 'name="period[]"' ), 'due periods are offered for approval' );
ok( ! preg_match( '/name="period\[\]"[^>]*checked/', $lc_html ),
	'and none arrives pre-ticked — one click used to post a whole year of irreversible rows' );

$lc_july_before = (int) bhela_bm_statement_data( '2025-07' )['investor_profit']['total'];
foreach ( array_slice( bhela_bm_profit_schedule( $lc_r ), 0, 3 ) as $lc_p ) {
	$lc_posted = bhela_bm_profit_post( $lc_r, $lc_p );
	ok( ! is_wp_error( $lc_posted ), 'period ' . $lc_p['key'] . ' posts',
		is_wp_error( $lc_posted ) ? $lc_posted->get_error_code() . ': ' . $lc_posted->get_error_message() : 'row ' . (int) $lc_posted );
}
$lc_again = bhela_bm_profit_post( $lc_r, bhela_bm_profit_schedule( $lc_r )[0] );
ok( is_wp_error( $lc_again ), 'posting July a second time is refused' );

/* ---------- 3. the statement and the yearly report name the deduction ---------- */
echo "\n=== 3. what the books show ===\n";

$lc_st = bhela_bm_statement_data( '2025-07' );
ok( 5000 === (int) $lc_st['investor_profit']['total'] - $lc_july_before,
	'July 2025 gains exactly the ৳5,000 accrual (a delta — §13.38)',
	(string) ( (int) $lc_st['investor_profit']['total'] - $lc_july_before ) );
$lc_html = lc_render( 'bhela_bm_statement_page', array( 'page' => 'bhela-bm-statement', 'month' => '2025-07' ) );
ok( false !== strpos( $lc_html, 'Less: Investor profit' ) && false !== strpos( $lc_html, 'ZZ Lifecycle Rahim' ),
	'the statement prints the deduction it makes — it used to move gross with no line saying why' );

$lc_y = bhela_bm_yearly_data( 2025, 'financial' );
$lc_reconciles = true;
foreach ( $lc_y['months'] as $lc_m ) {
	if ( $lc_m['profit'] - $lc_m['expenses'] - $lc_m['salary'] - $lc_m['commission'] - $lc_m['investor'] !== $lc_m['gross'] ) {
		$lc_reconciles = false;
	}
}
ok( $lc_reconciles, 'every Yearly Report row adds up: profit − expenses − salary − commission − investor = gross' );
ok( 15000 <= (int) $lc_y['totals']['investor'], 'and the year carries the accrual in its own column', (string) $lc_y['totals']['investor'] );
$lc_html = lc_render( 'bhela_bm_yearly_page', array( 'page' => 'bhela-bm-yearly', 'year' => '2025' ) );
ok( false !== strpos( $lc_html, 'Investor Profit' ), 'the Yearly Report has an Investor Profit column' );
ok( ! bhela_bm_yearly_idle( $lc_y['months'][0] ), 'a month whose only movement is an accrual is not drawn as idle' );
ok( (bool) preg_match( '/<option[^>]*value="2025"[^>]*selected/', $lc_html ),
	'and the year selector shows the year the table is showing' );

/* ---------- 4. what the investor put in ---------- */
echo "\n=== 4. invested is the money that arrived ===\n";

$lc_roi = bhela_bm_investor_roi( $lc_inv );
ok( 500000 === (int) $lc_roi['investment'], 'Invested reads ৳5,00,000, not ৳0', (string) $lc_roi['investment'] );
$lc_pay = bhela_bm_ledger_add( array( 'investor' => $lc_inv, 'type' => 'payment', 'amount' => 10000, 'date' => '2025-10-05', 'method' => 'bKash', 'note' => 'ZZ payout' ) );
$lc_roi = bhela_bm_investor_roi( $lc_inv );
ok( 2.0 === (float) $lc_roi['roi'], 'and ROI is 10,000 ÷ 5,00,000 = 2%', (string) $lc_roi['roi'] );

/* ---------- 5. a top-up ---------- */
echo "\n=== 5. a top-up earns from the day it arrives ===\n";

$lc_cap2 = bhela_bm_capital_add( array( 'investor' => $lc_inv, 'investment' => $lc_id, 'date' => '2025-10-01', 'amount' => 100000, 'ref' => 'ZZ-REC-2' ) );
$lc_made[] = (int) $lc_cap2;
$lc_r = bhela_bm_investment( $lc_id );
ok( 69000 === bhela_bm_profit_term_total( $lc_r ),
	'term = 3 × ৳5,000 + 9 × ৳6,000 = ৳69,000 — not ৳72,000 as if the top-up had always been there',
	(string) bhela_bm_profit_term_total( $lc_r ) );
$lc_acc   = bhela_bm_profit_accrue( $lc_r, '2026-06-30' );
$lc_first = $lc_acc['periods'][0];
ok( 5000 === (int) $lc_first['amount'] && 0 === (int) $lc_first['drift'],
	'an approved July still shows the ৳5,000 that was posted', $lc_first['amount'] . ' drift ' . $lc_first['drift'] );
ok( 15000 === (int) $lc_acc['posted'], 'and "approved" is the ledger\'s ৳15,000, not a recomputed ৳18,000', (string) $lc_acc['posted'] );
ok( 6000 === (int) $lc_acc['periods'][3]['amount'], 'October onwards earns on ৳6,00,000', (string) $lc_acc['periods'][3]['amount'] );
$lc_sum = array_sum( wp_list_pluck( bhela_bm_profit_schedule( $lc_r ), 'amount' ) );
ok( 69000 === $lc_sum, 'the periods still sum to the term exactly', (string) $lc_sum );

// Mid-period: ৳31,000 on 17 July is held 15 of July's 31 days.
$lc_b = bhela_bm_investment_add( array(
	'investor' => $lc_inv, 'date' => '2025-07-01', 'start' => '2025-07-01', 'maturity' => '2026-06-30',
	'type' => 'term', 'method' => 'fixed_annual', 'rate' => '12', 'frequency' => 'monthly', 'agreement' => $lc_agr,
) );
$lc_made[] = (int) $lc_b;
$lc_made[] = (int) bhela_bm_capital_add( array( 'investor' => $lc_inv, 'investment' => $lc_b, 'date' => '2025-07-01', 'amount' => 100000 ) );
$lc_made[] = (int) bhela_bm_capital_add( array( 'investor' => $lc_inv, 'investment' => $lc_b, 'date' => '2025-07-17', 'amount' => 31000 ) );
$lc_rb    = bhela_bm_investment( $lc_b );
$lc_sched = bhela_bm_profit_schedule( $lc_rb );
ok( 1150 === (int) $lc_sched[0]['amount'], 'July earns on ৳1,15,000 held (1,00,000 + 31,000 × 15/31) = ৳1,150', (string) $lc_sched[0]['amount'] );
ok( 1310 === (int) $lc_sched[1]['amount'], 'August earns on the full ৳1,31,000 = ৳1,310', (string) $lc_sched[1]['amount'] );
ok( 15560 === bhela_bm_profit_term_total( $lc_rb ) && 15560 === array_sum( wp_list_pluck( $lc_sched, 'amount' ) ),
	'and the term is ৳15,560, carved to the taka', (string) bhela_bm_profit_term_total( $lc_rb ) );

// A back-dated receipt under an approved period: the posted figure stands, and the
// difference is named rather than quietly restated.
bhela_bm_investment_transition( $lc_b, 'active' );
$lc_rb = bhela_bm_investment( $lc_b );
bhela_bm_profit_post( $lc_rb, bhela_bm_profit_schedule( $lc_rb )[0] );
$lc_made[] = (int) bhela_bm_capital_add( array( 'investor' => $lc_inv, 'investment' => $lc_b, 'date' => '2025-07-01', 'amount' => 10000 ) );
$lc_rb   = bhela_bm_investment( $lc_b );
$lc_jul  = bhela_bm_profit_accrue( $lc_rb, '2025-07-31' )['periods'][0];
ok( 1150 === (int) $lc_jul['amount'] && $lc_jul['drift'] > 0,
	'a back-dated receipt leaves the posted ৳1,150 standing and reports the drift',
	$lc_jul['amount'] . ' drift ' . $lc_jul['drift'] );

/* ---------- 6. certificates ---------- */
echo "\n=== 6. certificates say what happened, and keep saying it ===\n";

$lc_pc = bhela_bm_cert_issue( array( 'type' => 'profit', 'investment' => $lc_id ) );
ok( ! is_wp_error( $lc_pc ), 'the profit certificate issues', is_wp_error( $lc_pc ) ? $lc_pc->get_error_message() : '' );
$lc_made[] = (int) $lc_pc;
$lc_pcd = bhela_bm_cert_data( $lc_pc );
ok( '2025-07-01' === $lc_pcd['snapshot']['earned_from'] && '2025-09-30' === $lc_pcd['snapshot']['earned_to'],
	'it states the span the ৳15,000 was earned in — Jul to Sep — not the whole term',
	$lc_pcd['snapshot']['earned_from'] . ' → ' . $lc_pcd['snapshot']['earned_to'] );
ok( 'ZZ Signer One' === ( $lc_pcd['signoff']['approved'] ?? '' ), 'and freezes who signed it', (string) ( $lc_pcd['signoff']['approved'] ?? '(none)' ) );

wp_update_user( array( 'ID' => (int) $lc_admin, 'display_name' => 'ZZ Renamed Later' ) );
clean_user_cache( (int) $lc_admin );
$lc_pcd = bhela_bm_cert_data( $lc_pc );
ok( 'ZZ Signer One' === ( $lc_pcd['signoff']['approved'] ?? '' ),
	'renaming the user afterwards does not rewrite the signature on issued paper',
	(string) ( $lc_pcd['signoff']['approved'] ?? '(none)' ) );

/* ---------- 7. documents are reachable ---------- */
echo "\n=== 7. receipts and the statement can be opened from the office ===\n";

$lc_html = lc_render( 'bhela_bm_investment_page', array( 'page' => 'bhela-bm-investments', 'investment' => $lc_id ) );
ok( false !== strpos( $lc_html, 'bhela_receipt=capital' ), 'every capital receipt row links its receipt' );
$lc_html = lc_render( 'bhela_bm_investor_report_page', array( 'page' => 'bhela-bm-investor-report', 'investor' => $lc_inv ) );
ok( false !== strpos( $lc_html, 'bhela_receipt=payment' ), 'every payment row links its receipt' );
ok( false !== strpos( $lc_html, 'bhela_statement=' ), 'and the ledger links the account statement' );
ok( false === strpos( $lc_html, 'Current share value' ), 'a non-shareholder is not shown a share value block' );

/* ---------- 8. the portal ---------- */
echo "\n=== 8. the investor's own view ===\n";

$lc_pu = wp_insert_user( array( 'user_login' => 'zz_lc_investor', 'user_pass' => wp_generate_password( 32 ),
	'user_email' => 'zz_lc_investor@example.invalid', 'role' => 'bhela_investor' ) );
if ( is_wp_error( $lc_pu ) || (int) $lc_pu <= 1 ) {
	printf( "\n*** could not create the portal user ***\n" );
	exit( 1 );
}
$lc_users[] = (int) $lc_pu;
update_post_meta( $lc_inv, '_bhela_inv_user', (int) $lc_pu );
wp_set_current_user( 0 );
wp_set_current_user( (int) $lc_pu );
ob_start();
$lc_portal = do_shortcode( '[bhela_investor_portal]' );
$lc_portal .= (string) ob_get_clean();
$lc_ptxt   = wp_strip_all_tags( $lc_portal );
ok( false !== strpos( $lc_ptxt, 'July 2025' ), 'the month column names the month — it printed blank for every fixed-model row' );
ok( false === strpos( $lc_ptxt, 'shares ·' ), 'a ৳5,00,000 investor is not told they hold "0 of 125 shares"' );
ok( false === stripos( $lc_portal, 'Warning:' ) && false === stripos( $lc_portal, 'Notice:' ), 'and the portal renders with no PHP warning' );

/* ---------- 9. documents fit a phone ---------- */
echo "\n=== 9. documents read on a phone ===\n";
$lc_css = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/templates/doc-style.php' );
ok( (bool) preg_match( '/@media screen and \(max-width:\s*640px\)\s*\{.*?table\.cert-tbl\s*\{[^}]*overflow-x:\s*auto/s', $lc_css ),
	'the document stylesheet carries a phone layout — a certificate ran 406px wide in a 360px screen' );

/* ---------- cleanup ---------- */
foreach ( array_reverse( $lc_made ) as $lc_x ) {
	if ( $lc_x > 0 && get_post( $lc_x ) ) {
		bhela_test_delete( (int) $lc_x );
	}
}
lc_purge();
update_option( 'bhela_bm_doc_seq', $lc_seq_was, false );
update_option( 'bhela_bm_settings', $lc_cfg_was );

bhela_test_done();
