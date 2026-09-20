<?php
/**
 * The ⚖️ Settlement screen — the two directions, side by side.
 *
 * Split from includes/settlement.php on the same grounds as the valuation module: the
 * arithmetic is testable without a screen, and the screen is a rendering of figures
 * somebody else computed.
 *
 * The one rule this screen exists to keep: **the two directions are never added
 * together.** A season where one investor is owed ৳50,000 and another owes ৳50,000 is
 * not a settled season, and a single netted figure says it is. So there are two cards,
 * two totals and two groups of rows, and the net appears only where it is labelled a
 * net.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_settlement_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Settlement', 'bhela-booking' ),
		'⚖️ ' . __( 'Settlement', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-settlement',
		'bhela_bm_settlement_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_settlement_menu', 21 );

/** The window the screen is currently showing, read from the request. */
function bhela_bm_settlement_request_window() {
	return bhela_bm_settlement_window(
		isset( $_GET['season'] ) ? sanitize_key( wp_unslash( $_GET['season'] ) ) : '',
		isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
		isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''
	);
}

/* =========================================================
 * THE SCREEN
 * ========================================================= */

function bhela_bm_settlement_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this screen.', 'bhela-booking' ) );
	}

	$win     = bhela_bm_settlement_request_window();
	$d       = bhela_bm_settlement( $win['from'], $win['to'] );
	$states  = bhela_bm_settlement_states();
	$seasons = function_exists( 'bhela_bm_seasons' ) ? bhela_bm_seasons() : array();
	$money   = 'bhela_bm_money';

	$export = wp_nonce_url(
		add_query_arg(
			array( 'action' => 'bhela_bm_settlement_csv', 'season' => $win['season'], 'from' => $win['from'], 'to' => $win['to'] ),
			admin_url( 'admin-post.php' )
		),
		'bhela_bm_settlement_csv'
	);
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'⚖️',
			__( 'Settlement', 'bhela-booking' ),
			__( 'What each investor was declared, what they were actually paid, and which way the difference runs. Money owed to an investor and money owed by one are never added together — they are two different conversations.', 'bhela-booking' ),
			sprintf(
				'<a class="button" href="%s">%s</a>',
				esc_url( $export ),
				esc_html__( 'Download CSV', 'bhela-booking' )
			)
		);
		?>

		<form method="get" class="bha-bar">
			<input type="hidden" name="page" value="bhela-bm-settlement">
			<label>
				<?php esc_html_e( 'সিজন', 'bhela-booking' ); ?>
				<select name="season" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'সব সময়', 'bhela-booking' ); ?></option>
					<?php foreach ( $seasons as $skey => $srow ) : ?>
						<option value="<?php echo esc_attr( $skey ); ?>" <?php selected( $win['season'], $skey ); ?>>
							<?php echo esc_html( $srow['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'From', 'bhela-booking' ); ?>
				<input type="date" name="from" value="<?php echo esc_attr( $win['season'] ? '' : $win['from'] ); ?>"></label>
			<label><?php esc_html_e( 'To', 'bhela-booking' ); ?>
				<input type="date" name="to" value="<?php echo esc_attr( $win['season'] ? '' : $win['to'] ); ?>"></label>
			<button class="button"><?php esc_html_e( 'Filter', 'bhela-booking' ); ?></button>
			<span class="description">
				<?php
				printf(
					/* translators: %s: the period being shown */
					esc_html__( 'দেখাচ্ছে: %s', 'bhela-booking' ),
					'<strong>' . esc_html( $win['label'] ) . '</strong>'
				);
				?>
			</span>
		</form>

		<?php if ( ! $seasons ) : ?>
			<div class="bha-callout bha-callout--attention">
				<?php
				printf(
					/* translators: %s: link to the settings screen */
					esc_html__( 'কোনো সিজন এখনো নির্ধারণ করা হয়নি, তাই উপরের তালিকাটি ফাঁকা। ৩–৪ মাসের সেশন ধরে হিসাব দেখতে চাইলে %s থেকে সিজনগুলো যোগ করুন — তারপর এক ক্লিকেই সেই সময়ের হিসাব আসবে।', 'bhela-booking' ),
					'<a href="' . esc_url( bhela_bm_admin_url( 'bhela-bm-settings' ) . '#bhela-panel-heads' ) . '">'
						. esc_html__( 'Settings → Lists → Seasons', 'bhela-booking' ) . '</a>'
				);
				?>
			</div>
		<?php endif; ?>

		<div class="bha-cards">
			<div class="bha-card">
				<span class="bha-card__label"><?php esc_html_e( 'ভেলা দেবে', 'bhela-booking' ); ?></span>
				<span class="bha-card__value is-danger"><?php echo esc_html( $money( $d['owed_to_investor'] ) ); ?></span>
				<span class="bha-card__hint bha-plain">
					<?php
					printf(
						/* translators: %d: number of investors */
						esc_html__( '%d জন বিনিয়োগকারী', 'bhela-booking' ),
						(int) $d['count_owed']
					);
					?>
				</span>
			</div>
			<div class="bha-card">
				<span class="bha-card__label"><?php esc_html_e( 'ভেলা পাবে', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( $money( $d['owed_to_bhela'] ) ); ?></span>
				<span class="bha-card__hint bha-plain">
					<?php
					printf(
						/* translators: %d: number of investors */
						esc_html__( '%d জন বিনিয়োগকারী', 'bhela-booking' ),
						(int) $d['count_owes']
					);
					?>
				</span>
			</div>
			<div class="bha-card">
				<span class="bha-card__label"><?php esc_html_e( 'নিট পার্থক্য', 'bhela-booking' ); ?></span>
				<span class="bha-card__value"><?php echo esc_html( $money( $d['net'] ) ); ?></span>
				<span class="bha-card__hint"><?php esc_html_e( 'দুই দিকের বিয়োগফল — একা পড়ার মতো সংখ্যা নয়', 'bhela-booking' ); ?></span>
			</div>
			<?php if ( $d['count_undeclared'] ) : ?>
				<div class="bha-card">
					<span class="bha-card__label"><?php esc_html_e( 'লাভ ঘোষণা হয়নি', 'bhela-booking' ); ?></span>
					<span class="bha-card__value"><?php echo esc_html( $money( $d['undeclared'] ) ); ?></span>
					<span class="bha-card__hint bha-plain">
						<?php
						printf(
							/* translators: %d: number of investors */
							esc_html__( '%d জন — কোনো দিকেই গোনা হয়নি', 'bhela-booking' ),
							(int) $d['count_undeclared']
						);
						?>
					</span>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $d['count_undeclared'] ) : ?>
			<div class="bha-callout bha-callout--attention">
				<strong><?php esc_html_e( 'এই সময়ে কিছু বিনিয়োগকারীর লাভ ঘোষণা করা হয়নি।', 'bhela-booking' ); ?></strong>
				<?php esc_html_e( 'তাঁরা টাকা পেয়েছেন, কিন্তু সিস্টেমে ওই সময়ের লাভ বণ্টন করা নেই — তাই তাঁরা “বেশি নিয়েছেন” নন, হিসাবটাই অসম্পূর্ণ। উপরের কোনো মোট অঙ্কে তাঁদের ধরা হয়নি। ওই মাসগুলোর Distribution চালান, অথবা ইমপোর্টে লাভের কলামটি দিন।', 'bhela-booking' ); ?>
			</div>
		<?php endif; ?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'বিনিয়োগকারীভিত্তিক হিসাব', 'bhela-booking' ); ?></h2>
			<?php if ( ! $d['rows'] ) : ?>
				<p class="description"><?php esc_html_e( 'এই সময়ে কোনো লেনদেন নেই।', 'bhela-booking' ); ?></p>
			<?php else : ?>
				<table class="widefat striped bha-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'Shares', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'ঘোষিত লাভ', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'সমন্বয়', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'দেওয়া হয়েছে', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'পার্থক্য', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'কে কাকে দেবে', 'bhela-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $d['rows'] as $r ) : ?>
						<?php $st = $states[ $r['state'] ] ?? $states['settled']; ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-investor-report', array( 'investor' => $r['investor'] ) ) ); ?>"><?php echo esc_html( $r['name'] ); ?></a>
								<?php if ( $r['code'] ) : ?><br><span class="description"><?php echo esc_html( $r['code'] ); ?></span><?php endif; ?>
							</td>
							<td class="bha-num bha-plain"><?php echo esc_html( (string) $r['shares'] ); ?></td>
							<td class="bha-num"><?php echo esc_html( $money( $r['declared'] ) ); ?></td>
							<td class="bha-num"><?php echo esc_html( $r['adjustments'] ? $money( $r['adjustments'] ) : '—' ); ?></td>
							<td class="bha-num"><?php echo esc_html( $money( $r['paid'] ) ); ?></td>
							<td class="bha-num"><strong><?php echo esc_html( $money( abs( $r['balance'] ) ) ); ?></strong></td>
							<td><?php echo wp_kses_post( bhela_bm_status_pill( $st['label'], $st['tone'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th><?php esc_html_e( 'Total', 'bhela-booking' ); ?></th>
							<th></th>
							<th class="bha-num"><?php echo esc_html( $money( $d['declared'] ) ); ?></th>
							<th class="bha-num"><?php echo esc_html( $d['adjustments'] ? $money( $d['adjustments'] ) : '—' ); ?></th>
							<th class="bha-num"><?php echo esc_html( $money( $d['paid'] ) ); ?></th>
							<th class="bha-num"></th>
							<th></th>
						</tr>
					</tfoot>
				</table>
			<?php endif; ?>
			<p class="description">
				<?php esc_html_e( 'পার্থক্য = ঘোষিত লাভ + সমন্বয় − দেওয়া হয়েছে। বাতিল করা এন্ট্রি কোনো দিকেই গোনা হয় না। সব সময়ের হিসাব দেখলে এই অঙ্ক প্রত্যেকের স্টেটমেন্টের শেষ ব্যালেন্সের সমান হয় — দুটো কখনো আলাদা হতে পারে না।', 'bhela-booking' ); ?>
			</p>
		</div>
	</div>
	<?php
}

/* =========================================================
 * CSV
 * ========================================================= */

function bhela_bm_settlement_csv() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this.', 'bhela-booking' ) );
	}
	check_admin_referer( 'bhela_bm_settlement_csv' );

	$win    = bhela_bm_settlement_request_window();
	$d      = bhela_bm_settlement( $win['from'], $win['to'] );
	$states = bhela_bm_settlement_states();

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=bhela-settlement.csv' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );

	// No account numbers and no NID, for the reason the register CSV gives: an export
	// leaves the building, and "who has a copy of this" stops being answerable.
	fputcsv( $out, array_map( 'bhela_bm_csv_cell', array(
		__( 'Period', 'bhela-booking' ),
		$win['label'],
	) ) );
	fputcsv( $out, array( '' ) );

	fputcsv( $out, array_map( 'bhela_bm_csv_cell', array(
		__( 'Investor', 'bhela-booking' ),
		__( 'Investor ID', 'bhela-booking' ),
		__( 'Shares', 'bhela-booking' ),
		__( 'Declared', 'bhela-booking' ),
		__( 'Adjustments', 'bhela-booking' ),
		__( 'Paid', 'bhela-booking' ),
		__( 'Balance', 'bhela-booking' ),
		__( 'Direction', 'bhela-booking' ),
	) ) );

	foreach ( $d['rows'] as $r ) {
		$st = $states[ $r['state'] ] ?? $states['settled'];
		fputcsv( $out, array(
			bhela_bm_csv_cell( $r['name'] ),
			bhela_bm_csv_cell( $r['code'] ),
			$r['shares'],
			$r['declared'],
			$r['adjustments'],
			$r['paid'],
			// Signed, because a spreadsheet can sort on it and a reader cannot sort on
			// a word. The direction column says which way in words beside it.
			$r['balance'],
			bhela_bm_csv_cell( $st['label'] ),
		) );
	}

	fputcsv( $out, array( '' ) );
	// The two totals on their own rows. Netting them into one is the defect this
	// screen exists to fix, and a CSV that nets them would put it straight back.
	fputcsv( $out, array( bhela_bm_csv_cell( __( 'ভেলা দেবে', 'bhela-booking' ) ), '', '', '', '', '', $d['owed_to_investor'], '' ) );
	fputcsv( $out, array( bhela_bm_csv_cell( __( 'ভেলা পাবে', 'bhela-booking' ) ), '', '', '', '', '', $d['owed_to_bhela'], '' ) );
	if ( $d['count_undeclared'] ) {
		fputcsv( $out, array( bhela_bm_csv_cell( __( 'লাভ ঘোষণা হয়নি', 'bhela-booking' ) ), '', '', '', '', '', $d['undeclared'], '' ) );
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_bhela_bm_settlement_csv', 'bhela_bm_settlement_csv' );
