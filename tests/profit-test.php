<?php
/**
 * The profit engine, and the two places it touches the books.
 *
 * BHELA moved from an equity model to a fixed-return one. That makes an investor's
 * profit a **financing cost** rather than a share of what was made, and this harness
 * exists mostly to pin the three consequences that are easy to get wrong and expensive
 * to get wrong quietly:
 *
 * 1. **Posting twice pays once.** Running a month again is the most ordinary mistake
 *    there is, and the second run must write nothing.
 * 2. **The statement deducts it — and deducts only what this engine posted.** A `profit`
 *    ledger row can also come from a committed share distribution or the settlement
 *    importer, and counting those would take the same money off the bottom line twice
 *    and move months the business has already closed.
 * 3. **Two engines never both pay.** With the fixed model live, a share distribution
 *    refuses, because an investor holding shares and an investment would otherwise be
 *    paid once by each and both rows would look individually correct.
 *
 * Figures are fixtures this harness created or deltas (§13.38).
 */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles' );

wp_set_current_user( 0 );
wp_set_current_user( 1 );
bhela_bm_install_roles();

/* ---------- fixtures ---------- */

$pf_made     = array();
$pf_settings = get_option( 'bhela_bm_settings', array() );
$pf_seq_was  = get_option( 'bhela_bm_doc_seq', array() );
$pf_runs_was = get_option( 'bhela_bm_dist_runs', array() );

function pf_investor( $name ) {
	$id = wp_insert_post( array(
		'post_type' => 'bhela_investor', 'post_status' => 'publish', 'post_title' => $name,
	) );
	update_post_meta( $id, '_bhela_inv_shares', 0 );
	update_post_meta( $id, '_bhela_inv_amount', 0 );
	update_post_meta( $id, '_bhela_inv_status', 'active' );
	$GLOBALS['pf_made'][] = (int) $id;
	return (int) $id;
}

function pf_agreement( $investor ) {
	$id = bhela_bm_agreement_add( array(
		'investor' => $investor, 'date' => '2024-06-25', 'parties' => 'ZZ parties',
	) );
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	$GLOBALS['pf_made'][] = (int) $id;
	return (int) $id;
}

/** A draft investment with capital behind it, ready to activate. */
function pf_investment( $investor, $args = array() ) {
	$agreement = pf_agreement( $investor );
	$id        = bhela_bm_investment_add( array_merge( array(
		'investor'  => $investor,
		'date'      => '2024-07-01',
		'start'     => '2024-07-01',
		'maturity'  => '2025-06-30',
		'type'      => 'term',
		'method'    => 'fixed_annual',
		'rate'      => '12',
		'frequency' => 'monthly',
		'agreement' => $agreement,
	), $args ) );
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	$GLOBALS['pf_made'][] = (int) $id;

	$cap = bhela_bm_capital_add( array(
		'investor'   => $investor,
		'investment' => $id,
		'date'       => '2024-07-01',
		'amount'     => (int) ( $args['principal'] ?? 500000 ),
		'note'       => 'ZZ principal',
	) );
	if ( ! is_wp_error( $cap ) ) {
		$GLOBALS['pf_made'][] = (int) $cap;
	}
	return (int) $id;
}

$pf_a   = pf_investor( 'ZZ Profit A' );
$pf_inv = pf_investment( $pf_a );

echo "=== 1. the principal is the receipts, not a typed number ===\n";

$pf_r = bhela_bm_investment( $pf_inv );
ok( 500000 === $pf_r['principal'], 'principal is the sum of the capital rows', (string) $pf_r['principal'] );
ok( 12 === $pf_r['months'], '01 Jul 2024 to 30 Jun 2025 is twelve months, not eleven', (string) $pf_r['months'] );
ok( 1200 === $pf_r['rate_bp'], '12% is stored as 1200 basis points — never a float', (string) $pf_r['rate_bp'] );

echo "\n=== 2. the four methods, against figures worked by hand ===\n";

// The client's own worked example: 5,00,000 x 12% x 12/12 = 60,000.
ok( 60000 === bhela_bm_profit_term_total( $pf_r ), 'fixed annual: 60,000', (string) bhela_bm_profit_term_total( $pf_r ) );

$pf_monthly = $pf_r;
$pf_monthly['method']  = 'monthly_rate';
$pf_monthly['rate_bp'] = 100;                      // 1% a month
ok( 60000 === bhela_bm_profit_term_total( $pf_monthly ), 'monthly 1% x 12: 60,000', (string) bhela_bm_profit_term_total( $pf_monthly ) );

