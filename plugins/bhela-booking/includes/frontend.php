<?php
/**
 * Frontend: multi-cabin booking form + AJAX submission + availability check.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Assets ---------- */

function bhela_bm_enqueue_assets() {
	wp_register_style( 'bhela-bm-booking', BHELA_BM_URL . 'assets/booking.css', array(), BHELA_BM_VERSION );
	wp_register_script( 'bhela-bm-booking', BHELA_BM_URL . 'assets/booking.js', array(), BHELA_BM_VERSION, true );

	$settings = bhela_bm_get_settings();
	$rates    = array();
	foreach ( bhela_bm_get_rates() as $key => $row ) {
		$rates[ $key ] = array(
			'label'   => $row['label'],
			'sharing' => (int) $row['sharing'],
			'regular' => (int) $row['regular'],
			'weekday' => (int) $row['weekday'],
		);
	}
	// Rates indexed by cabin occupancy (2..6) — the engine prices by occupancy.
	$occ_rates = array();
	foreach ( bhela_bm_rates_by_occupancy() as $occ => $row ) {
		$occ_rates[ $occ ] = array(
			'regular' => (int) $row['regular'],
			'weekday' => (int) $row['weekday'],
			'label'   => $row['label'],
		);
	}
	wp_localize_script( 'bhela-bm-booking', 'bhelaBM', array(
		'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
		'nonce'          => wp_create_nonce( 'bhela_bm_booking' ),
		'rates'          => $rates,
		'occRates'       => $occ_rates,
		'maxCabins'      => bhela_bm_max_cabins(),
		'maxGuests'      => bhela_bm_max_guests(),
		'weekendDays'    => array_map( 'intval', (array) $settings['weekend_days'] ),
		'holidays'       => array_values( array_filter( array_map( 'trim', explode( "\n", (string) $settings['holidays'] ) ) ) ),
		'advancePercent' => (int) $settings['advance_percent'],
		'childPercent'   => 50,
		'whatsapp'       => preg_replace( '/[^0-9]/', '', $settings['whatsapp'] ),
	) );
}
add_action( 'wp_enqueue_scripts', 'bhela_bm_enqueue_assets', 20 );

/* ---------- Shortcode: [bhela_booking_form] ---------- */

