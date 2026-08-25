# TMI Category Filter

A lightweight WooCommerce archive filter built specifically for TMI Tractor Shop.

## Version 0.3.0

Initial target: the `zero-turn-mowers` product category and every descendant category.

## Admin settings

Version 0.3.0 adds **WooCommerce → TMI Category Filter** in WordPress admin.

The admin page provides:

- setup instructions for the shortcode `[tmi_category_filter]`;
- the required Elementor product-results CSS ID `tmi-product-results`;
- a configurable list of WooCommerce global attributes shown in the customer filter;
- customer-facing Display Name changes without renaming the underlying WooCommerce attribute;
- numeric ordering of filter attributes;
- the ability to remove configured attributes;
- the ability to add another existing WooCommerce global attribute.

Price and Stock remain built-in filter controls and are not managed in the attribute list.

If no saved settings exist, the default attributes remain:

1. Brand
2. Application
3. Deck Size

Existing query parameters remain backward compatible:

- Brand: `tmi_brand`
- Application: `tmi_application`
- Deck Size: `tmi_deck_size`

Additional attributes use automatically generated `tmi_attr_...` query parameters.

## Filter sources

- Configured attributes — existing WooCommerce global product attributes
- Price — WooCommerce `_price`
- Available In Store — WooCommerce `_stock_status`

The plugin resolves WooCommerce global attribute taxonomies from their registered attribute names and labels rather than requiring hard-coded taxonomy names.

## Filter logic

Filter groups are independent in the interface.

- Multiple choices **inside the same group** use OR logic.
  - Example: Brand = Hustler OR Bad Boy.
- Different filter groups use AND logic only when the shopper deliberately selects both.
  - Example: Deck Size = 54" AND Application = Commercial.
- Selecting Deck Size = 54" by itself returns every 54" mower in the current category tree, regardless of Brand or Application.
- Attribute choices are derived from the unfiltered current category/subcategory product set. Selecting one filter does not hide the available choices in another attribute group.

## Stock rule

WooCommerce can keep **Hide out of stock items** enabled globally.

Within Zero Turn Mowers and its descendants:

- no stock filter selected: show in-stock and out-of-stock products;
- `Available In Store` selected: show only products with `_stock_status = instock`.

## AJAX filtering

AJAX filtering uses the browser Fetch API as a progressive enhancement.

- Attribute and Stock choices update immediately when a selection changes.
- Price uses a dual-ended range slider in $500 steps.
- The price slider minimum remains $0.
- The price slider maximum is calculated from the highest-priced product matching the current category plus active attribute/stock selections, while deliberately ignoring the current Min/Max price restriction.
- The calculated maximum is rounded up to the next $500 step.
- Price values update while dragging and the AJAX filter runs when the handle is released.
- The browser URL is updated so the filtered view can be bookmarked or shared.
- Back/Forward navigation reloads the matching filtered results without a full page refresh.
- The Apply Filters button is rendered only inside `<noscript>`, so it appears only when JavaScript is unavailable.
- AJAX replaces only the WooCommerce product/results elements rather than replacing the surrounding Elementor results container.
- A minimum visible loading period is used so the product area fade is noticeable even when the filtered response is fast.

## Filter presentation

- Brand and Application use compact checkbox rows by default.
- Deck Size uses compact four-column selection tiles in the narrow archive sidebar.
- Newly added attributes use standard checkbox rows unless separately styled.
- Price uses a dual-ended slider with visible Min and Max values.
- Stock is presented as a highlighted Available In Store option.
- Clear Filters remains a full-width secondary action.

## Elementor setup

Add the filter shortcode where the filter should render:

`[tmi_category_filter]`

Set this CSS ID on the Elementor container that contains the WooCommerce archive products/results area:

`tmi-product-results`

Do not include `#` when entering the CSS ID in Elementor.

The wrapper is used for the loading/fade state only. The plugin does not replace the entire Elementor wrapper during AJAX updates.

## Installation

Copy the repository into:

`wp-content/plugins/tmi-category-filter/`

Activate **TMI Category Filter** in WordPress.

## Testing v0.3.0

1. Confirm **WooCommerce → TMI Category Filter** appears in WordPress admin.
2. Confirm the Setup section shows `[tmi_category_filter]` and `tmi-product-results`.
3. Confirm Brand, Application and Deck Size are present by default after upgrading from an installation with no saved attribute settings.
4. Change a Display Name and save; confirm only the customer-facing filter heading changes.
5. Change the Order values and save; confirm filter groups appear in that order.
6. Remove an attribute and save; confirm it disappears from the front-end filter without deleting the WooCommerce attribute itself.
7. Add another WooCommerce global attribute and confirm it appears in the filter.
8. Confirm Price and Stock continue to work independently of the configurable attribute list.
9. Confirm existing Brand/Application/Deck Size links remain compatible.
10. Purge LiteSpeed cache after updating the plugin files before final front-end testing.
