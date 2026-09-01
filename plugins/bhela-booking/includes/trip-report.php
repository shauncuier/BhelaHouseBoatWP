<?php
/**
 * Trip P&L and Revenue by Source.
 *
 * Two readings of the same store, both of which only became possible once a cost
 * sheet could say where its earnings came from (see `includes/income.php`):
 *
 * - **Trip P&L** answers "how did that one night go" — revenue by source, cost by
 *   head, profit, and what the trip contributed to the month it belongs to.
 * - **Revenue by Source** answers "what does food actually earn us" over any range.
 *
 * Neither stores anything. Every figure is replayed from the cost sheets, exactly as
 * the Monthly Statement replays them, so there is no third place for the same number
 * to disagree with itself.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * DATA
 * ========================================================= */

/**
 * Cost sheets in a range, newest trip first.
 *
 * Every status, not only approved: the point of the list is to find a trip, and a
 * draft is exactly the one somebody is looking for. The figures screen says which
 * status each sheet carries, so nothing is passed off as final that is not.
 *
 * @param string $from Y-m-d.
 * @param string $to   Y-m-d.
 * @return int[] Cost sheet ids.
 */
function bhela_bm_trip_sheets( $from, $to ) {
	$from = bhela_bm_report_date( $from );
	$to   = bhela_bm_report_date( $to );
	if ( '' === $from || '' === $to || $to < $from ) {
		return array();
	}
	return get_posts( array(
		'post_type'      => 'bhela_cost',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_cost_trip_date',
		'orderby'        => 'meta_value',
		'order'          => 'DESC',
		'meta_query'     => array(
			array( 'key' => '_bhela_cost_trip_date', 'value' => array( $from, $to ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
		),
	) );
}

/**
 * One trip, end to end.
 *
 * The `share` block is an **apportionment, not a record**. A distribution is monthly:
 * nothing anywhere says "this trip sent ৳4,000 to the reserve". What can be said
 * honestly is that this trip contributed a given fraction of the month's profit, so
 * the same fraction of what that month actually distributed is attributable to it.
 * It is computed only when the month has a committed run, and the screen says in so
 * many words that it is a share of a monthly figure rather than a transaction.
 *
 * @param int $post_id Cost sheet.
 * @return array|null
 */
function bhela_bm_trip_report( $post_id ) {
	if ( 'bhela_cost' !== get_post_type( $post_id ) ) {
		return null;
	}
	$date     = (string) get_post_meta( $post_id, '_bhela_cost_trip_date', true );
	$earnings = (int) get_post_meta( $post_id, '_bhela_cost_earnings', true );
	$cost     = (int) get_post_meta( $post_id, '_bhela_cost_total', true );
	$income   = bhela_bm_cost_income( $post_id );
	$heads    = bhela_bm_income_heads( true );

	$out = array(
		'id'       => (int) $post_id,
		'title'    => get_the_title( $post_id ),
		'date'     => $date,
		'status'   => (string) get_post_meta( $post_id, '_bhela_cost_status', true ),
		'earnings' => $earnings,
		'cost'     => $cost,
		'profit'   => $earnings - $cost,
		'income'   => array(),
		'unsplit'  => $income ? 0 : $earnings,
		'costs'    => array(),
		'share'    => null,
	);

	arsort( $income );
	foreach ( $income as $slug => $amount ) {
		$out['income'][] = array(
			'slug'   => $slug,
			'label'  => $heads[ $slug ] ?? $slug,
			'amount' => (int) $amount,
			// Share of this trip's revenue, which is the number that makes a food
			// line comparable between a ৳40,000 trip and a ৳300,000 one.
			'pct'    => $earnings > 0 ? round( $amount * 100 / $earnings, 1 ) : 0.0,
		);
	}

	foreach ( bhela_bm_cost_lines( $post_id ) as $row ) {
		if ( (int) $row['sub'] <= 0 ) {
			continue;   // a blank line to type into is not a cost
		}
		$out['costs'][] = array(
			'label'  => $row['label'],
			'amount' => (int) $row['sub'],
			'remark' => (string) $row['remark'],
		);
	}
	usort( $out['costs'], function ( $a, $b ) {
		return $b['amount'] <=> $a['amount'];
	} );

	// What this trip contributed to its month's distribution.
	$month = $date ? substr( $date, 0, 7 ) : '';
	$run   = $month && function_exists( 'bhela_bm_dist_run' ) ? bhela_bm_dist_run( $month ) : null;
	if ( $run && $out['profit'] > 0 ) {
		$d = bhela_bm_dist_data( $run );
		// The denominator is the month's profit BEFORE the deductions the statement
		// makes, so this is a share of the pool rather than of the gross. Summed over
		// every approved trip in the month it comes to the whole pool, which is the
		// property that makes it worth showing at all.
		$month_profit = 0;
		foreach ( bhela_bm_trip_sheets( $month . '-01', gmdate( 'Y-m-t', strtotime( $month . '-01' ) ) ) as $sid ) {
			if ( 'approved' !== get_post_meta( $sid, '_bhela_cost_status', true ) ) {
				continue;
			}
			$month_profit += (int) get_post_meta( $sid, '_bhela_cost_earnings', true )
				- (int) get_post_meta( $sid, '_bhela_cost_total', true );
		}
		if ( $month_profit > 0 ) {
			$f = $out['profit'] / $month_profit;
			$out['share'] = array(
				'month'      => $month,
				'fraction'   => round( $f * 100, 1 ),
				'reserve'    => (int) round( (int) $d['reserve'] * $f ),
				'investor'   => (int) round( (int) $d['investor'] * $f ),
				'management' => (int) round( (int) $d['management'] * $f ),
			);
		}
	}

	return $out;
}

/**
 * Revenue by source, grouped into periods.
 *
 * @param string $from   Y-m-d.
 * @param string $to     Y-m-d.
 * @param string $period day | month | year — how to break the range up.
 * @return array{heads:array,periods:array,totals:array,grand:int,unsplit:int}
 */
function bhela_bm_revenue_by_source( $from, $to, $period = 'month' ) {
	$from = bhela_bm_report_date( $from );
	$to   = bhela_bm_report_date( $to );
	$out  = array( 'heads' => array(), 'periods' => array(), 'totals' => array(), 'grand' => 0, 'unsplit' => 0 );
	if ( '' === $from || '' === $to || $to < $from ) {
		return $out;
	}
	$heads  = bhela_bm_income_heads( true );
	$period = in_array( $period, array( 'day', 'month', 'year' ), true ) ? $period : 'month';
	$cut    = array( 'day' => 10, 'month' => 7, 'year' => 4 );

	$grid = array();
	$seen = array();
	foreach ( bhela_bm_trip_sheets( $from, $to ) as $id ) {
		// Approved only, for the reason the Monthly Statement counts approved sheets:
		// a draft is a proposal, and a revenue report that moved whenever somebody
		// opened a sheet and typed in it would not be a report.
		if ( 'approved' !== get_post_meta( $id, '_bhela_cost_status', true ) ) {
			continue;
		}
		$date = (string) get_post_meta( $id, '_bhela_cost_trip_date', true );
		$key  = substr( $date, 0, $cut[ $period ] );
		if ( '' === $key ) {
			continue;
		}
		$income = bhela_bm_cost_income( $id );
		if ( ! $income ) {
			// Earnings with no breakdown. Folding these into "Other" would invent a
			// source; dropping them would make the total disagree with the statement.
			$amount = (int) get_post_meta( $id, '_bhela_cost_earnings', true );
			$grid[ $key ]['__unsplit'] = ( $grid[ $key ]['__unsplit'] ?? 0 ) + $amount;
			$out['unsplit'] += $amount;
			$out['grand']   += $amount;
			continue;
		}
		foreach ( $income as $slug => $amount ) {
			$grid[ $key ][ $slug ] = ( $grid[ $key ][ $slug ] ?? 0 ) + (int) $amount;
			$out['totals'][ $slug ] = ( $out['totals'][ $slug ] ?? 0 ) + (int) $amount;
			$out['grand'] += (int) $amount;
			$seen[ $slug ] = true;
		}
	}

	foreach ( array_keys( $seen ) as $slug ) {
		$out['heads'][ $slug ] = $heads[ $slug ] ?? $slug;
	}
	// Biggest earner first — the column order is the answer to the question.
	uksort( $out['heads'], function ( $a, $b ) use ( $out ) {
		return ( $out['totals'][ $b ] ?? 0 ) <=> ( $out['totals'][ $a ] ?? 0 );
	} );

	krsort( $grid );
	foreach ( $grid as $key => $row ) {
		$out['periods'][] = array(
			'key'     => $key,
			'cells'   => $row,
			'unsplit' => (int) ( $row['__unsplit'] ?? 0 ),
			'total'   => array_sum( $row ),
		);
	}
	return $out;
}

/* =========================================================
 * SCREENS
 * ========================================================= */

function bhela_bm_trip_report_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'accounts' ),
		__( 'Trip P&L', 'bhela-booking' ),
		'🧮 ' . __( 'Trip P&L', 'bhela-booking' ),
		'bhela_view_statement',
		'bhela-bm-trip-pl',
		'bhela_bm_trip_pl_page'
	);
	add_submenu_page(
		bhela_bm_menu_parent( 'accounts' ),
		__( 'Revenue by Source', 'bhela-booking' ),
		'💹 ' . __( 'Revenue by Source', 'bhela-booking' ),
		'bhela_view_statement',
		'bhela-bm-revenue',
		'bhela_bm_revenue_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_trip_report_menu', 21 );

