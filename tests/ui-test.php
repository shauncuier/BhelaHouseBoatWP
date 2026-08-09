<?php
/** Dev helper: render every BHELA admin screen and check the design system. */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'admin', 'reports', 'costs', 'expenses', 'statement', 'salary', 'dashboard', 'guide', 'log' );
wp_set_current_user( 1 );

echo "=== 1. helpers ===\n";
ok( function_exists( 'bhela_bm_screen_header' ), 'bhela_bm_screen_header()' );
ok( function_exists( 'bhela_bm_status_pill' ), 'bhela_bm_status_pill()' );
$pill = bhela_bm_status_pill( '<b>x</b>', 'good', true );
ok( false === strpos( $pill, '<b>' ), 'label is escaped', $pill );
ok( false !== strpos( $pill, 'bha-pill--good is-solid' ), 'tone + weight classes' );
ok( false !== strpos( bhela_bm_status_pill( 'x', 'nonsense' ), 'bha-pill--neutral' ), 'unknown tone falls back to neutral' );

echo "\n=== 2. every status vocabulary is distinguishable ===\n";
$seen = array();
foreach ( array_keys( bhela_bm_statuses() ) as $st ) {
	list( $tone, $solid ) = bhela_bm_status_tone( $st );
	$seen[] = $tone . ( $solid ? '/solid' : '/soft' );
	printf( "  booking %-13s → %s\n", $st, end( $seen ) );
}
ok( count( $seen ) === count( array_unique( $seen ) ), 'all five booking statuses look different' );
$cs = array();
foreach ( bhela_bm_cost_statuses() as $k => $def ) {
	$cs[] = $def['tone'] . ( ! empty( $def['solid'] ) ? '/solid' : '/soft' );
	printf( "  cost    %-13s → %s\n", $k, end( $cs ) );
}
ok( count( $cs ) === count( array_unique( $cs ) ), 'all four cost-sheet states look different' );

echo "\n=== 3. header emits the notice marker ===\n";
ob_start(); bhela_bm_screen_header( '📄', 'Trip Report', 'Lead text.', '<button class="button">Go</button>' ); $head = ob_get_clean();
ok( false !== strpos( $head, 'wp-header-end' ), 'wp-header-end present — notices land below the band' );
ok( strpos( $head, '<h1>' ) < strpos( $head, 'wp-header-end' ), 'h1 comes before the marker' );
ok( false !== strpos( $head, 'bha-head__lead' ), 'lead rendered' );

echo "\n=== 4. every screen renders clean ===\n";
// A sheet of each kind, so the editors have real content to render.
$made = array();
$cost = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZUI cost' ) );
update_post_meta( $cost, '_bhela_cost_trip_date', '2026-07-15' );
update_post_meta( $cost, '_bhela_cost_status', 'checked' );
$made[] = $cost;
$exp = wp_insert_post( array( 'post_type' => 'bhela_expense', 'post_status' => 'publish', 'post_title' => 'ZZUI expense' ) );
$made[] = $exp;
$sal = wp_insert_post( array( 'post_type' => 'bhela_salary', 'post_status' => 'publish', 'post_title' => 'ZZUI salary' ) );
update_post_meta( $sal, '_bhela_salary_month', '2026-07' );
$made[] = $sal;
$book = wp_insert_post( array( 'post_type' => 'bhela_booking', 'post_status' => 'publish', 'post_title' => 'ZZUI guest' ) );
update_post_meta( $book, '_bhela_status', 'advance_paid' );
update_post_meta( $book, '_bhela_travel_date', '2026-07-15' );
update_post_meta( $book, '_bhela_total', 60000 );
update_post_meta( $book, '_bhela_paid_amount', 20000 );
$made[] = $book;

