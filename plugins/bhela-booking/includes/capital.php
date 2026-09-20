<?php
/**
 * Dated capital contributions — what was put in, and when.
 *
 * Until this file existed the plugin could not answer "কোন বছরে কত টাকা ইনভেস্ট করেছি",
 * which is the first thing an Investment Certificate is for. Every founding holding is
 * two undated scalars on the investor record — `_bhela_inv_shares` and
 * `_bhela_inv_amount` — and the only dated capital records anywhere are the
 * `bhela_share_issue` rows, which began with the valuation module. So an investor who
 * paid in over four years had one number and no history.
 *
 * Four rules, each with a precedent elsewhere in this plugin:
 *
 * 1. **A row is immutable from birth**, like a committed share issue and for the same
 *    reason: it is the evidence behind a document somebody is holding. It is covered by
 *    the lock in includes/valuation-core.php rather than by a fourth lock of its own —
 *    that file already carries every hook §13.49 and §13.55 catalogue, and a new lock
 *    written from a shortened reading of it is exactly how both of those happened.
 * 2. **A mistake is VOIDED, never deleted.** `_bhela_cap_void` is deliberately outside
 *    the lock, the same deliberate exception `_bhela_cost_status` is (§13.40): a lock
 *    that cannot be lifted is a trap. A voided row still reads, carries its reason, and
 *    counts towards nothing.
 * 3. **It does not write `_bhela_inv_amount`.** That scalar stays the register's figure
 *    and `bhela_bm_capital_drift()` REPORTS the disagreement — two writers for one
 *    number is §13.44, and silently rescaling one to match the other would hide a
 *    contribution nobody recorded.
 * 4. **A committed share issue IS a capital contribution** and is read as one, tagged
 *    `source => 'issue'`. Without that, a round already recorded would be typed in
 *    again and the investor's history would double.
 *
 * Loaded on EVERY request, not behind `is_admin()`: the certificate renders on a
 * front-end URL and reads these rows.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * The post type
 * ========================================================= */

