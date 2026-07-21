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

		// HTML-escape any string before it is placed into innerHTML. Server values
		// are sanitized today; this makes the client robust regardless.
		function esc(s) {
			return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}

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
			color = /^#[0-9a-fA-F]{3,8}$/.test(color) ? color : '#555d66';
			return '<span class="bm-status-chip" style="background:' + color + '1a;color:' + color + ';border:1px solid ' + color + '55">' + esc(label || '—') + '</span>';
		}

		function trackCardHtml(b) {
			var rows = [
				['নাম', esc(b.name)], ['তারিখ', esc(b.travel_date || '—')], ['কেবিন', esc(b.cabin || '—')],
				['অতিথি', (b.guests || 0) + ' জন'], ['মোট', fmt(b.total)], ['অগ্রিম', fmt(b.advance)],
				['পরিশোধিত', fmt(b.paid)], ['বাকি', fmt(b.due)]
			].map(function (r) { return '<div class="bm-tc__row"><span>' + r[0] + '</span><strong>' + r[1] + '</strong></div>'; }).join('');
			return '<div class="bm-trackcard">' +
				'<div class="bm-trackcard__head"><strong>' + esc(b.invoice_no || '—') + '</strong>' + trackChip(b.status_label, b.status_color) + '</div>' +
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
					result.innerHTML = '<div class="bm-track__msg">মোবাইল নম্বর বা ইমেইল সঠিকভাবে দিন।</div>';
					return;
				}
				btn.disabled = true; btn.textContent = '⏳ খুঁজছি...';
				fetchTrack(val).then(function (res) {
					if (res.success && res.data && res.data.found) {
						result.innerHTML = res.data.bookings.map(trackCardHtml).join('');
					} else {
						var d = (res && res.data) || {};
						var waNum = String(d.whatsapp || '').replace(/[^0-9]/g, '');
						var wa = waNum ? '<a class="bhela-bm-btn" href="https://wa.me/' + waNum + '" target="_blank" rel="noopener">💬 WhatsApp-এ জিজ্ঞেস করুন</a>' : '';
						result.innerHTML = '<div class="bm-track__msg bm-track__msg--none">' + esc(d.message || 'কোনো বুকিং পাওয়া যায়নি।') + (wa ? '<br>' + wa : '') + '</div>';
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
		var availableCabins = MAX_CABINS; // narrowed per-date by the availability check
		function cabinCap() { return Math.max(0, Math.min(MAX_CABINS, availableCabins)); }

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

		/**
		 * Normalise a BD mobile to 01XXXXXXXXX, or '' when it is not valid.
		 * Mirrors bhela_bm_normalize_mobile() in PHP — keep the two in step.
		 */
		function normalizeMobile(raw) {
			var d = String(raw || '').replace(/[^0-9]/g, '');
			if (d.indexOf('00880') === 0) d = d.slice(5);
			else if (d.indexOf('880') === 0) d = d.slice(3);
			if (d.length === 10 && d.charAt(0) === '1') d = '0' + d;
			return /^01[3-9][0-9]{8}$/.test(d) ? d : '';
		}

		// Live feedback on the phone field.
		var phoneField = document.getElementById('bm-phone');
		if (phoneField) {
			phoneField.addEventListener('blur', function () {
				if (!phoneField.value.trim()) { phoneField.classList.remove('is-invalid'); return; }
				phoneField.classList.toggle('is-invalid', !normalizeMobile(phoneField.value));
			});
			phoneField.addEventListener('input', function () {
				if (normalizeMobile(phoneField.value)) phoneField.classList.remove('is-invalid');
			});
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
				if (current.length >= cabinCap()) return;
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
		 * Fill a size list with people. A 4–8 child gets a bed, so it takes a place
		 * in the cabin exactly like an adult — the sizes here are bodies. What the
		 * child does NOT do is raise the price tier: each cabin is rated on the
		 * ADULTS inside it (`tier`); children then pay a flat fee (bhelaBM.childFee).
		 *
		 * A cabin is only opened for ADULTS: every cabin needs at least MIN_CAP (2)
		 * adults, so children can never justify an extra cabin — 2 adults + 3
		 * children is one cabin, and two cabins require four adults. Layouts that
		 * break that rule are rejected by returning null. 0–4 infants share with
		 * their parents and take no place at all.
		 */
		function fillCombo(sizes, adults, c48, c04) {
			var leftA = adults, leftC = c48;
			// Not enough adults to open this many cabins.
			if (adults < sizes.length * MIN_CAP) return null;
			var cabins = sizes.map(function (size) {
				return { size: size, adults: 0, c48: 0, c04: 0 };
			});
			// Seat the minimum adults in every cabin first.
			cabins.forEach(function (cab) {
				var take = Math.min(MIN_CAP, cab.size, leftA);
				cab.adults = take; leftA -= take;
			});
			if (leftA > 0 || leftC > 0) {
				cabins.forEach(function (cab) {
					var space = cab.size - cab.adults - cab.c48;
					if (space <= 0) return;
					var takeA = Math.min(space, leftA);
					cab.adults += takeA; leftA -= takeA; space -= takeA;
					var takeC = Math.min(space, leftC);
					cab.c48 += takeC; leftC -= takeC;
				});
			}
			// Everyone must fit, and every cabin needs its minimum adults.
			if (leftA > 0 || leftC > 0) return null;
			for (var i = 0; i < cabins.length; i++) {
				if (cabins[i].adults < MIN_CAP) return null;
				// Rate tier = adults in this cabin.
				cabins[i].tier = cabins[i].adults;
			}
			if (c04 > 0 && cabins.length) cabins[0].c04 = c04; // free ride-along, no seat used
			return cabins;
		}

		/** Price a filled combo. `occupants` = paying guests (infants excluded). */
		function priceCombo(cabins, dt, occupants) {
			var total = 0, regular = 0;
			cabins.forEach(function (c) {
				// Priced on the adults in the cabin, not on how many bodies it holds.
				var tier = c.tier || Math.max(c.adults, MIN_CAP);
				var rate = occRate(tier, dt || 'weekend');
				var reg = occRate(tier, 'weekend');
				// 4–8 children pay a flat fee — identical on weekdays and weekends.
				total += c.adults * rate + c.c48 * bhelaBM.childFee;
				regular += c.adults * reg + c.c48 * bhelaBM.childFee;
			});
			return { cabins: cabins, total: total, regular: regular, bodies: occupants,
				perPerson: occupants ? Math.round(total / occupants) : 0 };
		}

		/**
		 * Every valid combination, priced and sorted cheapest-first. The first
		 * (lowest total) is flagged as our suggestion; the guest may pick any.
		 */
		function buildOptions(g, dt) {
			// Cabins must hold every body (4–8 children get a bed too); the price
			// tier of each cabin is then set by the adults inside it.
			var occupants = Math.max(g.adults + g.c48, MIN_CAP);
			var combos = [];
			genCombinations(occupants).forEach(function (sizes) {
				var filled = fillCombo(sizes, g.adults, g.c48, g.c04);
				if (filled) combos.push(priceCombo(filled, dt, g.adults + g.c48));
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
				// Rate comes from the cabin's adult tier, not from how many bodies it holds.
				var tier = cb.tier || Math.max(cb.adults, MIN_CAP);
				return 'কেবিন ' + (n + 1) + ' (' + cb.size + ' জন): ' + money(occRate(tier, dt || 'weekend')) + '/জন';
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
				var occ = c.adults + c.c48;   // bodies sharing the cabin (infants free)
				var tier = Math.max(c.adults, MIN_CAP); // rate tier = adults only
				var infantOnly = occ === 0 && c.c04 > 0;
				// Each cabin needs its minimum adults — children cannot open a cabin.
				var over = (occ > 0 && (occ < 2 || occ > MAX_CAP)) || infantOnly ||
					(occ > 0 && c.adults < MIN_CAP);
				var rowEl = editRows.querySelectorAll('.bm-cabin-row')[i];
				if (rowEl) {
					rowEl.classList.toggle('is-over', over);
					var tEl = rowEl.querySelector('.bm-row-total');
					if (tEl) tEl.textContent = (!over && occ >= 2 && occ <= MAX_CAP && dt)
						? money(c.adults * occRate(tier, dt) + c.c48 * bhelaBM.childFee)
						: '—';
				}
				if (over) badCabin = true;
				adults += c.adults; occupants += occ;
			});
			var msg = '';
			if (badCabin) msg = '⚠️ প্রতিটি কেবিনে অন্তত ' + MIN_CAP + ' জন বড় (৯+) এবং সর্বোচ্চ ' + MAX_CAP + ' জন (শিশু ০–৪ বাদে) থাকতে হবে।';
			else if (adults < MIN_CAP) msg = '⚠️ অন্তত ' + MIN_CAP + ' জন বড় (৯+) থাকতে হবে।';
			else if (cabins.length * MIN_CAP > adults) msg = '⚠️ ' + cabins.length + 'টি কেবিনের জন্য অন্তত ' + (cabins.length * MIN_CAP) + ' জন বড় (৯+) প্রয়োজন।';
			else if (occupants < 2) msg = '⚠️ অন্তত ২ জন প্রয়োজন।';
			else if (cabins.length > cabinCap()) msg = availableCabins < MAX_CABINS
				? '⚠️ এই তারিখে মাত্র ' + availableCabins + 'টি কেবিন খালি।'
				: '⚠️ সর্বোচ্চ ' + MAX_CABINS + 'টি কেবিন।';
			else if (occupants > MAX_GUESTS) msg = '⚠️ সর্বোচ্চ ' + MAX_GUESTS + ' জন।';
			var priced = null;
			if (!msg && dt) {
				// Bodies fill the cabin; the tier is set by its adults only.
				var filled = cabins.map(function (c) {
					return {
						size: c.adults + c.c48,
						tier: Math.max(c.adults, MIN_CAP),
						adults: c.adults, c48: c.c48, c04: c.c04
					};
				});
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

			// No combination fits (e.g. group needs more cabins than are free
			// on this date) — block progress instead of keeping stale options.
			if (!currentOptions.length) {
				if (guestError) {
					guestError.hidden = false;
					guestError.textContent = availableCabins < MAX_CABINS
						? '⚠️ এই তারিখে মাত্র ' + availableCabins + 'টি কেবিন খালি — এত জনের জায়গা হবে না। অতিথি কমান বা অন্য তারিখ বাছাই করুন।'
						: '⚠️ এই সংখ্যক অতিথির কম্বিনেশন সম্ভব নয়।';
				}
				if (next2) next2.disabled = true;
				if (submitBtn) submitBtn.disabled = true;
				priceBox.hidden = true;
				if (emptyMsg) emptyMsg.hidden = false;
				updateMobileBar('', dt);
				return;
			}

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
				// Priced on the cabin's adult tier — must match priceCombo exactly,
				// otherwise this line and the grand total disagree.
				var tier = cb.tier || Math.max(cb.adults, MIN_CAP);
				var rate = occRate(tier, dt);
				var line = cb.adults * rate + cb.c48 * bhelaBM.childFee;
				return '<div class="bm-bd-line"><span>কেবিন ' + (n + 1) + ' (' + cb.size + ' জন)<small>' + who + ' · ' + money(rate) + '/জন' + (cb.c48 ? ' · শিশু ' + money(bhelaBM.childFee) + '/জন' : '') + '</small></span><strong>' + money(line) + '</strong></div>';
			}).join('');

			priceBox.hidden = false;
			if (emptyMsg) emptyMsg.hidden = true;
			updateMobileBar(money(c.total), dt);
		}

		/* ---------- Mobile step wizard ---------- */

		var mq = window.matchMedia('(max-width: 860px)');
		var mBar = null, mBarPrice = null, mBarAction = null;

		function buildMobileBar() {
			if (mBar) return;
			mBar = document.createElement('div');
			mBar.className = 'bm-mobilebar';
			mBar.innerHTML = '<div class="bm-mobilebar__price"><small>মোট</small><strong id="bm-mbar-total">—</strong></div>' +
				'<button type="button" class="bm-mobilebar__action" id="bm-mbar-action">পরবর্তী →</button>';
			mBarPrice = mBar.querySelector('#bm-mbar-total');
			mBarAction = mBar.querySelector('#bm-mbar-action');
			mBarAction.addEventListener('click', function () {
				var n = currentStep();
				if (n < 3) {
					var nx = form.querySelector('.bhela-bm-step[data-step="' + n + '"] .bm-next');
					if (nx && !nx.disabled) { nx.click(); }
				} else if (submitBtn && !submitBtn.disabled) {
					submitBtn.click();
				}
			});
			wrap.appendChild(mBar);
		}

		function updateMobileBar(text, dt) {
			if (!mBarPrice) return;
			mBarPrice.textContent = text || '—';
		}

		function setStep(n, noScroll) {
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
			if (mBar) mBar.classList.toggle('is-shown', n >= 2);
			if (mBarAction) mBarAction.textContent = n >= 3 ? 'রিকোয়েস্ট পাঠান →' : 'পরবর্তী →';
			if (!noScroll) { try { window.scrollTo({ top: wrap.offsetTop - 12, behavior: 'smooth' }); } catch (e) { window.scrollTo(0, 0); } }
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
			availableCabins = MAX_CABINS;
			if (next1) next1.disabled = true;
			if (blockedBox) blockedBox.hidden = true;
		}

		// Availability could not be verified (expired nonce / server hiccup).
		// Unblock the user with an amber advisory instead of a dead-end — the
		// date is not known-booked, and custom dates are bookable regardless.
		function softAllow(msg) {
			availBox.hidden = false;
			availableCabins = MAX_CABINS;
			availChecked = true;
			if (next1) next1.disabled = false;
			if (blockedBox) blockedBox.hidden = true;
			availBox.innerHTML = '<span class="bm-avail-chip" style="background:#FFF7ED;color:#b45309;border:1px solid #b4530955">⚠️ ' + esc(msg) + '</span>';
			calc();
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
							availableCabins = booked ? 0
								: (typeof d.available === 'number' ? d.available : MAX_CABINS);
							var color = /^#[0-9a-fA-F]{3,8}$/.test(d.color) ? d.color : '#996800';
							var html = '<span class="bm-avail-chip" style="background:' + color + '1a;color:' + color + ';border:1px solid ' + color + '55">' + esc(d.label) + '</span>';
							if (!booked && typeof d.available === 'number' && d.trip) {
								var cabColor = d.available > 2 ? '#1a7f37' : '#b45309';
								html += ' <span class="bm-avail-chip" style="background:' + cabColor + '1a;color:' + cabColor + ';border:1px solid ' + cabColor + '55">🛏️ ' + d.total + 'টির মধ্যে ' + d.available + 'টি কেবিন খালি</span>';
							}
							if (d.trip) html += ' <span class="bm-avail-trip">📅 ' + esc(d.trip) + '</span>';
							if (d.note) html += '<div class="bm-avail-note">' + esc(d.note) + '</div>';
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
							// A success:false here is only an infra failure (expired
							// nonce / server hiccup) — never a real "full" signal, which
							// arrives as success:true+status:booked. Don't trap the user:
							// availability is advisory (custom dates are always bookable),
							// so let them continue and confirm with the team.
							softAllow((data.data && data.data.message) || 'এখন যাচাই করা যায়নি — পেজ রিফ্রেশ করে দেখুন, অথবা এগিয়ে যান, আমরা কনফার্ম করব।');
						}
					})
					.catch(function () {
						availBox.hidden = false;
						softAllow('এখন যাচাই করা যাচ্ছে না — এগিয়ে যান, আমরা তারিখ কনফার্ম করে জানাব।');
					})
					.finally(function () {
						availBtn.disabled = false;
						availBtn.textContent = '🔄 আবার চেক করুন';
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

		var availTimer = null;
			dateEl.addEventListener('change', function () {
				resetAvailability();
				calc();
				window.clearTimeout(availTimer);
				if (dateEl.value && availBtn) { availTimer = window.setTimeout(function () { availBtn.click(); }, 300); }
			});
			var dateChips = document.getElementById('bm-datechips');
			if (dateChips) {
				dateChips.querySelectorAll('.bm-chip').forEach(function (chip) {
					chip.addEventListener('click', function () {
						dateChips.querySelectorAll('.bm-chip').forEach(function (c) { c.classList.remove('is-on'); });
						chip.classList.add('is-on');
						dateEl.value = chip.getAttribute('data-date');
						dateEl.dispatchEvent(new Event('change', { bubbles: true }));
					});
				});
			}

			/* Deep link from the schedule page: /book-now/?date=YYYY-MM-DD.
			   Assigning .value does NOT fire 'change', so the availability check
			   has to be dispatched explicitly — otherwise the date is filled in
			   but never checked and the guest is stuck on step 1. Runs here,
			   after the change handler above is bound. */
			var urlDate = new URLSearchParams(window.location.search).get('date');
			if (urlDate && /^\d{4}-\d{2}-\d{2}$/.test(urlDate) && (!dateEl.min || urlDate >= dateEl.min)) {
				dateEl.value = urlDate;
				if (dateChips) {
					var urlChip = dateChips.querySelector('.bm-chip[data-date="' + urlDate + '"]');
					if (urlChip) { urlChip.classList.add('is-on'); }
				}
				dateEl.dispatchEvent(new Event('change', { bubbles: true }));
			}
			[gAdults, gC48, gC04].forEach(function (input) {
				if (!input) { return; }
				var field = input.closest('.bhela-bm-field');
				if (!field) { return; }
				var out = document.getElementById(input.getAttribute('data-out'));
				var min = parseInt(input.getAttribute('data-min'), 10) || 0;
				var max = parseInt(input.getAttribute('data-max'), 10) || 99;
				var btns = field.querySelectorAll('.bm-stepper__btn');
				function sync() {
					if (out) { out.textContent = input.value; }
					btns.forEach(function (b) {
						var d = parseInt(b.getAttribute('data-delta'), 10);
						b.disabled = (d < 0 && (+input.value) <= min) || (d > 0 && (+input.value) >= max);
					});
				}
				btns.forEach(function (b) {
					b.addEventListener('click', function () {
						var d = parseInt(b.getAttribute('data-delta'), 10) || 0;
						input.value = Math.max(min, Math.min(max, (parseInt(input.value, 10) || 0) + d));
						sync();
						input.dispatchEvent(new Event('change', { bubbles: true }));
					});
				});
				sync();
			});

		wrap.setAttribute('data-mstep', '1');
		resetAvailability();
		calc();
		buildMobileBar(); setStep(1, true);
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
			// The mobile price bar lives outside #bm-book-panel — hide it whenever
			// the form isn't the active view (track tab or post-submit done card).
			if (mBar && (track || showDoneNow)) mBar.classList.remove('is-shown');
		}

		function renderDone(opts) {
			if (!doneBox) return;
			var title = opts.recent ? '🛶 আপনার সর্বশেষ বুকিং' : '🎉 ' + (opts.message || 'বুকিং রিকোয়েস্ট জমা হয়েছে');
			var btns = '';
			// Both URLs are already fully URL-encoded server-side (rawurlencode /
			// add_query_arg). Do NOT re-encode — encodeURI escapes '%' to '%25',
			// double-encoding the Bangla message so WhatsApp shows garbage.
			if (opts.whatsapp_url) btns += '<a class="bhela-bm-btn" href="' + esc(opts.whatsapp_url) + '" target="_blank" rel="noopener">💬 WhatsApp</a>';
			if (opts.invoice_url) btns += '<a class="bhela-bm-btn bhela-bm-btn--invoice" href="' + esc(opts.invoice_url) + '" target="_blank" rel="noopener">🧾 ইনভয়েস</a>';
			doneBox.innerHTML =
				'<div class="bm-done__card bm-done">' +
					'<div class="tick">✓</div>' +
					'<div class="bm-done__title">' + esc(title) + '</div>' +
					(opts.invoice_no ? '<div class="bm-done__inv">Booking No: <strong>' + esc(opts.invoice_no) + '</strong></div>' : '') +
					'<div class="bm-done__status" id="bm-done-status">স্ট্যাটাস দেখা হচ্ছে…</div>' +
					'<div class="bm-done__btns">' + btns + '</div>' +
					'<button type="button" class="bm-newbooking" id="bm-newbooking">＋ নতুন বুকিং করুন</button>' +
				'</div>';
			var nb = document.getElementById('bm-newbooking');
			if (nb) nb.addEventListener('click', function () { clearStored(); setTab('book'); });
			// Live status is looked up by the customer's OWN phone (tracking no longer
			// accepts the guessable invoice number). No phone → show a static line.
			var statusEl = document.getElementById('bm-done-status');
			if (opts.phone) {
				fetchTrack(opts.phone).then(function (res) {
					if (!statusEl) return;
					if (res.success && res.data && res.data.found && res.data.bookings[0]) {
						var b = res.data.bookings[0];
						statusEl.innerHTML = trackChip(b.status_label, b.status_color) + ' <span class="bm-done__meta">' + esc(b.travel_date || '') + ' · বাকি ' + fmt(b.due) + '</span>';
					} else { statusEl.textContent = ''; }
				}).catch(function () { if (statusEl) statusEl.textContent = ''; });
			} else if (statusEl) {
				statusEl.textContent = 'রিকোয়েস্ট গৃহীত — আমরা শীঘ্রই যোগাযোগ করব।';
			}
		}

		if (tabsEl) tabsEl.querySelectorAll('.bhela-bm-tab').forEach(function (t) {
			t.addEventListener('click', function () { setTab(t.getAttribute('data-tab')); });
		});

		// On load: if a booking is remembered, show its card instead of the form.
		var storedBooking = getStored();
		if (storedBooking && storedBooking.invoice_no) {
			renderDone({ invoice_no: storedBooking.invoice_no, invoice_url: storedBooking.invoice_url, whatsapp_url: storedBooking.whatsapp_url, phone: storedBooking.phone, recent: true });
		}
		setTab('book');

		/* ---------- Submit ---------- */

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			response.innerHTML = '';

			// Check the mobile number before spending a request on it — this is
			// the only number the team has to call the guest back on.
			var phoneEl = document.getElementById('bm-phone');
			if (phoneEl && !normalizeMobile(phoneEl.value)) {
				response.innerHTML = '<div class="bhela-bm-error">⚠️ সঠিক মোবাইল নম্বর দিন — ১১ সংখ্যার, ০১ দিয়ে শুরু (যেমন ০১৭১২৩৪৫৬৭৮)।</div>';
				phoneEl.classList.add('is-invalid');
				phoneEl.focus();
				return;
			}

			submitBtn.disabled = true;
			submitBtn.classList.add('is-loading');

			var fd = new FormData(form);
			var params = new URLSearchParams();
			params.append('action', 'bhela_bm_submit');
			params.append('nonce', bhelaBM.nonce);
			['name', 'phone', 'email', 'date', 'message', 'bhela_bm_hp'].forEach(function (f) {
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
						setStored({ invoice_no: d.invoice_no, invoice_url: d.invoice_url, whatsapp_url: d.whatsapp_url, phone: fd.get('phone') || '' });
						renderDone({ message: d.message, invoice_no: d.invoice_no, invoice_url: d.invoice_url, whatsapp_url: d.whatsapp_url, phone: fd.get('phone') || '', recent: false });
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
