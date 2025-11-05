<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block\Widget;

use Magento\Widget\Block\BlockInterface;
use Merlin\ProductFinder\Block\Form as BaseForm;

class Finder extends BaseForm implements BlockInterface
{
    /** @var string */
    protected $_template = 'Merlin_ProductFinder::form.phtml';

    public function getTitle(): string
    {
        return (string)($this->getData('title') ?? '');
    }

    public function showPrePost(): bool
    {
        $v = $this->getData('show_pre_post');
        // default to true if not set
        return $v === null || $v === '' ? true : (bool)$v;
    }

    public function getPreHtml(): string
    {
        return $this->showPrePost() ? parent::getPreHtml() : '';
    }

    public function getPostHtml(): string
    {
        return $this->showPrePost() ? parent::getPostHtml() : '';
    }

    /**
     * Optional override for sections order if provided in widget params.
     * Falls back to config-defined sections in BaseForm (kept for legacy compat).
     */
    public function getSections(): array
    {
        $override = (string)($this->getData('sections_override') ?? '');
        if ($override !== '') {
            $arr = json_decode($override, true);
            if (is_array($arr) && !empty($arr)) {
                return $arr;
            }
        }
        return parent::getSections();
    }
}
