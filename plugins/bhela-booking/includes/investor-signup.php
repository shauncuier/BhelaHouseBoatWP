<?php
/**
 * Investor portal registration — an application, never an account.
 *
 * Somebody arriving at /investor-register/ can prove they hold a phone. That is all
 * they can prove, and it is nowhere near enough to be handed a page showing what
 * BHELA earned and what its shareholders are owed. So this module deliberately does
 * NOT create a login:
 *
 *     name + number     →  code sent to that number
 *     code proved       →  a 30-minute ticket, and only now the full form
 *     details + scans   →  application recorded (pending)
 *                       →  a person with bhela_investor_signup approves it
 *                       →  ONLY THEN is a login minted and linked
 *
 * **The form asks for exactly what the admin screen asks for.** It renders
 * bhela_bm_investor_fields() — the same registry the metabox draws, moved to
 * investors.php so a front-end request can read it (CLAUDE.md §13.22) — so a person
 * registering themselves and an officer typing in a paper form fill in the same
 * boxes, and the office never has to chase the eight fields a shorter form forgot to
 * ask about. Two exceptions: the Investor ID is assigned by the office, not claimed;
 * and shares and amounts are not on this form at all.
 *
 * **Nothing is uploaded until the number is proved.** The scans are NID photographs
 * and signatures, and asking for them on the first screen would make this an open
 * file-drop for anybody who found the URL. The ticket minted at step two is what
 * unlocks the upload, which is why the flow has three screens rather than two.
 *
 * Three properties carry the rest, and each exists because the obvious alternative
 * fails:
 *
 * 1. **The phone is proved before the application is written.** Otherwise the office's
 *    queue fills with numbers nobody can answer, and an approver has nothing to check.
 * 2. **Money is never self-declared.** An approved application with no matching record
 *    creates an investor with **zero shares and zero paid in**. Shares are entered by
 *    the office from a signed form.
 * 3. **Approval is its own capability.** It mints a WordPress account that reads real
 *    financial figures — a bigger act than editing an investor record — so it is
 *    `bhela_investor_signup`, not something "edit investors" quietly implies.
 *
 * On disclosure: before the code is proved the form says nothing about whether a
 * number is known, the same rule as the sign-in page. **After** it is proved the
 * visitor has demonstrated they hold that handset, so telling them about their own
 * number ("this one already has access, sign in") is not enumeration — it is the
 * answer to the question they came with.
 *
 * Two things this flow proves NOTHING about, and neither may be treated as identity:
 *
 * - **The email address.** It is a delivery fallback, and step three lets the
 *   applicant type any address at all. `bhela_bm_signup_make_user()` therefore always
 *   mints a fresh account and never joins on an existing one — see the comment there
 *   for the account takeover that reuse allowed.
 * - **Ownership of a number the code did not reach by SMS.** `sms_enabled` ships off,
 *   so on an unconfigured site every code goes to the address the applicant typed.
 *   Approval refuses to link such an application onto a record the office already
 *   holds, unless the approver states they confirmed the person by phone.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * The application record
 * ========================================================= */

/**
 * `bhela_inv_signup` — private, no REST, no UI of its own.
 *
 * It holds a name, a mobile, an NID and bank details, which puts it in exactly the
 * same class as `bhela_investor` (CLAUDE.md §3.5): it must never be addressable from
 * the front end. Its screen is the Registrations page, like `bhela_dist` and
 * `bhela_payreq`.
 */
function bhela_bm_register_signup_cpt() {
	register_post_type( 'bhela_inv_signup', array(
		'labels'              => array( 'name' => __( 'Investor Registrations', 'bhela-booking' ) ),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => false,
		'show_in_rest'        => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'supports'            => array( 'title' ),
		'capability_type'     => array( 'bhela_investor', 'bhela_investors' ),
		'map_meta_cap'        => true,
	) );
}
add_action( 'init', 'bhela_bm_register_signup_cpt' );

/** How long a proved number stays proved. Long enough to find the scans on a phone. */
const BHELA_BM_SGN_TICKET_TTL = 1800;

/** pending | approved | rejected. */
function bhela_bm_signup_states() {
	return array(
		'pending'  => array( 'label' => __( 'Awaiting approval', 'bhela-booking' ), 'tone' => 'attention' ),
		'approved' => array( 'label' => __( 'Approved', 'bhela-booking' ),          'tone' => 'good' ),
		'rejected' => array( 'label' => __( 'Rejected', 'bhela-booking' ),          'tone' => 'danger' ),
		// A record with no state at all. It cannot be acted on, and saying so beats
		// rendering a blank pill or defaulting to one that implies it is actionable.
		''         => array( 'label' => __( 'Unreadable — contact the office', 'bhela-booking' ), 'tone' => 'neutral' ),
	);
}

/**
 * Fields the office owns, which an applicant may not claim.
 *
 * The Investor ID goes into every ledger reference and every export. It is minted by
 * the office; letting a stranger pick one is how two records end up sharing an id.
 */
function bhela_bm_signup_skip_fields() {
	return array( 'code' );
}

/**
 * Bangla labels for the public form.
 *
 * The field LIST is shared with the admin screen so the two can never ask for
 * different things. The wording is not: the metabox is read by staff and is in
 * English, and a member of the public filling in a Bangla site should not hit
 * "Father name" halfway down. Anything without an entry here falls back to the
 * registry's own label, so a new field appears on both forms immediately — in English
 * on this one until somebody translates it, which is visible rather than silent.
 */
function bhela_bm_signup_labels() {
	return array(
		'name'              => __( 'পুরো নাম', 'bhela-booking' ),
		'father'            => __( 'পিতার নাম', 'bhela-booking' ),
		'mother'            => __( 'মাতার নাম', 'bhela-booking' ),
		'dob'               => __( 'জন্ম তারিখ', 'bhela-booking' ),
		'nid'               => __( 'NID / পাসপোর্ট / জন্মনিবন্ধন নম্বর', 'bhela-booking' ),
		'address'           => __( 'বর্তমান ঠিকানা', 'bhela-booking' ),
		'address_p'         => __( 'স্থায়ী ঠিকানা', 'bhela-booking' ),
		'mobile'            => __( 'মোবাইল নম্বর', 'bhela-booking' ),
		'email'             => __( 'ইমেইল', 'bhela-booking' ),
		'pay_mode'          => __( 'টাকা দেওয়ার মাধ্যম', 'bhela-booking' ),
		'pay_mode_other'    => __( 'অন্য কিছু হলে লিখুন', 'bhela-booking' ),
		'bank_name'         => __( 'ব্যাংকের নাম', 'bhela-booking' ),
		'bank_branch'       => __( 'শাখা', 'bhela-booking' ),
		'bank_account_name' => __( 'অ্যাকাউন্টের নাম', 'bhela-booking' ),
		'bank_account'      => __( 'অ্যাকাউন্ট নম্বর', 'bhela-booking' ),
		'bank_routing'      => __( 'রাউটিং নম্বর', 'bhela-booking' ),
		'nominee_name'      => __( 'নমিনির পুরো নাম', 'bhela-booking' ),
		'nominee_relation'  => __( 'নমিনির সম্পর্ক', 'bhela-booking' ),
		'nominee_dob'       => __( 'নমিনির জন্ম তারিখ', 'bhela-booking' ),
		'nominee_nid'       => __( 'নমিনির NID / পাসপোর্ট / জন্মনিবন্ধন', 'bhela-booking' ),
		'nominee_mobile'    => __( 'নমিনির মোবাইল', 'bhela-booking' ),
		'nominee_address'   => __( 'নমিনির ঠিকানা', 'bhela-booking' ),
		'declared'          => __( 'ঘোষণাপত্র', 'bhela-booking' ),
		'declared_on'       => __( 'ঘোষণার তারিখ', 'bhela-booking' ),
		'sig_investor'      => __( 'আপনার স্বাক্ষরের ছবি', 'bhela-booking' ),
		'sig_nominee'       => __( 'নমিনির স্বাক্ষরের ছবি', 'bhela-booking' ),
		'agreement'         => __( 'NID / চুক্তিপত্রের কপি', 'bhela-booking' ),
		'note'              => __( 'অফিসকে কিছু বলার থাকলে', 'bhela-booking' ),
	);
}

