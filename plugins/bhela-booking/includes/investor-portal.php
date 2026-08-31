<?php
/**
 * The investor portal — the one place outsiders sign in.
 *
 * Every role this plugin had before belonged to somebody who works for BHELA. This is
 * the first that does not, and that changes the threat model: an investor is a real
 * person with a real login who has a financial interest in figures they cannot be
 * allowed to edit, and no business at all seeing another investor's bank details.
 *
 * The whole security model is one function. **bhela_bm_current_investor() resolves
 * the viewer's record from their user id and nothing else.** No investor id is ever
 * read from a URL, a form, or a cookie, so there is no id to tamper with — the
 * classic "change the number in the address bar" attack has nothing to change.
 * Scoping by hiding UI would leave the data one crafted request away; scoping at the
 * resolver means a wrong id cannot even be expressed.
 *
 * The portal is READ-ONLY. It writes nothing, ever. An investor who disputes a figure
 * takes it up with the office, and the office corrects it with a reversal that leaves
 * a trail — which is the whole point of the ledger being append-only.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * Identity — the security boundary
 * ========================================================= */

/**
 * The investor record belonging to the current user, or 0.
 *
 * The ONLY way the portal learns whose money it is looking at. It takes a user id
 * and returns a post id; there is no parameter for "which investor", by design.
 *
 * @return int Investor post id, or 0 when the viewer is not a linked investor.
 */
function bhela_bm_current_investor() {
	// Keyed by user id, not a bare static. One request normally has one user, but
	// wp_set_current_user() can change it — in cron, WP-CLI, or any code that acts as
	// somebody else — and an unkeyed cache would then hand back the PREVIOUS user's
	// investor record. On the function that decides whose money is on screen, that is
	// not a risk worth carrying for one saved query.
	static $cache = array();

	$user = get_current_user_id();
	if ( ! $user ) {
		return 0;
	}
	if ( isset( $cache[ $user ] ) ) {
		return $cache[ $user ];
	}
	$cache[ $user ] = 0;
	$hit = get_posts( array(
		'post_type'      => 'bhela_investor',
		'post_status'    => array( 'publish', 'private', 'draft' ),
		'posts_per_page' => 2,       // 2 so a duplicate link can be detected, not hidden
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_inv_user',
		'meta_value'     => $user,
	) );

	// Two records claiming one login is a data error with a security edge: whichever
	// happened to sort first would decide whose money a person sees. Refuse instead.
	if ( count( $hit ) !== 1 ) {
		if ( count( $hit ) > 1 && function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'error', sprintf( 'Investor portal: user #%d is linked to %d investor records — access refused.', $user, count( $hit ) ) );
		}
		return 0;
	}
	$cache[ $user ] = (int) $hit[0];
	return $cache[ $user ];
}

/** The WordPress user linked to an investor record, or 0. */
function bhela_bm_investor_user( $investor_id ) {
	return (int) get_post_meta( $investor_id, '_bhela_inv_user', true );
}

/* =========================================================
 * Keeping investors out of wp-admin
 * ========================================================= */

/**
 * An investor never sees wp-admin.
 *
 * Belt and braces on top of the role holding no capabilities: WordPress screens,
 * other plugins and future core changes are all surface, and the cheapest way to
 * secure a surface is not to expose it. admin-ajax.php is deliberately excluded —
 * blocking it would break anything the theme does for logged-in visitors.
 */
function bhela_bm_investor_block_admin() {
	if ( ! is_admin() || wp_doing_ajax() || ! is_user_logged_in() ) {
		return;
	}
	$user = wp_get_current_user();
	if ( ! in_array( 'bhela_investor', (array) $user->roles, true ) ) {
		return;
	}
	// An account that also holds a staff role keeps its admin access; only a
	// pure-investor login is turned away.
	if ( count( array_intersect( (array) $user->roles, array( 'administrator', 'editor', 'author', 'contributor' ) ) ) ) {
		return;
	}
	wp_safe_redirect( bhela_bm_portal_url() );
	exit;
}
add_action( 'admin_init', 'bhela_bm_investor_block_admin' );