$pf_day = $pf_r;
$pf_day['method'] = 'day_based';
ok( 365 === bhela_bm_profit_days( '2024-07-01', '2025-06-30' ), 'the term is 365 days inclusive', (string) bhela_bm_profit_days( '2024-07-01', '2025-06-30' ) );
ok( 60000 === bhela_bm_profit_term_total( $pf_day ), 'day-based on a 365 basis: 60,000', (string) bhela_bm_profit_term_total( $pf_day ) );

// The basis is not cosmetic: the same term on 360 is worth 833 taka more, which is
// exactly why the certificate prints which one was used.
$pf_s                  = bhela_bm_get_settings();
$pf_s['inv_day_basis'] = 360;
update_option( 'bhela_bm_settings', $pf_s );
ok( 60833 === bhela_bm_profit_term_total( $pf_day ), 'and 60,833 on a 360 basis', (string) bhela_bm_profit_term_total( $pf_day ) );
$pf_s['inv_day_basis'] = 365;
update_option( 'bhela_bm_settings', $pf_s );

$pf_share = $pf_r;
$pf_share['method'] = 'profit_share';
ok( 0 === bhela_bm_profit_term_total( $pf_share ),
	'profit sharing with no committed distribution earns nothing — it does not fall back to a rate' );

echo "\n=== 3. the periods sum to the term, exactly ===\n";

$pf_sched = bhela_bm_profit_schedule( $pf_r );
ok( 12 === count( $pf_sched ), 'a monthly schedule over a year is twelve periods', (string) count( $pf_sched ) );
ok( 5000 === $pf_sched[0]['amount'], 'each month is 5,000', (string) $pf_sched[0]['amount'] );
ok( '2024-07-01' === $pf_sched[0]['from'] && '2024-07-31' === $pf_sched[0]['to'], 'the first period is July' );
ok( '2025-06-30' === $pf_sched[11]['to'], 'and the last closes on the maturity date', $pf_sched[11]['to'] );

$pf_sum = 0;
foreach ( $pf_sched as $p ) {
	$pf_sum += $p['amount'];
}
ok( 60000 === $pf_sum, 'they add up to the term total', (string) $pf_sum );

// The case independent rounding gets wrong: 12.5% is 62,500 a year, and twelve rounded
// 5,208s is 62,496. Four taka a year is how a ledger stops reconciling (§13.30).
$pf_odd            = $pf_r;
$pf_odd['rate_bp'] = 1250;
$pf_odd_total      = bhela_bm_profit_term_total( $pf_odd );
$pf_odd_sum        = 0;
foreach ( bhela_bm_profit_schedule( $pf_odd ) as $p ) {
	$pf_odd_sum += $p['amount'];
}
ok( 62500 === $pf_odd_total, '12.5% for a year is 62,500', (string) $pf_odd_total );
ok( $pf_odd_sum === $pf_odd_total,
	'and the twelve periods still sum to it exactly',
	$pf_odd_sum . ' vs ' . $pf_odd_total );

echo "\n=== 4. nothing is owed until a person approves it ===\n";

$pf_before = bhela_bm_settlement_investor( $pf_a );
ok( null === $pf_before, 'a fresh investment puts nothing in the ledger at all' );

$pf_acc = bhela_bm_profit_accrue( $pf_r, '2024-07-31' );
ok( 1 === count( $pf_acc['periods'] ), 'one period has ended by 31 Jul 2024', (string) count( $pf_acc['periods'] ) );
ok( 5000 === $pf_acc['unposted'] && 0 === $pf_acc['posted'], 'and it reads as unposted' );
ok( null === bhela_bm_settlement_investor( $pf_a ), 'accruing still writes nothing' );

echo "\n=== 5. an inactive investment cannot post ===\n";

$pf_p1 = $pf_acc['periods'][0];
$pf_no = bhela_bm_profit_post( $pf_r, $pf_p1 );
ok( is_wp_error( $pf_no ) && 'not_active' === $pf_no->get_error_code(), 'a draft refuses to post' );

echo "\n=== 6. activation refuses what it cannot certify ===\n";

