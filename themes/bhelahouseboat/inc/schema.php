<?php
/**
 * Schema / Structured Data for BHELA Houseboat
 *
 * @package BhelaHouseboat
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Output FAQPage schema on FAQ page
 */
function bhela_faq_schema() {
    if ( ! is_page_template( 'page-templates/template-faq.php' ) && ! is_front_page() ) {
        return;
    }

    $faqs = bhela_get_faqs();
    $faq_items = array();

    foreach ( $faqs as $category => $questions ) {
        foreach ( $questions as $faq ) {
            $faq_items[] = array(
                '@type'          => 'Question',
                'name'           => $faq['q'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $faq['a'],
                ),
            );
        }
    }

    $schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faq_items,
    );

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}
add_action( 'wp_head', 'bhela_faq_schema' );

/**
 * Output TouristTrip schema on homepage
 */
function bhela_tourist_trip_schema() {
    if ( ! is_front_page() ) return;

    $schema = array(
        '@context'    => 'https://schema.org',
        '@type'       => 'TouristTrip',
        'name'        => 'BHELA – Tanguar Haor Premium Houseboat Trip',
        'description' => 'Premium 2 Days 1 Night houseboat trip on Tanguar Haor with AC cabins, traditional food, and guided tour of Jadukata River, Niladri Lake, Barikka Tila & more.',
        'touristType' => array( 'Families', 'Groups', 'Couples', 'Corporate Teams' ),
        'itinerary'   => array(
            '@type'               => 'ItemList',
            'numberOfItems'       => 7,
            'itemListElement'     => array(
                array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Tanguar Haor' ),
                array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Jadukata River' ),
                array( '@type' => 'ListItem', 'position' => 3, 'name' => 'Barikka Tila' ),
                array( '@type' => 'ListItem', 'position' => 4, 'name' => 'Niladri Lake' ),
                array( '@type' => 'ListItem', 'position' => 5, 'name' => 'Watch Tower' ),
                array( '@type' => 'ListItem', 'position' => 6, 'name' => 'Shimul Bagan' ),
                array( '@type' => 'ListItem', 'position' => 7, 'name' => 'Tekerghat' ),
            ),
        ),
        'offers' => array(
            '@type'         => 'AggregateOffer',
            'lowPrice'      => '6400',
            'highPrice'     => '13000',
            'priceCurrency' => 'BDT',
            'offerCount'    => 5,
        ),
        'provider' => array(
            '@type'     => 'Organization',
            'name'      => 'BHELA – The Haor Exclusive',
            'email'     => 'infobhela@gmail.com',
            'telephone' => '+8801891562461',
        ),
    );

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}
add_action( 'wp_head', 'bhela_tourist_trip_schema' );

/**
 * Output LocalBusiness schema in footer
 */
function bhela_local_business_schema() {
    if ( ! is_front_page() ) return;

    $schema = array(
        '@context'    => 'https://schema.org',
        '@type'       => 'TouristAttraction',
        'name'        => 'BHELA – The Haor Exclusive',
        'description' => 'Premium Family & Group Friendly AC Houseboat on Tanguar Haor, Sunamganj. 6 Family Cabins, AC, Attached Washroom, Rooftop Lounge, Traditional Food.',
        'image'       => BHELA_URI . '/assets/images/logo.png',
        'telephone'   => '+8801891562461',
        'email'       => 'infobhela@gmail.com',
        'address'     => array(
            '@type'           => 'PostalAddress',
            'addressLocality' => 'Sunamganj',
            'addressRegion'   => 'Sylhet',
            'addressCountry'  => 'BD',
        ),
        'geo' => array(
            '@type'     => 'GeoCoordinates',
            'latitude'  => 25.1025,
            'longitude' => 91.0611,
        ),
    );

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}
add_action( 'wp_footer', 'bhela_local_business_schema' );
