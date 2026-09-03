<?php
/**
 * The investor portal — the one place outsiders sign in.
 *
 * Every role this plugin had before belonged to somebody who works for BHELA. This is
 * the first that does not, and that changes the threat model: an investor is a real
 * person with a real login who has a financial interest in figures they cannot be
 * allowed to edit, and no business at all seeing another investor's bank details.
 *
 * The whole security model is one function. **bhela_bm_current_investor() resolves
 * the viewer's record from their user id and nothing else.** No investor id is ever
 * read from a URL, a form, or a cookie, so there is no id to tamper with — the
 * classic "change the number in the address bar" attack has nothing to change.
 * Scoping by hiding UI would leave the data one crafted request away; scoping at the
 * resolver means a wrong id cannot even be expressed.
 *
 * The portal is READ-ONLY. It writes nothing, ever. An investor who disputes a figure
 * takes it up with the office, and the office corrects it with a reversal that leaves
 * a trail — which is the whole point of the ledger being append-only.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * Identity — the security boundary
 * ========================================================= */

/**
 * The investor record belonging to the current user, or 0.
 *
 * The ONLY way the portal learns whose money it is looking at. It takes a user id
 * and returns a post id; there is no parameter for "which investor", by design.
 *
 * @return int Investor post id, or 0 when the viewer is not a linked investor.
 */
function bhela_bm_current_investor() {
	// Keyed by user id, not a bare static. One request normally has one user, but
	// wp_set_current_user() can change it — in cron, WP-CLI, or any code that acts as
	// somebody else — and an unkeyed cache would then hand back the PREVIOUS user's
	// investor record. On the function that decides whose money is on screen, that is
	// not a risk worth carrying for one saved query.
	static $cache = array();

	$user = get_current_user_id();
	if ( ! $user ) {
		return 0;
	}
	if ( isset( $cache[ $user ] ) ) {
		return $cache[ $user ];
	}
	$cache[ $user ] = 0;
	$hit = get_posts( array(
		'post_type'      => 'bhela_investor',
		'post_status'    => array( 'publish', 'private', 'draft' ),
		'posts_per_page' => 2,       // 2 so a duplicate link can be detected, not hidden
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_inv_user',
		'meta_value'     => $user,
	) );

	// Two records claiming one login is a data error with a security edge: whichever
	// happened to sort first would decide whose money a person sees. Refuse instead.
	if ( count( $hit ) !== 1 ) {
		if ( count( $hit ) > 1 && function_exists( 'bhela_bm_log' ) ) {
			bhela_bm_log( 'error', sprintf( 'Investor portal: user #%d is linked to %d investor records — access refused.', $user, count( $hit ) ) );
		}
		return 0;
	}
	$cache[ $user ] = (int) $hit[0];
	return $cache[ $user ];
}

/** The WordPress user linked to an investor record, or 0. */
function bhela_bm_investor_user( $investor_id ) {
	return (int) get_post_meta( $investor_id, '_bhela_inv_user', true );
}

/* =========================================================
 * Keeping investors out of wp-admin
 * ========================================================= */

/**
 * An investor never sees wp-admin.
 *
 * Belt and braces on top of the role holding no capabilities: WordPress screens,
 * other plugins and future core changes are all surface, and the cheapest way to
 * secure a surface is not to expose it. admin-ajax.php is deliberately excluded —
 * blocking it would break anything the theme does for logged-in visitors.
 */
function bhela_bm_investor_block_admin() {
	if ( ! is_admin() || wp_doing_ajax() || ! is_user_logged_in() ) {
		return;
	}
	$user = wp_get_current_user();
	if ( ! in_array( 'bhela_investor', (array) $user->roles, true ) ) {
		return;
	}
	// An account that also holds a staff role keeps its admin access; only a
	// pure-investor login is turned away.
	if ( count( array_intersect( (array) $user->roles, array( 'administrator', 'editor', 'author', 'contributor' ) ) ) ) {
		return;
	}
	wp_safe_redirect( bhela_bm_portal_url() );
	exit;
}
add_action( 'admin_init', 'bhela_bm_investor_block_admin' );

/** No admin bar for an investor — it is a staff affordance and it leaks screen links. */
function bhela_bm_investor_admin_bar( $show ) {
	$user = wp_get_current_user();
	if ( $user && in_array( 'bhela_investor', (array) $user->roles, true ) && 1 === count( (array) $user->roles ) ) {
		return false;
	}
	return $show;
}
add_filter( 'show_admin_bar', 'bhela_bm_investor_admin_bar' );

