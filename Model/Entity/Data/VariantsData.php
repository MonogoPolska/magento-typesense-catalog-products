<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity\Data;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\Attributes\Collection as AttributeCollection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as Type;
use Magento\ConfigurableProductGraphQl\Model\Options\Collection as OptionCollection;
use Magento\ConfigurableProductGraphQl\Model\Variant\Collection as VariantCollection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\GraphQl\Model\Query\ContextInterface;

class VariantsData
{
    const OPTION_TYPE = 'configurable';

    /**
     * @var VariantCollection
     */
    private VariantCollection $variantCollection;

    /**
     * @var OptionCollection
     */
    private OptionCollection $optionCollection;

    /**
     * @var AttributeCollection
     */
    private AttributeCollection $attributeCollection;

    /**
     * @var MetadataPool
     */
    private MetadataPool $metadataPool;

    /**
     * @var ContextInterface
     */
    private ContextInterface $context;

    /**
     * @var string
     */
    private string $linkField = "";

    /**
     * @param VariantCollection $variantCollection
     * @param OptionCollection $optionCollection
     * @param AttributeCollection $attributeCollection
     * @param MetadataPool $metadataPool
     * @param ContextInterface $context
     */
    public function __construct(
        VariantCollection   $variantCollection,
        OptionCollection    $optionCollection,
        AttributeCollection $attributeCollection,
        MetadataPool        $metadataPool,
        ContextInterface    $context
    )
    {
        $this->variantCollection = $variantCollection;
        $this->optionCollection = $optionCollection;
        $this->attributeCollection = $attributeCollection;
        $this->metadataPool = $metadataPool;
        $this->context = $context;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getLinkField(): string
    {
        if (empty($this->linkField)) {
            $this->linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        }
        return $this->linkField;
    }

    /**
     * @param Collection $collection
     * @return void
     * @throws \Exception
     */
    public function setProducts(Collection $collection): void
    {
        foreach ($collection as $product) {
            if ($product->getTypeId() == Type::TYPE_CODE && !empty($product->getData($this->getLinkField()))) {
                $this->variantCollection->addParentProduct($product);
            }
        }
    }

    /**
     * @param Product $product
     * @return array
     * @throws \Exception
     */
    public function getVariants(Product $product):array
    {
        if ($product->getTypeId() !== Type::TYPE_CODE || empty($product->getData($this->getLinkField()))) {
            return [];
        }
        $attributes = $this->getConfigurableAttributeCodes($product);
        $this->variantCollection->addEavAttributes($attributes);
        $this->optionCollection->addProductId((int)$product->getData($this->getLinkField()));
        $this->context->getExtensionAttributes()->setStore($product->getStore());
        $children = $this->variantCollection->getChildProductsByParentId((int)$product->getData($this->getLinkField()), $this->context);

        $options = $this->optionCollection->getAttributesByProductId((int)$product->getData($this->getLinkField()));

        $variants = [];
        /** @var Product $child */
        foreach ($children as $key => $child) {
            $variants[$key] = [
                'child_id' => $child['model']->getId(),
                'attributes' => $this->getAttributes($child['model'], $options)
            ];
        }

        return $variants;
    }

    /**
     * @param Product $product
     * @param array $options
     * @return array
     */
    public function getAttributes(Product $product, array $options): array
    {
        $data = [];
        foreach ($options as $optionId => $option) {

            if (!isset($option['attribute_code'])) {
                continue;
            }
            $code = $option['attribute_code'];
            if (!$product || !$product->getData($code)) {
                continue;
            }

            if (isset($option['options_map'])) {
                $optionsFromMap = $this->getOptionsFromMap(
                    $option['options_map'] ?? [],
                    $code,
                    (int)$optionId,
                    (int)$product->getData($code),
                    (int)$option['attribute_id']
                );
                if (!empty($optionsFromMap)) {
                    $data[] = $optionsFromMap;
                }
            }
        }
        return $data;
    }

    /**
     * Get options by index mapping
     *
     * @param array $optionMap
     * @param string $code
     * @param int $optionId
     * @param int $attributeCodeId
     * @param int $attributeId
     * @return array
     */
    private function getOptionsFromMap(
        array  $optionMap,
        string $code,
        int    $optionId,
        int    $attributeCodeId,
        int    $attributeId
    ): array
    {
        $data = [];
        if (isset($optionMap[$optionId . ':' . $attributeCodeId])) {
            $optionValue = $optionMap[$optionId . ':' . $attributeCodeId];
            $data = $this->getOptionsArray($optionValue, $code, $attributeId);
        }
        return $data;
    }

    /**
     * Get options formatted as array
     *
     * @param array $optionValue
     * @param string $code
     * @param int $attributeId
     * @return array
     */
    private function getOptionsArray(array $optionValue, string $code, int $attributeId): array
    {
        return [
            'label' => $optionValue['label'] ?? null,
            'code' => $code,
            'use_default_value' => $optionValue['use_default_value'] ?? null,
            'value_index' => $optionValue['value_index'] ?? null,
            'attribute_id' => $attributeId,
            'uid' => $this->getAttributeUuid($optionValue),
        ];
    }

    /**
     * @param Product $product
     * @return array
     */
    public function getConfigurableAttributeCodes(Product $product): array
    {
        $options = [];
        $data = $product->getTypeInstance()->getConfigurableOptions($product);

        foreach ($data as $attr) {
            foreach ($attr as $option) {
                $options[$option['attribute_code']] = $option['attribute_code'];
            }
        }
        return $options;
    }

    /**
     * @param $value
     * @return string
     */
    public function getAttributeUuid($value): string
    {
        $optionDetails = [
            self::OPTION_TYPE,
            $value['attribute_id'] ?? 0,
            $value['value_index']
        ];
        $content = implode('/', $optionDetails);
        return base64_encode($content);
    }
}
