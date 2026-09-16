<?php

namespace steevanb\DoctrineReadOnlyHydrator\Tests\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use steevanb\DoctrineReadOnlyHydrator\Exception\DefaultValueCantBeRenderedException;
use steevanb\DoctrineReadOnlyHydrator\Hydrator\ReadOnlyHydrator;
use steevanb\DoctrineReadOnlyHydrator\Tests\Fixtures\EdgeCaseParent;
use steevanb\DoctrineReadOnlyHydrator\Tests\Fixtures\ParameterCases;
use steevanb\DoctrineReadOnlyHydrator\Tests\Fixtures\Suit;
use steevanb\DoctrineReadOnlyHydrator\Tests\Fixtures\UnrenderableDefault;

/**
 * Le proxie généré vit dans le namespace ReadOnlyProxies\… : toute classe nommée dans une signature
 * doit en sortir pleinement qualifiée, sous peine d'être cherchée dans ce namespace inexistant.
 */
#[CoversClass(ReadOnlyHydrator::class)]
class GetPhpForParameterTest extends TestCase
{
    private const FIXTURES = 'steevanb\\DoctrineReadOnlyHydrator\\Tests\\Fixtures';

    public static function parameterProvider(): iterable
    {
        yield 'sans type' => ['noType', '$value'];
        yield 'scalaire' => ['scalar', 'int $value'];
        yield 'scalaire nullable' => ['nullableScalar', '?int $value'];
        yield 'array' => ['arrayType', 'array $value'];
        yield 'callable' => ['callableType', 'callable $value'];
        yield 'iterable' => ['iterableType', 'iterable $value'];
        yield 'object' => ['objectType', 'object $value'];

        // mixed accepte déjà null : le préfixer de ? ne compile pas
        yield 'mixed' => ['mixedType', 'mixed $value'];

        yield 'classe' => ['classType', '\\' . self::FIXTURES . '\\EdgeCaseParent $value'];
        yield 'classe nullable' => ['nullableClass', '?\\' . self::FIXTURES . '\\EdgeCaseParent $value'];

        // self et parent doivent être résolus : le proxie hérite de l'entité, ils n'y désignent
        // plus les mêmes classes
        yield 'self' => ['selfType', '\\' . self::FIXTURES . '\\ParameterCases $value'];
        yield 'parent' => ['parentType', '\\' . self::FIXTURES . '\\EdgeCaseParent $value'];

        // chaque membre d'une union est qualifié individuellement, et la nullabilité reste portée
        // par le membre null plutôt que par un ? en tête, qui serait une erreur de compilation
        yield 'union' => ['union', '\Countable|array $value'];
        yield 'union nullable' => ['nullableUnion', '\Countable|array|null $value'];
        yield 'intersection' => ['intersection', '\Countable&\ArrayAccess $value'];

        yield 'par référence' => ['byReference', 'array &$value'];
        yield 'variadique' => ['variadic', 'int ...$value'];
        yield 'variadique par référence' => ['variadicByReference', 'int &...$value'];
    }

    public static function defaultValueProvider(): iterable
    {
        yield 'null' => ['defaultNull', '?string $value = null'];
        yield 'true' => ['defaultTrue', 'bool $value = true'];
        yield 'false' => ['defaultFalse', 'bool $value = false'];
        yield 'entier' => ['defaultInt', 'int $value = 42'];

        // var_export rendrait 1 et changerait le type du défaut
        yield 'flottant' => ['defaultFloat', 'float $value = 1.0'];

        // une apostrophe non échappée casse la signature générée
        yield 'chaîne à échapper' => ['defaultQuotedString', 'string $value = \'l\\\'apostrophe\''];

        yield 'tableau vide' => ['defaultEmptyArray', 'array $value = []'];

        // le contenu d'un tableau non vide doit survivre
        yield 'tableau imbriqué' => ['defaultNestedArray', 'array $value = [\'a\' => 1, \'b\' => [2, 3]]'];

        // self reste valide dans le proxie, qui hérite de l'entité
        yield 'constante self' => ['defaultSelfConstant', 'int $value = self::LOCAL'];

        yield 'constante de classe' => [
            'defaultNamespacedConstant',
            'string $value = \\' . self::FIXTURES . '\\EdgeCaseParent::SOME',
        ];

        // résolue en Fixtures\PHP_INT_MAX par la réflexion, alors que seule la globale existe
        yield 'constante globale' => ['defaultGlobalConstant', 'int $value = PHP_INT_MAX'];

        yield 'cas enum' => [
            'defaultEnum',
            '\\' . self::FIXTURES . '\\Suit $value = \\' . self::FIXTURES . '\\Suit::Hearts',
        ];

        // un enum imbriqué passe par getPhpForValue(), là où un enum direct est vu comme une constante
        yield 'enum dans un tableau' => [
            'defaultArrayWithEnum',
            'array $value = [\'suit\' => \\' . self::FIXTURES . '\\Suit::Hearts]',
        ];
    }

