<?php
/**
 * Investors, shares, and the arithmetic that turns one into the other.
 *
 * BHELA is funded by shares of a fixed value — 115 × ৳1,00,000 by default, and
 * changeable in Settings because a second boat or a fresh round would otherwise mean
 * editing PHP. Everything downstream (the distribution engine, the ledger, ROI) reads
 * its percentages from here, so this file is the only place that decides what a share
 * is worth.
 *
 * An investor is a CUSTOM POST TYPE rather than an option array like the agency
 * directory. That is a deliberate departure: an agency is four fields, while an
 * investor carries ~25 plus a nominee, KYC scans and a signed agreement. A CPT gives
 * attachments, capability mapping and a real editing screen; cramming that into a
 * serialised option would mean re-implementing all three badly.
 *
 * It is `private` with no REST exposure, exactly like `bhela_booking` (CLAUDE.md
 * §3.5). An investor record holds a bank account, an NID and a home address. It must
 * never be addressable from the front end.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * The post type
 * ========================================================= */

function bhela_bm_register_investor_cpt() {
	register_post_type( 'bhela_investor', array(
		'labels' => array(
			'name'          => __( 'Investors', 'bhela-booking' ),
			'singular_name' => __( 'Investor', 'bhela-booking' ),
			'menu_name'     => __( '👤 Investors', 'bhela-booking' ),
			'add_new'       => __( 'Add Investor', 'bhela-booking' ),
			'add_new_item'  => __( 'New Investor', 'bhela-booking' ),
			'edit_item'     => __( 'Investor', 'bhela-booking' ),
			'all_items'     => __( '👤 Investors', 'bhela-booking' ),
			'search_items'  => __( 'Search Investors', 'bhela-booking' ),
			'not_found'     => __( 'No investors yet.', 'bhela-booking' ),
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		// Registered under Bookings and moved to Investors on `admin_menu`, for the
		// reason set out in menu.php: `init` runs before the current user resolves,
		// so show_in_menu can never depend on who is asking.
		'show_in_menu'        => 'edit.php?post_type=bhela_booking',
		'show_in_rest'        => false,
		'supports'            => array( 'title' ),
		'capability_type'     => array( 'bhela_investor', 'bhela_investors' ),
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'rewrite'             => false,
		'menu_icon'           => 'dashicons-groups',
	) );
}
add_action( 'init', 'bhela_bm_register_investor_cpt' );

/* =========================================================
 * The record, field for field
 *
 * This registry lived in includes/investor-admin.php, which is admin-only. The public
 * registration form asks for exactly the same information, so it has to be readable on
 * a front-end request too — and a second copy of a 30-field list is a copy that drifts.
 * CLAUDE.md 13.22 is the same lesson from bhela_bm_report_date().
 * ========================================================= */

/**
 * The record, field for field as the onboarding form asks for it.
 *
 * These mirror BHELA's live "Investor Information & Nominee Declaration Form", so a
 * completed form can be typed in without anything being dropped or invented. Where
 * the form separates two things - present and permanent address, father and mother,
 * account name and number - this does too. Merging them loses information that a
 * bank transfer or a succession claim later needs exactly as it was written down.
 */
function bhela_bm_investor_fields() {
	return array(
		'identity' => array(
			'label'  => __( 'Section A - Investor Details', 'bhela-booking' ),
			'fields' => array(
				'code'      => array( 'label' => __( 'Investor ID', 'bhela-booking' ) ),
				'father'    => array( 'label' => __( 'Father name', 'bhela-booking' ) ),
				'mother'    => array( 'label' => __( 'Mother name', 'bhela-booking' ) ),
				'dob'       => array( 'label' => __( 'Date of birth', 'bhela-booking' ), 'type' => 'date' ),
				'nid'       => array( 'label' => __( 'NID / Passport / Birth certificate', 'bhela-booking' ) ),
				'address'   => array( 'label' => __( 'Present address', 'bhela-booking' ), 'type' => 'textarea' ),
				'address_p' => array( 'label' => __( 'Permanent address', 'bhela-booking' ), 'type' => 'textarea' ),
				'mobile'    => array( 'label' => __( 'Mobile', 'bhela-booking' ) ),
				'email'     => array( 'label' => __( 'Email', 'bhela-booking' ), 'type' => 'email' ),
			),
		),
		'bank'     => array(
			'label'  => __( 'Section B - Payment and Bank', 'bhela-booking' ),
			'fields' => array(
				'pay_mode'          => array(
					'label'   => __( 'Mode of payment', 'bhela-booking' ),
					'type'    => 'select',
					'options' => array(
						''       => __( 'Not recorded', 'bhela-booking' ),
						'cash'   => __( 'Cash', 'bhela-booking' ),
						'bank'   => __( 'Bank transfer', 'bhela-booking' ),
						'cheque' => __( 'Cheque', 'bhela-booking' ),
						'other'  => __( 'Other', 'bhela-booking' ),
					),
				),
				'pay_mode_other'    => array( 'label' => __( 'If other, specify', 'bhela-booking' ) ),
				'bank_name'         => array( 'label' => __( 'Bank name', 'bhela-booking' ) ),
				'bank_branch'       => array( 'label' => __( 'Branch name', 'bhela-booking' ) ),
				'bank_account_name' => array( 'label' => __( 'Account name', 'bhela-booking' ) ),
				'bank_account'      => array( 'label' => __( 'Account number', 'bhela-booking' ) ),
				'bank_routing'      => array( 'label' => __( 'Routing number', 'bhela-booking' ) ),
			),
		),
		'nominee'  => array(
			'label'  => __( 'Section C - Nominee', 'bhela-booking' ),
			'fields' => array(
				'nominee_name'     => array( 'label' => __( 'Full name', 'bhela-booking' ) ),
				'nominee_relation' => array( 'label' => __( 'Relation to investor', 'bhela-booking' ) ),
				'nominee_dob'      => array( 'label' => __( 'Date of birth', 'bhela-booking' ), 'type' => 'date' ),
				'nominee_nid'      => array( 'label' => __( 'NID / Passport / Birth certificate', 'bhela-booking' ) ),
				'nominee_mobile'   => array( 'label' => __( 'Mobile', 'bhela-booking' ) ),
				'nominee_address'  => array( 'label' => __( 'Address', 'bhela-booking' ), 'type' => 'textarea' ),
			),
		),
		'declaration' => array(
			'label'  => __( 'Section D - Declaration', 'bhela-booking' ),
			'fields' => array(
				'declared'     => array(
					'label'   => __( 'Declaration signed', 'bhela-booking' ),
					'type'    => 'select',
					'options' => array(
						''    => __( 'Not recorded', 'bhela-booking' ),
						'yes' => __( 'Yes - nominee rights confirmed', 'bhela-booking' ),
						'no'  => __( 'No', 'bhela-booking' ),
					),
					'help'    => __( 'The declaration on the form: the information is correct, and the nominee holds all rights to this investment in the investor absence.', 'bhela-booking' ),
				),
				'declared_on'  => array( 'label' => __( 'Date signed', 'bhela-booking' ), 'type' => 'date' ),
				'sig_investor' => array(
					'label' => __( 'Investor signature', 'bhela-booking' ),
					'type'  => 'file',
					'help'  => __( 'Upload the scan to the Media Library and paste its URL here. The record keeps a link, not a second copy.', 'bhela-booking' ),
				),
				'sig_nominee'  => array( 'label' => __( 'Nominee signature', 'bhela-booking' ), 'type' => 'file' ),
				'agreement'    => array( 'label' => __( 'Agreement / KYC document', 'bhela-booking' ), 'type' => 'file' ),
			),
		),
	);
}

/**
 * Fields whose VALUE must never reach the audit trail.
 *
 * A bank account number and an NID are exactly what an audit trail is protecting. A
 * log that records the old and the new value in full becomes a second copy of the
 * data — one that is deliberately never deleted, readable by anyone who can open the
 * Audit Trail, and outside the investor record's own access control.
 *
 * So for these the trail records THAT the field changed, by whom and when. The values
 * themselves live on the record, where the permissions are.
 */
function bhela_bm_investor_secret_fields() {
	return array( 'nid', 'bank_account', 'bank_account_name', 'bank_routing', 'nominee_nid' );
}

/**
 * Sanitise one field's posted value according to its own type.
 *
 * Shared by the admin metabox and the public registration form, because two
 * implementations of "what is a valid date of birth" is how they start to disagree.
 * sanitize_text_field() on everything would flatten the addresses and wave through a
 * junk URL; a select is checked against its OWN options, so a crafted post cannot put
 * a value in there that the screen would then fail to render.
 *
 * @param array  $def Field definition from bhela_bm_investor_fields().
 * @param mixed  $raw Unslashed posted value.
 * @return string
 */
function bhela_bm_investor_field_sanitize( $def, $raw ) {
	$type = isset( $def['type'] ) ? $def['type'] : 'text';

	if ( 'file' === $type ) {
		return esc_url_raw( $raw );
	}
	if ( 'textarea' === $type ) {
		return sanitize_textarea_field( $raw );
	}
	if ( 'email' === $type ) {
		return sanitize_email( $raw );
	}
	if ( 'date' === $type ) {
		return bhela_bm_report_date( $raw );
	}
	if ( 'select' === $type ) {
		$val = sanitize_text_field( $raw );
		return isset( $def['options'][ $val ] ) ? $val : '';
	}
	return sanitize_text_field( $raw );
}

/**
 * File types a scan or a signature may be.
 *
 * Deliberately short. These fields hold a photograph of a document, so images and PDF
 * cover every real case, and every extension left out is one fewer thing an upload
 * endpoint can be talked into storing.
 */
function bhela_bm_investor_upload_types() {
	return array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
		'pdf'      => 'application/pdf',
	);
}

