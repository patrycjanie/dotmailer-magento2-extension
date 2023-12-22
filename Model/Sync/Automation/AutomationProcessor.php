<?php

namespace Dotdigitalgroup\Email\Model\Sync\Automation;

use Dotdigitalgroup\Email\Exception\PendingOptInException;
use Dotdigitalgroup\Email\Helper\Data;
use Dotdigitalgroup\Email\Logger\Logger;
use Dotdigitalgroup\Email\Model\Automation;
use Dotdigitalgroup\Email\Model\ContactFactory;
use Dotdigitalgroup\Email\Model\Newsletter\BackportedSubscriberLoader;
use Dotdigitalgroup\Email\Model\ResourceModel\Automation as AutomationResource;
use Dotdigitalgroup\Email\Model\StatusInterface;
use Dotdigitalgroup\Email\Model\Sync\Automation\DataField\DataFieldCollector;
use Dotdigitalgroup\Email\Model\Sync\Automation\DataField\DataFieldTypeHandler;
use Magento\Framework\Exception\LocalizedException;
use Magento\Newsletter\Model\Subscriber;

class AutomationProcessor
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var AutomationResource
     */
    protected $automationResource;

    /**
     * @var ContactFactory
     */
    private $contactFactory;

    /**
     * @var ContactManager
     */
    private $contactManager;

    /**
     * @var DataFieldCollector
     */
    private $dataFieldCollector;

    /**
     * @var DataFieldTypeHandler
     */
    private $dataFieldTypeHandler;

    /**
     * @var BackportedSubscriberLoader
     */
    private $backportedSubscriberLoader;

    /**
     * AutomationProcessor constructor.
     *
     * @param Data $helper
     * @param Logger $logger
     * @param AutomationResource $automationResource
     * @param ContactFactory $contactFactory
     * @param ContactManager $contactManager
     * @param DataFieldCollector $dataFieldCollector
     * @param DataFieldTypeHandler $dataFieldTypeHandler
     * @param BackportedSubscriberLoader $backportedSubscriberLoader
     */
    public function __construct(
        Data $helper,
        Logger $logger,
        AutomationResource $automationResource,
        ContactFactory $contactFactory,
        ContactManager $contactManager,
        DataFieldCollector $dataFieldCollector,
        DataFieldTypeHandler $dataFieldTypeHandler,
        BackportedSubscriberLoader $backportedSubscriberLoader
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
        $this->automationResource = $automationResource;
        $this->contactFactory = $contactFactory;
        $this->contactManager = $contactManager;
        $this->dataFieldCollector = $dataFieldCollector;
        $this->dataFieldTypeHandler = $dataFieldTypeHandler;
        $this->backportedSubscriberLoader = $backportedSubscriberLoader;
    }

    /**
     * Process.
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection $collection
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process($collection)
    {
        $data = [];

        foreach ($collection as $automation) {
            if ($this->shouldExitLoop($automation)) {
                continue;
            }

            $email = $automation->getEmail();
            $websiteId = $automation->getWebsiteId();
            $storeId = $automation->getStoreId();
            $automationDataFields = $this->retrieveAutomationDataFields($automation, $email, $websiteId);

            try {
                $automationContact = $this->contactFactory->create()
                    ->loadByCustomerEmail($email, $websiteId);
                $automationSubscriber = $this->backportedSubscriberLoader->loadBySubscriberEmail($email, $websiteId);

                $this->checkNonSubscriberCanBeEnrolled($automationSubscriber, $automation);

                $contactId = $this->contactManager->prepareDotdigitalContact(
                    $automationContact,
                    $automationSubscriber,
                    $automationDataFields,
                    $automation->getAutomationType()
                );

                $data[$websiteId][$storeId]['contacts'][$automation->getId()] = $contactId;
            } catch (PendingOptInException $e) {
                $this->automationResource->setStatusAndSaveAutomation(
                    $automation,
                    StatusInterface::PENDING_OPT_IN
                );
                continue;
            } catch (\Exception $e) {
                $this->automationResource->setStatusAndSaveAutomation(
                    $automation,
                    StatusInterface::FAILED,
                    $e->getMessage()
                );
                $this->logger->debug(
                    sprintf('Enrolment failed for automation id: %s', $automation->getId()),
                    [(string) $e]
                );
                continue;
            }
        }

        return $data;
    }

    /**
     * Check if automation should be processed.
     *
     * @param Automation $automation
     * @return bool
     */
    protected function shouldExitLoop(Automation $automation)
    {
        return false;
    }

    /**
     * Retrieve automation data fields.
     *
     * @param Automation $automation
     * @param string $email
     * @param string|int $websiteId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function retrieveAutomationDataFields(Automation $automation, $email, $websiteId): array
    {
        $type = $automation->getAutomationType();
        //Set type to generic automation status if type contains constant value
        if (strpos($type, AutomationTypeHandler::ORDER_STATUS_AUTOMATION) !== false) {
            $type = AutomationTypeHandler::ORDER_STATUS_AUTOMATION;
        }

        return $this->dataFieldTypeHandler->retrieveDatafieldsByType(
            $type,
            $email,
            $websiteId,
            $automation->getTypeId(),
            $automation->getStoreName()
        );
    }

    /**
     * Check non-subscriber can be enrolled.
     *
     * For all automations apart from AC, this is governed by the switch in Sync Settings.
     *
     * @param Subscriber $subscriber
     * @param Automation $automation
     *
     * @return void
     * @throws LocalizedException
     */
    protected function checkNonSubscriberCanBeEnrolled(Subscriber $subscriber, Automation $automation)
    {
        if (!$subscriber->isSubscribed() &&
            $this->helper->isOnlySubscribersForContactSync($automation->getWebsiteId())
        ) {
            throw new LocalizedException(
                __('Non-subscribed contacts cannot be enrolled.')
            );
        }
    }
}
