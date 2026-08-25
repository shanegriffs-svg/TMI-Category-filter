<?php

defined( 'ABSPATH' ) || exit;

final class TMI_Filter_Query {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_wbw_filters' ), 5 );

		add_filter(
			'pre_option_woocommerce_hide_out_of_stock_items',
			array( __CLASS__, 'allow_out_of_stock_on_supported_archives' ),
			PHP_INT_MAX,
			1
		);

		add_filter(
			'woocommerce_product_query_tax_query',
			array( __CLASS__, 'remove_out_of_stock_visibility_exclusion' ),
			PHP_INT_MAX,
			2
		);

		add_action( 'woocommerce_product_query', array( __CLASS__, 'apply_filters_to_product_query' ), 50, 1 );
	}

	/**
	 * Convert legacy WBW Zero Turn Mower links to the TMI filter format.
	 *
	 * Example:
	 * ?wpf_fbv=1&pr_stock=instock&wpf_filter_brand=hustler-zero-turn-mower
	 * becomes:
	 * ?tmi_brand=hustler-zero-turn-mower&tmi_stock=instock
	 */
	public static function redirect_legacy_wbw_filters() {
		if ( ! TMI_Filter_Config::is_supported_archive() ) {
			return;
		}

		$has_legacy_brand = isset( $_GET['wpf_filter_brand'] ) && '' !== $_GET['wpf_filter_brand'];
		$has_legacy_stock = isset( $_GET['pr_stock'] ) && '' !== $_GET['pr_stock'];

		if ( ! $has_legacy_brand && ! $has_legacy_stock ) {
			return;
		}

		$category = TMI_Filter_Config::get_current_supported_category();
		$params   = TMI_Filter_Config::get_query_params();

		if ( ! $category ) {
			return;
		}

		$args = array();

		if ( $has_legacy_brand ) {
			$brand = sanitize_title( wp_unslash( $_GET['wpf_filter_brand'] ) );
			if ( $brand ) {
				$args[ $params['brand'] ] = $brand;
			}
		}

		if ( $has_legacy_stock ) {
			$stock = sanitize_key( wp_unslash( $_GET['pr_stock'] ) );
			if ( 'instock' === $stock ) {
				$args[ $params['stock'] ] = 'instock';
			}
		}

		$target = get_term_link( $category );
		if ( is_wp_error( $target ) ) {
			return;
		}

		if ( $args ) {
			$target = add_query_arg( $args, $target );
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	public static function allow_out_of_stock_on_supported_archives( $pre_option ) {
		if ( self::is_supported_request() ) {
			return 'no';
		}

		return $pre_option;
	}

	public static function remove_out_of_stock_visibility_exclusion( $tax_query, $wc_query ) {
		unset( $wc_query );

		if ( ! self::is_supported_request() || self::stock_filter_is_selected() ) {
			return $tax_query;
		}

		$visibility_terms = wc_get_product_visibility_term_ids();
		$outofstock_id    = isset( $visibility_terms['outofstock'] ) ? (int) $visibility_terms['outofstock'] : 0;

		if ( ! $outofstock_id ) {
			return $tax_query;
		}

		return self::remove_visibility_term_recursively( $tax_query, $outofstock_id );
	}

	public static function apply_filters_to_product_query( $query ) {
		if ( ! TMI_Filter_Config::is_supported_archive() ) {
			return;
		}

		$attribute_filters = TMI_Filter_Config::get_attribute_filters();
		$params            = TMI_Filter_Config::get_query_params();
		$tax_query         = (array) $query->get( 'tax_query' );
		$meta_query        = (array) $query->get( 'meta_query' );

		// OR within a filter group, AND between different filter groups.
		foreach ( $attribute_filters as $filter_key => $filter ) {
			if ( empty( $params[ $filter_key ] ) ) {
				continue;
			}

			$selected = self::get_selected_slugs( $params[ $filter_key ] );

			if ( ! $selected ) {
				continue;
			}

			$tax_query[] = array(
				'taxonomy' => $filter['taxonomy'],
				'field'    => 'slug',
				'terms'    => $selected,
				'operator' => 'IN',
			);
		}

		if ( count( $tax_query ) > 1 && empty( $tax_query['relation'] ) ) {
			$tax_query['relation'] = 'AND';
		}

		$min_price = self::get_decimal_param( $params['min_price'] );
		$max_price = self::get_decimal_param( $params['max_price'] );

		if ( null !== $min_price || null !== $max_price ) {
			$price_clause = array(
				'key'  => '_price',
				'type' => 'DECIMAL(10,2)',
			);

			if ( null !== $min_price && null !== $max_price ) {
				$price_clause['value']   = array( $min_price, $max_price );
				$price_clause['compare'] = 'BETWEEN';
			} elseif ( null !== $min_price ) {
				$price_clause['value']   = $min_price;
				$price_clause['compare'] = '>=';
			} else {
				$price_clause['value']   = $max_price;
				$price_clause['compare'] = '<=';
			}

			$meta_query[] = $price_clause;
		}

		if ( self::stock_filter_is_selected() ) {
			$meta_query[] = array(
				'key'     => '_stock_status',
				'value'   => 'instock',
				'compare' => '=',
			);
		}

		if ( count( $meta_query ) > 1 && empty( $meta_query['relation'] ) ) {
			$meta_query['relation'] = 'AND';
		}

		$query->set( 'tax_query', $tax_query );
		$query->set( 'meta_query', $meta_query );
	}

	private static function is_supported_request() {
		if ( TMI_Filter_Config::is_supported_archive() ) {
			return true;
		}

		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
			if ( is_string( $path ) && false !== strpos( $path, '/product-category/zero-turn-mowers' ) ) {
				return true;
			}
		}

		return false;
	}

	private static function stock_filter_is_selected() {
		$params = TMI_Filter_Config::get_query_params();
		return isset( $_GET[ $params['stock'] ] ) && 'instock' === sanitize_key( wp_unslash( $_GET[ $params['stock'] ] ) );
	}

	private static function get_selected_slugs( $param_name ) {
		if ( empty( $_GET[ $param_name ] ) ) {
			return array();
		}

		$raw = wp_unslash( $_GET[ $param_name ] );
		$raw = is_array( $raw ) ? $raw : array( $raw );

		return array_values(
			array_unique(
				array_filter( array_map( 'sanitize_title', $raw ) )
			)
		);
	}

	private static function get_decimal_param( $param_name ) {
		if ( ! isset( $_GET[ $param_name ] ) || '' === $_GET[ $param_name ] ) {
			return null;
		}

		$value = wc_format_decimal( wp_unslash( $_GET[ $param_name ] ) );

		return is_numeric( $value ) ? (float) $value : null;
	}

	private static function remove_visibility_term_recursively( $tax_query, $term_id ) {
		foreach ( $tax_query as $key => $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}

			if (
				'product_visibility' === ( $clause['taxonomy'] ?? '' ) &&
				'NOT IN' === strtoupper( $clause['operator'] ?? '' )
			) {
				$terms = array_map( 'intval', (array) ( $clause['terms'] ?? array() ) );
				$terms = array_values( array_diff( $terms, array( $term_id ) ) );

				if ( $terms ) {
					$tax_query[ $key ]['terms'] = $terms;
				} else {
					unset( $tax_query[ $key ] );
				}
				continue;
			}

			$tax_query[ $key ] = self::remove_visibility_term_recursively( $clause, $term_id );
		}

		return $tax_query;
	}
}