/** No admin bar for an investor — it is a staff affordance and it leaks screen links. */
function bhela_bm_investor_admin_bar( $show ) {
	$user = wp_get_current_user();
	if ( $user && in_array( 'bhela_investor', (array) $user->roles, true ) && 1 === count( (array) $user->roles ) ) {
		return false;
	}
	return $show;
}
add_filter( 'show_admin_bar', 'bhela_bm_investor_admin_bar' );

/** Where the portal lives. Filterable, because the page is created by hand. */
function bhela_bm_portal_url() {
	$page = get_page_by_path( 'investor' );
	$url  = $page ? get_permalink( $page ) : home_url( '/investor/' );
	return apply_filters( 'bhela_bm_portal_url', $url );
}

/* =========================================================
 * Data for the portal, scoped by construction
 * ========================================================= */

/**
 * Everything one investor is allowed to see about themselves.
 *
 * Takes no id. The caller cannot ask about anybody else.
 */
function bhela_bm_portal_data() {
	$id = bhela_bm_current_investor();
	if ( ! $id ) {
		return null;
	}
	$roi = bhela_bm_investor_roi( $id );
	$led = bhela_bm_investor_ledger( $id );
	$cfg = bhela_bm_share_config();

	// Per season, from the rows themselves. A "season" here is the month a profit row
	// was declared for, which is exactly what the distribution wrote.
	$by_month = array();
	foreach ( $led['rows'] as $r ) {
		if ( 'profit' !== $r['type'] ) {
			continue;
		}
		$key = $r['ref'] ? $r['ref'] : substr( $r['date'], 0, 7 );
		if ( ! isset( $by_month[ $key ] ) ) {
			$by_month[ $key ] = 0;
		}
		$by_month[ $key ] += $r['amount'];
	}
	krsort( $by_month );

	return array(
		'id'         => $id,
		'name'       => get_the_title( $id ),
		'code'       => (string) get_post_meta( $id, '_bhela_inv_code', true ),
		'status'     => bhela_bm_investor_status( $id ),
		'joined'     => (string) get_post_meta( $id, '_bhela_inv_date', true ),
		'shares'     => bhela_bm_investor_shares( $id ),
		'share_pct'  => bhela_bm_investor_share_pct( $id ),
		'total_shares' => $cfg['total_shares'],
		'roi'        => $roi,
		'rows'       => $led['rows'],
		'by_month'   => $by_month,
		'position'   => bhela_bm_investor_position( $id ),
	);
}

/* =========================================================
 * The shortcode
 * ========================================================= */

function bhela_bm_portal_assets() {
	wp_register_style( 'bhela-bm-investor', BHELA_BM_URL . 'assets/investor.css', array(), BHELA_BM_VERSION );
}
add_action( 'wp_enqueue_scripts', 'bhela_bm_portal_assets' );

function bhela_bm_portal_shortcode() {
	wp_enqueue_style( 'bhela-bm-investor' );

	if ( ! is_user_logged_in() ) {
		return bhela_bm_portal_login();
	}
	$d = bhela_bm_portal_data();
	if ( ! $d ) {
		// A logged-in visitor who is not a linked investor. Deliberately vague: it
		// says nothing about whether investor accounts exist or who holds one.
		return '<div class="bhela-inv"><div class="bhela-inv__card"><p>'
			. esc_html__( 'এই অ্যাকাউন্টের সাথে কোনো বিনিয়োগ রেকর্ড যুক্ত নেই। BHELA অফিসে যোগাযোগ করুন।', 'bhela-booking' )
			. '</p><p><a class="bhela-inv__btn" href="' . esc_url( wp_logout_url( bhela_bm_portal_url() ) ) . '">'
			. esc_html__( 'লগ আউট', 'bhela-booking' ) . '</a></p></div></div>';
	}

	ob_start();
	bhela_bm_portal_render( $d );
	return ob_get_clean();
}
add_shortcode( 'bhela_investor_portal', 'bhela_bm_portal_shortcode' );

