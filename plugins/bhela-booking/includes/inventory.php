<?php
/**
 * Inventory & Asset Register — lists, the quantity model, monthly periods,
 * the close workflow, and every admin screen.
 *
 * Admin-only. The post types and the lock live in inventory-core.php, which loads
 * on every request — see the header there for why that split is load-bearing.
 *
 * bhela_bm_inv_print() must remain the LAST function in this file: the UI harness
 * asserts on its source with substr() to end-of-file, the same way it does for the
 * cost sheet's print view.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * KINDS
 * ========================================================= */

/**
 * The two kinds of thing in the register.
 *
 * Inventory is consumed; an asset is not. That single difference is what makes
 * `use` an inventory-only movement — see bhela_bm_inv_movement_types().
 *
 * A kind is frozen once the item appears on any monthly sheet, for the same
 * reason a category code is: flipping it retroactively changes what the stored
 * history means.
 */
function bhela_bm_inv_kinds() {
	return array(
		'inventory' => array(
			'label' => __( 'Inventory', 'bhela-booking' ),
			'help'  => __( 'Things that get used up — food, cleaning supplies, fuel, guest amenities.', 'bhela-booking' ),
			'tone'  => 'progress',
		),
		'asset'     => array(
			'label' => __( 'Asset', 'bhela-booking' ),
			'help'  => __( 'Things that stay — engines, ACs, furniture, tools, safety equipment.', 'bhela-booking' ),
			'tone'  => 'neutral',
		),
	);
}

/** An item's kind, defaulting to inventory for a record that never set one. */
function bhela_bm_inv_kind( $item_id ) {
	$kind = get_post_meta( $item_id, '_bhela_inv_kind', true );
	return isset( bhela_bm_inv_kinds()[ $kind ] ) ? $kind : 'inventory';
}

/* =========================================================
 * THE QUANTITY MODEL
 * ========================================================= */

/**
 * Axis 1 — the movements that change how much is on hand.
 *
 * The spec (§4) lists Current/Good/Repairable/Under Repair/Damaged/Lost/Scrap as
 * if they were one set of peers, but §8's closing formula has no damage term and
 * §8 also says repair must not change an asset's count. Those statements only
 * reconcile if there are two axes, so this is axis one and
 * bhela_bm_inv_condition_types() is axis two.
 *
 * Consequences worth stating, because they are the point:
 *
 *   - There is NO damage movement here at all. Damaging something moves it from
 *     `good` to `dam` on axis 2, so §11 ("status, not quantity") holds by
 *     construction rather than by a special case, and a crafted POST cannot make
 *     damage change a total.
 *   - `loss` and `disp` DO leave the total, which is why they are movements and
 *     not condition buckets. §4's "Scrap" is this module's `disp`.
 *   - `tin` and `tout` are deliberately two fields rather than one signed one.
 *     Netting them would lose the size of each leg, and §28's transfer history
 *     needs both — netting is the shortcut that would force a migration later.
 *
 * @return array key => array{label, sign, kinds, signed}
 */
function bhela_bm_inv_movement_types() {
	return array(
		'add'  => array( 'label' => __( 'Purchase / Added', 'bhela-booking' ), 'sign' => 1,  'kinds' => array( 'inventory', 'asset' ) ),
		'tin'  => array( 'label' => __( 'Transfer In', 'bhela-booking' ),      'sign' => 1,  'kinds' => array( 'inventory', 'asset' ) ),
		'tout' => array( 'label' => __( 'Transfer Out', 'bhela-booking' ),     'sign' => -1, 'kinds' => array( 'inventory', 'asset' ) ),
		'use'  => array( 'label' => __( 'Used / Consumed', 'bhela-booking' ),  'sign' => -1, 'kinds' => array( 'inventory' ) ),
		'loss' => array( 'label' => __( 'Lost / Missing', 'bhela-booking' ),   'sign' => -1, 'kinds' => array( 'inventory', 'asset' ) ),
		'disp' => array( 'label' => __( 'Disposed / Scrapped', 'bhela-booking' ), 'sign' => -1, 'kinds' => array( 'inventory', 'asset' ) ),
		// The only field that may be negative, and the only one that needs a
		// capability to write. An approved adjustment is how a counted variance is
		// resolved — see §15 and bhela_bm_inv_can_close().
		'adj'  => array( 'label' => __( 'Approved Adjustment', 'bhela-booking' ), 'sign' => 1, 'kinds' => array( 'inventory', 'asset' ), 'signed' => true ),
	);
}

/**
 * Axis 2 — the condition of what is on hand.
 *
 * These must always sum to the closing quantity; moving between them never
 * changes it. That is what makes "an AC went out for repair" leave Total Asset at
 * 7 while Available drops to 6, exactly as §8 requires.
 *
 * @return array key => array{label, tone}
 */
function bhela_bm_inv_condition_types() {
	return array(
		'good' => array( 'label' => __( 'Good / Usable', 'bhela-booking' ), 'tone' => 'good' ),
		'rep'  => array( 'label' => __( 'Repairable', 'bhela-booking' ),    'tone' => 'attention' ),
		'ur'   => array( 'label' => __( 'Under Repair', 'bhela-booking' ),  'tone' => 'progress' ),
		'dam'  => array( 'label' => __( 'Damaged', 'bhela-booking' ),       'tone' => 'danger' ),
	);
}

/**
 * The stored shape of one item's month. Every key present, so a reader never
 * has to guess whether a missing key means zero or means "not recorded".
 *
 * @return array
 */
function bhela_bm_inv_blank_line() {
	$line = array( 'open' => 0 );
	foreach ( array_keys( bhela_bm_inv_movement_types() ) as $k ) {
		$line[ $k ] = 0;
	}
	foreach ( array_keys( bhela_bm_inv_condition_types() ) as $k ) {
		$line[ $k ] = 0;
	}
	return array_merge( $line, array(
		'add_rate' => 0,   // unit price of THIS receipt — see the note below
		'rate'     => 0,   // unit value carried for this month
		'count'    => null, // physical count; null means "not counted yet", which is not zero
		'var'      => 0,
		'reason'   => '',
		'remark'   => '',
		'inv_state' => '',
		'adj_by'   => 0,
		'adj_at'   => '',
	) );
}

/**
 * The key a line is stored under.
 *
 * Today this is just the item ID, because one item lives in exactly one location.
 * It exists as a function anyway: if the register ever has to track the same item
 * across two locations at once, the line key becomes (item, location) and this is
 * the single place that changes — plus a lazy read migration in the shape of
 * bhela_bm_cost_stored_lines(). Widening it in place is cheap; discovering later
 * that the key was hardcoded in fifteen call sites is not.
 *
 * @param int    $item_id  Item post ID.
 * @param string $location Reserved. Ignored today.
 * @return string
 */
function bhela_bm_inv_line_key( $item_id, $location = '' ) {
	unset( $location );
	return (string) (int) $item_id;
}

/**
 * Check one line's arithmetic.
 *
 * `close` is derived from the movements; `cond` is what the condition buckets add
 * up to. They must agree, and when they do not the line is not silently
 * rebalanced — it is stored as it stands and reported, because a line that does
 * not reconcile IS the §15 variance, i.e. the input to an investigation rather
 * than an error to swallow. bhela_bm_inv_can_close() is what refuses to file it.
 *
 * The same discipline as _bhela_cost_earnings_auto: a signed figure is preserved
 * and its disagreement is surfaced, never quietly corrected.
 *
 * @param array $l A line.
 * @return array{ok:bool,close:int,cond:int,diff:int,variance:int|null}
 */
function bhela_bm_inv_line_check( $l ) {
	$close = (int) ( $l['open'] ?? 0 );
	foreach ( bhela_bm_inv_movement_types() as $key => $def ) {
		$close += ( (int) $def['sign'] ) * (int) ( $l[ $key ] ?? 0 );
	}
	$cond = 0;
	foreach ( array_keys( bhela_bm_inv_condition_types() ) as $key ) {
		$cond += (int) ( $l[ $key ] ?? 0 );
	}
	// A count of null is "not counted", which is different from a count of zero.
	$counted  = isset( $l['count'] ) && '' !== $l['count'] && null !== $l['count'];
	$variance = $counted ? ( (int) $l['count'] - $close ) : null;

	return array(
		'ok'       => $cond === $close,
		'close'    => $close,
		'cond'     => $cond,
		'diff'     => $cond - $close,
		'variance' => $variance,
	);
}

/** Closing quantity for one line. */
function bhela_bm_inv_close( $l ) {
	return bhela_bm_inv_line_check( $l )['close'];
}

/**
 * Drop movements a kind cannot have.
 *
 * An asset is not consumed, so `use` on an asset line is discarded rather than
 * rejected — the cost sheet's rule, and for the same reason: a storekeeper's
 * otherwise-valid save must not be thrown away wholesale over one field the form
 * should not have offered.
 *
 * @param array  $line A line.
 * @param string $kind inventory|asset.
 * @return array
 */
function bhela_bm_inv_filter_by_kind( $line, $kind ) {
	foreach ( bhela_bm_inv_movement_types() as $key => $def ) {
		if ( ! in_array( $kind, $def['kinds'], true ) ) {
			$line[ $key ] = 0;
		}
	}
	return $line;
}

/* =========================================================
 * THE THREE LISTS
 *
 * Categories, sub-categories and locations, all owner-editable, all following
 * the cost-head rules: a slug is minted once from the first label and then
 * frozen, a blank label is a deletion, a retired entry is KEPT so a closed month
 * still renders its label, and the list can never be emptied.
 *
 * Categories carry one extra frozen field — a short CODE that is embedded in
 * every item ID ever minted under them (BHELA-KIT-0001). Renaming "Kitchen
 * Items" to "Galley" must not turn KIT into GAL, or the IDs already printed on
 * labels and typed into the owner's spreadsheet stop matching anything.
 * ========================================================= */

/** §5's inventory categories, shipped as the default. */
function bhela_bm_inv_category_defaults() {
	return array(
		'kitchen'     => array( 'label' => __( 'Kitchen Items', 'bhela-booking' ),           'code' => 'KIT', 'kind' => 'inventory' ),
		'dining'      => array( 'label' => __( 'Dining & Serving', 'bhela-booking' ),        'code' => 'DIN', 'kind' => 'inventory' ),
		'cleaning'    => array( 'label' => __( 'Cleaning & Housekeeping', 'bhela-booking' ), 'code' => 'CLN', 'kind' => 'inventory' ),
		'guest'       => array( 'label' => __( 'Guest Service Items', 'bhela-booking' ),     'code' => 'GST', 'kind' => 'inventory' ),
		'consumable'  => array( 'label' => __( 'Consumable Items', 'bhela-booking' ),        'code' => 'CON', 'kind' => 'inventory' ),
		'fuel'        => array( 'label' => __( 'Fuel & Oil', 'bhela-booking' ),              'code' => 'FUL', 'kind' => 'inventory' ),
		'safety_con'  => array( 'label' => __( 'Safety Consumables', 'bhela-booking' ),      'code' => 'SFC', 'kind' => 'inventory' ),
		'other_inv'   => array( 'label' => __( 'Other Inventory', 'bhela-booking' ),         'code' => 'OIN', 'kind' => 'inventory' ),
		// §5's assets.
		'engine'      => array( 'label' => __( 'Engine', 'bhela-booking' ),                  'code' => 'ENG', 'kind' => 'asset' ),
		'generator'   => array( 'label' => __( 'Generator', 'bhela-booking' ),               'code' => 'GEN', 'kind' => 'asset' ),
		'ac'          => array( 'label' => __( 'AC', 'bhela-booking' ),                      'code' => 'AC',  'kind' => 'asset' ),
		'electrical'  => array( 'label' => __( 'Electrical Equipment', 'bhela-booking' ),    'code' => 'ELE', 'kind' => 'asset' ),
		'fan'         => array( 'label' => __( 'Fan', 'bhela-booking' ),                     'code' => 'FAN', 'kind' => 'asset' ),
		'fridge'      => array( 'label' => __( 'Refrigerator', 'bhela-booking' ),            'code' => 'RFG', 'kind' => 'asset' ),
		'pump'        => array( 'label' => __( 'Water Pump', 'bhela-booking' ),              'code' => 'PMP', 'kind' => 'asset' ),
		'motor'       => array( 'label' => __( 'Motor', 'bhela-booking' ),                   'code' => 'MTR', 'kind' => 'asset' ),
		'furniture'   => array( 'label' => __( 'Furniture', 'bhela-booking' ),               'code' => 'FRN', 'kind' => 'asset' ),
		'kitchen_eq'  => array( 'label' => __( 'Kitchen Equipment', 'bhela-booking' ),       'code' => 'KEQ', 'kind' => 'asset' ),
		'tools'       => array( 'label' => __( 'Tools', 'bhela-booking' ),                   'code' => 'TOL', 'kind' => 'asset' ),
		'safety_eq'   => array( 'label' => __( 'Safety Equipment', 'bhela-booking' ),        'code' => 'SAF', 'kind' => 'asset' ),
		'comms'       => array( 'label' => __( 'Communication Equipment', 'bhela-booking' ), 'code' => 'COM', 'kind' => 'asset' ),
		'it'          => array( 'label' => __( 'IT Equipment', 'bhela-booking' ),            'code' => 'IT',  'kind' => 'asset' ),
		'marine'      => array( 'label' => __( 'Marine / Boat Equipment', 'bhela-booking' ), 'code' => 'MAR', 'kind' => 'asset' ),
		'other_asset' => array( 'label' => __( 'Other Fixed Assets', 'bhela-booking' ),      'code' => 'OFA', 'kind' => 'asset' ),
	);
}

/** §28's locations, shipped as the default. */
function bhela_bm_inv_location_defaults() {
	$out = array(
		'kitchen' => __( 'BHELA Kitchen', 'bhela-booking' ),
	);
	for ( $i = 1; $i <= bhela_bm_max_cabins(); $i++ ) {
		/* translators: %d: cabin number */
		$out[ 'cabin_' . $i ] = sprintf( __( 'Cabin %d', 'bhela-booking' ), $i );
	}
	return array_merge( $out, array(
		'lobby'    => __( 'Lobby', 'bhela-booking' ),
		'rooftop'  => __( 'Rooftop', 'bhela-booking' ),
		'upper'    => __( 'Upper Deck', 'bhela-booking' ),
		'engine'   => __( 'Engine Room', 'bhela-booking' ),
		'store'    => __( 'Store', 'bhela-booking' ),
		'workshop' => __( 'Workshop', 'bhela-booking' ),
		'outside'  => __( 'Outside / Repair Centre', 'bhela-booking' ),
	) );
}

/**
 * The category list in force.
 *
 * @param bool $include_retired Include categories no longer offered on new items.
 * @return array slug => array{label, code, kind, retired}
 */
function bhela_bm_inv_categories( $include_retired = false ) {
	$saved = get_option( 'bhela_bm_inv_categories', null );
	if ( ! is_array( $saved ) || ! $saved ) {
		return bhela_bm_inv_category_defaults();
	}
	$out = array();
	foreach ( $saved as $slug => $row ) {
		$slug  = sanitize_key( $slug );
		$label = is_array( $row ) ? (string) ( $row['label'] ?? '' ) : (string) $row;
		if ( '' === $slug || '' === $label ) {
			continue;
		}
		if ( ! $include_retired && ! empty( $row['retired'] ) ) {
			continue;
		}
		$out[ $slug ] = array(
			'label'   => $label,
			'code'    => is_array( $row ) ? (string) ( $row['code'] ?? '' ) : '',
			'kind'    => ( is_array( $row ) && 'asset' === ( $row['kind'] ?? '' ) ) ? 'asset' : 'inventory',
			'retired' => ( is_array( $row ) && ! empty( $row['retired'] ) ) ? 1 : 0,
		);
	}
	// Never emptiable — the same guard the cost heads have. An empty list would
	// leave every item uncategorised and every new ID unmintable.
	return $out ? $out : bhela_bm_inv_category_defaults();
}

/**
 * Sub-categories, each belonging to exactly one parent category.
 *
 * Parentage matters: without it "Kitchen → Cutlery" and "Deck → Cutlery" would
 * be one entry and silently merge two different sets of things.
 *
 * @return array slug => array{label, parent, retired}
 */
function bhela_bm_inv_subcats( $include_retired = false, $parent = '' ) {
	$saved = get_option( 'bhela_bm_inv_subcats', array() );
	$out   = array();
	if ( ! is_array( $saved ) ) {
		return $out;
	}
	foreach ( $saved as $slug => $row ) {
		$slug  = sanitize_key( $slug );
		$label = is_array( $row ) ? (string) ( $row['label'] ?? '' ) : (string) $row;
		if ( '' === $slug || '' === $label ) {
			continue;
		}
		if ( ! $include_retired && ! empty( $row['retired'] ) ) {
			continue;
		}
		$row_parent = is_array( $row ) ? sanitize_key( $row['parent'] ?? '' ) : '';
		if ( '' !== $parent && $row_parent !== $parent ) {
			continue;
		}
		$out[ $slug ] = array(
			'label'   => $label,
			'parent'  => $row_parent,
			'retired' => ( is_array( $row ) && ! empty( $row['retired'] ) ) ? 1 : 0,
		);
	}
	return $out;
}

/**
 * The location list in force.
 *
 * @return array slug => label
 */
function bhela_bm_inv_locations( $include_retired = false ) {
	$saved = get_option( 'bhela_bm_inv_locations', null );
	if ( ! is_array( $saved ) || ! $saved ) {
		return bhela_bm_inv_location_defaults();
	}
	$out = array();
	foreach ( $saved as $slug => $row ) {
		$slug  = sanitize_key( $slug );
		$label = is_array( $row ) ? (string) ( $row['label'] ?? '' ) : (string) $row;
		if ( '' === $slug || '' === $label ) {
			continue;
		}
		if ( ! $include_retired && is_array( $row ) && ! empty( $row['retired'] ) ) {
			continue;
		}
		$out[ $slug ] = $label;
	}
	return $out ? $out : bhela_bm_inv_location_defaults();
}

