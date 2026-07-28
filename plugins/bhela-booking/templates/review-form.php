<?php
/**
 * Guest review form, opened from the private link sent after a completed trip.
 *
 * Rendered standalone (like the invoice) rather than inside the theme, so the
 * page stays fast and focused on the one thing we are asking for.
 *
 * Expects: $booking_id, $settings, $limits, $existing, $submitted, $error,
 *          $guest_name, $invoice_no
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bhela_done = $submitted || $existing;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( sprintf( '%s — %s', __( 'Share your experience', 'bhela-booking' ), $settings['business_name'] ) ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
	<style>
	*, *::before, *::after { box-sizing:border-box; }
	body { margin:0; background:#EEF2F1; color:#0A2A2F; font-family:'Hind Siliguri','Segoe UI',Tahoma,sans-serif; line-height:1.7; padding:24px 12px; }
	.rv { max-width:640px; margin:0 auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 6px 30px rgba(10,42,47,.08); }
	.rv__head { background:#0A2A2F; color:#fff; padding:28px 32px; text-align:center; }
	.rv__head .brand { font-size:24px; font-weight:700; letter-spacing:1px; }
	.rv__head .sub { font-size:12px; color:#6FC7BF; letter-spacing:2px; text-transform:uppercase; margin-top:4px; }
	.rv__body { padding:28px 32px 32px; }
	.rv h1 { font-size:21px; margin:0 0 8px; }
	.rv p.lead { margin:0 0 22px; color:#5E7472; font-size:14.5px; }
	.rv label.f { display:block; font-weight:600; margin:18px 0 6px; font-size:14px; }
	.rv input[type=text], .rv textarea { width:100%; padding:11px 13px; border:1px solid #cdd9d8; border-radius:9px; font:inherit; background:#fff; color:inherit; }
	.rv textarea { min-height:130px; resize:vertical; }
	.rv input[type=text]:focus, .rv textarea:focus { outline:2px solid #137A74; outline-offset:1px; border-color:#137A74; }
	.rv .hint { font-size:12.5px; color:#7c8b8a; margin:5px 0 0; }
	/* Star rating — radio inputs reversed so CSS can highlight up to the checked one. */
	.stars { display:inline-flex; flex-direction:row-reverse; gap:4px; }
	.stars input { position:absolute; opacity:0; width:0; height:0; }
	.stars label { font-size:34px; line-height:1; color:#d8dedd; cursor:pointer; transition:color .12s; }
	.stars input:checked ~ label, .stars label:hover, .stars label:hover ~ label { color:#E5A400; }
	.stars input:focus-visible + label { outline:2px solid #137A74; outline-offset:2px; border-radius:4px; }
	.rv .btn { display:block; width:100%; margin-top:24px; background:#E5601F; color:#fff; border:0; border-radius:10px; padding:15px; font:inherit; font-size:16px; font-weight:700; cursor:pointer; }
	.rv .btn:hover { background:#c94f14; }
	.rv .files { border:1.5px dashed #cdd9d8; border-radius:10px; padding:14px; background:#FBFDFC; }
	.rv .files input { font-size:13.5px; max-width:100%; }
	.rv .note { margin-top:22px; padding:12px 14px; background:#FBF8F2; border-radius:10px; font-size:13px; color:#5E7472; }
	.rv .err { margin:0 0 18px; padding:12px 14px; background:#FDECEA; border:1px solid #F5B7B1; border-radius:10px; color:#922B21; font-size:14px; }
	.rv__done { text-align:center; padding:42px 32px; }
	.rv__done .tick { font-size:52px; line-height:1; }
	.rv__done h1 { margin:14px 0 8px; }
	.rv__foot { background:#0B2E33; color:#cfe3e2; text-align:center; padding:16px; font-size:12.5px; }
	.rv__foot a { color:#8fd6cf; text-decoration:none; }
	.rv__foot a:hover { text-decoration:underline; }
	.rv__by { margin-top:6px; font-size:11.5px; color:#7fa6a4; }
	@media (max-width:520px) { .rv__body { padding:22px 20px 26px; } .rv__head { padding:22px; } }
	</style>
</head>
<body>
	<div class="rv">
		<div class="rv__head">
			<div class="brand">🛶 <?php echo esc_html( $settings['business_name'] ); ?></div>
			<div class="sub"><?php echo esc_html( $settings['business_tagline'] ); ?></div>
		</div>

		<?php if ( $bhela_done ) : ?>
			<div class="rv__done">
				<div class="tick">🙏</div>
				<h1><?php esc_html_e( 'Thank you!', 'bhela-booking' ); ?></h1>
				<p class="lead"><?php esc_html_e( 'আপনার রিভিউ আমরা পেয়েছি। যাচাই করে খুব শীঘ্রই ওয়েবসাইটে প্রকাশ করা হবে।', 'bhela-booking' ); ?></p>
				<p class="lead" style="margin:0"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ওয়েবসাইটে ফিরে যান →', 'bhela-booking' ); ?></a></p>
			</div>
		<?php else : ?>
			<div class="rv__body">
				<h1><?php esc_html_e( 'আপনার অভিজ্ঞতা জানান', 'bhela-booking' ); ?></h1>
				<p class="lead">
					<?php echo esc_html( sprintf(
						/* translators: 1: guest name, 2: invoice number */
						__( 'প্রিয় %1$s, ভেলার সাথে ভ্রমণের জন্য ধন্যবাদ (বুকিং %2$s)। আপনার মতামত পরের অতিথিদের অনেক সাহায্য করবে।', 'bhela-booking' ),
						$guest_name, $invoice_no
					) ); ?>
				</p>

				<?php if ( 'empty' === $error ) : ?>
					<p class="err"><?php esc_html_e( 'অনুগ্রহ করে কিছু লিখুন — রিভিউ খালি রাখা যাবে না।', 'bhela-booking' ); ?></p>
				<?php elseif ( $error ) : ?>
					<p class="err"><?php esc_html_e( 'দুঃখিত, রিভিউটি সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।', 'bhela-booking' ); ?></p>
				<?php endif; ?>

				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bhela_bm_review_submit">
					<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">
					<input type="hidden" name="key" value="<?php echo esc_attr( bhela_bm_review_key( $booking_id ) ); ?>">
					<?php wp_nonce_field( 'bhela_bm_review_submit' ); ?>

					<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden">
						<label>Leave this field empty<input type="text" name="bhela_bm_hp" tabindex="-1" autocomplete="off"></label>
					</div>

					<label class="f" id="rating-label"><?php esc_html_e( 'ট্রিপটি কেমন ছিল?', 'bhela-booking' ); ?></label>
					<div class="stars" role="radiogroup" aria-labelledby="rating-label">
						<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
							<input type="radio" name="rating" id="star<?php echo esc_attr( $i ); ?>" value="<?php echo esc_attr( $i ); ?>" <?php checked( 5, $i ); ?>>
							<label for="star<?php echo esc_attr( $i ); ?>" title="<?php echo esc_attr( sprintf( _n( '%d star', '%d stars', $i, 'bhela-booking' ), $i ) ); ?>">★</label>
						<?php endfor; ?>
					</div>

					<label class="f" for="review_text"><?php esc_html_e( 'আপনার মতামত', 'bhela-booking' ); ?></label>
					<textarea id="review_text" name="review_text" required placeholder="<?php esc_attr_e( 'কেবিন, খাবার, ক্রু, হাওরের দৃশ্য — যা মনে ধরেছে লিখুন...', 'bhela-booking' ); ?>"></textarea>

					<label class="f" for="guest_name"><?php esc_html_e( 'আপনার নাম', 'bhela-booking' ); ?></label>
					<input type="text" id="guest_name" name="guest_name" value="<?php echo esc_attr( $guest_name ); ?>" maxlength="80">

					<label class="f" for="subtitle"><?php esc_html_e( 'ট্রিপের ধরন (ঐচ্ছিক)', 'bhela-booking' ); ?></label>
					<input type="text" id="subtitle" name="subtitle" maxlength="60" placeholder="<?php esc_attr_e( 'যেমন: Family Trip · Dhaka', 'bhela-booking' ); ?>">

					<?php if ( $limits['photos'] > 0 ) : ?>
						<label class="f" for="review_photos"><?php esc_html_e( 'ট্রিপের ছবি (ঐচ্ছিক)', 'bhela-booking' ); ?></label>
						<div class="files">
							<input type="file" id="review_photos" name="review_photos[]" accept="image/jpeg,image/png,image/webp" multiple>
							<p class="hint">
								<?php
								// Bengali copy gets Bengali numerals, as everywhere else guest-facing.
								$bhela_n = function ( $v ) {
									return function_exists( 'bhela_bm_bn_num' ) ? bhela_bm_bn_num( $v ) : $v;
								};
								echo esc_html( sprintf(
									/* translators: 1: max number of photos, 2: max megabytes each */
									__( 'সর্বোচ্চ %1$sটি ছবি, প্রতিটি %2$s MB পর্যন্ত। JPEG, PNG বা WebP।', 'bhela-booking' ),
									$bhela_n( $limits['photos'] ), $bhela_n( (int) ( $limits['bytes'] / MB_IN_BYTES ) )
								) ); ?>
							</p>
						</div>
					<?php endif; ?>

					<button type="submit" class="btn"><?php esc_html_e( 'রিভিউ পাঠান', 'bhela-booking' ); ?></button>
					<p class="note"><?php esc_html_e( 'আপনার রিভিউ যাচাইয়ের পর ওয়েবসাইটে প্রকাশ করা হবে। মোবাইল নম্বর বা ইমেইল কখনো প্রকাশ করা হয় না।', 'bhela-booking' ); ?></p>
				</form>
			</div>
		<?php endif; ?>

		<div class="rv__foot">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">🌐 <?php echo esc_html( preg_replace( '#^www\.#', '', (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ); ?></a>
			<?php if ( ! empty( $settings['ops_manager'] ) ) : ?>
				&nbsp;·&nbsp; <?php esc_html_e( 'Operation Manager:', 'bhela-booking' ); ?> <?php echo esc_html( $settings['ops_manager'] ); ?>
			<?php endif; ?>
			<div class="rv__by">
				<?php
				printf(
					/* translators: %s: linked developer name */
					esc_html__( 'Designed &amp; developed by %s', 'bhela-booking' ),
					'<a href="https://3s-soft.com" target="_blank" rel="noopener">3s-Soft</a>'
				);
				?>
			</div>
		</div>
	</div>
</body>
</html>
