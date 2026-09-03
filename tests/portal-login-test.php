<?php
/**
 * Passwordless portal sign-in and investor self-registration.
 *
 * Four things here are security properties rather than features, and each is asserted
 * behaviourally — never by grepping the source, because a comment explaining a rule
 * matches a grep for the rule (§13.43 learned that the hard way):
 *
 *   1. A number on the register and a number that is not produce the SAME page.
 *   2. A code can never sign in an account that holds more than the portal's own role.
 *   3. The email a login code falls back to comes from the record, not the request.
 *   4. Registration creates an APPLICATION. Only approval creates a login, and an
 *      approval that has to invent an investor record gives it zero shares.
 *   5. Nothing can be uploaded until a number has been proved, and the form asks for
 *      exactly the fields the admin screen asks for -- no more, and nothing less.
 *
 * SMS is deliberately off for the whole run (§13.32: a harness states the configuration
 * it asserts against) so every code travels by the email fallback, where `pre_wp_mail`
 * can capture it. Testing the SMS path would mean testing the gateway.
 */

require __DIR__ . '/bootstrap.php';
bhela_test_modules( 'ui', 'roles' );

wp_set_current_user( 0 );
wp_set_current_user( 1 );

/* ---------- fixtures ---------- */

$pl_settings_was = get_option( 'bhela_bm_settings', array() );
$pl_s            = bhela_bm_get_settings();
$pl_s['sms_enabled'] = 0;
$pl_s['otp_brand']   = 'BHELA';
update_option( 'bhela_bm_settings', $pl_s );

/** Every outgoing mail, captured. Returning true also makes wp_mail() report success. */
$GLOBALS['pl_mail'] = array();
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	$GLOBALS['pl_mail'][] = $atts;
	return true;
}, 10, 2 );

/** The six-digit code out of the last mail, or ''. */
function pl_last_code() {
	$mail = end( $GLOBALS['pl_mail'] );
	if ( ! $mail || empty( $mail['message'] ) ) {
		return '';
	}
	return preg_match( '/(\d{6})/', $mail['message'], $m ) ? $m[1] : '';
}

/** Who the last mail went to. */
function pl_last_to() {
	$mail = end( $GLOBALS['pl_mail'] );
	return $mail ? (string) ( is_array( $mail['to'] ) ? reset( $mail['to'] ) : $mail['to'] ) : '';
}

/**
 * Clear the three send ceilings.
 *
 * Not a convenience: without it the second assertion in any section hits the 60-second
 * per-number cooldown, and three consecutive suite runs inside an hour would exhaust
 * the per-address allowance and fail for a reason that has nothing to do with the code
 * under test.
 */
function pl_reset_limits( $phone = '' ) {
	delete_transient( 'bhela_bm_chalip_' . md5( bhela_bm_client_ip() ) );
	delete_transient( 'bhela_bm_inv_login_' . md5( bhela_bm_client_ip() ) );
	if ( $phone ) {
		delete_transient( 'bhela_bm_chalcool_' . md5( $phone ) );
		delete_transient( 'bhela_bm_chalday_' . md5( $phone ) );
	}
}

/** Switch user without inheriting the cached capabilities of the last one (§13.15). */
function pl_as_user( $uid ) {
	wp_set_current_user( 0 );
	clean_user_cache( $uid );
	wp_set_current_user( $uid );
	return $uid;
}

/** An investor record with a deliberately UNNORMALISED mobile, as the office types it. */
function pl_investor( $name, $mobile, $email = '' ) {
	$id = wp_insert_post( array(
		'post_type'   => 'bhela_investor',
		'post_status' => 'publish',
		'post_title'  => $name,
	) );
	update_post_meta( $id, '_bhela_inv_mobile', $mobile );
	update_post_meta( $id, '_bhela_inv_shares', 0 );
	if ( $email ) {
		update_post_meta( $id, '_bhela_inv_email', $email );
	}
	bhela_bm_investor_index_mobile( $id );
	return (int) $id;
}

$pl_phone_a = '01711000011';
$pl_phone_b = '01711000022';
$pl_phone_x = '01711000099';   // on no record at all

// Written the way a person writes it. The lookup has to find it from bare digits.
$pl_inv_a = pl_investor( 'ZZ Portal Investor A', '+880 1711-000011', 'zz-portal-a@example.com' );
$pl_inv_b = pl_investor( 'ZZ Portal Investor B', '01711000022' );

$pl_user_a = wp_insert_user( array(
	'user_login' => 'zz_portal_a',
	'user_email' => 'zz-portal-a@example.com',
	'user_pass'  => wp_generate_password( 24, true, true ),
	'role'       => 'bhela_investor',
) );
update_post_meta( $pl_inv_a, '_bhela_inv_user', (int) $pl_user_a );

// An investor record linked to an ADMINISTRATOR — the trap rule 4 exists to close.
$pl_user_admin = wp_insert_user( array(
	'user_login' => 'zz_portal_owner',
	'user_email' => 'zz-portal-owner@example.com',
	'user_pass'  => wp_generate_password( 24, true, true ),
	'role'       => 'administrator',
) );
update_post_meta( $pl_inv_b, '_bhela_inv_user', (int) $pl_user_admin );

$pl_approver = wp_insert_user( array(
	'user_login' => 'zz_sgn_approver',
	'user_email' => 'zz-sgn-approver@example.com',
	'user_pass'  => wp_generate_password( 24, true, true ),
	'role'       => 'bhela_manager',
) );
$pl_nobody = wp_insert_user( array(
	'user_login' => 'zz_sgn_nobody',
	'user_email' => 'zz-sgn-nobody@example.com',
	'user_pass'  => wp_generate_password( 24, true, true ),
	'role'       => 'bhela_booking_staff',
) );

echo "\n== 1. The number a visitor types finds the record the office typed ==\n";

ok( $pl_inv_a === bhela_bm_investor_by_mobile( $pl_phone_a ),
	'"+880 1711-000011" on the record is found by "01711000011"' );
