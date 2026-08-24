<?php
/**
 * The B2B Report — every agency booking in one place.
 *
 * The data existed from the moment agencies did, but only scattered: a commission
 * on each booking, a deduction line on the Monthly Statement, and nothing that
 * answered "show me everything Travel Compass has brought us". This is that screen.
 *
 * It leads with what needs doing rather than what happened. A referral attributes
 * itself and then waits for a person — so unconfirmed bookings are counted, totalled
 * and listed first, because a referral nobody looks at is a partner not being paid
 * and a figure missing from the month.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_b2b_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'accounts' ),
		__( 'B2B Report', 'bhela-booking' ),
		__( '🤝 B2B Report', 'bhela-booking' ),
		'bhela_view_statement',
		'bhela-bm-b2b',
		'bhela_bm_b2b_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_b2b_menu' );

/**
 * Agency bookings in a travel-date range, optionally one agency only.
 *
 * Deliberately NOT built on bhela_bm_commission_rows(): that answers "what does the
 * month owe", so it excludes cancelled bookings and unconfirmed referrals on
 * purpose. This screen has to show exactly the things that function hides — a
 * referral waiting to be confirmed is the main reason to open it.
 *
 * @param string $from      Y-m-d.
 * @param string $to        Y-m-d.
 * @param string $agency_id Limit to one agency, or '' for all.
 * @return array{rows:array,totals:array}
 */
function bhela_bm_b2b_rows( $from, $to, $agency_id = '' ) {
	$totals = array(
		'bookings'    => 0,
		'guests'      => 0,
		'value'       => 0,   // what the guests are paying
		'commission'  => 0,   // what the partners are owed, confirmed only
		'pending'     => 0,   // suggested, waiting on a person
		'pending_n'   => 0,
	);
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
		$agency = sanitize_key( (string) get_post_meta( $id, '_bhela_agency', true ) );
		$comm   = (int) get_post_meta( $id, '_bhela_commission', true );

		// Nothing to do with B2B at all.
		if ( '' === $agency && $comm <= 0 ) {
			continue;
		}
		if ( '' !== $agency_id && $agency !== $agency_id ) {
			continue;
		}

		$status   = get_post_meta( $id, '_bhela_status', true ) ?: 'pending';
		$referral = (string) get_post_meta( $id, '_bhela_referral', true );
		$total    = (int) get_post_meta( $id, '_bhela_total', true );
		$a        = '' !== $agency ? bhela_bm_agency( $agency ) : null;
		$guests   = (int) get_post_meta( $id, '_bhela_guests', true );

		$rows[] = array(
			'id'          => $id,
			'invoice_no'  => get_post_meta( $id, '_bhela_invoice_no', true ),
			'name'        => get_the_title( $id ),
			'date'        => get_post_meta( $id, '_bhela_travel_date', true ),
			'guests'      => $guests,
			'agency_id'   => $agency,
			'agency'      => $a ? $a['name'] : ( '' === $agency ? __( '(no agency named)', 'bhela-booking' ) : $agency ),
			'agency_ref'  => get_post_meta( $id, '_bhela_agency_ref', true ),
			'total'       => $total,
			'commission'  => $comm,
			'status'      => $status,
			'referral'    => $referral,
			'referred_at' => get_post_meta( $id, '_bhela_referral_at', true ),
		);

		$totals['bookings']++;
		$totals['guests'] += $guests;
		$totals['value']  += $total;

		// A cancelled trip owes nobody, and an unconfirmed referral is not yet a
		// commitment — the same two rules bhela_bm_commission_rows() applies, so the
		// "owed" figure here and the statement's deduction cannot disagree.
		if ( 'cancelled' === $status ) {
			continue;
		}
		if ( 'unconfirmed' === $referral ) {
			$totals['pending'] += $comm;
			$totals['pending_n']++;
			continue;
		}
		$totals['commission'] += $comm;
	}

	// Waiting-on-a-person first: this screen exists to get those cleared.
	usort( $rows, function ( $a, $b ) {
		$pa = 'unconfirmed' === $a['referral'] ? 0 : 1;
		$pb = 'unconfirmed' === $b['referral'] ? 0 : 1;
		return $pa === $pb ? strcmp( (string) $a['date'], (string) $b['date'] ) : $pa - $pb;
	} );

	return array( 'rows' => $rows, 'totals' => $totals );
}

/** Per-agency subtotals for the rows on screen. */
function bhela_bm_b2b_by_agency( $rows ) {
	$out = array();
	foreach ( $rows as $r ) {
		$key = '' !== $r['agency_id'] ? $r['agency_id'] : '_none';
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = array( 'name' => $r['agency'], 'bookings' => 0, 'value' => 0, 'commission' => 0, 'pending' => 0 );
		}
		$out[ $key ]['bookings']++;
		$out[ $key ]['value'] += $r['total'];
		if ( 'cancelled' === $r['status'] ) {
			continue;
		}
		if ( 'unconfirmed' === $r['referral'] ) {
			$out[ $key ]['pending'] += $r['commission'];
		} else {
			$out[ $key ]['commission'] += $r['commission'];
		}
	}
	uasort( $out, function ( $a, $b ) {
		return $b['commission'] <=> $a['commission'];
	} );
	return $out;
}

