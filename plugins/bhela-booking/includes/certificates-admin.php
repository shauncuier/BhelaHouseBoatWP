<?php
/**
 * 📜 Certificates — preview, issue, version, and the register of what has gone out.
 *
 * Issuing is a signing act, so the screen is built around seeing the figures before
 * committing to them: the preview is the exact array that will be frozen, and the Issue
 * button writes it unchanged. There is deliberately **no editable figure anywhere on
 * this screen** — that is how the brief's §18 ("the admin cannot change the amount") is
 * a property of the code rather than a rule somebody has to remember.
 *
 * Bulk issue exists because a period ends and thirty investments need the same document.
 * It previews every one and **names each refusal** rather than quietly issuing the ones
 * that worked: an investment missing from a batch with no explanation is how somebody
 * ends up never being given a certificate at all.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_cert_admin_url( $args = array() ) {
	return bhela_bm_admin_url( 'bhela-bm-certificates', $args );
}

function bhela_bm_cert_bail( $message ) {
	set_transient( 'bhela_bm_cert_err_' . get_current_user_id(), $message, 60 );
	wp_safe_redirect( bhela_bm_cert_admin_url() );
	exit;
}

function bhela_bm_cert_done( $message ) {
	set_transient( 'bhela_bm_cert_ok_' . get_current_user_id(), $message, 60 );
	wp_safe_redirect( bhela_bm_cert_admin_url() );
	exit;
}

/** The period the screen is working with. Blank means the investment's whole term. */
function bhela_bm_cert_request_window() {
	return array(
		'from' => isset( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : '',
		'to'   => isset( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : '',
	);
}

/* =========================================================
 * HANDLERS
 * ========================================================= */

function bhela_bm_cert_issue_post() {
	if ( ! current_user_can( 'bhela_investor_cert' ) ) {
		wp_die( esc_html__( 'You are not allowed to issue certificates.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_cert_issue' );

	$id = bhela_bm_cert_issue( array(
		'type'       => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '',
		'investment' => isset( $_POST['investment'] ) ? (int) $_POST['investment'] : 0,
		'window'     => bhela_bm_cert_request_window(),
		'note'       => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
		'replaces'   => isset( $_POST['replaces'] ) ? (int) $_POST['replaces'] : 0,
		'reason'     => isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '',
	) );

	if ( is_wp_error( $id ) ) {
		bhela_bm_cert_bail( $id->get_error_message() );
	}
	bhela_bm_cert_done( sprintf(
		/* translators: %s: certificate number */
		__( 'সনদ ইস্যু হয়েছে — %s', 'bhela-booking' ),
		(string) get_post_meta( $id, '_bhela_crt_number', true )
	) );
}
add_action( 'admin_post_bhela_bm_cert_issue', 'bhela_bm_cert_issue_post' );

/** Every eligible investment for one period, each refusal named. */
function bhela_bm_cert_bulk_post() {
	if ( ! current_user_can( 'bhela_investor_cert' ) ) {
		wp_die( esc_html__( 'You are not allowed to issue certificates.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_cert_bulk' );

	$type   = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
	$window = bhela_bm_cert_request_window();
	$made   = 0;
	$skip   = array();

	foreach ( bhela_bm_investments( 0, 'live' ) as $inv ) {
		$id = bhela_bm_cert_issue( array( 'type' => $type, 'investment' => $inv['id'], 'window' => $window ) );
		if ( is_wp_error( $id ) ) {
			// Named, not swallowed. A blank line in a batch is how somebody ends up
			// never getting a certificate and nobody noticing.
			$skip[] = $inv['code'] . ' (' . $inv['name'] . ') — ' . $id->get_error_message();
			continue;
		}
		$made++;
	}

	set_transient( 'bhela_bm_cert_skip_' . get_current_user_id(), $skip, 120 );
	bhela_bm_cert_done( sprintf(
		/* translators: 1: issued, 2: skipped */
		__( '%1$d টি সনদ ইস্যু হয়েছে, %2$d টি বাদ পড়েছে।', 'bhela-booking' ),
		$made,
		count( $skip )
	) );
}
add_action( 'admin_post_bhela_bm_cert_bulk', 'bhela_bm_cert_bulk_post' );

/* =========================================================
 * THE SCREEN
 * ========================================================= */

function bhela_bm_cert_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Certificates', 'bhela-booking' ),
		'📜 ' . __( 'Certificates', 'bhela-booking' ),
		'bhela_investor_cert',
		'bhela-bm-certificates',
		'bhela_bm_cert_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_cert_menu', 21 );

function bhela_bm_cert_page() {
	if ( ! current_user_can( 'bhela_investor_cert' ) ) {
		wp_die( esc_html__( 'You are not allowed to issue certificates.', 'bhela-booking' ) );
	}

	$money      = 'bhela_bm_money';
	$types      = bhela_bm_cert_types();
	$type       = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'investment';
	$type       = isset( $types[ $type ] ) ? $type : 'investment';
	$investment = isset( $_GET['investment'] ) ? (int) $_GET['investment'] : 0;
	$window     = bhela_bm_cert_request_window();

	$err  = get_transient( 'bhela_bm_cert_err_' . get_current_user_id() );
	$ok   = get_transient( 'bhela_bm_cert_ok_' . get_current_user_id() );
	$skip = get_transient( 'bhela_bm_cert_skip_' . get_current_user_id() );
	delete_transient( 'bhela_bm_cert_err_' . get_current_user_id() );
	delete_transient( 'bhela_bm_cert_ok_' . get_current_user_id() );
	delete_transient( 'bhela_bm_cert_skip_' . get_current_user_id() );

	$preview = $investment ? bhela_bm_cert_preview( $type, $investment, $window ) : null;
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'📜',
			__( 'Certificates', 'bhela-booking' ),
			__( 'বিনিয়োগ সনদ আর লাভের সনদ। ইস্যু করার মুহূর্তে হিসাব জমে যায় — পরে আবার প্রিন্ট করলেও একই থাকবে। সংশোধন করতে হলে নতুন সংস্করণ ইস্যু হয়।', 'bhela-booking' )
		);
		?>

		<?php if ( $err ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
		<?php endif; ?>
		<?php if ( $ok ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $ok ); ?></p></div>
		<?php endif; ?>
		<?php if ( is_array( $skip ) && $skip ) : ?>
			<div class="bha-callout bha-callout--attention">
				<p><strong><?php esc_html_e( 'যেগুলোর সনদ ইস্যু হয়নি', 'bhela-booking' ); ?></strong></p>
				<ul>
					<?php foreach ( $skip as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'ধাপ ১ — কী ইস্যু করবেন', 'bhela-booking' ); ?></h2>
			<?php // GET, and with no hidden post_type: this page hangs under admin.php (§13.14). ?>
			<form method="get" class="bha-bar">
				<input type="hidden" name="page" value="bhela-bm-certificates">
				<label>
					<?php esc_html_e( 'ধরন', 'bhela-booking' ); ?>
					<select name="type">
						<?php foreach ( $types as $key => $def ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $def['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'বিনিয়োগ', 'bhela-booking' ); ?>
					<select name="investment">
						<option value="0"><?php esc_html_e( '— বেছে নিন —', 'bhela-booking' ); ?></option>
						<?php foreach ( bhela_bm_investments( 0, '' ) as $inv ) : ?>
							<?php if ( in_array( $inv['status'], array( 'draft', 'cancelled' ), true ) ) { continue; } ?>
							<option value="<?php echo (int) $inv['id']; ?>" <?php selected( $investment, (int) $inv['id'] ); ?>>
								<?php echo esc_html( $inv['code'] . ' · ' . $inv['name'] . ' · ' . $money( $inv['principal'] ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'শুরু', 'bhela-booking' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $window['from'] ); ?>"></label>
				<label><?php esc_html_e( 'শেষ', 'bhela-booking' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $window['to'] ); ?>"></label>
				<button class="button"><?php esc_html_e( 'দেখুন', 'bhela-booking' ); ?></button>
			</form>
			<p class="description">
				<?php esc_html_e( 'বিনিয়োগ সনদে সময়ের সীমা লাগে না। লাভের সনদে সীমা না দিলে পুরো মেয়াদ ধরা হয়, আর যে সময়কালগুলোর হিসাব অনুমোদিত হয়েছে কেবল সেগুলোই আসে।', 'bhela-booking' ); ?>
			</p>
		</div>

		<?php if ( is_wp_error( $preview ) ) : ?>
			<div class="bha-callout bha-callout--attention">
				<p><strong><?php esc_html_e( 'এখন সনদ ইস্যু করা যাবে না', 'bhela-booking' ); ?></strong></p>
				<p><?php echo esc_html( $preview->get_error_message() ); ?></p>
			</div>
		<?php elseif ( is_array( $preview ) ) : ?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'ধাপ ২ — যা সনদে বসবে', 'bhela-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'নিচের সংখ্যাগুলোই হুবহু সনদে জমা হবে। ইস্যুর পরে আর বদলাবে না, আর এখানে কোনো অঙ্ক হাতে লেখার জায়গা নেই — তাই ভুল হলে সংশোধন মানে নতুন সংস্করণ।', 'bhela-booking' ); ?></p>

				<?php if ( 'investment' === $type ) : ?>
					<div class="bha-cards">
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'মূলধন', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $preview['principal'] ) ); ?></span></div>
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'মেয়াদ', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( $preview['months'] . ' ' . __( 'মাস', 'bhela-booking' ) ); ?></span></div>
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'হার', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( $preview['rate'] . '%' ); ?></span></div>
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'চুক্তি', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( $preview['agreement_ref'] ? $preview['agreement_ref'] : '—' ); ?></span></div>
					</div>
				<?php else : ?>
					<div class="bha-cards">
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'মোট লাভ', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $preview['gross'] ) ); ?></span></div>
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'সমন্বয়', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $preview['adjustments'] ) ); ?></span></div>
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'নিট', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $preview['net'] ) ); ?></span></div>
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'প্রদত্ত', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $preview['paid'] ) ); ?></span></div>
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'বকেয়া', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $preview['due'] ) ); ?></span></div>
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'অবস্থা', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( $preview['status']['en'] ); ?></span></div>
					</div>

					<?php if ( ! empty( $preview['other_investments'] ) ) : ?>
						<p class="description">
							<?php esc_html_e( 'এই বিনিয়োগকারীর একাধিক চলমান বিনিয়োগ আছে, আর খাতায় কোন টাকা কোন চুক্তির বিপরীতে দেওয়া হয়েছে তা আলাদা করে লেখা থাকে না — তাই "প্রদত্ত" ওই সময়ে তাঁকে দেওয়া মোট অর্থ। সনদেও এটি লেখা থাকবে।', 'bhela-booking' ); ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'bhela_bm_cert_issue' ); ?>
					<input type="hidden" name="action" value="bhela_bm_cert_issue">
					<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>">
					<input type="hidden" name="investment" value="<?php echo (int) $investment; ?>">
					<input type="hidden" name="from" value="<?php echo esc_attr( $window['from'] ); ?>">
					<input type="hidden" name="to" value="<?php echo esc_attr( $window['to'] ); ?>">
					<table class="form-table">
						<tr>
							<th><label for="cert-note"><?php esc_html_e( 'সনদে অতিরিক্ত নোট', 'bhela-booking' ); ?></label></th>
							<td><textarea name="note" id="cert-note" class="large-text" rows="2"></textarea></td>
						</tr>
						<tr>
							<th><label for="cert-replaces"><?php esc_html_e( 'পুরোনো সনদ সংশোধন', 'bhela-booking' ); ?></label></th>
							<td>
								<select name="replaces" id="cert-replaces">
									<option value="0"><?php esc_html_e( '— নতুন সনদ —', 'bhela-booking' ); ?></option>
									<?php foreach ( bhela_bm_cert_rows( (int) $preview['investor'], $type ) as $old ) : ?>
										<?php
										// Per investor, so it would otherwise offer the other
										// investments' certificates too — and a version may only
										// replace a certificate about the SAME investment. One
										// with no base predates versioning and has nothing to
										// take the next version of.
										if ( $old['superseded']
											|| (int) $old['investment'] !== (int) $investment
											|| '' === $old['base'] ) {
											continue;
										}
										?>
										<option value="<?php echo (int) $old['id']; ?>"><?php echo esc_html( $old['number'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<input type="text" name="reason" class="regular-text" placeholder="<?php esc_attr_e( 'কেন সংশোধন', 'bhela-booking' ); ?>">
								<p class="description"><?php esc_html_e( 'সংশোধন করলে একই নম্বরের পরের সংস্করণ (…-V2) ইস্যু হয়। পুরোনোটি মুছে যায় না — তাতে লেখা থাকে কোন সংস্করণ এটির জায়গা নিয়েছে।', 'bhela-booking' ); ?></p>
							</td>
						</tr>
					</table>
					<p><button class="button button-primary"><?php esc_html_e( 'সনদ ইস্যু করুন', 'bhela-booking' ); ?></button></p>
				</form>
			</div>
		<?php endif; ?>

		<?php if ( 'profit' === $type && $window['from'] && $window['to'] ) : ?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'সবার জন্য একসাথে', 'bhela-booking' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'নির্বাচিত সময়ের জন্য প্রতিটি চলমান বিনিয়োগের একটি করে সনদ। যেগুলোর ইস্যু করা যাবে না, তাদের নাম ও কারণ দেখানো হবে।', 'bhela-booking' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'bhela_bm_cert_bulk' ); ?>
					<input type="hidden" name="action" value="bhela_bm_cert_bulk">
					<input type="hidden" name="type" value="profit">
					<input type="hidden" name="from" value="<?php echo esc_attr( $window['from'] ); ?>">
					<input type="hidden" name="to" value="<?php echo esc_attr( $window['to'] ); ?>">
					<p><button class="button"><?php esc_html_e( 'সবার সনদ ইস্যু করুন', 'bhela-booking' ); ?></button></p>
				</form>
			</div>
		<?php endif; ?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'ইস্যু হওয়া সনদ', 'bhela-booking' ); ?></h2>
			<table class="widefat striped bha-table">
				<thead><tr>
					<th><?php esc_html_e( 'নম্বর', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'ধরন', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Investment ID', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'ইস্যু', 'bhela-booking' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php $issued = bhela_bm_cert_rows(); ?>
				<?php if ( ! $issued ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'এখনো কোনো সনদ ইস্যু হয়নি।', 'bhela-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $issued as $row ) : ?>
					<tr<?php echo $row['superseded'] ? ' style="opacity:.6"' : ''; ?>>
						<td class="bha-plain"><?php echo esc_html( $row['number'] ); ?></td>
						<td><?php echo esc_html( $types[ $row['type'] ]['label'] ?? $row['type'] ); ?></td>
						<td><?php echo esc_html( $row['snapshot']['name'] ?? '' ); ?></td>
						<td class="bha-plain"><?php echo esc_html( $row['snapshot']['investment_id'] ?? '' ); ?></td>
						<td class="bha-plain"><?php echo esc_html( mysql2date( 'j M Y', $row['issued'] ) ); ?></td>
						<td>
							<a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( bhela_bm_cert_url( $row['id'] ) ); ?>">
								<?php esc_html_e( 'প্রিন্ট', 'bhela-booking' ); ?>
							</a>
							<a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( bhela_bm_verify_url( $row['number'] ) ); ?>">
								<?php esc_html_e( 'যাচাই', 'bhela-booking' ); ?>
							</a>
							<?php if ( $row['superseded'] ) : ?>
								<span class="description">
									<?php
									printf(
										/* translators: %s: the replacing number */
										esc_html__( 'প্রতিস্থাপিত — %s', 'bhela-booking' ),
										esc_html( $row['superseded_number'] )
									);
									?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">
				<?php esc_html_e( 'প্রিন্ট লিংকে গোপন চাবি থাকে — বিনিয়োগকারীকে বা তাঁর ব্যাংককে দেওয়া যায়। যাচাই লিংক সবার জন্য খোলা, কিন্তু সেখানে কোনো টাকার অঙ্ক দেখানো হয় না।', 'bhela-booking' ); ?>
			</p>
		</div>
	</div>
	<?php
}
