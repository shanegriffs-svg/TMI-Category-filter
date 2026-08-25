# TMI Category Filter

A lightweight WooCommerce archive filter built specifically for TMI Tractor Shop.

## Version 0.2.0

Initial target: the `zero-turn-mowers` product category and every descendant category.

### Filter sources

- Brand — existing WooCommerce global product attribute
- Application — existing WooCommerce global product attribute
- Deck Size — existing WooCommerce global product attribute
- Price — WooCommerce `_price`
- Available In Store — WooCommerce `_stock_status`

The plugin resolves the WooCommerce global attribute taxonomies from their existing labels, so it is not dependent on a hard-coded `pa_deck-size` style taxonomy slug.

## Filter logic

Filter groups are independent in the interface.

- Multiple choices **inside the same group** use OR logic.
  - Example: Brand = Hustler OR Bad Boy.
- Different filter groups use AND logic only when the shopper deliberately selects both.
  - Example: Deck Size = 54" AND Application = Commercial.
- Selecting Deck Size = 54" by itself returns every 54" mower in the current category tree, regardless of Brand or Application.
- The choices shown in Brand, Application and Deck Size are derived from the unfiltered current category/subcategory product set. Selecting one filter does not hide choices in another filter.

## Stock rule

WooCommerce can keep **Hide out of stock items** enabled globally.

Within Zero Turn Mowers and its descendants:

- no stock filter selected: show in-stock and out-of-stock products;
- `Available In Store` selected: show only products with `_stock_status = instock`.

## AJAX filtering

Version 0.2.0 adds progressive AJAX filtering using the browser Fetch API.

- Brand, Application, Deck Size and Stock update immediately when a selection changes.
- Price inputs update after a short typing delay.
- The browser URL is updated so the filtered view can be bookmarked or shared.
- Back/Forward navigation reloads the matching filtered results without a full page refresh.
- The normal GET form remains available as a fallback if JavaScript is disabled or the AJAX replacement cannot be completed.

### Recommended Elementor results wrapper

For the most reliable archive replacement, set the Elementor CSS ID below on the container or widget that contains the WooCommerce archive products/results area:

`tmi-product-results`

The JavaScript first looks for `#tmi-product-results`. If it is not present, it falls back to standard WooCommerce archive selectors such as `ul.products` and `.woocommerce-pagination`.

## Installation

Copy the repository into:

`wp-content/plugins/tmi-category-filter/`

Activate **TMI Category Filter** in WordPress.

Add this shortcode where the filter should render, for example in an Elementor Shortcode widget:

`[tmi_category_filter]`

## Testing v0.2.0

1. Add CSS ID `tmi-product-results` to the Elementor archive products/results container.
2. Purge LiteSpeed cache after installing the updated plugin files.
3. Visit the Zero Turn Mowers category in a fresh browser window.
4. Select Brand, Application or Deck Size and confirm the product grid updates without a full page reload.
5. Select only Deck Size = 54" and confirm all matching 54" products remain regardless of Brand or Application.
6. Add Application = Commercial and confirm results narrow to 54" + Commercial.
7. Clear Application and confirm all 54" machines return.
8. Select Available In Store and confirm out-of-stock products disappear.
9. Test the browser Back button and confirm the previous filter state/results return.