/**
 * A category's ID code, upper-cased and safe for an item ID.
 *
 * Falls back to the first three letters of the slug so a hand-added category
 * without a code still mints usable IDs rather than `BHELA--0001`.
 *
 * @param string $slug Category slug.
 * @return string
 */
function bhela_bm_inv_category_code( $slug ) {
	$cats = bhela_bm_inv_categories( true );
	$code = isset( $cats[ $slug ] ) ? (string) ( $cats[ $slug ]['code'] ?? '' ) : '';
	if ( '' === $code ) {
		$code = strtoupper( substr( preg_replace( '/[^a-z0-9]/', '', strtolower( $slug ) ), 0, 3 ) );
	}
	$code = strtoupper( preg_replace( '/[^A-Z0-9]/', '', strtoupper( $code ) ) );
	return substr( $code ? $code : 'GEN', 0, 5 );
}

/**
 * Save the owner's category list.
 *
 * Both the slug AND the code are minted once and then frozen. The code is the
 * stricter of the two, because it is baked into every item ID under the category
 * and those IDs are printed on labels.
 *
 * @param array $posted Raw input.
 */
function bhela_bm_inv_save_categories( $posted ) {
	if ( ! is_array( $posted ) ) {
		return;
	}
	$existing = bhela_bm_inv_categories( true );
	$out      = array();
	$seen     = array();
	$codes    = array();

	foreach ( $posted as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = sanitize_text_field( $row['label'] ?? '' );
		if ( '' === $label ) {
			continue;                       // a blank row is a deletion
		}
		$slug = sanitize_key( $row['slug'] ?? '' );
		if ( '' === $slug ) {
			$slug = sanitize_key( sanitize_title( $label ) );
			$slug = $slug ? $slug : 'cat';
		}
		// Never collide: two categories sharing a slug would merge their items.
		$base = $slug;
		$n    = 2;
		while ( isset( $seen[ $slug ] ) ) {
			$slug = $base . '_' . $n;
			$n++;
		}
		$seen[ $slug ] = true;

		// A code that already exists on this slug is kept verbatim, frozen.
		$code = isset( $existing[ $slug ] ) && '' !== ( $existing[ $slug ]['code'] ?? '' )
			? $existing[ $slug ]['code']
			: strtoupper( preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) ( $row['code'] ?? '' ) ) ) );
		if ( '' === $code ) {
			$code = strtoupper( substr( preg_replace( '/[^a-z0-9]/', '', $slug ), 0, 3 ) );
		}
		$code = substr( $code ? $code : 'GEN', 0, 5 );
		// Two categories must never share a code, or their item IDs interleave in
		// one sequence and BHELA-KIT-0007 becomes ambiguous.
		$cbase = $code;
		$cn    = 2;
		while ( isset( $codes[ $code ] ) ) {
			$code = substr( $cbase, 0, 4 ) . $cn;
			$cn++;
		}
		$codes[ $code ] = true;

		$out[ $slug ] = array(
			'label'   => $label,
			'code'    => $code,
			'kind'    => 'asset' === ( $row['kind'] ?? '' ) ? 'asset' : 'inventory',
			'retired' => ! empty( $row['retired'] ) ? 1 : 0,
		);
	}
	if ( $out ) {
		update_option( 'bhela_bm_inv_categories', $out );
	}
}

/**
 * Save the owner's sub-category list.
 *
 * @param array $posted Raw input.
 */
function bhela_bm_inv_save_subcats( $posted ) {
	if ( ! is_array( $posted ) ) {
		return;
	}
	$out  = array();
	$seen = array();
	foreach ( $posted as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = sanitize_text_field( $row['label'] ?? '' );
		if ( '' === $label ) {
			continue;
		}
		$slug = sanitize_key( $row['slug'] ?? '' );
		if ( '' === $slug ) {
			$slug = sanitize_key( sanitize_title( $label ) );
			$slug = $slug ? $slug : 'sub';
		}
		$base = $slug;
		$n    = 2;
		while ( isset( $seen[ $slug ] ) ) {
			$slug = $base . '_' . $n;
			$n++;
		}
		$seen[ $slug ] = true;
		$out[ $slug ]  = array(
			'label'   => $label,
			'parent'  => sanitize_key( $row['parent'] ?? '' ),
			'retired' => ! empty( $row['retired'] ) ? 1 : 0,
		);
	}
	// Sub-categories are optional, so unlike the other two this list MAY be empty
	// — but only when the owner actually submitted an empty one.
	update_option( 'bhela_bm_inv_subcats', $out );
}

/**
 * Save the owner's location list.
 *
 * @param array $posted Raw input.
 */
function bhela_bm_inv_save_locations( $posted ) {
	if ( ! is_array( $posted ) ) {
		return;
	}
	$out  = array();
	$seen = array();
	foreach ( $posted as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = sanitize_text_field( $row['label'] ?? '' );
		if ( '' === $label ) {
			continue;
		}
		$slug = sanitize_key( $row['slug'] ?? '' );
		if ( '' === $slug ) {
			$slug = sanitize_key( sanitize_title( $label ) );
			$slug = $slug ? $slug : 'loc';
		}
		$base = $slug;
		$n    = 2;
		while ( isset( $seen[ $slug ] ) ) {
			$slug = $base . '_' . $n;
			$n++;
		}
		$seen[ $slug ] = true;
		$out[ $slug ]  = array(
			'label'   => $label,
			'retired' => ! empty( $row['retired'] ) ? 1 : 0,
		);
	}
	if ( $out ) {
		update_option( 'bhela_bm_inv_locations', $out );
	}
}

/* =========================================================
 * ITEM ID MINTING
 * ========================================================= */

/** Is this item code already taken? One indexed meta lookup. */
function bhela_bm_inv_code_exists( $code ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_bhela_inv_code' AND meta_value = %s LIMIT 1",
		$code
	) );
}

/**
 * Find an item by any of its codes.
 *
 * The importer's match rules use this, and so would a barcode scanner later — a
 * scanner is an input method filling the same box, which is why storing the codes
 * in Phase 1 is enough to make Phase 2 an addition rather than a migration.
 *
 * @param string $code Item code, barcode or asset tag.
 * @return int Item ID, or 0.
 */
function bhela_bm_inv_find_by_code( $code ) {
	global $wpdb;
	$code = trim( (string) $code );
	if ( '' === $code ) {
		return 0;
	}
	foreach ( array( '_bhela_inv_code', '_bhela_inv_barcode', '_bhela_inv_asset_tag' ) as $key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
			$key,
			$code
		) );
		if ( $id ) {
			return $id;
		}
	}
	return 0;
}

/**
 * Take the next item code for a category.
 *
 * Two things make this safe under a concurrent import:
 *
 *   - wp_options.option_name is UNIQUE, so add_option() is an INSERT that fails
 *     for the loser. That is a real database mutex with no new schema, and it is
 *     released in a finally so a fatal cannot wedge it.
 *   - If the number it lands on is already on an item, it skips past rather than
 *     colliding. The counter can legitimately fall behind — a restored database,
 *     or a code typed by hand — and a collision would be far worse than a gap.
 *
 * Codes are never reused and never renumbered. Deleting an item does not free its
 * number, because the number appears in an audit row that outlives the item.
 *
 * @param string $cat_slug Category slug.
 * @return string e.g. 'BHELA-KIT-0001'
 */
function bhela_bm_inv_mint_code( $cat_slug ) {
	$cat_code = bhela_bm_inv_category_code( $cat_slug );
	$lock     = 'bhela_bm_inv_seq_lock';
	$held     = false;

	for ( $try = 0; $try < 50; $try++ ) {
		if ( add_option( $lock, time(), '', 'no' ) ) {
			$held = true;
			break;
		}
		usleep( 20000 );
	}

	try {
		$seq  = (array) get_option( 'bhela_bm_inv_seq', array() );
		$n    = (int) ( $seq[ $cat_code ] ?? 0 );
		$code = '';
		do {
			$n++;
			$code = sprintf( 'BHELA-%s-%04d', $cat_code, $n );
		} while ( bhela_bm_inv_code_exists( $code ) && $n < 99999 );
		$seq[ $cat_code ] = $n;
		update_option( 'bhela_bm_inv_seq', $seq, false );
	} finally {
		if ( $held ) {
			delete_option( $lock );
		}
	}
	return $code;
}

/**
 * Move the counter past a code the register already contains.
 *
 * Called when a CSV supplies its own item code. Without this, re-importing a file
 * that carries BHELA-KIT-0009 would leave the counter at 3 and the next minted
 * code would eventually walk into the one the file already used.
 *
 * @param string $code Code seen in an import.
 */
function bhela_bm_inv_observe_code( $code ) {
	if ( ! preg_match( '/^BHELA-([A-Z0-9]{1,5})-(\d{1,5})$/', (string) $code, $m ) ) {
		return;
	}
	$seq = (array) get_option( 'bhela_bm_inv_seq', array() );
	if ( (int) $m[2] > (int) ( $seq[ $m[1] ] ?? 0 ) ) {
		$seq[ $m[1] ] = (int) $m[2];
		update_option( 'bhela_bm_inv_seq', $seq, false );
	}
}

/** Normalise a code the way the importer and the item editor both must. */
function bhela_bm_inv_clean_code( $raw ) {
	return substr( preg_replace( '/[^A-Z0-9-]/', '', strtoupper( trim( (string) $raw ) ) ), 0, 40 );
}

/* =========================================================
 * MONTHLY PERIODS
 * ========================================================= */

/** A valid YYYY-MM, or ''. Same shape the monthly statement accepts. */
function bhela_bm_inv_month( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	return preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $value ) ? $value : '';
}

/** The month before this one. */
function bhela_bm_inv_prev_month( $month ) {
	$ts = strtotime( $month . '-01' );
	return $ts ? gmdate( 'Y-m', strtotime( '-1 month', $ts ) ) : '';
}

/** The month after this one. */
function bhela_bm_inv_next_month( $month ) {
	$ts = strtotime( $month . '-01' );
	return $ts ? gmdate( 'Y-m', strtotime( '+1 month', $ts ) ) : '';
}

/**
 * The month => post ID index.
 *
 * This option IS the uniqueness constraint. The plugin has no other uniqueness
 * machinery — nothing stops two cost sheets on the same date today — so the
 * register builds its own, and every read goes through here rather than querying
 * for a month and hoping there is only one answer.
 *
 * @return array month => post ID
 */
function bhela_bm_inv_period_index() {
	$index = get_option( 'bhela_bm_inv_periods', array() );
	return is_array( $index ) ? $index : array();
}

/**
 * Resolve — and optionally create — the record for one month.
 *
 * Creation is behind a real database mutex: wp_options.option_name is UNIQUE, so
 * add_option() is an INSERT that fails for whoever loses the race. Two admins
 * clicking "Open month" at once therefore get the same post rather than two
 * records that would each claim to be July.
 *
 * The index is re-read INSIDE the lock, because the whole point is that it may
 * have changed since the caller looked.
 *
 * @param string $month  YYYY-MM.
 * @param bool   $create Create when missing.
 * @return int Post ID, or 0.
 */
function bhela_bm_inv_period_id( $month, $create = false ) {
	$month = bhela_bm_inv_month( $month );
	if ( '' === $month ) {
		return 0;
	}
	$index = bhela_bm_inv_period_index();
	if ( ! empty( $index[ $month ] ) && get_post( $index[ $month ] ) ) {
		return (int) $index[ $month ];
	}
	if ( ! $create ) {
		return 0;
	}

	$lock = 'bhela_bm_inv_lock_' . $month;
	$held = false;
	for ( $try = 0; $try < 50; $try++ ) {
		if ( add_option( $lock, time(), '', 'no' ) ) {
			$held = true;
			break;
		}
		usleep( 20000 );
	}

	$id = 0;
	try {
		// Re-read: the winner of a previous race may already have made it.
		$index = bhela_bm_inv_period_index();
		if ( ! empty( $index[ $month ] ) && get_post( $index[ $month ] ) ) {
			return (int) $index[ $month ];
		}
		$id = wp_insert_post( array(
			'post_type'   => 'bhela_inv_period',
			'post_status' => 'publish',
			'post_title'  => sprintf(
				/* translators: %s: month, e.g. "August 2026" */
				__( 'Stock — %s', 'bhela-booking' ),
				mysql2date( 'F Y', $month . '-01' )
			),
		), true );
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		update_post_meta( $id, '_bhela_inv_period_month', $month );
		update_post_meta( $id, '_bhela_inv_status', 'draft' );
		$index[ $month ] = (int) $id;
		ksort( $index );
		update_option( 'bhela_bm_inv_periods', $index, false );
	} finally {
		if ( $held ) {
			delete_option( $lock );
		}
	}
	return (int) $id;
}

/** The month a period post is for. */
function bhela_bm_inv_period_month( $period_id ) {
	return bhela_bm_inv_month( get_post_meta( $period_id, '_bhela_inv_period_month', true ) );
}

/**
 * Rebuild the index from the posts themselves.
 *
 * A repair tool, for a restored database or an index that got out of step. When it
 * finds two posts claiming one month it keeps the OLDEST and stamps the other,
 * because the older one is the one whose closing a later month was built on.
 *
 * It never deletes anything. A duplicate is a thing to look at, not a thing to
 * silently discard — and bhela_bm_inv_can_close() refuses to close a month that
 * has one flagged.
 *
 * @return array{index:array,duplicates:int[]}
 */
