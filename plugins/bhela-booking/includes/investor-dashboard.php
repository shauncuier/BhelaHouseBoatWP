<?php
/**
 * The investor dashboard — one screen instead of five.
 *
 * Everything here already existed; it was spread across the register, the Distribution
 * screen, the Investor Report, Funds and Cash Flow. Answering "where do we stand with
 * the investors" meant opening all five and holding the figures in your head, which is
 * how two of them end up quietly disagreeing without anyone noticing.
 *
 * Nothing on this screen is stored. Every figure is replayed from the same functions
 * the individual screens call, so it cannot drift from them: if a number here is wrong
 * it is wrong on the screen it came from too, which is the only kind of wrong worth
 * having.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_investor_dash_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Investor Dashboard', 'bhela-booking' ),
		'🧭 ' . __( 'Dashboard', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-investor-dash',
		'bhela_bm_investor_dash_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_investor_dash_menu', 21 );

/**
 * Everything the dashboard draws.
 *
 * Split from the render so it is assertable in a harness — the figures are the point
 * of this screen, and a screen whose figures are only checkable by looking at it is a
 * screen nobody checks.
 *
 * @return array
 */
function bhela_bm_investor_dash_data() {
	$out = array(
		'shares'      => bhela_bm_share_totals(),
		'investment'  => 0,
		'declared'    => 0,
		'received'    => 0,
		'outstanding' => 0,
		'rows'        => array(),
		'pending'     => array( 'count' => 0, 'total' => 0 ),
		'funds'       => array(),
		'last_run'    => null,
		// Capital value, kept in its own block rather than mixed into the figures
		// above. What an investor has been PAID and what their holding is now WORTH
		// are different kinds of money, and a screen that adds them tells somebody
		// they have received cash that is still in the boat.
		'capital'     => array(),
	);

	// One valuation read for the whole screen, not one per investor.
	$valuation = bhela_bm_valuation_current();

	foreach ( bhela_bm_investors() as $id ) {
		$r = bhela_bm_investor_roi( $id );
		$h = bhela_bm_investor_holding( $id, $valuation );
		$out['investment']  += $r['investment'];
		$out['declared']    += $r['declared'];
		$out['received']    += $r['received'];
		$out['outstanding'] += $r['outstanding'];
		$out['rows'][] = array(
			'investor'    => (int) $id,
			'name'        => get_the_title( $id ),
			'status'      => bhela_bm_investor_status( $id ),
			'shares'      => bhela_bm_investor_shares( $id ),
			'pct'         => bhela_bm_investor_share_pct( $id ),
			'investment'  => $r['investment'],
			'declared'    => $r['declared'],
			'received'    => $r['received'],
			'outstanding' => $r['outstanding'],
			'roi'         => $r['roi'],
			// Alongside, never instead of. Every key above still means exactly what it
			// meant before valuations existed.
			'holding'      => $h['holding'],
			'appreciation' => $h['appreciation'],
			'appr_pct'     => $h['appr_pct'],
		);
	}
	usort( $out['rows'], function ( $a, $b ) {
		return $b['shares'] <=> $a['shares'];
	} );

	$out['capital'] = bhela_bm_holding_totals();

	if ( function_exists( 'bhela_bm_payreq_pending_total' ) ) {
		$out['pending'] = bhela_bm_payreq_pending_total();
	}

	foreach ( bhela_bm_funds() as $key => $fund ) {
		$led = bhela_bm_fund_ledger( $key );
		$out['funds'][ $key ] = array(
			'label'     => $fund['label'],
			'allocated' => (int) $led['allocated'],
			'used'      => (int) $led['used'],
			'balance'   => (int) $led['closing'],
		);
	}

	// The most recent committed distribution, so "when did we last pay out" has an
	// answer on the same screen as "what is still owed".
	$index = get_option( 'bhela_bm_dist_runs', array() );
	if ( is_array( $index ) && $index ) {
		krsort( $index );
		$month = array_key_first( $index );
		$run   = bhela_bm_dist_run( $month );
		if ( $run ) {
			$d = bhela_bm_dist_data( $run );
			$out['last_run'] = array(
				'month'      => $month,
				'gross'      => (int) $d['gross'],
				'reserve'    => (int) $d['reserve'],
				'investor'   => (int) $d['investor'],
				'management' => (int) $d['management'],
			);
		}
	}

	return $out;
}

