<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Tests\Unit\Core\Form\IdentifiableObject\Builder\CartRule;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\CartRule\Exception\CartRuleConstraintException;
use PrestaShop\PrestaShop\Core\Domain\CartRule\ValueObject\CartRuleAction;
use PrestaShop\PrestaShop\Core\Domain\CartRule\ValueObject\DiscountApplicationType;
use PrestaShop\PrestaShop\Core\Domain\CartRule\ValueObject\GiftProduct;
use PrestaShop\PrestaShop\Core\Domain\Currency\ValueObject\CurrencyId;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\ValueObject\Money;
use PrestaShop\PrestaShop\Core\Domain\ValueObject\Reduction;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\Builder\CartRule\CartRuleActionBuilder;

class CartRuleActionBuilderTest extends TestCase
{
    /**
     * @dataProvider getSupportedData
     *
     * @param array<string, mixed> $data
     *
     * @return void
     */
    public function testItSupportsData(array $data): void
    {
        Assert::assertTrue($this->getCartRuleActionBuilder()->supports($data));
    }

    /**
     * @dataProvider getUnsupportedData
     *
     * @param array<string, mixed> $data
     *
     * @return void
     */
    public function testItDoesNotSupportData(array $data): void
    {
        Assert::assertFalse($this->getCartRuleActionBuilder()->supports($data));
    }

    /**
     * @dataProvider getInvalidData
     *
     * @param array<string, mixed> $data
     * @param string $expectedException
     * @param int $expectedCode
     *
     * @return void
     *
     * @throws CartRuleConstraintException
     */
    public function testItThrowsExceptionWhenDataIsInvalidToBuildAction(array $data, string $expectedException, int $expectedCode = 0): void
    {
        $this->expectException($expectedException);
        $this->expectExceptionCode($expectedCode);

        $this->getCartRuleActionBuilder()->build($data);
    }

    /**
     * @dataProvider getDataForFreeShippingAction
     * @dataProvider getDataForAmountDiscountAction
     * @dataProvider getDataForPercentageDiscountAction
     *
     * @param array<string, mixed> $data
     * @param CartRuleAction $expectedAction
     *
     * @return void
     */
    public function testItBuildsAction(
        array $data,
        CartRuleAction $expectedAction
    ): void {
        $action = $this->getCartRuleActionBuilder()->build($data);

        Assert::assertEquals($expectedAction, $action);
    }

    public function getSupportedData(): iterable
    {
        yield [
            ['free_shipping' => true],
        ];

        yield [
            ['free_shipping' => false],
        ];

        yield [
            [
                'gift_product' => [
                    [
                        'product_id' => 2,
                    ],
                ],
            ],
        ];

        yield [
            [
                'gift_product' => [
                    [
                        'product_id' => 2,
                        'combination_id' => 4,
                    ],
                ],
            ],
        ];

        yield [
            [
                'discount' => [
                    'reduction' => [
                        'type' => Reduction::TYPE_AMOUNT,
                        'value' => '30',
                    ],
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                ],
            ],
        ];

        yield [
            [
                'discount' => [
                    'reduction' => [
                        'type' => Reduction::TYPE_PERCENTAGE,
                        'value' => '90',
                    ],
                    'discount_application' => DiscountApplicationType::SPECIFIC_PRODUCT,
                    'specific_product' => 14,
                ],
            ],
        ];
    }

    public function getUnsupportedData(): iterable
    {
        yield [
            [[]],
        ];

        yield [
            ['discount'],
        ];

        yield 'missing reduction value' => [
            [
                'discount' => [
                    'reduction' => [
                        'type' => Reduction::TYPE_PERCENTAGE,
                    ],
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                ],
            ],
        ];

        yield 'missing reduction type' => [
            [
                'discount' => [
                    'reduction' => [
                        'value' => '144',
                    ],
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                ],
            ],
        ];

        yield 'missing discount application' => [
            [
                'discount' => [
                    'reduction' => [
                        'value' => '144',
                        'type' => Reduction::TYPE_AMOUNT,
                    ],
                ],
            ],
        ];
    }

