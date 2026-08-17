<?php
/**
 * Inventory & Asset Register — the column-mapped CSV importer.
 *
 * Four steps: upload, map, dry run, commit.
 *
 * Column-mapped rather than fixed-layout because the owner's spreadsheet is the
 * owner's spreadsheet — it will have its own column names, in its own order, and
 * probably a few columns that are none of our business. So the file is read as it
 * is and the owner says which column means what.
 *
 * Two properties worth stating up front:
 *
 *   - The uploaded file is NEVER moved into uploads/. It is parsed straight from
 *     PHP's temp file in the same request and then abandoned, so nothing
 *     web-readable is ever created. The parsed rows live in a site transient
 *     keyed to the user who uploaded them.
 *   - The dry run writes nothing, and the commit re-derives its plan from the
 *     staged rows rather than trusting anything the dry-run page posted back. The
 *     dry run is a display, not an input.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ceilings. A register of a few hundred items is ~60 KB, so these are generous. */
function bhela_bm_inv_import_limits() {
	return array(
		'bytes' => 2 * MB_IN_BYTES,
		'rows'  => 2000,
		'cols'  => 60,
	);
}

/**
 * The fields a CSV column can be mapped onto.
 *
 * Data, like every other registry in this plugin, so adding a field is one entry
 * rather than an edit in four places.
 *
 * @return array key => array{label, required, type}
 */
function bhela_bm_inv_import_fields() {
	return array(
		'name'      => array( 'label' => __( 'Item name', 'bhela-booking' ),       'required' => true,  'type' => 'text' ),
		'kind'      => array( 'label' => __( 'Inventory or Asset', 'bhela-booking' ), 'required' => false, 'type' => 'kind' ),
		'category'  => array( 'label' => __( 'Category', 'bhela-booking' ),        'required' => true,  'type' => 'cat' ),
		'subcat'    => array( 'label' => __( 'Sub-category', 'bhela-booking' ),    'required' => false, 'type' => 'sub' ),
		'location'  => array( 'label' => __( 'Location', 'bhela-booking' ),        'required' => false, 'type' => 'loc' ),
		'unit'      => array( 'label' => __( 'Unit', 'bhela-booking' ),            'required' => false, 'type' => 'text' ),
		'item_code' => array( 'label' => __( 'Item ID', 'bhela-booking' ),          'required' => false, 'type' => 'code' ),
		'asset_tag' => array( 'label' => __( 'Asset Tag', 'bhela-booking' ),        'required' => false, 'type' => 'text' ),
		'barcode'   => array( 'label' => __( 'Barcode / QR', 'bhela-booking' ),     'required' => false, 'type' => 'text' ),
		'open'      => array( 'label' => __( 'Opening quantity', 'bhela-booking' ), 'required' => true,  'type' => 'int' ),
		'good'      => array( 'label' => __( 'Good quantity', 'bhela-booking' ),    'required' => false, 'type' => 'int' ),
		'rep'       => array( 'label' => __( 'Repairable quantity', 'bhela-booking' ), 'required' => false, 'type' => 'int' ),
		'ur'        => array( 'label' => __( 'Under repair quantity', 'bhela-booking' ), 'required' => false, 'type' => 'int' ),
		'dam'       => array( 'label' => __( 'Damaged quantity', 'bhela-booking' ), 'required' => false, 'type' => 'int' ),
		'rate'      => array( 'label' => __( 'Unit value', 'bhela-booking' ),       'required' => false, 'type' => 'int' ),
		'supplier'  => array( 'label' => __( 'Supplier', 'bhela-booking' ),         'required' => false, 'type' => 'text' ),
		'bought_on' => array( 'label' => __( 'Purchase date', 'bhela-booking' ),    'required' => false, 'type' => 'date' ),
		'brand'     => array( 'label' => __( 'Brand', 'bhela-booking' ),            'required' => false, 'type' => 'text' ),
		'model'     => array( 'label' => __( 'Model', 'bhela-booking' ),            'required' => false, 'type' => 'text' ),
		'serial'    => array( 'label' => __( 'Serial number', 'bhela-booking' ),    'required' => false, 'type' => 'text' ),
		'remark'    => array( 'label' => __( 'Remark', 'bhela-booking' ),           'required' => false, 'type' => 'text' ),
	);
}

/** Staging key for one upload. */
function bhela_bm_inv_import_key( $token ) {
	return 'bhela_bm_inv_import_' . preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
}

/**
 * Read the staged rows, refusing another user's staging.
 *
 * @param string $token Token from the URL.
 * @return array|null
 */
function bhela_bm_inv_import_staged( $token ) {
	$data = get_site_transient( bhela_bm_inv_import_key( $token ) );
	if ( ! is_array( $data ) || empty( $data['rows'] ) ) {
		return null;
	}
	if ( (int) ( $data['user'] ?? 0 ) !== get_current_user_id() ) {
		return null;                            // one importer's file is not another's
	}
	return $data;
}

/**
 * Strip a UTF-8 BOM and make sure the text is UTF-8.
 *
 * Excel writes a BOM, and an un-stripped one attaches itself to the first header
 * cell — so column one silently fails to map and the owner cannot see why.
 * mbstring is not a given on this stack (which is why tests/run.php exists), so
 * the conversion is guarded.
 *
 * @param string $text Raw cell.
 * @return string
 */
function bhela_bm_inv_import_clean( $text ) {
	$text = (string) $text;
	if ( 0 === strncmp( $text, "\xEF\xBB\xBF", 3 ) ) {
		$text = substr( $text, 3 );
	}
	if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) ) {
		// A file saved from Excel on a Windows machine is usually Windows-1252.
		$text = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' ) : $text;
	}
	return trim( $text );
}

/**
 * A sample CSV, generated rather than shipped as a file.
 *
 * Generated for two reasons. It cannot drift from what the importer accepts,
 * because the headers ARE the field registry's labels and the categories ARE the
 * owner's current list — so a sample downloaded today maps itself perfectly and
 * every category in it resolves. And a static file in the repo would quietly go
 * stale the first time a field is added.
 *
 * The rows are deliberately a worked example rather than filler: one consumable,
 * one asset carrying a condition split, one asset with a serial and warranty, and
 * one row leaving every optional column empty to show that is allowed.
 */