/** Where the portal lives. Filterable, because the page is created by hand. */
function bhela_bm_portal_url() {
	$page = get_page_by_path( 'investor' );
	$url  = $page ? get_permalink( $page ) : home_url( '/investor/' );
	return apply_filters( 'bhela_bm_portal_url', $url );
}

/* =========================================================
 * Data for the portal, scoped by construction
 * ========================================================= */

/**
 * Everything one investor is allowed to see about themselves.
 *
 * Takes no id. The caller cannot ask about anybody else.
 */
function bhela_bm_portal_data() {
	$id = bhela_bm_current_investor();
	if ( ! $id ) {
		return null;
	}
	$roi = bhela_bm_investor_roi( $id );
	$led = bhela_bm_investor_ledger( $id );
	$cfg = bhela_bm_share_config();

	// Per season, from the rows themselves. A "season" here is the month a profit row
	// was declared for, which is exactly what the distribution wrote.
	$by_month = array();
	foreach ( $led['rows'] as $r ) {
		if ( 'profit' !== $r['type'] ) {
			continue;
		}
		$key = $r['ref'] ? $r['ref'] : substr( $r['date'], 0, 7 );
		if ( ! isset( $by_month[ $key ] ) ) {
			$by_month[ $key ] = 0;
		}
		$by_month[ $key ] += $r['amount'];
	}
	krsort( $by_month );

	// The season this investor is in the middle of, if the owner has named one. A
	// cumulative ROI five years in tells you nothing about how this year is going,
	// which is the question anybody actually holds a share to ask.
	$season = null;
	if ( function_exists( 'bhela_bm_season_for' ) ) {
		$now = bhela_bm_season_for( current_time( 'Y-m-d' ) );
		if ( $now ) {
			$declared = 0;
			$paid     = 0;
			foreach ( $led['rows'] as $r ) {
				if ( $r['date'] < $now['from'] || $r['date'] > $now['to'] ) {
					continue;
				}
				if ( bhela_bm_ledger_reversal_of( $r['id'] ) || $r['reverses'] ) {
					continue;
				}
				if ( 'profit' === $r['type'] ) {
					$declared += $r['amount'];
				} elseif ( in_array( $r['type'], array( 'payment', 'advance' ), true ) ) {
					$paid += $r['amount'];
				}
			}
			$season = array(
				'label'    => $now['label'],
				'from'     => $now['from'],
				'to'       => $now['to'],
				'declared' => $declared,
				'paid'     => $paid,
				'roi'      => $roi['investment'] > 0 ? round( $declared / $roi['investment'] * 100, 2 ) : 0.0,
			);
		}
	}

	// Business-level, not this investor's: the reserve and the management fund belong
	// to the company. Totals only — §18's breakdown of what management spent on is an
	// internal matter, and a portal is not where that conversation happens.
	// Cached, because this is a front-end page and bhela_bm_fund_ledger() replays every
	// row a fund has ever carried to produce one total. Every investor loading the
	// portal was paying for both funds' full history to see two numbers.
	//
	// Fifteen minutes, and the cache is deliberately NOT per investor: these are
	// business-level figures, identical for everybody, so one entry serves all of
	// them. An allocation only appears when a month is distributed, so a figure at
	// most fifteen minutes stale cannot mislead anybody about their own position —
	// which is replayed live, and always will be.
	// NOTHING PER-INVESTOR MAY ENTER THIS ARRAY. The cache key is global and shared
	// by every viewer, which is safe only while the payload is business-level. Adding
	// an investor-specific figure here would leak one member's data to the next one
	// through the cache, with nothing to signal it.
	//
	// And a holder of NO shares is shown none of it. Until self-registration existed,
	// every portal login was one the office had deliberately linked to a real
	// shareholding. Now a person can register themselves, be approved for access, and
	// sit at zero shares while the office works out what they actually bought — and
	// the company's reserve balance is not theirs to read in the meantime. Only
	// rendering the page after registering through it showed this: a brand-new
	// zero-share account was looking at ৳79,569 of management fund.
	$funds = array();
	if ( bhela_bm_investor_shares( $id ) > 0 ) {
		$funds = get_transient( 'bhela_bm_portal_funds' );
		if ( ! is_array( $funds ) ) {
			$funds = array();
			if ( function_exists( 'bhela_bm_funds' ) ) {
				foreach ( bhela_bm_funds() as $key => $fund ) {
					$fl            = bhela_bm_fund_ledger( $key );
					$funds[ $key ] = array( 'label' => $fund['label'], 'allocated' => (int) $fl['allocated'] );
				}
			}
			set_transient( 'bhela_bm_portal_funds', $funds, 15 * MINUTE_IN_SECONDS );
		}
	}

	// A payment somebody has raised but nobody has released. Showing it matters: an
	// investor who can see "৳50,000 approved and on its way" does not ring the office
	// about a balance that is already being dealt with. It is deliberately NOT added
	// to any figure above, because nothing has been paid.
	$pending = array( 'count' => 0, 'total' => 0 );
	if ( function_exists( 'bhela_bm_payreqs' ) ) {
		foreach ( bhela_bm_payreqs( 'requested', $id ) as $pr ) {
			$pending['count']++;
			$pending['total'] += $pr['amount'];
		}
	}

	// Capital value, from an APPROVED valuation only. A draft is somebody still
	// working, and a figure an investor has already seen is a figure they will ask to
	// be paid — so nothing reaches this page until it has been signed off.
	$holding = function_exists( 'bhela_bm_investor_holding' ) ? bhela_bm_investor_holding( $id ) : null;

	return array(
		'id'         => $id,
		'name'       => get_the_title( $id ),
		'holding'    => $holding,
		'code'       => (string) get_post_meta( $id, '_bhela_inv_code', true ),
		'status'     => bhela_bm_investor_status( $id ),
		'joined'     => (string) get_post_meta( $id, '_bhela_inv_date', true ),
		'shares'     => bhela_bm_investor_shares( $id ),
		'share_pct'  => bhela_bm_investor_share_pct( $id ),
		'total_shares' => $cfg['total_shares'],
		'roi'        => $roi,
		'rows'       => $led['rows'],
		'by_month'   => $by_month,
		'position'   => bhela_bm_investor_position( $id ),
		'season'     => $season,
		'funds'      => $funds,
		'pending'    => $pending,
	);
}

