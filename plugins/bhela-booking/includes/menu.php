<?php
/**
 * The admin menu — four top-level entries, grouped by the job being done.
 *
 * Everything used to hang off one "Bookings" menu. By v2.27.0 that was 22 rows
 * from Add New Booking to Audit Trail to Quick Guide, in no obvious grouping, and
 * finding anything meant reading the whole list.
 *
 *   Bookings   bookings, dashboard, trip report, calendar, reviews
 *   Accounts   cost sheets, expenses, salary, statement, yearly
 *   Store      item register, import, monthly stock, both reports, audit trail
 *   Setup      settings, team, spots, gallery, bulk upload, activity log, guide
 *
 * Three facts shape how this file works. They are all easy to get wrong and hard
 * to notice, so they are written down rather than implied:
 *
 * 1. `init` runs before the current user resolves, so a post type's
 *    `show_in_menu` can never be a `current_user_can()` decision. Every CPT
 *    therefore stays registered under Bookings and is MOVED here, on
 *    `admin_menu`, which is late enough to know who is looking.
 *
 * 2. A submenu may be registered against a parent that does not exist yet —
 *    WordPress matches `$submenu` to `$menu` by slug at render time. So the order
 *    of the priorities below is for readability, not correctness.
 *
 * 3. A top-level `$menu_title` must carry NO emoji. `$admin_page_hooks[$slug]` is
 *    `sanitize_title( $menu_title )`, and `sanitize_title( '📦 Store' )` is
 *    `'%f0%9f%93%a6-store'` — which would make every child's screen id
 *    percent-encoded noise, and screen ids are what the test harness sets. Top
 *    level gets a dashicon, like WordPress's own menus; the emoji live on the
 *    submenu rows.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The three menus this plugin creates, plus what makes each one worth showing.
 *
 * `caps` is an OR — hold any one of them and the menu appears. `add_menu_page()`
 * takes a single capability and cannot express that, so the OR is evaluated in
 * bhela_bm_menu_visible() instead.
 *
 * `slug` is the group's identity and its registered callback, so the parent is a
 * real screen rather than an index page nobody maintains — WordPress renders one
 * row when a child's slug equals its parent's.
 *
 * It is NOT necessarily where clicking the parent lands. _wp_menu_output() builds a
 * top-level's href from its FIRST submenu row, so the landing screen is whatever
 * bhela_bm_menu_layout() lists first for that group — Cost Sheets for Accounts,
 * Item Register for Store. bhela_bm_menu_landing() reports it, and ui-test pins it.
 *
 * Every slug keeps the `bhela-bm-` prefix, because that is what
 * bhela_bm_is_plugin_screen() matches on to load admin.css — a menu slug without
 * it would render unstyled.
 *
 * Bookings is deliberately absent: it belongs to the bhela_booking post type and
 * is not created here. Moving it would mean rewriting the ~40 places that build a
 * `post_type=bhela_booking` URL, for no gain.
 *
 * @return array group => array{title, slug, icon, pos, caps}
 */
function bhela_bm_menu_groups() {
	return array(
		'accounts' => array(
			'title' => __( 'Accounts', 'bhela-booking' ),
			'slug'  => 'bhela-bm-statement',
			'icon'  => 'dashicons-chart-line',
			'pos'   => 27,
			'caps'  => array( 'edit_bhela_costs', 'edit_bhela_expenses', 'edit_bhela_salaries', 'bhela_view_statement' ),
		),
		'store'    => array(
			'title' => __( 'Store', 'bhela-booking' ),
			'slug'  => 'bhela-bm-inv-month',
			'icon'  => 'dashicons-archive',
			'pos'   => 28,
			'caps'  => array( 'bhela_inv_view', 'bhela_inv_count', 'bhela_inv_audit' ),
		),
		'investors' => array(
			'title' => __( 'Investors', 'bhela-booking' ),
			'slug'  => 'bhela-bm-dist',
			'icon'  => 'dashicons-groups',
			'pos'   => 28.5,
			// OR, like every other group: holding any one of these is reason enough for
			// the menu to exist.
			'caps'  => array( 'bhela_investors_view', 'bhela_dist_run', 'bhela_investor_pay' ),
		),
		'setup'    => array(
			'title' => __( 'Setup', 'bhela-booking' ),
			'slug'  => 'bhela-bm-settings',
			'icon'  => 'dashicons-admin-generic',
			'pos'   => 29,
			// Every row in here already needs manage_options or edit_posts, which no
			// staff role holds. So this menu is administrator-only by construction —
			// intended, not an oversight.
			'caps'  => array( 'manage_options' ),
		),
	);
}

