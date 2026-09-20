<?php
/**
 * Importing what was actually paid — upload → map → dry run → commit.
 *
 * Years of payments were made before this system existed, unevenly and from a
 * spreadsheet. Typing them in one at a time through the payment-request screen is a
 * week of work and a week of typos, so they come in as a file.
 *
 * The flow and its safeties are lifted from includes/inventory-import.php, which has
 * already been through this once: the file is parsed at upload and **never copied into
 * uploads/**, the parsed rows live in a site transient keyed by a random token and
 * scoped to the uploading user, and the BOM is skipped before the parse rather than
 * stripped from the cells afterwards — Excel writes one, and after the parse the damage
 * is already done.
 *
 * Three decisions specific to money:
 *
 * 1. **These rows skip the payment-request chain, and that is the point.** A payreq is
 *    a second signature before money LEAVES. This money left years ago; the import is a
 *    record of history, not a decision to pay. So it writes ledger rows directly —
 *    which is exactly why it sits behind its own capability, `bhela_investor_import`,
 *    held by the administrator alone rather than by Manager.
 * 2. **Nothing is written until Commit.** The dry run resolves every row to a named
 *    investor and shows the totals it would write. A row it cannot resolve
 *    unambiguously is refused, on screen, with the reason — never guessed at.
 * 3. **Every row carries a batch reference.** An import that turns out to be wrong is
 *    undone the way everything else in this ledger is undone: with contra rows, through
 *    bhela_bm_ledger_reverse(). The batch ref is how you find them again. There is no
 *    delete path, here or anywhere else.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ceilings. A settlement file is a few hundred lines, not a database dump. */
function bhela_bm_settle_import_limits() {
	return array(
		'bytes' => 2 * MB_IN_BYTES,
		'rows'  => 2000,
		'cols'  => 30,
	);
}

/**
 * The columns a file may carry.
 *
 * `profit` is the optional one and it changes what the row MEANS: with it, the row
 * writes a declared-profit entry instead of a payment. It exists because a period may
 * never have been distributed in the system, and without the profit side every investor
 * paid in that period reads as overpaid.
 */
function bhela_bm_settle_import_fields() {
	return array(
		'investor' => array( 'label' => __( 'Investor (ID, mobile or name)', 'bhela-booking' ), 'required' => true ),
		'date'     => array( 'label' => __( 'Date', 'bhela-booking' ), 'required' => true ),
		'amount'   => array( 'label' => __( 'Amount paid', 'bhela-booking' ) ),
		'profit'   => array( 'label' => __( 'Declared profit (optional)', 'bhela-booking' ) ),
		'method'   => array( 'label' => __( 'Method', 'bhela-booking' ) ),
		'ref'      => array( 'label' => __( 'Reference', 'bhela-booking' ) ),
		'note'     => array( 'label' => __( 'Note', 'bhela-booking' ) ),
	);
}

function bhela_bm_settle_import_key( $token ) {
	return 'bhela_bm_settle_import_' . preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
}

/** The staged rows, refusing another user's staging. */
function bhela_bm_settle_import_staged( $token ) {
	$data = get_site_transient( bhela_bm_settle_import_key( $token ) );
	if ( ! is_array( $data ) || empty( $data['rows'] ) ) {
		return null;
	}
	if ( (int) ( $data['user'] ?? 0 ) !== get_current_user_id() ) {
		return null;
	}
	return $data;
}

function bhela_bm_settle_import_url( $args = array() ) {
	return bhela_bm_admin_url( 'bhela-bm-settle-import', $args );
}

function bhela_bm_settle_import_bail( $message ) {
	set_transient( 'bhela_bm_settle_import_err_' . get_current_user_id(), $message, 60 );
	wp_safe_redirect( bhela_bm_settle_import_url() );
	exit;
}

/**
 * Which field a header probably means. A convenience — the owner always confirms.
 *
 * Never commit on a guess: a silently wrong mapping fills a money ledger with plausible
 * nonsense, which is worse than an obvious failure.
 */
