<?php
/** Dev helper: rebuild July's staff salary sheet from the PDF. */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'reports', 'costs', 'expenses', 'statement', 'salary' );
wp_set_current_user( 1 );
$restore_staff = get_option( 'bhela_bm_staff', null );
$made = array();

// 13 approved trips so "trips completed" has something real to default from.
for ( $i = 1; $i <= 13; $i++ ) {
	$id = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZS trip ' . $i ) );
	$made[] = $id;
	update_post_meta( $id, '_bhela_cost_trip_date', sprintf( '2026-07-%02d', $i * 2 ) );
	update_post_meta( $id, '_bhela_cost_status', 'approved' );
	update_post_meta( $id, '_bhela_cost_header', wp_json_encode( array( 'total_guest' => '25' ) ) );
}

echo "=== 1. trips completed comes from the approved sheets ===\n";
ok( 13 === bhela_bm_salary_trip_count( '2026-07' ), '13 approved trips counted', (string) bhela_bm_salary_trip_count( '2026-07' ) );

// The roster exactly as the July salary sheet lists it.
$roster = array(
	'khairul' => array( 'name' => 'Khairul Kaka', 'designation' => 'Sukani',              'type' => 'trip',    'rate' => 3000, 'monthly' => 0,     'account' => '01704049838' ),
	'lipson'  => array( 'name' => 'Lipson',       'designation' => 'Driver',              'type' => 'trip',    'rate' => 3000, 'monthly' => 0,     'account' => '' ),
	'saimon'  => array( 'name' => 'Saimon',       'designation' => 'Chef',                'type' => 'trip',    'rate' => 3500, 'monthly' => 0,     'account' => '' ),
	'minhaj'  => array( 'name' => 'Minhaj',       'designation' => 'Chef Assistant',      'type' => 'trip',    'rate' => 2000, 'monthly' => 0,     'account' => '' ),
	'linkon'  => array( 'name' => 'Linkon',       'designation' => 'In-charge',           'type' => 'trip',    'rate' => 3000, 'monthly' => 0,     'account' => '' ),
	'uttom'   => array( 'name' => 'Uttom Shaha',  'designation' => 'Manager (Operation)', 'type' => 'both',    'rate' => 1000, 'monthly' => 20000, 'account' => '' ),
	'forhad'  => array( 'name' => 'Forhad',       'designation' => 'Supervisor',          'type' => 'trip',    'rate' => 1500, 'monthly' => 0,     'account' => '' ),
	'afsar'   => array( 'name' => 'Afsar',        'designation' => 'Marketing Executive', 'type' => 'trip',    'rate' => 1000, 'monthly' => 0,     'account' => '' ),
	'arif'    => array( 'name' => 'Arif',         'designation' => 'Service',             'type' => 'trip',    'rate' => 1500, 'monthly' => 0,     'account' => '' ),
	'salman'  => array( 'name' => 'Salman',       'designation' => 'Service',             'type' => 'trip',    'rate' => 1500, 'monthly' => 0,     'account' => '' ),
);
bhela_bm_save_staff( array_map( function ( $id, $r ) { return array_merge( $r, array( 'id' => $id ) ); }, array_keys( $roster ), $roster ) );

echo "\n=== 2. roster round-trips ===\n";
$staff = bhela_bm_staff();
ok( 10 === count( $staff ), '10 staff on the roster', (string) count( $staff ) );
ok( 3500 === ( $staff['saimon']['rate'] ?? 0 ), "chef's rate stored" );
ok( 'both' === ( $staff['uttom']['type'] ?? '' ), 'manager is trip + monthly' );

echo "\n=== 3. the sheet computes July's payroll ===\n";
$sheet = wp_insert_post( array( 'post_type' => 'bhela_salary', 'post_status' => 'publish', 'post_title' => 'ZZS salary' ) );
$made[] = $sheet;
update_post_meta( $sheet, '_bhela_salary_month', '2026-07' );

