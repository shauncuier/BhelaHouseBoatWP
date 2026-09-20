<?php
/**
 * Settlement: which way the money runs, and the import that fills in the history.
 *
 * The feature exists because profit was paid out unevenly, so some investors are owed
 * money and some owe it. The defect it replaces is that every screen added those two
 * directions together — an investor owed ৳50,000 and one owing ৳50,000 read as ৳0
 * outstanding, which is the opposite of the truth.
 *
 * So the assertion that matters most here is a NEGATIVE one: the two totals must not be
 * derivable from each other, and must not collapse into a net. Everything else is
 * arithmetic that the ledger already did.
 *
 * Figures are deltas or fixtures this harness created (§13.38) — the dev site carries
 * real investors and a real distribution, and an assertion about the database rather
 * than the code breaks the moment somebody records a payment.
 */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles' );

wp_set_current_user( 0 );
wp_set_current_user( 1 );

/* ---------- fixtures ---------- */

$st_made = array();

function st_investor( $name, $shares = 10 ) {
	$id = wp_insert_post( array(
		'post_type' => 'bhela_investor', 'post_status' => 'publish', 'post_title' => $name,
	) );
	update_post_meta( $id, '_bhela_inv_shares', $shares );
	update_post_meta( $id, '_bhela_inv_amount', $shares * 100000 );
	update_post_meta( $id, '_bhela_inv_status', 'active' );
	$GLOBALS['st_made'][] = (int) $id;
	return (int) $id;
}

function st_row( $inv, $type, $amount, $date, $reverses = 0 ) {
	$r = bhela_bm_ledger_add( array(
		'investor' => $inv, 'type' => $type, 'amount' => $amount,
		'date' => $date, 'reverses' => $reverses, 'note' => 'ZZ settle',
	) );
	return is_wp_error( $r ) ? 0 : (int) $r;
}

/** Only this harness's investors, so the dev site's own register cannot move a total. */
function st_only( $d, $ids ) {
	$out = array(
		'owed_to_investor' => 0, 'owed_to_bhela' => 0, 'undeclared' => 0,
		'count_owed' => 0, 'count_owes' => 0, 'count_undeclared' => 0, 'rows' => array(),
	);
	foreach ( $d['rows'] as $r ) {
		if ( ! in_array( $r['investor'], $ids, true ) ) {
			continue;
		}
		$out['rows'][ $r['investor'] ] = $r;
		if ( 'owed' === $r['state'] ) {
			$out['owed_to_investor'] += $r['balance'];
			$out['count_owed']++;
		} elseif ( 'owes' === $r['state'] ) {
			$out['owed_to_bhela'] += abs( $r['balance'] );
			$out['count_owes']++;
		} elseif ( 'undeclared' === $r['state'] ) {
			$out['undeclared'] += $r['paid'];
			$out['count_undeclared']++;
		}
	}
	return $out;
}

// A: declared 100k, paid 40k  -> BHELA owes 60k
$st_a = st_investor( 'ZZ Settle A' );
st_row( $st_a, 'profit',  100000, '2026-02-10' );
st_row( $st_a, 'payment',  40000, '2026-02-20' );

// B: declared 100k, paid 150k -> B owes BHELA 50k
$st_b = st_investor( 'ZZ Settle B' );
st_row( $st_b, 'profit',  100000, '2026-02-10' );
st_row( $st_b, 'payment', 150000, '2026-02-20' );

// C: paid, nothing declared -> undeclared, NOT overpaid
$st_c = st_investor( 'ZZ Settle C' );
st_row( $st_c, 'payment', 30000, '2026-02-20' );

// D: declared == paid -> settled
$st_d = st_investor( 'ZZ Settle D' );
st_row( $st_d, 'profit',  50000, '2026-02-10' );
st_row( $st_d, 'payment', 50000, '2026-02-20' );

// E: a payment inside the window, reversed AFTER it
$st_e     = st_investor( 'ZZ Settle E' );
st_row( $st_e, 'profit', 80000, '2026-02-10' );
$st_e_pay = st_row( $st_e, 'payment', 80000, '2026-02-20' );
st_row( $st_e, 'adjustment', 80000, '2026-06-01', $st_e_pay );

