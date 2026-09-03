<?php
/**
 * The Valuation and Share Issue screens.
 *
 * Split from `includes/valuation.php` and `includes/share-issue.php` for the reason the
 * rest of the module is split that way: the arithmetic is testable without a screen,
 * and the screen is a rendering of figures somebody else computed.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_valuation_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Valuation', 'bhela-booking' ),
		'💎 ' . __( 'Valuation', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-valuation',
		'bhela_bm_valuation_page'
	);
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Share Issue', 'bhela-booking' ),
		'🪙 ' . __( 'Share Issue', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-share-issue',
		'bhela_bm_share_issue_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_valuation_menu', 21 );

/* =========================================================
 * POST HANDLERS
 * ========================================================= */

function bhela_bm_valuation_admin_post() {
	if ( ! is_admin() || ! current_user_can( 'bhela_investors_view' ) ) {
		return;
	}

	if ( isset( $_POST['bhela_val_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_val_nonce'] ) ), 'bhela_bm_valuation' ) ) {
		bhela_bm_investor_notice( bhela_bm_valuation_add( array(
			'date'  => sanitize_text_field( wp_unslash( $_POST['val_date'] ?? '' ) ),
			'total' => (int) ( $_POST['val_total'] ?? 0 ),
			'basis' => sanitize_textarea_field( wp_unslash( $_POST['val_basis'] ?? '' ) ),
			'doc'   => esc_url_raw( wp_unslash( $_POST['val_doc'] ?? '' ) ),
			'note'  => sanitize_textarea_field( wp_unslash( $_POST['val_note'] ?? '' ) ),
		) ) );
	}

	if ( isset( $_POST['bhela_val_act_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_val_act_nonce'] ) ), 'bhela_bm_valuation_act' ) ) {
		$vid = (int) ( $_POST['valuation'] ?? 0 );
		bhela_bm_investor_notice(
			! empty( $_POST['val_reopen'] )
				? bhela_bm_valuation_reopen( $vid, sanitize_text_field( wp_unslash( $_POST['val_reason'] ?? '' ) ) )
				: bhela_bm_valuation_approve( $vid )
		);
	}

	if ( isset( $_POST['bhela_iss_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_iss_nonce'] ) ), 'bhela_bm_share_issue' ) ) {
		bhela_bm_investor_notice( bhela_bm_share_issue_commit( array(
			'investor' => (int) ( $_POST['iss_investor'] ?? 0 ),
			'shares'   => (int) ( $_POST['iss_shares'] ?? 0 ),
			'date'     => sanitize_text_field( wp_unslash( $_POST['iss_date'] ?? '' ) ),
			'note'     => sanitize_textarea_field( wp_unslash( $_POST['iss_note'] ?? '' ) ),
		) ) );
	}
}
add_action( 'admin_init', 'bhela_bm_valuation_admin_post' );

/* =========================================================
 * VALUATION HISTORY
 * ========================================================= */

