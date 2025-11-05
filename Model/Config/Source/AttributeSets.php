<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Model\Config\Source;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class AttributeSets implements OptionSourceInterface
{
    private AttributeSetCollectionFactory $setCollectionFactory;
    private EavConfig $eavConfig;

    public function __construct(
        AttributeSetCollectionFactory $setCollectionFactory,
        EavConfig $eavConfig
    ) {
        $this->setCollectionFactory = $setCollectionFactory;
        $this->eavConfig = $eavConfig;
    }

    public function toOptionArray(): array
    {
        $options = [];

        try {
            // 1) get numeric entity type id for catalog_product
            $productEntityType = $this->eavConfig->getEntityType(Product::ENTITY);
            $productEntityTypeId = (int)$productEntityType->getEntityTypeId();

            // 2) load attribute sets for that entity type id
            $collection = $this->setCollectionFactory->create();
            $collection->setEntityTypeFilter($productEntityTypeId);
            $collection->setOrder('attribute_set_name', 'ASC');

            foreach ($collection as $set) {
                $options[] = [
                    'value' => (int)$set->getAttributeSetId(),
                    'label' => $set->getAttributeSetName(),
                ];
            }
        } catch (\Throwable $e) {
            // don't break config page
            return [];
        }

        return $options;
    }
}