function bhela_bm_investor_dash_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this screen.', 'bhela-booking' ) );
	}
	$d      = bhela_bm_investor_dash_data();
	$season = sanitize_key( $_GET['season'] ?? '' );
	$sdata  = $season ? bhela_bm_season_investors( $season ) : null;
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'🧭',
			__( 'Investor Dashboard', 'bhela-booking' ),
			__( 'Where the investors stand, on one screen instead of five.', 'bhela-booking' ),
			sprintf(
				'<a class="button" href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bhela_bm_investor_csv' ), 'bhela_bm_investor_csv' ) ),
				esc_html__( 'Download CSV', 'bhela-booking' )
			)
		);
		?>

		<?php if ( $d['pending']['count'] > 0 ) : ?>
			<p class="bha-callout bha-callout--attention">
				<?php
				printf(
					/* translators: 1: number of requests, 2: total amount */
					esc_html( _n(
						'%1$d payment request is waiting for approval, worth %2$s. Nothing has been paid and no balance has moved until somebody releases it.',
						'%1$d payment requests are waiting for approval, worth %2$s in total. Nothing has been paid and no balance has moved until somebody releases them.',
						$d['pending']['count'],
						'bhela-booking'
					) ),
					(int) $d['pending']['count'],
					esc_html( bhela_bm_money( $d['pending']['total'] ) )
				);
				?>
			</p>
		<?php endif; ?>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Total investment', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $d['investment'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Investors', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( number_format_i18n( $d['shares']['investors'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Shares issued', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( number_format_i18n( $d['shares']['issued'] ) . ' / ' . number_format_i18n( $d['shares']['configured'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Profit declared', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $d['declared'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Paid out', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $d['received'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Outstanding', 'bhela-booking' ); ?></span><span class="bha-card__value <?php echo $d['outstanding'] > 0 ? 'is-danger' : 'is-good'; ?>"><?php echo esc_html( bhela_bm_money( $d['outstanding'] ) ); ?></span></div>
		</div>

		<h3 class="bha-sheet__h"><?php esc_html_e( 'Capital value', 'bhela-booking' ); ?></h3>
		<div class="bha-cards">
			<div class="bha-card">
				<span class="bha-card__label"><?php esc_html_e( 'Current share value', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $d['capital']['share_value'] ) ); ?></span>
				<p class="bha-note">
					<?php
					echo $d['capital']['valued']
						? esc_html( sprintf( /* translators: %s: date */ __( 'valued at %s', 'bhela-booking' ), mysql2date( 'j M Y', $d['capital']['as_at'] ) ) )
						: esc_html__( 'the original issue price — no valuation approved yet', 'bhela-booking' );
					?>
				</p>
			</div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Holding value', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $d['capital']['holding'] ) ); ?></span></div>
			<div class="bha-card">
				<span class="bha-card__label"><?php esc_html_e( 'Capital appreciation', 'bhela-booking' ); ?></span>
				<span class="bha-card__value <?php echo $d['capital']['appreciation'] < 0 ? 'is-danger' : 'is-good'; ?>"><?php echo esc_html( bhela_bm_money( $d['capital']['appreciation'] ) ); ?></span>
				<p class="bha-note"><?php esc_html_e( 'unrealised — this is value, not cash', 'bhela-booking' ); ?></p>
			</div>
		</div>
		<p class="bha-note">
			<?php esc_html_e( 'Capital appreciation and profit paid out are deliberately separate figures and are never added together. One is money already in an investor’s hand; the other is what their shares would be worth if the business were sold at the approved valuation.', 'bhela-booking' ); ?>
			<?php if ( $d['capital']['stale'] ) : ?>
				<br>
				<?php
				printf(
					/* translators: 1: shares issued since, 2: valuation date */
					esc_html__( '%1$d shares have been issued since the valuation of %2$s, so that figure is now pre-money and the holdings above are priced from it. Record a new valuation to bring the two back together.', 'bhela-booking' ),
					(int) $d['capital']['issued_since'],
					esc_html( mysql2date( 'j M Y', $d['capital']['as_at'] ) )
				);
				?>
			<?php elseif ( $d['capital']['valued'] && ( $d['capital']['unissued'] > 0 || 0 !== $d['capital']['rounding'] ) ) : ?>
				<br>
				<?php
				printf(
					/* translators: 1: valuation, 2: unissued share value, 3: rounding */
					esc_html__( 'The holdings above come to less than the %1$s valuation: %2$s of it sits on shares nobody holds yet, and %3$s is the remainder left by pricing each share in whole taka.', 'bhela-booking' ),
					esc_html( bhela_bm_money( $d['capital']['total'] ) ),
					esc_html( bhela_bm_money( $d['capital']['unissued'] ) ),
					esc_html( bhela_bm_money( $d['capital']['rounding'] ) )
				);
				?>
			<?php endif; ?>
		</p>

		<?php if ( $d['shares']['over'] ) : ?>
			<p class="bha-callout bha-callout--attention">
				<?php esc_html_e( 'More shares are issued than the share structure allows, so the percentages already add to more than 100%. Distribution is blocked until that is resolved on the register.', 'bhela-booking' ); ?>
			</p>
		<?php endif; ?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'Every investor', 'bhela-booking' ); ?></h2>
			<div class="bha-scroll">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Shares', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Invested', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Declared', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Received', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Outstanding', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'ROI', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Holding value', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Appreciation', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $d['rows'] ) : ?>
					<tr><td colspan="10"><?php esc_html_e( 'No investors on the register yet.', 'bhela-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $d['rows'] as $r ) : ?>
					<?php $tones = array( 'active' => 'good', 'suspended' => 'attention', 'exited' => 'neutral' ); ?>
					<tr>
						<td><a href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-investor-report', array( 'investor' => $r['investor'] ) ) ); ?>"><?php echo esc_html( $r['name'] ); ?></a></td>
						<td><?php echo bhela_bm_status_pill( $r['status'], $tones[ $r['status'] ] ?? 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td class="bha-num"><span class="bha-plain"><?php echo esc_html( number_format_i18n( $r['shares'] ) ); ?></span> · <?php echo esc_html( $r['pct'] ); ?>%</td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['investment'] ) ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['declared'] ) ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['received'] ) ); ?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['outstanding'] ) ); ?></td>
						<td class="bha-num"><?php echo esc_html( $r['roi'] ); ?>%</td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['holding'] ) ); ?></td>
						<td class="bha-num <?php echo $r['appreciation'] < 0 ? 'is-danger' : 'is-good'; ?>">
							<?php echo esc_html( bhela_bm_money( $r['appreciation'] ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot><tr>
					<th colspan="3"><?php esc_html_e( 'Total', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php echo esc_html( bhela_bm_money( $d['investment'] ) ); ?></th>
					<th class="bha-num"><?php echo esc_html( bhela_bm_money( $d['declared'] ) ); ?></th>
					<th class="bha-num"><?php echo esc_html( bhela_bm_money( $d['received'] ) ); ?></th>
					<th class="bha-num"><?php echo esc_html( bhela_bm_money( $d['outstanding'] ) ); ?></th>
					<th></th>
					<th class="bha-num"><?php echo esc_html( bhela_bm_money( $d['capital']['holding'] ) ); ?></th>
					<th class="bha-num"><?php echo esc_html( bhela_bm_money( $d['capital']['appreciation'] ) ); ?></th>
				</tr></tfoot>
			</table>
			</div>
		</div>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'Funds and the last distribution', 'bhela-booking' ); ?></h2>
			<div class="bha-cards">
				<?php foreach ( $d['funds'] as $f ) : ?>
					<div class="bha-card">
						<span class="bha-card__label"><?php echo esc_html( $f['label'] ); ?></span>
						<span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $f['balance'] ) ); ?></span>
						<p class="bha-note">
							<?php
							printf(
								/* translators: 1: allocated, 2: spent */
								esc_html__( '%1$s allocated · %2$s spent', 'bhela-booking' ),
								esc_html( bhela_bm_money( $f['allocated'] ) ),
								esc_html( bhela_bm_money( $f['used'] ) )
							);
							?>
						</p>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $d['last_run'] ) : ?>
				<p class="bha-note">
					<?php
					printf(
						/* translators: 1: month, 2: gross, 3: reserve, 4: investors, 5: management */
						esc_html__( 'Last distribution was %1$s: %2$s gross, of which %3$s to reserve, %4$s to investors and %5$s to management.', 'bhela-booking' ),
						esc_html( mysql2date( 'F Y', $d['last_run']['month'] . '-01' ) ),
						esc_html( bhela_bm_money( $d['last_run']['gross'] ) ),
						esc_html( bhela_bm_money( $d['last_run']['reserve'] ) ),
						esc_html( bhela_bm_money( $d['last_run']['investor'] ) ),
						esc_html( bhela_bm_money( $d['last_run']['management'] ) )
					);
					?>
				</p>
			<?php else : ?>
				<p class="bha-note"><?php esc_html_e( 'No month has been distributed yet.', 'bhela-booking' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'Season performance', 'bhela-booking' ); ?></h2>
			<?php $seasons = bhela_bm_seasons(); ?>
			<?php if ( ! $seasons ) : ?>
				<p class="bha-note">
					<?php
					printf(
						/* translators: %s: link to Settings */
						wp_kses_post( __( 'No seasons are defined. A season is just a name over a date range — add yours under <a href="%s">Setup → Settings</a> and every figure below groups by it. None are shipped, because inventing somebody else\'s season dates would put a confident wrong answer on this screen.', 'bhela-booking' ) ),
						esc_url( bhela_bm_admin_url( 'bhela-bm-settings' ) )
					);
					?>
				</p>
			<?php else : ?>
				<form method="get" class="bha-bar">
					<input type="hidden" name="page" value="bhela-bm-investor-dash">
					<div class="bha-field"><label for="bhela-dash-season"><?php esc_html_e( 'Season', 'bhela-booking' ); ?></label>
						<select id="bhela-dash-season" name="season">
							<option value=""><?php esc_html_e( '— pick a season —', 'bhela-booking' ); ?></option>
							<?php foreach ( $seasons as $s ) : ?>
								<option value="<?php echo esc_attr( $s['key'] ); ?>" <?php selected( $season, $s['key'] ); ?>>
									<?php echo esc_html( $s['label'] . ' (' . $s['from'] . ' → ' . $s['to'] . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select></div>
					<button class="button button-primary"><?php esc_html_e( 'Show', 'bhela-booking' ); ?></button>
				</form>
				<?php if ( $sdata ) : ?>
					<div class="bha-scroll">
					<table class="widefat striped">
						<thead><tr>
							<th><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'Declared in season', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'Paid in season', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'Difference', 'bhela-booking' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( ! $sdata['rows'] ) : ?>
							<tr><td colspan="4"><?php esc_html_e( 'Nothing was declared or paid inside this season.', 'bhela-booking' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $sdata['rows'] as $r ) : ?>
							<tr>
								<td><?php echo esc_html( $r['name'] ); ?></td>
								<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['declared'] ) ); ?></td>
								<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['paid'] ) ); ?></td>
								<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['outstanding'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
						<tfoot><tr>
							<th><?php esc_html_e( 'Total', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php echo esc_html( bhela_bm_money( $sdata['declared'] ) ); ?></th>
							<th class="bha-num"><?php echo esc_html( bhela_bm_money( $sdata['paid'] ) ); ?></th>
							<th class="bha-num"><?php echo esc_html( bhela_bm_money( $sdata['outstanding'] ) ); ?></th>
						</tr></tfoot>
					</table>
					</div>
					<p class="bha-note"><?php esc_html_e( 'Declared and paid inside the season’s own dates. The difference is not the investor’s lifetime balance — that is on the Investor Report, and it answers a different question.', 'bhela-booking' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/** The register as a file. Bank details are deliberately NOT in it. */
function bhela_bm_investor_csv() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this.', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_investor_csv' );

	$d = bhela_bm_investor_dash_data();

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=bhela-investors.csv' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );

	// No account numbers, no NID. An export leaves the building — it goes into email,
	// onto laptops, into shared drives — and "who has a copy of this" stops being a
	// question anybody can answer. The figures are what a report is for.
	fputcsv( $out, array_map( 'bhela_bm_csv_cell', array(
		__( 'Investor', 'bhela-booking' ),
		__( 'Status', 'bhela-booking' ),
		__( 'Shares', 'bhela-booking' ),
		__( 'Share %', 'bhela-booking' ),
		__( 'Invested', 'bhela-booking' ),
		__( 'Declared', 'bhela-booking' ),
		__( 'Received', 'bhela-booking' ),
		__( 'Outstanding', 'bhela-booking' ),
		__( 'ROI %', 'bhela-booking' ),
		__( 'Holding value', 'bhela-booking' ),
		__( 'Capital appreciation', 'bhela-booking' ),
	) ) );

	foreach ( $d['rows'] as $r ) {
		fputcsv( $out, array(
			bhela_bm_csv_cell( $r['name'] ),
			bhela_bm_csv_cell( $r['status'] ),
			$r['shares'],
			$r['pct'],
			$r['investment'],
			$r['declared'],
			$r['received'],
			$r['outstanding'],
			$r['roi'],
			$r['holding'],
			$r['appreciation'],
		) );
	}

	fputcsv( $out, array(
		bhela_bm_csv_cell( __( 'Total', 'bhela-booking' ) ),
		'', $d['shares']['issued'], '',
		$d['investment'], $d['declared'], $d['received'], $d['outstanding'], '',
		$d['capital']['holding'], $d['capital']['appreciation'],
	) );

	fclose( $out );
	exit;
}
add_action( 'admin_post_bhela_bm_investor_csv', 'bhela_bm_investor_csv' );

/**
 * One investor's ledger as a file.
 *
 * The running balance is exported alongside each row rather than left for the
 * spreadsheet to recompute — the whole point of this ledger is that the balance is
 * replayed from the rows in one place, and handing somebody a file that asks them to
 * replay it again in Excel is how a second, different answer gets into circulation.
 */
function bhela_bm_ledger_csv() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this.', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_ledger_csv' );

	$id = isset( $_GET['investor'] ) ? (int) $_GET['investor'] : 0;
	if ( 'bhela_investor' !== get_post_type( $id ) ) {
		wp_die( esc_html__( 'No such investor.', 'bhela-booking' ) );
	}
	$led = bhela_bm_investor_ledger( $id );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=bhela-ledger-' . $id . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array_map( 'bhela_bm_csv_cell', array(
		__( 'Date', 'bhela-booking' ),
		__( 'Type', 'bhela-booking' ),
		__( 'Reference', 'bhela-booking' ),
		__( 'Method', 'bhela-booking' ),
		__( 'Note', 'bhela-booking' ),
		__( 'Amount', 'bhela-booking' ),
		__( 'Balance', 'bhela-booking' ),
		__( 'Reversed', 'bhela-booking' ),
	) ) );
	foreach ( $led['rows'] as $r ) {
		fputcsv( $out, array(
			bhela_bm_csv_cell( $r['date'] ),
			bhela_bm_csv_cell( $r['label'] ),
			bhela_bm_csv_cell( $r['ref'] ),
			bhela_bm_csv_cell( $r['method'] ),
			bhela_bm_csv_cell( $r['note'] ),
			$r['signed'],
			$r['balance'],
			bhela_bm_ledger_reversal_of( $r['id'] ) ? 'yes' : '',
		) );
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_bhela_bm_ledger_csv', 'bhela_bm_ledger_csv' );

/** A fund's movements as a file. */
function bhela_bm_fund_csv() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this.', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_fund_csv' );

	$fund = sanitize_key( $_GET['fund'] ?? '' );
	if ( ! bhela_bm_fund_exists( $fund ) ) {
		wp_die( esc_html__( 'No such fund.', 'bhela-booking' ) );
	}
	$led = bhela_bm_fund_ledger( $fund );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=bhela-fund-' . $fund . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array_map( 'bhela_bm_csv_cell', array(
		__( 'Date', 'bhela-booking' ),
		__( 'Type', 'bhela-booking' ),
		__( 'Head', 'bhela-booking' ),
		__( 'Note', 'bhela-booking' ),
		__( 'Amount', 'bhela-booking' ),
		__( 'Balance', 'bhela-booking' ),
	) ) );
	foreach ( $led['rows'] as $r ) {
		fputcsv( $out, array(
			bhela_bm_csv_cell( $r['date'] ),
			bhela_bm_csv_cell( $r['type'] ),
			bhela_bm_csv_cell( (string) $r['head'] ),
			bhela_bm_csv_cell( (string) $r['note'] ),
			$r['signed'],
			$r['balance'],
		) );
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_bhela_bm_fund_csv', 'bhela_bm_fund_csv' );
