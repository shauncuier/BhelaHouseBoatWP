/**
 * BHELA Houseboat — Main JavaScript
 * Navigation, scroll effects, mobile menu
 *
 * @package BhelaHouseboat
 */

document.addEventListener('DOMContentLoaded', function () {

    // =============================================
    // HEADER SCROLL EFFECT
    // =============================================
    const header = document.getElementById('site-header');

    if (header) {
        let lastScroll = 0;

        window.addEventListener('scroll', function () {
            const currentScroll = window.pageYOffset;

            if (currentScroll > 80) {
                header.classList.add('site-header--scrolled');
                header.classList.remove('site-header--transparent');
            } else {
                if (document.body.classList.contains('is-front-page')) {
                    header.classList.remove('site-header--scrolled');
                    header.classList.add('site-header--transparent');
                }
            }

            lastScroll = currentScroll;
        }, { passive: true });
    }

    // =============================================
    // MOBILE NAVIGATION
    // =============================================
    const navToggle = document.getElementById('nav-toggle');
    const navMobile = document.getElementById('nav-mobile');
    const navOverlay = document.getElementById('nav-overlay');

    function openMobileNav() {
        navToggle.classList.add('active');
        navMobile.classList.add('open');
        navOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        navToggle.setAttribute('aria-expanded', 'true');
    }

    function closeMobileNav() {
        navToggle.classList.remove('active');
        navMobile.classList.remove('open');
        navOverlay.classList.remove('active');
        document.body.style.overflow = '';
        navToggle.setAttribute('aria-expanded', 'false');
    }

    if (navToggle) {
        navToggle.addEventListener('click', function () {
            if (navMobile.classList.contains('open')) {
                closeMobileNav();
            } else {
                openMobileNav();
            }
        });
    }

    if (navOverlay) {
        navOverlay.addEventListener('click', closeMobileNav);
    }

    // Close mobile nav on link click
    if (navMobile) {
        navMobile.querySelectorAll('a:not(.btn)').forEach(function (link) {
            link.addEventListener('click', closeMobileNav);
        });
    }

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && navMobile && navMobile.classList.contains('open')) {
            closeMobileNav();
        }
    });

    // =============================================
    // ACTIVE NAV LINK HIGHLIGHTING
    // =============================================
    const navLinks = document.querySelectorAll('.nav-primary a');
    const currentPath = window.location.pathname;

    navLinks.forEach(function (link) {
        const href = new URL(link.href).pathname;
        if (href === currentPath) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // =============================================
    // WHATSAPP FLOAT — HIDE ON SCROLL DOWN, SHOW ON SCROLL UP
    // =============================================
    const whatsappFloat = document.getElementById('whatsapp-float');
    let lastScrollY = 0;

    if (whatsappFloat) {
        window.addEventListener('scroll', function () {
            const currentScrollY = window.pageYOffset;

            // Always show after 300px scroll
            if (currentScrollY < 300) {
                whatsappFloat.style.opacity = '0';
                whatsappFloat.style.pointerEvents = 'none';
            } else {
                whatsappFloat.style.opacity = '1';
                whatsappFloat.style.pointerEvents = 'auto';
            }

            lastScrollY = currentScrollY;
        }, { passive: true });
    }

    // =============================================
    // SMOOTH ANCHOR SCROLLING (for same-page links)
    // =============================================
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                const headerHeight = header ? header.offsetHeight : 0;
                const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight - 20;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth',
                });
            }
        });
    });

    // =============================================
    // NUMBER FORMATTING HELPER (Bangla numerals)
    // =============================================
    window.bhelaFormatNumber = function (num) {
        const banglaDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        return num.toString().replace(/\d/g, function (d) {
            return banglaDigits[parseInt(d)];
        });
    };

    window.bhelaFormatCurrency = function (amount) {
        const formatted = amount.toLocaleString('en-IN');
        return '৳' + window.bhelaFormatNumber(formatted);
    };

    // =============================================
    // BOOKING FORM AJAX SUBMISSION
    // =============================================
    const contactForm = document.getElementById('contact-form');
    const responseDiv = document.getElementById('contact-form-response');
    const submitBtn = document.getElementById('contact-submit-btn');

    if (contactForm && responseDiv) {
        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Disable submit button & show loading state
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ ইনকোয়ারি পাঠানো হচ্ছে...';
            }

            // Prepare request data
            const formData = new FormData(contactForm);
            const params = new URLSearchParams();
            params.append('action', 'bhela_submit_booking');
            params.append('bhela_name', formData.get('name') || '');
            params.append('bhela_phone', formData.get('phone') || '');
            params.append('bhela_guests', formData.get('guests') || '');
            params.append('bhela_date', formData.get('date') || '');
            
            // Get selected cabin display name rather than internal value
            const cabinSelect = document.getElementById('contact-cabin');
            const cabinText = cabinSelect ? cabinSelect.options[cabinSelect.selectedIndex].text : '';
            params.append('bhela_cabin', cabinText || '');
            params.append('bhela_message', formData.get('message') || '');

            // Perform fetch call
            const ajaxUrl = typeof bhela_booking_vars !== 'undefined' ? bhela_booking_vars.ajax_url : '/wp-admin/admin-ajax.php';

            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success feedback
                    responseDiv.innerHTML = `
                        <div style="background: rgba(14, 110, 107, 0.1); border-left: 4px solid var(--color-primary); padding: var(--space-md); border-radius: var(--radius-sm); margin-bottom: var(--space-lg); color: var(--color-text);">
                            <p style="margin: 0; font-weight: 600; color: var(--color-primary);">🎉 ${data.data.message}</p>
                            <a href="${data.data.whatsapp_url}" target="_blank" rel="noopener" class="btn btn--whatsapp btn--full" style="margin-top: var(--space-md); display: inline-flex; justify-content: center; align-items: center;">
                                💬 সরাসরি WhatsApp এ বুকিং কনফার্ম করুন
                            </a>
                        </div>
                    `;
                    contactForm.reset(); // Reset form fields
                } else {
                    // Error feedback
                    responseDiv.innerHTML = `
                        <div style="background: rgba(242, 118, 46, 0.1); border-left: 4px solid var(--color-cta); padding: var(--space-md); border-radius: var(--radius-sm); color: var(--color-text);">
                            <p style="margin: 0; font-weight: 600; color: var(--color-cta);">❌ ${data.data.message || 'একটি ত্রুটি ঘটেছে। আবার চেষ্টা করুন।'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error submitting booking:', error);
                responseDiv.innerHTML = `
                    <div style="background: rgba(242, 118, 46, 0.1); border-left: 4px solid var(--color-cta); padding: var(--space-md); border-radius: var(--radius-sm); color: var(--color-text);">
                        <p style="margin: 0; font-weight: 600; color: var(--color-cta);">❌ নেটওয়ার্ক কানেকশন ব্যর্থ হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।</p>
                    </div>
                `;
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '📨 ইনকোয়ারি পাঠান';
                }
            });
        });
    }

});
