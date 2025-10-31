<?php
namespace Merlin\ProductFinder\Block;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Merlin\ProductFinder\Helper\Data;

class Form extends Template
{
    protected $_template = 'Merlin_ProductFinder::form.phtml';

    private Data $helper;
    private CategoryCollectionFactory $categoryCollectionFactory;
    private AttributeCollectionFactory $attributeCollectionFactory;

    /** @var ScopeConfigInterface */
    private ScopeConfigInterface $scopeConfig;

    /** @var AttributeSetRepositoryInterface */
    private AttributeSetRepositoryInterface $attributeSetRepository;

    /** @var AttributeRepositoryInterface */
    private AttributeRepositoryInterface $attributeRepository;

    public function __construct(
        Template\Context $context,
        Data $helper,
        CategoryCollectionFactory $categoryCollectionFactory,
        AttributeCollectionFactory $attributeCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        AttributeSetRepositoryInterface $attributeSetRepository,
        AttributeRepositoryInterface $attributeRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->attributeRepository = $attributeRepository;
    }

    public function getSections(): array
    {
        return $this->helper->getSections();
    }

    public function getPreHtml(): string
    {
        return (string)$this->helper->getConfig('layout/pre_html');
    }

    public function getPostHtml(): string
    {
        return (string)$this->helper->getConfig('layout/post_html');
    }

    public function getConfig($path)
    {
        return $this->helper->getConfig($path);
    }

    /**
     * Legacy method – now that we’re switching to attribute sets this won’t be used
     * on the frontend, but we keep it for BC (maybe you still show categories somewhere).
     */
    public function getTopCategories(): array
    {
        $ids = $this->helper->getConfig('general/top_categories');
        if (!$ids) {
            return [];
        }
        $ids = array_filter(array_map('intval', explode(',', $ids)));
        if (!$ids) {
            return [];
        }
        $col = $this->categoryCollectionFactory->create();
        $col->addAttributeToSelect('name')->addFieldToFilter('entity_id', ['in' => $ids]);
        $out = [];
        foreach ($col as $cat) {
            $out[] = ['id' => (int)$cat->getId(), 'name' => $cat->getName()];
        }
        return $out;
    }

    /**
     * Get options for a given product attribute code.
     */
    public function getAttributeOptions(string $attrCode): array
    {
        $attr = $this->attributeCollectionFactory->create()
            ->addFieldToFilter('attribute_code', $attrCode)
            ->getFirstItem();
        if (!$attr || !$attr->getId()) {
            return [];
        }
        $opts = $attr->getSource()->getAllOptions(false);
        return array_map(static function ($o) {
            return [
                'value' => $o['value'],
                'label' => $o['label'],
            ];
        }, $opts);
    }

    /**
     * Read allowed attribute sets for the finder from system config.
     *
     * Config path used: merlin_productfinder/general/allowed_attribute_sets
     *
     * @return int[]
     */
    public function getAllowedAttributeSetIds(): array
    {
        $val = (string)$this->scopeConfig->getValue(
            'merlin_productfinder/general/allowed_attribute_sets',
            ScopeInterface::SCOPE_STORE
        );
        if (!$val) {
            return [];
        }
        $ids = array_filter(array_map('intval', explode(',', $val)));
        return $ids ?: [];
    }

    /**
     * Returns a map: [setId => [attrCode1, attrCode2, ...], ...]
     * This is used on the frontend to hide fields that are not in the selected set.
     */
    public function getAttributesBySet(): array
    {
        $result  = [];
        $setIds  = $this->getAllowedAttributeSetIds();
        if (!$setIds) {
            return $result;
        }

        foreach ($setIds as $setId) {
            try {
                // get product attributes for this set
                $list = $this->attributeRepository->getList(
                    Product::ENTITY,
                    $setId
                );
                $codes = [];
                foreach ($list->getItems() as $attr) {
                    $codes[] = $attr->getAttributeCode();
                }
                $result[$setId] = $codes;
            } catch (\Throwable $e) {
                $result[$setId] = [];
            }
        }

        return $result;
    }
}
