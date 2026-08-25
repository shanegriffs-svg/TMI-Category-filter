<?php

defined( 'ABSPATH' ) || exit;

final class TMI_Filter_Admin {

	private const PAGE_SLUG = 'tmi-category-filter';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ) );
		add_action( 'admin_post_tmi_save_category_filter_settings', array( __CLASS__, 'save_settings' ) );
	}

	public static function add_admin_page() {
		add_submenu_page(
			'woocommerce',
			__( 'TMI Category Filter', 'tmi-category-filter' ),
			__( 'TMI Category Filter', 'tmi-category-filter' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'tmi-category-filter' ) );
		}

		$settings             = TMI_Filter_Config::get_attribute_settings();
		$available_attributes = TMI_Filter_Config::get_available_attributes();
		$configured_names     = wp_list_pluck( $settings, 'attribute_name' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TMI Category Filter', 'tmi-category-filter' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Filter settings saved.', 'tmi-category-filter' ); ?></p></div>
			<?php endif; ?>

			<div style="max-width: 980px;">
				<div class="card" style="max-width:none; margin-top:20px;">
					<h2><?php esc_html_e( 'Setup', 'tmi-category-filter' ); ?></h2>
					<p><?php esc_html_e( 'The filter currently runs on the Zero Turn Mowers product category and its child categories.', 'tmi-category-filter' ); ?></p>
					<table class="widefat striped" style="max-width:760px;">
						<tbody>
							<tr>
								<th style="width:220px;"><?php esc_html_e( 'Filter shortcode', 'tmi-category-filter' ); ?></th>
								<td><code>[tmi_category_filter]</code></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Elementor results CSS ID', 'tmi-category-filter' ); ?></th>
								<td><code>tmi-product-results</code></td>
							</tr>
						</tbody>
					</table>
					<p class="description" style="margin-top:10px;"><?php esc_html_e( 'Add the CSS ID to the Elementor container that holds the WooCommerce product results. Do not include the # symbol in Elementor.', 'tmi-category-filter' ); ?></p>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tmi_save_category_filter_settings">
					<?php wp_nonce_field( 'tmi_save_category_filter_settings' ); ?>

					<div class="card" style="max-width:none; margin-top:20px;">
						<h2><?php esc_html_e( 'Filter Attributes', 'tmi-category-filter' ); ?></h2>
						<p><?php esc_html_e( 'Choose which WooCommerce global attributes appear in the customer filter. Changing the Display Name only changes the filter heading; it does not rename the WooCommerce attribute or alter product data.', 'tmi-category-filter' ); ?></p>

						<table class="widefat striped">
							<thead>
								<tr>
									<th style="width:80px;"><?php esc_html_e( 'Order', 'tmi-category-filter' ); ?></th>
									<th><?php esc_html_e( 'WooCommerce Attribute', 'tmi-category-filter' ); ?></th>
									<th><?php esc_html_e( 'Display Name', 'tmi-category-filter' ); ?></th>
									<th style="width:90px;"><?php esc_html_e( 'Remove', 'tmi-category-filter' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( $settings ) : ?>
									<?php foreach ( $settings as $index => $setting ) : ?>
										<?php
										$attribute_name  = isset( $setting['attribute_name'] ) ? $setting['attribute_name'] : '';
										$attribute_label = isset( $available_attributes[ $attribute_name ] ) ? $available_attributes[ $attribute_name ] : $attribute_name;
										$order           = isset( $setting['order'] ) ? (int) $setting['order'] : ( ( $index + 1 ) * 10 );
										?>
										<tr>
											<td>
												<input type="number" min="0" step="1" name="attributes[<?php echo esc_attr( $index ); ?>][order]" value="<?php echo esc_attr( $order ); ?>" class="small-text">
											</td>
											<td>
												<strong><?php echo esc_html( $attribute_label ); ?></strong>
												<br><code><?php echo esc_html( $attribute_name ); ?></code>
												<input type="hidden" name="attributes[<?php echo esc_attr( $index ); ?>][attribute_name]" value="<?php echo esc_attr( $attribute_name ); ?>">
											</td>
											<td>
												<input type="text" name="attributes[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $setting['label'] ); ?>" class="regular-text">
											</td>
											<td>
												<label><input type="checkbox" name="attributes[<?php echo esc_attr( $index ); ?>][remove]" value="1"> <?php esc_html_e( 'Remove', 'tmi-category-filter' ); ?></label>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr><td colspan="4"><?php esc_html_e( 'No attribute filters are currently enabled. Price and Stock will still be shown.', 'tmi-category-filter' ); ?></td></tr>
								<?php endif; ?>
							</tbody>
						</table>

						<h3 style="margin-top:24px;"><?php esc_html_e( 'Add Attribute', 'tmi-category-filter' ); ?></h3>
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="tmi-add-attribute"><?php esc_html_e( 'WooCommerce attribute', 'tmi-category-filter' ); ?></label></th>
									<td>
										<select id="tmi-add-attribute" name="add_attribute_name">
											<option value=""><?php esc_html_e( '— Select an attribute —', 'tmi-category-filter' ); ?></option>
											<?php foreach ( $available_attributes as $attribute_name => $attribute_label ) : ?>
												<?php if ( in_array( $attribute_name, $configured_names, true ) ) { continue; } ?>
												<option value="<?php echo esc_attr( $attribute_name ); ?>"><?php echo esc_html( $attribute_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="tmi-add-label"><?php esc_html_e( 'Display name', 'tmi-category-filter' ); ?></label></th>
									<td>
										<input id="tmi-add-label" type="text" name="add_attribute_label" class="regular-text">
										<p class="description"><?php esc_html_e( 'Optional. Leave blank to use the normal WooCommerce attribute label.', 'tmi-category-filter' ); ?></p>
									</td>
								</tr>
							</tbody>
						</table>

						<p class="description"><?php esc_html_e( 'Price and Stock are built-in filter controls and are not managed in the attribute list.', 'tmi-category-filter' ); ?></p>
					</div>

					<?php submit_button( __( 'Save Filter Settings', 'tmi-category-filter' ) ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'tmi-category-filter' ) );
		}

		check_admin_referer( 'tmi_save_category_filter_settings' );

		$available_attributes = TMI_Filter_Config::get_available_attributes();
		$rows                 = isset( $_POST['attributes'] ) && is_array( $_POST['attributes'] ) ? wp_unslash( $_POST['attributes'] ) : array();
		$clean                = array();
		$sequence             = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['remove'] ) ) {
				continue;
			}

			$attribute_name = isset( $row['attribute_name'] ) ? sanitize_title( $row['attribute_name'] ) : '';

			if ( ! $attribute_name || ! isset( $available_attributes[ $attribute_name ] ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$order = isset( $row['order'] ) ? absint( $row['order'] ) : ( ( $sequence + 1 ) * 10 );

			$clean[ $attribute_name ] = array(
				'attribute_name' => $attribute_name,
				'label'          => $label ? $label : $available_attributes[ $attribute_name ],
				'order'          => $order,
				'_sequence'      => $sequence,
			);
			$sequence++;
		}

		$add_attribute_name = isset( $_POST['add_attribute_name'] ) ? sanitize_title( wp_unslash( $_POST['add_attribute_name'] ) ) : '';

		if ( $add_attribute_name && isset( $available_attributes[ $add_attribute_name ] ) && ! isset( $clean[ $add_attribute_name ] ) ) {
			$label       = isset( $_POST['add_attribute_label'] ) ? sanitize_text_field( wp_unslash( $_POST['add_attribute_label'] ) ) : '';
			$max_order   = 0;

			foreach ( $clean as $item ) {
				$max_order = max( $max_order, (int) $item['order'] );
			}

			$clean[ $add_attribute_name ] = array(
				'attribute_name' => $add_attribute_name,
				'label'          => $label ? $label : $available_attributes[ $add_attribute_name ],
				'order'          => $max_order + 10,
				'_sequence'      => $sequence,
			);
		}

		$clean = array_values( $clean );
		usort(
			$clean,
			static function ( $a, $b ) {
				$order_compare = (int) $a['order'] <=> (int) $b['order'];
				return 0 !== $order_compare ? $order_compare : (int) $a['_sequence'] <=> (int) $b['_sequence'];
			}
		);

		foreach ( $clean as &$item ) {
			unset( $item['_sequence'] );
		}
		unset( $item );

		update_option( TMI_Filter_Config::get_attribute_option_name(), $clean, false );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&updated=1' ) );
		exit;
	}
}
