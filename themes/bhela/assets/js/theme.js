/**
 * BHELA theme — nav, reveal, quick estimator, lightbox.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		/* ----- Nav scroll state ----- */
		var nav = document.getElementById('site-nav');
		function onScroll() {
			if (nav) nav.classList.toggle('is-scrolled', window.scrollY > 40);
		}
		onScroll();
		window.addEventListener('scroll', onScroll, { passive: true });

		/* ----- Mobile menu ----- */
		var toggle = document.getElementById('nav-toggle');
		var menu = document.getElementById('site-menu');
		if (toggle && menu) {
			toggle.addEventListener('click', function () {
				var open = menu.classList.toggle('is-open');
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				document.body.style.overflow = open ? 'hidden' : '';
			});
			menu.addEventListener('click', function (e) {
				if (e.target.tagName === 'A') {
					menu.classList.remove('is-open');
					toggle.setAttribute('aria-expanded', 'false');
					document.body.style.overflow = '';
				}
			});
		}

		/* ----- Scroll reveal ----- */
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					if (en.isIntersecting) {
						en.target.classList.add('is-visible');
						io.unobserve(en.target);
					}
				});
			}, { threshold: 0.12 });
			document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
		} else {
			document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('is-visible'); });
		}

		/* ----- Hero quick estimator ----- */
		var qeCabin = document.getElementById('qe-cabin');
		var qeGuests = document.getElementById('qe-guests');
		var qeDate = document.getElementById('qe-date');
		if (qeCabin && typeof bhelaTheme !== 'undefined' && bhelaTheme.rates) {
			var result = document.getElementById('qe-result');
			var meta = document.getElementById('qe-meta');
			var total = document.getElementById('qe-total');

			function dayType(str) {
				if (!str) return 'weekend';
				if ((bhelaTheme.holidays || []).indexOf(str) !== -1) return 'holiday';
				var d = new Date(str + 'T00:00:00');
				if (isNaN(d)) return 'weekend';
				return (bhelaTheme.weekendDays || [5, 6]).indexOf(d.getDay()) !== -1 ? 'weekend' : 'weekday';
			}

			function calc() {
				var key = qeCabin.value;
				var g = parseInt(qeGuests.value, 10) || 0;
				var rate = bhelaTheme.rates[key];
				if (!rate || !g) { result.hidden = true; return; }
				var dt = dayType(qeDate.value);
				var per = dt === 'weekday' ? rate.weekday : rate.regular;
				var t = per * g;
				meta.textContent = (dt === 'weekday' ? 'Weekday −20% 🔥 · ' : '') + '৳' + Number(per).toLocaleString('en-IN') + ' × ' + g + ' জন';
				total.textContent = '৳' + Number(t).toLocaleString('en-IN');
				result.hidden = false;
			}
			[qeCabin, qeGuests, qeDate].forEach(function (el) {
				el.addEventListener('change', calc);
				el.addEventListener('input', calc);
			});
		}

		/* ----- Gallery lightbox ----- */
		var gallery = document.querySelector('.gallery-grid');
		if (gallery) {
			var lb = document.createElement('div');
			lb.className = 'lightbox';
			lb.innerHTML = '<button class="lightbox__close" aria-label="Close">×</button><img src="" alt="">';
			document.body.appendChild(lb);
			var lbImg = lb.querySelector('img');

			gallery.addEventListener('click', function (e) {
				var a = e.target.closest('a');
				if (!a) return;
				e.preventDefault();
				lbImg.src = a.getAttribute('href');
				lb.classList.add('is-open');
			});
			lb.addEventListener('click', function (e) {
				if (e.target !== lbImg) lb.classList.remove('is-open');
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') lb.classList.remove('is-open');
			});
		}
	});
})();
