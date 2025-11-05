# Merlin_ProductFinder

Drill-down product finder for Magento 2.4.5-p6. Admin-driven sections, attribute mapping, price slider, and results page with optional layered navigation.


#1.5.0


We've added a caching layer which caches the per-attribute-set in-stock options for ~1 hour, and auto-invalidates when product/stock changes are saved.

#v1.4.0


This release changes the way the finder works to make sure only attribute values from in-stock products are shown.


# Inital Realease v1.3.0
## Features
- Enable/disable module.
- Drag-and-drop section ordering (Category, Product Type, Colour, Price, Extras).
- Map frontend fields to product attributes via admin.
- Configure top-level categories used as first step.
- Price slider with min/max/step.
- Pre/Post custom HTML areas around the form.
- Results page filtering product collection using selected attributes and price.
- Optional layered navigation on the left column of the results page.

## Routes
- Form: `/product-finder/`
- Results: `/product-finder/index/results`

## Install
1. Place module in `app/code/Merlin/ProductFinder`.
2. Run:
   ```bash
   bin/magento module:enable Merlin_ProductFinder
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```

## Configure
Admin: **Stores â†’ Configuration â†’ Catalog â†’ Merlin Product Finder**

- **General**: Enable, Show Layered Nav, Top Categories (multiselect).
- **Form Layout & Content**: Drag to reorder sections; set custom HTML above/below.
- **Attribute Mapping**: Set attribute codes for product type, colour, extras (JSON map), and price settings.

## Notes
- Ensure mapped attributes are filterable (dropdown/multiselect) with options.
- For large catalogs, consider adding pagination and sorting on results page.