$rows = bhela_bm_salary_rows( $sheet, '2026-07' );
ok( 39000 === $rows['khairul']['sub'], 'Sukani 3000 x 13 = 39,000', number_format( $rows['khairul']['sub'] ) );
ok( 45500 === $rows['saimon']['sub'],  'Chef 3500 x 13 = 45,500',  number_format( $rows['saimon']['sub'] ) );
ok( 26000 === $rows['minhaj']['sub'],  'Chef Assistant 2000 x 13 = 26,000', number_format( $rows['minhaj']['sub'] ) );
ok( 19500 === $rows['arif']['sub'],    'Service 1500 x 13 = 19,500', number_format( $rows['arif']['sub'] ) );
ok( 13000 === $rows['afsar']['sub'],   'Marketing 1000 x 13 = 13,000', number_format( $rows['afsar']['sub'] ) );
// The manager: 1000 x 13 trip pay PLUS a 20,000 monthly, payable 33,000.
ok( 13000 === $rows['uttom']['sub'] && 20000 === $rows['uttom']['monthly'] && 33000 === $rows['uttom']['payable'],
	'Manager 13,000 trip + 20,000 monthly = 33,000 payable', number_format( $rows['uttom']['payable'] ) );

echo "\n=== 4. a staff member who missed trips ===\n";
// Forhad worked 8 of the 13.
$post_rows = array();
foreach ( $rows as $id => $r ) {
	$post_rows[ $id ] = array(
		'name' => $r['name'], 'designation' => $r['designation'], 'type' => $r['type'],
		'account' => $r['account'], 'rate' => $r['rate'], 'monthly' => $r['monthly'],
		'trips' => 'forhad' === $id ? 8 : $r['trips'],
		'advance' => 'uttom' === $id ? 20000 : 0,
		'settlement' => 'PAID', 'adjustment' => 'No Adjustment', 'verify' => '',
	);
}
$_POST = array( 'bhela_bm_salary_nonce' => wp_create_nonce( 'bhela_bm_salary_save' ), 'sal_month' => '2026-07', 'sal_rows' => $post_rows );
bhela_bm_salary_save( $sheet, get_post( $sheet ) );
$_POST = array();

$rows = bhela_bm_salary_rows( $sheet, '2026-07' );
ok( 8 === $rows['forhad']['trips'], 'Forhad kept at 8 trips' );
ok( 12000 === $rows['forhad']['sub'], 'Supervisor 1500 x 8 = 12,000', number_format( $rows['forhad']['sub'] ) );
ok( 13000 === $rows['uttom']['after'], 'Manager 33,000 less 20,000 advance = 13,000', number_format( $rows['uttom']['after'] ) );

echo "\n=== 4b. a cleared trips field still means 'all of them' ===\n";
// bhela_bm_salary_row() documents blank as "however many trips ran" and a typed 0
// as "none". The save handler cast it with (int), and `(int) ''` is 0 — so the blank
// case could not survive a save: whoever cleared the field got a crew member priced
// for no trips at all, silently, on a sheet that then deducted too little payroll.
$blank_rows = array();
foreach ( $rows as $id => $r ) {
	$blank_rows[ $id ] = array(
		'name' => $r['name'], 'designation' => $r['designation'], 'type' => $r['type'],
		'account' => $r['account'], 'rate' => $r['rate'], 'monthly' => $r['monthly'],
		'trips' => 'forhad' === $id ? '' : 0,   // one cleared, the rest explicitly none
		'advance' => 0, 'settlement' => '', 'adjustment' => '', 'verify' => '',
	);
}
$_POST = array( 'bhela_bm_salary_nonce' => wp_create_nonce( 'bhela_bm_salary_save' ), 'sal_month' => '2026-07', 'sal_rows' => $blank_rows );
bhela_bm_salary_save( $sheet, get_post( $sheet ) );
$_POST = array();