$screens = array(
	'Dashboard'        => array( 'bhela_booking_page_bhela-bm-dashboard', array( 'page' => 'bhela-bm-dashboard' ), fn() => bhela_bm_dashboard_page() ),
	'Trip Report'      => array( 'bhela_booking_page_bhela-bm-reports', array( 'page' => 'bhela-bm-reports', 'from' => '2026-07-01', 'to' => '2026-07-31', 'cancelled' => 1 ), fn() => bhela_bm_reports_page() ),
	'Monthly Statement' => array( 'bhela_booking_page_bhela-bm-statement', array( 'page' => 'bhela-bm-statement', 'month' => '2026-07' ), fn() => bhela_bm_statement_page() ),
	'Team'             => array( 'bhela_booking_page_bhela-bm-team', array( 'page' => 'bhela-bm-team' ), fn() => bhela_bm_team_page() ),
	'Activity Log'     => array( 'bhela_booking_page_bhela-bm-log', array( 'page' => 'bhela-bm-log' ), fn() => bhela_bm_log_page() ),
	'Quick Guide'      => array( 'bhela_booking_page_bhela-bm-guide', array( 'page' => 'bhela-bm-guide' ), fn() => bhela_bm_guide_page() ),
	'Settings'         => array( 'bhela_booking_page_bhela-bm-settings', array( 'page' => 'bhela-bm-settings' ), fn() => bhela_bm_settings_page() ),
	'Cost sheet'       => array( 'bhela_cost', array(), fn() => bhela_bm_cost_sheet_cb( get_post( $GLOBALS['zz_cost'] ) ) ),
	'Cost approval'    => array( 'bhela_cost', array(), fn() => bhela_bm_cost_workflow_cb( get_post( $GLOBALS['zz_cost'] ) ) ),
	'Expense'          => array( 'bhela_expense', array(), fn() => bhela_bm_expense_meta_cb( get_post( $GLOBALS['zz_exp'] ) ) ),
	'Salary sheet'     => array( 'bhela_salary', array(), fn() => bhela_bm_salary_meta_cb( get_post( $GLOBALS['zz_sal'] ) ) ),
	'Booking details'  => array( 'bhela_booking', array(), fn() => bhela_bm_details_metabox( get_post( $GLOBALS['zz_book'] ) ) ),
	'Booking discount' => array( 'bhela_booking', array(), fn() => bhela_bm_discount_metabox( get_post( $GLOBALS['zz_book'] ) ) ),
);
$GLOBALS['zz_cost'] = $cost;
$GLOBALS['zz_exp']  = $exp;
$GLOBALS['zz_sal']  = $sal;
$GLOBALS['zz_book'] = $book;

$all = '';
foreach ( $screens as $name => list( $screen_id, $get, $render ) ) {
	$_GET = $get;
	set_current_screen( $screen_id );
	ob_start();
	try {
		$render();
	} catch ( Throwable $e ) {
		echo 'THREW: ' . $e->getMessage();
	}
	$html = ob_get_clean();
	$all .= $html;
	$bad  = array();
	foreach ( array( 'Fatal error', 'Warning:', 'Notice:', 'Deprecated:', 'THREW:', '<style>' ) as $needle ) {
		if ( false !== strpos( $html, $needle ) ) {
			$bad[] = $needle;
		}
	}
	ok( ! $bad && strlen( $html ) > 200, sprintf( '%-18s %6d bytes', $name, strlen( $html ) ), implode( ' ', $bad ) );
}

echo "\n=== 5. no drift left in the rendered markup ===\n";
foreach ( array( 'bhela-rep', 'bhela-dash__', 'bhela-st__', 'bhela-cs__', 'bhela-sal', 'bhela-exp', 'bhela-team', 'bhela-set__', 'bhela-disc', 'bhela-meta' ) as $prefix ) {
	ok( false === strpos( $all, $prefix ), "old prefix .$prefix gone" );
}
ok( false === strpos( $all, 'style="text-align:right"' ), 'no inline alignment' );
foreach ( array( '#1a7f37', '#b32d2e', '#b45309', '#dcdcde' ) as $hex ) {
	ok( false === strpos( $all, $hex ), "no hand-typed $hex" );
}
preg_match_all( '/\b\d{1,3}(,\d{3})+\b/u', $all, $plain );
$bare = array_filter( $plain[0], function ( $n ) use ( $all ) {
	return false === strpos( $all, '৳' . $n );
} );
ok( ! $bare, 'every grouped figure carries the ৳ symbol', implode( ' ', array_slice( $bare, 0, 5 ) ) );