/** The sign-in form. */
function bhela_bm_portal_login() {
	$err = '';
	if ( isset( $_POST['bhela_inv_login'] ) && ! empty( $_POST['bhela_inv_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_inv_nonce'] ) ), 'bhela_inv_login' ) ) {

		$user = wp_signon( array(
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
			// Not sanitised on purpose: a password is compared, never stored or
			// echoed, and stripping characters would silently break valid ones.
			'user_password' => (string) ( $_POST['pwd'] ?? '' ),
			'remember'      => ! empty( $_POST['rememberme'] ),
		), is_ssl() );

		if ( is_wp_error( $user ) ) {
			// One message for every failure. Distinguishing "no such user" from "wrong
			// password" tells an attacker which half they got right.
			$err = __( 'ইউজারনেম বা পাসওয়ার্ড সঠিক নয়।', 'bhela-booking' );
		} else {
			wp_safe_redirect( bhela_bm_portal_url() );
			exit;
		}
	}

	ob_start();
	?>
	<div class="bhela-inv bhela-inv--login">
		<div class="bhela-inv__card">
			<h2><?php esc_html_e( 'বিনিয়োগকারী লগইন', 'bhela-booking' ); ?></h2>
			<?php if ( $err ) : ?>
				<p class="bhela-inv__err"><?php echo esc_html( $err ); ?></p>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'bhela_inv_login', 'bhela_inv_nonce' ); ?>
				<label><?php esc_html_e( 'ইউজারনেম বা ইমেইল', 'bhela-booking' ); ?>
					<input type="text" name="log" autocomplete="username" required></label>
				<label><?php esc_html_e( 'পাসওয়ার্ড', 'bhela-booking' ); ?>
					<input type="password" name="pwd" autocomplete="current-password" required></label>
				<label class="bhela-inv__check"><input type="checkbox" name="rememberme" value="1">
					<?php esc_html_e( 'মনে রাখুন', 'bhela-booking' ); ?></label>
				<button type="submit" name="bhela_inv_login" value="1" class="bhela-inv__btn"><?php esc_html_e( 'লগইন', 'bhela-booking' ); ?></button>
			</form>
			<p class="bhela-inv__muted"><a href="<?php echo esc_url( wp_lostpassword_url( bhela_bm_portal_url() ) ); ?>"><?php esc_html_e( 'পাসওয়ার্ড ভুলে গেছেন?', 'bhela-booking' ); ?></a></p>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/** Dashboard + statement. */
