<?php
/**
 * Activity log — a plain record of what the plugin actually did.
 *
 * Exists so the owner can answer "did that work?" without a developer:
 * bookings received, emails/SMS sent or failed, statuses changed, and —
 * importantly — every trip-calendar save with the rows that were removed,
 * so a disappearing departure date can always be traced.
 *
 * Stored in a single option as a capped ring buffer (newest first), so there
 * is no table to create and nothing to migrate.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BHELA_BM_LOG_OPTION', 'bhela_bm_log' );
define( 'BHELA_BM_LOG_MAX', 300 );

/**
 * Log entry types.
 *
 * These are channels, not statuses — "Email" is not better or worse than
 * "SMS" — so each gets its own hue from the .bha-tag-- set in admin.css rather
 * than one of the five semantic tones. The key doubles as the class suffix.
 */
function bhela_bm_log_types() {
	return array(
		'booking'  => array( 'label' => 'Bookings' ),
		'status'   => array( 'label' => 'Status' ),
		'email'    => array( 'label' => 'Email' ),
		'sms'      => array( 'label' => 'SMS' ),
		'trips'    => array( 'label' => 'Trip Calendar' ),
		'cost'     => array( 'label' => 'Cost Sheets' ),
		'settings' => array( 'label' => 'Settings' ),
		'gallery'  => array( 'label' => 'Gallery' ),
		// Operational noise from the register — "the month was closed". The
		// permanent who-changed-what-from-what record is the Audit Trail, which is
		// a separate store precisely because this one is capped and clearable.
		'inventory' => array( 'label' => 'Stock & Assets' ),
		'error'    => array( 'label' => 'Problems' ),
	);
}

/**
 * Bengali digits. wp-admin is English throughout, so this is now only for
 * guest-facing output (the booking form's advance percentage), where English
 * numerals would look wrong inside Bengali copy.
 */
function bhela_bm_bn_num( $n ) {
	return strtr( (string) $n, array(
		'0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
		'5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
	) );
}

/**
 * Record one event.
 *
 * @param string $type    One of bhela_bm_log_types().
 * @param string $message Short human-readable sentence (Bangla is fine).
 * @param bool   $ok      Whether the action succeeded.
 */
function bhela_bm_log( $type, $message, $ok = true ) {
	$log = get_option( BHELA_BM_LOG_OPTION, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
	array_unshift( $log, array(
		'time' => current_time( 'mysql' ),
		'type' => sanitize_key( $type ),
		'msg'  => wp_strip_all_tags( (string) $message ),
		'ok'   => (bool) $ok,
		'user' => ( $user && $user->ID ) ? $user->user_login : '—',
	) );
	if ( count( $log ) > BHELA_BM_LOG_MAX ) {
		$log = array_slice( $log, 0, BHELA_BM_LOG_MAX );
	}
	// autoload=no: the log is only read on its own admin screen.
	update_option( BHELA_BM_LOG_OPTION, $log, false );
}

/** Newest-first log entries. */
function bhela_bm_get_log() {
	$log = get_option( BHELA_BM_LOG_OPTION, array() );
	return is_array( $log ) ? $log : array();
}

/* ---------- Admin page ---------- */

function bhela_bm_log_menu() {
	add_submenu_page(
		'edit.php?post_type=bhela_booking',
		__( 'Activity Log', 'bhela-booking' ),
		__( 'Activity Log', 'bhela-booking' ),
		'manage_options',
		'bhela-bm-log',
		'bhela_bm_log_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_log_menu' );

function bhela_bm_log_clear() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_log_clear' );
	update_option( BHELA_BM_LOG_OPTION, array(), false );
	wp_safe_redirect( add_query_arg(
		array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-log', 'cleared' => 1 ),
		admin_url( 'edit.php' )
	) );
	exit;
}
add_action( 'admin_post_bhela_bm_log_clear', 'bhela_bm_log_clear' );

function bhela_bm_log_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$log    = bhela_bm_get_log();
	$types  = bhela_bm_log_types();
	$filter = isset( $_GET['ltype'] ) ? sanitize_key( $_GET['ltype'] ) : '';
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'📋',
			__( 'Activity Log', 'bhela-booking' ),
			__( 'What the site actually did — bookings received, emails and SMS sent or failed, and every change to the trip calendar. Newest first.', 'bhela-booking' )
		);
		?>

		<?php if ( isset( $_GET['cleared'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Log cleared.', 'bhela-booking' ); ?></p></div>
		<?php endif; ?>

		<p class="bha-buttons">
			<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-log' ), admin_url( 'edit.php' ) ) ); ?>"
				class="button<?php echo '' === $filter ? ' button-primary' : ''; ?>"><?php esc_html_e( 'All', 'bhela-booking' ); ?></a>
			<?php foreach ( $types as $key => $t ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-log', 'ltype' => $key ), admin_url( 'edit.php' ) ) ); ?>"
					class="button<?php echo $filter === $key ? ' button-primary' : ''; ?>"><?php echo esc_html( $t['label'] ); ?></a>
			<?php endforeach; ?>
		</p>

		<?php if ( ! $log ) : ?>
			<p><em><?php esc_html_e( 'Nothing recorded yet. Take a booking or save your settings and it will show up here.', 'bhela-booking' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:1000px">
				<thead><tr>
					<th style="width:150px"><?php esc_html_e( 'Time', 'bhela-booking' ); ?></th>
					<th style="width:130px"><?php esc_html_e( 'Type', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'What happened', 'bhela-booking' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'Who', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				$shown = 0;
				foreach ( $log as $row ) :
					if ( $filter && $filter !== $row['type'] ) {
						continue;
					}
					$shown++;
					$t = $types[ $row['type'] ] ?? array( 'label' => $row['type'] );
					?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'j M Y, g:i a', $row['time'] ) ); ?></td>
						<td><span class="bha-pill bha-pill--tag bha-tag--<?php echo esc_attr( $row['type'] ); ?>"><?php echo esc_html( $t['label'] ); ?></span></td>
						<td><?php echo empty( $row['ok'] ) ? '❌ ' : '✅ '; ?><?php echo esc_html( $row['msg'] ); ?></td>
						<td><?php echo esc_html( $row['user'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $shown ) : ?>
					<tr><td colspan="4"><em><?php esc_html_e( 'No records of this type.', 'bhela-booking' ); ?></em></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
				<input type="hidden" name="action" value="bhela_bm_log_clear">
				<?php wp_nonce_field( 'bhela_bm_log_clear' ); ?>
				<button type="submit" class="button" onclick="return confirm('This will erase the entire log. Are you sure?')"><?php esc_html_e( 'Clear log', 'bhela-booking' ); ?></button>
				<span class="description" style="margin-left:8px"><?php echo esc_html( sprintf( __( 'The most recent %d records are kept.', 'bhela-booking' ), BHELA_BM_LOG_MAX ) ); ?></span>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