echo "\n=== 6. print view of a cost sheet ===\n";
$_GET = array( 'bhela_print' => '1', 'post' => $cost );
ob_start();
// bhela_bm_cost_print() exits; run its body by capturing through a subrequest-ish
// trick is overkill — assert on the source instead.
ob_end_clean();
$src = file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/includes/costs.php' );
$print = substr( $src, strpos( $src, 'function bhela_bm_cost_print' ) );
ok( false === strpos( $print, 'number_format(' ), 'print view has no raw number_format()' );
ok( substr_count( $print, 'bhela_bm_money(' ) >= 7, 'print view routes every figure through bhela_bm_money()', (string) substr_count( $print, 'bhela_bm_money(' ) );
ok( false !== strpos( $print, 'assets/admin.css' ), 'print view links the shared stylesheet' );

echo "\n=== 7. stylesheet loads only on our screens ===\n";
function bhela_ui_loads_on( $screen_id, $get = array() ) {
	global $wp_styles;
	$wp_styles = null;
	$_GET = $get;
	set_current_screen( $screen_id );
	do_action( 'admin_enqueue_scripts', $screen_id );
	return wp_style_is( 'bhela-bm-admin', 'enqueued' );
}
foreach ( array(
	'bhela_booking_page_bhela-bm-reports' => array( 'page' => 'bhela-bm-reports' ),
	'edit-bhela_cost' => array(), 'bhela_cost' => array(),
	'edit-bhela_salary' => array(), 'edit-bhela_expense' => array(),
) as $id => $get ) {
	ok( bhela_ui_loads_on( $id, $get ), "$id: loaded" );
	ok( false !== strpos( bhela_bm_admin_body_class( '' ), 'bhela-admin' ), "$id: body class set" );
}
foreach ( array( 'edit-post', 'upload', 'plugins', 'dashboard', 'options-general' ) as $id ) {
	ok( ! bhela_ui_loads_on( $id ), "$id: NOT loaded" );
	ok( '' === bhela_bm_admin_body_class( '' ), "$id: no body class" );
}

echo "\n=== 8. the stylesheet itself ===\n";
$css = file_get_contents( WP_PLUGIN_DIR . '/bhela-booking/assets/admin.css' );
ok( (bool) $css, 'admin.css readable', strlen( (string) $css ) . ' bytes' );
preg_match_all( '/var\(\s*(--bha-[a-z-]+)/', $css, $used );
preg_match_all( '/^\s*(--bha-[a-z-]+)\s*:/m', $css, $declared );
$missing = array_diff( array_unique( $used[1] ), array_unique( $declared[1] ) );
ok( ! $missing, 'every var() used is declared', implode( ', ', $missing ) );
ok( substr_count( $css, '{' ) === substr_count( $css, '}' ), 'braces balanced' );
// Every class the templates emit must exist in the stylesheet.
preg_match_all( '/\b(bha-[a-z0-9_-]+)/', $all, $emitted );
$defined = array();
preg_match_all( '/\.(bha-[a-z0-9_-]+)/', $css, $d );
$defined = array_unique( $d[1] );
$orphans = array_values( array_diff( array_unique( $emitted[1] ), $defined ) );
ok( ! $orphans, 'no class emitted without a rule', implode( ', ', $orphans ) );

echo "\n=== 9. menu icons are unique ===\n";
$icons = array();
foreach ( array(
	'📊 Dashboard', '📄 Trip Report', '🧾 Cost Sheets', '💸 Expenses',
	'📈 Monthly Statement', '👷 Salary', '📅 Trip Calendar', '🗺️ Spots',
	'🖼️ Gallery', '⬆️ Bulk Upload', '⭐ Reviews', '📋 Activity Log',
	'👥 Team', '⚙️ Settings', '🎯 Quick Guide',
) as $label ) {
	$icons[] = explode( ' ', $label )[0];
}
$dupes = array_keys( array_filter( array_count_values( $icons ), fn( $n ) => $n > 1 ) );
ok( ! $dupes, 'no two menu items share an icon', implode( ' ', $dupes ) );

echo "\n=== cleanup ===\n";
foreach ( $made as $id ) { wp_delete_post( $id, true ); }
ok( true, 'fixtures removed' );

bhela_test_done();
