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

// Bump when bhela_bm_role_defaults() or bhela_bm_permissions() changes, so
// existing sites re-sync once against the new definition.
define( 'BHELA_BM_ROLES_VERSION', 3 );

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
 * Everything the owner can switch on or off per role, from the Team screen.
 *
 * One checkbox maps to a bundle of WordPress capabilities. The roles rest on
 * about thirty primitives (`edit_others_bhela_bookings`,
 * `delete_published_bhela_costs`, …) and asking a non-technical owner to reason
 * about those would only produce roles that half-work.
 *
 * The registry is also the allow-list: a capability that is not named here can
 * never be granted from the UI, which is why `manage_options`, `edit_posts`,
 * `activate_plugins` and `list_users` are absent.
 *
 * `requires` is a real constraint, not a hint. `reports` needs `bookings_edit`
 * because the Dashboard and Trip Report are submenus of Bookings — granting the
 * capability alone registers menu items the user can never reach.
 *
 * @return array key => array{ label:string, help:string, caps:string[], requires:string }
 */
function bhela_bm_permissions() {
	return array(
		'bookings_edit'   => array(
			'label' => __( 'Take & edit bookings', 'bhela-booking' ),
			'help'  => __( 'See the booking list, add bookings and change their status.', 'bhela-booking' ),
			'caps'  => bhela_bm_booking_caps_basic(),
		),
		'bookings_delete' => array(
			'label'    => __( 'Delete bookings', 'bhela-booking' ),
			'help'     => __( 'Move bookings to the trash. Off for most staff.', 'bhela-booking' ),
			'caps'     => array( 'delete_bhela_bookings', 'delete_published_bhela_bookings', 'delete_bhela_booking' ),
			'requires' => 'bookings_edit',
		),
		'reports'         => array(
			'label'    => __( 'Dashboard & Trip Report', 'bhela-booking' ),
			'help'     => __( 'Money totals and the per-date trip report.', 'bhela-booking' ),
			'caps'     => array( 'bhela_view_reports' ),
			'requires' => 'bookings_edit',
		),
		'trips'           => array(
			'label' => __( 'Trip Calendar', 'bhela-booking' ),
			'help'  => __( 'Add departure dates and set cabin availability.', 'bhela-booking' ),
			'caps'  => array( 'bhela_manage_trips' ),
		),
		'costs_own'       => array(
			'label' => __( 'Fill in own cost sheets', 'bhela-booking' ),
			'help'  => __( 'Create cost sheets and submit them for checking.', 'bhela-booking' ),
			'caps'  => array_merge( bhela_bm_cost_caps_basic(), array( 'bhela_cost_prepare' ) ),
		),
		'costs_all'       => array(
			'label'    => __( "See everyone's cost sheets", 'bhela-booking' ),
			'help'     => __( 'Without this, only sheets the person created themselves.', 'bhela-booking' ),
			'caps'     => array( 'edit_others_bhela_costs' ),
			'requires' => 'costs_own',
		),
		'costs_check'     => array(
			'label'    => __( 'Check cost sheets', 'bhela-booking' ),
			'help'     => __( 'Mark a prepared sheet as checked, or send it back.', 'bhela-booking' ),
			'caps'     => array( 'bhela_cost_check' ),
			'requires' => 'costs_all',
		),
		'costs_approve'   => array(
			'label'    => __( 'Approve cost sheets', 'bhela-booking' ),
			'help'     => __( 'Final sign-off, which locks the sheet.', 'bhela-booking' ),
			'caps'     => array( 'bhela_cost_approve' ),
			'requires' => 'costs_check',
		),
	);
}

/** Capabilities every staff role gets, so they can reach wp-admin at all. */
function bhela_bm_base_caps() {
	return array( 'read', 'upload_files' );
}

/**
 * The roles as shipped, described by permission key rather than raw capability.
 *
 * This is the baseline the owner's choices are layered over, and what a
 * "Reset to defaults" returns a role to.
 *
 * @return array slug => array{ name:string, blurb:string, perms:string[] }
 */
