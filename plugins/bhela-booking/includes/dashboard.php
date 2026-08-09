<?php
/**
 * Plugin dashboard — the landing screen for the BHELA menu.
 *
 * One overview the owner sees first: bookings by status, money in, the next
 * departures, recent activity, content counts, a setup-health checklist and
 * quick links. Everything here is a read of data other modules already own —
 * the dashboard computes nothing new and stores nothing.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Menu (registered first, then floated to the top) ---------- */

function bhela_bm_dashboard_menu() {
	add_submenu_page(
		'edit.php?post_type=bhela_booking',
		__( 'BHELA Dashboard', 'bhela-booking' ),
		__( '📊 Dashboard', 'bhela-booking' ),
		'bhela_view_reports',
		'bhela-bm-dashboard',
		'bhela_bm_dashboard_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_dashboard_menu' );

/**
 * WordPress builds the Bookings submenu in registration order, which reads as a
 * jumble (CPT items, then each module in load order). Reorder the whole thing
 * into a task-flow sequence: overview → bookings → money → schedule → media →
 * feedback → admin → help. Pure display order, no capability change.
 *
 * Nested post types (show_in_menu pointing at this parent) contribute exactly
 * one entry each — core's _add_post_type_submenus() adds the "All items" link
 * and nothing else. Only bhela_booking, which owns the top-level menu, also
 * gets an "Add New". That is why the list below is shorter than the number of
 * post types suggests.
 */
function bhela_bm_dashboard_menu_order() {
	global $submenu;
	$parent = 'edit.php?post_type=bhela_booking';
	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}
	$order = array(
		// Every day
		'bhela-bm-dashboard',                     // 📊 Dashboard
		'edit.php?post_type=bhela_booking',       // All Bookings
		'post-new.php?post_type=bhela_booking',   // Add New Booking
		// Money
		'bhela-bm-reports',                       // 📄 Trip Report
		'edit.php?post_type=bhela_cost',          // 🧾 Cost Sheets
		'edit.php?post_type=bhela_expense',       // 💸 Expenses
		'bhela-bm-statement',                     // 📈 Monthly Statement
		'bhela-bm-yearly',                        // 📚 Yearly Report
		'edit.php?post_type=bhela_salary',        // 👷 Salary
		// Planning
		'bhela-bm-trips',                         // Trip Calendar
		'edit.php?post_type=bhela_spot',          // 🗺️ Spots
		// Content
		'edit.php?post_type=bhela_gallery',       // 🖼️ Gallery
		'bhela-bm-gallery-bulk',                  // ⬆️ Bulk Upload
		'edit.php?post_type=bhela_review',        // ⭐ Reviews
		// Administration
		'bhela-bm-log',                           // 📋 Activity Log
		'bhela-bm-team',                          // 👥 Team
		'bhela-bm-settings',                      // ⚙️ Settings
		'bhela-bm-guide',                         // 🎯 Quick Guide (help last)
	);

	// html_entity_decode covers slugs WordPress stores escaped (a taxonomy link
	// carries &amp; between its query args), so a future one still matches.
	$rank = array();
	foreach ( $order as $i => $slug ) {
		$rank[ $slug ]                       = $i;
		$rank[ html_entity_decode( $slug ) ] = $i;
	}

	// Anything unrecognised (a page added by a future release) keeps its
	// registration order at the end rather than being dropped or hidden.
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
add_action( 'admin_menu', 'bhela_bm_dashboard_menu_order', 999 );

/* ---------- Small read helpers ---------- */

/** Count of bookings in a given status. */
function bhela_bm_count_bookings( $status ) {
	$q = new WP_Query( array(
		'post_type'      => 'bhela_booking',
		'post_status'    => 'publish',
		'meta_key'       => '_bhela_status',
		'meta_value'     => $status,
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
	) );
	return (int) $q->found_posts;
}

/**
 * Money totals across all bookings. Confirmed + completed count as earned
 * revenue; paid amount is what has actually been collected so far.
 *
 * @return array{ earned:int, collected:int, pending_value:int }
 */
function bhela_bm_money_totals() {
	$ids = get_posts( array(
		'post_type'      => 'bhela_booking',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	$earned = 0;
	$coll   = 0;
	$pend   = 0;
	foreach ( $ids as $id ) {
		$status = get_post_meta( $id, '_bhela_status', true ) ?: 'pending';
		$total  = (int) get_post_meta( $id, '_bhela_total', true );
		$paid   = (int) get_post_meta( $id, '_bhela_paid_amount', true );
		if ( in_array( $status, array( 'confirmed', 'completed' ), true ) ) {
			$earned += $total;
		}
		if ( in_array( $status, array( 'pending', 'advance_paid' ), true ) ) {
			$pend += $total;
		}
		if ( 'cancelled' !== $status ) {
			$coll += $paid;
		}
	}
	return array( 'earned' => $earned, 'collected' => $coll, 'pending_value' => $pend );
}

/* ---------- Page ---------- */

function bhela_bm_dashboard_page() {
	if ( ! current_user_can( 'bhela_view_reports' ) ) {
		return;
	}
	$s        = bhela_bm_get_settings();
	$statuses = bhela_bm_statuses();
	$counts   = array();
	$total    = 0;
	foreach ( $statuses as $key => $label ) {
		$counts[ $key ] = bhela_bm_count_bookings( $key );
		$total         += $counts[ $key ];
	}
	$money = bhela_bm_money_totals();

	$link = function ( $args ) {
		return esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) );
	};
	$page = function ( $slug ) {
		return esc_url( add_query_arg( array( 'post_type' => 'bhela_booking', 'page' => $slug ), admin_url( 'edit.php' ) ) );
	};

	// Upcoming trips (future only).
	$today    = current_time( 'Y-m-d' );
	$upcoming = array();
	if ( function_exists( 'bhela_bm_get_trips' ) ) {
		foreach ( bhela_bm_get_trips() as $t ) {
			if ( ( $t['date'] ?? '' ) >= $today ) {
				$upcoming[] = $t;
			}
			if ( count( $upcoming ) >= 5 ) {
				break;
			}
		}
	}

	// Content counts.
	$gallery_n = function_exists( 'bhela_bm_get_gallery' ) ? count( bhela_bm_get_gallery() ) : 0;
	$review_q  = new WP_Query( array( 'post_type' => 'bhela_review', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
	$review_n  = (int) $review_q->found_posts;
	// Guest-submitted reviews sit in 'pending' until approved, so the published
	// count alone would hide them.
	$review_pending = function_exists( 'bhela_bm_reviews_pending_count' ) ? bhela_bm_reviews_pending_count() : 0;

	// Setup-health checklist.
	$checks = array(
		array( ! empty( $s['phone_1'] ) && ! empty( $s['whatsapp'] ), __( 'Contact number & WhatsApp set', 'bhela-booking' ), $page( 'bhela-bm-settings' ) ),
		array( ! empty( $s['bkash_number'] ) || ! empty( $s['nagad_number'] ) || ! empty( $s['bank_details'] ), __( 'Payment details set', 'bhela-booking' ), $page( 'bhela-bm-settings' ) ),
		array( ! empty( $s['email_enabled'] ), __( 'Email notifications on', 'bhela-booking' ), $page( 'bhela-bm-settings' ) ),
		array( ! empty( $s['sms_enabled'] ), __( 'SMS gateway on (optional)', 'bhela-booking' ), $page( 'bhela-bm-settings' ) ),
		array( ! empty( $upcoming ), __( 'Upcoming trips scheduled', 'bhela-booking' ), $page( 'bhela-bm-trips' ) ),
		array( $gallery_n > 0, __( 'Gallery has photos', 'bhela-booking' ), $link( array( 'post_type' => 'bhela_gallery' ) ) ),
	);

	$log = function_exists( 'bhela_bm_get_log' ) ? array_slice( bhela_bm_get_log(), 0, 6 ) : array();
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'🛶',
			__( 'BHELA Dashboard', 'bhela-booking' ),
			$s['business_name'] . ' — ' . __( 'Everything at a glance, in one place.', 'bhela-booking' ),
			'<a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=bhela_booking' ) ) . '">➕ ' . esc_html__( 'New Booking', 'bhela-booking' ) . '</a>'
		);
		?>

		<!-- Booking counts -->
		<div class="bha-tiles">
			<a class="bha-tile" href="<?php echo $link( array( 'post_type' => 'bhela_booking' ) ); ?>">
				<span class="bha-tile__value"><?php echo esc_html( $total ); ?></span>
				<span class="bha-tile__label"><?php esc_html_e( 'Total Bookings', 'bhela-booking' ); ?></span>
			</a>
			<?php foreach ( $statuses as $key => $label ) :
				list( $tone ) = bhela_bm_status_tone( $key );
				?>
				<a class="bha-tile" href="<?php echo $link( array( 'post_type' => 'bhela_booking', 'bhela_status' => $key ) ); ?>">
					<span class="bha-tile__value is-<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $counts[ $key ] ); ?></span>
					<span class="bha-tile__label"><?php echo esc_html( $label ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<?php
		// SMS credit. Only appears once a gateway with a balance API is set up.
		$sms_bal = function_exists( 'bhela_bm_sms_balance' ) ? bhela_bm_sms_balance() : array( 'balance' => null, 'at' => '', 'error' => '' );
		if ( null !== $sms_bal['balance'] || $sms_bal['error'] ) :
			$low     = function_exists( 'bhela_bm_sms_balance_low' ) && bhela_bm_sms_balance_low( $sms_bal['balance'] );
			$otp_on  = function_exists( 'bhela_bm_otp_on' ) && bhela_bm_otp_on();
			$refresh = wp_nonce_url( admin_url( 'admin-post.php?action=bhela_bm_sms_balance' ), 'bhela_bm_sms_balance' );
			?>
			<div class="bha-panel bha-panel--row<?php echo $low || $sms_bal['error'] ? ' bha-panel--alert' : ''; ?>">
				<div>
					<span class="bha-tile__label"><?php esc_html_e( 'SMS credit', 'bhela-booking' ); ?></span>
					<?php if ( null !== $sms_bal['balance'] ) : ?>
						<span class="bha-card__value<?php echo $low ? ' is-attention' : ''; ?>" style="font-size:26px"><?php echo esc_html( bhela_bm_money( $sms_bal['balance'] ) ); ?></span>
					<?php else : ?>
						<span class="bha-card__value is-danger" style="font-size:15px"><?php esc_html_e( 'unavailable', 'bhela-booking' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="bha-panel__note">
					<?php if ( $sms_bal['error'] ) : ?>
						<span class="bha-owed"><?php echo esc_html( $sms_bal['error'] ); ?></span>
					<?php elseif ( $low && $otp_on ) : ?>
						<strong class="bha-owed"><?php esc_html_e( 'Running low — top up soon.', 'bhela-booking' ); ?></strong>
						<?php esc_html_e( 'Number verification is on, so when the credit runs out codes fall back to email — and guests who leave the email blank will not be able to book at all.', 'bhela-booking' ); ?>
					<?php elseif ( $low ) : ?>
						<strong><?php esc_html_e( 'Running low — top up soon.', 'bhela-booking' ); ?></strong>
						<?php esc_html_e( 'Booking notifications will stop sending once it reaches zero.', 'bhela-booking' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Enough for booking notifications and verification codes.', 'bhela-booking' ); ?>
					<?php endif; ?>
					<?php if ( $sms_bal['at'] ) : ?>
						<span class="bha-sub" style="display:inline">· <?php echo esc_html( sprintf( __( 'checked %s', 'bhela-booking' ), mysql2date( 'j M, g:i a', $sms_bal['at'] ) ) ); ?></span>
					<?php endif; ?>
					<a href="<?php echo esc_url( $refresh ); ?>"><?php esc_html_e( 'refresh', 'bhela-booking' ); ?></a>
				</div>
			</div>
		<?php endif; ?>

		<!-- Money -->
		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Earned (confirmed + completed)', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $money['earned'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Received (incl. advances)', 'bhela-booking' ); ?></span><span class="bha-card__value is-good"><?php echo esc_html( bhela_bm_money( $money['collected'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Pending value', 'bhela-booking' ); ?></span><span class="bha-card__value is-attention"><?php echo esc_html( bhela_bm_money( $money['pending_value'] ) ); ?></span></div>
		</div>

		<!-- Quick actions -->
		<div class="bha-panel bha-buttons">
			<h2 class="bha-panel__title"><?php esc_html_e( 'Quick Actions', 'bhela-booking' ); ?></h2>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=bhela_booking' ) ); ?>">➕ <?php esc_html_e( 'New Booking', 'bhela-booking' ); ?></a>
			<a class="button" href="<?php echo $page( 'bhela-bm-reports' ); ?>">📄 <?php esc_html_e( 'Trip Report', 'bhela-booking' ); ?></a>
			<a class="button" href="<?php echo $link( array( 'post_type' => 'bhela_cost' ) ); ?>">🧾 <?php esc_html_e( 'Cost Sheets', 'bhela-booking' ); ?></a>
			<a class="button" href="<?php echo $page( 'bhela-bm-trips' ); ?>">📅 <?php esc_html_e( 'Trip Calendar', 'bhela-booking' ); ?></a>
			<a class="button" href="<?php echo $page( 'bhela-bm-gallery-bulk' ); ?>">🖼️ <?php esc_html_e( 'Bulk Upload', 'bhela-booking' ); ?></a>
			<a class="button" href="<?php echo $link( array( 'post_type' => 'bhela_review' ) ); ?>">⭐ <?php esc_html_e( 'Reviews', 'bhela-booking' ); ?></a>
			<a class="button" href="<?php echo $page( 'bhela-bm-settings' ); ?>">⚙️ <?php esc_html_e( 'Settings', 'bhela-booking' ); ?></a>
			<a class="button" href="<?php echo $page( 'bhela-bm-log' ); ?>">📋 <?php esc_html_e( 'Activity Log', 'bhela-booking' ); ?></a>
			<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">🌐 <?php esc_html_e( 'View Site', 'bhela-booking' ); ?></a>
		</div>

		<div class="bha-cols">
			<div>
				<!-- Upcoming trips -->
				<div class="bha-panel">
					<h2 class="bha-panel__title"><?php esc_html_e( 'Upcoming Trips', 'bhela-booking' ); ?></h2>
					<?php if ( $upcoming ) : ?>
						<table>
							<?php foreach ( $upcoming as $t ) :
								$av   = function_exists( 'bhela_bm_trip_availability' ) ? bhela_bm_trip_availability( $t['date'] ) : array( 'available' => '', 'total' => '', 'status' => 'available' );
								$tone = function_exists( 'bhela_bm_trip_tone' ) ? bhela_bm_trip_tone( $av['status'] ) : 'neutral';
								?>
								<tr>
									<td><strong><?php echo esc_html( $t['label'] ? $t['label'] : $t['date'] ); ?></strong><span class="bha-sub"><?php echo esc_html( $t['days'] ?? '' ); ?></span></td>
									<td class="bha-num">
										<?php
										echo bhela_bm_status_pill( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
											$av['available'] . '/' . $av['total'],
											$tone,
											true
										);
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
						<p style="margin:12px 0 0"><a href="<?php echo $page( 'bhela-bm-trips' ); ?>"><?php esc_html_e( 'Manage all trips →', 'bhela-booking' ); ?></a></p>
					<?php else : ?>
						<p><em><?php esc_html_e( 'No upcoming trips. Add departure dates in the Trip Calendar.', 'bhela-booking' ); ?></em></p>
						<p><a class="button" href="<?php echo $page( 'bhela-bm-trips' ); ?>">📅 <?php esc_html_e( 'Trip Calendar', 'bhela-booking' ); ?></a></p>
					<?php endif; ?>
				</div>

				<!-- Recent activity -->
				<div class="bha-panel">
					<h2 class="bha-panel__title"><?php esc_html_e( 'Recent Activity', 'bhela-booking' ); ?></h2>
					<?php if ( $log ) : ?>
						<table>
							<?php foreach ( $log as $row ) : ?>
								<tr>
									<td><?php echo empty( $row['ok'] ) ? '❌ ' : '✅ '; ?><?php echo esc_html( $row['msg'] ); ?>
										<span class="bha-sub"><?php echo esc_html( mysql2date( 'j M, g:i a', $row['time'] ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</table>
						<p style="margin:12px 0 0"><a href="<?php echo $page( 'bhela-bm-log' ); ?>"><?php esc_html_e( 'Full activity log →', 'bhela-booking' ); ?></a></p>
					<?php else : ?>
						<p><em><?php esc_html_e( 'Nothing recorded yet.', 'bhela-booking' ); ?></em></p>
					<?php endif; ?>
				</div>
			</div>

			<div>
				<!-- Setup health -->
				<div class="bha-panel">
					<h2 class="bha-panel__title"><?php esc_html_e( 'Setup Checklist', 'bhela-booking' ); ?></h2>
					<ul class="bha-list">
						<?php foreach ( $checks as $c ) : ?>
							<li><?php echo $c[0] ? '✅ ' : '⬜ '; ?>
								<?php if ( $c[0] ) : ?>
									<?php echo esc_html( $c[1] ); ?>
								<?php else : ?>
									<a href="<?php echo esc_url( $c[2] ); ?>"><?php echo esc_html( $c[1] ); ?></a>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<!-- Content counts -->
				<div class="bha-panel">
					<h2 class="bha-panel__title"><?php esc_html_e( 'Content', 'bhela-booking' ); ?></h2>
					<table>
						<tr><td>🖼️ <?php esc_html_e( 'Gallery photos', 'bhela-booking' ); ?></td><td class="bha-num"><strong><?php echo esc_html( $gallery_n ); ?></strong> · <a href="<?php echo $link( array( 'post_type' => 'bhela_gallery' ) ); ?>"><?php esc_html_e( 'manage', 'bhela-booking' ); ?></a></td></tr>
						<tr><td>⭐ <?php esc_html_e( 'Reviews', 'bhela-booking' ); ?></td><td class="bha-num"><strong><?php echo esc_html( $review_n ); ?></strong>
						<?php if ( $review_pending ) : ?>
							· <a class="bha-flag" href="<?php echo $link( array( 'post_type' => 'bhela_review', 'post_status' => 'pending' ) ); ?>"><?php echo esc_html( sprintf(
								/* translators: %d: number of reviews awaiting approval */
								_n( '%d awaiting approval', '%d awaiting approval', $review_pending, 'bhela-booking' ), $review_pending ) ); ?></a>
						<?php endif; ?>
						· <a href="<?php echo $link( array( 'post_type' => 'bhela_review' ) ); ?>"><?php esc_html_e( 'manage', 'bhela-booking' ); ?></a></td></tr>
					</table>
				</div>

				<div class="bha-panel" style="text-align:center">
					<p style="margin:0 0 8px"><?php esc_html_e( 'Need help using the plugin?', 'bhela-booking' ); ?></p>
					<a class="button" href="<?php echo $page( 'bhela-bm-guide' ); ?>">🎯 <?php esc_html_e( 'Open Quick Guide', 'bhela-booking' ); ?></a>
				</div>
			</div>
		</div>

		<p class="bha-foot"><?php echo esc_html( sprintf( __( 'BHELA Booking Engine v%s · 3s-Soft', 'bhela-booking' ), defined( 'BHELA_BM_VERSION' ) ? BHELA_BM_VERSION : '' ) ); ?></p>
	</div>
	<?php
}
