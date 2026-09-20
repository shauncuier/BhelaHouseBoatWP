<?php
/**
 * 💼 Capital — the dated contribution register, and the file it is filled from.
 *
 * The register's own `_bhela_inv_amount` is one number with no date behind it, so the
 * office types the history in once and every Investment Certificate afterwards can
 * answer "কোন বছরে কত". Most of that history lives in a spreadsheet, hence the
 * importer.
 *
 * The importer is the settlement one's flow — upload → map → dry run → commit, the file
 * parsed at upload and never written into uploads/, rows staged in a site transient
 * keyed by a random token and scoped to the uploading user — and it **reuses**
 * `bhela_bm_settle_import_investor()` rather than carrying a second copy of the
 * ambiguity rules. Two implementations of "which investor is this cell" is how one
 * investor's money ends up on another's statement (§13.22, §13.23).
 *
 * It lives on the Capital screen rather than taking a menu row of its own: the
 * Investors menu is already the longest of the five, and importing capital is a thing
 * you do while looking at the capital register, not a separate errand.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * Shared bits
 * ========================================================= */

function bhela_bm_capital_url( $args = array() ) {
	return bhela_bm_admin_url( 'bhela-bm-capital', $args );
}

function bhela_bm_capital_bail( $message ) {
	set_transient( 'bhela_bm_capital_err_' . get_current_user_id(), $message, 60 );
	wp_safe_redirect( bhela_bm_capital_url() );
	exit;
}

function bhela_bm_capital_done( $message ) {
	set_transient( 'bhela_bm_capital_ok_' . get_current_user_id(), $message, 60 );
	wp_safe_redirect( bhela_bm_capital_url() );
	exit;
}

/** The columns a capital file may carry. */
function bhela_bm_capital_import_fields() {
	return array(
		'investor' => array( 'label' => __( 'Investor (ID, mobile or name)', 'bhela-booking' ), 'required' => true ),
		'date'     => array( 'label' => __( 'Date', 'bhela-booking' ), 'required' => true ),
		'amount'   => array( 'label' => __( 'Amount invested', 'bhela-booking' ), 'required' => true ),
		'shares'   => array( 'label' => __( 'Shares (optional)', 'bhela-booking' ) ),
		'method'   => array( 'label' => __( 'Method', 'bhela-booking' ) ),
		'ref'      => array( 'label' => __( 'Reference', 'bhela-booking' ) ),
		'note'     => array( 'label' => __( 'Note', 'bhela-booking' ) ),
	);
}

function bhela_bm_capital_import_key( $token ) {
	return 'bhela_bm_capital_import_' . preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
}

/** The staged rows, refusing another user's staging. */
function bhela_bm_capital_import_staged( $token ) {
	$data = get_site_transient( bhela_bm_capital_import_key( $token ) );
	if ( ! is_array( $data ) || empty( $data['rows'] ) ) {
		return null;
	}
	if ( (int) ( $data['user'] ?? 0 ) !== get_current_user_id() ) {
		return null;
	}
	return $data;
}