function bhela_bm_role_defaults() {
	return array(
		'bhela_manager' => array(
			'name'  => __( 'BHELA Manager', 'bhela-booking' ),
			'blurb' => __( 'Runs day-to-day operations: bookings, trip calendar, reports, and checks cost sheets. Cannot approve costs or change settings.', 'bhela-booking' ),
			'perms' => array( 'bookings_edit', 'bookings_delete', 'reports', 'trips', 'costs_own', 'costs_all', 'costs_check' ),
		),
		'bhela_booking_staff' => array(
			'name'  => __( 'BHELA Booking Staff', 'bhela-booking' ),
			'blurb' => __( 'Takes and updates bookings, sees the trip report. No cost sheets, no calendar, no settings.', 'bhela-booking' ),
			'perms' => array( 'bookings_edit', 'reports' ),
		),
		'bhela_cost_checker' => array(
			'name'  => __( 'BHELA Cost Checker', 'bhela-booking' ),
			'blurb' => __( 'Reviews every cost sheet and marks it checked, or returns it. No booking access.', 'bhela-booking' ),
			'perms' => array( 'costs_own', 'costs_all', 'costs_check' ),
		),
		'bhela_cost_preparer' => array(
			'name'  => __( 'BHELA Cost Preparer', 'bhela-booking' ),
			'blurb' => __( 'Fills in their own cost sheets and submits them for checking. Cannot see other people\'s sheets.', 'bhela-booking' ),
			'perms' => array( 'costs_own' ),
		),
	);
}

/**
 * Owner-set permissions, keyed by role slug.
 *
 * Only roles the owner has actually changed appear here. A role that was never
 * touched keeps following bhela_bm_role_defaults(), so a later release can
 * still adjust it.
 *
 * @return array slug => string[] permission keys
 */
function bhela_bm_role_overrides() {
	$saved = get_option( 'bhela_bm_role_perms', array() );
	return is_array( $saved ) ? $saved : array();
}

/**
 * Drop unknown keys and pull in any prerequisite the caller left out.
 *
 * Applied on save and again on read, so a stored set can never describe a
 * half-granted permission — however it got into the option.
 *
 * @param array $perms Permission keys.
 * @return string[] Valid, dependency-complete keys, in registry order.
 */
function bhela_bm_normalise_perms( $perms ) {
	$registry = bhela_bm_permissions();
	$perms    = array_values( array_intersect( (array) $perms, array_keys( $registry ) ) );

	// Walk until stable: a prerequisite can itself have a prerequisite.
	do {
		$added = false;
		foreach ( $perms as $key ) {
			$req = $registry[ $key ]['requires'] ?? '';
			if ( $req && ! in_array( $req, $perms, true ) ) {
				$perms[] = $req;
				$added   = true;
			}
		}
	} while ( $added );

	return array_values( array_intersect( array_keys( $registry ), $perms ) );
}

/** The permission keys in force for a role — the owner's set, or the default. */
function bhela_bm_role_perms( $slug ) {
	$overrides = bhela_bm_role_overrides();
	$defaults  = bhela_bm_role_defaults();
	$perms     = $overrides[ $slug ] ?? ( $defaults[ $slug ]['perms'] ?? array() );
	return bhela_bm_normalise_perms( $perms );
}

/**
 * Every role this plugin ships, with the capabilities that define it.
 *
 * Capabilities are composed from the permissions in force, so this stays the
 * single thing the rest of the plugin reads — the sync, the Team matrix and
 * bhela_bm_owned_caps() all consume it and neither know nor care whether the
 * owner has customised anything.
 *
 * Nothing here grants `edit_posts` or `manage_options` — that separation is the
 * whole point: a staff member can run bookings without being able to touch the
 * site's pages, plugins or the SMS gateway credentials.
 *
 * @return array slug => array{ name:string, blurb:string, perms:string[], caps:string[] }
 */
function bhela_bm_roles() {
	$registry = bhela_bm_permissions();
	$out      = array();

	foreach ( bhela_bm_role_defaults() as $slug => $def ) {
		$perms = bhela_bm_role_perms( $slug );
		$caps  = bhela_bm_base_caps();
		foreach ( $perms as $key ) {
			$caps = array_merge( $caps, $registry[ $key ]['caps'] );
		}
		$out[ $slug ] = array(
			'name'  => $def['name'],
			'blurb' => $def['blurb'],
			'perms' => $perms,
			'caps'  => array_values( array_unique( $caps ) ),
		);
	}
	return $out;
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
 *
 * This is also why the Team screen's choices live in an option that feeds
 * bhela_bm_roles() rather than being written straight onto the role: the sync
 * enforces a definition that already includes them, so a version bump can never
 * quietly undo the owner's configuration.
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

/* =========================================================
 * SAVING PERMISSIONS
 * ========================================================= */

/** Back to the Team screen with a one-word result. */
function bhela_bm_perms_redirect( $msg ) {
	wp_safe_redirect( add_query_arg(
		array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-team', 'bhela_msg' => $msg ),
		admin_url( 'edit.php' )
	) );
	exit;
}

/**
 * Store the matrix.
 *
 * Everything posted is re-validated here: unknown keys are dropped and missing
 * prerequisites added by bhela_bm_normalise_perms(), so a hand-crafted POST
 * cannot grant a capability the registry does not offer or leave a role in a
 * half-granted state. Then the roles are re-synced so the change takes effect
 * immediately for anyone already logged in.
 */
function bhela_bm_save_perms() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_save_perms' );

	$posted = isset( $_POST['bhela_perms'] ) && is_array( $_POST['bhela_perms'] ) ? wp_unslash( $_POST['bhela_perms'] ) : array();
	$store  = array();
	$log    = array();

	foreach ( bhela_bm_role_defaults() as $slug => $def ) {
		$raw   = isset( $posted[ $slug ] ) ? array_map( 'sanitize_key', (array) $posted[ $slug ] ) : array();
		$perms = bhela_bm_normalise_perms( $raw );
		$store[ $slug ] = $perms;

		$before = bhela_bm_role_perms( $slug );
		if ( $before !== $perms ) {
			$log[] = $def['name'];
		}
	}

	update_option( 'bhela_bm_role_perms', $store );
	bhela_bm_install_roles();

	if ( $log && function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'settings', sprintf(
			'Role permissions changed — %s (by %s)',
			implode( ', ', $log ),
			wp_get_current_user()->display_name
		) );
	}
	bhela_bm_perms_redirect( $log ? 'saved' : 'nochange' );
}
add_action( 'admin_post_bhela_bm_save_perms', 'bhela_bm_save_perms' );