function bhela_bm_booking_form_shortcode() {
	wp_enqueue_style( 'bhela-bm-booking' );
	wp_enqueue_script( 'bhela-bm-booking' );

	ob_start();
	?>
	<div class="bhela-bm-form-wrap" id="bhela-booking">
		<div class="bhela-bm-tabs" id="bm-tabs">
			<button type="button" class="bhela-bm-tab is-active" data-tab="book">🛶 বুক করুন</button>
			<button type="button" class="bhela-bm-tab" data-tab="track">📍 বুকিং ট্র্যাক করুন</button>
		</div>

		<div class="bm-done" id="bm-done" hidden></div>

		<div class="bm-panel" id="bm-track-panel" hidden><?php echo bhela_bm_track_panel_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

		<div class="bm-panel" id="bm-book-panel">
		<div class="bhela-bm-steps" id="bm-stepbar">
			<span class="bm-stepdot is-active" data-dot="1"><i class="bm-stepdot__pill">১</i><em class="bm-stepdot__label">তারিখ</em></span>
			<span class="bm-stepdot" data-dot="2"><i class="bm-stepdot__pill">২</i><em class="bm-stepdot__label">অতিথি ও কেবিন</em></span>
			<span class="bm-stepdot" data-dot="3"><i class="bm-stepdot__pill">৩</i><em class="bm-stepdot__label">আপনার তথ্য</em></span>
		</div>
		<form class="bhela-bm-form" id="bhela-bm-form" novalidate>
			<div class="bhela-bm-hp" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden">
				<label>Leave this field empty<input type="text" name="bhela_bm_hp" tabindex="-1" autocomplete="off"></label>
			</div>
			<div class="bhela-bm-layout">
				<div class="bhela-bm-main">
					<fieldset class="bhela-bm-step" id="bm-step-date" data-step="1">
						<legend><span class="bhela-bm-step__num">১</span> কবে যেতে চান?</legend>
						<p class="bhela-bm-step__sub">আসন্ন গ্রুপ ট্রিপ থেকে বেছে নিন, অথবা নিজের তারিখ দিন — Availability সাথে সাথে দেখা যাবে।</p>
						<?php
						$bm_today    = date( 'Y-m-d' );
						$bm_upcoming = array();
						if ( function_exists( 'bhela_bm_get_trips' ) ) {
							foreach ( bhela_bm_get_trips() as $bm_t ) {
								if ( empty( $bm_t['date'] ) || $bm_t['date'] < $bm_today ) {
									continue;
								}
								if ( isset( $bm_t['status'] ) && 'booked' === $bm_t['status'] ) {
									continue;
								}
								$bm_upcoming[] = $bm_t;
								if ( count( $bm_upcoming ) >= 5 ) {
									break;
								}
							}
						}
						if ( $bm_upcoming ) :
							$bm_daylabels = array( 'weekday' => 'Weekday −২০%', 'weekend' => 'Weekend', 'holiday' => 'ছুটি' );
							?>
							<div class="bm-datechips" id="bm-datechips">
								<?php foreach ( $bm_upcoming as $bm_t ) : ?>
									<?php $bm_dt = function_exists( 'bhela_bm_day_type' ) ? bhela_bm_day_type( $bm_t['date'] ) : 'weekday'; ?>
									<button type="button" class="bm-chip" data-date="<?php echo esc_attr( $bm_t['date'] ); ?>"><span><?php echo esc_html( date_i18n( 'j M', strtotime( $bm_t['date'] ) ) ); ?></span><small><?php echo esc_html( isset( $bm_daylabels[ $bm_dt ] ) ? $bm_daylabels[ $bm_dt ] : $bm_dt ); ?></small></button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<div class="bhela-bm-grid">
							<p class="bhela-bm-field">
								<label for="bm-date">ভ্রমণের তারিখ *</label>
								<input type="date" id="bm-date" name="date" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
							</p>
							<p class="bhela-bm-field" style="align-self:end">
								<button type="button" class="bhela-bm-avail-btn" id="bm-check-avail">🔄 আবার চেক করুন</button>
							</p>
						</div>
						<div class="bhela-bm-avail" id="bm-avail-result" hidden></div>
						<div class="bhela-bm-blocked" id="bm-blocked" hidden>
							❌ <strong>এই তারিখে বুকড</strong> — অন্য তারিখ বাছাই করুন, অথবা
							<a href="#" id="bm-blocked-wa" target="_blank" rel="noopener">WhatsApp-এ জিজ্ঞেস করুন</a>।
						</div>
						<div class="bm-nav" data-nav="1">
							<button type="button" class="bm-next" id="bm-next-1" data-next="2" disabled>পরবর্তী: অতিথি ও কেবিন →</button>
						</div>
					</fieldset>

					<fieldset class="bhela-bm-step" id="bm-step-cabins" data-step="2">
						<legend><span class="bhela-bm-step__num">২</span> কতজন যাচ্ছেন?</legend>
						<p class="bhela-bm-step__sub">শুধু সংখ্যা দিন — সেরা কেবিন প্ল্যান ও দাম আমরা বেছে দিচ্ছি।</p>
						<div class="bm-guestpick">
							<p class="bhela-bm-field">
								<label for="bm-g-adults">বড় (৯+) *</label>
								<span class="bm-stepper__ctl"><button type="button" class="bm-stepper__btn" data-delta="-1" aria-label="কমান">−</button><output class="bm-stepper__val" id="bm-out-adults" aria-live="polite">2</output><button type="button" class="bm-stepper__btn bm-stepper__btn--plus" data-delta="1" aria-label="বাড়ান">+</button></span><input type="hidden" id="bm-g-adults" value="2" data-min="0" data-max="<?php echo esc_attr( bhela_bm_max_guests() ); ?>" data-out="bm-out-adults">
							</p>
							<p class="bhela-bm-field">
								<label for="bm-g-c48">শিশু ৪–৮ <span>(৫০%)</span></label>
								<span class="bm-stepper__ctl"><button type="button" class="bm-stepper__btn" data-delta="-1" aria-label="কমান">−</button><output class="bm-stepper__val" id="bm-out-c48" aria-live="polite">0</output><button type="button" class="bm-stepper__btn bm-stepper__btn--plus" data-delta="1" aria-label="বাড়ান">+</button></span><input type="hidden" id="bm-g-c48" value="0" data-min="0" data-max="10" data-out="bm-out-c48">
							</p>
							<p class="bhela-bm-field">
								<label for="bm-g-c04">শিশু ০–৪ <span>(ফ্রি)</span></label>
								<span class="bm-stepper__ctl"><button type="button" class="bm-stepper__btn" data-delta="-1" aria-label="কমান">−</button><output class="bm-stepper__val" id="bm-out-c04" aria-live="polite">0</output><button type="button" class="bm-stepper__btn bm-stepper__btn--plus" data-delta="1" aria-label="বাড়ান">+</button></span><input type="hidden" id="bm-g-c04" value="0" data-min="0" data-max="10" data-out="bm-out-c04">
							</p>
						</div>
						<p class="bm-guest-error" id="bm-guest-error" hidden>⚠️ শিশু (৪–৮) থাকলে অন্তত ১ জন বড় (৯+) থাকতে হবে।</p>
						<div class="bm-autoplan" id="bm-autoplan" hidden>
							<span class="bm-autoplan__label">✨ আমাদের সাজেশন — চাইলে নিজের কম্বিনেশন বেছে নিন:</span>
							<div id="bm-autoplan-chips" class="bm-opts"></div>
							<button type="button" class="bm-edit-toggle" id="bm-edit-toggle">✏️ নিজে কম্বিনেশন এডিট করুন</button>
						</div>
						<div class="bm-edit" id="bm-edit" hidden>
							<div class="bm-edit__head">✏️ কম্বিনেশন এডিট <button type="button" class="bm-edit__close" id="bm-edit-close">✕ সাজেশনে ফিরুন</button></div>
							<div id="bm-edit-rows"></div>
							<button type="button" class="bhela-bm-addcabin" id="bm-edit-add">➕ কেবিন যোগ করুন</button>
							<p class="bm-edit__note" id="bm-edit-note"></p>
						</div>
						<p class="bhela-bm-childnote">👶 ০–৪ বছর ফ্রি · ৪–৮ বছর ৫০% · ৯+ পূর্ণ রেট। ২–<?php echo esc_html( bhela_bm_max_guests() ); ?> জন (<?php echo esc_html( bhela_bm_max_cabins() ); ?>টি কেবিন) · যত বেশি জন, জনপ্রতি রেট তত কম।</p>
						<label class="bm-fullboat">
							<input type="checkbox" id="bm-fullboat" name="full_boat" value="1">
							<span>🚢 <strong>পুরো বোট রিজার্ভ</strong> করতে চাই — কাস্টম কোটের জন্য রিকোয়েস্ট পাঠাবো (<?php echo esc_html( bhela_bm_max_cabins() ); ?> কেবিন · <?php echo esc_html( bhela_bm_max_guests() ); ?> জন)</span>
						</label>
						<div class="bm-nav" data-nav="2">
							<button type="button" class="bm-back" data-back="1">← পিছনে</button>
							<button type="button" class="bm-next" id="bm-next-2" data-next="3" disabled>পরবর্তী: আপনার তথ্য →</button>
						</div>
					</fieldset>

					<fieldset class="bhela-bm-step" id="bm-step-info" data-step="3">
						<legend><span class="bhela-bm-step__num">৩</span> প্রায় শেষ!</legend>
						<p class="bhela-bm-step__sub">নাম ও নম্বর দিন — আমাদের টিম ফোন/WhatsApp-এ কনফার্ম করবে।</p>
						<div class="bhela-bm-grid">
							<p class="bhela-bm-field">
								<label for="bm-name">আপনার নাম *</label>
								<input type="text" id="bm-name" name="name" required autocomplete="name" placeholder="পুরো নাম">
							</p>
							<p class="bhela-bm-field">
								<label for="bm-phone">মোবাইল নম্বর *</label>
								<input type="tel" id="bm-phone" name="phone" required placeholder="01XXXXXXXXX" autocomplete="tel" inputmode="tel">
							</p>
						</div>
						<p class="bhela-bm-field">
							<label for="bm-email">ইমেইল <span>(ইনভয়েস পেতে — ঐচ্ছিক)</span></label>
							<input type="email" id="bm-email" name="email" autocomplete="email" placeholder="you@email.com">
						</p>
						<p class="bhela-bm-field">
							<label for="bm-message">বিশেষ নোট <span>(ঐচ্ছিক)</span></label>
							<textarea id="bm-message" name="message" rows="3" placeholder="যেমন: Full Boat Reservation, Corporate Tour, BBQ..."></textarea>
						</p>
						<div class="bm-nav" data-nav="3">
							<button type="button" class="bm-back" data-back="2">← পিছনে</button>
						</div>
					</fieldset>

					<div class="bhela-bm-response" id="bhela-bm-response" role="status" aria-live="polite"></div>
				</div>

				<aside class="bhela-bm-side" id="bm-side">
					<div class="bhela-bm-summary">
						<h3>🧾 আপনার বুকিং</h3>
						<div class="bhela-bm-price" id="bhela-bm-price" hidden>
							<div id="bm-breakdown"></div>
							<div class="bhela-bm-price__row"><span>দিনের ধরন</span><strong id="bm-daytype">—</strong></div>
							<div class="bhela-bm-price__row"><span>মোট অতিথি</span><strong id="bm-guests-echo">—</strong></div>
							<div class="bhela-bm-price__row"><span>মোট</span><strong id="bm-total">—</strong></div>
							<div class="bhela-bm-price__row bhela-bm-price__row--save" id="bm-savings-row" hidden><span>আপনার সাশ্রয় 🎉</span><strong id="bm-savings">—</strong></div>
							<div class="bhela-bm-price__row bhela-bm-price__row--advance"><span>অগ্রিম (৫০%)</span><strong id="bm-advance">—</strong></div>
						</div>
						<p class="bhela-bm-empty" id="bhela-bm-empty">তারিখ ও অতিথি সংখ্যা দিলে এখানে সেরা কেবিন অপশন ও দাম দেখা যাবে।</p>
						<div class="bm-discount" id="bm-discount">
							<button type="button" class="bm-discount__toggle" id="bm-discount-toggle">💬 আরও ভালো দাম চাই? (Request Discount)</button>
							<div class="bm-discount__body" id="bm-discount-body" hidden>
								<label class="bm-discount__label" for="bm-budget">আপনার বাজেট / প্রস্তাবিত মূল্য (৳)</label>
								<input type="number" id="bm-budget" name="requested_price" min="0" step="100" placeholder="যেমন: 65000" inputmode="numeric">
								<textarea id="bm-discount-msg" name="discount_msg" rows="2" placeholder="আপনার অনুরোধ (ঐচ্ছিক)"></textarea>
								<p class="bm-discount__hint">আমরা আপনার প্রস্তাব দেখে কাউন্টার অফার জানাবো।</p>
							</div>
						</div>
						<button type="submit" class="bhela-bm-submit" id="bhela-bm-submit"><span class="bhela-bm-submit__label">বুকিং রিকোয়েস্ট পাঠান →</span></button>
						<p class="bhela-bm-note">অগ্রিম পাওয়ার পরই বুকিং Confirmed হয়। আমাদের টিম ফোন/WhatsApp-এ যোগাযোগ করবে।</p>
						<p class="bhela-bm-reviews-line"><span class="stars">★★★★★</span> ৩০০+ পরিবার ঘুরে এসেছে</p>
							<ul class="bhela-bm-trust">
							<li>🛟 Life Jacket ও প্রশিক্ষিত ক্রু</li>
							<li>🔒 তথ্য সম্পূর্ণ গোপনীয়</li>
							<li>💳 bKash · Nagad · Bank</li>
						</ul>
					</div>
				</aside>
			</div>
		</form>
		</div><!-- #bm-book-panel -->
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'bhela_booking_form', 'bhela_bm_booking_form_shortcode' );

