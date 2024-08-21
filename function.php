<?php
/*
Plugin Name: Custom Order Dashboard Plugin
Description: Displays a list of products from orders with the status "processing" in the admin area.
Version: 1.0
Author: Owais Sheikh
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Hook to add admin menu
add_action('admin_menu', 'opo_add_admin_menu');

function opo_add_admin_menu() {
    add_menu_page(
        'On Process Orders', // Page title
        'On Process Orders', // Menu title
        'manage_options', // Capability
        'on-process-orders', // Menu slug
        'opo_display_orders_page', // Function to display page content
        'dashicons-admin-page', // Icon
        56 // Position
    );
}

function opo_display_orders_page() {
    // Ensure WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        echo '<div class="notice notice-error"><p>WooCommerce is not active. Please activate WooCommerce to use this plugin.</p></div>';
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>On Process Orders</h1>';

    // Filter form
    echo '<form method="get" class="custom_product_search" action="">';
    echo '<input type="hidden" name="page" value="on-process-orders" />';
    echo '<label for="product_name">Product Name:</label>';
    echo '<input type="text" name="product_name" id="product_name" value="' . esc_attr( isset( $_GET['product_name'] ) ? $_GET['product_name'] : '' ) . '" />';
    echo '<input type="submit" value="Filter" class="button button-primary" />';
    echo '<input type="submit" name="export_csv" value="Export as CSV" class="button button-secondary" />';
    echo '</form>';

    // Check if CSV export is requested
    if ( isset( $_GET['export_csv'] ) ) {
        opo_export_csv();
        return;
    }

    // Pagination variables
    $paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
    $posts_per_page = 10;

    // Get filter value
    $product_name_filter = isset( $_GET['product_name'] ) ? sanitize_text_field( $_GET['product_name'] ) : '';

    // Query orders with status "processing"
    $args = array(
        'status' => 'processing',
        'limit' => -1,
    );

    $orders = wc_get_orders( $args );

    // Aggregate products with the same name and variation
    $product_data = array();
    foreach ( $orders as $order ) {
        foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        
        // Check if the product exists
        if (!$product) {
        continue; // Skip this item if the product is not found
        }

            if ( $product_name_filter && stripos( $item->get_name(), $product_name_filter ) === false ) {
                continue;
            }
            // echo $item->get_product()->get_image();
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $key = $product_id . '-' . $variation_id;

            if ( isset( $product_data[ $key ] ) ) {
                $product_data[ $key ]['quantity'] += $item->get_quantity();
            } else {
                $product_data[ $key ] = array(
                    'product' => $item->get_product(),
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'variation' => $variation_id ? wc_get_product( $variation_id )->get_attributes() : 'N/A',
                    'image' => $item->get_product()->get_image(),
                    'order_status' => wc_get_order_status_name($order->get_status())
                );
            }
        }
    }

    // Pagination calculations
    $total_items = count( $product_data );
    $total_pages = ceil( $total_items / $posts_per_page );
    $current_page_data = array_slice( $product_data, ( $paged - 1 ) * $posts_per_page, $posts_per_page );

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>
            <tr>
                <th>Product Image</th>
                <th>Product Name</th>
                <th>Purchased Quantity</th>
                <th>Purchased Variation</th>
                <th>Order Status</th>
            </tr>
          </thead>';
    echo '<tbody>';

    foreach ( $current_page_data as $entry ) {
        echo '<tr>';
        echo '<td>' . $entry['image'] . '</td>';
        echo '<td>' . $entry['name'] . '</td>';
        echo '<td>' . $entry['quantity'] . '</td>';
        echo '<td>' . ( is_array( $entry['variation'] ) ? implode( ', ', $entry['variation'] ) : $entry['variation'] ) . '</td>';
        echo '<td>' . $entry['order_status'] . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    // Pagination links
    if ( $total_pages > 1 ) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        $page_links = paginate_links( array(
            'base' => add_query_arg( 'paged', '%#%' ),
            'format' => '',
            'prev_text' => __( '&laquo;' ),
            'next_text' => __( '&raquo;' ),
            'total' => $total_pages,
            'current' => $paged
        ) );
        echo $page_links;
        echo '</div></div>';
    }

    echo '</div>';
}

function opo_export_csv() {
    // Ensure WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        echo '<div class="notice notice-error"><p>WooCommerce is not active. Please activate WooCommerce to use this plugin.</p></div>';
        return;
    }

    // Get filter value
    $product_name_filter = isset( $_GET['product_name'] ) ? sanitize_text_field( $_GET['product_name'] ) : '';

    // Query orders with status "processing"
    $args = array(
        'status' => 'processing',
        'limit' => -1,
    );

    $orders = wc_get_orders( $args );

    // Aggregate products with the same name and variation
    $product_data = array();
    foreach ( $orders as $order ) {
        foreach ( $order->get_items() as $item ) {
            if ( $product_name_filter && stripos( $item->get_name(), $product_name_filter ) === false ) {
                continue;
            }
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $key = $product_id . '-' . $variation_id;

            if ( isset( $product_data[ $key ] ) ) {
                $product_data[ $key ]['quantity'] += $item->get_quantity();
            } else {
                $product_data[ $key ] = array(
                    'product' => $item->get_product(),
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'variation' => $variation_id ? wc_get_product( $variation_id )->get_attributes() : 'N/A',
                    'order_status' => wc_get_order_status_name($order->get_status())
                );
            }
        }
    }

    // Clean output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Output CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename=on-process-orders.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('Product Name', 'Purchased Quantity', 'Purchased Variation', 'Order Status'));

    foreach ( $product_data as $entry ) {
        $variation = is_array( $entry['variation'] ) ? implode( ', ', $entry['variation'] ) : $entry['variation'];
        fputcsv($output, array(
            $entry['name'],
            $entry['quantity'],
            $variation,
            $entry['order_status']
        ));
    }

    fclose($output);
    exit;
}

// Enqueue admin styles
add_action( 'admin_enqueue_scripts', 'opo_admin_styles' );

function opo_admin_styles() {
    wp_enqueue_style( 'opo-admin-styles', plugin_dir_url( __FILE__ ) . 'admin-styles.css' );
}
