/**
 * BHELA Booking Engine v4 — guest-driven auto cabin plan, availability gating,
 * mobile step wizard (Availability → Cabin/Price → Info → Submit).
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		if (typeof bhelaBM === 'undefined') return;

		/* ================= Booking tracking (works standalone + in the tab) ================= */

		function fmt(n) { return '৳' + Number(n).toLocaleString('en-IN'); }

		function fetchTrack(q) {
			var params = new URLSearchParams();
			params.append('action', 'bhela_bm_track');
			params.append('nonce', bhelaBM.nonce);
			params.append('q', q);
			return fetch(bhelaBM.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params.toString()
			}).then(function (r) { return r.json(); });
		}

		function trackChip(label, color) {
			color = color || '#555d66';
			return '<span class="bm-status-chip" style="background:' + color + '1a;color:' + color + ';border:1px solid ' + color + '55">' + (label || '—') + '</span>';
		}

		function trackCardHtml(b) {
			var rows = [
				['নাম', b.name], ['তারিখ', b.travel_date || '—'], ['কেবিন', b.cabin || '—'],
				['অতিথি', (b.guests || 0) + ' জন'], ['মোট', fmt(b.total)], ['অগ্রিম', fmt(b.advance)],
				['পরিশোধিত', fmt(b.paid)], ['বাকি', fmt(b.due)]
			].map(function (r) { return '<div class="bm-tc__row"><span>' + r[0] + '</span><strong>' + r[1] + '</strong></div>'; }).join('');
			return '<div class="bm-trackcard">' +
				'<div class="bm-trackcard__head"><strong>' + (b.invoice_no || '—') + '</strong>' + trackChip(b.status_label, b.status_color) + '</div>' +
				'<div class="bm-trackcard__rows">' + rows + '</div></div>';
		}

		(function initTracking() {
			var qEl = document.getElementById('bm-track-q');
			var btn = document.getElementById('bm-track-btn');
			var result = document.getElementById('bm-track-result');
			if (!qEl || !btn || !result) return;
			function run() {
				var val = (qEl.value || '').trim();
				if (val.length < 4) {
					result.innerHTML = '<div class="bm-track__msg">মোবাইল নম্বর, ইমেইল বা বুকিং নম্বর সঠিকভাবে দিন।</div>';
					return;
				}
				btn.disabled = true; btn.textContent = '⏳ খুঁজছি...';
				fetchTrack(val).then(function (res) {
					if (res.success && res.data && res.data.found) {
						result.innerHTML = res.data.bookings.map(trackCardHtml).join('');
					} else {
						var d = (res && res.data) || {};
						var wa = d.whatsapp ? '<a class="bhela-bm-btn" href="https://wa.me/' + d.whatsapp + '" target="_blank" rel="noopener">💬 WhatsApp-এ জিজ্ঞেস করুন</a>' : '';
						result.innerHTML = '<div class="bm-track__msg bm-track__msg--none">' + (d.message || 'কোনো বুকিং পাওয়া যায়নি।') + (wa ? '<br>' + wa : '') + '</div>';
					}
				}).catch(function () {
					result.innerHTML = '<div class="bm-track__msg">নেটওয়ার্ক সমস্যা — আবার চেষ্টা করুন।</div>';
				}).finally(function () {
					btn.disabled = false; btn.textContent = '🔍 ট্র্যাক করুন';
				});
			}
			btn.addEventListener('click', run);
			qEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(); } });
		})();

		/* ================= Booking form (only when the form is present) ================= */

		var wrap = document.getElementById('bhela-booking');
		var form = document.getElementById('bhela-bm-form');
		if (!wrap || !form) return;

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
		var fullBoatEl = document.getElementById('bm-fullboat');
		var editToggle = document.getElementById('bm-edit-toggle');
		var editBox = document.getElementById('bm-edit');
		var editRows = document.getElementById('bm-edit-rows');
		var editAdd = document.getElementById('bm-edit-add');
		var editClose = document.getElementById('bm-edit-close');
		var editNote = document.getElementById('bm-edit-note');
		var discountToggle = document.getElementById('bm-discount-toggle');
		var discountBody = document.getElementById('bm-discount-body');
		var next1 = document.getElementById('bm-next-1');
		var next2 = document.getElementById('bm-next-2');
		var EMPTY_DEFAULT = emptyMsg ? emptyMsg.textContent : '';

		var availChecked = false;    // date availability confirmed (not booked)

		/* Cabin occupancy tiers available (e.g. [2,3,4,5,6]) + boat limits. */
		var OCC_SIZES = Object.keys(bhelaBM.occRates).map(Number).sort(function (a, b) { return a - b; });
		var MIN_CAP = OCC_SIZES[0] || 2;
		var MAX_CAP = OCC_SIZES[OCC_SIZES.length - 1] || 6;
		var MAX_CABINS = bhelaBM.maxCabins || 7;
		var MAX_GUESTS = bhelaBM.maxGuests || (MAX_CABINS * MAX_CAP);

		function money(n) { return '৳' + Number(n).toLocaleString('en-IN'); }

		function dayType(dateStr) {
			if (!dateStr) return null;
			if (bhelaBM.holidays.indexOf(dateStr) !== -1) return 'holiday';
			var d = new Date(dateStr + 'T00:00:00');
			if (isNaN(d)) return null;
			return bhelaBM.weekendDays.indexOf(d.getDay()) !== -1 ? 'weekend' : 'weekday';
		}

		/* ---------- Combination engine (occupancy-priced) ---------- */

		function occRate(size, dt) {
			var r = bhelaBM.occRates[size];
			if (!r) { // nearest configured tier ≥ size, else largest
				var keys = Object.keys(bhelaBM.occRates).map(Number).sort(function (a, b) { return a - b; });
				var pick = keys.filter(function (k) { return k >= size; })[0] || keys[keys.length - 1];
				r = bhelaBM.occRates[pick];
			}
			return dt === 'weekday' ? r.weekday : r.regular;
		}

		function guestTotals() {
			return {
				adults: parseInt(gAdults.value, 10) || 0,
				c48: parseInt(gC48.value, 10) || 0,
				c04: parseInt(gC04.value, 10) || 0
			};
		}

		/**
		 * Every partition of `bodies` into cabins of size MIN_CAP..MAX_CAP,
		 * using at most MAX_CABINS cabins. Each partition is a size list (desc).
		 * e.g. 5 → [[5],[3,2]]; 8 → [[6,2],[5,3],[4,4],[4,2,2],[3,3,2],[2,2,2,2]].
		 */
		function genCombinations(bodies) {
			var out = [];
			(function recurse(remaining, maxPart, current) {
				if (remaining === 0) { out.push(current.slice()); return; }
				if (current.length >= MAX_CABINS) return;
				var hi = Math.min(maxPart, remaining);
				for (var p = hi; p >= MIN_CAP; p--) {
					// leftover after taking p must be 0 or ≥ MIN_CAP (no lone remainder)
					if (remaining - p !== 0 && remaining - p < MIN_CAP) continue;
					current.push(p);
					recurse(remaining - p, p, current);
					current.pop();
				}
			})(bodies, MAX_CAP, []);
			return out;
		}

		/**
		 * Distribute adults→c48 across a size list (largest cabin first). Cabin
		 * size = paying occupants (adults + 4–8 children). 0–4 infants are FREE
		 * riders: attached to the first cabin, they do NOT change the size/rate.
		 */
		function fillCombo(sizes, adults, c48, c04) {
			var pools = [{ k: 'adults', n: adults }, { k: 'c48', n: c48 }];
			var cabins = sizes.map(function (size) {
				var cab = { size: size, adults: 0, c48: 0, c04: 0 };
				var space = size;
				pools.forEach(function (pl) {
					if (space <= 0 || pl.n <= 0) return;
					var take = Math.min(space, pl.n);
					cab[pl.k] += take; pl.n -= take; space -= take;
				});
				return cab;
			});
			if (c04 > 0 && cabins.length) cabins[0].c04 = c04; // free ride-along, no seat used
			return cabins;
		}

		/** Price a filled combo. `occupants` = paying guests (infants excluded). */
		function priceCombo(cabins, dt, occupants) {
			var total = 0, regular = 0;
			cabins.forEach(function (c) {
				var rate = occRate(c.size, dt || 'weekend');
				var reg = occRate(c.size, 'weekend');
				total += c.adults * rate + Math.ceil(c.c48 * rate * (bhelaBM.childPercent / 100));
				regular += c.adults * reg + Math.ceil(c.c48 * reg * (bhelaBM.childPercent / 100));
			});
			return { cabins: cabins, total: total, regular: regular, bodies: occupants,
				perPerson: occupants ? Math.round(total / occupants) : 0 };
		}

		/**
		 * Every valid combination, priced and sorted cheapest-first. The first
		 * (lowest total) is flagged as our suggestion; the guest may pick any.
		 */
		function buildOptions(g, dt) {
			var occupants = g.adults + g.c48; // infants don't size cabins
			var combos = genCombinations(occupants).map(function (sizes) {
				return priceCombo(fillCombo(sizes, g.adults, g.c48, g.c04), dt, occupants);
			});
			if (!combos.length) return [];
			combos.sort(function (a, b) { return a.total - b.total || a.cabins.length - b.cabins.length; });
			return combos.map(function (c, i) {
				return { combo: c, suggested: i === 0 };
			});
		}

		/* ---------- Options UI + selection ---------- */

		var currentOptions = [];   // [{combo, suggested}]
		var selectedIndex = 0;
		var showAllOptions = false;

		function comboSizesLabel(cabins) {
			return cabins.map(function (c) { return c.size; }).join(' + ');
		}

		function optionCard(o, i, dt) {
			var c = o.combo;
			var detail = c.cabins.map(function (cb, n) {
				return 'কেবিন ' + (n + 1) + ': ' + cb.size + '×' + money(occRate(cb.size, dt || 'weekend'));
			}).join(' · ');
			var badge = o.suggested
				? '<span class="bm-opt__badge">✨ আমাদের সাজেশন</span>'
				: '<span class="bm-opt__badge bm-opt__badge--alt">' + c.cabins.length + ' কেবিন</span>';
			return '<label class="bm-opt' + (o.suggested ? ' bm-opt--best' : '') + (i === selectedIndex ? ' is-selected' : '') + '">' +
				'<input type="radio" name="bm-opt" value="' + i + '"' + (i === selectedIndex ? ' checked' : '') + '>' +
				'<span class="bm-opt__head">' + badge +
				'<span class="bm-opt__combo">' + comboSizesLabel(c.cabins) + '</span></span>' +
				'<span class="bm-opt__detail">' + detail + '</span>' +
				'<span class="bm-opt__total">' + money(c.total) + ' <small>· জনপ্রতি গড় ' + money(c.perPerson) + '</small></span>' +
				'</label>';
		}

		function renderOptions(dt) {
			if (!currentOptions.length) { autoplanBox.hidden = true; return; }

			// Collapsed: show the suggestion + the current pick (if different).
			var idxs;
			if (showAllOptions) {
				idxs = currentOptions.map(function (_, i) { return i; });
			} else {
				idxs = [0];
				if (selectedIndex !== 0) idxs.push(selectedIndex);
			}
			var cards = idxs.map(function (i) { return optionCard(currentOptions[i], i, dt); }).join('');

			var more = '';
			if (currentOptions.length > 1) {
				more = '<button type="button" class="bm-opts__more" id="bm-opts-more">' +
					(showAllOptions ? '▲ কম দেখান' : '🔧 নিজের কম্বিনেশন বাছাই করুন (' + currentOptions.length + ' অপশন)') +
					'</button>';
			}
			autoplanChips.innerHTML = cards + more;

			autoplanChips.querySelectorAll('input[name="bm-opt"]').forEach(function (input) {
				input.addEventListener('change', function () {
					selectedIndex = parseInt(input.value, 10) || 0;
					renderOptions(dayType(dateEl.value));
					renderSummary(dayType(dateEl.value));
				});
			});
			var moreBtn = document.getElementById('bm-opts-more');
			if (moreBtn) moreBtn.addEventListener('click', function () {
				showAllOptions = !showAllOptions;
				renderOptions(dayType(dateEl.value));
			});
			autoplanBox.hidden = false;
		}

		/** The cabins array for submission — edited builder, or selected option. */
		function activeCabins() {
			if (editMode) return builderCabins();
			var o = currentOptions[selectedIndex];
			if (!o) return [];
			return o.combo.cabins.map(function (c) {
				return { adults: c.adults, c48: c.c48, c04: c.c04 };
			});
		}

		/* ---------- Custom combination editor ---------- */

		var editMode = false;

		function numSelect(cls, max, val) {
			var out = '<select class="' + cls + '">';
			for (var i = 0; i <= max; i++) out += '<option value="' + i + '"' + (i === val ? ' selected' : '') + '>' + i + '</option>';
			return out + '</select>';
		}

		function addBuilderRow(cab) {
			cab = cab || { adults: 2, c48: 0, c04: 0 };
			var row = document.createElement('div');
			row.className = 'bm-cabin-row';
			row.innerHTML =
				'<div class="bm-cabin-row__n"><label>বড় (৯+)</label>' + numSelect('bm-b-adults', MAX_CAP, cab.adults || 0) + '</div>' +
				'<div class="bm-cabin-row__n"><label>শিশু ৪–৮</label>' + numSelect('bm-b-c48', MAX_CAP, cab.c48 || 0) + '</div>' +
				'<div class="bm-cabin-row__n"><label>শিশু ০–৪</label>' + numSelect('bm-b-c04', MAX_CAP, cab.c04 || 0) + '</div>' +
				'<div class="bm-cabin-row__price"><span class="bm-row-total">—</span></div>' +
				'<button type="button" class="bm-cabin-row__remove" aria-label="Remove">✕</button>';
			editRows.appendChild(row);
			row.querySelector('.bm-cabin-row__remove').addEventListener('click', function () {
				if (editRows.children.length > 1) row.remove();
				calc();
			});
			row.querySelectorAll('select').forEach(function (el) { el.addEventListener('change', calc); });
		}

		function builderCabins() {
			var cabins = [];
			editRows.querySelectorAll('.bm-cabin-row').forEach(function (row) {
				cabins.push({
					adults: parseInt(row.querySelector('.bm-b-adults').value, 10) || 0,
					c48: parseInt(row.querySelector('.bm-b-c48').value, 10) || 0,
					c04: parseInt(row.querySelector('.bm-b-c04').value, 10) || 0
				});
			});
			return cabins;
		}

		function openEdit() {
			editMode = true;
			editRows.innerHTML = '';
			var seed = (currentOptions[selectedIndex] && currentOptions[selectedIndex].combo.cabins) || [{ adults: 2, c48: 0, c04: 0 }];
			seed.forEach(function (c) { addBuilderRow({ adults: c.adults, c48: c.c48, c04: c.c04 }); });
			autoplanBox.hidden = true;
			editBox.hidden = false;
			calc();
		}

		function closeEdit() {
			editMode = false;
			editBox.hidden = true;
			calc();
		}

		/** Validate + price the builder; returns {ok, msg, priced}. */
		function evalBuilder(dt) {
			var cabins = builderCabins();
			var adults = 0, occupants = 0, badCabin = false;
			cabins.forEach(function (c, i) {
				var occ = c.adults + c.c48;   // paying occupants = cabin size (infants free)
				var infantOnly = occ === 0 && c.c04 > 0;
				var over = (occ > 0 && (occ < 2 || occ > MAX_CAP)) || infantOnly;
				var rowEl = editRows.querySelectorAll('.bm-cabin-row')[i];
				if (rowEl) {
					rowEl.classList.toggle('is-over', over);
					var tEl = rowEl.querySelector('.bm-row-total');
					if (tEl) tEl.textContent = (occ >= 2 && occ <= MAX_CAP && dt)
						? money(c.adults * occRate(occ, dt) + Math.ceil(c.c48 * occRate(occ, dt) * (bhelaBM.childPercent / 100)))
						: '—';
				}
				if (over) badCabin = true;
				adults += c.adults; occupants += occ;
			});
			var msg = '';
			if (badCabin) msg = '⚠️ প্রতিটি কেবিনে ২–' + MAX_CAP + ' জন (শিশু ০–৪ বাদে) থাকতে হবে।';
			else if (adults < 1) msg = '⚠️ অন্তত ১ জন বড় (৯+) থাকতে হবে।';
			else if (occupants < 2) msg = '⚠️ অন্তত ২ জন প্রয়োজন।';
			else if (cabins.length > MAX_CABINS) msg = '⚠️ সর্বোচ্চ ' + MAX_CABINS + 'টি কেবিন।';
			else if (occupants > MAX_GUESTS) msg = '⚠️ সর্বোচ্চ ' + MAX_GUESTS + ' জন।';
			var priced = null;
			if (!msg && dt) {
				var filled = cabins.map(function (c) { return { size: c.adults + c.c48, adults: c.adults, c48: c.c48, c04: c.c04 }; });
				priced = priceCombo(filled, dt, occupants);
			}
			return { ok: !msg, msg: msg, priced: priced };
		}

		/* ---------- Recompute options + summary ---------- */

		function fullBoat() { return !!(fullBoatEl && fullBoatEl.checked); }

		function calc() {
			var dt = dayType(dateEl.value);
			if (emptyMsg) emptyMsg.textContent = EMPTY_DEFAULT;

			// Full Boat → custom quote request; skip combo pricing entirely.
			if (fullBoat()) {
				currentOptions = [];
				editMode = false;
				editBox.hidden = true;
				autoplanBox.hidden = true;
				if (guestError) guestError.hidden = true;
				priceBox.hidden = true;
				if (emptyMsg) { emptyMsg.hidden = false; emptyMsg.textContent = '🚢 পুরো বোট রিজার্ভ — কাস্টম কোটের জন্য রিকোয়েস্ট পাঠান। তারিখ ও চাহিদা অনুযায়ী আমরা দাম জানাবো।'; }
				if (next2) next2.disabled = false;
				if (submitBtn) submitBtn.disabled = false;
				updateMobileBar('কাস্টম কোট', dt);
				return;
			}

			// Custom combination editor is the source of truth when open.
			if (editMode) {
				var ev = evalBuilder(dt);
				editNote.textContent = ev.msg;
				editNote.hidden = !ev.msg;
				if (guestError) guestError.hidden = true;
				if (next2) next2.disabled = !ev.ok;
				if (submitBtn) submitBtn.disabled = !ev.ok;
				if (!ev.ok || !dt || !ev.priced) {
					priceBox.hidden = true;
					if (emptyMsg) emptyMsg.hidden = false;
					updateMobileBar('', dt);
					return;
				}
				paintSummary(ev.priced, dt);
				return;
			}

			var g = guestTotals();
			// `occupants` = paying guests (adults + 4–8 children). 0–4 infants are
			// free ride-alongs and never affect cabin sizing, count, or capacity.
			var occupants = g.adults + g.c48;

			var errMsg = '';
			if ((g.c48 > 0 || g.c04 > 0) && g.adults < 1) errMsg = '⚠️ অন্তত ১ জন বড় (৯+) থাকতে হবে — শিশুরা একা ভ্রমণ করতে পারে না।';
			else if (occupants === 1) errMsg = '⚠️ অন্তত ২ জন প্রয়োজন — একা একজনের বুকিং সম্ভব নয়।';
			else if (occupants > MAX_GUESTS) errMsg = '⚠️ সর্বোচ্চ ' + MAX_GUESTS + ' জন (' + MAX_CABINS + 'টি কেবিন) — বড় গ্রুপের জন্য WhatsApp-এ যোগাযোগ করুন।';
			var invalid = !!errMsg;

			if (invalid || occupants < 2) {
				currentOptions = [];
				if (guestError) { guestError.hidden = !invalid; if (invalid) guestError.textContent = errMsg; }
				autoplanBox.hidden = true;
				if (next2) next2.disabled = true;
				if (submitBtn) submitBtn.disabled = invalid;
				priceBox.hidden = true;
				if (emptyMsg) emptyMsg.hidden = false;
				updateMobileBar('', dt);
				return;
			}
			if (guestError) guestError.hidden = true;

			currentOptions = buildOptions(g, dt);
			if (selectedIndex >= currentOptions.length) selectedIndex = 0;
			renderOptions(dt);

			if (next2) next2.disabled = false;
			if (submitBtn) submitBtn.disabled = false;
			renderSummary(dt);
		}

		function renderSummary(dt) {
			var o = currentOptions[selectedIndex];
			if (!o) { priceBox.hidden = true; if (emptyMsg) emptyMsg.hidden = false; updateMobileBar('', dt); return; }
			if (!dt) { priceBox.hidden = true; if (emptyMsg) emptyMsg.hidden = false; updateMobileBar('', dt); return; }
			paintSummary(o.combo, dt);
		}

		/** Paint the price summary from a priced combo {cabins,total,regular,bodies}. */
		function paintSummary(c, dt) {
			var infants = c.cabins.reduce(function (s, cb) { return s + cb.c04; }, 0);

			var labels = { weekday: 'Weekday −20% 🔥', weekend: 'Weekend', holiday: 'সরকারি ছুটি' };
			var paying = c.bodies; // already paying guests only (infants excluded)
			document.getElementById('bm-daytype').textContent = labels[dt];
			document.getElementById('bm-guests-echo').textContent = paying + ' জন' + (infants ? ' + ' + infants + ' শিশু (০–৪, ফ্রি)' : '');
			document.getElementById('bm-total').textContent = money(c.total);
			var advance = Math.ceil(c.total * (bhelaBM.advancePercent / 100));
			document.getElementById('bm-advance').textContent = money(advance);

			var savings = Math.max(0, c.regular - c.total);
			savingsRow.hidden = savings <= 0;
			if (savings > 0) document.getElementById('bm-savings').textContent = money(savings);

			breakdown.innerHTML = c.cabins.map(function (cb, n) {
				var who = cb.adults + ' বড়';
				if (cb.c48) who += ' + ' + cb.c48 + ' শিশু(৪–৮)';
				if (cb.c04) who += ' + ' + cb.c04 + ' শিশু(০–৪ ফ্রি)';
				var line = cb.adults * occRate(cb.size, dt) + Math.ceil(cb.c48 * occRate(cb.size, dt) * (bhelaBM.childPercent / 100));
				return '<div class="bm-bd-line"><span>কেবিন ' + (n + 1) + ' (' + cb.size + ' জন)<small>' + who + '</small></span><strong>' + money(line) + '</strong></div>';
			}).join('');

			priceBox.hidden = false;
			if (emptyMsg) emptyMsg.hidden = true;
			updateMobileBar(money(c.total), dt);
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

		[gAdults, gC48, gC04].forEach(function (el) {
			el.addEventListener('change', function () { selectedIndex = 0; showAllOptions = false; calc(); });
		});
		if (fullBoatEl) fullBoatEl.addEventListener('change', calc);
		if (editToggle) editToggle.addEventListener('click', openEdit);
		if (editClose) editClose.addEventListener('click', closeEdit);
		if (editAdd) editAdd.addEventListener('click', function () {
			if (editRows.children.length < MAX_CABINS) addBuilderRow();
			calc();
		});
		if (discountToggle) discountToggle.addEventListener('click', function () {
			discountBody.hidden = !discountBody.hidden;
			discountToggle.classList.toggle('is-on', !discountBody.hidden);
		});

		var urlDate = new URLSearchParams(window.location.search).get('date');
		if (urlDate && /^\d{4}-\d{2}-\d{2}$/.test(urlDate)) dateEl.value = urlDate;

		dateEl.addEventListener('change', function () { resetAvailability(); calc(); });

		wrap.setAttribute('data-mstep', '1');
		resetAvailability();
		calc();
		syncMode();
		if (mq.addEventListener) mq.addEventListener('change', syncMode);
		else if (mq.addListener) mq.addListener(syncMode);

		/* ---------- Tabs (Book / Track) + post-submit persistence ---------- */

		var tabsEl = document.getElementById('bm-tabs');
		var bookPanel = document.getElementById('bm-book-panel');
		var trackPanel = document.getElementById('bm-track-panel');
		var doneBox = document.getElementById('bm-done');
		var STORE_KEY = 'bhela_bm_last_booking';

		function getStored() { try { return JSON.parse(localStorage.getItem(STORE_KEY) || 'null'); } catch (e) { return null; } }
		function setStored(o) { try { localStorage.setItem(STORE_KEY, JSON.stringify(o)); } catch (e) {} }
		function clearStored() { try { localStorage.removeItem(STORE_KEY); } catch (e) {} }

		function setTab(name) {
			var track = name === 'track';
			if (tabsEl) tabsEl.querySelectorAll('.bhela-bm-tab').forEach(function (t) {
				t.classList.toggle('is-active', t.getAttribute('data-tab') === name);
			});
			var showDoneNow = !track && !!getStored();
			if (trackPanel) trackPanel.hidden = !track;
			if (doneBox) doneBox.hidden = track ? true : !showDoneNow;
			if (bookPanel) bookPanel.hidden = track ? true : showDoneNow;
		}

		function renderDone(opts) {
			if (!doneBox) return;
			var title = opts.recent ? '🛶 আপনার সর্বশেষ বুকিং' : '🎉 ' + (opts.message || 'বুকিং রিকোয়েস্ট জমা হয়েছে');
			var btns = '';
			if (opts.whatsapp_url) btns += '<a class="bhela-bm-btn" href="' + opts.whatsapp_url + '" target="_blank" rel="noopener">💬 WhatsApp</a>';
			if (opts.invoice_url) btns += '<a class="bhela-bm-btn bhela-bm-btn--invoice" href="' + opts.invoice_url + '" target="_blank" rel="noopener">🧾 ইনভয়েস</a>';
			doneBox.innerHTML =
				'<div class="bm-done__card">' +
					'<div class="bm-done__title">' + title + '</div>' +
					(opts.invoice_no ? '<div class="bm-done__inv">Booking No: <strong>' + opts.invoice_no + '</strong></div>' : '') +
					'<div class="bm-done__status" id="bm-done-status">স্ট্যাটাস দেখা হচ্ছে…</div>' +
					'<div class="bm-done__btns">' + btns + '</div>' +
					'<button type="button" class="bm-newbooking" id="bm-newbooking">＋ নতুন বুকিং করুন</button>' +
				'</div>';
			var nb = document.getElementById('bm-newbooking');
			if (nb) nb.addEventListener('click', function () { clearStored(); setTab('book'); });
			if (opts.invoice_no) {
				fetchTrack(opts.invoice_no).then(function (res) {
					var el = document.getElementById('bm-done-status');
					if (!el) return;
					if (res.success && res.data && res.data.found && res.data.bookings[0]) {
						var b = res.data.bookings[0];
						el.innerHTML = trackChip(b.status_label, b.status_color) + ' <span class="bm-done__meta">' + (b.travel_date || '') + ' · বাকি ' + fmt(b.due) + '</span>';
					} else { el.textContent = ''; }
				}).catch(function () { var el = document.getElementById('bm-done-status'); if (el) el.textContent = ''; });
			}
		}

		if (tabsEl) tabsEl.querySelectorAll('.bhela-bm-tab').forEach(function (t) {
			t.addEventListener('click', function () { setTab(t.getAttribute('data-tab')); });
		});

		// On load: if a booking is remembered, show its card instead of the form.
		var storedBooking = getStored();
		if (storedBooking && storedBooking.invoice_no) {
			renderDone({ invoice_no: storedBooking.invoice_no, invoice_url: storedBooking.invoice_url, whatsapp_url: storedBooking.whatsapp_url, recent: true });
		}
		setTab('book');

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
			params.append('cabins', JSON.stringify(fullBoat() ? [] : activeCabins()));
			params.append('full_boat', fullBoat() ? '1' : '');
			params.append('requested_price', fd.get('requested_price') || '');
			params.append('discount_msg', fd.get('discount_msg') || '');

			fetch(bhelaBM.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params.toString()
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data.success) {
						var d = data.data;
						// Remember the booking and replace the form with a status card.
						setStored({ invoice_no: d.invoice_no, invoice_url: d.invoice_url, whatsapp_url: d.whatsapp_url });
						renderDone({ message: d.message, invoice_no: d.invoice_no, invoice_url: d.invoice_url, whatsapp_url: d.whatsapp_url, recent: false });
						response.innerHTML = '';
						setTab('book');
						try { window.scrollTo({ top: wrap.offsetTop - 12, behavior: 'smooth' }); } catch (e) {}
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
					if (response.innerHTML) response.scrollIntoView({ behavior: 'smooth', block: 'center' });
				});
		});
	});
})();
