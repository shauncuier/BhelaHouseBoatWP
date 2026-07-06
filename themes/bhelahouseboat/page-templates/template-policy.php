<?php
/**
 * Template Name: Policies
 * Template Post Type: page
 *
 * @package BhelaHouseboat
 */

get_header();
?>

<main id="main-content" role="main">

    <div class="page-hero">
        <div class="container">
            <h1>নীতিমালা (Policies)</h1>
            <p>বুকিং, পেমেন্ট, বাতিলকরণ ও অন্যান্য নীতিমালা</p>
        </div>
    </div>

    <section class="section">
        <div class="container container--narrow">
            <div class="policy-content reveal">

                <h2 id="booking">💳 Booking & Payment Policy</h2>
                <ul>
                    <li>মোট মূল্যের ৫০% Advance বুকিংয়ের সময় দিতে হবে</li>
                    <li>বাকি ৫০% Check-in এর সময়</li>
                    <li>Payment Methods: bKash, Nagad, Bank Transfer</li>
                    <li>Advance না দিলে বুকিং কনফার্ম হবে না</li>
                    <li>বুকিং কনফার্মেশন WhatsApp-এ Confirmation Slip পাঠানো হবে</li>
                </ul>

                <h2 id="cancellation">❌ Cancellation & Refund Policy</h2>
                <table>
                    <thead>
                        <tr>
                            <th>সময়সীমা</th>
                            <th>Refund</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>২১+ দিন আগে</td>
                            <td>Advance-এর ৫০% Refund</td>
                        </tr>
                        <tr>
                            <td>৮-২০ দিন আগে</td>
                            <td>Cash Refund নেই, Future Credit/Reschedule বিবেচনাযোগ্য</td>
                        </tr>
                        <tr>
                            <td>৭ দিনের কম</td>
                            <td>কোনো Refund নেই</td>
                        </tr>
                        <tr>
                            <td>No Show</td>
                            <td>সম্পূর্ণ বাতিল, কোনো Refund/Credit নেই</td>
                        </tr>
                    </tbody>
                </table>

                <h2 id="reschedule">🔄 Rescheduling Policy</h2>
                <ul>
                    <li>ট্রিপের কমপক্ষে ৭ দিন আগে লিখিতভাবে (WhatsApp/Email) জানাতে হবে</li>
                    <li>একবার Reschedule সম্ভব (সিট/তারিখ প্রাপ্যতা সাপেক্ষে)</li>
                    <li>Peak Season / Holiday-তে Reschedule সীমিত</li>
                </ul>

                <h2 id="child-policy">👶 Child Policy</h2>
                <table>
                    <thead>
                        <tr>
                            <th>বয়স</th>
                            <th>চার্জ</th>
                            <th>বিবরণ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>০ – ৪ বছর</td>
                            <td>সম্পূর্ণ ফ্রি</td>
                            <td>আলাদা বেড/মিল ছাড়া</td>
                        </tr>
                        <tr>
                            <td>৪ – ৮ বছর</td>
                            <td>৫০% চার্জ</td>
                            <td>—</td>
                        </tr>
                        <tr>
                            <td>৯+ বছর</td>
                            <td>পূর্ণ চার্জ</td>
                            <td>প্রাপ্তবয়স্ক হিসেবে গণ্য</td>
                        </tr>
                    </tbody>
                </table>

                <h2 id="weather">🌧️ Weather Policy</h2>
                <ul>
                    <li>সাধারণ বৃষ্টিতে ট্রিপ হবে — বর্ষায় বৃষ্টিই হাওরের সৌন্দর্য!</li>
                    <li>প্রাকৃতিক দুর্যোগে সম্পূর্ণ রিশিডিউল / ফুল রিফান্ড</li>
                    <li>আংশিক খারাপ আবহাওয়ায় সিদ্ধান্ত ক্যাপ্টেন ও ম্যানেজমেন্ট নেবে</li>
                </ul>

                <h2 id="general">📋 General Terms</h2>
                <ul>
                    <li>বোটে মদ, ধূমপান, শব্দদূষণ কঠোরভাবে নিষিদ্ধ</li>
                    <li>অপরিচিত গ্রুপের সাথে কেবিন শেয়ার করা হয় না</li>
                    <li>বাচ্চাদের নিরাপত্তা অভিভাবকের দায়িত্ব</li>
                    <li>Itinerary আবহাওয়া অনুযায়ী পরিবর্তন হতে পারে</li>
                    <li>ব্যক্তিগত সম্পদের দায়িত্ব যাত্রীর</li>
                </ul>
            </div>
        </div>
    </section>

</main>

<?php get_footer(); ?>
