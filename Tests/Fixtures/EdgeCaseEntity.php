<?php

namespace steevanb\DoctrineReadOnlyHydrator\Tests\Fixtures;

/**
 * Chaque méthode porte une construction de signature que ReadOnlyHydrator doit savoir reproduire
 * dans le proxie généré, lequel vit dans un autre namespace et doit rester compatible avec sa mère.
 */
class EdgeCaseEntity extends EdgeCaseParent
{
    public const LOCAL = 1;

    private string $property = '';

    public function selfParameter(self $other): self
    {
        return $this->property === '' ? $other : $this;
    }

    public function parentParameter(EdgeCaseParent $other): EdgeCaseParent
    {
        return $this->property === '' ? $other : $this;
    }

    public function staticReturn(): static
    {
        $this->property;

        return $this;
    }

    public function variadic(int ...$numbers): int
    {
        return \count($numbers) + \strlen($this->property);
    }

    public function byReference(array &$rows): void
    {
        $rows[] = $this->property;
    }

    public function tricky(
        string $quoted = 'l\'apostrophe',
        array $rows = ['a' => 1, 'b' => [2, 3]],
        float $float = 1.0,
        ?string $nil = null,
        int $constant = self::LOCAL,
    ): mixed {
        return [$quoted, $rows, $float, $nil, $constant, $this->property];
    }

    public function namespacedConstant(string $value = EdgeCaseParent::SOME): string
    {
        return $value . $this->property;
    }

    public function unionReturn(): \Countable|array|null
    {
        return $this->property === '' ? null : [];
    }

    public function mixedNullable(mixed $any = null): mixed
    {
        return $any ?? $this->property;
    }
}
