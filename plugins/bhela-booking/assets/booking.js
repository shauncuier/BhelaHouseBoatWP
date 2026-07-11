/**
 * BHELA Booking Engine v4 — guest-driven auto cabin plan, availability gating,
 * mobile step wizard (Availability → Cabin/Price → Info → Submit).
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var wrap = document.getElementById('bhela-booking');
		var form = document.getElementById('bhela-bm-form');
		if (!wrap || !form || typeof bhelaBM === 'undefined') return;

		var dateEl = document.getElementById('bm-date');
		var gAdults = document.getElementById('bm-g-adults');
		var gC48 = document.getElementById('bm-g-c48');
		var gC04 = document.getElementById('bm-g-c04');
		var autoplanBox = document.getElementById('bm-autoplan');
		var autoplanChips = document.getElementById('bm-autoplan-chips');
		var priceBox = document.getElementById('bhela-bm-price');
		var emptyMsg = document.getElementById('bhela-bm-empty');
		var breakdown = document.getElementById('bm-breakdown');
		var savingsRow = document.getElementById('bm-savings-row');
		var response = document.getElementById('bhela-bm-response');
		var submitBtn = document.getElementById('bhela-bm-submit');
		var availBtn = document.getElementById('bm-check-avail');
		var availBox = document.getElementById('bm-avail-result');
		var blockedBox = document.getElementById('bm-blocked');
		var blockedWa = document.getElementById('bm-blocked-wa');
		var guestError = document.getElementById('bm-guest-error');
		var next1 = document.getElementById('bm-next-1');
		var next2 = document.getElementById('bm-next-2');

		var availChecked = false;    // date availability confirmed (not booked)

		/* Cabin capacities available, largest first (e.g. [6,5,4,3,2]). */
		var CAPS = Object.keys(bhelaBM.rates)
			.map(function (k) { return bhelaBM.rates[k].sharing; })
			.sort(function (a, b) { return b - a; });
		var MAX_CAP = CAPS[0] || 6;
		var MIN_CAP = CAPS[CAPS.length - 1] || 2;
		var MAX_CABINS = 7;                     // boat has 7 cabins
		var MAX_GUESTS = MAX_CABINS * MAX_CAP;  // → 42 guest capacity
		var CAP_TO_TYPE = {};
		Object.keys(bhelaBM.rates).forEach(function (k) { CAP_TO_TYPE[bhelaBM.rates[k].sharing] = k; });

		function money(n) { return '৳' + Number(n).toLocaleString('en-IN'); }

		function dayType(dateStr) {
			if (!dateStr) return null;
			if (bhelaBM.holidays.indexOf(dateStr) !== -1) return 'holiday';
			var d = new Date(dateStr + 'T00:00:00');
			if (isNaN(d)) return null;
			return bhelaBM.weekendDays.indexOf(d.getDay()) !== -1 ? 'weekend' : 'weekday';
		}

		function rateFor(typeKey, dt) {
			var r = bhelaBM.rates[typeKey];
			if (!r) return 0;
			return dt === 'weekday' ? r.weekday : r.regular;
		}

		/* ---------- Auto cabin plan (guest count → best-fit cabins) ---------- */

		/**
		 * Choose cabin capacities that seat `total` guests (min 2 per cabin):
		 * fill the largest cabin repeatedly, then an exact-fit cabin for the
		 * remainder. A remainder of 1 would strand a lone guest, so borrow a
		 * full cabin and split it (e.g. 7 → 5+2, not 6+1).
		 * e.g. 2→[2], 6→[6], 7→[5,2], 12→[6,6], 13→[6,5,2].
		 */
		function planCapacities(total) {
			var caps = [];
			var rem = total;
			while (rem > MAX_CAP) { caps.push(MAX_CAP); rem -= MAX_CAP; }
			if (rem === 1) {
				if (caps.length) {
					caps.pop();                       // give back a full cabin…
					caps.push(MAX_CAP - 1, MIN_CAP);  // …split its 7 people as 5 + 2
				} else {
					caps.push(MIN_CAP);               // lone guest (blocked upstream by min-2)
				}
			} else if (rem >= 2) {
				var fit = CAPS.filter(function (c) { return c >= rem; }).sort(function (a, b) { return a - b; })[0];
				caps.push(fit || MIN_CAP);
			}
			return caps;
		}

		/**
		 * Build cabin objects {type,adults,c48,c04} from guest totals.
		 * 0–4 infants are FREE — they don't occupy a seat or size the plan;
		 * they're just attached to the first cabin as recorded info.
		 */
		function buildAutoPlan(adults, c48, c04) {
			var occ = adults + c48; // paying/seated occupants
			if (occ < 2) return []; // a cabin needs at least 2 guests
			var caps = planCapacities(occ);
			var pools = [
				{ k: 'adults', n: adults },
				{ k: 'c48', n: c48 }
			];
			var cabins = caps.map(function (cap) {
				var cab = { type: CAP_TO_TYPE[cap] || CAP_TO_TYPE[MAX_CAP], adults: 0, c48: 0, c04: 0 };
				var space = cap;
				pools.forEach(function (p) {
					if (space <= 0 || p.n <= 0) return;
					var take = Math.min(space, p.n);
					cab[p.k] += take; p.n -= take; space -= take;
				});
				return cab;
			});
			if (c04 > 0 && cabins.length) cabins[0].c04 = c04; // ride-along, no seat used
			return cabins;
		}

		function guestTotals() {
			return {
				adults: parseInt(gAdults.value, 10) || 0,
				c48: parseInt(gC48.value, 10) || 0,
				c04: parseInt(gC04.value, 10) || 0
			};
		}

		function renderAutoPlan(cabins, dt) {
			if (!cabins.length) { autoplanBox.hidden = true; return; }
			autoplanChips.innerHTML = cabins.map(function (c) {
				var r = bhelaBM.rates[c.type];
				var seats = c.adults + c.c48; // infants (0–4) are free, not seated
				var per = rateFor(c.type, dt || 'weekend');
				var label = r.label.split('(')[0].trim();
				return '<span class="bm-chip"><b>' + label + '</b> · ' + seats + ' জন' +
					(c.c04 ? ' <i>+' + c.c04 + ' শিশু(ফ্রি)</i>' : '') +
					(dt ? ' · ' + money(per) + '/জন' : '') + '</span>';
			}).join('');
			autoplanBox.hidden = false;
		}

		/** Cabins currently in effect — always derived from the guest count. */
		function activeCabins() {
			var g = guestTotals();
			return buildAutoPlan(g.adults, g.c48, g.c04);
		}

		/* ---------- Price calculation ---------- */

		function calc() {
			var dt = dayType(dateEl.value);
			var cabins = activeCabins();
			renderAutoPlan(cabins, dt);

			var total = 0, regularTotal = 0, guests = 0, infants = 0;
			var adultsTotal = 0, c48Total = 0;
			var lines = [];

			cabins.forEach(function (c) {
				var r = bhelaBM.rates[c.type];
				if (!r || (c.adults + c.c48 + c.c04) < 1) return;
				adultsTotal += c.adults;
				c48Total += c.c48;
				guests += c.adults + c.c48; // 0–4 infants are free, not counted as guests
				infants += c.c04;
				if (!dt) return;
				var rate = rateFor(c.type, dt);
				var lineTotal = c.adults * rate + Math.ceil(c.c48 * rate * (bhelaBM.childPercent / 100));
				var lineRegular = c.adults * r.regular + Math.ceil(c.c48 * r.regular * (bhelaBM.childPercent / 100));
				total += lineTotal;
				regularTotal += lineRegular;
				var who = c.adults + ' বড়';
				if (c.c48) who += ' + ' + c.c48 + ' শিশু(৪–৮)';
				if (c.c04) who += ' + ' + c.c04 + ' শিশু(০–৪)';
				lines.push({ label: r.label.split('(')[0].trim(), who: who, total: lineTotal });
			});

			// Validation rules (in priority order):
			//  • a 4–8 child (50%) needs at least one adult in the party;
			//  • a booking needs at least 2 guests (no solo booking).
			var g = guestTotals();
			var rawOcc = g.adults + g.c48;
			var loneCabin = cabins.some(function (c) { return (c.adults + c.c48) === 1; });
			var errMsg = '';
			if (c48Total > 0 && adultsTotal < 1) errMsg = '⚠️ শিশু (৪–৮) থাকলে অন্তত ১ জন বড় (৯+) থাকতে হবে।';
			else if (rawOcc === 1) errMsg = '⚠️ অন্তত ২ জন অতিথি প্রয়োজন — একা একজনের বুকিং সম্ভব নয়।';
			else if (rawOcc > MAX_GUESTS) errMsg = '⚠️ সর্বোচ্চ ' + MAX_GUESTS + ' জন অতিথি (৭টি কেবিন) — বড় গ্রুপের জন্য WhatsApp-এ যোগাযোগ করুন।';
			else if (loneCabin) errMsg = '⚠️ প্রতিটি কেবিনে অন্তত ২ জন অতিথি থাকতে হবে।';
			var invalid = !!errMsg;
			if (guestError) { guestError.hidden = !invalid; if (invalid) guestError.textContent = errMsg; }
			if (invalid) autoplanBox.hidden = true;

			var hasGuests = guests >= 2 && !invalid;
			// Gate step 2 → 3 and submission: need a valid party.
			if (next2) next2.disabled = !hasGuests;
			if (submitBtn) submitBtn.disabled = invalid;

			if (invalid || !dt || total < 1) {
				priceBox.hidden = true;
				if (emptyMsg) emptyMsg.hidden = false;
				updateMobileBar('', dt);
				return;
			}

			var labels = { weekday: 'Weekday −20% 🔥', weekend: 'Weekend', holiday: 'সরকারি ছুটি' };
			document.getElementById('bm-daytype').textContent = labels[dt];
			document.getElementById('bm-guests-echo').textContent = guests + ' জন' + (infants ? ' + ' + infants + ' শিশু (০–৪, ফ্রি)' : '');
			document.getElementById('bm-total').textContent = money(total);
			var advance = Math.ceil(total * (bhelaBM.advancePercent / 100));
			document.getElementById('bm-advance').textContent = money(advance);

			var savings = Math.max(0, regularTotal - total);
			savingsRow.hidden = savings <= 0;
			if (savings > 0) document.getElementById('bm-savings').textContent = money(savings);

			breakdown.innerHTML = lines.map(function (l) {
				return '<div class="bm-bd-line"><span>' + l.label + '<small>' + l.who + '</small></span><strong>' + money(l.total) + '</strong></div>';
			}).join('');

			priceBox.hidden = false;
			if (emptyMsg) emptyMsg.hidden = true;
			updateMobileBar(money(total), dt);
		}

		/* ---------- Mobile step wizard ---------- */

		var mq = window.matchMedia('(max-width: 860px)');
		var mBar = null, mBarPrice = null;

		function buildMobileBar() {
			if (mBar) return;
			mBar = document.createElement('div');
			mBar.className = 'bm-mobilebar';
			mBar.innerHTML = '<div class="bm-mobilebar__price"><small>মোট</small><strong id="bm-mbar-total">—</strong></div>';
			mBarPrice = mBar.querySelector('#bm-mbar-total');
			wrap.appendChild(mBar);
		}

		function updateMobileBar(text, dt) {
			if (!mBarPrice) return;
			mBarPrice.textContent = text || '—';
		}

		function setStep(n) {
			wrap.setAttribute('data-mstep', String(n));
			var steps = form.querySelectorAll('.bhela-bm-step');
			steps.forEach(function (s) {
				s.classList.toggle('is-current', s.getAttribute('data-step') === String(n));
			});
			document.querySelectorAll('#bm-stepbar .bm-stepdot').forEach(function (dot) {
				var d = parseInt(dot.getAttribute('data-dot'), 10);
				dot.classList.toggle('is-active', d === n);
				dot.classList.toggle('is-done', d < n);
			});
			// Price panel only relevant from step 2 onward on mobile.
			if (mBar) mBar.classList.toggle('is-shown', mq.matches && n >= 2);
			try { window.scrollTo({ top: wrap.offsetTop - 12, behavior: 'smooth' }); } catch (e) { window.scrollTo(0, 0); }
		}

		function enableMobile() {
			wrap.setAttribute('data-mobile', '1');
			buildMobileBar();
			setStep(currentStep());
		}
		function disableMobile() {
			wrap.removeAttribute('data-mobile');
			if (mBar) mBar.classList.remove('is-shown');
		}
		function currentStep() {
			var s = parseInt(wrap.getAttribute('data-mstep'), 10);
			return s >= 1 && s <= 3 ? s : 1;
		}
		function syncMode() { if (mq.matches) enableMobile(); else disableMobile(); }

		document.querySelectorAll('.bm-next').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (btn.disabled) return;
				setStep(parseInt(btn.getAttribute('data-next'), 10));
			});
		});
		document.querySelectorAll('.bm-back').forEach(function (btn) {
			btn.addEventListener('click', function () {
				setStep(parseInt(btn.getAttribute('data-back'), 10));
			});
		});
		document.querySelectorAll('#bm-stepbar .bm-stepdot').forEach(function (dot) {
			dot.addEventListener('click', function () {
				var d = parseInt(dot.getAttribute('data-dot'), 10);
				// Allow jumping back, or forward only if that step is unlocked.
				if (d < currentStep()) { setStep(d); return; }
				if (d === 2 && !availChecked) return;
				if (d === 3 && (next2 && next2.disabled)) return;
				setStep(d);
			});
		});

		/* ---------- Availability check (gates step 1 → 2) ---------- */

		function resetAvailability() {
			availChecked = false;
			if (next1) next1.disabled = true;
			if (blockedBox) blockedBox.hidden = true;
		}

		if (availBtn) {
			availBtn.addEventListener('click', function () {
				if (!dateEl.value) {
					availBox.hidden = false;
					availBox.innerHTML = '<span class="bm-avail-chip" style="background:#FDF0E8;color:#7c2d12">আগে তারিখ বাছাই করুন</span>';
					return;
				}
				availBtn.disabled = true;
				availBtn.textContent = '⏳ চেক হচ্ছে...';
				var params = new URLSearchParams();
				params.append('action', 'bhela_bm_availability');
				params.append('nonce', bhelaBM.nonce);
				params.append('date', dateEl.value);
				fetch(bhelaBM.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: params.toString()
				})
					.then(function (r) { return r.json(); })
					.then(function (data) {
						availBox.hidden = false;
						if (data.success) {
							var d = data.data;
							var booked = d.status === 'booked';
							var html = '<span class="bm-avail-chip" style="background:' + d.color + '1a;color:' + d.color + ';border:1px solid ' + d.color + '55">' + d.label + '</span>';
							if (d.trip) html += ' <span class="bm-avail-trip">📅 ' + d.trip + '</span>';
							if (d.note) html += '<div class="bm-avail-note">' + d.note + '</div>';
							availBox.innerHTML = html;

							if (booked) {
								availChecked = false;
								if (next1) next1.disabled = true;
								if (blockedBox) {
									blockedBox.hidden = false;
									if (blockedWa && bhelaBM.whatsapp) {
										blockedWa.href = 'https://wa.me/' + bhelaBM.whatsapp +
											'?text=' + encodeURIComponent('আসসালামু আলাইকুম, ' + dateEl.value + ' তারিখে বুকিং সম্পর্কে জানতে চাই।');
									}
								}
							} else {
								availChecked = true;
								if (next1) next1.disabled = false;
								if (blockedBox) blockedBox.hidden = true;
								calc();
							}
						} else {
							availBox.innerHTML = '<span class="bm-avail-chip" style="background:#FDF0E8;color:#7c2d12">' + ((data.data && data.data.message) || 'চেক করা যায়নি') + '</span>';
						}
					})
					.catch(function () {
						availBox.hidden = false;
						availBox.innerHTML = '<span class="bm-avail-chip" style="background:#FDF0E8;color:#7c2d12">নেটওয়ার্ক সমস্যা — আবার চেষ্টা করুন</span>';
					})
					.finally(function () {
						availBtn.disabled = false;
						availBtn.textContent = '🔍 Availability চেক করুন';
					});
			});
		}

		/* ---------- Init ---------- */

		[gAdults, gC48, gC04].forEach(function (el) { el.addEventListener('change', calc); });

		var urlDate = new URLSearchParams(window.location.search).get('date');
		if (urlDate && /^\d{4}-\d{2}-\d{2}$/.test(urlDate)) dateEl.value = urlDate;

		dateEl.addEventListener('change', function () { resetAvailability(); calc(); });

		wrap.setAttribute('data-mstep', '1');
		resetAvailability();
		calc();
		syncMode();
		if (mq.addEventListener) mq.addEventListener('change', syncMode);
		else if (mq.addListener) mq.addListener(syncMode);

		/* ---------- Submit ---------- */

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			response.innerHTML = '';
			submitBtn.disabled = true;
			submitBtn.classList.add('is-loading');

			var fd = new FormData(form);
			var params = new URLSearchParams();
			params.append('action', 'bhela_bm_submit');
			params.append('nonce', bhelaBM.nonce);
			['name', 'phone', 'email', 'date', 'message'].forEach(function (f) {
				params.append(f, fd.get(f) || '');
			});
			params.append('cabins', JSON.stringify(activeCabins()));

			fetch(bhelaBM.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params.toString()
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data.success) {
						var d = data.data;
						var html = '<div class="bhela-bm-alert bhela-bm-alert--ok">🎉 ' + d.message;
						if (d.whatsapp_url) {
							html += '<br><a class="bhela-bm-btn" href="' + d.whatsapp_url + '" target="_blank" rel="noopener">💬 WhatsApp-এ কনফার্ম করুন</a>';
						}
						if (d.invoice_url) {
							html += '<a class="bhela-bm-btn bhela-bm-btn--invoice" href="' + d.invoice_url + '" target="_blank" rel="noopener">🧾 ইনভয়েস দেখুন</a>';
						}
						html += '</div>';
						response.innerHTML = html;
					} else {
						response.innerHTML = '<div class="bhela-bm-alert bhela-bm-alert--err">❌ ' + ((data.data && data.data.message) || 'একটি ত্রুটি ঘটেছে। আবার চেষ্টা করুন।') + '</div>';
					}
				})
				.catch(function () {
					response.innerHTML = '<div class="bhela-bm-alert bhela-bm-alert--err">❌ নেটওয়ার্ক সমস্যা হয়েছে। আবার চেষ্টা করুন।</div>';
				})
				.finally(function () {
					submitBtn.disabled = false;
					submitBtn.classList.remove('is-loading');
					response.scrollIntoView({ behavior: 'smooth', block: 'center' });
				});
		});
	});
})();
