# Merlin_ProductFinder

Drill-down product finder for Magento 2.4.5+. Admin-driven sections, attribute mapping, price slider, and results page with optional layered navigation.


v1.6.7


Fix: price slider to respect special prices and fall back to base prices if non found.


v1.6.6


Add: Re-introduce a compact, responsive Price slider that stays neatly inside our finder form and plays nicely with the progressive flow & in-stock filtering.


#v1.6.5


Add: Add an image for each attribute set which will show on the frontend form when a set is selected.


#v1.6.4


Fix: Mobile view on the results page was still cutting off the special price on certain devices.


#v1.6.3


Fix: Mobile view on the results page was cutting off the special price, changed to two product per row for mobiles.


#v1.6.2 

Update: Finder and Results with a clean, modern, and responsive UI.


#v1.6.1


Fix: added intersection filtering via AJAX for each subsequent dropdown. Seeding the first select from the preloaded per-set options and then fetch the next select’s options based on the user’s current selections, so we never present values that lead to zero salable products.


Fix: buttons in wrong location

#v1.6.0

Add: Added create/flush finder cache function and buttons

Fix: Json field wiping old profile when adding a new one; This has been fixed and now works as expected.
Fix: Profile name was setting EXAMPLE: "SET 127" and now sets the correct Attribute set name as the label.


#v1.5.1


Add: show special prices cleanly on the results page: original price struck-through, special price prominent, plus an optional “% off” badge.

Fix: Make attribute sets show in alphabetical order on frontend dropdowns

#v1.5.0


We've added a caching layer which caches the per-attribute-set in-stock options for ~1 hour, and auto-invalidates when product/stock changes are saved.

#v1.4.0


Fix: This release changes the way the finder works to make sure only attribute values from in-stock products are shown.

Fix: Make each step open after previous step is complete


# Inital Realease v1.3.0
## Features
- Enable/disable module.
- Drag-and-drop section ordering (Attribute Set, Product Type, Colour, Price, Extras).
- Map frontend fields to product attributes via admin.
- Configure attribute sets to be used as first step.
- Pre/Post custom HTML areas around the form.
- Results page filtering product collection using selected attributes and price.
- Optional layered navigation on the left column of the results page.
- Frontend form added simply via a widget which can be added on any page or block.

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
Admin: ** Stores - Configuration - Catalog - Merlin Product Finder **

- **General**: Enable, Show Layered Nav, Top Categories (multiselect).
- **Form Layout & Content**: Drag to reorder sections; set custom HTML above/below.
- **Attribute Mapping**: Set attribute codes for product type, colour, extras (JSON map), and price settings.

## Notes
- Ensure mapped attributes are filterable (dropdown/multiselect) with options.
- For large catalogs, consider adding pagination and sorting on results page.
