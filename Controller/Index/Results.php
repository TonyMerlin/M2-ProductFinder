<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Controller\Index;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Merlin\ProductFinder\Helper\Data as Helper;

class Results extends Action
{
    private PageFactory $pageFactory;
    private ProductCollectionFactory $collectionFactory;
    private Helper $helper;
    private Registry $registry;
    private EavConfig $eavConfig;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        ProductCollectionFactory $collectionFactory,
        Helper $helper,
        Registry $registry,
        EavConfig $eavConfig,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->pageFactory        = $pageFactory;
        $this->collectionFactory  = $collectionFactory;
        $this->helper             = $helper;
        $this->registry           = $registry;
        $this->eavConfig          = $eavConfig;
        $this->storeManager       = $storeManager;
    }

    public function execute()
    {
        if (!$this->helper->isEnabled()) {
            return $this->_redirect('/');
        }

        $params = $this->getRequest()->getParams();

        // 1) get all profiles from config
        $profiles  = $this->helper->getAttributeSetProfiles();
        $attrSetId = isset($params['attribute_set_id']) ? (int)$params['attribute_set_id'] : 0;

        // 2) base collection
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect([
            'name',
            'price',
            'special_price',
            'special_from_date',
            'special_to_date',
            'small_image'
        ]);
        $collection->addAttributeToFilter('status', 1);
        $collection->addAttributeToFilter('visibility', ['in' => [2, 3, 4]]);

        // 3) If attribute set chosen, filter to it
        if ($attrSetId > 0) {
            $collection->addAttributeToFilter('attribute_set_id', $attrSetId);
        }

    $applyAttr = function (string $code, $values) use ($collection) {
     if (!$code || $values === null || $values === '' || $values === []) {
        return;
    }

    $attr = $this->eavConfig->getAttribute('catalog_product', $code);
    if (!$attr || !$attr->getId()) {
        return; // invalid attribute code ? skip
    }

    // normalise to array
    $values = is_array($values) ? $values : [$values];

    // remove empties
    $values = array_values(array_filter($values, static function ($v) {
        return $v !== '' && $v !== null;
    }));
    if (!$values) {
        return;
    }

    // Detect multiselect and choose the correct condition
    $frontendInput = (string)$attr->getFrontendInput();
    if ($frontendInput === 'multiselect') {
        // multiselect is stored as comma-separated values, so we must use finset
        $condition = ['finset' => $values];
    } else {
        // normal single-select / dropdown / int attributes
        $condition = ['in' => $values];
    }

    $collection->addAttributeToFilter($code, $condition);
};

        // 4) Profile-based filtering
        if ($attrSetId && isset($profiles[$attrSetId]) && is_array($profiles[$attrSetId])) {
            $profile  = $profiles[$attrSetId];
            $sections = $profile['sections'] ?? [];
            $map      = $profile['map'] ?? [];
            $extras   = $profile['extras'] ?? [];

            // --- MAPPED FIELDS (except price/extras) ---
            foreach ($sections as $logical) {
                if ($logical === 'price' || $logical === 'extras') {
                    continue;
                }

                $realCode = $map[$logical] ?? $logical;

                if (isset($params[$logical])) {
                    $applyAttr($realCode, $params[$logical]);
                }
            }

            // --- EXTRAS ---
            if (!empty($extras) && is_array($extras)) {
                foreach ($extras as $key => $realCode) {
                    if (isset($params[$key])) {
                        $applyAttr((string)$realCode, $params[$key]);
                    }
                }
            }

            // NOTE: price slider handled *after* this block via final_price index
        } else {
            // 5) fallback to OLD behaviour (global mapping) if no profile
            // Product type
            $productTypeAttr = (string)$this->helper->getConfig('mapping/product_type');
            if (!empty($params['product_type'])) {
                $applyAttr($productTypeAttr, (array)$params['product_type']);
            }
            // Colour
            $colorAttr = (string)$this->helper->getConfig('mapping/color');
            if (!empty($params['color'])) {
                $applyAttr($colorAttr, (array)$params['color']);
            }
            // Extras
            $extrasMap = $this->helper->getExtrasMap();
            foreach ($extrasMap as $key => $attrCode) {
                if (!empty($params[$key])) {
                    $applyAttr((string)$attrCode, (array)$params[$key]);
                }
            }
            // NOTE: price slider handled globally below
        }

        /**
         * 5b) PRICE SLIDER (global, special-price aware via final_price)
         *
         * We no longer filter on the raw "price" attribute.
         * Instead, we join catalog_product_index_price and use final_price,
         * which already includes special_price and catalog rules.
         */
        $min = $params['price_min'] ?? null;
        $max = $params['price_max'] ?? null;

        if ($min !== null || $max !== null) {
            $min = ($min !== '' ? (float)$min : null);
            $max = ($max !== '' ? (float)$max : null);

            if ($min !== null || $max !== null) {
                $store     = $this->storeManager->getStore();
                $websiteId = (int)$store->getWebsiteId();
                $customerGroupId = 0; // adjust if you support customer groups

                $priceIndexTable = $collection->getTable('catalog_product_index_price');
                $alias           = 'mpf_price_idx';

                // Join price index if not already joined
                $select = $collection->getSelect();
                $from   = $select->getPart(\Zend_Db_Select::FROM);
                if (!isset($from[$alias])) {
                    $select->joinLeft(
                        [$alias => $priceIndexTable],
                        $alias . '.entity_id = e.entity_id'
                        . ' AND ' . $alias . '.website_id = ' . (int)$websiteId
                        . ' AND ' . $alias . '.customer_group_id = ' . (int)$customerGroupId,
                        []
                    );
                }

                if ($min !== null) {
                    $select->where($alias . '.final_price >= ?', $min);
                }
                if ($max !== null) {
                    $select->where($alias . '.final_price <= ?', $max);
                }
            }
        }

        // 6) Sorting + pagination
        $order = $params['order'] ?? 'name';
        $dir   = strtoupper($params['dir'] ?? 'ASC');
        $allowedOrders = ['name', 'price', 'created_at'];
        if (!in_array($order, $allowedOrders, true)) {
            $order = 'name';
        }
        $dir = in_array($dir, ['ASC', 'DESC'], true) ? $dir : 'ASC';
        $collection->setOrder($order, $dir);

        $p     = max(1, (int)($params['p'] ?? 1));
        $limit = (int)($params['limit'] ?? 12);
        $allowedLimits = [12, 24, 48];
        if (!in_array($limit, $allowedLimits, true)) {
            $limit = 12;
        }
        $collection->setCurPage($p)->setPageSize($limit);

        // 7) register for the block/template
        $this->registry->register('merlin_productfinder_collection', $collection);
        $this->registry->register('merlin_productfinder_params', [
            'order' => $order,
            'dir'   => $dir,
            'p'     => $p,
            'limit' => $limit
        ]);

        // 8) render page
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Finder Results'));
        return $page;
    }
}
