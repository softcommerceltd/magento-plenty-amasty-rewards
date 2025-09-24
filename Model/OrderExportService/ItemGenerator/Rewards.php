<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace SoftCommerce\PlentyAmastyRewards\Model\OrderExportService\ItemGenerator;

use Amasty\Rewards\Api\Data\SalesQuote\EntityInterface;
use Magento\Framework\Exception\LocalizedException;
use SoftCommerce\PlentyOrderProfile\Model\OrderExportService\Generator\Order\Items\ItemAbstract;
use SoftCommerce\PlentyOrderProfile\Model\OrderExportService\Processor\Order as OrderProcessor;
use SoftCommerce\PlentyOrder\RestApi\Order\ItemInterface as HttpClient;
use SoftCommerce\PlentyOrder\RestApi\OrderInterface as HttpOrderClient;
use SoftCommerce\Profile\Model\ServiceAbstract\ProcessorInterface;

/**
 * @inheritdoc
 * Class Rewards used to export
 * Amasty Rewards
 */
class Rewards extends ItemAbstract implements ProcessorInterface
{
    private const XML_PATH_DISCOUNT_INCLUDES_TAX = 'tax/calculation/discount_tax';

    /**
     * @inheritDoc
     */
    public function execute(): void
    {
        $this->initialize();
        $this->generate();
        $this->finalize();
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    private function generate(): void
    {
        $context = $this->getContext();
        $canProcess = !$context->getClientOrder()->getItemByTypeId(HttpClient::TYPE_PROMOTIONAL_COUPON);
        $discountAmount = (float) $context->getSalesOrder()->getDiscountAmount();

        if (!$canProcess
            || $discountAmount >= 0
            || !$points = $context->getSalesOrder()->getData(EntityInterface::POINTS_SPENT)
        ) {
            return;
        }

        $vatRate = 0;
        if ($this->scopeConfig->getValue(self::XML_PATH_DISCOUNT_INCLUDES_TAX)) {
            $vatRate = $this->getSalesOrderTaxRate->getTaxRate(
                (int) $context->getSalesOrder()->getEntityId()
            );
        }

        $amounts[] = [
            HttpClient::IS_SYSTEM_CURRENCY => true,
            HttpClient::CURRENCY => $context->getSalesOrder()->getBaseCurrencyCode(),
            HttpClient::EXCHANGE_RATE => 1,
            HttpClient::PRICE_ORIGINAL_GROSS => $discountAmount,
            HttpClient::SURCHARGE => 0,
            HttpClient::DISCOUNT => 0,
            HttpClient::IS_PERCENTAGE => false
        ];

        $referrerId = (float) $context->storeConfig()->getReferrerIdByStoreId(
            (int) $context->getSalesOrder()->getStoreId()
        );

        $context->getRequestStorage()->addData(
            [
                HttpClient::TYPE_ID => HttpClient::TYPE_PROMOTIONAL_COUPON,
                HttpClient::REFERRER_ID => $referrerId,
                HttpClient::QUANTITY => 1,
                HttpClient::COUNTRY_VAT_ID => $this->getCountryId(
                    $context->getSalesOrder()->getBillingAddress()->getCountryId()
                ),
                HttpClient::VAT_FIELD => 0,
                HttpClient::VAT_RATE => $vatRate,
                HttpClient::ORDER_ITEM_NAME => $context->getSalesOrder()->getDiscountDescription()
                    ?: __('Used %1 reward points', $points),
                HttpClient::AMOUNTS => $amounts,
            ],
            [OrderProcessor::TYPE_ID, HttpOrderClient::ORDER_ITEMS]
        );

        $context->getClientOrder()->setIsDiscountApplied(true);
    }
}
