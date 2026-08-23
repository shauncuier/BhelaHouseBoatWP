<?php
/**
 * Admin: booking list columns, editable meta box, status workflow, settings page.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- List table columns ---------- */

function bhela_bm_table_columns( $columns ) {
	return array(
		'cb'          => $columns['cb'],
		'title'       => __( 'Name', 'bhela-booking' ),
		'invoice_no'  => __( 'Invoice', 'bhela-booking' ),
		'phone'       => __( 'Phone', 'bhela-booking' ),
		'travel_date' => __( 'Travel Date', 'bhela-booking' ),
		'cabin'       => __( 'Cabin', 'bhela-booking' ),
		'guests'      => __( 'Guests', 'bhela-booking' ),
		'total'       => __( 'Total / Paid', 'bhela-booking' ),
		'bstatus'     => __( 'Status', 'bhela-booking' ),
		'date'        => __( 'Submitted', 'bhela-booking' ),
	);
}
add_filter( 'manage_bhela_booking_posts_columns', 'bhela_bm_table_columns' );

function bhela_bm_table_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'invoice_no':
			$no = get_post_meta( $post_id, '_bhela_invoice_no', true );
			if ( $no ) {
				printf( '<a href="%s" target="_blank"><strong>%s</strong></a>', esc_url( bhela_bm_invoice_url( $post_id ) ), esc_html( $no ) );
			} else {
				echo '—';
			}
			break;
		case 'phone':
			$phone = get_post_meta( $post_id, '_bhela_phone', true );
			echo $phone ? '<a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a>' : '—';
			// Only ever a positive claim. A booking taken before verification
			// existed, or typed in by hand here, carries no stamp — saying
			// "unverified" would read as a warning about the guest.
			$verified = function_exists( 'bhela_bm_otp_record' ) ? bhela_bm_otp_record( $post_id ) : array();
			if ( $verified ) {
				printf(
					'<span class="bha-sub bha-settled" title="%s">✅ %s</span>',
					esc_attr( sprintf(
						/* translators: 1: sms|email, 2: date */
						__( 'Verified by %1$s on %2$s', 'bhela-booking' ),
						$verified['channel'] ?? '—',
						$verified['at'] ? mysql2date( 'j M Y, g:i a', $verified['at'] ) : '—'
					) ),
					esc_html__( 'verified', 'bhela-booking' )
				);
			}
			break;
		case 'travel_date':
			echo esc_html( get_post_meta( $post_id, '_bhela_travel_date', true ) ?: '—' );
			break;
		case 'cabin':
			echo esc_html( get_post_meta( $post_id, '_bhela_cabin_type', true ) ?: '—' );
			break;
		case 'guests':
			echo esc_html( get_post_meta( $post_id, '_bhela_guests', true ) ?: '—' );
			break;
		case 'total':
			$total = (int) get_post_meta( $post_id, '_bhela_total', true );
			$paid  = (int) get_post_meta( $post_id, '_bhela_paid_amount', true );
			echo $total ? esc_html( bhela_bm_money( $total ) ) . ' / <span class="bha-settled">' . esc_html( bhela_bm_money( $paid ) ) . '</span>' : '—';
			break;
		case 'bstatus':
			$status = get_post_meta( $post_id, '_bhela_status', true ) ?: 'pending';
			echo bhela_bm_booking_pill( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
			break;
	}
}
add_action( 'manage_bhela_booking_posts_custom_column', 'bhela_bm_table_column_content', 10, 2 );

function bhela_bm_sortable_columns( $columns ) {
	$columns['travel_date'] = 'travel_date';
	return $columns;
}
add_filter( 'manage_edit-bhela_booking_sortable_columns', 'bhela_bm_sortable_columns' );

/** Status filter dropdown. */
function bhela_bm_status_filter() {
	global $typenow;
	if ( 'bhela_booking' !== $typenow ) {
		return;
	}
	$current = isset( $_GET['bhela_status'] ) ? sanitize_key( $_GET['bhela_status'] ) : '';
	echo '<select name="bhela_status"><option value="">' . esc_html__( 'All statuses', 'bhela-booking' ) . '</option>';
	foreach ( bhela_bm_statuses() as $key => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
	}
	echo '</select>';
}
add_action( 'restrict_manage_posts', 'bhela_bm_status_filter' );

function bhela_bm_status_filter_query( $query ) {
	global $pagenow;
	if ( is_admin() && 'edit.php' === $pagenow && $query->is_main_query()
		&& 'bhela_booking' === ( $_GET['post_type'] ?? '' ) && ! empty( $_GET['bhela_status'] ) ) {
		$query->set( 'meta_key', '_bhela_status' );
		$query->set( 'meta_value', sanitize_key( $_GET['bhela_status'] ) );
	}
}
add_action( 'pre_get_posts', 'bhela_bm_status_filter_query' );

/* ---------- Meta boxes ---------- */

