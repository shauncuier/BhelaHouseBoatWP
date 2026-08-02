<?php
/**
 * Staff roles and the Team screen.
 *
 * Before this, running BHELA meant being a WordPress administrator: every
 * screen was gated on `edit_posts` or `manage_options`, so there was no way to
 * let a booking clerk take reservations without also handing them the settings,
 * the SMS gateway keys and the whole site.
 *
 * This module owns every role and capability the plugin defines. Assigning them
 * stays where WordPress already does it well — Users → Add New lists these
 * roles in its dropdown, because they are ordinary WordPress roles. The Team
 * screen here is the part WordPress has no answer for: a table of what each
 * role may actually do, generated from the definitions below.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bump when bhela_bm_roles() changes so existing sites re-sync once.
define( 'BHELA_BM_ROLES_VERSION', 2 );

/* =========================================================
 * CAPABILITY SETS
 * ========================================================= */

/**
 * Custom capabilities that are not derived from a post type.
 *
 * @return array cap => human description, used by the Team screen's matrix.
 */
function bhela_bm_extra_caps() {
	return array(
		'bhela_view_reports'  => __( 'Dashboard & Trip Report', 'bhela-booking' ),
		'bhela_manage_trips'  => __( 'Trip Calendar', 'bhela-booking' ),
		'bhela_cost_prepare'  => __( 'Fill in cost sheets', 'bhela-booking' ),
		'bhela_cost_check'    => __( 'Check cost sheets', 'bhela-booking' ),
		'bhela_cost_approve'  => __( 'Approve cost sheets', 'bhela-booking' ),
	);
}

/**
 * The full primitive capability list for a post type using its own
 * capability_type, in the order WordPress derives them.
 *
 * @param string $singular e.g. 'bhela_booking'
 * @param string $plural   e.g. 'bhela_bookings'
 * @return string[]
 */
function bhela_bm_cpt_caps( $singular, $plural ) {
	return array(
		"edit_{$plural}",
		"edit_others_{$plural}",
		"edit_published_{$plural}",
		"edit_private_{$plural}",
		"publish_{$plural}",
		"read_private_{$plural}",
		"delete_{$plural}",
		"delete_others_{$plural}",
		"delete_published_{$plural}",
		"delete_private_{$plural}",
		"read_{$singular}",
		"edit_{$singular}",
		"delete_{$singular}",
	);
}

/** Booking capabilities minus the destructive ones — staff should not purge records. */
function bhela_bm_booking_caps_basic() {
	return array(
		'edit_bhela_bookings', 'edit_others_bhela_bookings', 'edit_published_bhela_bookings',
		'edit_private_bhela_bookings', 'publish_bhela_bookings', 'read_private_bhela_bookings',
		'read_bhela_booking', 'edit_bhela_booking',
	);
}

/** Cost-sheet capabilities a preparer needs (own sheets only — no edit_others). */
function bhela_bm_cost_caps_basic() {
	return array(
		'edit_bhela_costs', 'edit_published_bhela_costs', 'publish_bhela_costs',
		'read_private_bhela_costs', 'read_bhela_cost', 'edit_bhela_cost',
	);
}

/**
 * Every role this plugin ships, with the capabilities that define it.
 *
 * `read` and `upload_files` are the baseline that lets someone into wp-admin at
 * all. Nothing here grants `edit_posts` or `manage_options` — that separation is
 * the whole point: a staff member can run bookings without being able to touch
 * the site's pages, plugins or the SMS gateway credentials.
 *
 * @return array slug => array{ name:string, blurb:string, caps:string[] }
 */
