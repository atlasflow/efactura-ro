<?php

declare(strict_types=1);

namespace AtlasFlow\EFacturaRo\Tests\Fixtures;

use ReflectionClass;

/**
 * The model is readonly, so a test that wants "the standard invoice but with
 * a wrong total" rebuilds the object through its constructor with a few
 * named arguments overridden.
 */
final class Rebuild
{
    /**
     * @template T of object
     *
     * @param  T  $object
     * @param  array<string, mixed>  $overrides
     * @return T
     */
    public static function with(object $object, array $overrides): object
    {
        $class = new ReflectionClass($object);
        $constructor = $class->getConstructor();
        $arguments = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            $arguments[$name] = array_key_exists($name, $overrides) ? $overrides[$name] : $object->{$name};
        }

        /** @var T */
        return $class->newInstanceArgs($arguments);
    }
}
