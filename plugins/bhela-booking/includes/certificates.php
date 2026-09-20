<?php
/**
 * Investor certificates — two documents, issued, versioned and frozen.
 *
 * An investor had nothing to hand anybody. The portal shows live figures and the
 * register exports a CSV; neither is a document you can give a bank, keep for a tax
 * return or put in front of an auditor. Two are issued here, against an **Investment
 * Record** rather than against a person in the abstract:
 *
 * - **Investment Certificate** — what was invested, on what terms, under which agreement.
 * - **Profit Certificate** — what a period earned, what was paid, and what is still due.
 *
 * **A certificate is a frozen snapshot, and that is the only thing that makes it worth
 * issuing.** Everything else in this plugin derives on read (§13.8) — but somebody is
 * holding this piece of paper, and a reprint that quietly shows different figures
 * because a reversal landed last month makes the document worthless and the
 * disagreement invisible. So issuing stores the whole preview array verbatim and the
 * template draws the STORED array; no reader is called at render time.
 *
 * **Corrections are versions, never edits** (the brief's §19). A wrong certificate is
 * superseded by `…-V2`, and `…-V1` keeps rendering with the number that replaced it
 * printed across it. Financial documents are not deleted here, and one that silently
 * vanished would leave somebody holding paper the office no longer acknowledges.
 *
 * Preview is pure and the commit writes exactly it — the contract
 * `bhela_bm_dist_preview()` / `_commit()` already has. What is approved on screen is
 * what is stored, which is also how §18 ("the admin cannot change the amount") is a
 * property rather than a promise: there is no editable figure anywhere on the path.
 *
 * Loaded on EVERY request: the print page is a front-end URL, exactly like the invoice.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * The post type
 * ========================================================= */

function bhela_bm_register_cert_cpt() {
	register_post_type( 'bhela_cert', array(
		'labels' => array(
			'name'          => __( 'Certificates', 'bhela-booking' ),
			'singular_name' => __( 'Certificate', 'bhela-booking' ),
		),
		// Never addressable from the front end. The print page is reached through its
		// own handler, which checks a timing-safe key or the signed-in investor.
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'show_in_rest'        => false,
		'supports'            => array( 'title' ),
		'capability_type'     => array( 'bhela_investor', 'bhela_investors' ),
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'rewrite'             => false,
	) );
}
add_action( 'init', 'bhela_bm_register_cert_cpt' );

/**
 * The two kinds, their wording and their number infix.
 *
 * The infix is part of the certificate number and is therefore FROZEN — every issued
 * document carries it. `IC` and `PC` are the client's own convention.
 */
function bhela_bm_cert_types() {
	return array(
		'investment' => array(
			'label'    => __( 'বিনিয়োগ সনদ', 'bhela-booking' ),
			'en'       => __( 'Investment Certificate', 'bhela-booking' ),
			'infix'    => 'IC',
			'template' => 'certificate-investment.php',
		),
		'profit'     => array(
			'label'    => __( 'লাভের সনদ', 'bhela-booking' ),
			'en'       => __( 'Profit Certificate', 'bhela-booking' ),
			'infix'    => 'PC',
			'template' => 'certificate-profit.php',
		),
	);
}

/** How many certificates one listing reads. Filterable. */
function bhela_bm_cert_limit() {
	return (int) apply_filters( 'bhela_bm_cert_limit', 500 );
}

/* =========================================================
 * NUMBERING AND VERSIONS
 * ========================================================= */

/** Is this number already on a certificate? */
function bhela_bm_cert_number_exists( $number ) {
	return bhela_bm_series_taken( 'bhela_cert', '_bhela_crt_number', $number );
}

/** Is this BASE already in use? The version suffix is added on top of it. */
function bhela_bm_cert_base_exists( $base ) {
	return bhela_bm_series_taken( 'bhela_cert', '_bhela_crt_base', $base );
}

/**
 * A fresh base for a type's series, e.g. BHELA-PC-2027-0001.
 *
 * The minting itself is `bhela_bm_series_number()` in core — investments, agreements
 * and both certificates draw from one counter discipline, and three copies of a mutex
 * is three chances to get one of them wrong.
 */