$stored = json_decode( (string) get_post_meta( $sheet, '_bhela_salary_rows', true ), true );
ok( '' === ( $stored['forhad']['trips'] ?? 'x' ), 'a cleared field is stored blank, not as 0',
	var_export( $stored['forhad']['trips'] ?? null, true ) );
ok( 0 === ( $stored['uttom']['trips'] ?? null ), 'and a typed 0 is still 0',
	var_export( $stored['uttom']['trips'] ?? null, true ) );

$blank_read = bhela_bm_salary_rows( $sheet, '2026-07' );
$month_trips = bhela_bm_salary_trip_count( '2026-07' );
ok( $month_trips === $blank_read['forhad']['trips'], 'blank reads back as the month\'s trip count',
	$blank_read['forhad']['trips'] . ' vs ' . $month_trips );
ok( 0 === $blank_read['uttom']['trips'], 'a typed 0 still means none' );
ok( 0 === $blank_read['uttom']['sub'], 'so nothing is owed per trip for that person' );

// Put section 4's figures back, since sections 5 onward assert on them.
$_POST = array( 'bhela_bm_salary_nonce' => wp_create_nonce( 'bhela_bm_salary_save' ), 'sal_month' => '2026-07', 'sal_rows' => $post_rows );
bhela_bm_salary_save( $sheet, get_post( $sheet ) );
$_POST = array();
$rows = bhela_bm_salary_rows( $sheet, '2026-07' );
ok( 8 === $rows['forhad']['trips'], 'section 4 figures restored', (string) $rows['forhad']['trips'] );

echo "\n=== 5. month total matches the PDF ===\n";
$t = bhela_bm_salary_totals( $rows );
printf( "  sub=%s monthly=%s payable=%s advance=%s after=%s\n",
	number_format( $t['sub'] ), number_format( $t['monthly'] ), number_format( $t['payable'] ),
	number_format( $t['advance'] ), number_format( $t['after'] ) );
// 39000+39000+45500+26000+39000+13000+12000+13000+19500+19500 = 265,500 trip pay
ok( 265500 === $t['sub'], 'trip pay total = 265,500', number_format( $t['sub'] ) );
ok( 285500 === $t['payable'], 'payable = 285,500 (incl. the 20,000 monthly)', number_format( $t['payable'] ) );
ok( 265500 === $t['after'], 'after the 20,000 advance = 265,500', number_format( $t['after'] ) );

echo "\n=== 6. a pay rise does not rewrite a paid month ===\n";
$raised = $roster;
$raised['saimon']['rate'] = 5000;
bhela_bm_save_staff( array_map( function ( $id, $r ) { return array_merge( $r, array( 'id' => $id ) ); }, array_keys( $raised ), $raised ) );
$rows_after = bhela_bm_salary_rows( $sheet, '2026-07' );
ok( 3500 === $rows_after['saimon']['rate'], 'saved sheet kept the old rate', (string) $rows_after['saimon']['rate'] );
ok( 45500 === $rows_after['saimon']['sub'], "…and the old sub-total", number_format( $rows_after['saimon']['sub'] ) );
$fresh = bhela_bm_salary_rows( 0, '2026-07' );
ok( 5000 === $fresh['saimon']['rate'], 'a NEW sheet picks up the new rate' );

echo "\n=== 6b. a NEW hire does not appear in a month already paid ===\n";
// Section 6 covers a pay RISE, which was always handled. A new PERSON was not: the
// sheet's rows were "whatever it holds, plus any roster member not on it yet", so
// hiring a monthly-salaried manager today added them to every saved sheet and
// dropped each of those months' gross profit by a full monthly salary — with nobody
// editing those months. Found by adding one manager and watching July move from
// ৳558,500 to ৳583,500.
// Measured with bhela_bm_salary_totals() over the SAVED rows — which is exactly the
// quantity bhela_bm_salary_month_total() sums per sheet. It cannot be measured through
// month_total() here: bhela_bm_salary_save() renames the sheet to "Salary - July 2026",
// and bhela_test_isolate() scopes queries to `post_title LIKE 'ZZ%'`, so the fixture is
// invisible to that query and it would answer 0 no matter what the fix did.
$saved_payable = function ( $sheet ) {
	return (int) bhela_bm_salary_totals( bhela_bm_salary_rows( $sheet, '2026-07', null, false ) )['payable'];
};
$before_total = $saved_payable( $sheet );
$before_rows  = count( bhela_bm_salary_rows( $sheet, '2026-07', null, false ) );
ok( $before_total > 0, 'July has a payroll figure to protect', number_format( $before_total ) );

