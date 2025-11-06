<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block\Adminhtml\System\Config\Button;

class Clear extends AbstractButton
{
    protected function getButtonId(): string   { return 'merlin_pf_clear_cache'; }
    protected function getButtonLabel(): string{ return __('Clear Finder Cache')->render(); }

    protected function getActionUrl(): string
    {
        return $this->getUrl('merlin_productfinder/cache/clear');
    }
}