/** Which field a header probably means. A convenience — the owner always confirms. */
function bhela_bm_capital_import_guess( $header ) {
	$h = strtolower( trim( (string) $header ) );
	if ( '' === $h ) {
		return '';
	}
	$map = array(
		'investor' => array( 'investor', 'name', 'নাম', 'বিনিয়োগকারী', 'investor id', 'id', 'code', 'mobile', 'phone', 'মোবাইল' ),
		'date'     => array( 'date', 'on', 'year', 'তারিখ', 'বছর' ),
		'amount'   => array( 'amount', 'invested', 'investment', 'capital', 'taka', 'tk', 'পরিমাণ', 'টাকা', 'বিনিয়োগ' ),
		'shares'   => array( 'share', 'shares', 'unit', 'শেয়ার' ),
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
 * Turn staged rows plus a mapping into a plan.
 *
 * Pure: it writes nothing. The dry run renders this and the commit walks exactly the
 * same structure, so what is approved on screen is what is written.
 */
function bhela_bm_capital_import_plan( $rows, $map ) {
	$plan   = array( 'ok' => array(), 'bad' => array(), 'amount' => 0, 'shares' => 0, 'people' => array() );
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
		// Strip thousands separators and a taka sign — a spreadsheet exports
		// "৳1,20,000" and (int) on that is 1.
		$clean = function ( $v ) {
			return (int) preg_replace( '/[^0-9\-]/', '', (string) $v );
		};

		$line   = $n + 1;
		$who    = bhela_bm_settle_import_investor( $get( 'investor' ) );
		$date   = bhela_bm_report_date( $get( 'date' ) );
		$amount = $clean( $get( 'amount' ) );
		$shares = max( 0, $clean( $get( 'shares' ) ) );

		if ( $who['error'] ) {
			$plan['bad'][] = array( 'line' => $line, 'raw' => $get( 'investor' ), 'why' => $who['error'] );
			continue;
		}
		if ( '' === $date ) {
			// Not defaulted to today. The date is the entire reason this record
			// exists — see bhela_bm_capital_add().
			$plan['bad'][] = array( 'line' => $line, 'raw' => $get( 'date' ), 'why' => __( 'that date cannot be read — a capital row without a date is not worth recording', 'bhela-booking' ) );
			continue;
		}
		if ( $amount <= 0 ) {
			$plan['bad'][] = array( 'line' => $line, 'raw' => $get( 'amount' ), 'why' => __( 'no amount on the row', 'bhela-booking' ) );
			continue;
		}

		$plan['ok'][] = array(
			'line'     => $line,
			'investor' => $who['id'],
			'name'     => get_the_title( $who['id'] ),
			'date'     => $date,
			'amount'   => $amount,
			'shares'   => $shares,
			'method'   => sanitize_text_field( $get( 'method' ) ),
			'ref'      => sanitize_text_field( $get( 'ref' ) ),
			'note'     => sanitize_textarea_field( $get( 'note' ) ),
		);
		$plan['amount'] += $amount;
		$plan['shares'] += $shares;
		$plan['people'][ $who['id'] ] = true;
	}
	$plan['people'] = count( $plan['people'] );
	return $plan;
}

/* =========================================================
 * HANDLERS
 * ========================================================= */

/** One row, typed in by hand. */
function bhela_bm_capital_add_post() {
	if ( ! current_user_can( 'bhela_investor_capital' ) ) {
		wp_die( esc_html__( 'You are not allowed to record capital.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_capital_add' );

	$r = bhela_bm_capital_add( array(
		'investor' => isset( $_POST['investor'] ) ? (int) $_POST['investor'] : 0,
		'date'     => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
		'amount'   => isset( $_POST['amount'] ) ? (int) preg_replace( '/[^0-9\-]/', '', wp_unslash( $_POST['amount'] ) ) : 0,
		'shares'   => isset( $_POST['shares'] ) ? (int) $_POST['shares'] : 0,
		'method'   => isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : '',
		'ref'      => isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : '',
		'note'     => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
	) );

	if ( is_wp_error( $r ) ) {
		bhela_bm_capital_bail( $r->get_error_message() );
	}
	bhela_bm_capital_done( __( 'মূলধন রেকর্ড করা হয়েছে।', 'bhela-booking' ) );
}
add_action( 'admin_post_bhela_bm_capital_add', 'bhela_bm_capital_add_post' );

/** Void a row. Not a delete — see includes/capital.php. */
function bhela_bm_capital_void_post() {
	if ( ! current_user_can( 'bhela_investor_capital' ) ) {
		wp_die( esc_html__( 'You are not allowed to record capital.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_capital_void' );

	$r = bhela_bm_capital_void(
		isset( $_POST['row'] ) ? (int) $_POST['row'] : 0,
		isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : ''
	);
	if ( is_wp_error( $r ) ) {
		bhela_bm_capital_bail( $r->get_error_message() );
	}
	bhela_bm_capital_done( __( 'রেকর্ডটি বাতিল হিসেবে চিহ্নিত হয়েছে।', 'bhela-booking' ) );
}
add_action( 'admin_post_bhela_bm_capital_void', 'bhela_bm_capital_void_post' );

/** Parse the file and stage it. Nothing is written. */
function bhela_bm_capital_import_upload() {
	if ( ! current_user_can( 'bhela_investor_capital' ) ) {
		wp_die( esc_html__( 'You are not allowed to record capital.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_capital_import_upload' );

	$limits = bhela_bm_settle_import_limits();
	$file   = $_FILES['bhela_capital_csv'] ?? null;

	if ( ! $file || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		bhela_bm_capital_bail( __( 'No file arrived. Choose a CSV and try again.', 'bhela-booking' ) );
	}
	if ( (int) $file['size'] > $limits['bytes'] ) {
		bhela_bm_capital_bail( __( 'That file is larger than 2 MB — is it definitely a CSV and not a workbook?', 'bhela-booking' ) );
	}
	$ext = strtolower( (string) pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
		bhela_bm_capital_bail( __( 'Save the sheet as CSV first — an .xlsx workbook cannot be read directly.', 'bhela-booking' ) );
	}

	$fh = fopen( $file['tmp_name'], 'r' );
	if ( ! $fh ) {
		bhela_bm_capital_bail( __( 'That file could not be opened.', 'bhela-booking' ) );
	}
	// Before the parse, not after: Excel writes a BOM and by the time the cells exist
	// the first column's header has already been mangled.
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
		bhela_bm_capital_bail( __( 'That file has no data rows in it.', 'bhela-booking' ) );
	}

	$token = wp_generate_password( 20, false );
	set_site_transient( bhela_bm_capital_import_key( $token ), array(
		'rows' => $rows,
		'user' => get_current_user_id(),
		'name' => sanitize_file_name( (string) $file['name'] ),
	), 2 * HOUR_IN_SECONDS );

	wp_safe_redirect( bhela_bm_capital_url( array( 'step' => 'map', 'token' => $token ) ) );
	exit;
}
add_action( 'admin_post_bhela_bm_capital_import_upload', 'bhela_bm_capital_import_upload' );

/** Write exactly what the dry run showed. */
function bhela_bm_capital_import_commit() {
	if ( ! current_user_can( 'bhela_investor_capital' ) ) {
		wp_die( esc_html__( 'You are not allowed to record capital.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_capital_import_commit' );

	$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	$data  = bhela_bm_capital_import_staged( $token );
	if ( ! $data ) {
		bhela_bm_capital_bail( __( 'That upload has expired. Start again.', 'bhela-booking' ) );
	}

	$map = array();
	foreach ( array_keys( bhela_bm_capital_import_fields() ) as $field ) {
		$raw = isset( $_POST['map'][ $field ] ) ? sanitize_text_field( wp_unslash( $_POST['map'][ $field ] ) ) : '';
		$map[ $field ] = ( '' === $raw ) ? '' : (int) $raw;
	}
	$plan = bhela_bm_capital_import_plan( $data['rows'], $map );

	if ( ! $plan['ok'] ) {
		bhela_bm_capital_bail( __( 'Nothing in that file could be imported. Check the column mapping.', 'bhela-booking' ) );
	}

	// One reference on every row, so a wrong file can be found again. A capital row is
	// voided rather than deleted, and the batch is how you find the set to void.
	$batch = 'CAP-' . gmdate( 'Ymd-His' ) . '-' . strtoupper( wp_generate_password( 4, false ) );
	$made  = 0;
	$fail  = 0;

	foreach ( $plan['ok'] as $r ) {
		$row = bhela_bm_capital_add( array(
			'investor' => $r['investor'],
			'date'     => $r['date'],
			'amount'   => $r['amount'],
			'shares'   => $r['shares'],
			'method'   => $r['method'],
			'ref'      => $r['ref'] ? $batch . ' / ' . $r['ref'] : $batch,
			'note'     => $r['note'],
		) );
		is_wp_error( $row ) ? $fail++ : $made++;
	}

	// Spend the staging. A refresh must not write the batch a second time.
	delete_site_transient( bhela_bm_capital_import_key( $token ) );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'capital_import',
		'object_type' => 'capital',
		'object_id'   => 0,
		'object_ref'  => $batch,
		'field'       => 'rows',
		'new_value'   => (string) $made,
		'reason'      => sprintf(
			/* translators: 1: file name, 2: investors touched, 3: failed rows */
			__( 'Imported from %1$s across %2$d investors. %3$d rows failed.', 'bhela-booking' ),
			$data['name'],
			(int) $plan['people'],
			(int) $fail
		),
	) );

	bhela_bm_capital_done( sprintf(
		/* translators: 1: rows written, 2: batch reference, 3: failed rows */
		__( '%1$d টি রেকর্ড যোগ হয়েছে (ব্যাচ %2$s)। %3$d টি সারি বাদ পড়েছে।', 'bhela-booking' ),
		$made,
		$batch,
		$fail
	) );
}
add_action( 'admin_post_bhela_bm_capital_import_commit', 'bhela_bm_capital_import_commit' );

/* =========================================================
 * THE SCREEN
 * ========================================================= */

function bhela_bm_capital_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'capital' ),
		__( 'Contributions', 'bhela-booking' ),
		'💼 ' . __( 'Contributions', 'bhela-booking' ),
		'bhela_investor_capital',
		'bhela-bm-capital',
		'bhela_bm_capital_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_capital_menu', 21 );

/** The mapping the screen is working with — posted, or guessed from the header. */
function bhela_bm_capital_import_map( $header ) {
	$map = array();
	foreach ( array_keys( bhela_bm_capital_import_fields() ) as $field ) {
		if ( isset( $_POST['map'][ $field ] ) ) {
			$raw           = sanitize_text_field( wp_unslash( $_POST['map'][ $field ] ) );
			$map[ $field ] = ( '' === $raw ) ? '' : (int) $raw;
			continue;
		}
		$map[ $field ] = '';
		foreach ( $header as $i => $h ) {
			if ( $field === bhela_bm_capital_import_guess( $h ) ) {
				$map[ $field ] = $i;
				break;
			}
		}
	}
	return $map;
}

function bhela_bm_capital_page() {
	if ( ! current_user_can( 'bhela_investor_capital' ) ) {
		wp_die( esc_html__( 'You are not allowed to record capital.', 'bhela-booking' ) );
	}

	$money    = 'bhela_bm_money';
	$investor = isset( $_GET['investor'] ) ? (int) $_GET['investor'] : 0;
	$step     = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
	$token    = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	$err      = get_transient( 'bhela_bm_capital_err_' . get_current_user_id() );
	$ok       = get_transient( 'bhela_bm_capital_ok_' . get_current_user_id() );
	delete_transient( 'bhela_bm_capital_err_' . get_current_user_id() );
	delete_transient( 'bhela_bm_capital_ok_' . get_current_user_id() );

	$rows  = bhela_bm_capital_rows( $investor );
	$years = $investor ? bhela_bm_capital_years( $investor ) : null;
	$drift = $investor ? bhela_bm_capital_drift( $investor ) : null;
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'💼',
			__( 'Capital', 'bhela-booking' ),
			__( 'কে কোন বছরে কত টাকা দিয়েছেন। রেজিস্টারে মোট টাকাটা আছে, কিন্তু তারিখ নেই — সনদে বছরভিত্তিক হিসাব দেখাতে হলে এখানে তুলতে হবে।', 'bhela-booking' )
		);
		?>

		<?php if ( $err ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
		<?php endif; ?>
		<?php if ( $ok ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $ok ); ?></p></div>
		<?php endif; ?>

		<form method="get" class="bha-bar">
			<?php // No hidden post_type input: this page hangs under admin.php, and carrying one would submit the filter to the Posts list (§13.14). ?>
			<input type="hidden" name="page" value="bhela-bm-capital">
			<label>
				<?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?>
				<select name="investor">
					<option value="0"><?php esc_html_e( 'সবাই', 'bhela-booking' ); ?></option>
					<?php foreach ( bhela_bm_investors() as $id ) : ?>
						<option value="<?php echo (int) $id; ?>" <?php selected( $investor, (int) $id ); ?>>
							<?php echo esc_html( get_the_title( $id ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<button class="button"><?php esc_html_e( 'দেখুন', 'bhela-booking' ); ?></button>
		</form>

		<?php if ( $years ) : ?>
			<div class="bha-cards">
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'তারিখসহ রেকর্ড', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $years['dated'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'তারিখ রেকর্ড নেই', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $years['undated'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'রেজিস্টারে মোট', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $years['register'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'শেয়ার', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( (string) $years['shares'] ); ?></span></div>
			</div>

			<?php if ( $drift && $drift['over'] ) : ?>
				<?php
				// Reports, never corrects (§13.30's contract). Either a row was typed
				// twice or the register is short — a person decides which, and rewriting
				// either one to make the screen tidy would hide whichever was right.
				?>
				<div class="bha-callout bha-callout--attention">
					<p>
						<?php
						printf(
							/* translators: 1: dated total, 2: register total, 3: the gap */
							esc_html__( 'তারিখসহ রেকর্ডের যোগফল (%1$s) রেজিস্টারের মোট বিনিয়োগের (%2$s) চেয়ে %3$s বেশি। হয় কোনো সারি দুবার তোলা হয়েছে, নয়তো রেজিস্টারের সংখ্যাটি পুরোনো। এখানে কিছু নিজে থেকে ঠিক করা হয় না — কোনটি ভুল তা আপনাকেই ঠিক করতে হবে।', 'bhela-booking' ),
							esc_html( $money( $drift['dated'] ) ),
							esc_html( $money( $drift['register'] ) ),
							esc_html( $money( abs( $drift['gap'] ) ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'রেকর্ড', 'bhela-booking' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'তারিখ', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'পরিমাণ', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'শেয়ার', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'মাধ্যম', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'রেফারেন্স', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'উৎস', 'bhela-booking' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'কোনো রেকর্ড নেই।', 'bhela-booking' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $r ) : ?>
						<tr<?php echo $r['void'] ? ' style="opacity:.55"' : ''; ?>>
							<td><?php echo esc_html( mysql2date( 'd M Y', $r['date'] ) ); ?></td>
							<td><?php echo esc_html( $r['name'] ); ?></td>
							<td class="bha-num"><?php echo esc_html( $money( $r['amount'] ) ); ?></td>
							<td class="bha-num bha-plain"><?php echo $r['shares'] ? esc_html( (string) $r['shares'] ) : '—'; ?></td>
							<td><?php echo esc_html( $r['method'] ); ?></td>
							<td><?php echo esc_html( $r['ref'] ); ?></td>
							<td>
								<?php
								echo 'issue' === $r['source']
									? esc_html__( 'শেয়ার ইস্যু', 'bhela-booking' )
									: esc_html__( 'রেকর্ড', 'bhela-booking' );
								?>
							</td>
							<td>
								<?php if ( $r['void'] ) : ?>
									<em><?php echo esc_html( __( 'বাতিল — ', 'bhela-booking' ) . $r['void_reason'] ); ?></em>
								<?php elseif ( 'issue' === $r['source'] ) : ?>
									<?php
									// A share issue is read here, not owned here. It is
									// corrected on its own screen, or it would have two
									// places to be undone from.
									?>
									<em><?php esc_html_e( 'শেয়ার ইস্যু স্ক্রিনে', 'bhela-booking' ); ?></em>
								<?php else : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px">
										<?php wp_nonce_field( 'bhela_bm_capital_void' ); ?>
										<input type="hidden" name="action" value="bhela_bm_capital_void">
										<input type="hidden" name="row" value="<?php echo (int) $r['id']; ?>">
										<input type="text" name="reason" required placeholder="<?php esc_attr_e( 'বাতিলের কারণ', 'bhela-booking' ); ?>">
										<button class="button button-small"><?php esc_html_e( 'বাতিল', 'bhela-booking' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">
				<?php esc_html_e( 'একটি রেকর্ড মুছে ফেলা যায় না — ভুল হলে কারণসহ বাতিল করতে হয়, আর বাতিল রেকর্ড কোনো হিসাবে যোগ হয় না।', 'bhela-booking' ); ?>
			</p>
		</div>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'নতুন রেকর্ড', 'bhela-booking' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bhela_bm_capital_add' ); ?>
				<input type="hidden" name="action" value="bhela_bm_capital_add">
				<table class="form-table">
					<tr>
						<th><label for="cap-investor"><?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?></label></th>
						<td>
							<select name="investor" id="cap-investor" required>
								<option value=""><?php esc_html_e( '— বেছে নিন —', 'bhela-booking' ); ?></option>
								<?php foreach ( bhela_bm_investors() as $id ) : ?>
									<option value="<?php echo (int) $id; ?>" <?php selected( $investor, (int) $id ); ?>>
										<?php echo esc_html( get_the_title( $id ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="cap-date"><?php esc_html_e( 'তারিখ', 'bhela-booking' ); ?></label></th>
						<td>
							<input type="date" name="date" id="cap-date" required>
							<p class="description"><?php esc_html_e( 'তারিখ ছাড়া রেকর্ড রাখা হয় না — এই রেকর্ডের মূল বিষয়ই তারিখ।', 'bhela-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cap-amount"><?php esc_html_e( 'পরিমাণ', 'bhela-booking' ); ?></label></th>
						<td><input type="text" name="amount" id="cap-amount" inputmode="numeric" required></td>
					</tr>
					<tr>
						<th><label for="cap-shares"><?php esc_html_e( 'শেয়ার', 'bhela-booking' ); ?></label></th>
						<td>
							<input type="number" name="shares" id="cap-shares" min="0" step="1" value="0">
							<p class="description"><?php esc_html_e( 'এখানে শেয়ার লিখলে তা শুধু এই রেকর্ডে থাকে — রেজিস্টারের শেয়ার সংখ্যা বদলায় না। শেয়ার ইস্যু করতে 🪙 Share Issue ব্যবহার করুন।', 'bhela-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cap-method"><?php esc_html_e( 'মাধ্যম', 'bhela-booking' ); ?></label></th>
						<td><input type="text" name="method" id="cap-method" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="cap-ref"><?php esc_html_e( 'রেফারেন্স', 'bhela-booking' ); ?></label></th>
						<td><input type="text" name="ref" id="cap-ref" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="cap-note"><?php esc_html_e( 'মন্তব্য', 'bhela-booking' ); ?></label></th>
						<td><textarea name="note" id="cap-note" class="large-text" rows="2"></textarea></td>
					</tr>
				</table>
				<p><button class="button button-primary"><?php esc_html_e( 'রেকর্ড করুন', 'bhela-booking' ); ?></button></p>
			</form>
		</div>

		<?php bhela_bm_capital_import_block( $step, $token ); ?>
	</div>
	<?php
}

/** Upload → map → dry run → commit, as a section of the Capital screen. */
function bhela_bm_capital_import_block( $step, $token ) {
	$money  = 'bhela_bm_money';
	$fields = bhela_bm_capital_import_fields();
	$data   = ( 'map' === $step && $token ) ? bhela_bm_capital_import_staged( $token ) : null;

	if ( ! $data ) {
		?>
		<div class="bha-panel">
			<h2><?php esc_html_e( 'ফাইল থেকে ইমপোর্ট — ধাপ ১', 'bhela-booking' ); ?></h2>
			<p class="bha-set__lead"><?php esc_html_e( 'প্রতি লাইনে একটি বিনিয়োগ। প্রথম সারিটি কলামের নাম।', 'bhela-booking' ); ?></p>
			<table class="widefat striped bha-table">
				<thead><tr>
					<th><?php esc_html_e( 'Column', 'bhela-booking' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Needed', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Notes', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
					<tr><td><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></td><td>✔</td><td><?php esc_html_e( 'Investor ID, mobile number, or the exact name. A value matching two records is refused rather than guessed.', 'bhela-booking' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Date', 'bhela-booking' ); ?></td><td>✔</td><td><?php esc_html_e( 'YYYY-MM-DD. A row with no readable date is refused — the date is the whole point of this record.', 'bhela-booking' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Amount invested', 'bhela-booking' ); ?></td><td>✔</td><td><?php esc_html_e( 'Taka. Commas and a ৳ sign are fine.', 'bhela-booking' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Shares', 'bhela-booking' ); ?></td><td>—</td><td><?php esc_html_e( 'Recorded on the row only. It does not change the register share count — that is what the Share Issue screen is for.', 'bhela-booking' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Method / Reference / Note', 'bhela-booking' ); ?></td><td>—</td><td><?php esc_html_e( 'Free text, carried onto the record.', 'bhela-booking' ); ?></td></tr>
				</tbody>
			</table>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bhela_bm_capital_import_upload' ); ?>
				<input type="hidden" name="action" value="bhela_bm_capital_import_upload">
				<p><input type="file" name="bhela_capital_csv" accept=".csv,text/csv,text/plain" required></p>
				<p><button class="button button-primary"><?php esc_html_e( 'ফাইল পড়ুন', 'bhela-booking' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'ফাইলটি এখানেই পড়া হয়, সার্ভারে কপি হয় না। পড়লে কিছুই লেখা হয় না — কমিটের আগে আপনি দেখে নেবেন কী লেখা হবে।', 'bhela-booking' ); ?></p>
			</form>
		</div>
		<?php
		return;
	}

	$header = $data['rows'][0];
	$map    = bhela_bm_capital_import_map( $header );
	$plan   = bhela_bm_capital_import_plan( $data['rows'], $map );
	?>
	<div class="bha-panel">
		<h2><?php esc_html_e( 'ধাপ ২ — কোন কলাম কোনটি', 'bhela-booking' ); ?></h2>
		<p class="bha-set__lead">
			<?php
			printf(
				/* translators: %s: uploaded file name */
				esc_html__( '%s থেকে পড়া হয়েছে। নিচের অনুমানগুলো শুধুই অনুমান — মিলিয়ে নিন।', 'bhela-booking' ),
				'<strong>' . esc_html( $data['name'] ) . '</strong>'
			);
			?>
		</p>
		<?php
		// Posts back to the page, not to admin-post.php: re-mapping is a look, not a
		// write, and a re-check button sharing a form with Commit is one stray Enter
		// key away from writing the batch.
		?>
		<form method="post" action="<?php echo esc_url( bhela_bm_capital_url( array( 'step' => 'map', 'token' => $token ) ) ); ?>">
			<table class="form-table">
				<?php foreach ( $fields as $field => $def ) : ?>
					<tr>
						<th><?php echo esc_html( $def['label'] ); ?><?php echo empty( $def['required'] ) ? '' : ' *'; ?></th>
						<td>
							<select name="map[<?php echo esc_attr( $field ); ?>]">
								<option value=""><?php esc_html_e( '— এই ফাইলে নেই —', 'bhela-booking' ); ?></option>
								<?php foreach ( $header as $i => $h ) : ?>
									<option value="<?php echo esc_attr( $i ); ?>" <?php selected( (string) $map[ $field ], (string) $i ); ?>>
										<?php echo esc_html( '' === trim( (string) $h ) ? sprintf( __( 'কলাম %d', 'bhela-booking' ), $i + 1 ) : $h ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p><button class="button"><?php esc_html_e( 'এই ম্যাপিং দিয়ে আবার দেখুন', 'bhela-booking' ); ?></button></p>
		</form>
	</div>

	<div class="bha-cards">
		<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'যত সারি লেখা হবে', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( (string) count( $plan['ok'] ) ); ?></span></div>
		<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'বিনিয়োগকারী', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( (string) $plan['people'] ); ?></span></div>
		<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'মোট', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $money( $plan['amount'] ) ); ?></span></div>
		<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'বাদ পড়েছে', 'bhela-booking' ); ?></span><span class="bha-card__value bha-plain"><?php echo esc_html( (string) count( $plan['bad'] ) ); ?></span></div>
	</div>

	<?php if ( $plan['bad'] ) : ?>
		<div class="bha-panel">
			<h2><?php esc_html_e( 'যে সারিগুলো লেখা হবে না', 'bhela-booking' ); ?></h2>
			<p class="description"><?php esc_html_e( 'এগুলো বাদ যাবে। কিছুই অনুমান করা হয় না — ফাইল ঠিক করে আবার তুলুন, অথবা বাকিগুলো ইমপোর্ট করে এগুলো হাতে তুলুন।', 'bhela-booking' ); ?></p>
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
		<h2><?php esc_html_e( 'ধাপ ৩ — ড্রাই রান', 'bhela-booking' ); ?></h2>
		<p class="description"><?php esc_html_e( 'কমিট করলে ঠিক যা লেখা হবে, সারি ধরে ধরে। এখনো কিছুই সেভ হয়নি।', 'bhela-booking' ); ?></p>
		<table class="widefat striped bha-table">
			<thead><tr>
				<th style="width:70px"><?php esc_html_e( 'Line', 'bhela-booking' ); ?></th>
				<th><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></th>
				<th><?php esc_html_e( 'Date', 'bhela-booking' ); ?></th>
				<th class="bha-num"><?php esc_html_e( 'Amount', 'bhela-booking' ); ?></th>
				<th class="bha-num"><?php esc_html_e( 'Shares', 'bhela-booking' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( array_slice( $plan['ok'], 0, 200 ) as $r ) : ?>
				<tr>
					<td class="bha-plain"><?php echo esc_html( (string) $r['line'] ); ?></td>
					<td><?php echo esc_html( $r['name'] ); ?></td>
					<td class="bha-plain"><?php echo esc_html( mysql2date( 'j M Y', $r['date'] ) ); ?></td>
					<td class="bha-num"><?php echo esc_html( $money( $r['amount'] ) ); ?></td>
					<td class="bha-num bha-plain"><?php echo $r['shares'] ? esc_html( (string) $r['shares'] ) : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( count( $plan['ok'] ) > 200 ) : ?>
			<p class="description"><?php
				printf(
					/* translators: %d: total rows */
					esc_html__( 'প্রথম ২০০টি দেখানো হচ্ছে। মোট %d টি লেখা হবে।', 'bhela-booking' ),
					count( $plan['ok'] )
				);
			?></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bhela_bm_capital_import_commit' ); ?>
			<input type="hidden" name="action" value="bhela_bm_capital_import_commit">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<?php foreach ( $map as $field => $col ) : ?>
				<input type="hidden" name="map[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( (string) $col ); ?>">
			<?php endforeach; ?>
			<p>
				<button class="button button-primary" <?php disabled( empty( $plan['ok'] ) ); ?>>
					<?php
					printf(
						/* translators: %d: rows */
						esc_html__( '%d টি রেকর্ড কমিট করুন', 'bhela-booking' ),
						count( $plan['ok'] )
					);
					?>
				</button>
				<a class="button" href="<?php echo esc_url( bhela_bm_capital_url() ); ?>"><?php esc_html_e( 'বাতিল', 'bhela-booking' ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'রেকর্ডগুলো মুছে ফেলা যাবে না — ভুল ব্যাচ কারণসহ বাতিল করতে হবে, ব্যাচ রেফারেন্স ধরে।', 'bhela-booking' ); ?></p>
		</form>
	</div>
	<?php
}
