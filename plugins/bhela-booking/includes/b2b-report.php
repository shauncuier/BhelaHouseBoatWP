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

/**
 * Resolve the date window from the request.
 *
 * **Blank means every date, and that default is the fix for a real failure.** This
 * screen opened on the current calendar month, filtered by TRAVEL date — but a
 * referral is taken now for a trip months away, so confirming one changed nothing
 * you could see. Three agency bookings, one of them visible, and no error to explain
 * the other two. A report whose default hides its own subject is worse than one that
 * loads slowly.
 *
 * So an unfiltered view shows everything and the operator narrows down, rather than
 * starting narrow and having to guess what is being kept from them.
 *
 * It is one function because the page and the CSV export both need the answer, and
 * the CSV used to pass the raw request through with no defaulting at all — blank
 * dates fell into the early return in bhela_bm_b2b_rows() and downloaded an empty
 * file.
 *
 * @param mixed $raw_from Request value.
 * @param mixed $raw_to   Request value.
 * @return array{from:string,to:string,all:bool}
 */
function bhela_bm_b2b_range( $raw_from, $raw_to ) {
	$from = bhela_bm_report_date( $raw_from );
	$to   = bhela_bm_report_date( $raw_to );

	if ( '' === $from && '' === $to ) {
		return array( 'from' => '1900-01-01', 'to' => '2999-12-31', 'all' => true );
	}
	// One end given is an open-ended range, not an excuse to invent the other.
	if ( '' === $from ) {
		$from = '1900-01-01';
	}
	if ( '' === $to ) {
		$to = '2999-12-31';
	}
	// An inverted range collapses to a single day rather than returning nothing,
	// which would read as "no agency bookings".
	if ( $to < $from ) {
		$to = $from;
	}
	return array( 'from' => $from, 'to' => $to, 'all' => false );
}

/**
 * Referrals waiting on a person, across every date.
 *
 * Counted outside the filter on purpose. The date window is the operator's choice,
 * and a choice must not be able to hide the one thing this screen exists to surface
 * — an unconfirmed referral is a partner not being paid and a figure missing from
 * the month, whichever month it belongs to.
 *
 * @return array{count:int,total:int}
 */
function bhela_bm_b2b_pending_all() {
	$out = array( 'count' => 0, 'total' => 0 );

	$ids = get_posts( array(
		'post_type'      => 'bhela_booking',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'   => '_bhela_referral',
				'value' => 'unconfirmed',
			),
		),
	) );

	foreach ( $ids as $id ) {
		// A cancelled trip owes nobody, so it is not waiting on anyone either.
		if ( 'cancelled' === get_post_meta( $id, '_bhela_status', true ) ) {
			continue;
		}
		$out['count']++;
		$out['total'] += (int) get_post_meta( $id, '_bhela_commission', true );
	}
	return $out;
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

	$range  = bhela_bm_b2b_range( $_GET['from'] ?? '', $_GET['to'] ?? '' );
	$agency = sanitize_key( $_GET['agency'] ?? '' );
	$data   = bhela_bm_b2b_rows( $range['from'], $range['to'], $agency );

	$stamp = $range['all'] ? 'all-dates' : $range['from'] . '_to_' . $range['to'];
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bhela-b2b-' . $stamp . '.csv"' );
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

	// Blank dates mean every date — see bhela_bm_b2b_range().
	$range     = bhela_bm_b2b_range( $_GET['from'] ?? '', $_GET['to'] ?? '' );
	$from      = $range['from'];
	$to        = $range['to'];
	$agency_id = sanitize_key( $_GET['agency'] ?? '' );

	// What the date inputs show. An all-dates view leaves them empty, because that
	// is what produced it and typing a date is how you narrow it.
	$in_from = $range['all'] ? '' : bhela_bm_report_date( $_GET['from'] ?? '' );
	$in_to   = $range['all'] ? '' : bhela_bm_report_date( $_GET['to'] ?? '' );

	$data     = bhela_bm_b2b_rows( $from, $to, $agency_id );
	$rows     = $data['rows'];
	$t        = $data['totals'];
	$per      = bhela_bm_b2b_by_agency( $rows );
	$pending  = bhela_bm_b2b_pending_all();
	$statuses = bhela_bm_statuses();
	$agencies = bhela_bm_agencies( true );

	$csv_url = wp_nonce_url(
		add_query_arg(
			array( 'action' => 'bhela_bm_b2b_csv', 'from' => $in_from, 'to' => $in_to, 'agency' => $agency_id ),
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
						$range['all']
				? __( 'Every booking a travel partner brought, what it was worth, and what they are owed — all dates. Nothing here is ever shown to a guest.', 'bhela-booking' )
				: sprintf(
					/* translators: 1: start date, 2: end date */
					__( 'Agency bookings travelling between %1$s and %2$s. Leave the dates blank to see every one. Nothing here is ever shown to a guest.', 'bhela-booking' ),
					mysql2date( 'j M Y', $from ),
					mysql2date( 'j M Y', $to )
				),
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
					<input type="date" id="bhela-b2b-from" name="from" value="<?php echo esc_attr( $in_from ); ?>">
				</div>
				<div class="bha-field">
					<label for="bhela-b2b-to"><?php esc_html_e( 'To', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-b2b-to" name="to" value="<?php echo esc_attr( $in_to ); ?>">
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

		<?php
		// Counted across every date, not just the filtered window. A date filter is
		// the operator's choice and must not be able to hide the one thing this
		// screen exists to surface.
		$hidden = max( 0, $pending['count'] - $t['pending_n'] );
		if ( $pending['count'] > 0 ) :
			?>
			<p class="bha-callout bha-callout--attention bha-callout--lead">
				<strong><?php
					printf(
						/* translators: %d: how many referrals are waiting */
						esc_html( _n( '%d referral is waiting for you to confirm it.', '%d referrals are waiting for you to confirm it.', $pending['count'], 'bhela-booking' ) ),
						(int) $pending['count']
					);
				?></strong>
				<?php esc_html_e( 'Until you do, that commission is counted by nothing — not the Monthly Statement, not the trip cost sheet. Open the booking and tick Confirm this referral.', 'bhela-booking' ); ?>
				<?php if ( $hidden > 0 ) : ?>
					<br>
					<?php
					printf(
						/* translators: %d: referrals outside the current filter */
						esc_html( _n( '%d of them falls outside the dates you are filtering on.', '%d of them fall outside the dates you are filtering on.', $hidden, 'bhela-booking' ) ),
						(int) $hidden
					);
					?>
					<a href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-b2b' ) ); ?>"><?php esc_html_e( 'Show every date', 'bhela-booking' ); ?></a>
				<?php endif; ?>
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
									<a class="button button-small" href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-b2b', array( 'agency' => $aid, 'from' => $in_from, 'to' => $in_to ) ) ); ?>"><?php esc_html_e( 'Only this one', 'bhela-booking' ); ?></a>
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
