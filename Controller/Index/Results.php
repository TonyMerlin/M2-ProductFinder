<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Controller\Index;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Merlin\ProductFinder\Helper\Data as Helper;

class Results extends Action
{
    private PageFactory $pageFactory;
    private ProductCollectionFactory $collectionFactory;
    private Helper $helper;
    private Registry $registry;
    private EavConfig $eavConfig;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        ProductCollectionFactory $collectionFactory,
        Helper $helper,
        Registry $registry,
        EavConfig $eavConfig
    ) {
        parent::__construct($context);
        $this->pageFactory        = $pageFactory;
        $this->collectionFactory  = $collectionFactory;
        $this->helper             = $helper;
        $this->registry           = $registry;
        $this->eavConfig          = $eavConfig;
    }

    public function execute()
    {
        if (!$this->helper->isEnabled()) {
            return $this->_redirect('/');
        }

        $params = $this->getRequest()->getParams();

        // 1) get all profiles from config
        // merlin_productfinder/general/attribute_set_profiles
        $profiles = $this->helper->getAttributeSetProfiles(); // we added this earlier to Helper
        $attrSetId = isset($params['attribute_set_id']) ? (int)$params['attribute_set_id'] : 0;

        // 2) base collection
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name', 'price', 'small_image']);
        $collection->addAttributeToFilter('status', 1);
        $collection->addAttributeToFilter('visibility', ['in' => [2, 3, 4]]);

        // 3) If attribute set chosen, filter to it
        if ($attrSetId > 0) {
            $collection->addAttributeToFilter('attribute_set_id', $attrSetId);
        }

        // small helper to safely apply
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
            $collection->addAttributeToFilter($code, ['in' => $values]);
        };

        // 4) If we have a profile for this attribute set, we use that to decide what to filter
        if ($attrSetId && isset($profiles[$attrSetId]) && is_array($profiles[$attrSetId])) {
            $profile  = $profiles[$attrSetId];
            $sections = $profile['sections'] ?? [];
            $map      = $profile['map'] ?? [];
            $extras   = $profile['extras'] ?? [];

            // --- MAPPED FIELDS ---
            // sections might contain "appliance_type","color","price","extras",...
            foreach ($sections as $logical) {
                // skip price & extras here, we handle them below
                if ($logical === 'price' || $logical === 'extras') {
                    continue;
                }

                // real attribute code for this logical field
                $realCode = $map[$logical] ?? $logical;

                // request param will have the logical name, e.g. ?appliance_type[]=...
                if (isset($params[$logical])) {
                    $applyAttr($realCode, $params[$logical]);
                }
            }

            // --- EXTRAS ---
            // extras: { "energy_rating":"energy_rating", "frost_free":"frost_free" }
            if (!empty($extras) && is_array($extras)) {
                foreach ($extras as $key => $realCode) {
                    if (isset($params[$key])) {
                        $applyAttr((string)$realCode, $params[$key]);
                    }
                }
            }

            // --- PRICE ---
            if (in_array('price', $sections, true)) {
                $min = $params['price_min'] ?? null;
                $max = $params['price_max'] ?? null;

                if ($min !== null || $max !== null) {
                    // use global price attr fallback
                    $priceAttr = $this->helper->getConfig('mapping/price_attribute') ?: 'price';
                    $cond = [];
                    if ($min !== null && $min !== '') {
                        $cond['from'] = (float)$min;
                    }
                    if ($max !== null && $max !== '') {
                        $cond['to'] = (float)$max;
                    }
                    if (!empty($cond)) {
                        $collection->addAttributeToFilter($priceAttr, $cond);
                    }
                }
            }
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
            // Price
            $min = $params['price_min'] ?? null;
            $max = $params['price_max'] ?? null;
            if ($min !== null || $max !== null) {
                $priceAttr = $this->helper->getConfig('mapping/price_attribute') ?: 'price';
                $cond = [];
                if ($min !== null && $min !== '') {
                    $cond['from'] = (float)$min;
                }
                if ($max !== null && $max !== '') {
                    $cond['to'] = (float)$max;
                }
                if (!empty($cond)) {
                    $collection->addAttributeToFilter($priceAttr, $cond);
                }
            }
        }

        // 6) Sorting + pagination (keep as before)
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
