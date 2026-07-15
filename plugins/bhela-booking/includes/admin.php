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
			echo $total ? esc_html( bhela_bm_money( $total ) ) . ' / <span style="color:#1a7f37">' . esc_html( bhela_bm_money( $paid ) ) . '</span>' : '—';
			break;
		case 'bstatus':
			$status   = get_post_meta( $post_id, '_bhela_status', true ) ?: 'pending';
			$statuses = bhela_bm_statuses();
			printf(
				'<span style="display:inline-block;padding:2px 10px;border-radius:12px;font-weight:600;color:#fff;background:%s;font-size:11px;">%s</span>',
				esc_attr( bhela_bm_status_color( $status ) ),
				esc_html( isset( $statuses[ $status ] ) ? strtok( $statuses[ $status ], ' ' ) : $status )
			);
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
	?>
	<style>.bhela-disc label{display:block;font-weight:600;margin:8px 0 2px}.bhela-disc input{width:100%}.bhela-disc .req{background:#FFF7E6;border:1px solid #F5C97B;border-radius:8px;padding:8px 10px;margin:0 0 10px;font-size:12.5px;line-height:1.6}</style>
	<div class="bhela-disc">
		<?php if ( $full ) : ?>
			<p class="req">🚢 <strong><?php esc_html_e( 'Full Boat — custom quote requested.', 'bhela-booking' ); ?></strong> <?php esc_html_e( 'Set the price with Custom Total below.', 'bhela-booking' ); ?></p>
		<?php endif; ?>
		<?php if ( $requested || $disc_msg ) : ?>
			<div class="req">💬 <strong><?php esc_html_e( 'Guest request', 'bhela-booking' ); ?></strong><br>
				<?php if ( $requested ) : ?><?php esc_html_e( 'Budget:', 'bhela-booking' ); ?> <strong><?php echo esc_html( bhela_bm_money( $requested ) ); ?></strong><br><?php endif; ?>
				<?php if ( $disc_msg ) : ?><em><?php echo esc_html( $disc_msg ); ?></em><?php endif; ?>
			</div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Base Price:', 'bhela-booking' ); ?> <strong><?php echo esc_html( bhela_bm_money( $base ) ); ?></strong></p>
		<label><?php esc_html_e( 'Discount %', 'bhela-booking' ); ?></label>
		<input type="number" name="bhela_discount_percent" min="0" max="100" step="0.5" value="<?php echo esc_attr( $pct ); ?>">
		<label><?php esc_html_e( 'Flat Discount (৳)', 'bhela-booking' ); ?></label>
		<input type="number" name="bhela_discount_flat" min="0" value="<?php echo esc_attr( $flat ); ?>">
		<label><?php esc_html_e( 'Custom Total (৳ — overrides both)', 'bhela-booking' ); ?></label>
		<input type="number" name="bhela_custom_total" min="0" value="<?php echo esc_attr( $custom ); ?>">
		<?php if ( $offer ) : ?>
			<p style="margin-top:10px"><?php esc_html_e( 'Computed Offer:', 'bhela-booking' ); ?> <strong style="color:#137A74;font-size:15px"><?php echo esc_html( bhela_bm_money( $offer ) ); ?></strong></p>
		<?php endif; ?>
		<p style="margin-top:8px"><label style="font-weight:400"><input type="checkbox" name="bhela_apply_offer" value="1"> <?php esc_html_e( 'Apply offer as the booking Total on save', 'bhela-booking' ); ?></label></p>
		<p class="description"><?php esc_html_e( 'Offer = Custom Total, or Base − %% − Flat. Applying it updates Total & Advance.', 'bhela-booking' ); ?></p>
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
	<style>.bhela-meta th{width:180px;text-align:left}.bhela-meta input[type=text],.bhela-meta input[type=email],.bhela-meta input[type=date],.bhela-meta input[type=number],.bhela-meta select,.bhela-meta textarea{width:100%;max-width:420px}</style>
	<table class="form-table bhela-meta">
		<tr><th><?php esc_html_e( 'Phone', 'bhela-booking' ); ?> *</th>
			<td><input type="text" name="bhela_phone" value="<?php echo esc_attr( $m( '_bhela_phone' ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Email', 'bhela-booking' ); ?></th>
			<td><input type="email" name="bhela_email" value="<?php echo esc_attr( $m( '_bhela_email' ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Travel Date', 'bhela-booking' ); ?> *</th>
			<td><input type="date" name="bhela_travel_date" value="<?php echo esc_attr( $m( '_bhela_travel_date' ) ); ?>"></td></tr>
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
		<tr><th><?php esc_html_e( 'Advance Due (৳)', 'bhela-booking' ); ?></th>
			<td><input type="number" name="bhela_advance" value="<?php echo esc_attr( $m( '_bhela_advance' ) ); ?>"></td></tr>
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
	<?php if ( $invoice_no ) : ?>
		<p><a class="button button-secondary" href="<?php echo esc_url( bhela_bm_invoice_url( $post->ID ) ); ?>" target="_blank">🧾 <?php esc_html_e( 'View / Print Invoice', 'bhela-booking' ); ?></a></p>
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

	$fields = array(
		'_bhela_phone'        => sanitize_text_field( $_POST['bhela_phone'] ?? '' ),
		'_bhela_email'        => sanitize_email( $_POST['bhela_email'] ?? '' ),
		'_bhela_travel_date'  => sanitize_text_field( $_POST['bhela_travel_date'] ?? '' ),
		'_bhela_cabin_key'    => sanitize_key( $_POST['bhela_cabin_key'] ?? '' ),
		'_bhela_guests'       => max( 1, (int) ( $_POST['bhela_guests'] ?? 1 ) ),
		'_bhela_pay_method'   => sanitize_key( $_POST['bhela_pay_method'] ?? '' ),
		'_bhela_txn_id'       => sanitize_text_field( $_POST['bhela_txn_id'] ?? '' ),
		'_bhela_message'      => sanitize_textarea_field( $_POST['bhela_message'] ?? '' ),
		'_bhela_paid_amount'  => max( 0, (int) ( $_POST['bhela_paid_amount'] ?? 0 ) ),
		'_bhela_manual_price' => isset( $_POST['bhela_manual_price'] ) ? '1' : '',
	);
	foreach ( $fields as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}

	if ( ! get_post_meta( $post_id, '_bhela_invoice_no', true ) ) {
		update_post_meta( $post_id, '_bhela_invoice_no', bhela_bm_next_invoice_number() );
	}

	$cabin_key = $fields['_bhela_cabin_key'];
	if ( '1' === $fields['_bhela_manual_price'] || ! $cabin_key ) {
		update_post_meta( $post_id, '_bhela_per_person', (int) ( $_POST['bhela_per_person'] ?? 0 ) );
		update_post_meta( $post_id, '_bhela_total', (int) ( $_POST['bhela_total'] ?? 0 ) );
		update_post_meta( $post_id, '_bhela_advance', (int) ( $_POST['bhela_advance'] ?? 0 ) );
	} else {
		$price = bhela_bm_calc_price( $cabin_key, $fields['_bhela_guests'], $fields['_bhela_travel_date'] );
		if ( ! is_wp_error( $price ) ) {
			update_post_meta( $post_id, '_bhela_cabin_type', $price['cabin_label'] );
			update_post_meta( $post_id, '_bhela_day_type', $price['day_type'] );
			update_post_meta( $post_id, '_bhela_per_person', $price['per_person'] );
			update_post_meta( $post_id, '_bhela_total', $price['total'] );
			update_post_meta( $post_id, '_bhela_advance', $price['advance'] );
		}
	}

	// Cabin combination editor → reprice via the occupancy engine.
	if ( ! empty( $_POST['bhela_combo_recalc'] ) ) {
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
			update_post_meta( $post_id, '_bhela_advance', $cprice['advance'] );
			update_post_meta( $post_id, '_bhela_manual_price', '1' );
		}
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
		$settings = bhela_bm_get_settings();
		update_post_meta( $post_id, '_bhela_total', $offer );
		update_post_meta( $post_id, '_bhela_advance', (int) ceil( $offer * ( (float) $settings['advance_percent'] / 100 ) ) );
		update_post_meta( $post_id, '_bhela_manual_price', '1' );
	}

	$old_status = get_post_meta( $post_id, '_bhela_status', true ) ?: 'pending';
	$new_status = sanitize_key( $_POST['bhela_status'] ?? $old_status );
	if ( array_key_exists( $new_status, bhela_bm_statuses() ) ) {
		update_post_meta( $post_id, '_bhela_status', $new_status );
		if ( 'confirmed' === $new_status && 'confirmed' !== $old_status ) {
			bhela_bm_email_customer( $post_id, 'confirmed' );
		}
		if ( function_exists( 'bhela_bm_sms_on_status_change' ) ) {
			bhela_bm_sms_on_status_change( $post_id, $new_status, $old_status );
		}
	}

	if ( ! empty( $_POST['bhela_send_email'] ) ) {
		bhela_bm_email_customer( $post_id, 'confirmed' === $new_status ? 'confirmed' : 'request' );
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
}
add_action( 'admin_notices', 'bhela_bm_combo_error_notice' );

