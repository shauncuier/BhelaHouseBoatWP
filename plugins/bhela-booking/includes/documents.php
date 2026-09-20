<?php
/**
 * The two documents that are a VIEW of a record rather than a snapshot of one.
 *
 * A certificate is frozen because it asserts a computed position — gross, net, paid,
 * due — and those move. A **payment receipt** and an **account statement** do not need
 * freezing, and pretending they do would be cargo-culting:
 *
 * - A receipt says "this exact amount arrived on this date under this reference". Its
 *   source is a single `bhela_capital` row or a single ledger row, and BOTH are already
 *   immutable — the capital row is locked from birth and the ledger is append-only with
 *   contra-row reversal. There is nothing a snapshot could protect that the record does
 *   not already protect itself. A reversed payment's receipt says so, which is the
 *   honest behaviour and one a frozen copy could not manage.
 * - A statement is an as-of-today reading by definition. Freezing one would mean the
 *   investor's own "current statement" stopped being current.
 *
 * Both reach the same three-way access rule the certificate uses: a timing-safe key, the
 * signed-in investor it belongs to, or somebody holding `bhela_investors_view`.
 *
 * The statement is the brief's §13: one running balance across capital in, profit
 * earned and profit paid. Note what that means and say it plainly on the page — it is
 * **what BHELA owes this investor**, so the principal is a credit. It is not a valuation
 * of their holding and it is not the Monthly Statement, which answers a different
 * question about the business.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * RECEIPTS
 * ========================================================= */

/**
 * The two kinds, which the brief's §12 insists are never the same transaction.
 *
 * `capital` — money the investor paid IN, against an investment.
 * `payment` — profit BHELA paid OUT.
 */
function bhela_bm_receipt_kinds() {
	return array(
		'capital' => array(
			'label' => __( 'বিনিয়োগ প্রাপ্তি রসিদ', 'bhela-booking' ),
			'en'    => __( 'Investment Receipt', 'bhela-booking' ),
			'dir'   => __( 'Received from', 'bhela-booking' ),
		),
		'payment' => array(
			'label' => __( 'লাভ পরিশোধ রসিদ', 'bhela-booking' ),
			'en'    => __( 'Profit Payment Receipt', 'bhela-booking' ),
			'dir'   => __( 'Paid to', 'bhela-booking' ),
		),
	);
}

/**
 * The receipt number.
 *
 * Derived from the record's own id rather than minted from a counter, and deliberately
 * so: the row it describes is already unique and already immutable, so a second series
 * to keep in step would be a second thing that can drift. Reprinting a receipt always
 * gives the same number because the record always has the same id.
 */
function bhela_bm_receipt_number( $kind, $row_id, $date ) {
	$s      = bhela_bm_get_settings();
	$prefix = preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $s['doc_prefix'] ?? '' ) );
	$prefix = '' === $prefix ? 'BHELA' : strtoupper( $prefix );
	$infix  = 'capital' === $kind ? 'RC' : 'RP';
	$year   = $date ? substr( $date, 0, 4 ) : current_time( 'Y' );
	return sprintf( '%s-%s-%s-%d', $prefix, $infix, $year, (int) $row_id );
}

/**
 * Everything a receipt prints, or null.
 *
 * @param string $kind   capital|payment.
 * @param int    $row_id The capital row or ledger row.
 * @return array|null
 */
function bhela_bm_receipt_data( $kind, $row_id ) {
	$kind  = sanitize_key( $kind );
	$kinds = bhela_bm_receipt_kinds();
	if ( ! isset( $kinds[ $kind ] ) ) {
		return null;
	}

	if ( 'capital' === $kind ) {
		$row = bhela_bm_capital( $row_id );
		if ( ! $row ) {
			return null;
		}
		$investment = $row['investment'] ? bhela_bm_investment( $row['investment'] ) : null;
		return array(
			'kind'       => $kind,
			'label'      => $kinds[ $kind ]['label'],
			'en'         => $kinds[ $kind ]['en'],
			'dir'        => $kinds[ $kind ]['dir'],
			'number'     => bhela_bm_receipt_number( $kind, $row['id'], $row['date'] ),
			'investor'   => $row['investor'],
			'name'       => $row['name'],
			'code'       => (string) get_post_meta( $row['investor'], '_bhela_inv_code', true ),
			'date'       => $row['date'],
			'amount'     => $row['amount'],
			'method'     => $row['method'],
			'ref'        => $row['ref'],
			'note'       => $row['note'],
			'investment' => $investment ? $investment['code'] : '',
			'agreement'  => $investment ? $investment['agreement_ref'] : '',
			// A voided receipt still prints and says it was voided. Somebody may be
			// holding it, and silence would be the worst of the three options.
			'void'       => $row['void'],
			'void_reason' => $row['void_reason'],
		);
	}

	$row = bhela_bm_ledger_row( $row_id );
	if ( ! $row || ! in_array( $row['type'], array( 'payment', 'advance' ), true ) ) {
		return null;
	}
	$reversed = bhela_bm_ledger_reversal_of( $row['id'] );
	return array(
		'kind'        => $kind,
		'label'       => $kinds[ $kind ]['label'],
		'en'          => $kinds[ $kind ]['en'],
		'dir'         => $kinds[ $kind ]['dir'],
		'number'      => bhela_bm_receipt_number( $kind, $row['id'], $row['date'] ),
		'investor'    => $row['investor'],
		'name'        => get_the_title( $row['investor'] ),
		'code'        => (string) get_post_meta( $row['investor'], '_bhela_inv_code', true ),
		'date'        => $row['date'],
		'amount'      => $row['amount'],
		'method'      => $row['method'],
		'ref'         => $row['ref'],
		'note'        => $row['note'],
		'investment'  => '',
		'agreement'   => '',
		'void'        => (bool) $reversed,
		'void_reason' => $reversed ? __( 'এই পরিশোধ পরে বাতিল করা হয়েছে।', 'bhela-booking' ) : '',
	);
}

