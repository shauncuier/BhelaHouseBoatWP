<?php
/**
 * The Registrations screen — where a portal application becomes a login.
 *
 * Split from `includes/investor-signup.php` for the reason the valuation module is
 * split that way: the decision logic is testable without a screen, and the screen is a
 * rendering of a queue somebody else filled.
 *
 * Viewing the queue needs `bhela_investors_view`, because knowing who has applied is
 * ordinary investor-relations work. Acting on one needs `bhela_investor_signup`,
 * because approving mints an account that reads real money.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_signup_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Registrations', 'bhela-booking' ),
		'📝 ' . __( 'Registrations', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-signups',
		'bhela_bm_signup_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_signup_menu', 21 );

/**
 * How many are waiting — the bubble beside the menu row.
 *
 * A queue nobody is told about is a queue nobody works, and an investor waiting on
 * approval has no way to chase it except by ringing the office.
 */
function bhela_bm_signup_pending_count() {
	$n = 0;
	foreach ( bhela_bm_signups( 'pending', 200 ) as $row ) {
		$n++;
	}
	return $n;
}

function bhela_bm_signup_menu_bubble() {
	global $submenu;
	$parent = bhela_bm_menu_parent( 'investors' );
	if ( empty( $submenu[ $parent ] ) || ! current_user_can( 'bhela_investors_view' ) ) {
		return;
	}
	$n = bhela_bm_signup_pending_count();
	if ( ! $n ) {
		return;
	}
	foreach ( $submenu[ $parent ] as $i => $item ) {
		if ( isset( $item[2] ) && 'bhela-bm-signups' === $item[2] ) {
			$submenu[ $parent ][ $i ][0] .= sprintf(
				' <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
				$n
			);
			break;
		}
	}
}
add_action( 'admin_menu', 'bhela_bm_signup_menu_bubble', 99 );

/* =========================================================
 * POST HANDLER
 * ========================================================= */

function bhela_bm_signup_admin_post() {
	if ( ! is_admin() || empty( $_POST['bhela_sgn_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_sgn_nonce'] ) ), 'bhela_bm_signup_act' ) ) {
		return;
	}
	$id     = (int) ( $_POST['signup'] ?? 0 );
	$reason = sanitize_textarea_field( wp_unslash( $_POST['sgn_reason'] ?? '' ) );

	if ( ! empty( $_POST['sgn_approve'] ) ) {
		$res = bhela_bm_signup_approve( $id, ! empty( $_POST['sgn_notify'] ), ! empty( $_POST['sgn_confirmed'] ) );
		if ( ! is_wp_error( $res ) && ! empty( $res['created'] ) ) {
			// Say it out loud. A record created from a registration has no shares and
			// no money on it, and somebody has to go and put them there.
			$res = new WP_Error( 'created', __( 'অনুমোদিত। নতুন বিনিয়োগকারী রেকর্ড তৈরি হয়েছে — শেয়ার ও বিনিয়োগের অঙ্ক এখনো শূন্য, রেকর্ডে গিয়ে বসান।', 'bhela-booking' ) );
		}
		bhela_bm_investor_notice( $res );
		return;
	}
	if ( ! empty( $_POST['sgn_reject'] ) ) {
		bhela_bm_investor_notice( bhela_bm_signup_reject( $id, $reason, ! empty( $_POST['sgn_notify'] ) ) );
		return;
	}
	if ( ! empty( $_POST['sgn_delete'] ) ) {
		bhela_bm_investor_notice( bhela_bm_signup_delete( $id ) );
	}
}
add_action( 'admin_init', 'bhela_bm_signup_admin_post' );

/**
 * Everything one application says, group by group.
 *
 * The form asks for the same thirty-odd fields the metabox does, so the queue has to
 * show them — an approver deciding whether this is really the person who signed the
 * paper form needs the father's name and the NID in front of them, not four columns
 * and a shrug. Blank fields are left out rather than printed as dashes.
 *
 * Where the application disagrees with a record that already holds a value, both are
 * shown: bhela_bm_signup_copy_to_record() keeps the record's, and the point of
 * showing the difference is that somebody can decide the record is the one that is
 * wrong.
 */