// F: an ordinary adjustment, not a reversal
$st_f = st_investor( 'ZZ Settle F' );
st_row( $st_f, 'profit',      60000, '2026-02-10' );
st_row( $st_f, 'payment',     20000, '2026-02-20' );
st_row( $st_f, 'adjustment', -10000, '2026-02-25' );

$st_ids = array( $st_a, $st_b, $st_c, $st_d, $st_e, $st_f );

echo "\n=== 1. which way the money runs ===\n";

$st_all = bhela_bm_settlement();
$st_m   = st_only( $st_all, $st_ids );

ok( 'owed' === $st_m['rows'][ $st_a ]['state'] && 60000 === $st_m['rows'][ $st_a ]['balance'],
	'paid less than declared: BHELA owes 60,000',
	$st_m['rows'][ $st_a ]['state'] . ' ' . $st_m['rows'][ $st_a ]['balance'] );
ok( 'owes' === $st_m['rows'][ $st_b ]['state'] && -50000 === $st_m['rows'][ $st_b ]['balance'],
	'paid MORE than declared: the investor owes 50,000',
	$st_m['rows'][ $st_b ]['state'] . ' ' . $st_m['rows'][ $st_b ]['balance'] );
ok( 'settled' === $st_m['rows'][ $st_d ]['state'], 'declared equals paid: settled' );

echo "\n=== 2. the two directions are never netted ===\n";
// THE assertion. A: +60,000. F: 60,000 - 20,000 - 10,000 = +30,000. B: -50,000.
// E is +80,000 as well, and that is the right answer rather than a wrinkle: 80,000 was
// declared, 80,000 was paid, and then the payment was REVERSED — the money came back,
// so BHELA still owes it. This window is open, so the reversal is inside it. (The first
// version of this section expected 90,000 and two investors owed, which was an
// assertion about my own arithmetic rather than about the code.)
ok( 170000 === $st_m['owed_to_investor'], 'ভেলা দেবে = 1,70,000', (string) $st_m['owed_to_investor'] );
ok( 50000 === $st_m['owed_to_bhela'], 'ভেলা পাবে = 50,000', (string) $st_m['owed_to_bhela'] );
// Netted, these would be 120,000 — one number that answers neither question.
ok( 120000 !== $st_m['owed_to_investor'] && 120000 !== $st_m['owed_to_bhela'],
	'and neither total is the net of the two' );
ok( 3 === $st_m['count_owed'] && 1 === $st_m['count_owes'],
	'counted on both sides', $st_m['count_owed'] . ' / ' . $st_m['count_owes'] );

echo "\n=== 3. paid with nothing declared is UNDECLARED, not overpaid ===\n";
ok( 'undeclared' === $st_m['rows'][ $st_c ]['state'], 'C reads undeclared',
	$st_m['rows'][ $st_c ]['state'] );
ok( 30000 === $st_m['undeclared'], 'reported on its own', (string) $st_m['undeclared'] );
// The point of the state: without it C's 30,000 would be in owed_to_bhela.
ok( 50000 === $st_m['owed_to_bhela'], 'and NOT counted as money the investor owes' );

echo "\n=== 4. a reversal is honoured wherever it sits ===\n";
$st_feb = st_only( bhela_bm_settlement( '2026-02-01', '2026-03-31' ), $st_ids );
ok( 0 === $st_feb['rows'][ $st_e ]['paid'],
	'a payment inside the window, reversed outside it, does not count as paid',
	(string) $st_feb['rows'][ $st_e ]['paid'] );
ok( 80000 === $st_feb['rows'][ $st_e ]['declared'], 'while the declared profit still does' );

echo "\n=== 5. balance = declared + adjustments − paid, exactly ===\n";
foreach ( $st_ids as $st_id ) {
	$r = $st_m['rows'][ $st_id ];
	ok( $r['balance'] === $r['declared'] + $r['adjustments'] - $r['paid'],
		'the parts add up for ' . $r['name'],
		$r['declared'] . ' + ' . $r['adjustments'] . ' - ' . $r['paid'] . ' = ' . $r['balance'] );
}