/** The same list as an `accept` attribute. */
function bhela_bm_investor_upload_accept() {
	return array( 'image/jpeg', 'image/png', 'image/webp', 'application/pdf' );
}

/** Biggest file a scan may be. Filterable — a phone camera can exceed this. */
function bhela_bm_investor_upload_max() {
	return (int) apply_filters( 'bhela_bm_investor_upload_max', 8 * MB_IN_BYTES );
}

/**
 * Take one uploaded scan and return its URL.
 *
 * Three rules, each because of what these files are:
 *
 * - **The mime type is checked by CONTENT, not by the name.** `wp_handle_upload()` with
 *   an explicit `mimes` list runs the extension against the real type, so `nid.pdf.php`
 *   does not become an executable sitting in uploads.
 * - **The stored name is random.** These are NID scans and signatures. Files under
 *   wp-content/uploads are served straight off disk with no capability check, so the
 *   only thing standing between a scan and a stranger is that its URL cannot be
 *   guessed — `nid-scan-rahim.jpg` can be.
 * - **No attachment post on the public path.** An attachment is a queryable object with
 *   its own permalink page; a registration should not create one. The admin path does
 *   attach, so the office can find and delete these from the Media Library.
 *
 * @param string $field Key in $_FILES.
 * @param bool   $attach Register it in the Media Library (admin path only).
 * @return string|WP_Error URL.
 */
