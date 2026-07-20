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

/* ---------- Contact form (AJAX) ---------- */
(function () {
	var form = document.getElementById('bhela-contact-form');
	if (!form || typeof bhelaContact === 'undefined') return;

	var btn = document.getElementById('bc-submit');
	var msg = document.getElementById('bc-msg');

	function show(text, ok) {
		msg.hidden = false;
		msg.textContent = text;
		msg.className = 'bhela-contact-form__msg is-' + (ok ? 'ok' : 'error');
	}

	// Mirrors bhela_bm_normalize_mobile() in PHP — keep the two in step.
	function normalizeMobile(raw) {
		var d = String(raw || '').replace(/[^0-9]/g, '');
		if (d.indexOf('00880') === 0) d = d.slice(5);
		else if (d.indexOf('880') === 0) d = d.slice(3);
		if (d.length === 10 && d.charAt(0) === '1') d = '0' + d;
		return /^01[3-9][0-9]{8}$/.test(d) ? d : '';
	}

	var phoneEl = document.getElementById('bc-phone');
	if (phoneEl) {
		phoneEl.addEventListener('blur', function () {
			if (!phoneEl.value.trim()) { phoneEl.classList.remove('is-invalid'); return; }
			phoneEl.classList.toggle('is-invalid', !normalizeMobile(phoneEl.value));
		});
		phoneEl.addEventListener('input', function () {
			if (normalizeMobile(phoneEl.value)) phoneEl.classList.remove('is-invalid');
		});
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var data = new FormData(form);
		if (!data.get('name') || !data.get('phone') || !data.get('message')) {
			show('নাম, ফোন ও বার্তা লিখুন।', false);
			return;
		}
		if (!normalizeMobile(data.get('phone'))) {
			show('সঠিক মোবাইল নম্বর দিন — ১১ সংখ্যার, ০১ দিয়ে শুরু (যেমন ০১৭১২৩৪৫৬৭৮)।', false);
			if (phoneEl) { phoneEl.classList.add('is-invalid'); phoneEl.focus(); }
			return;
		}
		var params = new URLSearchParams();
		params.append('action', 'bhela_contact_submit');
		params.append('nonce', bhelaContact.nonce);
		['name', 'phone', 'email', 'subject', 'message', 'bhela_hp'].forEach(function (k) {
			params.append(k, data.get(k) || '');
		});

		btn.disabled = true;
		var label = btn.textContent;
		btn.textContent = 'পাঠানো হচ্ছে…';

		fetch(bhelaContact.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: params.toString()
		})
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success) {
					show((res.data && res.data.message) || 'ধন্যবাদ! বার্তা পৌঁছেছে।', true);
					form.reset();
				} else {
					show((res && res.data && res.data.message) || 'পাঠানো যায়নি — ফোন বা WhatsApp-এ যোগাযোগ করুন।', false);
				}
			})
			.catch(function () {
				show('নেটওয়ার্ক সমস্যা — আবার চেষ্টা করুন।', false);
			})
			.finally(function () {
				btn.disabled = false;
				btn.textContent = label;
			});
	});
})();
