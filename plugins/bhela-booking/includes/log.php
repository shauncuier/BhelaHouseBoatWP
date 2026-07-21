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

/** Log entry types → label + colour, used by the admin table. */
function bhela_bm_log_types() {
	return array(
		'booking'  => array( 'label' => 'বুকিং', 'color' => '#137A74' ),
		'status'   => array( 'label' => 'স্ট্যাটাস', 'color' => '#0A2A2F' ),
		'email'    => array( 'label' => 'ইমেইল', 'color' => '#1a7f37' ),
		'sms'      => array( 'label' => 'SMS', 'color' => '#7c3aed' ),
		'trips'    => array( 'label' => 'ট্রিপ ক্যালেন্ডার', 'color' => '#b45309' ),
		'settings' => array( 'label' => 'সেটিংস', 'color' => '#5E7472' ),
		'gallery'  => array( 'label' => 'গ্যালারি', 'color' => '#0891b2' ),
		'error'    => array( 'label' => 'সমস্যা', 'color' => '#b32d2e' ),
	);
}

/**
 * Bangla digits for log sentences. The site locale is en_US, so
 * number_format_i18n() would return English digits inside Bangla text.
 * Money keeps English digits elsewhere to match invoices — this is only
 * for counts in admin-facing sentences.
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
		wp_die( esc_html__( 'অনুমতি নেই।', 'bhela-booking' ) );
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
	<div class="wrap">
		<h1>📋 <?php esc_html_e( 'অ্যাক্টিভিটি লগ', 'bhela-booking' ); ?></h1>
		<p><?php esc_html_e( 'সাইটে কী কী ঘটছে তার রেকর্ড — বুকিং এসেছে কিনা, ইমেইল/SMS গেছে কিনা, ট্রিপ ক্যালেন্ডারে কী বদলেছে। নতুনটা সবার উপরে।', 'bhela-booking' ); ?></p>

		<?php if ( isset( $_GET['cleared'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'লগ মুছে ফেলা হয়েছে।', 'bhela-booking' ); ?></p></div>
		<?php endif; ?>

		<p>
			<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-log' ), admin_url( 'edit.php' ) ) ); ?>"
				class="button<?php echo '' === $filter ? ' button-primary' : ''; ?>"><?php esc_html_e( 'সব', 'bhela-booking' ); ?></a>
			<?php foreach ( $types as $key => $t ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-log', 'ltype' => $key ), admin_url( 'edit.php' ) ) ); ?>"
					class="button<?php echo $filter === $key ? ' button-primary' : ''; ?>"><?php echo esc_html( $t['label'] ); ?></a>
			<?php endforeach; ?>
		</p>

		<?php if ( ! $log ) : ?>
			<p><em><?php esc_html_e( 'এখনো কিছু রেকর্ড হয়নি। একটা বুকিং বা সেটিংস সেভ করলেই এখানে দেখা যাবে।', 'bhela-booking' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:1000px">
				<thead><tr>
					<th style="width:150px"><?php esc_html_e( 'সময়', 'bhela-booking' ); ?></th>
					<th style="width:130px"><?php esc_html_e( 'ধরন', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'কী হয়েছে', 'bhela-booking' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'কে', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				$shown = 0;
				foreach ( $log as $row ) :
					if ( $filter && $filter !== $row['type'] ) {
						continue;
					}
					$shown++;
					$t = $types[ $row['type'] ] ?? array( 'label' => $row['type'], 'color' => '#5E7472' );
					?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'j M Y, g:i a', $row['time'] ) ); ?></td>
						<td><span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:600;color:#fff;background:<?php echo esc_attr( $t['color'] ); ?>"><?php echo esc_html( $t['label'] ); ?></span></td>
						<td><?php echo empty( $row['ok'] ) ? '❌ ' : '✅ '; ?><?php echo esc_html( $row['msg'] ); ?></td>
						<td><?php echo esc_html( $row['user'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $shown ) : ?>
					<tr><td colspan="4"><em><?php esc_html_e( 'এই ধরনের কোনো রেকর্ড নেই।', 'bhela-booking' ); ?></em></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
				<input type="hidden" name="action" value="bhela_bm_log_clear">
				<?php wp_nonce_field( 'bhela_bm_log_clear' ); ?>
				<button type="submit" class="button" onclick="return confirm('পুরো লগ মুছে যাবে — নিশ্চিত?')"><?php esc_html_e( 'লগ মুছুন', 'bhela-booking' ); ?></button>
				<span class="description" style="margin-left:8px"><?php echo esc_html( sprintf( __( 'সর্বশেষ %d টি রেকর্ড রাখা হয়।', 'bhela-booking' ), BHELA_BM_LOG_MAX ) ); ?></span>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
