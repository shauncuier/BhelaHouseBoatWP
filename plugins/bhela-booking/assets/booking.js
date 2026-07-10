/**
 * BHELA Booking Engine v3 — multi-cabin builder, child rates, availability check.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('bhela-bm-form');
		if (!form || typeof bhelaBM === 'undefined') return;

		var dateEl = document.getElementById('bm-date');
		var rowsBox = document.getElementById('bm-cabin-rows');
		var addBtn = document.getElementById('bm-add-cabin');
		var priceBox = document.getElementById('bhela-bm-price');
		var emptyMsg = document.getElementById('bhela-bm-empty');
		var breakdown = document.getElementById('bm-breakdown');
		var savingsRow = document.getElementById('bm-savings-row');
		var response = document.getElementById('bhela-bm-response');
		var submitBtn = document.getElementById('bhela-bm-submit');
		var availBtn = document.getElementById('bm-check-avail');
		var availBox = document.getElementById('bm-avail-result');

		function money(n) {
			return '৳' + Number(n).toLocaleString('en-IN');
		}

		function dayType(dateStr) {
			if (!dateStr) return null;
			if (bhelaBM.holidays.indexOf(dateStr) !== -1) return 'holiday';
			var d = new Date(dateStr + 'T00:00:00');
			if (isNaN(d)) return null;
			return bhelaBM.weekendDays.indexOf(d.getDay()) !== -1 ? 'weekend' : 'weekday';
		}

		/* ---------- Cabin rows ---------- */

		function countOptions(max, selected) {
			var out = '';
			for (var i = 0; i <= max; i++) {
				out += '<option value="' + i + '"' + (i === selected ? ' selected' : '') + '>' + i + '</option>';
			}
			return out;
		}

		function typeOptions(selected) {
			var out = '';
			Object.keys(bhelaBM.rates).forEach(function (key) {
				var r = bhelaBM.rates[key];
				out += '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>' + r.label + ' — ' + money(r.regular) + '/জন</option>';
			});
			return out;
		}

		function addRow(typeKey, adults) {
			var row = document.createElement('div');
			row.className = 'bm-cabin-row';
			var t = typeKey || 'deluxe';
			var cap = (bhelaBM.rates[t] || {}).sharing || 6;
			row.innerHTML =
				'<div class="bm-cabin-row__type"><label>কেবিন</label><select class="bm-ctype">' + typeOptions(t) + '</select></div>' +
				'<div class="bm-cabin-row__n"><label>বড় (৯+)</label><select class="bm-adults">' + countOptions(6, adults || Math.min(cap, 2)) + '</select></div>' +
				'<div class="bm-cabin-row__n"><label>শিশু ৪–৮</label><select class="bm-c48">' + countOptions(4, 0) + '</select></div>' +
				'<div class="bm-cabin-row__n"><label>শিশু ০–৪</label><select class="bm-c04">' + countOptions(4, 0) + '</select></div>' +
				'<div class="bm-cabin-row__price"><span class="bm-row-total">—</span></div>' +
				'<button type="button" class="bm-cabin-row__remove" aria-label="Remove">✕</button>';
			rowsBox.appendChild(row);

			row.querySelector('.bm-cabin-row__remove').addEventListener('click', function () {
				if (rowsBox.children.length > 1) {
					row.remove();
				} else {
					row.querySelector('.bm-adults').value = 0;
					row.querySelector('.bm-c48').value = 0;
					row.querySelector('.bm-c04').value = 0;
				}
				calc();
			});
			row.querySelectorAll('select').forEach(function (el) {
				el.addEventListener('change', calc);
			});
			calc();
		}

		function collectCabins() {
			var cabins = [];
			rowsBox.querySelectorAll('.bm-cabin-row').forEach(function (row) {
				cabins.push({
					type: row.querySelector('.bm-ctype').value,
					adults: parseInt(row.querySelector('.bm-adults').value, 10) || 0,
					c48: parseInt(row.querySelector('.bm-c48').value, 10) || 0,
					c04: parseInt(row.querySelector('.bm-c04').value, 10) || 0
				});
			});
			return cabins;
		}

		/* ---------- Calculation ---------- */

		function calc() {
			var dt = dayType(dateEl.value);
			var cabins = collectCabins();
			var total = 0, regularTotal = 0, guests = 0;
			var lines = [];

			rowsBox.querySelectorAll('.bm-cabin-row').forEach(function (row, i) {
				var c = cabins[i];
				var r = bhelaBM.rates[c.type];
				var totalEl = row.querySelector('.bm-row-total');
				var over = r && (c.adults + c.c48 + c.c04) > r.sharing;
				row.classList.toggle('is-over', !!over);
				if (!r || !dt || (c.adults + c.c48) < 1) {
					totalEl.textContent = '—';
					if (r && c.c04 > 0 && (c.adults + c.c48) < 1) guests += c.c04;
					return;
				}
				var rate = dt === 'weekday' ? r.weekday : r.regular;
				var lineTotal = c.adults * rate + Math.ceil(c.c48 * rate * (bhelaBM.childPercent / 100));
				var lineRegular = c.adults * r.regular + Math.ceil(c.c48 * r.regular * (bhelaBM.childPercent / 100));
				total += lineTotal;
				regularTotal += lineRegular;
				guests += c.adults + c.c48 + c.c04;
				totalEl.textContent = money(lineTotal);
				var who = c.adults + ' বড়';
				if (c.c48) who += ' + ' + c.c48 + ' শিশু(৪–৮)';
				if (c.c04) who += ' + ' + c.c04 + ' শিশু(০–৪)';
				lines.push({ label: r.label.split('(')[0].trim(), who: who, total: lineTotal });
			});

			if (!dt || total < 1) {
				priceBox.hidden = true;
				if (emptyMsg) emptyMsg.hidden = false;
				return;
			}

			var labels = { weekday: 'Weekday −20% 🔥', weekend: 'Weekend', holiday: 'সরকারি ছুটি' };
			document.getElementById('bm-daytype').textContent = labels[dt];
			document.getElementById('bm-guests-echo').textContent = guests + ' জন';
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
		}

		/* ---------- Availability check ---------- */

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
							var html = '<span class="bm-avail-chip" style="background:' + d.color + '1a;color:' + d.color + ';border:1px solid ' + d.color + '55">' + d.label + '</span>';
							if (d.trip) html += ' <span class="bm-avail-trip">📅 ' + d.trip + '</span>';
							if (d.note) html += '<div class="bm-avail-note">' + d.note + '</div>';
							availBox.innerHTML = html;
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

		addBtn.addEventListener('click', function () { addRow(); });
		addRow('deluxe', 4); // first row

		var urlDate = new URLSearchParams(window.location.search).get('date');
		if (urlDate && /^\d{4}-\d{2}-\d{2}$/.test(urlDate)) {
			dateEl.value = urlDate;
		}
		dateEl.addEventListener('change', calc);
		calc();

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
			params.append('cabins', JSON.stringify(collectCabins()));

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
