<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const XML_PATH = 'merlin_productfinder/';

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH . 'general/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getConfig(string $path, $store = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH . $path, ScopeInterface::SCOPE_STORE, $store);
    }

    public function getSections(): array
    {
        $json = (string)($this->getConfig('layout/sections') ?? '');
        $arr = $json ? json_decode($json, true) : null;
        if (!is_array($arr) || !$arr) {
            $arr = ['category','product_type','color','price','extras'];
        }
        return $arr;
    }

    public function getExtrasMap(): array
    {
        $json = (string)($this->getConfig('mapping/extras') ?? '');
        $map = $json ? json_decode($json, true) : null;
        return is_array($map) ? $map : [];
    }
}
