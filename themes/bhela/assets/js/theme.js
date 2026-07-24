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
			// threshold 0 (any pixel) + a bottom rootMargin: a tall element — e.g.
			// the whole trip list stacked into one column on mobile — reveals as
			// soon as its top edge scrolls in. A percentage threshold could never
			// fire when the block is many screens tall, hiding it entirely.
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					if (en.isIntersecting) {
						en.target.classList.add('is-visible');
						io.unobserve(en.target);
					}
				});
			}, { threshold: 0, rootMargin: '0px 0px -8% 0px' });
			document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
		} else {
			document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('is-visible'); });
		}

		/* ----- Hero quick estimator ----- */
		var qeGuests = document.getElementById('qe-guests');
		var qeDate = document.getElementById('qe-date');
		if (qeGuests && typeof bhelaTheme !== 'undefined' && bhelaTheme.rates) {
			var result = document.getElementById('qe-result');
			var meta = document.getElementById('qe-meta');
			var totalEl = document.getElementById('qe-total');
			var qeBook = document.getElementById('qe-book');
			var bookBase = (qeBook && qeBook.getAttribute('href')) || bhelaTheme.bookingUrl || '';
			var availEl = document.getElementById('qe-avail');
			var chipsEl = document.getElementById('qe-chips');
			var trips = bhelaTheme.trips || {};

			// Quick-pick chips for the upcoming scheduled trips (soonest first).
			if (chipsEl) {
				Object.keys(trips).sort().slice(0, 4).forEach(function (dstr) {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'qe-chip';
					b.setAttribute('data-date', dstr);
					b.textContent = trips[dstr].label || dstr;
					b.addEventListener('click', function () {
						qeDate.value = dstr;
						qeDate.dispatchEvent(new Event('change'));
					});
					chipsEl.appendChild(b);
				});
			}

			// Enable/disable the book button. A disabled anchor is styled + its
			// click is swallowed, so a date with no trip (or a full one) can't
			// start an online booking — it belongs on WhatsApp.
			function setBook(enabled) {
				if (!qeBook) { return; }
				qeBook.classList.toggle('is-disabled', !enabled);
				qeBook.setAttribute('aria-disabled', enabled ? 'false' : 'true');
			}
			if (qeBook) {
				qeBook.addEventListener('click', function (e) {
					if (qeBook.classList.contains('is-disabled')) { e.preventDefault(); }
				});
			}

			// Live availability for the picked date, same source the booking page
			// uses. A date with no scheduled trip is a Full Boat / custom request.
			function syncChips(ds) {
				if (!chipsEl) { return; }
				chipsEl.querySelectorAll('.qe-chip').forEach(function (c) {
					c.classList.toggle('is-active', c.getAttribute('data-date') === ds);
				});
			}

			function showAvail() {
				if (!availEl) { return; }
				var ds = qeDate.value;
				syncChips(ds);
				if (!ds) { availEl.hidden = true; setBook(true); return; } // no date → generic booking
				var t = trips[ds];
				availEl.hidden = false;
				if (!t) {
					availEl.className = 'qe-avail is-none';
					availEl.textContent = 'এই তারিখে গ্রুপ ট্রিপ নেই — Full Boat/কাস্টম WhatsApp-এ';
					setBook(false);
					return;
				}
				var full = t.status === 'booked' || t.available <= 0;
				availEl.className = 'qe-avail ' + (full ? 'is-full' : 'is-open');
				availEl.textContent = full
					? 'এই তারিখে বুকড — অন্য তারিখ দেখুন'
					: t.total + 'টির মধ্যে ' + t.available + 'টি কেবিন খালি';
				setBook(!full);
			}

			function dayType(str) {
				if (!str) return 'weekend';
				if ((bhelaTheme.holidays || []).indexOf(str) !== -1) return 'holiday';
				var d = new Date(str + 'T00:00:00');
				if (isNaN(d)) return 'weekend';
				return (bhelaTheme.weekendDays || [5, 6]).indexOf(d.getDay()) !== -1 ? 'weekend' : 'weekday';
			}

			// Rates keyed by cabin occupancy (people sharing) — the per-person
			// rate is decided by how many share a cabin, exactly like the server
			// engine (bhela_bm_rate_for_occupancy). Multiplying a chosen cabin's
			// headline rate by total guests under-quoted (4 guests in a 6-share
			// cabin is a 4-share tier, not a 6-share one).
			var occRates = {};
			Object.keys(bhelaTheme.rates).forEach(function (k) {
				var r = bhelaTheme.rates[k];
				occRates[parseInt(r.sharing, 10)] = r;
			});
			var occKeys = Object.keys(occRates).map(Number).sort(function (a, b) { return a - b; });
			var maxShare = occKeys.length ? occKeys[occKeys.length - 1] : 6;
			function rateForOcc(o) {
				if (occRates[o]) { return occRates[o]; }
				for (var i = 0; i < occKeys.length; i++) { if (occKeys[i] >= o) { return occRates[occKeys[i]]; } }
				return occRates[maxShare];
			}

			function updateBookLink(g) {
				if (!qeBook || !bookBase) { return; }
				var sep = bookBase.indexOf('?') === -1 ? '?' : '&';
				var qs = [];
				if (qeDate.value) { qs.push('date=' + encodeURIComponent(qeDate.value)); }
				if (g) { qs.push('adults=' + g); }   // hero "total guests" seeds the booking form's adult count
				qeBook.setAttribute('href', qs.length ? bookBase + sep + qs.join('&') : bookBase);
			}

			// Cheapest way to seat `g` adults, obeying the engine's rules: every
			// cabin holds 2..maxShare adults, the per-person rate is that cabin's
			// occupancy tier, and no cabin may be left with a single adult. A DP
			// (not a greedy fill) is needed — for 7 guests, greedy 6+1 is invalid
			// and 6+2 over-charges; the real cheapest is 5+2.
			function cheapest(g, weekday) {
				var INF = Infinity;
				var cost = [0];
				var pick = [0];
				for (var n = 1; n <= g; n++) { cost[n] = INF; pick[n] = 0; }
				for (var t = 2; t <= g; t++) {
					for (var p = 2; p <= Math.min(maxShare, t); p++) {
						var rest = t - p;
						if (rest !== 0 && rest < 2) { continue; } // leftover can't form a cabin
						if (cost[rest] === INF) { continue; }
						var r = rateForOcc(p);
						var c = (weekday ? r.weekday : r.regular) * p + cost[rest];
						if (c < cost[t]) { cost[t] = c; pick[t] = p; }
					}
				}
				if (cost[g] === INF) { return null; }
				var cabins = 0, nn = g;
				while (nn > 0 && pick[nn] > 0) { cabins++; nn -= pick[nn]; }
				return { sum: cost[g], cabins: cabins };
			}

			function calc() {
				var g = parseInt(qeGuests.value, 10) || 0;
				updateBookLink(g);
				showAvail();
				if (!g) { result.hidden = true; return; }
				// A booking needs at least 2 adults in a cabin, so 1 guest is
				// estimated at the 2-person minimum.
				var weekday = dayType(qeDate.value) === 'weekday';
				var est = cheapest(Math.max(g, 2), weekday);
				if (!est) { result.hidden = true; return; }
				meta.textContent = (weekday ? 'Weekday −20% 🔥 · ' : '') + est.cabins + ' কেবিন';
				totalEl.textContent = '৳' + Number(est.sum).toLocaleString('en-IN');
				result.hidden = false;
			}
			[qeGuests, qeDate].forEach(function (el) {
				el.addEventListener('change', calc);
				el.addEventListener('input', calc);
			});
			calc(); // show a price immediately (default guests)
		}

		/* ----- Gallery: category filter + lightbox -----
		 * Driven entirely by data attributes on the anchors, so the plugin's
		 * markup and the theme's bundled fallback markup take the same path.
		 * Legacy anchors simply carry no data-cats, so they get the lightbox
		 * with no filtering. */
		initGallery(document.querySelector('.bhela-gallery, .gallery-grid'));

		function initGallery(container) {
			if (!container) return;

			var items = [].slice.call(container.querySelectorAll('a')).map(function (a) {
				var img = a.querySelector('img');
				return {
					el: a,
					full: a.getAttribute('href'),
					caption: a.dataset.caption || (img && img.alt) || '',
					cats: (a.dataset.cats || '').split(/\s+/).filter(Boolean)
				};
			});
			if (!items.length) return;

			var visible = items.slice();   // recomputed on every filter change
			var index = 0;                 // indexes `visible`, never `items`
			var lastFocus = null;

			/* --- Lightbox --- */
			var lb = document.createElement('div');
			lb.className = 'lightbox';
			lb.setAttribute('role', 'dialog');
			lb.setAttribute('aria-modal', 'true');
			lb.setAttribute('aria-label', 'ছবি প্রদর্শন');
			lb.innerHTML =
				'<button class="lightbox__close" type="button" aria-label="বন্ধ করুন">×</button>' +
				'<button class="lightbox__nav lightbox__nav--prev" type="button" aria-label="আগের ছবি">‹</button>' +
				'<figure class="lightbox__figure"><img src="" alt=""></figure>' +
				'<button class="lightbox__nav lightbox__nav--next" type="button" aria-label="পরের ছবি">›</button>' +
				'<div class="lightbox__bar" aria-live="polite">' +
					'<span class="lightbox__caption"></span>' +
					'<span class="lightbox__count"></span>' +
				'</div>';
			document.body.appendChild(lb);

			var lbImg = lb.querySelector('img');
			var lbCap = lb.querySelector('.lightbox__caption');
			var lbCount = lb.querySelector('.lightbox__count');
			var btnClose = lb.querySelector('.lightbox__close');
			var btnPrev = lb.querySelector('.lightbox__nav--prev');
			var btnNext = lb.querySelector('.lightbox__nav--next');

			function bn(n) {
				try { return Number(n).toLocaleString('bn-BD'); } catch (e) { return String(n); }
			}

			function render() {
				var item = visible[index];
				if (!item) return;
				lbImg.src = item.full;
				lbImg.alt = item.caption;
				lbCap.textContent = item.caption;
				lbCount.textContent = bn(index + 1) + ' / ' + bn(visible.length);
				var solo = visible.length < 2;
				btnPrev.hidden = solo;
				btnNext.hidden = solo;
				// Preloading the neighbour makes navigation feel instant.
				if (!solo) new Image().src = visible[(index + 1) % visible.length].full;
			}

			function open(el) {
				var i = visible.findIndex(function (it) { return it.el === el; });
				if (i < 0) return;
				lastFocus = document.activeElement;
				index = i;
				render();
				lb.classList.add('is-open');
				document.body.style.overflow = 'hidden';
				btnClose.focus();
			}

			function close() {
				if (!lb.classList.contains('is-open')) return;
				lb.classList.remove('is-open');
				document.body.style.overflow = '';
				lbImg.src = '';
				if (lastFocus && lastFocus.focus) lastFocus.focus();
			}

			function go(delta) {
				if (visible.length < 2) return;
				index = (index + delta + visible.length) % visible.length;
				render();
			}

			container.addEventListener('click', function (e) {
				var a = e.target.closest('a');
				if (!a || !container.contains(a)) return;
				e.preventDefault();
				open(a);
			});
			btnClose.addEventListener('click', close);
			btnPrev.addEventListener('click', function () { go(-1); });
			btnNext.addEventListener('click', function () { go(1); });
			lb.addEventListener('click', function (e) {
				// Backdrop only — never the image or the controls.
				if (e.target === lb || e.target.classList.contains('lightbox__figure')) close();
			});

			document.addEventListener('keydown', function (e) {
				if (!lb.classList.contains('is-open')) return;
				if (e.key === 'Escape') { close(); return; }
				if (e.key === 'ArrowLeft') { e.preventDefault(); go(-1); return; }
				if (e.key === 'ArrowRight') { e.preventDefault(); go(1); return; }
				if (e.key === 'Tab') {
					// Only three controls, so cycling them is a sufficient trap.
					var f = [].slice.call(lb.querySelectorAll('button')).filter(function (b) { return !b.hidden; });
					if (!f.length) return;
					var first = f[0], last = f[f.length - 1];
					if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
					else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
				}
			});

			// Swipe: horizontal dominance guard keeps page scrolling from navigating.
			var sx = 0, sy = 0;
			lb.addEventListener('touchstart', function (e) {
				sx = e.changedTouches[0].clientX; sy = e.changedTouches[0].clientY;
			}, { passive: true });
			lb.addEventListener('touchend', function (e) {
				var dx = e.changedTouches[0].clientX - sx;
				var dy = e.changedTouches[0].clientY - sy;
				if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.5) go(dx < 0 ? 1 : -1);
			}, { passive: true });

			/* --- Category filter --- */
			var tabs = document.querySelector('.bhela-gallery-filter');
			if (!tabs) return;

			tabs.addEventListener('click', function (e) {
				var btn = e.target.closest('.bhela-gallery-filter__btn');
				if (!btn) return;
				close(); // never leave the lightbox pointing at a filtered-out image
				var slug = btn.dataset.filter;

				[].forEach.call(tabs.querySelectorAll('.bhela-gallery-filter__btn'), function (b) {
					var on = b === btn;
					b.classList.toggle('is-active', on);
					b.setAttribute('aria-pressed', on ? 'true' : 'false');
				});

				items.forEach(function (it) {
					it.el.hidden = !('*' === slug || it.cats.indexOf(slug) !== -1);
				});
				visible = items.filter(function (it) { return !it.el.hidden; });
				index = 0;
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
