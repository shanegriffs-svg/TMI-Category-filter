<?php

defined( 'ABSPATH' ) || exit;

final class TMI_Filter_Config {

	private const ROOT_CATEGORY_SLUG = 'zero-turn-mowers';
	private const ATTRIBUTE_OPTION   = 'tmi_category_filter_attributes';

	public static function get_current_supported_category() {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return null;
		}

		$term = get_queried_object();

		if ( ! $term instanceof WP_Term || 'product_cat' !== $term->taxonomy ) {
			return null;
		}

		if ( self::ROOT_CATEGORY_SLUG === $term->slug ) {
			return $term;
		}

		$ancestors = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );

		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );
			if ( $ancestor instanceof WP_Term && self::ROOT_CATEGORY_SLUG === $ancestor->slug ) {
				return $term;
			}
		}

		return null;
	}

	public static function is_supported_archive() {
		return null !== self::get_current_supported_category();
	}

	public static function get_attribute_option_name() {
		return self::ATTRIBUTE_OPTION;
	}

	public static function get_available_attributes() {
		$available = array();

		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return $available;
		}

		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			if ( empty( $attribute->attribute_name ) ) {
				continue;
			}

			$name  = sanitize_title( $attribute->attribute_name );
			$label = ! empty( $attribute->attribute_label ) ? sanitize_text_field( $attribute->attribute_label ) : $name;

			$available[ $name ] = $label;
		}

		return $available;
	}

	public static function get_attribute_settings() {
		$stored = get_option( self::ATTRIBUTE_OPTION, null );

		if ( null === $stored || ! is_array( $stored ) ) {
			return self::get_default_attribute_settings();
		}

		$settings = array();

		foreach ( $stored as $index => $row ) {
			if ( ! is_array( $row ) || empty( $row['attribute_name'] ) ) {
				continue;
			}

			$attribute_name = sanitize_title( $row['attribute_name'] );
			$label          = ! empty( $row['label'] ) ? sanitize_text_field( $row['label'] ) : $attribute_name;
			$order          = isset( $row['order'] ) ? absint( $row['order'] ) : ( ( $index + 1 ) * 10 );

			$settings[] = array(
				'attribute_name' => $attribute_name,
				'label'          => $label,
				'order'          => $order,
			);
		}

		usort(
			$settings,
			static function ( $a, $b ) {
				return (int) $a['order'] <=> (int) $b['order'];
			}
		);

		return $settings;
	}

	public static function get_attribute_filters() {
		$filters = array();

		foreach ( self::get_attribute_settings() as $setting ) {
			$attribute_name = $setting['attribute_name'];
			$taxonomy       = self::resolve_attribute_taxonomy( $setting['label'], $attribute_name );

			if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$key = self::get_attribute_key( $attribute_name );

			$filters[ $key ] = array(
				'label'          => $setting['label'],
				'taxonomy'       => $taxonomy,
				'attribute_name' => $attribute_name,
			);
		}

		return $filters;
	}

	private static function get_default_attribute_settings() {
		return array(
			array(
				'attribute_name' => 'brand',
				'label'          => 'Brand',
				'order'          => 10,
			),
			array(
				'attribute_name' => 'application',
				'label'          => 'Application',
				'order'          => 20,
			),
			array(
				'attribute_name' => 'deck-size',
				'label'          => 'Deck Size',
				'order'          => 30,
			),
		);
	}

	private static function get_attribute_key( $attribute_name ) {
		return str_replace( '-', '_', sanitize_key( $attribute_name ) );
	}

	private static function resolve_attribute_taxonomy( $label, $fallback_name ) {
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$attributes = wc_get_attribute_taxonomies();
			$label_key  = sanitize_title( $label );
			$name_key   = sanitize_title( $fallback_name );

			foreach ( $attributes as $attribute ) {
				$attribute_label = isset( $attribute->attribute_label ) ? sanitize_title( $attribute->attribute_label ) : '';
				$attribute_name  = isset( $attribute->attribute_name ) ? sanitize_title( $attribute->attribute_name ) : '';

				if ( $name_key === $attribute_name || $label_key === $attribute_label ) {
					return wc_attribute_taxonomy_name( $attribute->attribute_name );
				}
			}
		}

		return wc_attribute_taxonomy_name( $fallback_name );
	}

	public static function get_query_params() {
		$params = array(
			'min_price' => 'tmi_min_price',
			'max_price' => 'tmi_max_price',
			'stock'     => 'tmi_stock',
		);

		foreach ( self::get_attribute_settings() as $setting ) {
			$key = self::get_attribute_key( $setting['attribute_name'] );

			switch ( $key ) {
				case 'brand':
					$params[ $key ] = 'tmi_brand';
					break;
				case 'application':
					$params[ $key ] = 'tmi_application';
					break;
				case 'deck_size':
					$params[ $key ] = 'tmi_deck_size';
					break;
				default:
					$params[ $key ] = 'tmi_attr_' . $key;
					break;
			}
		}

		return $params;
	}
}