    #[DataProvider('parameterProvider')]
    #[DataProvider('defaultValueProvider')]
    public function testParameterIsRendered(string $method, string $expected): void
    {
        $parameter = (new \ReflectionMethod(ParameterCases::class, $method))->getParameters()[0];

        static::assertSame($expected, $this->renderParameter($parameter));
    }

    /**
     * var_export() rendrait un Objet::__set_state(), qui n'est pas une expression constante : le proxie
     * ne compilerait pas, et generateProxyFile() ne réécrivant jamais un fichier existant, le fatal
     * rejouerait à chaque requête. Mieux vaut échouer avant que le fichier ne soit écrit.
     */
    public function testObjectDefaultValueIsRefusedBeforeAnythingIsWritten(): void
    {
        $parameter = (new \ReflectionMethod(UnrenderableDefault::class, 'objectDefault'))->getParameters()[0];

        $this->expectException(DefaultValueCantBeRenderedException::class);
        $this->expectExceptionMessageIs(
            'Default value of type DateTime can\'t be rendered as a constant expression in a read only '
                . 'proxy. Use null as default value instead.'
        );

        $this->renderParameter($parameter);
    }

    /** Les types DNF n'existent qu'à partir de PHP 8.2 : chaque intersection doit être parenthésée. */
    #[RequiresPhp('>= 8.2.0')]
    public function testDisjunctiveNormalFormIsParenthesized(): void
    {
        eval(
            'namespace ' . self::FIXTURES . ';'
                . 'class DnfCase { public function dnf((\Countable&\ArrayAccess)|int $value): void {} }'
        );

        $parameter = (new \ReflectionMethod(self::FIXTURES . '\\DnfCase', 'dnf'))->getParameters()[0];

        static::assertSame('(\Countable&\ArrayAccess)|int $value', $this->renderParameter($parameter));
    }

    /**
     * Le contrôle décisif : les déclarations rendues doivent former une classe qui compile et reste
     * compatible avec la classe mère, ce qu'une simple comparaison de chaînes ne garantit pas.
     */
    public function testEveryRenderedParameterCompilesAndStaysCompatible(): void
    {
        $methods = [];
        foreach ((new \ReflectionClass(ParameterCases::class))->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== ParameterCases::class) {
                continue;
            }

            $parameters = array_map(
                fn (\ReflectionParameter $parameter): string => $this->renderParameter($parameter),
                $method->getParameters()
            );
            $methods[] = sprintf(
                "    public function %s(%s): void {}",
                $method->getName(),
                implode(', ', $parameters)
            );
        }

        static::assertGreaterThanOrEqual(30, \count($methods));

        eval(sprintf(
            "namespace ReadOnlyProxies\\%s;\n\nclass ParameterCasesProxy extends \\%s\n{\n%s\n}",
            self::FIXTURES,
            ParameterCases::class,
            implode("\n\n", $methods)
        ));

        $proxy = new \ReflectionClass('ReadOnlyProxies\\' . self::FIXTURES . '\\ParameterCasesProxy');

        static::assertSame(ParameterCases::class, (string) $proxy->getMethod('selfType')->getParameters()[0]->getType());
        static::assertSame(EdgeCaseParent::class, (string) $proxy->getMethod('parentType')->getParameters()[0]->getType());
        static::assertTrue($proxy->getMethod('variadicByReference')->getParameters()[0]->isVariadic());
        static::assertTrue($proxy->getMethod('variadicByReference')->getParameters()[0]->isPassedByReference());
        static::assertSame(\PHP_INT_MAX, $proxy->getMethod('defaultGlobalConstant')->getParameters()[0]->getDefaultValue());
        static::assertSame(Suit::Hearts, $proxy->getMethod('defaultEnum')->getParameters()[0]->getDefaultValue());
    }

    private function renderParameter(\ReflectionParameter $parameter): string
    {
        $hydrator = (new \ReflectionClass(ReadOnlyHydrator::class))->newInstanceWithoutConstructor();

        return \Closure::bind(
            fn (\ReflectionParameter $reflectionParameter): string
                => $this->getPhpForParameter($reflectionParameter),
            $hydrator,
            ReadOnlyHydrator::class
        )($parameter);
    }
}