echo "\n=== 6. over an open window it IS the ledger's own closing balance ===\n";
// Two readings of one number is how a silent disagreement starts (§13.23). These two
// must agree by construction, not by coincidence.
foreach ( $st_ids as $st_id ) {
	$pos = bhela_bm_investor_position( $st_id );
	ok( $st_m['rows'][ $st_id ]['balance'] === $pos['outstanding'],
		'settlement equals position for ' . get_the_title( $st_id ),
		$st_m['rows'][ $st_id ]['balance'] . ' vs ' . $pos['outstanding'] );
}

echo "\n=== 7. a blank window means EVERY date ===\n";
// §13.51: a sentinel range reads as "no filter" and is not one.
$st_old = st_investor( 'ZZ Settle Ancient' );
st_row( $st_old, 'profit', 12345, '2003-04-05' );
$st_wide = st_only( bhela_bm_settlement(), array( $st_old ) );
ok( isset( $st_wide['rows'][ $st_old ] ), 'a 2003 row is inside the open window' );
ok( 12345 === $st_wide['rows'][ $st_old ]['declared'], 'with its figure intact' );
$st_ids[] = $st_old;

echo "\n=== 8. the season window agrees with the season reader ===\n";
$st_seasons_was = get_option( 'bhela_bm_seasons', array() );
bhela_bm_save_seasons( array(
	array( 'key' => '', 'label' => 'ZZ Settle Season', 'from' => '2026-02-01', 'to' => '2026-03-31' ),
) );
$st_skey = '';
foreach ( bhela_bm_seasons() as $k => $srow ) {
	if ( 'ZZ Settle Season' === $srow['label'] ) {
		$st_skey = $k;
	}
}
ok( '' !== $st_skey, 'the season saved', $st_skey );

$st_sdata = bhela_bm_season_investors( $st_skey );
$st_win   = bhela_bm_settlement( '2026-02-01', '2026-03-31' );
ok( $st_sdata['declared'] === $st_win['declared'], 'declared agrees to the taka',
	$st_sdata['declared'] . ' vs ' . $st_win['declared'] );
ok( $st_sdata['paid'] === $st_win['paid'], 'and so does paid' );
// The season reader delegates now, so it carries the two directions as well.
ok( $st_sdata['owed_to_investor'] === $st_win['owed_to_investor']
	&& $st_sdata['owed_to_bhela'] === $st_win['owed_to_bhela'],
	'and the two directions, rather than only their net' );

$st_wres = bhela_bm_settlement_window( $st_skey );
ok( '2026-02-01' === $st_wres['from'] && '2026-03-31' === $st_wres['to'],
	'the filter resolves a season to its own dates' );
$st_wnone = bhela_bm_settlement_window( '', '', '' );
ok( '' === $st_wnone['from'] && '' === $st_wnone['to'],
	'and a blank filter resolves to no bound at all, not to a sentinel' );

echo "\n=== 9. the importer refuses what it cannot resolve ===\n";
$st_dupe_a = st_investor( 'ZZ Settle Twin' );
$st_dupe_b = st_investor( 'ZZ Settle Twin' );
$st_ids[]  = $st_dupe_a;
$st_ids[]  = $st_dupe_b;

$st_rows = array(
	array( 'Investor', 'Date', 'Amount', 'Profit' ),
	array( 'ZZ Settle A',     '2026-04-01', '৳12,000', '' ),
	array( 'ZZ Settle Twin',  '2026-04-01', '5000',    '' ),   // two records, refused
	array( 'ZZ Nobody Here',  '2026-04-01', '5000',    '' ),   // no match, refused
	array( 'ZZ Settle A',     'not a date', '5000',    '' ),   // bad date, refused
	array( 'ZZ Settle A',     '2026-04-01', '-500',    '' ),   // negative, refused
	array( 'ZZ Settle A',     '2026-04-01', '',        '' ),   // nothing, refused
);
$st_map  = array( 'investor' => 0, 'date' => 1, 'amount' => 2, 'profit' => 3, 'method' => '', 'ref' => '', 'note' => '' );
$st_plan = bhela_bm_settle_import_plan( $st_rows, $st_map );

