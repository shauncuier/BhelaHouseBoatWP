/**
 * BHELA Houseboat — Smooth Scroll & Reveal Animations
 * Uses Intersection Observer for performant scroll-triggered reveals
 *
 * @package BhelaHouseboat
 */

document.addEventListener('DOMContentLoaded', function () {

    // =============================================
    // SCROLL REVEAL (Intersection Observer)
    // =============================================
    const revealElements = document.querySelectorAll('.reveal');

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, {
            root: null,
            rootMargin: '0px 0px -60px 0px',
            threshold: 0.1,
        });

        revealElements.forEach(function (el) {
            revealObserver.observe(el);
        });
    } else {
        // Fallback: show all elements immediately
        revealElements.forEach(function (el) {
            el.classList.add('revealed');
        });
    }

    // =============================================
    // COUNTER ANIMATION (for stats numbers)
    // =============================================
    const counters = document.querySelectorAll('[data-counter]');

    if (counters.length && 'IntersectionObserver' in window) {
        const counterObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(function (counter) {
            counterObserver.observe(counter);
        });
    }

    function animateCounter(element) {
        const target = parseInt(element.dataset.counter);
        const duration = 1500;
        const increment = target / (duration / 16);
        let current = 0;

        const timer = setInterval(function () {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString('en-IN');
        }, 16);
    }

    // =============================================
    // PARALLAX EFFECT (subtle, for hero only)
    // =============================================
    const heroSection = document.querySelector('.hero__bg img');

    if (heroSection && window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
        window.addEventListener('scroll', function () {
            const scrolled = window.pageYOffset;
            if (scrolled < window.innerHeight) {
                heroSection.style.transform = `scale(1) translateY(${scrolled * 0.15}px)`;
            }
        }, { passive: true });
    }
});