function bhela_bm_settle_import_guess( $header ) {
	$h = strtolower( trim( (string) $header ) );
	if ( '' === $h ) {
		return '';
	}
	$map = array(
		'investor' => array( 'investor', 'name', 'নাম', 'বিনিয়োগকারী', 'investor id', 'id', 'code', 'mobile', 'phone', 'মোবাইল' ),
		'date'     => array( 'date', 'paid on', 'payment date', 'তারিখ' ),
		'amount'   => array( 'amount', 'paid', 'payment', 'taka', 'tk', 'পরিমাণ', 'টাকা', 'দেওয়া' ),
		'profit'   => array( 'profit', 'declared', 'share', 'লাভ', 'ঘোষিত' ),
		'method'   => array( 'method', 'mode', 'via', 'মাধ্যম' ),
		'ref'      => array( 'ref', 'reference', 'txn', 'transaction', 'রেফারেন্স' ),
		'note'     => array( 'note', 'remark', 'comment', 'মন্তব্য' ),
	);
	foreach ( $map as $field => $needles ) {
		foreach ( $needles as $n ) {
			if ( $h === $n || false !== strpos( $h, $n ) ) {
				return $field;
			}
		}
	}
	return '';
}

/**
 * Resolve a cell to exactly one investor.
 *
 * Investor ID first because it is the office's own identifier, then the normalised
 * mobile (reusing the portal's own lookup, which already refuses a number on two
 * records), then an exact name. **Ambiguity is refused, never resolved** — picking
 * whichever sorted first would put one investor's money on another's statement.
 *
 * @return array{id:int,error:string}
 */
function bhela_bm_settle_import_investor( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return array( 'id' => 0, 'error' => __( 'no investor given', 'bhela-booking' ) );
	}

	// 1. Investor ID as the office writes it.
	$hit = get_posts( array(
		'post_type'      => 'bhela_investor',
		'post_status'    => array( 'publish', 'private', 'draft' ),
		'posts_per_page' => 2,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_inv_code',
		'meta_value'     => $raw,
	) );
	if ( 1 === count( $hit ) ) {
		return array( 'id' => (int) $hit[0], 'error' => '' );
	}
	if ( count( $hit ) > 1 ) {
		return array( 'id' => 0, 'error' => __( 'two records share that Investor ID', 'bhela-booking' ) );
	}

	// 2. Mobile, through the lookup the portal signs people in with.
	if ( function_exists( 'bhela_bm_investor_by_mobile' ) && bhela_bm_normalize_mobile( $raw ) ) {
		$by_mobile = bhela_bm_investor_by_mobile( $raw );
		if ( $by_mobile ) {
			return array( 'id' => (int) $by_mobile, 'error' => '' );
		}
		return array( 'id' => 0, 'error' => __( 'that mobile matches no single record', 'bhela-booking' ) );
	}

	// 3. An exact name, and only an exact one.
	$named = array();
	foreach ( bhela_bm_investors() as $id ) {
		if ( 0 === strcasecmp( trim( get_the_title( $id ) ), $raw ) ) {
			$named[] = (int) $id;
		}
	}
	if ( 1 === count( $named ) ) {
		return array( 'id' => $named[0], 'error' => '' );
	}
	if ( count( $named ) > 1 ) {
		return array( 'id' => 0, 'error' => __( 'more than one investor has that name', 'bhela-booking' ) );
	}
	return array( 'id' => 0, 'error' => __( 'no investor matches', 'bhela-booking' ) );
}

/**
 * Turn the staged rows plus a mapping into a plan.
 *
 * Pure: it writes nothing. The dry run renders this, and the commit walks exactly the
 * same structure — so what is approved on screen is what is written, which is the same
 * contract bhela_bm_dist_preview() has with bhela_bm_dist_commit().
 */