function bhela_bm_valuation_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this screen.', 'bhela-booking' ) );
	}
	$rows    = bhela_bm_valuation_history();
	$current = bhela_bm_valuation_current();
	$states  = bhela_bm_valuation_states();
	$cfg     = bhela_bm_share_config();
	// One pass over the issues for the whole screen: the drift banner and the per-row
	// "was an issue priced from this" both read it, and each used to re-query.
	$issue_map = bhela_bm_share_issue_valuation_map();
	$drift     = bhela_bm_share_issue_drift();
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'💎',
			__( 'Valuation', 'bhela-booking' ),
			__( 'What BHELA is worth, and therefore what one share is worth. The share count does not change as the business grows — the valuation does, and the value per share follows.', 'bhela-booking' ),
			sprintf(
				'<a class="button" href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bhela_bm_valuation_csv' ), 'bhela_bm_valuation_csv' ) ),
				esc_html__( 'Download CSV', 'bhela-booking' )
			)
		);
		?>

		<div class="bha-cards">
			<div class="bha-card">
				<span class="bha-card__label"><?php esc_html_e( 'Valuation in force', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( $current ? bhela_bm_money( $current['total'] ) : bhela_bm_money( (int) $cfg['total_investment'] ) ); ?></span>
				<p class="bha-note">
					<?php
					echo $current
						? esc_html( sprintf( /* translators: %s: date */ __( 'as at %s', 'bhela-booking' ), mysql2date( 'j M Y', $current['date'] ) ) )
						: esc_html__( 'No valuation approved yet — this is the initial equity value from Settings.', 'bhela-booking' );
					?>
				</p>
			</div>
			<div class="bha-card">
				<span class="bha-card__label"><?php esc_html_e( 'Current share value', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( bhela_bm_money( bhela_bm_share_value() ) ); ?></span>
				<p class="bha-note">
					<?php
					printf(
						/* translators: 1: total shares */
						esc_html__( 'across %1$s shares', 'bhela-booking' ),
						'<span class="bha-plain">' . esc_html( number_format_i18n( $current ? $current['shares'] : (int) $cfg['total_shares'] ) ) . '</span>'
					);
					?>
				</p>
			</div>
			<div class="bha-card">
				<span class="bha-card__label"><?php esc_html_e( 'Original share value', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( bhela_bm_money( (int) $cfg['per_share'] ) ); ?></span>
				<p class="bha-note"><?php esc_html_e( 'the issue price the shares were first sold at', 'bhela-booking' ); ?></p>
			</div>
		</div>

		<?php if ( $drift['drift'] ) : ?>
			<p class="bha-callout bha-callout--attention">
				<?php
				printf(
					/* translators: 1: configured, 2: expected, 3: initial, 4: issued */
					esc_html__( 'The configured share total is %1$d, but the issue history adds up to %2$d (%3$d at the start plus %4$d issued). Nothing has been changed automatically — the divisor under every percentage and every distribution is this number, so a person needs to decide which figure is right.', 'bhela-booking' ),
					(int) $drift['configured'],
					(int) $drift['expected'],
					(int) $drift['initial'],
					(int) $drift['issued']
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( current_user_can( 'bhela_investor_valuation' ) ) : ?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'Record a valuation', 'bhela-booking' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'bhela_bm_valuation', 'bhela_val_nonce' ); ?>
					<div class="bha-bar">
						<div class="bha-field"><label for="val-date"><?php esc_html_e( 'Valuation date', 'bhela-booking' ); ?></label>
							<input type="date" id="val-date" name="val_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></div>
						<div class="bha-field"><label for="val-total"><?php esc_html_e( 'Total valuation ৳', 'bhela-booking' ); ?></label>
							<input type="number" min="1" step="1" id="val-total" name="val_total" required></div>
						<div class="bha-field"><label for="val-basis"><?php esc_html_e( 'Basis', 'bhela-booking' ); ?></label>
							<input type="text" id="val-basis" name="val_basis" placeholder="<?php esc_attr_e( 'how the figure was arrived at', 'bhela-booking' ); ?>"></div>
						<div class="bha-field"><label for="val-doc"><?php esc_html_e( 'Supporting document', 'bhela-booking' ); ?></label>
							<input type="url" id="val-doc" name="val_doc" placeholder="https://"></div>
						<div class="bha-field"><label for="val-note"><?php esc_html_e( 'Remarks', 'bhela-booking' ); ?></label>
							<input type="text" id="val-note" name="val_note"></div>
						<button class="button button-primary"><?php esc_html_e( 'Record as draft', 'bhela-booking' ); ?></button>
					</div>
					<p class="bha-note"><?php esc_html_e( 'A recorded valuation is a draft and decides nothing: no investor sees it and no share can be issued from it until somebody else approves it. The share count is captured as it stands today, so a later share issue cannot change what this valuation says a share was worth.', 'bhela-booking' ); ?></p>
				</form>
			</div>
		<?php endif; ?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'Valuation history', 'bhela-booking' ); ?></h2>
			<div class="bha-scroll">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Date', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Total valuation', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Shares', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Per share', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Growth', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Basis', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bhela-booking' ); ?></th>
					<th class="bha-noprint"></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No valuation has been recorded yet.', 'bhela-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<?php
					$st   = $states[ $r['status'] ] ?? $states[''];
					$mine = get_current_user_id() === $r['by'];
					$live = $current && $current['id'] === $r['id'];
					$who  = get_userdata( $r['approved_by'] ? $r['approved_by'] : $r['by'] );
					?>
					<tr>
						<td>
							<?php echo esc_html( mysql2date( 'j M Y', $r['date'] ) ); ?>
							<?php if ( $live ) : ?>
								<br><span style="opacity:.6;font-size:11px"><?php esc_html_e( 'in force', 'bhela-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['total'] ) ); ?></td>
						<td class="bha-num"><span class="bha-plain"><?php echo esc_html( number_format_i18n( $r['shares'] ) ); ?></span></td>
						<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $r['per_share'] ) ); ?></strong></td>
						<td class="bha-num <?php echo $r['growth'] < 0 ? 'is-danger' : 'is-good'; ?>">
							<span class="bha-plain"><?php echo esc_html( ( $r['growth'] > 0 ? '+' : '' ) . $r['growth'] ); ?>%</span>
						</td>
						<td>
							<?php echo esc_html( $r['basis'] ); ?>
							<?php if ( $r['doc'] ) : ?>
								<a href="<?php echo esc_url( $r['doc'] ); ?>" target="_blank" rel="noopener">📎</a>
							<?php endif; ?>
							<?php if ( $r['note'] ) : ?>
								<br><span style="opacity:.6;font-size:11px"><?php echo esc_html( $r['note'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo bhela_bm_status_pill( $st['label'], $st['tone'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php if ( $who ) : ?>
								<br><span style="opacity:.6;font-size:11px"><?php echo esc_html( $who->display_name ); ?></span>
							<?php endif; ?>
						</td>
						<td class="bha-noprint">
							<?php if ( ! current_user_can( 'bhela_investor_approve' ) ) : ?>
								<span style="opacity:.6"><?php esc_html_e( '—', 'bhela-booking' ); ?></span>
							<?php elseif ( 'draft' === $r['status'] && $mine ) : ?>
								<span style="opacity:.6"><?php esc_html_e( 'you recorded this — somebody else must approve it', 'bhela-booking' ); ?></span>
							<?php elseif ( 'draft' === $r['status'] ) : ?>
								<form method="post">
									<?php wp_nonce_field( 'bhela_bm_valuation_act', 'bhela_val_act_nonce' ); ?>
									<input type="hidden" name="valuation" value="<?php echo esc_attr( $r['id'] ); ?>">
									<button class="button button-primary button-small"><?php esc_html_e( 'Approve', 'bhela-booking' ); ?></button>
								</form>
							<?php elseif ( bhela_bm_valuation_used_by_issue( $r['id'], $issue_map ) ) : ?>
								<span style="opacity:.6"><?php esc_html_e( 'a share issue was priced from this', 'bhela-booking' ); ?></span>
							<?php else : ?>
								<form method="post" style="display:flex;gap:4px;align-items:center">
									<?php wp_nonce_field( 'bhela_bm_valuation_act', 'bhela_val_act_nonce' ); ?>
									<input type="hidden" name="valuation" value="<?php echo esc_attr( $r['id'] ); ?>">
									<input type="text" name="val_reason" required placeholder="<?php esc_attr_e( 'reason', 'bhela-booking' ); ?>" style="width:110px">
									<button class="button button-small" name="val_reopen" value="1"><?php esc_html_e( 'Reopen', 'bhela-booking' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<p class="bha-note"><?php esc_html_e( 'Growth is measured against the previous approved valuation; the earliest one is measured against the initial equity value. A draft is nobody’s baseline.', 'bhela-booking' ); ?></p>
		</div>
	</div>
	<?php
}

/* =========================================================
 * SHARE ISSUE
 * ========================================================= */

function bhela_bm_share_issue_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this screen.', 'bhela-booking' ) );
	}
	$target  = isset( $_GET['target'] ) ? max( 0, (int) $_GET['target'] ) : 0;
	$shares  = isset( $_GET['shares'] ) ? max( 0, (int) $_GET['shares'] ) : 0;
	$p       = bhela_bm_share_issue_preview( $shares, $target );
	$effect  = bhela_bm_share_issue_effect( $p );
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'🪙',
			__( 'Share Issue', 'bhela-booking' ),
			__( 'A new investor buys in at the approved valuation of the day. Nobody’s share count changes and nobody’s holding value changes — only the percentages, because there are now more shares.', 'bhela-booking' )
		);
		?>

		<?php if ( ! $p['valued'] ) : ?>
			<p class="bha-callout bha-callout--attention">
				<?php
				printf(
					/* translators: %s: link to the valuation screen */
					wp_kses_post( __( 'No valuation has been approved, so a share cannot be priced and none can be issued. Record and approve one under <a href="%s">Valuation</a> first — issuing at the original price after the business has grown is exactly the dilution this screen exists to prevent.', 'bhela-booking' ) ),
					esc_url( bhela_bm_admin_url( 'bhela-bm-valuation' ) )
				);
				?>
			</p>
		<?php endif; ?>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Pre-money valuation', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $p['pre_money'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Price per share', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $p['price'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Shares before', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( number_format_i18n( $p['before'] ) ); ?></span></div>
		</div>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'Work out the round', 'bhela-booking' ); ?></h2>
			<form method="get" class="bha-bar">
				<input type="hidden" name="page" value="bhela-bm-share-issue">
				<div class="bha-field"><label for="iss-target"><?php esc_html_e( 'They want to invest ৳', 'bhela-booking' ); ?></label>
					<input type="number" min="0" step="1" id="iss-target" name="target" value="<?php echo esc_attr( $target ? $target : '' ); ?>"></div>
				<div class="bha-field"><label for="iss-calc-shares"><?php esc_html_e( 'or issue this many shares', 'bhela-booking' ); ?></label>
					<input type="number" min="0" step="1" id="iss-calc-shares" name="shares" value="<?php echo esc_attr( $shares ? $shares : '' ); ?>"></div>
				<button class="button button-primary"><?php esc_html_e( 'Calculate', 'bhela-booking' ); ?></button>
			</form>

			<?php if ( $target > 0 && $p['price'] > 0 ) : ?>
				<p class="bha-callout">
					<?php
					printf(
						/* translators: 1: amount, 2: price, 3: exact share count */
						esc_html__( '%1$s at %2$s per share is %3$s shares. A share cannot be split, so pick one of these:', 'bhela-booking' ),
						esc_html( bhela_bm_money( $target ) ),
						esc_html( bhela_bm_money( $p['price'] ) ),
						'<strong>' . esc_html( (string) $p['exact'] ) . '</strong>'
					);
					?>
					<?php foreach ( array( $p['suggest_down'], $p['suggest_up'] ) as $opt ) : ?>
						<?php if ( $opt > 0 ) : ?>
							<a class="button button-small" href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-share-issue', array( 'shares' => $opt, 'target' => $target ) ) ); ?>">
								<?php
								printf(
									/* translators: 1: share count, 2: money raised */
									esc_html__( '%1$d shares — %2$s', 'bhela-booking' ),
									(int) $opt,
									esc_html( bhela_bm_money( $opt * $p['price'] ) )
								);
								?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<?php if ( $p['shares'] > 0 ) : ?>
				<div class="bha-cards">
					<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Shares issued', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( number_format_i18n( $p['shares'] ) ); ?></span></div>
					<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Amount raised', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $p['amount'] ) ); ?></span></div>
					<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Post-money valuation', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $p['post_money'] ) ); ?></span></div>
					<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Shares after', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( number_format_i18n( $p['after'] ) ); ?></span></div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $effect ) : ?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'What it does to the existing holders', 'bhela-booking' ); ?></h2>
				<div class="bha-scroll">
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Shares', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Ownership before', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Ownership after', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Holding value', 'bhela-booking' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $effect as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r['name'] ); ?></td>
							<td class="bha-num"><span class="bha-plain"><?php echo esc_html( number_format_i18n( $r['shares'] ) ); ?></span></td>
							<td class="bha-num"><span class="bha-plain"><?php echo esc_html( $r['pct_before'] ); ?>%</span></td>
							<td class="bha-num"><span class="bha-plain"><?php echo esc_html( $r['pct_after'] ); ?>%</span></td>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['value'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
				<p class="bha-note"><?php esc_html_e( 'Every share count and every holding value in this table is the same before and after. Only the percentages move, because the business took in cash worth exactly what the new shares are worth — that is what pricing a round at the approved valuation means, and it is the answer to “why did my percentage go down”.', 'bhela-booking' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $p['shares'] > 0 && $p['valued'] && current_user_can( 'bhela_investor_approve' ) ) : ?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'Commit the round', 'bhela-booking' ); ?></h2>
				<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Issue these shares? This cannot be undone or edited afterwards.', 'bhela-booking' ) ); ?>');">
					<?php wp_nonce_field( 'bhela_bm_share_issue', 'bhela_iss_nonce' ); ?>
					<input type="hidden" name="iss_shares" value="<?php echo esc_attr( $p['shares'] ); ?>">
					<div class="bha-bar">
						<div class="bha-field"><label for="iss-investor"><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></label>
							<select id="iss-investor" name="iss_investor" required>
								<option value=""><?php esc_html_e( '— pick an investor —', 'bhela-booking' ); ?></option>
								<?php foreach ( bhela_bm_investors() as $iid ) : ?>
									<?php if ( 'exited' === bhela_bm_investor_status( $iid ) ) { continue; } ?>
									<option value="<?php echo esc_attr( $iid ); ?>"><?php echo esc_html( get_the_title( $iid ) ); ?></option>
								<?php endforeach; ?>
							</select></div>
						<div class="bha-field"><label for="iss-date"><?php esc_html_e( 'Date', 'bhela-booking' ); ?></label>
							<input type="date" id="iss-date" name="iss_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></div>
						<div class="bha-field"><label for="iss-note"><?php esc_html_e( 'Remarks', 'bhela-booking' ); ?></label>
							<input type="text" id="iss-note" name="iss_note"></div>
						<button class="button button-primary">
							<?php
							printf(
								/* translators: 1: shares, 2: amount */
								esc_html__( 'Issue %1$d shares for %2$s', 'bhela-booking' ),
								(int) $p['shares'],
								esc_html( bhela_bm_money( $p['amount'] ) )
							);
							?>
						</button>
					</div>
					<p class="bha-note"><?php esc_html_e( 'Committing raises the configured share total and credits the investor with the shares and the money. The record is immutable afterwards, and it is the only thing in the plugin that changes the share total — a hand-edited setting is reported as a discrepancy rather than accepted.', 'bhela-booking' ); ?></p>
				</form>
			</div>
		<?php endif; ?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'Issues on record', 'bhela-booking' ); ?></h2>
			<div class="bha-scroll">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Date', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Shares', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Price', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Raised', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Pre-money', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Post-money', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Total shares', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
				<?php $issues = bhela_bm_share_issues(); ?>
				<?php if ( ! $issues ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No shares have been issued since the original structure was set up.', 'bhela-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $issues as $r ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'j M Y', $r['date'] ) ); ?></td>
						<td><?php echo esc_html( get_the_title( $r['investor'] ) ); ?></td>
						<td class="bha-num"><span class="bha-plain"><?php echo esc_html( number_format_i18n( $r['shares'] ) ); ?></span></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['price'] ) ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['amount'] ) ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['pre_money'] ) ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['post_money'] ) ); ?></td>
						<td class="bha-num">
							<span class="bha-plain"><?php echo esc_html( number_format_i18n( $r['shares_before'] ) . ' → ' . number_format_i18n( $r['shares_after'] ) ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div>
	</div>
	<?php
}

