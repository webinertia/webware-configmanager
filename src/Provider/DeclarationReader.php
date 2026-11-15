<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

use ReflectionClass;
use Webware\ConfigManager\Attribute\LoadAfter;
use Webware\ConfigManager\Attribute\LoadBefore;

use function class_exists;

/**
 * Reads the ordering declarations a provider carries.
 *
 * Reflection is used rather than an interface check, so a provider does not have to
 * implement anything, and an instance is read by its class.
 *
 * A reference that cannot be reflected has no declarations. That covers a class-string
 * naming something that is not installed, which is ordinary: a package declares an order
 * relative to a peer the host may not have, and the resolver tolerates a dangling target.
 * It also keeps the reader total, so ordering never turns a missing class into a failure
 * before the resolver can report it properly.
 *
 * @internal
 */
final class DeclarationReader
{
    /** @return list<Declaration> */
    public static function read(mixed $provider): array
    {
        $reflection = self::reflect($provider);

        if (null === $reflection) {
            return [];
        }

        $declarations = [];

        foreach ($reflection->getAttributes(LoadAfter::class) as $attribute) {
            foreach ($attribute->newInstance()->providers as $target) {
                $declarations[] = new Declaration(Relation::After, $target);
            }
        }

        foreach ($reflection->getAttributes(LoadBefore::class) as $attribute) {
            foreach ($attribute->newInstance()->providers as $target) {
                $declarations[] = new Declaration(Relation::Before, $target);
            }
        }

        return $declarations;
    }

    /**
     * @return ReflectionClass<object>|null
     * @mago-expect analysis:unhandled-thrown-type
     */
    private static function reflect(mixed $provider): ?ReflectionClass
    {
        $class = ProviderReference::of($provider)->class;

        if (null === $class || ! class_exists($class)) {
            return null;
        }

        // ReflectionClass::__construct() declares ReflectionException, and class_exists()
        // above has already resolved the name, so it cannot raise it here. The
        // acknowledgement is on the method rather than a catch that could never run.
        return new ReflectionClass($class);
    }
}