function bhela_bm_inv_sample_csv() {
	if ( ! current_user_can( 'bhela_inv_import' ) ) {
		wp_die( esc_html__( 'You are not allowed to import the register.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_inv_sample_csv' );

	$fields = bhela_bm_inv_import_fields();
	$cats   = bhela_bm_inv_categories();
	$locs   = bhela_bm_inv_locations();

	// Pick real entries from the owner's own lists, so the sample round-trips.
	$pick = function ( $list, $wanted, $fallback_index = 0 ) {
		foreach ( (array) $wanted as $slug ) {
			if ( isset( $list[ $slug ] ) ) {
				return is_array( $list[ $slug ] ) ? $list[ $slug ]['label'] : $list[ $slug ];
			}
		}
		$vals = array_values( $list );
		$val  = $vals[ $fallback_index ] ?? reset( $vals );
		return is_array( $val ) ? $val['label'] : $val;
	};
	$cat_kitchen = $pick( $cats, array( 'kitchen' ), 0 );
	$cat_safety  = $pick( $cats, array( 'safety_eq' ), 1 );
	$cat_fan     = $pick( $cats, array( 'fan', 'electrical' ), 2 );
	$loc_kitchen = $pick( $locs, array( 'kitchen' ), 0 );
	$loc_upper   = $pick( $locs, array( 'upper', 'rooftop' ), 1 );
	$loc_cabin   = $pick( $locs, array( 'cabin_1' ), 2 );

	$rows = array(
		// name, kind, category, subcat, location, unit, item_code, asset_tag,
		// barcode, open, good, rep, ur, dam, rate, supplier, bought_on, brand,
		// model, serial, remark
		array( 'Noodles Bowl', 'Inventory', $cat_kitchen, '', $loc_kitchen, 'PCS', '', '', '', 39, 39, 0, 0, 0, 180, 'Sunamganj Crockery', '2026-05-12', '', '', '', '' ),
		array( 'Life Jacket', 'Asset', $cat_safety, '', $loc_upper, 'PCS', '', 'TAG-LJ-01', '', 25, 10, 8, 0, 7, 1200, 'Dhaka Marine', '2025-11-03', 'Aqua', 'AQ-200', '', 'seven beyond repair' ),
		array( 'Adjust Fan', 'Asset', $cat_fan, '', $loc_cabin, 'PCS', '', '', '', 4, 3, 0, 1, 0, 3500, 'Electro Mart', '2026-01-20', 'Walton', 'W-16', 'SN-99127', 'one away for repair' ),
		array( 'Dish Soap', 'Inventory', $cat_kitchen, '', $loc_kitchen, 'LTR', '', '', '', 12, 12, 0, 0, 0, 0, '', '', '', '', '', '' ),
	);

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bhela-register-sample.csv"' );

	$fh = fopen( 'php://output', 'w' );
	// Same BOM every other export writes: without it Excel reads UTF-8 as the
	// local codepage and any Bengali item name arrives as mojibake.
	fwrite( $fh, "\xEF\xBB\xBF" );

	// Headers are the registry's own labels, which is exactly what the column
	// guesser matches on — so re-uploading this file maps every column with no
	// hand-picking. A required field is marked, the same way the mapping screen
	// marks it.
	$head = array();
	foreach ( $fields as $def ) {
		$head[] = $def['label'] . ( empty( $def['required'] ) ? '' : ' *' );
	}
	fputcsv( $fh, $head );

	foreach ( $rows as $row ) {
		$out = array();
		foreach ( $row as $cell ) {
			// Figures stay figures so the columns remain sortable; text goes
			// through the formula guard like every other export.
			$out[] = is_int( $cell ) ? $cell : bhela_bm_csv_cell( $cell );
		}
		fputcsv( $fh, $out );
	}

	fclose( $fh );
	exit;
}
add_action( 'admin_post_bhela_bm_inv_sample_csv', 'bhela_bm_inv_sample_csv' );

/** Step 1 — take the file, parse it, stage it, and go to the mapping step. */
function bhela_bm_inv_import_upload() {
	if ( ! current_user_can( 'bhela_inv_import' ) ) {
		wp_die( esc_html__( 'You are not allowed to import the register.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_inv_import_upload' );

	$limits = bhela_bm_inv_import_limits();
	$file   = $_FILES['bhela_inv_csv'] ?? null;

	if ( ! $file || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		bhela_bm_inv_import_bail( __( 'No file arrived. Choose a CSV and try again.', 'bhela-booking' ) );
	}
	if ( (int) $file['size'] > $limits['bytes'] ) {
		bhela_bm_inv_import_bail( __( 'That file is larger than 2 MB. A register of a few hundred items is far smaller than that — is it definitely a CSV and not a workbook?', 'bhela-booking' ) );
	}
	// Sniff the bytes, not the extension.
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array(
		'csv' => 'text/csv',
		'txt' => 'text/plain',
	) );
	$ext = strtolower( (string) pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
		bhela_bm_inv_import_bail( __( 'Save the sheet as CSV first — an .xlsx workbook cannot be read directly.', 'bhela-booking' ) );
	}
	unset( $check );

	// Parsed here and now, from the temp file. Nothing is copied into uploads/.
	$fh = fopen( $file['tmp_name'], 'r' );
	if ( ! $fh ) {
		bhela_bm_inv_import_bail( __( 'That file could not be opened.', 'bhela-booking' ) );
	}

	// Skip a UTF-8 BOM before parsing, not after.
	//
	// Excel writes one, and it sits in front of the first field's opening quote —
	// so fgetcsv does not see a quoted field at all and hands back the quotes as
	// literal text. The first column then arrives as `"Item name"` including the
	// punctuation, and every value in it is subtly wrong. Stripping the BOM from
	// each cell afterwards is too late: by then the damage is in the parse.
	if ( "\xEF\xBB\xBF" !== fread( $fh, 3 ) ) {
		rewind( $fh );
	}
	$rows = array();
	$n    = 0;
	while ( ( $row = fgetcsv( $fh ) ) !== false ) {
		if ( $n >= $limits['rows'] ) {
			break;
		}
		if ( 1 === count( $row ) && ( null === $row[0] || '' === trim( (string) $row[0] ) ) ) {
			continue;                           // a blank line
		}
		$row = array_slice( $row, 0, $limits['cols'] );
		foreach ( $row as $i => $cell ) {
			$row[ $i ] = bhela_bm_inv_import_clean( $cell );
		}
		$rows[] = $row;
		$n++;
	}
	fclose( $fh );

	if ( count( $rows ) < 2 ) {
		bhela_bm_inv_import_bail( __( 'That file has no data rows in it.', 'bhela-booking' ) );
	}

	$token = wp_generate_password( 20, false );
	set_site_transient( bhela_bm_inv_import_key( $token ), array(
		'rows' => $rows,
		'user' => get_current_user_id(),
		'name' => sanitize_file_name( (string) $file['name'] ),
	), 2 * HOUR_IN_SECONDS );

	wp_safe_redirect( bhela_bm_inv_import_url( array( 'step' => 'map', 'token' => $token ) ) );
	exit;
}
add_action( 'admin_post_bhela_bm_inv_import_upload', 'bhela_bm_inv_import_upload' );

/** Send the owner back to step 1 with something readable. */
function bhela_bm_inv_import_bail( $message ) {
	set_transient( 'bhela_bm_inv_import_err_' . get_current_user_id(), $message, 60 );
	wp_safe_redirect( bhela_bm_inv_import_url() );
	exit;
}

/** URL of the importer. */
function bhela_bm_inv_import_url( $args = array() ) {
	return bhela_bm_admin_url( 'bhela-bm-inv-import', $args );
}

/**
 * Guess which field a column header means.
 *
 * A convenience only — the owner always confirms. Never commit on the guess: a
 * wrong silent mapping would fill the register with plausible nonsense.
 *
 * @param string $header Column header.
 * @return string Field key, or ''.
 */
function bhela_bm_inv_import_guess( $header ) {
	$h = sanitize_title( $header );
	if ( '' === $h ) {
		return '';
	}
	$alias = array(
		'name' => array( 'item', 'item-name', 'items', 'particulars', 'description', 'product' ),
		'open' => array( 'qty', 'quantity', 'opening', 'opening-qty', 'stock', 'total', 'master-qty' ),
		'category' => array( 'cat', 'categories', 'group', 'type' ),
		'subcat' => array( 'sub-category', 'subcategory', 'sub-cat' ),
		'location' => array( 'loc', 'place', 'where', 'current-location' ),
		// Deliberately NOT 'sl', 'sl-no', 'no' or '#': in a register written by hand
		// those are the row number, and guessing them as the Item ID would offer to
		// mint items coded 1, 2, 3. A row-number column maps to nothing.
		'item_code' => array( 'code', 'item-id', 'item-code', 'asset-id', 'asset-code' ),
		'asset_tag' => array( 'tag', 'asset-tag-number' ),
		'rate' => array( 'price', 'unit-price', 'value', 'unit-value', 'cost' ),
		'good' => array( 'usable', 'good-qty' ),
		'rep' => array( 'repairable' ),
		'ur' => array( 'under-repair' ),
		'dam' => array( 'damaged', 'damage' ),
		'kind' => array( 'inventory-asset', 'item-type' ),
		'unit' => array( 'uom', 'units' ),
		'supplier' => array( 'vendor', 'shop' ),
		'bought_on' => array( 'purchase-date', 'date', 'buy-date' ),
		'remark' => array( 'remarks', 'note', 'notes', 'comment' ),
	);
	foreach ( bhela_bm_inv_import_fields() as $key => $def ) {
		if ( $h === $key || $h === sanitize_title( $def['label'] ) ) {
			return $key;
		}
	}
	foreach ( $alias as $key => $names ) {
		if ( in_array( $h, $names, true ) ) {
			return $key;
		}
	}
	return '';
}

/** The mapping the owner chose, sanitised against the field registry. */
function bhela_bm_inv_import_mapping( $posted, $header ) {
	$fields = bhela_bm_inv_import_fields();
	$map    = array();
	foreach ( (array) $posted as $i => $field ) {
		$field = sanitize_key( $field );
		if ( isset( $fields[ $field ] ) ) {
			$map[ (int) $i ] = $field;
		}
	}
	unset( $header );
	return $map;
}

/**
 * Work out what a commit would do. Writes nothing.
 *
 * Match order is deliberate and reported, so an unexpected match is visible rather
 * than mysterious: explicit item code, then barcode, then asset tag, then name
 * within a category.
 *
 * @param array $data    Staged rows.
 * @param array $map     Column => field.
 * @param array $options has_header, kind, month.
 * @return array{create:array,update:array,skip:array,blocked:array,counts:array}
 */
function bhela_bm_inv_import_plan( $data, $map, $options ) {
	$out    = array( 'create' => array(), 'update' => array(), 'skip' => array(), 'blocked' => array(), 'counts' => array() );
	$fields = bhela_bm_inv_import_fields();
	$cats   = bhela_bm_inv_categories( true );
	$subs   = bhela_bm_inv_subcats( true );
	$locs   = bhela_bm_inv_locations( true );
	$rows   = $data['rows'];
	if ( ! empty( $options['has_header'] ) ) {
		array_shift( $rows );
	}

	// Two rows in one file minting the same code is the file's problem, not ours.
	$codes_seen = array();
	$cat_by_label = array();
	foreach ( $cats as $slug => $def ) {
		$cat_by_label[ sanitize_title( $def['label'] ) ] = $slug;
		$cat_by_label[ $slug ]                           = $slug;
		$cat_by_label[ strtolower( $def['code'] ) ]       = $slug;
	}
	$loc_by_label = array();
	foreach ( $locs as $slug => $label ) {
		$loc_by_label[ sanitize_title( $label ) ] = $slug;
		$loc_by_label[ $slug ]                    = $slug;
	}
	$sub_by_label = array();
	foreach ( $subs as $slug => $def ) {
		$sub_by_label[ sanitize_title( $def['label'] ) ] = $slug;
	}

	foreach ( $rows as $n => $row ) {
		$line = (int) $n + ( empty( $options['has_header'] ) ? 1 : 2 );
		$vals = array();
		foreach ( $map as $i => $field ) {
			$vals[ $field ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
		}

		// Required fields.
		$missing = array();
		foreach ( $fields as $key => $def ) {
			if ( ! empty( $def['required'] ) && '' === ( $vals[ $key ] ?? '' ) ) {
				$missing[] = $def['label'];
			}
		}
		if ( $missing ) {
			$out['blocked'][] = array( 'line' => $line, 'name' => $vals['name'] ?? '', 'why' => sprintf(
				/* translators: %s: comma-separated field names */
				__( 'missing %s', 'bhela-booking' ),
				implode( ', ', $missing )
			) );
			continue;
		}

		// Category must resolve to something real.
		$cat = $cat_by_label[ sanitize_title( $vals['category'] ) ] ?? '';
		if ( '' === $cat ) {
			$out['blocked'][] = array( 'line' => $line, 'name' => $vals['name'], 'why' => sprintf(
				/* translators: %s: the unknown category from the file */
				__( 'category "%s" is not in the list — add it under Settings → Store Lists first', 'bhela-booking' ),
				$vals['category']
			) );
			continue;
		}

		$open = (int) preg_replace( '/[^0-9-]/', '', (string) ( $vals['open'] ?? '0' ) );
		$cond = 0;
		$has_cond = false;
		foreach ( array( 'good', 'rep', 'ur', 'dam' ) as $ck ) {
			if ( '' !== ( $vals[ $ck ] ?? '' ) ) {
				$has_cond = true;
				$cond    += (int) preg_replace( '/[^0-9]/', '', (string) $vals[ $ck ] );
			}
		}
		// If the file states a condition split, it has to add up to the opening —
		// otherwise the baseline would start life already failing its own invariant.
		if ( $has_cond && $cond !== $open ) {
			$out['blocked'][] = array( 'line' => $line, 'name' => $vals['name'], 'why' => sprintf(
				/* translators: 1: sum of the condition columns, 2: opening quantity */
				__( 'the condition columns add up to %1$d but opening is %2$d', 'bhela-booking' ),
				$cond,
				$open
			) );
			continue;
		}

		$code = isset( $vals['item_code'] ) ? bhela_bm_inv_clean_code( $vals['item_code'] ) : '';
		if ( '' !== $code && isset( $codes_seen[ $code ] ) ) {
			$out['blocked'][] = array( 'line' => $line, 'name' => $vals['name'], 'why' => sprintf(
				/* translators: 1: item code, 2: the earlier line number */
				__( 'Item ID %1$s is already used on line %2$d of this file', 'bhela-booking' ),
				$code,
				$codes_seen[ $code ]
			) );
			continue;
		}
		if ( '' !== $code ) {
			$codes_seen[ $code ] = $line;
		}

		// Match, and say which rule fired.
		$match = 0;
		$via   = '';
		foreach ( array( 'item_code' => 'code', 'barcode' => 'barcode', 'asset_tag' => 'asset tag' ) as $field => $label ) {
			$probe = '' !== ( $vals[ $field ] ?? '' ) ? trim( (string) $vals[ $field ] ) : '';
			if ( 'item_code' === $field ) {
				$probe = $code;
			}
			if ( '' !== $probe ) {
				$match = bhela_bm_inv_find_by_code( $probe );
				if ( $match ) {
					$via = $label;
					break;
				}
			}
		}
		if ( ! $match ) {
			$match = bhela_bm_inv_import_find_by_name( $vals['name'], $cat );
			$via   = $match ? 'name and category' : '';
		}

		$kind = 'asset' === strtolower( (string) ( $vals['kind'] ?? '' ) ) ? 'asset'
			: ( 'inventory' === strtolower( (string) ( $vals['kind'] ?? '' ) ) ? 'inventory' : '' );
		if ( '' === $kind ) {
			// Fall back to the category's own kind, then to what the owner chose.
			$kind = $cats[ $cat ]['kind'] ?? ( 'asset' === ( $options['kind'] ?? '' ) ? 'asset' : 'inventory' );
		}

		$fields_out = array(
			'name'      => sanitize_text_field( $vals['name'] ),
			'kind'      => $kind,
			'cat'       => $cat,
			'subcat'    => $sub_by_label[ sanitize_title( $vals['subcat'] ?? '' ) ] ?? '',
			'location'  => $loc_by_label[ sanitize_title( $vals['location'] ?? '' ) ] ?? '',
			'unit'      => sanitize_text_field( $vals['unit'] ?? 'PCS' ),
			'code'      => $code,
			'asset_tag' => sanitize_text_field( $vals['asset_tag'] ?? '' ),
			'barcode'   => sanitize_text_field( $vals['barcode'] ?? '' ),
			'supplier'  => sanitize_text_field( $vals['supplier'] ?? '' ),
			'bought_on' => bhela_bm_inv_import_date( $vals['bought_on'] ?? '' ),
			'brand'     => sanitize_text_field( $vals['brand'] ?? '' ),
			'model'     => sanitize_text_field( $vals['model'] ?? '' ),
			'serial'    => sanitize_text_field( $vals['serial'] ?? '' ),
			'rate'      => (int) preg_replace( '/[^0-9]/', '', (string) ( $vals['rate'] ?? '0' ) ),
			'remark'    => sanitize_text_field( $vals['remark'] ?? '' ),
		);
		$qty = array(
			'open' => max( 0, $open ),
			'good' => $has_cond ? (int) preg_replace( '/[^0-9]/', '', (string) ( $vals['good'] ?? '0' ) ) : max( 0, $open ),
			'rep'  => (int) preg_replace( '/[^0-9]/', '', (string) ( $vals['rep'] ?? '0' ) ),
			'ur'   => (int) preg_replace( '/[^0-9]/', '', (string) ( $vals['ur'] ?? '0' ) ),
			'dam'  => (int) preg_replace( '/[^0-9]/', '', (string) ( $vals['dam'] ?? '0' ) ),
			'rate' => $fields_out['rate'],
		);

		$entry = array( 'line' => $line, 'fields' => $fields_out, 'qty' => $qty, 'item' => $match, 'via' => $via );

		if ( ! $match ) {
			$entry['code_preview'] = '' !== $code ? $code : sprintf( 'BHELA-%s-####', bhela_bm_inv_category_code( $cat ) );
			$out['create'][]       = $entry;
			continue;
		}

		// What would actually change? A row identical to what is stored is a skip,
		// and that is what makes re-running the same file a no-op.
		$diff = array();
		foreach ( $fields_out as $key => $value ) {
			if ( '' === (string) $value ) {
				continue;                       // a blank cell does not erase a stored value
			}
			$stored = bhela_bm_inv_import_stored_value( $match, $key );
			if ( (string) $stored !== (string) $value ) {
				$diff[ $key ] = array( 'from' => $stored, 'to' => $value );
			}
		}
		$entry['diff'] = $diff;
		if ( $diff ) {
			$out['update'][] = $entry;
		} else {
			$out['skip'][] = $entry;
		}
	}

	$out['counts'] = array(
		'read'    => count( $rows ),
		'create'  => count( $out['create'] ),
		'update'  => count( $out['update'] ),
		'skip'    => count( $out['skip'] ),
		'blocked' => count( $out['blocked'] ),
	);
	return $out;
}

/**
 * Where a mapped field is actually stored, and what it currently holds.
 *
 * There has to be an explicit map here rather than a `'_bhela_inv_' . $key`
 * convention, because two of the field names do not match their storage:
 * `remark` lands in `_bhela_inv_desc`, and `name` is the post title rather than a
 * meta at all. Deriving the key instead of stating it made every re-import report
 * `remark` as changed forever, since it was comparing against a meta nothing ever
 * writes — so a "nothing to do" import looked like real work.
 *
 * The title is read RAW from the post row, not through get_the_title(), which
 * applies wptexturize() and turns a straight apostrophe into a curly one — enough
 * to make an item called `O'Brien Pump` differ from itself on every import.
 *
 * @param int    $item_id Item.
 * @param string $key     Field key from bhela_bm_inv_import_plan().
 * @return string
 */
function bhela_bm_inv_import_stored_value( $item_id, $key ) {
	if ( 'name' === $key ) {
		$post = get_post( $item_id );
		return $post ? (string) $post->post_title : '';
	}
	$map = array(
		'remark' => '_bhela_inv_desc',
		'code'   => '_bhela_inv_code',
		'cat'    => '_bhela_inv_cat',
	);
	$meta = $map[ $key ] ?? ( '_bhela_inv_' . $key );
	return (string) get_post_meta( $item_id, $meta, true );
}

/** An existing item with this name in this category. */
function bhela_bm_inv_import_find_by_name( $name, $cat ) {
	$hits = get_posts( array(
		'post_type'      => 'bhela_inv_item',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'title'          => $name,
		'meta_query'     => array( array( 'key' => '_bhela_inv_cat', 'value' => $cat ) ),
	) );
	return $hits ? (int) $hits[0] : 0;
}

/** A date in any of the shapes a spreadsheet writes, as Y-m-d or ''. */
function bhela_bm_inv_import_date( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return '';
	}
	foreach ( array( 'Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd.m.Y', 'j M Y', 'd M Y' ) as $fmt ) {
		$d = DateTime::createFromFormat( $fmt, $raw );
		if ( $d && $d->format( $fmt ) === $raw ) {
			return $d->format( 'Y-m-d' );
		}
	}
	$ts = strtotime( $raw );
	return $ts ? gmdate( 'Y-m-d', $ts ) : '';
}

/** Step 4 — write it. */
function bhela_bm_inv_import_commit() {
	if ( ! current_user_can( 'bhela_inv_import' ) ) {
		wp_die( esc_html__( 'You are not allowed to import the register.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_inv_import_commit' );

	$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
	$data  = bhela_bm_inv_import_staged( $token );
	if ( ! $data ) {
		bhela_bm_inv_import_bail( __( 'That upload has expired. Start again.', 'bhela-booking' ) );
	}
	$options = array(
		'has_header' => ! empty( $_POST['has_header'] ),
		'kind'       => sanitize_key( $_POST['default_kind'] ?? 'inventory' ),
		'month'      => bhela_bm_inv_month( $_POST['month'] ?? '' ),
	);
	if ( '' === $options['month'] ) {
		bhela_bm_inv_import_bail( __( 'Give the baseline a month.', 'bhela-booking' ) );
	}

	// Re-derived from the staged rows, NOT from anything the dry-run page posted.
	$map  = bhela_bm_inv_import_mapping( $_POST['map'] ?? array(), $data['rows'][0] ?? array() );
	$plan = bhela_bm_inv_import_plan( $data, $map, $options );

	$period = bhela_bm_inv_period_id( $options['month'], true );
	if ( bhela_bm_inv_is_locked( $period ) ) {
		bhela_bm_inv_import_bail( __( 'That month is closed. Reopen it before importing into it.', 'bhela-booking' ) );
	}

	$lines   = bhela_bm_inv_stored_lines( $period );
	$opening = bhela_bm_inv_stored_opening( $period );
	$made    = 0;
	$changed = 0;

	foreach ( array_merge( $plan['create'], $plan['update'] ) as $entry ) {
		$item = (int) $entry['item'];
		$f    = $entry['fields'];

		if ( ! $item ) {
			$item = wp_insert_post( array(
				'post_type'   => 'bhela_inv_item',
				'post_status' => 'publish',
				'post_title'  => $f['name'],
			), true );
			if ( is_wp_error( $item ) || ! $item ) {
				continue;
			}
			$made++;
			$code = '' !== $f['code'] ? $f['code'] : bhela_bm_inv_mint_code( $f['cat'] );
			// A code the file supplied moves the counter past it, so a later mint
			// cannot collide with a number this file already used.
			bhela_bm_inv_observe_code( $code );
			update_post_meta( $item, '_bhela_inv_code', $code );
			if ( function_exists( 'bhela_bm_audit' ) ) {
				bhela_bm_audit( array(
					'channel'     => 'import',
					'action'      => 'create',
					'object_type' => 'inv_item',
					'object_id'   => $item,
					'object_ref'  => $code,
					'field'       => 'name',
					'new_value'   => $f['name'],
				) );
			}
		} else {
			$code = (string) get_post_meta( $item, '_bhela_inv_code', true );
			// Raw title, not get_the_title(): the latter texturizes, so an item whose
			// name contains an apostrophe would be "renamed" on every single import.
			if ( bhela_bm_inv_import_stored_value( $item, 'name' ) !== $f['name'] ) {
				wp_update_post( array( 'ID' => $item, 'post_title' => $f['name'] ) );
			}
			foreach ( $entry['diff'] as $field => $move ) {
				$changed++;
				if ( function_exists( 'bhela_bm_audit' ) ) {
					bhela_bm_audit( array(
						'channel'     => 'import',
						'action'      => 'update',
						'object_type' => 'inv_item',
						'object_id'   => $item,
						'object_ref'  => $code,
						'field'       => $field,
						'old_value'   => $move['from'],
						'new_value'   => $move['to'],
						'reason'      => sprintf( 'CSV import: %s', $data['name'] ),
					) );
				}
			}
		}

		foreach ( array(
			'_bhela_inv_kind'      => $f['kind'],
			'_bhela_inv_cat'       => $f['cat'],
			'_bhela_inv_subcat'    => $f['subcat'],
			'_bhela_inv_location'  => $f['location'],
			'_bhela_inv_unit'      => $f['unit'],
			'_bhela_inv_asset_tag' => $f['asset_tag'],
			'_bhela_inv_barcode'   => $f['barcode'],
			'_bhela_inv_supplier'  => $f['supplier'],
			'_bhela_inv_bought_on' => $f['bought_on'],
			'_bhela_inv_brand'     => $f['brand'],
			'_bhela_inv_model'     => $f['model'],
			'_bhela_inv_serial'    => $f['serial'],
			'_bhela_inv_rate'      => $f['rate'],
			'_bhela_inv_desc'      => $f['remark'],
		) as $key => $value ) {
			if ( '' === (string) $value && in_array( $key, array( '_bhela_inv_subcat', '_bhela_inv_location' ), true ) ) {
				continue;
			}
			update_post_meta( $item, $key, $value );
		}

		$k             = bhela_bm_inv_line_key( $item );
		$opening[ $k ] = (int) $entry['qty']['open'];
		$lines[ $k ]   = array_merge( bhela_bm_inv_blank_line(), array(
			'open' => (int) $entry['qty']['open'],
			'good' => (int) $entry['qty']['good'],
			'rep'  => (int) $entry['qty']['rep'],
			'ur'   => (int) $entry['qty']['ur'],
			'dam'  => (int) $entry['qty']['dam'],
			'rate' => (int) $entry['qty']['rate'],
		) );
	}

	// The imported figures ARE this period's opening, which is what makes it the
	// baseline rather than a month carried from something earlier.
	bhela_bm_inv_meta_write( $period, '_bhela_inv_opening', wp_json_encode( $opening, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT ) );
	bhela_bm_inv_meta_write( $period, '_bhela_inv_baseline', 1 );
	bhela_bm_inv_meta_write( $period, '_bhela_inv_opening_from', 0 );
	bhela_bm_inv_meta_write( $period, '_bhela_inv_import_ref', $data['name'] );
	bhela_bm_inv_write_lines( $period, $lines );

	if ( function_exists( 'bhela_bm_audit' ) ) {
		bhela_bm_audit( array(
			'channel'     => 'import',
			'action'      => 'import',
			'object_type' => 'inv_period',
			'object_id'   => $period,
			'object_ref'  => $options['month'],
			'field'       => 'baseline',
			'new_value'   => sprintf( '%d created, %d fields updated', $made, $changed ),
			'reason'      => $data['name'],
		) );
	}
	if ( function_exists( 'bhela_bm_log' ) ) {
		bhela_bm_log( 'inventory', sprintf( 'Register imported from %s — %d items created, %d fields updated.', $data['name'], $made, $changed ) );
	}

	delete_site_transient( bhela_bm_inv_import_key( $token ) );
	set_transient( 'bhela_bm_inv_import_ok_' . get_current_user_id(), array( 'made' => $made, 'changed' => $changed, 'month' => $options['month'] ), 60 );
	wp_safe_redirect( bhela_bm_inv_import_url( array( 'step' => 'done' ) ) );
	exit;
}
add_action( 'admin_post_bhela_bm_inv_import_commit', 'bhela_bm_inv_import_commit' );

/** The importer screen — all four steps. */
function bhela_bm_inv_import_page() {
	if ( ! current_user_can( 'bhela_inv_import' ) ) {
		return;
	}
	$step  = sanitize_key( $_GET['step'] ?? 'upload' );
	$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
	$err   = get_transient( 'bhela_bm_inv_import_err_' . get_current_user_id() );
	if ( $err ) {
		delete_transient( 'bhela_bm_inv_import_err_' . get_current_user_id() );
	}
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'📦',
			__( 'Import the Register', 'bhela-booking' ),
			__( 'Load the register from a spreadsheet. You choose what each column means, see exactly what would happen, and only then commit.', 'bhela-booking' )
		);
		if ( $err ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $err ) );
		}

		if ( 'done' === $step ) {
			$ok = get_transient( 'bhela_bm_inv_import_ok_' . get_current_user_id() );
			delete_transient( 'bhela_bm_inv_import_ok_' . get_current_user_id() );
			?>
			<div class="bha-panel">
				<h2 class="bha-panel__title">✅ <?php esc_html_e( 'Imported', 'bhela-booking' ); ?></h2>
				<?php if ( is_array( $ok ) ) : ?>
					<p><?php
						printf(
							/* translators: 1: items created, 2: fields updated, 3: month */
							esc_html__( '%1$d items created and %2$d fields updated. %3$s now holds the baseline.', 'bhela-booking' ),
							(int) $ok['made'],
							(int) $ok['changed'],
							esc_html( mysql2date( 'F Y', $ok['month'] . '-01' ) )
						);
					?></p>
					<p class="bha-buttons">
						<a class="button button-primary" href="<?php echo esc_url( bhela_bm_inv_month_url( $ok['month'] ) ); ?>">🔧 <?php esc_html_e( 'Open the stock sheet', 'bhela-booking' ); ?></a>
						<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=bhela_inv_item' ) ); ?>">📦 <?php esc_html_e( 'See the register', 'bhela-booking' ); ?></a>
					</p>
				<?php endif; ?>
				<p class="bha-note"><?php esc_html_e( 'Running the same file again is safe — every row will report as unchanged and nothing will be created twice.', 'bhela-booking' ); ?></p>
			</div>
			</div>
			<?php
			return;
		}

		$data = $token ? bhela_bm_inv_import_staged( $token ) : null;

		if ( ! $data ) {
			?>
			<div class="bha-panel">
				<h2 class="bha-panel__title"><?php esc_html_e( 'Step 1 — choose the file', 'bhela-booking' ); ?></h2>
				<p><?php esc_html_e( 'A CSV, up to 2 MB. Save your spreadsheet as CSV first if it is an .xlsx workbook. The file is read once and never stored on the server.', 'bhela-booking' ); ?></p>
				<p class="bha-callout"><?php esc_html_e( 'Your own column names and order are fine — you say what each one means at the next step. The sample below is only a starting point if you do not have a list yet.', 'bhela-booking' ); ?></p>
				<p class="bha-buttons"><a class="button" href="<?php echo esc_url( wp_nonce_url(
					add_query_arg( 'action', 'bhela_bm_inv_sample_csv', admin_url( 'admin-post.php' ) ),
					'bhela_bm_inv_sample_csv'
				) ); ?>">📄 <?php esc_html_e( 'Download a sample CSV', 'bhela-booking' ); ?></a></p>
				<p class="bha-note"><?php esc_html_e( 'The sample carries every column the importer understands, filled in with four worked rows — a consumable, two assets and one row leaving every optional column blank. Its categories and locations come from your own lists, so you can fill it in and upload it straight back.', 'bhela-booking' ); ?></p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bhela_bm_inv_import_upload">
					<?php wp_nonce_field( 'bhela_bm_inv_import_upload' ); ?>
					<p><input type="file" name="bhela_inv_csv" accept=".csv,text/csv,text/plain" required></p>
					<p class="bha-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Read the file', 'bhela-booking' ); ?></button></p>
				</form>
			</div>
			</div>
			<?php
			return;
		}

		$rows       = $data['rows'];
		$header     = $rows[0] ?? array();
		$has_header = ! isset( $_POST['has_header'] ) ? true : ! empty( $_POST['has_header'] );
		$map        = isset( $_POST['map'] ) ? bhela_bm_inv_import_mapping( $_POST['map'], $header ) : array();
		if ( ! $map ) {
			foreach ( $header as $i => $h ) {
				$guess = bhela_bm_inv_import_guess( $h );
				if ( '' !== $guess && ! in_array( $guess, $map, true ) ) {
					$map[ (int) $i ] = $guess;
				}
			}
		}
		$options = array(
			'has_header' => $has_header,
			'kind'       => sanitize_key( $_POST['default_kind'] ?? 'inventory' ),
			'month'      => bhela_bm_inv_month( $_POST['month'] ?? '' ) ?: '2026-07',
		);
		$plan = ( 'dry' === $step ) ? bhela_bm_inv_import_plan( $data, $map, $options ) : null;
		?>

		<form method="post" action="<?php echo esc_url( bhela_bm_inv_import_url( array( 'step' => 'dry', 'token' => $token ) ) ); ?>">
			<div class="bha-panel">
				<h2 class="bha-panel__title"><?php esc_html_e( 'Step 2 — say what each column means', 'bhela-booking' ); ?></h2>
				<p><?php
					printf(
						/* translators: 1: file name, 2: number of rows */
						esc_html__( '%1$s — %2$d rows read. The first few are shown below; the guesses are only guesses, so check them.', 'bhela-booking' ),
						esc_html( $data['name'] ),
						count( $rows )
					);
				?></p>

				<div class="bha-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<?php foreach ( $header as $i => $h ) : ?>
									<th>
										<select name="map[<?php echo (int) $i; ?>]">
											<option value=""><?php esc_html_e( '— ignore —', 'bhela-booking' ); ?></option>
											<?php foreach ( bhela_bm_inv_import_fields() as $key => $def ) : ?>
												<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $map[ $i ] ?? '', $key ); ?>><?php
													echo esc_html( $def['label'] );
													echo empty( $def['required'] ) ? '' : ' *';
												?></option>
											<?php endforeach; ?>
										</select>
										<br><span class="bha-sub"><?php echo esc_html( $h ); ?></span>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( array_slice( $rows, $has_header ? 1 : 0, 8 ) as $r ) : ?>
							<tr><?php foreach ( $header as $i => $unused ) : ?>
								<td><?php echo esc_html( $r[ $i ] ?? '' ); ?></td>
							<?php endforeach; ?></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="bha-grid">
					<div class="bha-field"><label><input type="checkbox" name="has_header" value="1" <?php checked( $has_header ); ?>> <?php esc_html_e( 'The first row is column headings', 'bhela-booking' ); ?></label></div>
					<div class="bha-field">
						<label for="bhela-imp-month"><?php esc_html_e( 'Baseline month', 'bhela-booking' ); ?></label>
						<input type="month" id="bhela-imp-month" name="month" value="<?php echo esc_attr( $options['month'] ); ?>">
					</div>
					<div class="bha-field">
						<label for="bhela-imp-kind"><?php esc_html_e( 'When the file does not say', 'bhela-booking' ); ?></label>
						<select id="bhela-imp-kind" name="default_kind">
							<?php foreach ( bhela_bm_inv_kinds() as $k => $kd ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $options['kind'], $k ); ?>><?php echo esc_html( $kd['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<p class="bha-note"><?php esc_html_e( 'Fields marked * are required. A category is matched by name or by its short code, and one that is not in the list blocks the row rather than being invented.', 'bhela-booking' ); ?></p>
				<p class="bha-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Show me what would happen', 'bhela-booking' ); ?></button></p>
			</div>
		</form>

		<?php if ( $plan ) : $c = $plan['counts']; ?>
			<div class="bha-panel">
				<h2 class="bha-panel__title"><?php esc_html_e( 'Step 3 — what would happen', 'bhela-booking' ); ?></h2>
				<p class="bha-note"><?php esc_html_e( 'Nothing below has been written. This is a dry run.', 'bhela-booking' ); ?></p>
				<div class="bha-cards">
					<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'Rows read', 'bhela-booking' ); ?></div><div class="bha-card__value"><?php echo esc_html( (int) $c['read'] ); ?></div></div>
					<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'To create', 'bhela-booking' ); ?></div><div class="bha-card__value is-good"><?php echo esc_html( (int) $c['create'] ); ?></div></div>
					<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'To update', 'bhela-booking' ); ?></div><div class="bha-card__value is-attention"><?php echo esc_html( (int) $c['update'] ); ?></div></div>
					<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'Unchanged', 'bhela-booking' ); ?></div><div class="bha-card__value"><?php echo esc_html( (int) $c['skip'] ); ?></div></div>
					<div class="bha-card"><div class="bha-card__label"><?php esc_html_e( 'Blocked', 'bhela-booking' ); ?></div><div class="bha-card__value is-danger"><?php echo esc_html( (int) $c['blocked'] ); ?></div></div>
				</div>
			</div>

			<?php if ( $plan['blocked'] ) : ?>
				<div class="bha-panel bha-panel--alert">
					<h2 class="bha-panel__title">⚠️ <?php esc_html_e( 'These rows will be skipped', 'bhela-booking' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Line', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Item', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Why', 'bhela-booking' ); ?></th></tr></thead>
						<tbody><?php foreach ( $plan['blocked'] as $b ) : ?>
							<tr><td><?php echo esc_html( $b['line'] ); ?></td><td><?php echo esc_html( $b['name'] ); ?></td><td><?php echo esc_html( $b['why'] ); ?></td></tr>
						<?php endforeach; ?></tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( $plan['create'] ) : ?>
				<div class="bha-panel">
					<h2 class="bha-panel__title"><?php esc_html_e( 'New items', 'bhela-booking' ); ?></h2>
					<div class="bha-scroll">
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'Item ID', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Item', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Kind', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Category', 'bhela-booking' ); ?></th><th class="bha-num"><?php esc_html_e( 'Opening', 'bhela-booking' ); ?></th><th class="bha-num"><?php esc_html_e( 'Unit value', 'bhela-booking' ); ?></th></tr></thead>
							<tbody><?php foreach ( array_slice( $plan['create'], 0, 60 ) as $e ) : ?>
								<tr>
									<td><?php echo esc_html( $e['code_preview'] ); ?></td>
									<td><?php echo esc_html( $e['fields']['name'] ); ?></td>
									<td><?php echo esc_html( bhela_bm_inv_kinds()[ $e['fields']['kind'] ]['label'] ); ?></td>
									<td><?php echo esc_html( bhela_bm_inv_categories( true )[ $e['fields']['cat'] ]['label'] ?? '' ); ?></td>
									<td class="bha-num"><?php echo esc_html( (int) $e['qty']['open'] ); ?></td>
									<td class="bha-num"><?php echo esc_html( bhela_bm_money( (int) $e['qty']['rate'] ) ); ?></td>
								</tr>
							<?php endforeach; ?></tbody>
						</table>
					</div>
					<?php if ( count( $plan['create'] ) > 60 ) : ?>
						<p class="bha-note"><?php
							printf(
								/* translators: %d: how many more rows there are */
								esc_html__( 'The first 60 are shown; %d more will be created too.', 'bhela-booking' ),
								count( $plan['create'] ) - 60
							);
						?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $plan['update'] ) : ?>
				<div class="bha-panel">
					<h2 class="bha-panel__title"><?php esc_html_e( 'Existing items that would change', 'bhela-booking' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Item', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Matched by', 'bhela-booking' ); ?></th><th><?php esc_html_e( 'Changes', 'bhela-booking' ); ?></th></tr></thead>
						<tbody><?php foreach ( array_slice( $plan['update'], 0, 60 ) as $e ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $e['fields']['name'] ); ?></strong><br><span class="bha-sub"><?php echo esc_html( bhela_bm_inv_item_code( $e['item'] ) ); ?></span></td>
								<td><?php echo esc_html( $e['via'] ); ?></td>
								<td><?php foreach ( $e['diff'] as $field => $move ) : ?>
									<span class="bha-sub"><?php echo esc_html( $field ); ?>: <?php echo esc_html( ( '' === $move['from'] ? '—' : $move['from'] ) . ' → ' . $move['to'] ); ?></span><br>
								<?php endforeach; ?></td>
							</tr>
						<?php endforeach; ?></tbody>
					</table>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bhela_bm_inv_import_commit">
				<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
				<input type="hidden" name="month" value="<?php echo esc_attr( $options['month'] ); ?>">
				<input type="hidden" name="default_kind" value="<?php echo esc_attr( $options['kind'] ); ?>">
				<?php if ( $has_header ) : ?><input type="hidden" name="has_header" value="1"><?php endif; ?>
				<?php foreach ( $map as $i => $field ) : ?>
					<input type="hidden" name="map[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $field ); ?>">
				<?php endforeach; ?>
				<?php wp_nonce_field( 'bhela_bm_inv_import_commit' ); ?>
				<div class="bha-panel">
					<h2 class="bha-panel__title"><?php esc_html_e( 'Step 4 — commit', 'bhela-booking' ); ?></h2>
					<p><?php
						printf(
							/* translators: %s: month name */
							esc_html__( 'The imported quantities become the opening balance for %s, and that month becomes the baseline every later month is carried from.', 'bhela-booking' ),
							esc_html( mysql2date( 'F Y', $options['month'] . '-01' ) )
						);
					?></p>
					<p class="bha-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Import now', 'bhela-booking' ); ?></button></p>
				</div>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