$pf_bare = bhela_bm_investment_add( array( 'investor' => $pf_a, 'start' => '2024-07-01' ) );
$GLOBALS['pf_made'][] = (int) $pf_bare;
$pf_block = bhela_bm_investment_blockers( $pf_bare );
ok( count( $pf_block ) >= 4, 'a bare draft lists every missing thing', (string) count( $pf_block ) );
ok( is_wp_error( bhela_bm_investment_transition( $pf_bare, 'active' ) ), 'and cannot be activated' );

// A rate is never assumed. This is the whole reason activation refuses rather than
// defaulting: a guessed 12% would end up on a document somebody files with a bank.
bhela_bm_investment_save( $pf_bare, array(
	'investor' => $pf_a, 'start' => '2024-07-01', 'maturity' => '2025-06-30',
	'method' => 'fixed_annual', 'frequency' => 'monthly', 'rate' => '0',
	'agreement' => pf_agreement( $pf_a ),
) );
$pf_block = bhela_bm_investment_blockers( $pf_bare );
$pf_rate_named = false;
foreach ( $pf_block as $b ) {
	if ( false !== mb_strpos( $b, 'হার' ) ) {
		$pf_rate_named = true;
	}
}
ok( $pf_rate_named, 'a blank rate is named as the blocker, not filled in' );

echo "\n=== 7. posting twice pays once ===\n";

ok( true === bhela_bm_investment_transition( $pf_inv, 'active' ), 'the complete draft activates' );
$pf_r = bhela_bm_investment( $pf_inv );

$pf_row = bhela_bm_profit_post( $pf_r, $pf_p1 );
ok( ! is_wp_error( $pf_row ), 'the first period posts', is_wp_error( $pf_row ) ? $pf_row->get_error_message() : '' );

$pf_again = bhela_bm_profit_post( $pf_r, $pf_p1 );
ok( is_wp_error( $pf_again ) && 'already' === $pf_again->get_error_code(), 'the same period refuses a second time' );

$pf_after = bhela_bm_settlement_investor( $pf_a );
ok( 5000 === $pf_after['declared'], 'and the investor is owed 5,000 — once', (string) $pf_after['declared'] );

echo "\n=== 8. a period that has not ended cannot be posted ===\n";

$pf_future = bhela_bm_profit_schedule( $pf_r );
$pf_last   = end( $pf_future );
$pf_last['due'] = false;
$pf_nd     = bhela_bm_profit_post( $pf_r, $pf_last );
ok( is_wp_error( $pf_nd ) && 'not_due' === $pf_nd->get_error_code(), 'tomorrow\'s profit is not today\'s' );

echo "\n=== 9. the statement deducts it, and only under the fixed model ===\n";

// Deltas: the dev site carries real trips and real expenses in other months.
$pf_month = '2024-07';
$pf_s     = bhela_bm_get_settings();

$pf_s['inv_model'] = 'shares';
update_option( 'bhela_bm_settings', $pf_s );
$pf_shares_gross = bhela_bm_statement_data( $pf_month )['gross'];

$pf_s['inv_model'] = 'fixed';
update_option( 'bhela_bm_settings', $pf_s );
$pf_fixed = bhela_bm_statement_data( $pf_month );

ok( 5000 === $pf_fixed['investor_profit']['total'], 'the month carries the accrual', (string) $pf_fixed['investor_profit']['total'] );
ok( ( $pf_shares_gross - $pf_fixed['gross'] ) === 5000,
	'and gross falls by exactly it',
	$pf_shares_gross . ' → ' . $pf_fixed['gross'] );

echo "\n=== 10. a distribution's own rows are NOT a financing cost ===\n";

// The trap this guard exists for: a committed share distribution writes `profit` rows
// too. Counting those would deduct from the bottom line money that IS the bottom line,
// and would move every month the business has already closed.
$pf_dist_row = bhela_bm_ledger_add( array(
	'investor' => $pf_a, 'type' => 'profit', 'amount' => 99000,
	'date' => '2024-07-15', 'ref' => 'ZZ not-an-investment', 'note' => 'ZZ dist',
) );
$pf_after_dist = bhela_bm_statement_data( $pf_month );
ok( 5000 === $pf_after_dist['investor_profit']['total'],
	'a profit row with no investment behind it is ignored',
	(string) $pf_after_dist['investor_profit']['total'] );
ok( $pf_after_dist['gross'] === $pf_fixed['gross'], 'so the statement does not move' );

echo "\n=== 11. two engines never both pay ===\n";

