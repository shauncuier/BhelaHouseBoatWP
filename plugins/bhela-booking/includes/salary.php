<?php
/**
 * Staff roster and the monthly salary sheet.
 *
 * Most of the crew are paid per trip, so the month's wage bill is a function of
 * how many trips actually ran — a number the site already knows from the
 * approved cost sheets. The sheet fills that in and leaves the human parts
 * (advances, settlement, adjustments) to be typed.
 *
 * Roster rows are snapshotted onto each month's sheet when it is saved. A pay
 * rise next month must not rewrite what was paid last month.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * ROSTER
 * ========================================================= */

/** Employment types. These change the arithmetic, so the set is structural. */
function bhela_bm_employment_types() {
	return array(
		'trip'    => __( 'Trip based', 'bhela-booking' ),
		'monthly' => __( 'Monthly', 'bhela-booking' ),
		'both'    => __( 'Trip + monthly', 'bhela-booking' ),
	);
}

/** The roster as shipped — empty. The owner builds it in Settings. */
function bhela_bm_staff( $include_retired = false ) {
	$saved = get_option( 'bhela_bm_staff', array() );
	if ( ! is_array( $saved ) ) {
		return array();
	}
	$out = array();
	foreach ( $saved as $id => $row ) {
		$id = sanitize_key( $id );
		if ( '' === $id || ! is_array( $row ) || '' === ( $row['name'] ?? '' ) ) {
			continue;
		}
		if ( ! $include_retired && ! empty( $row['retired'] ) ) {
			continue;
		}
		$out[ $id ] = array(
			'name'        => (string) $row['name'],
			'designation' => (string) ( $row['designation'] ?? '' ),
			'type'        => array_key_exists( $row['type'] ?? '', bhela_bm_employment_types() ) ? $row['type'] : 'trip',
			'rate'        => (int) ( $row['rate'] ?? 0 ),
			'monthly'     => (int) ( $row['monthly'] ?? 0 ),
			'account'     => (string) ( $row['account'] ?? '' ),
			'retired'     => ! empty( $row['retired'] ),
		);
	}
	return $out;
}

/**
 * Save the roster.
 *
 * Like the cost heads, an id is minted once and frozen — it is the key a
 * saved salary sheet refers back to.
 */
function bhela_bm_save_staff( $posted ) {
	if ( ! is_array( $posted ) ) {
		return;
	}
	$out  = array();
	$seen = array();
	foreach ( $posted as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$name = sanitize_text_field( $row['name'] ?? '' );
		if ( '' === $name ) {
			continue;                       // a blank name deletes the row
		}
		$id = sanitize_key( $row['id'] ?? '' );
		if ( '' === $id ) {
			$id = sanitize_key( sanitize_title( $name ) ) ?: 'staff';
		}
		$base = $id;
		$n    = 2;
		while ( isset( $seen[ $id ] ) ) {
			$id = $base . '_' . $n;
			$n++;
		}
		$seen[ $id ] = true;

		$out[ $id ] = array(
			'name'        => $name,
			'designation' => sanitize_text_field( $row['designation'] ?? '' ),
			'type'        => array_key_exists( $row['type'] ?? '', bhela_bm_employment_types() ) ? $row['type'] : 'trip',
			'rate'        => max( 0, (int) ( $row['rate'] ?? 0 ) ),
			'monthly'     => max( 0, (int) ( $row['monthly'] ?? 0 ) ),
			'account'     => sanitize_text_field( $row['account'] ?? '' ),
			'retired'     => ! empty( $row['retired'] ) ? 1 : 0,
		);
	}
	update_option( 'bhela_bm_staff', $out );
}

/* =========================================================
 * SHEETS
 * ========================================================= */