function bhela_bm_add_meta_boxes() {
	add_meta_box( 'bhela_booking_details', __( 'Booking Details', 'bhela-booking' ), 'bhela_bm_details_metabox', 'bhela_booking', 'normal', 'high' );
	add_meta_box( 'bhela_booking_actions', __( 'Invoice & Actions', 'bhela-booking' ), 'bhela_bm_actions_metabox', 'bhela_booking', 'side', 'high' );
	add_meta_box( 'bhela_booking_discount', __( 'Discount & Counter-Offer', 'bhela-booking' ), 'bhela_bm_discount_metabox', 'bhela_booking', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'bhela_bm_add_meta_boxes' );

/** Admin discount panel: base → %/flat/custom → offer price → apply. */
function bhela_bm_discount_metabox( $post ) {
	$base      = (int) ( get_post_meta( $post->ID, '_bhela_base_price', true ) ?: get_post_meta( $post->ID, '_bhela_total', true ) );
	$requested = (int) get_post_meta( $post->ID, '_bhela_requested_price', true );
	$disc_msg  = get_post_meta( $post->ID, '_bhela_discount_msg', true );
	$pct       = get_post_meta( $post->ID, '_bhela_discount_percent', true );
	$flat      = get_post_meta( $post->ID, '_bhela_discount_flat', true );
	$custom    = get_post_meta( $post->ID, '_bhela_custom_total', true );
	$offer     = (int) get_post_meta( $post->ID, '_bhela_offer_price', true );
	$full      = get_post_meta( $post->ID, '_bhela_full_boat', true );
	$saving    = ( $offer && $base > $offer ) ? $base - $offer : 0;
	?>
	<div class="bha-disc">
		<?php if ( $full ) : ?>
			<?php $fb_total = (int) get_post_meta( $post->ID, '_bhela_total', true ); ?>
			<p class="bha-callout bha-callout--attention bha-callout--lead">🚢 <strong><?php esc_html_e( 'Full Boat — the whole boat, priced by hand.', 'bhela-booking' ); ?></strong>
				<?php if ( $fb_total < 1 ) : ?>
					<?php esc_html_e( 'No price set yet — name it with Custom Total below, or in the Total field.', 'bhela-booking' ); ?>
				<?php else : ?>
					<?php
					printf(
						/* translators: %s: money amount */
						esc_html__( 'Agreed price: %s.', 'bhela-booking' ),
						esc_html( bhela_bm_money( $fb_total ) )
					);
					?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<?php if ( $requested || $disc_msg ) : ?>
			<div class="bha-callout bha-callout--attention bha-callout--lead">💬 <strong><?php esc_html_e( 'Guest request', 'bhela-booking' ); ?></strong><br>
				<?php if ( $requested ) : ?><?php esc_html_e( 'Budget:', 'bhela-booking' ); ?> <strong><?php echo esc_html( bhela_bm_money( $requested ) ); ?></strong><br><?php endif; ?>
				<?php if ( $disc_msg ) : ?><em><?php echo esc_html( $disc_msg ); ?></em><?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="bha-disc__base bha-disc__row">
			<span><?php esc_html_e( 'Base Price', 'bhela-booking' ); ?></span>
			<strong><?php echo esc_html( bhela_bm_money( $base ) ); ?></strong>
		</div>

		<?php
		// text + inputmode, not type=number: a Bangla-locale admin renders numeric
		// spinners in Bengali digits (০, ২০০০), which then cast to 0 on save.
		?>
		<div class="bha-disc__f">
			<label  for="bhela_discount_percent"><?php esc_html_e( 'Discount %', 'bhela-booking' ); ?></label>
			<input type="text" id="bhela_discount_percent" name="bhela_discount_percent" inputmode="decimal" pattern="[0-9.]*" placeholder="0" value="<?php echo esc_attr( $pct ); ?>">
		</div>
		<div class="bha-disc__f">
			<label  for="bhela_discount_flat"><?php esc_html_e( 'Flat Discount (৳)', 'bhela-booking' ); ?></label>
			<input type="text" id="bhela_discount_flat" name="bhela_discount_flat" inputmode="numeric" pattern="[0-9]*" placeholder="0" value="<?php echo esc_attr( $flat ); ?>">
		</div>
		<div class="bha-disc__f">
			<label  for="bhela_custom_total"><?php esc_html_e( 'Custom Total (৳)', 'bhela-booking' ); ?></label>
			<input type="text" id="bhela_custom_total" name="bhela_custom_total" inputmode="numeric" pattern="[0-9]*" placeholder="0" value="<?php echo esc_attr( $custom ); ?>">
			<p class="bha-note"><?php esc_html_e( 'Set this to name an exact price — it overrides both discounts above.', 'bhela-booking' ); ?></p>
		</div>

		<?php if ( $offer ) : ?>
			<div class="bha-disc__out">
				<div class="bha-disc__row">
					<span><?php esc_html_e( 'Computed Offer', 'bhela-booking' ); ?></span>
					<strong><?php echo esc_html( bhela_bm_money( $offer ) ); ?></strong>
				</div>
				<?php if ( $saving ) : ?>
					<div class="bha-disc__row bha-disc__row--save">
						<span><?php esc_html_e( 'Guest saves', 'bhela-booking' ); ?></span>
						<strong><?php echo esc_html( bhela_bm_money( $saving ) ); ?></strong>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<label class="bha-disc__apply">
			<input type="checkbox" name="bhela_apply_offer" value="1">
			<span><strong><?php esc_html_e( 'Apply this offer as the booking Total when I save', 'bhela-booking' ); ?></strong><br>
			<?php esc_html_e( 'Only changes the Total. The Advance stays exactly as you set it.', 'bhela-booking' ); ?></span>
		</label>

		<p class="description" style="margin-top:10px"><?php esc_html_e( 'Offer = Custom Total, or Base minus the % and the flat discount.', 'bhela-booking' ); ?></p>
	</div>
	<?php
}

function bhela_bm_details_metabox( $post ) {
	wp_nonce_field( 'bhela_bm_save', 'bhela_bm_nonce' );
	$m = function ( $k, $d = '' ) use ( $post ) {
		$v = get_post_meta( $post->ID, $k, true );
		return '' !== $v ? $v : $d;
	};
	$rates     = bhela_bm_get_rates();
	$cabin_key = $m( '_bhela_cabin_key' );
	?>
	<table class="form-table bha-form">
		<tr><th><?php esc_html_e( 'Phone', 'bhela-booking' ); ?> *</th>
			<td><input type="text" name="bhela_phone" value="<?php echo esc_attr( $m( '_bhela_phone' ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Email', 'bhela-booking' ); ?></th>
			<td><input type="email" name="bhela_email" value="<?php echo esc_attr( $m( '_bhela_email' ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Address', 'bhela-booking' ); ?></th>
			<td><input type="text" class="regular-text" name="bhela_address" value="<?php echo esc_attr( $m( '_bhela_address' ) ); ?>" placeholder="<?php esc_attr_e( 'Sunamganj', 'bhela-booking' ); ?>">
				<p class="description"><?php esc_html_e( 'Shown on the confirmation message. Optional.', 'bhela-booking' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Travel Date', 'bhela-booking' ); ?> *</th>
			<td><input type="date" name="bhela_travel_date" value="<?php echo esc_attr( $m( '_bhela_travel_date' ) ); ?>">
				<?php
				$stay = bhela_bm_booking_stay( $post->ID );
				if ( $stay['in'] ) :
					?>
					<p class="description">
						<?php
						printf(
							/* translators: 1: check-in date, 2: check-in window, 3: check-out date, 4: check-out window */
							esc_html__( 'Check-in %1$s (%2$s) - check-out %3$s (%4$s). Both follow this date; the windows are set in Settings.', 'bhela-booking' ),
							esc_html( mysql2date( 'j M Y', $stay['in'] ) ),
							esc_html( $stay['in_time'] ),
							esc_html( mysql2date( 'j M Y', $stay['out'] ) ),
							esc_html( $stay['out_time'] )
						);
						?>
					</p>
				<?php endif; ?>
			</td></tr>
		<tr><th><?php esc_html_e( 'Room No.', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_room_no" value="<?php echo esc_attr( $m( '_bhela_room_no' ) ); ?>" placeholder="02, 03">
				<p class="description"><?php esc_html_e( 'Which physical rooms this guest has. The engine counts cabins; it does not name them.', 'bhela-booking' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Boarding Ghat', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_boarding" value="<?php echo esc_attr( $m( '_bhela_boarding' ) ); ?>" placeholder="<?php echo esc_attr( bhela_bm_get_settings()['boarding_ghat'] ?? '' ); ?>">
				<p class="description"><?php esc_html_e( 'Leave empty to use the default from Settings.', 'bhela-booking' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Booking taken by', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_booked_by" value="<?php echo esc_attr( $m( '_bhela_booked_by' ) ); ?>">
				<p class="description"><?php esc_html_e( 'Who took this booking. Filled with your name on first save; a phone booking entered by someone else can be corrected here.', 'bhela-booking' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Confirmation issued by', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_issued_by" value="<?php echo esc_attr( $m( '_bhela_issued_by' ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Cabin', 'bhela-booking' ); ?></th>
			<td><select name="bhela_cabin_key">
				<option value=""><?php esc_html_e( '— Custom / Unknown —', 'bhela-booking' ); ?></option>
				<?php foreach ( $rates as $key => $row ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cabin_key, $key ); ?>><?php echo esc_html( $row['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Changing cabin/date/guests recalculates the price on save (unless manual override is checked).', 'bhela-booking' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></th>
			<td><input type="number" name="bhela_guests" min="1" max="<?php echo esc_attr( bhela_bm_max_guests() ); ?>" value="<?php echo esc_attr( $m( '_bhela_guests', 1 ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Per Person (৳)', 'bhela-booking' ); ?></th>
			<td><input type="number" name="bhela_per_person" value="<?php echo esc_attr( $m( '_bhela_per_person' ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Total (৳)', 'bhela-booking' ); ?></th>
			<td><input type="number" name="bhela_total" value="<?php echo esc_attr( $m( '_bhela_total' ) ); ?>">
			<label style="margin-left:8px"><input type="checkbox" name="bhela_manual_price" value="1" <?php checked( $m( '_bhela_manual_price' ), '1' ); ?>> <?php esc_html_e( 'Manual price override', 'bhela-booking' ); ?></label></td></tr>
		<?php
		// Beside the Total, because typing the Total is how a whole boat gets its
		// price — and in the same table as the cabin fields it overrides, rather
		// than in the Discount box, where the cause and its effect would be in two
		// separately-collapsible panels.
		?>
		<tr><th><?php esc_html_e( 'Full Boat', 'bhela-booking' ); ?></th>
			<td><label><input type="checkbox" name="bhela_full_boat" id="bhela_full_boat" value="1" <?php checked( $m( '_bhela_full_boat' ), '1' ); ?>>
				<strong>🚢 <?php printf(
					/* translators: 1: cabin count, 2: guest capacity */
					esc_html__( 'Whole-boat booking — takes all %1$d cabins (up to %2$d guests)', 'bhela-booking' ),
					(int) bhela_bm_max_cabins(),
					(int) bhela_bm_max_guests()
				); ?></strong></label>
			<p class="description"><?php esc_html_e( 'The cabin combination below is ignored — the boat is sold as one unit, and the price is whatever you type in Total above (or name with Custom Total in the Discount box). Put the real head count in Guests: it goes on the invoice and in the SMS. Untick to go back to per-cabin pricing.', 'bhela-booking' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Advance (৳)', 'bhela-booking' ); ?></th>
			<?php
			$adv_total = (int) $m( '_bhela_total' );
			$adv_pct   = bhela_bm_advance_pct( $m( '_bhela_advance' ), $adv_total );
			$adv_sugg  = (int) ceil( $adv_total * ( (float) bhela_bm_get_settings()['advance_percent'] / 100 ) );
			?>
			<td><input type="number" name="bhela_advance" min="0" value="<?php echo esc_attr( $m( '_bhela_advance' ) ); ?>">
			<strong style="margin-left:8px;color:#137A74"><?php echo esc_html( $adv_pct ); ?>%</strong>
			<p class="description">
				<?php esc_html_e( 'Whatever advance you actually agreed — any amount. Your figure is kept exactly as typed and is never recalculated, not even when the price or cabins change.', 'bhela-booking' ); ?>
				<?php if ( $adv_total > 0 ) : ?>
					<br><?php printf(
						/* translators: 1: advance percent setting, 2: money amount */
						esc_html__( 'For reference, %1$d%% of the current Total is %2$s.', 'bhela-booking' ),
						(int) bhela_bm_get_settings()['advance_percent'],
						esc_html( bhela_bm_money( $adv_sugg ) )
					); ?>
				<?php endif; ?>
			</p></td></tr>
		<tr><th><?php esc_html_e( 'Paid Amount (৳)', 'bhela-booking' ); ?></th>
			<td><input type="number" name="bhela_paid_amount" value="<?php echo esc_attr( $m( '_bhela_paid_amount', 0 ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Payment Method', 'bhela-booking' ); ?></th>
			<td><select name="bhela_pay_method">
				<?php foreach ( array( '' => '—', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'bank' => 'Bank Transfer', 'cash' => 'Cash' ) as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $m( '_bhela_pay_method' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
		<tr><th><?php esc_html_e( 'Transaction ID', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_txn_id" value="<?php echo esc_attr( $m( '_bhela_txn_id' ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Customer Note', 'bhela-booking' ); ?></th>
			<td><textarea name="bhela_message" rows="3"><?php echo esc_textarea( $m( '_bhela_message' ) ); ?></textarea></td></tr>
	</table>

	<?php
	$cabins = json_decode( (string) get_post_meta( $post->ID, '_bhela_cabins_json', true ), true );
	if ( ! is_array( $cabins ) || ! $cabins ) {
		$cabins = array( array( 'adults' => (int) $m( '_bhela_guests', 2 ), 'c48' => 0, 'c04' => 0 ) );
	}
	$max_cap = max( array_keys( bhela_bm_rates_by_occupancy() ) );
	?>
	<h4 style="margin:14px 0 6px"><?php esc_html_e( '🛏️ Cabin Combination (edit & recalculate)', 'bhela-booking' ); ?></h4>
	<p class="description" style="margin:0 0 8px"><?php printf( esc_html__( 'Each cabin = %1$d–%2$d people. Tick "Recalculate" to reprice from this combination on save (occupancy-based; 0–4 infants free).', 'bhela-booking' ), 2, (int) $max_cap ); ?></p>
	<?php
	// Shown by the script below whenever Full Boat is ticked. A dimmed table still
	// looks like the place to type a head count — one admin put 25 in the Adults
	// cell, where the per-cabin maximum of 6 then refused to let the page save.
	?>
	<p class="bha-callout bha-callout--attention" id="bhela-combo-off-note" style="max-width:520px" hidden>🚢 <?php printf(
		/* translators: 1: the bolded "Guests" field label, 2: guest capacity */
		esc_html__( 'Full Boat is on, so this combination is ignored and locked. The head count goes in %1$s above (up to %2$d).', 'bhela-booking' ),
		'<strong>' . esc_html__( 'Guests', 'bhela-booking' ) . '</strong>',
		(int) bhela_bm_max_guests()
	); ?></p>
	<table class="widefat" id="bhela-combo-table" style="max-width:520px">
		<thead><tr><th><?php esc_html_e( 'Cabin', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Adults (9+)', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Child 4–8', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Infant 0–4', 'bhela-booking' ); ?></th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $cabins as $i => $cab ) : ?>
			<tr>
				<td><?php echo (int) $i + 1; ?></td>
				<td><input type="number" name="bhela_cabin_adults[]" min="0" max="<?php echo esc_attr( $max_cap ); ?>" value="<?php echo esc_attr( (int) ( $cab['adults'] ?? 0 ) ); ?>" style="width:70px"></td>
				<td><input type="number" name="bhela_cabin_c48[]" min="0" max="<?php echo esc_attr( $max_cap ); ?>" value="<?php echo esc_attr( (int) ( $cab['c48'] ?? 0 ) ); ?>" style="width:70px"></td>
				<td><input type="number" name="bhela_cabin_c04[]" min="0" max="<?php echo esc_attr( $max_cap ); ?>" value="<?php echo esc_attr( (int) ( $cab['c04'] ?? 0 ) ); ?>" style="width:70px"></td>
				<td><button type="button" class="button bhela-combo-del">✕</button></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p>
		<button type="button" class="button" id="bhela-combo-add">➕ <?php esc_html_e( 'Add Cabin', 'bhela-booking' ); ?></button>
		<label style="margin-left:12px"><input type="checkbox" name="bhela_combo_recalc" value="1"> <strong><?php esc_html_e( 'Recalculate price from combination on save', 'bhela-booking' ); ?></strong></label>
	</p>
	<script>
	(function () {
		var tbl = document.getElementById('bhela-combo-table');
		var max = <?php echo (int) $max_cap; ?>;
		function renum() { tbl.querySelectorAll('tbody tr').forEach(function (tr, i) { tr.cells[0].textContent = i + 1; }); }
		document.getElementById('bhela-combo-add').addEventListener('click', function () {
			var tr = document.createElement('tr');
			tr.innerHTML = '<td></td>' +
				'<td><input type="number" name="bhela_cabin_adults[]" min="0" max="' + max + '" value="2" style="width:70px"></td>' +
				'<td><input type="number" name="bhela_cabin_c48[]" min="0" max="' + max + '" value="0" style="width:70px"></td>' +
				'<td><input type="number" name="bhela_cabin_c04[]" min="0" max="' + max + '" value="0" style="width:70px"></td>' +
				'<td><button type="button" class="button bhela-combo-del">✕</button></td>';
			tbl.querySelector('tbody').appendChild(tr); renum();
		});
		tbl.addEventListener('click', function (e) {
			if (e.target.classList.contains('bhela-combo-del')) {
				var rows = tbl.querySelectorAll('tbody tr');
				if (rows.length > 1) { e.target.closest('tr').remove(); renum(); }
			}
		});

		// Full Boat sells the boat as one unit, so the combination above stops
		// meaning anything. Disabled rather than emptied: unticking must put the
		// admin back exactly where they were, and the server ignores these rows
		// while the flag is set regardless of what the browser did.
		//
		// `disabled` is load-bearing, not decoration. Dimming alone left the number
		// inputs live, so their max="6" still ran through HTML5 constraint
		// validation and refused to submit the page at all — an admin who typed the
		// head count into the Adults cell got "Value must be less than or equal
		// to 6" and could not save the booking. A disabled control is skipped by
		// validation and is not posted.
		var fb     = document.getElementById('bhela_full_boat');
		var recalc = document.querySelector('input[name="bhela_combo_recalc"]');
		var addBtn = document.getElementById('bhela-combo-add');
		var offNote = document.getElementById('bhela-combo-off-note');
		function syncFullBoat() {
			var on = !!(fb && fb.checked);
			tbl.classList.toggle('bha-combo-off', on);
			tbl.querySelectorAll('input, button').forEach(function (el) { el.disabled = on; });
			if (addBtn) { addBtn.disabled = on; }
			// A disabled checkbox is not posted either — a second layer behind the
			// server-side guard, and it stops a leftover tick from looking armed.
			if (recalc) { recalc.disabled = on; if (on) { recalc.checked = false; } }
			if (offNote) { offNote.hidden = !on; }
		}
		if (fb) { fb.addEventListener('change', syncFullBoat); syncFullBoat(); }
	})();
	</script>
	<?php
}

function bhela_bm_actions_metabox( $post ) {
	$status     = get_post_meta( $post->ID, '_bhela_status', true ) ?: 'pending';
	$invoice_no = get_post_meta( $post->ID, '_bhela_invoice_no', true );
	$email      = get_post_meta( $post->ID, '_bhela_email', true );
	?>
	<p><strong><?php esc_html_e( 'Invoice No:', 'bhela-booking' ); ?></strong> <?php echo esc_html( $invoice_no ?: '—' ); ?></p>
	<p><label for="bhela_status"><strong><?php esc_html_e( 'Booking Status', 'bhela-booking' ); ?></strong></label><br>
	<select name="bhela_status" id="bhela_status" style="width:100%">
		<?php foreach ( bhela_bm_statuses() as $key => $label ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select></p>
		<p><label><input type="checkbox" name="bhela_overbook" value="1"> ⚠️ <?php esc_html_e( 'Overbook — confirm even if this date has no cabins left (Full Boat / exceptions)', 'bhela-booking' ); ?></label>
		<span class="description"><?php esc_html_e( 'Rarely needed. Without it, confirming is blocked once the date is full.', 'bhela-booking' ); ?></span></p>
		<?php if ( $invoice_no ) : ?>
		<p><a class="button button-secondary" href="<?php echo esc_url( bhela_bm_invoice_url( $post->ID ) ); ?>" target="_blank">🧾 <?php esc_html_e( 'View / Print Invoice', 'bhela-booking' ); ?></a></p>
	<?php endif; ?>
	<?php
	if ( function_exists( 'bhela_bm_confirm_text' ) ) :
		$bhela_confirm = bhela_bm_confirm_text( $post->ID );
		$bhela_wa      = bhela_bm_wa_url( get_post_meta( $post->ID, '_bhela_phone', true ), $bhela_confirm );
		?>
		<p>
			<button type="button" class="button button-primary" id="bhela-copy-confirm"
				data-copied="<?php esc_attr_e( 'Copied', 'bhela-booking' ); ?>">📋 <?php esc_html_e( 'Copy confirmation', 'bhela-booking' ); ?></button>
			<?php if ( $bhela_wa ) : ?>
				<a class="button button-secondary" href="<?php echo esc_url( $bhela_wa ); ?>" target="_blank" rel="noopener">💬 <?php esc_html_e( 'Send on WhatsApp', 'bhela-booking' ); ?></a>
			<?php endif; ?>
		</p>
		<?php // Held in a textarea rather than a data- attribute: the message is multi-line and full of emoji, and a textarea needs no escaping gymnastics to survive both. ?>
		<textarea id="bhela-confirm-text" readonly rows="6" class="large-text code" style="display:none"><?php echo esc_textarea( $bhela_confirm ); ?></textarea>
		<p class="description">
			<a href="#" id="bhela-confirm-toggle"><?php esc_html_e( 'Preview the message', 'bhela-booking' ); ?></a> ·
			<?php esc_html_e( 'Save the booking first if you have just changed anything — this is built from what is stored.', 'bhela-booking' ); ?>
		</p>
		<script>
		( function () {
			var btn = document.getElementById( 'bhela-copy-confirm' ),
				box = document.getElementById( 'bhela-confirm-text' ),
				tog = document.getElementById( 'bhela-confirm-toggle' );
			if ( ! btn || ! box ) { return; }
			tog.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				box.style.display = box.style.display === 'none' ? '' : 'none';
			} );
			btn.addEventListener( 'click', function () {
				var done = function () {
					var was = btn.textContent;
					btn.textContent = '✅ ' + btn.dataset.copied;
					setTimeout( function () { btn.textContent = was; }, 1600 );
				};
				// navigator.clipboard needs a secure context; a LocalWP site is plain
				// http, so the textarea fallback is the path that actually runs here.
				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( box.value ).then( done );
					return;
				}
				box.style.display = '';
				box.select();
				try { document.execCommand( 'copy' ); done(); } catch ( err ) {}
			} );
		}() );
		</script>
	<?php endif; ?>
	<?php if ( function_exists( 'bhela_bm_review_url' ) ) : ?>
		<?php $bhela_rv = function_exists( 'bhela_bm_review_for_booking' ) ? bhela_bm_review_for_booking( $post->ID ) : 0; ?>
		<p><a class="button button-secondary" href="<?php echo esc_url( bhela_bm_review_url( $post->ID ) ); ?>" target="_blank">⭐ <?php esc_html_e( 'Open review form', 'bhela-booking' ); ?></a></p>
		<p class="description">
			<?php if ( $bhela_rv ) : ?>
				<?php
				printf(
					/* translators: %s: link to the submitted review */
					esc_html__( 'This guest has already reviewed the trip — %s.', 'bhela-booking' ),
					'<a href="' . esc_url( get_edit_post_link( $bhela_rv ) ) . '">'
						. ( 'pending' === get_post_status( $bhela_rv )
							? esc_html__( 'awaiting your approval', 'bhela-booking' )
							: esc_html__( 'published', 'bhela-booking' ) )
						. '</a>'
				);
				?>
			<?php elseif ( 'completed' === $status ) : ?>
				<?php esc_html_e( 'This is the private link emailed and texted to the guest. Nothing submitted yet.', 'bhela-booking' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Preview only — guests can open this once the booking is set to Completed.', 'bhela-booking' ); ?>
			<?php endif; ?>
		</p>
	<?php endif; ?>
	<?php if ( $email ) : ?>
		<p><label><input type="checkbox" name="bhela_send_email" value="1"> <?php esc_html_e( 'Email summary + invoice link to customer on save', 'bhela-booking' ); ?></label></p>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'No customer email on file — add one to send the invoice by email.', 'bhela-booking' ); ?></p>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'Setting status to "Confirmed" automatically emails a confirmation (if email exists).', 'bhela-booking' ); ?></p>
	<?php
}

/** Save handler. */
function bhela_bm_save_booking( $post_id, $post ) {
	if ( 'bhela_booking' !== $post->post_type ) {
		return;
	}
	if ( ! isset( $_POST['bhela_bm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_bm_nonce'] ) ), 'bhela_bm_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Capture the pre-save travel date so the capacity guard can tell a date
	// move (into a possibly-full date) from a plain status change.
	$old_date = get_post_meta( $post_id, '_bhela_travel_date', true );

	// And the pre-save footprint, for the same reason: ticking Full Boat grows a
	// booking from one or two cabins to six without moving status or date.
	$old_cabins = (int) bhela_bm_booking_cabin_count( $post_id );

	$fields = array(
		'_bhela_phone'        => sanitize_text_field( $_POST['bhela_phone'] ?? '' ),
		'_bhela_email'        => sanitize_email( $_POST['bhela_email'] ?? '' ),
		'_bhela_address'      => sanitize_text_field( $_POST['bhela_address'] ?? '' ),
		'_bhela_travel_date'  => sanitize_text_field( $_POST['bhela_travel_date'] ?? '' ),
		'_bhela_room_no'      => sanitize_text_field( $_POST['bhela_room_no'] ?? '' ),
		// Blank is meaningful: it means "use the Settings default", so it is stored
		// as blank rather than being back-filled with the setting at save time. A
		// copied-in default would freeze today's ghat onto the booking for ever.
		'_bhela_boarding'     => sanitize_text_field( $_POST['bhela_boarding'] ?? '' ),
		'_bhela_cabin_key'    => sanitize_key( $_POST['bhela_cabin_key'] ?? '' ),
		'_bhela_guests'       => max( 1, (int) ( $_POST['bhela_guests'] ?? 1 ) ),
		'_bhela_pay_method'   => sanitize_key( $_POST['bhela_pay_method'] ?? '' ),
		'_bhela_txn_id'       => sanitize_text_field( $_POST['bhela_txn_id'] ?? '' ),
		'_bhela_message'      => sanitize_textarea_field( $_POST['bhela_message'] ?? '' ),
		'_bhela_paid_amount'  => max( 0, (int) ( $_POST['bhela_paid_amount'] ?? 0 ) ),
		'_bhela_manual_price' => isset( $_POST['bhela_manual_price'] ) ? '1' : '',
		// Going through the loop below is the whole un-tick contract: an absent
		// checkbox writes '', on every save, so the flag can never get stuck on.
		'_bhela_full_boat'    => isset( $_POST['bhela_full_boat'] ) ? '1' : '',
	);
	foreach ( $fields as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}

	// Staff attribution. Kept out of the loop above deliberately: that loop writes
	// whatever was posted, including '', which is right for a field the admin can
	// clear and wrong for these two. They default to whoever is saving, and once a
	// name is on the booking an empty box must not wipe it — a booking taken by
	// Nishat should not lose her name because a manager opened it and pressed
	// Update. Typing a different name still replaces it.
	//
	// Stored as text rather than a user ID on purpose: staff on the salary roster
	// do not all have WordPress logins, and a phone booking is often entered by
	// somebody other than the person who took the call.
	$who = wp_get_current_user();
	$who = $who && $who->exists() ? $who->display_name : '';
	foreach ( array( '_bhela_booked_by' => 'bhela_booked_by', '_bhela_issued_by' => 'bhela_issued_by' ) as $meta => $field ) {
		$posted = sanitize_text_field( $_POST[ $field ] ?? '' );
		if ( '' !== $posted ) {
			update_post_meta( $post_id, $meta, $posted );
		} elseif ( '' === (string) get_post_meta( $post_id, $meta, true ) ) {
			update_post_meta( $post_id, $meta, $who );
		}
	}

	// `_bhela_day_type` is a label, not a price, so unlike the Total it is safe —
	// and necessary — to refresh on every save. It used to be written only by the
	// two repricing branches below, neither of which a booking taken online can
	// reach, so moving the Travel Date left the old label in place and the invoice
	// printed "Weekend" against a Monday. Written here: after the loop above, so
	// the new travel date is already stored, and ahead of those branches, which
	// then restate the identical value from the same engine.
	update_post_meta( $post_id, '_bhela_day_type', bhela_bm_booking_day_type( $post_id ) );

	$full_boat = '1' === $fields['_bhela_full_boat'];

	// A whole-boat booking is priced by hand, always — there is no per-cabin rate
	// for "the boat". Forcing the override here rather than trusting the admin to
	// tick two boxes is what stops the reprice branch below from valuing the boat as
	// a single cabin the moment a cabin type is picked in the dropdown. The local
	// array is updated as well as the meta, because that is what the branch reads.
	if ( $full_boat && '1' !== $fields['_bhela_manual_price'] ) {
		$fields['_bhela_manual_price'] = '1';
		update_post_meta( $post_id, '_bhela_manual_price', '1' );
	}

	if ( ! get_post_meta( $post_id, '_bhela_invoice_no', true ) ) {
		update_post_meta( $post_id, '_bhela_invoice_no', bhela_bm_next_invoice_number() );
	}

	$cabin_key = $fields['_bhela_cabin_key'];
	if ( '1' === $fields['_bhela_manual_price'] || ! $cabin_key ) {
		// A lump sum has no per-person rate, and templates/invoice.php would print
		// one if it were left there. Mirrors bhela_bm_process_submission().
		update_post_meta( $post_id, '_bhela_per_person', $full_boat ? 0 : (int) ( $_POST['bhela_per_person'] ?? 0 ) );
		update_post_meta( $post_id, '_bhela_total', (int) ( $_POST['bhela_total'] ?? 0 ) );
	} else {
		$price = bhela_bm_calc_price( $cabin_key, $fields['_bhela_guests'], $fields['_bhela_travel_date'] );
		if ( ! is_wp_error( $price ) ) {
			update_post_meta( $post_id, '_bhela_cabin_type', $price['cabin_label'] );
			update_post_meta( $post_id, '_bhela_day_type', $price['day_type'] );
			update_post_meta( $post_id, '_bhela_per_person', $price['per_person'] );
			update_post_meta( $post_id, '_bhela_total', $price['total'] );
		}
	}

	// Cabin combination editor → reprice via the occupancy engine. A whole-boat
	// booking has no per-cabin price, so the combination is ignored entirely:
	// otherwise a "Recalculate" tick left over from before the boat was sold as one
	// unit would overwrite the agreed lump sum with the price of whatever rows
	// happen to still be in the table.
	if ( ! $full_boat && ! empty( $_POST['bhela_combo_recalc'] ) ) {
		$adults_in = (array) ( $_POST['bhela_cabin_adults'] ?? array() );
		$c48_in    = (array) ( $_POST['bhela_cabin_c48'] ?? array() );
		$c04_in    = (array) ( $_POST['bhela_cabin_c04'] ?? array() );
		$combo     = array();
		foreach ( $adults_in as $i => $a ) {
			$combo[] = array(
				'adults' => max( 0, (int) $a ),
				'c48'    => max( 0, (int) ( $c48_in[ $i ] ?? 0 ) ),
				'c04'    => max( 0, (int) ( $c04_in[ $i ] ?? 0 ) ),
			);
		}
		$cprice = bhela_bm_calc_multi( $combo, $fields['_bhela_travel_date'] );
		if ( is_wp_error( $cprice ) ) {
			set_transient( 'bhela_combo_err_' . $post_id, $cprice->get_error_message(), 45 );
		} else {
			$parts = array();
			foreach ( $cprice['lines'] as $l ) {
				$parts[] = $l['label'] . ' (' . $l['who'] . ')';
			}
			update_post_meta( $post_id, '_bhela_cabins_json', wp_json_encode( $combo, JSON_UNESCAPED_UNICODE ) );
			update_post_meta( $post_id, '_bhela_cabin_type', implode( ' + ', $parts ) );
			update_post_meta( $post_id, '_bhela_guests', $cprice['guests'] );
			update_post_meta( $post_id, '_bhela_day_type', $cprice['day_type'] );
			update_post_meta( $post_id, '_bhela_per_person', 0 );
			update_post_meta( $post_id, '_bhela_total', $cprice['total'] );
			update_post_meta( $post_id, '_bhela_base_price', $cprice['total'] );
			update_post_meta( $post_id, '_bhela_manual_price', '1' );
		}
	}

	// Always persist an accurate cabin count AND head count — never depend on the
	// "Recalculate" checkbox. The combo table is posted on every edit, so count
	// the rows that actually hold a guest; fall back to the stored cabins JSON,
	// then to a single cabin.
	//
	// The head count matters as much as the cabin count: the plain "Guests" number
	// field is written on every save, so a stale value there would otherwise
	// overwrite the real total and go out in the SMS and emails — an invoice
	// listing four people alongside a text reading "Guests: 1".
	if ( $full_boat ) {
		// Sold as one unit: it takes every cabin, whatever the combination table
		// happens to show. The cabin count is what the capacity guard below reads,
		// so getting this wrong would let a full boat be confirmed onto a date that
		// already has cabins sold.
		update_post_meta( $post_id, '_bhela_cabin_count', bhela_bm_max_cabins() );
		update_post_meta( $post_id, '_bhela_cabin_type', bhela_bm_full_boat_label() );
		// The head count stays the admin's, unlike on the booking form, which has to
		// assume a full 36: an admin has the real number in front of them, and it is
		// what goes on the invoice and in the SMS. Clamped anyway, because the
		// input's max attribute is only advice.
		update_post_meta( $post_id, '_bhela_guests', min(
			bhela_bm_max_guests(),
			max( 1, (int) ( $_POST['bhela_guests'] ?? 1 ) )
		) );
	} elseif ( isset( $_POST['bhela_cabin_adults'] ) && is_array( $_POST['bhela_cabin_adults'] ) ) {
		$adults_c = (array) $_POST['bhela_cabin_adults'];
		$c48_c    = (array) ( $_POST['bhela_cabin_c48'] ?? array() );
		$c04_c    = (array) ( $_POST['bhela_cabin_c04'] ?? array() );
		$cnt      = 0;
		$heads    = 0;
		foreach ( $adults_c as $i => $a ) {
			$adults = (int) $a;
			$c48    = (int) ( $c48_c[ $i ] ?? 0 );
			$c04    = (int) ( $c04_c[ $i ] ?? 0 );
			if ( $adults + $c48 + $c04 > 0 ) {
				$cnt++;
				// Match bhela_bm_calc_multi(): "guests" means paying occupants, so
				// 0–4 infants ride along without being counted. Counting them here
				// would put a different number in the SMS than on the invoice.
				$heads += $adults + $c48;
			}
		}
		update_post_meta( $post_id, '_bhela_cabin_count', max( 1, $cnt ) );
		if ( $heads > 0 ) {
			update_post_meta( $post_id, '_bhela_guests', $heads );
		}
	} elseif ( '' === get_post_meta( $post_id, '_bhela_cabin_count', true ) ) {
		$rows = json_decode( (string) get_post_meta( $post_id, '_bhela_cabins_json', true ), true );
		update_post_meta( $post_id, '_bhela_cabin_count', is_array( $rows ) && $rows ? count( $rows ) : 1 );
	}

	// Discount & counter-offer panel.
	$base   = (int) ( get_post_meta( $post_id, '_bhela_base_price', true ) ?: get_post_meta( $post_id, '_bhela_total', true ) );
	$pct    = max( 0, min( 100, (float) ( $_POST['bhela_discount_percent'] ?? 0 ) ) );
	$flat   = max( 0, (int) ( $_POST['bhela_discount_flat'] ?? 0 ) );
	$custom = max( 0, (int) ( $_POST['bhela_custom_total'] ?? 0 ) );
	update_post_meta( $post_id, '_bhela_discount_percent', $pct );
	update_post_meta( $post_id, '_bhela_discount_flat', $flat );
	update_post_meta( $post_id, '_bhela_custom_total', $custom );
	$offer = $custom > 0 ? $custom : max( 0, (int) round( $base - ( $base * $pct / 100 ) - $flat ) );
	update_post_meta( $post_id, '_bhela_offer_price', $offer );
	if ( ! empty( $_POST['bhela_apply_offer'] ) && $offer > 0 ) {
		update_post_meta( $post_id, '_bhela_total', $offer );
		update_post_meta( $post_id, '_bhela_manual_price', '1' );
	}

	// Checked after the discount panel, so an applied Custom Total counts as a
	// price. A full boat at ৳0 is an unpriced quote — legitimate when the guest
	// created it, almost certainly a forgotten field when an admin did. Nothing
	// else on the screen would say so: the invoice would go out with a ৳0 total,
	// and it would not print PAID either.
	if ( $full_boat && (int) get_post_meta( $post_id, '_bhela_total', true ) < 1 ) {
		set_transient( 'bhela_bm_fb_warn_' . $post_id, 1, 45 );
	}

	// The Advance is the admin's decision, never a formula. 50% is only the
	// suggestion a *new* booking is created with (see bhela_bm_process_submission);
	// from then on this field is the single source of truth and no repricing path
	// above may touch it. Repricing the trip changes the Total, not what the guest
	// was asked to pay up front — the metabox shows the current 50% figure beside
	// the field so it can be re-applied by hand when that is what is wanted.
	if ( isset( $_POST['bhela_advance'] ) ) {
		update_post_meta( $post_id, '_bhela_advance', max( 0, (int) $_POST['bhela_advance'] ) );
	}

	$old_status     = get_post_meta( $post_id, '_bhela_status', true ) ?: 'pending';
	$new_status     = sanitize_key( $_POST['bhela_status'] ?? $old_status );
	$new_date       = $fields['_bhela_travel_date'];
	$sent_confirmed = false;
	$invoice_ref    = get_post_meta( $post_id, '_bhela_invoice_no', true );

	// ---- Capacity guard ---------------------------------------------------
	// A booking "consumes" a cabin once it is advance_paid, confirmed or
	// completed. If this save moves the booking INTO a consuming state (or
	// moves an already-consuming booking to a different date), make sure the
	// date still has room. Block by default; the "Overbook" checkbox forces it
	// through for Full Boat / exceptions.
	$consuming   = array( 'advance_paid', 'confirmed', 'completed' );
	// The third arm catches a booking that grew: ticking Full Boat on an already
	// confirmed, same-date two-cabin booking triples its footprint without moving
	// status or date, and the first two arms would never have looked.
	$is_entering = in_array( $new_status, $consuming, true )
		&& ( ! in_array( $old_status, $consuming, true )
			|| $new_date !== $old_date
			|| bhela_bm_booking_cabin_count( $post_id ) > $old_cabins );
	$override    = ! empty( $_POST['bhela_overbook'] );
	$cap_blocked = false;

	if ( $is_entering && $new_date && function_exists( 'bhela_bm_counted_booked_cabins' ) ) {
		// Free = capacity − manual hold − other online-sold cabins (hold + sold
		// are additive, so the guard must subtract both).
		$manual = function_exists( 'bhela_bm_trip_availability' ) ? (int) bhela_bm_trip_availability( $new_date )['manual'] : 0;
		$others = (int) bhela_bm_counted_booked_cabins( $new_date, $post_id );
		$free   = bhela_bm_max_cabins() - $manual - $others;
		$need   = (int) bhela_bm_booking_cabin_count( $post_id );
		if ( $need > $free && ! $override ) {
			$cap_blocked = true;
			set_transient( 'bhela_bm_cap_err_' . $post_id, sprintf(
				/* translators: 1: travel date, 2: free cabins, 3: cabins needed */
				__( 'Could not confirm — %1$s has only %2$d cabin(s) free, but this booking needs %3$d. The status was left unchanged. Tick "Overbook" to force it through.', 'bhela-booking' ),
				esc_html( $new_date ), max( 0, $free ), $need
			), 45 );
			if ( function_exists( 'bhela_bm_log' ) ) {
				bhela_bm_log( 'error', sprintf( 'Booking %s — confirm blocked: %s has %d cabin(s) free, %d needed.',
					$invoice_ref, $new_date, max( 0, $free ), $need ), false );
			}
		} elseif ( $need > $free && $override && function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'status', sprintf( 'Booking %s — confirmed by overbooking (%s is over capacity).', $invoice_ref, $new_date ) );
		}
	}
	// -----------------------------------------------------------------------

	if ( ! $cap_blocked && array_key_exists( $new_status, bhela_bm_statuses() ) ) {
		update_post_meta( $post_id, '_bhela_status', $new_status );
		if ( $new_status !== $old_status && function_exists( 'bhela_bm_log' ) ) {
			$st_labels = bhela_bm_statuses();
			bhela_bm_log( 'status', sprintf( 'Booking %s — %s → %s',
				$invoice_ref,
				$st_labels[ $old_status ] ?? $old_status,
				$st_labels[ $new_status ] ?? $new_status ) );
		}
		if ( 'confirmed' === $new_status && 'confirmed' !== $old_status ) {
			bhela_bm_email_customer( $post_id, 'confirmed' );
			$sent_confirmed = true;
		}
		// Trip finished: thank the guest and invite a review. Guarded on the
		// transition, so re-saving a completed booking never mails them twice.
		if ( 'completed' === $new_status && 'completed' !== $old_status ) {
			bhela_bm_email_customer( $post_id, 'completed' );
		}
		if ( function_exists( 'bhela_bm_sms_on_status_change' ) ) {
			bhela_bm_sms_on_status_change( $post_id, $new_status, $old_status );
		}
		/**
		 * A booking's status changed.
		 *
		 * The extension point this plugin was missing: every notification so far
		 * had to be wired into this function by hand, which is why it grew so
		 * long. New side effects can hook here instead.
		 *
		 * @param int    $post_id    Booking ID.
		 * @param string $new_status Status it moved to.
		 * @param string $old_status Status it came from.
		 */
		if ( $new_status !== $old_status ) {
			do_action( 'bhela_bm_status_changed', $post_id, $new_status, $old_status );
		}
	} elseif ( $cap_blocked ) {
		$new_status = $old_status; // keep downstream (email) logic honest
	}

	if ( ! empty( $_POST['bhela_send_email'] ) ) {
		$type = ( 'confirmed' === $new_status ) ? 'confirmed' : 'request';
		// Don't double-send: the status→Confirmed change above may have already
		// fired the same confirmation email this save.
		if ( ! ( $sent_confirmed && 'confirmed' === $type ) ) {
			bhela_bm_email_customer( $post_id, $type );
		}
	}
}
add_action( 'save_post', 'bhela_bm_save_booking', 10, 2 );

/** Surface a combination recalculation error after save. */
function bhela_bm_combo_error_notice() {
	global $post;
	if ( ! $post || 'bhela_booking' !== $post->post_type ) {
		return;
	}
	$err = get_transient( 'bhela_combo_err_' . $post->ID );
	if ( $err ) {
		delete_transient( 'bhela_combo_err_' . $post->ID );
		echo '<div class="notice notice-error is-dismissible"><p><strong>Cabin combination not applied:</strong> ' . esc_html( $err ) . '</p></div>';
	}
	$cap = get_transient( 'bhela_bm_cap_err_' . $post->ID );
	if ( $cap ) {
		delete_transient( 'bhela_bm_cap_err_' . $post->ID );
		echo '<div class="notice notice-error is-dismissible"><p>⚠️ ' . esc_html( $cap ) . '</p></div>';
	}
	// A Full Boat with no price is legitimate when a guest asked for a quote, and
	// almost certainly a forgotten field when an admin typed the booking in.
	if ( get_transient( 'bhela_bm_fb_warn_' . $post->ID ) ) {
		delete_transient( 'bhela_bm_fb_warn_' . $post->ID );
		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Full Boat saved with no price.', 'bhela-booking' ),
			esc_html__( 'The invoice will show ৳0 and no balance due. Type the agreed amount in Total (or Custom Total) and save again.', 'bhela-booking' )
		);
	}
}
add_action( 'admin_notices', 'bhela_bm_combo_error_notice' );

/* ---------- Settings page ---------- */

function bhela_bm_settings_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'setup' ),
		__( 'Booking Settings', 'bhela-booking' ),
		'⚙️ ' . __( 'Settings', 'bhela-booking' ),
		'manage_options',
		'bhela-bm-settings',
		'bhela_bm_settings_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_settings_menu' );

function bhela_bm_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['bhela_bm_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_bm_settings_nonce'] ) ), 'bhela_bm_settings' ) ) {
		$s = bhela_bm_get_settings();
		foreach ( array( 'business_name', 'business_tagline', 'address', 'phone_1', 'phone_2', 'whatsapp', 'bkash_number', 'nagad_number', 'invoice_prefix', 'ops_manager', 'support_whatsapp', 'boarding_ghat', 'checkin_time', 'checkout_time', 'package_label' ) as $f ) {
			$s[ $f ] = isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : $s[ $f ];
		}
		$s['email'] = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : $s['email'];
		$s['bank_details']    = sanitize_textarea_field( $_POST['bank_details'] ?? '' );
		$s['nagad_qr']        = esc_url_raw( $_POST['nagad_qr'] ?? '' );
		$s['bangla_qr']       = esc_url_raw( $_POST['bangla_qr'] ?? '' );
		unset( $s['holidays'] ); // holidays now live on the Trip Calendar rows
		$s['invoice_note']    = sanitize_textarea_field( $_POST['invoice_note'] ?? '' );
		// Only overwrite when the Business panel was actually submitted, for the same
		// reason the weekend days are guarded below: a save from another panel would
		// otherwise blank the notes.
		if ( isset( $_POST['boarding_ghat'] ) ) {
			$s['confirm_notes'] = sanitize_textarea_field( $_POST['confirm_notes'] ?? '' );
		}

		// Email notification settings.
		foreach ( array( 'email_enabled', 'email_admin_new', 'email_customer_request', 'email_customer_confirmed', 'email_customer_completed' ) as $f ) {
			$s[ $f ] = empty( $_POST[ $f ] ) ? 0 : 1;
		}
		$s['notify_email']    = sanitize_email( wp_unslash( $_POST['notify_email'] ?? '' ) );
		$s['email_reply_to']  = sanitize_email( wp_unslash( $_POST['email_reply_to'] ?? '' ) );
		$s['email_from_name'] = sanitize_text_field( wp_unslash( $_POST['email_from_name'] ?? '' ) );
		$s['advance_percent'] = min( 100, max( 1, (int) ( $_POST['advance_percent'] ?? 50 ) ) );
		$s['child_fee']       = max( 0, (int) ( $_POST['child_fee'] ?? 5000 ) );
		$s['date_chips']      = min( 20, max( 0, (int) ( $_POST['date_chips'] ?? 5 ) ) );
		// Only rewrite the weekend days when the Pricing Days panel was actually
		// submitted. Unticked checkboxes are simply absent from a POST, so a save
		// that does not include this panel would otherwise clear every weekend
		// day — and with none set, every date silently falls to the discounted
		// weekday rate. A marker field tells the two cases apart.
		if ( isset( $_POST['bhela_pricing_days_present'] ) ) {
			$s['weekend_days'] = bhela_bm_sanitize_weekend_days( $_POST['weekend_days'] ?? array() );
		}

		// Guest review submissions — caps on the only public upload surface.
		$s['review_max_photos'] = min( 10, max( 0, (int) ( $_POST['review_max_photos'] ?? 5 ) ) );
		$s['review_max_mb']     = min( 20, max( 1, (int) ( $_POST['review_max_mb'] ?? 5 ) ) );

		// SMS notification settings.
		foreach ( array( 'sms_enabled', 'sms_admin_new', 'sms_customer_request', 'sms_customer_confirmed', 'sms_customer_completed', 'otp_enabled' ) as $f ) {
			$s[ $f ] = empty( $_POST[ $f ] ) ? 0 : 1;
		}
		// Forced through the GSM-7 filter on the way in, so a pasted en-dash or
		// Bangla word cannot quietly double the cost of every code sent.
		$brand = function_exists( 'bhela_bm_otp_gsm_safe' )
			? bhela_bm_otp_gsm_safe( wp_unslash( $_POST['otp_brand'] ?? '' ) )
			: sanitize_text_field( wp_unslash( $_POST['otp_brand'] ?? '' ) );
		$s['otp_brand']       = mb_substr( $brand, 0, 20 ) ?: 'BHELA';
		$s['sms_low_balance'] = max( 0, (int) ( $_POST['sms_low_balance'] ?? 100 ) );

		// Cost heads live in their own option, not in the settings blob — the
		// cost sheet reads them on every render and they have their own reset.
		if ( function_exists( 'bhela_bm_inv_save_lists' ) ) {
			bhela_bm_inv_save_lists( wp_unslash( $_POST ) );
		}
		if ( isset( $_POST['cost_heads'] ) && function_exists( 'bhela_bm_save_cost_heads' ) ) {
			bhela_bm_save_cost_heads( wp_unslash( $_POST['cost_heads'] ) );
		}
		if ( isset( $_POST['staff'] ) && function_exists( 'bhela_bm_save_staff' ) ) {
			bhela_bm_save_staff( wp_unslash( $_POST['staff'] ) );
		}
		if ( function_exists( 'bhela_bm_save_expense_list' ) ) {
			foreach ( array(
				'expense_types'   => 'bhela_bm_expense_types',
				'expense_methods' => 'bhela_bm_expense_methods',
			) as $field => $option ) {
				if ( isset( $_POST[ $field ] ) ) {
					bhela_bm_save_expense_list( $option, wp_unslash( $_POST[ $field ] ) );
				}
			}
		}
		$s['sms_json']     = empty( $_POST['sms_json'] ) ? 0 : 1;
		$s['sms_provider'] = in_array( ( $_POST['sms_provider'] ?? '' ), array( 'bulksmsbd', 'custom' ), true ) ? $_POST['sms_provider'] : 'bulksmsbd';
		$s['sms_method']   = ( 'POST' === strtoupper( $_POST['sms_method'] ?? '' ) ) ? 'POST' : 'GET';
		$s['sms_api_url']  = esc_url_raw( wp_unslash( $_POST['sms_api_url'] ?? '' ) );
		foreach ( array( 'sms_sender_id', 'sms_param_number', 'sms_param_message', 'sms_param_key', 'sms_param_sender', 'sms_auth_header', 'sms_admin_number' ) as $f ) {
			$s[ $f ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ?? '' ) );
		}
		// API key: keep the stored value if the field still shows the mask.
		$posted_key = sanitize_text_field( wp_unslash( $_POST['sms_api_key'] ?? '' ) );
		if ( '' !== $posted_key && $posted_key !== bhela_bm_mask( $s['sms_api_key'] ) ) {
			$s['sms_api_key'] = $posted_key;
		}
		// Only overwrite a template that was actually posted: a save that does not
		// include these fields must not silently blank them.
		foreach ( array( 'sms_tpl_admin', 'sms_tpl_new', 'sms_tpl_confirmed', 'sms_tpl_completed', 'confirm_template' ) as $f ) {
			if ( isset( $_POST[ $f ] ) ) {
				$s[ $f ] = sanitize_textarea_field( wp_unslash( $_POST[ $f ] ) );
			}
		}
		// BulkSMSBD preset: lock the well-known endpoint + params.
		if ( 'bulksmsbd' === $s['sms_provider'] ) {
			$s['sms_api_url']       = 'https://bulksmsbd.net/api/smsapi';
			$s['sms_method']        = 'GET';
			$s['sms_param_number']  = 'number';
			$s['sms_param_message'] = 'message';
			$s['sms_param_key']     = 'api_key';
			$s['sms_param_sender']  = 'senderid';
		}

		update_option( 'bhela_bm_settings', $s );
		if ( function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'settings', 'Booking settings saved' );
		}

		$rates = bhela_bm_get_rates();
		foreach ( $rates as $key => $row ) {
			if ( isset( $_POST[ 'rate_label_' . $key ] ) ) {
				$rates[ $key ]['label']   = sanitize_text_field( $_POST[ 'rate_label_' . $key ] );
				$rates[ $key ]['sharing'] = max( 1, (int) $_POST[ 'rate_sharing_' . $key ] );
				$rates[ $key ]['regular'] = max( 0, (int) $_POST[ 'rate_regular_' . $key ] );
				$rates[ $key ]['weekday'] = max( 0, (int) $_POST[ 'rate_weekday_' . $key ] );
			}
		}
		update_option( 'bhela_bm_rates', $rates );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'bhela-booking' ) . '</p></div>';
	}

	$s     = bhela_bm_get_settings();
	$rates = bhela_bm_get_rates();
	$days  = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );

	$bhela_tabs = array(
		'business' => array( 'icon' => '🏠', 'label' => __( 'Business', 'bhela-booking' ) ),
		'payment'  => array( 'icon' => '💳', 'label' => __( 'Payment & Invoice', 'bhela-booking' ) ),
		'pricing'  => array( 'icon' => '📅', 'label' => __( 'Pricing Days', 'bhela-booking' ) ),
		'rates'    => array( 'icon' => '🛏️', 'label' => __( 'Cabin Rates', 'bhela-booking' ) ),
		'email'    => array( 'icon' => '📧', 'label' => __( 'Email', 'bhela-booking' ) ),
		'sms'      => array( 'icon' => '📱', 'label' => __( 'SMS', 'bhela-booking' ) ),
		'heads'    => array( 'icon' => '🧾', 'label' => __( 'Lists', 'bhela-booking' ) ),
		'store'    => array( 'icon' => '📦', 'label' => __( 'Store Lists', 'bhela-booking' ) ),
		'staff'    => array( 'icon' => '👷', 'label' => __( 'Staff', 'bhela-booking' ) ),
	);
	?>
	<div class="wrap bha-set">

		<?php
		bhela_bm_screen_header(
			'🛶',
			__( 'BHELA Booking Settings', 'bhela-booking' ),
			__( 'Business details, pricing and the notifications your guests receive.', 'bhela-booking' ),
			'',
			'bha-head--attached'
		);
		?>

		<div class="bha-set__tabs" role="tablist">
			<?php foreach ( $bhela_tabs as $key => $tab ) : ?>
				<button type="button" class="bha-set__tab" role="tab" data-tab="<?php echo esc_attr( $key ); ?>"
					id="bhela-tab-<?php echo esc_attr( $key ); ?>" aria-controls="bhela-panel-<?php echo esc_attr( $key ); ?>" aria-selected="false">
					<span aria-hidden="true"><?php echo esc_html( $tab['icon'] ); ?></span><?php echo esc_html( $tab['label'] ); ?>
				</button>
			<?php endforeach; ?>
		</div>

		<form method="post" class="bha-set__body">
			<?php wp_nonce_field( 'bhela_bm_settings', 'bhela_bm_settings_nonce' ); ?>

			<div class="bha-set__panel" id="bhela-panel-business" role="tabpanel" aria-labelledby="bhela-tab-business">
			<h2><?php esc_html_e( 'Business Information', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'These appear on the website, the booking form and every invoice.', 'bhela-booking' ); ?></p>
			<table class="form-table">
				<tr><th>Business Name</th><td><input type="text" class="regular-text" name="business_name" value="<?php echo esc_attr( $s['business_name'] ); ?>"></td></tr>
				<tr><th>Tagline</th><td><input type="text" class="regular-text" name="business_tagline" value="<?php echo esc_attr( $s['business_tagline'] ); ?>"></td></tr>
				<tr><th>Address</th><td><input type="text" class="regular-text" name="address" value="<?php echo esc_attr( $s['address'] ); ?>"></td></tr>
				<tr><th>Phone 1</th><td><input type="text" name="phone_1" value="<?php echo esc_attr( $s['phone_1'] ); ?>"></td></tr>
				<tr><th>Phone 2</th><td><input type="text" name="phone_2" value="<?php echo esc_attr( $s['phone_2'] ); ?>"></td></tr>
				<tr><th>WhatsApp</th><td><input type="text" name="whatsapp" value="<?php echo esc_attr( $s['whatsapp'] ); ?>"></td></tr>
				<tr><th>Business Email</th><td><input type="text" class="regular-text" name="email" value="<?php echo esc_attr( $s['email'] ); ?>"></td></tr>
				<tr><th>Operation Manager</th><td><input type="text" name="ops_manager" value="<?php echo esc_attr( $s['ops_manager'] ?? '' ); ?>" placeholder="Uttam">
					<p class="description"><?php esc_html_e( 'Named at the bottom of every invoice so guests know who to contact. Leave empty to hide that line.', 'bhela-booking' ); ?></p></td></tr>
				<tr><th>Support WhatsApp</th><td><input type="text" name="support_whatsapp" value="<?php echo esc_attr( $s['support_whatsapp'] ?? '' ); ?>" placeholder="+8801781720957">
					<p class="description"><?php esc_html_e( 'Booking-support number shown on the invoice. Falls back to the WhatsApp number above when empty.', 'bhela-booking' ); ?></p></td></tr>
			</table>

			<h2><?php esc_html_e( 'Trip Logistics', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'Printed on the invoice, the customer email and the booking confirmation message. These used to be fixed in the code.', 'bhela-booking' ); ?></p>
			<table class="form-table">
				<tr><th>Boarding Ghat</th><td><input type="text" class="regular-text" name="boarding_ghat" value="<?php echo esc_attr( $s['boarding_ghat'] ?? '' ); ?>" placeholder="Anwarpur Ghat">
					<p class="description"><?php esc_html_e( 'Where guests board. A booking can override this individually if a trip leaves from elsewhere.', 'bhela-booking' ); ?></p></td></tr>
				<tr><th>Check-in Time</th><td><input type="text" name="checkin_time" value="<?php echo esc_attr( $s['checkin_time'] ?? '' ); ?>" placeholder="8:00 AM – 10:00 AM"></td></tr>
				<tr><th>Check-out Time</th><td><input type="text" name="checkout_time" value="<?php echo esc_attr( $s['checkout_time'] ?? '' ); ?>" placeholder="5:00 PM – 7:00 PM">
					<p class="description"><?php esc_html_e( 'Check-in is the travel date and check-out the day after — the dates follow the booking, only these time windows are fixed.', 'bhela-booking' ); ?></p></td></tr>
				<tr><th>Package Label</th><td><input type="text" name="package_label" value="<?php echo esc_attr( $s['package_label'] ?? '' ); ?>" placeholder="২ দিন ১ রাত"></td></tr>
				<tr><th>Confirmation Notes</th><td>
					<textarea name="confirm_notes" rows="3" class="large-text" placeholder="AC Service: 16–18 Hours&#10;Electricity: 24 Hours"><?php echo esc_textarea( $s['confirm_notes'] ?? '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One note per line. Shown at the bottom of the confirmation message.', 'bhela-booking' ); ?></p></td></tr>
			</table>
			</div><!-- /business -->

			<div class="bha-set__panel" id="bhela-panel-payment" role="tabpanel" aria-labelledby="bhela-tab-payment">
			<h2><?php esc_html_e( 'Payment Details', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'Shown on the invoice so guests know how to pay, plus the figures the pricing engine uses.', 'bhela-booking' ); ?></p>
			<table class="form-table">
				<tr><th>bKash</th><td><input type="text" class="regular-text" name="bkash_number" value="<?php echo esc_attr( $s['bkash_number'] ); ?>"></td></tr>
				<tr><th>Nagad</th><td><input type="text" class="regular-text" name="nagad_number" value="<?php echo esc_attr( $s['nagad_number'] ); ?>"></td></tr>
				<tr><th>Bank Details</th><td><textarea name="bank_details" rows="3" class="large-text"><?php echo esc_textarea( $s['bank_details'] ); ?></textarea></td></tr>
				<tr><th>Nagad QR Image URL</th><td><input type="url" class="large-text" name="nagad_qr" value="<?php echo esc_attr( $s['nagad_qr'] ?? '' ); ?>" placeholder="Upload the Nagad QR photo in Media Library, paste its URL here">
					<p class="description"><?php esc_html_e( 'Shown on the invoice so guests can scan & pay. Media → Add New → copy File URL.', 'bhela-booking' ); ?></p></td></tr>
				<tr><th>Bangla QR Image URL</th><td><input type="url" class="large-text" name="bangla_qr" value="<?php echo esc_attr( $s['bangla_qr'] ?? '' ); ?>" placeholder="Upload the Bangla QR photo in Media Library, paste its URL here"></td></tr>
				<tr><th>Advance %</th><td><input type="number" name="advance_percent" min="1" max="100" value="<?php echo esc_attr( $s['advance_percent'] ); ?>"> %</td></tr>
				<tr><th>Date chips</th><td><input type="number" name="date_chips" min="0" max="20" value="<?php echo esc_attr( $s['date_chips'] ); ?>"><br><span class="description">How many upcoming Trip Calendar dates appear as quick-pick chips on the booking form. Set 0 to hide them.</span></td></tr>
				<tr><th>Child fee (age 4–8)</th><td><input type="number" name="child_fee" min="0" step="100" value="<?php echo esc_attr( $s['child_fee'] ); ?>"> ৳ per child<br><span class="description">A flat charge — it does not follow the cabin rate or the weekday discount. Ages 0–4 are always free.</span></td></tr>
				<tr><th>Invoice Prefix</th><td><input type="text" name="invoice_prefix" value="<?php echo esc_attr( $s['invoice_prefix'] ); ?>"></td></tr>
				<tr><th>Review photos per guest</th><td><input type="number" name="review_max_photos" min="0" max="10" value="<?php echo esc_attr( $s['review_max_photos'] ?? 5 ); ?>"> <?php esc_html_e( 'photos, max', 'bhela-booking' ); ?>
					<input type="number" name="review_max_mb" min="1" max="20" value="<?php echo esc_attr( $s['review_max_mb'] ?? 5 ); ?>"> MB <?php esc_html_e( 'each', 'bhela-booking' ); ?>
					<p class="description"><?php esc_html_e( 'Guests attach these to the review they submit after a completed trip. JPEG, PNG and WebP only. Set photos to 0 to turn uploads off.', 'bhela-booking' ); ?></p></td></tr>
				<tr><th>Invoice Note / Terms</th><td><textarea name="invoice_note" rows="4" class="large-text"><?php echo esc_textarea( $s['invoice_note'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Printed at the bottom of every invoice. These fill in per booking:', 'bhela-booking' ); ?>
						<code>{total}</code> <code>{advance}</code> <code>{advance_pct}</code> <code>{paid}</code> <code>{due}</code>
						<br><?php esc_html_e( 'Use them instead of typing a fixed percentage — you now set each booking\'s advance yourself, so a hardcoded figure here can contradict the invoice above it.', 'bhela-booking' ); ?>
					</p></td></tr>
			</table>
			</div><!-- /payment -->

			<div class="bha-set__panel" id="bhela-panel-pricing" role="tabpanel" aria-labelledby="bhela-tab-pricing">
			<h2><?php esc_html_e( 'Pricing Days', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'Which days charge the regular rate. Every other day gets the weekday rate, currently 20% less.', 'bhela-booking' ); ?></p>
			<?php if ( ! array_filter( (array) $s['weekend_days'], 'strlen' ) ) : ?>
				<div class="notice notice-warning inline" style="margin:12px 0"><p>
					<strong><?php esc_html_e( 'No weekend days are ticked.', 'bhela-booking' ); ?></strong>
					<?php esc_html_e( 'That means every date is charged the discounted weekday rate — including Fridays and Saturdays. Tick the days that should charge your regular rate.', 'bhela-booking' ); ?>
				</p></div>
			<?php endif; ?>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Weekend Days (regular rate)', 'bhela-booking' ); ?></th><td>
					<input type="hidden" name="bhela_pricing_days_present" value="1">
					<?php foreach ( $days as $num => $label ) : ?>
						<label style="margin-right:14px"><input type="checkbox" name="weekend_days[]" value="<?php echo esc_attr( $num ); ?>" <?php checked( in_array( $num, array_map( 'intval', (array) $s['weekend_days'] ), true ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</td></tr>
				<tr><th><?php esc_html_e( 'Holidays', 'bhela-booking' ); ?></th>
					<td><p class="description"><?php esc_html_e( 'Holidays are set on the Trip Calendar now. Any trip ticked as a holiday is charged the regular rate (no 20% weekday discount). Every other non-weekend day gets the weekday rate.', 'bhela-booking' ); ?></p>
					<p><a class="button" href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-trips' ) ); ?>">📅 <?php esc_html_e( 'Open Trip Calendar', 'bhela-booking' ); ?></a></p></td></tr>
			</table>
			</div><!-- /pricing -->

			<div class="bha-set__panel" id="bhela-panel-rates" role="tabpanel" aria-labelledby="bhela-tab-rates">
			<h2><?php esc_html_e( 'Cabin Rates', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'Per person, for the 2 days 1 night package. The engine prices by how many people share a cabin, so the sharing number matters.', 'bhela-booking' ); ?></p>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Cabin Label</th><th>Sharing</th><th>Regular/Holiday ৳</th><th>Weekday ৳</th></tr></thead>
				<tbody>
				<?php foreach ( $rates as $key => $row ) : ?>
					<tr>
						<td><input type="text" style="width:95%" name="rate_label_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $row['label'] ); ?>"></td>
						<td><input type="number" style="width:70px" name="rate_sharing_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $row['sharing'] ); ?>"></td>
						<td><input type="number" style="width:100px" name="rate_regular_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $row['regular'] ); ?>"></td>
						<td><input type="number" style="width:100px" name="rate_weekday_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $row['weekday'] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			</div><!-- /rates -->

			<div class="bha-set__panel" id="bhela-panel-email" role="tabpanel" aria-labelledby="bhela-tab-email">
			<h2 id="bhela-email"><?php esc_html_e( 'Email Notifications', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'Emails go out on new bookings and status changes. The customer email uses your Business Email (Business tab) as the From address.', 'bhela-booking' ); ?></p>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Enable emails', 'bhela-booking' ); ?></th><td><label><input type="checkbox" name="email_enabled" value="1" <?php checked( ! empty( $s['email_enabled'] ) ); ?>> <?php esc_html_e( 'Master switch — send booking emails', 'bhela-booking' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'Which emails', 'bhela-booking' ); ?></th><td>
					<label style="display:block;margin-bottom:6px"><input type="checkbox" name="email_admin_new" value="1" <?php checked( ! empty( $s['email_admin_new'] ) ); ?>> <?php esc_html_e( 'New booking → notify you (owner)', 'bhela-booking' ); ?></label>
					<label style="display:block;margin-bottom:6px"><input type="checkbox" name="email_customer_request" value="1" <?php checked( ! empty( $s['email_customer_request'] ) ); ?>> <?php esc_html_e( 'New booking → customer (request received)', 'bhela-booking' ); ?></label>
					<label style="display:block;margin-bottom:6px"><input type="checkbox" name="email_customer_confirmed" value="1" <?php checked( ! empty( $s['email_customer_confirmed'] ) ); ?>> <?php esc_html_e( 'Status = Confirmed → customer (confirmation)', 'bhela-booking' ); ?></label>
					<label style="display:block"><input type="checkbox" name="email_customer_completed" value="1" <?php checked( ! empty( $s['email_customer_completed'] ) ); ?>> <?php esc_html_e( 'Status = Completed → customer (thank-you + review invite)', 'bhela-booking' ); ?></label>
				</td></tr>
				<tr><th><?php esc_html_e( 'Owner notification email', 'bhela-booking' ); ?></th><td><input type="email" class="regular-text" name="notify_email" value="<?php echo esc_attr( $s['notify_email'] ); ?>" placeholder="<?php echo esc_attr( $s['email'] ); ?>">
					<p class="description"><?php esc_html_e( 'Where new-booking alerts go. Blank = Business Email.', 'bhela-booking' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'From name', 'bhela-booking' ); ?></th><td><input type="text" class="regular-text" name="email_from_name" value="<?php echo esc_attr( $s['email_from_name'] ); ?>" placeholder="<?php echo esc_attr( $s['business_name'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Reply-To', 'bhela-booking' ); ?></th><td><input type="email" class="regular-text" name="email_reply_to" value="<?php echo esc_attr( $s['email_reply_to'] ); ?>" placeholder="<?php echo esc_attr( $s['email'] ); ?>"></td></tr>
			</table>
			<?php
			$email_last = get_transient( 'bhela_bm_email_test_result' );
			if ( $email_last ) {
				delete_transient( 'bhela_bm_email_test_result' );
				printf(
					'<div class="notice notice-%s inline"><p><strong>Test email → %s:</strong> %s</p></div>',
					$email_last['ok'] ? 'success' : 'error',
					esc_html( $email_last['to'] ),
					$email_last['ok'] ? esc_html__( 'sent (check the inbox / Mailpit).', 'bhela-booking' ) : esc_html__( 'wp_mail() failed — check the site mail setup.', 'bhela-booking' )
				);
			}
			?>

			<p>
				<button type="submit" class="button" form="bhela-email-test">📧 <?php esc_html_e( 'Send Test Email', 'bhela-booking' ); ?></button>
				<span class="description" style="margin-left:8px"><?php esc_html_e( 'Save your settings first.', 'bhela-booking' ); ?></span>
			</p>
			</div><!-- /email -->

			<div class="bha-set__panel" id="bhela-panel-sms" role="tabpanel" aria-labelledby="bhela-tab-sms">
			<h2 id="bhela-sms"><?php esc_html_e( 'SMS Notifications', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'Send an SMS on every new booking (to you and the customer) and when you change a booking status. Works with any Bangladesh SMS gateway.', 'bhela-booking' ); ?></p>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Enable SMS', 'bhela-booking' ); ?></th><td><label><input type="checkbox" name="sms_enabled" value="1" <?php checked( ! empty( $s['sms_enabled'] ) ); ?>> <?php esc_html_e( 'Master switch — send SMS notifications', 'bhela-booking' ); ?></label></td></tr>
				<?php
				$bal = function_exists( 'bhela_bm_sms_balance' ) ? bhela_bm_sms_balance() : array( 'balance' => null, 'at' => '', 'error' => '' );
				if ( null !== $bal['balance'] || $bal['error'] ) :
					?>
					<tr><th><?php esc_html_e( 'SMS credit', 'bhela-booking' ); ?></th><td>
						<?php if ( null !== $bal['balance'] ) : ?>
							<strong style="font-size:16px" class="<?php echo bhela_bm_sms_balance_low( $bal['balance'] ) ? 'bha-owed' : ''; ?>"><?php echo esc_html( bhela_bm_money( $bal['balance'] ) ); ?></strong>
						<?php else : ?>
							<strong class="bha-owed"><?php echo esc_html( $bal['error'] ); ?></strong>
						<?php endif; ?>
						<a class="button button-small" style="margin-left:8px" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bhela_bm_sms_balance' ), 'bhela_bm_sms_balance' ) ); ?>"><?php esc_html_e( 'Refresh', 'bhela-booking' ); ?></a>
						<p class="description"><?php esc_html_e( 'Read live from the gateway and cached for 15 minutes.', 'bhela-booking' ); ?></p>
					</td></tr>
					<tr><th><?php esc_html_e( 'Warn below', 'bhela-booking' ); ?></th><td>
						<input type="number" name="sms_low_balance" min="0" step="1" value="<?php echo esc_attr( $s['sms_low_balance'] ?? 100 ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Show a low-credit warning on the dashboard at or below this amount.', 'bhela-booking' ); ?></p>
					</td></tr>
				<?php endif; ?>
				<tr><th><?php esc_html_e( 'Verify mobile numbers', 'bhela-booking' ); ?></th><td>
					<label><input type="checkbox" name="otp_enabled" value="1" <?php checked( ! empty( $s['otp_enabled'] ) ); ?>> <?php esc_html_e( 'Require a code before a booking can be submitted', 'bhela-booking' ); ?></label>
					<p class="description"><?php esc_html_e( 'Stops fake and mistyped numbers. Costs one SMS per booking attempt; if the gateway fails the code is emailed instead.', 'bhela-booking' ); ?></p>
				</td></tr>
				<tr><th><?php esc_html_e( 'Brand in the code SMS', 'bhela-booking' ); ?></th><td>
					<input type="text" name="otp_brand" value="<?php echo esc_attr( $s['otp_brand'] ?? 'BHELA' ); ?>" class="regular-text" maxlength="20">
					<p class="description">
						<?php
						printf(
							/* translators: %s: the message preview */
							esc_html__( 'Sent as: %s', 'bhela-booking' ),
							'<code>' . esc_html( function_exists( 'bhela_bm_otp_message' ) ? bhela_bm_otp_message( '1234' ) : 'Your BHELA OTP is 1234' ) . '</code>'
						);
						?>
						<br><?php esc_html_e( 'Keep it short and English. Any Bangla or fancy punctuation switches the SMS to Unicode, which halves the characters per message and doubles the cost of every code you send.', 'bhela-booking' ); ?>
					</p>
				</td></tr>
				<tr><th><?php esc_html_e( 'Which SMS', 'bhela-booking' ); ?></th><td>
					<label style="display:block;margin-bottom:6px"><input type="checkbox" name="sms_admin_new" value="1" <?php checked( ! empty( $s['sms_admin_new'] ) ); ?>> <?php esc_html_e( 'New booking → notify you (owner)', 'bhela-booking' ); ?></label>
					<label style="display:block;margin-bottom:6px"><input type="checkbox" name="sms_customer_request" value="1" <?php checked( ! empty( $s['sms_customer_request'] ) ); ?>> <?php esc_html_e( 'New booking → customer (request received)', 'bhela-booking' ); ?></label>
					<label style="display:block;margin-bottom:6px"><input type="checkbox" name="sms_customer_confirmed" value="1" <?php checked( ! empty( $s['sms_customer_confirmed'] ) ); ?>> <?php esc_html_e( 'Status change → customer', 'bhela-booking' ); ?></label>
					<label style="display:block"><input type="checkbox" name="sms_customer_completed" value="1" <?php checked( ! empty( $s['sms_customer_completed'] ) ); ?>> <?php esc_html_e( 'Trip completed → customer (thank-you + review invite)', 'bhela-booking' ); ?></label>
					<p class="description"><?php esc_html_e( 'Each message costs money, so untick any you do not want. The master switch above turns all of them off at once.', 'bhela-booking' ); ?></p>
				</td></tr>
				<tr><th><?php esc_html_e( 'Gateway', 'bhela-booking' ); ?></th><td>
					<select name="sms_provider">
						<option value="bulksmsbd" <?php selected( $s['sms_provider'], 'bulksmsbd' ); ?>>BulkSMSBD (bulksmsbd.net)</option>
						<option value="custom" <?php selected( $s['sms_provider'], 'custom' ); ?>><?php esc_html_e( 'Custom / other gateway', 'bhela-booking' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Pick BulkSMSBD for a ready preset, or Custom to map any gateway’s API below.', 'bhela-booking' ); ?></p>
				</td></tr>
				<tr><th>API Key</th><td><input type="text" class="regular-text" name="sms_api_key" value="<?php echo esc_attr( bhela_bm_mask( $s['sms_api_key'] ) ); ?>" autocomplete="off">
					<p class="description"><?php esc_html_e( 'Leave the masked value to keep the saved key; type a new key to change it.', 'bhela-booking' ); ?></p></td></tr>
				<tr><th>Sender ID</th><td><input type="text" name="sms_sender_id" value="<?php echo esc_attr( $s['sms_sender_id'] ); ?>" placeholder="8809XXXXXXXXX / brand"></td></tr>
				<tr><th><?php esc_html_e( 'Admin SMS number', 'bhela-booking' ); ?></th><td><input type="text" name="sms_admin_number" value="<?php echo esc_attr( $s['sms_admin_number'] ); ?>" placeholder="<?php echo esc_attr( $s['phone_1'] ); ?>">
					<p class="description"><?php esc_html_e( 'Where new-booking alerts go. Blank = Phone 1.', 'bhela-booking' ); ?></p></td></tr>
			</table>

			<div class="bha-set__sub" style="max-width:900px">
				<h3><?php esc_html_e( 'Custom gateway mapping', 'bhela-booking' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Only needed for "Custom" — BulkSMSBD is auto-configured.', 'bhela-booking' ); ?></p>
				<table class="form-table">
					<tr><th>API URL</th><td><input type="url" class="large-text" name="sms_api_url" value="<?php echo esc_attr( $s['sms_api_url'] ); ?>"></td></tr>
					<tr><th>Method</th><td>
						<label style="margin-right:12px"><input type="radio" name="sms_method" value="GET" <?php checked( $s['sms_method'], 'GET' ); ?>> GET</label>
						<label style="margin-right:12px"><input type="radio" name="sms_method" value="POST" <?php checked( $s['sms_method'], 'POST' ); ?>> POST</label>
						<label><input type="checkbox" name="sms_json" value="1" <?php checked( ! empty( $s['sms_json'] ) ); ?>> <?php esc_html_e( 'POST body as JSON', 'bhela-booking' ); ?></label>
					</td></tr>
					<tr><th><?php esc_html_e( 'Param names', 'bhela-booking' ); ?></th><td>
						number <input type="text" style="width:120px" name="sms_param_number" value="<?php echo esc_attr( $s['sms_param_number'] ); ?>">
						message <input type="text" style="width:120px" name="sms_param_message" value="<?php echo esc_attr( $s['sms_param_message'] ); ?>">
						api key <input type="text" style="width:120px" name="sms_param_key" value="<?php echo esc_attr( $s['sms_param_key'] ); ?>">
						sender <input type="text" style="width:120px" name="sms_param_sender" value="<?php echo esc_attr( $s['sms_param_sender'] ); ?>">
					</td></tr>
					<tr><th><?php esc_html_e( 'Auth header (optional)', 'bhela-booking' ); ?></th><td><input type="text" class="regular-text" name="sms_auth_header" value="<?php echo esc_attr( $s['sms_auth_header'] ); ?>" placeholder="Authorization: Bearer xxxxx"></td></tr>
				</table>
			</div>

			<table class="form-table">
				<tr><th colspan="2"><em><?php esc_html_e( 'Placeholders:', 'bhela-booking' ); ?></em> <code>{name} {phone} {invoice} {date} {cabin} {guests} {total} {advance} {paid} {due} {status} {review_link}</code><br>
					<em><?php esc_html_e( 'Also available:', 'bhela-booking' ); ?></em> <code>{address} {boarding} {checkin} {checkout} {checkin_time} {checkout_time} {package} {room} {room_type} {pay_method} {booked_by} {issued_by} {issued_on} {notes} {invoice_link} {ops_manager} {support_whatsapp}</code></th></tr>
				<tr><th><?php esc_html_e( 'New booking → you', 'bhela-booking' ); ?></th><td><textarea name="sms_tpl_admin" rows="2" class="large-text"><?php echo esc_textarea( $s['sms_tpl_admin'] ); ?></textarea></td></tr>
				<tr><th><?php esc_html_e( 'New booking → customer', 'bhela-booking' ); ?></th><td><textarea name="sms_tpl_new" rows="2" class="large-text"><?php echo esc_textarea( $s['sms_tpl_new'] ); ?></textarea></td></tr>
				<tr><th><?php esc_html_e( 'Status change → customer', 'bhela-booking' ); ?></th><td><textarea name="sms_tpl_confirmed" rows="2" class="large-text"><?php echo esc_textarea( $s['sms_tpl_confirmed'] ); ?></textarea></td></tr>
				<tr><th><?php esc_html_e( 'Trip completed → customer', 'bhela-booking' ); ?></th><td><textarea name="sms_tpl_completed" rows="2" class="large-text"><?php echo esc_textarea( $s['sms_tpl_completed'] ?? '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Sent instead of the line above when a booking is marked Completed. Use {review_link} for the guest\'s private review link. Leave a template empty to fall back to the wording shipped with the plugin — use the tick boxes above to stop a message being sent.', 'bhela-booking' ); ?></p></td></tr>
			</table>

			<h2><?php esc_html_e( 'Booking Confirmation Message', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'The message staff copy to WhatsApp from a booking. Not sent automatically — a person presses Copy confirmation on the booking and pastes it.', 'bhela-booking' ); ?></p>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Template', 'bhela-booking' ); ?></th><td>
					<textarea name="confirm_template" rows="18" class="large-text code" placeholder="<?php echo esc_attr( bhela_bm_confirm_default_template() ); ?>"><?php echo esc_textarea( $s['confirm_template'] ?? '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Leave empty to use the wording shipped with the plugin, shown greyed out above. Any placeholder from the list further up works here. A line whose value is empty — an address nobody gave — is dropped rather than printed blank.', 'bhela-booking' ); ?></p></td></tr>
			</table>
			<?php
			$sms_last = get_transient( 'bhela_bm_sms_test_result' );
			if ( $sms_last ) {
				delete_transient( 'bhela_bm_sms_test_result' );
				printf(
					'<div class="notice notice-%s inline"><p><strong>Test SMS → %s:</strong> HTTP %d — %s</p></div>',
					$sms_last['ok'] ? 'success' : 'error',
					esc_html( $sms_last['to'] ),
					(int) $sms_last['code'],
					esc_html( $sms_last['body'] ? $sms_last['body'] : ( $sms_last['ok'] ? 'sent' : 'failed' ) )
				);
			}
			?>

			<p>
				<button type="submit" class="button" form="bhela-sms-test">📲 <?php esc_html_e( 'Send Test SMS', 'bhela-booking' ); ?></button>
				<span class="description" style="margin-left:8px"><?php esc_html_e( 'Save your settings first.', 'bhela-booking' ); ?></span>
			</p>
			</div><!-- /sms -->

			<div class="bha-set__panel" id="bhela-panel-heads" role="tabpanel" aria-labelledby="bhela-tab-heads">
			<h2><?php esc_html_e( 'Trip Cost Heads', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'The standing expense lines on every trip cost sheet. Rename them, reorder them, add your own. One-off items belong on the sheet itself, not here.', 'bhela-booking' ); ?></p>
			<?php
			$all_heads = bhela_bm_cost_heads( true );
			$raw_heads = get_option( 'bhela_bm_cost_heads', array() );
			$in_use    = bhela_bm_cost_heads_in_use();
			?>
			<table class="widefat striped" id="bhela-heads-table">
				<thead>
					<tr>
						<th style="width:44px">#</th>
						<th><?php esc_html_e( 'Head', 'bhela-booking' ); ?></th>
						<th style="width:110px"><?php esc_html_e( 'In use', 'bhela-booking' ); ?></th>
						<th style="width:130px"><?php esc_html_e( 'Retired', 'bhela-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php $n = 0; foreach ( $all_heads as $slug => $label ) : $n++; $used = in_array( $slug, $in_use, true ); ?>
					<tr>
						<td><?php echo esc_html( $n ); ?></td>
						<td>
							<input type="hidden" name="cost_heads[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>">
							<input type="text" class="large-text" name="cost_heads[<?php echo esc_attr( $slug ); ?>][label]" value="<?php echo esc_attr( $label ); ?>">
						</td>
						<td><?php echo $used ? '<span title="' . esc_attr__( 'Used on a saved cost sheet', 'bhela-booking' ) . '">✔</span>' : '—'; ?></td>
						<td>
							<label>
								<input type="checkbox" name="cost_heads[<?php echo esc_attr( $slug ); ?>][retired]" value="1"
									<?php checked( ! empty( $raw_heads[ $slug ]['retired'] ) ); ?>>
								<?php esc_html_e( 'Hide', 'bhela-booking' ); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="bhela-heads-add">+ <?php esc_html_e( 'Add head', 'bhela-booking' ); ?></button>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bhela_bm_reset_cost_heads' ), 'bhela_bm_reset_cost_heads' ) ); ?>"
					style="margin-left:10px"
					onclick="return confirm('<?php echo esc_js( __( 'Reset the head list to the one shipped with the plugin? Saved cost sheets keep their figures.', 'bhela-booking' ) ); ?>')"><?php esc_html_e( 'reset to defaults', 'bhela-booking' ); ?></a>
			</p>
			<p class="description">
				<?php esc_html_e( 'Retiring a head hides it from new sheets but leaves it on the ones that already use it — a closed month must not change. Renaming only changes the label; the figures stay attached to the same head.', 'bhela-booking' ); ?>
			</p>
			<script>
			(function () {
				var btn = document.getElementById('bhela-heads-add');
				if (!btn) return;
				btn.addEventListener('click', function () {
					var body = document.querySelector('#bhela-heads-table tbody');
					// A new head gets a placeholder key; the server turns the
					// typed label into a real slug on save.
					var key = 'new_' + Date.now().toString(36);
					var tr = document.createElement('tr');
					tr.innerHTML =
						'<td>' + (body.rows.length + 1) + '</td>' +
						'<td><input type="hidden" name="cost_heads[' + key + '][slug]" value="">' +
						'<input type="text" class="large-text" name="cost_heads[' + key + '][label]" placeholder="<?php echo esc_js( __( 'New head…', 'bhela-booking' ) ); ?>"></td>' +
						'<td>—</td><td></td>';
					body.appendChild(tr);
					tr.querySelector('input[type=text]').focus();
				});
			})();
			</script>

			<?php
			// Expense types and payment methods, same shape as the heads above.
			$bhela_lists = array(
				'expense_types'   => array(
					'title' => __( 'Expense Types', 'bhela-booking' ),
					'lead'  => __( 'How spending outside a trip is classified. Each type becomes its own deduction line on the Monthly Statement.', 'bhela-booking' ),
					'items' => function_exists( 'bhela_bm_expense_types' ) ? bhela_bm_expense_types( true ) : array(),
					'raw'   => get_option( 'bhela_bm_expense_types', array() ),
				),
				'expense_methods' => array(
					'title' => __( 'Payment Methods', 'bhela-booking' ),
					'lead'  => __( 'Offered when recording an expense.', 'bhela-booking' ),
					'items' => function_exists( 'bhela_bm_expense_methods' ) ? bhela_bm_expense_methods( true ) : array(),
					'raw'   => get_option( 'bhela_bm_expense_methods', array() ),
				),
			);
			foreach ( $bhela_lists as $list_key => $list ) :
				if ( ! $list['items'] ) { continue; }
				?>
				<h2 style="margin-top:32px"><?php echo esc_html( $list['title'] ); ?></h2>
				<p class="bha-set__lead"><?php echo esc_html( $list['lead'] ); ?></p>
				<table class="widefat striped" data-list="<?php echo esc_attr( $list_key ); ?>">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'bhela-booking' ); ?></th>
							<th style="width:130px"><?php esc_html_e( 'Retired', 'bhela-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $list['items'] as $slug => $label ) : ?>
						<tr>
							<td>
								<input type="hidden" name="<?php echo esc_attr( $list_key ); ?>[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>">
								<input type="text" class="large-text" name="<?php echo esc_attr( $list_key ); ?>[<?php echo esc_attr( $slug ); ?>][label]" value="<?php echo esc_attr( $label ); ?>">
							</td>
							<td><label><input type="checkbox" name="<?php echo esc_attr( $list_key ); ?>[<?php echo esc_attr( $slug ); ?>][retired]" value="1"
								<?php checked( ! empty( $list['raw'][ $slug ]['retired'] ) ); ?>> <?php esc_html_e( 'Hide', 'bhela-booking' ); ?></label></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button bhela-list-add" data-list="<?php echo esc_attr( $list_key ); ?>">+ <?php esc_html_e( 'Add', 'bhela-booking' ); ?></button></p>
			<?php endforeach; ?>

			<script>
			(function () {
				document.querySelectorAll('.bhela-list-add').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var name = btn.dataset.list;
						var body = document.querySelector('table[data-list="' + name + '"] tbody');
						var key = 'new_' + Date.now().toString(36);
						var tr = document.createElement('tr');
						tr.innerHTML =
							'<td><input type="hidden" name="' + name + '[' + key + '][slug]" value="">' +
							'<input type="text" class="large-text" name="' + name + '[' + key + '][label]"></td><td></td>';
						body.appendChild(tr);
						tr.querySelector('input[type=text]').focus();
					});
				});
			})();
			</script>
			</div><!-- /heads -->

			<div class="bha-set__panel" id="bhela-panel-store" role="tabpanel" aria-labelledby="bhela-tab-store">
			<h2><?php esc_html_e( 'Categories, Sub-categories & Locations', 'bhela-booking' ); ?></h2>
			<?php
			// Lives here beside the cost heads and expense types, because "the lists"
			// is one place an owner looks rather than three.
			if ( function_exists( 'bhela_bm_inv_lists_panel' ) ) {
				bhela_bm_inv_lists_panel();
			}
			?>
			</div><!-- /store -->

			<div class="bha-set__panel" id="bhela-panel-staff" role="tabpanel" aria-labelledby="bhela-tab-staff">
			<h2><?php esc_html_e( 'Staff Roster', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'Who gets paid, and how. Trip-based crew are multiplied by the number of approved trips in the month; monthly staff get a flat amount. The salary sheet fills the rest in.', 'bhela-booking' ); ?></p>
			<?php
			$staff_rows = function_exists( 'bhela_bm_staff' ) ? bhela_bm_staff( true ) : array();
			$emp_types  = function_exists( 'bhela_bm_employment_types' ) ? bhela_bm_employment_types() : array();
			?>
			<table class="widefat striped" id="bhela-staff-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Designation', 'bhela-booking' ); ?></th>
						<th style="width:130px"><?php esc_html_e( 'Type', 'bhela-booking' ); ?></th>
						<th style="width:110px"><?php esc_html_e( 'Per trip (৳)', 'bhela-booking' ); ?></th>
						<th style="width:110px"><?php esc_html_e( 'Monthly (৳)', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Account', 'bhela-booking' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Left', 'bhela-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $staff_rows as $id => $st ) : ?>
					<tr>
						<td>
							<input type="hidden" name="staff[<?php echo esc_attr( $id ); ?>][id]" value="<?php echo esc_attr( $id ); ?>">
							<input type="text" name="staff[<?php echo esc_attr( $id ); ?>][name]" value="<?php echo esc_attr( $st['name'] ); ?>" style="width:100%">
						</td>
						<td><input type="text" name="staff[<?php echo esc_attr( $id ); ?>][designation]" value="<?php echo esc_attr( $st['designation'] ); ?>" style="width:100%"></td>
						<td>
							<select name="staff[<?php echo esc_attr( $id ); ?>][type]" style="width:100%">
								<?php foreach ( $emp_types as $k => $lbl ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $st['type'], $k ); ?>><?php echo esc_html( $lbl ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="number" min="0" name="staff[<?php echo esc_attr( $id ); ?>][rate]" value="<?php echo esc_attr( $st['rate'] ?: '' ); ?>" style="width:100%"></td>
						<td><input type="number" min="0" name="staff[<?php echo esc_attr( $id ); ?>][monthly]" value="<?php echo esc_attr( $st['monthly'] ?: '' ); ?>" style="width:100%"></td>
						<td><input type="text" name="staff[<?php echo esc_attr( $id ); ?>][account]" value="<?php echo esc_attr( $st['account'] ); ?>" style="width:100%"></td>
						<td><label><input type="checkbox" name="staff[<?php echo esc_attr( $id ); ?>][retired]" value="1" <?php checked( $st['retired'] ); ?>> <?php esc_html_e( 'Hide', 'bhela-booking' ); ?></label></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="bhela-staff-add">+ <?php esc_html_e( 'Add staff', 'bhela-booking' ); ?></button></p>
			<p class="description"><?php esc_html_e( 'Marking someone as left keeps them on the salary sheets they were already on — a paid month must not change — but drops them from new ones. Clearing a name deletes the row.', 'bhela-booking' ); ?></p>
			<script>
			(function () {
				var btn = document.getElementById('bhela-staff-add');
				if (!btn) return;
				var types = <?php echo wp_json_encode( $emp_types ); ?>;
				btn.addEventListener('click', function () {
					var body = document.querySelector('#bhela-staff-table tbody');
					var key = 'new_' + Date.now().toString(36);
					var opts = Object.keys(types).map(function (k) {
						return '<option value="' + k + '">' + types[k] + '</option>';
					}).join('');
					var tr = document.createElement('tr');
					tr.innerHTML =
						'<td><input type="hidden" name="staff[' + key + '][id]" value="">' +
						'<input type="text" name="staff[' + key + '][name]" style="width:100%"></td>' +
						'<td><input type="text" name="staff[' + key + '][designation]" style="width:100%"></td>' +
						'<td><select name="staff[' + key + '][type]" style="width:100%">' + opts + '</select></td>' +
						'<td><input type="number" min="0" name="staff[' + key + '][rate]" style="width:100%"></td>' +
						'<td><input type="number" min="0" name="staff[' + key + '][monthly]" style="width:100%"></td>' +
						'<td><input type="text" name="staff[' + key + '][account]" style="width:100%"></td>' +
						'<td></td>';
					body.appendChild(tr);
					tr.querySelector('input[type=text]').focus();
				});
			})();
			</script>
			</div><!-- /staff -->

			<div class="bha-set__save">
				<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Settings', 'bhela-booking' ); ?></button>
				<span class="spacer"></span>
				<span class="description"><?php esc_html_e( 'Saving applies every tab at once.', 'bhela-booking' ); ?></span>
			</div>
		</form>

		<?php
		// The two test actions post to admin-post.php, so they cannot be nested
		// inside the settings form. They live here and are fired from inside the
		// panels via the buttons' form="" attribute.
		?>
		<form id="bhela-email-test" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hidden">
			<?php wp_nonce_field( 'bhela_bm_email_test' ); ?>
			<input type="hidden" name="action" value="bhela_bm_email_test">
		</form>
		<form id="bhela-sms-test" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hidden">
			<?php wp_nonce_field( 'bhela_bm_sms_test' ); ?>
			<input type="hidden" name="action" value="bhela_bm_sms_test">
		</form>

		<?php // The credit now sits in the admin footer on every BHELA screen, so it is not repeated here. ?>

		<script>
		(function () {
			var wrap = document.querySelector('.bha-set');
			if (!wrap) { return; }
			var tabs = wrap.querySelectorAll('.bha-set__tab');
			var KEY = 'bhelaSettingsTab';

			function show(name) {
				var found = false;
				tabs.forEach(function (t) {
					var on = t.dataset.tab === name;
					if (on) { found = true; }
					t.classList.toggle('is-active', on);
					t.setAttribute('aria-selected', on ? 'true' : 'false');
					var panel = document.getElementById('bhela-panel-' + t.dataset.tab);
					if (panel) { panel.classList.toggle('is-active', on); }
				});
				if (!found) { return false; }
				try { localStorage.setItem(KEY, name); } catch (e) {}
				return true;
			}

			tabs.forEach(function (t) {
				t.addEventListener('click', function () { show(t.dataset.tab); });
			});

			// Restore the tab the owner was on: the page reloads on save, and
			// landing back on Business every time loses their place.
			var start = (location.hash || '').replace('#tab-', '');
			if (!start) { try { start = localStorage.getItem(KEY) || ''; } catch (e) {} }
			if (!start || !show(start)) { show(tabs[0].dataset.tab); }

			// A test result renders inside its own panel — make sure it is visible.
			var notice = wrap.querySelector('.bha-set__panel .notice');
			if (notice) {
				var owner = notice.closest('.bha-set__panel');
				if (owner && !owner.classList.contains('is-active')) {
					show(owner.id.replace('bhela-panel-', ''));
				}
			}
		})();
		</script>
	</div>
	<?php
}

/** Mask a secret for display (keep last 4). */
/* ---------- Admin footer credit ---------- */

if ( ! function_exists( 'bhela_bm_is_plugin_screen' ) ) {
	/** True on any screen belonging to this plugin. */
	function bhela_bm_is_plugin_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		if ( isset( $screen->post_type ) && 0 === strpos( (string) $screen->post_type, 'bhela_' ) ) {
			return true;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		return 0 === strpos( $page, 'bhela-bm-' );
	}
}

/**
 * Replace the admin footer text on this plugin's screens only.
 *
 * Scoped deliberately: taking over the footer across all of wp-admin would put
 * our credit on other people's plugin pages, which is not ours to do.
 */
function bhela_bm_admin_footer_text( $text ) {
	if ( ! bhela_bm_is_plugin_screen() ) {
		return $text;
	}
	return sprintf(
		'🛶 <strong>%1$s</strong> v%2$s &nbsp;·&nbsp; %3$s',
		esc_html__( 'BHELA Booking Engine', 'bhela-booking' ),
		esc_html( BHELA_BM_VERSION ),
		sprintf(
			/* translators: %s: linked developer name */
			esc_html__( 'Designed &amp; developed by %s', 'bhela-booking' ),
			'<a href="https://3s-soft.com" target="_blank" rel="noopener">3s-Soft</a>'
		)
	);
}
add_filter( 'admin_footer_text', 'bhela_bm_admin_footer_text' );

function bhela_bm_mask( $value ) {
	$value = (string) $value;
	if ( strlen( $value ) <= 4 ) {
		return $value ? str_repeat( '•', strlen( $value ) ) : '';
	}
	return str_repeat( '•', max( 4, strlen( $value ) - 4 ) ) . substr( $value, -4 );
}

/* ---------- Dashboard widget ---------- */

function bhela_bm_dashboard_widget() {
	wp_add_dashboard_widget( 'bhela_bm_glance', '🛶 BHELA Bookings', function () {
		echo '<ul>';
		foreach ( bhela_bm_statuses() as $key => $label ) {
			$q = new WP_Query( array(
				'post_type'      => 'bhela_booking',
				'meta_key'       => '_bhela_status',
				'meta_value'     => $key,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );
			printf(
				'<li><a href="%s"><strong style="color:%s">%d</strong> — %s</a></li>',
				esc_url( admin_url( 'edit.php?post_type=bhela_booking&bhela_status=' . $key ) ),
				esc_attr( bhela_bm_status_color( $key ) ),
				(int) $q->found_posts,
				esc_html( $label )
			);
		}
		echo '</ul>';
	} );
}
add_action( 'wp_dashboard_setup', 'bhela_bm_dashboard_widget' );