function bhela_bm_portal_render( $d ) {
	$money = 'bhela_bm_money';
	$types = bhela_bm_ledger_types();
	?>
	<div class="bhela-inv">
		<header class="bhela-inv__head">
			<div>
				<h2><?php echo esc_html( $d['name'] ); ?></h2>
				<p class="bhela-inv__muted">
					<?php if ( $d['code'] ) : ?><?php echo esc_html( $d['code'] ); ?> · <?php endif; ?>
					<?php
					printf(
						/* translators: 1: shares held, 2: total shares, 3: percentage */
						esc_html__( '%1$d of %2$d shares · %3$s%%', 'bhela-booking' ),
						(int) $d['shares'],
						(int) $d['total_shares'],
						esc_html( (string) $d['share_pct'] )
					);
					?>
				</p>
			</div>
			<div class="bhela-inv__actions">
				<button type="button" class="bhela-inv__btn bhela-inv__btn--ghost" onclick="window.print()">🖨️ <?php esc_html_e( 'স্টেটমেন্ট প্রিন্ট', 'bhela-booking' ); ?></button>
				<a class="bhela-inv__btn bhela-inv__btn--ghost" href="<?php echo esc_url( wp_logout_url( bhela_bm_portal_url() ) ); ?>"><?php esc_html_e( 'লগ আউট', 'bhela-booking' ); ?></a>
			</div>
		</header>

		<div class="bhela-inv__kpis">
			<?php
			$kpis = array(
				array( __( 'বিনিয়োগ', 'bhela-booking' ), $money( $d['roi']['investment'] ) ),
				array( __( 'ঘোষিত লাভ', 'bhela-booking' ), $money( $d['roi']['declared'] ) ),
				array( __( 'প্রাপ্ত', 'bhela-booking' ), $money( $d['roi']['received'] ) ),
				array( __( 'বকেয়া', 'bhela-booking' ), $money( $d['roi']['outstanding'] ) ),
				array( __( 'ROI (প্রাপ্ত)', 'bhela-booking' ), $d['roi']['roi'] . '%' ),
				array( __( 'ROI (ঘোষিত)', 'bhela-booking' ), $d['roi']['roi_declared'] . '%' ),
			);
			foreach ( $kpis as $k ) :
				?>
				<div class="bhela-inv__kpi"><span><?php echo esc_html( $k[0] ); ?></span><strong><?php echo esc_html( $k[1] ); ?></strong></div>
			<?php endforeach; ?>
		</div>

		<?php if ( $d['position']['last_payment'] ) : ?>
			<p class="bhela-inv__muted"><?php
				printf(
					/* translators: %s: date */
					esc_html__( 'সর্বশেষ পেমেন্ট: %s', 'bhela-booking' ),
					esc_html( mysql2date( 'j M Y', $d['position']['last_payment'] ) )
				);
			?></p>
		<?php endif; ?>

		<?php if ( $d['by_month'] ) : ?>
			<section class="bhela-inv__card">
				<h3><?php esc_html_e( 'মাসভিত্তিক লাভ', 'bhela-booking' ); ?></h3>
				<div class="bhela-inv__scroll">
					<table class="bhela-inv__table">
						<thead><tr>
							<th><?php esc_html_e( 'মাস', 'bhela-booking' ); ?></th>
							<th class="num"><?php esc_html_e( 'ঘোষিত লাভ', 'bhela-booking' ); ?></th>
							<th class="num"><?php esc_html_e( 'ROI', 'bhela-booking' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $d['by_month'] as $m => $amt ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'F Y', $m . '-01' ) ); ?></td>
								<td class="num"><?php echo esc_html( $money( $amt ) ); ?></td>
								<td class="num"><?php echo esc_html( $d['roi']['investment'] > 0 ? round( $amt / $d['roi']['investment'] * 100, 2 ) . '%' : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<section class="bhela-inv__card">
			<h3><?php esc_html_e( 'লেনদেনের বিবরণ', 'bhela-booking' ); ?></h3>
			<?php if ( ! $d['rows'] ) : ?>
				<p class="bhela-inv__muted"><?php esc_html_e( 'এখনো কোনো লেনদেন নেই।', 'bhela-booking' ); ?></p>
			<?php else : ?>
				<div class="bhela-inv__scroll">
					<table class="bhela-inv__table">
						<thead><tr>
							<th><?php esc_html_e( 'তারিখ', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'বিবরণ', 'bhela-booking' ); ?></th>
							<th class="num"><?php esc_html_e( 'পরিমাণ', 'bhela-booking' ); ?></th>
							<th class="num"><?php esc_html_e( 'ব্যালেন্স', 'bhela-booking' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $d['rows'] as $r ) : ?>
							<?php $undone = bhela_bm_ledger_reversal_of( $r['id'] ); ?>
							<tr<?php echo $undone ? ' class="is-void"' : ''; ?>>
								<td><?php echo esc_html( mysql2date( 'j M Y', $r['date'] ) ); ?></td>
								<td><?php echo esc_html( $types[ $r['type'] ]['label'] ?? $r['type'] ); ?>
									<?php if ( $r['note'] ) : ?><span class="bhela-inv__muted"> — <?php echo esc_html( $r['note'] ); ?></span><?php endif; ?>
									<?php if ( $undone ) : ?><span class="bhela-inv__void"><?php esc_html_e( 'বাতিল', 'bhela-booking' ); ?></span><?php endif; ?>
								</td>
								<td class="num"><?php echo esc_html( ( $r['signed'] > 0 ? '+' : '' ) . $money( $r['signed'] ) ); ?></td>
								<td class="num"><strong><?php echo esc_html( $money( $r['balance'] ) ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
			<p class="bhela-inv__muted"><?php esc_html_e( 'কোনো অঙ্ক নিয়ে প্রশ্ন থাকলে BHELA অফিসে যোগাযোগ করুন — সংশোধন সবসময় নতুন এন্ট্রি হিসেবে যুক্ত হয়, পুরোনো রেকর্ড মুছে নয়।', 'bhela-booking' ); ?></p>
		</section>
	</div>
	<?php
}
