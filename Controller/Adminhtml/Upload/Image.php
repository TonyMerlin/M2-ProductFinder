<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Controller\Adminhtml\Upload;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class Image extends Action
{
    public const ADMIN_RESOURCE = 'Merlin_ProductFinder::config';

    private UploaderFactory $uploaderFactory;
    private Filesystem $filesystem;
    private JsonFactory $resultJsonFactory;
    private UrlInterface $url;

    public function __construct(
        Context $context,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        JsonFactory $resultJsonFactory,
        UrlInterface $url
    ) {
        parent::__construct($context);
        $this->uploaderFactory   = $uploaderFactory;
        $this->filesystem        = $filesystem;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->url               = $url;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            // input name from <input type="file" name="profile_image">
            $uploader = $this->uploaderFactory->create(['fileId' => 'image']);
            $uploader->setAllowedExtensions(['jpg','jpeg','png','gif','webp','avif']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true); // hashed subfolders

            $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $target   = 'merlin_productfinder';

            $save = $uploader->save($mediaDir->getAbsolutePath($target));
            if (!$save) {
                throw new LocalizedException(__('Unable to save the file.'));
            }

            $relPath = $target . $save['file']; // includes dispersion path
            $url     = $this->url->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA]) . $relPath;

            return $result->setData(['ok' => true, 'url' => $url, 'path' => $relPath]);
        } catch (\Throwable $e) {
            return $result->setHttpResponseCode(400)->setData([
                'ok' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