ok( 1 === count( $st_plan['ok'] ), 'one row of six is importable', (string) count( $st_plan['ok'] ) );
ok( 5 === count( $st_plan['bad'] ), 'and five are refused with a reason', (string) count( $st_plan['bad'] ) );
ok( 12000 === $st_plan['paid'], 'the ৳ sign and the comma are read, not dropped',
	(string) $st_plan['paid'] );
$st_why = array();
foreach ( $st_plan['bad'] as $b ) {
	$st_why[] = $b['why'];
}
ok( count( array_filter( $st_why ) ) === count( $st_why ), 'every refusal says why' );

$st_twin = bhela_bm_settle_import_investor( 'ZZ Settle Twin' );
ok( 0 === $st_twin['id'] && '' !== $st_twin['error'],
	'a name on two records is refused rather than resolved to the first' );

echo "\n=== 10. the dry run writes nothing ===\n";
$st_before = bhela_bm_investor_position( $st_a );
bhela_bm_settle_import_plan( $st_rows, $st_map );
$st_after = bhela_bm_investor_position( $st_a );
ok( $st_before['outstanding'] === $st_after['outstanding'],
	'planning an import moves no money at all' );

echo "\n=== 11. a spent staging cannot be committed twice ===\n";
$st_token = wp_generate_password( 20, false );
set_site_transient( bhela_bm_settle_import_key( $st_token ), array(
	'rows' => $st_rows, 'user' => get_current_user_id(), 'name' => 'ZZ.csv',
), 600 );
ok( null !== bhela_bm_settle_import_staged( $st_token ), 'the staging reads back' );
delete_site_transient( bhela_bm_settle_import_key( $st_token ) );
ok( null === bhela_bm_settle_import_staged( $st_token ),
	'and once spent it is gone — a refresh cannot write the batch again' );

// One importer's file is not another's.
set_site_transient( bhela_bm_settle_import_key( $st_token ), array(
	'rows' => $st_rows, 'user' => get_current_user_id() + 999, 'name' => 'ZZ.csv',
), 600 );
ok( null === bhela_bm_settle_import_staged( $st_token ),
	'another user\'s staging is refused' );
delete_site_transient( bhela_bm_settle_import_key( $st_token ) );

echo "\n=== 12. an import writes ordinary ledger rows, and moves the settlement ===\n";
$st_imp_before = bhela_bm_settlement_investor( $st_a )['paid'];
$st_batch      = 'IMP-ZZ-' . wp_generate_password( 4, false );
$st_imp_row    = bhela_bm_ledger_add( array(
	'investor' => $st_a, 'type' => 'payment', 'amount' => 12000,
	'date' => '2026-04-01', 'ref' => $st_batch, 'note' => 'ZZ imported',
) );
ok( ! is_wp_error( $st_imp_row ), 'the row writes' );
$st_imp_after = bhela_bm_settlement_investor( $st_a )['paid'];
ok( $st_imp_before + 12000 === $st_imp_after, 'and the settlement moves by exactly that',
	$st_imp_before . ' -> ' . $st_imp_after );
ok( $st_batch === bhela_bm_ledger_row( $st_imp_row )['ref'],
	'carrying the batch reference that makes it findable again' );

// Reversal is the undo, because there is no delete path.
$st_rev = bhela_bm_ledger_reverse( $st_imp_row, 'ZZ wrong batch' );
ok( ! is_wp_error( $st_rev ), 'a wrong import row is reversed, not deleted' );
ok( $st_imp_before === bhela_bm_settlement_investor( $st_a )['paid'],
	'and the settlement returns to where it was',
	(string) bhela_bm_settlement_investor( $st_a )['paid'] );

echo "\n=== 13. the export neutralises its text cells ===\n";
ok( "'=cmd|' /C calc'!A0" === bhela_bm_csv_cell( "=cmd|' /C calc'!A0" ),
	'a formula payload is defused before it reaches a spreadsheet' );
ok( '12345' === (string) bhela_bm_csv_cell( '12345' ), 'and a plain figure is untouched' );

