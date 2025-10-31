<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Merlin\ProductFinder\Helper\Data as Helper;
use Magento\Framework\Registry;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class Results extends Action
{
    private PageFactory $pageFactory;
    private ProductCollectionFactory $collectionFactory;
    private Helper $helper;
    private Registry $registry;
    private EavConfig $eavConfig;
    private CategoryRepository $categoryRepository;
    private CategoryCollectionFactory $categoryCollectionFactory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        ProductCollectionFactory $collectionFactory,
        Helper $helper,
        Registry $registry,
        EavConfig $eavConfig,
        CategoryRepository $categoryRepository,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->collectionFactory = $collectionFactory;
        $this->helper = $helper;
        $this->registry = $registry;
        $this->eavConfig = $eavConfig;
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    public function execute()
    {
        if (!$this->helper->isEnabled()) {
            return $this->_redirect('/');
        }

        $params = $this->getRequest()->getParams();

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name','price','small_image']);
        $collection->addAttributeToFilter('status', 1);
        $collection->addAttributeToFilter('visibility', ["in" => [2,3,4]]);

        // Category filter: include all descendants of the chosen top category
        if (!empty($params['category_id'])) {
            $catId = (int)$params['category_id'];
            if ($catId > 0) {
                try {
                    $top = $this->categoryRepository->get($catId);
                    // Get all children (including the category itself)
                    $childrenIds = $top->getAllChildren(true);
                    if (is_string($childrenIds)) { // older Magento returns csv string
                        $childrenIds = array_filter(array_map('intval', explode(',', $childrenIds)));
                    }
                    if (is_array($childrenIds) && !empty($childrenIds)) {
                        $collection->addCategoriesFilter(['in' => $childrenIds]);
                    } else {
                        $collection->addCategoriesFilter(['in' => [$catId]]);
                    }
                } catch (\Exception $e) {
                    // Fallback: just use the provided category id
                    $collection->addCategoriesFilter(['in' => [$catId]]);
                }
            }
        }

        // Helper to safely apply an attribute filter if the code exists
        $applyAttr = function (?string $code, array $values) use ($collection) {
            $code = (string)$code;
            if (!$code || !$values) return;
            $attr = $this->eavConfig->getAttribute('catalog_product', $code);
            if (!$attr || !$attr->getId()) return; // invalid code: skip
            $collection->addAttributeToFilter($code, ['in' => $values]);
        };

        // Product type (custom attribute like appliance_type) â€” skip if left blank or invalid
        $productTypeAttr = (string)$this->helper->getConfig('mapping/product_type');
        if (!empty($params['product_type'])) {
            $applyAttr($productTypeAttr, (array)$params['product_type']);
        }

        // Colour
        $colorAttr = (string)$this->helper->getConfig('mapping/color');
        if (!empty($params['color'])) {
            $applyAttr($colorAttr, (array)$params['color']);
        }

        // Extras (e.g., brand => manufacturer)
        $extrasMap = $this->helper->getExtrasMap();
        foreach ($extrasMap as $key => $attrCode) {
            if (!empty($params[$key])) {
                $applyAttr((string)$attrCode, (array)$params[$key]);
            }
        }

        // Price range
        $min = $params['price_min'] ?? null; $max = $params['price_max'] ?? null;
        if ($min !== null || $max !== null) {
            $priceAttr = $this->helper->getConfig('mapping/price_attribute') ?: 'price';
            $cond = [];
            if ($min !== null && $min !== '') { $cond['from'] = (float)$min; }
            if ($max !== null && $max !== '') { $cond['to'] = (float)$max; }
            if (!empty($cond)) {
                $collection->addAttributeToFilter($priceAttr, $cond);
            }
        }

        // Sorting & pagination (as in v1.1)
        $order = $params['order'] ?? 'name';
        $dir = strtoupper($params['dir'] ?? 'ASC');
        $allowedOrders = ['name','price','created_at'];
        if (!in_array($order, $allowedOrders)) { $order = 'name'; }
        $dir = in_array($dir, ['ASC','DESC']) ? $dir : 'ASC';
        $collection->setOrder($order, $dir);

        $p = max(1, (int)($params['p'] ?? 1));
        $limit = (int)($params['limit'] ?? 12);
        $allowedLimits = [12, 24, 48];
        if (!in_array($limit, $allowedLimits)) { $limit = 12; }
        $collection->setCurPage($p)->setPageSize($limit);

        $this->registry->register('merlin_productfinder_collection', $collection);
        $this->registry->register('merlin_productfinder_params', ['order'=>$order,'dir'=>$dir,'p'=>$p,'limit'=>$limit]);

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Finder Results'));
        return $page;
    }
}
