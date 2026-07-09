/**
 * BHELA Booking Engine — live price calculation + AJAX submission (UX v2).
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('bhela-bm-form');
		if (!form || typeof bhelaBM === 'undefined') return;

		var cabin = document.getElementById('bm-cabin');
		var guests = document.getElementById('bm-guests');
		var dateEl = document.getElementById('bm-date');
		var priceBox = document.getElementById('bhela-bm-price');
		var emptyMsg = document.getElementById('bhela-bm-empty');
		var savingsRow = document.getElementById('bm-savings-row');
		var response = document.getElementById('bhela-bm-response');
		var submitBtn = document.getElementById('bhela-bm-submit');

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

		function bump(id) {
			var el = document.getElementById(id);
			if (!el) return;
			var row = el.closest('.bhela-bm-price__row');
			if (!row) return;
			row.classList.remove('bump');
			void row.offsetWidth;
			row.classList.add('bump');
		}

		function calc() {
			var key = cabin.value;
			var g = parseInt(guests.value, 10) || 0;
			var dt = dayType(dateEl.value);
			if (!key || !g || !dt || !bhelaBM.rates[key]) {
				priceBox.hidden = true;
				if (emptyMsg) emptyMsg.hidden = false;
				return;
			}
			var rate = bhelaBM.rates[key];
			var per = dt === 'weekday' ? rate.weekday : rate.regular;
			var total = per * g;
			var advance = Math.ceil(total * (bhelaBM.advancePercent / 100));
			var savings = dt === 'weekday' ? (rate.regular - rate.weekday) * g : 0;

			var labels = { weekday: 'Weekday −20% 🔥', weekend: 'Weekend', holiday: 'সরকারি ছুটি' };
			document.getElementById('bm-daytype').textContent = labels[dt];
			document.getElementById('bm-per').textContent = money(per);
			document.getElementById('bm-guests-echo').textContent = g;
			document.getElementById('bm-total').textContent = money(total);
			document.getElementById('bm-advance').textContent = money(advance);

			if (savingsRow) {
				savingsRow.hidden = savings <= 0;
				if (savings > 0) document.getElementById('bm-savings').textContent = money(savings);
			}

			priceBox.hidden = false;
			if (emptyMsg) emptyMsg.hidden = true;
			bump('bm-advance');
		}

		[cabin, guests, dateEl].forEach(function (el) {
			el.addEventListener('change', calc);
			el.addEventListener('input', calc);
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			response.innerHTML = '';
			submitBtn.disabled = true;
			submitBtn.classList.add('is-loading');

			var fd = new FormData(form);
			var params = new URLSearchParams();
			params.append('action', 'bhela_bm_submit');
			params.append('nonce', bhelaBM.nonce);
			['name', 'phone', 'email', 'date', 'cabin', 'guests', 'message'].forEach(function (f) {
				params.append(f, fd.get(f) || '');
			});

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
						form.reset();
						priceBox.hidden = true;
						if (emptyMsg) emptyMsg.hidden = false;
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