ok( $pl_inv_a === bhela_bm_investor_by_mobile( '+8801711000011' ),
	'and by the international form' );
ok( 0 === bhela_bm_investor_by_mobile( $pl_phone_x ), 'a number on no record resolves to nothing' );
ok( 0 === bhela_bm_investor_by_mobile( 'not a phone' ), 'and so does a non-number' );

// Two records on one number decides whose money somebody sees. It refuses instead.
$pl_dupe = pl_investor( 'ZZ Portal Duplicate', $pl_phone_a );
ok( 0 === bhela_bm_investor_by_mobile( $pl_phone_a ),
	'two records carrying one number refuse to resolve rather than picking one' );
bhela_test_delete( $pl_dupe );

echo "\n== 2. A code may only ever sign in a pure portal account ==\n";

ok( bhela_bm_otp_login_allowed( get_userdata( $pl_user_a ) ),
	'an account holding only bhela_investor may sign in with a code' );
ok( ! bhela_bm_otp_login_allowed( get_userdata( $pl_user_admin ) ),
	'an ADMINISTRATOR linked to an investor record may not — one phone number would be wp-admin' );
ok( ! bhela_bm_otp_login_allowed( get_userdata( $pl_approver ) ),
	'nor a manager' );
ok( ! bhela_bm_otp_login_allowed( null ), 'and a missing user is not an account' );

// A capability granted to ONE user is invisible to a role check, which is why the
// guard asks about capabilities as well as roles.
$pl_u = new WP_User( $pl_user_a );
$pl_u->add_cap( 'bhela_investors_view' );
clean_user_cache( $pl_user_a );
ok( ! bhela_bm_otp_login_allowed( get_userdata( $pl_user_a ) ),
	'a per-user capability grant disqualifies the account even though its role is unchanged' );
$pl_u = new WP_User( $pl_user_a );
$pl_u->remove_cap( 'bhela_investors_view' );
clean_user_cache( $pl_user_a );
ok( bhela_bm_otp_login_allowed( get_userdata( $pl_user_a ) ), 'and removing it restores access' );

echo "\n== 3. The challenge itself ==\n";

pl_reset_limits( $pl_phone_a );
$pl_chal = bhela_bm_chal_start( 'login', $pl_phone_a, array( 'user' => $pl_user_a ), 'zz-portal-a@example.com' );
ok( ! is_wp_error( $pl_chal ), 'a challenge starts', is_wp_error( $pl_chal ) ? $pl_chal->get_error_code() : 'ok' );
ok( 'email' === $pl_chal['channel'], 'SMS is off, so it fell back to email', $pl_chal['channel'] );
ok( 32 === strlen( $pl_chal['id'] ), 'the browser gets a 32-character opaque id' );

$pl_code = pl_last_code();
ok( 6 === strlen( $pl_code ), 'the code is six digits', $pl_code );

// The code is never stored in the clear: a transient is readable by anything with
// database access, and six digits is nothing to brute-force from a plain value.
$pl_stored = get_transient( bhela_bm_chal_key( 'login', $pl_chal['id'] ) );
ok( is_array( $pl_stored ) && empty( $pl_stored['payload']['code'] ), 'the payload does not carry the code' );
ok( is_array( $pl_stored ) && false === strpos( wp_json_encode( $pl_stored ), $pl_code ),
	'and the code appears nowhere in the stored challenge — only its keyed hash' );

$pl_bad = bhela_bm_chal_verify( 'login', $pl_chal['id'], '000000' );
ok( is_wp_error( $pl_bad ) && 'wrong' === $pl_bad->get_error_code(), 'a wrong code is refused' );

// The purpose is part of the transient key, so a signup code cannot answer a login.
$pl_cross = bhela_bm_chal_verify( 'signup', $pl_chal['id'], $pl_code );
ok( is_wp_error( $pl_cross ) && 'expired' === $pl_cross->get_error_code(),
	'the same id under another purpose does not exist' );

$pl_ok = bhela_bm_chal_verify( 'login', $pl_chal['id'], $pl_code );
ok( ! is_wp_error( $pl_ok ), 'the right code verifies' );
ok( (int) $pl_user_a === (int) $pl_ok['payload']['user'], 'and hands back the payload it was opened with' );

$pl_again = bhela_bm_chal_verify( 'login', $pl_chal['id'], $pl_code );
ok( is_wp_error( $pl_again ) && 'expired' === $pl_again->get_error_code(),
	'a used code cannot be used twice' );

// Five wrong guesses kill the code — on the fifth, not the sixth.
pl_reset_limits( $pl_phone_a );
$pl_chal2 = bhela_bm_chal_start( 'login', $pl_phone_a, array(), 'zz-portal-a@example.com' );
$pl_burn  = '';
for ( $i = 1; $i <= BHELA_BM_CHAL_MAX_TRIES; $i++ ) {
	$pl_burn = bhela_bm_chal_verify( 'login', $pl_chal2['id'], '000000' );
}
ok( is_wp_error( $pl_burn ) && 'expired' === $pl_burn->get_error_code(),
	'the code dies ON the attempt that exhausts the allowance, not the one after' );
ok( false === get_transient( bhela_bm_chal_key( 'login', $pl_chal2['id'] ) ), 'and the challenge is gone' );

echo "\n== 4. A known number and an unknown one render the same page ==\n";

/** Drive step one as the form does, and return the page it produces. */
function pl_step_phone( $phone ) {
	pl_reset_limits( $phone );
	bhela_bm_portal_state( array() );
	$_POST = array( 'bhela_inv_phone' => $phone );
	bhela_bm_login_step_phone( 'bhela_bm_inv_login_' . md5( bhela_bm_client_ip() ), 0 );
	$state = bhela_bm_portal_state();
	$html  = bhela_bm_portal_login_form();
	$_POST = array();
	return array( $state, $html );
}

list( $pl_known_state, $pl_known_html )     = pl_step_phone( $pl_phone_a );
list( $pl_unknown_state, $pl_unknown_html ) = pl_step_phone( $pl_phone_x );

