<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity\Data;

use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Monogo\TypesenseCatalogCategories\Model\Entity\Data\CategoryData as CatalogCategoryData;
use Monogo\TypesenseCatalogCategories\Services\ConfigService;

class CategoryData
{
    /**
     * @var ConfigService
     */
    protected ConfigService $configService;

    /**
     * @var CatalogCategoryData
     */
    protected CatalogCategoryData $categoryData;

    /**
     * @param ConfigService $configService
     * @param CatalogCategoryData $categoryData
     */
    public function __construct(
        ConfigService       $configService,
        CatalogCategoryData $categoryData,
    )
    {
        $this->configService = $configService;
        $this->categoryData = $categoryData;

    }

    /**
     * @param Product $product
     * @return void
     * @throws LocalizedException
     */
    public function addCategoryData(Product $product): void
    {
        $storeId = $product->getStoreId();
        $categories = [];
        $categoryIds = [];

        $_categoryIds = $product->getCategoryIds();


        if (is_array($_categoryIds) && count($_categoryIds) > 0) {
            $categoryCollection = $this->getAllCategories($_categoryIds, $storeId);

            $rootCat = $this->categoryData->getRootCategoryId();

            foreach ($categoryCollection as $category) {
                $path = $category->getPath();
                $pathParts = explode('/', $path);
                if (!in_array($rootCat, $pathParts)) {
                    continue;
                }

                $categoryName = $this->categoryData->getCategoryName((int)$category->getId(), $storeId);
                if ($categoryName) {
                    $categories[base64_encode((string)$category->getId())] = $categoryName;
                }

                foreach ($category->getPathIds() as $treeCategoryId) {
                    $name = $this->categoryData->getCategoryName((int)$treeCategoryId, $storeId);
                    if ($name) {
                        $categoryIds[] = $treeCategoryId;
                    }
                }
            }
        }

        $product->setData('categories', $categories);
        $product->setData('category_ids', array_values(array_unique($categoryIds)));
    }

    /**
     * @param $arr
     * @return array
     */
    protected function arrayValuesRecursive($arr): array
    {
        $result = [];
        foreach (array_keys($arr) as $k) {
            $v = $arr[$k];

            if (is_scalar($v)) {
                $result[] = $v;
            } elseif (is_array($v)) {
                $result = array_merge($result, $this->arrayValuesRecursive($v));
            }
        }
        return $result;
    }

    /**
     * @param array $categoryIds
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function getAllCategories(array $categoryIds, ?int $storeId): array
    {
        $filterNotIncludedCategories = $this->configService->getFilterIncludeInMenu($storeId);
        $categories = $this->categoryData->getCoreCategories($filterNotIncludedCategories, $storeId);

        $selectedCategories = [];
        foreach ($categoryIds as $id) {
            if (isset($categories[$id])) {
                $selectedCategories[] = $categories[$id];
            }
        }

        return $selectedCategories;
    }
}