echo "\n=== 14. an administrator may approve their own request; a manager may not ===\n";
$st_mgr = wp_insert_user( array(
	'user_login' => 'zz_settle_mgr', 'user_email' => 'zz-settle-mgr@example.test',
	'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_manager',
) );
$st_rel = wp_insert_user( array(
	'user_login' => 'zz_settle_rel', 'user_email' => 'zz-settle-rel@example.test',
	'user_pass' => wp_generate_password( 20 ), 'role' => 'bhela_investor_relations',
) );

/** Switch user from a cold cache (§13.15). */
function st_as( $uid ) {
	wp_set_current_user( 0 );
	clean_user_cache( $uid );
	wp_set_current_user( $uid );
	return $uid;
}

// A manager raising and approving their own is still refused.
$st_mgr_user = new WP_User( $st_mgr );
$st_mgr_user->add_cap( 'bhela_investor_pay' );
clean_user_cache( $st_mgr );
st_as( $st_mgr );
$st_req_m = bhela_bm_payreq_add( array(
	'investor' => $st_a, 'type' => 'payment', 'amount' => 1500, 'date' => '2026-05-01',
) );
ok( ! is_wp_error( $st_req_m ), 'the manager raises a request',
	is_wp_error( $st_req_m ) ? $st_req_m->get_error_code() : 'ok' );
$st_self_m = bhela_bm_payreq_approve( $st_req_m );
ok( is_wp_error( $st_self_m ) && 'same_person' === $st_self_m->get_error_code(),
	'and may NOT approve it themselves — the second signature still stands',
	is_wp_error( $st_self_m ) ? $st_self_m->get_error_code() : 'approved!' );

// The administrator may, at the owner's instruction, and it is recorded.
wp_set_current_user( 0 );
wp_set_current_user( 1 );
$st_req_a = bhela_bm_payreq_add( array(
	'investor' => $st_a, 'type' => 'payment', 'amount' => 2500, 'date' => '2026-05-02',
) );
ok( ! is_wp_error( $st_req_a ), 'the administrator raises one' );
$st_self_a = bhela_bm_payreq_approve( $st_req_a );
ok( ! is_wp_error( $st_self_a ), 'and may approve it',
	is_wp_error( $st_self_a ) ? $st_self_a->get_error_code() : 'ok' );
ok( 1 === (int) get_post_meta( $st_req_a, '_bhela_pr_self', true ),
	'with the request recording that one hand did both halves' );

// Clean the two approved payments back out of the balance.
$st_paid_rows = array( (int) $st_self_a );
foreach ( $st_paid_rows as $st_pr ) {
	if ( $st_pr ) {
		bhela_bm_ledger_reverse( $st_pr, 'ZZ harness cleanup' );
	}
}

echo "\n=== 15. the capability is the administrator's alone ===\n";
bhela_bm_install_roles();
ok( ! get_role( 'bhela_manager' )->has_cap( 'bhela_investor_import' ),
	'a Manager cannot import payments' );
ok( ! get_role( 'bhela_investor_relations' )->has_cap( 'bhela_investor_import' ),
	'nor can Investor Relations — it writes payment rows with no second signature' );
ok( get_role( 'administrator' )->has_cap( 'bhela_investor_import' ),
	'the administrator can' );

/* ---------- cleanup ---------- */
wp_set_current_user( 0 );
wp_set_current_user( 1 );
global $wpdb;
foreach ( $GLOBALS['st_made'] as $st_id ) {
	foreach ( $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_bhela_led_investor' AND meta_value=%d",
		$st_id
	) ) as $st_row_id ) {
		bhela_test_delete( (int) $st_row_id );
	}
	foreach ( get_posts( array(
		'post_type' => 'bhela_payreq', 'post_status' => 'any', 'posts_per_page' => -1,
		'fields' => 'ids', 'no_found_rows' => true,
		'meta_key' => '_bhela_pr_investor', 'meta_value' => $st_id,
	) ) as $st_pq ) {
		bhela_test_delete( (int) $st_pq );
	}
	bhela_test_delete( $st_id );
}
update_option( 'bhela_bm_seasons', $st_seasons_was, false );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $st_mgr, $st_rel ) as $st_u ) {
	if ( $st_u && ! is_wp_error( $st_u ) ) {
		wp_delete_user( $st_u );
	}
}

bhela_test_done();