function bhela_bm_inv_period_reindex() {
	$ids = get_posts( array(
		'post_type'      => 'bhela_inv_period',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	$index = array();
	$dupes = array();
	foreach ( $ids as $id ) {
		$month = bhela_bm_inv_period_month( $id );
		if ( '' === $month ) {
			continue;
		}
		if ( isset( $index[ $month ] ) ) {
			$dupes[] = (int) $id;
			if ( ! get_post_meta( $id, '_bhela_inv_duplicate', true ) ) {
				bhela_bm_inv_meta_write( $id, '_bhela_inv_duplicate', 1 );
				if ( function_exists( 'bhela_bm_audit' ) ) {
					bhela_bm_audit( array(
						'channel'     => 'period',
						'action'      => 'update',
						'object_type' => 'inv_period',
						'object_id'   => (int) $id,
						'object_ref'  => $month,
						'field'       => 'duplicate',
						'old_value'   => '',
						'new_value'   => '1',
						'reason'      => sprintf( 'A second record was found for %s; #%d is the one in use.', $month, (int) $index[ $month ] ),
					) );
				}
			}
			continue;
		}
		$index[ $month ] = (int) $id;
	}
	ksort( $index );
	update_option( 'bhela_bm_inv_periods', $index, false );
	return array( 'index' => $index, 'duplicates' => $dupes );
}

/** Every month flagged as having a duplicate record. */
function bhela_bm_inv_has_duplicate( $period_id ) {
	return (bool) get_post_meta( $period_id, '_bhela_inv_duplicate', true );
}

/**
 * Write a `_bhela_inv_*` meta through the lock.
 *
 * The plugin's own writes have to pass the guard in inventory-core.php, and that
 * guard is deliberately blind to WHO is writing — it only knows whether the
 * window is open. This is the one function that opens it, so every legitimate
 * write goes through here and the window is never left open by accident.
 *
 * @param int    $post_id Period ID.
 * @param string $key     Meta key.
 * @param mixed  $value   Value.
 */
function bhela_bm_inv_meta_write( $post_id, $key, $value ) {
	bhela_bm_inv_unlocking( true );
	try {
		update_post_meta( $post_id, $key, $value );
	} finally {
		bhela_bm_inv_unlocking( false );
	}
}

/* =========================================================
 * LINES
 * ========================================================= */

/** The stored lines for a period, keyed by line key. */
function bhela_bm_inv_stored_lines( $period_id ) {
	$raw = json_decode( (string) get_post_meta( $period_id, '_bhela_inv_lines', true ), true );
	return is_array( $raw ) ? $raw : array();
}

/** The stored opening snapshot for a period, keyed by line key. */
function bhela_bm_inv_stored_opening( $period_id ) {
	$raw = json_decode( (string) get_post_meta( $period_id, '_bhela_inv_opening', true ), true );
	return is_array( $raw ) ? $raw : array();
}

/**
 * Write the lines blob.
 *
 * JSON_FORCE_OBJECT for the reason the cost sheet needs it: the keys here are
 * item IDs, so an all-numeric map would encode as a JSON list and come back as a
 * positional array with the item IDs replaced by 0,1,2 — silently attaching every
 * figure to the wrong item.
 *
 * @param int   $period_id Period.
 * @param array $lines     Lines keyed by line key.
 */
function bhela_bm_inv_write_lines( $period_id, $lines ) {
	bhela_bm_inv_meta_write( $period_id, '_bhela_inv_lines', wp_json_encode( $lines, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT ) );
	bhela_bm_inv_meta_write( $period_id, '_bhela_inv_totals', wp_json_encode( bhela_bm_inv_totals( $lines ), JSON_UNESCAPED_UNICODE ) );
}

/**
 * Roll a set of lines up into the figures a report and a rollup both need.
 *
 * Cached onto the period as `_bhela_inv_totals` so a quarter is three reads and a
 * year is twelve — not twelve full recomputations, which is what makes the
 * existing yearly report the slow screen.
 *
 * @param array $lines Lines.
 * @return array
 */
function bhela_bm_inv_totals( $lines ) {
	$out = array( 'items' => 0, 'open' => 0, 'close' => 0, 'value' => 0, 'unreconciled' => 0, 'variance' => 0 );
	foreach ( array_keys( bhela_bm_inv_movement_types() ) as $k ) {
		$out[ $k ] = 0;
	}
	foreach ( array_keys( bhela_bm_inv_condition_types() ) as $k ) {
		$out[ $k ] = 0;
	}
	foreach ( $lines as $line ) {
		$chk = bhela_bm_inv_line_check( $line );
		$out['items']++;
		$out['open']  += (int) ( $line['open'] ?? 0 );
		$out['close'] += $chk['close'];
		$out['value'] += $chk['close'] * (int) ( $line['rate'] ?? 0 );
		foreach ( array_keys( bhela_bm_inv_movement_types() ) as $k ) {
			$out[ $k ] += (int) ( $line[ $k ] ?? 0 );
		}
		foreach ( array_keys( bhela_bm_inv_condition_types() ) as $k ) {
			$out[ $k ] += (int) ( $line[ $k ] ?? 0 );
		}
		if ( ! $chk['ok'] ) {
			$out['unreconciled']++;
		}
		if ( null !== $chk['variance'] && 0 !== $chk['variance'] ) {
			$out['variance']++;
		}
	}
	return $out;
}

/** The cached totals for a period, recomputed if absent. */
function bhela_bm_inv_period_totals( $period_id ) {
	$raw = json_decode( (string) get_post_meta( $period_id, '_bhela_inv_totals', true ), true );
	return is_array( $raw ) ? $raw : bhela_bm_inv_totals( bhela_bm_inv_stored_lines( $period_id ) );
}

/* =========================================================
 * OPENING = PREVIOUS CLOSING
 * ========================================================= */

/** Closing quantity per line key for a period — what the next month opens on. */
function bhela_bm_inv_closings( $period_id ) {
	$out = array();
	foreach ( bhela_bm_inv_stored_lines( $period_id ) as $key => $line ) {
		$out[ $key ] = bhela_bm_inv_close( $line );
	}
	return $out;
}

/**
 * Snapshot the predecessor's closings as this period's opening.
 *
 * A snapshot rather than a live read, for the reason `_bhela_cost_earnings_auto`
 * exists: a month that has been closed must keep rendering the number it was
 * closed with, even if the month before it is later reopened and edited. The hash
 * is what lets bhela_bm_inv_opening_drift() notice that has happened.
 *
 * @param int  $period_id Period to give an opening.
 * @param bool $force     Re-take an opening that already exists.
 * @return array{written:bool,from:int,changed:array}
 */
function bhela_bm_inv_take_opening( $period_id, $force = false ) {
	$month = bhela_bm_inv_period_month( $period_id );
	$prev  = bhela_bm_inv_prev_month( $month );
	$prev_id = $prev ? bhela_bm_inv_period_id( $prev ) : 0;

	$had = bhela_bm_inv_stored_opening( $period_id );
	if ( $had && ! $force ) {
		return array( 'written' => false, 'from' => (int) get_post_meta( $period_id, '_bhela_inv_opening_from', true ), 'changed' => array() );
	}
	// No predecessor: this is the baseline. Its opening comes from the import, so
	// leave whatever is there rather than blanking it.
	if ( ! $prev_id ) {
		bhela_bm_inv_meta_write( $period_id, '_bhela_inv_opening_from', 0 );
		return array( 'written' => false, 'from' => 0, 'changed' => array() );
	}

	$closings = bhela_bm_inv_closings( $prev_id );
	$changed  = array();
	foreach ( $closings as $key => $qty ) {
		$was = isset( $had[ $key ] ) ? (int) $had[ $key ] : null;
		if ( $was !== (int) $qty ) {
			$changed[ $key ] = array( 'from' => $was, 'to' => (int) $qty );
		}
	}

	bhela_bm_inv_meta_write( $period_id, '_bhela_inv_opening', wp_json_encode( $closings, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT ) );
	bhela_bm_inv_meta_write( $period_id, '_bhela_inv_opening_from', (int) $prev_id );
	bhela_bm_inv_meta_write( $period_id, '_bhela_inv_opening_hash', bhela_bm_inv_closings_hash( $closings ) );

	return array( 'written' => true, 'from' => (int) $prev_id, 'changed' => $changed );
}

/** A stable fingerprint of a set of closings. */
function bhela_bm_inv_closings_hash( $closings ) {
	ksort( $closings );
	return md5( (string) wp_json_encode( $closings ) );
}

/**
 * Where a period's opening came from.
 *
 * Stated on the screen, so nobody wonders why the first month has no predecessor.
 *
 * @param int $period_id Period.
 * @return string import|carry|none
 */
function bhela_bm_inv_opening_source( $period_id ) {
	if ( get_post_meta( $period_id, '_bhela_inv_baseline', true ) ) {
		return 'import';
	}
	return get_post_meta( $period_id, '_bhela_inv_opening_from', true ) ? 'carry' : 'none';
}

/**
 * Has the month underneath this one changed since we took its closings?
 *
 * This is the `bhela_bm_cost_earnings_drift()` situation and gets the same
 * treatment: REPORT, never correct. Silently re-snapshotting would move a figure
 * on a month somebody has already signed, which is precisely what an audit
 * register must not do.
 *
 * @param int $period_id Period.
 * @return array{stale:bool,from:int,items:array}
 */
function bhela_bm_inv_opening_drift( $period_id ) {
	$out  = array( 'stale' => false, 'from' => 0, 'items' => array() );
	$from = (int) get_post_meta( $period_id, '_bhela_inv_opening_from', true );
	if ( ! $from || ! get_post( $from ) ) {
		return $out;                            // baseline, or a predecessor that is gone
	}
	$out['from'] = $from;

	$stored = get_post_meta( $period_id, '_bhela_inv_opening_hash', true );
	$live   = bhela_bm_inv_closings( $from );
	if ( $stored === bhela_bm_inv_closings_hash( $live ) ) {
		return $out;
	}

	$snapshot = bhela_bm_inv_stored_opening( $period_id );
	$keys     = array_unique( array_merge( array_keys( $snapshot ), array_keys( $live ) ) );
	foreach ( $keys as $key ) {
		$was = isset( $snapshot[ $key ] ) ? (int) $snapshot[ $key ] : 0;
		$now = isset( $live[ $key ] ) ? (int) $live[ $key ] : 0;
		if ( $was !== $now ) {
			$out['items'][] = array(
				'item'     => (int) $key,
				'snapshot' => $was,
				'live'     => $now,
				'diff'     => $now - $was,
			);
		}
	}
	$out['stale'] = (bool) $out['items'];
	return $out;
}

/* =========================================================
 * THE MONTHLY WORKFLOW
 * ========================================================= */

/**
 * The five states a month can be in.
 *
 * `reopened` is deliberately a state of its own rather than a return to `draft`.
 * A month that was closed and then opened again is not the same thing as a month
 * that was never closed: every later month's opening was taken from its closing,
 * so the distinction is what makes the drift downstream reviewable.
 *
 * Five distinct tone/weight pairs, within the ten the design system offers.
 */
function bhela_bm_inv_statuses() {
	return array(
		'draft'    => array( 'label' => __( 'Draft', 'bhela-booking' ),     'tone' => 'neutral',   'solid' => false ),
		'counted'  => array( 'label' => __( 'Counted', 'bhela-booking' ),   'tone' => 'attention', 'solid' => false ),
		'checked'  => array( 'label' => __( 'Checked', 'bhela-booking' ),   'tone' => 'progress',  'solid' => false ),
		'closed'   => array( 'label' => __( 'Closed', 'bhela-booking' ),    'tone' => 'good',      'solid' => true ),
		'reopened' => array( 'label' => __( 'Reopened', 'bhela-booking' ),  'tone' => 'danger',    'solid' => false ),
	);
}

/** A period's status. Anything unrecognised reads as draft — fail safe. */
function bhela_bm_inv_status( $period_id ) {
	$s = get_post_meta( $period_id, '_bhela_inv_status', true );
	return isset( bhela_bm_inv_statuses()[ $s ] ) ? $s : 'draft';
}

/** The pill for a period's status. */
function bhela_bm_inv_status_pill( $period_id ) {
	$s   = bhela_bm_inv_status( $period_id );
	$def = bhela_bm_inv_statuses()[ $s ];
	return bhela_bm_status_pill( $def['label'], $def['tone'], $def['solid'] );
}

/**
 * The workflow, as data.
 *
 * `reopen` carries its own capability rather than riding on `approve`: closing a
 * month files it, reopening one invalidates the opening of every month after it.
 * The second is a strictly larger act and deserves a separate grant.
 *
 * @return array action => array{from, to, cap, log}
 */
function bhela_bm_inv_transitions() {
	return array(
		'count'  => array( 'from' => array( 'draft', 'reopened' ),  'to' => 'counted',  'cap' => 'bhela_inv_count',   'log' => 'submitted for checking' ),
		'check'  => array( 'from' => array( 'counted' ),            'to' => 'checked',  'cap' => 'bhela_inv_check',   'log' => 'marked as checked' ),
		'close'  => array( 'from' => array( 'checked' ),            'to' => 'closed',   'cap' => 'bhela_inv_approve', 'log' => 'closed and locked' ),
		'return' => array( 'from' => array( 'counted', 'checked' ), 'to' => 'draft',    'cap' => 'bhela_inv_check',   'log' => 'returned for a recount' ),
		'reopen' => array( 'from' => array( 'closed' ),             'to' => 'reopened', 'cap' => 'bhela_inv_reopen',  'log' => 'reopened' ),
	);
}

/** Is this transition available from the period's current state? */
function bhela_bm_inv_transition_allowed( $period_id, $action ) {
	$t = bhela_bm_inv_transitions()[ $action ] ?? null;
	return $t && in_array( bhela_bm_inv_status( $period_id ), $t['from'], true );
}

/**
 * Everything standing between a checked month and a closed one.
 *
 * Six reasons rather than the cost sheet's one, so the answer is a list. Closing
 * is the irreversible act — it writes the figure every later month is built on —
 * so each of these is a thing that would make that figure a lie.
 *
 * @param int $period_id Period.
 * @return array{ok:bool,why:string[],detail:array}
 */
function bhela_bm_inv_can_close( $period_id ) {
	$why    = array();
	$detail = array();

	$month = bhela_bm_inv_period_month( $period_id );
	if ( '' === $month ) {
		// The undated-sheet lesson: an unmonthed record files into no period, and
		// approval cannot be undone.
		$why[] = 'no_month';
	}

	// The predecessor must be settled first, or this month's opening is provisional.
	$prev = $month ? bhela_bm_inv_prev_month( $month ) : '';
	$prev_id = $prev ? bhela_bm_inv_period_id( $prev ) : 0;
	if ( $prev_id && 'closed' !== bhela_bm_inv_status( $prev_id ) ) {
		$why[]           = 'prev_open';
		$detail['prev']  = $prev;
	}

	$drift = bhela_bm_inv_opening_drift( $period_id );
	if ( $drift['stale'] ) {
		$why[]           = 'drift';
		$detail['drift'] = $drift;
	}

	if ( bhela_bm_inv_has_duplicate( $period_id ) ) {
		$why[] = 'duplicate';
	}

	$unreconciled = array();
	$unexplained  = array();
	foreach ( bhela_bm_inv_stored_lines( $period_id ) as $key => $line ) {
		$chk = bhela_bm_inv_line_check( $line );
		if ( ! $chk['ok'] ) {
			$unreconciled[] = (int) $key;
		}
		// A counted variance has to be explained AND adjusted. A reason alone
		// leaves the figure wrong; an adjustment alone leaves it unexplained.
		if ( null !== $chk['variance'] && 0 !== $chk['variance'] && '' === trim( (string) ( $line['reason'] ?? '' ) ) ) {
			$unexplained[] = (int) $key;
		}
	}
	if ( $unreconciled ) {
		$why[]                   = 'unreconciled';
		$detail['unreconciled']  = $unreconciled;
	}
	if ( $unexplained ) {
		$why[]                  = 'unexplained';
		$detail['unexplained']  = $unexplained;
	}

	return array( 'ok' => ! $why, 'why' => $why, 'detail' => $detail );
}

/** Human sentences for what bhela_bm_inv_can_close() refused. */
function bhela_bm_inv_close_reasons() {
	return array(
		'no_month'     => __( 'This sheet is not attached to a month, so it cannot be filed.', 'bhela-booking' ),
		'prev_open'    => __( 'The month before this one is not closed yet. Months close in order, because each one opens on the last one\'s closing.', 'bhela-booking' ),
		'drift'        => __( 'The month before this one changed after this sheet took its opening. Re-take the opening, then close.', 'bhela-booking' ),
		'duplicate'    => __( 'A second record exists for this month. Sort that out before closing.', 'bhela-booking' ),
		'unreconciled' => __( 'Some lines do not add up — Good + Repairable + Under Repair + Damaged must equal the closing quantity.', 'bhela-booking' ),
		'unexplained'  => __( 'Some counted lines differ from the system and carry no reason. Every variance needs one.', 'bhela-booking' ),
	);
}

/**
 * Can a closed month be reopened?
 *
 * Only if nothing after it is closed. You reopen forward-to-back, because
 * reopening April while May is closed would leave May's opening describing a
 * figure April no longer reports.
 *
 * @param int $period_id Period.
 * @return array{ok:bool,blocker:string}
 */
function bhela_bm_inv_can_reopen( $period_id ) {
	$month = bhela_bm_inv_period_month( $period_id );
	foreach ( bhela_bm_inv_period_index() as $m => $id ) {
		if ( $m > $month && 'closed' === bhela_bm_inv_status( $id ) ) {
			return array( 'ok' => false, 'blocker' => $m );
		}
	}
	return array( 'ok' => true, 'blocker' => '' );
}

/**
 * Apply a transition.
 *
 * Guard order copied from the cost sheet, including the 409: a stale browser tab
 * replaying a transition must not move the record a second time, and that is a
 * conflict rather than a permission problem.
 */
function bhela_bm_inv_transition() {
	$id     = (int) ( $_GET['period'] ?? 0 );
	$action = sanitize_key( $_GET['do'] ?? '' );

	check_admin_referer( 'bhela_bm_inv_transition_' . $id );

	$t    = bhela_bm_inv_transitions()[ $action ] ?? null;
	$post = $id ? get_post( $id ) : null;
	if ( ! $t || ! $post || 'bhela_inv_period' !== $post->post_type ) {
		wp_die( esc_html__( 'Unknown request.', 'bhela-booking' ), 400 );
	}
	if ( ! current_user_can( $t['cap'] ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'bhela-booking' ), 403 );
	}
	$from = bhela_bm_inv_status( $id );
	if ( ! in_array( $from, $t['from'], true ) ) {
		wp_die( esc_html__( 'This sheet has already moved on — reload the page and look again.', 'bhela-booking' ), 409 );
	}

	$month = bhela_bm_inv_period_month( $id );
	$note  = sanitize_textarea_field( wp_unslash( $_GET['note'] ?? '' ) );

	if ( 'close' === $action ) {
		$can = bhela_bm_inv_can_close( $id );
		if ( ! $can['ok'] ) {
			set_transient( 'bhela_bm_inv_msg_' . $id, array( 'type' => 'error', 'why' => $can['why'] ), 45 );
			wp_safe_redirect( bhela_bm_inv_month_url( $month ) );
			exit;
		}
	}
	if ( 'reopen' === $action ) {
		$can = bhela_bm_inv_can_reopen( $id );
		if ( ! $can['ok'] ) {
			set_transient( 'bhela_bm_inv_msg_' . $id, array( 'type' => 'error', 'why' => array( 'later_closed' ), 'blocker' => $can['blocker'] ), 45 );
			wp_safe_redirect( bhela_bm_inv_month_url( $month ) );
			exit;
		}
	}

	$user = wp_get_current_user();
	bhela_bm_inv_meta_write( $id, '_bhela_inv_status', $t['to'] );

	if ( 'close' === $action ) {
		bhela_bm_inv_meta_write( $id, '_bhela_inv_closed_by', $user->ID );
		bhela_bm_inv_meta_write( $id, '_bhela_inv_closed_at', current_time( 'mysql' ) );
		// Stamp every item the month touched, so the deletion guard is one meta
		// read rather than a scan of every period on every render.
		foreach ( array_keys( bhela_bm_inv_stored_lines( $id ) ) as $key ) {
			update_post_meta( (int) $key, '_bhela_inv_locked', 1 );
		}
		// The next month can now take its opening from this one.
		$next = bhela_bm_inv_next_month( $month );
		$next_id = $next ? bhela_bm_inv_period_id( $next ) : 0;
		if ( $next_id ) {
			bhela_bm_inv_take_opening( $next_id, true );
		}
	} elseif ( 'counted' === $t['to'] ) {
		bhela_bm_inv_meta_write( $id, '_bhela_inv_counted_by', $user->ID );
		bhela_bm_inv_meta_write( $id, '_bhela_inv_counted_at', current_time( 'mysql' ) );
	} elseif ( 'checked' === $t['to'] ) {
		bhela_bm_inv_meta_write( $id, '_bhela_inv_checked_by', $user->ID );
		bhela_bm_inv_meta_write( $id, '_bhela_inv_checked_at', current_time( 'mysql' ) );
	}

	// Going backwards clears the stamps ahead of the new state, so a sheet never
	// shows a sign-off it no longer has.
	if ( 'return' === $action ) {
		foreach ( array( 'counted', 'checked' ) as $step ) {
			bhela_bm_inv_meta_write( $id, '_bhela_inv_' . $step . '_by', '' );
			bhela_bm_inv_meta_write( $id, '_bhela_inv_' . $step . '_at', '' );
		}
		bhela_bm_inv_meta_write( $id, '_bhela_inv_note', $note );
	}
	if ( 'reopen' === $action ) {
		bhela_bm_inv_meta_write( $id, '_bhela_inv_closed_by', '' );
		bhela_bm_inv_meta_write( $id, '_bhela_inv_closed_at', '' );
		bhela_bm_inv_meta_write( $id, '_bhela_inv_note', $note );
	}

	if ( function_exists( 'bhela_bm_audit' ) ) {
		bhela_bm_audit( array(
			'channel'      => 'period',
			'action'       => $action,
			'object_type'  => 'inv_period',
			'object_id'    => $id,
			'object_ref'   => $month,
			'field'        => 'status',
			'old_value'    => $from,
			'new_value'    => $t['to'],
			'reason'       => $note,
			'approval_ref' => $action . ':' . $month . ':#' . $user->ID,
		) );
	}
	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'inventory', sprintf( 'Stock sheet %s — %s by %s', $month, $t['log'], $user->display_name ) );
	}

	set_transient( 'bhela_bm_inv_msg_' . $id, array( 'type' => 'ok', 'action' => $action ), 45 );
	wp_safe_redirect( bhela_bm_inv_month_url( $month ) );
	exit;
}
add_action( 'admin_post_bhela_bm_inv_transition', 'bhela_bm_inv_transition' );