function bhela_bm_register_capital_cpt() {
	register_post_type( 'bhela_capital', array(
		'labels' => array(
			'name'          => __( 'Capital', 'bhela-booking' ),
			'singular_name' => __( 'Capital contribution', 'bhela-booking' ),
		),
		// Same posture as the investor record it belongs to: it names a person and an
		// amount of money, so it must never be addressable from the front end.
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
add_action( 'init', 'bhela_bm_register_capital_cpt' );

/** How many rows one listing reads. Filterable. */
function bhela_bm_capital_limit() {
	return (int) apply_filters( 'bhela_bm_capital_limit', 500 );
}

/* =========================================================
 * WRITING
 * ========================================================= */

/**
 * Record one dated contribution.
 *
 * @param array $args investor, date, amount, shares, method, ref, note, investment.
 * @return int|WP_Error Row id.
 */
function bhela_bm_capital_add( $args ) {
	if ( ! current_user_can( 'bhela_investor_capital' ) ) {
		return new WP_Error( 'denied', __( 'মূলধন রেকর্ড করার অনুমতি নেই।', 'bhela-booking' ) );
	}

	$investor = (int) ( $args['investor'] ?? 0 );
	$amount   = (int) ( $args['amount'] ?? 0 );
	$shares   = max( 0, (int) ( $args['shares'] ?? 0 ) );
	$date     = bhela_bm_report_date( $args['date'] ?? '' );
	// Which Investment Record this receipt paid into, when there is one. A row without
	// it is pre-system history and still reads everywhere it did before — the link is
	// what lets an investment derive its principal instead of carrying a typed copy.
	$invest   = (int) ( $args['investment'] ?? 0 );
	if ( $invest && 'bhela_investment' !== get_post_type( $invest ) ) {
		return new WP_Error( 'bad_investment', __( 'বিনিয়োগ রেকর্ড পাওয়া যায়নি।', 'bhela-booking' ) );
	}

	if ( ! $investor || 'bhela_investor' !== get_post_type( $investor ) ) {
		return new WP_Error( 'no_investor', __( 'বিনিয়োগকারী নির্বাচন করুন।', 'bhela-booking' ) );
	}
	if ( $amount <= 0 ) {
		return new WP_Error( 'bad_amount', __( 'পরিমাণ শূন্যের বেশি হতে হবে।', 'bhela-booking' ) );
	}
	if ( '' === $date ) {
		// Deliberately refused rather than defaulted to today. The whole point of this
		// record is the date; a row dated "whenever it was typed in" would put a wrong
		// year on a certificate, which is worse than no year at all.
		return new WP_Error( 'no_date', __( 'তারিখ ছাড়া মূলধন রেকর্ড করা যাবে না — এই রেকর্ডের মূল বিষয়ই তারিখ।', 'bhela-booking' ) );
	}

	$id = wp_insert_post( array(
		'post_type'   => 'bhela_capital',
		'post_status' => 'publish',
		'post_title'  => sprintf(
			/* translators: 1: investor name, 2: date, 3: amount */
			__( '%1$s — %2$s — %3$s', 'bhela-booking' ),
			get_the_title( $investor ),
			$date,
			bhela_bm_money( $amount )
		),
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	// Through the lock's own window: this record is immutable from the moment it
	// exists, so even its first write needs the pen.
	foreach ( array(
		'investor'   => $investor,
		'investment' => $invest,
		'date'     => $date,
		'amount'   => $amount,
		'shares'   => $shares,
		'method'   => sanitize_text_field( $args['method'] ?? '' ),
		'ref'      => sanitize_text_field( $args['ref'] ?? '' ),
		'note'     => sanitize_textarea_field( $args['note'] ?? '' ),
		'by'       => get_current_user_id(),
		'at'       => current_time( 'mysql' ),
	) as $k => $v ) {
		bhela_bm_val_meta_write( $id, '_bhela_cap_' . $k, $v );
	}

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'capital_add',
		'object_type' => 'capital',
		'object_id'   => $id,
		'object_ref'  => get_the_title( $investor ),
		'field'       => 'amount',
		'new_value'   => (string) $amount,
		'reason'      => sprintf(
			/* translators: 1: date, 2: shares */
			__( 'Dated %1$s, %2$d shares.', 'bhela-booking' ),
			$date,
			$shares
		),
	) );

	return (int) $id;
}

/**
 * Void a row that should not have been recorded.
 *
 * Not a delete. The row stays readable with its reason attached, because a certificate
 * may already have been issued from a register that contained it, and "it was removed"
 * answers nothing a year later.
 */
function bhela_bm_capital_void( $id, $reason ) {
	if ( ! current_user_can( 'bhela_investor_capital' ) ) {
		return new WP_Error( 'denied', __( 'মূলধন রেকর্ড বাতিল করার অনুমতি নেই।', 'bhela-booking' ) );
	}
	if ( 'bhela_capital' !== get_post_type( $id ) ) {
		return new WP_Error( 'not_found', __( 'রেকর্ড পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	$reason = sanitize_textarea_field( $reason );
	if ( '' === $reason ) {
		return new WP_Error( 'no_reason', __( 'বাতিলের কারণ লিখুন।', 'bhela-booking' ) );
	}
	if ( get_post_meta( $id, '_bhela_cap_void', true ) ) {
		return new WP_Error( 'already', __( 'এই রেকর্ড আগেই বাতিল করা হয়েছে।', 'bhela-booking' ) );
	}

	// These two keys sit OUTSIDE the lock on purpose — see the file header.
	update_post_meta( $id, '_bhela_cap_void', 1 );
	update_post_meta( $id, '_bhela_cap_void_reason', $reason );
	update_post_meta( $id, '_bhela_cap_void_by', get_current_user_id() );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'capital_void',
		'object_type' => 'capital',
		'object_id'   => (int) $id,
		'object_ref'  => get_the_title( (int) get_post_meta( $id, '_bhela_cap_investor', true ) ),
		'field'       => 'void',
		'old_value'   => (string) (int) get_post_meta( $id, '_bhela_cap_amount', true ),
		'new_value'   => '0',
		'reason'      => $reason,
	) );

	return true;
}

/* =========================================================
 * READING
 * ========================================================= */

/** One row, or null. */
function bhela_bm_capital( $id ) {
	if ( 'bhela_capital' !== get_post_type( $id ) ) {
		return null;
	}
	$m = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_bhela_cap_' . $k, true );
	};
	$investor = (int) $m( 'investor' );
	return array(
		'id'         => (int) $id,
		'investor'   => $investor,
		'investment' => (int) $m( 'investment' ),
		'name'       => $investor ? get_the_title( $investor ) : '',
		'date'       => (string) $m( 'date' ),
		'amount'     => (int) $m( 'amount' ),
		'shares'     => (int) $m( 'shares' ),
		'method'     => (string) $m( 'method' ),
		'ref'        => (string) $m( 'ref' ),
		'note'       => (string) $m( 'note' ),
		'by'         => (int) $m( 'by' ),
		'at'         => (string) $m( 'at' ),
		'source'     => 'manual',
		'void'       => (bool) $m( 'void' ),
		'void_reason' => (string) $m( 'void_reason' ),
	);
}

/**
 * Every dated contribution, oldest first, with committed share issues merged in.
 *
 * Blank dates mean EVERY date — a sentinel range reads as "no filter" and is not one
 * (§13.24, §13.51).
 *
 * @param int    $investor Investor post id, or 0 for everybody.
 * @param string $from     Y-m-d or ''.
 * @param string $to       Y-m-d or ''.
 * @return array[]
 */
function bhela_bm_capital_rows( $investor = 0, $from = '', $to = '' ) {
	$investor = (int) $investor;
	$from     = bhela_bm_report_date( $from );
	$to       = bhela_bm_report_date( $to );

	$query = array(
		'post_type'      => 'bhela_capital',
		'post_status'    => 'publish',
		'posts_per_page' => bhela_bm_capital_limit(),
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	);
	if ( $investor ) {
		$query['meta_key']   = '_bhela_cap_investor';
		$query['meta_value'] = $investor;
	}

	$out = array();
	foreach ( get_posts( $query ) as $id ) {
		$row = bhela_bm_capital( $id );
		if ( $row ) {
			$out[] = $row;
		}
	}

	// A committed share issue is money that arrived on a date, which is what this
	// listing is. It is read, never copied: the issue record stays the only place that
	// round is stored.
	if ( function_exists( 'bhela_bm_share_issues' ) ) {
		foreach ( bhela_bm_share_issues() as $iss ) {
			if ( $investor && (int) $iss['investor'] !== $investor ) {
				continue;
			}
			$out[] = array(
				'id'          => (int) $iss['id'],
				'investor'    => (int) $iss['investor'],
				'investment'  => 0,
				'name'        => get_the_title( $iss['investor'] ),
				'date'        => (string) $iss['date'],
				'amount'      => (int) $iss['amount'],
				'shares'      => (int) $iss['shares'],
				'method'      => '',
				'ref'         => '',
				'note'        => (string) $iss['note'],
				'by'          => (int) $iss['by'],
				'at'          => (string) $iss['at'],
				'source'      => 'issue',
				'void'        => false,
				'void_reason' => '',
			);
		}
	}

	$out = array_values( array_filter( $out, function ( $r ) use ( $from, $to ) {
		if ( '' === $r['date'] ) {
			return false;
		}
		if ( $from && $r['date'] < $from ) {
			return false;
		}
		if ( $to && $r['date'] > $to ) {
			return false;
		}
		return true;
	} ) );

	usort( $out, function ( $a, $b ) {
		return ( $a['date'] <=> $b['date'] ) ?: ( $a['id'] <=> $b['id'] );
	} );
	return $out;
}

/**
 * Every live capital row paid into one investment.
 *
 * Voided rows are excluded, which is what makes this safe to sum: a receipt cancelled
 * with a reason must not still be counted as principal on a certificate. Share issues
 * are not here either — they belong to the equity model, and an investment's principal
 * is what arrived against THAT agreement.
 *
 * @param int $investment Investment post id.
 * @return array[]
 */
function bhela_bm_capital_rows_for_investment( $investment ) {
	$investment = (int) $investment;
	if ( ! $investment ) {
		return array();
	}
	$out = array();
	foreach ( get_posts( array(
		'post_type'      => 'bhela_capital',
		'post_status'    => 'publish',
		'posts_per_page' => bhela_bm_capital_limit(),
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'meta_key'       => '_bhela_cap_investment',
		'meta_value'     => $investment,
	) ) as $id ) {
		$row = bhela_bm_capital( $id );
		if ( $row && ! $row['void'] ) {
			$out[] = $row;
		}
	}
	return $out;
}

/**
 * An investor's capital rows that belong to no investment yet.
 *
 * Every row recorded before the Investment Record existed is one of these, and so is
 * anything typed on the 💼 Capital screen without picking an investment. They are not
 * orphans to be cleaned up — they are real receipts — but an investment cannot derive a
 * principal from them until somebody says which agreement they were against.
 *
 * @return array[]
 */
function bhela_bm_capital_unlinked( $investor ) {
	$out = array();
	foreach ( bhela_bm_capital_rows( (int) $investor ) as $row ) {
		if ( $row['investment'] || $row['void'] || 'manual' !== $row['source'] ) {
			continue;
		}
		$out[] = $row;
	}
	return $out;
}

/**
 * Attach an existing receipt to an investment.
 *
 * The one write this module allows against a locked row, and it is deliberately narrow:
 * it may only fill the link when it is EMPTY, never move a row from one investment to
 * another. Moving one would change two principals at once — including, possibly, one a
 * certificate has already been issued against.
 *
 * @return true|WP_Error
 */
function bhela_bm_capital_link( $row_id, $investment ) {
	if ( ! current_user_can( 'bhela_investor_capital' ) ) {
		return new WP_Error( 'denied', __( 'মূলধন রেকর্ড করার অনুমতি নেই।', 'bhela-booking' ) );
	}
	$row = bhela_bm_capital( $row_id );
	$inv = function_exists( 'bhela_bm_investment' ) ? bhela_bm_investment( $investment ) : null;
	if ( ! $row || ! $inv ) {
		return new WP_Error( 'not_found', __( 'রেকর্ড পাওয়া যায়নি।', 'bhela-booking' ) );
	}
	if ( $row['investment'] ) {
		return new WP_Error(
			'already',
			__( 'এই প্রাপ্তি আগেই একটি বিনিয়োগের সঙ্গে যুক্ত — সরানো যায় না, কারণ তাতে দুটি বিনিয়োগের মূলধনই বদলে যেত।', 'bhela-booking' )
		);
	}
	if ( (int) $row['investor'] !== (int) $inv['investor'] ) {
		return new WP_Error( 'wrong_investor', __( 'এই প্রাপ্তি অন্য বিনিয়োগকারীর।', 'bhela-booking' ) );
	}
	if ( 'draft' !== $inv['status'] ) {
		// Attaching to a live investment would move its principal underneath any
		// certificate already issued from it. Reopen to draft first, with a reason.
		return new WP_Error(
			'locked',
			__( 'সক্রিয় বিনিয়োগে প্রাপ্তি যোগ করা যায় না — আগে কারণসহ খসড়ায় ফেরত আনুন।', 'bhela-booking' )
		);
	}

	bhela_bm_val_meta_write( $row_id, '_bhela_cap_investment', (int) $investment );

	bhela_bm_audit( array(
		'channel'     => 'investor',
		'action'      => 'capital_link',
		'object_type' => 'capital',
		'object_id'   => (int) $row_id,
		'object_ref'  => $inv['code'],
		'field'       => 'investment',
		'old_value'   => '0',
		'new_value'   => (string) (int) $investment,
		'reason'      => __( 'Existing receipt attached to an investment.', 'bhela-booking' ),
	) );
	return true;
}

/**
 * One investor's capital history grouped by year, plus the part that has no date.
 *
 * `undated` is the figure the register holds that no dated row accounts for. It is
 * NAMED rather than given a year: putting a contribution into a year nobody recorded
 * would be a confident wrong answer on a document somebody files with a bank.
 *
 * @param int $investor Investor post id.
 * @return array
 */
function bhela_bm_capital_years( $investor ) {
	$investor = (int) $investor;
	$rows     = bhela_bm_capital_rows( $investor );

	$years        = array();
	$dated        = 0;
	$dated_shares = 0;

	foreach ( $rows as $r ) {
		if ( $r['void'] ) {
			continue;                       // voided rows count towards nothing
		}
		$year = substr( $r['date'], 0, 4 );
		if ( ! isset( $years[ $year ] ) ) {
			$years[ $year ] = array( 'year' => $year, 'amount' => 0, 'shares' => 0, 'rows' => array() );
		}
		$years[ $year ]['amount'] += $r['amount'];
		$years[ $year ]['shares'] += $r['shares'];
		$years[ $year ]['rows'][]  = $r;
		$dated        += $r['amount'];
		$dated_shares += $r['shares'];
	}
	ksort( $years );

	$register        = bhela_bm_investor_amount( $investor );
	$register_shares = bhela_bm_investor_shares( $investor );

	return array(
		'investor'       => $investor,
		'rows'           => $rows,
		'years'          => array_values( $years ),
		'dated'          => $dated,
		'dated_shares'   => $dated_shares,
		// What the register says but no dated row explains. Never negative: when the
		// dated rows exceed the register the difference is drift, not a contribution,
		// and bhela_bm_capital_drift() is where that is reported.
		'undated'        => max( 0, $register - $dated ),
		'undated_shares' => max( 0, $register_shares - $dated_shares ),
		'register'       => $register,
		'shares'         => $register_shares,
	);
}

/**
 * Do the dated rows and the register agree?
 *
 * **Reports, never corrects** — the same contract as bhela_bm_share_issue_drift() and
 * the cost sheet's earnings drift. When the books disagree with the register, a person
 * decides which is wrong; rewriting either one to make the screen tidy hides whichever
 * of them was right.
 *
 * @return array{dated:int,register:int,gap:int,over:bool,complete:bool}
 */
function bhela_bm_capital_drift( $investor ) {
	$y   = bhela_bm_capital_years( $investor );
	$gap = $y['dated'] - $y['register'];
	return array(
		'dated'    => $y['dated'],
		'register' => $y['register'],
		'gap'      => $gap,
		'over'     => $gap > 0,
		'complete' => 0 === $gap,
	);
}
