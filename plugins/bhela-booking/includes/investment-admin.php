<?php
/**
 * 💠 Investments and 📑 Agreements — the two screens behind the record.
 *
 * The Investments screen is built around one idea: **the draft is where everything is
 * decided, and activating is a one-way door.** So the editor shows the blockers as a
 * checklist rather than failing on save, the capital rows sit on the same page as the
 * terms they are the principal for, and the Activate button says what it will stop
 * being possible afterwards.
 *
 * Agreements get their own screen because the brief lists them as their own thing and
 * because an agreement outlives the investment that cites it. Nothing here composes
 * agreement wording — see includes/agreement.php for why that is deliberate.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_investment_url( $args = array() ) {
	return bhela_bm_admin_url( 'bhela-bm-investments', $args );
}

function bhela_bm_agreement_url( $args = array() ) {
	return bhela_bm_admin_url( 'bhela-bm-agreements', $args );
}

function bhela_bm_investment_notice( $result, $url ) {
	$key = 'bhela_bm_ivm_msg_' . get_current_user_id();
	set_transient( $key, is_wp_error( $result ) ? array( 'err', $result->get_error_message() ) : array( 'ok', (string) $result ), 60 );
	wp_safe_redirect( $url );
	exit;
}

/* =========================================================
 * HANDLERS
 * ========================================================= */

