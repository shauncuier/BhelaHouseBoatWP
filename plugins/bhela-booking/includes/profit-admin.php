<?php
/**
 * ➗ Profit Calculation — what each investment has earned, and the approval that makes
 * it owed.
 *
 * The brief's §17 puts three steps between a calculation and a certificate: calculated,
 * reviewed, approved. This screen is the middle one made visible. Everything on it is a
 * reading until somebody presses Approve; approving is what writes the ledger row, and
 * from that moment the money is owed, appears on the investor's own portal, and comes
 * off the month's bottom line.
 *
 * Two things the screen is careful about:
 *
 * - **It shows periods that have ENDED, and nothing else.** Accruing tomorrow's profit
 *   today would put money on a statement the business has not earned.
 * - **An already-posted period is shown, not hidden.** Hiding it would make the screen
 *   look like nothing had happened and invite somebody to run the month again — which
 *   the engine would refuse, but the honest answer is to say so on screen rather than
 *   in an error.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_profit_url( $args = array() ) {
	return bhela_bm_admin_url( 'bhela-bm-profit', $args );
}

/* =========================================================
 * THE APPROVAL
 * ========================================================= */

/**
 * Post every period the operator ticked.
 *
 * Walks the same schedule the screen rendered rather than trusting an amount from the
 * request — a posted figure must come from the engine, never from a form field, or §18
 * of the brief ("the admin cannot change the amount") would be a claim rather than a
 * property.
 */
