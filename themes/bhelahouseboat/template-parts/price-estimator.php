<?php
/**
 * Template Part: Price Estimator
 *
 * @package BhelaHouseboat
 */
?>

<div class="price-estimator reveal" id="price-estimator">
    <h3 class="price-estimator__title">💰 ট্রিপের খরচ হিসাব করুন</h3>

    <div class="price-estimator__form">
        <div class="price-estimator__field">
            <label for="estimator-cabin">কেবিন টাইপ</label>
            <select id="estimator-cabin">
                <option value="">কেবিন নির্বাচন করুন</option>
                <option value="budget" data-holiday="8000" data-weekday="6400" data-sharing="6">🟢 Budget Friendly (৬ জন)</option>
                <option value="comfort" data-holiday="9000" data-weekday="7200" data-sharing="5">🔵 Comfort (৫ জন)</option>
                <option value="deluxe" data-holiday="10000" data-weekday="8000" data-sharing="4">🟡 Double Deluxe (৪ জন)</option>
                <option value="luxury" data-holiday="12000" data-weekday="9600" data-sharing="3">🟣 Luxury Triple (৩ জন)</option>
                <option value="couple" data-holiday="13000" data-weekday="10400" data-sharing="2">🔴 Exclusive Couple (২ জন)</option>
            </select>
        </div>

        <div class="price-estimator__field">
            <label for="estimator-guests">অতিথি সংখ্যা</label>
            <select id="estimator-guests">
                <option value="">সংখ্যা নির্বাচন করুন</option>
                <?php for ( $i = 2; $i <= 40; $i++ ) : ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?> জন</option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="price-estimator__field">
            <label for="estimator-daytype">দিনের ধরন</label>
            <select id="estimator-daytype">
                <option value="holiday">Holiday / Weekend</option>
                <option value="weekday">Weekday (−২০% ছাড়)</option>
            </select>
        </div>
    </div>

    <!-- Result -->
    <div class="price-estimator__result" id="estimator-result">
        <div class="price-estimator__result-grid">
            <div class="price-estimator__result-item">
                <div class="price-estimator__result-label">জনপ্রতি খরচ</div>
                <div class="price-estimator__result-value" id="result-per-person">৳০</div>
            </div>
            <div class="price-estimator__result-item">
                <div class="price-estimator__result-label">মোট খরচ</div>
                <div class="price-estimator__result-value price-estimator__result-value--highlight" id="result-total">৳০</div>
            </div>
            <div class="price-estimator__result-item">
                <div class="price-estimator__result-label">৫০% অগ্রিম</div>
                <div class="price-estimator__result-value" id="result-advance">৳০</div>
            </div>
        </div>

        <a href="#" class="btn btn--whatsapp btn--lg" id="estimator-book-btn" target="_blank" rel="noopener">
            <span class="btn__icon">💬</span> WhatsApp এ বুক করুন
        </a>
    </div>
</div>