    public function getInvalidData(): iterable
    {
        yield [
            [
                'free_shipping' => false,
            ],
            CartRuleConstraintException::class,
            CartRuleConstraintException::MISSING_ACTION,
        ];

        yield [
            [
                'gift_product' => [
                    [],
                ],
            ],
            CartRuleConstraintException::class,
            CartRuleConstraintException::MISSING_ACTION,
        ];

        yield [
            [
                'gift_product' => [
                    [
                        'product_id' => 0,
                    ],
                ],
            ],
            ProductConstraintException::class,
            ProductConstraintException::INVALID_ID,
        ];

        yield 'reduciton amount value is empty' => [
            [
                'discount' => [
                    'reduction' => [
                        'value' => 0,
                        'type' => Reduction::TYPE_AMOUNT,
                    ],
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                ],
            ],
            CartRuleConstraintException::class,
            CartRuleConstraintException::MISSING_ACTION,
        ];

        yield 'reduction percentage value is empty' => [
            [
                'discount' => [
                    'reduction' => [
                        'value' => 0,
                        'type' => Reduction::TYPE_PERCENTAGE,
                    ],
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                ],
            ],
            CartRuleConstraintException::class,
            CartRuleConstraintException::MISSING_ACTION,
        ];

        yield 'invalid reduction type' => [
            [
                'discount' => [
                    'reduction' => [
                        'value' => '10',
                        'type' => 'woah',
                    ],
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                ],
            ],
            DomainConstraintException::class,
            DomainConstraintException::INVALID_REDUCTION_TYPE,
        ];

        yield 'invalid reduction percentage' => [
            [
                'discount' => [
                    'reduction' => [
                        'value' => '-150',
                        'type' => Reduction::TYPE_PERCENTAGE,
                    ],
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                ],
            ],
            DomainConstraintException::class,
            DomainConstraintException::INVALID_REDUCTION_PERCENTAGE,
        ];

        yield 'invalid reduction amount' => [
            [
                'discount' => [
                    'reduction' => [
                        'value' => '-150',
                        'type' => Reduction::TYPE_AMOUNT,
                        'currency' => 1,
                        'include_tax' => true,
                    ],
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                ],
            ],
            DomainConstraintException::class,
            DomainConstraintException::INVALID_REDUCTION_AMOUNT,
        ];

        yield 'invalid discount application type for amount discount 1' => [
            [
                'discount' => [
                    'reduction' => [
                        'value' => '150',
                        'type' => Reduction::TYPE_AMOUNT,
                        'currency' => 1,
                        'include_tax' => true,
                    ],
                    'discount_application' => DiscountApplicationType::CHEAPEST_PRODUCT,
                ],
            ],
            CartRuleConstraintException::class,
            CartRuleConstraintException::INVALID_DISCOUNT_APPLICATION_TYPE,
        ];

        yield 'invalid discount application type for amount discount 2' => [
            [
                'discount' => [
                    'reduction' => [
                        'value' => '150',
                        'type' => Reduction::TYPE_AMOUNT,
                        'currency' => 1,
                        'include_tax' => true,
                    ],
                    'discount_application' => DiscountApplicationType::SELECTED_PRODUCTS,
                ],
            ],
            CartRuleConstraintException::class,
            CartRuleConstraintException::INVALID_DISCOUNT_APPLICATION_TYPE,
        ];
    }

    public function getDataForFreeShippingAction(): iterable
    {
        yield [
            ['free_shipping' => true],
            CartRuleAction::buildFreeShipping(),
        ];
        yield [
            [
                'free_shipping' => true,
                'gift_product' => [
                    ['product_id' => 17],
                ],
            ],
            CartRuleAction::buildFreeShipping(new GiftProduct(17)),
        ];
        yield [
            [
                'free_shipping' => true,
                'gift_product' => [
                    ['product_id' => 17, 'combination_id' => 12],
                ],
            ],
            CartRuleAction::buildFreeShipping(new GiftProduct(17, 12)),
        ];
    }

    public function getDataForGiftProductAction(): iterable
    {
        yield 'gift product action' => [
            [
                'gift_product' => [
                    ['product_id' => 16],
                ],
            ],
            CartRuleAction::buildGiftProduct(new GiftProduct(16)),
        ];

        yield 'gift product with combination action' => [
            [
                'gift_product' => [
                    [
                        'product_id' => 16,
                        'combination_id' => 18,
                    ],
                ],
            ],
            CartRuleAction::buildGiftProduct(new GiftProduct(16, 18)),
        ];
    }

    public function getDataForAmountDiscountAction(): iterable
    {
        yield 'amount action' => [
            [
                'discount' => [
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                    'reduction' => [
                        'type' => Reduction::TYPE_AMOUNT,
                        'value' => '11.23',
                        'include_tax' => true,
                        'currency' => 3,
                    ],
                ],
            ],
            CartRuleAction::buildAmountDiscount(
                new Money(new DecimalNumber('11.23'), new CurrencyId(3), true),
                false,
                new DiscountApplicationType(DiscountApplicationType::ORDER_WITHOUT_SHIPPING)
            ),
        ];

        yield 'amount with specific product' => [
            [
                'discount' => [
                    'discount_application' => DiscountApplicationType::SPECIFIC_PRODUCT,
                    'specific_product' => [['id' => 10]],
                    'reduction' => [
                        'type' => Reduction::TYPE_AMOUNT,
                        'value' => '11.23',
                        'include_tax' => true,
                        'currency' => 3,
                    ],
                ],
            ],
            CartRuleAction::buildAmountDiscount(
                new Money(new DecimalNumber('11.23'), new CurrencyId(3), true),
                false,
                new DiscountApplicationType(DiscountApplicationType::SPECIFIC_PRODUCT, 10)
            ),
        ];

        yield 'amount with specific product and gift' => [
            [
                'discount' => [
                    'discount_application' => DiscountApplicationType::SPECIFIC_PRODUCT,
                    'specific_product' => [['id' => 10]],
                    'reduction' => [
                        'type' => Reduction::TYPE_AMOUNT,
                        'value' => '11.23',
                        'include_tax' => true,
                        'currency' => 3,
                    ],
                ],
                'gift_product' => [
                    ['product_id' => 14],
                ],
            ],
            CartRuleAction::buildAmountDiscount(
                new Money(new DecimalNumber('11.23'), new CurrencyId(3), true),
                false,
                new DiscountApplicationType(DiscountApplicationType::SPECIFIC_PRODUCT, 10),
                new GiftProduct(14)
            ),
        ];

        yield 'amount with specific product, gift combination and free shipping' => [
            [
                'free_shipping' => true,
                'discount' => [
                    'discount_application' => DiscountApplicationType::SPECIFIC_PRODUCT,
                    'specific_product' => [['id' => 10]],
                    'reduction' => [
                        'type' => Reduction::TYPE_AMOUNT,
                        'value' => '11.23',
                        'include_tax' => true,
                        'currency' => 3,
                    ],
                ],
                'gift_product' => [
                    [
                        'product_id' => 14,
                        'combination_id' => 12,
                    ],
                ],
            ],
            CartRuleAction::buildAmountDiscount(
                new Money(new DecimalNumber('11.23'), new CurrencyId(3), true),
                true,
                new DiscountApplicationType(DiscountApplicationType::SPECIFIC_PRODUCT, 10),
                new GiftProduct(14, 12)
            ),
        ];
    }