function bhela_bm_investment_post() {
	if ( ! current_user_can( 'edit_bhela_investors' ) ) {
		wp_die( esc_html__( 'You are not allowed to record investments.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_investment_save' );

	$id   = isset( $_POST['investment'] ) ? (int) $_POST['investment'] : 0;
	$args = array(
		'investor'  => isset( $_POST['investor'] ) ? (int) $_POST['investor'] : 0,
		'date'      => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
		'start'     => isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : '',
		'maturity'  => isset( $_POST['maturity'] ) ? sanitize_text_field( wp_unslash( $_POST['maturity'] ) ) : '',
		'type'      => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '',
		'method'    => isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : '',
		'rate'      => isset( $_POST['rate'] ) ? sanitize_text_field( wp_unslash( $_POST['rate'] ) ) : '',
		'frequency' => isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : '',
		'agreement' => isset( $_POST['agreement'] ) ? (int) $_POST['agreement'] : 0,
		'note'      => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
	);

	$r = $id ? bhela_bm_investment_save( $id, $args ) : bhela_bm_investment_add( $args );
	if ( is_wp_error( $r ) ) {
		bhela_bm_investment_notice( $r, bhela_bm_investment_url( $id ? array( 'investment' => $id ) : array() ) );
	}
	$id = $id ? $id : (int) $r;
	bhela_bm_investment_notice( __( 'সংরক্ষিত হয়েছে।', 'bhela-booking' ), bhela_bm_investment_url( array( 'investment' => $id ) ) );
}
add_action( 'admin_post_bhela_bm_investment_save', 'bhela_bm_investment_post' );

function bhela_bm_investment_move_post() {
	check_admin_referer( 'bhela_bm_investment_move' );
	$id = isset( $_POST['investment'] ) ? (int) $_POST['investment'] : 0;
	$to = isset( $_POST['to'] ) ? sanitize_key( wp_unslash( $_POST['to'] ) ) : '';
	$r  = bhela_bm_investment_transition(
		$id,
		$to,
		isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : ''
	);
	bhela_bm_investment_notice(
		is_wp_error( $r ) ? $r : __( 'অবস্থা পরিবর্তন হয়েছে।', 'bhela-booking' ),
		bhela_bm_investment_url( array( 'investment' => $id ) )
	);
}
add_action( 'admin_post_bhela_bm_investment_move', 'bhela_bm_investment_move_post' );

/** A receipt against this investment. The principal is the sum of these. */
function bhela_bm_investment_capital_post() {
	if ( ! current_user_can( 'bhela_investor_capital' ) ) {
		wp_die( esc_html__( 'You are not allowed to record capital.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_investment_capital' );

	$id = isset( $_POST['investment'] ) ? (int) $_POST['investment'] : 0;
	$r  = bhela_bm_capital_add( array(
		'investor'   => isset( $_POST['investor'] ) ? (int) $_POST['investor'] : 0,
		'investment' => $id,
		'date'       => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
		'amount'     => isset( $_POST['amount'] ) ? (int) preg_replace( '/[^0-9\-]/', '', wp_unslash( $_POST['amount'] ) ) : 0,
		'method'     => isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : '',
		'ref'        => isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : '',
	) );
	bhela_bm_investment_notice(
		is_wp_error( $r ) ? $r : __( 'প্রাপ্তি রেকর্ড হয়েছে।', 'bhela-booking' ),
		bhela_bm_investment_url( array( 'investment' => $id ) )
	);
}
add_action( 'admin_post_bhela_bm_investment_capital', 'bhela_bm_investment_capital_post' );

/** Attach a receipt recorded before this investment existed. */
function bhela_bm_investment_link_post() {
	if ( ! current_user_can( 'bhela_investor_capital' ) ) {
		wp_die( esc_html__( 'You are not allowed to record capital.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_investment_link' );

	$id   = isset( $_POST['investment'] ) ? (int) $_POST['investment'] : 0;
	$rows = isset( $_POST['row'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['row'] ) ) : array();
	$done = 0;
	$last = null;
	foreach ( $rows as $row ) {
		$r = bhela_bm_capital_link( $row, $id );
		if ( is_wp_error( $r ) ) {
			$last = $r;
			continue;
		}
		$done++;
	}

	bhela_bm_investment_notice(
		( 0 === $done && $last ) ? $last : sprintf(
			/* translators: %d: rows attached */
			__( '%d টি প্রাপ্তি যুক্ত হয়েছে।', 'bhela-booking' ),
			$done
		),
		bhela_bm_investment_url( array( 'investment' => $id ) )
	);
}
add_action( 'admin_post_bhela_bm_investment_link', 'bhela_bm_investment_link_post' );

function bhela_bm_agreement_post() {
	if ( ! current_user_can( 'edit_bhela_investors' ) ) {
		wp_die( esc_html__( 'You are not allowed to record agreements.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_agreement_add' );

	$file = '';
	if ( ! empty( $_FILES['agreement_file']['name'] ) ) {
		// The same upload path a KYC scan takes: mime checked by content, stored under
		// a random name because uploads/ is served with no capability check (§13.69).
		$up = bhela_bm_investor_upload( 'agreement_file', true );
		if ( is_wp_error( $up ) ) {
			bhela_bm_investment_notice( $up, bhela_bm_agreement_url() );
		}
		// It returns the URL as a string; the array form was never a thing.
		$file = (string) $up;
	}

	$r = bhela_bm_agreement_add( array(
		'investor' => isset( $_POST['investor'] ) ? (int) $_POST['investor'] : 0,
		'date'     => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
		'parties'  => isset( $_POST['parties'] ) ? sanitize_text_field( wp_unslash( $_POST['parties'] ) ) : '',
		'file'     => $file,
		'note'     => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
	) );
	bhela_bm_investment_notice(
		is_wp_error( $r ) ? $r : __( 'চুক্তি রেকর্ড হয়েছে।', 'bhela-booking' ),
		bhela_bm_agreement_url()
	);
}
add_action( 'admin_post_bhela_bm_agreement_add', 'bhela_bm_agreement_post' );

/* =========================================================
 * MENUS
 * ========================================================= */

function bhela_bm_investment_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'capital' ),
		__( 'Investments', 'bhela-booking' ),
		'💠 ' . __( 'Investments', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-investments',
		'bhela_bm_investment_page'
	);
	add_submenu_page(
		bhela_bm_menu_parent( 'capital' ),
		__( 'Agreements', 'bhela-booking' ),
		'📑 ' . __( 'Agreements', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-agreements',
		'bhela_bm_agreement_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_investment_menu', 21 );

/** The notice a handler left behind. */
function bhela_bm_investment_flash() {
	$key = 'bhela_bm_ivm_msg_' . get_current_user_id();
	$msg = get_transient( $key );
	delete_transient( $key );
	if ( ! is_array( $msg ) ) {
		return;
	}
	printf(
		'<div class="notice notice-%s"><p>%s</p></div>',
		'err' === $msg[0] ? 'error' : 'success',
		esc_html( $msg[1] )
	);
}

/* =========================================================
 * THE INVESTMENTS SCREEN
 * ========================================================= */

function bhela_bm_investment_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You are not allowed to see investments.', 'bhela-booking' ) );
	}
	$money   = 'bhela_bm_money';
	$one     = isset( $_GET['investment'] ) ? (int) $_GET['investment'] : 0;
	$filter  = isset( $_GET['investor'] ) ? (int) $_GET['investor'] : 0;
	$state   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	$states  = bhela_bm_investment_states();
	$methods = bhela_bm_profit_methods();
	$freqs   = bhela_bm_investment_freqs();
	$types   = bhela_bm_investment_types();
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'💠',
			__( 'Investments', 'bhela-booking' ),
			__( 'কে কত টাকা, কোন শর্তে, কত মেয়াদে বিনিয়োগ করেছেন। খসড়া অবস্থায় সব বদলানো যায়; সক্রিয় করার পর শর্ত জমে যায়।', 'bhela-booking' )
		);
		bhela_bm_investment_flash();
		?>

		<?php if ( 'fixed' !== bhela_bm_investor_model() ) : ?>
			<div class="bha-callout bha-callout--attention">
				<p>
					<?php esc_html_e( 'বিনিয়োগের হিসাব এখনো শেয়ারভিত্তিক বণ্টনে চলছে। নির্দিষ্ট-হার পদ্ধতিতে যেতে ⚙️ Settings-এ মডেল বদলান — তার আগে এখানে তৈরি করা বিনিয়োগ থেকে কোনো লাভ হিসাবে যোগ হবে না।', 'bhela-booking' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $one && bhela_bm_investment( $one ) ) : ?>
			<?php bhela_bm_investment_editor( bhela_bm_investment( $one ), $methods, $freqs, $types, $states ); ?>
			<p><a class="button" href="<?php echo esc_url( bhela_bm_investment_url() ); ?>"><?php esc_html_e( '← তালিকায় ফিরুন', 'bhela-booking' ); ?></a></p>
		<?php else : ?>
			<form method="get" class="bha-bar">
				<input type="hidden" name="page" value="bhela-bm-investments">
				<label>
					<?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?>
					<select name="investor">
						<option value="0"><?php esc_html_e( 'সবাই', 'bhela-booking' ); ?></option>
						<?php foreach ( bhela_bm_investors() as $iid ) : ?>
							<option value="<?php echo (int) $iid; ?>" <?php selected( $filter, (int) $iid ); ?>><?php echo esc_html( get_the_title( $iid ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'অবস্থা', 'bhela-booking' ); ?>
					<select name="status">
						<option value=""><?php esc_html_e( 'সব', 'bhela-booking' ); ?></option>
						<?php foreach ( $states as $slug => $def ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $state, $slug ); ?>><?php echo esc_html( $def['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button class="button"><?php esc_html_e( 'দেখুন', 'bhela-booking' ); ?></button>
				<a class="button button-primary" href="<?php echo esc_url( bhela_bm_investment_url( array( 'investment' => 'new' ) ) ); ?>"><?php esc_html_e( '+ নতুন বিনিয়োগ', 'bhela-booking' ); ?></a>
			</form>

			<div class="bha-panel">
				<table class="widefat striped bha-table">
					<thead><tr>
						<th><?php esc_html_e( 'Investment ID', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'মূলধন', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'মেয়াদ', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'হার', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'অবস্থা', 'bhela-booking' ); ?></th>
						<th></th>
					</tr></thead>
					<tbody>
					<?php $rows = bhela_bm_investments( $filter, $state ); ?>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'কোনো বিনিয়োগ নেই।', 'bhela-booking' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td class="bha-plain"><?php echo esc_html( $r['code'] ); ?></td>
							<td><?php echo esc_html( $r['name'] ); ?></td>
							<td class="bha-num"><?php echo esc_html( $money( $r['principal'] ) ); ?></td>
							<td class="bha-plain">
								<?php
								echo $r['start'] ? esc_html( mysql2date( 'j M Y', $r['start'] ) . ' — ' . mysql2date( 'j M Y', $r['maturity'] ) ) : '—';
								?>
							</td>
							<td class="bha-num bha-plain"><?php echo esc_html( $r['rate'] . '%' ); ?></td>
							<td><?php echo bhela_bm_status_pill( $r['status_label'], $states[ $r['status'] ]['tone'] ?? 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
							<td><a class="button button-small" href="<?php echo esc_url( bhela_bm_investment_url( array( 'investment' => $r['id'] ) ) ); ?>"><?php esc_html_e( 'খুলুন', 'bhela-booking' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( 'new' === ( $_GET['investment'] ?? '' ) ) : ?>
				<?php bhela_bm_investment_editor( null, $methods, $freqs, $types, $states ); ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/** The terms, the blockers, the receipts, and the state buttons. */
function bhela_bm_investment_editor( $r, $methods, $freqs, $types, $states ) {
	$money    = 'bhela_bm_money';
	$id       = $r ? (int) $r['id'] : 0;
	$locked   = $r ? $r['locked'] : false;
	$blockers = $id ? bhela_bm_investment_blockers( $id ) : array();
	?>
	<div class="bha-panel">
		<h2>
			<?php
			echo $r
				? esc_html( $r['code'] . ' — ' . $r['name'] )
				: esc_html__( 'নতুন বিনিয়োগ', 'bhela-booking' );
			?>
		</h2>

		<?php if ( $locked ) : ?>
			<p class="description">
				<?php esc_html_e( 'এই বিনিয়োগ সক্রিয় — শর্ত আর বদলানো যাবে না। ভুল থাকলে কারণসহ খসড়ায় ফেরত আনুন; তার রেকর্ড থাকবে।', 'bhela-booking' ); ?>
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bhela_bm_investment_save' ); ?>
			<input type="hidden" name="action" value="bhela_bm_investment_save">
			<input type="hidden" name="investment" value="<?php echo (int) $id; ?>">
			<table class="form-table">
				<tr>
					<th><label for="ivm-investor"><?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?></label></th>
					<td>
						<select name="investor" id="ivm-investor" <?php disabled( $locked ); ?> required>
							<option value=""><?php esc_html_e( '— বেছে নিন —', 'bhela-booking' ); ?></option>
							<?php foreach ( bhela_bm_investors() as $iid ) : ?>
								<option value="<?php echo (int) $iid; ?>" <?php selected( $r ? $r['investor'] : 0, (int) $iid ); ?>><?php echo esc_html( get_the_title( $iid ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="ivm-date"><?php esc_html_e( 'বিনিয়োগের তারিখ', 'bhela-booking' ); ?></label></th>
					<td><input type="date" id="ivm-date" name="date" value="<?php echo esc_attr( $r['date'] ?? '' ); ?>" <?php disabled( $locked ); ?>></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'মেয়াদ', 'bhela-booking' ); ?></th>
					<td>
						<input type="date" name="start" value="<?php echo esc_attr( $r['start'] ?? '' ); ?>" <?php disabled( $locked ); ?>>
						—
						<input type="date" name="maturity" value="<?php echo esc_attr( $r['maturity'] ?? '' ); ?>" <?php disabled( $locked ); ?>>
						<?php if ( $r && $r['months'] ) : ?>
							<span class="description">
								<?php
								printf(
									/* translators: %d: months */
									esc_html__( '%d মাস', 'bhela-booking' ),
									(int) $r['months']
								);
								?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="ivm-type"><?php esc_html_e( 'ধরন', 'bhela-booking' ); ?></label></th>
					<td>
						<select name="type" id="ivm-type" <?php disabled( $locked ); ?>>
							<option value=""><?php esc_html_e( '— বেছে নিন —', 'bhela-booking' ); ?></option>
							<?php foreach ( $types as $slug => $def ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $r['type'] ?? '', $slug ); ?>><?php echo esc_html( $def['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="ivm-method"><?php esc_html_e( 'লাভ হিসাবের পদ্ধতি', 'bhela-booking' ); ?></label></th>
					<td>
						<select name="method" id="ivm-method" <?php disabled( $locked ); ?>>
							<option value=""><?php esc_html_e( '— বেছে নিন —', 'bhela-booking' ); ?></option>
							<?php foreach ( $methods as $slug => $def ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $r['method'] ?? '', $slug ); ?>>
									<?php echo esc_html( $def['label'] . ' — ' . $def['formula'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'সক্রিয় করার পর পদ্ধতি আর বদলায় না — প্রতিটি হিসাব করা সময়কাল এটির ওপর নির্ভর করে।', 'bhela-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ivm-rate"><?php esc_html_e( 'হার %', 'bhela-booking' ); ?></label></th>
					<td>
						<input type="text" id="ivm-rate" name="rate" inputmode="decimal" value="<?php echo esc_attr( $r ? rtrim( rtrim( number_format( $r['rate'], 2, '.', '' ), '0' ), '.' ) : '' ); ?>" <?php disabled( $locked ); ?>>
						<p class="description"><?php esc_html_e( 'পদ্ধতি অনুযায়ী এটি বার্ষিক হার, মাসিক হার, অথবা বিনিয়োগকারীর অংশ। ফাঁকা রাখলে সক্রিয় হবে না — অনুমান করে কিছু বসানো হয় না।', 'bhela-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ivm-freq"><?php esc_html_e( 'লাভ পরিশোধের সময়সূচি', 'bhela-booking' ); ?></label></th>
					<td>
						<select name="frequency" id="ivm-freq" <?php disabled( $locked ); ?>>
							<option value=""><?php esc_html_e( '— বেছে নিন —', 'bhela-booking' ); ?></option>
							<?php foreach ( $freqs as $slug => $def ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $r['frequency'] ?? '', $slug ); ?>><?php echo esc_html( $def['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="ivm-agr"><?php esc_html_e( 'চুক্তি', 'bhela-booking' ); ?></label></th>
					<td>
						<select name="agreement" id="ivm-agr" <?php disabled( $locked ); ?>>
							<option value="0"><?php esc_html_e( '— নেই —', 'bhela-booking' ); ?></option>
							<?php foreach ( bhela_bm_agreements( $r ? $r['investor'] : 0 ) as $a ) : ?>
								<option value="<?php echo (int) $a['id']; ?>" <?php selected( $r['agreement'] ?? 0, (int) $a['id'] ); ?>>
									<?php echo esc_html( $a['ref'] . ' · ' . mysql2date( 'j M Y', $a['date'] ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<a href="<?php echo esc_url( bhela_bm_agreement_url() ); ?>"><?php esc_html_e( 'চুক্তি যোগ করুন', 'bhela-booking' ); ?></a>
					</td>
				</tr>
				<tr>
					<th><label for="ivm-note"><?php esc_html_e( 'মন্তব্য', 'bhela-booking' ); ?></label></th>
					<td><textarea id="ivm-note" name="note" rows="2" class="large-text" <?php disabled( $locked ); ?>><?php echo esc_textarea( $r['note'] ?? '' ); ?></textarea></td>
				</tr>
			</table>
			<?php if ( ! $locked ) : ?>
				<p><button class="button button-primary"><?php esc_html_e( 'সংরক্ষণ', 'bhela-booking' ); ?></button></p>
			<?php endif; ?>
		</form>
	</div>

	<?php if ( ! $id ) : ?>
		<?php return; ?>
	<?php endif; ?>

	<div class="bha-panel">
		<h2><?php esc_html_e( 'প্রাপ্তি — মূলধন', 'bhela-booking' ); ?></h2>
		<p class="description"><?php esc_html_e( 'এই বিনিয়োগের মূলধন মানে এখানে রেকর্ড করা প্রাপ্তিগুলোর যোগফল। আলাদা করে টাইপ করার জায়গা নেই — তাহলে রসিদ আর সনদ দুই রকম বলতে পারত।', 'bhela-booking' ); ?></p>
		<table class="widefat striped bha-table">
			<thead><tr>
				<th><?php esc_html_e( 'তারিখ', 'bhela-booking' ); ?></th>
				<th class="bha-num"><?php esc_html_e( 'পরিমাণ', 'bhela-booking' ); ?></th>
				<th><?php esc_html_e( 'মাধ্যম', 'bhela-booking' ); ?></th>
				<th><?php esc_html_e( 'রেফারেন্স', 'bhela-booking' ); ?></th>
				<th class="bha-noprint"></th>
			</tr></thead>
			<tbody>
			<?php $caps = bhela_bm_capital_rows_for_investment( $id ); ?>
			<?php if ( ! $caps ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'কোনো প্রাপ্তি রেকর্ড নেই।', 'bhela-booking' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $caps as $c ) : ?>
				<tr>
					<td class="bha-plain"><?php echo esc_html( mysql2date( 'j M Y', $c['date'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( $money( $c['amount'] ) ); ?></td>
					<td><?php echo esc_html( $c['method'] ); ?></td>
					<td><?php echo esc_html( $c['ref'] ); ?></td>
					<td class="bha-noprint">
						<?php
						// The receipt the office hands over when the money arrives. It was
						// fully built in documents.php and linked from nowhere, so the only
						// way to print one was to know its URL shape and hash.
						?>
						<a class="button button-small" href="<?php echo esc_url( bhela_bm_receipt_url( 'capital', (int) $c['id'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'রসিদ', 'bhela-booking' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
				<tr>
					<td><strong><?php esc_html_e( 'মোট মূলধন', 'bhela-booking' ); ?></strong></td>
					<td class="bha-num"><strong><?php echo esc_html( $money( $r['principal'] ) ); ?></strong></td>
					<td colspan="3"></td>
				</tr>
			</tbody>
		</table>

		<?php if ( current_user_can( 'bhela_investor_capital' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
				<?php wp_nonce_field( 'bhela_bm_investment_capital' ); ?>
				<input type="hidden" name="action" value="bhela_bm_investment_capital">
				<input type="hidden" name="investment" value="<?php echo (int) $id; ?>">
				<input type="hidden" name="investor" value="<?php echo (int) $r['investor']; ?>">
				<input type="date" name="date" required>
				<input type="text" name="amount" inputmode="numeric" placeholder="<?php esc_attr_e( 'পরিমাণ', 'bhela-booking' ); ?>" required>
				<input type="text" name="method" placeholder="<?php esc_attr_e( 'মাধ্যম', 'bhela-booking' ); ?>">
				<input type="text" name="ref" placeholder="<?php esc_attr_e( 'রেফারেন্স', 'bhela-booking' ); ?>">
				<button class="button"><?php esc_html_e( 'প্রাপ্তি যোগ করুন', 'bhela-booking' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<?php
	$unlinked = ( ! $locked && function_exists( 'bhela_bm_capital_unlinked' ) )
		? bhela_bm_capital_unlinked( $r['investor'] )
		: array();
	?>
	<?php if ( $unlinked ) : ?>
		<div class="bha-panel">
			<h2><?php esc_html_e( 'আগের প্রাপ্তি যুক্ত করুন', 'bhela-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'এই বিনিয়োগকারীর নামে এমন প্রাপ্তি আছে যা এখনো কোনো বিনিয়োগের সঙ্গে যুক্ত নয় — সাধারণত Investment Record চালু হওয়ার আগের রেকর্ড। কোনটি এই চুক্তির বিপরীতে, তা আপনি ঠিক করবেন; সিস্টেম নিজে থেকে ধরে নেয় না।', 'bhela-booking' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bhela_bm_investment_link' ); ?>
				<input type="hidden" name="action" value="bhela_bm_investment_link">
				<input type="hidden" name="investment" value="<?php echo (int) $id; ?>">
				<table class="widefat striped bha-table">
					<thead><tr>
						<th style="width:34px"></th>
						<th><?php esc_html_e( 'তারিখ', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'পরিমাণ', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'মাধ্যম', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'রেফারেন্স', 'bhela-booking' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $unlinked as $u ) : ?>
						<tr>
							<td><input type="checkbox" name="row[]" value="<?php echo (int) $u['id']; ?>"></td>
							<td class="bha-plain"><?php echo esc_html( mysql2date( 'j M Y', $u['date'] ) ); ?></td>
							<td class="bha-num"><?php echo esc_html( $money( $u['amount'] ) ); ?></td>
							<td><?php echo esc_html( $u['method'] ); ?></td>
							<td><?php echo esc_html( $u['ref'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button class="button"><?php esc_html_e( 'টিক দেওয়া প্রাপ্তি এই বিনিয়োগে যুক্ত করুন', 'bhela-booking' ); ?></button></p>
			</form>
		</div>
	<?php endif; ?>

	<div class="bha-panel">
		<h2><?php esc_html_e( 'অবস্থা', 'bhela-booking' ); ?></h2>

		<?php if ( $blockers ) : ?>
			<div class="bha-callout bha-callout--attention">
				<p><strong><?php esc_html_e( 'সক্রিয় করার আগে যা লাগবে', 'bhela-booking' ); ?></strong></p>
				<ul>
					<?php foreach ( $blockers as $b ) : ?>
						<li><?php echo esc_html( $b ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php
		$moves = bhela_bm_investment_transitions();
		$allow = $moves[ $r['status'] ] ?? array();
		?>
		<?php if ( ! $allow ) : ?>
			<p class="description"><?php esc_html_e( 'এখান থেকে আর কোথাও যাওয়ার নেই।', 'bhela-booking' ); ?></p>
		<?php endif; ?>
		<?php foreach ( $allow as $to => $cap ) : ?>
			<?php if ( ! current_user_can( $cap ) ) { continue; } ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-flex;gap:6px;margin-right:10px">
				<?php wp_nonce_field( 'bhela_bm_investment_move' ); ?>
				<input type="hidden" name="action" value="bhela_bm_investment_move">
				<input type="hidden" name="investment" value="<?php echo (int) $id; ?>">
				<input type="hidden" name="to" value="<?php echo esc_attr( $to ); ?>">
				<?php if ( 'draft' === $to ) : ?>
					<input type="text" name="reason" required placeholder="<?php esc_attr_e( 'ফেরত আনার কারণ', 'bhela-booking' ); ?>">
				<?php endif; ?>
				<button class="button <?php echo 'active' === $to ? 'button-primary' : ''; ?>" <?php disabled( 'active' === $to && $blockers ); ?>>
					<?php
					printf(
						/* translators: %s: the state being moved to */
						esc_html__( '%s করুন', 'bhela-booking' ),
						esc_html( $states[ $to ]['label'] ?? $to )
					);
					?>
				</button>
			</form>
		<?php endforeach; ?>
	</div>
	<?php
}

/* =========================================================
 * THE AGREEMENTS SCREEN
 * ========================================================= */

function bhela_bm_agreement_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You are not allowed to see agreements.', 'bhela-booking' ) );
	}
	$investor = isset( $_GET['investor'] ) ? (int) $_GET['investor'] : 0;
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'📑',
			__( 'Agreements', 'bhela-booking' ),
			__( 'স্বাক্ষরিত চুক্তির রেফারেন্স ও কপি। সনদে এই রেফারেন্সই উদ্ধৃত হয়।', 'bhela-booking' )
		);
		bhela_bm_investment_flash();
		?>

		<div class="bha-callout">
			<p>
				<?php esc_html_e( 'এখানে চুক্তির খসড়া তৈরি হয় না — স্বাক্ষরিত কপি রাখা হয় আর তার রেফারেন্স দেওয়া হয়। চুক্তির ভাষা BHELA-র আইন ও হিসাব উপদেষ্টার অনুমোদনের বিষয়, সফটওয়্যারের নয়।', 'bhela-booking' ); ?>
			</p>
		</div>

		<form method="get" class="bha-bar">
			<input type="hidden" name="page" value="bhela-bm-agreements">
			<label>
				<?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?>
				<select name="investor">
					<option value="0"><?php esc_html_e( 'সবাই', 'bhela-booking' ); ?></option>
					<?php foreach ( bhela_bm_investors() as $iid ) : ?>
						<option value="<?php echo (int) $iid; ?>" <?php selected( $investor, (int) $iid ); ?>><?php echo esc_html( get_the_title( $iid ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button class="button"><?php esc_html_e( 'দেখুন', 'bhela-booking' ); ?></button>
		</form>

		<div class="bha-panel">
			<table class="widefat striped bha-table">
				<thead><tr>
					<th><?php esc_html_e( 'রেফারেন্স', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'তারিখ', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'পক্ষ', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'অবস্থা', 'bhela-booking' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php $rows = bhela_bm_agreements( $investor ); ?>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'কোনো চুক্তি রেকর্ড নেই।', 'bhela-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $a ) : ?>
					<tr>
						<td class="bha-plain"><?php echo esc_html( $a['ref'] ); ?></td>
						<td><?php echo esc_html( $a['name'] ); ?></td>
						<td class="bha-plain"><?php echo esc_html( mysql2date( 'j M Y', $a['date'] ) ); ?></td>
						<td><?php echo esc_html( $a['parties'] ); ?></td>
						<td><?php echo esc_html( $a['status_label'] ); ?></td>
						<td>
							<?php if ( $a['file'] ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $a['file'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'কপি', 'bhela-booking' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( current_user_can( 'edit_bhela_investors' ) ) : ?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'নতুন চুক্তি', 'bhela-booking' ); ?></h2>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'bhela_bm_agreement_add' ); ?>
					<input type="hidden" name="action" value="bhela_bm_agreement_add">
					<table class="form-table">
						<tr>
							<th><label for="agr-investor"><?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?></label></th>
							<td>
								<select name="investor" id="agr-investor" required>
									<option value=""><?php esc_html_e( '— বেছে নিন —', 'bhela-booking' ); ?></option>
									<?php foreach ( bhela_bm_investors() as $iid ) : ?>
										<option value="<?php echo (int) $iid; ?>" <?php selected( $investor, (int) $iid ); ?>><?php echo esc_html( get_the_title( $iid ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="agr-date"><?php esc_html_e( 'স্বাক্ষরের তারিখ', 'bhela-booking' ); ?></label></th>
							<td><input type="date" id="agr-date" name="date" required></td>
						</tr>
						<tr>
							<th><label for="agr-parties"><?php esc_html_e( 'পক্ষসমূহ', 'bhela-booking' ); ?></label></th>
							<td><input type="text" id="agr-parties" name="parties" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="agr-file"><?php esc_html_e( 'স্বাক্ষরিত কপি', 'bhela-booking' ); ?></label></th>
							<td>
								<input type="file" id="agr-file" name="agreement_file" accept="<?php echo esc_attr( implode( ',', bhela_bm_investor_upload_accept() ) ); ?>">
								<p class="description"><?php esc_html_e( 'ফাইলটি এলোমেলো নামে সংরক্ষিত হয়, যাতে URL অনুমান করে কেউ খুলতে না পারে।', 'bhela-booking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="agr-note"><?php esc_html_e( 'মন্তব্য', 'bhela-booking' ); ?></label></th>
							<td><textarea id="agr-note" name="note" rows="2" class="large-text"></textarea></td>
						</tr>
					</table>
					<p><button class="button button-primary"><?php esc_html_e( 'চুক্তি রেকর্ড করুন', 'bhela-booking' ); ?></button></p>
				</form>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