function bhela_bm_receipt_key( $kind, $row_id ) {
	return wp_hash( 'bhela-receipt-' . sanitize_key( $kind ) . '-' . (int) $row_id );
}

function bhela_bm_receipt_url( $kind, $row_id ) {
	return add_query_arg( array(
		'bhela_receipt' => sanitize_key( $kind ),
		'row'           => (int) $row_id,
		'key'           => bhela_bm_receipt_key( $kind, $row_id ),
	), home_url( '/' ) );
}

/* =========================================================
 * THE ACCOUNT STATEMENT
 * ========================================================= */

/**
 * One investor's account, as the brief's §13 lays it out.
 *
 * A single running balance over three kinds of row:
 *
 *   capital in  → CREDIT  (BHELA now owes the principal back)
 *   profit      → CREDIT  (and owes the profit too)
 *   payment out → DEBIT   (some of it has been handed over)
 *
 * So the closing balance is **what BHELA owes this investor** — principal plus profit
 * earned, less profit paid. That is a liability reading, not a valuation of a holding,
 * and the template says so in words: under the fixed-return model an investor is owed
 * their money back, which is a different thing from owning a share of the boat.
 *
 * Reversed rows are shown and marked, not dropped: a statement that silently omits a
 * cancelled payment cannot be reconciled against the receipts somebody is holding.
 *
 * @return array
 */
function bhela_bm_account_statement( $investor, $from = '', $to = '' ) {
	$investor = (int) $investor;
	$from     = bhela_bm_report_date( $from );
	$to       = bhela_bm_report_date( $to );

	$out = array(
		'investor'   => $investor,
		'name'       => $investor ? get_the_title( $investor ) : '',
		'code'       => (string) get_post_meta( $investor, '_bhela_inv_code', true ),
		'from'       => $from,
		'to'         => $to,
		'rows'       => array(),
		'capital'    => 0,
		'profit'     => 0,
		'paid'       => 0,
		'adjustment' => 0,
		'balance'    => 0,
	);
	if ( ! $investor || 'bhela_investor' !== get_post_type( $investor ) ) {
		return $out;
	}

	$rows = array();

	foreach ( bhela_bm_capital_rows( $investor ) as $c ) {
		if ( $c['void'] ) {
			continue;                       // a voided receipt is not a transaction
		}
		$rows[] = array(
			'date'    => $c['date'],
			'what'    => 'capital',
			'label'   => __( 'বিনিয়োগ গ্রহণ · Investment received', 'bhela-booking' ),
			'ref'     => $c['ref'],
			'credit'  => $c['amount'],
			'debit'   => 0,
			'void'    => false,
			'receipt' => 'issue' === $c['source'] ? '' : bhela_bm_receipt_url( 'capital', $c['id'] ),
		);
	}

	foreach ( bhela_bm_investor_ledger( $investor )['rows'] as $r ) {
		$reversed = (bool) bhela_bm_ledger_reversal_of( $r['id'] );
		// The profit engine puts its period KEY on `ref` so posting can be idempotent,
		// and the same period is already spelled out readably in the note. Printing
		// both put "2024-07-01 থেকে 2024-07-31" beside
		// "BHELA-IN-2026-0003:2024-07-01:2024-07-31" on every single row. The key is an
		// internal handle, so the statement shows the investment code from it and drops
		// the rest. Only rendering the page showed this (§13.29).
		$ref = (string) $r['ref'];
		if ( false !== strpos( $ref, ':' ) ) {
			$ref = strtok( $ref, ':' );
		}
		$row      = array(
			'date'    => $r['date'],
			'what'    => $r['type'],
			'label'   => $r['label'] . ( $r['note'] ? ' — ' . $r['note'] : '' ),
			'ref'     => $ref,
			'credit'  => 0,
			'debit'   => 0,
			'void'    => $reversed,
			'receipt' => '',
		);
		if ( 'profit' === $r['type'] ) {
			$row['credit'] = $r['amount'];
		} elseif ( in_array( $r['type'], array( 'payment', 'advance' ), true ) ) {
			$row['debit']   = $r['amount'];
			$row['receipt'] = bhela_bm_receipt_url( 'payment', $r['id'] );
		} else {
			// An adjustment carries its own sign, so it lands on whichever side it
			// belongs — including a contra row, which is how a reversal shows up.
			if ( $r['signed'] >= 0 ) {
				$row['credit'] = abs( $r['signed'] );
			} else {
				$row['debit'] = abs( $r['signed'] );
			}
		}
		$rows[] = $row;
	}

	usort( $rows, function ( $a, $b ) {
		return ( $a['date'] <=> $b['date'] ) ?: ( $a['what'] <=> $b['what'] );
	} );

	// The balance is replayed across EVERY row, then the window is applied for display —
	// a statement that starts at zero in the middle of a year is not a statement.
	$balance = 0;
	$opening = 0;
	foreach ( $rows as $row ) {
		$effect = $row['void'] ? 0 : ( $row['credit'] - $row['debit'] );
		$balance += $effect;

		if ( $from && $row['date'] < $from ) {
			$opening = $balance;
			continue;
		}
		if ( $to && $row['date'] > $to ) {
			continue;
		}
		$row['balance'] = $balance;
		$out['rows'][]  = $row;

		if ( ! $row['void'] ) {
			if ( 'capital' === $row['what'] ) {
				$out['capital'] += $row['credit'];
			} elseif ( 'profit' === $row['what'] ) {
				$out['profit'] += $row['credit'];
			} elseif ( in_array( $row['what'], array( 'payment', 'advance' ), true ) ) {
				$out['paid'] += $row['debit'];
			} else {
				$out['adjustment'] += $row['credit'] - $row['debit'];
			}
		}
	}

	$out['opening'] = $opening;
	$out['balance'] = $balance;
	return $out;
}