/* =========================================================
 * EXPORT
 * ========================================================= */

/** The valuation history as a file. */
function bhela_bm_valuation_csv() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this.', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_valuation_csv' );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=bhela-valuation-history.csv' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array_map( 'bhela_bm_csv_cell', array(
		__( 'Date', 'bhela-booking' ),
		__( 'Total valuation', 'bhela-booking' ),
		__( 'Total shares', 'bhela-booking' ),
		__( 'Per share', 'bhela-booking' ),
		__( 'Previous valuation', 'bhela-booking' ),
		__( 'Growth %', 'bhela-booking' ),
		__( 'Basis', 'bhela-booking' ),
		__( 'Status', 'bhela-booking' ),
		__( 'Approved by', 'bhela-booking' ),
		__( 'Approved at', 'bhela-booking' ),
		__( 'Document', 'bhela-booking' ),
		__( 'Remarks', 'bhela-booking' ),
	) ) );
	foreach ( bhela_bm_valuation_history() as $r ) {
		$who = get_userdata( $r['approved_by'] );
		fputcsv( $out, array(
			bhela_bm_csv_cell( $r['date'] ),
			$r['total'],
			$r['shares'],
			$r['per_share'],
			$r['prev_total'],
			$r['growth'],
			bhela_bm_csv_cell( $r['basis'] ),
			bhela_bm_csv_cell( $r['status'] ),
			bhela_bm_csv_cell( $who ? $who->display_name : '' ),
			bhela_bm_csv_cell( $r['approved_at'] ),
			bhela_bm_csv_cell( $r['doc'] ),
			bhela_bm_csv_cell( $r['note'] ),
		) );
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_bhela_bm_valuation_csv', 'bhela_bm_valuation_csv' );