/* ---------- Multi-cabin price calculation (server-side, authoritative) ---------- */

/**
 * Price a chosen cabin combination.
 *
 * Cabin size / rate tier is decided by the ADULT count alone. 4–8 children ride
 * in that cabin and pay 50% of the same per-person rate (after any discount) —
 * they never push the booking into a bigger, cheaper-per-head tier. Example:
 * 4 adults + one 5-year-old on a weekend is a 4-person cabin (4 × 10,000) plus
 * 5,000 for the child = 45,000 — not a 5-person cabin at 9,000/head.
 *
 * 0–4 infants are FREE ride-alongs (shared food and bed with the parents) and
 * do not affect the cabin size, rate tier, guest count, or capacity.
 *
 * $cabins: array of ['adults' => n, 'c48' => n, 'c04' => n].
 * Returns array|WP_Error.
 */
function bhela_bm_calc_multi( $cabins, $date ) {
	$settings = bhela_bm_get_settings();
	$day_type = bhela_bm_day_type( $date );
	$max_cap  = max( array_keys( bhela_bm_rates_by_occupancy() ) );

	$total         = 0;
	$regular_total = 0;
	$guests        = 0; // paying occupants (adults + 4–8 children); infants excluded
	$adults_total  = 0;
	$c48_total     = 0;
	$lines         = array();

	foreach ( (array) $cabins as $c ) {
		$adults = max( 0, (int) ( $c['adults'] ?? 0 ) );
		$c48    = max( 0, (int) ( $c['c48'] ?? 0 ) );
		$c04    = max( 0, (int) ( $c['c04'] ?? 0 ) );
		$occ    = $adults + $c48;          // people sharing the cabin — 0–4 infants excluded
		if ( $occ + $c04 < 1 ) {
			continue;
		}
		if ( $adults < 1 ) {
			return new WP_Error( 'no_adult', __( 'প্রতিটি কেবিনে অন্তত ১ জন বড় (৯+) থাকতে হবে।', 'bhela-booking' ) );
		}
		if ( $occ < 2 ) {
			return new WP_Error( 'lone_cabin', __( 'প্রতিটি কেবিনে অন্তত ২ জন অতিথি থাকতে হবে (শিশু ০–৪ বাদে)।', 'bhela-booking' ) );
		}
		if ( $occ > $max_cap ) {
			return new WP_Error( 'over_cabin', sprintf( __( 'একটি কেবিনে সর্বোচ্চ %d জন (শিশু ০–৪ বাদে)।', 'bhela-booking' ), $max_cap ) );
		}

		// Rate tier follows the ADULT count only — 4–8 children ride along at 50%
		// of this cabin's per-person rate without enlarging the tier. The smallest
		// cabin is a couple cabin, so a lone adult still books that tier.
		$min_cap = min( array_keys( bhela_bm_rates_by_occupancy() ) );
		$tier    = max( $adults, $min_cap );
		$row     = bhela_bm_rate_for_occupancy( $tier );
		$rate    = ( 'weekday' === $day_type ) ? (int) $row['weekday'] : (int) $row['regular'];
		$reg     = (int) $row['regular'];
		$line    = $adults * $rate + (int) ceil( $c48 * $rate * 0.5 );
		$line_rg = $adults * $reg + (int) ceil( $c48 * $reg * 0.5 );

		$total         += $line;
		$regular_total += $line_rg;
		$guests        += $occ; // paying only (0–4 infants free, not counted)
		$adults_total  += $adults;
		$c48_total     += $c48;

		$who = $adults . ' বড়';
		if ( $c48 ) {
			$who .= ' + ' . $c48 . ' শিশু(৪–৮)';
		}
		if ( $c04 ) {
			$who .= ' + ' . $c04 . ' শিশু(০–৪ ফ্রি)';
		}
		$lines[] = array(
			'label'  => sprintf( __( 'কেবিন (%d জন)', 'bhela-booking' ), $tier ),
			'who'    => $who,
			'total'  => $line,
			'rate'   => $rate,   // per-person rate for this cabin's tier (adult-based)
			'occ'    => $tier,   // cabin tier (adult-based); 4–8 children ride along
			'people' => $occ,    // actual bodies in the cabin (adults + 4–8 children)
			'adults' => $adults,
			'c48'    => $c48,
		);
	}

	if ( ! $lines ) {
		return new WP_Error( 'no_cabins', __( 'অন্তত একটি কেবিনে অতিথি সংখ্যা দিন।', 'bhela-booking' ) );
	}
	if ( $adults_total < 1 ) {
		return new WP_Error( 'need_adult', __( 'অন্তত ১ জন বড় (৯+) থাকতে হবে — শিশুরা একা ভ্রমণ করতে পারে না।', 'bhela-booking' ) );
	}
	if ( $guests < 2 ) {
		return new WP_Error( 'min_guests', __( 'অন্তত ২ জন প্রয়োজন — একা একজনের বুকিং সম্ভব নয়।', 'bhela-booking' ) );
	}
	if ( $guests > bhela_bm_max_guests() || count( $lines ) > bhela_bm_max_cabins() ) {
		return new WP_Error( 'over_capacity', sprintf( __( 'সর্বোচ্চ %1$d জন (%2$d টি কেবিন)। বড় গ্রুপের জন্য সরাসরি যোগাযোগ করুন।', 'bhela-booking' ), bhela_bm_max_guests(), bhela_bm_max_cabins() ) );
	}

	$advance = (int) ceil( $total * ( (float) $settings['advance_percent'] / 100 ) );

	return array(
		'day_type' => $day_type,
		'lines'    => $lines,
		'guests'   => $guests,
		'total'    => $total,
		'savings'  => max( 0, $regular_total - $total ),
		'advance'  => $advance,
		'due'      => $total - $advance,
	);
}

