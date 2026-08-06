<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Rule\Condition;

use Magento\Backend\Helper\Data as BackendData;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductCategoryList;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection as AttributeSetCollection;
use Magento\Framework\DB\Select;
use Magento\Framework\Locale\FormatInterface;
use Magento\Rule\Model\Condition\Context;
use Magento\Rule\Model\Condition\Product\AbstractProduct;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Product attribute condition translated into SQL applied to the indexer product collection
 */
class Product extends AbstractProduct
{
    use ElementNameTrait;

    /**
     * Attributes handled by static columns of catalog_product_entity or by dedicated parent logic
     */
    const SPECIAL_ATTRIBUTES = ['sku', 'type_id', 'attribute_set_id', 'category_ids'];

    /**
     * @var string
     */
    protected $elementName = 'rule';

    /**
     * @var array
     */
    protected array $joinedAttributes = [];

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ProductType
     */
    private ProductType $productType;

    /**
     * @param Context $context
     * @param BackendData $backendData
     * @param EavConfig $config
     * @param ProductFactory $productFactory
     * @param ProductRepositoryInterface $productRepository
     * @param ProductResource $productResource
     * @param AttributeSetCollection $attrSetCollection
     * @param FormatInterface $localeFormat
     * @param StoreManagerInterface $storeManager
     * @param ProductType $productType
     * @param array $data
     * @param ProductCategoryList|null $categoryList
     */
    public function __construct(
        Context                    $context,
        BackendData                $backendData,
        EavConfig                  $config,
        ProductFactory             $productFactory,
        ProductRepositoryInterface $productRepository,
        ProductResource            $productResource,
        AttributeSetCollection     $attrSetCollection,
        FormatInterface            $localeFormat,
        StoreManagerInterface      $storeManager,
        ProductType                $productType,
        array                      $data = [],
        ?ProductCategoryList       $categoryList = null
    )
    {
        $this->storeManager = $storeManager;
        $this->productType = $productType;
        parent::__construct(
            $context,
            $backendData,
            $config,
            $productFactory,
            $productRepository,
            $productResource,
            $attrSetCollection,
            $localeFormat,
            $data,
            $categoryList
        );
    }

    /**
     * Every attribute carrying a frontend label is selectable, not only the ones flagged for promo rules
     *
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $attributes = [];

        /** @var Attribute $attribute */
        foreach ($this->_productResource->loadAllAttributes()->getAttributesByCode() as $attribute) {
            if (!$attribute->getFrontendLabel()) {
                continue;
            }
            $attributes[$attribute->getAttributeCode()] = $attribute->getFrontendLabel();
        }

        $this->_addSpecialAttributes($attributes);

        asort($attributes);
        $this->setAttributeOption($attributes);