function bhela_bm_register_salary_cpt() {
	register_post_type( 'bhela_salary', array(
		'labels' => array(
			'name'          => __( 'Salary Sheets', 'bhela-booking' ),
			'singular_name' => __( 'Salary Sheet', 'bhela-booking' ),
			'menu_name'     => __( '👷 Salary', 'bhela-booking' ),
			'all_items'     => __( '👷 Salary', 'bhela-booking' ),
			'add_new_item'  => __( 'New Salary Sheet', 'bhela-booking' ),
			'edit_item'     => __( 'Salary Sheet', 'bhela-booking' ),
			'not_found'     => __( 'No salary sheets yet.', 'bhela-booking' ),
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => 'edit.php?post_type=bhela_booking',
		'show_in_rest'        => false,
		'rewrite'             => false,
		'capability_type'     => array( 'bhela_salary', 'bhela_salaries' ),
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'supports'            => array( 'title' ),
	) );
}
add_action( 'init', 'bhela_bm_register_salary_cpt' );

/** How many trips actually ran in a month, per the approved cost sheets. */
function bhela_bm_salary_trip_count( $month ) {
	if ( ! $month || ! function_exists( 'bhela_bm_statement_data' ) ) {
		return 0;
	}
	// The statement now deducts payroll, and payroll prices trip-based crew from
	// the month's trip count — so these two functions can call each other. The
	// statement passes its own already-computed count down to avoid that, and this
	// guard is the backstop for any future caller that forgets to: re-entering
	// here means a cycle, and 0 trips is a wrong answer that returns, where an
	// infinite loop is a wrong answer that hangs the request.
	static $busy = array();
	if ( isset( $busy[ $month ] ) ) {
		return 0;
	}
	$busy[ $month ] = true;
	try {
		return count( bhela_bm_statement_data( $month )['trips'] );
	} finally {
		unset( $busy[ $month ] );
	}
}

/**
 * The rows for a sheet.
 *
 * Two callers wanting two different things, which is why `$include_roster` exists:
 *
 *   - The **edit screen** wants the sheet plus anyone on the roster who is not on
 *     it yet, so a new hire can be seen and added. That is a working surface.
 *   - The **money** — the monthly statement and the stored sheet total — wants only
 *     the rows the sheet was actually saved with. That is the record.
 *
 * Conflating the two was a real accounting bug. Adding one monthly-salaried manager
 * to the roster raised an already-saved July sheet's payroll by their full monthly
 * salary and dropped that month's gross profit by the same amount, with nobody
 * editing July. A month that has been paid must not gain a wage bill because
 * someone was hired later; there is no hire date on the roster to reason with, so
 * the snapshot is the only honest answer.
 *
 * A saved row also keeps the name, rate and designation it was saved with, so a pay
 * rise does not rewrite a month already paid. That half always worked — it was only
 * *new* people who leaked backwards.
 *
 * @param int      $post_id        Sheet ID (0 for a new one).
 * @param string   $month          YYYY-MM, used to default trips completed.
 * @param int|null $trips          Trip count, when the caller already knows it. Null
 *                                 asks for it, which costs a statement query.
 * @param bool     $include_roster Merge in roster members the sheet does not hold.
 *                                 True for the form, false for anything that adds up.
 * @return array
 */
function bhela_bm_salary_rows( $post_id = 0, $month = '', $trips = null, $include_roster = true ) {
	$saved = $post_id ? json_decode( (string) get_post_meta( $post_id, '_bhela_salary_rows', true ), true ) : array();
	$saved = is_array( $saved ) ? $saved : array();
	// A caller that already knows the month's trip count passes it in. The monthly
	// statement does exactly that, because it deducts payroll and would otherwise
	// send this function back into the statement to ask how many trips there were.
	$trips = null === $trips ? bhela_bm_salary_trip_count( $month ) : max( 0, (int) $trips );

	// A sheet with no saved rows at all is a blank form: price the roster so there is
	// something to edit. Once it holds rows, those rows are the sheet.
	$blank = ! $saved;

	$rows = array();
	foreach ( bhela_bm_staff() as $id => $s ) {
		$on_sheet = array_key_exists( $id, $saved );
		if ( ! $on_sheet && ! $include_roster ) {
			continue;                           // not on the sheet, so not in the total
		}
		$r           = $saved[ $id ] ?? array();
		$rows[ $id ] = bhela_bm_salary_row( $id, wp_parse_args( $r, $s ), $trips, $on_sheet || $blank );
		unset( $saved[ $id ] );
	}
	// Anyone left is a retired or removed staff member the sheet still records. They
	// were saved onto it, so they count.
	foreach ( $saved as $id => $r ) {
		$rows[ $id ] = bhela_bm_salary_row( $id, $r, $trips, true );
	}
	return $rows;
}

/**
 * Shape one row and do its arithmetic.
 *
 * @param string $id            Staff id.
 * @param array  $r             Row data (saved row merged over the roster entry).
 * @param int    $default_trips Trips to use when the row leaves the field blank.
 * @param bool   $saved         Whether this row is actually on the sheet. False means
 *                              it came from the roster and is not counted anywhere
 *                              until someone saves the sheet — the screen says so.
 */
function bhela_bm_salary_row( $id, $r, $default_trips, $saved = true ) {
	$type    = $r['type'] ?? 'trip';
	$rate    = (int) ( $r['rate'] ?? 0 );
	$monthly = (int) ( $r['monthly'] ?? 0 );
	// A blank trips field means "all of them"; 0 typed in means none.
	$trips   = isset( $r['trips'] ) && '' !== $r['trips'] ? max( 0, (int) $r['trips'] ) : (int) $default_trips;

	$sub     = ( 'monthly' === $type ) ? 0 : $rate * $trips;
	$monthly = ( 'trip' === $type ) ? 0 : $monthly;
	$payable = $sub + $monthly;
	$advance = max( 0, (int) ( $r['advance'] ?? 0 ) );

	return array(
		'id'          => (string) $id,
		'name'        => (string) ( $r['name'] ?? '' ),
		'designation' => (string) ( $r['designation'] ?? '' ),
		'type'        => $type,
		'account'     => (string) ( $r['account'] ?? '' ),
		'rate'        => $rate,
		'trips'       => $trips,
		'sub'         => $sub,
		'monthly'     => $monthly,
		'payable'     => $payable,
		'advance'     => $advance,
		'after'       => $payable - $advance,
		'settlement'  => (string) ( $r['settlement'] ?? '' ),
		'adjustment'  => (string) ( $r['adjustment'] ?? '' ),
		'verify'      => (string) ( $r['verify'] ?? '' ),
		'saved'       => (bool) $saved,
	);
}

/** Month totals for a set of rows. */
function bhela_bm_salary_totals( $rows ) {
	$t = array( 'sub' => 0, 'monthly' => 0, 'payable' => 0, 'advance' => 0, 'after' => 0 );
	foreach ( $rows as $r ) {
		foreach ( array_keys( $t ) as $k ) {
			$t[ $k ] += (int) $r[ $k ];
		}
	}
	return $t;
}

/**
 * The month's payroll, as a cost the monthly statement deducts.
 *
 * Crew wages are a cost of running the boat, so they belong in gross profit
 * alongside fuel and groceries. The statement was computing
 * `profit − expenses` and leaving payroll out entirely, which overstated every
 * month's bottom line by the whole wage bill.
 *
 * Two deliberate choices:
 *
 *   - It reads SAVED sheets only, and within a sheet only the rows that were saved
 *     onto it — hence the `false` for `$include_roster`. Two separate traps. A sheet
 *     that does not exist must not deduct wages for a month nobody has done payroll
 *     for; and a sheet that does exist must not gain a wage bill because someone was
 *     added to the roster afterwards. The second one shipped: one new manager
 *     silently cost an already-closed July its whole monthly salary.
 *   - It totals `payable`, not `after`. An advance already handed over is still
 *     part of the wage bill; `after` is only what is left to pay, and deducting
 *     that would make a month look cheaper for having paid early.
 *
 * There is no one-sheet-per-month constraint on bhela_salary, so every sheet
 * found is summed and the count is reported — two sheets for one month is a thing
 * the owner needs to see rather than something to silently pick between.
 *
 * @param string $month YYYY-MM.
 * @return array{total:int,sheets:int,ids:int[]}
 */
function bhela_bm_salary_month_total( $month, $trips = null ) {
	$out = array( 'total' => 0, 'sheets' => 0, 'ids' => array() );
	if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', (string) $month ) ) {
		return $out;
	}
	$ids = get_posts( array(
		'post_type'      => 'bhela_salary',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => '_bhela_salary_month', 'value' => $month ),
		),
	) );
	foreach ( $ids as $id ) {
		// false: saved rows only. A roster member who is not on this sheet was not
		// paid in this month, and adding them today must not change what it cost.
		$rows           = bhela_bm_salary_rows( $id, $month, $trips, false );
		$out['total']  += (int) bhela_bm_salary_totals( $rows )['payable'];
		$out['sheets']++;
		$out['ids'][]   = (int) $id;
	}
	return $out;
}