function bhela_bm_roles() {
	$base = array( 'read', 'upload_files' );

	$staff = array_merge(
		$base,
		bhela_bm_booking_caps_basic(),
		array( 'bhela_view_reports' )
	);

	$manager = array_merge(
		$staff,
		bhela_bm_cost_caps_basic(),
		array(
			'edit_others_bhela_costs',
			'bhela_manage_trips',
			'bhela_cost_prepare',
			'bhela_cost_check',
			'delete_bhela_bookings',
			'delete_published_bhela_bookings',
			'delete_bhela_booking',
		)
	);

	// No bhela_view_reports here on purpose. The Dashboard and Trip Report are
	// submenus of Bookings, which cost-only staff cannot see — granting the cap
	// would register menu items they could never reach. The booking earnings
	// they do need are already printed on the cost sheet itself.
	$preparer = array_merge(
		$base,
		bhela_bm_cost_caps_basic(),
		array( 'bhela_cost_prepare' )
	);

	$checker = array_merge(
		$preparer,
		array( 'edit_others_bhela_costs', 'bhela_cost_check' )
	);

	return array(
		'bhela_manager' => array(
			'name'  => __( 'BHELA Manager', 'bhela-booking' ),
			'blurb' => __( 'Runs day-to-day operations: bookings, trip calendar, reports, and checks cost sheets. Cannot approve costs or change settings.', 'bhela-booking' ),
			'caps'  => $manager,
		),
		'bhela_booking_staff' => array(
			'name'  => __( 'BHELA Booking Staff', 'bhela-booking' ),
			'blurb' => __( 'Takes and updates bookings, sees the trip report. No cost sheets, no calendar, no settings.', 'bhela-booking' ),
			'caps'  => $staff,
		),
		'bhela_cost_checker' => array(
			'name'  => __( 'BHELA Cost Checker', 'bhela-booking' ),
			'blurb' => __( 'Reviews every cost sheet and marks it checked, or returns it. No booking access.', 'bhela-booking' ),
			'caps'  => $checker,
		),
		'bhela_cost_preparer' => array(
			'name'  => __( 'BHELA Cost Preparer', 'bhela-booking' ),
			'blurb' => __( 'Fills in their own cost sheets and submits them for checking. Cannot see other people\'s sheets.', 'bhela-booking' ),
			'caps'  => $preparer,
		),
	);
}

/** Everything an administrator holds — the union of every role, plus approval. */
function bhela_bm_admin_caps() {
	$caps = array_keys( bhela_bm_extra_caps() );
	$caps = array_merge( $caps, bhela_bm_cpt_caps( 'bhela_booking', 'bhela_bookings' ) );
	$caps = array_merge( $caps, bhela_bm_cpt_caps( 'bhela_cost', 'bhela_costs' ) );
	return array_values( array_unique( $caps ) );
}

/* =========================================================
 * INSTALL / SYNC
 * ========================================================= */

/** Every capability this plugin is responsible for, across all its post types. */
function bhela_bm_owned_caps() {
	return array_values( array_unique( array_merge(
		array_keys( bhela_bm_extra_caps() ),
		bhela_bm_cpt_caps( 'bhela_booking', 'bhela_bookings' ),
		bhela_bm_cpt_caps( 'bhela_cost', 'bhela_costs' )
	) ) );
}

/**
 * Create or re-sync every role. Idempotent — safe on every activation.
 *
 * On a role the plugin owns, the sync is authoritative for the plugin's own
 * capabilities: a cap dropped from bhela_bm_roles() is dropped from the stored
 * role too. Add-only syncing looks safer but silently leaves permissions behind
 * — narrow a role in a later version and every existing site keeps the wider
 * one. Capabilities from outside this plugin are never touched, so anything the
 * owner granted through another plugin survives.
 */
