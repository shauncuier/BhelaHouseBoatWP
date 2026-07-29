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
		'edit_posts',
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
	if ( ! current_user_can( 'edit_posts' ) ) {
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
	if ( ! current_user_can( 'edit_posts' ) ) {
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
	?>
	<style>
		.bhela-rep { max-width: 1240px; }
		.bhela-rep__bar { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 14px 16px; margin: 12px 0 18px; }
		.bhela-rep__bar form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
		.bhela-rep__f { display: flex; flex-direction: column; gap: 4px; }
		.bhela-rep__f label { font-size: 12px; color: #50575e; font-weight: 600; }
		.bhela-rep__chk { display: flex; align-items: center; gap: 6px; font-size: 13px; padding-bottom: 6px; }
		.bhela-rep__sum { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
		.bhela-rep__sum > div { flex: 1; min-width: 130px; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 12px 14px; }
		.bhela-rep__sum b { font-size: 21px; display: block; line-height: 1.3; }
		.bhela-rep__sum span { color: #50575e; font-size: 12px; }
		.bhela-rep table.widefat { border-radius: 10px; overflow: hidden; }
		.bhela-rep td, .bhela-rep th { vertical-align: middle; }
		.bhela-rep__due { font-weight: 700; color: #b32d2e; }
		.bhela-rep__clear { font-weight: 700; color: #1a7f37; }
		.bhela-rep__pill { display: inline-block; padding: 2px 9px; border-radius: 12px; color: #fff; font-size: 11px; font-weight: 600; }
		.bhela-rep tfoot td { background: #f6f7f7; font-weight: 700; border-top: 2px solid #c3c4c7; }
		.bhela-rep__row--cancelled td { opacity: .55; }
		.bhela-rep__print { display: none; }
		.bhela-rep__note { color: #787c82; font-size: 12px; margin-top: 10px; }
		.bhela-rep__actions { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }

		/* Print: drop the admin chrome so the sheet is the whole page. */
		@media print {
			#adminmenumain, #wpadminbar, #wpfooter, .bhela-rep__bar, .bhela-rep__actions,
			.notice, .update-nag, #screen-meta, #screen-meta-links, .bhela-rep__col-action { display: none !important; }
			#wpcontent, #wpbody-content { margin: 0 !important; padding: 0 !important; }
			html.wp-toolbar { padding-top: 0 !important; }
			.bhela-rep { max-width: none; }
			.bhela-rep__print { display: block; margin-bottom: 14px; }
			.bhela-rep__print h2 { margin: 0 0 4px; font-size: 18px; }
			.bhela-rep table.widefat { border: 1px solid #333; }
			.bhela-rep td, .bhela-rep th { border: 1px solid #999 !important; font-size: 11px; padding: 5px 6px !important; }
			.bhela-rep__sum > div { border: 1px solid #999; }
			a { text-decoration: none !important; color: #000 !important; }
		}
	</style>

	<div class="wrap bhela-rep">
		<h1>📄 <?php esc_html_e( 'Trip Report', 'bhela-booking' ); ?></h1>
		<p style="color:#50575e;margin:4px 0 0"><?php esc_html_e( 'Bookings, advances and dues for a travel date — ready to hand to the operations manager.', 'bhela-booking' ); ?></p>

		<div class="bhela-rep__bar">
			<form method="get">
				<input type="hidden" name="post_type" value="bhela_booking">
				<input type="hidden" name="page" value="bhela-bm-reports">
				<div class="bhela-rep__f">
					<label for="bhela-from"><?php esc_html_e( 'From', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-from" name="from" value="<?php echo esc_attr( $from ); ?>">
				</div>
				<div class="bhela-rep__f">
					<label for="bhela-to"><?php esc_html_e( 'To', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-to" name="to" value="<?php echo esc_attr( $to ); ?>">
				</div>
				<?php if ( $trip_dates ) : ?>
					<div class="bhela-rep__f">
						<label for="bhela-trip"><?php esc_html_e( 'Or pick a trip', 'bhela-booking' ); ?></label>
						<select id="bhela-trip">
							<option value=""><?php esc_html_e( '— Trip Calendar —', 'bhela-booking' ); ?></option>
							<?php foreach ( $trip_dates as $d => $lab ) : ?>
								<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $from === $d && $to === $d ); ?>><?php echo esc_html( $lab ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
				<label class="bhela-rep__chk">
					<input type="checkbox" name="cancelled" value="1" <?php checked( $with_cancelled ); ?>>
					<?php esc_html_e( 'Include cancelled', 'bhela-booking' ); ?>
				</label>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'View Report', 'bhela-booking' ); ?></button>
			</form>
		</div>

		<div class="bhela-rep__actions">
			<button type="button" class="button" onclick="window.print()">🖨️ <?php esc_html_e( 'Print / PDF', 'bhela-booking' ); ?></button>
			<button type="button" class="button" id="bhela-rep-copy">💬 <?php esc_html_e( 'Copy for WhatsApp', 'bhela-booking' ); ?></button>
			<a class="button" href="<?php echo esc_url( $csv_url ); ?>">📊 <?php esc_html_e( 'Download CSV', 'bhela-booking' ); ?></a>
		</div>

		<!-- Print-only heading: on screen the <h1> and filter bar already say this. -->
		<div class="bhela-rep__print">
			<h2><?php echo esc_html( $s['business_name'] ); ?> — <?php esc_html_e( 'Trip Report', 'bhela-booking' ); ?></h2>
			<div><?php echo esc_html( $label ); ?></div>
		</div>

		<div class="bhela-rep__sum">
			<div><span><?php esc_html_e( 'Bookings', 'bhela-booking' ); ?></span><b><?php echo esc_html( $t['bookings'] ); ?></b></div>
			<div><span><?php esc_html_e( 'Cabins', 'bhela-booking' ); ?></span><b><?php echo esc_html( $t['cabins'] ); ?></b></div>
			<div><span><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></span><b><?php echo esc_html( $t['guests'] ); ?></b></div>
			<div><span><?php esc_html_e( 'Total', 'bhela-booking' ); ?></span><b><?php echo esc_html( bhela_bm_money( $t['total'] ) ); ?></b></div>
			<div><span><?php esc_html_e( 'Paid', 'bhela-booking' ); ?></span><b style="color:#1a7f37"><?php echo esc_html( bhela_bm_money( $t['paid'] ) ); ?></b></div>
			<div><span><?php esc_html_e( 'Due', 'bhela-booking' ); ?></span><b style="color:#b32d2e"><?php echo esc_html( bhela_bm_money( max( 0, $t['due'] ) ) ); ?></b></div>
		</div>

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
					<th style="text-align:right"><?php esc_html_e( 'Total', 'bhela-booking' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Paid', 'bhela-booking' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Due', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bhela-booking' ); ?></th>
					<th class="bhela-rep__col-action"></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="12"><em><?php esc_html_e( 'No bookings for this date.', 'bhela-booking' ); ?></em></td></tr>
				<?php endif; ?>
				<?php $i = 0; foreach ( $rows as $r ) : $i++; ?>
					<tr class="<?php echo 'cancelled' === $r['status'] ? 'bhela-rep__row--cancelled' : ''; ?>">
						<td><?php echo esc_html( $i ); ?></td>
						<td>
							<?php if ( $r['invoice_no'] ) : ?>
								<a href="<?php echo esc_url( bhela_bm_invoice_url( $r['id'] ) ); ?>" target="_blank"><strong><?php echo esc_html( $r['invoice_no'] ); ?></strong></a>
							<?php else : ?>—<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><strong><?php echo esc_html( $r['name'] ); ?></strong></a>
							<?php if ( $r['cabin_type'] ) : ?>
								<br><span style="color:#787c82;font-size:11px"><?php echo esc_html( $r['cabin_type'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo $r['phone'] ? '<a href="tel:' . esc_attr( $r['phone'] ) . '">' . esc_html( $r['phone'] ) . '</a>' : '—'; ?></td>
						<?php if ( $from !== $to ) : ?>
							<td><?php echo esc_html( mysql2date( 'j M', $r['date'] ) ); ?></td>
						<?php endif; ?>
						<td><?php echo esc_html( $r['cabins'] ); ?></td>
						<td><?php echo esc_html( $r['guests'] ); ?></td>
						<td style="text-align:right"><?php echo esc_html( bhela_bm_money( $r['total'] ) ); ?></td>
						<td style="text-align:right"><?php echo esc_html( bhela_bm_money( $r['paid'] ) ); ?></td>
						<td style="text-align:right" class="<?php echo $r['due'] > 0 ? 'bhela-rep__due' : 'bhela-rep__clear'; ?>">
							<?php echo esc_html( bhela_bm_money( max( 0, $r['due'] ) ) ); ?>
						</td>
						<td>
							<span class="bhela-rep__pill" style="background:<?php echo esc_attr( bhela_bm_status_color( $r['status'] ) ); ?>"><?php echo esc_html( $statuses[ $r['status'] ] ?? $r['status'] ); ?></span>
							<?php if ( $r['pay_method'] ) : ?>
								<br><span style="color:#787c82;font-size:11px"><?php echo esc_html( $methods[ $r['pay_method'] ] ?? $r['pay_method'] ); ?><?php echo $r['txn_id'] ? ' · ' . esc_html( $r['txn_id'] ) : ''; ?></span>
							<?php endif; ?>
						</td>
						<td class="bhela-rep__col-action">
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
						<td style="text-align:right"><?php echo esc_html( bhela_bm_money( $t['total'] ) ); ?></td>
						<td style="text-align:right;color:#1a7f37"><?php echo esc_html( bhela_bm_money( $t['paid'] ) ); ?></td>
						<td style="text-align:right;color:#b32d2e"><?php echo esc_html( bhela_bm_money( max( 0, $t['due'] ) ) ); ?></td>
						<td colspan="2"></td>
					</tr>
				</tfoot>
			<?php endif; ?>
		</table>

		<?php if ( $with_cancelled ) : ?>
			<p class="bhela-rep__note"><?php esc_html_e( 'Cancelled bookings are listed for reference but left out of the totals.', 'bhela-booking' ); ?></p>
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

		var copy = document.getElementById('bhela-rep-copy');
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