function bhela_bm_cert_number( $type ) {
	$types = bhela_bm_cert_types();
	if ( ! isset( $types[ $type ] ) ) {
		return '';
	}
	return bhela_bm_series_number( $types[ $type ]['infix'], 'bhela_bm_cert_base_exists' );
}

/** base + version → the number as it is printed. */
function bhela_bm_cert_versioned( $base, $version ) {
	return $base . '-V' . max( 1, (int) $version );
}

/* =========================================================
 * PREVIEW — pure
 * ========================================================= */

/**
 * Exactly what issuing would write. Writes nothing itself.
 *
 * @param string $type       investment|profit.
 * @param int    $investment Investment post id.
 * @param array  $window     from/to for a profit certificate; ignored for the other.
 * @return array|WP_Error
 */
function bhela_bm_cert_preview( $type, $investment, $window = array() ) {
	$types = bhela_bm_cert_types();
	if ( ! isset( $types[ $type ] ) ) {
		return new WP_Error( 'bad_type', __( 'সনদের ধরন সঠিক নয়।', 'bhela-booking' ) );
	}

	$inv = bhela_bm_investment( $investment );
	if ( ! $inv ) {
		return new WP_Error( 'no_investment', __( 'বিনিয়োগ রেকর্ড নির্বাচন করুন।', 'bhela-booking' ) );
	}
	if ( in_array( $inv['status'], array( 'draft', 'cancelled' ), true ) ) {
		// A draft is somebody still deciding the terms. Certifying it would put terms
		// on paper that nobody has agreed to yet.
		return new WP_Error(
			'not_active',
			__( 'খসড়া বা বাতিল বিনিয়োগের সনদ ইস্যু করা যায় না — আগে সক্রিয় করুন।', 'bhela-booking' )
		);
	}

	$investor = (int) $inv['investor'];
	$methods  = bhela_bm_profit_methods();
	$freqs    = bhela_bm_investment_freqs();
	$itypes   = bhela_bm_investment_types();
	$settings = bhela_bm_get_settings();

	$base = array(
		'type'       => $type,
		'type_label' => $types[ $type ]['label'],
		'type_en'    => $types[ $type ]['en'],

		// Who. The NID, the bank account and the nominee's NID are deliberately NOT
		// carried: a certificate travels — to a bank, an accountant, a tax file — and
		// the fields in bhela_bm_investor_secret_fields() are exactly what should not
		// travel with it.
		'investor'   => $investor,
		'name'       => get_the_title( $investor ),
		'code'       => (string) get_post_meta( $investor, '_bhela_inv_code', true ),
		'father'     => (string) get_post_meta( $investor, '_bhela_inv_father', true ),
		'address'    => (string) get_post_meta( $investor, '_bhela_inv_address', true ),

		// What
		'investment'    => (int) $inv['id'],
		'investment_id' => $inv['code'],
		'principal'     => $inv['principal'],
		'invest_date'   => $inv['date'],
		'start'         => $inv['start'],
		'maturity'      => $inv['maturity'],
		'months'        => $inv['months'],
		'inv_type'      => $itypes[ $inv['type'] ]['label'] ?? $inv['type'],
		'method'        => $inv['method'],
		'method_label'  => $methods[ $inv['method'] ]['label'] ?? $inv['method'],
		'method_en'     => $methods[ $inv['method'] ]['en'] ?? '',
		'formula'       => $methods[ $inv['method'] ]['formula'] ?? '',
		'rate'          => $inv['rate'],
		'frequency'     => $freqs[ $inv['frequency'] ]['label'] ?? $inv['frequency'],
		'day_basis'     => 'day_based' === $inv['method'] ? bhela_bm_profit_day_basis() : 0,
		'agreement_ref' => $inv['agreement_ref'],
		'inv_status'    => $inv['status_label'],

		'project'   => (string) ( $settings['business_name'] ?? 'BHELA' ),
		'generated' => current_time( 'Y-m-d' ),
	);

	if ( 'investment' === $type ) {
		if ( $base['principal'] <= 0 ) {
			return new WP_Error( 'nothing', __( 'এই বিনিয়োগের বিপরীতে কোনো প্রাপ্তি রেকর্ড নেই।', 'bhela-booking' ) );
		}
		// The receipts behind the principal, so the certificate shows its own working
		// rather than asking anyone to take the total on trust.
		$base['receipts'] = array();
		foreach ( bhela_bm_capital_rows_for_investment( $inv['id'] ) as $c ) {
			$base['receipts'][] = array(
				'date'   => $c['date'],
				'amount' => $c['amount'],
				'method' => $c['method'],
				'ref'    => $c['ref'],
			);
		}
		return $base;
	}

	return bhela_bm_cert_preview_profit( $base, $inv, $window );
}

