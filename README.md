# TMI Category Filter

A lightweight WooCommerce archive filter built specifically for TMI Tractor Shop.

## Version 0.1.0

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

## Installation

Copy the repository into:

`wp-content/plugins/tmi-category-filter/`

Activate **TMI Category Filter** in WordPress.

Add this shortcode where the filter should render, for example in an Elementor Shortcode widget:

`[tmi_category_filter]`

## First test

1. Deactivate/disable WBW filtering on the Zero Turn Mowers archive while testing to avoid both plugins modifying the same query.
2. Visit the Zero Turn Mowers category.
3. Confirm out-of-stock mowers are visible without selecting stock.
4. Select only Deck Size = 54" and apply filters.
5. Confirm all matching 54" products remain regardless of Brand or Application.
6. Add Application = Commercial and confirm results narrow to 54" + Commercial.
7. Clear Application and confirm all 54" machines return.
8. Select Available In Store and confirm out-of-stock products disappear.

## Next stage

After the URL-based filtering is verified, AJAX can be added as a progressive enhancement without changing the underlying filter/query rules.