        return $this;
    }

    /**
     * @param array $attributes
     * @return void
     */
    protected function _addSpecialAttributes(array &$attributes)
    {
        parent::_addSpecialAttributes($attributes);
        $attributes['sku'] = __('SKU');
        $attributes['type_id'] = __('Product Type');
    }

    /**
     * @return string
     */
    public function getInputType()
    {
        if ($this->getAttribute() === 'type_id') {
            return 'select';
        }

        return parent::getInputType();
    }

    /**
     * @return string
     */
    public function getValueElementType()
    {
        if ($this->getAttribute() === 'type_id') {
            return 'select';
        }

        return parent::getValueElementType();
    }

    /**
     * @return $this
     */
    protected function _prepareValueOptions()
    {
        if ($this->getAttribute() !== 'type_id') {
            return parent::_prepareValueOptions();
        }

        $selectOptions = [];
        foreach ($this->productType->getOptionArray() as $value => $label) {
            $selectOptions[] = ['value' => $value, 'label' => $label];
        }

        $this->_setSelectOptions(
            $selectOptions,
            $this->getData('value_select_options'),
            $this->getData('value_option')
        );

        return $this;
    }

    /**
     * Join everything the condition needs to be evaluated in SQL
     *
     * @param Collection $collection
     * @return $this
     */
    public function addToCollection($collection)
    {
        $attributeCode = $this->getAttribute();

        if (in_array($attributeCode, self::SPECIAL_ATTRIBUTES, true)) {
            return $this;
        }

        $attribute = $this->getAttributeObject();
        if (!$attribute->getAttributeCode()) {
            return $this;
        }

        if ($attributeCode === 'price' && $collection->getLimitationFilters()->isUsingPriceIndex()) {
            $this->joinedAttributes['price'] = 'price_index.min_price';

            return $this;
        }

        if ($collection->isEnabledFlat()) {
            $this->addFlatAttribute($attribute, $collection);

            return $this;
        }

        if (!$attribute->isStatic()) {
            if ($attribute->getBackend() && $attribute->isScopeGlobal()) {
                $this->addGlobalAttribute($attribute, $collection);
            } else {
                $this->addNotGlobalAttribute($attribute, $collection);
            }
        }

        return $this;
    }

    /**
     * @param Collection $productCollection
     * @return $this
     */
    public function collectValidatedAttributes($productCollection)
    {
        return $this->addToCollection($productCollection);
    }

    /**
     * @return string
     */
    public function getMappedSqlField()
    {
        $attributeCode = $this->getAttribute();

        if ($attributeCode === 'type_id') {
            return 'e.type_id';
        }

        if (in_array($attributeCode, ['sku', 'attribute_set_id', 'category_ids'], true)) {
            return parent::getMappedSqlField();
        }

        if (isset($this->joinedAttributes[$attributeCode])) {
            return $this->joinedAttributes[$attributeCode];
        }

        if ($this->getAttributeObject()->isStatic()) {
            return $this->getAttributeObject()->getAttributeCode();
        }

        if ($this->getValueParsed()) {
            return 'e.entity_id';
        }

        return '';
    }

    /**
     * @return array|float|int|mixed|string|\Zend_Db_Expr
     */
    public function getBindArgumentValue()
    {
        $value = parent::getBindArgumentValue();

        if (is_array($value) && $this->getMappedSqlField() === 'e.entity_id') {
            return new \Zend_Db_Expr(
                $this->_productResource->getConnection()->quoteInto('?', $value, \Zend_Db::INT_TYPE)
            );
        }

        return $value;
    }

    /**
     * @param Attribute $attribute
     * @param Collection $collection
     * @return void
     */
    private function addFlatAttribute(Attribute $attribute, Collection $collection): void
    {
        $attributeCode = $attribute->getAttributeCode();

        if ($attribute->isEnabledInFlat()) {
            $alias = array_keys($collection->getSelect()->getPart(Select::FROM))[0];
            $this->joinedAttributes[$attributeCode] = $alias . '.' . $attributeCode;

            return;
        }

        $alias = 'at_' . $attributeCode;
        if (!array_key_exists($alias, $collection->getSelect()->getPart(Select::FROM))) {
            $collection->joinAttribute($attributeCode, "catalog_product/$attributeCode", 'entity_id');
        }

        $this->joinedAttributes[$attributeCode] = $alias . '.value';
    }

    /**
     * @param Attribute $attribute
     * @param Collection $collection
     * @return void
     */
    private function addGlobalAttribute(Attribute $attribute, Collection $collection): void
    {
        switch ($attribute->getBackendType()) {
            case 'decimal':
            case 'datetime':
            case 'int':
                $alias = 'at_' . $attribute->getAttributeCode();
                $collection->addAttributeToSelect($attribute->getAttributeCode(), 'inner');
                break;
            default:
                $alias = 'at_' . sha1((string)$this->getId()) . $attribute->getAttributeCode();

                $connection = $this->_productResource->getConnection();
                $storeId = $connection->getIfNullSql($alias . '.store_id', $this->storeManager->getStore()->getId());
                $linkField = $attribute->getEntity()->getLinkField();

                $collection->getSelect()->join(
                    [$alias => $collection->getTable($attribute->getBackendTable())],
                    "($alias.$linkField = e.$linkField) AND ($alias.store_id = $storeId)"
                    . " AND ($alias.attribute_id = {$attribute->getId()})",
                    []
                );
        }

        $this->joinedAttributes[$attribute->getAttributeCode()] = $alias . '.value';
    }

    /**
     * @param Attribute $attribute
     * @param Collection $collection
     * @return void
     */
    private function addNotGlobalAttribute(Attribute $attribute, Collection $collection): void
    {
        $connection = $this->_productResource->getConnection();

        switch ($attribute->getBackendType()) {
            case 'decimal':
            case 'datetime':
            case 'int':
            case 'varchar':
            case 'text':
                $aliasDefault = 'at_' . $attribute->getAttributeCode() . '_default';
                $aliasStore = 'at_' . $attribute->getAttributeCode();
                $collection->addAttributeToSelect($attribute->getAttributeCode(), 'left');
                break;
            default:
                $aliasDefault = 'at_' . sha1((string)$this->getId()) . $attribute->getAttributeCode() . '_default';
                $aliasStore = 'at_' . sha1((string)$this->getId()) . $attribute->getAttributeCode();

                $storeDefaultId = $connection->getIfNullSql($aliasDefault . '.store_id', Store::DEFAULT_STORE_ID);
                $storeId = $connection->getIfNullSql(
                    $aliasStore . '.store_id',
                    $this->storeManager->getStore()->getId()
                );
                $linkField = $attribute->getEntity()->getLinkField();

                $collection->getSelect()->joinLeft(
                    [$aliasDefault => $collection->getTable($attribute->getBackendTable())],
                    "($aliasDefault.$linkField = e.$linkField) AND ($aliasDefault.store_id = $storeDefaultId)"
                    . " AND ($aliasDefault.attribute_id = {$attribute->getId()})",
                    []
                );
                $collection->getSelect()->joinLeft(
                    [$aliasStore => $collection->getTable($attribute->getBackendTable())],
                    "($aliasStore.$linkField = e.$linkField) AND ($aliasStore.store_id = $storeId)"
                    . " AND ($aliasStore.attribute_id = {$attribute->getId()})",
                    []
                );
        }

        $fromPart = $collection->getSelect()->getPart(Select::FROM);

        if (isset($fromPart[$aliasStore]['joinType'], $fromPart[$aliasDefault]['joinType'])) {
            $joinedAttribute = $connection->getCheckSql(
                $connection->quoteIdentifier($aliasStore . '.value_id') . ' > 0',
                $connection->quoteIdentifier($aliasStore . '.value'),
                $connection->quoteIdentifier($aliasDefault . '.value')
            );
        } else {
            $joinedAttribute = $aliasStore . '.value';
        }

        $this->joinedAttributes[$attribute->getAttributeCode()] = $joinedAttribute;
    }
}
