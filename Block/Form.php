<?php
namespace Merlin\ProductFinder\Block;

use Magento\Framework\View\Element\Template;
use Merlin\ProductFinder\Helper\Data;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;

class Form extends Template
{
    protected $_template = 'Merlin_ProductFinder::form.phtml';

    private Data $helper;
    private CategoryCollectionFactory $categoryCollectionFactory;
    private AttributeCollectionFactory $attributeCollectionFactory;

    public function __construct(
        Template\Context $context,
        Data $helper,
        CategoryCollectionFactory $categoryCollectionFactory,
        AttributeCollectionFactory $attributeCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    public function getSections(): array { return $this->helper->getSections(); }
    public function getPreHtml(): string { return (string)$this->helper->getConfig('layout/pre_html'); }
    public function getPostHtml(): string { return (string)$this->helper->getConfig('layout/post_html'); }
    public function getConfig($path) { return $this->helper->getConfig($path); }

    public function getTopCategories(): array
    {
        $ids = $this->helper->getConfig('general/top_categories');
        if (!$ids) return [];
        $ids = array_filter(array_map('intval', explode(',', $ids)));
        if (!$ids) return [];
        $col = $this->categoryCollectionFactory->create();
        $col->addAttributeToSelect('name')->addFieldToFilter('entity_id', ['in' => $ids]);
        $out = [];
        foreach ($col as $cat) { $out[] = ['id' => (int)$cat->getId(), 'name' => $cat->getName()]; }
        return $out;
    }

    public function getAttributeOptions(string $attrCode): array
    {
        $attr = $this->attributeCollectionFactory->create()
            ->addFieldToFilter('attribute_code', $attrCode)
            ->getFirstItem();
        if (!$attr || !$attr->getId()) return [];
        $opts = $attr->getSource()->getAllOptions(false);
        return array_map(fn($o) => ['value' => $o['value'], 'label' => $o['label']], $opts);
    }
}