/**
 * Bangla option lists, and Bangla help where a field needs any.
 *
 * The registry's own options and help are written for staff: "Not recorded", and on
 * the signature fields "Upload the scan to the Media Library and paste its URL here" —
 * advice about wp-admin, printed next to a file picker on a public page. Only
 * rendering the form showed it; the harness asserts which fields exist, and every one
 * of them did.
 */
function bhela_bm_signup_options() {
	return array(
		'pay_mode' => array(
			''       => __( 'বলা হয়নি', 'bhela-booking' ),
			'cash'   => __( 'নগদ', 'bhela-booking' ),
			'bank'   => __( 'ব্যাংক ট্রান্সফার', 'bhela-booking' ),
			'cheque' => __( 'চেক', 'bhela-booking' ),
			'other'  => __( 'অন্যান্য', 'bhela-booking' ),
		),
		'declared' => array(
			''    => __( 'বলা হয়নি', 'bhela-booking' ),
			'yes' => __( 'হ্যাঁ — নমিনির অধিকার নিশ্চিত করছি', 'bhela-booking' ),
			'no'  => __( 'না', 'bhela-booking' ),
		),
	);
}

/** Help text for the public form. Anything not listed shows none. */
function bhela_bm_signup_help() {
	return array(
		'email'        => __( 'SMS না গেলে কোড এই ইমেইলে যাবে — তাই দেওয়া ভালো।', 'bhela-booking' ),
		'declared'     => __( 'আপনার দেওয়া তথ্য সঠিক, এবং আপনার অবর্তমানে এই বিনিয়োগের সব অধিকার নমিনির — এটি স্বীকার করছেন কি না।', 'bhela-booking' ),
		'sig_investor' => __( 'কাগজে করা স্বাক্ষরের ছবি তুলে দিন।', 'bhela-booking' ),
		'sig_nominee'  => __( 'কাগজে করা স্বাক্ষরের ছবি তুলে দিন।', 'bhela-booking' ),
		'agreement'    => __( 'NID বা চুক্তিপত্রের ছবি/স্ক্যান।', 'bhela-booking' ),
	);
}

/** Bangla headings for the registry's four sections. */
function bhela_bm_signup_group_labels() {
	return array(
		'identity'    => __( 'ক — আপনার তথ্য', 'bhela-booking' ),
		'bank'        => __( 'খ — পেমেন্ট ও ব্যাংক', 'bhela-booking' ),
		'nominee'     => __( 'গ — নমিনি', 'bhela-booking' ),
		'declaration' => __( 'ঘ — ঘোষণা ও কাগজপত্র', 'bhela-booking' ),
	);
}

/**
 * The public form, group by group — the admin registry with three changes.
 *
 * `name` is prepended because on the record the name is the post TITLE rather than a
 * meta field, so the registry has no entry for it and a form without it would file
 * applications nobody can identify. `code` is dropped. `note` is appended, because a
 * person registering themselves usually has something to say and there is nowhere
 * else for it to go.
 */
function bhela_bm_signup_groups() {
	$skip   = bhela_bm_signup_skip_fields();
	$labels = bhela_bm_signup_labels();
	$opts   = bhela_bm_signup_options();
	$help   = bhela_bm_signup_help();
	$heads  = bhela_bm_signup_group_labels();
	$out    = array();

	foreach ( bhela_bm_investor_fields() as $gkey => $group ) {
		$fields = array();

		if ( 'identity' === $gkey ) {
			$fields['name'] = array( 'label' => $labels['name'], 'required' => true );
		}
		foreach ( $group['fields'] as $key => $def ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			if ( isset( $labels[ $key ] ) ) {
				$def['label'] = $labels[ $key ];
			}
			if ( isset( $opts[ $key ] ) ) {
				$def['options'] = $opts[ $key ];
			}
			// The registry's help is written for staff. Dropped rather than translated
			// wholesale: most of it is about wp-admin and means nothing out here.
			unset( $def['help'] );
			if ( isset( $help[ $key ] ) ) {
				$def['help'] = $help[ $key ];
			}
			if ( 'mobile' === $key ) {
				$def['required'] = true;
			}
			$fields[ $key ] = $def;
		}
		if ( 'declaration' === $gkey ) {
			$fields['note'] = array( 'label' => $labels['note'], 'type' => 'textarea' );
		}

		$out[ $gkey ] = array(
			'label'  => $heads[ $gkey ] ?? $group['label'],
			'fields' => $fields,
		);
	}
	return $out;
}

/** Every field key the form can carry, flat. */
function bhela_bm_signup_keys() {
	$keys = array();
	foreach ( bhela_bm_signup_groups() as $group ) {
		foreach ( $group['fields'] as $key => $def ) {
			$keys[ $key ] = $def;
		}
	}
	return $keys;
}

/** The keys asked for on the FIRST screen — only what a code needs. */
function bhela_bm_signup_first_keys() {
	return array( 'name', 'mobile', 'email' );
}

/** The file fields, which are only reachable once the number is proved. */
function bhela_bm_signup_file_keys() {
	$out = array();
	foreach ( bhela_bm_signup_keys() as $key => $def ) {
		if ( 'file' === ( $def['type'] ?? '' ) ) {
			$out[] = $key;
		}
	}
	return $out;
}

/** Control meta that is not a record field. */
function bhela_bm_signup_meta_keys() {
	return array( 'state', 'channel', 'at', 'ip', 'investor', 'user', 'by', 'decided_at', 'reason', 'email_clash' );
}

/** One application, read back. */
function bhela_bm_signup( $id ) {
	if ( 'bhela_inv_signup' !== get_post_type( $id ) ) {
		return null;
	}
	$out  = array( 'id' => (int) $id );
	$keys = array_merge( array_keys( bhela_bm_signup_keys() ), bhela_bm_signup_meta_keys() );
	foreach ( $keys as $k ) {
		$out[ $k ] = get_post_meta( $id, '_bhela_sgn_' . $k, true );
	}
	$out['investor'] = (int) $out['investor'];
	$out['user']     = (int) $out['user'];
	$out['by']       = (int) $out['by'];
	return $out;
}

/**
 * Applications, newest first.
 *
 * @param string $state Filter by state, or '' for all.
 */
