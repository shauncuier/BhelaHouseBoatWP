<?php
/** Dev helper: save-handler round trip on the new keyed rows, incl. the custom-row cap. */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'reports', 'costs' );
function by_label( $lines, $label ) { foreach ( $lines as $l ) { if ( $l['label'] === $label ) return $l; } return null; }

wp_set_current_user( 1 );
$sheet = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ roundtrip' ) );

function save( $sheet, $lines, $income = null ) {
	$_POST = array(
		'bhela_bm_cost_nonce'  => wp_create_nonce( 'bhela_bm_cost_save' ),
		'bhela_cost_trip_date' => '2026-07-01',
		'bhela_cost_header'    => array( 'trip_id' => 'WJ3-2601', 'total_guest' => '25' ),
		'bhela_cost_lines'     => $lines,
		'bhela_cost_earnings'  => '142400',
	);
	// null means "this form has no income block at all" — a sheet saved by a build
	// that predates income heads, which is the case that must not change value.
	if ( null !== $income ) {
		$_POST['bhela_cost_income'] = $income;
	}
	bhela_bm_cost_save( $sheet, get_post( $sheet ) );
	$_POST = array();
}

echo "=== 1. keyed save round-trips ===\n";
save( $sheet, array(
	'engine_fuel'   => array( 'p1' => 15600, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'groceries'     => array( 'p1' => 16095, 'p2' => 290, 'p3' => 0, 'remark' => '' ),
	'staff_bill'    => array( 'p1' => 19500, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'custom_spoon'  => array( 'label' => 'Spoon', 'p1' => 250, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'custom_batt'   => array( 'label' => 'Pencil Battary', 'p1' => 100, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'custom_elec'   => array( 'label' => 'Electric Materials', 'p1' => 1260, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'custom_cold'   => array( 'label' => 'Cold Drinks', 'p1' => 470, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
	'blank_one'     => array( 'label' => '', 'p1' => 0, 'p2' => 0, 'p3' => 0, 'remark' => '' ),
) );
$total = (int) get_post_meta( $sheet, '_bhela_cost_total', true );
ok( 53565 === $total, 'total = 15600+16385+19500+250+100+1260+470', (string) $total );

$stored = json_decode( (string) get_post_meta( $sheet, '_bhela_cost_lines', true ), true );
ok( ! isset( $stored[0] ), 'stored as a KEYED map, not positional', implode( ',', array_slice( array_keys( $stored ), 0, 4 ) ) );
ok( ! array_key_exists( 'blank_one', $stored ), 'untouched blank row was dropped, not accumulated' );
ok( 4 === count( array_filter( array_keys( $stored ), function ( $k ) { return 0 === strpos( $k, 'custom_' ); } ) ), 'all four July one-offs kept' );

$lines = bhela_bm_cost_lines( $sheet );
ok( 250 === ( by_label( $lines, 'Spoon' )['sub'] ?? 0 ), 'Spoon renders back' );
ok( 1260 === ( by_label( $lines, 'Electric Materials' )['sub'] ?? 0 ), 'Electric Materials renders back' );

echo "\n=== 2. saving again is stable (no drift, no duplicates) ===\n";
$before = get_post_meta( $sheet, '_bhela_cost_lines', true );
$again  = array();
foreach ( bhela_bm_cost_lines( $sheet ) as $l ) {
	$again[ $l['key'] ] = array( 'label' => $l['label'], 'p1' => $l['p1'], 'p2' => $l['p2'], 'p3' => $l['p3'], 'remark' => $l['remark'] );
}
save( $sheet, $again );
ok( 53565 === (int) get_post_meta( $sheet, '_bhela_cost_total', true ), 'total identical after re-save' );
$after = json_decode( (string) get_post_meta( $sheet, '_bhela_cost_lines', true ), true );
ok( 7 === count( $after ), 'still 7 rows, no blank accumulation', (string) count( $after ) );

echo "\n=== 3. custom-row cap holds against a crafted POST ===\n";
$flood = array( 'engine_fuel' => array( 'p1' => 100 ) );
for ( $i = 0; $i < 60; $i++ ) {
	$flood[ 'x_' . $i ] = array( 'label' => 'Junk ' . $i, 'p1' => 1 );
}
save( $sheet, $flood );
$after = json_decode( (string) get_post_meta( $sheet, '_bhela_cost_lines', true ), true );
$customs = count( array_filter( array_keys( $after ), function ( $k ) { return 0 === strpos( $k, 'x_' ); } ) );
ok( $customs <= bhela_bm_cost_max_custom_rows(), 'capped at ' . bhela_bm_cost_max_custom_rows(), (string) $customs );

echo "\n=== 3b. the B2B line fills itself, and is not double counted ===\n";
// The commission is entered once, on the booking. The cost sheet's B2B Partner line
// fills from it — so the same 3,000 cannot be deducted once by the sheet and again
// by the statement reading the bookings.
bhela_bm_save_agencies( array(
	array( 'id' => '', 'name' => 'ZZ RT Agency', 'phone' => '', 'email' => '', 'rate' => 10 ),
) );
$rt_ag = '';
foreach ( bhela_bm_agencies() as $aid => $arow ) {
	if ( 'ZZ RT Agency' === $arow['name'] ) {
		$rt_ag = $aid;
	}
}
$rt_book = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_status' => 'publish', 'post_title' => 'ZZ rt b2b' ) );
update_post_meta( $rt_book, '_bhela_travel_date', '2026-07-01' );
update_post_meta( $rt_book, '_bhela_status', 'confirmed' );
update_post_meta( $rt_book, '_bhela_total', 30000 );
update_post_meta( $rt_book, '_bhela_agency', $rt_ag );
update_post_meta( $rt_book, '_bhela_commission', 3000 );

update_post_meta( $sheet, '_bhela_cost_status', 'draft' );   // §4 below locks it again
save( $sheet, array( 'engine_fuel' => array( 'p1' => 5000 ) ) );
$rt_lines = bhela_bm_cost_stored_lines( $sheet );
ok( 3000 === (int) ( $rt_lines['b2b_partner']['p1'] ?? 0 ), 'the B2B line filled itself from the booking',
	(string) ( $rt_lines['b2b_partner']['p1'] ?? 0 ) );
ok( 8000 === (int) get_post_meta( $sheet, '_bhela_cost_total', true ), 'and is included in the sheet total once',
	get_post_meta( $sheet, '_bhela_cost_total', true ) );

// Move the commission underneath the sheet: it must REPORT, never silently rewrite.
// Same contract as the earnings drift, and for the same reason — three people sign
// off a sheet, and changing a figure they approved without saying so is worse than
// showing it is out of date.
update_post_meta( $rt_book, '_bhela_commission', 4200 );
$rt_drift = bhela_bm_cost_b2b_drift( $sheet );
ok( $rt_drift['stale'], 'a changed commission is reported as stale' );
ok( 3000 === (int) $rt_drift['stored'] && 4200 === (int) $rt_drift['live'], 'with both figures named',
	$rt_drift['stored'] . ' → ' . $rt_drift['live'] );
ok( 3000 === (int) bhela_bm_cost_stored_lines( $sheet )['b2b_partner']['p1'], 'and the sheet is NOT rewritten behind the owner' );

// A figure typed over by hand is a decision, not a cache — leave it alone entirely.
save( $sheet, array( 'engine_fuel' => array( 'p1' => 5000 ), 'b2b_partner' => array( 'p1' => 9999 ) ) );
ok( 9999 === (int) bhela_bm_cost_stored_lines( $sheet )['b2b_partner']['p1'], 'a hand-typed B2B figure survives a save',
	(string) bhela_bm_cost_stored_lines( $sheet )['b2b_partner']['p1'] );
ok( ! bhela_bm_cost_b2b_drift( $sheet )['stale'], 'and is not reported stale — it was never auto-filled' );

bhela_test_delete( $rt_book );
// The agency directory is NOT deleted here. It is owner-built data with live
// referral tokens in it, and a delete_option() on the way out took a real partner
// with it once already. bhela_test_owner_options() in the bootstrap snapshots and
// restores it, so this harness can write to it freely and leave nothing behind.

echo "\n=== 4. approved sheet still refuses writes ===\n";
update_post_meta( $sheet, '_bhela_cost_status', 'approved' );
$locked_total = (int) get_post_meta( $sheet, '_bhela_cost_total', true );
save( $sheet, array( 'engine_fuel' => array( 'p1' => 999999 ) ) );
ok( $locked_total === (int) get_post_meta( $sheet, '_bhela_cost_total', true ), 'locked sheet unchanged' );


echo "\n=== 5. income heads, and the one rule that keeps them honest ===\n";
$rt_inc = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ income' ) );

// A sheet saved with no income block at all behaves exactly as it did before this
// existed. This is the assertion that lets the feature ship: nothing already
// approved can change value.
save( $rt_inc, array( 'engine_fuel' => array( 'p1' => 10000 ) ) );
ok( 142400 === (int) get_post_meta( $rt_inc, '_bhela_cost_earnings', true ), 'no income block: the typed earnings figure stands', (string) get_post_meta( $rt_inc, '_bhela_cost_earnings', true ) );
ok( array() === bhela_bm_cost_income( $rt_inc ), 'and nothing is stored against the heads' );
ok( 0 === bhela_bm_cost_income_total( $rt_inc ), 'so the income total is zero, which is what "not in use" means' );

// Fill the heads and the earnings ARE their sum — note the typed 142,400 above is
// deliberately still in the POST and is deliberately overruled.
save( $rt_inc, array( 'engine_fuel' => array( 'p1' => 10000 ) ), array(
	'cabin' => '90000', 'food' => '12000', 'bbq' => '4500', 'transport' => '3500',
) );
ok( 110000 === bhela_bm_cost_income_total( $rt_inc ), 'the heads add up', (string) bhela_bm_cost_income_total( $rt_inc ) );
ok( 110000 === (int) get_post_meta( $rt_inc, '_bhela_cost_earnings', true ),
	'and the sheet\'s earnings ARE that sum — not the number still sitting in the earnings box',
	(string) get_post_meta( $rt_inc, '_bhela_cost_earnings', true ) );

// An unrecognised key is a tampered or stale field. Keeping it would put money on
// the sheet under a label nothing can render.
save( $rt_inc, array( 'engine_fuel' => array( 'p1' => 10000 ) ), array(
	'cabin' => '90000', 'not_a_head' => '50000',
) );
ok( 90000 === bhela_bm_cost_income_total( $rt_inc ), 'an unknown head is dropped rather than stored', (string) bhela_bm_cost_income_total( $rt_inc ) );
ok( ! isset( bhela_bm_cost_income( $rt_inc )['not_a_head'] ), 'and does not appear on the sheet' );

// Clearing every line hands the earnings box back.
save( $rt_inc, array( 'engine_fuel' => array( 'p1' => 10000 ) ), array( 'cabin' => '0', 'food' => '' ) );
ok( array() === bhela_bm_cost_income( $rt_inc ), 'clearing every line clears the block' );
ok( 142400 === (int) get_post_meta( $rt_inc, '_bhela_cost_earnings', true ), 'and the typed earnings figure governs again',
	(string) get_post_meta( $rt_inc, '_bhela_cost_earnings', true ) );

echo "\n=== 6. revenue by source agrees with the sheets it reads ===\n";
save( $rt_inc, array( 'engine_fuel' => array( 'p1' => 10000 ) ), array( 'cabin' => '90000', 'food' => '12000' ) );

// A draft is a proposal. A revenue report that moved whenever somebody opened a
// sheet and typed in it would not be a report.
update_post_meta( $rt_inc, '_bhela_cost_status', 'draft' );
$rt_rev = bhela_bm_revenue_by_source( '2026-07-01', '2026-07-31', 'month' );
ok( 0 === $rt_rev['grand'] || ! isset( $rt_rev['totals']['food'] ) || 12000 !== $rt_rev['totals']['food'],
	'a draft sheet contributes nothing' );

update_post_meta( $rt_inc, '_bhela_cost_status', 'approved' );
$rt_rev = bhela_bm_revenue_by_source( '2026-07-01', '2026-07-31', 'month' );
ok( isset( $rt_rev['totals']['food'] ) && 12000 === (int) $rt_rev['totals']['food'], 'approving it brings the food line in',
	(string) ( $rt_rev['totals']['food'] ?? 0 ) );
ok( $rt_rev['grand'] >= 102000, 'and the grand total carries both heads', (string) $rt_rev['grand'] );

// Every column plus the unsplit column equals the grand total. Two ways of adding
// the same figures is how a silent disagreement starts, so they are pinned together.
$rt_cols = array_sum( $rt_rev['totals'] ) + $rt_rev['unsplit'];
ok( $rt_cols === $rt_rev['grand'], 'the columns and the total agree to the taka', $rt_cols . ' vs ' . $rt_rev['grand'] );
$rt_periods = 0;
foreach ( $rt_rev['periods'] as $rt_p ) {
	$rt_periods += $rt_p['total'];
}
ok( $rt_periods === $rt_rev['grand'], 'and so do the rows', $rt_periods . ' vs ' . $rt_rev['grand'] );

// One trip's own report has to say the same thing the range report does.
$rt_one = bhela_bm_trip_report( $rt_inc );
ok( 102000 === $rt_one['earnings'], 'the trip P&L reports the same revenue', (string) $rt_one['earnings'] );
ok( 2 === count( $rt_one['income'] ), 'broken into two sources' );
ok( $rt_one['earnings'] - $rt_one['cost'] === $rt_one['profit'], 'and profit is revenue minus cost' );
ok( 100.0 === array_sum( wp_list_pluck( $rt_one['income'], 'pct' ) ), 'the source shares come to 100%',
	(string) array_sum( wp_list_pluck( $rt_one['income'], 'pct' ) ) );

// A spreadsheet executes a cell that opens with =. Every free-text cell in the
// export goes through the neutraliser; no figure does.
$rt_csv = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/trip-report.php' );
ok( false !== strpos( $rt_csv, 'bhela_bm_csv_cell' ), 'the CSV neutralises its text cells' );
ok( "'=cmd|' /C calc'!A0" === bhela_bm_csv_cell( '=cmd|\' /C calc\'!A0' ), 'and the neutraliser still does its job',
	bhela_bm_csv_cell( '=cmd|\' /C calc\'!A0' ) );

bhela_test_delete( $rt_inc );


echo "\n=== 7. a one-off row survives being saved twice ===\n";
// The client's report: "sometimes an extra field is auto-removed after save". It was
// a key collision, and it only bit on the SECOND save, which is why it looked
// intermittent. A row typed into a pre-rendered blank is stored under that blank's
// own key — new_0 — and the render then emitted a FRESH blank new_0 after it. Two
// inputs, one name: the browser sends both, PHP keeps the last, and the last is the
// empty one. The typed row and its money vanished off the sheet's total.
$rt_row = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ rowkeys' ) );

save( $rt_row, array(
	'engine_fuel' => array( 'p1' => 5000 ),
	// Exactly what the form names its first spare slot.
	'new_0'       => array( 'label' => 'Chair', 'p1' => 1200, 'remark' => '' ),
) );
ok( 'Chair' === ( bhela_bm_cost_stored_lines( $rt_row )['new_0']['label'] ?? '' ), 'a one-off typed into a blank slot is stored' );
ok( 6200 === (int) get_post_meta( $rt_row, '_bhela_cost_total', true ), 'and its money is in the total', (string) get_post_meta( $rt_row, '_bhela_cost_total', true ) );

// The render is where the bug lived: no key may appear twice, or the browser cannot
// help but send one row's value under another row's name.
$rt_rendered = bhela_bm_cost_lines( $rt_row );
$rt_seen  = array();
$rt_dupes = array();
foreach ( $rt_rendered as $rt_r ) {
	if ( isset( $rt_seen[ $rt_r['key'] ] ) ) {
		$rt_dupes[] = $rt_r['key'];
	}
	$rt_seen[ $rt_r['key'] ] = true;
}
ok( ! $rt_dupes, 'the form emits no key twice', implode( ', ', $rt_dupes ) );

// Now save the way a browser does: every rendered row, in render order, last one
// winning on a repeated name. This is the assertion the old code failed.
$rt_post = array();
foreach ( $rt_rendered as $rt_r ) {
	$rt_post[ $rt_r['key'] ] = array(
		'label'  => $rt_r['fixed'] ? '' : $rt_r['label'],
		'p1'     => $rt_r['p1'],
		'p2'     => $rt_r['p2'],
		'p3'     => $rt_r['p3'],
		'remark' => $rt_r['remark'],
	);
}
save( $rt_row, $rt_post );
ok( 'Chair' === ( bhela_bm_cost_stored_lines( $rt_row )['new_0']['label'] ?? '' ), 'and it is still there after a second save',
	wp_json_encode( bhela_bm_cost_stored_lines( $rt_row )['new_0'] ?? null ) );
ok( 6200 === (int) get_post_meta( $rt_row, '_bhela_cost_total', true ), 'with the total unchanged',
	(string) get_post_meta( $rt_row, '_bhela_cost_total', true ) );

// A third round trip, because the collision appeared one save late the first time.
$rt_post2 = array();
foreach ( bhela_bm_cost_lines( $rt_row ) as $rt_r ) {
	$rt_post2[ $rt_r['key'] ] = array(
		'label'  => $rt_r['fixed'] ? '' : $rt_r['label'],
		'p1'     => $rt_r['p1'],
		'p2'     => $rt_r['p2'],
		'p3'     => $rt_r['p3'],
		'remark' => $rt_r['remark'],
	);
}
save( $rt_row, $rt_post2 );
ok( 'Chair' === ( bhela_bm_cost_stored_lines( $rt_row )['new_0']['label'] ?? '' ), 'and after a third' );

// Several one-offs at once, each typed into a different blank, all keeping their own
// label and figure — the form offers five slots and a real July sheet used fourteen.
$rt_many = array( 'engine_fuel' => array( 'p1' => 1000 ) );
$rt_names = array( 'Spoon' => 250, 'Pencil Battary' => 100, 'Electric Materials' => 1260, 'Cold Drinks' => 800, 'Silencer Screw' => 300 );
$rt_i = 0;
foreach ( $rt_names as $rt_label => $rt_amt ) {
	$rt_many[ 'new_' . $rt_i ] = array( 'label' => $rt_label, 'p1' => $rt_amt, 'remark' => '' );
	$rt_i++;
}
save( $rt_row, $rt_many );
$rt_stored = bhela_bm_cost_stored_lines( $rt_row );
$rt_kept = 0;
foreach ( $rt_names as $rt_label => $rt_amt ) {
	foreach ( $rt_stored as $rt_line ) {
		if ( ( $rt_line['label'] ?? '' ) === $rt_label && (int) ( $rt_line['p1'] ?? 0 ) === $rt_amt ) {
			$rt_kept++;
		}
	}
}
ok( 5 === $rt_kept, 'five one-off rows all survive one save', (string) $rt_kept );

$rt_render2 = bhela_bm_cost_lines( $rt_row );
$rt_seen2 = array();
$rt_dup2  = array();
foreach ( $rt_render2 as $rt_r ) {
	if ( isset( $rt_seen2[ $rt_r['key'] ] ) ) {
		$rt_dup2[] = $rt_r['key'];
	}
	$rt_seen2[ $rt_r['key'] ] = true;
}
ok( ! $rt_dup2, 'and the re-render still collides with nothing', implode( ', ', $rt_dup2 ) );

$rt_post3 = array();
foreach ( $rt_render2 as $rt_r ) {
	$rt_post3[ $rt_r['key'] ] = array(
		'label'  => $rt_r['fixed'] ? '' : $rt_r['label'],
		'p1'     => $rt_r['p1'],
		'p2'     => $rt_r['p2'],
		'p3'     => $rt_r['p3'],
		'remark' => $rt_r['remark'],
	);
}
save( $rt_row, $rt_post3 );
$rt_stored3 = bhela_bm_cost_stored_lines( $rt_row );
$rt_kept3 = 0;
foreach ( $rt_names as $rt_label => $rt_amt ) {
	foreach ( $rt_stored3 as $rt_line ) {
		if ( ( $rt_line['label'] ?? '' ) === $rt_label ) {
			$rt_kept3++;
		}
	}
}
ok( 5 === $rt_kept3, 'all five are still there after the round trip', (string) $rt_kept3 );

// Opening a sheet must not grow it five slots every time. The spare-slot count is
// steady, or a sheet visited ten times would carry fifty empty rows.
$rt_blanks_a = 0;
foreach ( bhela_bm_cost_lines( $rt_row ) as $rt_r ) {
	if ( ! $rt_r['fixed'] && 0 === $rt_r['sub'] && '' === $rt_r['label'] ) {
		$rt_blanks_a++;
	}
}
$rt_blanks_b = 0;
foreach ( bhela_bm_cost_lines( $rt_row ) as $rt_r ) {
	if ( ! $rt_r['fixed'] && 0 === $rt_r['sub'] && '' === $rt_r['label'] ) {
		$rt_blanks_b++;
	}
}
ok( $rt_blanks_a === $rt_blanks_b && $rt_blanks_a === bhela_bm_cost_extra_rows(),
	'the number of spare slots is steady across renders', $rt_blanks_a . ' / ' . $rt_blanks_b );

// The cap still bounds a crafted POST, and no longer does it in silence.
$rt_crafted = array();
for ( $rt_c = 0; $rt_c < bhela_bm_cost_max_custom_rows() + 12; $rt_c++ ) {
	$rt_crafted[ 'atk_' . $rt_c ] = array( 'label' => 'ZZ atk ' . $rt_c, 'p1' => 1 );
}
save( $rt_row, $rt_crafted );
$rt_atk = 0;
foreach ( bhela_bm_cost_stored_lines( $rt_row ) as $rt_k => $rt_line ) {
	if ( 0 === strpos( (string) $rt_k, 'atk_' ) ) {
		$rt_atk++;
	}
}
ok( $rt_atk === bhela_bm_cost_max_custom_rows(), 'the one-off cap holds against a crafted POST', (string) $rt_atk );
$rt_src = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/costs.php' );
ok( false !== strpos( $rt_src, '$dropped++' ) && false !== strpos( $rt_src, 'at its limit of' ),
	'and says so rather than truncating in silence — silent truncation looks exactly like the bug above' );

// The nonce is normalised before it is checked, like every other one in the plugin.
ok( false !== strpos( $rt_src, "wp_verify_nonce( sanitize_text_field( wp_unslash( \$_POST['bhela_bm_cost_nonce'] ) )" ),
	'the cost-sheet nonce goes through wp_unslash() before verification' );

bhela_test_delete( $rt_row );


echo "\n=== 8. an approved sheet is locked against more than the metabox ===\n";
// bhela_bm_cost_save() has always refused an approved sheet, and that covered the
// form. It covered nothing else: a direct update_post_meta(), trash, hard delete and
// quick edit all walked straight past it. That was a documented shortcoming while an
// approved sheet only fed a report — but the investor distribution now reads approved
// sheets and nothing else, so a sheet deletable from WP-CLI leaves profit declared
// owed to named people against a trip the books can no longer show.
$rt_lock = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ locked' ) );
save( $rt_lock, array( 'engine_fuel' => array( 'p1' => 7000 ) ) );
update_post_meta( $rt_lock, '_bhela_cost_status', 'approved' );

ok( bhela_bm_cost_locked( $rt_lock ), 'the sheet reports itself locked' );

// 1. A direct meta write.
$rt_total_was = (int) get_post_meta( $rt_lock, '_bhela_cost_total', true );
update_post_meta( $rt_lock, '_bhela_cost_total', 999999 );
ok( $rt_total_was === (int) get_post_meta( $rt_lock, '_bhela_cost_total', true ),
	'a direct update_post_meta() on the total is refused', (string) get_post_meta( $rt_lock, '_bhela_cost_total', true ) );
update_post_meta( $rt_lock, '_bhela_cost_earnings', 123456 );
ok( 123456 !== (int) get_post_meta( $rt_lock, '_bhela_cost_earnings', true ), 'and so is one on the earnings' );
update_post_meta( $rt_lock, '_bhela_cost_income', '{"cabin":1}' );
ok( array() === bhela_bm_cost_income( $rt_lock ), 'and one on the income heads' );
delete_post_meta( $rt_lock, '_bhela_cost_lines' );
ok( '' !== get_post_meta( $rt_lock, '_bhela_cost_lines', true ), 'deleting the lines is refused too' );

// 2. Trash and hard delete, as WP-CLI or a cron job would reach them — no is_admin()
//    anywhere in the path.
wp_trash_post( $rt_lock );
ok( 'trash' !== get_post_status( $rt_lock ), 'an approved sheet cannot be trashed' );
wp_delete_post( $rt_lock, true );
ok( 'bhela_cost' === get_post_type( $rt_lock ), 'nor hard-deleted — by anyone, administrator included' );

// 3. Quick edit and the delete links come off the row, so the list does not offer
//    something that would silently do nothing.
$rt_actions = apply_filters( 'post_row_actions', array( 'inline hide-if-no-js' => 'x', 'trash' => 'x', 'edit' => 'x' ), get_post( $rt_lock ) );
ok( ! isset( $rt_actions['inline hide-if-no-js'] ), 'quick edit is not offered on a locked sheet' );
ok( ! isset( $rt_actions['trash'] ), 'nor is Trash' );
ok( isset( $rt_actions['edit'] ), 'but the sheet can still be opened and read' );

// 4. The status itself is deliberately NOT guarded: unlocking is how an approved
//    sheet is legitimately reopened, and a lock that cannot be lifted is a trap.
update_post_meta( $rt_lock, '_bhela_cost_status', 'prepared' );
ok( 'prepared' === get_post_meta( $rt_lock, '_bhela_cost_status', true ), 'the status can still be changed, so unlock still works' );
ok( ! bhela_bm_cost_locked( $rt_lock ), 'and the sheet is editable again once it is' );
update_post_meta( $rt_lock, '_bhela_cost_total', 4242 );
ok( 4242 === (int) get_post_meta( $rt_lock, '_bhela_cost_total', true ), 'with its figures writable again', (string) get_post_meta( $rt_lock, '_bhela_cost_total', true ) );

// 5. The guard loads on every request, not behind is_admin() — the whole reason it
//    is in its own file. Asserted at source, because a test running in wp-admin
//    cannot tell the difference.
// php_strip_whitespace() drops the comments, so this asserts about the CODE. Over the
// raw file it failed on the comment that explains why there is no is_admin() here —
// a test that reads prose is testing the prose.
$rt_core = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/costs-core.php' );
ok( false === strpos( $rt_core, 'is_admin()' ), 'costs-core.php contains no is_admin() gate at all' );
$rt_boot = (string) file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/bhela-booking.php' );
ok( false !== strpos( $rt_boot, "includes/costs-core.php" ), 'and it is required unconditionally' );
// It must not depend on costs.php, which is admin-only — a front-end or WP-CLI
// request never loads that, which is exactly where the gaps were reachable from.
ok( false === strpos( $rt_core, 'bhela_bm_cost_status(' ), 'and it reads the meta directly rather than calling an admin-only helper' );

// The lock has to be lifted before the fixture can be cleaned up, which is the same
// dance bhela_test_delete() does for a closed stock month.
update_post_meta( $rt_lock, '_bhela_cost_status', 'draft' );
bhela_test_delete( $rt_lock );
ok( 'bhela_cost' !== get_post_type( $rt_lock ), 'an unlocked sheet deletes normally' );


echo "\n=== 9. the lock covers add_post_meta(), not only update ===\n";
// The gap the shipped §8 missed. It probed keys that already EXISTED, and
// update_post_meta() is caught either way because WordPress fires the update filter
// before it checks existence. add_post_meta() fires `add_post_metadata` and nothing
// else, so a locked key was writable while it was ABSENT — and _bhela_cost_income is
// absent on every sheet approved before income heads existed, which is exactly the
// meta Trip P&L and Revenue by Source read.
$rt_add = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ addmeta' ) );
save( $rt_add, array( 'engine_fuel' => array( 'p1' => 3000 ) ) );
update_post_meta( $rt_add, '_bhela_cost_status', 'approved' );

foreach ( bhela_bm_cost_locked_keys() as $rt_k ) {
	// Absent first — that is the only state add_post_meta() can reach.
	bhela_bm_cost_writing( true );
	delete_post_meta( $rt_add, $rt_k );
	bhela_bm_cost_writing( false );

	add_post_meta( $rt_add, $rt_k, 'ZZ forged' );
	ok( '' === get_post_meta( $rt_add, $rt_k, true ), "add_post_meta() on an absent $rt_k is refused",
		var_export( get_post_meta( $rt_add, $rt_k, true ), true ) );
}

// And the writer still works through the same filters.
bhela_bm_cost_meta_write( $rt_add, '_bhela_cost_total', 3000 );
ok( 3000 === (int) get_post_meta( $rt_add, '_bhela_cost_total', true ), 'the plugin\'s own write window still writes',
	(string) get_post_meta( $rt_add, '_bhela_cost_total', true ) );

// All three hooks, asserted at source: the omission was one missing line and looked
// exactly like the two that were there.
$rt_core3 = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/costs-core.php' );
foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $rt_hook ) {
	ok( false !== strpos( $rt_core3, "'$rt_hook'" ), "costs-core.php filters $rt_hook" );
}
// The same one-line omission was in the distribution lock, on the ledger.
$rt_dist3 = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/distribution-core.php' );
foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $rt_hook ) {
	ok( false !== strpos( $rt_dist3, "'$rt_hook'" ), "distribution-core.php filters $rt_hook" );
}
$rt_inv3 = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/inventory-core.php' );
foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $rt_hook ) {
	ok( false !== strpos( $rt_inv3, "'$rt_hook'" ), "inventory-core.php filters $rt_hook" );
}

// The filter runs on every meta write in the site, so it must reject a foreign key
// before it does any work.
ok( false !== strpos( $rt_core3, "strpos( \$key, '_bhela_cost_' )" ),
	'and it rejects a non-cost meta key before allocating anything' );

update_post_meta( $rt_add, '_bhela_cost_status', 'draft' );
bhela_test_delete( $rt_add );

echo "\n=== 10. a save with no income block cannot contradict the stored heads ===\n";
// The earnings figure and the income heads are the same number by design. A POST
// carrying no income block at all — a programmatic save, or a form cached from before
// the feature — used to leave the heads stored while taking the posted earnings, so
// Trip P&L showed heads summing to one figure beside a total that was another.
$rt_ni = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ noincome' ) );
save( $rt_ni, array( 'engine_fuel' => array( 'p1' => 1000 ) ), array( 'cabin' => '80000', 'food' => '9000' ) );
ok( 89000 === (int) get_post_meta( $rt_ni, '_bhela_cost_earnings', true ), 'the heads set the earnings',
	(string) get_post_meta( $rt_ni, '_bhela_cost_earnings', true ) );

// Now save with NO income key in the POST. save() posts earnings of 142400.
save( $rt_ni, array( 'engine_fuel' => array( 'p1' => 1000 ) ) );
ok( 89000 === bhela_bm_cost_income_total( $rt_ni ), 'the stored heads survive a POST that never mentioned them',
	(string) bhela_bm_cost_income_total( $rt_ni ) );
ok( 89000 === (int) get_post_meta( $rt_ni, '_bhela_cost_earnings', true ),
	'and the earnings still equal them, rather than the figure in the POST',
	(string) get_post_meta( $rt_ni, '_bhela_cost_earnings', true ) );

// Explicitly clearing them is still honoured — that is a person deciding.
save( $rt_ni, array( 'engine_fuel' => array( 'p1' => 1000 ) ), array( 'cabin' => '', 'food' => '' ) );
ok( 0 === bhela_bm_cost_income_total( $rt_ni ), 'clearing them explicitly still clears them' );
ok( 142400 === (int) get_post_meta( $rt_ni, '_bhela_cost_earnings', true ), 'and the earnings box governs again',
	(string) get_post_meta( $rt_ni, '_bhela_cost_earnings', true ) );

bhela_test_delete( $rt_ni );

echo "\n=== 11. the P&L list does not re-query a month per row ===\n";
// bhela_bm_trip_report() re-queries every sheet in the row's own month to work out
// the distribution share, so one call per row made the list O(n^2) — over a blank
// filter, which means every trip on record. The list does not show the share.
$rt_marks = array();
$rt_src2 = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/trip-report.php' );
ok( false !== strpos( $rt_src2, 'function bhela_bm_trip_rows' ), 'the list has its own cheap reader' );

// Measured, not inferred from a call count: what the finding was about is that the
// work per row grew with the number of rows. Build one sheet, count the queries the
// list costs; build four more, count again. Linear means the delta per sheet is
// roughly flat — quadratic means it climbs with every row added.
global $wpdb;
$rt_perf = array();
for ( $rt_n = 0; $rt_n < 5; $rt_n++ ) {
	$rt_p = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ perf ' . $rt_n ) );
	$rt_perf[] = $rt_p;
	save( $rt_p, array( 'engine_fuel' => array( 'p1' => 1000 + $rt_n ) ) );
	bhela_test_cost_meta( $rt_p, '_bhela_cost_trip_date', '2026-07-0' . ( $rt_n + 1 ) );
	bhela_test_cost_meta( $rt_p, '_bhela_cost_status', 'approved' );

	if ( 0 === $rt_n || 4 === $rt_n ) {
		$rt_before = $wpdb->num_queries;
		bhela_bm_trip_rows( '2026-07-01', '2026-07-31' );
		$rt_cost_q = $wpdb->num_queries - $rt_before;
		$rt_marks[ $rt_n ] = $rt_cost_q;
	}
}
// The MARGINAL cost per added row is the discriminator, not a multiple of the
// baseline. Measured both ways on this fixture: the cheap reader goes 2 -> 5 queries
// (0.75 per added row, and the batched meta cache is why it is under one), while the
// per-row bhela_bm_trip_report() version goes 4 -> 11 (1.75 per added row, because
// each row re-queries its own month). A ceiling of one query per added row separates
// them cleanly and survives an unrelated constant being added to either end — a
// multiple of the baseline does not: `11 < 4 * 6` passed happily.
$rt_marginal = ( $rt_marks[4] - $rt_marks[0] ) / 4;
ok( $rt_marginal <= 1, 'the list costs at most one query per added row',
	$rt_marks[0] . ' query(s) for 1 sheet, ' . $rt_marks[4] . ' for 5 (' . $rt_marginal . ' per added row)' );
foreach ( $rt_perf as $rt_p ) {
	bhela_test_cost_meta( $rt_p, '_bhela_cost_status', 'draft' );
	bhela_test_delete( $rt_p );
}

// The two readings have to agree, or the list and the detail tell different stories.
$rt_cmp = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ cmp' ) );
save( $rt_cmp, array( 'engine_fuel' => array( 'p1' => 12000 ) ), array( 'cabin' => '70000', 'food' => '5000' ) );
update_post_meta( $rt_cmp, '_bhela_cost_status', 'approved' );
$rt_list = bhela_bm_trip_rows( '2026-07-01', '2026-07-31' );
$rt_mine = null;
foreach ( $rt_list as $rt_r ) {
	if ( (int) $rt_r['id'] === (int) $rt_cmp ) {
		$rt_mine = $rt_r;
	}
}
$rt_full = bhela_bm_trip_report( $rt_cmp );
ok( null !== $rt_mine, 'the sheet appears in the list' );
if ( $rt_mine ) {
	ok( $rt_mine['earnings'] === $rt_full['earnings'], 'list and detail agree on revenue', $rt_mine['earnings'] . ' vs ' . $rt_full['earnings'] );
	ok( $rt_mine['cost'] === $rt_full['cost'], 'and on cost' );
	ok( $rt_mine['profit'] === $rt_full['profit'], 'and on profit' );
	ok( $rt_mine['sources'] === count( $rt_full['income'] ), 'and on the number of income sources' );
}
update_post_meta( $rt_cmp, '_bhela_cost_status', 'draft' );
bhela_test_delete( $rt_cmp );

echo "\n=== 12. a blank date filter really does mean every date ===\n";
// A sentinel window reads as unbounded and is not: '2000-01-01' silently drops a
// sheet dated earlier and a two-year ceiling drops a trip booked further out. §13.24
// again, wearing a hat. The bound now comes from the data.
$rt_old = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZ ancient' ) );
save( $rt_old, array( 'engine_fuel' => array( 'p1' => 500 ) ) );
bhela_test_cost_meta( $rt_old, '_bhela_cost_trip_date', '1998-03-04' );
ok( bhela_bm_trip_date_bound( 'min' ) <= '1998-03-04', 'the lower bound reaches a sheet older than any sentinel',
	bhela_bm_trip_date_bound( 'min' ) );
$rt_src3 = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/trip-report.php' );
ok( false === strpos( $rt_src3, "'2000-01-01'" ), 'and no hardcoded sentinel date is left in the file' );
ok( false === strpos( $rt_src3, "'+2 years'" ), 'nor a hardcoded ceiling' );
bhela_test_delete( $rt_old );

bhela_test_delete( $sheet );
bhela_test_done();
