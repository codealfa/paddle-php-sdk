<?php

declare(strict_types=1);

namespace Paddle\SDK\Tests\Functional\Resources\Prices;

use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as MockClient;
use Paddle\SDK\Client;
use Paddle\SDK\Entities\Shared\CountryCode;
use Paddle\SDK\Entities\Shared\CurrencyCode;
use Paddle\SDK\Entities\Shared\CustomData;
use Paddle\SDK\Entities\Shared\Interval;
use Paddle\SDK\Entities\Shared\Money;
use Paddle\SDK\Entities\Shared\PriceQuantity;
use Paddle\SDK\Entities\Shared\PriceTrialPeriod;
use Paddle\SDK\Entities\Shared\Status;
use Paddle\SDK\Entities\Shared\TaxMode;
use Paddle\SDK\Entities\Shared\TimePeriod;
use Paddle\SDK\Entities\Shared\UnitPriceOverride;
use Paddle\SDK\Environment;
use Paddle\SDK\Options;
use Paddle\SDK\Resources\Prices\Operations\CreatePrice;
use Paddle\SDK\Resources\Prices\Operations\List\Includes;
use Paddle\SDK\Resources\Prices\Operations\ListPrices;
use Paddle\SDK\Resources\Prices\Operations\UpdatePrice;
use Paddle\SDK\Resources\Shared\Operations\List\Pager;
use Paddle\SDK\Tests\Utils\ReadsFixtures;
use Paddle\SDK\Undefined;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PricesClientTest extends TestCase
{
    use ReadsFixtures;

    private MockClient $mockClient;
    private Client $client;

    public function setUp(): void
    {
        $this->mockClient = new MockClient();
        $this->client = new Client(
            apiKey: 'API_KEY_PLACEHOLDER',
            options: new Options(Environment::SANDBOX),
            httpClient: $this->mockClient);
    }

    /**
     * @test
     *
     * @dataProvider createOperationsProvider
     */
    public function it_uses_expected_payload_on_create(
        CreatePrice $operation,
        ResponseInterface $response,
        string $expectedBody,
    ): void {
        $this->mockClient->addResponse($response);
        $this->client->prices->create($operation);
        $request = $this->mockClient->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertEquals('POST', $request->getMethod());
        self::assertEquals(Environment::SANDBOX->baseUrl() . '/prices', urldecode((string) $request->getUri()));
        self::assertJsonStringEqualsJsonString($expectedBody, (string) $request->getBody());
    }

    public static function createOperationsProvider(): \Generator
    {
        yield 'Basic Create' => [
            new CreatePrice(
                description: 'Monthly (per seat)',
                productId: 'pro_01h7zcgmdc6tmwtjehp3sh7azf',
                unitPrice: new Money('500', CurrencyCode::USD()),
            ),
            new Response(200, body: self::readRawJsonFixture('response/minimal_entity')),
            self::readRawJsonFixture('request/create_basic'),
        ];

        yield 'Create with Data' => [
            new CreatePrice(
                description: 'Weekly (per seat)',
                productId: 'pro_01gsz4t5hdjse780zja8vvr7jg',
                unitPrice: new Money('1000', CurrencyCode::GBP()),
                name: 'Weekly',
                unitPriceOverrides: [
                    new UnitPriceOverride(
                        [CountryCode::CA(), CountryCode::US()],
                        new Money('5000', CurrencyCode::USD()),
                    ),
                ],
                taxMode: TaxMode::AccountSetting(),
                trialPeriod: new TimePeriod(Interval::Week(), 1),
                billingCycle: new TimePeriod(Interval::Year(), 1),
                quantity: new PriceQuantity(1, 1),
                customData: new CustomData(['foo' => 'bar']),
            ),
            new Response(200, body: self::readRawJsonFixture('response/full_entity')),
            self::readRawJsonFixture('request/create_full'),
        ];

        yield 'Create with Paid Trial' => [
            new CreatePrice(
                description: 'Annual plan with an introductory first year',
                productId: 'pro_01gsz4t5hdjse780zja8vvr7jg',
                unitPrice: new Money('10000', CurrencyCode::USD()),
                trialPeriod: new PriceTrialPeriod(
                    interval: Interval::Year(),
                    frequency: 1,
                    requiresPaymentMethod: true,
                    unitPrice: new Money('5000', CurrencyCode::USD()),
                    unitPriceOverrides: [
                        new UnitPriceOverride([CountryCode::GB()], new Money('4000', CurrencyCode::GBP())),
                    ],
                ),
                billingCycle: new TimePeriod(Interval::Year(), 1),
            ),
            new Response(200, body: self::readRawJsonFixture('response/paid_trial_entity')),
            self::readRawJsonFixture('request/create_paid_trial'),
        ];
    }

    /**
     * @test
     *
     * @dataProvider updateOperationsProvider
     */
    public function it_uses_expected_payload_on_update(
        UpdatePrice $operation,
        ResponseInterface $response,
        string $expectedBody,
    ): void {
        $this->mockClient->addResponse($response);
        $this->client->prices->update('pro_01h7zcgmdc6tmwtjehp3sh7azf', $operation);
        $request = $this->mockClient->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertEquals('PATCH', $request->getMethod());
        self::assertEquals(Environment::SANDBOX->baseUrl() . '/prices/pro_01h7zcgmdc6tmwtjehp3sh7azf', urldecode((string) $request->getUri()));
        self::assertJsonStringEqualsJsonString($expectedBody, (string) $request->getBody());
    }

    public static function updateOperationsProvider(): \Generator
    {
        yield 'Update Single' => [
            new UpdatePrice(name: 'Annually'),
            new Response(200, body: self::readRawJsonFixture('response/full_entity')),
            self::readRawJsonFixture('request/update_single'),
        ];

        yield 'Update Partial' => [
            new UpdatePrice(name: 'Annually', unitPrice: new Money('100000', CurrencyCode::GBP())),
            new Response(200, body: self::readRawJsonFixture('response/full_entity')),
            self::readRawJsonFixture('request/update_partial'),
        ];

        yield 'Update All' => [
            new UpdatePrice(
                description: 'Annually (per seat)',
                name: 'Annually',
                billingCycle: new TimePeriod(Interval::Year(), 1),
                trialPeriod: new TimePeriod(Interval::Month(), 1),
                taxMode: TaxMode::External(),
                unitPrice: new Money('100000', CurrencyCode::GBP()),
                unitPriceOverrides: [new UnitPriceOverride([CountryCode::US()], new Money('200000', CurrencyCode::USD()))],
                quantity: new PriceQuantity(1, 10),
                customData: new CustomData(['features' => ['reports' => true]]),
            ),
            new Response(200, body: self::readRawJsonFixture('response/full_entity')),
            self::readRawJsonFixture('request/update_full'),
        ];

        yield 'Update to Paid Trial' => [
            new UpdatePrice(
                trialPeriod: new PriceTrialPeriod(
                    interval: Interval::Year(),
                    frequency: 1,
                    requiresPaymentMethod: true,
                    unitPrice: new Money('5000', CurrencyCode::USD()),
                ),
            ),
            new Response(200, body: self::readRawJsonFixture('response/paid_trial_entity')),
            self::readRawJsonFixture('request/update_paid_trial'),
        ];

        yield 'Update to Free Trial without Payment Method' => [
            new UpdatePrice(
                trialPeriod: new PriceTrialPeriod(
                    interval: Interval::Day(),
                    frequency: 14,
                    requiresPaymentMethod: false,
                    unitPrice: null,
                ),
            ),
            new Response(200, body: self::readRawJsonFixture('response/full_entity')),
            self::readRawJsonFixture('request/update_trial_without_price'),
        ];
    }

    /**
     * @test
     *
     * @dataProvider listOperationsProvider
     */
    public function list_hits_expected_uri(
        ListPrices $operation,
        ResponseInterface $response,
        string $expectedUri,
    ): void {
        $this->mockClient->addResponse($response);
        $this->client->prices->list($operation);
        $request = $this->mockClient->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals($expectedUri, urldecode((string) $request->getUri()));
    }

    public static function listOperationsProvider(): \Generator
    {
        yield 'Default' => [
            new ListPrices(),
            new Response(200, body: self::readRawJsonFixture('response/list_default')),
            sprintf('%s/prices', Environment::SANDBOX->baseUrl()),
        ];

        yield 'Default Paged' => [
            new ListPrices(new Pager()),
            new Response(200, body: self::readRawJsonFixture('response/list_default')),
            sprintf(
                '%s/prices?order_by=id[asc]&per_page=50',
                Environment::SANDBOX->baseUrl(),
            ),
        ];

        yield 'Default Paged with After' => [
            new ListPrices(new Pager(after: 'pro_01gsz4s0w61y0pp88528f1wvvb')),
            new Response(200, body: self::readRawJsonFixture('response/list_default')),
            sprintf(
                '%s/prices?after=pro_01gsz4s0w61y0pp88528f1wvvb&order_by=id[asc]&per_page=50',
                Environment::SANDBOX->baseUrl(),
            ),
        ];

        yield 'NotificationStatus Filtered' => [
            new ListPrices(statuses: [Status::Archived()]),
            new Response(200, body: self::readRawJsonFixture('response/list_default')),
            sprintf('%s/prices?status=archived', Environment::SANDBOX->baseUrl()),
        ];

        yield 'ID Filtered' => [
            new ListPrices(ids: ['pri_01gsz4s0w61y0pp88528f1wvvb']),
            new Response(200, body: self::readRawJsonFixture('response/list_default')),
            sprintf('%s/prices?id=pri_01gsz4s0w61y0pp88528f1wvvb', Environment::SANDBOX->baseUrl()),
        ];

        yield 'Multiple ID Filtered' => [
            new ListPrices(ids: ['pri_01he6hp8cg49jjf1pdjf6d5yw1', 'pri_01h1vjes1y163xfj1rh1tkfb65']),
            new Response(200, body: self::readRawJsonFixture('response/list_default')),
            sprintf(
                '%s/prices?id=pri_01he6hp8cg49jjf1pdjf6d5yw1,pri_01h1vjes1y163xfj1rh1tkfb65',
                Environment::SANDBOX->baseUrl(),
            ),
        ];

        yield 'Product ID Filtered' => [
            new ListPrices(productIds: ['pro_01gsz4s0w61y0pp88528f1wvvb']),
            new Response(200, body: self::readRawJsonFixture('response/list_default')),
            sprintf('%s/prices?product_id=pro_01gsz4s0w61y0pp88528f1wvvb', Environment::SANDBOX->baseUrl()),
        ];

        yield 'Multiple Product ID Filtered' => [
            new ListPrices(productIds: ['pro_01he6hp8cg49jjf1pdjf6d5yw1', 'pro_01h1vjes1y163xfj1rh1tkfb65']),
            new Response(200, body: self::readRawJsonFixture('response/list_default')),
            sprintf(
                '%s/prices?product_id=pro_01he6hp8cg49jjf1pdjf6d5yw1,pro_01h1vjes1y163xfj1rh1tkfb65',
                Environment::SANDBOX->baseUrl(),
            ),
        ];

        yield 'Recurring Filtered' => [
            new ListPrices(recurring: true),
            new Response(200, body: self::readRawJsonFixture('response/list_default')),
            sprintf('%s/prices?recurring=true', Environment::SANDBOX->baseUrl()),
        ];

        yield 'With Includes' => [
            new ListPrices(includes: [Includes::Product()]),
            new Response(200, body: self::readRawJsonFixture('response/list_default')),
            sprintf('%s/prices?include=product', Environment::SANDBOX->baseUrl()),
        ];
    }

    /**
     * @test
     *
     * @dataProvider getRequestProvider
     *
     * @param Includes[] $includes
     */
    public function get_hits_expected_uri(
        array $includes,
        ResponseInterface $response,
        string $expectedUri,
    ): void {
        $this->mockClient->addResponse($response);
        $this->client->prices->get('pri_01h7zcgmdc6tmwtjehp3sh7azf', $includes);
        $request = $this->mockClient->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals($expectedUri, urldecode((string) $request->getUri()));
    }

    public static function getRequestProvider(): \Generator
    {
        yield 'Without Includes' => [
            [],
            new Response(200, body: self::readRawJsonFixture('response/full_entity')),
            sprintf('%s/prices/pri_01h7zcgmdc6tmwtjehp3sh7azf', Environment::SANDBOX->baseUrl()),
        ];

        yield 'With Includes' => [
            [Includes::Product()],
            new Response(200, body: self::readRawJsonFixture('response/full_entity_with_includes')),
            sprintf('%s/prices/pri_01h7zcgmdc6tmwtjehp3sh7azf?include=product', Environment::SANDBOX->baseUrl()),
        ];
    }

    /**
     * @test
     */
    public function get_reads_paid_trial(): void
    {
        $this->mockClient->addResponse(new Response(200, body: self::readRawJsonFixture('response/paid_trial_entity')));
        $price = $this->client->prices->get('pri_01hzpaidtrial00000000000000');

        $trial = $price->trialPeriod;
        self::assertInstanceOf(PriceTrialPeriod::class, $trial);
        self::assertEquals(Interval::Year(), $trial->interval);
        self::assertSame(1, $trial->frequency);
        self::assertTrue($trial->requiresPaymentMethod);
        self::assertInstanceOf(Money::class, $trial->unitPrice);
        self::assertSame('5000', $trial->unitPrice->amount);
        self::assertEquals(CurrencyCode::USD(), $trial->unitPrice->currencyCode);
        self::assertIsArray($trial->unitPriceOverrides);
        self::assertCount(1, $trial->unitPriceOverrides);
        self::assertSame('4000', $trial->unitPriceOverrides[0]->unitPrice->amount);
        self::assertSame('10000', $price->unitPrice->amount);
    }

    /**
     * @test
     */
    public function get_reads_free_trial(): void
    {
        $this->mockClient->addResponse(new Response(200, body: self::readRawJsonFixture('response/full_entity')));
        $trial = $this->client->prices->get('pri_01he6hp8cg49jjf1pdjf6d5yw1')->trialPeriod;

        self::assertInstanceOf(PriceTrialPeriod::class, $trial);
        self::assertTrue($trial->requiresPaymentMethod);
        self::assertNull($trial->unitPrice);
        self::assertSame([], $trial->unitPriceOverrides);
    }

    /**
     * @test
     */
    public function trial_from_response_without_paid_trial_fields_is_free_and_requires_payment_method(): void
    {
        $trial = PriceTrialPeriod::from(['interval' => 'week', 'frequency' => 1]);

        self::assertTrue($trial->requiresPaymentMethod);
        self::assertNull($trial->unitPrice);
        self::assertSame([], $trial->unitPriceOverrides);
    }

    /**
     * @test
     */
    public function trial_leaves_out_fields_that_are_not_set(): void
    {
        $trial = new PriceTrialPeriod(Interval::Month(), 1);

        self::assertInstanceOf(Undefined::class, $trial->unitPrice);
        self::assertSame(['interval' => 'month', 'frequency' => 1], json_decode(json_encode($trial), true));
    }
}