/** The Bookings parent, which belongs to the post type rather than to this file. */
function bhela_bm_menu_bookings_parent() {
	return 'edit.php?post_type=bhela_booking';
}

/**
 * Can this user reach anything in the group?
 *
 * A menu nobody in the room can use should not be in the room. Booking staff see
 * Bookings and nothing else; a storekeeper sees Store and nothing else.
 *
 * @param string $group Group key.
 * @return bool
 */
function bhela_bm_menu_visible( $group ) {
	if ( 'bookings' === $group ) {
		return current_user_can( 'edit_bhela_bookings' );
	}
	$caps = bhela_bm_menu_groups()[ $group ]['caps'] ?? array();
	foreach ( $caps as $cap ) {
		if ( current_user_can( $cap ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The parent slug a page in this group should hang under.
 *
 * Falls back to the Bookings parent when the group is hidden, rather than
 * returning '' — an add_submenu_page() with an empty parent creates an orphan row
 * WordPress renders at the top level, which looks like a bug to whoever sees it.
 * The row's own capability still keeps it out of reach.
 *
 * @param string $group Group key: bookings|accounts|store|setup.
 * @return string
 */
function bhela_bm_menu_parent( $group ) {
	if ( 'bookings' === $group ) {
		return bhela_bm_menu_bookings_parent();
	}
	$slug = bhela_bm_menu_groups()[ $group ]['slug'] ?? '';
	return ( $slug && bhela_bm_menu_visible( $group ) ) ? $slug : bhela_bm_menu_bookings_parent();
}

/**
 * Where clicking a top-level menu actually lands.
 *
 * _wp_menu_output() builds a top-level's href from `$submenu[$parent][0][2]` — its
 * first row — not from the parent's own slug. So this is a property of
 * bhela_bm_menu_layout()'s ordering, and reordering a group silently changes where
 * its menu goes. It went wrong once: with 📊 Dashboard listed first, clicking
 * "Bookings" opened the dashboard instead of the booking list.
 *
 * @param string $group Group key.
 * @return string First slug in the group, or '' if the group has no rows.
 */
function bhela_bm_menu_landing( $group ) {
	return bhela_bm_menu_layout()[ $group ][0] ?? '';
}

/** Which group owns a page slug. Drives bhela_bm_admin_url() and the legacy shim. */
function bhela_bm_menu_page_group( $page ) {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( bhela_bm_menu_layout() as $group => $slugs ) {
			foreach ( $slugs as $slug ) {
				$map[ $slug ] = $group;
			}
		}
	}
	return $map[ $page ] ?? 'bookings';
}

/**
 * Every row, in the order it should appear, grouped by parent.
 *
 * One list doing two jobs: it says which group owns a page (so a URL can be
 * built and a legacy one redirected) and it fixes the display order. WordPress
 * builds a submenu in registration order, which for this plugin means file load
 * order — a jumble.
 *
 * A slug missing from here still works; it simply sorts to the end of its parent.
 *
 * @return array parent-group => slug[]
 */
function bhela_bm_menu_layout() {
	return array(
		'bookings' => array(
			// All Bookings first, and not for tidiness: WordPress builds a top-level
			// menu's own href from its FIRST submenu row. With Dashboard here, clicking
			// "Bookings" went to admin.php?page=bhela-bm-dashboard — which does resolve,
			// but is not the booking list anyone clicking "Bookings" is asking for.
			'edit.php?post_type=bhela_booking',       // All Bookings
			'post-new.php?post_type=bhela_booking',   // Add New Booking
			'bhela-bm-dashboard',                     // 📊 Dashboard
			'bhela-bm-reports',                       // 📄 Trip Report
			'bhela-bm-trips',                         // 📅 Trip Calendar
			'edit.php?post_type=bhela_review',        // ⭐ Reviews
		),
		'accounts' => array(
			'edit.php?post_type=bhela_cost',          // 🧾 Cost Sheets
			'edit.php?post_type=bhela_expense',       // 💸 Expenses
			'edit.php?post_type=bhela_salary',        // 👷 Salary
			'bhela-bm-statement',                     // 📈 Monthly Statement
			'bhela-bm-yearly',                        // 📚 Yearly Report
			'bhela-bm-b2b',                           // 🤝 B2B Report
			'bhela-bm-trip-pl',                       // 🧮 Trip P&L
			'bhela-bm-revenue',                       // 💹 Revenue by Source
		),
		'store'    => array(
			'edit.php?post_type=bhela_inv_item',      // 📦 Item Register
			'bhela-bm-inv-import',                    // 🚚 Import Register
			'bhela-bm-inv-month',                     // 🔧 Monthly Stock
			'bhela-bm-inv-report',                    // 📐 Inventory Report
			'bhela-bm-inv-assets',                    // 🏷️ Asset Report
			'bhela-bm-audit',                         // 🔩 Audit Trail
		),
		'investors' => array(
			'edit.php?post_type=bhela_investor',      // 👤 Investors
			'bhela-bm-investor-dash',                 // 🧭 Dashboard
			'bhela-bm-dist',                          // 💰 Distribution
			'bhela-bm-investor-report',               // 📊 Investor Report
			'bhela-bm-signups',                       // 📝 Registrations
			'bhela-bm-valuation',                     // 💎 Valuation
			'bhela-bm-share-issue',                   // 🪙 Share Issue
			'bhela-bm-funds',                         // 🏦 Funds
			'bhela-bm-cashflow',                      // 💵 Cash Flow
		),
		'setup'    => array(
			'bhela-bm-settings',                      // ⚙️ Settings
			'bhela-bm-team',                          // 👥 Team
			'edit.php?post_type=bhela_spot',          // 🗺️ Spots
			'edit.php?post_type=bhela_gallery',       // 🖼️ Gallery
			'bhela-bm-gallery-bulk',                  // ⬆️ Bulk Upload
			'bhela-bm-log',                           // 📋 Activity Log
			'bhela-bm-guide',                         // 🎯 Quick Guide (help last)
		),
	);
}

/**
 * The admin URL of one of this plugin's pages.
 *
 * The single place that knows which parent a page hangs under. Before this
 * existed, roughly twenty-five call sites hand-built
 * `edit.php?post_type=bhela_booking&page=…`, so moving a page between menus meant
 * finding every one of them — and a missed one does not error, it just goes
 * somewhere wrong.
 *
 * @param string $page Page slug, e.g. 'bhela-bm-statement'.
 * @param array  $args Extra query args.
 * @return string
 */
function bhela_bm_admin_url( $page, $args = array() ) {
	$page  = sanitize_key( $page );
	$group = bhela_bm_menu_page_group( $page );

	if ( 'bookings' === $group ) {
		// Still a child of edit.php, so it needs the post type to resolve.
		return add_query_arg(
			array_merge( array( 'post_type' => 'bhela_booking', 'page' => $page ), $args ),
			admin_url( 'edit.php' )
		);
	}
	return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
}

/* =========================================================
 * REGISTRATION
 * ========================================================= */

/**
 * Create the three menus, for the people who can use them.
 *
 * Priority 5, before the modules add their rows at 10 — for sidebar order only.
 * Registration itself is order-independent, because WordPress joins a submenu to
 * its parent by slug at render time rather than at registration.
 *
 * The capability passed to add_menu_page() is the group's first, but visibility is
 * really decided by bhela_bm_menu_visible(): a user holding only a later cap in
 * the list would otherwise lose the parent while keeping its children, and
 * WordPress would promote one child to the top level on its own.
 */
function bhela_bm_menu_register() {
	foreach ( bhela_bm_menu_groups() as $group => $def ) {
		if ( ! bhela_bm_menu_visible( $group ) ) {
			continue;
		}
		add_menu_page(
			/* translators: %s: menu name */
			sprintf( __( 'BHELA %s', 'bhela-booking' ), $def['title'] ),
			$def['title'],                       // no emoji — see the file header
			'read',                              // the real gate is menu_visible() above
			$def['slug'],
			'',                                  // the landing page is a child with the same slug
			$def['icon'],
			$def['pos']
		);
	}
}
add_action( 'admin_menu', 'bhela_bm_menu_register', 5 );

/**
 * Move an already-registered submenu row to another parent, label intact.
 *
 * For the CPT rows, whose parent is fixed at `init` before the user exists. The
 * row is re-added rather than re-registered so the label survives: for a nested
 * post type the emoji lives in `labels['all_items']` (or `labels['name']` as a
 * fallback, which bhela_inv_item relies on), and rebuilding the label here would
 * put the emoji in two places to drift apart.
 *
 * @param string $slug Row slug to move.
 * @param string $to   Destination parent slug.
 * @param string $from Source parent slug.
 * @return bool Whether a row moved.
 */
function bhela_bm_menu_move( $slug, $to, $from = '' ) {
	global $submenu;
	$from = $from ? $from : bhela_bm_menu_bookings_parent();
	if ( '' === $to || $to === $from || empty( $submenu[ $from ] ) ) {
		return false;
	}
	foreach ( $submenu[ $from ] as $i => $row ) {
		if ( ( $row[2] ?? '' ) !== $slug ) {
			continue;
		}
		unset( $submenu[ $from ][ $i ] );
		$submenu[ $from ] = array_values( $submenu[ $from ] );
		$submenu[ $to ][] = $row;
		return true;
	}
	return false;
}

/**
 * Re-home the post-type rows.
 *
 * Priority 20, because core's _add_post_type_submenus() runs at 10 and the row has
 * to exist before it can be moved. Everything not listed stays under Bookings.
 */
function bhela_bm_menu_move_cpts() {
	$moves = array(
		'edit.php?post_type=bhela_cost'     => 'accounts',
		'edit.php?post_type=bhela_expense'  => 'accounts',
		'edit.php?post_type=bhela_salary'   => 'accounts',
		'edit.php?post_type=bhela_inv_item' => 'store',
		'edit.php?post_type=bhela_investor' => 'investors',
		'edit.php?post_type=bhela_spot'     => 'setup',
		'edit.php?post_type=bhela_gallery'  => 'setup',
	);
	foreach ( $moves as $slug => $group ) {
		bhela_bm_menu_move( $slug, bhela_bm_menu_parent( $group ) );
	}
}
add_action( 'admin_menu', 'bhela_bm_menu_move_cpts', 20 );

/**
 * Sort every parent into its intended order.
 *
 * WordPress builds each submenu in registration order, which here means file load
 * order. This was one parent's problem until v2.28.0; now it is four, so the list
 * is keyed by parent.
 *
 * Mechanism unchanged from the single-parent version: rank by position in the
 * list, alias each slug through html_entity_decode() because WordPress stores
 * some slugs escaped (a taxonomy link carries `&amp;` between its query args),
 * send anything unrecognised to the end rather than dropping it, and re-index at
 * the finish — WordPress's submenu array is positional.
 *
 * Priority 999: after registration (5, 10) and after the CPT moves (20), before
 * the reviews bubble (1000).
 */
function bhela_bm_menu_order() {
	global $submenu;

	foreach ( bhela_bm_menu_layout() as $group => $order ) {
		$parent = 'bookings' === $group ? bhela_bm_menu_bookings_parent() : ( bhela_bm_menu_groups()[ $group ]['slug'] ?? '' );
		if ( '' === $parent || empty( $submenu[ $parent ] ) ) {
			continue;
		}

		// Collapse the row WordPress adds for the parent itself.
		//
		// add_submenu_page() inserts a link to the parent as the first row whenever
		// the FIRST child registered happens to have a different slug from the
		// parent. Since each group's parent slug IS one of its pages, that produces
		// two rows for the same screen — one labelled "Store" with the placeholder
		// `read` capability, one the module's own with its emoji and real cap. Which
		// group it happens to depends on file load order, so it is deduped here
		// rather than by arranging the requires in bhela-booking.php just so.
		//
		// The later row wins: WordPress inserts its own before the module registers.
		$by_slug = array();
		foreach ( $submenu[ $parent ] as $item ) {
			$by_slug[ $item[2] ] = $item;
		}
		$submenu[ $parent ] = array_values( $by_slug );

		$rank = array();
		foreach ( $order as $i => $slug ) {
			$rank[ $slug ]                       = $i;
			$rank[ html_entity_decode( $slug ) ] = $i;
		}

		// A page added by a future release keeps its registration order at the end
		// rather than being hidden.
		$fallback = count( $order );
		foreach ( $submenu[ $parent ] as $i => $item ) {
			$submenu[ $parent ][ $i ]['bhela_rank'] = $rank[ $item[2] ] ?? ( $fallback + $i );
		}
		usort( $submenu[ $parent ], function ( $a, $b ) {
			return $a['bhela_rank'] <=> $b['bhela_rank'];
		} );
		foreach ( $submenu[ $parent ] as $i => $item ) {
			unset( $submenu[ $parent ][ $i ]['bhela_rank'] );
		}
		$submenu[ $parent ] = array_values( $submenu[ $parent ] );
	}
}
add_action( 'admin_menu', 'bhela_bm_menu_order', 999 );

/**
 * Keep old links working.
 *
 * Every page used to live under Bookings, so a URL of the shape
 * `edit.php?post_type=bhela_booking&page=bhela-bm-statement` was correct for
 * years. Once the page is registered under another parent, WordPress looks for the
 * hookname built from THIS parent, finds nothing, and refuses the request.
 *
 * That would break bookmarks, and worse: the plugin has already put settings URLs
 * of exactly that shape into email it has sent (see includes/emails.php and
 * includes/sms.php). A dead link in somebody's inbox is not something a menu
 * tidy-up gets to cause, so this shim is permanent rather than transitional.
 */
function bhela_bm_menu_legacy_redirect() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}
	global $pagenow;
	if ( 'edit.php' !== $pagenow || 'bhela_booking' !== ( $_GET['post_type'] ?? '' ) ) {
		return;
	}
	$page = sanitize_key( $_GET['page'] ?? '' );
	if ( '' === $page || 0 !== strpos( $page, 'bhela-bm-' ) ) {
		return;
	}
	if ( 'bookings' === bhela_bm_menu_page_group( $page ) ) {
		return;                                  // still lives here
	}

	// Carry every other query arg across, so a bookmarked month or filter survives.
	$args = $_GET;
	unset( $args['post_type'], $args['page'] );
	wp_safe_redirect( bhela_bm_admin_url( $page, array_map( 'sanitize_text_field', $args ) ) );
	exit;
}
add_action( 'admin_init', 'bhela_bm_menu_legacy_redirect' );