$hired = $left ?? $raised;
$hired['zz_newmanager'] = array(
	'name' => 'ZZ Late Hire', 'designation' => 'Operations Manager',
	'type' => 'monthly', 'rate' => 0, 'monthly' => 25000, 'account' => 'Bank',
);
bhela_bm_save_staff( array_map(
	function ( $id, $r ) {
		return array_merge( $r, array( 'id' => $id ) );
	},
	array_keys( $hired ),
	$hired
) );
ok( isset( bhela_bm_staff()['zz_newmanager'] ), 'the new hire is on the roster' );

// The money must not budge.
ok( $before_total === $saved_payable( $sheet ),
	'July payroll is unchanged by a hire made afterwards',
	number_format( $before_total ) . ' -> ' . number_format( $saved_payable( $sheet ) ) );
$saved_rows = bhela_bm_salary_rows( $sheet, '2026-07', null, false );
ok( $before_rows === count( $saved_rows ), 'and the saved sheet still holds the same people', $before_rows . ' -> ' . count( $saved_rows ) );
ok( ! isset( $saved_rows['zz_newmanager'] ), 'the new hire is not among them' );

// The statement is the figure that matters, so check it directly too.


// But the FORM must still show them, or there is no way to add someone mid-month.
$form_rows = bhela_bm_salary_rows( $sheet, '2026-07' );
ok( isset( $form_rows['zz_newmanager'] ), 'the edit screen still offers the new hire' );
ok( empty( $form_rows['zz_newmanager']['saved'] ), 'flagged as not on the sheet yet' );
ok( ! empty( $form_rows['saimon']['saved'] ), 'while a row that IS on the sheet is not flagged' );
$sheet_html = '';
if ( function_exists( 'bhela_bm_salary_meta_cb' ) ) {
	ob_start();
	bhela_bm_salary_meta_cb( get_post( $sheet ) );
	$sheet_html = (string) ob_get_clean();
}
ok( false !== strpos( $sheet_html, 'not on this sheet yet' ), 'and the screen says so in words' );

// Saving the sheet is what commits them — then, and only then, the month changes.
$with_hire = array();
foreach ( $form_rows as $id => $r ) {
	$with_hire[ $id ] = array(
		'name' => $r['name'], 'designation' => $r['designation'], 'type' => $r['type'],
		'account' => $r['account'], 'rate' => $r['rate'], 'monthly' => $r['monthly'],
		'trips' => $r['trips'], 'advance' => $r['advance'],
		'settlement' => $r['settlement'], 'adjustment' => $r['adjustment'], 'verify' => $r['verify'],
	);
}
$_POST = array( 'bhela_bm_salary_nonce' => wp_create_nonce( 'bhela_bm_salary_save' ), 'sal_month' => '2026-07', 'sal_rows' => $with_hire );
bhela_bm_salary_save( $sheet, get_post( $sheet ) );
$_POST = array();
ok( $before_total + 25000 === $saved_payable( $sheet ),
	'once saved, the hire is counted — deliberately, by someone',
	number_format( $saved_payable( $sheet ) ) );
ok( (int) get_post_meta( $sheet, '_bhela_salary_total', true ) === $saved_payable( $sheet ),
	'and the total stamped on the sheet is the same figure the statement would deduct',
	get_post_meta( $sheet, '_bhela_salary_total', true ) . ' vs ' . $saved_payable( $sheet ) );

