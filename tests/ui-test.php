<?php
/** Dev helper: render every BHELA admin screen and check the design system. */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles', 'admin', 'reports', 'costs', 'expenses', 'statement', 'yearly', 'salary', 'dashboard', 'guide', 'log', 'audit', 'inventory-core', 'inventory', 'inventory-import' );
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
$iv = array();
foreach ( bhela_bm_inv_statuses() as $k => $def ) {
	$iv[] = $def['tone'] . ( ! empty( $def['solid'] ) ? '/solid' : '/soft' );
	printf( "  stock   %-13s → %s\n", $k, end( $iv ) );
}
ok( count( $iv ) === count( array_unique( $iv ) ), 'all five stock-sheet states look different' );

echo "\n=== 3. header emits the notice marker ===\n";
ob_start(); bhela_bm_screen_header( '📄', 'Trip Report', 'Lead text.', '<button class="button">Go</button>' ); $head = ob_get_clean();
ok( false !== strpos( $head, 'wp-header-end' ), 'wp-header-end present — notices land below the band' );
ok( strpos( $head, '<h1>' ) < strpos( $head, 'wp-header-end' ), 'h1 comes before the marker' );
ok( false !== strpos( $head, 'bha-head__lead' ), 'lead rendered' );

echo "\n=== 4. every screen renders clean ===\n";
// A sheet of each kind, so the editors have real content to render.
$made = array();
$cost = wp_insert_post( array( 'post_type' => 'bhela_cost', 'post_status' => 'publish', 'post_title' => 'ZZUI cost' ) );
bhela_test_cost_meta( $cost, '_bhela_cost_trip_date', '2026-07-15' );
bhela_test_cost_meta( $cost, '_bhela_cost_status', 'checked' );
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

// An item and a month, so the register's screens have something real to draw.
// The line deliberately does NOT reconcile, so the variance flag renders too.
bhela_bm_audit_install();
$item = wp_insert_post( array( 'post_type' => 'bhela_inv_item', 'post_status' => 'publish', 'post_title' => 'ZZUI life jacket' ) );
update_post_meta( $item, '_bhela_inv_kind', 'asset' );
update_post_meta( $item, '_bhela_inv_cat', 'safety_eq' );
update_post_meta( $item, '_bhela_inv_code', 'BHELA-SAF-9999' );
update_post_meta( $item, '_bhela_inv_location', 'upper' );
update_post_meta( $item, '_bhela_inv_rate', 1200 );
$made[] = $item;
$period = bhela_bm_inv_period_id( '2026-07', true );
$made[] = $period;
$ikey = bhela_bm_inv_line_key( $item );
bhela_bm_inv_meta_write( $period, '_bhela_inv_baseline', 1 );
bhela_bm_inv_meta_write( $period, '_bhela_inv_opening', wp_json_encode( array( $ikey => 25 ), JSON_FORCE_OBJECT ) );
bhela_bm_inv_write_lines( $period, array( $ikey => array_merge( bhela_bm_inv_blank_line(), array(
	'open' => 25, 'add' => 10, 'good' => 20, 'dam' => 14, 'count' => 34, 'rate' => 1200,
) ) ) );

$GLOBALS['zz_cost'] = $cost;