/** The investment status, in the words an investor reads rather than a slug. */
function bhela_bm_portal_status_label( $status ) {
	$map = array(
		'active'    => __( 'সক্রিয়', 'bhela-booking' ),
		'suspended' => __( 'স্থগিত', 'bhela-booking' ),
		'exited'    => __( 'প্রত্যাহৃত', 'bhela-booking' ),
	);
	return $map[ $status ] ?? $status;
}

/* =========================================================
 * The shortcode
 * ========================================================= */

function bhela_bm_portal_assets() {
	wp_register_style( 'bhela-bm-investor', BHELA_BM_URL . 'assets/investor.css', array(), BHELA_BM_VERSION );
}
add_action( 'wp_enqueue_scripts', 'bhela_bm_portal_assets' );

function bhela_bm_portal_shortcode() {
	wp_enqueue_style( 'bhela-bm-investor' );

	if ( ! is_user_logged_in() ) {
		return bhela_bm_portal_login();
	}
	$d = bhela_bm_portal_data();
	if ( ! $d ) {
		// A logged-in visitor who is not a linked investor. Deliberately vague: it
		// says nothing about whether investor accounts exist or who holds one.
		return '<div class="bhela-inv"><div class="bhela-inv__card"><p>'
			. esc_html__( 'এই অ্যাকাউন্টের সাথে কোনো বিনিয়োগ রেকর্ড যুক্ত নেই। BHELA অফিসে যোগাযোগ করুন।', 'bhela-booking' )
			. '</p><p><a class="bhela-inv__btn" href="' . esc_url( wp_logout_url( bhela_bm_portal_url() ) ) . '">'
			. esc_html__( 'লগ আউট', 'bhela-booking' ) . '</a></p></div></div>';
	}

	ob_start();
	bhela_bm_portal_render( $d );
	return ob_get_clean();
}
add_shortcode( 'bhela_investor_portal', 'bhela_bm_portal_shortcode' );

/**
 * The sign-in form.
 *
 * The password form this replaced is gone: sign-in is by a one-time code sent to the
 * mobile number on the investor record — see includes/investor-login.php, which holds
 * both the handler and the form. Two things stayed exactly as they were, because they
 * were right: bhela_bm_portal_login_limit() still caps failures per address, and a
 * throttled attempt still renders byte-for-byte the ordinary failure page (§13.43).
 *
 * The POST is handled on `template_redirect` rather than here. A successful sign-in
 * sets a cookie and redirects, and this function runs inside `the_content`, by which
 * point a theme may already have flushed the headers both of those need.
 */
function bhela_bm_portal_login() {
	return bhela_bm_portal_login_form();
}

/** How many failed attempts an address gets in an hour. Filterable. */
function bhela_bm_portal_login_limit() {
	return (int) apply_filters( 'bhela_bm_portal_login_limit', 8 );
}