function bhela_bm_signup_detail( $row, $investor = 0 ) {
	$secret = bhela_bm_investor_secret_fields();
	?>
	<details class="bha-sgn-detail">
		<summary><?php esc_html_e( 'Full application', 'bhela-booking' ); ?></summary>
		<?php foreach ( bhela_bm_signup_groups() as $group ) : ?>
			<?php
			// Skip a section the applicant left entirely blank.
			$any = false;
			foreach ( $group['fields'] as $key => $def ) {
				if ( '' !== (string) ( $row[ $key ] ?? '' ) ) {
					$any = true;
					break;
				}
			}
			if ( ! $any ) {
				continue;
			}
			?>
			<h4><?php echo esc_html( $group['label'] ); ?></h4>
			<table class="widefat striped bha-table">
				<tbody>
				<?php foreach ( $group['fields'] as $key => $def ) : ?>
					<?php
					$val = (string) ( $row[ $key ] ?? '' );
					if ( '' === $val ) {
						continue;
					}
					$on_record = $investor ? (string) get_post_meta( $investor, '_bhela_inv_' . $key, true ) : '';
					?>
					<tr>
						<th scope="row" style="width:16em"><?php echo esc_html( $def['label'] ); ?></th>
						<td>
							<?php if ( 'file' === ( $def['type'] ?? '' ) ) : ?>
								<a href="<?php echo esc_url( $val ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'open the file', 'bhela-booking' ); ?></a>
							<?php elseif ( 'select' === ( $def['type'] ?? '' ) ) : ?>
								<?php echo esc_html( $def['options'][ $val ] ?? $val ); ?>
							<?php else : ?>
								<?php echo esc_html( $val ); ?>
							<?php endif; ?>
							<?php if ( '' !== $on_record && $on_record !== $val ) : ?>
								<br><span class="description"><?php
									printf(
										/* translators: %s: the value already on the investor record */
										esc_html__( 'the record says: %s', 'bhela-booking' ),
										esc_html( in_array( $key, $secret, true ) ? __( '(a different value)', 'bhela-booking' ) : $on_record )
									);
								?> — <?php esc_html_e( 'the record is kept.', 'bhela-booking' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
	</details>
	<?php
}

/* =========================================================
 * THE SCREEN
 * ========================================================= */

function bhela_bm_signup_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'bhela-booking' ) );
	}
	$can    = current_user_can( 'bhela_investor_signup' );
	$states = bhela_bm_signup_states();
	$rows   = bhela_bm_signups();
	$reg    = bhela_bm_signup_url();
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'📝',
			__( 'Investor Registrations', 'bhela-booking' ),
			__( 'Applications from the public registration page. A number here has been proved by a one-time code; nothing else about the applicant has.', 'bhela-booking' )
		);
		bhela_bm_investor_print_notice();
		?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'The two pages', 'bhela-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Registration:', 'bhela-booking' ); ?>
				<a href="<?php echo esc_url( $reg ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $reg ); ?></a>
				&nbsp;·&nbsp;
				<?php esc_html_e( 'Portal:', 'bhela-booking' ); ?>
				<a href="<?php echo esc_url( bhela_bm_portal_url() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( bhela_bm_portal_url() ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'Shortcodes, if you build the pages yourself: [bhela_investor_register] and [bhela_investor_portal].', 'bhela-booking' ); ?></p>
			<?php if ( ! get_page_by_path( 'investor-register' ) ) : ?>
				<div class="bha-callout bha-callout--attention"><?php esc_html_e( 'There is no page at /investor-register/. Create one carrying [bhela_investor_register], or the link above goes nowhere.', 'bhela-booking' ); ?></div>
			<?php endif; ?>
		</div>

		<?php
		// Sign-in is by a code sent to a mobile number. A record whose number cannot
		// carry one has a login that can never be used, and nothing else on the site
		// would ever say so.
		$stuck = bhela_bm_investor_unreachable();
		if ( $stuck ) :
			?>
			<div class="bha-callout bha-callout--attention">
				<strong><?php esc_html_e( 'These investors have a portal login but no usable mobile number, so no code can reach them:', 'bhela-booking' ); ?></strong>
				<ul>
					<?php foreach ( $stuck as $sid ) : ?>
						<li><a href="<?php echo esc_url( get_edit_post_link( $sid ) ); ?>"><?php echo esc_html( get_the_title( $sid ) ); ?></a>
							— <?php echo esc_html( (string) get_post_meta( $sid, '_bhela_inv_mobile', true ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'Applications', 'bhela-booking' ); ?></h2>
			<?php if ( ! $rows ) : ?>
				<p class="description"><?php esc_html_e( 'Nothing yet.', 'bhela-booking' ); ?></p>
			<?php else : ?>
				<table class="widefat striped bha-table">
					<thead><tr>
						<th><?php esc_html_e( 'Applicant', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Mobile', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Proved by', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Received', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Action', 'bhela-booking' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<?php
						$state = $states[ $r['state'] ] ?? $states[''];
						$match = bhela_bm_investor_by_mobile( $r['mobile'] );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $r['name'] ); ?></strong>
								<?php if ( $r['email'] ) : ?><br><span class="description"><?php echo esc_html( $r['email'] ); ?></span><?php endif; ?>
								<?php if ( ! empty( $r['email_clash'] ) ) : ?>
									<br><span class="description"><strong><?php esc_html_e( 'That address already belongs to another WordPress account, so the new login was created without one — nothing was linked to it.', 'bhela-booking' ); ?></strong></span>
								<?php endif; ?>
								<?php if ( $r['note'] ) : ?><br><span class="description">“<?php echo esc_html( $r['note'] ); ?>”</span><?php endif; ?>
								<?php bhela_bm_signup_detail( $r, $match ); ?>
							</td>
							<td>
								<?php echo esc_html( $r['mobile'] ); ?>
								<?php if ( $match ) : ?>
									<br><span class="description">↳ <a href="<?php echo esc_url( get_edit_post_link( $match ) ); ?>"><?php echo esc_html( get_the_title( $match ) ); ?></a></span>
								<?php else : ?>
									<br><span class="description"><?php esc_html_e( 'no matching record', 'bhela-booking' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								echo 'sms' === $r['channel']
									? esc_html__( 'SMS', 'bhela-booking' )
									: esc_html__( 'email fallback', 'bhela-booking' );
								?>
								<?php if ( 'sms' !== $r['channel'] ) : ?>
									<br><span class="description"><?php esc_html_e( 'proves an address, not the handset', 'bhela-booking' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="bha-plain"><?php echo esc_html( $r['at'] ? mysql2date( 'j M Y, g:i a', $r['at'] ) : '—' ); ?>
								<br><span class="description"><?php echo esc_html( $r['ip'] ); ?></span></td>
							<td><?php echo wp_kses_post( bhela_bm_status_pill( $state['label'], $state['tone'] ) ); ?>
								<?php if ( $r['reason'] ) : ?><br><span class="description"><?php echo esc_html( $r['reason'] ); ?></span><?php endif; ?>
							</td>
							<td>
								<?php if ( ! $can ) : ?>
									<span class="description"><?php esc_html_e( 'view only', 'bhela-booking' ); ?></span>
								<?php elseif ( 'pending' === $r['state'] ) : ?>
									<form method="post" class="bha-inline">
										<?php wp_nonce_field( 'bhela_bm_signup_act', 'bhela_sgn_nonce' ); ?>
										<input type="hidden" name="signup" value="<?php echo esc_attr( $r['id'] ); ?>">
										<?php if ( $match && 'sms' !== $r['channel'] ) : ?>
											<div class="bha-callout bha-callout--attention">
												<strong><?php esc_html_e( 'This number is already on the register, and the code did NOT go by SMS.', 'bhela-booking' ); ?></strong>
												<?php esc_html_e( 'An emailed code proves an address, not that this person holds the handset — so nothing here shows they are the investor whose record it matches. Ring the number on the record before approving.', 'bhela-booking' ); ?>
												<p><label><input type="checkbox" name="sgn_confirmed" value="1">
													<?php esc_html_e( 'I have confirmed this person by phone.', 'bhela-booking' ); ?></label></p>
											</div>
										<?php endif; ?>
										<p><label><input type="checkbox" name="sgn_notify" value="1" checked>
											<?php esc_html_e( 'Tell them', 'bhela-booking' ); ?></label></p>
										<p><input type="text" name="sgn_reason" class="regular-text"
											placeholder="<?php esc_attr_e( 'Reason, if rejecting', 'bhela-booking' ); ?>"></p>
										<p>
											<button class="button button-primary" name="sgn_approve" value="1"><?php esc_html_e( 'Approve', 'bhela-booking' ); ?></button>
											<button class="button" name="sgn_reject" value="1"><?php esc_html_e( 'Reject', 'bhela-booking' ); ?></button>
										</p>
									</form>
								<?php else : ?>
									<?php if ( $r['investor'] ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $r['investor'] ) ); ?>"><?php esc_html_e( 'Open record', 'bhela-booking' ); ?></a><br>
									<?php endif; ?>
									<form method="post" class="bha-inline">
										<?php wp_nonce_field( 'bhela_bm_signup_act', 'bhela_sgn_nonce' ); ?>
										<input type="hidden" name="signup" value="<?php echo esc_attr( $r['id'] ); ?>">
										<button class="button-link" name="sgn_delete" value="1"><?php esc_html_e( 'Delete application', 'bhela-booking' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Approving an application with no matching record creates one with zero shares and zero invested. Shares are entered by the office from a signed form — never from what somebody typed into a web page.', 'bhela-booking' ); ?></p>
			<p class="description"><?php esc_html_e( 'Approval copies the details onto the record, but only into fields that are still empty. Anything the office has already typed in is kept, and the difference is shown above so you can decide which is right.', 'bhela-booking' ); ?></p>
		</div>
	</div>
	<?php
}
