<?php
/**
 * Monthly Statement — the month's approved trips, less the month's expenses.
 *
 * The layer above the cost sheets: what the owner currently assembles by hand
 * from thirteen sheets and an expense report. Every figure is read from records
 * that already exist, so the statement cannot disagree with the sheets it is
 * built from.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_statement_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'accounts' ),
		__( 'Monthly Statement', 'bhela-booking' ),
		__( '📈 Monthly Statement', 'bhela-booking' ),
		'bhela_view_statement',
		'bhela-bm-statement',
		'bhela_bm_statement_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_statement_menu' );

/** A YYYY-MM string, or '' if it is not one. */
function bhela_bm_statement_month( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	return preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $value ) ? $value : '';
}

/**
 * Everything the statement needs for one month.
 *
 * Only **approved** cost sheets are counted. A sheet still being typed would
 * otherwise move the month's profit every time someone saved a line, and the
 * owner would have no way to tell a finished month from a half-finished one.
 * Unapproved sheets are returned separately so the screen can say they exist
 * rather than quietly omitting them.
 *
 * @param string $month YYYY-MM.
 * @return array
 */
function bhela_bm_statement_data( $month ) {
	$out = array(
		'trips' => array(), 'pending' => array(),
		'guests' => 0, 'earnings' => 0, 'cost' => 0, 'profit' => 0,
		'expenses' => array( 'rows' => array(), 'by_type' => array(), 'total' => 0 ),
		'salary' => array( 'total' => 0, 'sheets' => 0, 'ids' => array() ),
		'gross' => 0, 'cost_pp' => 0.0, 'profit_pp' => 0.0,
		'signoff' => array(), 'stale' => array(),
	);
	if ( ! $month ) {
		return $out;
	}
	$from = $month . '-01';
	$to   = gmdate( 'Y-m-t', strtotime( $from ) );

	$ids = get_posts( array(
		'post_type'      => 'bhela_cost',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_cost_trip_date',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => '_bhela_cost_trip_date',
				'value'   => array( $from, $to ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	) );

	foreach ( $ids as $id ) {
		$header   = json_decode( (string) get_post_meta( $id, '_bhela_cost_header', true ), true );
		$header   = is_array( $header ) ? $header : array();
		$earnings = (int) get_post_meta( $id, '_bhela_cost_earnings', true );
		$cost     = (int) get_post_meta( $id, '_bhela_cost_total', true );
		$row      = array(
			'id'       => $id,
			'date'     => (string) get_post_meta( $id, '_bhela_cost_trip_date', true ),
			'trip_id'  => (string) ( $header['trip_id'] ?? '' ),
			'guests'   => (int) ( $header['total_guest'] ?? 0 ),
			'earnings' => $earnings,
			'cost'     => $cost,
			'profit'   => $earnings - $cost,
			'status'   => bhela_bm_cost_status( $id ),
		);

		if ( 'approved' !== $row['status'] ) {
			$out['pending'][] = $row;
			continue;
		}
		// An approved sheet whose bookings have changed since sign-off is still
		// counted — it is the signed figure, and quietly substituting a new one
		// would make the statement disagree with the sheet it cites — but the
		// screen has to be able to say so.
		$drift = function_exists( 'bhela_bm_cost_earnings_drift' ) ? bhela_bm_cost_earnings_drift( $id ) : array( 'stale' => false );
		if ( ! empty( $drift['stale'] ) ) {
			$out['stale'][] = array(
				'id'     => $id,
				'date'   => $row['date'],
				'stored' => (int) $drift['stored'],
				'live'   => (int) $drift['live'],
				'diff'   => (int) $drift['diff'],
			);
		}

		$out['trips'][]   = $row;
		$out['guests']   += $row['guests'];
		$out['earnings'] += $row['earnings'];
		$out['cost']     += $row['cost'];
		$out['profit']   += $row['profit'];

		// Carry the sign-offs through from the sheets themselves, so the
		// statement names whoever actually approved the month's figures.
		foreach ( array( 'prepared', 'checked', 'approved' ) as $step ) {
			$by = (int) get_post_meta( $id, '_bhela_cost_' . $step . '_by', true );
			if ( $by && empty( $out['signoff'][ $step ] ) ) {
				$u = get_userdata( $by );
				$out['signoff'][ $step ] = $u ? $u->display_name : '#' . $by;
			}
		}
	}

	$out['expenses'] = function_exists( 'bhela_bm_expense_rows' )
		? bhela_bm_expense_rows( $from, $to )
		: $out['expenses'];

	// Crew wages are a cost of running the boat, so they come off the bottom line
	// like fuel and groceries do. This used to be omitted altogether, which
	// overstated every month's gross profit by the whole wage bill.
	//
	// Kept as its own figure rather than folded into `expenses`, because the
	// owner's sheet reads them as separate things — trip costs, then overheads
	// like marketing and renovation, then payroll — and merging them would also
	// silently move the "deductions" total the July harness pins.
	// The trip count is passed in from what this function has already counted, so
	// payroll never has to ask the statement for it and re-enter this function.
	$out['salary'] = function_exists( 'bhela_bm_salary_month_total' )
		? bhela_bm_salary_month_total( $month, count( $out['trips'] ) )
		: $out['salary'];

	$out['gross'] = $out['profit'] - $out['expenses']['total'] - $out['salary']['total'];
	if ( $out['guests'] > 0 ) {
		// Cost per person on the owner's sheet includes marketing and
		// renovation, not just trip cost — the two readings differ by about a
		// thousand taka a head, so this follows the sheet. Payroll is in here for
		// the same reason it is in gross profit: it is a cost of carrying them.
		$out['cost_pp']   = round( ( $out['cost'] + $out['expenses']['total'] + $out['salary']['total'] ) / $out['guests'], 2 );
		$out['profit_pp'] = round( $out['gross'] / $out['guests'], 2 );
	}
	return $out;
}

function bhela_bm_statement_page() {
	if ( ! current_user_can( 'bhela_view_statement' ) ) {
		return;
	}
	$month = bhela_bm_statement_month( $_GET['month'] ?? '' );
	if ( ! $month ) {
		$month = current_time( 'Y-m' );
	}
	$d       = bhela_bm_statement_data( $month );
	$types   = function_exists( 'bhela_bm_expense_types' ) ? bhela_bm_expense_types( true ) : array();
	$s       = bhela_bm_get_settings();
	$label   = mysql2date( 'F Y', $month . '-01' );
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'📈',
			__( 'Monthly Statement', 'bhela-booking' ),
			__( 'Approved trip cost sheets for the month, less the month\'s expenses.', 'bhela-booking' ),
			'<button type="button" class="button" onclick="window.print()">🖨️ ' . esc_html__( 'Print / PDF', 'bhela-booking' ) . '</button>'
		);
		?>

		<div class="bha-bar">
			<form method="get">
				<?php // No post_type: this page is a child of admin.php now, not edit.php.
				// Leaving it in would send the filter to the Posts list, silently. ?>
				<input type="hidden" name="page" value="bhela-bm-statement">
				<div class="bha-field bha-field--caps">
					<label for="st-month"><?php esc_html_e( 'Month', 'bhela-booking' ); ?></label>
					<input type="month" id="st-month" name="month" value="<?php echo esc_attr( $month ); ?>">
				</div>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Show', 'bhela-booking' ); ?></button>
			</form>
		</div>

		<div class="bha-printonly">
			<h2><?php echo esc_html( $s['business_name'] ); ?> — <?php esc_html_e( 'Monthly Statement', 'bhela-booking' ); ?></h2>
			<div><?php echo esc_html( $label ); ?></div>
		</div>


		<?php
		// Sheets with no trip date are in no month and no year at all, so they
		// cannot show up as a row anywhere below. Name them here or they are
		// simply money that vanished.
		$bhela_undated = function_exists( 'bhela_bm_cost_undated' ) ? bhela_bm_cost_undated() : array();
		if ( $bhela_undated ) :
			?>
			<p class="bha-callout bha-callout--attention bha-callout--lead">
				<strong>⚠️ <?php echo esc_html( sprintf(
					/* translators: %d: number of cost sheets with no trip date */
					_n( '%d cost sheet has no trip date.', '%d cost sheets have no trip date.', count( $bhela_undated ), 'bhela-booking' ),
					count( $bhela_undated )
				) ); ?></strong>
				<?php esc_html_e( 'It counts towards no month and no year until a date is set — not here, and not on any other report.', 'bhela-booking' ); ?>
				<?php foreach ( $bhela_undated as $bhela_u ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $bhela_u['id'] ) ); ?>"><?php echo esc_html( $bhela_u['title'] ?: sprintf( '#%d', $bhela_u['id'] ) ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>


		<?php if ( ! empty( $d['stale'] ) ) : ?>
			<p class="bha-callout bha-callout--attention bha-callout--lead">
				<strong>⚠️ <?php echo esc_html( sprintf(
					/* translators: %d: number of approved sheets whose bookings changed */
					_n( '%d approved sheet no longer matches its bookings.', '%d approved sheets no longer match their bookings.', count( $d['stale'] ), 'bhela-booking' ),
					count( $d['stale'] )
				) ); ?></strong>
				<?php esc_html_e( 'A booking was added, cancelled or refunded after the sheet was signed off. The signed figure is what counts below — unlock the sheet, re-save and re-approve to bring it up to date.', 'bhela-booking' ); ?>
				<?php foreach ( $d['stale'] as $bhela_s ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $bhela_s['id'] ) ); ?>"><?php
						printf(
							'%s: %s → %s',
							esc_html( mysql2date( 'j M', $bhela_s['date'] ) ),
							esc_html( bhela_bm_money( $bhela_s['stored'] ) ),
							esc_html( bhela_bm_money( $bhela_s['live'] ) )
						);
					?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<?php if ( $d['pending'] ) : ?>
			<p class="bha-callout bha-callout--attention bha-callout--lead">
				<strong>⚠️ <?php echo esc_html( sprintf(
					/* translators: %d: number of sheets */
					_n( '%d cost sheet for this month is not approved yet.', '%d cost sheets for this month are not approved yet.', count( $d['pending'] ), 'bhela-booking' ),
					count( $d['pending'] )
				) ); ?></strong>
				<?php esc_html_e( 'It is left out of the totals below — a month is only final once every sheet is signed off.', 'bhela-booking' ); ?>
				<?php foreach ( $d['pending'] as $p ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $p['id'] ) ); ?>"><?php echo esc_html( $p['trip_id'] ?: mysql2date( 'j M', $p['date'] ) ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Trips', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( count( $d['trips'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $d['guests'] ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Cost / person', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $d['cost_pp'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Profit / person', 'bhela-booking' ); ?></span><span class="bha-card__value <?php echo $d['profit_pp'] < 0 ? 'is-danger' : 'is-good'; ?>"><?php echo esc_html( bhela_bm_money( $d['profit_pp'] ) ); ?></span></div>
		</div>

		<div class="bha-scroll">
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:40px">#</th>
					<th><?php esc_html_e( 'Trip Date', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Trip ID', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Trip Earnings', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Trip Cost', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Trip Profit', 'bhela-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $d['trips'] ) : ?>
				<tr><td colspan="7"><em><?php esc_html_e( 'No approved cost sheets for this month.', 'bhela-booking' ); ?></em></td></tr>
			<?php endif; ?>
			<?php foreach ( $d['trips'] as $i => $t ) : ?>
				<tr>
					<td><?php echo esc_html( $i + 1 ); ?></td>
					<td><a href="<?php echo esc_url( get_edit_post_link( $t['id'] ) ); ?>"><?php echo esc_html( mysql2date( 'j M Y', $t['date'] ) ); ?></a></td>
					<td><?php echo esc_html( $t['trip_id'] ?: '—' ); ?></td>
					<td class="bha-num"><?php echo esc_html( $t['guests'] ?: '—' ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['earnings'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['cost'] ) ); ?></td>
					<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $t['profit'] ) ); ?></strong></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="3"><?php esc_html_e( 'Sub-total', 'bhela-booking' ); ?></td>
					<td class="bha-num"><?php echo esc_html( $d['guests'] ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $d['earnings'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $d['cost'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $d['profit'] ) ); ?></td>
				</tr>
				<?php
				// One row per expense type present, so adding a type in Settings
				// grows the statement with no code change.
				foreach ( $d['expenses']['by_type'] as $slug => $amount ) :
					?>
					<tr class="bha-row--deduct">
						<td colspan="6"><?php echo esc_html( sprintf( __( 'Less: %s', 'bhela-booking' ), $types[ $slug ] ?? $slug ) ); ?></td>
						<td class="bha-num">− <?php echo esc_html( bhela_bm_money( $amount ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php
				// Payroll, on its own row. Shown whenever a sheet exists for the month
				// even if it totals zero, because "payroll: 0" and "no payroll sheet
				// yet" are different facts and the owner should be able to tell which
				// one they are looking at.
				if ( $d['salary']['sheets'] > 0 ) :
					?>
					<tr class="bha-row--deduct">
						<td colspan="6"><?php
							esc_html_e( 'Less: Staff salary', 'bhela-booking' );
							if ( $d['salary']['sheets'] > 1 ) {
								echo ' <span class="bha-flag">' . esc_html( sprintf(
									/* translators: %d: how many salary sheets exist for the month */
									__( '%d sheets for this month — check that is intended', 'bhela-booking' ),
									(int) $d['salary']['sheets']
								) ) . '</span>';
							}
						?></td>
						<td class="bha-num">− <?php echo esc_html( bhela_bm_money( $d['salary']['total'] ) ); ?></td>
					</tr>
				<?php endif; ?>
				<tr class="bha-row--total">
					<td colspan="6"><?php esc_html_e( 'Gross Profit', 'bhela-booking' ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $d['gross'] ) ); ?></td>
				</tr>
				<?php if ( 0 === $d['salary']['sheets'] && $d['trips'] ) : ?>
					<tr class="bha-row--muted">
						<td colspan="7"><span class="bha-flag"><?php esc_html_e( 'No salary sheet for this month yet, so no wages have been deducted — the figure above is before payroll.', 'bhela-booking' ); ?></span></td>
					</tr>
				<?php endif; ?>
			</tfoot>
		</table>
		</div>

		<?php if ( $d['signoff'] ) : ?>
			<div class="bha-sign">
				<?php foreach ( array( 'prepared' => __( 'Prepared by', 'bhela-booking' ), 'checked' => __( 'Checked by', 'bhela-booking' ), 'approved' => __( 'Approved by', 'bhela-booking' ) ) as $k => $lbl ) : ?>
					<div><strong><?php echo esc_html( $lbl ); ?>:</strong> <?php echo esc_html( $d['signoff'][ $k ] ?? '—' ); ?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<p class="bha-note">
			<?php esc_html_e( 'Cost per person includes the deductions above, matching the printed statement. Trip cost alone is lower.', 'bhela-booking' ); ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bhela_expense' ) ); ?>"><?php esc_html_e( 'Manage expenses', 'bhela-booking' ); ?></a>
		</p>
	</div>
	<?php
}
