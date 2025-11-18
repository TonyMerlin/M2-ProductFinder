<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block\Adminhtml\System\Config\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as ProductAttributeCollectionFactory;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\ScopeInterface;

class Profiles extends Field
{
    /** Config path: multi-select of allowed attribute sets */
    private const XML_PATH_ALLOWED_SETS = 'merlin_productfinder/general/allowed_attribute_sets';

    /** Use the template renderer instead of echoing HTML inline */
    protected $_template = 'Merlin_ProductFinder::system/config/profiles.phtml';

    private AttributeSetCollectionFactory $setCollectionFactory;
    private ProductAttributeCollectionFactory $attributeCollectionFactory;
    private EavConfig $eavConfig;

    public function __construct(
        Context $context,
        AttributeSetCollectionFactory $setCollectionFactory,
        ProductAttributeCollectionFactory $attributeCollectionFactory,
        EavConfig $eavConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setCollectionFactory       = $setCollectionFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->eavConfig                  = $eavConfig;
    }

    /** Magento calls this to render the field; delegate to the template */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->setElement($element);
        return $this->_toHtml();
    }

    /**
     * Attribute sets (sorted A–Z), filtered by
     * “Allowed Attribute Sets for Finder” config if set.
     *
     * @return array<int, array{id:int,name:string}>
     */
    public function getProductAttributeSets(): array
    {
        $entityTypeId = (int)$this->eavConfig
            ->getEntityType(Product::ENTITY)
            ->getEntityTypeId();

        $col = $this->setCollectionFactory->create();
        $col->setEntityTypeFilter($entityTypeId)
            ->setOrder('attribute_set_name', 'ASC');

        // Read allowed sets from config (comma-separated IDs)
        $allowedRaw = (string)$this->_scopeConfig->getValue(
            self::XML_PATH_ALLOWED_SETS,
            ScopeInterface::SCOPE_STORE
        );

        $allowedIds = [];
        if ($allowedRaw !== '') {
            $allowedIds = array_filter(array_map('trim', explode(',', $allowedRaw)), 'strlen');
            // normalise to strings for comparison
            $allowedIds = array_map('strval', $allowedIds);
        }

        $out = [];
        foreach ($col as $set) {
            $id   = (int)$set->getAttributeSetId();
            $name = (string)$set->getAttributeSetName();

            // If config is set, only keep allowed IDs
            if ($allowedIds && !in_array((string)$id, $allowedIds, true)) {
                continue;
            }

            $out[] = [
                'id'   => $id,
                'name' => $name,
            ];
        }

        return $out;
    }

    /** Pass the textarea’s HTML id */
    public function getHtmlId(): string
    {
        return $this->getElement()->getHtmlId();
    }

    public function getName(): string
    {
        return $this->getElement()->getName();
    }

    public function getProfilesJson(): string
    {
        $val = (string)$this->getElement()->getData('value');
        return $val !== '' ? $val : '{}';
    }

    /** URL for image AJAX upload used by profiles.js */
    public function getImageUploadUrl(): string
    {
        return $this->getUrl('merlin_productfinder/upload/image');
    }

    /**
     * Build a map of attribute-set-id => list of attributes for that set.
     * Only includes sets returned by getProductAttributeSets()
     * (so already filtered by “Allowed Attribute Sets”).
     *
     * [
     *   "126": [
     *     {"code":"item_type","label":"Item Type"},
     *     {"code":"colour","label":"Colour"},
     *     ...
     *   ],
     *   ...
     * ]
     */
    public function getAttributeMapJson(): string
    {
        try {
            $result   = [];
            $attrSets = $this->getProductAttributeSets();

            foreach ($attrSets as $set) {
                $setId = (int)($set['id'] ?? 0);
                if ($setId <= 0) {
                    continue;
                }

                $collection = $this->attributeCollectionFactory->create();
                $collection
                    ->setAttributeSetFilter($setId)
                    // visible on storefront (“Visible on Catalog Pages on Storefront”)
                    ->addFieldToFilter('is_visible_on_front', 1);

                $items = [];
                foreach ($collection as $attr) {
                    $code = (string)$attr->getAttributeCode();
                    if ($code === '') {
                        continue;
                    }

                    $label = (string)($attr->getFrontendLabel() ?: $code);

                    $items[] = [
                        'code'  => $code,
                        'label' => $label,
                    ];
                }

                // IMPORTANT: cast key to string so JSON is an object, not an array
                $result[(string)$setId] = $items;
            }

            $json = json_encode($result, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                return '{}';
            }
            return $json;
        } catch (\Throwable $e) {
            return '{}';
        }
    }
}