ok( 'code' === $pl_known_state['step'] && 'code' === $pl_unknown_state['step'],
	'both numbers advance to the code step' );
ok( 32 === strlen( $pl_known_state['chal'] ) && 32 === strlen( $pl_unknown_state['chal'] ),
	'both carry a real 32-character challenge id — the unknown one is a decoy' );
ok( $pl_known_state['chal'] !== $pl_unknown_state['chal'], 'and they are different ids' );

/** Blank out the two values that MUST differ between any two requests. */
function pl_normalise( $html, $chal, $phone ) {
	$html = str_replace( $chal, 'CHALLENGE', $html );
	$html = preg_replace( '/name="bhela_inv_nonce" value="[a-f0-9]+"/', 'NONCE', $html );
	$html = preg_replace( '/name="_wp_http_referer" value="[^"]*"/', 'REFERER', $html );
	// The mask is built from what was typed, so two different numbers mask differently.
	// Everything else about the page has to match.
	return str_replace( bhela_bm_chal_mask( $phone ), 'MASK', $html );
}

$pl_n1 = pl_normalise( $pl_known_html, $pl_known_state['chal'], $pl_phone_a );
$pl_n2 = pl_normalise( $pl_unknown_html, $pl_unknown_state['chal'], $pl_phone_x );
ok( $pl_n1 === $pl_n2,
	'the two pages are byte-identical once the nonce and the opaque id are normalised',
	$pl_n1 === $pl_n2 ? '' : 'lengths ' . strlen( $pl_n1 ) . ' vs ' . strlen( $pl_n2 ) );

// The decoy sent nothing. That is the whole point: a stranger's handset is not ours
// to text just because somebody typed their number into our form.
$pl_decoy_store = get_transient( bhela_bm_chal_key( 'login', $pl_unknown_state['chal'] ) );
ok( is_array( $pl_decoy_store ) && 'none' === $pl_decoy_store['channel'],
	'nothing was delivered for the unknown number' );
ok( is_array( $pl_decoy_store ) && array() === $pl_decoy_store['payload'],
	'and the decoy carries no user to sign in' );

// A decoy's "code" is non-numeric, so no digits a person can type will ever match it.
$pl_guess = bhela_bm_chal_verify( 'login', $pl_unknown_state['chal'], '000000' );
ok( is_wp_error( $pl_guess ) && 'wrong' === $pl_guess->get_error_code(),
	'a decoy answers with the ordinary wrong-code message' );

echo "\n== 5. A staff-linked record is treated exactly like an unknown number ==\n";

list( $pl_staff_state, ) = pl_step_phone( $pl_phone_b );
$pl_staff_store = get_transient( bhela_bm_chal_key( 'login', $pl_staff_state['chal'] ) );
ok( is_array( $pl_staff_store ) && 'none' === $pl_staff_store['channel'],
	'the administrator-linked record gets no code at all' );
ok( is_array( $pl_staff_store ) && array() === $pl_staff_store['payload'],
	'and no payload — there is nothing for a code to sign in to' );

echo "\n== 6. The email fallback address comes from the record, never the request ==\n";

pl_reset_limits( $pl_phone_a );
bhela_bm_portal_state( array() );
$_POST = array(
	'bhela_inv_phone' => $pl_phone_a,
	// An attacker who knows an investor's number, trying to redirect the code.
	'email'           => 'zz-attacker@example.com',
	'bhela_inv_email' => 'zz-attacker@example.com',
	'reg_email'       => 'zz-attacker@example.com',
);
bhela_bm_login_step_phone( 'bhela_bm_inv_login_' . md5( bhela_bm_client_ip() ), 0 );
$_POST = array();
ok( 'zz-portal-a@example.com' === pl_last_to(),
	'the code went to the address on the record', pl_last_to() );
ok( false === strpos( pl_last_to(), 'attacker' ), 'and not to one supplied in the request' );

echo "\n== 7. Registration files an application, and nothing else ==\n";

$pl_reg_phone = '01711000033';

$pl_bad_reg = bhela_bm_signup_add( array( 'name' => 'ZZ No Number', 'mobile' => 'nonsense' ) );
ok( is_wp_error( $pl_bad_reg ), 'an application with no usable number is refused' );
$pl_bad_reg = bhela_bm_signup_add( array( 'name' => '', 'mobile' => $pl_reg_phone ) );
ok( is_wp_error( $pl_bad_reg ), 'and one with no name' );

$pl_app = bhela_bm_signup_add( array(
	'name'    => 'ZZ Applicant',
	'mobile'  => $pl_reg_phone,
	'email'   => 'zz-applicant@example.com',
	'channel' => 'sms',
) );
ok( ! is_wp_error( $pl_app ), 'an application is recorded' );
$pl_row = bhela_bm_signup( $pl_app );
ok( 'pending' === $pl_row['state'], 'as pending' );
ok( $pl_reg_phone === $pl_row['mobile'], 'with the normalised number' );
ok( 0 === bhela_bm_investor_by_mobile( $pl_reg_phone ),
	'and NO investor record — an application is not an account' );
ok( ! get_user_by( 'login', 'bhela-' . $pl_reg_phone ), 'and no WordPress user' );

$pl_app2 = bhela_bm_signup_add( array( 'name' => 'ZZ Applicant Again', 'mobile' => $pl_reg_phone ) );
ok( (int) $pl_app === (int) $pl_app2, 'a second submission updates the first rather than queueing a duplicate' );

echo "\n== 8. Only approval creates a login ==\n";

pl_as_user( $pl_nobody );
$pl_denied = bhela_bm_signup_approve( $pl_app, false );
ok( is_wp_error( $pl_denied ) && 'denied' === $pl_denied->get_error_code(),
	'somebody without bhela_investor_signup cannot approve' );
ok( 'pending' === bhela_bm_signup( $pl_app )['state'], 'and the application is untouched' );