function bhela_bm_install_roles() {
	$owned = bhela_bm_owned_caps();

	foreach ( bhela_bm_roles() as $slug => $def ) {
		$role = get_role( $slug );
		if ( ! $role ) {
			add_role( $slug, $def['name'], array_fill_keys( $def['caps'], true ) );
			continue;
		}
		foreach ( $def['caps'] as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
		foreach ( $owned as $cap ) {
			if ( ! in_array( $cap, $def['caps'], true ) && $role->has_cap( $cap ) ) {
				$role->remove_cap( $cap );
			}
		}
	}

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( bhela_bm_admin_caps() as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}

	// Bookings used to run on the generic `post` capabilities, so any role with
	// edit_others_posts (Editor, by default) could already manage them. Carry
	// that across rather than silently revoking access on upgrade.
	foreach ( wp_roles()->role_objects as $slug => $role ) {
		if ( 'administrator' === $slug || ! $role->has_cap( 'edit_others_posts' ) ) {
			continue;
		}
		foreach ( array_merge( bhela_bm_booking_caps_basic(), array( 'bhela_view_reports' ) ) as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}
}

function bhela_bm_maybe_install_roles() {
	if ( (int) get_option( 'bhela_bm_roles_version', 0 ) >= BHELA_BM_ROLES_VERSION ) {
		return;
	}
	bhela_bm_install_roles();
	update_option( 'bhela_bm_roles_version', BHELA_BM_ROLES_VERSION );
}
add_action( 'admin_init', 'bhela_bm_maybe_install_roles', 5 );

/* =========================================================
 * TEAM SCREEN
 * ========================================================= */

function bhela_bm_team_menu() {
	add_submenu_page(
		'edit.php?post_type=bhela_booking',
		__( 'Team', 'bhela-booking' ),
		__( '👥 Team', 'bhela-booking' ),
		'manage_options',
		'bhela-bm-team',
		'bhela_bm_team_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_team_menu' );

/** Users who currently hold one of the plugin's roles, plus every administrator. */
function bhela_bm_team_members() {
	$slugs = array_keys( bhela_bm_roles() );
	$users = get_users( array( 'role__in' => array_merge( $slugs, array( 'administrator' ) ), 'orderby' => 'display_name' ) );
	return $users;
}

/** The BHELA role a user holds, or '' if none. */
function bhela_bm_user_role( $user ) {
	foreach ( array_keys( bhela_bm_roles() ) as $slug ) {
		if ( in_array( $slug, (array) $user->roles, true ) ) {
			return $slug;
		}
	}
	return '';
}


/**
 * The Team screen: a read-only view of who can use BHELA and exactly what each
 * role may do.
 *
 * Deliberately not a user editor. Users → Add New already creates accounts and
 * assigns roles — these are real WordPress roles, so they appear in its
 * dropdown with no extra code — and duplicating that would mean two places to
 * change a role and two places to keep correct. What WordPress cannot show is
 * what a role actually permits: its user list prints role *names* and stops
 * there, so nothing there reveals that a Cost Checker cannot approve. That
 * table is this screen's reason to exist.
 */
function bhela_bm_team_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$roles   = bhela_bm_roles();
	$members = bhela_bm_team_members();
	?>
	<style>
		.bhela-team { max-width: 1140px; }
		.bhela-team__card { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 18px 20px; margin: 0 0 18px; }
		.bhela-team__card h2 { margin: 0 0 4px; font-size: 15px; }
		.bhela-team__card > p.bhela-team__blurb { margin: 0 0 14px; }
		.bhela-team table.widefat { border-radius: 8px; overflow: hidden; }
		.bhela-team table.widefat th,
		.bhela-team table.widefat td { padding: 10px 12px; vertical-align: middle; }
		.bhela-team__cell-actions { text-align: right; white-space: nowrap; }
		.bhela-team__role { font-weight: 600; }
		.bhela-team__blurb { color: #787c82; font-size: 12px; margin: 2px 0 0; }
		.bhela-team__admin { color: #787c82; font-style: italic; }
		.bhela-team__none { color: #b45309; font-weight: 600; }
		.bhela-team__matrix th, .bhela-team__matrix td { text-align: center; }
		.bhela-team__matrix th:first-child, .bhela-team__matrix td:first-child { text-align: left; width: 34%; }
		.bhela-team__matrix thead th { vertical-align: bottom; line-height: 1.35; }
		.bhela-team__matrix tbody td:not(:first-child) { font-size: 15px; }
		.bhela-team__yes { color: #1a7f37; font-weight: 700; }
		.bhela-team__no { color: #c3c4c7; }
		.bhela-team__legend { margin: 14px 0 0; }
		.bhela-team__legend p { margin: 0 0 6px; }
		@media (max-width: 782px) { .bhela-team__cell-actions { text-align: left; } }
	</style>

	<div class="wrap bhela-team">
		<h1>👥 <?php esc_html_e( 'Team', 'bhela-booking' ); ?></h1>
		<p style="color:#50575e;margin:4px 0 16px"><?php esc_html_e( 'Who can use BHELA, and how much of it. Nobody here gets access to the rest of WordPress.', 'bhela-booking' ); ?></p>

		<div class="bhela-team__card">
			<h2><?php esc_html_e( 'Current team', 'bhela-booking' ); ?></h2>
			<p class="bhela-team__blurb">
				<?php esc_html_e( 'Add someone, change a role or remove an account in Users — the BHELA roles are listed in its role dropdown.', 'bhela-booking' ); ?>
				<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Add New User', 'bhela-booking' ); ?></a> ·
				<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>"><?php esc_html_e( 'All Users', 'bhela-booking' ); ?></a>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:26%"><?php esc_html_e( 'Name', 'bhela-booking' ); ?></th>
						<th style="width:26%"><?php esc_html_e( 'Email', 'bhela-booking' ); ?></th>
						<th style="width:26%"><?php esc_html_e( 'BHELA role', 'bhela-booking' ); ?></th>
						<th style="width:110px"><?php esc_html_e( 'Added', 'bhela-booking' ); ?></th>
						<th style="width:90px" class="bhela-team__cell-actions"></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $members as $u ) :
					$is_admin = in_array( 'administrator', (array) $u->roles, true );
					$current  = bhela_bm_user_role( $u );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $u->display_name ); ?></strong>
							<div class="bhela-team__blurb"><?php echo esc_html( $u->user_login ); ?></div>
						</td>
						<td><?php echo esc_html( $u->user_email ); ?></td>
						<td>
							<?php if ( $is_admin ) : ?>
								<span class="bhela-team__admin"><?php esc_html_e( 'Administrator — full access', 'bhela-booking' ); ?></span>
							<?php elseif ( $current ) : ?>
								<span class="bhela-team__role"><?php echo esc_html( $roles[ $current ]['name'] ); ?></span>
							<?php else : ?>
								<span class="bhela-team__none"><?php esc_html_e( 'No BHELA role', 'bhela-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( mysql2date( 'j M Y', $u->user_registered ) ); ?></td>
						<td class="bhela-team__cell-actions">
							<a class="button" href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>"><?php esc_html_e( 'Edit', 'bhela-booking' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="bhela-team__card">
			<h2><?php esc_html_e( 'What each role can do', 'bhela-booking' ); ?></h2>
			<p class="bhela-team__blurb"><?php esc_html_e( 'Read straight from the role definitions, so it cannot drift from what the site actually enforces.', 'bhela-booking' ); ?></p>
			<table class="widefat striped bhela-team__matrix">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Can', 'bhela-booking' ); ?></th>
						<?php foreach ( $roles as $def ) : ?>
							<th><?php echo esc_html( $def['name'] ); ?></th>
						<?php endforeach; ?>
						<th><?php esc_html_e( 'Administrator', 'bhela-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$matrix = array(
					__( 'Take & edit bookings', 'bhela-booking' )         => 'edit_bhela_bookings',
					__( 'Dashboard & Trip Report', 'bhela-booking' )      => 'bhela_view_reports',
					__( 'Trip Calendar', 'bhela-booking' )                => 'bhela_manage_trips',
					__( 'Fill in cost sheets', 'bhela-booking' )          => 'bhela_cost_prepare',
					__( 'See everyone\'s cost sheets', 'bhela-booking' )  => 'edit_others_bhela_costs',
					__( 'Check cost sheets', 'bhela-booking' )            => 'bhela_cost_check',
					__( 'Approve cost sheets', 'bhela-booking' )          => 'bhela_cost_approve',
					__( 'Settings, SMS & email', 'bhela-booking' )        => 'manage_options',
				);
				foreach ( $matrix as $label => $cap ) :
					?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<?php foreach ( $roles as $def ) : ?>
							<td class="<?php echo in_array( $cap, $def['caps'], true ) ? 'bhela-team__yes' : 'bhela-team__no'; ?>">
								<?php echo in_array( $cap, $def['caps'], true ) ? '✓' : '—'; ?>
							</td>
						<?php endforeach; ?>
						<td class="bhela-team__yes">✓</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<div class="bhela-team__legend">
				<?php foreach ( $roles as $def ) : ?>
					<p class="bhela-team__blurb"><strong class="bhela-team__role"><?php echo esc_html( $def['name'] ); ?></strong> — <?php echo esc_html( $def['blurb'] ); ?></p>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}
