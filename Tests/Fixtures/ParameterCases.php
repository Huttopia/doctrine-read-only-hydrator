<?php

namespace steevanb\DoctrineReadOnlyHydrator\Tests\Fixtures;

/**
 * Une méthode par forme de paramètre que ReadOnlyHydrator::getPhpForParameter() doit savoir rendre.
 * Les types DNF, qui exigent PHP 8.2, sont traités à part : ce fichier doit rester analysable sur
 * le plancher déclaré par composer.json.
 */
class ParameterCases extends EdgeCaseParent
{
    public const LOCAL = 1;

    public function noType($value): void {}

    public function scalar(int $value): void {}

    public function nullableScalar(?int $value): void {}

    public function classType(EdgeCaseParent $value): void {}

    public function nullableClass(?EdgeCaseParent $value): void {}

    public function selfType(self $value): void {}

    public function parentType(parent $value): void {}

    public function arrayType(array $value): void {}

    public function callableType(callable $value): void {}

    public function iterableType(iterable $value): void {}

    public function objectType(object $value): void {}

    public function mixedType(mixed $value): void {}

    public function union(\Countable|array $value): void {}

    public function nullableUnion(\Countable|array|null $value): void {}

    public function intersection(\Countable&\ArrayAccess $value): void {}

    public function byReference(array &$value): void {}

    public function variadic(int ...$value): void {}

    public function variadicByReference(int &...$value): void {}

    public function defaultNull(?string $value = null): void {}

    public function defaultTrue(bool $value = true): void {}

    public function defaultFalse(bool $value = false): void {}

    public function defaultInt(int $value = 42): void {}

    public function defaultFloat(float $value = 1.0): void {}

    public function defaultQuotedString(string $value = 'l\'apostrophe'): void {}

    public function defaultEmptyArray(array $value = []): void {}

    public function defaultNestedArray(array $value = ['a' => 1, 'b' => [2, 3]]): void {}

    public function defaultSelfConstant(int $value = self::LOCAL): void {}

    public function defaultNamespacedConstant(string $value = EdgeCaseParent::SOME): void {}

    public function defaultGlobalConstant(int $value = PHP_INT_MAX): void {}

    public function defaultEnum(Suit $value = Suit::Hearts): void {}

    public function defaultArrayWithEnum(array $value = ['suit' => Suit::Hearts]): void {}
}