/**
 * Re-take a period's opening from its predecessor.
 *
 * The explicit fix for drift. It rewrites the snapshot, records one audit row per
 * changed item with the real old and new figures, and forces the sheet back to
 * draft — because a sheet that was checked against one opening has not been
 * checked against this one.
 */
function bhela_bm_inv_retake_opening() {
	$id = (int) ( $_GET['period'] ?? 0 );
	check_admin_referer( 'bhela_bm_inv_retake_' . $id );

	if ( ! current_user_can( 'bhela_inv_approve' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'bhela-booking' ), 403 );
	}
	$post = $id ? get_post( $id ) : null;
	if ( ! $post || 'bhela_inv_period' !== $post->post_type ) {
		wp_die( esc_html__( 'Unknown request.', 'bhela-booking' ), 400 );
	}
	$month = bhela_bm_inv_period_month( $id );
	if ( bhela_bm_inv_is_locked( $id ) ) {
		wp_die( esc_html__( 'This month is closed. Reopen it first.', 'bhela-booking' ), 409 );
	}

	$res = bhela_bm_inv_take_opening( $id, true );

	if ( function_exists( 'bhela_bm_audit' ) ) {
		foreach ( $res['changed'] as $key => $move ) {
			bhela_bm_audit( array(
				'channel'      => 'period',
				'action'       => 'update',
				'object_type'  => 'inv_line',
				'object_id'    => (int) $key,
				'object_ref'   => $month . ':' . bhela_bm_inv_item_code( (int) $key ),
				'field'        => 'open',
				'old_value'    => null === $move['from'] ? '' : $move['from'],
				'new_value'    => $move['to'],
				'reason'       => 'Opening re-taken from the previous month after it changed.',
				'approval_ref' => 'retake:' . $month . ':#' . get_current_user_id(),
			) );
		}
	}

	// Back to draft: the count was reviewed against a different opening.
	$was = bhela_bm_inv_status( $id );
	if ( 'draft' !== $was ) {
		bhela_bm_inv_meta_write( $id, '_bhela_inv_status', 'draft' );
		if ( function_exists( 'bhela_bm_audit' ) ) {
			bhela_bm_audit( array(
				'channel'     => 'period',
				'action'      => 'update',
				'object_type' => 'inv_period',
				'object_id'   => $id,
				'object_ref'  => $month,
				'field'       => 'status',
				'old_value'   => $was,
				'new_value'   => 'draft',
				'reason'      => 'Opening was re-taken, so the sheet needs checking again.',
			) );
		}
	}

	set_transient( 'bhela_bm_inv_msg_' . $id, array( 'type' => 'ok', 'action' => 'retake', 'n' => count( $res['changed'] ) ), 45 );
	wp_safe_redirect( bhela_bm_inv_month_url( $month ) );
	exit;
}
add_action( 'admin_post_bhela_bm_inv_retake', 'bhela_bm_inv_retake_opening' );

/** An item's code, for an audit row's object_ref. */
function bhela_bm_inv_item_code( $item_id ) {
	$code = get_post_meta( $item_id, '_bhela_inv_code', true );
	return $code ? $code : ( '#' . (int) $item_id );
}

/** URL of the Monthly Update screen for a month. */
function bhela_bm_inv_month_url( $month, $args = array() ) {
	return add_query_arg( array_merge( array(
		'post_type' => 'bhela_booking',
		'page'      => 'bhela-bm-inv-month',
		'month'     => $month,
	), $args ), admin_url( 'edit.php' ) );
}

/* =========================================================
 * SAVING A MONTH
 * ========================================================= */

/**
 * Save the posted lines for one month.
 *
 * The rules, in the order they matter:
 *
 *   1. A closed month is refused outright. The meta guard in inventory-core.php
 *      would stop the write anyway, but failing here means the user gets told.
 *   2. `open` is not in the loop AT ALL. Opening is derived from the previous
 *      month's closing, so there is nothing for a crafted POST to aim at — which
 *      is strictly stronger than rendering the field readonly.
 *   3. Movements a kind cannot have are dropped, not rejected.
 *   4. `adj` needs bhela_inv_adjust. Without it the posted value is ignored and
 *      the stored one kept — the counter's other figures still save, because
 *      throwing away an otherwise-valid sheet over one field they should not have
 *      been shown is worse than ignoring the field.
 *   5. Only the items actually posted are touched. The form is paged by category,
 *      so a save must merge into the blob rather than replace it.
 *   6. Every changed field becomes one audit row with the real before and after.
 *      A no-op re-save writes nothing, which is what keeps the trail readable.
 */
function bhela_bm_inv_save_month() {
	$id = (int) ( $_POST['bhela_inv_period'] ?? 0 );

	if ( ! isset( $_POST['bhela_inv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_inv_nonce'] ) ), 'bhela_bm_inv_save_' . $id ) ) {
		wp_die( esc_html__( 'That form has expired. Reload the page and try again.', 'bhela-booking' ), 400 );
	}
	if ( ! current_user_can( 'bhela_inv_count' ) ) {
		wp_die( esc_html__( 'You are not allowed to edit the stock sheet.', 'bhela-booking' ), 403 );
	}
	$post = $id ? get_post( $id ) : null;
	if ( ! $post || 'bhela_inv_period' !== $post->post_type ) {
		wp_die( esc_html__( 'Unknown request.', 'bhela-booking' ), 400 );
	}
	$month = bhela_bm_inv_period_month( $id );

	if ( bhela_bm_inv_is_locked( $id ) ) {
		set_transient( 'bhela_bm_inv_msg_' . $id, array( 'type' => 'error', 'why' => array( 'locked' ) ), 45 );
		wp_safe_redirect( bhela_bm_inv_month_url( $month ) );
		exit;
	}

	$posted = isset( $_POST['bhela_inv_lines'] ) && is_array( $_POST['bhela_inv_lines'] )
		? wp_unslash( $_POST['bhela_inv_lines'] )
		: array();

	$changes = bhela_bm_inv_apply_lines( $id, $posted, current_user_can( 'bhela_inv_adjust' ) );

	set_transient( 'bhela_bm_inv_msg_' . $id, array( 'type' => 'ok', 'action' => 'save', 'n' => count( $changes ) ), 45 );
	wp_safe_redirect( bhela_bm_inv_month_url( $month, array( 'cat' => sanitize_key( $_POST['bhela_inv_cat'] ?? '' ) ) ) );
	exit;
}
add_action( 'admin_post_bhela_bm_inv_save_month', 'bhela_bm_inv_save_month' );

/**
 * Merge posted figures into a month's lines, and record what moved.
 *
 * Split out of the request handler so it can be called — and therefore tested —
 * without a request that ends in exit(). The handler above owns the nonce, the
 * capability and the redirect; this owns the rules.
 *
 * @param int   $id          Period post ID.
 * @param array $posted      Raw `bhela_inv_lines` — item key => field => value.
 * @param bool  $can_adjust  Whether the actor may write the signed adjustment.
 * @return array The changes recorded, one entry per changed field.
 */
function bhela_bm_inv_apply_lines( $id, $posted, $can_adjust ) {
	$month    = bhela_bm_inv_period_month( $id );
	$stored   = bhela_bm_inv_stored_lines( $id );
	$opening  = bhela_bm_inv_stored_opening( $id );
	$can_adj  = (bool) $can_adjust;
	$movements = array_keys( bhela_bm_inv_movement_types() );
	$conditions = array_keys( bhela_bm_inv_condition_types() );
	$changes  = array();

	foreach ( $posted as $key => $row ) {
		$key     = (string) (int) $key;
		$item_id = (int) $key;
		if ( ! $item_id || 'bhela_inv_item' !== get_post_type( $item_id ) || ! is_array( $row ) ) {
			continue;
		}
		$was  = isset( $stored[ $key ] ) ? array_merge( bhela_bm_inv_blank_line(), $stored[ $key ] ) : bhela_bm_inv_blank_line();
		$line = $was;

		// Opening is never taken from the form — see rule 2.
		$line['open'] = isset( $opening[ $key ] ) ? (int) $opening[ $key ] : (int) $was['open'];

		foreach ( $movements as $field ) {
			if ( 'adj' === $field ) {
				// Signed, and gated. An ungated POST leaves the stored value alone.
				if ( $can_adj && array_key_exists( 'adj', $row ) ) {
					$line['adj'] = (int) $row['adj'];
					if ( (int) $was['adj'] !== (int) $line['adj'] ) {
						$line['adj_by'] = get_current_user_id();
						$line['adj_at'] = current_time( 'mysql' );
					}
				}
				continue;
			}
			if ( array_key_exists( $field, $row ) ) {
				$line[ $field ] = max( 0, (int) $row[ $field ] );
			}
		}
		foreach ( $conditions as $field ) {
			if ( array_key_exists( $field, $row ) ) {
				$line[ $field ] = max( 0, (int) $row[ $field ] );
			}
		}
		foreach ( array( 'add_rate', 'rate' ) as $field ) {
			if ( array_key_exists( $field, $row ) ) {
				$line[ $field ] = max( 0, (int) $row[ $field ] );
			}
		}
		// A blank count is "not counted", which is not the same as counted zero.
		if ( array_key_exists( 'count', $row ) ) {
			$raw            = trim( (string) $row['count'] );
			$line['count']  = ( '' === $raw ) ? null : max( 0, (int) $raw );
		}
		foreach ( array( 'reason', 'remark' ) as $field ) {
			if ( array_key_exists( $field, $row ) ) {
				$line[ $field ] = sanitize_textarea_field( (string) $row[ $field ] );
			}
		}
		if ( array_key_exists( 'inv_state', $row ) ) {
			$state              = sanitize_key( (string) $row['inv_state'] );
			$line['inv_state'] = in_array( $state, array( '', 'open', 'resolved' ), true ) ? $state : '';
		}

		$line = bhela_bm_inv_filter_by_kind( $line, bhela_bm_inv_kind( $item_id ) );

		// The variance flag is derived, so it cannot be posted.
		$chk         = bhela_bm_inv_line_check( $line );
		$line['var'] = ( null !== $chk['variance'] && 0 !== $chk['variance'] ) || ! $chk['ok'] ? 1 : 0;

		// Diff before writing — this is the whole reason the audit trail can say
		// "10 became 12" where the activity log can only say "something changed".
		foreach ( $line as $field => $value ) {
			if ( in_array( $field, array( 'adj_by', 'adj_at', 'var' ), true ) ) {
				continue;                       // bookkeeping, not a figure
			}
			$before = $was[ $field ] ?? null;
			if ( (string) $before === (string) $value ) {
				continue;
			}
			$changes[] = array(
				'item'  => $item_id,
				'field' => $field,
				'from'  => $before,
				'to'    => $value,
				'why'   => (string) $line['reason'],
			);
		}

		$stored[ $key ] = $line;
	}

	bhela_bm_inv_write_lines( $id, $stored );

	if ( $changes && function_exists( 'bhela_bm_audit' ) ) {
		foreach ( $changes as $c ) {
			bhela_bm_audit( array(
				'channel'     => 'period',
				'action'      => 'update',
				'object_type' => 'inv_line',
				'object_id'   => $c['item'],
				'object_ref'  => $month . ':' . bhela_bm_inv_item_code( $c['item'] ),
				'field'       => $c['field'],
				'old_value'   => null === $c['from'] ? '' : $c['from'],
				'new_value'   => null === $c['to'] ? '' : $c['to'],
				'reason'      => 'reason' === $c['field'] ? '' : $c['why'],
			) );
		}
	}

	return $changes;
}

/* =========================================================
 * MENUS
 * ========================================================= */

/** The register's screens, all nested under Bookings like every other module. */
function bhela_bm_inv_menu() {
	$parent = 'edit.php?post_type=bhela_booking';

	add_submenu_page( $parent, __( 'Monthly Stock Update', 'bhela-booking' ), '🔧 ' . __( 'Monthly Stock', 'bhela-booking' ), 'bhela_inv_count', 'bhela-bm-inv-month', 'bhela_bm_inv_month_page' );
	add_submenu_page( $parent, __( 'Inventory Report', 'bhela-booking' ), '📐 ' . __( 'Inventory Report', 'bhela-booking' ), 'bhela_inv_view', 'bhela-bm-inv-report', 'bhela_bm_inv_report_page' );
	add_submenu_page( $parent, __( 'Asset Report', 'bhela-booking' ), '🏷️ ' . __( 'Asset Report', 'bhela-booking' ), 'bhela_inv_view', 'bhela-bm-inv-assets', 'bhela_bm_inv_asset_page' );
	// A real menu item, like the Gallery's Bulk Upload next door. It was briefly
	// registered with a null parent to save a slot, which made the whole importer
	// unreachable except by typing its URL — a feature nobody can find is not a
	// feature, and one menu row is a cheap price for the register's way in.
	add_submenu_page( $parent, __( 'Import Register', 'bhela-booking' ), '🚚 ' . __( 'Import Register', 'bhela-booking' ), 'bhela_inv_import', 'bhela-bm-inv-import', 'bhela_bm_inv_import_page' );
}
add_action( 'admin_menu', 'bhela_bm_inv_menu' );

/**
 * A storekeeper has no bookings, so the nested menu is unreachable for them.
 *
 * Same treatment the cost sheet gives a cost-only preparer: drop the nested entry
 * and give them a top-level one. Priority 20, because core's own
 * _add_post_type_submenus() runs at 10 and would otherwise re-add it after us.
 */
function bhela_bm_inv_standalone_menu() {
	if ( current_user_can( 'edit_bhela_bookings' ) || ! current_user_can( 'bhela_inv_view' ) ) {
		return;
	}
	remove_submenu_page( 'edit.php?post_type=bhela_booking', 'edit.php?post_type=bhela_inv_item' );

	add_menu_page(
		__( 'Item Register', 'bhela-booking' ),
		'📦 ' . __( 'Stock', 'bhela-booking' ),
		'bhela_inv_view',
		'edit.php?post_type=bhela_inv_item',
		'',
		'dashicons-archive',
		27
	);
	if ( current_user_can( 'bhela_inv_count' ) ) {
		add_submenu_page( 'edit.php?post_type=bhela_inv_item', __( 'Monthly Stock Update', 'bhela-booking' ), '🔧 ' . __( 'Monthly Stock', 'bhela-booking' ), 'bhela_inv_count', 'bhela-bm-inv-month', 'bhela_bm_inv_month_page' );
	}
	add_submenu_page( 'edit.php?post_type=bhela_inv_item', __( 'Inventory Report', 'bhela-booking' ), '📐 ' . __( 'Inventory Report', 'bhela-booking' ), 'bhela_inv_view', 'bhela-bm-inv-report', 'bhela_bm_inv_report_page' );
	add_submenu_page( 'edit.php?post_type=bhela_inv_item', __( 'Asset Report', 'bhela-booking' ), '🏷️ ' . __( 'Asset Report', 'bhela-booking' ), 'bhela_inv_view', 'bhela-bm-inv-assets', 'bhela_bm_inv_asset_page' );
}
add_action( 'admin_menu', 'bhela_bm_inv_standalone_menu', 20 );

/**
 * Send a storekeeper somewhere useful.
 *
 * wp-admin's Dashboard is a dead end for a role with no other capabilities, so
 * they land on the register instead — the same courtesy costs.php does for a
 * cost-only preparer.
 */