/** Dashboard + statement. */
function bhela_bm_portal_render( $d ) {
	$money = 'bhela_bm_money';
	$types = bhela_bm_ledger_types();
	?>
	<div class="bhela-inv">
		<header class="bhela-inv__head">
			<div>
				<h2><?php echo esc_html( $d['name'] ); ?></h2>
				<p class="bhela-inv__muted">
					<?php if ( $d['code'] ) : ?><?php echo esc_html( $d['code'] ); ?> · <?php endif; ?>
					<?php
					printf(
						/* translators: 1: shares held, 2: total shares, 3: percentage */
						esc_html__( '%1$d of %2$d shares · %3$s%%', 'bhela-booking' ),
						(int) $d['shares'],
						(int) $d['total_shares'],
						esc_html( (string) $d['share_pct'] )
					);
					?>
				</p>
			</div>
			<div class="bhela-inv__actions">
				<button type="button" class="bhela-inv__btn bhela-inv__btn--ghost" onclick="window.print()">🖨️ <?php esc_html_e( 'স্টেটমেন্ট প্রিন্ট', 'bhela-booking' ); ?></button>
				<a class="bhela-inv__btn bhela-inv__btn--ghost" href="<?php echo esc_url( wp_logout_url( bhela_bm_portal_url() ) ); ?>"><?php esc_html_e( 'লগ আউট', 'bhela-booking' ); ?></a>
			</div>
		</header>

		<div class="bhela-inv__kpis">
			<?php
			$kpis = array(
				array( __( 'বিনিয়োগ', 'bhela-booking' ), $money( $d['roi']['investment'] ) ),
				array( __( 'ঘোষিত লাভ', 'bhela-booking' ), $money( $d['roi']['declared'] ) ),
				array( __( 'প্রাপ্ত', 'bhela-booking' ), $money( $d['roi']['received'] ) ),
				array( __( 'বকেয়া', 'bhela-booking' ), $money( $d['roi']['outstanding'] ) ),
				array( __( 'ROI (প্রাপ্ত)', 'bhela-booking' ), $d['roi']['roi'] . '%' ),
				array( __( 'ROI (ঘোষিত)', 'bhela-booking' ), $d['roi']['roi_declared'] . '%' ),
				array( __( 'অবস্থা', 'bhela-booking' ), bhela_bm_portal_status_label( $d['status'] ) ),
			);
			// The capital side. Deliberately after the cash figures and never summed
			// with them: one is money already received, the other is what the shares
			// would be worth if the business were sold at the approved valuation.
			if ( ! empty( $d['holding'] ) && $d['holding']['valued'] ) {
				$kpis[] = array( __( 'বর্তমান শেয়ার মূল্য', 'bhela-booking' ), $money( $d['holding']['share_value'] ) );
				$kpis[] = array( __( 'বর্তমান হোল্ডিং মূল্য', 'bhela-booking' ), $money( $d['holding']['holding'] ) );
				$kpis[] = array(
					__( 'মূলধন বৃদ্ধি', 'bhela-booking' ),
					( $d['holding']['appreciation'] >= 0 ? '+' : '' ) . $money( $d['holding']['appreciation'] ),
				);
			}
			if ( $d['season'] ) {
				$kpis[] = array(
					/* translators: %s: season name */
					sprintf( __( '%s — ঘোষিত', 'bhela-booking' ), $d['season']['label'] ),
					$money( $d['season']['declared'] ),
				);
				$kpis[] = array(
					/* translators: %s: season name */
					sprintf( __( '%s — ROI', 'bhela-booking' ), $d['season']['label'] ),
					$d['season']['roi'] . '%',
				);
			}
			foreach ( $d['funds'] as $f ) {
				$kpis[] = array( $f['label'], $money( $f['allocated'] ) );
			}
			foreach ( $kpis as $k ) :
				?>
				<div class="bhela-inv__kpi"><span><?php echo esc_html( $k[0] ); ?></span><strong><?php echo esc_html( $k[1] ); ?></strong></div>
			<?php endforeach; ?>
		</div>

		<?php if ( ! empty( $d['holding'] ) && $d['holding']['valued'] ) : ?>
			<p class="bhela-inv__note"><?php
				printf(
					/* translators: 1: share value, 2: valuation date */
					esc_html__( 'বর্তমান শেয়ার মূল্য %1$s — %2$s তারিখের অনুমোদিত মূল্যায়ন অনুযায়ী। এটি একটি প্রাক্কলিত ন্যায্য মূল্য, নিশ্চিত বিক্রয়মূল্য নয়; শেয়ার বিক্রি না করা পর্যন্ত এই বৃদ্ধি নগদ নয়। আপনার প্রাপ্ত লাভ এর সাথে যোগ হয়নি — সেটি আলাদা করে উপরে দেখানো আছে।', 'bhela-booking' ),
					esc_html( $money( $d['holding']['share_value'] ) ),
					esc_html( mysql2date( 'j M Y', $d['holding']['as_at'] ) )
				);
			?></p>
		<?php endif; ?>

		<?php if ( $d['pending']['count'] > 0 ) : ?>
			<p class="bhela-inv__note"><?php
				printf(
					/* translators: %s: amount */
					esc_html__( '%s অনুমোদনের অপেক্ষায় আছে। অনুমোদনের আগে এটি উপরের কোনো হিসাবে যোগ হয়নি।', 'bhela-booking' ),
					esc_html( $money( $d['pending']['total'] ) )
				);
			?></p>
		<?php endif; ?>

		<?php if ( $d['position']['last_payment'] ) : ?>
			<p class="bhela-inv__muted"><?php
				printf(
					/* translators: %s: date */
					esc_html__( 'সর্বশেষ পেমেন্ট: %s', 'bhela-booking' ),
					esc_html( mysql2date( 'j M Y', $d['position']['last_payment'] ) )
				);
			?></p>
		<?php endif; ?>

		<?php if ( $d['by_month'] ) : ?>
			<section class="bhela-inv__card">
				<h3><?php esc_html_e( 'মাসভিত্তিক লাভ', 'bhela-booking' ); ?></h3>
				<div class="bhela-inv__scroll">
					<table class="bhela-inv__table">
						<thead><tr>
							<th><?php esc_html_e( 'মাস', 'bhela-booking' ); ?></th>
							<th class="num"><?php esc_html_e( 'ঘোষিত লাভ', 'bhela-booking' ); ?></th>
							<th class="num"><?php esc_html_e( 'ROI', 'bhela-booking' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $d['by_month'] as $m => $amt ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'F Y', $m . '-01' ) ); ?></td>
								<td class="num"><?php echo esc_html( $money( $amt ) ); ?></td>
								<td class="num"><?php echo esc_html( $d['roi']['investment'] > 0 ? round( $amt / $d['roi']['investment'] * 100, 2 ) . '%' : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<section class="bhela-inv__card">
			<h3><?php esc_html_e( 'লেনদেনের বিবরণ', 'bhela-booking' ); ?></h3>
			<?php if ( ! $d['rows'] ) : ?>
				<p class="bhela-inv__muted"><?php esc_html_e( 'এখনো কোনো লেনদেন নেই।', 'bhela-booking' ); ?></p>
			<?php else : ?>
				<div class="bhela-inv__scroll">
					<table class="bhela-inv__table">
						<thead><tr>
							<th><?php esc_html_e( 'তারিখ', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'বিবরণ', 'bhela-booking' ); ?></th>
							<th class="num"><?php esc_html_e( 'পরিমাণ', 'bhela-booking' ); ?></th>
							<th class="num"><?php esc_html_e( 'ব্যালেন্স', 'bhela-booking' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $d['rows'] as $r ) : ?>
							<?php $undone = bhela_bm_ledger_reversal_of( $r['id'] ); ?>
							<tr<?php echo $undone ? ' class="is-void"' : ''; ?>>
								<td><?php echo esc_html( mysql2date( 'j M Y', $r['date'] ) ); ?></td>
								<td><?php echo esc_html( $types[ $r['type'] ]['label'] ?? $r['type'] ); ?>
									<?php if ( $r['note'] ) : ?><span class="bhela-inv__muted"> — <?php echo esc_html( $r['note'] ); ?></span><?php endif; ?>
									<?php if ( $undone ) : ?><span class="bhela-inv__void"><?php esc_html_e( 'বাতিল', 'bhela-booking' ); ?></span><?php endif; ?>
								</td>
								<td class="num"><?php echo esc_html( ( $r['signed'] > 0 ? '+' : '' ) . $money( $r['signed'] ) ); ?></td>
								<td class="num"><strong><?php echo esc_html( $money( $r['balance'] ) ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
			<p class="bhela-inv__muted"><?php esc_html_e( 'কোনো অঙ্ক নিয়ে প্রশ্ন থাকলে BHELA অফিসে যোগাযোগ করুন — সংশোধন সবসময় নতুন এন্ট্রি হিসেবে যুক্ত হয়, পুরোনো রেকর্ড মুছে নয়।', 'bhela-booking' ); ?></p>
		</section>
	</div>
	<?php
}
