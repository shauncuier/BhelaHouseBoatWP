<?php
/**
 * Yearly Report — twelve monthly statements on one page, plus the year's totals.
 *
 * The layer above the Monthly Statement. It answers the questions a month
 * cannot: which months carried the year, what the season actually looked like,
 * and where the money went across twelve months rather than one.
 *
 * Every figure comes from bhela_bm_statement_data(), called once per month,
 * rather than from a second set of queries. That is deliberate and worth the
 * twelve round trips: a yearly total computed independently would eventually
 * disagree with the months it summarises, and there would be no way to tell
 * which one was right.
 *
 * Read-only. Nothing here writes, and no new meta key is introduced.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Menu ---------- */

function bhela_bm_yearly_menu() {
	add_submenu_page(
		'edit.php?post_type=bhela_booking',
		__( 'Yearly Report', 'bhela-booking' ),
		__( '📚 Yearly Report', 'bhela-booking' ),
		'bhela_view_statement',
		'bhela-bm-yearly',
		'bhela_bm_yearly_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_yearly_menu' );

/* ---------- Year shapes ---------- */

/**
 * The two ways this business counts a year.
 *
 * Bangladesh's financial year runs July to June, and BHELA's own records start
 * in July — so the financial year is the default here. The calendar year stays
 * available because that is what a guest, a bank statement or a tax form from
 * elsewhere will assume.
 *
 * @return array mode => array{ label, start_month }
 */
function bhela_bm_year_modes() {
	return array(
		'financial' => array(
			'label'       => __( 'Financial year (July – June)', 'bhela-booking' ),
			'start_month' => 7,
		),
		'calendar'  => array(
			'label'       => __( 'Calendar year (January – December)', 'bhela-booking' ),
			'start_month' => 1,
		),
	);
}

/** A four-digit year, or '' if it is not one. */
function bhela_bm_yearly_year( $value ) {
	$value = is_string( $value ) || is_int( $value ) ? trim( (string) $value ) : '';
	return preg_match( '/^\d{4}$/', $value ) ? $value : '';
}

/**
 * The twelve YYYY-MM keys a year covers, in order.
 *
 * For the financial year, $year names the year it *starts* in: 2026 means
 * July 2026 through June 2027.
 *
 * @param string $year Four-digit year.
 * @param string $mode financial | calendar.
 * @return string[]
 */
function bhela_bm_yearly_months( $year, $mode = 'financial' ) {
	$modes = bhela_bm_year_modes();
	$start = $modes[ $mode ]['start_month'] ?? 7;
	$out   = array();
	for ( $i = 0; $i < 12; $i++ ) {
		$m     = $start + $i;
		$y     = (int) $year + intdiv( $m - 1, 12 );
		$out[] = sprintf( '%04d-%02d', $y, ( ( $m - 1 ) % 12 ) + 1 );
	}
	return $out;
}

/** How the year is labelled: "2026–27" for a financial year, "2026" for calendar. */
function bhela_bm_yearly_label( $year, $mode = 'financial' ) {
	return 'financial' === $mode
		? sprintf( '%d–%02d', (int) $year, ( (int) $year + 1 ) % 100 )
		: (string) (int) $year;
}

/* ---------- Data ---------- */

/**
 * A year of monthly statements, with the totals across them.
 *
 * @param string $year Four-digit year.
 * @param string $mode financial | calendar.
 * @return array{months:array,totals:array,by_type:array,pending:int,best:?array,worst:?array}
 */
function bhela_bm_yearly_data( $year, $mode = 'financial' ) {
	$totals = array(
		'trips' => 0, 'guests' => 0, 'earnings' => 0,
		'cost' => 0, 'profit' => 0, 'expenses' => 0, 'salary' => 0, 'gross' => 0,
	);
	$out = array(
		'months' => array(), 'totals' => $totals, 'by_type' => array(),
		'pending' => 0, 'stale' => 0, 'best' => null, 'worst' => null,
	);
	if ( ! $year || ! function_exists( 'bhela_bm_statement_data' ) ) {
		return $out;
	}

	foreach ( bhela_bm_yearly_months( $year, $mode ) as $key ) {
		$d   = bhela_bm_statement_data( $key );
		$row = array(
			'key'      => $key,
			'label'    => mysql2date( 'M Y', $key . '-01' ),
			'trips'    => count( $d['trips'] ),
			'guests'   => (int) $d['guests'],
			'earnings' => (int) $d['earnings'],
			'cost'     => (int) $d['cost'],
			'profit'   => (int) $d['profit'],
			'expenses' => (int) $d['expenses']['total'],
			// Payroll is a cost of the month like any other, so it rolls up too.
			'salary'   => (int) $d['salary']['total'],
			'gross'    => (int) $d['gross'],
			'pending'  => count( $d['pending'] ),
		);
		$out['months'][] = $row;

		$out['totals']['trips']    += $row['trips'];
		$out['totals']['guests']   += $row['guests'];
		$out['totals']['earnings'] += $row['earnings'];
		$out['totals']['cost']     += $row['cost'];
		$out['totals']['profit']   += $row['profit'];
		$out['totals']['expenses'] += $row['expenses'];
		$out['totals']['salary']   += $row['salary'];
		$out['totals']['gross']    += $row['gross'];
		$out['pending']            += $row['pending'];
		$out['stale']              += count( $d['stale'] );

		// Expense mix for the whole year, so a single heavy month does not
		// have to be opened to see what it was spent on.
		foreach ( $d['expenses']['by_type'] as $slug => $amount ) {
			$out['by_type'][ $slug ] = ( $out['by_type'][ $slug ] ?? 0 ) + (int) $amount;
		}

		// Best and worst are judged on gross profit, and only among months
		// that actually sailed — an empty month is not a bad month, and
		// ranking it worst would say nothing useful about the season.
		if ( $row['trips'] > 0 ) {
			if ( null === $out['best'] || $row['gross'] > $out['best']['gross'] ) {
				$out['best'] = $row;
			}
			if ( null === $out['worst'] || $row['gross'] < $out['worst']['gross'] ) {
				$out['worst'] = $row;
			}
		}
	}

	arsort( $out['by_type'] );

	$g = $out['totals']['guests'];
	$out['totals']['cost_pp']   = $g > 0 ? round( ( $out['totals']['cost'] + $out['totals']['expenses'] + $out['totals']['salary'] ) / $g, 2 ) : 0.0;
	$out['totals']['profit_pp'] = $g > 0 ? round( $out['totals']['gross'] / $g, 2 ) : 0.0;
	// Margin on what was invoiced, which is the figure an owner compares
	// against last year rather than the absolute taka.
	$out['totals']['margin'] = $out['totals']['earnings'] > 0
		? round( $out['totals']['gross'] / $out['totals']['earnings'] * 100, 1 )
		: 0.0;

	return $out;
}

/**
 * Years worth offering in the picker.
 *
 * Derived from the cost sheets that exist, so the list never offers an empty
 * year or hides a real one. Always includes the current year.
 *
 * @param string $mode financial | calendar.
 * @return int[] Newest first.
 */
function bhela_bm_yearly_available( $mode = 'financial' ) {
	$dates = get_posts( array(
		'post_type'      => 'bhela_cost',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	$start = bhela_bm_year_modes()[ $mode ]['start_month'] ?? 7;
	$years = array();
	foreach ( $dates as $id ) {
		$d = (string) get_post_meta( $id, '_bhela_cost_trip_date', true );
		if ( ! preg_match( '/^(\d{4})-(\d{2})/', $d, $m ) ) {
			continue;
		}
		// A month before the year's start belongs to the year that started
		// the previous January/July.
		$years[] = (int) $m[2] >= $start ? (int) $m[1] : (int) $m[1] - 1;
	}
	$years[] = bhela_bm_yearly_current( $mode );
	$years   = array_values( array_unique( $years ) );
	rsort( $years );
	return $years;
}

/** The year we are in right now, under the given mode. */
function bhela_bm_yearly_current( $mode = 'financial' ) {
	$start = bhela_bm_year_modes()[ $mode ]['start_month'] ?? 7;
	$y     = (int) current_time( 'Y' );
	$m     = (int) current_time( 'n' );
	return $m >= $start ? $y : $y - 1;
}

/* ---------- CSV ---------- */

/** Stream the year as CSV, one row per month plus a total. */
function bhela_bm_yearly_csv() {
	if ( ! current_user_can( 'bhela_view_statement' ) ) {
		wp_die( esc_html__( 'You are not allowed to export the yearly report.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_yearly_csv' );

	$mode = isset( $_GET['mode'] ) && array_key_exists( sanitize_key( $_GET['mode'] ), bhela_bm_year_modes() )
		? sanitize_key( $_GET['mode'] )
		: 'financial';
	$year = bhela_bm_yearly_year( $_GET['year'] ?? '' ) ?: bhela_bm_yearly_current( $mode );
	$d    = bhela_bm_yearly_data( $year, $mode );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bhela-yearly-' . bhela_bm_yearly_label( $year, $mode ) . '.csv"' );

	$fh = fopen( 'php://output', 'w' );
	// Excel reads a UTF-8 CSV as the local codepage without a BOM, which turns
	// every Bengali expense label into mojibake.
	fwrite( $fh, "\xEF\xBB\xBF" );
	fputcsv( $fh, array( 'Month', 'Trips', 'Guests', 'Earnings', 'Trip Cost', 'Trip Profit', 'Expenses', 'Salary', 'Gross Profit' ) );
	foreach ( $d['months'] as $m ) {
		fputcsv( $fh, array(
			$m['label'], $m['trips'], $m['guests'], $m['earnings'],
			$m['cost'], $m['profit'], $m['expenses'], $m['salary'], $m['gross'],
		) );
	}
	$t = $d['totals'];
	fputcsv( $fh, array() );
	fputcsv( $fh, array( 'TOTAL', $t['trips'], $t['guests'], $t['earnings'], $t['cost'], $t['profit'], $t['expenses'], $t['salary'], $t['gross'] ) );

	if ( $d['by_type'] ) {
		$types = function_exists( 'bhela_bm_expense_types' ) ? bhela_bm_expense_types( true ) : array();
		fputcsv( $fh, array() );
		fputcsv( $fh, array( 'Expenses by type' ) );
		foreach ( $d['by_type'] as $slug => $amount ) {
			// The type label is owner-entered free text, so it goes through the
			// spreadsheet-formula guard. The amount does not — it must stay a number.
			fputcsv( $fh, array( bhela_bm_csv_cell( $types[ $slug ] ?? $slug ), $amount ) );
		}
	}
	fclose( $fh );
	exit;
}
add_action( 'admin_post_bhela_bm_yearly_csv', 'bhela_bm_yearly_csv' );

/* ---------- Page ---------- */

function bhela_bm_yearly_page() {
	if ( ! current_user_can( 'bhela_view_statement' ) ) {
		return;
	}

	$modes = bhela_bm_year_modes();
	$mode  = isset( $_GET['mode'] ) && array_key_exists( sanitize_key( $_GET['mode'] ), $modes )
		? sanitize_key( $_GET['mode'] )
		: 'financial';
	$year  = bhela_bm_yearly_year( $_GET['year'] ?? '' ) ?: bhela_bm_yearly_current( $mode );

	$d       = bhela_bm_yearly_data( $year, $mode );
	$t       = $d['totals'];
	$label   = bhela_bm_yearly_label( $year, $mode );
	$types   = function_exists( 'bhela_bm_expense_types' ) ? bhela_bm_expense_types( true ) : array();
	$s       = bhela_bm_get_settings();
	$years   = bhela_bm_yearly_available( $mode );
	// Scale on the largest magnitude, not the largest profit. Measuring only
	// the positive side drew a losing month as an empty bar — September could
	// be ৳215,000 down and look exactly like a month with no trips at all.
	$peak    = max( 1, max( array_map( fn( $m ) => abs( $m['gross'] ), $d['months'] ) ?: array( 1 ) ) );

	$csv_url = wp_nonce_url(
		add_query_arg(
			array( 'action' => 'bhela_bm_yearly_csv', 'year' => $year, 'mode' => $mode ),
			admin_url( 'admin-post.php' )
		),
		'bhela_bm_yearly_csv'
	);

	$actions = '<button type="button" class="button" onclick="window.print()">🖨️ ' . esc_html__( 'Print / PDF', 'bhela-booking' ) . '</button>'
		. ' <a class="button" href="' . esc_url( $csv_url ) . '">📊 ' . esc_html__( 'Download CSV', 'bhela-booking' ) . '</a>';
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'📚',
			__( 'Yearly Report', 'bhela-booking' ),
			__( 'Twelve months of approved trips and expenses, side by side.', 'bhela-booking' ),
			$actions
		);
		?>

		<div class="bha-bar">
			<form method="get">
				<input type="hidden" name="post_type" value="bhela_booking">
				<input type="hidden" name="page" value="bhela-bm-yearly">
				<div class="bha-field bha-field--caps">
					<label for="yr-mode"><?php esc_html_e( 'Year runs', 'bhela-booking' ); ?></label>
					<select id="yr-mode" name="mode">
						<?php foreach ( $modes as $key => $def ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $mode, $key ); ?>><?php echo esc_html( $def['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="bha-field bha-field--caps">
					<label for="yr-year"><?php esc_html_e( 'Year', 'bhela-booking' ); ?></label>
					<select id="yr-year" name="year">
						<?php foreach ( $years as $y ) : ?>
							<option value="<?php echo esc_attr( $y ); ?>" <?php selected( (int) $year, $y ); ?>><?php echo esc_html( bhela_bm_yearly_label( $y, $mode ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Show', 'bhela-booking' ); ?></button>
			</form>
		</div>

		<div class="bha-printonly">
			<h2><?php echo esc_html( $s['business_name'] ); ?> — <?php esc_html_e( 'Yearly Report', 'bhela-booking' ); ?></h2>
			<div><?php echo esc_html( $modes[ $mode ]['label'] . ' ' . $label ); ?></div>
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


		<?php if ( $d['stale'] ) : ?>
			<p class="bha-callout bha-callout--attention bha-callout--lead">
				<strong>⚠️ <?php echo esc_html( sprintf(
					/* translators: %d: number of approved sheets whose bookings changed */
					_n( '%d approved sheet no longer matches its bookings.', '%d approved sheets no longer match their bookings.', $d['stale'], 'bhela-booking' ),
					$d['stale']
				) ); ?></strong>
				<?php esc_html_e( 'Bookings changed after those sheets were signed off, so the earnings below are the signed figures rather than the current ones. Open the month to see which.', 'bhela-booking' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $d['pending'] ) : ?>
			<p class="bha-callout bha-callout--attention bha-callout--lead">
				<strong>⚠️ <?php echo esc_html( sprintf(
					/* translators: %d: number of unapproved cost sheets */
					_n( '%d cost sheet in this year is not approved yet.', '%d cost sheets in this year are not approved yet.', $d['pending'], 'bhela-booking' ),
					$d['pending']
				) ); ?></strong>
				<?php esc_html_e( 'Those trips are left out of every figure below, so the year reads lower than it will once they are signed off.', 'bhela-booking' ); ?>
			</p>
		<?php endif; ?>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Trips', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $t['trips'] ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $t['guests'] ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Earnings', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $t['earnings'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Gross profit', 'bhela-booking' ); ?></span><span class="bha-card__value <?php echo $t['gross'] < 0 ? 'is-danger' : 'is-good'; ?>"><?php echo esc_html( bhela_bm_money( $t['gross'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Margin', 'bhela-booking' ); ?></span><span class="bha-card__value <?php echo $t['margin'] < 0 ? 'is-danger' : ''; ?>"><?php echo esc_html( $t['margin'] ); ?>%</span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Profit / guest', 'bhela-booking' ); ?></span><span class="bha-card__value <?php echo $t['profit_pp'] < 0 ? 'is-danger' : ''; ?>"><?php echo esc_html( bhela_bm_money( $t['profit_pp'] ) ); ?></span></div>
		</div>

		<div class="bha-scroll">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Month', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Trips', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Earnings', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Trip Cost', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Trip Profit', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Expenses', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Salary', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Gross Profit', 'bhela-booking' ); ?></th>
					<th class="bha-noprint" style="width:110px"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $d['months'] as $m ) :
				$empty = 0 === $m['trips'];
				?>
				<tr class="<?php echo $empty ? 'bha-row--muted' : ''; ?>">
					<td>
						<strong><?php echo esc_html( $m['label'] ); ?></strong>
						<?php if ( $m['pending'] ) : ?>
							<span class="bha-sub bha-flag"><?php echo esc_html( sprintf(
								/* translators: %d: unapproved sheets in this month */
								_n( '%d sheet not approved', '%d sheets not approved', $m['pending'], 'bhela-booking' ),
								$m['pending']
							) ); ?></span>
						<?php endif; ?>
					</td>
					<td class="bha-num"><?php echo esc_html( $m['trips'] ?: '—' ); ?></td>
					<td class="bha-num"><?php echo esc_html( $m['guests'] ?: '—' ); ?></td>
					<td class="bha-num"><?php echo $empty ? '—' : esc_html( bhela_bm_money( $m['earnings'] ) ); ?></td>
					<td class="bha-num"><?php echo $empty ? '—' : esc_html( bhela_bm_money( $m['cost'] ) ); ?></td>
					<td class="bha-num"><?php echo $empty ? '—' : esc_html( bhela_bm_money( $m['profit'] ) ); ?></td>
					<td class="bha-num"><?php echo $m['expenses'] ? esc_html( bhela_bm_money( $m['expenses'] ) ) : '—'; ?></td>
					<td class="bha-num"><?php echo $m['salary'] ? esc_html( bhela_bm_money( $m['salary'] ) ) : '—'; ?></td>
					<td class="bha-num <?php echo $empty ? '' : ( $m['gross'] < 0 ? 'bha-num--due' : 'bha-num--clear' ); ?>">
						<?php echo $empty && ! $m['expenses'] && ! $m['salary'] ? '—' : esc_html( bhela_bm_money( $m['gross'] ) ); ?>
					</td>
					<td class="bha-noprint">
						<?php if ( ! $empty || $m['expenses'] || $m['salary'] ) : ?>
							<a class="button button-small" href="<?php echo esc_url( add_query_arg(
								array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-statement', 'month' => $m['key'] ),
								admin_url( 'edit.php' )
							) ); ?>"><?php esc_html_e( 'Statement', 'bhela-booking' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr class="bha-row--total">
					<td><?php esc_html_e( 'YEAR TOTAL', 'bhela-booking' ); ?></td>
					<td class="bha-num"><?php echo esc_html( $t['trips'] ); ?></td>
					<td class="bha-num"><?php echo esc_html( $t['guests'] ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['earnings'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['cost'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['profit'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['expenses'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['salary'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['gross'] ) ); ?></td>
					<td class="bha-noprint"></td>
				</tr>
			</tfoot>
		</table>
		</div>

		<div class="bha-cols" style="margin-top:20px">
			<div class="bha-panel">
				<h2 class="bha-panel__title"><?php esc_html_e( 'Gross profit by month', 'bhela-booking' ); ?></h2>
				<?php if ( $t['trips'] ) : ?>
					<table>
						<?php foreach ( $d['months'] as $m ) :
							// Bar length is a share of the biggest month either way, so the
							// shape of the season is readable without reading a figure. A
							// loss runs red; a month that never sailed draws nothing.
							$pct  = (int) round( abs( $m['gross'] ) / $peak * 100 );
							$loss = $m['gross'] < 0;
							$idle = 0 === $m['trips'] && 0 === $m['expenses'];
							?>
							<tr>
								<td style="width:74px"><?php echo esc_html( mysql2date( 'M', $m['key'] . '-01' ) ); ?></td>
								<td>
									<?php if ( ! $idle ) : ?>
										<span class="bha-meter<?php echo $loss ? " bha-meter--loss" : ""; ?>"
											style="width:<?php echo esc_attr( max( 1, $pct ) ); ?>%" aria-hidden="true"></span>
									<?php endif; ?>
								</td>
								<td class="bha-num<?php echo $loss ? ' bha-num--due' : ''; ?>" style="width:110px"><?php echo $idle ? '—' : esc_html( bhela_bm_money( $m['gross'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php if ( $d['best'] && $d['worst'] && $d['best']['key'] !== $d['worst']['key'] ) : ?>
						<p class="bha-note">
							<?php printf(
								/* translators: 1: best month, 2: its profit, 3: weakest month, 4: its profit */
								esc_html__( 'Strongest %1$s at %2$s · weakest %3$s at %4$s. Months with no trips are left out.', 'bhela-booking' ),
								esc_html( $d['best']['label'] ),
								esc_html( bhela_bm_money( $d['best']['gross'] ) ),
								esc_html( $d['worst']['label'] ),
								esc_html( bhela_bm_money( $d['worst']['gross'] ) )
							); ?>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<p><em><?php esc_html_e( 'No approved cost sheets in this year yet.', 'bhela-booking' ); ?></em></p>
				<?php endif; ?>
			</div>

			<div class="bha-panel">
				<h2 class="bha-panel__title"><?php esc_html_e( 'Where the money went', 'bhela-booking' ); ?></h2>
				<?php if ( $d['by_type'] ) : ?>
					<table>
						<tr>
							<td><?php esc_html_e( 'Trip cost', 'bhela-booking' ); ?></td>
							<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $t['cost'] ) ); ?></strong></td>
						</tr>
						<?php foreach ( $d['by_type'] as $slug => $amount ) : ?>
							<tr>
								<td><?php echo esc_html( $types[ $slug ] ?? $slug ); ?></td>
								<td class="bha-num"><?php echo esc_html( bhela_bm_money( $amount ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<td><strong><?php esc_html_e( 'Total spent', 'bhela-booking' ); ?></strong></td>
							<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $t['cost'] + $t['expenses'] ) ); ?></strong></td>
						</tr>
					</table>
					<p class="bha-note">
						<?php printf(
							/* translators: %s: cost per guest */
							esc_html__( 'That is %s per guest across the year, trip cost and expenses together.', 'bhela-booking' ),
							esc_html( bhela_bm_money( $t['cost_pp'] ) )
						); ?>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bhela_expense' ) ); ?>"><?php esc_html_e( 'Manage expenses', 'bhela-booking' ); ?></a>
					</p>
				<?php else : ?>
					<p><em><?php esc_html_e( 'No expenses recorded in this year.', 'bhela-booking' ); ?></em></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}
