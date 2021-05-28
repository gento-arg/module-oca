<?php

namespace Gento\Oca\Observer\Quote;

use Gento\Oca\Api\BranchRepositoryInterface;
use Gento\Oca\Helper\Data;
use Gento\Oca\Model\OcaApi;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class SubmitBeforeObserver implements ObserverInterface
{
    /**
     * @var BranchRepositoryInterface
     */
    private $branchRepository;

    /**
     * @var Data
     */
    private $helper;
    /**
     * @var OcaApi
     */
    private $ocaApi;

    /**
     * SubmitBeforeObserver constructor.
     * @param BranchRepositoryInterface $branchRepository
     * @param Data $helper
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     * @param OcaApi $ocaApi
     */
    public function __construct(
        BranchRepositoryInterface $branchRepository,
        Data $helper,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        OcaApi $ocaApi
    ) {
        $this->branchRepository = $branchRepository;
        $this->helper = $helper;
        $this->quoteRepository = $quoteRepository;
        $this->ocaApi = $ocaApi;
    }

    /**
     * @param Observer $observer
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        /* @var Order $order */
        $order = $observer->getEvent()->getData('order');

        $shippingMethod = $order->getShippingMethod();
        if (substr($shippingMethod, 0, 9) !== 'gento_oca') {
            return $this;
        }

        /* @var Quote $quote */
        $quote = $this->quoteRepository->get($order->getQuoteId());

        $originBranchCode = $quote->getData('shipping_origin_branch');
        $order->setData('shipping_origin_branch', $originBranchCode);

        $branchData = null;
        try {
            $shippingBranch = $quote->getData('shipping_branch');

            $order->setData('shipping_branch', $shippingBranch);

            $branch = $this->branchRepository->getByCode($shippingBranch);
            $branchData = $branch->getData();
        } catch (NoSuchEntityException $e) {
        }

        if ($branchData === null) {
            $postcode = $quote->getShippingAddress()->getPostcode();
            $branches = $this->ocaApi->getBranchesZipCode($postcode);
            foreach ($branches as $branch) {
                if ($branch['code'] == $branchCode) {
                    $branchData = $branch;
                    break;
                }
            }
        }

        if ($branchData !== null) {
            $branchData = $this->helper->addDescriptionToBranch($branchData);
            $branchDescription = trim($branchData['branch_description']);
            if (!empty($branchDescription)) {
                $shippingDescription = $order->getShippingDescription() . PHP_EOL . $branchData['branch_description'];
                $order->setShippingDescription($shippingDescription);
            }
        }

        return $this;
    }

}