pl_as_user( $pl_approver );
ok( current_user_can( 'bhela_investor_signup' ), 'a manager holds the capability' );
$pl_res = bhela_bm_signup_approve( $pl_app, false );
ok( ! is_wp_error( $pl_res ), 'and can approve', is_wp_error( $pl_res ) ? $pl_res->get_error_message() : 'ok' );
ok( ! empty( $pl_res['created'] ), 'a record was created, because the number matched none' );

$pl_new_inv = (int) $pl_res['investor'];
ok( 0 === bhela_bm_investor_shares( $pl_new_inv ),
	'the new investor holds ZERO shares — money is never self-declared',
	(string) bhela_bm_investor_shares( $pl_new_inv ) );
ok( 0 === (int) get_post_meta( $pl_new_inv, '_bhela_inv_amount', true ), 'and zero invested' );
ok( $pl_new_inv === bhela_bm_investor_by_mobile( $pl_reg_phone ), 'the record is reachable by its number' );

$pl_new_user = get_userdata( (int) $pl_res['user'] );
ok( $pl_new_user && array( 'bhela_investor' ) === array_values( (array) $pl_new_user->roles ),
	'the login holds the investor role and nothing else',
	$pl_new_user ? implode( ',', (array) $pl_new_user->roles ) : 'none' );
ok( ! user_can( $pl_new_user, 'edit_posts' ) && ! user_can( $pl_new_user, 'bhela_investors_view' ),
	'and no capability that reaches wp-admin or another investor' );
ok( bhela_bm_otp_login_allowed( $pl_new_user ), 'so a code may sign it in — the flow closes end to end' );
ok( (int) $pl_res['user'] === bhela_bm_investor_user( $pl_new_inv ), 'record and login are linked' );
ok( 'approved' === bhela_bm_signup( $pl_app )['state'], 'and the application is settled' );

$pl_twice = bhela_bm_signup_approve( $pl_app, false );
ok( is_wp_error( $pl_twice ) && 'state' === $pl_twice->get_error_code(), 'a settled application cannot be approved again' );

echo "\n== 9. An application matching an existing record links it, never duplicates it ==\n";

$pl_known_phone = '01711000044';
$pl_inv_c       = pl_investor( 'ZZ Existing Investor', $pl_known_phone );
update_post_meta( $pl_inv_c, '_bhela_inv_shares', 10 );

wp_set_current_user( 1 );
$pl_app3 = bhela_bm_signup_add( array( 'name' => 'ZZ Existing Investor', 'mobile' => $pl_known_phone, 'channel' => 'sms' ) );
pl_as_user( $pl_approver );
$pl_res3 = bhela_bm_signup_approve( $pl_app3, false );
ok( ! is_wp_error( $pl_res3 ), 'it approves', is_wp_error( $pl_res3 ) ? $pl_res3->get_error_message() : 'ok' );
ok( empty( $pl_res3['created'] ), 'without creating a second record' );
ok( $pl_inv_c === (int) $pl_res3['investor'], 'the existing record is the one linked' );
ok( 10 === bhela_bm_investor_shares( $pl_inv_c ), 'and its shareholding is untouched by the registration' );

echo "\n== 10. Rejection, and clearing up afterwards ==\n";

wp_set_current_user( 1 );
$pl_app4 = bhela_bm_signup_add( array( 'name' => 'ZZ Rejected', 'mobile' => '01711000055', 'channel' => 'email' ) );

pl_as_user( $pl_nobody );
ok( is_wp_error( bhela_bm_signup_reject( $pl_app4, 'ZZ no' ) ), 'rejection needs the capability too' );

pl_as_user( $pl_approver );
$pl_del_pending = bhela_bm_signup_delete( $pl_app4 );
ok( is_wp_error( $pl_del_pending ) && 'state' === $pl_del_pending->get_error_code(),
	'a PENDING application cannot be deleted — decide it first, or the decision leaves no trace' );

ok( true === bhela_bm_signup_reject( $pl_app4, 'ZZ unknown to us' ), 'it rejects' );
$pl_row4 = bhela_bm_signup( $pl_app4 );
ok( 'rejected' === $pl_row4['state'] && 'ZZ unknown to us' === $pl_row4['reason'], 'with the reason recorded' );
ok( 0 === bhela_bm_investor_by_mobile( '01711000055' ), 'and no record was created' );
ok( ! get_user_by( 'login', 'bhela-01711000055' ), 'and no login' );

ok( true === bhela_bm_signup_delete( $pl_app4 ), 'a settled application can be deleted' );
ok( null === bhela_bm_signup( $pl_app4 ), 'and it is gone' );

echo "\n== 11. The form itself ==\n";

wp_set_current_user( 0 );
bhela_bm_portal_state( array() );
$pl_form = bhela_bm_portal_login_form();
ok( false === strpos( $pl_form, 'type="password"' ), 'the sign-in form has no password field at all' );
ok( false !== strpos( $pl_form, 'bhela_inv_phone' ), 'it asks for a mobile number' );
ok( false !== strpos( $pl_form, 'bhela_inv_nonce' ), 'and carries a nonce' );
ok( false !== strpos( $pl_form, 'bhela_bm_hp' ), 'with a honeypot, like every other public form here' );

bhela_bm_signup_state( array() );
$pl_reg_form = bhela_bm_signup_form();
ok( false === strpos( $pl_reg_form, 'type="password"' ), 'nor does the registration form' );
ok( false === stripos( $pl_reg_form, 'reg_shares' ) && false === stripos( $pl_reg_form, 'reg_amount' ),
	'and it never asks how many shares somebody thinks they hold' );

// The first screen asks for three things. The NID, the bank account and the scans come
// after the code, so an unverified visitor cannot post any of them.
ok( false !== strpos( $pl_reg_form, 'reg_mobile' ) && false !== strpos( $pl_reg_form, 'reg_name' ),
	'the first screen asks for a name and a number' );
ok( false === strpos( $pl_reg_form, 'reg_nid' ) && false === strpos( $pl_reg_form, 'reg_bank_account' ),
	'and for nothing else — identity and bank fields are not on it' );