/* ---------- Submission processor ---------- */

function bhela_bm_process_submission( $data ) {
	$name      = sanitize_text_field( $data['name'] ?? '' );
	$phone     = sanitize_text_field( $data['phone'] ?? '' );
	$email     = sanitize_email( $data['email'] ?? '' );
	$date      = sanitize_text_field( $data['date'] ?? '' );
	$message   = sanitize_textarea_field( $data['message'] ?? '' );
	$cabins    = json_decode( wp_unslash( $data['cabins'] ?? '' ), true );
	$full_boat = ! empty( $data['full_boat'] );
	$requested = max( 0, (int) ( $data['requested_price'] ?? 0 ) );
	$disc_msg  = sanitize_textarea_field( $data['discount_msg'] ?? '' );

	if ( ! $name || ! $phone || ! $date ) {
		return new WP_Error( 'missing', __( 'অনুগ্রহ করে নাম, মোবাইল নম্বর ও তারিখ পূরণ করুন।', 'bhela-booking' ) );
	}

	// Travel date must be a real Y-m-d — it is stored, invoiced, and echoed.
	$d = DateTime::createFromFormat( 'Y-m-d', $date );
	if ( ! $d || $d->format( 'Y-m-d' ) !== $date ) {
		return new WP_Error( 'bad_date', __( 'ভ্রমণের তারিখ সঠিক ফরম্যাটে দিন।', 'bhela-booking' ) );
	}

	if ( $full_boat ) {
		// Custom-quote request for the whole boat — no per-cabin pricing.
		$price = array(
			'day_type' => bhela_bm_day_type( $date ),
			'lines'    => array(),
			'guests'   => bhela_bm_max_guests(),
			'total'    => 0,
			'savings'  => 0,
			'advance'  => 0,
			'due'      => 0,
		);
		$cabin_summary = sprintf( __( 'Full Boat — কাস্টম কোট (%1$d কেবিন / %2$d জন)', 'bhela-booking' ), bhela_bm_max_cabins(), bhela_bm_max_guests() );
	} else {
		$price = is_array( $cabins )
			? bhela_bm_calc_multi( $cabins, $date )
			: new WP_Error( 'no_cabins', __( 'অন্তত একটি কেবিনে অতিথি সংখ্যা দিন।', 'bhela-booking' ) );
		if ( is_wp_error( $price ) ) {
			return $price; // pass through the specific reason (no cabins / needs an adult)
		}
		// Inventory: never accept more cabins than are free on that date.
		$avail = bhela_bm_trip_availability( $date );
		if ( count( $price['lines'] ) > $avail['available'] ) {
			return new WP_Error( 'no_cabins_left', sprintf(
				/* translators: %d: free cabins. */
				__( 'দুঃখিত — এই তারিখে মাত্র %dটি কেবিন খালি আছে। অতিথি সংখ্যা কমান বা অন্য তারিখ বাছাই করুন।', 'bhela-booking' ),
				$avail['available']
			) );
		}

		$summary_parts = array();
		foreach ( $price['lines'] as $l ) {
			$summary_parts[] = $l['label'] . ' (' . $l['who'] . ')';
		}
		$cabin_summary = implode( ' + ', $summary_parts );
	}

	$post_id = wp_insert_post( array(
		'post_title'  => $name,
		'post_type'   => 'bhela_booking',
		'post_status' => 'publish',
	), true );
	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'save', __( 'দুঃখিত, তথ্য সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।', 'bhela-booking' ) );
	}

	$invoice_no = bhela_bm_next_invoice_number();

	update_post_meta( $post_id, '_bhela_phone', $phone );
	update_post_meta( $post_id, '_bhela_email', $email );
	update_post_meta( $post_id, '_bhela_travel_date', $date );
	update_post_meta( $post_id, '_bhela_cabin_type', $cabin_summary );
	update_post_meta( $post_id, '_bhela_cabins_json', wp_json_encode( is_array( $cabins ) ? $cabins : array(), JSON_UNESCAPED_UNICODE ) );
	update_post_meta( $post_id, '_bhela_lines', wp_json_encode( $price['lines'], JSON_UNESCAPED_UNICODE ) );
	update_post_meta( $post_id, '_bhela_guests', $price['guests'] );
	update_post_meta( $post_id, '_bhela_message', $message );
	update_post_meta( $post_id, '_bhela_status', 'pending' );
	update_post_meta( $post_id, '_bhela_invoice_no', $invoice_no );
	update_post_meta( $post_id, '_bhela_paid_amount', 0 );
	update_post_meta( $post_id, '_bhela_day_type', $price['day_type'] );
	update_post_meta( $post_id, '_bhela_per_person', 0 );
	update_post_meta( $post_id, '_bhela_total', $price['total'] );
	update_post_meta( $post_id, '_bhela_base_price', $price['total'] ); // pre-discount reference
	update_post_meta( $post_id, '_bhela_advance', $price['advance'] );
	update_post_meta( $post_id, '_bhela_manual_price', '1' ); // preserve multi-cabin total on admin save
	update_post_meta( $post_id, '_bhela_full_boat', $full_boat ? '1' : '' );
	if ( $requested > 0 ) {
		update_post_meta( $post_id, '_bhela_requested_price', $requested );
	}
	if ( $disc_msg ) {
		update_post_meta( $post_id, '_bhela_discount_msg', $disc_msg );
	}

	bhela_bm_email_admin_new( $post_id );
	if ( $email ) {
		bhela_bm_email_customer( $post_id, 'request' );
	}
	if ( function_exists( 'bhela_bm_sms_on_new_booking' ) ) {
		bhela_bm_sms_on_new_booking( $post_id );
	}

	$settings = bhela_bm_get_settings();
	$wa_num   = preg_replace( '/[^0-9]/', '', $settings['whatsapp'] );
	if ( $full_boat ) {
		$wa_text = sprintf(
			"আসসালামু আলাইকুম। আমি পুরো বোট (Full Boat) রিজার্ভ করতে চাই — কাস্টম কোট প্রয়োজন।\n\n🧾 Booking No: %s\nনাম: %s\nমোবাইল: %s\nতারিখ: %s",
			$invoice_no, $name, $phone, $date
		);
	} else {
		$wa_text = sprintf(
			"আসসালামু আলাইকুম। আমি ভেলা হাউসবোট বুকিং করতে চাই।\n\n🧾 Booking No: %s\nনাম: %s\nমোবাইল: %s\nতারিখ: %s\nকেবিন: %s\nঅতিথি: %d জন\nমোট: %s | অগ্রিম (৫০%%): %s",
			$invoice_no, $name, $phone, $date, $cabin_summary, $price['guests'],
			bhela_bm_money( $price['total'] ), bhela_bm_money( $price['advance'] )
		);
	}
	if ( $requested > 0 ) {
		$wa_text .= sprintf( "\n💬 প্রস্তাবিত বাজেট: %s", bhela_bm_money( $requested ) );
	}

	return array(
		'booking_id'   => $post_id,
		'invoice_no'   => $invoice_no,
		'whatsapp_url' => 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( $wa_text ),
		'invoice_url'  => bhela_bm_invoice_url( $post_id ),
	);
}