/** The profit side: what the period earned, what was paid, what is still due. */
function bhela_bm_cert_preview_profit( $base, $inv, $window ) {
	$from = bhela_bm_report_date( $window['from'] ?? '' );
	$to   = bhela_bm_report_date( $window['to'] ?? '' );
	// Blank means the whole term, which here is a real answer rather than a sentinel:
	// the term IS the period the agreement is about.
	$from = '' === $from ? $inv['start'] : $from;
	$to   = '' === $to ? $inv['maturity'] : $to;
	if ( $to < $from ) {
		return new WP_Error( 'bad_window', __( 'শেষ তারিখ শুরুর আগে হতে পারে না।', 'bhela-booking' ) );
	}
	// Never certify profit for a period that has not finished.
	$today = current_time( 'Y-m-d' );
	$to    = $to > $today ? $today : $to;

	// What this investment earned in the window: the periods this engine has actually
	// POSTED. An unapproved calculation is not profit, and a certificate must never be
	// the first place a figure appears.
	$periods = array();
	$gross   = 0;
	foreach ( bhela_bm_profit_schedule( $inv ) as $p ) {
		if ( $p['from'] < $from || $p['to'] > $to ) {
			continue;
		}
		$row = bhela_bm_profit_posted( $p['key'] );
		if ( ! $row ) {
			continue;
		}
		$p['posted'] = $row;
		$periods[]   = $p;
		$gross      += $p['amount'];
	}

	if ( ! $periods ) {
		return new WP_Error(
			'nothing_posted',
			__( 'এই সময়ে অনুমোদিত কোনো লাভ নেই — আগে ➗ Profit স্ক্রিনে হিসাব অনুমোদন করুন।', 'bhela-booking' )
		);
	}

	// Paid and adjustments come from the investor's own ledger over the window, through
	// the one reader every other screen uses. They are per INVESTOR, not per investment:
	// a payment is money handed to a person, and nothing in the ledger says which
	// agreement it was against. Where the investor holds more than one live investment
	// the certificate SAYS SO rather than inventing an allocation between them.
	$settle = bhela_bm_settlement_investor( $base['investor'], $from, $to );
	$paid   = $settle ? $settle['paid'] : 0;
	$adjust = $settle ? $settle['adjustments'] : 0;
	$net    = $gross + $adjust;
	$due    = max( 0, $net - $paid );

	if ( $paid <= 0 ) {
		$status = array( 'key' => 'unpaid', 'en' => 'UNPAID', 'bn' => __( 'অপরিশোধিত', 'bhela-booking' ) );
	} elseif ( $paid >= $net ) {
		$status = array( 'key' => 'paid', 'en' => 'PAID', 'bn' => __( 'পরিশোধিত', 'bhela-booking' ) );
	} else {
		$status = array( 'key' => 'partial', 'en' => 'PARTIALLY PAID', 'bn' => __( 'আংশিক পরিশোধিত', 'bhela-booking' ) );
	}

	$others = 0;
	foreach ( bhela_bm_investments( $base['investor'], 'live' ) as $other ) {
		if ( (int) $other['id'] !== (int) $inv['id'] ) {
			$others++;
		}
	}

	return $base + array(
		'from'              => $from,
		'to'                => $to,
		'periods'           => $periods,
		'gross'             => $gross,
		'adjustments'       => $adjust,
		'net'               => $net,
		'paid'              => $paid,
		'due'               => $due,
		'status'            => $status,
		'other_investments' => $others,
	);
}

/* =========================================================
 * ISSUING
 * ========================================================= */

/**
 * Issue a certificate: take a number and freeze the preview.
 *
 * @param array $args type, investment, window, note, replaces, reason.
 * @return int|WP_Error
 */