ok( false === strpos( $pl_reg_form, 'type="file"' ), 'with no file input anywhere on it' );
ok( false === strpos( $pl_reg_form, 'multipart/form-data' ), 'and the form is not even multipart' );

// The registration page has to exist for the sign-in page's link to go anywhere.
ok( false !== strpos( $pl_form, esc_url( bhela_bm_signup_url() ) ), 'the sign-in page links to registration' );

wp_set_current_user( 1 );

echo "\n== 12. the form asks for what the admin screen asks for ==\n";

// One registry, two forms. A second list of thirty fields is a list that drifts, and
// the failure is silent: the office simply never gets asked for the father's name.
$pl_admin_keys = array();
foreach ( bhela_bm_investor_fields() as $pl_g ) {
	foreach ( $pl_g['fields'] as $pl_k => $pl_d ) {
		$pl_admin_keys[] = $pl_k;
	}
}
$pl_form_keys = array_keys( bhela_bm_signup_keys() );
$pl_missing   = array_diff( $pl_admin_keys, $pl_form_keys, bhela_bm_signup_skip_fields() );
ok( array() === $pl_missing, 'every admin field is on the registration form',
	$pl_missing ? implode( ',', $pl_missing ) : 'none missing' );
ok( in_array( 'code', bhela_bm_signup_skip_fields(), true ) && ! in_array( 'code', $pl_form_keys, true ),
	'except the Investor ID, which the office assigns rather than the applicant claiming' );
ok( in_array( 'name', $pl_form_keys, true ), 'plus the name, which on the record is the post title' );
ok( count( bhela_bm_signup_file_keys() ) >= 3, 'and the three document fields take real uploads',
	implode( ',', bhela_bm_signup_file_keys() ) );

// The sanitiser is the shared one, so a select cannot be posted a value it does not
// have and a date cannot be posted a sentence.
ok( '' === bhela_bm_investor_field_sanitize( array( 'type' => 'select', 'options' => array( 'cash' => 'Cash' ) ), 'wire-me-money' ),
	'a select refuses a value that is not one of its own options' );
ok( 'cash' === bhela_bm_investor_field_sanitize( array( 'type' => 'select', 'options' => array( 'cash' => 'Cash' ) ), 'cash' ),
	'and accepts one that is' );
ok( '' === bhela_bm_investor_field_sanitize( array( 'type' => 'date' ), 'last Tuesday' ), 'a date must be a date' );

echo "\n== 13. the whole three-step flow, and the upload gate ==\n";

$pl_flow_phone = '01711000066';
pl_reset_limits( $pl_flow_phone );
wp_set_current_user( 0 );

/** Post to one step of the registration form, the way a browser does. */
function pl_reg( $fields, $files = array() ) {
	$_POST  = array_merge( array( 'bhela_reg_nonce' => wp_create_nonce( 'bhela_inv_register' ) ), $fields );
	$_FILES = $files;
	bhela_bm_signup_state( array() );
	$to    = '';
	$step  = sanitize_key( $fields['bhela_reg_step'] ?? '' );
	if ( 'start' === $step ) {
		bhela_bm_signup_step_start();
	} elseif ( 'code' === $step ) {
		$to = bhela_bm_signup_step_code();
	} else {
		$to = bhela_bm_signup_step_details();
	}
	$state  = bhela_bm_signup_state();
	$_POST  = array();
	$_FILES = array();
	return array( $state, $to );
}

// Step three with no ticket at all: somebody posting straight at the endpoint.
list( $pl_no_ticket, $pl_no_to ) = pl_reg( array(
	'bhela_reg_step'   => 'details',
	'bhela_reg_ticket' => 'not-a-real-ticket',
	'reg_name'         => 'ZZ Forged',
	'reg_nid'          => '1234567890',
) );
ok( '' === $pl_no_to && 'start' === $pl_no_ticket['step'],
	'step three with no proved number sends the visitor back to the beginning' );
ok( 0 === bhela_bm_signup_pending_for( $pl_flow_phone ), 'and files nothing' );

// Now the real thing.
list( $pl_s1, ) = pl_reg( array(
	'bhela_reg_step' => 'start',
	'reg_name'       => 'ZZ Full Applicant',
	'reg_mobile'     => $pl_flow_phone,
	'reg_email'      => 'zz-full@example.com',
) );
ok( 'code' === $pl_s1['step'], 'step one sends a code' );
ok( 0 === bhela_bm_signup_pending_for( $pl_flow_phone ), 'and still files nothing — a typed number is not an application' );

$pl_s1_code = pl_last_code();
list( $pl_s2, ) = pl_reg( array(
	'bhela_reg_step' => 'code',
	'bhela_reg_chal' => $pl_s1['chal'],
	'bhela_reg_code' => $pl_s1_code,
) );
ok( 'details' === $pl_s2['step'], 'the code opens the details step' );
ok( ! empty( $pl_s2['ticket'] ), 'with a ticket' );
ok( 0 === bhela_bm_signup_pending_for( $pl_flow_phone ), 'and STILL files nothing until the details are submitted' );

// The details form is the one that can carry files.
$pl_detail_html = bhela_bm_signup_form();
ok( false !== strpos( $pl_detail_html, 'multipart/form-data' ), 'the details form is multipart' );
ok( false !== strpos( $pl_detail_html, 'reg_file_agreement' ), 'and carries the document uploads' );
ok( false !== strpos( $pl_detail_html, 'reg_nid' ), 'and the identity fields' );

