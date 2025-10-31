<?php
namespace Merlin\ProductFinder\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Option\ArrayInterface;

class TopCategories implements ArrayInterface
{
    private CollectionFactory $factory;
    public function __construct(CollectionFactory $factory) { $this->factory = $factory; }

    public function toOptionArray()
    {
        $collection = $this->factory->create();
        $collection->addAttributeToSelect('name')->addIsActiveFilter();
        $out = [];
        foreach ($collection as $cat) {
            $out[] = ['value' => $cat->getId(), 'label' => $cat->getName() . ' (#'.$cat->getId().')'];
        }
        return $out;
    }
}