function bhela_bm_cert_issue( $args ) {
	if ( ! current_user_can( 'bhela_investor_cert' ) ) {
		return new WP_Error( 'denied', __( 'সনদ ইস্যু করার অনুমতি নেই।', 'bhela-booking' ) );
	}

	$type       = sanitize_key( $args['type'] ?? '' );
	$investment = (int) ( $args['investment'] ?? 0 );
	$window     = is_array( $args['window'] ?? null ) ? $args['window'] : array();
	$replaces   = (int) ( $args['replaces'] ?? 0 );

	$snapshot = bhela_bm_cert_preview( $type, $investment, $window );
	if ( is_wp_error( $snapshot ) ) {
		return $snapshot;
	}

	// A correction keeps the base number and moves the version on. That is what makes
	// BHELA-PC-2027-0001-V2 recognisably the same document corrected, which is the whole
	// point of versioning rather than issuing an unrelated number.
	$old = $replaces ? bhela_bm_cert_data( $replaces ) : null;
	if ( $replaces && ! $old ) {
		return new WP_Error( 'no_original', __( 'যে সনদটি প্রতিস্থাপন করতে চান সেটি পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( $old && $old['type'] !== $type ) {
		return new WP_Error( 'wrong_type', __( 'ভিন্ন ধরনের সনদ প্রতিস্থাপন করা যায় না।', 'bhela-booking' ) );
	}

	$base    = $old ? $old['base'] : bhela_bm_cert_number( $type );
	$version = $old ? ( (int) $old['version'] + 1 ) : 1;
	if ( '' === $base ) {
		return new WP_Error( 'no_number', __( 'সনদ নম্বর তৈরি করা যায়নি।', 'bhela-booking' ) );
	}
	$number = bhela_bm_cert_versioned( $base, $version );
	if ( bhela_bm_cert_number_exists( $number ) ) {
		return new WP_Error( 'collision', __( 'এই নম্বরের সনদ আগেই আছে।', 'bhela-booking' ) );
	}

	$id = wp_insert_post( array(
		'post_type'   => 'bhela_cert',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%s — %s', $number, $snapshot['name'] ),
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	// The snapshot is stored WHOLE and verbatim. Nothing is recomputed at render time,
	// which is the property the whole module rests on.
	foreach ( array(
		'number'     => $number,
		'base'       => $base,
		'version'    => $version,
		'type'       => $type,
		'investor'   => (int) $snapshot['investor'],
		'investment' => (int) $snapshot['investment'],
		'from'       => (string) ( $snapshot['from'] ?? '' ),
		'to'         => (string) ( $snapshot['to'] ?? '' ),
		'note'       => sanitize_textarea_field( $args['note'] ?? '' ),
		'snapshot'   => $snapshot,
		'issued'     => current_time( 'Y-m-d' ),
		'by'         => get_current_user_id(),
		'at'         => current_time( 'mysql' ),
	) as $k => $v ) {
		bhela_bm_val_meta_write( $id, '_bhela_crt_' . $k, $v );
	}

	if ( $old ) {
		bhela_bm_cert_supersede( $replaces, (int) $id, (string) ( $args['reason'] ?? '' ) );
	}

	bhela_bm_audit( array(
		'channel'      => 'investor',
		'action'       => 'cert_issue',
		'object_type'  => 'certificate',
		'object_id'    => (int) $id,
		'object_ref'   => $number,
		'field'        => 'type',
		'new_value'    => $type,
		'approval_ref' => $snapshot['investment_id'],
		'reason'       => sanitize_textarea_field( $args['note'] ?? '' ),
	) );

	return (int) $id;
}

/**
 * Record that a newer certificate replaces this one.
 *
 * The old record is untouched and still renders: the investor may be holding it, and a
 * document that quietly stops existing is worse than one marked superseded. Both keys
 * sit outside the lock for the same reason `_bhela_val_status` does — superseding must
 * stay possible, and it is audited.
 */
function bhela_bm_cert_supersede( $id, $new_id, $reason = '' ) {
	if ( ! current_user_can( 'bhela_investor_cert' ) ) {
		return new WP_Error( 'denied', __( 'সনদ ইস্যু করার অনুমতি নেই।', 'bhela-booking' ) );
	}
	if ( 'bhela_cert' !== get_post_type( $id ) || 'bhela_cert' !== get_post_type( $new_id ) ) {
		return new WP_Error( 'not_found', __( 'সনদ পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( (int) $id === (int) $new_id ) {
		return new WP_Error( 'same', __( 'একটি সনদ নিজেকে প্রতিস্থাপন করতে পারে না।', 'bhela-booking' ) );
	}

	update_post_meta( $id, '_bhela_crt_super', (int) $new_id );
	update_post_meta( $id, '_bhela_crt_super_reason', sanitize_textarea_field( $reason ) );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'cert_supersede',
		'object_type' => 'certificate',
		'object_id'   => (int) $id,
		'object_ref'  => (string) get_post_meta( $id, '_bhela_crt_number', true ),
		'field'       => 'superseded_by',
		'new_value'   => (string) get_post_meta( $new_id, '_bhela_crt_number', true ),
		'reason'      => sanitize_textarea_field( $reason ),
	) );

	return true;
}

/* =========================================================
 * READING
 * ========================================================= */

/** One certificate, entirely from what was stored. */
function bhela_bm_cert_data( $id ) {
	if ( 'bhela_cert' !== get_post_type( $id ) ) {
		return null;
	}
	$m = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_bhela_crt_' . $k, true );
	};
	$snapshot = $m( 'snapshot' );
	if ( ! is_array( $snapshot ) ) {
		return null;
	}
	$super = (int) $m( 'super' );

	return array(
		'id'                => (int) $id,
		'number'            => (string) $m( 'number' ),
		'base'              => (string) $m( 'base' ),
		'version'           => max( 1, (int) $m( 'version' ) ),
		'type'              => (string) $m( 'type' ),
		'investor'          => (int) $m( 'investor' ),
		'investment'        => (int) $m( 'investment' ),
		'from'              => (string) $m( 'from' ),
		'to'                => (string) $m( 'to' ),
		'note'              => (string) $m( 'note' ),
		'issued'            => (string) $m( 'issued' ),
		'by'                => (int) $m( 'by' ),
		'at'                => (string) $m( 'at' ),
		'superseded'        => $super,
		'superseded_number' => $super ? (string) get_post_meta( $super, '_bhela_crt_number', true ) : '',
		'super_reason'      => (string) $m( 'super_reason' ),
		'snapshot'          => $snapshot,
	);
}

/**
 * Who prepared, checked and approved what this certificate states.
 *
 * Taken from the records' own history rather than from a box somebody types into: the
 * terms were entered by whoever created the investment, activated by whoever approved
 * it, and the document issued by whoever pressed Issue. A free-text signature field
 * would let all three say anything at all.
 */
function bhela_bm_cert_signoff( $cert ) {
	$name = function ( $user_id ) {
		$u = $user_id ? get_userdata( (int) $user_id ) : null;
		return $u ? $u->display_name : '';
	};
	$inv = (int) ( $cert['investment'] ?? 0 );
	return array(
		'prepared' => $name( $inv ? get_post_meta( $inv, '_bhela_ivm_by', true ) : 0 ),
		'verified' => $name( $inv ? get_post_meta( $inv, '_bhela_ivm_active_by', true ) : 0 ),
		'approved' => $name( $cert['by'] ?? 0 ),
	);
}

/**
 * Issued certificates, newest first.
 *
 * @param int    $investor Investor post id, or 0 for everybody.
 * @param string $type     investment|profit, or '' for both.
 * @return array[]
 */
function bhela_bm_cert_rows( $investor = 0, $type = '' ) {
	$query = array(
		'post_type'      => 'bhela_cert',
		'post_status'    => 'publish',
		'posts_per_page' => bhela_bm_cert_limit(),
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'ID',
		'order'          => 'DESC',
	);
	if ( $investor ) {
		$query['meta_key']   = '_bhela_crt_investor';
		$query['meta_value'] = (int) $investor;
	}

	$out  = array();
	$type = sanitize_key( $type );
	foreach ( get_posts( $query ) as $id ) {
		$row = bhela_bm_cert_data( $id );
		if ( ! $row ) {
			continue;
		}
		if ( $type && $row['type'] !== $type ) {
			continue;
		}
		$out[] = $row;
	}
	return $out;
}

/** The certificate behind a printed number, or null. Used by the verification page. */
function bhela_bm_cert_by_number( $number ) {
	$hit = get_posts( array(
		'post_type'      => 'bhela_cert',
		'post_status'    => 'publish',
		'posts_per_page' => 2,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_crt_number',
		'meta_value'     => (string) $number,
	) );
	// Two records on one number refuse rather than resolve. It should be impossible —
	// the minter skips a number in use — and if it ever happened, saying nothing beats
	// confirming the wrong document.
	return 1 === count( $hit ) ? bhela_bm_cert_data( (int) $hit[0] ) : null;
}

/* =========================================================
 * THE PRINT PAGE
 * ========================================================= */

/**
 * Which template renders this certificate.
 *
 * Certificates issued under the previous share-based model stored a differently shaped
 * snapshot, and four of them were already in the wild when the model changed. The new
 * templates would have thrown on every one — which breaks §13.75 more completely than a
 * wrong figure would, because a document that errors does not render at all.
 *
 * So the format is detected from the snapshot itself rather than from a stored version
 * flag: `investment_id` exists on every snapshot the current code writes and on none
 * of the old ones. A flag would have had to be back-filled onto records that are locked.
 */
function bhela_bm_cert_template( $cert ) {
	$types = bhela_bm_cert_types();
	if ( empty( $cert['snapshot']['investment_id'] ) ) {
		return 'certificate-legacy.php';
	}
	return $types[ $cert['type'] ]['template'] ?? '';
}

/** Secret key for a certificate's link — full 128-bit wp_hash, as the invoice. */
function bhela_bm_cert_key( $id ) {
	return wp_hash( 'bhela-cert-' . (int) $id . get_post_field( 'post_date', $id ) );
}

/** The shareable URL. Safe to give the investor; they may pass it to their bank. */
function bhela_bm_cert_url( $id ) {
	return add_query_arg( array(
		'bhela_cert' => (int) $id,
		'key'        => bhela_bm_cert_key( $id ),
	), home_url( '/' ) );
}

/**
 * Render when the link is visited.
 *
 * Three ways in, and no fourth: the timing-safe key, the signed-in investor the
 * certificate belongs to, or somebody holding `bhela_investors_view`. The portal uses
 * the second, so an investor's own list never has to carry a key that would work for
 * anybody who saw it.
 */
function bhela_bm_maybe_render_certificate() {
	if ( empty( $_GET['bhela_cert'] ) ) {
		return;
	}
	$id   = (int) $_GET['bhela_cert'];
	$cert = bhela_bm_cert_data( $id );
	if ( ! $cert ) {
		wp_die( esc_html__( 'Certificate not found.', 'bhela-booking' ), 404 );
	}

	$key_ok = isset( $_GET['key'] ) && hash_equals( bhela_bm_cert_key( $id ), (string) $_GET['key'] );
	$own    = function_exists( 'bhela_bm_current_investor' )
		&& $cert['investor'] > 0
		&& (int) bhela_bm_current_investor() === $cert['investor'];
	$office = current_user_can( 'bhela_investors_view' );

	if ( ! $key_ok && ! $own && ! $office ) {
		wp_die( esc_html__( 'You are not allowed to view this certificate.', 'bhela-booking' ), 403 );
	}

	// This page carries a named person's money behind a query string. Most BD hosting
	// runs a page cache that keys on the URL and may ignore query args entirely — which
	// would serve one investor's certificate to the next visitor. Say no-store rather
	// than trusting the configuration.
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );

	$types    = bhela_bm_cert_types();
	$template = bhela_bm_cert_template( $cert );
	if ( '' === $template || ! file_exists( BHELA_BM_PATH . 'templates/' . $template ) ) {
		wp_die( esc_html__( 'Certificate template missing.', 'bhela-booking' ), 500 );
	}

	$settings = bhela_bm_get_settings();
	$snap     = $cert['snapshot'];
	$signoff  = bhela_bm_cert_signoff( $cert );
	include BHELA_BM_PATH . 'templates/' . $template;
	exit;
}
add_action( 'template_redirect', 'bhela_bm_maybe_render_certificate' );