echo "\n=== 7. someone who leaves ===\n";
$left = $raised;
$left['forhad']['retired'] = 1;
bhela_bm_save_staff( array_map( function ( $id, $r ) { return array_merge( $r, array( 'id' => $id ) ); }, array_keys( $left ), $left ) );
ok( ! isset( bhela_bm_staff()['forhad'] ), 'off the active roster' );
ok( isset( bhela_bm_salary_rows( $sheet, '2026-07' )['forhad'] ), 'still on the month they were paid for' );
ok( ! isset( bhela_bm_salary_rows( 0, '2026-07' )['forhad'] ), 'absent from a new sheet' );

echo "\n=== 8. permissions ===\n";
$saved_override = get_option( 'bhela_bm_role_perms', array() );
delete_option( 'bhela_bm_role_perms' );
bhela_bm_install_roles();
ok( get_role( 'bhela_manager' )->has_cap( 'edit_bhela_salaries' ), 'default: manager can run payroll' );
ok( ! get_role( 'bhela_booking_staff' )->has_cap( 'edit_bhela_salaries' ), 'booking staff cannot — pay rates are visible there' );
ok( ! get_role( 'bhela_cost_preparer' )->has_cap( 'edit_bhela_salaries' ), 'cost preparer cannot' );
if ( $saved_override ) { update_option( 'bhela_bm_role_perms', $saved_override ); bhela_bm_install_roles(); }

echo "\n=== 8b. a month with nothing approved yet says so ===\n";
// Trip pay is rate x trips and the count comes from approved cost sheets, so
// before any are approved every trip-based crew member silently reads zero. A
// sheet printed in that state underpays the whole crew.
$empty = wp_insert_post( array( 'post_type' => 'bhela_salary', 'post_status' => 'publish', 'post_title' => 'ZZS empty month' ) );
$made[] = $empty;
update_post_meta( $empty, '_bhela_salary_month', '2026-12' );
ok( 0 === bhela_bm_salary_trip_count( '2026-12' ), 'no approved sheets in that month', (string) bhela_bm_salary_trip_count( '2026-12' ) );

$_GET = array();
ob_start(); bhela_bm_salary_meta_cb( get_post( $empty ) ); $warn = ob_get_clean();
ok( false !== strpos( $warn, 'No approved cost sheets for this month yet' ),
	'the sheet warns before anyone pays from it' );
ok( false !== strpos( $warn, 'bha-callout--attention' ), 'and it is styled as something to act on' );

// It must stay quiet on a month that does have trips, or it becomes noise
// people learn to scroll past.
ok( 13 === bhela_bm_salary_trip_count( '2026-07' ), 'July still has its 13 approved trips', (string) bhela_bm_salary_trip_count( '2026-07' ) );
ob_start(); bhela_bm_salary_meta_cb( get_post( $sheet ) ); $ok_month = ob_get_clean();
ok( false === strpos( $ok_month, 'No approved cost sheets for this month yet' ),
	'and stays quiet on a month that has them' );

echo "\n=== 9. screen renders ===\n";
ob_start(); bhela_bm_salary_meta_cb( get_post( $sheet ) ); $html = ob_get_clean();
foreach ( array( 'Fatal error', 'Warning:', 'Notice:' ) as $bad ) { ok( false === strpos( $html, $bad ), "no '$bad'" ); }
ok( false !== strpos( $html, 'Khairul Kaka' ), 'renders staff names' );
ok( false !== strpos( $html, 'sal_rows' ), 'renders editable fields' );

echo "\n=== cleanup ===\n";
foreach ( $made as $id ) { bhela_test_delete( $id ); }
if ( is_array( $restore_staff ) ) { update_option( 'bhela_bm_staff', $restore_staff ); } else { delete_option( 'bhela_bm_staff' ); }
ok( true, 'restored' );

bhela_test_done();
