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
use SoftCommerce\PlentyOrderRestApi\Model\OrderInterface as HttpClient;
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
        if ($context->getClientOrder()->getItemByTypeId(HttpClient::ITEM_TYPE_PROMOTIONAL_COUPON)
            || !$amount = (float) $context->getSalesOrder()->getData(EntityInterface::POINTS_SPENT)
        ) {
            return;
        }

        $vatRate = 0;
        if ($this->scopeConfig->getValue(self::XML_PATH_DISCOUNT_INCLUDES_TAX)) {
            $vatRate = $this->getSalesOrderTaxRate->getTaxRate(
                (int) $this->getContext()->getSalesOrder()->getEntityId()
            );
        }

        $amounts[] = [
            HttpClient::IS_SYSTEM_CURRENCY => true,
            HttpClient::CURRENCY => $context->getSalesOrder()->getBaseCurrencyCode(),
            HttpClient::EXCHANGE_RATE => 1,
            HttpClient::PRICE_ORIGINAL_GROSS => $amount,
            HttpClient::SURCHARGE => 0,
            HttpClient::DISCOUNT => 0,
            HttpClient::IS_PERCENTAGE => false
        ];

        $this->getRequestStorage()->addData(
            [
                HttpClient::TYPE_ID => HttpClient::ITEM_TYPE_PROMOTIONAL_COUPON,
                HttpClient::REFERRER_ID => $this->getContext()->orderConfig()->getOrderReferrerId(
                    $context->getSalesOrder()->getStoreId()
                ),
                HttpClient::QUANTITY => 1,
                HttpClient::COUNTRY_VAT_ID => $this->getCountryId(
                    $this->getContext()->getSalesOrder()->getBillingAddress()->getCountryId()
                ),
                HttpClient::VAT_FIELD => 0,
                HttpClient::VAT_RATE => $vatRate,
                HttpClient::ORDER_ITEM_NAME => __(
                    'Amasty Rewards: (%1)',
                    $context->getSalesOrder()->getDiscountDescription() ?: 'N/A'
                ),
                HttpClient::AMOUNTS => $amounts,
            ]
        );

        $this->getContext()->getClientOrder()->setIsDiscountApplied(true);
    }
}