/* =========================================================
 * EDIT SCREEN
 * ========================================================= */

function bhela_bm_salary_meta_box() {
	add_meta_box( 'bhela_salary_sheet', __( 'Salary Sheet', 'bhela-booking' ), 'bhela_bm_salary_meta_cb', 'bhela_salary', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'bhela_bm_salary_meta_box' );

function bhela_bm_salary_meta_cb( $post ) {
	wp_nonce_field( 'bhela_bm_salary_save', 'bhela_bm_salary_nonce' );
	$month = (string) get_post_meta( $post->ID, '_bhela_salary_month', true );
	if ( ! $month ) {
		$month = current_time( 'Y-m' );
	}
	$rows   = bhela_bm_salary_rows( $post->ID, $month );
	$totals = bhela_bm_salary_totals( $rows );
	$trips  = bhela_bm_salary_trip_count( $month );
	$types  = bhela_bm_employment_types();
	?>
	<div class="bha-sheet">
		<div class="bha-bar" style="margin-top:0">
			<div class="bha-field bha-field--caps">
				<label for="sal_month"><?php esc_html_e( 'Month', 'bhela-booking' ); ?></label>
				<input type="month" id="sal_month" name="sal_month" value="<?php echo esc_attr( $month ); ?>">
			</div>
			<p class="bha-note" style="margin:0">
				<?php
				printf(
					/* translators: %d: number of approved trips */
					esc_html( _n( '%d approved trip this month — used as the default for everyone.', '%d approved trips this month — used as the default for everyone.', $trips, 'bhela-booking' ) ),
					(int) $trips
				);
				?>
				<br><?php esc_html_e( 'Override the count for anyone who missed a trip. Save the month first if you have just changed it.', 'bhela-booking' ); ?>
			</p>
		</div>

		<?php if ( $rows && 0 === (int) $trips ) : ?>
			<?php
			// Trip pay is rate × trips, and the count comes from approved cost
			// sheets. Before any are approved that count is zero, so every
			// trip-based crew member silently reads ৳0 payable. A sheet printed
			// in that state underpays the whole crew, and nothing on the page
			// said why the figures were empty.
			?>
			<p class="bha-callout bha-callout--attention bha-callout--lead">
				<strong>⚠️ <?php esc_html_e( 'No approved cost sheets for this month yet, so every trip-based figure below is ৳0.', 'bhela-booking' ); ?></strong>
				<?php esc_html_e( 'Approve this month\'s cost sheets first, then re-save this sheet — or type each person\'s trip count by hand. Do not pay from this sheet as it stands.', 'bhela-booking' ); ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bhela_cost' ) ); ?>"><?php esc_html_e( 'Open Cost Sheets', 'bhela-booking' ); ?></a>
			</p>
		<?php endif; ?>

		<?php if ( ! $rows ) : ?>
			<p class="bha-callout bha-callout--attention bha-callout--lead">
				<?php esc_html_e( 'No staff on the roster yet.', 'bhela-booking' ); ?>
				<a href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-settings' ) . '#bhela-panel-staff' ); ?>"><?php esc_html_e( 'Add staff in Settings → Staff', 'bhela-booking' ); ?></a>
			</p>
		<?php else : ?>
		<div class="bha-scroll">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Staff', 'bhela-booking' ); ?></th>
					<th style="width:100px"><?php esc_html_e( 'Type', 'bhela-booking' ); ?></th>
					<th style="width:90px" class="bha-num"><?php esc_html_e( 'Rate', 'bhela-booking' ); ?></th>
					<th style="width:80px" class="bha-num"><?php esc_html_e( 'Trips', 'bhela-booking' ); ?></th>
					<th style="width:100px" class="bha-num"><?php esc_html_e( 'Sub-total', 'bhela-booking' ); ?></th>
					<th style="width:100px" class="bha-num"><?php esc_html_e( 'Monthly', 'bhela-booking' ); ?></th>
					<th style="width:105px" class="bha-num"><?php esc_html_e( 'Payable', 'bhela-booking' ); ?></th>
					<th style="width:100px" class="bha-num"><?php esc_html_e( 'Advance', 'bhela-booking' ); ?></th>
					<th style="width:105px" class="bha-num"><?php esc_html_e( 'After advance', 'bhela-booking' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'Settlement', 'bhela-booking' ); ?></th>
					<th style="width:140px"><?php esc_html_e( 'Adjustment', 'bhela-booking' ); ?></th>
					<th style="width:130px"><?php esc_html_e( 'Verification', 'bhela-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $id => $r ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $r['name'] ); ?></strong>
						<?php
						// A row merged in from the roster is NOT part of this month's payroll
						// until the sheet is saved — the statement counts saved rows only. Say
						// so on the row, because a figure sitting in a Payable column reads as
						// owed whether or not anything has counted it.
						if ( empty( $r['saved'] ) ) :
							?>
							<span class="bha-pill bha-pill--attention"><?php esc_html_e( 'not on this sheet yet', 'bhela-booking' ); ?></span>
						<?php endif; ?>
						<div class="bha-sub"><?php echo esc_html( $r['designation'] ); ?><?php echo $r['account'] ? ' · ' . esc_html( $r['account'] ) : ''; ?></div>
						<?php // Snapshot the roster details onto the sheet, so a later pay rise
						      // cannot rewrite a month that has already been paid. ?>
						<?php foreach ( array( 'name', 'designation', 'type', 'account', 'rate', 'monthly' ) as $f ) : ?>
							<input type="hidden" name="sal_rows[<?php echo esc_attr( $id ); ?>][<?php echo esc_attr( $f ); ?>]" value="<?php echo esc_attr( 'monthly' === $f ? ( $r['monthly'] ?: ( 'trip' === $r['type'] ? 0 : $r['monthly'] ) ) : $r[ $f ] ); ?>">
						<?php endforeach; ?>
					</td>
					<td><?php echo esc_html( $types[ $r['type'] ] ?? $r['type'] ); ?></td>
					<td class="bha-num"><?php echo esc_html( $r['rate'] ? bhela_bm_money( $r['rate'] ) : '—' ); ?></td>
					<td><input type="number" min="0" name="sal_rows[<?php echo esc_attr( $id ); ?>][trips]" value="<?php echo esc_attr( $r['trips'] ); ?>"></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['sub'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( $r['monthly'] ? bhela_bm_money( $r['monthly'] ) : '—' ); ?></td>
					<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $r['payable'] ) ); ?></strong></td>
					<td><input type="number" min="0" name="sal_rows[<?php echo esc_attr( $id ); ?>][advance]" value="<?php echo esc_attr( $r['advance'] ?: '' ); ?>"></td>
					<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $r['after'] ) ); ?></strong></td>
					<td><input type="text" name="sal_rows[<?php echo esc_attr( $id ); ?>][settlement]" value="<?php echo esc_attr( $r['settlement'] ); ?>" placeholder="PAID"></td>
					<td><input type="text" name="sal_rows[<?php echo esc_attr( $id ); ?>][adjustment]" value="<?php echo esc_attr( $r['adjustment'] ); ?>" placeholder="<?php esc_attr_e( 'No Adjustment', 'bhela-booking' ); ?>"></td>
					<td><input type="text" name="sal_rows[<?php echo esc_attr( $id ); ?>][verify]" value="<?php echo esc_attr( $r['verify'] ); ?>"></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="4"><?php esc_html_e( 'Total', 'bhela-booking' ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $totals['sub'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $totals['monthly'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $totals['payable'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $totals['advance'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $totals['after'] ) ); ?></td>
					<td colspan="3"></td>
				</tr>
			</tfoot>
		</table>
		</div>
		<p class="bha-note"><?php esc_html_e( 'Sub-total is rate × trips. Payable adds any monthly salary. Save to recalculate.', 'bhela-booking' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

function bhela_bm_salary_save( $post_id, $post ) {
	if ( 'bhela_salary' !== $post->post_type ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['bhela_bm_salary_nonce'] ) || ! wp_verify_nonce( $_POST['bhela_bm_salary_nonce'], 'bhela_bm_salary_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$month = sanitize_text_field( wp_unslash( $_POST['sal_month'] ?? '' ) );
	$month = preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ? $month : current_time( 'Y-m' );
	update_post_meta( $post_id, '_bhela_salary_month', $month );

	$posted = isset( $_POST['sal_rows'] ) && is_array( $_POST['sal_rows'] ) ? wp_unslash( $_POST['sal_rows'] ) : array();
	$rows   = array();
	foreach ( $posted as $id => $r ) {
		$id = sanitize_key( $id );
		if ( '' === $id || ! is_array( $r ) ) {
			continue;
		}
		$rows[ $id ] = array(
			'name'        => sanitize_text_field( $r['name'] ?? '' ),
			'designation' => sanitize_text_field( $r['designation'] ?? '' ),
			'type'        => array_key_exists( $r['type'] ?? '', bhela_bm_employment_types() ) ? $r['type'] : 'trip',
			'account'     => sanitize_text_field( $r['account'] ?? '' ),
			'rate'        => max( 0, (int) ( $r['rate'] ?? 0 ) ),
			'monthly'     => max( 0, (int) ( $r['monthly'] ?? 0 ) ),
			// Blank is kept blank. bhela_bm_salary_row() reads a blank trips field as
			// "however many trips ran" and a typed 0 as "none" — and `(int) ''` is 0,
			// so casting here collapsed the two and the blank case could never survive
			// a save. Whoever cleared the field got a crew member paid for no trips.
			// The form pre-fills the month's count, so blank is always deliberate.
			'trips'       => ( '' === trim( (string) ( $r['trips'] ?? '' ) ) ) ? '' : max( 0, (int) $r['trips'] ),
			'advance'     => max( 0, (int) ( $r['advance'] ?? 0 ) ),
			'settlement'  => sanitize_text_field( $r['settlement'] ?? '' ),
			'adjustment'  => sanitize_text_field( $r['adjustment'] ?? '' ),
			'verify'      => sanitize_text_field( $r['verify'] ?? '' ),
		);
	}
	update_post_meta( $post_id, '_bhela_salary_rows', wp_json_encode( $rows, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT ) );

	// Saved rows only, so the figure stamped on the sheet is the figure the monthly
	// statement deducts. Anything merged in from the roster is not on the sheet yet.
	$totals = bhela_bm_salary_totals( bhela_bm_salary_rows( $post_id, $month, null, false ) );
	update_post_meta( $post_id, '_bhela_salary_total', $totals['payable'] );

	$title = sprintf( __( 'Salary — %s', 'bhela-booking' ), mysql2date( 'F Y', $month . '-01' ) );
	if ( $title !== $post->post_title ) {
		remove_action( 'save_post', 'bhela_bm_salary_save', 10 );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
		add_action( 'save_post', 'bhela_bm_salary_save', 10, 2 );
	}
}
add_action( 'save_post', 'bhela_bm_salary_save', 10, 2 );

/* =========================================================
 * LIST TABLE
 * ========================================================= */

function bhela_bm_salary_columns( $columns ) {
	return array(
		'cb'      => $columns['cb'] ?? '',
		'title'   => __( 'Sheet', 'bhela-booking' ),
		'salmon'  => __( 'Month', 'bhela-booking' ),
		'salstaff' => __( 'Staff', 'bhela-booking' ),
		'saltot'  => __( 'Total payable', 'bhela-booking' ),
	);
}
add_filter( 'manage_bhela_salary_posts_columns', 'bhela_bm_salary_columns' );

function bhela_bm_salary_column( $column, $post_id ) {
	switch ( $column ) {
		case 'salmon':
			$m = get_post_meta( $post_id, '_bhela_salary_month', true );
			echo $m ? esc_html( mysql2date( 'F Y', $m . '-01' ) ) : '—';
			break;
		case 'salstaff':
			$rows = json_decode( (string) get_post_meta( $post_id, '_bhela_salary_rows', true ), true );
			echo esc_html( is_array( $rows ) ? count( $rows ) : 0 );
			break;
		case 'saltot':
			echo '<strong>' . esc_html( bhela_bm_money( (int) get_post_meta( $post_id, '_bhela_salary_total', true ) ) ) . '</strong>';
			break;
	}
}
add_action( 'manage_bhela_salary_posts_custom_column', 'bhela_bm_salary_column', 10, 2 );
