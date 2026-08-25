<?php

defined( 'ABSPATH' ) || exit;

final class TMI_Filter_Renderer {

	public static function init() {
		add_shortcode( 'tmi_category_filter', array( __CLASS__, 'render_shortcode' ) );
	}

	public static function render_shortcode() {
		$category = TMI_Filter_Config::get_current_supported_category();

		if ( ! $category ) {
			return '';
		}

		$attribute_filters = TMI_Filter_Config::get_attribute_filters();
		$params            = TMI_Filter_Config::get_query_params();
		$product_ids       = self::get_base_category_product_ids( $category );
		$price_min_limit   = 0;
		$price_max_limit   = 50000;
		$price_step        = 500;
		$current_min_price = self::get_price_value( $params['min_price'], $price_min_limit, $price_min_limit, $price_max_limit );
		$current_max_price = self::get_price_value( $params['max_price'], $price_max_limit, $price_min_limit, $price_max_limit );

		if ( $current_min_price > $current_max_price ) {
			$current_min_price = $current_max_price;
		}

		$min_position = ( ( $current_min_price - $price_min_limit ) / ( $price_max_limit - $price_min_limit ) ) * 100;
		$max_position = ( ( $current_max_price - $price_min_limit ) / ( $price_max_limit - $price_min_limit ) ) * 100;

		ob_start();
		?>
		<form class="tmi-category-filter" method="get" action="<?php echo esc_url( get_term_link( $category ) ); ?>">
			<div class="tmi-filter-groups">
				<?php foreach ( $attribute_filters as $filter_key => $filter ) : ?>
					<?php
					$terms = self::get_terms_for_products( $filter['taxonomy'], $product_ids, 'deck_size' === $filter_key );
					if ( ! $terms ) {
						continue;
					}
					$selected = self::get_selected_values( $params[ $filter_key ] );
					?>
					<fieldset class="tmi-filter-group tmi-filter-<?php echo esc_attr( $filter_key ); ?>">
						<legend><?php echo esc_html( $filter['label'] ); ?></legend>
						<div class="tmi-filter-options">
							<?php foreach ( $terms as $term ) : ?>
								<label class="tmi-filter-option">
									<input type="checkbox" name="<?php echo esc_attr( $params[ $filter_key ] ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $selected, true ) ); ?>>
									<span><?php echo esc_html( $term->name ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>

				<fieldset class="tmi-filter-group tmi-filter-price">
					<legend><?php esc_html_e( 'Price', 'tmi-category-filter' ); ?></legend>
					<div
						class="tmi-price-slider"
						style="--tmi-price-min-pos: <?php echo esc_attr( number_format( $min_position, 2, '.', '' ) ); ?>%; --tmi-price-max-pos: <?php echo esc_attr( number_format( $max_position, 2, '.', '' ) ); ?>%;"
					>
						<div class="tmi-price-values" aria-live="polite">
							<span class="tmi-price-value">
								<small><?php esc_html_e( 'Min', 'tmi-category-filter' ); ?></small>
								<output class="tmi-price-output tmi-price-output-min">$<?php echo esc_html( number_format_i18n( $current_min_price ) ); ?></output>
							</span>
							<span class="tmi-price-value tmi-price-value-max">
								<small><?php esc_html_e( 'Max', 'tmi-category-filter' ); ?></small>
								<output class="tmi-price-output tmi-price-output-max">$<?php echo esc_html( number_format_i18n( $current_max_price ) ); ?></output>
							</span>
						</div>

						<div class="tmi-price-range-wrap">
							<div class="tmi-price-track" aria-hidden="true"><span></span></div>
							<input
								type="range"
								class="tmi-price-range tmi-price-range-min"
								min="<?php echo esc_attr( $price_min_limit ); ?>"
								max="<?php echo esc_attr( $price_max_limit ); ?>"
								step="<?php echo esc_attr( $price_step ); ?>"
								name="<?php echo esc_attr( $params['min_price'] ); ?>"
								value="<?php echo esc_attr( $current_min_price ); ?>"
								aria-label="<?php esc_attr_e( 'Minimum price', 'tmi-category-filter' ); ?>"
							>
							<input
								type="range"
								class="tmi-price-range tmi-price-range-max"
								min="<?php echo esc_attr( $price_min_limit ); ?>"
								max="<?php echo esc_attr( $price_max_limit ); ?>"
								step="<?php echo esc_attr( $price_step ); ?>"
								name="<?php echo esc_attr( $params['max_price'] ); ?>"
								value="<?php echo esc_attr( $current_max_price ); ?>"
								aria-label="<?php esc_attr_e( 'Maximum price', 'tmi-category-filter' ); ?>"
							>
						</div>

						<div class="tmi-price-scale" aria-hidden="true">
							<span>$<?php echo esc_html( number_format_i18n( $price_min_limit ) ); ?></span>
							<span>$<?php echo esc_html( number_format_i18n( $price_max_limit ) ); ?></span>
						</div>
					</div>
				</fieldset>

				<fieldset class="tmi-filter-group tmi-filter-stock">
					<legend><?php esc_html_e( 'Stock', 'tmi-category-filter' ); ?></legend>
					<label class="tmi-filter-option tmi-stock-option">
						<input type="checkbox" name="<?php echo esc_attr( $params['stock'] ); ?>" value="instock" <?php checked( 'instock', self::get_scalar_value( $params['stock'] ) ); ?>>
						<span><?php esc_html_e( 'Available In Store', 'tmi-category-filter' ); ?></span>
					</label>
				</fieldset>
			</div>

			<div class="tmi-filter-actions">
				<noscript>
					<button type="submit" class="tmi-filter-apply"><?php esc_html_e( 'Apply Filters', 'tmi-category-filter' ); ?></button>
				</noscript>
				<a class="tmi-filter-clear" href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php esc_html_e( 'Clear Filters', 'tmi-category-filter' ); ?></a>
			</div>
		</form>
		<?php
		return ob_get_clean();
	}

	private static function get_base_category_product_ids( WP_Term $category ) {
		return get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => array( $category->term_id ),
						'include_children' => true,
					),
				),
			)
		);
	}

	private static function get_terms_for_products( $taxonomy, $product_ids, $numeric_sort = false ) {
		if ( ! $product_ids ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'object_ids' => $product_ids,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		if ( $numeric_sort ) {
			usort(
				$terms,
				static function ( $a, $b ) {
					$a_num = (float) preg_replace( '/[^0-9.]/', '', $a->name );
					$b_num = (float) preg_replace( '/[^0-9.]/', '', $b->name );
					return $a_num <=> $b_num;
				}
			);
		}

		return $terms;
	}

	private static function get_selected_values( $param_name ) {
		if ( empty( $_GET[ $param_name ] ) ) {
			return array();
		}

		$raw = wp_unslash( $_GET[ $param_name ] );
		$raw = is_array( $raw ) ? $raw : array( $raw );
		return array_values( array_filter( array_map( 'sanitize_title', $raw ) ) );
	}

	private static function get_scalar_value( $param_name ) {
		if ( ! isset( $_GET[ $param_name ] ) || is_array( $_GET[ $param_name ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_GET[ $param_name ] ) );
	}

	private static function get_price_value( $param_name, $default, $minimum, $maximum ) {
		$value = self::get_scalar_value( $param_name );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return $default;
		}

		$value = (int) round( (float) $value );
		return max( $minimum, min( $maximum, $value ) );
	}
}