$screens = array(
	'Dashboard'        => array( 'bhela_booking_page_bhela-bm-dashboard', array( 'page' => 'bhela-bm-dashboard' ), fn() => bhela_bm_dashboard_page() ),
	'Trip Report'      => array( 'bhela_booking_page_bhela-bm-reports', array( 'page' => 'bhela-bm-reports', 'from' => '2026-07-01', 'to' => '2026-07-31', 'cancelled' => 1 ), fn() => bhela_bm_reports_page() ),
	'Monthly Statement' => array( 'toplevel_page_bhela-bm-statement', array( 'page' => 'bhela-bm-statement', 'month' => '2026-07' ), fn() => bhela_bm_statement_page() ),
	// Was missing from this list entirely, which is how a column could be added to
	// its table without anything checking the row widths still lined up.
	'Yearly Report'    => array( 'accounts_page_bhela-bm-yearly', array( 'page' => 'bhela-bm-yearly', 'year' => '2026' ), fn() => bhela_bm_yearly_page() ),
	'Team'             => array( 'setup_page_bhela-bm-team', array( 'page' => 'bhela-bm-team' ), fn() => bhela_bm_team_page() ),
	'Activity Log'     => array( 'setup_page_bhela-bm-log', array( 'page' => 'bhela-bm-log' ), fn() => bhela_bm_log_page() ),
	'Quick Guide'      => array( 'setup_page_bhela-bm-guide', array( 'page' => 'bhela-bm-guide' ), fn() => bhela_bm_guide_page() ),
	'Settings'         => array( 'toplevel_page_bhela-bm-settings', array( 'page' => 'bhela-bm-settings' ), fn() => bhela_bm_settings_page() ),
	'Cost sheet'       => array( 'bhela_cost', array(), fn() => bhela_bm_cost_sheet_cb( get_post( $GLOBALS['zz_cost'] ) ) ),
	'Cost approval'    => array( 'bhela_cost', array(), fn() => bhela_bm_cost_workflow_cb( get_post( $GLOBALS['zz_cost'] ) ) ),
	'Expense'          => array( 'bhela_expense', array(), fn() => bhela_bm_expense_meta_cb( get_post( $GLOBALS['zz_exp'] ) ) ),
	'Salary sheet'     => array( 'bhela_salary', array(), fn() => bhela_bm_salary_meta_cb( get_post( $GLOBALS['zz_sal'] ) ) ),
	'Booking details'  => array( 'bhela_booking', array(), fn() => bhela_bm_details_metabox( get_post( $GLOBALS['zz_book'] ) ) ),
	'Booking discount' => array( 'bhela_booking', array(), fn() => bhela_bm_discount_metabox( get_post( $GLOBALS['zz_book'] ) ) ),
	'Monthly Stock'    => array( 'toplevel_page_bhela-bm-inv-month', array( 'page' => 'bhela-bm-inv-month', 'month' => '2026-07' ), fn() => bhela_bm_inv_month_page() ),
	'Inventory Report' => array( 'store_page_bhela-bm-inv-report', array( 'page' => 'bhela-bm-inv-report', 'month' => '2026-07' ), fn() => bhela_bm_inv_report_page() ),
	'Asset Report'     => array( 'store_page_bhela-bm-inv-assets', array( 'page' => 'bhela-bm-inv-assets', 'month' => '2026-07' ), fn() => bhela_bm_inv_asset_page() ),
	'CSV Import'       => array( 'store_page_bhela-bm-inv-import', array( 'page' => 'bhela-bm-inv-import' ), fn() => bhela_bm_inv_import_page() ),
	'Audit Trail'      => array( 'store_page_bhela-bm-audit', array( 'page' => 'bhela-bm-audit' ), fn() => bhela_bm_audit_page() ),
	'Item editor'      => array( 'bhela_inv_item', array(), fn() => bhela_bm_inv_item_meta_cb( get_post( $GLOBALS['zz_item'] ) ) ),
	// The investor chain shipped without a single one of its screens in this list,
	// so nothing was checking they render clean, carry the taka on every figure or
	// keep their columns aligned. That is exactly the gap that let a misaligned
	// header ship on fourteen tables.
	'Investor Dash'    => array( 'investors_page_bhela-bm-investor-dash', array( 'page' => 'bhela-bm-investor-dash' ), fn() => bhela_bm_investor_dash_page() ),
	'Distribution'     => array( 'toplevel_page_bhela-bm-dist', array( 'page' => 'bhela-bm-dist', 'month' => '2026-07' ), fn() => bhela_bm_dist_page() ),
	'Investor Report'  => array( 'investors_page_bhela-bm-investor-report', array( 'page' => 'bhela-bm-investor-report' ), fn() => bhela_bm_investor_report_page() ),
	'Funds'            => array( 'investors_page_bhela-bm-funds', array( 'page' => 'bhela-bm-funds' ), fn() => bhela_bm_funds_page() ),
	'Cash Flow'        => array( 'investors_page_bhela-bm-cashflow', array( 'page' => 'bhela-bm-cashflow', 'from' => '2026-07-01', 'to' => '2026-07-31' ), fn() => bhela_bm_cashflow_page() ),
	'Trip P&L list'    => array( 'accounts_page_bhela-bm-trip-pl', array( 'page' => 'bhela-bm-trip-pl' ), fn() => bhela_bm_trip_pl_page() ),
	'Trip P&L one'     => array( 'accounts_page_bhela-bm-trip-pl', array( 'page' => 'bhela-bm-trip-pl', 'sheet' => $GLOBALS['zz_cost'] ), fn() => bhela_bm_trip_pl_page() ),
	'Valuation'        => array( 'investors_page_bhela-bm-valuation', array( 'page' => 'bhela-bm-valuation' ), fn() => bhela_bm_valuation_page() ),
	'Share Issue'      => array( 'investors_page_bhela-bm-share-issue', array( 'page' => 'bhela-bm-share-issue', 'target' => '1000000' ), fn() => bhela_bm_share_issue_page() ),
	'Revenue'          => array( 'accounts_page_bhela-bm-revenue', array( 'page' => 'bhela-bm-revenue', 'period' => 'month' ), fn() => bhela_bm_revenue_page() ),
);
$GLOBALS['zz_exp']  = $exp;
$GLOBALS['zz_sal']  = $sal;
$GLOBALS['zz_book'] = $book;
$GLOBALS['zz_item'] = $item;

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
// Scan the rendered text, not the markup. Over raw HTML a figure whose leading
// digit sits in its own tag — "<strong>1</strong>,064,000" — cannot match from
// the 1, so the pattern matches "064,000" instead and reports a symbol-less
// figure that no reader ever sees. Stripping tags first tests what is actually
// on screen, which is what the assertion is about.
// A .bha-plain figure is a count, not a sum — the audit trail's "1,021 events
// recorded" is the case that forced this. Drop those before scanning, so the
// assertion keeps meaning "every money figure" rather than "every figure".
$money_html = preg_replace( '#<span class="bha-plain">.*?</span>#u', '', $all );
$text       = wp_strip_all_tags( $money_html );
preg_match_all( '/(?<![\d,])\d{1,3}(?:,\d{3})+(?![\d,])/u', $text, $plain );
$bare = array_filter( $plain[0], function ( $n ) use ( $text ) {
	return false === strpos( $text, '৳' . $n );
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

// One component, one primary rule. Reusing a name silently redefines whatever
// held it — `.bha-bar` was the filter bar, got reused for a chart bar, and
// flattened the filter on four screens into a 10px teal strip. Later rule
// wins and nothing warns you. Overrides inside @media are legitimate, so the
// media blocks come out before counting.
$base  = preg_replace( '!/\*.*?\*/!s', '', $css );
$depth = 0;
$flat  = '';
for ( $i = 0; $i < strlen( $base ); $i++ ) {
	if ( 0 === $depth && preg_match( '/\G@media[^{]*\{/', $base, $mm, 0, $i ) ) {
		$i    += strlen( $mm[0] ) - 1;
		$depth = 1;
		continue;
	}
	if ( $depth > 0 ) {
		if ( '{' === $base[ $i ] ) { $depth++; }
		if ( '}' === $base[ $i ] ) { $depth--; }
		continue;
	}
	$flat .= $base[ $i ];
}
preg_match_all( '/([^{}]+)\{/', $flat, $sels );
$primary = array();
foreach ( $sels[1] as $sel ) {
	$sel = trim( $sel );
	if ( preg_match( '/^\.(bha-[a-z0-9_-]+)$/', $sel ) ) {
		$primary[ $sel ] = ( $primary[ $sel ] ?? 0 ) + 1;
	}
}
$collisions = array_keys( array_filter( $primary, fn( $n ) => $n > 1 ) );
ok( ! $collisions, sprintf( 'each of the %d components is defined once', count( $primary ) ), implode( ', ', $collisions ) );

echo "\n=== 8b. the taka sign has a font that can draw it ===\n";
// wp-admin's stack has no U+09F3, so Chrome substitutes a narrow face and ৳
// renders about a third thinner than the digits beside it. Every surface that
// prints money has to name a Bengali-capable font.
ok( false !== strpos( $css, '--bha-font-money' ), 'the money font token exists' );
ok( preg_match( '/@font-face\s*\{[^}]*local\(\s*"Nirmala UI"\s*\)/si', $css ),
	'the custom face sources Nirmala UI locally — nothing is downloaded' );

// Applied across the whole scope rather than per component. Tagging the ten
// money classes missed the list tables, whose money columns are plain <td>s —
// Cost Sheets rendered a ৳3,318,506 total with the bad glyph while the cards
// above it were right. Assert the scope rule, not the components.
preg_match_all( '/([^{}]*)\{\s*font-family:\s*var\(--bha-font-money\)[^}]*\}/s', $css, $applied );
$scope = implode( ' ', $applied[1] );
ok( false !== strpos( $scope, '.bhela-admin,' ) || preg_match( '/\.bhela-admin\s*,/', $scope ),
	'the money font is applied to the whole scope, not per component' );
foreach ( array( 'input', 'select', 'textarea', 'button' ) as $ctl ) {
	ok( false !== strpos( $scope, '.bhela-admin ' . $ctl ), "…and to $ctl, which does not inherit it" );
}
// The rest of the stack must stay byte-identical to wp-admin's, which is what
// makes a scope-wide font-family safe.
// \s+ rather than a literal newline: this file and admin.css do not necessarily
// share line endings, and a regex that embeds one is asserting about CRLF rather
// than about the font stack. It failed exactly that way once.
// A money column's HEADING must be right-aligned too. `.bha-num` alone loses to
// WordPress's own `.widefat th { text-align: left }`, so for a long time every one of
// the plugin's ~191 numeric cells sat under a left-aligned label — the table read as
// two halves that had nothing to do with each other.
ok( (bool) preg_match( '/\.bhela-admin table\.widefat th\.bha-num[^{]*\{[^}]*text-align:\s*right/s', $css ),
	'a numeric column heading is right-aligned, at core’s specificity' );

ok( preg_match( '/--bha-font-money:[^;]*-apple-system,\s+BlinkMacSystemFont,\s+"Segoe UI",\s+Roboto,\s+Oxygen-Sans,\s+Ubuntu,\s+Cantarell,\s+"Helvetica Neue",\s+sans-serif/s', $css ),
	'and the stack behind it matches wp-admin exactly' );
ok( preg_match( '/@font-face\s*\{[^}]*unicode-range:\s*U\+09F3/si', $css ),
	'the custom face claims only U+09F3, so nothing else can change' );

// The three surfaces outside this stylesheet that also render taka.
$plugin_dir = WP_PLUGIN_DIR . '/bhela-booking';
foreach ( array(
	'the cost-sheet print view' => array( $plugin_dir . '/assets/admin.css', '.bha-doc' ),
	'the customer emails'       => array( $plugin_dir . '/includes/emails.php', '<body' ),
	'the invoice'               => array( $plugin_dir . '/templates/invoice.php', 'body {' ),
) as $what => list( $file, $needle ) ) {
	$body = (string) file_get_contents( $file );
	ok( false !== strpos( $body, 'Nirmala UI' ), "$what names a font that can draw ৳" );
}
// The print view inherits the token rather than restating a bare stack.
ok( false === strpos( $css, 'font-family: -apple-system' ),
	'no bare Segoe-only stack left in admin.css' );

/* =========================================================
 * 9. THE MENU, READ FROM THE MENU
 *
 * This section used to be a hardcoded list of 22 labels checked against itself,
 * which asserted nothing: it had already drifted from the code, claiming emoji
 * for Trip Calendar, Activity Log and Settings that none of them registered.
 * Everything below fires admin_menu and reads what actually came out.
 * ========================================================= */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * Build the menu as one user sees it.
 *
 * The globals are cleared first because admin_menu is not idempotent — firing it
 * twice onto a populated $submenu registers every row again. Clearing makes each
 * pass a clean build, which is what lets this run once per role.
 *
 * $menu holds no CPT top-levels here: WordPress creates those in
 * wp-admin/menu.php, which is a file, not an action. So assertions about
 * top-level presence below cover the three NEW menus only, and Bookings is
 * checked through its submenu instead.
 */
function zz_menu( $user_id ) {
	// Via 0 deliberately: wp_set_current_user() returns the cached WP_User when the
	// id has not changed, so re-setting the same account after set_role() would keep
	// the old capabilities and every role would report identical menus.
	wp_set_current_user( 0 );
	clean_user_cache( $user_id );
	wp_set_current_user( $user_id );
	$GLOBALS['menu'] = array();
	$GLOBALS['submenu'] = array();
	$GLOBALS['admin_page_hooks'] = array();
	do_action( 'admin_menu', '' );
	return array( $GLOBALS['menu'], $GLOBALS['submenu'], $GLOBALS['admin_page_hooks'] );
}

/** First codepoint of a menu label, or '' when it opens with a letter or digit. */
function zz_icon( $label ) {
	$label = trim( wp_strip_all_tags( (string) $label ) );
	$first = preg_split( '//u', $label, -1, PREG_SPLIT_NO_EMPTY )[0] ?? '';
	return preg_match( '/[\p{L}\p{N}]/u', $first ) ? '' : $first;
}

echo "\n=== 9. the four menus ===\n";

$admin_id = get_current_user_id();
list( $m, $sub, $hooks ) = zz_menu( $admin_id );

$bookings = 'edit.php?post_type=bhela_booking';
$tops     = wp_list_pluck( $m, 2 );
foreach ( array( 'bhela-bm-statement' => 'Accounts', 'bhela-bm-inv-month' => 'Store', 'bhela-bm-settings' => 'Setup' ) as $slug => $title ) {
	ok( in_array( $slug, $tops, true ), "$title registers as a top-level menu" );
	// An emoji in a top-level title would make sanitize_title() percent-encode it
	// and every child's screen id with it, so the hook is asserted, not assumed.
	ok( strtolower( $title ) === ( $hooks[ $slug ] ?? '' ),
		"$title hook is '" . strtolower( $title ) . "', not percent-encoded", $hooks[ $slug ] ?? 'missing' );
}
ok( ! empty( $sub[ $bookings ] ), 'Bookings keeps its own rows' );

// _wp_menu_output() takes a top-level's href from its FIRST submenu row, not from
// the parent's own slug — so reordering a group silently moves where its menu goes.
// Caught in the browser: with Dashboard listed first, clicking "Bookings" opened
// admin.php?page=bhela-bm-dashboard rather than the booking list.
foreach ( array(
	'bookings' => 'edit.php?post_type=bhela_booking',
	'accounts' => 'edit.php?post_type=bhela_cost',
	'store'    => 'edit.php?post_type=bhela_inv_item',
	'setup'    => 'bhela-bm-settings',
) as $group => $expected ) {
	ok( bhela_bm_menu_landing( $group ) === $expected, "clicking $group opens $expected",
		'declared: ' . bhela_bm_menu_landing( $group ) );
	// And what the layout declares is what WordPress will actually use. Skipped for
	// Bookings: All Bookings and Add New are added by wp-admin/menu.php, a file rather
	// than an action, so they are absent here and the first row LOOKS like Dashboard.
	// Verified in a real request instead — that is where the bug showed up.
	if ( 'bookings' === $group ) {
		continue;
	}
	$first = $sub[ bhela_bm_menu_groups()[ $group ]['slug'] ][0][2] ?? '';
	ok( $first === $expected, "$group's first rendered row is $expected", "rendered: $first" );
}

// The wall of 22 is the thing this change set out to fix.
$counts = array();
foreach ( array( $bookings, 'bhela-bm-statement', 'bhela-bm-inv-month', 'bhela-bm-settings' ) as $p ) {
	$counts[ $p ] = count( $sub[ $p ] ?? array() );
}
ok( max( $counts ) <= 8, 'no menu holds more than 8 rows', 'worst: ' . max( $counts ) );

// The live duplication bug: bhela_bm_inv_menu() added the stock screens under
// Bookings unconditionally while the standalone menu removed only the CPT row, so
// a storekeeper saw Monthly Stock, Inventory Report and Asset Report twice.
$where = array();
foreach ( $sub as $parent => $rows ) {
	foreach ( $rows as $r ) {
		if ( isset( $r[2] ) && 0 === strpos( $r[2], 'bhela-bm-' ) ) {
			$where[ $r[2] ][ $parent ] = true;
		}
	}
}
$twice = array();
foreach ( $where as $slug => $parents ) {
	if ( count( $parents ) > 1 ) {
		$twice[] = $slug . ' (' . implode( ' + ', array_keys( $parents ) ) . ')';
	}
}
ok( ! $twice, 'no page appears under two parents', implode( ', ', $twice ) );

// Same slug listed twice under ONE parent — WordPress inserts a row for the
// parent itself when the first child registered has a different slug.
$dupe_rows = array();
foreach ( $sub as $parent => $rows ) {
	$seen = array();
	foreach ( $rows as $r ) {
		if ( isset( $seen[ $r[2] ] ) ) {
			$dupe_rows[] = $parent . ' / ' . $r[2];
		}
		$seen[ $r[2] ] = true;
	}
}
ok( ! $dupe_rows, 'no parent lists the same slug twice', implode( ', ', $dupe_rows ) );

echo "\n=== 9b. menu icons, as registered ===\n";
$icons = array();
$plain = array();
foreach ( array( $bookings, 'bhela-bm-statement', 'bhela-bm-inv-month', 'bhela-bm-settings' ) as $p ) {
	foreach ( $sub[ $p ] ?? array() as $r ) {
		$label = trim( wp_strip_all_tags( $r[0] ) );
		// Core's own two rows on the Bookings menu (All Bookings, Add New) are not
		// this plugin's to decorate.
		if ( in_array( $r[2], array( 'edit.php?post_type=bhela_booking', 'post-new.php?post_type=bhela_booking' ), true ) ) {
			continue;
		}
		$ic = zz_icon( $label );
		if ( '' === $ic ) {
			$plain[] = $label;
			continue;
		}
		$icons[ $ic ][] = $label;
	}
}
ok( ! $plain, 'every plugin menu row carries an icon', implode( ', ', $plain ) );
$dupes = array();
foreach ( $icons as $ic => $labels ) {
	if ( count( $labels ) > 1 ) {
		$dupes[] = $ic . ' → ' . implode( ' / ', $labels );
	}
}
ok( ! $dupes, 'no two menu rows share an icon', implode( ', ', $dupes ) );

echo "\n=== 9c. screen ids match the parents ===\n";
// These strings are how this harness sets the screen before rendering, so a stale
// one fails silently — nothing in production reads a screen id. Checked against
// the menu that just registered.
$checked = 0;
foreach ( $screens as $name => list( $screen_id, $get ) ) {
	$slug = $get['page'] ?? '';
	if ( '' === $slug || ! isset( $where[ $slug ] ) ) {
		continue;                                // a CPT metabox screen, not a page
	}
	$parent   = array_keys( $where[ $slug ] )[0];
	$expected = $slug === $parent
		? 'toplevel_page_' . $slug
		: ( $hooks[ $parent ] ?? 'bhela_booking' ) . '_page_' . $slug;
	ok( $screen_id === $expected, "$name screen id is current", "want $expected, have $screen_id" );
	$checked++;
}
ok( $checked >= 13, 'every page screen in the list was cross-checked', "checked $checked" );

echo "\n=== 9d. a menu nobody can use does not appear ===\n";
// Visibility is decided at admin_menu, where the current user finally exists.
// At init — where show_in_menu is fixed — it cannot be asked at all.
$zz_user = wp_insert_user( array(
	'user_login' => 'zz_menu_probe',
	'user_pass'  => wp_generate_password(),
	'user_email' => 'zz_menu_probe@example.invalid',
	'role'       => 'bhela_booking_staff',
) );
if ( is_wp_error( $zz_user ) ) {
	ok( false, 'probe user created', $zz_user->get_error_message() );
} else {
	foreach ( array(
		'bhela_manager'       => array( 'accounts', 'store' ),
		'bhela_booking_staff' => array(),
		'bhela_cost_checker'  => array( 'accounts', 'store' ),
		'bhela_cost_preparer' => array( 'accounts' ),
		'bhela_storekeeper'   => array( 'store' ),
	) as $role => $expect ) {
		$u = new WP_User( $zz_user );
		$u->set_role( $role );
		list( $rm ) = zz_menu( $zz_user );
		$got = array();
		foreach ( array( 'accounts' => 'bhela-bm-statement', 'store' => 'bhela-bm-inv-month', 'setup' => 'bhela-bm-settings' ) as $g => $slug ) {
			if ( in_array( $slug, wp_list_pluck( $rm, 2 ), true ) ) {
				$got[] = $g;
			}
		}
		sort( $got );
		sort( $expect );
		ok( $got === $expect, "$role sees " . ( $expect ? implode( ' + ', $expect ) : 'no extra menu' ),
			'got: ' . ( $got ? implode( ' + ', $got ) : 'none' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $zz_user );
}
zz_menu( $admin_id );                            // put the admin back

echo "\n=== 9e. URLs and the legacy shim ===\n";
foreach ( array( 'bhela-bm-dashboard', 'bhela-bm-reports', 'bhela-bm-trips' ) as $slug ) {
	ok( false !== strpos( bhela_bm_admin_url( $slug ), 'edit.php?post_type=bhela_booking&page=' . $slug ),
		"$slug URL stays under edit.php", bhela_bm_admin_url( $slug ) );
}
foreach ( array( 'bhela-bm-statement', 'bhela-bm-inv-month', 'bhela-bm-settings', 'bhela-bm-audit', 'bhela-bm-log', 'bhela-bm-yearly' ) as $slug ) {
	$u = bhela_bm_admin_url( $slug );
	ok( false !== strpos( $u, 'admin.php?page=' . $slug ) && false === strpos( $u, 'post_type' ),
		"$slug URL is admin.php with no post_type", $u );
}
ok( bhela_bm_admin_url( 'bhela-bm-inv-month', array( 'month' => '2026-07' ) ) === bhela_bm_inv_month_url( '2026-07' ),
	'bhela_bm_inv_month_url() delegates to the one helper' );
ok( false !== strpos( bhela_bm_inv_import_url( array( 'step' => 2 ) ), 'admin.php?page=bhela-bm-inv-import' ),
	'bhela_bm_inv_import_url() delegates too' );

// The shim keeps a legacy link working, but a link this plugin renders TODAY should
// not need it — a redirect on every click is a bug that merely does not show. $all
// is the concatenated markup of every screen rendered in section 4.
$legacy = array();
foreach ( $where as $slug => $parents ) {
	if ( 'bookings' === bhela_bm_menu_page_group( $slug ) ) {
		continue;
	}
	if ( false !== strpos( $all, 'post_type=bhela_booking&#038;page=' . $slug )
		|| false !== strpos( $all, 'post_type=bhela_booking&page=' . $slug ) ) {
		$legacy[] = $slug;
	}
}
ok( ! $legacy, 'no rendered link still points a moved page at edit.php', implode( ', ', $legacy ) );

// Settings URLs of the old shape are already in email this plugin has sent, so the
// shim is load-bearing. Throwing from the filter unwinds past the exit() that
// follows wp_safe_redirect(), which is the only way to observe the target.
ok( false !== has_action( 'admin_init', 'bhela_bm_menu_legacy_redirect' ), 'the shim is hooked' );
$zz_catch = function ( $loc ) {
	throw new RuntimeException( $loc );
};
add_filter( 'wp_redirect', $zz_catch );
$zz_shim = function ( $get, $pagenow = 'edit.php' ) {
	$_GET = $get;
	$GLOBALS['pagenow'] = $pagenow;
	try {
		bhela_bm_menu_legacy_redirect();
		return '';                               // no redirect
	} catch ( RuntimeException $e ) {
		return $e->getMessage();
	}
};
$to = $zz_shim( array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-statement', 'month' => '2026-07' ) );
ok( false !== strpos( $to, 'admin.php?page=bhela-bm-statement' ) && false !== strpos( $to, 'month=2026-07' ),
	'a legacy statement URL lands on the statement, filter intact', $to );
ok( '' === $zz_shim( array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-reports' ) ),
	'Trip Report is not redirected — it never moved' );
ok( '' === $zz_shim( array( 'post_type' => 'bhela_booking' ) ), 'the plain booking list is not redirected' );
ok( '' === $zz_shim( array( 'post_type' => 'bhela_booking', 'page' => 'acf-options' ) ),
	'another plugin\'s page is left alone' );
ok( '' === $zz_shim( array( 'page' => 'bhela-bm-statement' ), 'admin.php' ), 'the new URL does not redirect to itself' );
remove_filter( 'wp_redirect', $zz_catch );
$_GET = array();


echo "\n=== 10. the cost-sheet tone map lives where every caller can reach it ===\n";
// §13.22's lesson: a shared helper parked in one screen's file is a load-order
// accident. This one was defined in trip-report.php while the cost sheet needed it.
ok( function_exists( 'bhela_bm_cost_status_tone' ), 'bhela_bm_cost_status_tone() exists' );
$ui_ui_src = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/ui.php' );
ok( false !== strpos( $ui_ui_src, 'function bhela_bm_cost_status_tone' ), 'and it is defined in ui.php' );
$ui_tr_src = (string) php_strip_whitespace( WP_PLUGIN_DIR . '/bhela-booking/includes/trip-report.php' );
ok( false === strpos( $ui_tr_src, 'function bhela_bm_cost_status_tone' ), 'not in the report screen it happened to be written for' );
foreach ( array( 'draft' => 'neutral', 'prepared' => 'progress', 'checked' => 'progress', 'approved' => 'good' ) as $ui_st => $ui_tone ) {
	ok( $ui_tone === bhela_bm_cost_status_tone( $ui_st ), "$ui_st reads as $ui_tone" );
}
ok( 'neutral' === bhela_bm_cost_status_tone( 'nonsense' ), 'and an unknown status falls back rather than erroring' );

echo "\n=== cleanup ===\n";
foreach ( $made as $id ) { bhela_test_delete( $id ); }
ok( true, 'fixtures removed' );

bhela_test_done();