function bhela_bm_signups( $state = '', $limit = 200 ) {
	$args = array(
		'post_type'      => 'bhela_inv_signup',
		'post_status'    => array( 'publish', 'private', 'draft' ),
		'posts_per_page' => (int) $limit,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( $state ) {
		$args['meta_key']   = '_bhela_sgn_state';
		$args['meta_value'] = $state;
	}
	$out = array();
	foreach ( get_posts( $args ) as $id ) {
		$row = bhela_bm_signup( $id );
		if ( $row ) {
			$out[] = $row;
		}
	}
	return $out;
}

/** A pending application for this number, or 0. */
function bhela_bm_signup_pending_for( $mobile ) {
	$mobile = bhela_bm_normalize_mobile( $mobile );
	if ( ! $mobile ) {
		return 0;
	}
	$hit = get_posts( array(
		'post_type'      => 'bhela_inv_signup',
		'post_status'    => array( 'publish', 'private', 'draft' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => '_bhela_sgn_mobile', 'value' => $mobile ),
			array( 'key' => '_bhela_sgn_state', 'value' => 'pending' ),
		),
	) );
	return $hit ? (int) $hit[0] : 0;
}

/**
 * Record an application. Called only after the phone has been proved.
 *
 * Values are sanitised through bhela_bm_investor_field_sanitize(), the same function
 * the admin metabox uses, so a date is a date and a select holds one of its own
 * options whichever form the value came in through. Anything not in the registry is
 * dropped rather than stored.
 *
 * @param array $args Field keys from bhela_bm_signup_keys(), plus `channel`.
 * @return int|WP_Error Application post id.
 */
function bhela_bm_signup_add( $args ) {
	$defs   = bhela_bm_signup_keys();
	$mobile = bhela_bm_normalize_mobile( $args['mobile'] ?? '' );
	$name   = sanitize_text_field( $args['name'] ?? '' );
	if ( ! $mobile ) {
		return new WP_Error( 'mobile', __( 'সঠিক মোবাইল নম্বর দিন — ১১ সংখ্যার, ০১ দিয়ে শুরু।', 'bhela-booking' ) );
	}
	if ( '' === $name ) {
		return new WP_Error( 'name', __( 'আপনার পুরো নাম লিখুন।', 'bhela-booking' ) );
	}

	// One pending application per number. A second submission updates the first
	// rather than queueing a duplicate for the office to reconcile by eye.
	$id = bhela_bm_signup_pending_for( $mobile );
	if ( ! $id ) {
		$id = wp_insert_post( array(
			'post_type'   => 'bhela_inv_signup',
			'post_status' => 'publish',
			'post_title'  => sprintf( '%s %s', $name, $mobile ),
		), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
	} else {
		wp_update_post( array( 'ID' => $id, 'post_title' => sprintf( '%s %s', $name, $mobile ) ) );
	}

	foreach ( $defs as $key => $def ) {
		if ( ! array_key_exists( $key, $args ) ) {
			continue;
		}
		$val = ( 'name' === $key ) ? $name : bhela_bm_investor_field_sanitize( $def, $args[ $key ] );
		if ( 'mobile' === $key ) {
			$val = $mobile;
		}
		update_post_meta( $id, '_bhela_sgn_' . $key, $val );
	}

	foreach ( array(
		'state'   => 'pending',
		// Which channel proved the number. An email fallback proves an address, not a
		// handset, and the approver is entitled to know which one they are trusting.
		'channel' => sanitize_key( $args['channel'] ?? '' ),
		'at'      => current_time( 'mysql' ),
		'ip'      => bhela_bm_client_ip(),
	) as $k => $v ) {
		update_post_meta( $id, '_bhela_sgn_' . $k, $v );
	}

	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'investor', sprintf( 'Investor registration received from %s (%s).', $name, $mobile ) );
	}
	bhela_bm_signup_notify_office( $id );
	return (int) $id;
}

/** Tell the office an application is waiting. Uses the admin address already configured. */
function bhela_bm_signup_notify_office( $id ) {
	$row = bhela_bm_signup( $id );
	if ( ! $row ) {
		return;
	}
	$to = '';
	if ( function_exists( 'bhela_bm_get_settings' ) ) {
		$s  = bhela_bm_get_settings();
		$to = sanitize_email( $s['admin_email'] ?? '' );
	}
	if ( ! $to || ! is_email( $to ) ) {
		$to = get_option( 'admin_email' );
	}
	if ( ! $to || ! is_email( $to ) ) {
		return;
	}
	// menu.php is admin-only, and this runs on a front-end submit. The built URL is
	// the same shape the shim keeps alive either way — see §3.9.
	$link = function_exists( 'bhela_bm_admin_url' )
		? bhela_bm_admin_url( 'bhela-bm-signups' )
		: admin_url( 'admin.php?page=bhela-bm-signups' );
	wp_mail(
		$to,
		__( 'BHELA — নতুন বিনিয়োগকারী নিবন্ধন অনুমোদনের অপেক্ষায়', 'bhela-booking' ),
		sprintf(
			/* translators: 1: name, 2: mobile, 3: admin URL */
			__( "নাম: %1\$s\nমোবাইল: %2\$s\n\nঅনুমোদন করুন: %3\$s", 'bhela-booking' ),
			$row['name'],
			$row['mobile'],
			$link
		)
	);
}

/* =========================================================
 * Approve / reject
 * ========================================================= */

/**
 * Approve an application: link or create the investor record, then mint the login.
 *
 * The order matters. The record comes first because it is what the login resolves to;
 * a user created before there is anything to link it to is an account with access to
 * a portal that will refuse it, which reads to the investor as a broken site.
 *
 * **A code that went by email proves an address, not a handset.** SMS is the primary
 * channel and the fallback exists so an investor can still get in through a gateway
 * outage — but `sms_enabled` ships OFF, so on an unconfigured site every code goes to
 * the address the applicant typed. That is fine for a brand-new record, where the
 * applicant is only ever claiming themselves. It is not fine when the number already
 * matches a record the office holds: somebody could name a real investor's mobile,
 * take the code at their own inbox, and be approved straight onto that shareholding.
 * So that one combination — an existing record plus a non-SMS proof — is REFUSED
 * unless the approver states they have confirmed the person by phone themselves.
 *
 * @param int  $id        Application id.
 * @param bool $notify    Tell the applicant their access is ready.
 * @param bool $confirmed The approver has verified this person by phone. Only ever
 *                        true because somebody ticked the box saying so.
 * @return array|WP_Error { investor, user, created }
 */
