<?php

namespace Dotdigitalgroup\Email\Model\Sync\Batch;

use Dotdigitalgroup\Email\Helper\File;
use Dotdigitalgroup\Email\Logger\Logger;
use Dotdigitalgroup\Email\Model\ImporterFactory;
use Dotdigitalgroup\Email\Model\ResourceModel\ContactFactory as ContactResourceFactory;

abstract class AbstractBatchProcessor
{
    /**
     * @var File
     */
    private $file;

    /**
     * @var ImporterFactory
     */
    protected $importerFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ContactResourceFactory
     */
    protected $contactResourceFactory;

    /**
     * AbstractBatchProcessor constructor.
     *
     * @param File $file
     * @param ImporterFactory $importerFactory
     * @param Logger $logger
     * @param ContactResourceFactory $contactResourceFactory
     */
    public function __construct(
        File $file,
        ImporterFactory $importerFactory,
        Logger $logger,
        ContactResourceFactory $contactResourceFactory
    ) {
        $this->file = $file;
        $this->importerFactory = $importerFactory;
        $this->logger = $logger;
        $this->contactResourceFactory = $contactResourceFactory;
    }

    /**
     * Queue batch with importer.
     *
     * @param array $batch
     * @param string|int $websiteId
     * @param string $filename
     *
     * @return void
     */
    abstract protected function addToImportQueue(array $batch, $websiteId, string $filename);

    /**
     * Process a completed batch.
     *
     * @param array $batch
     * @param int|string $websiteId
     * @param string $filename
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process(array $batch, $websiteId, string $filename)
    {
        if (empty($batch) || empty($filename)) {
            return;
        }
        $this->sendDataToFile($batch, $filename);
        $this->addToImportQueue($batch, $websiteId, $filename);
        $this->markContactsAsImported(array_keys($batch));
    }

    /**
     * Mark contacts as imported.
     *
     * @param array $contactIds
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function markContactsAsImported($contactIds)
    {
        $this->contactResourceFactory->create()
            ->setContactsImportedByIds(
                $contactIds
            );
    }

    /**
     * Print each row to the csv file.
     *
     * @param array $batch
     * @param string $filename
     *
     * @return void
     */
    private function sendDataToFile($batch, $filename)
    {
        $filepath = $this->file->getFilePath($filename);
        foreach ($batch as $item) {
            $this->file->outputCSV(
                $filepath,
                $item
            );
        }
    }
}