/** AJAX: submit booking. */
function bhela_bm_ajax_submit() {
	check_ajax_referer( 'bhela_bm_booking', 'nonce' );

	// Honeypot: bots fill every field. Real users never see bhela_bm_hp.
	if ( ! empty( $_POST['bhela_bm_hp'] ) ) {
		wp_send_json_error( array( 'message' => __( 'দুঃখিত, রিকোয়েস্টটি গ্রহণ করা যায়নি।', 'bhela-booking' ) ) );
	}

	// Per-IP throttle: each submit publishes a post and sends up to 2 emails.
	$ip   = preg_replace( '/[^0-9a-fA-F:.]/', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key  = 'bhela_bm_submit_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 10 ) {
		wp_send_json_error( array( 'message' => __( 'অনেকবার চেষ্টা হয়েছে — কিছুক্ষণ পর আবার চেষ্টা করুন।', 'bhela-booking' ) ) );
	}
	set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

	$result = bhela_bm_process_submission( wp_unslash( $_POST ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	$msg = sprintf(
		__( 'ধন্যবাদ! আপনার বুকিং রিকোয়েস্ট (No: %s) জমা হয়েছে। আমাদের টিম দ্রুত যোগাযোগ করবে।', 'bhela-booking' ),
		$result['invoice_no']
	);
	wp_send_json_success( array_merge( $result, array( 'message' => $msg ) ) );
}
add_action( 'wp_ajax_bhela_bm_submit', 'bhela_bm_ajax_submit' );
add_action( 'wp_ajax_nopriv_bhela_bm_submit', 'bhela_bm_ajax_submit' );

/** AJAX: room availability check (from Trip Calendar). */
function bhela_bm_ajax_availability() {
	check_ajax_referer( 'bhela_bm_booking', 'nonce' );
	$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
	if ( ! $date ) {
		wp_send_json_error( array( 'message' => 'আগে তারিখ বাছাই করুন।' ) );
	}
	$statuses = function_exists( 'bhela_bm_trip_statuses' ) ? bhela_bm_trip_statuses() : array();
	$avail    = bhela_bm_trip_availability( $date );
	if ( $avail['trip'] ) {
		$st = $statuses[ $avail['status'] ] ?? null;
		wp_send_json_success( array(
			'status'    => $avail['status'],
			'label'     => $st ? $st['short'] : $avail['status'],
			'color'     => $st ? $st['color'] : '#1a7f37',
			'trip'      => $avail['trip']['label'],
			'note'      => $avail['trip']['note'],
			'total'     => $avail['total'],
			'booked'    => $avail['booked'],
			'available' => $avail['available'],
		) );
	}
	wp_send_json_success( array(
		'status'    => 'unknown',
		'label'     => 'এই তারিখে নির্ধারিত গ্রুপ ট্রিপ নেই',
		'color'     => '#996800',
		'trip'      => '',
		'note'      => 'Full Boat/কাস্টম ট্রিপের জন্য WhatsApp-এ যোগাযোগ করুন — রিকোয়েস্ট পাঠালে আমরা কনফার্ম করব।',
		'total'     => $avail['total'],
		'booked'    => 0,
		'available' => $avail['total'],
	) );
}
add_action( 'wp_ajax_bhela_bm_availability', 'bhela_bm_ajax_availability' );
add_action( 'wp_ajax_nopriv_bhela_bm_availability', 'bhela_bm_ajax_availability' );

/* ---------- Booking tracking ---------- */

/** Mask a customer name for public status results (e.g. "রাকিব হাসান" → "রা••• ন"). */
function bhela_bm_mask_name( $name ) {
	$name = trim( wp_strip_all_tags( (string) $name ) );
	if ( '' === $name ) {
		return '—';
	}
	$len = mb_strlen( $name );
	if ( $len <= 2 ) {
		return mb_substr( $name, 0, 1 ) . '•';
	}
	return mb_substr( $name, 0, 2 ) . '•••' . mb_substr( $name, -1 );
}

/**
 * Find bookings by phone or email (exact match on the trimmed input).
 * Lookup by the sequential invoice number is deliberately NOT supported —
 * those numbers are guessable (BH-2026-0001…) and would allow enumeration of
 * every customer's booking. Phone/email are higher-entropy and known only to
 * the customer. Returns up to 5 booking IDs, newest first.
 */
function bhela_bm_find_bookings( $q ) {
	$q = trim( (string) $q );
	if ( mb_strlen( $q ) < 4 ) {
		return array();
	}
	$query = new WP_Query( array(
		'post_type'      => 'bhela_booking',
		'post_status'    => 'publish',
		'posts_per_page' => 5,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => '_bhela_phone', 'value' => $q, 'compare' => '=' ),
			array( 'key' => '_bhela_email', 'value' => $q, 'compare' => '=' ),
		),
	) );
	return $query->posts;
}