function bhela_bm_settle_import_plan( $rows, $map ) {
	$plan = array(
		'ok'      => array(),
		'bad'     => array(),
		'paid'    => 0,
		'profit'  => 0,
		'people'  => array(),
	);
	$header = true;
	foreach ( $rows as $n => $row ) {
		if ( $header ) {
			$header = false;
			continue;                            // row 1 is the header
		}
		$get = function ( $field ) use ( $row, $map ) {
			$col = $map[ $field ] ?? '';
			return ( '' === $col || ! isset( $row[ $col ] ) ) ? '' : trim( (string) $row[ $col ] );
		};

		$line  = $n + 1;
		$who   = bhela_bm_settle_import_investor( $get( 'investor' ) );
		$date  = bhela_bm_report_date( $get( 'date' ) );
		// Strip thousands separators and a taka sign before reading a figure — a
		// spreadsheet exports "৳1,20,000" and (int) on that is 1.
		$clean = function ( $v ) {
			return (int) preg_replace( '/[^0-9\-]/', '', (string) $v );
		};
		$amount = $clean( $get( 'amount' ) );
		$profit = $clean( $get( 'profit' ) );

		if ( $who['error'] ) {
			$plan['bad'][] = array( 'line' => $line, 'raw' => $get( 'investor' ), 'why' => $who['error'] );
			continue;
		}
		if ( '' === $date ) {
			$plan['bad'][] = array( 'line' => $line, 'raw' => $get( 'date' ), 'why' => __( 'that date cannot be read', 'bhela-booking' ) );
			continue;
		}
		if ( $amount <= 0 && $profit <= 0 ) {
			$plan['bad'][] = array( 'line' => $line, 'raw' => $get( 'amount' ), 'why' => __( 'no amount on the row', 'bhela-booking' ) );
			continue;
		}
		if ( $amount < 0 || $profit < 0 ) {
			// A negative here is a refund typed into the wrong column. It would
			// quietly increase what the investor is owed.
			$plan['bad'][] = array( 'line' => $line, 'raw' => $get( 'amount' ), 'why' => __( 'a negative figure — record a correction as an adjustment instead', 'bhela-booking' ) );
			continue;
		}

		$plan['ok'][] = array(
			'line'     => $line,
			'investor' => $who['id'],
			'name'     => get_the_title( $who['id'] ),
			'date'     => $date,
			'amount'   => $amount,
			'profit'   => $profit,
			'method'   => sanitize_text_field( $get( 'method' ) ),
			'ref'      => sanitize_text_field( $get( 'ref' ) ),
			'note'     => sanitize_textarea_field( $get( 'note' ) ),
		);
		$plan['paid']   += $amount;
		$plan['profit'] += $profit;
		$plan['people'][ $who['id'] ] = true;
	}
	$plan['people'] = count( $plan['people'] );
	return $plan;
}

/* =========================================================
 * UPLOAD
 * ========================================================= */

