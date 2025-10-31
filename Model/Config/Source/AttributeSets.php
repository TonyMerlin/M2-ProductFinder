<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Model\Config\Source;

use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Api\Data\AttributeSetInterface;
use Magento\Framework\Data\OptionSourceInterface;

class AttributeSets implements OptionSourceInterface
{
    private $attributeSetRepository;

    public function __construct(
        AttributeSetRepositoryInterface $attributeSetRepository
    ) {
        $this->attributeSetRepository = $attributeSetRepository;
    }

    public function toOptionArray()
    {
        $options = [];
        try {
            // product entity type id = 4 on most installs, but let Magento handle it via repo
            $searchCriteria = null; // repo is okay with empty, or inject SearchCriteriaBuilder if needed
            $sets = $this->attributeSetRepository->getList(\Magento\Catalog\Model\Product::ENTITY, $searchCriteria);
            foreach ($sets->getItems() as $set) {
                /** @var AttributeSetInterface $set */
                $options[] = [
                    'value' => $set->getAttributeSetId(),
                    'label' => $set->getAttributeSetName()
                ];
            }
        } catch (\Throwable $e) {
            // swallow – just return empty
        }
        return $options;
    }
}