function bhela_bm_profit_approve_post() {
	if ( ! current_user_can( 'bhela_investor_profit' ) ) {
		wp_die( esc_html__( 'You are not allowed to approve profit.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_profit_approve' );

	$keys = isset( $_POST['period'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['period'] ) ) : array();
	$upto = isset( $_POST['upto'] ) ? sanitize_text_field( wp_unslash( $_POST['upto'] ) ) : '';
	$made = 0;
	$fail = array();

	foreach ( bhela_bm_investments( 0, 'live' ) as $inv ) {
		foreach ( bhela_bm_profit_accrue( $inv, $upto )['periods'] as $p ) {
			if ( ! in_array( $p['key'], $keys, true ) ) {
				continue;
			}
			$r = bhela_bm_profit_post( $inv, $p );
			if ( is_wp_error( $r ) ) {
				$fail[] = $inv['code'] . ' ' . $p['from'] . ': ' . $r->get_error_message();
				continue;
			}
			$made++;
		}
	}

	set_transient( 'bhela_bm_profit_msg_' . get_current_user_id(), array( $made, $fail ), 120 );
	wp_safe_redirect( bhela_bm_profit_url( $upto ? array( 'upto' => $upto ) : array() ) );
	exit;
}
add_action( 'admin_post_bhela_bm_profit_approve', 'bhela_bm_profit_approve_post' );

/* =========================================================
 * THE SCREEN
 * ========================================================= */

function bhela_bm_profit_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'capital' ),
		__( 'Profit Calculation', 'bhela-booking' ),
		'➗ ' . __( 'Profit', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-profit',
		'bhela_bm_profit_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_profit_menu', 21 );

function bhela_bm_profit_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You are not allowed to see profit calculations.', 'bhela-booking' ) );
	}

	$money   = 'bhela_bm_money';
	$methods = bhela_bm_profit_methods();
	$upto    = isset( $_GET['upto'] ) ? bhela_bm_report_date( wp_unslash( $_GET['upto'] ) ) : '';
	$upto    = '' === $upto ? current_time( 'Y-m-d' ) : $upto;

	$msg = get_transient( 'bhela_bm_profit_msg_' . get_current_user_id() );
	delete_transient( 'bhela_bm_profit_msg_' . get_current_user_id() );

	$live     = bhela_bm_investments( 0, 'live' );
	$rows     = array();
	$due      = 0;
	$posted   = 0;
	$unposted = 0;
	foreach ( $live as $inv ) {
		$acc = bhela_bm_profit_accrue( $inv, $upto );
		foreach ( $acc['periods'] as $p ) {
			$p['inv'] = $inv;
			$rows[]   = $p;
		}
		$due      += $acc['due'];
		$posted   += $acc['posted'];
		$unposted += $acc['unposted'];
	}
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'➗',
			__( 'Profit Calculation', 'bhela-booking' ),
			__( 'প্রতিটি সক্রিয় বিনিয়োগ কোন সময়কালে কত লাভ করেছে। অনুমোদন না করা পর্যন্ত কারো খাতায় কিছু যোগ হয় না।', 'bhela-booking' )
		);
		?>

		<?php if ( is_array( $msg ) ) : ?>
			<?php
			// "0 periods approved" in green read as success after a double-click or a
			// back-button resubmit, when what happened is that nothing was posted —
			// because it already had been, or because nothing was ticked.
			$made = (int) $msg[0];
			?>
			<div class="notice <?php echo $made > 0 ? 'notice-success' : 'notice-warning'; ?>">
				<p>
					<?php
					if ( $made > 0 ) {
						printf(
							/* translators: %d: periods posted */
							esc_html__( '%d টি সময়কাল অনুমোদিত হয়েছে।', 'bhela-booking' ),
							$made
						);
					} else {
						esc_html_e( 'নতুন কোনো সময়কাল অনুমোদিত হয়নি — বাছাই করা সময়কালগুলো আগেই অনুমোদিত, অথবা কিছুই টিক দেওয়া হয়নি।', 'bhela-booking' );
					}
					?>
				</p>
				<?php if ( ! empty( $msg[1] ) ) : ?>
					<ul>
						<?php foreach ( $msg[1] as $line ) : ?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( 'fixed' !== bhela_bm_investor_model() ) : ?>
			<div class="bha-callout bha-callout--attention">
				<p><?php esc_html_e( 'মডেল এখনো শেয়ারভিত্তিক। এখানে অনুমোদিত লাভ খাতায় যোগ হবে, কিন্তু মাসিক হিসাবে খরচ হিসেবে বাদ যাবে না — ⚙️ Settings-এ মডেল বদলান।', 'bhela-booking' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="get" class="bha-bar">
			<input type="hidden" name="page" value="bhela-bm-profit">
			<label>
				<?php esc_html_e( 'এই তারিখ পর্যন্ত', 'bhela-booking' ); ?>
				<input type="date" name="upto" value="<?php echo esc_attr( $upto ); ?>">
			</label>
			<button class="button"><?php esc_html_e( 'হিসাব করুন', 'bhela-booking' ); ?></button>
		</form>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'সক্রিয় বিনিয়োগ', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( (string) count( $live ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'মোট হিসাবযোগ্য', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $due ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'অনুমোদিত', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $posted ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'অপেক্ষমাণ', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $unposted ) ); ?></span></div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bhela_bm_profit_approve' ); ?>
			<input type="hidden" name="action" value="bhela_bm_profit_approve">
			<input type="hidden" name="upto" value="<?php echo esc_attr( $upto ); ?>">

			<div class="bha-panel">
				<table class="widefat striped bha-table">
					<thead><tr>
						<th style="width:34px">
							<?php if ( current_user_can( 'bhela_investor_profit' ) ) : ?>
								<input type="checkbox" aria-label="<?php esc_attr_e( 'সব বাছাই করুন', 'bhela-booking' ); ?>"
									onclick="var c=this.checked;this.closest('table').querySelectorAll('input[name=\'period[]\']').forEach(function(b){b.checked=c;});">
							<?php endif; ?>
						</th>
						<th><?php esc_html_e( 'Investment ID', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'সময়কাল', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'পদ্ধতি', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'লাভ', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'অবস্থা', 'bhela-booking' ); ?></th>
					</tr></thead>
					<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'এই তারিখ পর্যন্ত হিসাব করার মতো কোনো শেষ হওয়া সময়কাল নেই।', 'bhela-booking' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $p ) : ?>
						<tr>
							<td>
								<?php if ( ! $p['row'] && current_user_can( 'bhela_investor_profit' ) ) : ?>
									<?php
									// Unticked by default. Approving writes a ledger row that can
									// only be reversed, never removed; with every due period
									// pre-ticked, one click posted a whole year at once.
									?>
									<input type="checkbox" name="period[]" value="<?php echo esc_attr( $p['key'] ); ?>">
								<?php endif; ?>
							</td>
							<td class="bha-plain"><?php echo esc_html( $p['inv']['code'] ); ?></td>
							<td><?php echo esc_html( $p['inv']['name'] ); ?></td>
							<td class="bha-plain"><?php echo esc_html( mysql2date( 'j M Y', $p['from'] ) . ' — ' . mysql2date( 'j M Y', $p['to'] ) ); ?></td>
							<td><?php echo esc_html( $methods[ $p['inv']['method'] ]['label'] ?? $p['inv']['method'] ); ?></td>
							<td class="bha-num">
								<?php echo esc_html( $money( $p['amount'] ) ); ?>
								<?php if ( $p['row'] && ! empty( $p['drift'] ) ) : ?>
									<span class="bha-sub bha-flag"><?php
									printf(
										/* translators: %s: what the terms compute today */
										esc_html__( 'শর্ত এখন %s বলে — প্রয়োজনে সমন্বয় করুন', 'bhela-booking' ),
										esc_html( $money( $p['computed'] ) )
									);
									?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								echo $p['row']
									? bhela_bm_status_pill( __( 'অনুমোদিত', 'bhela-booking' ), 'good' ) // phpcs:ignore WordPress.Security.EscapeOutput
									: bhela_bm_status_pill( __( 'অপেক্ষমাণ', 'bhela-booking' ), 'attention' ); // phpcs:ignore WordPress.Security.EscapeOutput
								?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( current_user_can( 'bhela_investor_profit' ) ) : ?>
				<p>
					<button class="button button-primary" <?php disabled( $unposted <= 0 ); ?>><?php esc_html_e( 'টিক দেওয়া সময়কালগুলো অনুমোদন করুন', 'bhela-booking' ); ?></button>
				</p>
				<p class="description">
					<?php esc_html_e( 'অনুমোদন করলে প্রতিটি সময়কালের জন্য একটি করে খাতার এন্ট্রি লেখা হয় — মুছে ফেলা যায় না, ভুল হলে বিপরীত এন্ট্রি দিয়ে সংশোধন করতে হয়। একই সময়কাল দুবার অনুমোদন করা যায় না।', 'bhela-booking' ); ?>
				</p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'অনুমোদনের অনুমতি আপনার নেই — হিসাব দেখতে পারছেন, অনুমোদন করতে পারবেন না।', 'bhela-booking' ); ?></p>
			<?php endif; ?>
		</form>
	</div>
	<?php
}
