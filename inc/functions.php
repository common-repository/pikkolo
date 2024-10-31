<?php
/*
** Helper Functions
*/


/*
**========== Direct access not allowed ===========
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Try to get the delivery date from the billing fields.
 *
 * @param array $billing_fields The billing fields array.
 * @return string
 */
function pikkolois_get_delivery_date( array $billing_fields ): string {
	$delivery_date = $billing_fields['billing_delivery_date'];
	$d_m_y         = DateTime::createFromFormat( 'd#m#Y', $delivery_date );
	$y_m_d         = DateTime::createFromFormat( 'Y#m#d', $delivery_date );
	if ( $d_m_y ) {
		return $d_m_y->format( 'Y-m-d' );
	}
	if ( $y_m_d ) {
		return $y_m_d->format( 'Y-m-d' );
	}
	return '';
}

/**
 * Get the station name from the cookies and add it to the shipping method title.
 *
 * @param WC_Order $order The order object.
 * @param array    $cookies The cookies array.
 * @return bool
 */
function pikkolois_add_station_name_to_shipping_method_title( $order, array $cookies ): string {
	$found_pikkolo = false;
	foreach ( $order->get_shipping_methods() as $shipping_method ) {
		if ( $shipping_method->get_method_id() === 'pikkolois' ) {
			$found_pikkolo = true;
			// Add the station name to the shipping method title.
			$station_name = '';
			if ( isset( $_COOKIE['pikkolo_station_name'] ) ) {
				$station_name = sanitize_text_field( wp_unslash( $cookies['pikkolo_station_name'] ) );
			}
			$shipping_method->set_method_title( ( 'Pikkoló - ' . $station_name ) );
		}
	}
	return $found_pikkolo;
}

/**
 * Gets the necessary data from the order's products.
 *
 * @param WC_Order $order The order object.
 * @return array An array of product data.
 */
function pikkolois_get_products_data( $order ) {
	$products = $order->get_items();

	// Initializing variables.
	$refrigerated_count    = 0;
	$frozen_count          = 0;
	$age_restriction_value = 0;

	$item_lines_refrigerated = array();
	$item_lines_frozen       = array();

	$item_name_refrigerated = array();
	$item_name_frozen       = array();

	foreach ( $products as $product ) {
		$product_id      = $product->get_product_id();
		$quantity        = $product->get_quantity();
		$frozen          = get_post_meta( $product_id, 'pikkolo_frozen', true );
		$age_restriction = get_post_meta( $product_id, 'pikkolo_age_restriction', true );

		$wc_product = $product->get_product();
        
		$weight     = $wc_product ? $wc_product->get_weight() : 0;
		$dimensions = $wc_product ? $wc_product->get_dimensions() : array();

		if ( 'none' !== $age_restriction && $age_restriction > $age_restriction_value ) {
			$age_restriction_value = $age_restriction;
		}

		$line_item = array(
			'name'     => $product->get_name(),
			'sku'      => (string) $product_id,
			'quantity' => $quantity,
			'weight'   => $weight,
		);

		if ( is_array( $dimensions ) ) {
			$line_item['dimensions'] = array(
				$dimensions['length'] ? $dimensions['length'] : 0,
				$dimensions['width'] ? $dimensions['width'] : 0,
				$dimensions['height'] ? $dimensions['height'] : 0,
			);
		}

		if ( 'true' === $frozen ) {
			$frozen_count       += $quantity;
			$item_name_frozen[]  = $line_item['name'];
			$line_item['type']   = 'Frozen';
			$item_lines_frozen[] = $line_item;
		} else {
			$refrigerated_count       += $quantity;
			$item_name_refrigerated[]  = $line_item['name'];
			$line_item['type']         = 'Refrigerated';
			$item_lines_refrigerated[] = $line_item;
		}
	}

	$items = array();
	if ( $refrigerated_count > 0 && array() !== $item_lines_refrigerated ) {
		$items[] = array(
			'description' => substr( join( '; ', $item_name_refrigerated ), 0, 191 ),
			'type'        => 'Refrigerated',
			'lineItems'   => $item_lines_refrigerated,
		);
	}
	if ( $frozen_count > 0 && array() !== $item_lines_frozen ) {
		$items[] = array(
			'description' => substr( join( '; ', $item_name_frozen ), 0, 191 ),
			'type'        => 'Frozen',
			'lineItems'   => $item_lines_frozen,
		);
	}

	return array(
		'items'                 => $items,
		'refrigerated_count'    => $refrigerated_count,
		'frozen_count'          => $frozen_count,
		'age_restriction_value' => $age_restriction_value,
	);
}