function bhela_bm_inv_dashboard_redirect() {
	global $pagenow;
	if ( 'index.php' !== $pagenow || wp_doing_ajax() ) {
		return;
	}
	if ( ! current_user_can( 'bhela_inv_view' ) || current_user_can( 'edit_bhela_bookings' ) || current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=bhela_inv_item' ) );
	exit;
}
add_action( 'admin_init', 'bhela_bm_inv_dashboard_redirect' );

/* =========================================================
 * ITEM EDITOR
 * ========================================================= */

/** Meta boxes on an item. */
function bhela_bm_inv_item_boxes() {
	add_meta_box( 'bhela_inv_item_main', __( 'Item Details', 'bhela-booking' ), 'bhela_bm_inv_item_meta_cb', 'bhela_inv_item', 'normal', 'high' );
	add_meta_box( 'bhela_inv_item_files', __( 'Bills & Photos', 'bhela-booking' ), 'bhela_bm_inv_item_files_cb', 'bhela_inv_item', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'bhela_bm_inv_item_boxes' );

/** The item form. */
function bhela_bm_inv_item_meta_cb( $post ) {
	wp_nonce_field( 'bhela_bm_inv_item_save', 'bhela_inv_item_nonce' );
	$m = function ( $k, $d = '' ) use ( $post ) {
		$v = get_post_meta( $post->ID, $k, true );
		return '' !== $v ? $v : $d;
	};
	$code   = $m( '_bhela_inv_code' );
	$kind   = bhela_bm_inv_kind( $post->ID );
	$cat    = $m( '_bhela_inv_cat' );
	$locked = (bool) $m( '_bhela_inv_locked' );
	?>
	<?php if ( $locked ) : ?>
		<p class="bha-callout bha-callout--attention bha-callout--lead">🔒 <strong><?php esc_html_e( 'This item appears on a closed month.', 'bhela-booking' ); ?></strong>
			<?php esc_html_e( 'Its name and details can still be corrected, but its ID, kind and category are fixed, and it cannot be deleted — a closed report still renders them.', 'bhela-booking' ); ?></p>
	<?php endif; ?>
	<table class="form-table bha-form">
		<tr><th><?php esc_html_e( 'Item ID', 'bhela-booking' ); ?></th>
			<td><?php if ( $code ) : ?>
				<strong><?php echo esc_html( $code ); ?></strong>
				<p class="description"><?php esc_html_e( 'Minted once from the category and never changes — it is what the audit trail and any printed label refer to.', 'bhela-booking' ); ?></p>
			<?php else : ?>
				<em><?php esc_html_e( 'Assigned when you save.', 'bhela-booking' ); ?></em>
			<?php endif; ?></td></tr>
		<tr><th><?php esc_html_e( 'Kind', 'bhela-booking' ); ?></th>
			<td><?php if ( $locked ) : ?>
				<strong><?php echo esc_html( bhela_bm_inv_kinds()[ $kind ]['label'] ); ?></strong>
			<?php else : ?>
				<?php foreach ( bhela_bm_inv_kinds() as $key => $def ) : ?>
					<label style="margin-right:16px"><input type="radio" name="bhela_inv_kind" value="<?php echo esc_attr( $key ); ?>" <?php checked( $kind, $key ); ?>> <strong><?php echo esc_html( $def['label'] ); ?></strong></label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Inventory is consumed; an asset is not. This decides whether the monthly sheet offers a "Used" column, and it is fixed once the item appears on a closed month.', 'bhela-booking' ); ?></p>
			<?php endif; ?></td></tr>
		<tr><th><?php esc_html_e( 'Category', 'bhela-booking' ); ?></th>
			<td><?php if ( $locked ) : ?>
				<strong><?php echo esc_html( bhela_bm_inv_categories( true )[ $cat ]['label'] ?? $cat ); ?></strong>
			<?php else : ?>
				<select name="bhela_inv_cat" id="bhela_inv_cat">
					<?php foreach ( bhela_bm_inv_categories() as $slug => $def ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cat, $slug ); ?>><?php echo esc_html( $def['label'] ); ?> (<?php echo esc_html( $def['code'] ); ?>)</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?></td></tr>
		<tr><th><?php esc_html_e( 'Sub-category', 'bhela-booking' ); ?></th>
			<td><select name="bhela_inv_subcat">
				<option value=""><?php esc_html_e( '— none —', 'bhela-booking' ); ?></option>
				<?php foreach ( bhela_bm_inv_subcats() as $slug => $def ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $m( '_bhela_inv_subcat' ), $slug ); ?>><?php echo esc_html( $def['label'] ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
		<tr><th><?php esc_html_e( 'Location', 'bhela-booking' ); ?></th>
			<td><select name="bhela_inv_location">
				<?php foreach ( bhela_bm_inv_locations() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $m( '_bhela_inv_location' ), $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
		<tr><th><?php esc_html_e( 'Unit', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_inv_unit" value="<?php echo esc_attr( $m( '_bhela_inv_unit', 'PCS' ) ); ?>" class="regular-text"></td></tr>
		<tr><th><?php esc_html_e( 'Brand / Model', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_inv_brand" value="<?php echo esc_attr( $m( '_bhela_inv_brand' ) ); ?>" placeholder="<?php esc_attr_e( 'Brand', 'bhela-booking' ); ?>">
			<input type="text" name="bhela_inv_model" value="<?php echo esc_attr( $m( '_bhela_inv_model' ) ); ?>" placeholder="<?php esc_attr_e( 'Model', 'bhela-booking' ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Serial / Asset Tag', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_inv_serial" value="<?php echo esc_attr( $m( '_bhela_inv_serial' ) ); ?>" placeholder="<?php esc_attr_e( 'Serial number', 'bhela-booking' ); ?>">
			<input type="text" name="bhela_inv_asset_tag" value="<?php echo esc_attr( $m( '_bhela_inv_asset_tag' ) ); ?>" placeholder="<?php esc_attr_e( 'Asset tag', 'bhela-booking' ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Barcode / QR', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_inv_barcode" value="<?php echo esc_attr( $m( '_bhela_inv_barcode' ) ); ?>" class="regular-text">
			<p class="description"><?php esc_html_e( 'Stored so a scanner can find the item later. Nothing scans it yet — counts are typed.', 'bhela-booking' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Supplier', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_inv_supplier" value="<?php echo esc_attr( $m( '_bhela_inv_supplier' ) ); ?>" class="regular-text"></td></tr>
		<tr><th><?php esc_html_e( 'Purchase date / Invoice', 'bhela-booking' ); ?></th>
			<td><input type="date" name="bhela_inv_bought_on" value="<?php echo esc_attr( $m( '_bhela_inv_bought_on' ) ); ?>">
			<input type="text" name="bhela_inv_invoice" value="<?php echo esc_attr( $m( '_bhela_inv_invoice' ) ); ?>" placeholder="<?php esc_attr_e( 'Invoice / bill no.', 'bhela-booking' ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Unit value (৳)', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_inv_rate" inputmode="numeric" pattern="[0-9]*" value="<?php echo esc_attr( $m( '_bhela_inv_rate' ) ); ?>">
			<p class="description"><?php esc_html_e( 'What one of these is worth. Used to value closing stock; each month keeps the figure it closed with.', 'bhela-booking' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Warranty until', 'bhela-booking' ); ?></th>
			<td><input type="date" name="bhela_inv_warranty" value="<?php echo esc_attr( $m( '_bhela_inv_warranty' ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Responsible person', 'bhela-booking' ); ?></th>
			<td><input type="text" name="bhela_inv_owner" value="<?php echo esc_attr( $m( '_bhela_inv_owner' ) ); ?>" class="regular-text"></td></tr>
		<tr><th><?php esc_html_e( 'Description', 'bhela-booking' ); ?></th>
			<td><textarea name="bhela_inv_desc" rows="2" class="large-text"><?php echo esc_textarea( $m( '_bhela_inv_desc' ) ); ?></textarea></td></tr>
	</table>
	<?php
}

/** Bills and photos, with a plain file input — never a media frame. */
function bhela_bm_inv_item_files_cb( $post ) {
	$photos = array_map( 'intval', (array) get_post_meta( $post->ID, '_bhela_inv_photos', true ) );
	$bills  = array_map( 'intval', (array) get_post_meta( $post->ID, '_bhela_inv_bills', true ) );
	$photos = array_filter( $photos );
	$bills  = array_filter( $bills );
	?>
	<p class="bha-note"><?php esc_html_e( 'Photos of the item, and its purchase bill or warranty card. Images and PDF, up to 4 MB each.', 'bhela-booking' ); ?></p>
	<?php if ( $photos || $bills ) : ?>
		<ul class="bha-list">
			<?php foreach ( array_merge( $photos, $bills ) as $att ) : ?>
				<li><a href="<?php echo esc_url( (string) wp_get_attachment_url( $att ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $att ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( current_user_can( 'bhela_inv_attach' ) && $post->ID ) : ?>
		<p class="description"><?php esc_html_e( 'Upload from the button below the item, after saving.', 'bhela-booking' ); ?></p>
	<?php endif; ?>
	<?php
}

/**
 * Save an item.
 *
 * The ID, the kind and the category are frozen once the item appears on a closed
 * month; everything else — including the name — stays correctable forever, because
 * a typo in "Noodels Bowl" should not be permanent.
 */
function bhela_bm_inv_save_item( $post_id, $post ) {
	if ( 'bhela_inv_item' !== $post->post_type ) {
		return;
	}
	if ( ! isset( $_POST['bhela_inv_item_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_inv_item_nonce'] ) ), 'bhela_bm_inv_item_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'bhela_inv_items' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$locked = (bool) get_post_meta( $post_id, '_bhela_inv_locked', true );
	$before = array();
	$fields = array(
		'_bhela_inv_subcat'    => sanitize_key( $_POST['bhela_inv_subcat'] ?? '' ),
		'_bhela_inv_location'  => sanitize_key( $_POST['bhela_inv_location'] ?? '' ),
		'_bhela_inv_unit'      => sanitize_text_field( $_POST['bhela_inv_unit'] ?? 'PCS' ),
		'_bhela_inv_brand'     => sanitize_text_field( $_POST['bhela_inv_brand'] ?? '' ),
		'_bhela_inv_model'     => sanitize_text_field( $_POST['bhela_inv_model'] ?? '' ),
		'_bhela_inv_serial'    => sanitize_text_field( $_POST['bhela_inv_serial'] ?? '' ),
		'_bhela_inv_asset_tag' => sanitize_text_field( $_POST['bhela_inv_asset_tag'] ?? '' ),
		'_bhela_inv_barcode'   => sanitize_text_field( $_POST['bhela_inv_barcode'] ?? '' ),
		'_bhela_inv_supplier'  => sanitize_text_field( $_POST['bhela_inv_supplier'] ?? '' ),
		'_bhela_inv_bought_on' => sanitize_text_field( $_POST['bhela_inv_bought_on'] ?? '' ),
		'_bhela_inv_invoice'   => sanitize_text_field( $_POST['bhela_inv_invoice'] ?? '' ),
		'_bhela_inv_rate'      => max( 0, (int) ( $_POST['bhela_inv_rate'] ?? 0 ) ),
		'_bhela_inv_warranty'  => sanitize_text_field( $_POST['bhela_inv_warranty'] ?? '' ),
		'_bhela_inv_owner'     => sanitize_text_field( $_POST['bhela_inv_owner'] ?? '' ),
		'_bhela_inv_desc'      => sanitize_textarea_field( $_POST['bhela_inv_desc'] ?? '' ),
	);
	if ( ! $locked ) {
		$kind = sanitize_key( $_POST['bhela_inv_kind'] ?? '' );
		$fields['_bhela_inv_kind'] = isset( bhela_bm_inv_kinds()[ $kind ] ) ? $kind : 'inventory';
		$fields['_bhela_inv_cat']  = sanitize_key( $_POST['bhela_inv_cat'] ?? '' );
	}

	foreach ( $fields as $key => $value ) {
		$before[ $key ] = get_post_meta( $post_id, $key, true );
		update_post_meta( $post_id, $key, $value );
	}

	// Mint the ID once we know the category.
	$code = get_post_meta( $post_id, '_bhela_inv_code', true );
	if ( ! $code ) {
		$code = bhela_bm_inv_mint_code( get_post_meta( $post_id, '_bhela_inv_cat', true ) );
		update_post_meta( $post_id, '_bhela_inv_code', $code );
		if ( function_exists( 'bhela_bm_audit' ) ) {
			bhela_bm_audit( array(
				'channel'     => 'inv',
				'action'      => 'create',
				'object_type' => 'inv_item',
				'object_id'   => $post_id,
				'object_ref'  => $code,
				'field'       => 'code',
				'new_value'   => $code,
			) );
		}
	}

	if ( function_exists( 'bhela_bm_audit' ) ) {
		foreach ( $fields as $key => $value ) {
			if ( (string) $before[ $key ] === (string) $value ) {
				continue;
			}
			bhela_bm_audit( array(
				'channel'     => 'inv',
				'action'      => 'update',
				'object_type' => 'inv_item',
				'object_id'   => $post_id,
				'object_ref'  => $code,
				'field'       => str_replace( '_bhela_inv_', '', $key ),
				'old_value'   => $before[ $key ],
				'new_value'   => $value,
			) );
		}
	}
}
add_action( 'save_post', 'bhela_bm_inv_save_item', 10, 2 );

/** Columns on the register list. */
function bhela_bm_inv_item_columns( $cols ) {
	return array(
		'cb'       => $cols['cb'] ?? '',
		'title'    => __( 'Item', 'bhela-booking' ),
		'inv_code' => __( 'Item ID', 'bhela-booking' ),
		'inv_kind' => __( 'Kind', 'bhela-booking' ),
		'inv_cat'  => __( 'Category', 'bhela-booking' ),
		'inv_loc'  => __( 'Location', 'bhela-booking' ),
		'inv_rate' => __( 'Unit value', 'bhela-booking' ),
	);
}
add_filter( 'manage_bhela_inv_item_posts_columns', 'bhela_bm_inv_item_columns' );

/** Column contents. Counts stay raw integers; only money goes through the formatter. */
function bhela_bm_inv_item_column( $col, $post_id ) {
	switch ( $col ) {
		case 'inv_code':
			echo esc_html( get_post_meta( $post_id, '_bhela_inv_code', true ) ?: '—' );
			break;
		case 'inv_kind':
			$kind = bhela_bm_inv_kind( $post_id );
			$def  = bhela_bm_inv_kinds()[ $kind ];
			echo bhela_bm_status_pill( $def['label'], $def['tone'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
			break;
		case 'inv_cat':
			$cats = bhela_bm_inv_categories( true );
			$slug = get_post_meta( $post_id, '_bhela_inv_cat', true );
			echo esc_html( $cats[ $slug ]['label'] ?? '—' );
			$sub = get_post_meta( $post_id, '_bhela_inv_subcat', true );
			if ( $sub ) {
				$subs = bhela_bm_inv_subcats( true );
				echo '<br><span class="bha-sub">' . esc_html( $subs[ $sub ]['label'] ?? $sub ) . '</span>';
			}
			break;
		case 'inv_loc':
			$locs = bhela_bm_inv_locations( true );
			echo esc_html( $locs[ get_post_meta( $post_id, '_bhela_inv_location', true ) ] ?? '—' );
			break;
		case 'inv_rate':
			$rate = (int) get_post_meta( $post_id, '_bhela_inv_rate', true );
			echo '<span class="bha-num">' . esc_html( $rate ? bhela_bm_money( $rate ) : '—' ) . '</span>';
			break;
	}
}
add_action( 'manage_bhela_inv_item_posts_custom_column', 'bhela_bm_inv_item_column', 10, 2 );

/* =========================================================
 * READING A MONTH
 * ========================================================= */

/**
 * Every item in the register, as one indexed pass.
 *
 * Two `get_post_meta( $id )` calls per item would be 500 round trips; priming the
 * meta cache with `update_meta_cache()` makes the whole register a handful of
 * queries however many items there are. That is the difference between this and
 * the shape used by the existing dashboard totals, and the scale test in
 * tests/inventory-test.php is what holds it.
 *
 * @param array $filter kind, cat, loc.
 * @return array item_id => array{id,name,code,kind,cat,subcat,loc,unit,rate}
 */
function bhela_bm_inv_items( $filter = array() ) {
	$args = array(
		'post_type'      => 'bhela_inv_item',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	$meta = array();
	foreach ( array( 'kind' => '_bhela_inv_kind', 'cat' => '_bhela_inv_cat', 'loc' => '_bhela_inv_location' ) as $key => $mk ) {
		if ( ! empty( $filter[ $key ] ) ) {
			$meta[] = array( 'key' => $mk, 'value' => $filter[ $key ] );
		}
	}
	if ( $meta ) {
		$args['meta_query'] = $meta;
	}
	$ids = get_posts( $args );
	if ( ! $ids ) {
		return array();
	}
	// One query for every item's meta, instead of one per item per field.
	update_meta_cache( 'post', $ids );

	$cats = bhela_bm_inv_categories( true );
	$subs = bhela_bm_inv_subcats( true );
	$locs = bhela_bm_inv_locations( true );

	$out = array();
	foreach ( $ids as $id ) {
		$cat = get_post_meta( $id, '_bhela_inv_cat', true );
		$sub = get_post_meta( $id, '_bhela_inv_subcat', true );
		$loc = get_post_meta( $id, '_bhela_inv_location', true );
		$out[ (int) $id ] = array(
			'id'     => (int) $id,
			'name'   => get_the_title( $id ),
			'code'   => (string) get_post_meta( $id, '_bhela_inv_code', true ),
			'kind'   => bhela_bm_inv_kind( $id ),
			'cat'    => $cat,
			'cat_label' => (string) ( $cats[ $cat ]['label'] ?? '' ),
			'subcat' => (string) ( $subs[ $sub ]['label'] ?? '' ),
			'loc'    => (string) ( $locs[ $loc ] ?? '' ),
			'unit'   => (string) ( get_post_meta( $id, '_bhela_inv_unit', true ) ?: 'PCS' ),
			'rate'   => (int) get_post_meta( $id, '_bhela_inv_rate', true ),
		);
	}
	return $out;
}

/**
 * A month, resolved into rows ready to render.
 *
 * One post read plus one indexed items pass — no per-row queries, which is what
 * makes the report affordable at 500 items and what the scale assertion pins.
 *
 * @param string $month  YYYY-MM.
 * @param array  $filter kind, cat, loc.
 * @return array{month:string,period:int,status:string,rows:array,totals:array,drift:array,can_close:array,source:string}
 */
function bhela_bm_inv_month_data( $month, $filter = array() ) {
	$month  = bhela_bm_inv_month( $month );
	$period = $month ? bhela_bm_inv_period_id( $month ) : 0;

	$out = array(
		'month'     => $month,
		'period'    => $period,
		'status'    => $period ? bhela_bm_inv_status( $period ) : '',
		'rows'      => array(),
		'totals'    => bhela_bm_inv_totals( array() ),
		'drift'     => array( 'stale' => false, 'items' => array(), 'from' => 0 ),
		'can_close' => array( 'ok' => false, 'why' => array( 'no_month' ), 'detail' => array() ),
		'source'    => 'none',
	);
	if ( ! $period ) {
		return $out;
	}

	$lines   = bhela_bm_inv_stored_lines( $period );
	$opening = bhela_bm_inv_stored_opening( $period );
	$items   = bhela_bm_inv_items( $filter );

	$rows = array();
	foreach ( $items as $id => $item ) {
		$key  = bhela_bm_inv_line_key( $id );
		$line = isset( $lines[ $key ] ) ? array_merge( bhela_bm_inv_blank_line(), $lines[ $key ] ) : bhela_bm_inv_blank_line();
		// A line the sheet has never touched still shows its opening, so the sheet
		// reads as a full stocklist rather than only the rows somebody edited.
		if ( ! isset( $lines[ $key ] ) ) {
			$line['open'] = isset( $opening[ $key ] ) ? (int) $opening[ $key ] : 0;
			$line['rate'] = $item['rate'];
		}
		$chk    = bhela_bm_inv_line_check( $line );
		$rows[] = array_merge( $item, array( 'key' => $key, 'line' => $line, 'check' => $chk ) );
	}

	$out['rows']      = $rows;
	$out['totals']    = bhela_bm_inv_period_totals( $period );
	$out['drift']     = bhela_bm_inv_opening_drift( $period );
	$out['can_close'] = bhela_bm_inv_can_close( $period );
	$out['source']    = bhela_bm_inv_opening_source( $period );
	return $out;
}

/* =========================================================
 * SCREEN: MONTHLY UPDATE
 * ========================================================= */

/** The month picker's default: the current month. */
function bhela_bm_inv_current_month() {
	return current_time( 'Y-m' );
}

/** Months that already have a record, newest first, for the picker. */
function bhela_bm_inv_known_months() {
	$index = bhela_bm_inv_period_index();
	krsort( $index );
	return array_keys( $index );
}

/** The Monthly Update screen. */
function bhela_bm_inv_month_page() {
	if ( ! current_user_can( 'bhela_inv_count' ) ) {
		return;
	}
	$month = bhela_bm_inv_month( $_GET['month'] ?? '' );
	if ( '' === $month ) {
		$month = bhela_bm_inv_current_month();
	}
	$cat    = sanitize_key( $_GET['cat'] ?? '' );
	$period = bhela_bm_inv_period_id( $month );
	$data   = bhela_bm_inv_month_data( $month, $cat ? array( 'cat' => $cat ) : array() );
	$locked = $period ? bhela_bm_inv_is_locked( $period ) : false;
	$cats   = bhela_bm_inv_categories();
	$msg    = $period ? get_transient( 'bhela_bm_inv_msg_' . $period ) : false;
	if ( $msg ) {
		delete_transient( 'bhela_bm_inv_msg_' . $period );
	}

	$actions = '';
	if ( $period ) {
		$actions .= '<a class="button" href="' . esc_url( add_query_arg( array( 'bhela_inv_print' => '1', 'month' => $month ), admin_url( 'edit.php' ) ) ) . '" target="_blank">🖨️ ' . esc_html__( 'Print view', 'bhela-booking' ) . '</a>';
	}
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'🔧',
			__( 'Monthly Stock Update', 'bhela-booking' ),
			__( 'What came in, what went out, and what is actually on the boat. Each month opens on the last one\'s closing.', 'bhela-booking' ),
			$actions
		);

		if ( $msg ) {
			bhela_bm_inv_notice( $msg );
		}
		?>

		<div class="bha-bar">
			<form method="get">
				<input type="hidden" name="post_type" value="bhela_booking">
				<input type="hidden" name="page" value="bhela-bm-inv-month">
				<div class="bha-field bha-field--caps">
					<label for="bhela-inv-month"><?php esc_html_e( 'Month', 'bhela-booking' ); ?></label>
					<input type="month" id="bhela-inv-month" name="month" value="<?php echo esc_attr( $month ); ?>">
				</div>
				<div class="bha-field">
					<label for="bhela-inv-cat"><?php esc_html_e( 'Category', 'bhela-booking' ); ?></label>
					<select id="bhela-inv-cat" name="cat">
						<option value=""><?php esc_html_e( 'All categories', 'bhela-booking' ); ?></option>
						<?php foreach ( $cats as $slug => $def ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cat, $slug ); ?>><?php echo esc_html( $def['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="bha-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Show', 'bhela-booking' ); ?></button></div>
			</form>
		</div>

		<?php if ( ! $period ) : ?>
			<div class="bha-panel">
				<h2 class="bha-panel__title"><?php echo esc_html( mysql2date( 'F Y', $month . '-01' ) ); ?></h2>
				<p><?php esc_html_e( 'This month has not been opened yet.', 'bhela-booking' ); ?></p>
				<?php
				$prev    = bhela_bm_inv_prev_month( $month );
				$prev_id = $prev ? bhela_bm_inv_period_id( $prev ) : 0;
				if ( $prev_id && 'closed' !== bhela_bm_inv_status( $prev_id ) ) :
					?>
					<p class="bha-callout bha-callout--attention"><?php
						printf(
							/* translators: %s: month name */
							esc_html__( 'Close %s first. Each month opens on the previous one\'s closing, so they are opened in order.', 'bhela-booking' ),
							esc_html( mysql2date( 'F Y', $prev . '-01' ) )
						);
					?></p>
				<?php endif; ?>
				<?php if ( current_user_can( 'bhela_inv_count' ) ) : ?>
					<p class="bha-buttons"><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bhela_bm_inv_open_month', 'month' => $month ), admin_url( 'admin-post.php' ) ), 'bhela_bm_inv_open_' . $month ) ); ?>">📅 <?php esc_html_e( 'Open this month', 'bhela-booking' ); ?></a></p>
				<?php endif; ?>
			</div>
			</div>
			<?php
			return;
		endif;

		$t = $data['totals'];
		?>

		<div class="bha-printonly">
			<h2><?php echo esc_html( bhela_bm_get_settings()['business_name'] ); ?> — <?php esc_html_e( 'Stock Sheet', 'bhela-booking' ); ?></h2>
			<p><?php echo esc_html( mysql2date( 'F Y', $month . '-01' ) ); ?></p>
		</div>

		<div class="bha-cards">
			<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'Status', 'bhela-booking' ); ?></div>
				<div class="bha-card__value"><?php echo bhela_bm_inv_status_pill( $period ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
			<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'Items', 'bhela-booking' ); ?></div>
				<div class="bha-card__value"><?php echo esc_html( count( $data['rows'] ) ); ?></div></div>
			<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'Opening', 'bhela-booking' ); ?></div>
				<div class="bha-card__value"><?php echo esc_html( (int) $t['open'] ); ?></div></div>
			<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'Closing', 'bhela-booking' ); ?></div>
				<div class="bha-card__value"><?php echo esc_html( (int) $t['close'] ); ?></div></div>
			<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'Closing value', 'bhela-booking' ); ?></div>
				<div class="bha-card__value"><?php echo esc_html( bhela_bm_money( (int) $t['value'] ) ); ?></div></div>
			<?php if ( (int) $t['variance'] ) : ?>
				<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'Variances', 'bhela-booking' ); ?></div>
					<div class="bha-card__value is-attention"><?php echo esc_html( (int) $t['variance'] ); ?></div></div>
			<?php endif; ?>
		</div>

		<?php
		// Drift: the month underneath this one moved after we took its closings.
		// Reported, never silently corrected — see bhela_bm_inv_opening_drift().
		if ( $data['drift']['stale'] ) :
			?>
			<div class="bha-panel bha-panel--alert">
				<h2 class="bha-panel__title">⚠️ <?php esc_html_e( 'The opening is out of date', 'bhela-booking' ); ?></h2>
				<p><?php
					printf(
						/* translators: %s: month name */
						esc_html__( '%s changed after this sheet took its opening figures, so the two no longer agree. Nothing has been altered here — re-take the opening when you are ready, which sends this sheet back to draft for a fresh check.', 'bhela-booking' ),
						esc_html( mysql2date( 'F Y', bhela_bm_inv_prev_month( $month ) . '-01' ) )
					);
				?></p>
				<div class="bha-scroll">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Item', 'bhela-booking' ); ?></th><th class="bha-num"><?php esc_html_e( 'This sheet says', 'bhela-booking' ); ?></th><th class="bha-num"><?php esc_html_e( 'Last month now says', 'bhela-booking' ); ?></th><th class="bha-num"><?php esc_html_e( 'Difference', 'bhela-booking' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $data['drift']['items'] as $d ) : ?>
							<tr>
								<td><?php echo esc_html( get_the_title( $d['item'] ) ); ?> <span class="bha-sub"><?php echo esc_html( bhela_bm_inv_item_code( $d['item'] ) ); ?></span></td>
								<td class="bha-num"><?php echo esc_html( $d['snapshot'] ); ?></td>
								<td class="bha-num"><?php echo esc_html( $d['live'] ); ?></td>
								<td class="bha-num"><?php echo esc_html( ( $d['diff'] > 0 ? '+' : '' ) . $d['diff'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php if ( current_user_can( 'bhela_inv_approve' ) && ! $locked ) : ?>
					<p class="bha-buttons"><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bhela_bm_inv_retake', 'period' => $period ), admin_url( 'admin-post.php' ) ), 'bhela_bm_inv_retake_' . $period ) ); ?>">🔄 <?php esc_html_e( 'Re-take the opening', 'bhela-booking' ); ?></a></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $locked ) : ?>
			<p class="bha-callout bha-callout--good bha-callout--lead">🔒 <strong><?php esc_html_e( 'This month is closed.', 'bhela-booking' ); ?></strong>
				<?php esc_html_e( 'Its figures are the next month\'s opening and cannot be edited — not from this form, not from the database, and it cannot be deleted. Reopen it if something genuinely has to change; that is recorded.', 'bhela-booking' ); ?></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bhela_bm_inv_save_month">
			<input type="hidden" name="bhela_inv_period" value="<?php echo esc_attr( $period ); ?>">
			<input type="hidden" name="bhela_inv_cat" value="<?php echo esc_attr( $cat ); ?>">
			<?php wp_nonce_field( 'bhela_bm_inv_save_' . $period, 'bhela_inv_nonce' ); ?>

			<div class="bha-scroll">
				<table class="widefat striped bha-sheet">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Item', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'Opening', 'bhela-booking' ); ?></th>
							<?php foreach ( bhela_bm_inv_movement_types() as $mk => $def ) : ?>
								<th class="bha-num"><?php echo esc_html( $def['label'] ); ?></th>
							<?php endforeach; ?>
							<th class="bha-num"><?php esc_html_e( 'Closing', 'bhela-booking' ); ?></th>
							<?php foreach ( bhela_bm_inv_condition_types() as $ck => $def ) : ?>
								<th class="bha-num"><?php echo esc_html( $def['label'] ); ?></th>
							<?php endforeach; ?>
							<th class="bha-num"><?php esc_html_e( 'Counted', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'Variance', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'bhela-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( ! $data['rows'] ) : ?>
						<tr><td colspan="20"><?php
							// Both routes out of an empty register, as links rather than as
							// advice — this is the screen an owner lands on first.
							printf(
								/* translators: 1: link to the CSV importer, 2: link to add one item */
								esc_html__( 'No items in the register yet. %1$s, or %2$s.', 'bhela-booking' ),
								'<a href="' . esc_url( function_exists( 'bhela_bm_inv_import_url' )
									? bhela_bm_inv_import_url()
									: add_query_arg( array( 'post_type' => 'bhela_booking', 'page' => 'bhela-bm-inv-import' ), admin_url( 'edit.php' ) )
								) . '">' . esc_html__( 'import it from a spreadsheet', 'bhela-booking' ) . '</a>',
								'<a href="' . esc_url( admin_url( 'post-new.php?post_type=bhela_inv_item' ) ) . '">' . esc_html__( 'add one by hand', 'bhela-booking' ) . '</a>'
							);
						?></td></tr>
					<?php endif; ?>
					<?php foreach ( $data['rows'] as $row ) :
						$l   = $row['line'];
						$chk = $row['check'];
						$n   = 'bhela_inv_lines[' . $row['key'] . ']';
						?>
						<tr<?php echo $chk['ok'] ? '' : ' class="bha-row--muted"'; ?>>
							<td><strong><?php echo esc_html( $row['name'] ); ?></strong>
								<span class="bha-sub"><?php echo esc_html( $row['code'] ); ?> · <?php echo esc_html( $row['unit'] ); ?><?php echo $row['loc'] ? ' · ' . esc_html( $row['loc'] ) : ''; ?></span></td>
							<?php // Opening is a cell, never an input — there is nothing here for a crafted POST to aim at. ?>
							<td class="bha-num"><?php echo esc_html( (int) $l['open'] ); ?></td>
							<?php foreach ( bhela_bm_inv_movement_types() as $mk => $def ) :
								$allowed = in_array( $row['kind'], $def['kinds'], true );
								$gated   = 'adj' === $mk && ! current_user_can( 'bhela_inv_adjust' );
								?>
								<td class="bha-num"><?php if ( ! $allowed ) : ?>
									<span class="bha-muted-em">—</span>
								<?php elseif ( $locked || $gated ) : ?>
									<?php echo esc_html( (int) $l[ $mk ] ); ?>
								<?php else : ?>
									<input type="number" name="<?php echo esc_attr( $n . '[' . $mk . ']' ); ?>" value="<?php echo esc_attr( (int) $l[ $mk ] ); ?>"<?php echo empty( $def['signed'] ) ? ' min="0"' : ''; ?>>
								<?php endif; ?></td>
							<?php endforeach; ?>
							<td class="bha-num"><strong><?php echo esc_html( $chk['close'] ); ?></strong></td>
							<?php foreach ( bhela_bm_inv_condition_types() as $ck => $def ) : ?>
								<td class="bha-num"><?php if ( $locked ) : ?>
									<?php echo esc_html( (int) $l[ $ck ] ); ?>
								<?php else : ?>
									<input type="number" min="0" name="<?php echo esc_attr( $n . '[' . $ck . ']' ); ?>" value="<?php echo esc_attr( (int) $l[ $ck ] ); ?>">
								<?php endif; ?></td>
							<?php endforeach; ?>
							<td class="bha-num"><?php if ( $locked ) : ?>
								<?php echo esc_html( null === $l['count'] ? '—' : (int) $l['count'] ); ?>
							<?php else : ?>
								<input type="number" min="0" name="<?php echo esc_attr( $n . '[count]' ); ?>" value="<?php echo esc_attr( null === $l['count'] ? '' : (int) $l['count'] ); ?>" placeholder="—">
							<?php endif; ?></td>
							<td class="bha-num <?php echo ( null !== $chk['variance'] && 0 !== $chk['variance'] ) ? 'bha-num--due' : 'bha-num--clear'; ?>">
								<?php echo esc_html( null === $chk['variance'] ? '—' : ( ( $chk['variance'] > 0 ? '+' : '' ) . $chk['variance'] ) ); ?>
								<?php if ( ! $chk['ok'] ) : ?>
									<br><span class="bha-flag"><?php
										printf(
											/* translators: %d: the difference between the condition split and the closing quantity */
											esc_html__( 'split is off by %d', 'bhela-booking' ),
											(int) $chk['diff']
										);
									?></span>
								<?php endif; ?>
							</td>
							<td><?php if ( $locked ) : ?>
								<?php echo esc_html( $l['reason'] ); ?>
							<?php else : ?>
								<input type="text" name="<?php echo esc_attr( $n . '[reason]' ); ?>" value="<?php echo esc_attr( $l['reason'] ); ?>" placeholder="<?php esc_attr_e( 'why it differs', 'bhela-booking' ); ?>">
							<?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( ! $locked && $data['rows'] ) : ?>
				<p class="bha-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save the sheet', 'bhela-booking' ); ?></button>
					<span class="bha-note"><?php esc_html_e( 'Every changed figure is recorded in the Audit Trail with what it was before.', 'bhela-booking' ); ?></span></p>
			<?php endif; ?>
		</form>

		<?php bhela_bm_inv_workflow_panel( $period, $data ); ?>
	</div>
	<?php
}

/** Surface the result of the last action on this sheet. */
function bhela_bm_inv_notice( $msg ) {
	$reasons = bhela_bm_inv_close_reasons();
	if ( 'ok' === ( $msg['type'] ?? '' ) ) {
		$text = __( 'Saved.', 'bhela-booking' );
		if ( 'save' === ( $msg['action'] ?? '' ) ) {
			$n    = (int) ( $msg['n'] ?? 0 );
			$text = $n
				/* translators: %d: number of changed figures */
				? sprintf( _n( 'Saved — %d figure changed and recorded.', 'Saved — %d figures changed and recorded.', $n, 'bhela-booking' ), $n )
				: __( 'Saved — nothing had changed, so nothing was recorded.', 'bhela-booking' );
		} elseif ( 'retake' === ( $msg['action'] ?? '' ) ) {
			$n    = (int) ( $msg['n'] ?? 0 );
			/* translators: %d: number of items whose opening moved */
			$text = sprintf( _n( 'Opening re-taken — %d item moved. The sheet is back to draft.', 'Opening re-taken — %d items moved. The sheet is back to draft.', $n, 'bhela-booking' ), $n );
		}
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $text ) );
		return;
	}
	$why = (array) ( $msg['why'] ?? array() );
	echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Could not do that:', 'bhela-booking' ) . '</strong></p><ul style="margin-left:18px;list-style:disc">';
	foreach ( $why as $key ) {
		if ( 'locked' === $key ) {
			echo '<li>' . esc_html__( 'This month is closed. Reopen it first.', 'bhela-booking' ) . '</li>';
			continue;
		}
		if ( 'later_closed' === $key ) {
			echo '<li>' . esc_html( sprintf(
				/* translators: %s: month */
				__( '%s is already closed. Months reopen newest first, so that one has to come back before this one.', 'bhela-booking' ),
				$msg['blocker'] ?? ''
			) ) . '</li>';
			continue;
		}
		echo '<li>' . esc_html( $reasons[ $key ] ?? $key ) . '</li>';
	}
	echo '</ul></div>';
}

/** The count → check → close panel. */
function bhela_bm_inv_workflow_panel( $period, $data ) {
	$status = bhela_bm_inv_status( $period );
	$month  = bhela_bm_inv_period_month( $period );
	$url    = function ( $action ) use ( $period ) {
		return wp_nonce_url(
			add_query_arg( array( 'action' => 'bhela_bm_inv_transition', 'period' => $period, 'do' => $action ), admin_url( 'admin-post.php' ) ),
			'bhela_bm_inv_transition_' . $period
		);
	};
	$stamp = function ( $step ) use ( $period ) {
		$by = (int) get_post_meta( $period, '_bhela_inv_' . $step . '_by', true );
		$at = get_post_meta( $period, '_bhela_inv_' . $step . '_at', true );
		if ( ! $by ) {
			return '';
		}
		$u = get_userdata( $by );
		return sprintf( '%s · %s', $u ? $u->display_name : '#' . $by, $at ? mysql2date( 'j M Y', $at ) : '' );
	};
	$can = $data['can_close'];
	?>
	<div class="bha-panel bha-noprint">
		<h2 class="bha-panel__title"><?php esc_html_e( 'Where this month stands', 'bhela-booking' ); ?></h2>
		<table>
			<tr><td><?php esc_html_e( 'Opening came from', 'bhela-booking' ); ?></td><td><strong><?php
				echo esc_html( array(
					'import' => __( 'the imported 21 July baseline', 'bhela-booking' ),
					'carry'  => __( 'last month\'s closing', 'bhela-booking' ),
					'none'   => __( 'nothing yet', 'bhela-booking' ),
				)[ $data['source'] ] ?? $data['source'] );
			?></strong></td></tr>
			<?php foreach ( array( 'counted' => __( 'Counted by', 'bhela-booking' ), 'checked' => __( 'Checked by', 'bhela-booking' ), 'closed' => __( 'Closed by', 'bhela-booking' ) ) as $step => $label ) :
				$s = $stamp( $step );
				if ( ! $s ) {
					continue;
				}
				?>
				<tr><td><?php echo esc_html( $label ); ?></td><td class="bha-sign"><?php echo esc_html( $s ); ?></td></tr>
			<?php endforeach; ?>
		</table>

		<?php if ( ! $can['ok'] && 'closed' !== $status ) : ?>
			<div class="bha-callout bha-callout--attention">
				<strong><?php esc_html_e( 'Before this month can close:', 'bhela-booking' ); ?></strong>
				<ul style="margin:6px 0 0 18px;list-style:disc">
					<?php $reasons = bhela_bm_inv_close_reasons(); ?>
					<?php foreach ( $can['why'] as $key ) : ?>
						<li><?php echo esc_html( $reasons[ $key ] ?? $key ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<p class="bha-buttons">
			<?php foreach ( bhela_bm_inv_transitions() as $action => $t ) :
				if ( ! in_array( $status, $t['from'], true ) || ! current_user_can( $t['cap'] ) ) {
					continue;
				}
				$blocked = ( 'close' === $action && ! $can['ok'] );
				$labels  = array(
					'count'  => __( '✅ Submit for checking', 'bhela-booking' ),
					'check'  => __( '🔍 Mark as checked', 'bhela-booking' ),
					'close'  => __( '🔒 Close the month', 'bhela-booking' ),
					'return' => __( '↩️ Return for a recount', 'bhela-booking' ),
					'reopen' => __( '🔓 Reopen', 'bhela-booking' ),
				);
				?>
				<a class="button <?php echo 'close' === $action ? 'button-primary' : ''; ?>"
					href="<?php echo esc_url( $url( $action ) ); ?>"
					<?php echo $blocked ? 'aria-disabled="true" onclick="return false" style="pointer-events:none;opacity:.5"' : ''; ?>><?php
					echo esc_html( $labels[ $action ] ?? $action );
				?></a>
			<?php endforeach; ?>
		</p>
		<?php if ( 'closed' === $status && ! current_user_can( 'bhela_inv_reopen' ) ) : ?>
			<p class="bha-note"><?php esc_html_e( 'Reopening a closed month needs a separate permission, because every later month opened on this one\'s figures.', 'bhela-booking' ); ?></p>
		<?php endif; ?>
		<?php $note = get_post_meta( $period, '_bhela_inv_note', true ); ?>
		<?php if ( $note ) : ?>
			<p class="bha-panel__note"><strong><?php esc_html_e( 'Note:', 'bhela-booking' ); ?></strong> <?php echo esc_html( $note ); ?></p>
		<?php endif; ?>
		<p class="bha-note"><?php
			printf(
				/* translators: %s: month */
				esc_html__( 'Sheet for %s.', 'bhela-booking' ),
				esc_html( $month )
			);
		?></p>
	</div>
	<?php
}

/** Open a month — the only way a period record is ever created. */
function bhela_bm_inv_open_month() {
	$month = bhela_bm_inv_month( $_GET['month'] ?? '' );
	check_admin_referer( 'bhela_bm_inv_open_' . $month );

	if ( ! current_user_can( 'bhela_inv_count' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'bhela-booking' ), 403 );
	}
	if ( '' === $month ) {
		wp_die( esc_html__( 'That is not a month.', 'bhela-booking' ), 400 );
	}

	$id = bhela_bm_inv_period_id( $month, true );
	if ( $id ) {
		bhela_bm_inv_take_opening( $id );
		if ( function_exists( 'bhela_bm_audit' ) ) {
			bhela_bm_audit( array(
				'channel'     => 'period',
				'action'      => 'create',
				'object_type' => 'inv_period',
				'object_id'   => $id,
				'object_ref'  => $month,
				'field'       => 'status',
				'new_value'   => 'draft',
			) );
		}
	}
	wp_safe_redirect( bhela_bm_inv_month_url( $month ) );
	exit;
}
add_action( 'admin_post_bhela_bm_inv_open_month', 'bhela_bm_inv_open_month' );

/* =========================================================
 * SCREENS: THE TWO REPORTS
 *
 * One builder, two callers. §19 and §20 differ by which columns matter — an
 * inventory report is about consumption, an asset report about condition — not by
 * where the figures live, so a second data path would only be a second place for
 * them to disagree.
 * ========================================================= */

/** The Inventory Report (§19). */
function bhela_bm_inv_report_page() {
	bhela_bm_inv_render_report( 'inventory', '📐', __( 'Inventory Report', 'bhela-booking' ), __( 'What was on hand, what came in, what got used, and what is left.', 'bhela-booking' ) );
}

/** The Asset Report (§20). */
function bhela_bm_inv_asset_page() {
	bhela_bm_inv_render_report( 'asset', '🏷️', __( 'Asset Report', 'bhela-booking' ), __( 'Every asset, where it is, and what condition it is in. Repair moves an asset between columns without changing the count.', 'bhela-booking' ) );
}

/**
 * Render one of the two monthly reports.
 *
 * @param string $kind  inventory|asset.
 * @param string $icon  Screen emoji.
 * @param string $title Screen title.
 * @param string $lead  One-line explanation.
 */
function bhela_bm_inv_render_report( $kind, $icon, $title, $lead ) {
	if ( ! current_user_can( 'bhela_inv_view' ) ) {
		return;
	}
	$month = bhela_bm_inv_month( $_GET['month'] ?? '' );
	if ( '' === $month ) {
		$month = bhela_bm_inv_current_month();
	}
	$cat  = sanitize_key( $_GET['cat'] ?? '' );
	$loc  = sanitize_key( $_GET['loc'] ?? '' );
	$data = bhela_bm_inv_month_data( $month, array_filter( array( 'kind' => $kind, 'cat' => $cat, 'loc' => $loc ) ) );

	$csv = wp_nonce_url(
		add_query_arg( array( 'action' => 'bhela_bm_inv_csv', 'kind' => $kind, 'month' => $month, 'cat' => $cat, 'loc' => $loc ), admin_url( 'admin-post.php' ) ),
		'bhela_bm_inv_csv'
	);
	$actions = '<button type="button" class="button" onclick="window.print()">🖨️ ' . esc_html__( 'Print / PDF', 'bhela-booking' ) . '</button>'
		. ' <a class="button" href="' . esc_url( $csv ) . '">📊 ' . esc_html__( 'Download CSV', 'bhela-booking' ) . '</a>';

	// Only the columns this report is actually about.
	$movements = 'asset' === $kind
		? array( 'add', 'tin', 'tout', 'loss', 'disp', 'adj' )
		: array( 'add', 'tin', 'tout', 'use', 'loss', 'disp', 'adj' );
	$all_moves = bhela_bm_inv_movement_types();
	$conds     = bhela_bm_inv_condition_types();
	$t         = bhela_bm_inv_totals( wp_list_pluck( $data['rows'], 'line' ) );
	?>
	<div class="wrap bha-page">
		<?php bhela_bm_screen_header( $icon, $title, $lead, $actions ); ?>

		<div class="bha-bar">
			<form method="get">
				<input type="hidden" name="post_type" value="bhela_booking">
				<input type="hidden" name="page" value="<?php echo esc_attr( 'asset' === $kind ? 'bhela-bm-inv-assets' : 'bhela-bm-inv-report' ); ?>">
				<div class="bha-field bha-field--caps">
					<label for="bhela-inv-rmonth"><?php esc_html_e( 'Month', 'bhela-booking' ); ?></label>
					<input type="month" id="bhela-inv-rmonth" name="month" value="<?php echo esc_attr( $month ); ?>">
				</div>
				<div class="bha-field">
					<label for="bhela-inv-rcat"><?php esc_html_e( 'Category', 'bhela-booking' ); ?></label>
					<select id="bhela-inv-rcat" name="cat">
						<option value=""><?php esc_html_e( 'All', 'bhela-booking' ); ?></option>
						<?php foreach ( bhela_bm_inv_categories( true ) as $slug => $def ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cat, $slug ); ?>><?php echo esc_html( $def['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="bha-field">
					<label for="bhela-inv-rloc"><?php esc_html_e( 'Location', 'bhela-booking' ); ?></label>
					<select id="bhela-inv-rloc" name="loc">
						<option value=""><?php esc_html_e( 'All', 'bhela-booking' ); ?></option>
						<?php foreach ( bhela_bm_inv_locations( true ) as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $loc, $slug ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="bha-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Show', 'bhela-booking' ); ?></button></div>
			</form>
		</div>

		<div class="bha-printonly">
			<h2><?php echo esc_html( bhela_bm_get_settings()['business_name'] ); ?> — <?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( mysql2date( 'F Y', $month . '-01' ) ); ?></p>
		</div>

		<?php if ( ! $data['period'] ) : ?>
			<div class="bha-panel"><p><?php
				printf(
					/* translators: %s: month name */
					esc_html__( '%s has not been opened yet, so there is nothing to report.', 'bhela-booking' ),
					esc_html( mysql2date( 'F Y', $month . '-01' ) )
				);
			?></p></div>
			</div>
			<?php
			return;
		endif;
		?>

		<div class="bha-tiles">
			<div class="bha-tile"><div class="bha-tile__value"><?php echo esc_html( count( $data['rows'] ) ); ?></div><div class="bha-tile__label"><?php esc_html_e( 'Items', 'bhela-booking' ); ?></div></div>
			<div class="bha-tile"><div class="bha-tile__value"><?php echo esc_html( (int) $t['open'] ); ?></div><div class="bha-tile__label"><?php esc_html_e( 'Opening', 'bhela-booking' ); ?></div></div>
			<div class="bha-tile"><div class="bha-tile__value"><?php echo esc_html( (int) $t['close'] ); ?></div><div class="bha-tile__label"><?php esc_html_e( 'Closing', 'bhela-booking' ); ?></div></div>
			<?php if ( 'asset' === $kind ) : ?>
				<div class="bha-tile"><div class="bha-tile__value is-good"><?php echo esc_html( (int) $t['good'] ); ?></div><div class="bha-tile__label"><?php esc_html_e( 'Good', 'bhela-booking' ); ?></div></div>
				<div class="bha-tile"><div class="bha-tile__value is-attention"><?php echo esc_html( (int) $t['ur'] ); ?></div><div class="bha-tile__label"><?php esc_html_e( 'Under Repair', 'bhela-booking' ); ?></div></div>
				<div class="bha-tile"><div class="bha-tile__value is-danger"><?php echo esc_html( (int) $t['dam'] ); ?></div><div class="bha-tile__label"><?php esc_html_e( 'Damaged', 'bhela-booking' ); ?></div></div>
			<?php else : ?>
				<div class="bha-tile"><div class="bha-tile__value"><?php echo esc_html( (int) $t['use'] ); ?></div><div class="bha-tile__label"><?php esc_html_e( 'Consumed', 'bhela-booking' ); ?></div></div>
			<?php endif; ?>
			<div class="bha-tile"><div class="bha-tile__value"><?php echo esc_html( bhela_bm_money( (int) $t['value'] ) ); ?></div><div class="bha-tile__label"><?php esc_html_e( 'Closing value', 'bhela-booking' ); ?></div></div>
		</div>

		<?php if ( $data['drift']['stale'] ) : ?>
			<p class="bha-callout bha-callout--attention">⚠️ <?php esc_html_e( 'The month before this one changed after these opening figures were taken, so this report and the one before it do not agree yet. Re-take the opening on the Monthly Stock screen.', 'bhela-booking' ); ?></p>
		<?php endif; ?>

		<div class="bha-scroll">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Location', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Opening', 'bhela-booking' ); ?></th>
						<?php foreach ( $movements as $mk ) : ?>
							<th class="bha-num"><?php echo esc_html( $all_moves[ $mk ]['label'] ); ?></th>
						<?php endforeach; ?>
						<th class="bha-num"><?php esc_html_e( 'Closing', 'bhela-booking' ); ?></th>
						<?php if ( 'asset' === $kind ) : ?>
							<?php foreach ( $conds as $def ) : ?>
								<th class="bha-num"><?php echo esc_html( $def['label'] ); ?></th>
							<?php endforeach; ?>
						<?php endif; ?>
						<th class="bha-num"><?php esc_html_e( 'Counted', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Value', 'bhela-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $data['rows'] ) : ?>
					<tr><td colspan="14"><?php esc_html_e( 'Nothing in the register matches this filter.', 'bhela-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $data['rows'] as $row ) :
					$l   = $row['line'];
					$chk = $row['check'];
					?>
					<tr>
						<td><strong><?php echo esc_html( $row['name'] ); ?></strong>
							<span class="bha-sub"><?php echo esc_html( $row['code'] ); ?><?php echo $row['cat_label'] ? ' · ' . esc_html( $row['cat_label'] ) : ''; ?></span></td>
						<td><?php echo esc_html( $row['loc'] ? $row['loc'] : '—' ); ?></td>
						<td class="bha-num"><?php echo esc_html( (int) $l['open'] ); ?></td>
						<?php foreach ( $movements as $mk ) : ?>
							<td class="bha-num"><?php echo esc_html( (int) $l[ $mk ] ); ?></td>
						<?php endforeach; ?>
						<td class="bha-num"><strong><?php echo esc_html( $chk['close'] ); ?></strong></td>
						<?php if ( 'asset' === $kind ) : ?>
							<?php foreach ( array_keys( $conds ) as $ck ) : ?>
								<td class="bha-num"><?php echo esc_html( (int) $l[ $ck ] ); ?></td>
							<?php endforeach; ?>
						<?php endif; ?>
						<td class="bha-num<?php echo ( null !== $chk['variance'] && 0 !== $chk['variance'] ) ? ' bha-num--due' : ''; ?>"><?php
							echo esc_html( null === $l['count'] ? '—' : (int) $l['count'] );
							if ( null !== $chk['variance'] && 0 !== $chk['variance'] ) {
								echo '<br><span class="bha-sub">' . esc_html( ( $chk['variance'] > 0 ? '+' : '' ) . $chk['variance'] ) . '</span>';
							}
						?></td>
						<td class="bha-num"><?php echo esc_html( bhela_bm_money( $chk['close'] * (int) $l['rate'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<?php if ( $data['rows'] ) : ?>
					<tfoot>
						<tr class="bha-row--total">
							<td colspan="2"><strong><?php esc_html_e( 'TOTAL', 'bhela-booking' ); ?></strong></td>
							<td class="bha-num"><?php echo esc_html( (int) $t['open'] ); ?></td>
							<?php foreach ( $movements as $mk ) : ?>
								<td class="bha-num"><?php echo esc_html( (int) $t[ $mk ] ); ?></td>
							<?php endforeach; ?>
							<td class="bha-num"><strong><?php echo esc_html( (int) $t['close'] ); ?></strong></td>
							<?php if ( 'asset' === $kind ) : ?>
								<?php foreach ( array_keys( $conds ) as $ck ) : ?>
									<td class="bha-num"><?php echo esc_html( (int) $t[ $ck ] ); ?></td>
								<?php endforeach; ?>
							<?php endif; ?>
							<td class="bha-num">—</td>
							<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( (int) $t['value'] ) ); ?></strong></td>
						</tr>
					</tfoot>
				<?php endif; ?>
			</table>
		</div>
	</div>
	<?php
}

/**
 * Stream either report as CSV.
 *
 * Same shape as the Trip Report's export: capability, nonce, re-sanitise
 * everything, BOM first so Excel reads Bengali, then fputcsv — and every free-text
 * cell through bhela_bm_csv_cell(), because an item description is exactly the
 * kind of field a formula hides in.
 */
function bhela_bm_inv_csv() {
	if ( ! current_user_can( 'bhela_inv_view' ) ) {
		wp_die( esc_html__( 'You are not allowed to export the register.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_inv_csv' );

	$kind  = 'asset' === ( $_GET['kind'] ?? '' ) ? 'asset' : 'inventory';
	$month = bhela_bm_inv_month( $_GET['month'] ?? '' );
	if ( '' === $month ) {
		$month = bhela_bm_inv_current_month();
	}
	$data = bhela_bm_inv_month_data( $month, array_filter( array(
		'kind' => $kind,
		'cat'  => sanitize_key( $_GET['cat'] ?? '' ),
		'loc'  => sanitize_key( $_GET['loc'] ?? '' ),
	) ) );

	$moves = array_keys( bhela_bm_inv_movement_types() );
	$conds = array_keys( bhela_bm_inv_condition_types() );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bhela-' . $kind . '-' . $month . '.csv"' );

	$fh = fopen( 'php://output', 'w' );
	// Without this, Excel reads the file as the local codepage and every Bengali
	// item name arrives as mojibake.
	fwrite( $fh, "\xEF\xBB\xBF" );

	$head = array( 'Item ID', 'Item', 'Category', 'Sub-category', 'Location', 'Unit', 'Opening' );
	foreach ( $moves as $mk ) {
		$head[] = bhela_bm_inv_movement_types()[ $mk ]['label'];
	}
	$head[] = 'Closing';
	foreach ( $conds as $ck ) {
		$head[] = bhela_bm_inv_condition_types()[ $ck ]['label'];
	}
	$head = array_merge( $head, array( 'Counted', 'Variance', 'Unit value', 'Closing value', 'Reason', 'Remark' ) );
	fputcsv( $fh, $head );

	foreach ( $data['rows'] as $row ) {
		$l   = $row['line'];
		$chk = $row['check'];
		$out = array(
			bhela_bm_csv_cell( $row['code'] ),
			bhela_bm_csv_cell( $row['name'] ),
			bhela_bm_csv_cell( $row['cat_label'] ),
			bhela_bm_csv_cell( $row['subcat'] ),
			bhela_bm_csv_cell( $row['loc'] ),
			bhela_bm_csv_cell( $row['unit'] ),
			(int) $l['open'],
		);
		foreach ( $moves as $mk ) {
			$out[] = (int) $l[ $mk ];
		}
		$out[] = $chk['close'];
		foreach ( $conds as $ck ) {
			$out[] = (int) $l[ $ck ];
		}
		// An uncounted line gets an empty cell, not a dash, so the column stays
		// countable — the same rule the Trip Report's export follows.
		$out[] = null === $l['count'] ? '' : (int) $l['count'];
		$out[] = null === $chk['variance'] ? '' : $chk['variance'];
		$out[] = (int) $l['rate'];
		$out[] = $chk['close'] * (int) $l['rate'];
		$out[] = bhela_bm_csv_cell( $l['reason'] );
		$out[] = bhela_bm_csv_cell( $l['remark'] );
		fputcsv( $fh, $out );
	}

	$t = bhela_bm_inv_totals( wp_list_pluck( $data['rows'], 'line' ) );
	fputcsv( $fh, array() );
	$tot = array( '', 'TOTAL', '', '', '', '', (int) $t['open'] );
	foreach ( $moves as $mk ) {
		$tot[] = (int) $t[ $mk ];
	}
	$tot[] = (int) $t['close'];
	foreach ( $conds as $ck ) {
		$tot[] = (int) $t[ $ck ];
	}
	$tot = array_merge( $tot, array( '', '', '', (int) $t['value'], '', '' ) );
	fputcsv( $fh, $tot );

	fclose( $fh );
	exit;
}
add_action( 'admin_post_bhela_bm_inv_csv', 'bhela_bm_inv_csv' );

/* =========================================================
 * SETTINGS: THE THREE LISTS
 * ========================================================= */

/**
 * The Categories & Locations panel, rendered into the Settings screen's tab strip.
 *
 * Folded into Settings rather than given a menu slot, because that is where the
 * cost heads and expense types already live — an owner looking for "the lists"
 * has one place to look.
 */
function bhela_bm_inv_lists_panel() {
	$cats = bhela_bm_inv_categories( true );
	$subs = bhela_bm_inv_subcats( true );
	$locs = get_option( 'bhela_bm_inv_locations', null );
	$locs = is_array( $locs ) ? $locs : array_map( function ( $l ) {
		return array( 'label' => $l, 'retired' => 0 );
	}, bhela_bm_inv_location_defaults() );
	?>
	<p class="bha-set__lead"><?php esc_html_e( 'The lists every item is filed under. A category\'s short code is baked into every Item ID minted under it (BHELA-KIT-0001), so the code is fixed once it has been used — rename the category freely, but the code stays.', 'bhela-booking' ); ?></p>

	<h3 class="bha-set__sub"><?php esc_html_e( 'Categories', 'bhela-booking' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Name', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Code', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Kind', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Retired', 'bhela-booking' ); ?></th></tr></thead>
		<tbody>
		<?php $i = 0; foreach ( $cats as $slug => $def ) : ?>
			<tr>
				<td><input type="hidden" name="inv_cats[<?php echo (int) $i; ?>][slug]" value="<?php echo esc_attr( $slug ); ?>">
					<input type="text" name="inv_cats[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $def['label'] ); ?>" class="regular-text"></td>
				<td><strong><?php echo esc_html( $def['code'] ); ?></strong>
					<input type="hidden" name="inv_cats[<?php echo (int) $i; ?>][code]" value="<?php echo esc_attr( $def['code'] ); ?>"></td>
				<td><select name="inv_cats[<?php echo (int) $i; ?>][kind]">
					<?php foreach ( bhela_bm_inv_kinds() as $k => $kd ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $def['kind'], $k ); ?>><?php echo esc_html( $kd['label'] ); ?></option>
					<?php endforeach; ?>
				</select></td>
				<td><input type="checkbox" name="inv_cats[<?php echo (int) $i; ?>][retired]" value="1" <?php checked( ! empty( $def['retired'] ) ); ?>></td>
			</tr>
		<?php $i++; endforeach; ?>
		<?php for ( $n = 0; $n < 3; $n++ ) : ?>
			<tr>
				<td><input type="text" name="inv_cats[<?php echo (int) ( $i + $n ); ?>][label]" placeholder="<?php esc_attr_e( 'new category', 'bhela-booking' ); ?>" class="regular-text"></td>
				<td><input type="text" name="inv_cats[<?php echo (int) ( $i + $n ); ?>][code]" placeholder="<?php esc_attr_e( 'CODE', 'bhela-booking' ); ?>" size="6"></td>
				<td><select name="inv_cats[<?php echo (int) ( $i + $n ); ?>][kind]">
					<?php foreach ( bhela_bm_inv_kinds() as $k => $kd ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $kd['label'] ); ?></option>
					<?php endforeach; ?>
				</select></td>
				<td></td>
			</tr>
		<?php endfor; ?>
		</tbody>
	</table>
	<p class="bha-set__note"><?php esc_html_e( 'Clear a name to delete that category. Retire one instead when items already use it — a retired category keeps rendering on closed months but is not offered on new items.', 'bhela-booking' ); ?></p>

	<h3 class="bha-set__sub"><?php esc_html_e( 'Sub-categories', 'bhela-booking' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Name', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Belongs to', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Retired', 'bhela-booking' ); ?></th></tr></thead>
		<tbody>
		<?php $j = 0; foreach ( $subs as $slug => $def ) : ?>
			<tr>
				<td><input type="hidden" name="inv_subs[<?php echo (int) $j; ?>][slug]" value="<?php echo esc_attr( $slug ); ?>">
					<input type="text" name="inv_subs[<?php echo (int) $j; ?>][label]" value="<?php echo esc_attr( $def['label'] ); ?>" class="regular-text"></td>
				<td><select name="inv_subs[<?php echo (int) $j; ?>][parent]">
					<?php foreach ( $cats as $cslug => $cdef ) : ?>
						<option value="<?php echo esc_attr( $cslug ); ?>" <?php selected( $def['parent'], $cslug ); ?>><?php echo esc_html( $cdef['label'] ); ?></option>
					<?php endforeach; ?>
				</select></td>
				<td><input type="checkbox" name="inv_subs[<?php echo (int) $j; ?>][retired]" value="1" <?php checked( ! empty( $def['retired'] ) ); ?>></td>
			</tr>
		<?php $j++; endforeach; ?>
		<?php for ( $n = 0; $n < 3; $n++ ) : ?>
			<tr>
				<td><input type="text" name="inv_subs[<?php echo (int) ( $j + $n ); ?>][label]" placeholder="<?php esc_attr_e( 'new sub-category', 'bhela-booking' ); ?>" class="regular-text"></td>
				<td><select name="inv_subs[<?php echo (int) ( $j + $n ); ?>][parent]">
					<?php foreach ( $cats as $cslug => $cdef ) : ?>
						<option value="<?php echo esc_attr( $cslug ); ?>"><?php echo esc_html( $cdef['label'] ); ?></option>
					<?php endforeach; ?>
				</select></td>
				<td></td>
			</tr>
		<?php endfor; ?>
		</tbody>
	</table>
	<p class="bha-set__note"><?php esc_html_e( 'A sub-category belongs to exactly one category, so "Kitchen → Cutlery" and "Deck → Cutlery" stay separate things.', 'bhela-booking' ); ?></p>

	<h3 class="bha-set__sub"><?php esc_html_e( 'Locations', 'bhela-booking' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Name', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Retired', 'bhela-booking' ); ?></th></tr></thead>
		<tbody>
		<?php $k = 0; foreach ( $locs as $slug => $def ) :
			$label = is_array( $def ) ? ( $def['label'] ?? '' ) : $def;
			?>
			<tr>
				<td><input type="hidden" name="inv_locs[<?php echo (int) $k; ?>][slug]" value="<?php echo esc_attr( $slug ); ?>">
					<input type="text" name="inv_locs[<?php echo (int) $k; ?>][label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text"></td>
				<td><input type="checkbox" name="inv_locs[<?php echo (int) $k; ?>][retired]" value="1" <?php checked( is_array( $def ) && ! empty( $def['retired'] ) ); ?>></td>
			</tr>
		<?php $k++; endforeach; ?>
		<?php for ( $n = 0; $n < 3; $n++ ) : ?>
			<tr>
				<td><input type="text" name="inv_locs[<?php echo (int) ( $k + $n ); ?>][label]" placeholder="<?php esc_attr_e( 'new location', 'bhela-booking' ); ?>" class="regular-text"></td>
				<td></td>
			</tr>
		<?php endfor; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Persist the three lists, and record the changes.
 *
 * §30 covers master data too, so a renamed category or a retired location is an
 * audit row like any other figure.
 *
 * @param array $post The settings POST.
 */
function bhela_bm_inv_save_lists( $post ) {
	if ( ! current_user_can( 'bhela_inv_lists' ) && ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$before = array(
		'categories' => bhela_bm_inv_categories( true ),
		'subcats'    => bhela_bm_inv_subcats( true ),
		'locations'  => bhela_bm_inv_locations( true ),
	);

	if ( isset( $post['inv_cats'] ) ) {
		bhela_bm_inv_save_categories( $post['inv_cats'] );
	}
	if ( isset( $post['inv_subs'] ) ) {
		bhela_bm_inv_save_subcats( $post['inv_subs'] );
	}
	if ( isset( $post['inv_locs'] ) ) {
		bhela_bm_inv_save_locations( $post['inv_locs'] );
	}

	if ( ! function_exists( 'bhela_bm_audit' ) ) {
		return;
	}
	$after = array(
		'categories' => bhela_bm_inv_categories( true ),
		'subcats'    => bhela_bm_inv_subcats( true ),
		'locations'  => bhela_bm_inv_locations( true ),
	);
	foreach ( $after as $list => $rows ) {
		foreach ( $rows as $slug => $row ) {
			$was = $before[ $list ][ $slug ] ?? null;
			$now = is_array( $row ) ? ( $row['label'] ?? '' ) : $row;
			$old = is_array( $was ) ? ( $was['label'] ?? '' ) : $was;
			if ( (string) $old === (string) $now ) {
				continue;
			}
			bhela_bm_audit( array(
				'channel'     => 'lists',
				'action'      => null === $was ? 'create' : 'update',
				'object_type' => 'inv_list',
				'object_ref'  => $list . ':' . $slug,
				'field'       => 'label',
				'old_value'   => null === $was ? '' : $old,
				'new_value'   => $now,
			) );
		}
		foreach ( $before[ $list ] as $slug => $was ) {
			if ( ! isset( $rows[ $slug ] ) ) {
				bhela_bm_audit( array(
					'channel'     => 'lists',
					'action'      => 'delete',
					'object_type' => 'inv_list',
					'object_ref'  => $list . ':' . $slug,
					'field'       => 'label',
					'old_value'   => is_array( $was ) ? ( $was['label'] ?? '' ) : $was,
					'new_value'   => '',
				) );
			}
		}
	}
}

/**
 * The stock sheet as a standalone printable document.
 *
 * Mirrors the cost sheet's print view: hooked on admin_init, self-gates on a query
 * flag, checks the capability, emits a complete document and exits. No nonce,
 * because it reads and changes nothing.
 *
 * MUST REMAIN THE LAST FUNCTION IN THIS FILE. tests/ui-test.php asserts on its
 * source by taking substr() from this function's name to end-of-file, so anything
 * appended below would silently fall outside those assertions.
 */
function bhela_bm_inv_print() {
	if ( empty( $_GET['bhela_inv_print'] ) || ! is_admin() ) {
		return;
	}
	if ( ! current_user_can( 'bhela_inv_view' ) ) {
		wp_die( esc_html__( 'You are not allowed to view the register.', 'bhela-booking' ), 403 );
	}
	$month = bhela_bm_inv_month( $_GET['month'] ?? '' );
	if ( '' === $month ) {
		return;
	}
	$data = bhela_bm_inv_month_data( $month );
	$s    = bhela_bm_get_settings();
	$t    = bhela_bm_inv_totals( wp_list_pluck( $data['rows'], 'line' ) );
	$moves = bhela_bm_inv_movement_types();
	?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( sprintf( '%s — %s', __( 'Stock Sheet', 'bhela-booking' ), $month ) ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( BHELA_BM_URL . 'assets/admin.css?ver=' . BHELA_BM_VERSION ); ?>">
</head>
<body class="bhela-admin bha-doc" onload="window.print()">
	<h1><?php echo esc_html( $s['business_name'] ); ?></h1>
	<p class="bha-doc__sub"><?php esc_html_e( 'Monthly Stock Sheet', 'bhela-booking' ); ?> — <?php echo esc_html( mysql2date( 'F Y', $month . '-01' ) ); ?></p>
	<div class="bha-doc__facts">
		<span><?php esc_html_e( 'Items', 'bhela-booking' ); ?>: <strong><?php echo esc_html( count( $data['rows'] ) ); ?></strong></span>
		<span><?php esc_html_e( 'Opening', 'bhela-booking' ); ?>: <strong><?php echo esc_html( (int) $t['open'] ); ?></strong></span>
		<span><?php esc_html_e( 'Closing', 'bhela-booking' ); ?>: <strong><?php echo esc_html( (int) $t['close'] ); ?></strong></span>
		<span><?php esc_html_e( 'Closing value', 'bhela-booking' ); ?>: <strong><?php echo esc_html( bhela_bm_money( (int) $t['value'] ) ); ?></strong></span>
	</div>
	<table>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Item ID', 'bhela-booking' ); ?></th>
				<th><?php esc_html_e( 'Item', 'bhela-booking' ); ?></th>
				<th class="bha-num"><?php esc_html_e( 'Opening', 'bhela-booking' ); ?></th>
				<?php foreach ( $moves as $def ) : ?>
					<th class="bha-num"><?php echo esc_html( $def['label'] ); ?></th>
				<?php endforeach; ?>
				<th class="bha-num"><?php esc_html_e( 'Closing', 'bhela-booking' ); ?></th>
				<th class="bha-num"><?php esc_html_e( 'Counted', 'bhela-booking' ); ?></th>
				<th class="bha-num"><?php esc_html_e( 'Value', 'bhela-booking' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $data['rows'] as $row ) :
			$l   = $row['line'];
			$chk = $row['check'];
			?>
			<tr>
				<td><?php echo esc_html( $row['code'] ); ?></td>
				<td><?php echo esc_html( $row['name'] ); ?></td>
				<td class="bha-num"><?php echo esc_html( (int) $l['open'] ); ?></td>
				<?php foreach ( array_keys( $moves ) as $mk ) : ?>
					<td class="bha-num"><?php echo esc_html( (int) $l[ $mk ] ); ?></td>
				<?php endforeach; ?>
				<td class="bha-num"><?php echo esc_html( $chk['close'] ); ?></td>
				<td class="bha-num"><?php echo esc_html( null === $l['count'] ? '' : (int) $l['count'] ); ?></td>
				<td class="bha-num"><?php echo esc_html( bhela_bm_money( $chk['close'] * (int) $l['rate'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<div class="bha-doc__sum">
		<span><?php esc_html_e( 'Closing value', 'bhela-booking' ); ?></span>
		<strong><?php echo esc_html( bhela_bm_money( (int) $t['value'] ) ); ?></strong>
	</div>
	<div class="bha-doc__sign">
		<span><?php esc_html_e( 'Counted by', 'bhela-booking' ); ?></span>
		<span><?php esc_html_e( 'Checked by', 'bhela-booking' ); ?></span>
		<span><?php esc_html_e( 'Approved by', 'bhela-booking' ); ?></span>
	</div>
	<p class="bha-noprint"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'bhela-booking' ); ?></button></p>
</body>
</html>
	<?php
	exit;
}
add_action( 'admin_init', 'bhela_bm_inv_print' );
