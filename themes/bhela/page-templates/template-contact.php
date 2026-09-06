<?php
/**
 * Template Name: BHELA — Contact
 *
 * @package Bhela
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$bhela_wa_num  = preg_replace( '/[^0-9]/', '', bhela_contact( 'whatsapp' ) );
$bhela_msgr    = trim( (string) bhela_contact( 'messenger' ) );
$bhela_phone_1 = bhela_contact( 'phone_1' );
$bhela_phone_2 = bhela_contact( 'phone_2' );
$bhela_email   = bhela_contact( 'email' );
$bhela_address = bhela_contact( 'address' );

// The branches that take bookings in person, owner-editable in Settings -> Business.
// Guarded because the theme has to render with the plugin switched off, and empty
// because an owner who has removed every office should get no section at all rather
// than a heading with nothing under it.
$bhela_offices = function_exists( 'bhela_bm_offices' ) ? bhela_bm_offices() : array();
?>

<section class="page-hero">
	<div class="container">
		<span class="eyebrow"><?php esc_html_e( 'যোগাযোগ · Contact', 'bhela' ); ?></span>
		<h1><?php esc_html_e( 'আমাদের সাথে কথা বলুন', 'bhela' ); ?></h1>
		<p class="section-lead"><?php esc_html_e( 'বুকিং, গ্রুপ ট্রিপ, কাস্টম প্যাকেজ বা যেকোনো প্রশ্ন — যেভাবে সুবিধা সেভাবেই যোগাযোগ করুন। ফোন ও WhatsApp-এ দ্রুততম উত্তর পাবেন।', 'bhela' ); ?></p>
	</div>
</section>

<section class="section">
	<div class="container">

		<!-- Quick channels -->
		<div class="contact-channels">
			<a class="contact-card contact-card--call" href="tel:<?php echo esc_attr( $bhela_phone_1 ); ?>">
				<span class="contact-card__icon">📞</span>
				<strong><?php esc_html_e( 'ফোন করুন', 'bhela' ); ?></strong>
				<span class="contact-card__value"><?php echo esc_html( $bhela_phone_1 ); ?></span>
				<?php if ( $bhela_phone_2 ) : ?>
					<span class="contact-card__value"><?php echo esc_html( $bhela_phone_2 ); ?></span>
				<?php endif; ?>
				<em><?php esc_html_e( 'সকাল ৯টা – রাত ১০টা', 'bhela' ); ?></em>
			</a>

			<?php if ( $bhela_wa_num ) : ?>
				<a class="contact-card contact-card--wa" href="<?php echo esc_url( bhela_wa_link() ); ?>" target="_blank" rel="noopener">
					<span class="contact-card__icon">💬</span>
					<strong>WhatsApp</strong>
					<span class="contact-card__value"><?php echo esc_html( bhela_contact( 'whatsapp' ) ); ?></span>
					<em><?php esc_html_e( 'সবচেয়ে দ্রুত উত্তর', 'bhela' ); ?></em>
				</a>
			<?php endif; ?>

			<?php if ( $bhela_msgr ) : ?>
				<a class="contact-card contact-card--msgr" href="<?php echo esc_url( $bhela_msgr ); ?>" target="_blank" rel="noopener">
					<span class="contact-card__icon">📨</span>
					<strong>Messenger</strong>
					<span class="contact-card__value"><?php esc_html_e( 'Facebook-এ মেসেজ', 'bhela' ); ?></span>
					<em><?php esc_html_e( 'চ্যাটে কথা বলুন', 'bhela' ); ?></em>
				</a>
			<?php endif; ?>

			<a class="contact-card contact-card--mail" href="mailto:<?php echo esc_attr( $bhela_email ); ?>">
				<span class="contact-card__icon">✉️</span>
				<strong><?php esc_html_e( 'ইমেইল', 'bhela' ); ?></strong>
				<span class="contact-card__value"><?php echo esc_html( $bhela_email ); ?></span>
				<em><?php esc_html_e( 'বিস্তারিত প্রশ্নের জন্য', 'bhela' ); ?></em>
			</a>
		</div>

		<!-- Form + side info -->
		<div class="contact-layout">
			<div class="contact-formbox">
				<h2 class="section-title"><?php esc_html_e( 'বার্তা পাঠান', 'bhela' ); ?></h2>
				<p class="section-lead"><?php esc_html_e( 'ফর্মটি পূরণ করুন — আমরা ফোন, WhatsApp বা ইমেইলে ফিরে যোগাযোগ করব।', 'bhela' ); ?></p>
				<?php echo do_shortcode( '[bhela_contact_form]' ); ?>
			</div>

			<aside class="contact-side">
				<div class="contact-side__box">
					<h3><?php esc_html_e( '📍 আমাদের ঠিকানা', 'bhela' ); ?></h3>
					<p><?php echo esc_html( $bhela_address ); ?></p>
					<p class="muted"><?php esc_html_e( 'বোট ছাড়ার ঘাট — ট্রিপের আগে সঠিক লোকেশন WhatsApp-এ পাঠানো হয়।', 'bhela' ); ?></p>
				</div>

				<div class="contact-side__box">
					<h3><?php esc_html_e( '🕘 যোগাযোগের সময়', 'bhela' ); ?></h3>
					<ul class="contact-hours">
						<li><span><?php esc_html_e( 'শনি – বৃহস্পতি', 'bhela' ); ?></span><strong><?php esc_html_e( 'সকাল ৯টা – রাত ১০টা', 'bhela' ); ?></strong></li>
						<li><span><?php esc_html_e( 'শুক্রবার', 'bhela' ); ?></span><strong><?php esc_html_e( 'বিকাল ৩টা – রাত ১০টা', 'bhela' ); ?></strong></li>
					</ul>
					<p class="muted"><?php esc_html_e( 'ট্রিপ চলাকালীন উত্তর দিতে একটু দেরি হতে পারে।', 'bhela' ); ?></p>
				</div>

				<?php if ( bhela_social_links() ) : ?>
					<div class="contact-side__box contact-side__box--social">
						<h3><?php esc_html_e( '🌐 সোশ্যাল মিডিয়া', 'bhela' ); ?></h3>
						<?php bhela_social_icons( 'social-icons social-icons--dark' ); ?>
					</div>
				<?php endif; ?>

				<div class="contact-side__box contact-side__box--cta">
					<h3><?php esc_html_e( 'এখনই বুক করবেন?', 'bhela' ); ?></h3>
					<p><?php esc_html_e( '২ মিনিটে তারিখ দেখে বুকিং রিকোয়েস্ট পাঠান।', 'bhela' ); ?></p>
					<a class="btn btn--cta" href="<?php echo esc_url( bhela_page_url( 'book-now' ) ); ?>"><?php esc_html_e( 'বুক করুন', 'bhela' ); ?> →</a>
				</div>
			</aside>
		</div>

		<?php if ( $bhela_offices ) : ?>
			<div class="offices">
				<h2 class="section-title"><?php esc_html_e( 'আমাদের অফিস', 'bhela' ); ?></h2>
				<p class="section-lead"><?php esc_html_e( 'সরাসরি এসে কথা বলতে চাইলে — নিচের যেকোনো অফিসে আসুন, বা সেখানকার দায়িত্বে যিনি আছেন তাঁকে ফোন করুন।', 'bhela' ); ?></p>
				<div class="office-grid">
					<?php foreach ( $bhela_offices as $bhela_office ) : ?>
						<?php $bhela_office_tel = bhela_bm_office_tel( $bhela_office['mobile'] ); ?>
						<div class="office-card">
							<h3 class="office-card__name">📍 <?php echo esc_html( $bhela_office['name'] ); ?></h3>
							<?php if ( $bhela_office['address'] ) : ?>
								<p class="office-card__address">
									<?php
									// Escape first, then introduce the breaks — the address is a
									// textarea the owner typed, and its line breaks are meaningful.
									echo nl2br( esc_html( $bhela_office['address'] ) );
									?>
								</p>
							<?php endif; ?>
							<?php if ( $bhela_office['contact_person'] ) : ?>
								<p class="office-card__person">
									<span><?php esc_html_e( 'যোগাযোগ', 'bhela' ); ?></span>
									<strong><?php echo esc_html( $bhela_office['contact_person'] ); ?></strong>
								</p>
							<?php endif; ?>
							<?php if ( $bhela_office_tel ) : ?>
								<a class="office-card__phone" href="tel:<?php echo esc_attr( $bhela_office_tel ); ?>">
									📱 <?php echo esc_html( $bhela_office['mobile'] ); ?>
								</a>
							<?php elseif ( $bhela_office['mobile'] ) : ?>
								<span class="office-card__phone"><?php echo esc_html( $bhela_office['mobile'] ); ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

	</div>
</section>

<?php get_footer(); ?>