/** Drop one role's override so it follows the shipped defaults again. */
function bhela_bm_reset_perms() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'bhela-booking' ), 403 );
	}
	$slug = sanitize_key( $_GET['role'] ?? '' );
	check_admin_referer( 'bhela_bm_reset_perms_' . $slug );

	$defaults = bhela_bm_role_defaults();
	if ( ! isset( $defaults[ $slug ] ) ) {
		bhela_bm_perms_redirect( 'invalid' );
	}

	$store = bhela_bm_role_overrides();
	unset( $store[ $slug ] );
	update_option( 'bhela_bm_role_perms', $store );
	bhela_bm_install_roles();

	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'settings', sprintf( 'Role permissions reset to defaults — %s', $defaults[ $slug ]['name'] ) );
	}
	bhela_bm_perms_redirect( 'reset' );
}
add_action( 'admin_post_bhela_bm_reset_perms', 'bhela_bm_reset_perms' );

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
	$notice  = isset( $_GET['bhela_msg'] ) ? sanitize_key( $_GET['bhela_msg'] ) : '';
	$notices = array(
		'saved'    => array( 'success', __( 'Permissions saved. They are in force straight away, including for anyone already signed in.', 'bhela-booking' ) ),
		'nochange' => array( 'info', __( 'Nothing to change — those permissions were already set.', 'bhela-booking' ) ),
		'reset'    => array( 'success', __( 'Role reset to the permissions it shipped with.', 'bhela-booking' ) ),
		'invalid'  => array( 'error', __( 'That role does not exist.', 'bhela-booking' ) ),
	);
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
		.bhela-team__matrix tbody td:first-child { line-height: 1.4; }
		.bhela-team__matrix input[type=checkbox] { margin: 0; }
		.bhela-team__save { display: flex; align-items: center; gap: 12px; margin: 14px 0 0; }
		.bhela-team__selfwarn {
			background: #fcf9e8; border-left: 3px solid #b45309; border-radius: 4px;
			padding: 9px 12px; margin: 14px 0 0; font-size: 12px;
		}
		@media (max-width: 782px) { .bhela-team__cell-actions { text-align: left; } }
	</style>

	<div class="wrap bhela-team">
		<h1>👥 <?php esc_html_e( 'Team', 'bhela-booking' ); ?></h1>
		<p style="color:#50575e;margin:4px 0 16px"><?php esc_html_e( 'Who can use BHELA, and how much of it. Nobody here gets access to the rest of WordPress.', 'bhela-booking' ); ?></p>

		<?php if ( isset( $notices[ $notice ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notices[ $notice ][0] ); ?> is-dismissible">
				<p><?php echo esc_html( $notices[ $notice ][1] ); ?></p>
			</div>
		<?php endif; ?>

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
			<p class="bhela-team__blurb"><?php esc_html_e( 'Tick what each role is allowed to do, then save. Changes apply immediately, including to people already signed in.', 'bhela-booking' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bhela-perms-form">
				<input type="hidden" name="action" value="bhela_bm_save_perms">
				<?php wp_nonce_field( 'bhela_bm_save_perms' ); ?>
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
					<?php foreach ( bhela_bm_permissions() as $key => $perm ) : ?>
						<tr>
							<td>
								<?php echo esc_html( $perm['label'] ); ?>
								<div class="bhela-team__blurb"><?php echo esc_html( $perm['help'] ); ?></div>
							</td>
							<?php foreach ( $roles as $slug => $def ) : ?>
								<td>
									<input type="checkbox"
										name="bhela_perms[<?php echo esc_attr( $slug ); ?>][]"
										value="<?php echo esc_attr( $key ); ?>"
										data-role="<?php echo esc_attr( $slug ); ?>"
										data-perm="<?php echo esc_attr( $key ); ?>"
										<?php echo isset( $perm['requires'] ) ? 'data-requires="' . esc_attr( $perm['requires'] ) . '"' : ''; ?>
										<?php checked( in_array( $key, $def['perms'], true ) ); ?>>
								</td>
							<?php endforeach; ?>
							<td class="bhela-team__yes" title="<?php esc_attr_e( 'Administrators always have everything.', 'bhela-booking' ); ?>">✓</td>
						</tr>
					<?php endforeach; ?>
						<tr>
							<td><?php esc_html_e( 'Settings, SMS & email', 'bhela-booking' ); ?>
								<div class="bhela-team__blurb"><?php esc_html_e( 'Administrator only — cannot be granted to staff.', 'bhela-booking' ); ?></div>
							</td>
							<?php foreach ( $roles as $def ) : ?>
								<td class="bhela-team__no">—</td>
							<?php endforeach; ?>
							<td class="bhela-team__yes">✓</td>
						</tr>
					</tbody>
				</table>

				<div id="bhela-perms-warn" class="bhela-team__selfwarn" hidden>
					⚠️ <span></span>
				</div>

				<p class="bhela-team__save">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save permissions', 'bhela-booking' ); ?></button>
					<span class="bhela-team__blurb"><?php esc_html_e( 'Ticking a permission also ticks anything it depends on.', 'bhela-booking' ); ?></span>
				</p>
			</form>

			<div class="bhela-team__legend">
				<?php foreach ( $roles as $slug => $def ) :
					$custom = array_key_exists( $slug, bhela_bm_role_overrides() );
					?>
					<p class="bhela-team__blurb">
						<strong class="bhela-team__role"><?php echo esc_html( $def['name'] ); ?></strong> — <?php echo esc_html( $def['blurb'] ); ?>
						<?php if ( $custom ) : ?>
							<em><?php esc_html_e( '(customised)', 'bhela-booking' ); ?></em>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bhela_bm_reset_perms&role=' . $slug ), 'bhela_bm_reset_perms_' . $slug ) ); ?>"
								onclick="return confirm('<?php echo esc_js( __( 'Reset this role to the permissions it shipped with?', 'bhela-booking' ) ); ?>')"><?php esc_html_e( 'reset to defaults', 'bhela-booking' ); ?></a>
						<?php endif; ?>
					</p>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<script>
	(function () {
		var form = document.getElementById('bhela-perms-form');
		if (!form) return;
		var warnBox = document.getElementById('bhela-perms-warn');

		function box(role, perm) {
			return form.querySelector('input[data-role="' + role + '"][data-perm="' + perm + '"]');
		}

		/* Ticking a permission ticks whatever it depends on, and unticking one
		   unticks anything that depends on it. The server re-derives all of this
		   on save — this only stops the form from *looking* inconsistent. */
		function cascade(el) {
			var role = el.dataset.role;
			if (el.checked) {
				var req = el.dataset.requires;
				while (req) {
					var parent = box(role, req);
					if (!parent || parent.checked) break;
					parent.checked = true;
					req = parent.dataset.requires;
				}
			} else {
				form.querySelectorAll('input[data-role="' + role + '"][data-requires="' + el.dataset.perm + '"]').forEach(function (child) {
					if (child.checked) { child.checked = false; cascade(child); }
				});
			}
		}

		function refreshWarning() {
			var names = [];
			form.querySelectorAll('input[data-perm="costs_approve"]').forEach(function (el) {
				if (!el.checked) return;
				var check = box(el.dataset.role, 'costs_check');
				if (check && check.checked) {
					var idx = Array.prototype.indexOf.call(el.closest('tr').children, el.closest('td'));
					var head = form.querySelectorAll('thead th')[idx];
					if (head) names.push(head.textContent.trim());
				}
			});
			if (!warnBox) return;
			warnBox.hidden = names.length === 0;
			warnBox.querySelector('span').textContent = names.length
				? <?php echo wp_json_encode( __( 'These roles can both check and approve, so one person could approve their own cost sheet:', 'bhela-booking' ) ); ?> + ' ' + names.join(', ')
				: '';
		}

		form.addEventListener('change', function (e) {
			if (e.target.type !== 'checkbox') return;
			cascade(e.target);
			refreshWarning();
		});
		refreshWarning();
	})();
	</script>
	<?php
}
