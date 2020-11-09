<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Controller\Adminhtml\Order;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Backend\Model\Auth\Session;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Helper\OrderHelper;
use Magento\Framework\App\ResourceConnection;

class Unhold
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var Session
     */
    protected $authSession;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Unhold constructor.
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderHelper $orderHelper
     * @param Session $authSession
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderHelper $orderHelper,
        Session $authSession,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ResourceConnection $resourceConnection
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderHelper = $orderHelper;
        $this->authSession = $authSession;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->casedataFactory = $casedataFactory;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param \Magento\Sales\Controller\Adminhtml\Order $subject
     * @param $result
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function afterExecute(\Magento\Sales\Controller\Adminhtml\Order $subject, $result)
    {
        $signifydUnhold = $subject->getRequest()->getParam('signifyd_unhold');
        $orderId = $subject->getRequest()->getParam('order_id');

        if (empty($signifydUnhold)) {
            return $result;
        }

        if (empty($orderId)) {
            return $result;
        }

        try {
            /** @var $case \Signifyd\Connect\Model\Casedata */
            $case = $this->casedataFactory->create();

            $this->resourceConnection->getConnection()->beginTransaction();
            $this->casedataResourceModel->loadForUpdate($case, $case->getId());

            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderRepository->get($orderId);

            if ($case->isHoldReleased() === false) {
                $case->setEntries('hold_released', 1);
                $this->casedataResourceModel->save($case);
            }

            $this->orderHelper->addCommentToStatusHistory(
                $order,
                "Signifyd: order status updated by {$this->authSession->getUser()->getUserName()}"
            );

            $this->orderRepository->save($case->getOrder());
            $this->resourceConnection->getConnection()->commit();
        } catch (\Exception $e) {
            $this->resourceConnection->getConnection()->rollBack();
        }

        return $result;
    }
}
