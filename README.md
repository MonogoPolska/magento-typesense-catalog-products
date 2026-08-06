# Typesense Magento integration - Catalog Products indexer

Indexer for Magento Catalog Products

## Configuration
As the first step, Go to Magento Admin &rarr; Configuration &rarr; Typesense &rarr; Catalog Products


## Indexer Filters
Stores &rarr; Configuration &rarr; Typesense &rarr; Catalog Products &rarr; Indexer Filters

By default the whole catalog is indexed. Indexer Filters narrow down the product collection processed by the indexers,
using the same condition tree that Cart Price Rules use. Only products matching the conditions are indexed.

Conditions can be nested, combined with `ALL`/`ANY` and negated with `TRUE`/`FALSE`, so a rule such as
`(color is Red OR color is Blue) AND stock status is not Out of Stock` is expressed as:

```
If ALL of these conditions are TRUE:
    If ANY of these conditions are TRUE:
        color is Red
        color is Blue
    Stock Status is In Stock
```

Available condition subjects:

| Subject             | Notes                                                                             |
|---------------------|-----------------------------------------------------------------------------------|
| Product attributes  | Every attribute with a frontend label, evaluated in the scope of the indexed store |
| SKU, Attribute Set  | Static columns of `catalog_product_entity`                                        |
| Product Type        | simple, configurable, bundle, grouped, ...                                        |
| Category            | Matches products assigned to the selected categories                              |
| Stock Status, Quantity | Read from `cataloginventory_stock_status`, not available as product attributes |

Settings:

| Field                                 | Description                                                                                                                                                          |
|---------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Enabled                               | Turns filtering off without losing the configured conditions                                                                                                         |
| Product index conditions              | Applied to `typesense_products`                                                                                                                                      |
| Include parents of matching children  | Keeps configurable, bundle and grouped products whose children match. Needed when filtering by attributes that only simple products carry, such as color or size      |
| Children index conditions             | Applied to `typesense_products_children`                                                                                                                             |

Conditions are store view scoped. Saving them invalidates both product indexers, and products that stop matching are
removed from the Typesense collection on the next reindex.

### How parents are matched

With `Include parents of matching children` set to Yes a composite product is kept when it matches on its own values
**or** when at least one of its children matches. The two branches are combined with `OR`, so one matching child is
enough and the parent's own values are never able to exclude it.

That is what makes attribute filters usable, because a configurable carries no `color` or `size` of its own. It also
means conditions the parent does carry cannot veto the result. A configurable that is out of stock stays in the index
as long as one of its variants is in stock, even with a condition such as `Stock Status is In Stock`. Set the option to
No to judge composite products only by their own values.

## Indexers

| Indexer                                                | Description                                                                                                                      |
|--------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------|
| ```bin/magento indexer:reindex typesense_products```   | Typesense Products indexer. To enable this, configure <br/>Stores &rarr; Configuration &rarr;Typesense &rarr; Catalog Products   |


## Initial schema
```
'name' => $prefix . '_products' . $suffix,
'fields' => [
            'entity_id' => ['name' => 'entity_id', 'type' => 'int32', 'optional' => false, 'index' => true],
            'uid' => ['name' => 'uid', 'type' => 'string', 'optional' => false, 'index' => true],
            'sku' => ['name' => 'sku', 'type' => 'string', 'optional' => false, 'index' => true],
            'store_id' => ['name' => 'store_id', 'type' => 'int32', 'optional' => true, 'index' => false],
            'status' => ['name' => 'status', 'type' => 'int32', 'optional' => true, 'index' => false],
            'visibility' => ['name' => 'visibility', 'type' => 'int32', 'optional' => true, 'index' => false],
            'visibility_label' => ['name' => 'visibility_label', 'type' => 'string', 'optional' => true, 'index' => false],
            'name' => ['name' => 'name', 'type' => 'string', 'optional' => false, 'index' => true],
            'url' => ['name' => 'url', 'type' => 'string', 'optional' => false, 'index' => true],
            'url_key' => ['name' => 'url_key', 'type' => 'string', 'optional' => false, 'index' => true],
            'type_id' => ['name' => 'type_id', 'type' => 'string', 'optional' => true, 'index' => false,],
            'subproducts' => ['name' => 'subproducts', 'type' => 'string[]', 'optional' => true, 'index' => false,],
            'parent_ids' => ['name' => 'parent_ids', 'type' => 'string[]', 'optional' => true, 'index' => false,],
            'description' => ['name' => 'description', 'type' => 'string', 'optional' => true, 'index' => false],
            'description_stripped' => ['name' => 'description_stripped', 'type' => 'string', 'optional' => true, 'index' => true],
            'short_description' => ['name' => 'short_description', 'type' => 'string', 'optional' => true, 'index' => false],
            'short_description_stripped' => ['name' => 'short_description_stripped', 'type' => 'string', 'optional' => true, 'index' => true],
            'meta_title' => ['name' => 'meta_title', 'type' => 'string', 'optional' => true, 'index' => true],
            'meta_keywords' => ['name' => 'meta_keywords', 'type' => 'string', 'optional' => true, 'index' => true],
            'meta_description' => ['name' => 'meta_description', 'type' => 'string', 'optional' => true, 'index' => true],
            'category_ids' => ['name' => 'category_ids', 'type' => 'string[]', 'optional' => true, 'index' => true],
            'category_uid' => ['name' => 'category_uid', 'type' => 'string[]', 'optional' => true, 'index' => true, 'facet' => true],
            'stock_status' => ['name' => 'stock_status', 'type' => 'string', 'optional' => false, 'index' => true, 'facet' => true],
            'related_products_ids' => ['name' => 'related_products_ids', 'type' => 'string[]', 'optional' => true, 'index' => false],
            'upsell_products_ids' => ['name' => 'upsell_products_ids', 'type' => 'string[]', 'optional' => true, 'index' => false],
            'crossell_products_ids' => ['name' => 'crossell_products_ids', 'type' => 'string[]', 'optional' => true, 'index' => false],
            'final_price' => ['name' => 'final_price', 'type' => 'float', 'optional' => false, 'index' => true, 'sort' => true],
        ];
```

# Credits
- [Monogo](https://monogo.pl/en)
- [Typesense](https://typesense.org)
- [Official Algolia magento module](https://github.com/algolia/algoliasearch-magento-2)