list( $pl_s3, $pl_s3_to ) = pl_reg( array(
	'bhela_reg_step'   => 'details',
	'bhela_reg_ticket' => $pl_s2['ticket'],
	'reg_name'         => 'ZZ Full Applicant',
	// A crafted post trying to move the application onto somebody else's number.
	'reg_mobile'       => '01711000077',
	'reg_father'       => 'ZZ Father',
	'reg_nid'          => '19900112233',
	'reg_address'      => "ZZ House 1\nZZ Road 2",
	'reg_pay_mode'     => 'bank',
	'reg_bank_account' => '1234509876',
	'reg_nominee_name' => 'ZZ Nominee',
	'reg_declared'     => 'yes',
	'reg_dob'          => '1990-01-12',
	'reg_note'         => 'ZZ please call after 6pm',
) );
ok( '' !== $pl_s3_to && false !== strpos( $pl_s3_to, 'bhela_reg=done' ), 'the details step files the application' );

$pl_flow_app = bhela_bm_signup_pending_for( $pl_flow_phone );
ok( $pl_flow_app > 0, 'which is now pending' );
$pl_flow_row = bhela_bm_signup( $pl_flow_app );
ok( $pl_flow_phone === $pl_flow_row['mobile'],
	'on the PROVED number, not the one re-posted at step three', $pl_flow_row['mobile'] );
ok( 'ZZ Father' === $pl_flow_row['father'] && '19900112233' === $pl_flow_row['nid'],
	'carrying the identity fields' );
ok( 'bank' === $pl_flow_row['pay_mode'] && '1234509876' === $pl_flow_row['bank_account'],
	'the bank fields' );
ok( 'ZZ Nominee' === $pl_flow_row['nominee_name'] && 'yes' === $pl_flow_row['declared'],
	'the nominee and the declaration' );
ok( '1990-01-12' === $pl_flow_row['dob'], 'and a date that survived as a date', $pl_flow_row['dob'] );

// The ticket is spent, so a refresh cannot file a second application.
list( $pl_s4, $pl_s4_to ) = pl_reg( array(
	'bhela_reg_step'   => 'details',
	'bhela_reg_ticket' => $pl_s2['ticket'],
	'reg_name'         => 'ZZ Full Applicant',
) );
ok( '' === $pl_s4_to && 'start' === $pl_s4['step'], 'the ticket is spent — a refresh cannot file it twice' );

echo "\n== 14. approval copies the details, but never over the office's own ==\n";

pl_as_user( $pl_approver );
$pl_flow_res = bhela_bm_signup_approve( $pl_flow_app, false );
ok( ! is_wp_error( $pl_flow_res ), 'it approves', is_wp_error( $pl_flow_res ) ? $pl_flow_res->get_error_message() : 'ok' );
$pl_flow_inv = (int) $pl_flow_res['investor'];
ok( 'ZZ Father' === get_post_meta( $pl_flow_inv, '_bhela_inv_father', true ), 'the identity fields land on the record' );
ok( '1234509876' === get_post_meta( $pl_flow_inv, '_bhela_inv_bank_account', true ), 'and the bank details' );
ok( 0 === bhela_bm_investor_shares( $pl_flow_inv ), 'and the shareholding is still zero' );
ok( '' === (string) get_post_meta( $pl_flow_inv, '_bhela_inv_note', true ),
	'the message to the office is NOT copied onto the record — it is a note, not a field' );

// A record the office has already filled in wins. What a stranger types into a web
// form is at best a second opinion, and losing a verified bank account to one would be
// the most expensive thing that could happen here.
$pl_held_phone = '01711000088';
$pl_held       = pl_investor( 'ZZ Office Typed', $pl_held_phone );
update_post_meta( $pl_held, '_bhela_inv_bank_account', '9999999999' );
update_post_meta( $pl_held, '_bhela_inv_father', '' );
wp_set_current_user( 1 );
$pl_held_app = bhela_bm_signup_add( array(
	'name'         => 'ZZ Office Typed',
	'mobile'       => $pl_held_phone,
	'bank_account' => '1111111111',
	'father'       => 'ZZ New Father',
	'channel'      => 'sms',
) );
pl_as_user( $pl_approver );
$pl_held_res = bhela_bm_signup_approve( $pl_held_app, false );
ok( ! is_wp_error( $pl_held_res ), 'the application approves', is_wp_error( $pl_held_res ) ? $pl_held_res->get_error_message() : 'ok' );
ok( '9999999999' === get_post_meta( $pl_held, '_bhela_inv_bank_account', true ),
	'a bank account the office typed is KEPT, not overwritten by the applicant',
	(string) get_post_meta( $pl_held, '_bhela_inv_bank_account', true ) );
ok( 'ZZ New Father' === get_post_meta( $pl_held, '_bhela_inv_father', true ),
	'while a field that was empty is filled in' );
ok( '1111111111' === bhela_bm_signup( $pl_held_app )['bank_account'],
	'and the value the applicant gave is still on the application, to compare' );

echo "
== 15. a portal login with no shares reads the company's reserves as nothing ==
";

// Registration is what makes this reachable. Every portal login used to be one the
// office had deliberately linked to a real shareholding; now a person can register
// themselves, be approved for access, and sit at zero shares while the office works
// out what they bought — and the reserve balance is not theirs to read meanwhile.
// Caught by loading the portal after registering through it, not by any assertion
// that existed at the time.
pl_as_user( (int) $pl_flow_res['user'] );
ok( $pl_flow_inv === bhela_bm_current_investor(), 'the registered account resolves to its own record' );
$pl_zero = bhela_bm_portal_data();
ok( 0 === bhela_bm_investor_shares( $pl_flow_inv ), 'it holds no shares' );
ok( array() === $pl_zero['funds'], 'so the fund totals are absent, not merely hidden on screen',
	wp_json_encode( $pl_zero['funds'] ) );
ob_start();
bhela_bm_portal_render( $pl_zero );
$pl_zero_html = ob_get_clean();
ok( false === strpos( $pl_zero_html, 'RESERVE' ) && false === stripos( $pl_zero_html, 'reserve fund' ),
	'and the rendered page never names them' );

// Give it a share and the business-level figures come back — this is a gate on the
// holding, not a permanent silence.
update_post_meta( $pl_flow_inv, '_bhela_inv_shares', 1 );
$pl_one = bhela_bm_portal_data();
ok( ! empty( $pl_one['funds'] ), 'one share is enough to see them again' );
update_post_meta( $pl_flow_inv, '_bhela_inv_shares', 0 );