function bhela_bm_investor_upload( $field, $attach = false ) {
	if ( empty( $_FILES[ $field ]['name'] ) ) {
		return new WP_Error( 'nofile', __( 'কোনো ফাইল পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	$file = $_FILES[ $field ];

	if ( ! empty( $file['error'] ) ) {
		return new WP_Error( 'upload', __( 'ফাইল আপলোড হয়নি — আবার চেষ্টা করুন।', 'bhela-booking' ) );
	}
	if ( (int) $file['size'] > bhela_bm_investor_upload_max() ) {
		return new WP_Error( 'toobig', sprintf(
			/* translators: %d: megabytes */
			__( 'ফাইলটি বড় — সর্বোচ্চ %d MB।', 'bhela-booking' ),
			(int) ( bhela_bm_investor_upload_max() / MB_IN_BYTES )
		) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';

	// Random name, extension preserved. The extension still has to survive the mime
	// check below, so this cannot be used to smuggle one.
	$ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
	$file['name'] = 'bhela-doc-' . wp_generate_password( 20, false ) . ( $ext ? '.' . $ext : '' );

	$moved = wp_handle_upload( $file, array(
		'test_form' => false,
		'mimes'     => bhela_bm_investor_upload_types(),
	) );
	if ( ! is_array( $moved ) || ! empty( $moved['error'] ) ) {
		return new WP_Error( 'upload', is_array( $moved ) && ! empty( $moved['error'] )
			? (string) $moved['error']
			: __( 'ফাইলের ধরন সমর্থিত নয় — ছবি বা PDF দিন।', 'bhela-booking' ) );
	}

	if ( $attach ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = wp_insert_attachment( array(
			'post_mime_type' => $moved['type'],
			'post_title'     => basename( $moved['file'] ),
			'post_status'    => 'inherit',
		), $moved['file'] );
		if ( ! is_wp_error( $id ) && $id ) {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $moved['file'] ) );
		}
	}

	return $moved['url'];
}

/* =========================================================
 * Share configuration
 * ========================================================= */

/**
 * The share structure, from settings.
 *
 * @return array{total_investment:int,total_shares:int,per_share:int}
 */
function bhela_bm_share_config() {
	$s = bhela_bm_get_settings();
	return array(
		'total_investment' => max( 0, (int) ( $s['inv_total_investment'] ?? 0 ) ),
		'total_shares'     => max( 0, (int) ( $s['inv_total_shares'] ?? 0 ) ),
		'per_share'        => max( 0, (int) ( $s['inv_per_share'] ?? 0 ) ),
	);
}

/** Every investor, active first. Retired ones still resolve — history must read. */
function bhela_bm_investors( $include_inactive = true ) {
	$ids = get_posts( array(
		'post_type'      => 'bhela_investor',
		'post_status'    => array( 'publish', 'private', 'draft' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	$out = array();
	foreach ( $ids as $id ) {
		if ( ! $include_inactive && 'active' !== bhela_bm_investor_status( $id ) ) {
			continue;
		}
		$out[] = (int) $id;
	}
	return $out;
}

/** active | exited | suspended. Anything unrecognised reads as active. */
function bhela_bm_investor_status( $id ) {
	$s = (string) get_post_meta( $id, '_bhela_inv_status', true );
	return in_array( $s, array( 'exited', 'suspended' ), true ) ? $s : 'active';
}

/** Shares held. */
function bhela_bm_investor_shares( $id ) {
	return max( 0, (int) get_post_meta( $id, '_bhela_inv_shares', true ) );
}

/** What was actually paid in. Falls back to shares × per-share when not recorded. */
function bhela_bm_investor_amount( $id ) {
	$amount = (int) get_post_meta( $id, '_bhela_inv_amount', true );
	if ( $amount > 0 ) {
		return $amount;
	}
	$cfg = bhela_bm_share_config();
	return bhela_bm_investor_shares( $id ) * $cfg['per_share'];
}

/**
 * Share percentage, against the CONFIGURED total rather than the issued one.
 *
 * Using the configured total is the honest choice: if only 90 of 115 shares are
 * issued, the holders of those 90 own 78% of the boat between them and the remaining
 * 22% is unallocated — it does not belong to them. Dividing by the issued count
 * instead would quietly inflate every holder to fill the gap and pay out money the
 * business never earned on their behalf.
 *
 * bhela_bm_share_totals() surfaces that gap so somebody can decide what to do about
 * it. This function does not decide for them.
 */
function bhela_bm_investor_share_pct( $id ) {
	$cfg = bhela_bm_share_config();
	if ( $cfg['total_shares'] <= 0 ) {
		return 0.0;
	}
	return round( bhela_bm_investor_shares( $id ) / $cfg['total_shares'] * 100, 6 );
}

/**
 * Issued versus configured — and the gap between them.
 *
 * **Reports, never corrects.** Same contract as bhela_bm_inv_opening_drift() and the
 * cost sheet's earnings drift: when the books disagree with the configuration, a
 * person decides which is wrong. Silently rescaling the percentages to make the
 * numbers look tidy would hide a share issue nobody recorded.
 *
 * @return array{issued:int,configured:int,gap:int,over:bool,under:bool,pct_issued:float,investors:int}
 */
function bhela_bm_share_totals() {
	$cfg    = bhela_bm_share_config();
	$issued = 0;
	$people = 0;
	foreach ( bhela_bm_investors() as $id ) {
		$held = bhela_bm_investor_shares( $id );
		if ( $held > 0 ) {
			$people++;
		}
		$issued += $held;
	}
	$gap = $issued - $cfg['total_shares'];
	return array(
		'issued'     => $issued,
		'configured' => $cfg['total_shares'],
		'gap'        => $gap,
		'over'       => $gap > 0,
		'under'      => $gap < 0,
		'pct_issued' => $cfg['total_shares'] > 0 ? round( $issued / $cfg['total_shares'] * 100, 4 ) : 0.0,
		'investors'  => $people,
	);
}

/* =========================================================
 * Splitting money across shareholders
 * ========================================================= */

/**
 * Split a pot across investors by shareholding, losing nothing.
 *
 * **The parts must sum to the whole, exactly.** ৳63,000 over 115 shares is ৳547.826
 * a share; round each of 115 holdings independently and the total lands a few taka
 * off the pool. Those taka have to go somewhere, and "somewhere" cannot be nowhere —
 * a ledger that loses ৳7 every month stops reconciling within a season, and the
 * error is invisible until someone adds up a year.
 *
 * So: floor every allocation, then hand the remaining whole taka out one at a time,
 * largest fractional part first. Ties break on the larger holding, then on the lower
 * post id, so the same inputs always produce the same output — a distribution that
 * shuffles its own rounding between previews is not one anybody can check.
 *
 * **The divisor is the CONFIGURED share total, not the sum of the holders.** That
 * distinction only shows when the two disagree, which is exactly when it matters. If
 * 35 of 115 shares are issued and the pool is split across the 35, each of those
 * shares earns 3.3× what a share is worth — the holders quietly absorb the profit on
 * 80 shares nobody paid for. Dividing by 115 instead pays each share what a share is
 * owed and leaves the rest undistributed, which `unallocated` then reports.
 *
 * It also keeps this function honest with bhela_bm_investor_share_pct(), which has
 * always divided by the configured total. Two functions disagreeing about what a
 * share is worth is precisely the silent contradiction this codebase keeps getting
 * bitten by.
 *
 * @param int      $pot     Amount to split, in taka.
 * @param array    $holders id => shares. Zero-share holders are dropped.
 * @param int|null $divisor Shares the pot is measured against. Defaults to the
 *                          configured total; pass the holder sum only when the pot
 *                          genuinely belongs to those holders alone.
 * @return array id => amount. Sums to $pot when the holders cover the divisor.
 */
function bhela_bm_split_by_shares( $pot, $holders, $divisor = null ) {
	$pot = (int) $pot;
	$out = array();

	$holders = array_filter( array_map( 'intval', (array) $holders ) );
	if ( null === $divisor ) {
		$cfg     = bhela_bm_share_config();
		$divisor = $cfg['total_shares'];
	}
	$divisor = (int) $divisor;
	$held    = array_sum( $holders );
	// More issued than configured is a data error, not a licence to over-pay: fall
	// back to the issued sum so nobody receives more than 100% between them.
	$total = ( $divisor > 0 && $held <= $divisor ) ? $divisor : $held;
	if ( $pot <= 0 || $total <= 0 ) {
		foreach ( $holders as $id => $shares ) {
			$out[ $id ] = 0;
		}
		return $out;
	}

	$rema = array();
	$sum  = 0;
	foreach ( $holders as $id => $shares ) {
		$exact      = $pot * $shares / $total;
		$whole      = (int) floor( $exact );
		$out[ $id ] = $whole;
		$sum       += $whole;
		$rema[ $id ] = $exact - $whole;
	}

	// Only the holders' own portion is theirs to round up into. With 35 of 115 shares
	// issued the pot's remaining 80/115 is undistributed, not a rounding remainder.
	$due  = (int) floor( $pot * $held / $total );
	$left = max( 0, min( $due, $pot ) - $sum );
	if ( $left > 0 ) {
		$order = array_keys( $holders );
		usort( $order, function ( $a, $b ) use ( $rema, $holders ) {
			if ( $rema[ $a ] !== $rema[ $b ] ) {
				return $rema[ $b ] <=> $rema[ $a ];   // biggest fraction first
			}
			if ( $holders[ $a ] !== $holders[ $b ] ) {
				return $holders[ $b ] <=> $holders[ $a ]; // then the larger holding
			}
			return $a <=> $b;                          // then stable by id
		} );
		foreach ( array_slice( $order, 0, $left ) as $id ) {
			$out[ $id ]++;
		}
	}

	return $out;
}
