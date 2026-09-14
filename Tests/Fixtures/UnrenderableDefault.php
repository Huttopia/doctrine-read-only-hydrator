<?php

namespace steevanb\DoctrineReadOnlyHydrator\Tests\Fixtures;

class UnrenderableDefault
{
    public function objectDefault(\DateTimeInterface $value = new \DateTime('2020-01-01')): void {}
}
