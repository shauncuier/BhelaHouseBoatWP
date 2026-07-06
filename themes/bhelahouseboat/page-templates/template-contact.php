<?php
/**
 * Template Name: Contact
 * Template Post Type: page
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">

    <div class="page-hero">
        <div class="container">
            <h1>যোগাযোগ</h1>
            <p>বুকিং, তথ্য বা যেকোনো প্রশ্নে সরাসরি যোগাযোগ করুন</p>
        </div>
    </div>

    <section class="section">
        <div class="container">
            <div class="contact-grid">
                <!-- Contact Info -->
                <div class="contact-info reveal">
                    <div class="contact-card">
                        <div class="contact-card__icon">📱</div>
                        <div>
                            <h4 class="contact-card__title">ফোন</h4>
                            <div class="contact-card__detail">
                                <a href="<?php echo esc_url( bhela_phone_link( '01891562461' ) ); ?>">01891-562461</a><br>
                                <a href="<?php echo esc_url( bhela_phone_link( '01614182769' ) ); ?>">01614-182769</a>
                            </div>
                        </div>
                    </div>

                    <div class="contact-card">
                        <div class="contact-card__icon">💬</div>
                        <div>
                            <h4 class="contact-card__title">WhatsApp</h4>
                            <div class="contact-card__detail">
                                <a href="<?php echo esc_url( bhela_whatsapp_link() ); ?>" target="_blank" rel="noopener">+8801793395556</a><br>
                                <small>সবচেয়ে দ্রুত রেসপন্স — ২ মিনিটে!</small>
                            </div>
                        </div>
                    </div>

                    <div class="contact-card">
                        <div class="contact-card__icon">📧</div>
                        <div>
                            <h4 class="contact-card__title">ইমেইল</h4>
                            <div class="contact-card__detail">
                                <a href="mailto:infobhela@gmail.com">infobhela@gmail.com</a>
                            </div>
                        </div>
                    </div>

                    <div class="contact-card">
                        <div class="contact-card__icon">📍</div>
                        <div>
                            <h4 class="contact-card__title">অবস্থান</h4>
                            <div class="contact-card__detail">
                                তাহিরপুর, সুনামগঞ্জ<br>
                                <small>টাঙ্গুয়ার হাওর, সিলেট বিভাগ</small>
                            </div>
                        </div>
                    </div>

                    <!-- Quick WhatsApp -->
                    <a href="<?php echo esc_url( bhela_whatsapp_link() ); ?>" class="btn btn--whatsapp btn--lg btn--full" target="_blank" rel="noopener">
                        <span class="btn__icon">💬</span> WhatsApp এ মেসেজ করুন — দ্রুত রেসপন্স!
                    </a>
                </div>

                <!-- Booking Inquiry Form -->
                <div class="contact-form reveal reveal--delay-2">
                    <h3 class="contact-form__title">📝 বুকিং ইনকোয়ারি</h3>
                    <p style="color: var(--color-text-muted); margin-bottom: var(--space-xl); font-size: var(--font-size-sm);">
                        ফর্ম পূরণ করুন — আমরা WhatsApp/ফোনে যোগাযোগ করব
                    </p>

                    <div id="contact-form-response" style="margin-bottom: var(--space-md);"></div>

                    <form id="contact-form" action="#" method="post">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact-name">আপনার নাম *</label>
                                <input type="text" id="contact-name" name="name" required placeholder="সম্পূর্ণ নাম">
                            </div>
                            <div class="form-group">
                                <label for="contact-phone">ফোন নম্বর *</label>
                                <input type="tel" id="contact-phone" name="phone" required placeholder="01XXXXXXXXX">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact-guests">অতিথি সংখ্যা</label>
                                <select id="contact-guests" name="guests">
                                    <option value="">নির্বাচন করুন</option>
                                    <?php for ( $i = 2; $i <= 40; $i++ ) : ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> জন</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="contact-date">পছন্দের তারিখ</label>
                                <input type="date" id="contact-date" name="date">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="contact-cabin">পছন্দের কেবিন</label>
                            <select id="contact-cabin" name="cabin">
                                <option value="">নির্বাচন করুন</option>
                                <option value="budget">Budget Friendly (৬ জন)</option>
                                <option value="comfort">Comfort Adjustment (৫ জন)</option>
                                <option value="deluxe">Double Deluxe (৪ জন)</option>
                                <option value="luxury">Luxury Triple (৩ জন)</option>
                                <option value="couple">Exclusive Couple (২ জন)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="contact-message">বিস্তারিত / প্রশ্ন</label>
                            <textarea id="contact-message" name="message" placeholder="আপনার প্রশ্ন বা বিশেষ কোনো অনুরোধ..."></textarea>
                        </div>

                        <button type="submit" class="btn btn--primary btn--lg btn--full" id="contact-submit-btn">
                            📨 ইনকোয়ারি পাঠান
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

</main>

<?php get_footer(); ?>
