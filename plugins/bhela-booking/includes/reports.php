<?php
/**
 * Trip report — bookings for a travel date (or a range) with money owed.
 *
 * The bookings list answers "what came in"; this answers "who is sailing on the
 * 31st, what did each of them pay, and how much is still to collect". It is the
 * sheet the operations manager works from: they call down the list, so every row
 * carries a dialable number and the due figure, and the whole thing prints.
 *
 * Read-only. Every figure is derived from booking meta that already exists —
 * nothing here writes, and no new meta key is introduced.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Menu ---------- */

function bhela_bm_reports_menu() {
	add_submenu_page(
		'edit.php?post_type=bhela_booking',
		__( 'Trip Report', 'bhela-booking' ),
		__( '📄 Trip Report', 'bhela-booking' ),
		'bhela_view_reports',
		'bhela-bm-reports',
		'bhela_bm_reports_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_reports_menu' );

/* ---------- Data ---------- */

/**
 * Accept a date only in the exact storage format, and only if it is a real
 * calendar date. `_bhela_travel_date` is a plain Y-m-d string, so a malformed
 * value would silently match nothing (or everything) rather than error.
 *
 * @param mixed $value Raw request value.
 * @return string Valid Y-m-d date, or '' when it is not one.
 */
function bhela_bm_report_date( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
		return '';
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
}

/**
 * Bookings travelling between two dates, plus the totals for the footer row.
 *
 * Travel dates are stored as Y-m-d strings, which sort correctly as text — so a
 * plain BETWEEN on the meta value is both accurate and index-friendly. Cancelled
 * bookings are dropped by default: their money is not collectable, and leaving
 * them in would inflate every total on the sheet.
 *
 * @param string $from            Y-m-d, inclusive.
 * @param string $to              Y-m-d, inclusive.
 * @param bool   $with_cancelled  Keep cancelled bookings in the result.
 * @return array{rows:array,totals:array}
 */
function bhela_bm_report_rows( $from, $to, $with_cancelled = false ) {
	$totals = array( 'bookings' => 0, 'cabins' => 0, 'guests' => 0, 'total' => 0, 'paid' => 0, 'due' => 0 );
	if ( ! $from || ! $to ) {
		return array( 'rows' => array(), 'totals' => $totals );
	}

	$ids = get_posts( array(
		'post_type'      => 'bhela_booking',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_travel_date',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => '_bhela_travel_date',
				'value'   => array( $from, $to ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	) );

	$rows = array();
	foreach ( $ids as $id ) {
		$status = get_post_meta( $id, '_bhela_status', true ) ?: 'pending';
		if ( 'cancelled' === $status && ! $with_cancelled ) {
			continue;
		}
		$total = (int) get_post_meta( $id, '_bhela_total', true );
		$paid  = (int) get_post_meta( $id, '_bhela_paid_amount', true );
		$row   = array(
			'id'         => $id,
			'invoice_no' => get_post_meta( $id, '_bhela_invoice_no', true ),
			'name'       => get_the_title( $id ),
			'phone'      => get_post_meta( $id, '_bhela_phone', true ),
			'date'       => get_post_meta( $id, '_bhela_travel_date', true ),
			'cabin_type' => get_post_meta( $id, '_bhela_cabin_type', true ),
			'cabins'     => max( 1, (int) get_post_meta( $id, '_bhela_cabin_count', true ) ),
			'guests'     => (int) get_post_meta( $id, '_bhela_guests', true ),
			'total'      => $total,
			'paid'       => $paid,
			// Unclamped on purpose — this is the one surface that must NOT use
			// bhela_bm_balance(): a negative figure here is how the owner spots an
			// overpayment, which every guest-facing document clamps away.
			'due'        => $total - $paid,
			'status'     => $status,
			'pay_method' => get_post_meta( $id, '_bhela_pay_method', true ),
			'txn_id'     => get_post_meta( $id, '_bhela_txn_id', true ),
		);
		$rows[] = $row;

		// Cancelled rows are shown for reference only when the toggle is on —
		// folding their money into the totals would misstate what is collectable.
		if ( 'cancelled' === $status ) {
			continue;
		}
		$totals['bookings']++;
		$totals['cabins'] += $row['cabins'];
		$totals['guests'] += $row['guests'];
		$totals['total']  += $row['total'];
		$totals['paid']   += $row['paid'];
		$totals['due']    += $row['due'];
	}

	return array( 'rows' => $rows, 'totals' => $totals );
}

/** Human range label, collapsing a single-day range to one date. */
function bhela_bm_report_range_label( $from, $to ) {
	if ( ! $from || ! $to ) {
		return '';
	}
	return $from === $to
		? mysql2date( 'j F Y', $from )
		: mysql2date( 'j M', $from ) . ' – ' . mysql2date( 'j M Y', $to );
}

/**
 * The report as plain text, for pasting straight into WhatsApp.
 *
 * Deliberately not a table: WhatsApp uses a proportional font, so columns never
 * line up. A short block per booking stays readable on a phone.
 *
 * @param array  $rows   Rows from bhela_bm_report_rows().
 * @param array  $totals Totals from the same call.
 * @param string $label  Range label for the heading.
 * @return string
 */
function bhela_bm_report_text( $rows, $totals, $label ) {
	$s    = bhela_bm_get_settings();
	$out  = '🛶 ' . $s['business_name'] . " — Trip Report\n";
	$out .= '📅 ' . $label . "\n\n";

	if ( ! $rows ) {
		return $out . 'No bookings for this date.';
	}

	$i = 0;
	foreach ( $rows as $r ) {
		$i++;
		$out .= $i . '. ' . $r['name'] . ' — ' . $r['phone'] . "\n";
		$out .= '   Cabins ' . $r['cabins'] . ' · Guests ' . $r['guests'];
		if ( $r['invoice_no'] ) {
			$out .= ' · ' . $r['invoice_no'];
		}
		$out .= "\n";
		$out .= '   Total ' . bhela_bm_money( $r['total'] )
			. ' | Paid ' . bhela_bm_money( $r['paid'] )
			. ' | Due ' . bhela_bm_money( max( 0, $r['due'] ) );
		if ( 'cancelled' === $r['status'] ) {
			$out .= ' (cancelled)';
		} elseif ( $r['due'] > 0 ) {
			$out .= ' ⚠️';
		}
		$out .= "\n\n";
	}

	$out .= "━━━━━━━━━━━━━━━\n";
	$out .= 'Bookings ' . $totals['bookings'] . ' · Cabins ' . $totals['cabins'] . ' · Guests ' . $totals['guests'] . "\n";
	$out .= 'Total ' . bhela_bm_money( $totals['total'] ) . "\n";
	$out .= 'Paid  ' . bhela_bm_money( $totals['paid'] ) . "\n";
	$out .= 'Due   ' . bhela_bm_money( max( 0, $totals['due'] ) );

	return $out;
}

/* ---------- CSV export ---------- */

/**
 * Stream the same report as CSV.
 *
 * Runs through admin-post rather than the page itself so the download starts on
 * a clean request with no admin markup already sent.
 */
function bhela_bm_report_csv() {
	if ( ! current_user_can( 'bhela_view_reports' ) ) {
		wp_die( esc_html__( 'You are not allowed to export bookings.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_report_csv' );

	$from = bhela_bm_report_date( $_GET['from'] ?? '' );
	$to   = bhela_bm_report_date( $_GET['to'] ?? '' );
	if ( ! $from ) {
		$from = current_time( 'Y-m-d' );
	}
	if ( ! $to || $to < $from ) {
		$to = $from;
	}
	$with_cancelled = ! empty( $_GET['cancelled'] );

	$data     = bhela_bm_report_rows( $from, $to, $with_cancelled );
	$statuses = bhela_bm_statuses();
	$methods  = bhela_bm_report_pay_methods();

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bhela-trip-report-' . $from . ( $to !== $from ? '_to_' . $to : '' ) . '.csv"' );

	$fh = fopen( 'php://output', 'w' );
	// Excel reads a UTF-8 CSV as the local codepage unless it finds a BOM, which
	// turns every Bengali name into mojibake. This byte order mark prevents that.
	fwrite( $fh, "\xEF\xBB\xBF" );
	fputcsv( $fh, array(
		'#', 'Invoice', 'Name', 'Phone', 'Travel Date', 'Cabin Type', 'Cabins',
		'Guests', 'Total', 'Paid', 'Due', 'Status', 'Payment Method', 'Txn ID',
	) );

	$i = 0;
	foreach ( $data['rows'] as $r ) {
		$i++;
		fputcsv( $fh, array(
			$i,
			$r['invoice_no'],
			$r['name'],
			$r['phone'],
			$r['date'],
			$r['cabin_type'],
			$r['cabins'],
			$r['guests'],
			$r['total'],
			$r['paid'],
			$r['due'],
			$statuses[ $r['status'] ] ?? $r['status'],
			// The screen shows an em dash for "not recorded"; a spreadsheet wants
			// an empty cell so the column stays sortable and countable.
			$r['pay_method'] ? ( $methods[ $r['pay_method'] ] ?? $r['pay_method'] ) : '',
			$r['txn_id'],
		) );
	}

	$t = $data['totals'];
	fputcsv( $fh, array() );
	fputcsv( $fh, array( '', '', 'TOTAL (' . $t['bookings'] . ' bookings)', '', '', '', $t['cabins'], $t['guests'], $t['total'], $t['paid'], $t['due'], '', '', '' ) );
	fclose( $fh );
	exit;
}
add_action( 'admin_post_bhela_bm_report_csv', 'bhela_bm_report_csv' );

/** Payment method labels — mirrors the booking meta box options. */
function bhela_bm_report_pay_methods() {
	return array(
		''      => '—',
		'bkash' => 'bKash',
		'nagad' => 'Nagad',
		'bank'  => 'Bank Transfer',
		'cash'  => 'Cash',
	);
}

/* ---------- Page ---------- */

function bhela_bm_reports_page() {
	if ( ! current_user_can( 'bhela_view_reports' ) ) {
		return;
	}

	$from = bhela_bm_report_date( $_GET['from'] ?? '' );
	$to   = bhela_bm_report_date( $_GET['to'] ?? '' );
	if ( ! $from ) {
		$from = current_time( 'Y-m-d' );
	}
	// An inverted or missing end date collapses to a single-day report rather
	// than returning nothing, which would look like "no bookings".
	if ( ! $to || $to < $from ) {
		$to = $from;
	}
	$with_cancelled = ! empty( $_GET['cancelled'] );

	$data     = bhela_bm_report_rows( $from, $to, $with_cancelled );
	$rows     = $data['rows'];
	$t        = $data['totals'];
	$label    = bhela_bm_report_range_label( $from, $to );
	$statuses = bhela_bm_statuses();
	$methods  = bhela_bm_report_pay_methods();
	$s        = bhela_bm_get_settings();

	$csv_url = wp_nonce_url(
		add_query_arg(
			array(
				'action'    => 'bhela_bm_report_csv',
				'from'      => $from,
				'to'        => $to,
				'cancelled' => $with_cancelled ? 1 : 0,
			),
			admin_url( 'admin-post.php' )
		),
		'bhela_bm_report_csv'
	);

	// Trip dates already on the calendar, for the one-click date jump.
	$trip_dates = array();
	if ( function_exists( 'bhela_bm_get_trips' ) ) {
		foreach ( bhela_bm_get_trips() as $trip ) {
			if ( ! empty( $trip['date'] ) ) {
				$trip_dates[ $trip['date'] ] = $trip['label'] ? $trip['label'] : $trip['date'];
			}
		}
	}
	// The three things you do with a finished report, in the band beside the
	// title: print it, paste it into the ops group, or open it in a spreadsheet.
	$actions = '<button type="button" class="button" onclick="window.print()">🖨️ ' . esc_html__( 'Print / PDF', 'bhela-booking' ) . '</button>'
		. ' <button type="button" class="button" id="bhela-bm-report-copy">💬 ' . esc_html__( 'Copy for WhatsApp', 'bhela-booking' ) . '</button>'
		. ' <a class="button" href="' . esc_url( $csv_url ) . '">📊 ' . esc_html__( 'Download CSV', 'bhela-booking' ) . '</a>';
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'📄',
			__( 'Trip Report', 'bhela-booking' ),
			__( 'Bookings, advances and dues for a travel date — ready to hand to the operations manager.', 'bhela-booking' ),
			$actions
		);
		?>

		<div class="bha-bar">
			<form method="get">
				<input type="hidden" name="post_type" value="bhela_booking">
				<input type="hidden" name="page" value="bhela-bm-reports">
				<div class="bha-field">
					<label for="bhela-from"><?php esc_html_e( 'From', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-from" name="from" value="<?php echo esc_attr( $from ); ?>">
				</div>
				<div class="bha-field">
					<label for="bhela-to"><?php esc_html_e( 'To', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-to" name="to" value="<?php echo esc_attr( $to ); ?>">
				</div>
				<?php if ( $trip_dates ) : ?>
					<div class="bha-field">
						<label for="bhela-trip"><?php esc_html_e( 'Or pick a trip', 'bhela-booking' ); ?></label>
						<select id="bhela-trip">
							<option value=""><?php esc_html_e( '— Trip Calendar —', 'bhela-booking' ); ?></option>
							<?php foreach ( $trip_dates as $d => $lab ) : ?>
								<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $from === $d && $to === $d ); ?>><?php echo esc_html( $lab ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
				<label class="bha-check">
					<input type="checkbox" name="cancelled" value="1" <?php checked( $with_cancelled ); ?>>
					<?php esc_html_e( 'Include cancelled', 'bhela-booking' ); ?>
				</label>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'View Report', 'bhela-booking' ); ?></button>
			</form>
		</div>

		<!-- Print-only heading: on screen the band and filter bar already say this. -->
		<div class="bha-printonly">
			<h2><?php echo esc_html( $s['business_name'] ); ?> — <?php esc_html_e( 'Trip Report', 'bhela-booking' ); ?></h2>
			<div><?php echo esc_html( $label ); ?></div>
		</div>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Bookings', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $t['bookings'] ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Cabins', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $t['cabins'] ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $t['guests'] ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Total', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $t['total'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Paid', 'bhela-booking' ); ?></span><span class="bha-card__value is-good"><?php echo esc_html( bhela_bm_money( $t['paid'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Due', 'bhela-booking' ); ?></span><span class="bha-card__value is-danger"><?php echo esc_html( bhela_bm_money( max( 0, $t['due'] ) ) ); ?></span></div>
		</div>

		<div class="bha-scroll">
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:34px">#</th>
					<th><?php esc_html_e( 'Invoice', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Name', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'bhela-booking' ); ?></th>
					<?php if ( $from !== $to ) : ?>
						<th><?php esc_html_e( 'Trip', 'bhela-booking' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Cabins', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Total', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Paid', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Due', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bhela-booking' ); ?></th>
					<th class="bha-noprint"></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="12"><em><?php esc_html_e( 'No bookings for this date.', 'bhela-booking' ); ?></em></td></tr>
				<?php endif; ?>
				<?php $i = 0; foreach ( $rows as $r ) : $i++; ?>
					<tr class="<?php echo 'cancelled' === $r['status'] ? 'bha-row--muted' : ''; ?>">
						<td><?php echo esc_html( $i ); ?></td>
						<td>
							<?php if ( $r['invoice_no'] ) : ?>
								<a href="<?php echo esc_url( bhela_bm_invoice_url( $r['id'] ) ); ?>" target="_blank"><strong><?php echo esc_html( $r['invoice_no'] ); ?></strong></a>
							<?php else : ?>—<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><strong><?php echo esc_html( $r['name'] ); ?></strong></a>
							<?php if ( $r['cabin_type'] ) : ?>
								<span class="bha-sub"><?php echo esc_html( $r['cabin_type'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo $r['phone'] ? '<a href="tel:' . esc_attr( $r['phone'] ) . '">' . esc_html( $r['phone'] ) . '</a>' : '—'; ?></td>
						<?php if ( $from !== $to ) : ?>
							<td><?php echo esc_html( mysql2date( 'j M', $r['date'] ) ); ?></td>
						<?php endif; ?>
						<td><?php echo esc_html( $r['cabins'] ); ?></td>
						<td><?php echo esc_html( $r['guests'] ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['total'] ) ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['paid'] ) ); ?></td>
						<td class="bha-num <?php echo $r['due'] > 0 ? 'bha-num--due' : 'bha-num--clear'; ?>">
							<?php echo esc_html( bhela_bm_money( max( 0, $r['due'] ) ) ); ?>
						</td>
						<td>
							<?php echo bhela_bm_booking_pill( $r['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?>
							<?php if ( $r['pay_method'] ) : ?>
								<span class="bha-sub"><?php echo esc_html( $methods[ $r['pay_method'] ] ?? $r['pay_method'] ); ?><?php echo $r['txn_id'] ? ' · ' . esc_html( $r['txn_id'] ) : ''; ?></span>
							<?php endif; ?>
						</td>
						<td class="bha-noprint">
							<?php if ( $r['phone'] ) : ?>
								<a class="button button-small" target="_blank" rel="noopener"
									href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D/', '', $r['phone'] ) ); ?>">💬</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<?php if ( $rows ) : ?>
				<tfoot>
					<tr>
						<td colspan="<?php echo $from !== $to ? 5 : 4; ?>"><?php esc_html_e( 'TOTAL', 'bhela-booking' ); ?> (<?php echo esc_html( $t['bookings'] ); ?>)</td>
						<td><?php echo esc_html( $t['cabins'] ); ?></td>
						<td><?php echo esc_html( $t['guests'] ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['total'] ) ); ?></td>
						<td class="bha-num bha-num--clear"><?php echo esc_html( bhela_bm_money( $t['paid'] ) ); ?></td>
						<td class="bha-num bha-num--due"><?php echo esc_html( bhela_bm_money( max( 0, $t['due'] ) ) ); ?></td>
						<td colspan="2"></td>
					</tr>
				</tfoot>
			<?php endif; ?>
		</table>
		</div>

		<?php if ( $with_cancelled ) : ?>
			<p class="bha-note"><?php esc_html_e( 'Cancelled bookings are listed for reference but left out of the totals.', 'bhela-booking' ); ?></p>
		<?php endif; ?>
	</div>

	<script>
	(function () {
		var trip = document.getElementById('bhela-trip');
		if (trip) {
			// Picking a calendar trip means a one-day report — fill both ends.
			trip.addEventListener('change', function () {
				if (!trip.value) return;
				document.getElementById('bhela-from').value = trip.value;
				document.getElementById('bhela-to').value = trip.value;
				trip.form.submit();
			});
		}

		var copy = document.getElementById('bhela-bm-report-copy');
		var text = <?php echo wp_json_encode( bhela_bm_report_text( $rows, $t, $label ) ); ?>;
		if (copy) {
			copy.addEventListener('click', function () {
				var done = function () {
					var old = copy.textContent;
					copy.textContent = '✅ <?php echo esc_js( __( 'Copied', 'bhela-booking' ) ); ?>';
					setTimeout(function () { copy.textContent = old; }, 1800);
				};
				// navigator.clipboard needs a secure context; plain-HTTP installs
				// (and LocalWP by default) fall back to a hidden textarea.
				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText(text).then(done);
					return;
				}
				var ta = document.createElement('textarea');
				ta.value = text;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild(ta);
				ta.select();
				try { document.execCommand('copy'); done(); } catch (e) { window.prompt('Copy:', text); }
				document.body.removeChild(ta);
			});
		}
	})();
	</script>
	<?php
}