/* ---------- Settings page ---------- */

function bhela_bm_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=bhela_booking',
		__( 'Booking Settings', 'bhela-booking' ),
		__( 'Settings', 'bhela-booking' ),
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
		foreach ( array( 'business_name', 'business_tagline', 'address', 'phone_1', 'phone_2', 'whatsapp', 'bkash_number', 'nagad_number', 'invoice_prefix' ) as $f ) {
			$s[ $f ] = isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : $s[ $f ];
		}
		$s['email'] = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : $s['email'];
		$s['bank_details']    = sanitize_textarea_field( $_POST['bank_details'] ?? '' );
		$s['nagad_qr']        = esc_url_raw( $_POST['nagad_qr'] ?? '' );
		$s['bangla_qr']       = esc_url_raw( $_POST['bangla_qr'] ?? '' );
		$s['holidays']        = sanitize_textarea_field( $_POST['holidays'] ?? '' );
		$s['invoice_note']    = sanitize_textarea_field( $_POST['invoice_note'] ?? '' );

		// Email notification settings.
		foreach ( array( 'email_enabled', 'email_admin_new', 'email_customer_request', 'email_customer_confirmed' ) as $f ) {
			$s[ $f ] = empty( $_POST[ $f ] ) ? 0 : 1;
		}
		$s['notify_email']    = sanitize_email( wp_unslash( $_POST['notify_email'] ?? '' ) );
		$s['email_reply_to']  = sanitize_email( wp_unslash( $_POST['email_reply_to'] ?? '' ) );
		$s['email_from_name'] = sanitize_text_field( wp_unslash( $_POST['email_from_name'] ?? '' ) );
		$s['advance_percent'] = min( 100, max( 1, (int) ( $_POST['advance_percent'] ?? 50 ) ) );
		$s['weekend_days']    = array_map( 'intval', (array) ( $_POST['weekend_days'] ?? array() ) );

		// SMS notification settings.
		$s['sms_enabled']  = empty( $_POST['sms_enabled'] ) ? 0 : 1;
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
		foreach ( array( 'sms_tpl_admin', 'sms_tpl_new', 'sms_tpl_confirmed' ) as $f ) {
			$s[ $f ] = sanitize_textarea_field( wp_unslash( $_POST[ $f ] ?? '' ) );
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
	?>
	<div class="wrap">
		<h1>🛶 <?php esc_html_e( 'BHELA Booking Settings', 'bhela-booking' ); ?></h1>
		<form method="post">
			<?php wp_nonce_field( 'bhela_bm_settings', 'bhela_bm_settings_nonce' ); ?>

			<h2><?php esc_html_e( 'Business Information', 'bhela-booking' ); ?></h2>
			<table class="form-table">
				<tr><th>Business Name</th><td><input type="text" class="regular-text" name="business_name" value="<?php echo esc_attr( $s['business_name'] ); ?>"></td></tr>
				<tr><th>Tagline</th><td><input type="text" class="regular-text" name="business_tagline" value="<?php echo esc_attr( $s['business_tagline'] ); ?>"></td></tr>
				<tr><th>Address</th><td><input type="text" class="regular-text" name="address" value="<?php echo esc_attr( $s['address'] ); ?>"></td></tr>
				<tr><th>Phone 1</th><td><input type="text" name="phone_1" value="<?php echo esc_attr( $s['phone_1'] ); ?>"></td></tr>
				<tr><th>Phone 2</th><td><input type="text" name="phone_2" value="<?php echo esc_attr( $s['phone_2'] ); ?>"></td></tr>
				<tr><th>WhatsApp</th><td><input type="text" name="whatsapp" value="<?php echo esc_attr( $s['whatsapp'] ); ?>"></td></tr>
				<tr><th>Business Email</th><td><input type="text" class="regular-text" name="email" value="<?php echo esc_attr( $s['email'] ); ?>"></td></tr>
			</table>

			<h2><?php esc_html_e( 'Payment Details (shown on invoice)', 'bhela-booking' ); ?></h2>
			<table class="form-table">
				<tr><th>bKash</th><td><input type="text" class="regular-text" name="bkash_number" value="<?php echo esc_attr( $s['bkash_number'] ); ?>"></td></tr>
				<tr><th>Nagad</th><td><input type="text" class="regular-text" name="nagad_number" value="<?php echo esc_attr( $s['nagad_number'] ); ?>"></td></tr>
				<tr><th>Bank Details</th><td><textarea name="bank_details" rows="3" class="large-text"><?php echo esc_textarea( $s['bank_details'] ); ?></textarea></td></tr>
				<tr><th>Nagad QR Image URL</th><td><input type="url" class="large-text" name="nagad_qr" value="<?php echo esc_attr( $s['nagad_qr'] ?? '' ); ?>" placeholder="Upload the Nagad QR photo in Media Library, paste its URL here">
					<p class="description"><?php esc_html_e( 'Shown on the invoice so guests can scan & pay. Media → Add New → copy File URL.', 'bhela-booking' ); ?></p></td></tr>
				<tr><th>Bangla QR Image URL</th><td><input type="url" class="large-text" name="bangla_qr" value="<?php echo esc_attr( $s['bangla_qr'] ?? '' ); ?>" placeholder="Upload the Bangla QR photo in Media Library, paste its URL here"></td></tr>
				<tr><th>Advance %</th><td><input type="number" name="advance_percent" min="1" max="100" value="<?php echo esc_attr( $s['advance_percent'] ); ?>"> %</td></tr>
				<tr><th>Invoice Prefix</th><td><input type="text" name="invoice_prefix" value="<?php echo esc_attr( $s['invoice_prefix'] ); ?>"></td></tr>
				<tr><th>Invoice Note / Terms</th><td><textarea name="invoice_note" rows="3" class="large-text"><?php echo esc_textarea( $s['invoice_note'] ); ?></textarea></td></tr>
			</table>

			<h2><?php esc_html_e( 'Pricing Days', 'bhela-booking' ); ?></h2>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Weekend Days (regular rate)', 'bhela-booking' ); ?></th><td>
					<?php foreach ( $days as $num => $label ) : ?>
						<label style="margin-right:14px"><input type="checkbox" name="weekend_days[]" value="<?php echo esc_attr( $num ); ?>" <?php checked( in_array( $num, array_map( 'intval', (array) $s['weekend_days'] ), true ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</td></tr>
				<tr><th><?php esc_html_e( 'Holidays (one per line, YYYY-MM-DD)', 'bhela-booking' ); ?></th>
					<td><textarea name="holidays" rows="5" class="regular-text"><?php echo esc_textarea( $s['holidays'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Holiday & weekend dates use the Regular rate; other days use the Weekday rate.', 'bhela-booking' ); ?></p></td></tr>
			</table>

			<h2><?php esc_html_e( 'Cabin Rates (per person, 2D1N)', 'bhela-booking' ); ?></h2>
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

			<h2 id="bhela-email">📧 <?php esc_html_e( 'Email Notifications', 'bhela-booking' ); ?></h2>
			<p class="description" style="max-width:900px"><?php esc_html_e( 'Emails go out on new bookings and status changes. The customer email uses your Business Email (above) as the From address.', 'bhela-booking' ); ?></p>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Enable emails', 'bhela-booking' ); ?></th><td><label><input type="checkbox" name="email_enabled" value="1" <?php checked( ! empty( $s['email_enabled'] ) ); ?>> <?php esc_html_e( 'Master switch — send booking emails', 'bhela-booking' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'Which emails', 'bhela-booking' ); ?></th><td>
					<label style="display:block;margin-bottom:6px"><input type="checkbox" name="email_admin_new" value="1" <?php checked( ! empty( $s['email_admin_new'] ) ); ?>> <?php esc_html_e( 'New booking → notify you (owner)', 'bhela-booking' ); ?></label>
					<label style="display:block;margin-bottom:6px"><input type="checkbox" name="email_customer_request" value="1" <?php checked( ! empty( $s['email_customer_request'] ) ); ?>> <?php esc_html_e( 'New booking → customer (request received)', 'bhela-booking' ); ?></label>
					<label style="display:block"><input type="checkbox" name="email_customer_confirmed" value="1" <?php checked( ! empty( $s['email_customer_confirmed'] ) ); ?>> <?php esc_html_e( 'Status = Confirmed → customer (confirmation)', 'bhela-booking' ); ?></label>
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

			<h2 id="bhela-sms">📱 <?php esc_html_e( 'SMS Notifications', 'bhela-booking' ); ?></h2>
			<p class="description" style="max-width:900px"><?php esc_html_e( 'Send an SMS on every new booking (to you + the customer) and when you change a booking status (to the customer). Works with any Bangladesh SMS gateway.', 'bhela-booking' ); ?></p>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Enable SMS', 'bhela-booking' ); ?></th><td><label><input type="checkbox" name="sms_enabled" value="1" <?php checked( ! empty( $s['sms_enabled'] ) ); ?>> <?php esc_html_e( 'Send SMS notifications', 'bhela-booking' ); ?></label></td></tr>
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

			<div style="border:1px solid #dcdcde;border-radius:6px;padding:4px 14px 14px;max-width:900px;background:#fbfbfc">
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
				<tr><th colspan="2"><em><?php esc_html_e( 'Placeholders:', 'bhela-booking' ); ?></em> <code>{name} {phone} {invoice} {date} {cabin} {guests} {total} {advance} {due} {status}</code></th></tr>
				<tr><th><?php esc_html_e( 'New booking → you', 'bhela-booking' ); ?></th><td><textarea name="sms_tpl_admin" rows="2" class="large-text"><?php echo esc_textarea( $s['sms_tpl_admin'] ); ?></textarea></td></tr>
				<tr><th><?php esc_html_e( 'New booking → customer', 'bhela-booking' ); ?></th><td><textarea name="sms_tpl_new" rows="2" class="large-text"><?php echo esc_textarea( $s['sms_tpl_new'] ); ?></textarea></td></tr>
				<tr><th><?php esc_html_e( 'Status change → customer', 'bhela-booking' ); ?></th><td><textarea name="sms_tpl_confirmed" rows="2" class="large-text"><?php echo esc_textarea( $s['sms_tpl_confirmed'] ); ?></textarea></td></tr>
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

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'bhela-booking' ); ?></button>
			</p>
		</form>

		<p style="margin-top:-6px">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:10px">
				<?php wp_nonce_field( 'bhela_bm_email_test' ); ?>
				<input type="hidden" name="action" value="bhela_bm_email_test">
				<button type="submit" class="button">📧 <?php esc_html_e( 'Send Test Email', 'bhela-booking' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
				<?php wp_nonce_field( 'bhela_bm_sms_test' ); ?>
				<input type="hidden" name="action" value="bhela_bm_sms_test">
				<button type="submit" class="button">📲 <?php esc_html_e( 'Send Test SMS', 'bhela-booking' ); ?></button>
			</form>
			<span class="description"><?php esc_html_e( 'Save settings first.', 'bhela-booking' ); ?></span>
		</p>
	</div>
	<?php
}

/** Mask a secret for display (keep last 4). */
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