echo "\n== 16. an unverified email is never an identity ==\n";

// This flow proves a phone number. The email is a delivery fallback the applicant can
// type freely at step three, and an earlier version of bhela_bm_signup_make_user()
// called email_exists() and RETURNED that user on a hit — so typing a stranger's
// address linked your new investor record to their WordPress account, and approval
// then handed you a working auth cookie for it. Against a plain subscriber that is a
// complete takeover, because bhela_bm_investor_block_admin() only turns away the
// investor role.
$pl_victim = wp_insert_user( array(
	'user_login' => 'zz_victim_sub',
	'user_email' => 'zz-victim@example.com',
	'user_pass'  => wp_generate_password( 24, true, true ),
	'role'       => 'subscriber',
) );

wp_set_current_user( 1 );
$pl_steal = bhela_bm_signup_add( array(
	'name'    => 'ZZ Impostor',
	'mobile'  => '01711000111',          // the attacker's OWN number
	'email'   => 'zz-victim@example.com', // somebody else's address
	'channel' => 'sms',
) );
ok( ! is_wp_error( $pl_steal ), 'an application naming another account’s email is filed like any other' );

pl_as_user( $pl_approver );
$pl_steal_res = bhela_bm_signup_approve( $pl_steal, false );
ok( ! is_wp_error( $pl_steal_res ), 'and approves',
	is_wp_error( $pl_steal_res ) ? $pl_steal_res->get_error_message() : 'ok' );

$pl_steal_inv = (int) $pl_steal_res['investor'];
$pl_steal_usr = (int) $pl_steal_res['user'];
ok( $pl_steal_usr !== (int) $pl_victim,
	'but the login minted is NOT the account that already held that address',
	$pl_steal_usr . ' vs victim ' . $pl_victim );
ok( (int) $pl_victim !== bhela_bm_investor_user( $pl_steal_inv ),
	'so the victim’s user is not linked to the impostor’s record' );
ok( 'zz-victim@example.com' !== get_userdata( $pl_steal_usr )->user_email,
	'and the new account did not take the address either',
	get_userdata( $pl_steal_usr )->user_email );
ok( ! empty( bhela_bm_signup( $pl_steal )['email_clash'] ),
	'the collision is recorded for the office rather than silently resolved' );

// A code sent to that number now signs in the account the registration made, and
// nobody else. Before the fix this asserted the victim id.
pl_reset_limits( '01711000111' );
bhela_bm_portal_state( array() );
$_POST = array( 'bhela_inv_phone' => '01711000111' );
bhela_bm_login_step_phone( 'bhela_bm_inv_login_' . md5( bhela_bm_client_ip() ), 0 );
$pl_steal_state = bhela_bm_portal_state();
$_POST = array();
$pl_steal_chal = get_transient( bhela_bm_chal_key( 'login', $pl_steal_state['chal'] ) );
ok( is_array( $pl_steal_chal ) && $pl_steal_usr === (int) $pl_steal_chal['payload']['user'],
	'the sign-in challenge carries the registered account, never the victim’s' );
ok( is_array( $pl_steal_chal ) && (int) $pl_victim !== (int) $pl_steal_chal['payload']['user'],
	'— which is the takeover this closes' );

// One login, one record: the invariant every other writer of _bhela_inv_user enforces.
$pl_second = pl_investor( 'ZZ Second Claim', '01711000222' );
update_post_meta( $pl_second, '_bhela_inv_user', $pl_steal_usr );
wp_set_current_user( 1 );
$pl_dup_app = bhela_bm_signup_add( array( 'name' => 'ZZ Dup', 'mobile' => '01711000333', 'channel' => 'sms' ) );
pl_as_user( $pl_approver );
$pl_dup_res = bhela_bm_signup_approve( $pl_dup_app, false );
ok( ! is_wp_error( $pl_dup_res ), 'a fresh registration still approves normally' );
ok( (int) $pl_dup_res['user'] !== $pl_steal_usr, 'onto a login of its own' );
bhela_test_delete( $pl_dup_app );

echo "\n== 17. an emailed code cannot claim a record the office already holds ==\n";

// SMS ships OFF, so on an unconfigured site every code goes to the address the
// applicant typed — which proves an address, not the handset. Fine when the applicant
// is claiming themselves; not fine when the number already matches a real investor,
// because approval would link a login straight onto that shareholding.
$pl_real_phone = '01711000444';
$pl_real       = pl_investor( 'ZZ Real Investor', $pl_real_phone );
update_post_meta( $pl_real, '_bhela_inv_shares', 12 );

wp_set_current_user( 1 );
$pl_claim = bhela_bm_signup_add( array(
	'name'    => 'ZZ Claimant',
	'mobile'  => $pl_real_phone,
	'email'   => 'zz-claimant@example.com',
	'channel' => 'email',          // the fallback fired: an address, not a handset
) );
pl_as_user( $pl_approver );
$pl_claim_res = bhela_bm_signup_approve( $pl_claim, false );
ok( is_wp_error( $pl_claim_res ) && 'unproved_link' === $pl_claim_res->get_error_code(),
	'approval is REFUSED when an email-proved application matches an existing record',
	is_wp_error( $pl_claim_res ) ? $pl_claim_res->get_error_code() : 'approved!' );
ok( 0 === bhela_bm_investor_user( $pl_real ), 'and no login was linked to the real record' );
ok( 'pending' === bhela_bm_signup( $pl_claim )['state'], 'the application is still waiting' );

// The approver can override, but only by saying they checked. That is a person taking
// responsibility, not a default.
$pl_claim_ok = bhela_bm_signup_approve( $pl_claim, false, true );
ok( ! is_wp_error( $pl_claim_ok ), 'a confirmed approval goes through',
	is_wp_error( $pl_claim_ok ) ? $pl_claim_ok->get_error_message() : 'ok' );
ok( $pl_real === (int) $pl_claim_ok['investor'], 'onto the record it matched' );

