<?php declare(strict_types=1);

namespace Swag\SaasConnect\Test\Core\Framework\AppUrlChangeResolver;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Swag\SaasConnect\Core\Framework\AppUrlChangeResolver\AppUrlChangeResolverInterface;
use Swag\SaasConnect\Core\Framework\AppUrlChangeResolver\AppUrlChangeResolverNotFoundException;
use Swag\SaasConnect\Core\Framework\AppUrlChangeResolver\AppUrlChangeResolverStrategy;

class AppUrlChangeResolverStrategyTest extends TestCase
{
    /**
     * @var MockObject|AppUrlChangeResolverInterface
     */
    private $firstStrategy;

    /**
     * @var MockObject|AppUrlChangeResolverInterface
     */
    private $secondStrategy;

    /**
     * @var AppUrlChangeResolverStrategy
     */
    private $appUrlChangedResolverStrategy;

    public function setUp(): void
    {
        $this->firstStrategy = $this->createMock(AppUrlChangeResolverInterface::class);
        $this->firstStrategy->method('getName')
            ->willReturn('FirstStrategy');

        $this->secondStrategy = $this->createMock(AppUrlChangeResolverInterface::class);
        $this->secondStrategy->method('getName')
            ->willReturn('SecondStrategy');

        $this->appUrlChangedResolverStrategy = new AppUrlChangeResolverStrategy([
            $this->firstStrategy,
            $this->secondStrategy,
        ]);
    }

    public function testItCallsRightStrategy(): void
    {
        $this->firstStrategy->expects(static::once())
            ->method('resolve');

        $this->secondStrategy->expects(static::never())
            ->method('resolve');

        $this->appUrlChangedResolverStrategy->resolve('FirstStrategy', Context::createDefaultContext());
    }

    public function testItThrowsOnUnknownStrategy(): void
    {
        $this->firstStrategy->expects(static::never())
            ->method('resolve');

        $this->secondStrategy->expects(static::never())
            ->method('resolve');

        static::expectException(AppUrlChangeResolverNotFoundException::class);
        $this->appUrlChangedResolverStrategy->resolve('ThirdStrategy', Context::createDefaultContext());
    }
}
