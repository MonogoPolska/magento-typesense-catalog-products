<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as AttributeResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Exception\LocalizedException;

class AttributeData
{
    /**
     * @var string[]
     */
    protected array $attributesToIndexAsArray = [
        'sku',
    ];

    /**
     * @param ProductCollection $products
     * @return void
     */
    public function addMandatoryAttributes(ProductCollection $products): void
    {
        $products->addFinalPrice()
            ->addMinimalPrice()
            ->addAttributeToSelect('*')
            ->addAttributeToSelect('special_price')
            ->addAttributeToSelect('special_from_date')
            ->addAttributeToSelect('special_to_date')
            ->addAttributeToSelect('visibility')
            ->addAttributeToSelect('status');
    }

    /**
     * @param array $productData
     * @param array $additionalAttributes
     * @param Product $product
     * @param array $subProducts
     * @return array
     * @throws LocalizedException
     */
    public function addAdditionalAttributes(
        array   &$productData,
        array   $additionalAttributes,
        Product $product,
        array   $subProducts
    ): array
    {
        $productDataTmp = [];
        foreach ($additionalAttributes as $attribute) {
            $attributeName = $attribute['name'];
            if (isset($productData[$attributeName])) {
                continue;
            }
            /** @var \Magento\Catalog\Model\ResourceModel\Product $resource */
            $resource = $product->getResource();

            /** @var AttributeResource $attributeResource */
            $attributeResource = $resource->getAttribute($attributeName);
            if (!$attributeResource) {
                continue;
            }

            $attributeResource = $attributeResource->setData('store_id', $product->getStoreId());

            $value = $product->getData($attributeName);

            if ($value !== null) {
                $productDataTmp = $this->addNonNullValue(
                    $productData,
                    $value,
                    $product,
                    $attribute,
                    $attributeResource
                );
                if (!in_array($attributeName, $this->attributesToIndexAsArray, true)) {
                    continue;
                }
            }

            $type = $product->getTypeId();
            if ($type !== 'configurable' && $type !== 'grouped' && $type !== 'bundle') {
                continue;
            }

            $productDataTmp = $this->addNullValue(
                $productData,
                $product,
                $subProducts,
                $attribute,
                $attributeResource
            );

            $this->setDefaultValue($productDataTmp, $attribute);
        }
        $productData = array_merge($productData, $productDataTmp);

        return $productData;
    }

    /**
     * @param array $productDataTmp
     * @param array $attribute
     * @return void
     */
    public function setDefaultValue(array &$productDataTmp, array $attribute): void
    {
        if (!$attribute['optional'] && empty($productDataTmp[$attribute['name']])) {
            if (str_contains($attribute['type'], '[]')) {
                $productDataTmp[$attribute['name']] = [];
            } elseif (str_contains($attribute['type'], 'int')) {
                $productDataTmp[$attribute['name']] = 0;
            } elseif (str_contains($attribute['type'], 'float')) {
                $productDataTmp[$attribute['name']] = 0;
            } elseif (str_contains($attribute['type'], 'bool')) {
                $productDataTmp[$attribute['name']] = false;
            } elseif (str_contains($attribute['type'], 'string')) {
                $productDataTmp[$attribute['name']] = '';
            }
        }
    }

    /**
     * @param array $productData
     * @param mixed $value
     * @param Product $product
     * @param array $attribute
     * @param AttributeResource $attributeResource
     * @return array
     */
    protected function addNonNullValue(
        array             &$productData,
        mixed             $value,
        Product           $product,
        array             $attribute,
        AttributeResource $attributeResource
    ): array
    {
        $indexAsArray = str_contains($attribute['type'], '[]');
        $valueText = null;

        $productData[$attribute['name'].'_label'] = $attributeResource->getStoreLabel($product->getStoreId());
        $productData[$attribute['name'].'_position'] = $attributeResource->getPosition();

        if ($indexAsArray) {
            $productData[$attribute['name'] . '_raw'] = [];
        } else {
            $productData[$attribute['name'] . '_raw'] = '';
        }

        if (!is_array($value) && $attributeResource->usesSource()) {
            $valueText = $product->getAttributeText($attribute['name']);
        }

        if ($valueText) {
            $value = $valueText;
        } else {
            $attributeResource = $attributeResource->setData('store_id', $product->getStoreId());
            $value = $attributeResource->getFrontend()->getValue($product);
        }
        if ($indexAsArray) {
            $productData[$attribute['name'] . '_raw'][] = $product->getData($attribute['name']);
        } else {
            $productData[$attribute['name'] . '_raw'] = $product->getData($attribute['name']);
        }

        if ($indexAsArray) {
            if ($value) {
                if (is_array($value)) {
                    $productData[$attribute['name']] = $value;
                } else {
                    $productData[$attribute['name']][] = $value;

                }
            }
        } else {
            if ($value) {
                $productData[$attribute['name']] = $value;
            } else {
                $productData[$attribute['name']] = '';
            }
        }


        return $productData;
    }

    /**
     * @param array $productData
     * @param Product $product
     * @param array $subProducts
     * @param array $attribute
     * @param AttributeResource $attributeResource
     * @return array
     */
    protected function addNullValue(
        array             &$productData,
        Product           $product,
        array             $subProducts,
        array             $attribute,
        AttributeResource $attributeResource
    ): array
    {
        $attributeName = $attribute['name'];

        $productData[$attribute['name'].'_label'] = $attributeResource->getStoreLabel($product->getStoreId());
        $productData[$attribute['name'].'_position'] = $attributeResource->getPosition();

        $values = [];
        $productData[$attribute['name'] . '_raw'] = [];
        if (isset($productData[$attributeName])) {
            $originalValue = $productData[$attributeName];
            if (is_array($originalValue)) {
                $originalValue = reset($productData[$attributeName]);
            }
            $values[] = $originalValue;
        }

        /** @var Product $subProduct */
        foreach ($subProducts as $subProduct) {
            $value = $subProduct->getData($attributeName);
            if ($value) {

                $productData[$attribute['name'] . '_raw'][] = $value;

                /** @var string|array $valueText */
                $valueText = $subProduct->getAttributeText($attributeName);

                $values = array_merge($values, $this->getValues($valueText, $subProduct, $attributeResource));
            }
        }

        if (is_array($values) && !empty($values)) {
            $productData[$attributeName] = array_values(array_unique($values));
        } else {
            $productData[$attributeName] = [];
        }
        $productData[$attribute['name'] . '_raw'] = array_values(array_unique($productData[$attribute['name'] . '_raw']));

        return $productData;
    }

    /**
     * @param mixed $valueText
     * @param Product $subProduct
     * @param AttributeResource $attributeResource
     * @return mixed
     */
    protected function getValues(mixed $valueText, Product $subProduct, AttributeResource $attributeResource): array
    {
        $values = [];

        if ($valueText) {
            if (is_array($valueText)) {
                foreach ($valueText as $valueText_elt) {
                    $values[] = $valueText_elt;
                }
            } else {
                $values[] = $valueText;
            }
        } else {
            $values[] = $attributeResource->getFrontend()->getValue($subProduct);
        }

        return $values;
    }
}
