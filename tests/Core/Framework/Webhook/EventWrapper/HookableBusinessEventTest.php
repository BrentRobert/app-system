<?php declare(strict_types=1);

namespace Swag\SaasConnect\Test\Core\Framework\Webhook\EventWrapper;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;
use Swag\SaasConnect\Core\Framework\Api\Acl\AclPrivilegeCollection;
use Swag\SaasConnect\Core\Framework\Webhook\BusinessEventEncoder;
use Swag\SaasConnect\Core\Framework\Webhook\EventWrapper\HookableBusinessEvent;
use Swag\SaasConnect\Test\Core\Framework\Webhook\_fixtures\BusinessEvents\ArrayBusinessEvent;
use Swag\SaasConnect\Test\Core\Framework\Webhook\_fixtures\BusinessEvents\CollectionBusinessEvent;
use Swag\SaasConnect\Test\Core\Framework\Webhook\_fixtures\BusinessEvents\EntityBusinessEvent;
use Swag\SaasConnect\Test\Core\Framework\Webhook\_fixtures\BusinessEvents\NestedEntityBusinessEvent;
use Swag\SaasConnect\Test\Core\Framework\Webhook\_fixtures\BusinessEvents\ScalarBusinessEvent;
use Swag\SaasConnect\Test\Core\Framework\Webhook\_fixtures\BusinessEvents\StructuredArrayObjectBusinessEvent;
use Swag\SaasConnect\Test\Core\Framework\Webhook\_fixtures\BusinessEvents\StructuredObjectBusinessEvent;
use Swag\SaasConnect\Test\Core\Framework\Webhook\_fixtures\BusinessEvents\UnstructuredObjectBusinessEvent;

class HookableBusinessEventTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetter(): void
    {
        $scalarEvent = new ScalarBusinessEvent();
        $event = HookableBusinessEvent::fromBusinessEvent(
            $scalarEvent,
            $this->getContainer()->get(BusinessEventEncoder::class)
        );

        static::assertEquals($scalarEvent->getName(), $event->getName());
        $shopwareVersion = $this->getContainer()->getParameter('kernel.shopware_version');
        static::assertEquals($scalarEvent->getEncodeValues($shopwareVersion), $event->getWebhookPayload());
    }

    /**
     * @dataProvider getEventsWithoutPermissions
     */
    public function testIsAllowedForNonEntityBasedEvents(BusinessEventInterface $rootEvent): void
    {
        $event = HookableBusinessEvent::fromBusinessEvent(
            $rootEvent,
            $this->getContainer()->get(BusinessEventEncoder::class)
        );

        static::assertTrue($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection()));
    }

    /**
     * @dataProvider getEventsWithPermissions
     */
    public function testIsAllowedForEntityBasedEvents(BusinessEventInterface $rootEvent): void
    {
        $event = HookableBusinessEvent::fromBusinessEvent(
            $rootEvent,
            $this->getContainer()->get(BusinessEventEncoder::class)
        );

        $allowedPermissions = new AclPrivilegeCollection([
            TaxDefinition::ENTITY_NAME . ':' . AclPrivilegeCollection::PRIVILEGE_LIST,
            TaxDefinition::ENTITY_NAME . ':' . AclPrivilegeCollection::PRIVILEGE_READ,
        ]);
        static::assertTrue($event->isAllowed(Uuid::randomHex(), $allowedPermissions));

        $notAllowedPermissions = new AclPrivilegeCollection([
            ProductDefinition::ENTITY_NAME . ':' . AclPrivilegeCollection::PRIVILEGE_LIST,
            ProductDefinition::ENTITY_NAME . ':' . AclPrivilegeCollection::PRIVILEGE_READ,
        ]);
        static::assertFalse($event->isAllowed(Uuid::randomHex(), $notAllowedPermissions));
    }

    public function getEventsWithoutPermissions(): array
    {
        return [
            [new ScalarBusinessEvent()],
            [new StructuredObjectBusinessEvent()],
            [new StructuredArrayObjectBusinessEvent()],
            [new UnstructuredObjectBusinessEvent()],
        ];
    }

    public function getEventsWithPermissions(): array
    {
        return [
            [new EntityBusinessEvent($this->getTaxEntity())],
            [new CollectionBusinessEvent($this->getTaxCollection())],
            [new ArrayBusinessEvent($this->getTaxCollection())],
            [new NestedEntityBusinessEvent($this->getTaxEntity())],
        ];
    }

    private function getTaxEntity(): TaxEntity
    {
        /** @var EntityRepositoryInterface $taxRepo */
        $taxRepo = $this->getContainer()->get('tax.repository');

        return $taxRepo->search(new Criteria(), Context::createDefaultContext())->first();
    }

    private function getTaxCollection(): TaxCollection
    {
        /** @var EntityRepositoryInterface $taxRepo */
        $taxRepo = $this->getContainer()->get('tax.repository');

        return $taxRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();
    }
}
