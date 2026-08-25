<?php

defined( 'ABSPATH' ) || exit;

final class TMI_Filter_Config {

	private const ROOT_CATEGORY_SLUG = 'zero-turn-mowers';

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

	public static function get_attribute_filters() {
		return array_filter(
			array(
				'brand'       => self::build_attribute_filter( 'Brand', 'brand' ),
				'application' => self::build_attribute_filter( 'Application', 'application' ),
				'deck_size'   => self::build_attribute_filter( 'Deck Size', 'deck-size' ),
			)
		);
	}

	private static function build_attribute_filter( $label, $fallback_name ) {
		$taxonomy = self::resolve_attribute_taxonomy( $label, $fallback_name );

		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		return array(
			'label'    => $label,
			'taxonomy' => $taxonomy,
		);
	}

	private static function resolve_attribute_taxonomy( $label, $fallback_name ) {
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$attributes = wc_get_attribute_taxonomies();
			$label_key  = sanitize_title( $label );
			$name_key   = sanitize_title( $fallback_name );

			foreach ( $attributes as $attribute ) {
				$attribute_label = isset( $attribute->attribute_label ) ? sanitize_title( $attribute->attribute_label ) : '';
				$attribute_name  = isset( $attribute->attribute_name ) ? sanitize_title( $attribute->attribute_name ) : '';

				if ( $label_key === $attribute_label || $name_key === $attribute_name ) {
					return wc_attribute_taxonomy_name( $attribute->attribute_name );
				}
			}
		}

		return wc_attribute_taxonomy_name( $fallback_name );
	}

	public static function get_query_params() {
		return array(
			'brand'       => 'tmi_brand',
			'application' => 'tmi_application',
			'deck_size'   => 'tmi_deck_size',
			'min_price'   => 'tmi_min_price',
			'max_price'   => 'tmi_max_price',
			'stock'       => 'tmi_stock',
		);
	}
}
