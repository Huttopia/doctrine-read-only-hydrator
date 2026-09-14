<?php

namespace steevanb\DoctrineReadOnlyHydrator\Tests\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use steevanb\DoctrineReadOnlyHydrator\Hydrator\ReadOnlyHydrator;
use steevanb\DoctrineReadOnlyHydrator\Tests\Fixtures\EdgeCaseEntity;
use steevanb\DoctrineReadOnlyHydrator\Tests\Fixtures\EdgeCaseParent;

/**
 * Le proxie généré doit rester compatible avec l'entité dont il hérite, quelle que soit la
 * signature d'origine, et déléguer sans passer par un callable "parent" (déprécié depuis PHP 8.2).
 */
#[CoversClass(ReadOnlyHydrator::class)]
class ReadOnlyHydratorTest extends TestCase
{
    private const PROXY_NAMESPACE = 'ReadOnlyProxies\\steevanb\\DoctrineReadOnlyHydrator\\Tests\\Fixtures';

    public function testGeneratedProxyCompilesAndStaysCompatible(): void
    {
        $methods = [];
        foreach ((new \ReflectionClass(EdgeCaseEntity::class))->getMethods() as $reflectionMethod) {
            if ($reflectionMethod->getDeclaringClass()->getName() === EdgeCaseEntity::class) {
                $methods[] = $this->generateMethod($reflectionMethod);
            }
        }

        static::assertCount(9, $methods);

        // eval() compile la classe : une signature invalide ou incompatible avec la classe mère
        // déclenche une ParseError ou une Error ici même
        eval($this->wrapInProxyClass('EdgeCaseProxy', EdgeCaseEntity::class, $methods));

        $proxy = new \ReflectionClass(self::PROXY_NAMESPACE . '\\EdgeCaseProxy');

        static::assertTrue($proxy->getMethod('variadic')->getParameters()[0]->isVariadic());
        static::assertTrue($proxy->getMethod('byReference')->getParameters()[0]->isPassedByReference());
        static::assertSame('static', (string) $proxy->getMethod('staticReturn')->getReturnType());
        static::assertSame(
            EdgeCaseEntity::class,
            (string) $proxy->getMethod('selfParameter')->getParameters()[0]->getType()
        );
        static::assertSame(
            EdgeCaseParent::class,
            (string) $proxy->getMethod('parentParameter')->getParameters()[0]->getType()
        );
    }

    public function testDefaultValuesSurviveGeneration(): void
    {
        $method = $this->generateMethod(new \ReflectionMethod(EdgeCaseEntity::class, 'tricky'));
        eval($this->wrapInProxyClass('DefaultsProxy', EdgeCaseEntity::class, [$method]));

        $parameters = (new \ReflectionClass(self::PROXY_NAMESPACE . '\\DefaultsProxy'))
            ->getMethod('tricky')
            ->getParameters()
        ;

        static::assertSame('l\'apostrophe', $parameters[0]->getDefaultValue());
        static::assertSame(['a' => 1, 'b' => [2, 3]], $parameters[1]->getDefaultValue());
        static::assertSame(1.0, $parameters[2]->getDefaultValue());
        static::assertNull($parameters[3]->getDefaultValue());
        static::assertSame(EdgeCaseEntity::LOCAL, $parameters[4]->getDefaultValue());
    }

    public function testDelegationDoesNotUseTheParentCallable(): void
    {
        $method = $this->generateMethod(new \ReflectionMethod(EdgeCaseEntity::class, 'variadic'));

        static::assertStringContainsString('parent::variadic(...func_get_args())', $method);
        static::assertStringNotContainsString('call_user_func_array', $method);
    }

    public function testParentReturnTypeIsRejected(): void
    {
        $entity = new class extends EdgeCaseParent {
            public function parentReturn(): parent
            {
                return $this;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIs('Function with return type parent can\'t be overloaded.');

        $this->generateMethod(new \ReflectionMethod($entity, 'parentReturn'));
    }

    /** @param string[] $methods */
    private function wrapInProxyClass(string $proxyClassName, string $entityClassName, array $methods): string
    {
        $namespace = self::PROXY_NAMESPACE;
        $methodsCode = implode("\n\n", $methods);

        return <<<PHP
            namespace $namespace;

            class $proxyClassName extends \\$entityClassName
            {
                public function assertReadOnlyPropertiesAreLoaded(array \$properties) {}

            $methodsCode
            }
            PHP;
    }

    private function generateMethod(\ReflectionMethod $reflectionMethod): string
    {
        $hydrator = (new \ReflectionClass(ReadOnlyHydrator::class))->newInstanceWithoutConstructor();

        return \Closure::bind(
            fn (\ReflectionMethod $method): string => $this->getPhpForMethod($method, ['property']),
            $hydrator,
            ReadOnlyHydrator::class
        )($reflectionMethod);
    }
}
