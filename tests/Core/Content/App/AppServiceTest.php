<?php declare(strict_types=1);

namespace Swag\SaasConnect\Test\Core\Content\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\SaasConnect\Core\Content\App\Aggregate\ActionButton\ActionButtonEntity;
use Swag\SaasConnect\Core\Content\App\AppCollection;
use Swag\SaasConnect\Core\Content\App\AppService;
use Swag\SaasConnect\Core\Content\App\Lifecycle\AppLifecycle;
use Swag\SaasConnect\Core\Content\App\Lifecycle\AppLifecycleIterator;
use Swag\SaasConnect\Core\Content\App\Lifecycle\AppLoader;
use Swag\SaasConnect\Core\Content\App\Manifest\Manifest;
use Swag\SaasConnect\Test\StorefrontAppRegistryTestBehaviour;

class AppServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontAppRegistryTestBehaviour;

    /**
     * @var AppService
     */
    private $appService;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $actionButtonRepository;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('saas_app.repository');
        $this->actionButtonRepository = $this->getContainer()->get('saas_app_action_button.repository');

        $this->appService = new AppService(
            new AppLifecycleIterator(
                $this->appRepository,
                new AppLoader(__DIR__ . '/Manifest/_fixtures/test')
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );

        $this->context = Context::createDefaultContext();
    }

    public function testRefreshInstallsNewApp(): void
    {
        $this->appService->refreshApps(true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('SwagApp', $apps->first()->getName());

        $this->assertDefaultActionButtons();
    }

    public function testRefreshUpdatesApp(): void
    {
        $this->appRepository->create([[
            'name' => 'SwagApp',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
        ]], $this->context);

        $this->appService->refreshApps(true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('SwagApp', $apps->first()->getName());
        static::assertEquals('1.0.0', $apps->first()->getVersion());
        static::assertNotEquals('test', $apps->first()->getTranslation('label'));

        $this->assertDefaultActionButtons();
    }

    public function testRefreshAppIsUntouched(): void
    {
        $this->appRepository->create([[
            'name' => 'SwagApp',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '1.0.0',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
        ]], $this->context);

        $this->appService->refreshApps(true, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('SwagApp', $apps->first()->getName());
        static::assertEquals('1.0.0', $apps->first()->getVersion());
        static::assertEquals('test', $apps->first()->getTranslation('label'));
    }

    public function testRefreshDeletesApp(): void
    {
        $appId = Uuid::randomHex();
        $this->appRepository->create([[
            'id' => $appId,
            'name' => 'Test',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
        ]], $this->context);

        $this->appService->refreshApps(true, $this->context);

        $apps = $this->appRepository->searchIds(new Criteria([$appId]), $this->context)->getIds();
        static::assertCount(0, $apps);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $apps = $this->actionButtonRepository->searchIds($criteria, $this->context)->getIds();
        static::assertCount(0, $apps);
    }

    public function testGetRefreshableApps(): void
    {
        $this->appRepository->create([
            [
                'name' => 'Test',
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'actionButtons' => [
                    [
                        'entity' => 'order',
                        'view' => 'detail',
                        'action' => 'test',
                        'label' => 'test',
                        'url' => 'test.com',
                    ],
                ],
                'integration' => [
                    'label' => 'test',
                    'writeAccess' => false,
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'SwagApp',
                ],
            ],
            [
                'name' => 'SwagApp',
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'actionButtons' => [
                    [
                        'entity' => 'order',
                        'view' => 'detail',
                        'action' => 'test',
                        'label' => 'test',
                        'url' => 'test.com',
                    ],
                ],
                'integration' => [
                    'label' => 'test',
                    'writeAccess' => false,
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'SwagApp',
                ],
            ],
        ], $this->context);

        $appService = new AppService(
            new AppLifecycleIterator(
                $this->appRepository,
                new AppLoader(__DIR__ . '/Manifest/_fixtures')
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );
        $refreshableApps = $appService->getRefreshableAppInfo($this->context);

        static::assertCount(2, $refreshableApps->getToBeInstalled());
        static::assertCount(1, $refreshableApps->getToBeUpdated());
        static::assertCount(1, $refreshableApps->getToBeDeleted());

        static::assertInstanceOf(Manifest::class, $refreshableApps->getToBeInstalled()[0]);
        static::assertInstanceOf(Manifest::class, $refreshableApps->getToBeUpdated()[0]);
        static::assertEquals('Test', $refreshableApps->getToBeDeleted()[0]);
    }

    public function testInstallFailureDoesNotAffectAllApps(): void
    {
        $appService = new AppService(
            new AppLifecycleIterator(
                $this->appRepository,
                new AppLoader(__DIR__ . '/Manifest/_fixtures/')
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );
        $fails = $appService->refreshApps(true, $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(2, $apps);

        static::assertCount(1, $fails);
    }

    private function assertDefaultActionButtons(): void
    {
        $actionButtons = $this->actionButtonRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $actionButtons);
        $actionNames = array_map(function (ActionButtonEntity $actionButton) {
            return $actionButton->getAction();
        }, $actionButtons->getElements());

        static::assertContains('viewOrder', $actionNames);
        static::assertContains('doStuffWithProducts', $actionNames);
    }
}