    public function getDataForPercentageDiscountAction(): iterable
    {
        yield 'percent action with free_shipping not set' => [
            [
                'discount' => [
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                    'reduction' => [
                        'type' => Reduction::TYPE_PERCENTAGE,
                        'value' => '11.23',
                    ],
                    'apply_to_discounted_products' => false,
                ],
            ],
            CartRuleAction::buildPercentageDiscount(
                new DecimalNumber('11.23'),
                false,
                false,
                new DiscountApplicationType(DiscountApplicationType::ORDER_WITHOUT_SHIPPING)
            ),
        ];

        yield 'percent action with free_shipping set to false' => [
            [
                'free_shipping' => false,
                'discount' => [
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                    'reduction' => [
                        'type' => Reduction::TYPE_PERCENTAGE,
                        'value' => '11.23',
                    ],
                    'apply_to_discounted_products' => false,
                ],
            ],
            CartRuleAction::buildPercentageDiscount(
                new DecimalNumber('11.23'),
                false,
                false,
                new DiscountApplicationType(DiscountApplicationType::ORDER_WITHOUT_SHIPPING)
            ),
        ];

        yield 'percent action with free shipping' => [
            [
                'free_shipping' => true,
                'discount' => [
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                    'reduction' => [
                        'type' => Reduction::TYPE_PERCENTAGE,
                        'value' => '11.23',
                    ],
                    'apply_to_discounted_products' => false,
                ],
            ],
            CartRuleAction::buildPercentageDiscount(
                new DecimalNumber('11.23'),
                false,
                true,
                new DiscountApplicationType(DiscountApplicationType::ORDER_WITHOUT_SHIPPING)
            ),
        ];

        yield 'percent action applies to discounted products and specific product' => [
            [
                'discount' => [
                    'discount_application' => DiscountApplicationType::SPECIFIC_PRODUCT,
                    'specific_product' => [['id' => 13]],
                    'reduction' => [
                        'type' => Reduction::TYPE_PERCENTAGE,
                        'value' => '50',
                    ],
                    'apply_to_discounted_products' => true,
                ],
            ],
            CartRuleAction::buildPercentageDiscount(
                new DecimalNumber('50'),
                true,
                false,
                new DiscountApplicationType(DiscountApplicationType::SPECIFIC_PRODUCT, 13)
            ),
        ];

        yield 'percent action with gift product' => [
            [
                'discount' => [
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                    'reduction' => [
                        'type' => Reduction::TYPE_PERCENTAGE,
                        'value' => '50',
                    ],
                ],
                'gift_product' => [
                    ['product_id' => 15],
                ],
            ],
            CartRuleAction::buildPercentageDiscount(
                new DecimalNumber('50'),
                false,
                false,
                new DiscountApplicationType(DiscountApplicationType::ORDER_WITHOUT_SHIPPING),
                new GiftProduct(15)
            ),
        ];

        yield 'percent action with gift combination' => [
            [
                'discount' => [
                    'discount_application' => DiscountApplicationType::ORDER_WITHOUT_SHIPPING,
                    'reduction' => [
                        'type' => Reduction::TYPE_PERCENTAGE,
                        'value' => '50',
                    ],
                ],
                'gift_product' => [
                    [
                        'product_id' => 15,
                        'combination_id' => 32,
                    ],
                ],
            ],
            CartRuleAction::buildPercentageDiscount(
                new DecimalNumber('50'),
                false,
                false,
                new DiscountApplicationType(DiscountApplicationType::ORDER_WITHOUT_SHIPPING),
                new GiftProduct(15, 32)
            ),
        ];
    }

    private function getCartRuleActionBuilder(): CartRuleActionBuilder
    {
        return new CartRuleActionBuilder();
    }
}
