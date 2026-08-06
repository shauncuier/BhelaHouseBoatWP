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
		'edit.php?post_type=bhela_booking',
		__( 'Monthly Statement', 'bhela-booking' ),
		__( '📊 Monthly Statement', 'bhela-booking' ),
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
		'gross' => 0, 'cost_pp' => 0.0, 'profit_pp' => 0.0,
		'signoff' => array(),
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

	$out['gross'] = $out['profit'] - $out['expenses']['total'];
	if ( $out['guests'] > 0 ) {
		// Cost per person on the owner's sheet includes marketing and
		// renovation, not just trip cost — the two readings differ by about a
		// thousand taka a head, so this follows the sheet.
		$out['cost_pp']   = round( ( $out['cost'] + $out['expenses']['total'] ) / $out['guests'], 2 );
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
	<style>
		.bhela-st { max-width: 1240px; }
		.bhela-st__bar { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 14px 16px; margin: 12px 0 18px; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
		.bhela-st__f { display: flex; flex-direction: column; gap: 5px; }
		.bhela-st__f label { font-size: 11px; font-weight: 600; color: #50575e; text-transform: uppercase; letter-spacing: .04em; }
		.bhela-st__sum { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
		.bhela-st__sum > div { flex: 1; min-width: 140px; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 12px 14px; }
		.bhela-st__sum span { display: block; color: #50575e; font-size: 12px; }
		.bhela-st__sum b { font-size: 21px; }
		.bhela-st table.widefat th, .bhela-st table.widefat td { padding: 9px 12px; vertical-align: middle; }
		.bhela-st .n { text-align: right; white-space: nowrap; }
		.bhela-st tfoot td { background: #f6f7f7; font-weight: 700; border-top: 2px solid #c3c4c7; }
		.bhela-st__ded td { background: #FFFBEB; }
		.bhela-st__gross td { background: #EDFAEF; font-size: 15px; }
		.bhela-st__warn { background: #FFFBEB; border-left: 3px solid #b45309; padding: 10px 12px; margin: 0 0 16px; font-size: 13px; }
		.bhela-st__sign { display: flex; gap: 26px; margin-top: 26px; flex-wrap: wrap; }
		.bhela-st__sign div { flex: 1; min-width: 180px; border-top: 1px solid #333; padding-top: 6px; font-size: 12px; }
		.bhela-st__print { display: none; }
		@media print {
			#adminmenumain, #wpadminbar, #wpfooter, .bhela-st__bar, .bhela-st__actions,
			.notice, #screen-meta, #screen-meta-links { display: none !important; }
			#wpcontent, #wpbody-content { margin: 0 !important; padding: 0 !important; }
			html.wp-toolbar { padding-top: 0 !important; }
			.bhela-st { max-width: none; }
			.bhela-st__print { display: block; margin-bottom: 14px; }
			.bhela-st td, .bhela-st th { border: 1px solid #999 !important; font-size: 11px; }
			a { text-decoration: none !important; color: #000 !important; }
		}
	</style>

	<div class="wrap bhela-st">
		<h1>📊 <?php esc_html_e( 'Monthly Statement', 'bhela-booking' ); ?></h1>
		<p style="color:#50575e;margin:4px 0 0"><?php esc_html_e( 'Approved trip cost sheets for the month, less the month\'s expenses.', 'bhela-booking' ); ?></p>

		<div class="bhela-st__bar">
			<form method="get" style="display:flex;gap:12px;align-items:flex-end">
				<input type="hidden" name="post_type" value="bhela_booking">
				<input type="hidden" name="page" value="bhela-bm-statement">
				<div class="bhela-st__f">
					<label for="st-month"><?php esc_html_e( 'Month', 'bhela-booking' ); ?></label>
					<input type="month" id="st-month" name="month" value="<?php echo esc_attr( $month ); ?>">
				</div>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Show', 'bhela-booking' ); ?></button>
			</form>
			<div class="bhela-st__actions">
				<button type="button" class="button" onclick="window.print()">🖨️ <?php esc_html_e( 'Print / PDF', 'bhela-booking' ); ?></button>
			</div>
		</div>

		<div class="bhela-st__print">
			<h2><?php echo esc_html( $s['business_name'] ); ?> — <?php esc_html_e( 'Monthly Statement', 'bhela-booking' ); ?></h2>
			<div><?php echo esc_html( $label ); ?></div>
		</div>

		<?php if ( $d['pending'] ) : ?>
			<p class="bhela-st__warn">
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

		<div class="bhela-st__sum">
			<div><span><?php esc_html_e( 'Trips', 'bhela-booking' ); ?></span><b><?php echo esc_html( count( $d['trips'] ) ); ?></b></div>
			<div><span><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></span><b><?php echo esc_html( $d['guests'] ); ?></b></div>
			<div><span><?php esc_html_e( 'Cost / person', 'bhela-booking' ); ?></span><b><?php echo esc_html( bhela_bm_money( $d['cost_pp'] ) ); ?></b></div>
			<div><span><?php esc_html_e( 'Profit / person', 'bhela-booking' ); ?></span><b style="color:<?php echo $d['profit_pp'] < 0 ? '#b32d2e' : '#1a7f37'; ?>"><?php echo esc_html( bhela_bm_money( $d['profit_pp'] ) ); ?></b></div>
		</div>

		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:40px">#</th>
					<th><?php esc_html_e( 'Trip Date', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Trip ID', 'bhela-booking' ); ?></th>
					<th class="n"><?php esc_html_e( 'Guests', 'bhela-booking' ); ?></th>
					<th class="n"><?php esc_html_e( 'Trip Earnings', 'bhela-booking' ); ?></th>
					<th class="n"><?php esc_html_e( 'Trip Cost', 'bhela-booking' ); ?></th>
					<th class="n"><?php esc_html_e( 'Trip Profit', 'bhela-booking' ); ?></th>
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
					<td class="n"><?php echo esc_html( $t['guests'] ?: '—' ); ?></td>
					<td class="n"><?php echo esc_html( bhela_bm_money( $t['earnings'] ) ); ?></td>
					<td class="n"><?php echo esc_html( bhela_bm_money( $t['cost'] ) ); ?></td>
					<td class="n"><strong><?php echo esc_html( bhela_bm_money( $t['profit'] ) ); ?></strong></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="3"><?php esc_html_e( 'Sub-total', 'bhela-booking' ); ?></td>
					<td class="n"><?php echo esc_html( $d['guests'] ); ?></td>
					<td class="n"><?php echo esc_html( bhela_bm_money( $d['earnings'] ) ); ?></td>
					<td class="n"><?php echo esc_html( bhela_bm_money( $d['cost'] ) ); ?></td>
					<td class="n"><?php echo esc_html( bhela_bm_money( $d['profit'] ) ); ?></td>
				</tr>
				<?php
				// One row per expense type present, so adding a type in Settings
				// grows the statement with no code change.
				foreach ( $d['expenses']['by_type'] as $slug => $amount ) :
					?>
					<tr class="bhela-st__ded">
						<td colspan="6"><?php echo esc_html( sprintf( __( 'Less: %s', 'bhela-booking' ), $types[ $slug ] ?? $slug ) ); ?></td>
						<td class="n">− <?php echo esc_html( bhela_bm_money( $amount ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				<tr class="bhela-st__gross">
					<td colspan="6"><?php esc_html_e( 'Gross Profit', 'bhela-booking' ); ?></td>
					<td class="n"><?php echo esc_html( bhela_bm_money( $d['gross'] ) ); ?></td>
				</tr>
			</tfoot>
		</table>

		<?php if ( $d['signoff'] ) : ?>
			<div class="bhela-st__sign">
				<?php foreach ( array( 'prepared' => __( 'Prepared by', 'bhela-booking' ), 'checked' => __( 'Checked by', 'bhela-booking' ), 'approved' => __( 'Approved by', 'bhela-booking' ) ) as $k => $lbl ) : ?>
					<div><strong><?php echo esc_html( $lbl ); ?>:</strong> <?php echo esc_html( $d['signoff'][ $k ] ?? '—' ); ?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<p style="color:#787c82;font-size:12px;margin-top:16px">
			<?php esc_html_e( 'Cost per person includes the deductions above, matching the printed statement. Trip cost alone is lower.', 'bhela-booking' ); ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bhela_expense' ) ); ?>"><?php esc_html_e( 'Manage expenses', 'bhela-booking' ); ?></a>
		</p>
	</div>
	<?php
}
