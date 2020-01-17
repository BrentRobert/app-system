<?php declare(strict_types=1);

namespace Swag\SaasConnect\Test\Core\Content\App\Action;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\SaasConnect\Core\Content\App\Action\ActionButtonLoader;

class ActionButtonLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var ActionButtonLoader
     */
    private $actionButtonLoader;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $app1OrderDetailButtonId;

    /**
     * @var string
     */
    private $app1ProductDetailButtonId;

    /**
     * @var string
     */
    private $app1OrderListButtonId;

    /**
     * @var string
     */
    private $app2OrderDetailButtonId;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->actionButtonLoader = $this->getContainer()->get(ActionButtonLoader::class);
        $this->context = Context::createDefaultContext();

        $this->app1OrderDetailButtonId = Uuid::randomHex();
        $this->app1ProductDetailButtonId = Uuid::randomHex();
        $this->app1OrderListButtonId = Uuid::randomHex();
        $this->app2OrderDetailButtonId = Uuid::randomHex();
    }

    public function testLoadActionButtonsForView(): void
    {
        $this->registerActionButtons();

        $loadedActionButtons = $this->actionButtonLoader->loadActionButtonsForView('order', 'detail', $this->context);

        usort($loadedActionButtons, function (array $a, array $b): int {
            return $a['app'] <=> $b['app'];
        });

        static::assertEquals([
            [
                'app' => 'App1',
                'id' => $this->app1OrderDetailButtonId,
                'label' => [
                    'en-GB' => 'Order Detail App1'
                ],
                'action' => 'orderDetailApp1',
                'url' => 'app1.com/order/detail',
                'openNewTab' => false
            ], [
                'app' => 'App2',
                'id' => $this->app2OrderDetailButtonId,
                'label' => [
                    'en-GB' => 'Order Detail App2'
                ],
                'action' => 'orderDetailApp2',
                'url' => 'app2.com/order/detail',
                'openNewTab' => false
            ]
        ], $loadedActionButtons);
    }

    private function registerActionButtons(): void
    {
        $this->appRepository->create([[
            'name' => 'App1',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'actionButtons' => [
                [
                    'id' => $this->app1OrderDetailButtonId,
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'orderDetailApp1',
                    'label' => 'Order Detail App1',
                    'url' => 'app1.com/order/detail'
                ],
                [
                    'id' => $this->app1ProductDetailButtonId,
                    'entity' => 'product',
                    'view' => 'detail',
                    'action' => 'productDetailApp1',
                    'label' => 'Product Detail App1',
                    'url' => 'app1.com/product/detail'
                ],
                [
                    'id' => $this->app1OrderListButtonId,
                    'entity' => 'order',
                    'view' => 'listing',
                    'action' => 'orderListApp1',
                    'label' => 'Order List App1',
                    'url' => 'app1.com/order/list'
                ]
            ]
        ], [
            'name' => 'App2',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'actionButtons' => [
                [
                    'id' => $this->app2OrderDetailButtonId,
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'orderDetailApp2',
                    'label' => 'Order Detail App2',
                    'url' => 'app2.com/order/detail'
                ],
            ]
        ]], $this->context);
    }
}