// An SMS-proved application needs no confirmation — the handset was demonstrated.
$pl_sms_phone = '01711000555';
$pl_sms_inv   = pl_investor( 'ZZ SMS Investor', $pl_sms_phone );
wp_set_current_user( 1 );
$pl_sms_app = bhela_bm_signup_add( array( 'name' => 'ZZ SMS', 'mobile' => $pl_sms_phone, 'channel' => 'sms' ) );
pl_as_user( $pl_approver );
$pl_sms_res = bhela_bm_signup_approve( $pl_sms_app, false );
ok( ! is_wp_error( $pl_sms_res ), 'an SMS-proved match approves without a confirmation',
	is_wp_error( $pl_sms_res ) ? $pl_sms_res->get_error_code() : 'ok' );

// And a brand-new number never needs one: the applicant is only claiming themselves.
wp_set_current_user( 1 );
$pl_new_app = bhela_bm_signup_add( array( 'name' => 'ZZ Fresh', 'mobile' => '01711000666', 'channel' => 'email' ) );
pl_as_user( $pl_approver );
$pl_new_res = bhela_bm_signup_approve( $pl_new_app, false );
ok( ! is_wp_error( $pl_new_res ) && ! empty( $pl_new_res['created'] ),
	'an email-proved application on an UNKNOWN number still approves — nobody is being claimed' );

echo "\n== 18. an address the code was read from is not editable afterwards ==\n";

// When the fallback fired, the address the code was read from is the one thing the
// application proved. Step three must not be able to replace it.
$pl_tk_phone = '01711000777';
pl_reset_limits( $pl_tk_phone );
$pl_ticket = bhela_bm_signup_ticket_add( array(
	'name'    => 'ZZ Ticket',
	'mobile'  => $pl_tk_phone,
	'email'   => 'zz-proved@example.com',
	'channel' => 'email',
) );
list( , $pl_tk_to ) = pl_reg( array(
	'bhela_reg_step'   => 'details',
	'bhela_reg_ticket' => $pl_ticket,
	'reg_name'         => 'ZZ Ticket',
	'reg_email'        => 'zz-swapped@example.com',   // the swap
) );
ok( '' !== $pl_tk_to, 'the application files' );
$pl_tk_app = bhela_bm_signup_pending_for( $pl_tk_phone );
ok( 'zz-proved@example.com' === bhela_bm_signup( $pl_tk_app )['email'],
	'and keeps the address the code was actually read from',
	bhela_bm_signup( $pl_tk_app )['email'] );

// An SMS-proved ticket proves neither address, so a correction is allowed.
$pl_tk2 = bhela_bm_signup_ticket_add( array(
	'name' => 'ZZ Ticket Two', 'mobile' => '01711000888',
	'email' => 'zz-typo@example.com', 'channel' => 'sms',
) );
pl_reg( array(
	'bhela_reg_step'   => 'details',
	'bhela_reg_ticket' => $pl_tk2,
	'reg_name'         => 'ZZ Ticket Two',
	'reg_email'        => 'zz-fixed@example.com',
) );
$pl_tk2_app = bhela_bm_signup_pending_for( '01711000888' );
ok( 'zz-fixed@example.com' === bhela_bm_signup( $pl_tk2_app )['email'],
	'while an SMS-proved one may still be corrected',
	bhela_bm_signup( $pl_tk2_app )['email'] );

wp_set_current_user( 1 );

/* ---------- cleanup ---------- */

foreach ( array( $pl_app, $pl_app3, $pl_flow_app, $pl_held_app, $pl_steal, $pl_claim,
	$pl_sms_app, $pl_new_app, $pl_tk_app, $pl_tk2_app ) as $pl_z ) {
	if ( $pl_z && ! is_wp_error( $pl_z ) ) {
		bhela_test_delete( $pl_z );
	}
}
foreach ( array( $pl_inv_a, $pl_inv_b, $pl_inv_c, $pl_new_inv, $pl_flow_inv, $pl_held,
	$pl_steal_inv, $pl_second, $pl_real, $pl_sms_inv,
	is_wp_error( $pl_dup_res ) ? 0 : (int) $pl_dup_res['investor'] ) as $pl_z ) {
	if ( $pl_z && ! is_wp_error( $pl_z ) ) {
		bhela_test_delete( $pl_z );
	}
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $pl_user_a, $pl_user_admin, $pl_approver, $pl_nobody, $pl_victim,
	(int) $pl_res['user'], (int) $pl_flow_res['user'], (int) $pl_held_res['user'],
	is_wp_error( $pl_res3 ) ? 0 : (int) $pl_res3['user'],
	$pl_steal_usr, (int) $pl_dup_res['user'],
	is_wp_error( $pl_claim_ok ) ? 0 : (int) $pl_claim_ok['user'],
	is_wp_error( $pl_sms_res ) ? 0 : (int) $pl_sms_res['user'],
	is_wp_error( $pl_new_res ) ? 0 : (int) $pl_new_res['user'] ) as $pl_z ) {
	if ( $pl_z && ! is_wp_error( $pl_z ) ) {
		wp_delete_user( $pl_z );
	}
}
pl_reset_limits( $pl_phone_a );
pl_reset_limits( $pl_phone_b );
pl_reset_limits( $pl_phone_x );
pl_reset_limits( '01711000033' );
pl_reset_limits( '01711000044' );
pl_reset_limits( '01711000055' );
pl_reset_limits( '01711000066' );
pl_reset_limits( '01711000088' );
foreach ( array( '111', '222', '333', '444', '555', '666', '777', '888' ) as $pl_z ) {
	pl_reset_limits( '01711000' . $pl_z );
}
$pl_new_inv2 = is_wp_error( $pl_new_res ) ? 0 : (int) $pl_new_res['investor'];
if ( $pl_new_inv2 ) {
	bhela_test_delete( $pl_new_inv2 );
}
update_option( 'bhela_bm_settings', $pl_settings_was );

bhela_test_done();
