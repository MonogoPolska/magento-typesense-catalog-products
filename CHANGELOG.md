# CHANGE LOG

## [1.0.12] - 2026-08-05
- Added Indexer Filters: a Cart Price Rule style condition tree in the module configuration that narrows down the
  product collection processed by the `typesense_products` and `typesense_products_children` indexers
- Added stock status and quantity conditions, which are not reachable through product attributes
- Added an option to keep composite products whose child products match the conditions
- Saving the conditions invalidates the product indexers

## [1.0.4] - 2025-11-14
- Fix for YesNo class 

## [1.0.3] - 2025-03-07
- Added sortable field salable_qty
- Changed stock counting logic for configurable products

## [1.0.2] - 2025-02-21
- Fixed issue with missing method for magento versions before 2.4.7

## [1.0.1] - 2024-12-12
- Added embeddings and general improvements

## [1.0.0] - 2023-08-25
- Initial release