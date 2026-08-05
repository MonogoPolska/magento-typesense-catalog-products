<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Rule\Condition;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Model\Stock as LegacyStock;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\AbstractModel;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

/**
 * Stock condition, needed because stock data lives outside of EAV and is not reachable by product attribute conditions
 */
class Stock extends AbstractCondition
{
    use ElementNameTrait;

    /**
     * Shared table alias, a single join serves every stock condition in the tree
     */
    const TABLE_ALIAS = 'typesense_filter_stock';

    const ATTRIBUTE_STOCK_STATUS = 'stock_status';

    const ATTRIBUTE_QTY = 'qty';

    /**
     * @var string
     */
    protected $elementName = 'rule';

    /**
     * @var StockConfigurationInterface
     */
    private StockConfigurationInterface $stockConfiguration;

    /**
     * @param Context $context
     * @param StockConfigurationInterface $stockConfiguration
     * @param array $data
     */
    public function __construct(
        Context                     $context,
        StockConfigurationInterface $stockConfiguration,
        array                       $data = []
    )
    {
        $this->stockConfiguration = $stockConfiguration;
        parent::__construct($context, $data);
        $this->setType(self::class);
    }

    /**
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $this->setAttributeOption([
            self::ATTRIBUTE_STOCK_STATUS => __('Stock Status'),
            self::ATTRIBUTE_QTY => __('Quantity'),
        ]);

        return $this;
    }

    /**
     * The attribute is picked from the "add condition" dropdown and stays fixed afterwards
     *
     * @return \Magento\Framework\Data\Form\Element\AbstractElement
     */
    public function getAttributeElement()
    {
        $element = parent::getAttributeElement();
        $element->setShowAsText(true);

        return $element;
    }

    /**
     * @return string
     */
    public function getInputType()
    {
        return $this->getAttribute() === self::ATTRIBUTE_QTY ? 'numeric' : 'select';
    }

    /**
     * @return string
     */
    public function getValueElementType()
    {
        return $this->getAttribute() === self::ATTRIBUTE_QTY ? 'text' : 'select';
    }

    /**
     * @return array
     */
    public function getValueSelectOptions()
    {
        if ($this->getAttribute() === self::ATTRIBUTE_QTY) {
            return [];
        }

        return [
            ['value' => LegacyStock::STOCK_IN_STOCK, 'label' => __('In Stock')],
            ['value' => LegacyStock::STOCK_OUT_OF_STOCK, 'label' => __('Out of Stock')],
        ];
    }

    /**
     * @param string|null $option
     * @return array|string|null
     */
    public function getValueOption($option = null)
    {
        $options = [];
        foreach ($this->getValueSelectOptions() as $selectOption) {
            $options[$selectOption['value']] = $selectOption['label'];
        }

        if ($option === null) {
            return $options;
        }

        return $options[$option] ?? null;
    }

    /**
     * @param Collection $productCollection
     * @return $this
     */
    public function collectValidatedAttributes($productCollection)
    {
        $select = $productCollection->getSelect();

        if (array_key_exists(self::TABLE_ALIAS, (array)$select->getPart(Select::FROM))) {
            return $this;
        }

        $select->joinLeft(
            [self::TABLE_ALIAS => $productCollection->getTable('cataloginventory_stock_status')],
            sprintf(
                '%1$s.product_id = e.entity_id AND %1$s.website_id = %2$d AND %1$s.stock_id = %3$d',
                self::TABLE_ALIAS,
                (int)$this->stockConfiguration->getDefaultScopeId(),
                LegacyStock::DEFAULT_STOCK_ID
            ),
            []
        );

        return $this;
    }

    /**
     * @return string
     */
    public function getMappedSqlField()
    {
        if ($this->getAttribute() === self::ATTRIBUTE_QTY) {
            return self::TABLE_ALIAS . '.qty';
        }

        return self::TABLE_ALIAS . '.stock_status';
    }

    /**
     * @param AbstractModel $model
     * @return bool
     */
    public function validate(AbstractModel $model)
    {
        $stock = $model->getData('stock');

        if ($this->getAttribute() === self::ATTRIBUTE_QTY) {
            return $this->validateAttribute(is_array($stock) ? ($stock['qty'] ?? 0) : 0);
        }

        if (is_array($stock) && isset($stock['stock_status'])) {
            return $this->validateAttribute(
                $stock['stock_status'] === 'IN_STOCK'
                    ? LegacyStock::STOCK_IN_STOCK
                    : LegacyStock::STOCK_OUT_OF_STOCK
            );
        }

        if (!method_exists($model, 'isSalable')) {
            return false;
        }

        return $this->validateAttribute(
            $model->isSalable() ? LegacyStock::STOCK_IN_STOCK : LegacyStock::STOCK_OUT_OF_STOCK
        );
    }
}