/** Default range: this season so far, not this calendar month (§13.24's lesson). */
function bhela_bm_trip_range() {
	$from = bhela_bm_report_date( $_GET['from'] ?? '' );
	$to   = bhela_bm_report_date( $_GET['to'] ?? '' );
	if ( '' === $from && '' === $to ) {
		// A blank range means every date. Starting narrow is how the B2B Report hid
		// its own subject for a release — see CLAUDE.md §13.24.
		$from = '2000-01-01';
		$to   = gmdate( 'Y-m-d', strtotime( '+2 years' ) );
	}
	if ( '' === $from ) {
		$from = '2000-01-01';
	}
	if ( '' === $to ) {
		$to = gmdate( 'Y-m-d', strtotime( '+2 years' ) );
	}
	return array( $from, $to );
}

function bhela_bm_trip_pl_page() {
	if ( ! current_user_can( 'bhela_view_statement' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this report.', 'bhela-booking' ) );
	}
	$one = isset( $_GET['sheet'] ) ? (int) $_GET['sheet'] : 0;
	list( $from, $to ) = bhela_bm_trip_range();
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'🧮',
			__( 'Trip P&L', 'bhela-booking' ),
			__( 'One trip, end to end: what it earned and from what, what it cost and on what, and what it left behind.', 'bhela-booking' )
		);
		?>
		<?php if ( $one && bhela_bm_trip_report( $one ) ) : ?>
			<?php $t = bhela_bm_trip_report( $one ); ?>
			<p>
				<a class="button" href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-trip-pl' ) ); ?>">← <?php esc_html_e( 'All trips', 'bhela-booking' ); ?></a>
				<a class="button" href="<?php echo esc_url( get_edit_post_link( $one ) ); ?>"><?php esc_html_e( 'Open the cost sheet', 'bhela-booking' ); ?></a>
			</p>
			<div class="bha-cards">
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Trip date', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $t['date'] ? mysql2date( 'j M Y', $t['date'] ) : '—' ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Revenue', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $t['earnings'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Cost', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $t['cost'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Profit', 'bhela-booking' ); ?></span><span class="bha-card__value <?php echo $t['profit'] < 0 ? 'is-danger' : 'is-good'; ?>"><?php echo esc_html( bhela_bm_money( $t['profit'] ) ); ?></span></div>
			</div>

			<div class="bha-panel">
				<h2><?php esc_html_e( 'Revenue by source', 'bhela-booking' ); ?></h2>
				<?php if ( ! $t['income'] ) : ?>
					<p class="bha-callout bha-callout--attention"><?php esc_html_e( 'This sheet carries a single earnings figure with no breakdown, so there is nothing to attribute. Fill the income lines on the cost sheet and this fills itself.', 'bhela-booking' ); ?></p>
				<?php else : ?>
					<div class="bha-scroll">
					<table class="widefat striped">
						<thead><tr>
							<th><?php esc_html_e( 'Source', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'Amount', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'Share', 'bhela-booking' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $t['income'] as $r ) : ?>
							<tr>
								<td><?php echo esc_html( $r['label'] ); ?></td>
								<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['amount'] ) ); ?></td>
								<td class="bha-num"><?php echo esc_html( $r['pct'] ); ?>%</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
						<tfoot><tr>
							<th><?php esc_html_e( 'Total', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php echo esc_html( bhela_bm_money( $t['earnings'] ) ); ?></th>
							<th class="bha-num">100%</th>
						</tr></tfoot>
					</table>
					</div>
				<?php endif; ?>
			</div>

			<div class="bha-panel">
				<h2><?php esc_html_e( 'Cost by head', 'bhela-booking' ); ?></h2>
				<div class="bha-scroll">
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Head', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Remark', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Amount', 'bhela-booking' ); ?></th>
					</tr></thead>
					<tbody>
					<?php if ( ! $t['costs'] ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'Nothing recorded.', 'bhela-booking' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $t['costs'] as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r['label'] ); ?></td>
							<td><?php echo esc_html( $r['remark'] ); ?></td>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['amount'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot><tr>
						<th colspan="2"><?php esc_html_e( 'Total cost', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php echo esc_html( bhela_bm_money( $t['cost'] ) ); ?></th>
					</tr></tfoot>
				</table>
				</div>
			</div>

			<?php if ( $t['share'] ) : ?>
				<div class="bha-panel">
					<h2><?php esc_html_e( 'What this trip contributed', 'bhela-booking' ); ?></h2>
					<div class="bha-cards">
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Reserve', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $t['share']['reserve'] ) ); ?></span></div>
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Investors', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $t['share']['investor'] ) ); ?></span></div>
						<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Management', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $t['share']['management'] ) ); ?></span></div>
					</div>
					<p class="bha-note">
						<?php
						printf(
							/* translators: 1: percentage, 2: month */
							esc_html__( 'A distribution is monthly, so nothing anywhere says this trip sent a taka to the reserve. What can be said is that it made %1$s%% of the approved profit in %2$s, so the same share of what that month actually distributed is attributable to it. It is an apportionment, not a transaction.', 'bhela-booking' ),
							esc_html( (string) $t['share']['fraction'] ),
							esc_html( mysql2date( 'F Y', $t['share']['month'] . '-01' ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<form method="get" class="bha-bar">
				<input type="hidden" name="page" value="bhela-bm-trip-pl">
				<div class="bha-field"><label for="bhela-tp-from"><?php esc_html_e( 'From', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-tp-from" name="from" value="<?php echo esc_attr( isset( $_GET['from'] ) ? bhela_bm_report_date( $_GET['from'] ) : '' ); ?>"></div>
				<div class="bha-field"><label for="bhela-tp-to"><?php esc_html_e( 'To', 'bhela-booking' ); ?></label>
					<input type="date" id="bhela-tp-to" name="to" value="<?php echo esc_attr( isset( $_GET['to'] ) ? bhela_bm_report_date( $_GET['to'] ) : '' ); ?>"></div>
				<button class="button button-primary"><?php esc_html_e( 'Filter', 'bhela-booking' ); ?></button>
				<span class="bha-note"><?php esc_html_e( 'Leave both blank for every trip.', 'bhela-booking' ); ?></span>
			</form>
			<div class="bha-panel">
				<div class="bha-scroll">
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Trip date', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Sheet', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Revenue', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Cost', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Profit', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Breakdown', 'bhela-booking' ); ?></th>
					</tr></thead>
					<tbody>
					<?php $sheets = bhela_bm_trip_sheets( $from, $to ); ?>
					<?php if ( ! $sheets ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No cost sheets in this range.', 'bhela-booking' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $sheets as $sid ) : ?>
						<?php $t = bhela_bm_trip_report( $sid ); ?>
						<tr>
							<td><?php echo esc_html( $t['date'] ? mysql2date( 'j M Y', $t['date'] ) : '—' ); ?></td>
							<td><a href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-trip-pl', array( 'sheet' => $sid ) ) ); ?>"><?php echo esc_html( $t['title'] ); ?></a></td>
							<td><?php echo bhela_bm_status_pill( $t['status'] ? $t['status'] : 'draft', bhela_bm_cost_status_tone( $t['status'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['earnings'] ) ); ?></td>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['cost'] ) ); ?></td>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $t['profit'] ) ); ?></td>
							<td>
								<?php if ( $t['income'] ) : ?>
									<?php echo bhela_bm_status_pill( sprintf( /* translators: %d: number of income sources */ _n( '%d source', '%d sources', count( $t['income'] ), 'bhela-booking' ), count( $t['income'] ) ), 'good' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php else : ?>
									<?php echo bhela_bm_status_pill( __( 'one figure', 'bhela-booking' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/** Cost-sheet status → pill tone. Its own function so the report and the list agree. */
function bhela_bm_cost_status_tone( $status ) {
	$map = array(
		'draft'    => 'neutral',
		'prepared' => 'progress',
		'checked'  => 'progress',
		'approved' => 'good',
	);
	return $map[ $status ] ?? 'neutral';
}

function bhela_bm_revenue_page() {
	if ( ! current_user_can( 'bhela_view_statement' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this report.', 'bhela-booking' ) );
	}
	list( $from, $to ) = bhela_bm_trip_range();
	$period = sanitize_key( $_GET['period'] ?? 'month' );
	$period = in_array( $period, array( 'day', 'month', 'year' ), true ) ? $period : 'month';
	$d      = bhela_bm_revenue_by_source( $from, $to, $period );
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'💹',
			__( 'Revenue by Source', 'bhela-booking' ),
			__( 'What the boat earns, and from what. Cabins are only part of it — this is the rest.', 'bhela-booking' ),
			sprintf(
				'<a class="button" href="%s">%s</a>',
				esc_url( wp_nonce_url(
					admin_url( 'admin-post.php?action=bhela_bm_revenue_csv&from=' . rawurlencode( $from ) . '&to=' . rawurlencode( $to ) . '&period=' . $period ),
					'bhela_bm_revenue_csv'
				) ),
				esc_html__( 'Download CSV', 'bhela-booking' )
			)
		);
		?>
		<form method="get" class="bha-bar">
			<input type="hidden" name="page" value="bhela-bm-revenue">
			<div class="bha-field"><label for="bhela-rv-from"><?php esc_html_e( 'From', 'bhela-booking' ); ?></label>
				<input type="date" id="bhela-rv-from" name="from" value="<?php echo esc_attr( isset( $_GET['from'] ) ? bhela_bm_report_date( $_GET['from'] ) : '' ); ?>"></div>
			<div class="bha-field"><label for="bhela-rv-to"><?php esc_html_e( 'To', 'bhela-booking' ); ?></label>
				<input type="date" id="bhela-rv-to" name="to" value="<?php echo esc_attr( isset( $_GET['to'] ) ? bhela_bm_report_date( $_GET['to'] ) : '' ); ?>"></div>
			<div class="bha-field"><label for="bhela-rv-period"><?php esc_html_e( 'Grouped by', 'bhela-booking' ); ?></label>
				<select id="bhela-rv-period" name="period">
					<option value="day" <?php selected( 'day', $period ); ?>><?php esc_html_e( 'Trip date', 'bhela-booking' ); ?></option>
					<option value="month" <?php selected( 'month', $period ); ?>><?php esc_html_e( 'Month', 'bhela-booking' ); ?></option>
					<option value="year" <?php selected( 'year', $period ); ?>><?php esc_html_e( 'Year', 'bhela-booking' ); ?></option>
				</select></div>
			<button class="button button-primary"><?php esc_html_e( 'Filter', 'bhela-booking' ); ?></button>
			<span class="bha-note"><?php esc_html_e( 'Leave the dates blank for every trip on record.', 'bhela-booking' ); ?></span>
		</form>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Total revenue', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $d['grand'] ) ); ?></span></div>
			<?php $i = 0; foreach ( $d['heads'] as $slug => $label ) : if ( $i++ >= 3 ) { break; } ?>
				<div class="bha-card">
					<span class="bha-card__label"><?php echo esc_html( $label ); ?></span>
					<span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $d['totals'][ $slug ] ?? 0 ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $d['unsplit'] > 0 ) : ?>
			<p class="bha-callout bha-callout--attention">
				<?php
				printf(
					/* translators: %s: amount */
					esc_html__( '%s of this revenue sits on sheets that carry a single earnings figure with no breakdown. It is counted in the total and shown in its own column rather than folded into a source — attributing it would be inventing where the money came from.', 'bhela-booking' ),
					esc_html( bhela_bm_money( $d['unsplit'] ) )
				);
				?>
			</p>
		<?php endif; ?>

		<div class="bha-panel">
			<div class="bha-scroll">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Period', 'bhela-booking' ); ?></th>
					<?php foreach ( $d['heads'] as $label ) : ?>
						<th class="bha-num"><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
					<?php if ( $d['unsplit'] > 0 ) : ?>
						<th class="bha-num"><?php esc_html_e( 'Not broken down', 'bhela-booking' ); ?></th>
					<?php endif; ?>
					<th class="bha-num"><?php esc_html_e( 'Total', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $d['periods'] ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No approved cost sheets in this range.', 'bhela-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $d['periods'] as $p ) : ?>
					<tr>
						<td><?php echo esc_html( $p['key'] ); ?></td>
						<?php foreach ( array_keys( $d['heads'] ) as $slug ) : ?>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( (int) ( $p['cells'][ $slug ] ?? 0 ) ) ); ?></td>
						<?php endforeach; ?>
						<?php if ( $d['unsplit'] > 0 ) : ?>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $p['unsplit'] ) ); ?></td>
						<?php endif; ?>
						<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $p['total'] ) ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot><tr>
					<th><?php esc_html_e( 'Total', 'bhela-booking' ); ?></th>
					<?php foreach ( array_keys( $d['heads'] ) as $slug ) : ?>
						<th class="bha-num"><?php echo esc_html( bhela_bm_money( (int) ( $d['totals'][ $slug ] ?? 0 ) ) ); ?></th>
					<?php endforeach; ?>
					<?php if ( $d['unsplit'] > 0 ) : ?>
						<th class="bha-num"><?php echo esc_html( bhela_bm_money( $d['unsplit'] ) ); ?></th>
					<?php endif; ?>
					<th class="bha-num"><?php echo esc_html( bhela_bm_money( $d['grand'] ) ); ?></th>
				</tr></tfoot>
			</table>
			</div>
		</div>
	</div>
	<?php
}

/** The same table as a file. Every label goes through bhela_bm_csv_cell(); no figure does. */
function bhela_bm_revenue_csv() {
	if ( ! current_user_can( 'bhela_view_statement' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this report.', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_revenue_csv' );

	list( $from, $to ) = bhela_bm_trip_range();
	$period = sanitize_key( $_GET['period'] ?? 'month' );
	$d      = bhela_bm_revenue_by_source( $from, $to, $period );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=bhela-revenue-' . $from . '-to-' . $to . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );   // so Excel opens the Bengali labels correctly

	$head = array( bhela_bm_csv_cell( __( 'Period', 'bhela-booking' ) ) );
	foreach ( $d['heads'] as $label ) {
		$head[] = bhela_bm_csv_cell( $label );
	}
	$head[] = bhela_bm_csv_cell( __( 'Not broken down', 'bhela-booking' ) );
	$head[] = bhela_bm_csv_cell( __( 'Total', 'bhela-booking' ) );
	fputcsv( $out, $head );

	foreach ( $d['periods'] as $p ) {
		$row = array( bhela_bm_csv_cell( $p['key'] ) );
		foreach ( array_keys( $d['heads'] ) as $slug ) {
			$row[] = (int) ( $p['cells'][ $slug ] ?? 0 );
		}
		$row[] = $p['unsplit'];
		$row[] = $p['total'];
		fputcsv( $out, $row );
	}

	$row = array( bhela_bm_csv_cell( __( 'Total', 'bhela-booking' ) ) );
	foreach ( array_keys( $d['heads'] ) as $slug ) {
		$row[] = (int) ( $d['totals'][ $slug ] ?? 0 );
	}
	$row[] = $d['unsplit'];
	$row[] = $d['grand'];
	fputcsv( $out, $row );

	fclose( $out );
	exit;
}
add_action( 'admin_post_bhela_bm_revenue_csv', 'bhela_bm_revenue_csv' );