function bhela_bm_statement_key( $investor ) {
	return wp_hash( 'bhela-account-statement-' . (int) $investor );
}

function bhela_bm_statement_url( $investor, $from = '', $to = '' ) {
	$args = array(
		'bhela_statement' => (int) $investor,
		'key'             => bhela_bm_statement_key( $investor ),
	);
	if ( $from ) {
		$args['from'] = $from;
	}
	if ( $to ) {
		$args['to'] = $to;
	}
	return add_query_arg( $args, home_url( '/' ) );
}

/* =========================================================
 * RENDERING
 * ========================================================= */

/**
 * The one access rule both documents share.
 *
 * Identical in shape to the certificate's, and deliberately a separate small function
 * rather than three copies: an access check that is written out three times is one that
 * eventually differs in one of them.
 */
function bhela_bm_document_allowed( $investor, $key_ok ) {
	if ( $key_ok ) {
		return true;
	}
	if ( current_user_can( 'bhela_investors_view' ) ) {
		return true;
	}
	return function_exists( 'bhela_bm_current_investor' )
		&& $investor > 0
		&& (int) bhela_bm_current_investor() === (int) $investor;
}

/** Print one receipt. */
function bhela_bm_maybe_render_receipt() {
	if ( empty( $_GET['bhela_receipt'] ) ) {
		return;
	}
	$kind = sanitize_key( wp_unslash( $_GET['bhela_receipt'] ) );
	$row  = isset( $_GET['row'] ) ? (int) $_GET['row'] : 0;

	$receipt = bhela_bm_receipt_data( $kind, $row );
	if ( ! $receipt ) {
		wp_die( esc_html__( 'Receipt not found.', 'bhela-booking' ), 404 );
	}

	$key_ok = isset( $_GET['key'] ) && hash_equals( bhela_bm_receipt_key( $kind, $row ), (string) $_GET['key'] );
	if ( ! bhela_bm_document_allowed( $receipt['investor'], $key_ok ) ) {
		wp_die( esc_html__( 'You are not allowed to view this receipt.', 'bhela-booking' ), 403 );
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );

	$settings = bhela_bm_get_settings();
	include BHELA_BM_PATH . 'templates/receipt-payment.php';
	exit;
}
add_action( 'template_redirect', 'bhela_bm_maybe_render_receipt' );

/** Print one investor's account statement. */
function bhela_bm_maybe_render_account_statement() {
	if ( empty( $_GET['bhela_statement'] ) ) {
		return;
	}
	$investor = (int) $_GET['bhela_statement'];
	if ( 'bhela_investor' !== get_post_type( $investor ) ) {
		wp_die( esc_html__( 'Statement not found.', 'bhela-booking' ), 404 );
	}

	$key_ok = isset( $_GET['key'] ) && hash_equals( bhela_bm_statement_key( $investor ), (string) $_GET['key'] );
	if ( ! bhela_bm_document_allowed( $investor, $key_ok ) ) {
		wp_die( esc_html__( 'You are not allowed to view this statement.', 'bhela-booking' ), 403 );
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );

	$statement = bhela_bm_account_statement(
		$investor,
		isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
		isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''
	);
	$settings = bhela_bm_get_settings();
	include BHELA_BM_PATH . 'templates/statement-account.php';
	exit;
}
add_action( 'template_redirect', 'bhela_bm_maybe_render_account_statement' );
