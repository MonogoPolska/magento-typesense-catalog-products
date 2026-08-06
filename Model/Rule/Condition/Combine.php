<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Rule\Condition;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Rule\Model\Condition\Combine as CombineCore;
use Magento\Rule\Model\Condition\Context;

/**
 * Root and nested combination of indexer filter conditions
 */
class Combine extends CombineCore
{
    /**
     * @var string
     */
    protected $elementName = 'rule';

    /**
     * @var ProductFactory
     */
    private ProductFactory $productConditionFactory;

    /**
     * @var StockFactory
     */
    private StockFactory $stockConditionFactory;

    /**
     * @var array
     */
    private array $excludedAttributes;

    /**
     * @param Context $context
     * @param ProductFactory $productConditionFactory
     * @param StockFactory $stockConditionFactory
     * @param array $data
     * @param array $excludedAttributes
     */
    public function __construct(
        Context        $context,
        ProductFactory $productConditionFactory,
        StockFactory   $stockConditionFactory,
        array          $data = [],
        array          $excludedAttributes = []
    )
    {
        $this->productConditionFactory = $productConditionFactory;
        $this->stockConditionFactory = $stockConditionFactory;
        $this->excludedAttributes = $excludedAttributes;
        parent::__construct($context, $data);
        $this->setType(self::class);
    }

    /**
     * The parent constructor stores the condition list under the default 'conditions' key, so every later prefix
     * change has to move the list as well. Without it getConditions() returns null for a freshly prefixed combine.
     *
     * @param string $prefix
     * @return $this
     */
    public function setPrefix($prefix)
    {
        $currentPrefix = (string)$this->getPrefix();
        $conditions = $this->getConditions();

        if ($currentPrefix !== '' && $currentPrefix !== (string)$prefix) {
            $this->unsetData($currentPrefix);
        }

        $this->setData('prefix', $prefix);
        $this->setConditions($conditions);

        return $this;
    }

    /**
     * Core code iterates the result without a null check
     *
     * @return array
     */
    public function getConditions()
    {
        return (array)parent::getConditions();
    }

    /**
     * @param string $elementName
     * @return $this
     */
    public function setElementName(string $elementName): self
    {
        $this->elementName = $elementName;

        foreach ($this->getConditions() as $condition) {
            if (method_exists($condition, 'setElementName')) {
                $condition->setElementName($elementName);
            }
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getElementName(): string
    {
        return $this->elementName;
    }

    /**
     * @param object $condition
     * @return $this
     */
    public function addCondition($condition)
    {
        parent::addCondition($condition);

        if (method_exists($condition, 'setElementName')) {
            $condition->setElementName($this->elementName);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getNewChildSelectOptions()
    {
        $productAttributes = [];
        foreach ($this->productConditionFactory->create()->loadAttributeOptions()->getAttributeOption() as $code => $label) {
            if (in_array($code, $this->excludedAttributes, true)) {
                continue;
            }
            $productAttributes[] = [
                'value' => Product::class . '|' . $code,
                'label' => $label,
            ];
        }

        $stockAttributes = [];
        foreach ($this->stockConditionFactory->create()->loadAttributeOptions()->getAttributeOption() as $code => $label) {
            $stockAttributes[] = [
                'value' => Stock::class . '|' . $code,
                'label' => $label,
            ];
        }

        return array_merge_recursive(
            parent::getNewChildSelectOptions(),
            [
                ['value' => self::class, 'label' => __('Conditions Combination')],
                ['label' => __('Product Attribute'), 'value' => $productAttributes],
                ['label' => __('Stock'), 'value' => $stockAttributes],
            ]
        );
    }

    /**
     * @param Collection $productCollection
     * @return $this
     */
    public function collectValidatedAttributes($productCollection)
    {
        foreach ($this->getConditions() as $condition) {
            $condition->collectValidatedAttributes($productCollection);
        }

        return $this;
    }
}