/** Public, privacy-safe status summary for a booking. No PII, no secret link. */
function bhela_bm_track_payload( $booking_id ) {
	$m = function ( $k ) use ( $booking_id ) {
		return get_post_meta( $booking_id, $k, true );
	};
	$status   = $m( '_bhela_status' ) ? $m( '_bhela_status' ) : 'pending';
	$statuses = bhela_bm_statuses();
	$total    = (int) $m( '_bhela_total' );
	$paid     = (int) $m( '_bhela_paid_amount' );

	return array(
		'invoice_no'   => (string) $m( '_bhela_invoice_no' ),
		'name'         => bhela_bm_mask_name( get_the_title( $booking_id ) ),
		'travel_date'  => (string) $m( '_bhela_travel_date' ),
		'cabin'        => (string) $m( '_bhela_cabin_type' ),
		'guests'       => (int) $m( '_bhela_guests' ),
		'total'        => $total,
		'advance'      => (int) $m( '_bhela_advance' ),
		'paid'         => $paid,
		'due'          => max( 0, $total - $paid ),
		'status_key'   => $status,
		'status_label' => isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status,
		'status_color' => bhela_bm_status_color( $status ),
	);
}

/** AJAX: track booking(s) by phone / email / invoice number. */
function bhela_bm_ajax_track() {
	check_ajax_referer( 'bhela_bm_booking', 'nonce' );
	$q = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
	if ( mb_strlen( trim( $q ) ) < 4 ) {
		wp_send_json_error( array( 'message' => __( 'মোবাইল নম্বর বা ইমেইল সঠিকভাবে দিন।', 'bhela-booking' ) ) );
	}

	// Per-IP throttle on FAILED lookups only. Lookup is by phone/email (high
	// entropy, not the guessable invoice number), so this just blunts blind
	// guessing/abuse. A customer re-checking their own real booking always
	// succeeds and is never counted — important behind shared/CGNAT IPs.
	$ip  = preg_replace( '/[^0-9a-fA-F:.]/', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key = 'bhela_bm_track_' . md5( $ip );
	if ( (int) get_transient( $key ) >= 30 ) {
		wp_send_json_error( array( 'message' => __( 'অনেকবার চেষ্টা হয়েছে — কিছুক্ষণ পর আবার চেষ্টা করুন।', 'bhela-booking' ) ) );
	}

	$ids = bhela_bm_find_bookings( $q );
	if ( ! $ids ) {
		// Count only misses toward the limit.
		set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
		$settings = bhela_bm_get_settings();
		wp_send_json_success( array(
			'found'    => false,
			'message'  => __( 'এই তথ্যে কোনো বুকিং পাওয়া যায়নি। মোবাইল নম্বর/ইমেইল যাচাই করুন অথবা WhatsApp-এ যোগাযোগ করুন।', 'bhela-booking' ),
			'whatsapp' => preg_replace( '/[^0-9]/', '', $settings['whatsapp'] ),
		) );
	}

	$bookings = array_map( 'bhela_bm_track_payload', $ids );
	wp_send_json_success( array( 'found' => true, 'bookings' => $bookings ) );
}
add_action( 'wp_ajax_bhela_bm_track', 'bhela_bm_ajax_track' );
add_action( 'wp_ajax_nopriv_bhela_bm_track', 'bhela_bm_ajax_track' );

/** Inner markup for the tracking UI — shared by the form tab and the standalone shortcode. */
function bhela_bm_track_panel_html() {
	ob_start();
	?>
	<div class="bm-track">
		<p class="bm-track__lead">বুকিংয়ে দেওয়া মোবাইল নম্বর বা ইমেইল দিয়ে আপনার বুকিং-এর সর্বশেষ অবস্থা দেখুন।</p>
		<div class="bm-track__form">
			<input type="text" id="bm-track-q" placeholder="01XXXXXXXXX / you@email.com" autocomplete="off">
			<button type="button" class="bm-track__btn" id="bm-track-btn">🔍 ট্র্যাক করুন</button>
		</div>
		<div class="bm-track__result" id="bm-track-result" role="status" aria-live="polite"></div>
	</div>
	<?php
	return ob_get_clean();
}

/* ---------- Shortcode: [bhela_booking_track] ---------- */

function bhela_bm_booking_track_shortcode() {
	wp_enqueue_style( 'bhela-bm-booking' );
	wp_enqueue_script( 'bhela-bm-booking' );
	return '<div class="bhela-bm-form-wrap bhela-bm-track-wrap" id="bhela-booking-track">' . bhela_bm_track_panel_html() . '</div>';
}
add_shortcode( 'bhela_booking_track', 'bhela_bm_booking_track_shortcode' );
