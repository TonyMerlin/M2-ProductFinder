<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class Results extends Template
{
    protected $_template = 'Merlin_ProductFinder::results.phtml';

    /** @var Registry */
    private $registry;

    public function __construct(
        Template\Context $context,
        array $data = [],
        $maybeRegistry = null
    ) {
        parent::__construct($context, $data);

        if ($maybeRegistry instanceof Registry) {
            $this->registry = $maybeRegistry;
        } else {
            $this->registry = ObjectManager::getInstance()->get(Registry::class);
        }
    }

    /** @return \Magento\Catalog\Model\ResourceModel\Product\Collection|null */
    public function getCollection()
    {
        return $this->registry->registry('merlin_productfinder_collection');
    }

    public function getFinderParams(): array
    {
        $params = $this->registry->registry('merlin_productfinder_params');
        return is_array($params) ? $params : ['order' => 'name', 'dir' => 'ASC', 'p' => 1, 'limit' => 12];
    }

    /**
     * Ultra defensive price formatting.
     */
    public function formatPrice($amount): string
    {
        $amount = (float)$amount;

        // 1) Try the block's own priceCurrency (normal Magento way)
        $priceCurrency = $this->getPriceCurrency();
        if ($priceCurrency instanceof PriceCurrencyInterface) {
            return $priceCurrency->convertAndFormat($amount);
        }

        // 2) Fallback: pull from OM
        try {
            $om = ObjectManager::getInstance();
            /** @var PriceCurrencyInterface $pc */
            $pc = $om->get(PriceCurrencyInterface::class);
            return $pc->convertAndFormat($amount);
        } catch (\Throwable $e) {
            // 3) Last resort: plain number
            return number_format($amount, 2);
        }
    }
}