function bhela_bm_settle_import_upload() {
	if ( ! current_user_can( 'bhela_investor_import' ) ) {
		wp_die( esc_html__( 'You are not allowed to import payments.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_settle_import_upload' );

	$limits = bhela_bm_settle_import_limits();
	$file   = $_FILES['bhela_settle_csv'] ?? null;

	if ( ! $file || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		bhela_bm_settle_import_bail( __( 'No file arrived. Choose a CSV and try again.', 'bhela-booking' ) );
	}
	if ( (int) $file['size'] > $limits['bytes'] ) {
		bhela_bm_settle_import_bail( __( 'That file is larger than 2 MB — is it definitely a CSV and not a workbook?', 'bhela-booking' ) );
	}
	$ext = strtolower( (string) pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
		bhela_bm_settle_import_bail( __( 'Save the sheet as CSV first — an .xlsx workbook cannot be read directly.', 'bhela-booking' ) );
	}

	$fh = fopen( $file['tmp_name'], 'r' );
	if ( ! $fh ) {
		bhela_bm_settle_import_bail( __( 'That file could not be opened.', 'bhela-booking' ) );
	}
	// Before the parse, not after — see the file header.
	if ( "\xEF\xBB\xBF" !== fread( $fh, 3 ) ) {
		rewind( $fh );
	}
	$rows = array();
	$n    = 0;
	while ( ( $row = fgetcsv( $fh ) ) !== false ) {
		if ( $n >= $limits['rows'] ) {
			break;
		}
		if ( 1 === count( $row ) && ( null === $row[0] || '' === trim( (string) $row[0] ) ) ) {
			continue;
		}
		$row = array_slice( $row, 0, $limits['cols'] );
		foreach ( $row as $i => $cell ) {
			$row[ $i ] = function_exists( 'bhela_bm_inv_import_clean' )
				? bhela_bm_inv_import_clean( $cell )
				: (string) $cell;
		}
		$rows[] = $row;
		$n++;
	}
	fclose( $fh );

	if ( count( $rows ) < 2 ) {
		bhela_bm_settle_import_bail( __( 'That file has no data rows in it.', 'bhela-booking' ) );
	}

	$token = wp_generate_password( 20, false );
	set_site_transient( bhela_bm_settle_import_key( $token ), array(
		'rows' => $rows,
		'user' => get_current_user_id(),
		'name' => sanitize_file_name( (string) $file['name'] ),
	), 2 * HOUR_IN_SECONDS );

	wp_safe_redirect( bhela_bm_settle_import_url( array( 'step' => 'map', 'token' => $token ) ) );
	exit;
}
add_action( 'admin_post_bhela_bm_settle_import_upload', 'bhela_bm_settle_import_upload' );

/* =========================================================
 * COMMIT
 * ========================================================= */

function bhela_bm_settle_import_commit() {
	if ( ! current_user_can( 'bhela_investor_import' ) ) {
		wp_die( esc_html__( 'You are not allowed to import payments.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_settle_import_commit' );

	$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	$data  = bhela_bm_settle_import_staged( $token );
	if ( ! $data ) {
		bhela_bm_settle_import_bail( __( 'That upload has expired. Start again.', 'bhela-booking' ) );
	}

	$map = array();
	foreach ( array_keys( bhela_bm_settle_import_fields() ) as $field ) {
		$raw = isset( $_POST['map'][ $field ] ) ? sanitize_text_field( wp_unslash( $_POST['map'][ $field ] ) ) : '';
		$map[ $field ] = ( '' === $raw ) ? '' : (int) $raw;
	}
	$plan = bhela_bm_settle_import_plan( $data['rows'], $map );

	if ( ! $plan['ok'] ) {
		bhela_bm_settle_import_bail( __( 'Nothing in that file could be imported. Check the column mapping.', 'bhela-booking' ) );
	}

	// One reference for the whole batch, so these rows can be found again and reversed
	// together if the file turns out to be wrong. The ledger has no delete path and
	// this does not add one.
	$batch = 'IMP-' . gmdate( 'Ymd-His' ) . '-' . strtoupper( wp_generate_password( 4, false ) );
	$made  = 0;
	$fail  = 0;

	foreach ( $plan['ok'] as $r ) {
		$common = array(
			'investor' => $r['investor'],
			'date'     => $r['date'],
			'method'   => $r['method'],
			'ref'      => $r['ref'] ? $batch . ' / ' . $r['ref'] : $batch,
			'note'     => $r['note'],
		);
		// The profit side first: a payment against a profit declared on the same day
		// should not momentarily read as an overpayment.
		if ( $r['profit'] > 0 ) {
			$row = bhela_bm_ledger_add( $common + array( 'type' => 'profit', 'amount' => $r['profit'] ) );
			is_wp_error( $row ) ? $fail++ : $made++;
		}
		if ( $r['amount'] > 0 ) {
			$row = bhela_bm_ledger_add( $common + array( 'type' => 'payment', 'amount' => $r['amount'] ) );
			is_wp_error( $row ) ? $fail++ : $made++;
		}
	}

	// Spend the staging. A refresh must not write the batch a second time.
	delete_site_transient( bhela_bm_settle_import_key( $token ) );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'settle_import',
		'object_type' => 'ledger',
		'object_id'   => 0,
		'object_ref'  => $batch,
		'field'       => 'rows',
		'new_value'   => (string) $made,
		'reason'      => sprintf(
			/* translators: 1: file name, 2: investors touched, 3: failed rows */
			__( 'Imported from %1$s across %2$d investors. %3$d rows failed. Reverse with the batch reference if wrong.', 'bhela-booking' ),
			$data['name'],
			(int) $plan['people'],
			(int) $fail
		),
	) );

	set_transient( 'bhela_bm_settle_import_ok_' . get_current_user_id(), array(
		'made'  => $made,
		'fail'  => $fail,
		'batch' => $batch,
	), 60 );

	wp_safe_redirect( bhela_bm_settle_import_url() );
	exit;
}
add_action( 'admin_post_bhela_bm_settle_import_commit', 'bhela_bm_settle_import_commit' );

/* =========================================================
 * THE SCREEN
 * ========================================================= */

function bhela_bm_settle_import_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Import Payments', 'bhela-booking' ),
		'📥 ' . __( 'Import Payments', 'bhela-booking' ),
		'bhela_investor_import',
		'bhela-bm-settle-import',
		'bhela_bm_settle_import_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_settle_import_menu', 21 );

/** The mapping the screen is working with — posted, or guessed from the header. */
function bhela_bm_settle_import_map( $header ) {
	$map = array();
	foreach ( array_keys( bhela_bm_settle_import_fields() ) as $field ) {
		if ( isset( $_POST['map'][ $field ] ) ) {
			$raw           = sanitize_text_field( wp_unslash( $_POST['map'][ $field ] ) );
			$map[ $field ] = ( '' === $raw ) ? '' : (int) $raw;
			continue;
		}
		$map[ $field ] = '';
		foreach ( $header as $i => $h ) {
			if ( $field === bhela_bm_settle_import_guess( $h ) ) {
				$map[ $field ] = $i;
				break;
			}
		}
	}
	return $map;
}

function bhela_bm_settle_import_page() {
	if ( ! current_user_can( 'bhela_investor_import' ) ) {
		wp_die( esc_html__( 'You are not allowed to import payments.', 'bhela-booking' ) );
	}
	$step  = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	$err   = get_transient( 'bhela_bm_settle_import_err_' . get_current_user_id() );
	$ok    = get_transient( 'bhela_bm_settle_import_ok_' . get_current_user_id() );
	delete_transient( 'bhela_bm_settle_import_err_' . get_current_user_id() );
	delete_transient( 'bhela_bm_settle_import_ok_' . get_current_user_id() );
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'📥',
			__( 'Import Payments', 'bhela-booking' ),
			__( 'What has already been paid to investors, from a spreadsheet. Nothing is written until you have seen exactly what it would write.', 'bhela-booking' )
		);
		?>

		<?php if ( $err ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
		<?php endif; ?>

		<?php if ( is_array( $ok ) ) : ?>
			<div class="notice notice-success">
				<p>
					<?php
					printf(
						/* translators: 1: rows written, 2: batch reference */
						esc_html__( '%1$d ledger rows written. Batch reference: %2$s', 'bhela-booking' ),
						(int) $ok['made'],
						'<code>' . esc_html( $ok['batch'] ) . '</code>'
					);
					?>
					<?php if ( ! empty( $ok['fail'] ) ) : ?>
						<br><strong><?php
							printf(
								/* translators: %d: rows that failed */
								esc_html__( '%d rows could not be written — check the ledger before re-running.', 'bhela-booking' ),
								(int) $ok['fail']
							);
						?></strong>
					<?php endif; ?>
					<br><span class="description"><?php esc_html_e( 'Keep that reference. If the file turns out to be wrong, these rows are reversed with contra entries — never deleted.', 'bhela-booking' ); ?></span>
				</p>
			</div>
		<?php endif; ?>

		<?php
		$data = $token ? bhela_bm_settle_import_staged( $token ) : null;
		if ( 'map' === $step && $data ) :
			$header = $data['rows'][0];
			$map    = bhela_bm_settle_import_map( $header );
			$plan   = bhela_bm_settle_import_plan( $data['rows'], $map );
			?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'Step 2 — which column is which', 'bhela-booking' ); ?></h2>
				<p class="bha-set__lead">
					<?php
					printf(
						/* translators: %s: uploaded file name */
						esc_html__( 'Read from %s. The guesses below are only guesses — check them.', 'bhela-booking' ),
						'<strong>' . esc_html( $data['name'] ) . '</strong>'
					);
					?>
				</p>
				<form method="post" action="<?php echo esc_url( bhela_bm_settle_import_url( array( 'step' => 'map', 'token' => $token ) ) ); ?>">
					<table class="form-table">
						<?php foreach ( bhela_bm_settle_import_fields() as $field => $def ) : ?>
							<tr>
								<th><?php echo esc_html( $def['label'] ); ?><?php echo empty( $def['required'] ) ? '' : ' *'; ?></th>
								<td>
									<select name="map[<?php echo esc_attr( $field ); ?>]">
										<option value=""><?php esc_html_e( '— not in this file —', 'bhela-booking' ); ?></option>
										<?php foreach ( $header as $i => $h ) : ?>
											<option value="<?php echo esc_attr( $i ); ?>" <?php selected( (string) $map[ $field ], (string) $i ); ?>>
												<?php echo esc_html( '' === trim( (string) $h ) ? sprintf( __( 'Column %d', 'bhela-booking' ), $i + 1 ) : $h ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<p><button class="button"><?php esc_html_e( 'Re-check with this mapping', 'bhela-booking' ); ?></button></p>
				</form>
			</div>

			<div class="bha-cards">
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Rows it would write', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( (string) count( $plan['ok'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Investors', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( (string) $plan['people'] ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Total paid', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $plan['paid'] ) ); ?></span></div>
				<?php if ( $plan['profit'] ) : ?>
					<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Declared profit', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $plan['profit'] ) ); ?></span></div>
				<?php endif; ?>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Refused', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( (string) count( $plan['bad'] ) ); ?></span></div>
			</div>

			<?php if ( $plan['bad'] ) : ?>
				<div class="bha-panel">
					<h2><?php esc_html_e( 'Refused rows', 'bhela-booking' ); ?></h2>
					<p class="description"><?php esc_html_e( 'These are skipped. Nothing is guessed at — fix the file and upload it again, or import the rest and handle these by hand.', 'bhela-booking' ); ?></p>
					<table class="widefat striped bha-table">
						<thead><tr>
							<th style="width:80px"><?php esc_html_e( 'Line', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'Value', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'Why', 'bhela-booking' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( array_slice( $plan['bad'], 0, 100 ) as $b ) : ?>
							<tr>
								<td class="bha-plain"><?php echo esc_html( (string) $b['line'] ); ?></td>
								<td><?php echo esc_html( $b['raw'] ); ?></td>
								<td><?php echo esc_html( $b['why'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<div class="bha-panel">
				<h2><?php esc_html_e( 'Step 3 — the dry run', 'bhela-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Exactly what Commit will write, row for row. Nothing has been saved yet.', 'bhela-booking' ); ?></p>
				<table class="widefat striped bha-table">
					<thead><tr>
						<th style="width:70px"><?php esc_html_e( 'Line', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Date', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Paid', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Declared', 'bhela-booking' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( array_slice( $plan['ok'], 0, 200 ) as $r ) : ?>
						<tr>
							<td class="bha-plain"><?php echo esc_html( (string) $r['line'] ); ?></td>
							<td><?php echo esc_html( $r['name'] ); ?></td>
							<td class="bha-plain"><?php echo esc_html( mysql2date( 'j M Y', $r['date'] ) ); ?></td>
							<td class="bha-num"><?php echo esc_html( $r['amount'] ? bhela_bm_money( $r['amount'] ) : '—' ); ?></td>
							<td class="bha-num"><?php echo esc_html( $r['profit'] ? bhela_bm_money( $r['profit'] ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( count( $plan['ok'] ) > 200 ) : ?>
					<p class="description"><?php
						printf(
							/* translators: %d: total rows */
							esc_html__( 'Showing the first 200. All %d will be written.', 'bhela-booking' ),
							count( $plan['ok'] )
						);
					?></p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'bhela_bm_settle_import_commit' ); ?>
					<input type="hidden" name="action" value="bhela_bm_settle_import_commit">
					<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
					<?php foreach ( $map as $field => $col ) : ?>
						<input type="hidden" name="map[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( (string) $col ); ?>">
					<?php endforeach; ?>
					<p>
						<button class="button button-primary" <?php disabled( empty( $plan['ok'] ) ); ?>>
							<?php
							printf(
								/* translators: %d: rows */
								esc_html__( 'Commit %d rows to the ledger', 'bhela-booking' ),
								count( $plan['ok'] )
							);
							?>
						</button>
					</p>
					<p class="description"><?php esc_html_e( 'These become ordinary ledger entries. They cannot be deleted afterwards — a wrong batch is corrected with reversals, using the batch reference shown after the import.', 'bhela-booking' ); ?></p>
				</form>
			</div>

		<?php else : ?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'Step 1 — the file', 'bhela-booking' ); ?></h2>
				<p class="bha-set__lead"><?php esc_html_e( 'A CSV with one line per payment. An investor with three payments has three lines; one with a single total has one. The first row is the column headings.', 'bhela-booking' ); ?></p>
				<table class="widefat striped bha-table">
					<thead><tr>
						<th><?php esc_html_e( 'Column', 'bhela-booking' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Needed', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'bhela-booking' ); ?></th>
					</tr></thead>
					<tbody>
						<tr><td><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></td><td>✔</td><td><?php esc_html_e( 'Investor ID, mobile number, or the exact name. A value matching two records is refused rather than guessed.', 'bhela-booking' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Date', 'bhela-booking' ); ?></td><td>✔</td><td>YYYY-MM-DD</td></tr>
						<tr><td><?php esc_html_e( 'Amount paid', 'bhela-booking' ); ?></td><td>✔</td><td><?php esc_html_e( 'Taka. Commas and a ৳ sign are fine.', 'bhela-booking' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Declared profit', 'bhela-booking' ); ?></td><td>—</td><td><?php esc_html_e( 'Only for periods where the profit was never distributed in the system. Without it those investors read as having taken more than they were owed.', 'bhela-booking' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Method / Reference / Note', 'bhela-booking' ); ?></td><td>—</td><td><?php esc_html_e( 'Free text, carried onto the ledger row.', 'bhela-booking' ); ?></td></tr>
					</tbody>
				</table>

				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'bhela_bm_settle_import_upload' ); ?>
					<input type="hidden" name="action" value="bhela_bm_settle_import_upload">
					<p><input type="file" name="bhela_settle_csv" accept=".csv,text/csv,text/plain" required></p>
					<p><button class="button button-primary"><?php esc_html_e( 'Read the file', 'bhela-booking' ); ?></button></p>
					<p class="description"><?php esc_html_e( 'The file is read here and now and is never copied onto the server. Reading it writes nothing — you will see what it would do before anything is saved.', 'bhela-booking' ); ?></p>
				</form>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