/** CSV of whatever is on screen. */
function bhela_bm_b2b_csv() {
	if ( ! current_user_can( 'bhela_view_statement' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_b2b_csv' );

	$from   = bhela_bm_report_date( $_GET['from'] ?? '' );
	$to     = bhela_bm_report_date( $_GET['to'] ?? '' );
	$agency = sanitize_key( $_GET['agency'] ?? '' );
	$data   = bhela_bm_b2b_rows( $from, $to, $agency );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bhela-b2b-' . $from . '_to_' . $to . '.csv"' );
	$fh = fopen( 'php://output', 'w' );
	fwrite( $fh, "\xEF\xBB\xBF" );          // BOM, so Excel reads the Bengali
	fputcsv( $fh, array( 'Travel date', 'Booking', 'Guest', 'Agency', 'Agency ref', 'Guests', 'Booking value', 'Commission', 'Referral', 'Status' ) );

	foreach ( $data['rows'] as $r ) {
		// Every free-text cell through bhela_bm_csv_cell(): an agency name is typed
		// by hand, and a spreadsheet will execute a cell that opens with '='.
		fputcsv( $fh, array(
			$r['date'],
			bhela_bm_csv_cell( $r['invoice_no'] ),
			bhela_bm_csv_cell( $r['name'] ),
			bhela_bm_csv_cell( $r['agency'] ),
			bhela_bm_csv_cell( $r['agency_ref'] ),
			$r['guests'],
			$r['total'],
			$r['commission'],
			bhela_bm_csv_cell( $r['referral'] ? $r['referral'] : 'entered by staff' ),
			bhela_bm_csv_cell( $r['status'] ),
		) );
	}
	$t = $data['totals'];
	fputcsv( $fh, array() );
	fputcsv( $fh, array( '', '', 'TOTAL (' . $t['bookings'] . ' bookings)', '', '', $t['guests'], $t['value'], $t['commission'], 'confirmed only', '' ) );
	fputcsv( $fh, array( '', '', 'AWAITING CONFIRMATION (' . $t['pending_n'] . ')', '', '', '', '', $t['pending'], '', '' ) );
	fclose( $fh );
	exit;
}
add_action( 'admin_post_bhela_bm_b2b_csv', 'bhela_bm_b2b_csv' );

function bhela_bm_b2b_page() {
	if ( ! current_user_can( 'bhela_view_statement' ) ) {
		return;
	}

	// Defaults to the current financial-ish view: this month plus the rest of it.
	$from = bhela_bm_report_date( $_GET['from'] ?? '' );
	$to   = bhela_bm_report_date( $_GET['to'] ?? '' );
	if ( ! $from ) {
		$from = gmdate( 'Y-m-01' );
	}
	if ( ! $to || $to < $from ) {
		$to = gmdate( 'Y-m-t', strtotime( $from ) );
	}
	$agency_id = sanitize_key( $_GET['agency'] ?? '' );

	$data     = bhela_bm_b2b_rows( $from, $to, $agency_id );
	$rows     = $data['rows'];
	$t        = $data['totals'];
	$per      = bhela_bm_b2b_by_agency( $rows );
	$statuses = bhela_bm_statuses();
	$agencies = bhela_bm_agencies( true );

	$csv_url = wp_nonce_url(
		add_query_arg(
			array( 'action' => 'bhela_bm_b2b_csv', 'from' => $from, 'to' => $to, 'agency' => $agency_id ),
			admin_url( 'admin-post.php' )
		),
		'bhela_bm_b2b_csv'
	);
	$actions = '<button type="button" class="button" onclick="window.print()">🖨️ ' . esc_html__( 'Print / PDF', 'bhela-booking' ) . '</button>'
		. ' <a class="button" href="' . esc_url( $csv_url ) . '">📊 ' . esc_html__( 'Download CSV', 'bhela-booking' ) . '</a>';
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'🤝',
			__( 'B2B Report', 'bhela-booking' ),
			__( 'Every booking a travel partner brought, what it was worth, and what they are owed. Nothing here is ever shown to a guest.', 'bhela-booking' ),
			$actions
		);
		?>

		<div class="bha-bar">
			<?php // No hidden post_type: this page hangs off admin.php, and carrying one would submit the filter to the Posts list (CLAUDE.md §13.14). ?>
			<form method="get">
				<input type="hidden" name="page" value="bhela-bm-b2b">
				<div class="bha-field">
					<label for="bhela-b2b-agency"><?php esc_html_e( 'Agency', 'bhela-booking' ); ?></label>
					<select id="bhela-b2b-agency" name="agency">
						<option value=""><?php esc_html_e( '— All agencies —', 'bhela-booking' ); ?></option>
						<?php foreach ( $agencies as $aid => $a ) : ?>
							<option value="<?php echo esc_attr( $aid ); ?>" <?php selected( $agency_id, $aid ); ?>>
								<?php echo esc_html( $a['name'] . ( ! empty( $a['retired'] ) ? ' (ended)' : '' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="bha-field">
					<label for="bhela-b2b-from"><?php esc_html_e( 'From', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-b2b-from" name="from" value="<?php echo esc_attr( $from ); ?>">
				</div>
				<div class="bha-field">
					<label for="bhela-b2b-to"><?php esc_html_e( 'To', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-b2b-to" name="to" value="<?php echo esc_attr( $to ); ?>">
				</div>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'View', 'bhela-booking' ); ?></button>
			</form>
		</div>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Bookings', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( $t['bookings'] ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( $t['guests'] ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Booking value', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $t['value'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Commission owed', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $t['commission'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Awaiting confirmation', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $t['pending'] ) ); ?></span></div>
		</div>

		<?php if ( $t['pending_n'] > 0 ) : ?>
			<p class="bha-callout bha-callout--attention bha-callout--lead">
				<strong><?php
					printf(
						/* translators: %d: how many referrals are waiting */
						esc_html( _n( '%d referral is waiting for you to confirm it.', '%d referrals are waiting for you to confirm it.', $t['pending_n'], 'bhela-booking' ) ),
						(int) $t['pending_n']
					);
				?></strong>
				<?php esc_html_e( 'Until you do, that commission is counted by nothing — not the Monthly Statement, not the trip cost sheet. Open a booking below and tick Confirm this referral.', 'bhela-booking' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( count( $per ) > 1 ) : ?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'By agency', 'bhela-booking' ); ?></h2>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Agency', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Bookings', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Booking value', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Commission owed', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Awaiting', 'bhela-booking' ); ?></th>
						<th class="bha-noprint"></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $per as $aid => $a ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $a['name'] ); ?></strong></td>
							<td class="bha-num"><?php echo esc_html( $a['bookings'] ); ?></td>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $a['value'] ) ); ?></td>
							<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $a['commission'] ) ); ?></strong></td>
							<td class="bha-num"><?php echo $a['pending'] > 0 ? esc_html( bhela_bm_money( $a['pending'] ) ) : '—'; ?></td>
							<td class="bha-noprint">
								<?php if ( '_none' !== $aid ) : ?>
									<a class="button button-small" href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-b2b', array( 'agency' => $aid, 'from' => $from, 'to' => $to ) ) ); ?>"><?php esc_html_e( 'Only this one', 'bhela-booking' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'Bookings', 'bhela-booking' ); ?></h2>
			<?php if ( ! $rows ) : ?>
				<p class="bha-callout"><?php esc_html_e( 'No agency bookings in this range.', 'bhela-booking' ); ?></p>
			<?php else : ?>
			<div class="bha-scroll">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Travel date', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Booking', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Guest', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Agency', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Booking value', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Commission', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'State', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r['date'] ? mysql2date( 'j M Y', $r['date'] ) : '—' ); ?></td>
						<td><a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><?php echo esc_html( $r['invoice_no'] ?: '#' . $r['id'] ); ?></a></td>
						<td><?php echo esc_html( $r['name'] ); ?></td>
						<td><?php echo esc_html( $r['agency'] ); ?>
							<?php if ( $r['agency_ref'] ) : ?><div class="bha-sub"><?php echo esc_html( $r['agency_ref'] ); ?></div><?php endif; ?></td>
						<td class="bha-num"><?php echo esc_html( $r['guests'] ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['total'] ) ); ?></td>
						<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $r['commission'] ) ); ?></strong></td>
						<td>
							<?php
							if ( 'unconfirmed' === $r['referral'] ) {
								echo bhela_bm_status_pill( __( 'referral — confirm it', 'bhela-booking' ), 'attention' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} elseif ( 'confirmed' === $r['referral'] ) {
								echo bhela_bm_status_pill( __( 'referral confirmed', 'bhela-booking' ), 'good' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} else {
								echo bhela_bm_status_pill( __( 'entered by staff', 'bhela-booking' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							if ( 'cancelled' === $r['status'] ) {
								echo ' ' . bhela_bm_status_pill( $statuses['cancelled'] ?? 'Cancelled', 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr class="bha-row--total">
						<td colspan="5"><?php esc_html_e( 'Total', 'bhela-booking' ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['value'] ) ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['commission'] ) ); ?></td>
						<td><?php esc_html_e( 'confirmed only', 'bhela-booking' ); ?></td>
					</tr>
				</tfoot>
			</table>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
