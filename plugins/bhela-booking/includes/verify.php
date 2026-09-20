<?php
/**
 * The public verification page — proof a document is genuine, and nothing more.
 *
 * Anyone holding a BHELA certificate can scan its QR or type its number and be told
 * whether the office issued it. That is the entire job. The page therefore shows the
 * certificate number, the document type, the issue date, the status, and the investor's
 * name **masked** — and it shows no amount, no rate, no term, no mobile number and no
 * NID.
 *
 * That restraint is the design, not caution for its own sake: the URL is meant to be
 * printed on a document and scanned by strangers, so whatever it reveals is public. A
 * verification page that echoed the figures would turn every certificate number into a
 * way of reading somebody's finances, and the numbers are sequential.
 *
 * Two behaviours worth stating:
 *
 * - An unknown number gets the same page as a known one, minus the details — it says
 *   "no certificate with this number", which is a fact about BHELA's own records rather
 *   than about a person, and is the answer a verifier actually needs.
 * - A **superseded** certificate is reported as superseded rather than as valid or as
 *   missing. Somebody may be holding the replaced paper, and "this was replaced by
 *   BHELA-PC-2027-0001-V2" is the one thing they most need to learn.
 *
 * The route is `/verify/{number}`, with `?bhela_verify={number}` working as well. The
 * query-string form is not a fallback nobody uses: rewrite rules need flushing, and a
 * verification link that 404s because permalinks were regenerated is a broken promise
 * printed on paper that cannot be recalled.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bumped when the rule changes, so the flush below runs once and only once. */
define( 'BHELA_BM_VERIFY_RW_VERSION', 1 );

function bhela_bm_verify_rewrite() {
	add_rewrite_rule( '^verify/([^/]+)/?$', 'index.php?bhela_verify=$matches[1]', 'top' );
}
add_action( 'init', 'bhela_bm_verify_rewrite' );

function bhela_bm_verify_query_var( $vars ) {
	$vars[] = 'bhela_verify';
	return $vars;
}
add_filter( 'query_vars', 'bhela_bm_verify_query_var' );

/** Flush once when the rule is new, rather than on every request. */
function bhela_bm_verify_maybe_flush() {
	if ( (int) get_option( 'bhela_bm_verify_rw', 0 ) === BHELA_BM_VERIFY_RW_VERSION ) {
		return;
	}
	bhela_bm_verify_rewrite();
	flush_rewrite_rules( false );
	update_option( 'bhela_bm_verify_rw', BHELA_BM_VERIFY_RW_VERSION );
}
add_action( 'admin_init', 'bhela_bm_verify_maybe_flush', 6 );

/** The public URL printed on a certificate and encoded into its QR. */
function bhela_bm_verify_url( $number ) {
	$number = rawurlencode( (string) $number );
	// home_url() rather than site_url(): the verification page is a front-end page and
	// may be reached on a domain that differs from where WordPress itself lives.
	return home_url( '/verify/' . $number );
}

/**
 * What the page is allowed to say about a certificate.
 *
 * Split out from the rendering so the harness can assert on the DATA rather than by
 * grepping HTML — an assertion that a field is absent from a page is exactly the kind
 * that passes for the wrong reason when the markup changes.
 *
 * @return array{found:bool,status:string,number:string,type:string,issued:string,name:string,replaced_by:string}
 */
function bhela_bm_verify_lookup( $number ) {
	$number = trim( (string) $number );
	$out    = array(
		'found'       => false,
		'status'      => 'unknown',
		'number'      => $number,
		'type'        => '',
		'issued'      => '',
		'name'        => '',
		'replaced_by' => '',
	);
	if ( '' === $number ) {
		return $out;
	}

	$cert = bhela_bm_cert_by_number( $number );
	if ( ! $cert ) {
		return $out;
	}

	$types         = bhela_bm_cert_types();
	$out['found']  = true;
	$out['status'] = $cert['superseded'] ? 'superseded' : 'valid';
	$out['number'] = $cert['number'];
	$out['type']   = $types[ $cert['type'] ]['en'] ?? $cert['type'];
	$out['issued'] = $cert['issued'];
	// The masked name, and the ONLY thing about the investor that appears here.
	$out['name']        = bhela_bm_mask_person( $cert['snapshot']['name'] ?? '' );
	$out['replaced_by'] = (string) $cert['superseded_number'];
	return $out;
}

/** Render the verification page when the route is hit. */
function bhela_bm_maybe_render_verify() {
	$number = get_query_var( 'bhela_verify' );
	if ( '' === $number || null === $number ) {
		$number = isset( $_GET['bhela_verify'] ) ? sanitize_text_field( wp_unslash( $_GET['bhela_verify'] ) ) : '';
	}
	if ( '' === $number ) {
		return;
	}

	$v        = bhela_bm_verify_lookup( $number );
	$settings = bhela_bm_get_settings();

	// A verification result must never be served from a cache: a superseded certificate
	// that still reads "valid" because a page cache kept yesterday's answer is the one
	// failure this page cannot have.
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	status_header( $v['found'] ? 200 : 404 );

	include BHELA_BM_PATH . 'templates/verify.php';
	exit;
}
add_action( 'template_redirect', 'bhela_bm_maybe_render_verify' );
