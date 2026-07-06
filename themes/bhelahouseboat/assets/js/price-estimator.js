/**
 * BHELA Houseboat — Price Estimator
 * Interactive cabin price calculator
 *
 * @package BhelaHouseboat
 */

document.addEventListener('DOMContentLoaded', function () {
    const cabinSelect = document.getElementById('estimator-cabin');
    const guestsSelect = document.getElementById('estimator-guests');
    const dayTypeSelect = document.getElementById('estimator-daytype');
    const resultBox = document.getElementById('estimator-result');
    const resultPerPerson = document.getElementById('result-per-person');
    const resultTotal = document.getElementById('result-total');
    const resultAdvance = document.getElementById('result-advance');
    const bookBtn = document.getElementById('estimator-book-btn');

    if (!cabinSelect || !guestsSelect || !dayTypeSelect) return;

    function calculate() {
        const cabinOption = cabinSelect.options[cabinSelect.selectedIndex];
        const guests = parseInt(guestsSelect.value);
        const dayType = dayTypeSelect.value;

        if (!cabinSelect.value || !guests) {
            resultBox.classList.remove('visible');
            return;
        }

        const holidayRate = parseInt(cabinOption.dataset.holiday);
        const weekdayRate = parseInt(cabinOption.dataset.weekday);
        const sharing = parseInt(cabinOption.dataset.sharing);

        const perPerson = dayType === 'weekday' ? weekdayRate : holidayRate;
        const total = perPerson * guests;
        const advance = Math.ceil(total / 2);

        // Format with Bangla numerals
        resultPerPerson.textContent = window.bhelaFormatCurrency ? window.bhelaFormatCurrency(perPerson) : '৳' + perPerson.toLocaleString('en-IN');
        resultTotal.textContent = window.bhelaFormatCurrency ? window.bhelaFormatCurrency(total) : '৳' + total.toLocaleString('en-IN');
        resultAdvance.textContent = window.bhelaFormatCurrency ? window.bhelaFormatCurrency(advance) : '৳' + advance.toLocaleString('en-IN');

        // Update WhatsApp link
        const cabinName = cabinOption.textContent.trim();
        const dayLabel = dayType === 'weekday' ? 'Weekday' : 'Holiday/Weekend';
        const message = `আসসালামু আলাইকুম,\n\nআমি ভেলা হাউসবোটে বুকিং করতে চাই:\n\n📌 কেবিন: ${cabinName}\n👥 অতিথি: ${guests} জন\n📅 ধরন: ${dayLabel}\n💰 জনপ্রতি: ৳${perPerson.toLocaleString('en-IN')}\n💵 মোট: ৳${total.toLocaleString('en-IN')}\n\nঅনুগ্রহ করে তারিখ ও বিস্তারিত জানাবেন।`;

        const whatsappNumber = (typeof bhelaData !== 'undefined' && bhelaData.whatsapp) ? bhelaData.whatsapp : '+8801793395556';
        const cleanNumber = whatsappNumber.replace(/[^0-9]/g, '');
        bookBtn.href = `https://wa.me/${cleanNumber}?text=${encodeURIComponent(message)}`;

        resultBox.classList.add('visible');
    }

    cabinSelect.addEventListener('change', calculate);
    guestsSelect.addEventListener('change', calculate);
    dayTypeSelect.addEventListener('change', calculate);
});
