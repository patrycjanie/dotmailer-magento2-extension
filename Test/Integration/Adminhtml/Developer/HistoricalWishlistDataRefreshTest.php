<?php

namespace Dotdigitalgroup\Email\Tests\Integration\Adminhtml\Developer;

include __DIR__ . '/../../_files/wishlist.php';

class HistoricalWishlistDataRefreshTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    /**
     * @var string
     */
    public $model = \Dotdigitalgroup\Email\Model\Wishlist::class;

    /**
     * @var string
     */
    public $uri = 'backend/dotdigitalgroup_email/run/wishlistsreset';

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var string
     */
    public $resource = 'Dotdigitalgroup_Email::config';

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $params = [
            'from' => '',
            'to' => ''
        ];
        $this->getRequest()->setParams($params);
        $this->data = $this->getWishlistData();
    }

    /**
     * @param string $from
     * @param string $to
     * @return void
     */
    public function runReset($from, $to)
    {
        $params = [
            'from' => $from,
            'to' => $to
        ];
        $this->getRequest()->setParams($params);
        $this->dispatch($this->uri);
    }

    /**
     * @return void
     */
    public function testWishlistResetSuccessfulGivenDateRange()
    {
        $collection = $this->createWishlistDataAndGetCollection();

        $this->runReset('2017-02-09', '2017-02-10');

        $this->assertEquals(1, $collection->getSize());
    }

    /**
     * @return void
     */
    public function testWishlistResetNotSuccessfulWrongDateRange()
    {
        $collection = $this->createWishlistDataAndGetCollection();

        $this->runReset('2017-02-09', '2017-01-10');

        $this->assertSessionMessages(
            $this->equalTo(['To Date cannot be earlier then From Date.']),
            \Magento\Framework\Message\MessageInterface::TYPE_ERROR
        );

        $this->assertEquals(0, $collection->getSize());
    }

    /**
     * @return void
     */
    public function testWishlistResetNotSuccessfulInvalidDateRange()
    {
        $collection = $this->createWishlistDataAndGetCollection();

        $this->runReset('2017-02-09', 'not valid');

        $this->assertSessionMessages(
            $this->equalTo(['From or To date is not a valid date.']),
            \Magento\Framework\Message\MessageInterface::TYPE_ERROR
        );

        $this->assertEquals(0, $collection->getSize());
    }

    /**
     * @return void
     */
    public function testWishlistFullResetSuccessfulWithoutDateRange()
    {
        $collection = $this->createWishlistDataAndGetCollection();

        $this->runReset('', '');

        $this->assertEquals(1, $collection->getSize());
    }

    /**
     * @return void
     */
    public function testWishlistFullResetSuccessWithFromDateOnly()
    {
        $collection = $this->createWishlistDataAndGetCollection();

        $this->runReset('2017-02-10', '');

        $this->assertEquals(1, $collection->getSize());
    }

    /**
     * @return void
     */
    public function testWishlistFullResetSuccessWithToDateOnly()
    {
        $collection = $this->createWishlistDataAndGetCollection();

        $this->runReset('', '2017-02-10');

        $this->assertEquals(1, $collection->getSize());
    }

    /**
     * @param array $data
     * @return void
     */
    public function createEmailData($data)
    {
        $emailModel = $this->_objectManager->create($this->model);
        $emailModel->addData($data)->save();
    }

    /**
     * @return void
     */
    public function emptyTable()
    {
        $abandonedCollection = $this->_objectManager->create(
            \Dotdigitalgroup\Email\Model\ResourceModel\Wishlist\Collection::class
        );
        $abandonedCollection->walk('delete');
    }

    /**
     * @return array
     */
    private function getWishlistData()
    {
        /** @var \Magento\Wishlist\Model\ResourceModel\Wishlist\Collection $collection */
        $collection = $this->_objectManager->create(
            \Magento\Wishlist\Model\ResourceModel\Wishlist\Collection::class
        );
        $wishlist = $collection->getFirstItem();
        $data = [
            'wishlist_id' => $wishlist->getId(),
            'item_count' => $wishlist->getItemsCount(),
            'customer_id' => $wishlist->getCustomerId(),
            'store_id' => '1',
            'wishlist_imported' => '1',
            'created_at' => '2017-02-09'
        ];

        return $data;
    }

    /**
     * @return mixed
     */
    private function createWishlistDataAndGetCollection()
    {
        $this->emptyTable();
        $this->createEmailData($this->data);

        $collection = $this->_objectManager->create($this->model)
            ->getCollection();
        $collection->addFieldToFilter('wishlist_imported', ['null' => true]);
        return $collection;
    }
}