function bhela_bm_signup_approve( $id, $notify = true, $confirmed = false ) {
	if ( ! current_user_can( 'bhela_investor_signup' ) ) {
		return new WP_Error( 'denied', __( 'আপনার এই কাজের অনুমতি নেই।', 'bhela-booking' ) );
	}
	$row = bhela_bm_signup( $id );
	if ( ! $row ) {
		return new WP_Error( 'missing', __( 'নিবন্ধনটি পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( 'pending' !== $row['state'] ) {
		return new WP_Error( 'state', __( 'এই নিবন্ধনটি ইতিমধ্যে নিষ্পত্তি হয়েছে।', 'bhela-booking' ) );
	}

	$created  = false;
	$investor = bhela_bm_investor_by_mobile( $row['mobile'] );

	// See the docblock. A brand-new record is the applicant claiming themselves; an
	// existing one is a claim ON somebody, and an emailed code is no evidence for it.
	if ( $investor && 'sms' !== $row['channel'] && ! $confirmed ) {
		return new WP_Error( 'unproved_link', __( 'এই নম্বরটি আগে থেকেই খাতায় আছে, কিন্তু কোডটি SMS-এ যায়নি — অর্থাৎ নম্বরের মালিকানা প্রমাণ হয়নি। ফোন করে পরিচয় নিশ্চিত করে নিচের ঘরে টিক দিন, তারপর অনুমোদন করুন।', 'bhela-booking' ) );
	}

	if ( ! $investor ) {
		// A record the office has never seen. It is created with ZERO shares and zero
		// paid in — see property 2 in the file header. What somebody claims to have
		// invested is not evidence that they did.
		$investor = wp_insert_post( array(
			'post_type'   => 'bhela_investor',
			'post_status' => 'publish',
			'post_title'  => $row['name'],
		), true );
		if ( is_wp_error( $investor ) ) {
			return $investor;
		}
		$created = true;
		update_post_meta( $investor, '_bhela_inv_shares', 0 );
		update_post_meta( $investor, '_bhela_inv_amount', 0 );
		update_post_meta( $investor, '_bhela_inv_status', 'active' );
		update_post_meta( $investor, '_bhela_inv_date', current_time( 'Y-m-d' ) );

		bhela_bm_audit( array(
			'channel' => 'investor', 'action' => 'signup_create', 'object_type' => 'investor',
			'object_id' => $investor, 'object_ref' => $row['name'],
			'field' => 'shares', 'old_value' => '', 'new_value' => '0',
			'reason' => __( 'Created from an approved portal registration. Shares and amount are zero until the office records them.', 'bhela-booking' ),
		) );
	}

	$row['id'] = (int) $id;
	bhela_bm_signup_copy_to_record( $row, $investor, $created );

	$user = bhela_bm_investor_user( $investor );
	if ( ! $user ) {
		$user = bhela_bm_signup_make_user( $row, $investor );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		// One login, one record — the same guard bhela_bm_investor_save_login() runs
		// before its own link, and for the same reason: a user id claimed by two
		// records makes bhela_bm_current_investor() refuse BOTH of them, which locks
		// the real investor out of their own portal. This path was the only writer of
		// `_bhela_inv_user` that did not check.
		$taken = get_posts( array(
			'post_type' => 'bhela_investor', 'post_status' => 'any', 'posts_per_page' => 1,
			'fields' => 'ids', 'no_found_rows' => true, 'post__not_in' => array( (int) $investor ),
			'meta_key' => '_bhela_inv_user', 'meta_value' => (int) $user,
		) );
		if ( $taken ) {
			return new WP_Error( 'user_taken', __( 'এই লগইনটি অন্য একটি বিনিয়োগকারী রেকর্ডের সাথে যুক্ত। হাতে যাচাই করুন।', 'bhela-booking' ) );
		}
		update_post_meta( $investor, '_bhela_inv_user', (int) $user );
		bhela_bm_audit( array(
			'channel' => 'investor', 'action' => 'link', 'object_type' => 'investor',
			'object_id' => $investor, 'object_ref' => get_the_title( $investor ),
			'field' => 'user', 'new_value' => (string) $user,
			'reason' => __( 'Portal registration approved.', 'bhela-booking' ),
		) );
	}

	update_post_meta( $id, '_bhela_sgn_state', 'approved' );
	update_post_meta( $id, '_bhela_sgn_investor', (int) $investor );
	update_post_meta( $id, '_bhela_sgn_user', (int) $user );
	update_post_meta( $id, '_bhela_sgn_by', get_current_user_id() );
	update_post_meta( $id, '_bhela_sgn_decided_at', current_time( 'mysql' ) );

	bhela_bm_audit( array(
		'channel' => 'investor', 'action' => 'signup_approve', 'object_type' => 'investor',
		'object_id' => $investor, 'object_ref' => get_the_title( $investor ),
		'field' => 'registration', 'old_value' => 'pending', 'new_value' => 'approved',
		'reason' => sprintf(
			/* translators: 1: mobile, 2: how the number was proved */
			__( 'Registration for %1$s, number proved by %2$s.', 'bhela-booking' ),
			$row['mobile'],
			$row['channel'] ? $row['channel'] : __( 'unknown', 'bhela-booking' )
		),
	) );

	if ( $notify ) {
		bhela_bm_signup_notify_applicant( $row );
	}
	return array( 'investor' => (int) $investor, 'user' => (int) $user, 'created' => $created );
}

/**
 * Copy an approved application's details onto the investor record.
 *
 * **An existing record's filled-in field is never overwritten.** The office typed it
 * off a signed form; what a web form says is at best a second opinion, and losing a
 * verified bank account to one would be the most expensive possible outcome here.
 * So a blank field is filled and a filled one is left alone, with the incoming value
 * still on the application for anybody who wants to compare. A record this approval
 * just created has nothing to protect, so everything lands.
 *
 * Every write goes through the audit trail, and the five fields in
 * bhela_bm_investor_secret_fields() record THAT they changed and not the values —
 * §13.42, because the trail is never pruned and is read by more people than the
 * record is.
 */
function bhela_bm_signup_copy_to_record( $row, $investor, $fresh = false ) {
	$secret  = bhela_bm_investor_secret_fields();
	$skipped = array();

	foreach ( bhela_bm_signup_keys() as $key => $def ) {
		if ( in_array( $key, array( 'name', 'note' ), true ) ) {
			continue;   // the name is the title; the note is a message, not a field
		}
		$new = (string) ( $row[ $key ] ?? '' );
		if ( '' === $new ) {
			continue;
		}
		$old = (string) get_post_meta( $investor, '_bhela_inv_' . $key, true );
		if ( '' !== $old ) {
			if ( $old !== $new ) {
				$skipped[] = $key;
			}
			continue;
		}
		update_post_meta( $investor, '_bhela_inv_' . $key, $new );

		$hide = in_array( $key, $secret, true );
		bhela_bm_audit( array(
			'channel' => 'investor', 'action' => 'profile', 'object_type' => 'investor',
			'object_id' => (int) $investor, 'object_ref' => get_the_title( $investor ),
			'field' => $key,
			'old_value' => '',
			'new_value' => $hide ? '' : $new,
			'reason'    => $hide
				? __( 'From an approved registration. Value not recorded — this field holds bank or identity details.', 'bhela-booking' )
				: __( 'From an approved registration.', 'bhela-booking' ),
		) );
	}

	if ( 'mobile' === '' ) {
		return;   // unreachable; kept so the index call below reads as deliberate
	}
	bhela_bm_investor_index_mobile( $investor );

	// A field the applicant filled in differently from the record is worth saying out
	// loud once, rather than leaving somebody to spot it by reading two screens.
	if ( $skipped && ! $fresh ) {
		bhela_bm_audit( array(
			'channel' => 'investor', 'action' => 'profile', 'object_type' => 'investor',
			'object_id' => (int) $investor, 'object_ref' => get_the_title( $investor ),
			'field' => 'registration_conflict',
			'new_value' => implode( ', ', $skipped ),
			'reason' => __( 'The registration gave a different value for these; the record was kept. Compare them on the Registrations screen.', 'bhela-booking' ),
		) );
	}
}

/**
 * The WordPress account behind a portal login.
 *
 * Never a chosen or guessable password: sign-in is by one-time code, so the password
 * is deliberately a random string nobody — at BHELA or here — ever sees. The username
 * is the mobile number because an investor may not have an email at all, and a login
 * name is not a credential in this flow.
 *
 * @return int|WP_Error User id.
 */
function bhela_bm_signup_make_user( $row, $investor ) {
	$email = sanitize_email( $row['email'] ?? '' );

	// **The email is NEVER an identity join key.** This flow proves a phone number and
	// nothing else — the address is only a delivery fallback, and step three lets the
	// applicant type any address at all. An earlier version of this function called
	// email_exists() and, on a hit, RETURNED THAT USER, so an applicant who typed
	// somebody else's address had their new investor record linked to that person's
	// WordPress account. Approval then handed them a real auth cookie for it: type your
	// own mobile, type a stranger's email, receive the code on your own handset, and
	// sign in as them. Against a plain subscriber that is a complete account takeover,
	// because bhela_bm_investor_block_admin() only turns away the investor role.
	//
	// So a registration always mints its OWN account. A colliding address is simply
	// left off it — flagged for the office rather than joined on, because a duplicate
	// address is either a person who already has a login (nothing to approve) or two
	// different people, and this code cannot tell which.
	$clash = ( $email && is_email( $email ) ) ? email_exists( $email ) : false;
	if ( $clash ) {
		$email = '';
		bhela_bm_audit( array(
			'channel' => 'investor', 'action' => 'signup_approve', 'object_type' => 'investor',
			'object_id' => (int) $investor, 'object_ref' => (string) $row['name'],
			'field' => 'email',
			'reason' => __( 'The address on the registration already belongs to another account, so the new login was created without one. Nothing was linked to that account. Set an address by hand once the office has confirmed whose it is.', 'bhela-booking' ),
		) );
	}

	$login = 'bhela-' . $row['mobile'];
	if ( username_exists( $login ) ) {
		$login .= '-' . wp_generate_password( 4, false );
	}
	$args = array(
		'user_login'   => $login,
		'user_pass'    => wp_generate_password( 32, true, true ),
		'display_name' => $row['name'],
		'first_name'   => $row['name'],
		'role'         => 'bhela_investor',
	);
	// Only when there is one, and only when it is free. A synthesised address would
	// silently swallow the email fallback and every notice this account is ever sent;
	// a duplicate one makes wp_insert_user() fail outright with existing_user_email.
	if ( $email && is_email( $email ) ) {
		$args['user_email'] = $email;
	}
	$uid = wp_insert_user( $args );
	if ( is_wp_error( $uid ) ) {
		return $uid;
	}
	if ( $clash ) {
		update_post_meta( $row['id'], '_bhela_sgn_email_clash', 1 );
	}
	return (int) $uid;
}

/** Tell an approved applicant they can sign in. */
function bhela_bm_signup_notify_applicant( $row ) {
	$url = bhela_bm_portal_url();
	$msg = sprintf( 'Your %s investor portal access is ready. Sign in with your mobile number: %s', bhela_bm_otp_brand(), $url );

	if ( function_exists( 'bhela_bm_send_sms' ) ) {
		bhela_bm_send_sms( $row['mobile'], bhela_bm_otp_gsm_safe( $msg ) );
	}
	if ( ! empty( $row['email'] ) && is_email( $row['email'] ) ) {
		wp_mail(
			$row['email'],
			__( 'BHELA — বিনিয়োগকারী পোর্টাল চালু হয়েছে', 'bhela-booking' ),
			sprintf(
				/* translators: 1: portal URL, 2: mobile number */
				__( "আপনার BHELA বিনিয়োগকারী পোর্টাল চালু হয়েছে।\n\n%1\$s\n\nপাসওয়ার্ড লাগবে না — %2\$s নম্বরটি দিন, কোড পাবেন।", 'bhela-booking' ),
				$url,
				$row['mobile']
			)
		);
	}
}

/**
 * Reject an application. Writes no user and no investor record.
 *
 * The applicant is not told by default: a rejection is usually "we do not know who
 * this is", and answering it turns the form into a way of finding out how BHELA
 * responds to a given number.
 */
function bhela_bm_signup_reject( $id, $reason = '', $notify = false ) {
	if ( ! current_user_can( 'bhela_investor_signup' ) ) {
		return new WP_Error( 'denied', __( 'আপনার এই কাজের অনুমতি নেই।', 'bhela-booking' ) );
	}
	$row = bhela_bm_signup( $id );
	if ( ! $row ) {
		return new WP_Error( 'missing', __( 'নিবন্ধনটি পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( 'pending' !== $row['state'] ) {
		return new WP_Error( 'state', __( 'এই নিবন্ধনটি ইতিমধ্যে নিষ্পত্তি হয়েছে।', 'bhela-booking' ) );
	}

	update_post_meta( $id, '_bhela_sgn_state', 'rejected' );
	update_post_meta( $id, '_bhela_sgn_reason', sanitize_textarea_field( $reason ) );
	update_post_meta( $id, '_bhela_sgn_by', get_current_user_id() );
	update_post_meta( $id, '_bhela_sgn_decided_at', current_time( 'mysql' ) );

	bhela_bm_audit( array(
		'channel' => 'investor', 'action' => 'signup_reject', 'object_type' => 'signup',
		'object_id' => (int) $id, 'object_ref' => $row['name'],
		'field' => 'registration', 'old_value' => 'pending', 'new_value' => 'rejected',
		'reason' => sanitize_textarea_field( $reason ),
	) );

	if ( $notify && ! empty( $row['email'] ) && is_email( $row['email'] ) ) {
		wp_mail(
			$row['email'],
			__( 'BHELA — বিনিয়োগকারী নিবন্ধন', 'bhela-booking' ),
			__( 'আপনার নিবন্ধনটি এখন অনুমোদন করা যায়নি। প্রয়োজনে BHELA অফিসে যোগাযোগ করুন।', 'bhela-booking' )
		);
	}
	return true;
}

/**
 * Delete a settled application.
 *
 * These carry a name, a number, an NID and bank details, and an application that has
 * been dealt with has no reason to keep holding them. The audit trail keeps what
 * happened — a rejection is recorded there permanently, so deleting the form loses no
 * history. A PENDING one cannot be deleted: decide it first, or the decision leaves
 * no trace.
 */
function bhela_bm_signup_delete( $id ) {
	if ( ! current_user_can( 'bhela_investor_signup' ) ) {
		return new WP_Error( 'denied', __( 'আপনার এই কাজের অনুমতি নেই।', 'bhela-booking' ) );
	}
	$row = bhela_bm_signup( $id );
	if ( ! $row ) {
		return new WP_Error( 'missing', __( 'নিবন্ধনটি পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( 'pending' === $row['state'] ) {
		return new WP_Error( 'state', __( 'অপেক্ষমাণ নিবন্ধন মুছে ফেলা যায় না — আগে অনুমোদন বা বাতিল করুন।', 'bhela-booking' ) );
	}
	wp_delete_post( $id, true );
	return true;
}

/* =========================================================
 * The ticket — proof that a number was verified
 * ========================================================= */

/** Mint a ticket for a proved number. Its id is opaque and random. */
function bhela_bm_signup_ticket_add( $data ) {
	$id = wp_generate_password( 32, false );
	set_transient( 'bhela_bm_sgnok_' . $id, $data, BHELA_BM_SGN_TICKET_TTL );
	return $id;
}

/**
 * Read a ticket, or null.
 *
 * NOT consumed here. A failed submit — an oversized scan, a missing name — has to be
 * retryable, and burning the ticket on the first attempt would send the applicant
 * back to the beginning to wait for another code.
 */
function bhela_bm_signup_ticket( $id ) {
	$id = preg_replace( '/[^A-Za-z0-9]/', '', (string) $id );
	if ( ! $id ) {
		return null;
	}
	$t = get_transient( 'bhela_bm_sgnok_' . $id );
	return is_array( $t ) ? $t : null;
}

/** Spend it. Called once, when the application is filed. */
function bhela_bm_signup_ticket_spend( $id ) {
	$id = preg_replace( '/[^A-Za-z0-9]/', '', (string) $id );
	if ( $id ) {
		delete_transient( 'bhela_bm_sgnok_' . $id );
	}
}

/* =========================================================
 * The public form
 * ========================================================= */

/** Where the registration page lives. Filterable, like the portal's own URL. */
function bhela_bm_signup_url() {
	$page = get_page_by_path( 'investor-register' );
	$url  = $page ? get_permalink( $page ) : home_url( '/investor-register/' );
	return apply_filters( 'bhela_bm_signup_url', $url );
}

/** State handed from the handler to the shortcode. See bhela_bm_portal_state(). */
function bhela_bm_signup_state( $set = null ) {
	static $state = array();
	if ( is_array( $set ) ) {
		$state = $set;
	}
	return $state;
}

/**
 * Handled on template_redirect so the success case can redirect.
 *
 * Post/redirect/get on success, because a refresh must not re-file the application —
 * and `?bhela_reg=done` carries nothing anybody would mind seeing in a server log.
 */
function bhela_bm_signup_handle() {
	if ( empty( $_POST['bhela_reg_step'] ) ) {
		return;
	}
	$step = sanitize_key( wp_unslash( $_POST['bhela_reg_step'] ) );
	if ( ! in_array( $step, array( 'start', 'code', 'details' ), true ) ) {
		return;
	}
	if ( empty( $_POST['bhela_reg_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_reg_nonce'] ) ), 'bhela_inv_register' ) ) {
		return;
	}
	if ( ! empty( $_POST['bhela_bm_hp'] ) ) {
		return;
	}

	if ( 'start' === $step ) {
		bhela_bm_signup_step_start();
		return;
	}
	$to = ( 'code' === $step ) ? bhela_bm_signup_step_code() : bhela_bm_signup_step_details();
	if ( $to ) {
		wp_safe_redirect( $to );
		exit;
	}
}
add_action( 'template_redirect', 'bhela_bm_signup_handle' );

/** Step one: a name and a number, and a code goes out. Nothing is stored. */
function bhela_bm_signup_step_start() {
	$in = array();
	foreach ( bhela_bm_signup_first_keys() as $key ) {
		$defs       = bhela_bm_signup_keys();
		$in[ $key ] = bhela_bm_investor_field_sanitize(
			$defs[ $key ] ?? array(),
			wp_unslash( $_POST[ 'reg_' . $key ] ?? '' )
		);
	}

	$mobile = bhela_bm_normalize_mobile( $in['mobile'] );
	if ( '' === $in['name'] ) {
		bhela_bm_signup_state( array( 'step' => 'start', 'in' => $in, 'err' => __( 'আপনার পুরো নাম লিখুন।', 'bhela-booking' ) ) );
		return;
	}
	if ( ! $mobile ) {
		bhela_bm_signup_state( array( 'step' => 'start', 'in' => $in, 'err' => __( 'সঠিক মোবাইল নম্বর দিন — ১১ সংখ্যার, ০১ দিয়ে শুরু।', 'bhela-booking' ) ) );
		return;
	}
	if ( ! empty( $_POST['reg_email'] ) && ! is_email( $in['email'] ) ) {
		bhela_bm_signup_state( array( 'step' => 'start', 'in' => $in, 'err' => __( 'ইমেইল ঠিকানাটি সঠিক নয়।', 'bhela-booking' ) ) );
		return;
	}
	$in['mobile'] = $mobile;

	// The three answers travel in the challenge payload, server-side. Nothing reaches
	// the database — or the office's queue — on the strength of a typed number alone.
	$chal = bhela_bm_chal_start( 'signup', $mobile, $in, $in['email'] );
	if ( is_wp_error( $chal ) ) {
		bhela_bm_signup_state( array( 'step' => 'start', 'in' => $in, 'err' => $chal->get_error_message() ) );
		return;
	}
	bhela_bm_signup_state( array(
		'step' => 'code',
		'chal' => $chal['id'],
		'mask' => bhela_bm_chal_mask( $mobile ),
		'in'   => $in,
	) );
}

/**
 * Step two: the code is proved, and a ticket unlocks the rest of the form.
 *
 * @return string Redirect URL, or '' to re-render.
 */
function bhela_bm_signup_step_code() {
	$chal = sanitize_text_field( wp_unslash( $_POST['bhela_reg_chal'] ?? '' ) );
	$code = sanitize_text_field( wp_unslash( $_POST['bhela_reg_code'] ?? '' ) );
	$mask = sanitize_text_field( wp_unslash( $_POST['bhela_reg_mask'] ?? '' ) );

	$ok = $chal
		? bhela_bm_chal_verify( 'signup', $chal, $code )
		: new WP_Error( 'expired', __( 'কোডের মেয়াদ শেষ। আবার শুরু করুন।', 'bhela-booking' ) );

	if ( is_wp_error( $ok ) ) {
		bhela_bm_signup_state( array(
			'step' => 'expired' === $ok->get_error_code() ? 'start' : 'code',
			'chal' => $chal,
			'mask' => $mask,
			'err'  => $ok->get_error_message(),
		) );
		return '';
	}

	$in     = is_array( $ok['payload'] ) ? $ok['payload'] : array();
	$mobile = bhela_bm_normalize_mobile( $ok['phone'] );

	// The number is proved, so from here it is safe to answer the visitor about their
	// own number. Before this point it would have been enumeration.
	$investor = bhela_bm_investor_by_mobile( $mobile );
	if ( $investor && bhela_bm_investor_user( $investor ) ) {
		return add_query_arg( 'bhela_reg', 'exists', bhela_bm_signup_url() );
	}

	$in['mobile']  = $mobile;
	$in['channel'] = $ok['channel'];

	bhela_bm_signup_state( array(
		'step'   => 'details',
		'ticket' => bhela_bm_signup_ticket_add( $in ),
		'mask'   => bhela_bm_chal_mask( $mobile ),
		'in'     => $in,
	) );
	return '';
}

/**
 * Step three: the full form, and the scans. The only place an upload can happen.
 *
 * @return string Redirect URL, or '' to re-render.
 */
function bhela_bm_signup_step_details() {
	$tid    = sanitize_text_field( wp_unslash( $_POST['bhela_reg_ticket'] ?? '' ) );
	$ticket = bhela_bm_signup_ticket( $tid );

	if ( ! $ticket ) {
		// Expired, or somebody arriving here without having proved a number at all.
		bhela_bm_signup_state( array(
			'step' => 'start',
			'err'  => __( 'সময় শেষ হয়ে গেছে — আবার শুরু করুন।', 'bhela-booking' ),
		) );
		return '';
	}

	$defs = bhela_bm_signup_keys();
	$in   = $ticket;
	foreach ( $defs as $key => $def ) {
		if ( 'file' === ( $def['type'] ?? '' ) ) {
			continue;
		}
		if ( isset( $_POST[ 'reg_' . $key ] ) ) {
			$in[ $key ] = bhela_bm_investor_field_sanitize( $def, wp_unslash( $_POST[ 'reg_' . $key ] ) );
		}
	}
	// The number and the name came from the proved ticket, not from this form. A
	// re-post cannot move an application onto a different phone.
	$in['mobile'] = $ticket['mobile'];
	if ( empty( $in['name'] ) ) {
		$in['name'] = $ticket['name'] ?? '';
	}
	// When the code travelled by EMAIL, the address it was read from is the one thing
	// this application has actually proved. Letting step three replace it would throw
	// that away and leave the record carrying an address nobody has ever demonstrated
	// reaching. When the code went by SMS neither address is proved, so the applicant
	// may correct a typo freely.
	if ( 'email' === ( $ticket['channel'] ?? '' ) && ! empty( $ticket['email'] ) ) {
		$in['email'] = $ticket['email'];
	}

	// Uploads, now that there is a ticket. An error stops the submit rather than
	// filing an application that quietly lost its scans.
	foreach ( bhela_bm_signup_file_keys() as $key ) {
		if ( empty( $_FILES[ 'reg_file_' . $key ]['name'] ) ) {
			continue;
		}
		$url = bhela_bm_investor_upload( 'reg_file_' . $key );
		if ( is_wp_error( $url ) ) {
			bhela_bm_signup_state( array(
				'step'   => 'details',
				'ticket' => $tid,
				'mask'   => bhela_bm_chal_mask( $ticket['mobile'] ),
				'in'     => $in,
				'err'    => sprintf(
					/* translators: 1: field label, 2: the reason */
					__( '%1$s: %2$s', 'bhela-booking' ),
					$defs[ $key ]['label'] ?? $key,
					$url->get_error_message()
				),
			) );
			return '';
		}
		$in[ $key ] = $url;
	}

	$res = bhela_bm_signup_add( $in );
	if ( is_wp_error( $res ) ) {
		bhela_bm_signup_state( array(
			'step'   => 'details',
			'ticket' => $tid,
			'mask'   => bhela_bm_chal_mask( $ticket['mobile'] ),
			'in'     => $in,
			'err'    => $res->get_error_message(),
		) );
		return '';
	}

	bhela_bm_signup_ticket_spend( $tid );
	return add_query_arg( 'bhela_reg', 'done', bhela_bm_signup_url() );
}

function bhela_bm_signup_shortcode() {
	wp_enqueue_style( 'bhela-bm-investor' );

	$done = isset( $_GET['bhela_reg'] ) ? sanitize_key( wp_unslash( $_GET['bhela_reg'] ) ) : '';
	if ( 'done' === $done ) {
		return bhela_bm_signup_panel(
			__( 'নিবন্ধন জমা হয়েছে ✅', 'bhela-booking' ),
			__( 'আপনার মোবাইল নম্বর যাচাই হয়েছে এবং নিবন্ধনটি BHELA অফিসে পাঠানো হয়েছে। অফিস অনুমোদন করার পর আপনি SMS পাবেন — তারপর পোর্টালে সাইন ইন করতে পারবেন। অনুমোদন ছাড়া পোর্টাল খোলা যায় না।', 'bhela-booking' )
		);
	}
	if ( 'exists' === $done ) {
		return bhela_bm_signup_panel(
			__( 'এই নম্বরে পোর্টাল আগেই চালু আছে', 'bhela-booking' ),
			__( 'নতুন করে নিবন্ধনের দরকার নেই — সরাসরি সাইন ইন করুন।', 'bhela-booking' ),
			bhela_bm_portal_url()
		);
	}

	if ( is_user_logged_in() && bhela_bm_current_investor() ) {
		return bhela_bm_signup_panel(
			__( 'আপনি সাইন ইন করা আছেন', 'bhela-booking' ),
			__( 'আপনার পোর্টাল খোলা আছে।', 'bhela-booking' ),
			bhela_bm_portal_url()
		);
	}

	return bhela_bm_signup_form();
}
add_shortcode( 'bhela_investor_register', 'bhela_bm_signup_shortcode' );

/** A one-message panel — used for every terminal state of the form. */
function bhela_bm_signup_panel( $title, $body, $link = '' ) {
	ob_start();
	?>
	<div class="bhela-inv bhela-inv--login">
		<div class="bhela-inv__card">
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $body ); ?></p>
			<?php if ( $link ) : ?>
				<p><a class="bhela-inv__btn" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'পোর্টালে যান', 'bhela-booking' ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/** One field, rendered. Shared by the first screen and the full one. */
function bhela_bm_signup_field( $key, $def, $value ) {
	$type = $def['type'] ?? 'text';
	$req  = ! empty( $def['required'] );
	?>
	<label>
		<?php echo esc_html( $def['label'] ); ?><?php echo $req ? ' *' : ''; ?>
		<?php if ( 'textarea' === $type ) : ?>
			<textarea name="reg_<?php echo esc_attr( $key ); ?>" rows="2"<?php echo $req ? ' required' : ''; ?>><?php echo esc_textarea( $value ); ?></textarea>
		<?php elseif ( 'select' === $type ) : ?>
			<select name="reg_<?php echo esc_attr( $key ); ?>">
				<?php foreach ( (array) ( $def['options'] ?? array() ) as $ov => $ol ) : ?>
					<option value="<?php echo esc_attr( $ov ); ?>"<?php selected( $value, $ov ); ?>><?php echo esc_html( $ol ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php elseif ( 'file' === $type ) : ?>
			<input type="file" name="reg_file_<?php echo esc_attr( $key ); ?>"
				accept="<?php echo esc_attr( implode( ',', bhela_bm_investor_upload_accept() ) ); ?>">
			<?php if ( $value ) : ?>
				<span class="bhela-inv__help"><?php esc_html_e( 'একটি ফাইল যুক্ত হয়েছে ✅', 'bhela-booking' ); ?></span>
			<?php endif; ?>
		<?php elseif ( 'date' === $type ) : ?>
			<input type="date" name="reg_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $req ? ' required' : ''; ?>>
		<?php else : ?>
			<input type="<?php echo 'email' === $type ? 'email' : 'text'; ?>"
				name="reg_<?php echo esc_attr( $key ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php echo 'mobile' === $key ? ' inputmode="numeric" placeholder="01XXXXXXXXX" maxlength="20"' : ''; ?>
				<?php echo $req ? ' required' : ''; ?>>
		<?php endif; ?>
		<?php if ( ! empty( $def['help'] ) ) : ?>
			<span class="bhela-inv__help"><?php echo esc_html( $def['help'] ); ?></span>
		<?php endif; ?>
	</label>
	<?php
}

function bhela_bm_signup_form() {
	$state = bhela_bm_signup_state();
	$step  = $state['step'] ?? 'start';
	$err   = $state['err'] ?? '';
	$in    = is_array( $state['in'] ?? null ) ? $state['in'] : array();
	$defs  = bhela_bm_signup_keys();

	ob_start();
	?>
	<div class="bhela-inv bhela-inv--login<?php echo 'details' === $step ? ' bhela-inv--wide' : ''; ?>">
		<div class="bhela-inv__card">
			<h2><?php esc_html_e( 'বিনিয়োগকারী নিবন্ধন', 'bhela-booking' ); ?></h2>
			<ol class="bhela-inv__steps">
				<li<?php echo 'start' === $step ? ' class="is-now"' : ''; ?>><?php esc_html_e( 'নম্বর', 'bhela-booking' ); ?></li>
				<li<?php echo 'code' === $step ? ' class="is-now"' : ''; ?>><?php esc_html_e( 'যাচাই', 'bhela-booking' ); ?></li>
				<li<?php echo 'details' === $step ? ' class="is-now"' : ''; ?>><?php esc_html_e( 'তথ্য ও কাগজপত্র', 'bhela-booking' ); ?></li>
			</ol>
			<?php if ( $err ) : ?>
				<p class="bhela-inv__err"><?php echo esc_html( $err ); ?></p>
			<?php endif; ?>

			<?php if ( 'code' === $step ) : ?>
				<p class="bhela-inv__muted"><?php
					printf(
						/* translators: %s: masked mobile number */
						esc_html__( '%s নম্বরে একটি ৬ সংখ্যার কোড পাঠানো হয়েছে। কোডটি দিলে বাকি তথ্য ও কাগজপত্র দেওয়ার সুযোগ আসবে।', 'bhela-booking' ),
						'<strong>' . esc_html( $state['mask'] ?? '' ) . '</strong>'
					);
				?></p>
				<form method="post">
					<?php wp_nonce_field( 'bhela_inv_register', 'bhela_reg_nonce' ); ?>
					<input type="hidden" name="bhela_reg_step" value="code">
					<input type="hidden" name="bhela_reg_chal" value="<?php echo esc_attr( $state['chal'] ?? '' ); ?>">
					<input type="hidden" name="bhela_reg_mask" value="<?php echo esc_attr( $state['mask'] ?? '' ); ?>">
					<p class="bhela-inv__hp"><input type="text" name="bhela_bm_hp" value="" tabindex="-1" autocomplete="off"></p>
					<label><?php esc_html_e( 'কোড', 'bhela-booking' ); ?>
						<input type="text" name="bhela_reg_code" inputmode="numeric" autocomplete="one-time-code"
							pattern="[0-9]*" maxlength="6" required autofocus></label>
					<button type="submit" class="bhela-inv__btn"><?php esc_html_e( 'যাচাই করুন', 'bhela-booking' ); ?></button>
				</form>

			<?php elseif ( 'details' === $step ) : ?>
				<p class="bhela-inv__muted"><?php
					printf(
						/* translators: %s: masked mobile number */
						esc_html__( '%s নম্বরটি যাচাই হয়েছে ✅ এখন বাকি তথ্য দিন। নাম আর মোবাইল ছাড়া সবই ইচ্ছাধীন — যা এখন নেই, অফিস পরে বসিয়ে নেবে।', 'bhela-booking' ),
						'<strong>' . esc_html( $state['mask'] ?? '' ) . '</strong>'
					);
				?></p>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'bhela_inv_register', 'bhela_reg_nonce' ); ?>
					<input type="hidden" name="bhela_reg_step" value="details">
					<input type="hidden" name="bhela_reg_ticket" value="<?php echo esc_attr( $state['ticket'] ?? '' ); ?>">
					<p class="bhela-inv__hp"><input type="text" name="bhela_bm_hp" value="" tabindex="-1" autocomplete="off"></p>
					<?php foreach ( bhela_bm_signup_groups() as $group ) : ?>
						<fieldset class="bhela-inv__group">
							<legend><?php echo esc_html( $group['label'] ); ?></legend>
							<?php foreach ( $group['fields'] as $key => $def ) : ?>
								<?php bhela_bm_signup_field( $key, $def, (string) ( $in[ $key ] ?? '' ) ); ?>
							<?php endforeach; ?>
						</fieldset>
					<?php endforeach; ?>
					<p class="bhela-inv__muted"><?php
						printf(
							/* translators: %d: megabytes */
							esc_html__( 'ছবি বা PDF, প্রতিটি সর্বোচ্চ %d MB।', 'bhela-booking' ),
							(int) ( bhela_bm_investor_upload_max() / MB_IN_BYTES )
						);
					?></p>
					<button type="submit" class="bhela-inv__btn"><?php esc_html_e( 'নিবন্ধন জমা দিন', 'bhela-booking' ); ?></button>
				</form>

			<?php else : ?>
				<p class="bhela-inv__muted"><?php esc_html_e( 'পাসওয়ার্ড লাগবে না। প্রথমে নাম ও মোবাইল নম্বর দিন — নম্বরে একটি কোড পাঠানো হবে। যাচাইয়ের পর বাকি তথ্য ও কাগজপত্র দেওয়া যাবে, আর BHELA অফিস অনুমোদন করলে পোর্টাল চালু হবে।', 'bhela-booking' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'bhela_inv_register', 'bhela_reg_nonce' ); ?>
					<input type="hidden" name="bhela_reg_step" value="start">
					<p class="bhela-inv__hp"><input type="text" name="bhela_bm_hp" value="" tabindex="-1" autocomplete="off"></p>
					<?php foreach ( bhela_bm_signup_first_keys() as $key ) : ?>
						<?php bhela_bm_signup_field( $key, $defs[ $key ] ?? array( 'label' => $key ), (string) ( $in[ $key ] ?? '' ) ); ?>
					<?php endforeach; ?>
					<p class="bhela-inv__help"><?php esc_html_e( 'নাম আর নম্বর ছাড়া এখন আর কিছু লাগবে না।', 'bhela-booking' ); ?></p>
					<button type="submit" class="bhela-inv__btn"><?php esc_html_e( 'কোড পাঠান', 'bhela-booking' ); ?></button>
				</form>
				<p class="bhela-inv__muted">
					<?php esc_html_e( 'আগেই নিবন্ধন করেছেন?', 'bhela-booking' ); ?>
					<a href="<?php echo esc_url( bhela_bm_portal_url() ); ?>"><?php esc_html_e( 'সাইন ইন করুন', 'bhela-booking' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/* =========================================================
 * Provisioning the two pages
 * ========================================================= */

/**
 * Create the portal and registration pages, once, ever.
 *
 * The flag is set whether or not a page was created, so a page somebody deliberately
 * deleted is never resurrected on the next admin page load — the same restraint the
 * theme's auto-page creation shows.
 */
function bhela_bm_provision_portal_pages() {
	if ( get_option( 'bhela_bm_portal_pages' ) ) {
		return;
	}
	update_option( 'bhela_bm_portal_pages', 1 );

	foreach ( array(
		'investor'          => array( __( 'বিনিয়োগকারী পোর্টাল', 'bhela-booking' ), '[bhela_investor_portal]' ),
		'investor-register' => array( __( 'বিনিয়োগকারী নিবন্ধন', 'bhela-booking' ), '[bhela_investor_register]' ),
	) as $slug => $page ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		wp_insert_post( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_name'      => $slug,
			'post_title'     => $page[0],
			'post_content'   => $page[1],
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );
	}
}
add_action( 'admin_init', 'bhela_bm_provision_portal_pages', 6 );