$pf_gate = bhela_bm_dist_commit( '2024-07' );
ok( is_wp_error( $pf_gate ) && 'model' === $pf_gate->get_error_code(),
	'a share distribution refuses while the fixed model is live',
	is_wp_error( $pf_gate ) ? $pf_gate->get_error_code() : 'no error' );

$pf_s['inv_model'] = 'shares';
update_option( 'bhela_bm_settings', $pf_s );
$pf_gate_off = bhela_bm_dist_commit( '2024-07' );
ok( is_wp_error( $pf_gate_off ) && 'model' !== $pf_gate_off->get_error_code(),
	'and stops refusing for that reason once the model is back',
	is_wp_error( $pf_gate_off ) ? $pf_gate_off->get_error_code() : 'committed' );

echo "\n=== 12. an active investment is locked ===\n";

update_post_meta( $pf_inv, '_bhela_ivm_rate_bp', 9999 );
ok( 1200 === (int) get_post_meta( $pf_inv, '_bhela_ivm_rate_bp', true ), 'update_post_meta is refused' );

delete_post_meta( $pf_inv, '_bhela_ivm_absent' );
add_post_meta( $pf_inv, '_bhela_ivm_absent', 'forged' );
ok( '' === get_post_meta( $pf_inv, '_bhela_ivm_absent', true ), 'add_post_meta on an absent key is refused (§13.49)' );

delete_post_meta_by_key( '_bhela_ivm_rate_bp' );
ok( 1200 === (int) get_post_meta( $pf_inv, '_bhela_ivm_rate_bp', true ), 'a blanket delete across every post is refused (§13.55)' );

wp_delete_post( $pf_inv, true );
ok( 'bhela_investment' === get_post_type( $pf_inv ), 'hard delete is refused' );

ok( is_wp_error( bhela_bm_investment_save( $pf_inv, array( 'rate' => '5' ) ) ),
	'and the terms cannot be edited while it is active' );

// The hinge that stops the lock being a trap.
ok( is_wp_error( bhela_bm_investment_transition( $pf_inv, 'draft' ) ),
	'reopening with no reason is refused' );
ok( true === bhela_bm_investment_transition( $pf_inv, 'draft', 'ZZ correcting the rate' ),
	'reopening with one is allowed, and audited' );
ok( ! bhela_bm_investment_locked( $pf_inv ), 'and the record unlocks again' );

echo "\n=== 13. a voided receipt is not principal ===\n";

$pf_r2  = bhela_bm_investment( $pf_inv );
$pf_cap = bhela_bm_capital_add( array(
	'investor' => $pf_a, 'investment' => $pf_inv, 'date' => '2024-08-01', 'amount' => 100000,
) );
$GLOBALS['pf_made'][] = (int) $pf_cap;
ok( 600000 === bhela_bm_investment_principal( $pf_inv ), 'a second receipt raises the principal', (string) bhela_bm_investment_principal( $pf_inv ) );
bhela_bm_capital_void( $pf_cap, 'ZZ entered twice' );
ok( 500000 === bhela_bm_investment_principal( $pf_inv ), 'and voiding it takes it back out', (string) bhela_bm_investment_principal( $pf_inv ) );

echo "\n=== 14. the capability is its own ===\n";

ok( get_role( 'bhela_manager' )->has_cap( 'bhela_investor_profit' ), 'a Manager approves profit' );
ok( ! get_role( 'bhela_investor_relations' )->has_cap( 'bhela_investor_profit' ),
	'Investor Relations prepares but does not approve' );
ok( ! get_role( 'bhela_investor' )->has_cap( 'bhela_investor_profit' ), 'an investor holds nothing' );

/* ---------- cleanup ---------- */
wp_set_current_user( 0 );
wp_set_current_user( 1 );
global $wpdb;
foreach ( $GLOBALS['pf_made'] as $pf_id ) {
	if ( 'bhela_investor' === get_post_type( $pf_id ) ) {
		foreach ( $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_bhela_led_investor' AND meta_value=%d",
			$pf_id
		) ) as $pf_led ) {
			bhela_test_delete( (int) $pf_led );
		}
	}
	bhela_test_delete( (int) $pf_id );
}
update_option( 'bhela_bm_settings', $pf_settings );
update_option( 'bhela_bm_doc_seq', $pf_seq_was, false );
update_option( 'bhela_bm_dist_runs', $pf_runs_was, false );

bhela_test_done();
