# M2-ProductFinder

Drill-down product finder for Magento 2.4.5+. Admin-driven sections, attribute mapping, price slider, and results page with optional layered navigation.


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
Admin: **Stores → Configuration → Catalog → Merlin Product Finder**


- **General**: Enable, Show Layered Nav, Attribute Sets (multiselect).
- ** Json Wizard to create different profiles per attribute set
- **Form Layout & Content**: Drag to reorder sections; set custom HTML above/below.
- **Attribute Mapping**: Set attribute codes for product type, colour, extras (JSON map), and price settings.


## Theming / Overrides
- Templates under `view/frontend/templates/`.
- JS modules for form and slider: `view/frontend/web/js/`.
- Adjust CSS in `view/frontend/web/css/productfinder.css`.


## Notes
- Ensure mapped attributes are filterable (dropdown/multiselect) with options.
- For large catalogs, consider adding pagination and sorting on results page.
- You can extend `Controller/Index/Results.php` to include stock status, website filter, etc.
