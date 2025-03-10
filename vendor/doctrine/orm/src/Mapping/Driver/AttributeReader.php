<?php

declare(strict_types=1);

namespace Doctrine\ORM\Mapping\Driver;

use Attribute;
use Doctrine\ORM\Mapping\Annotation;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use function assert;
use function is_string;
use function is_subclass_of;
use function sprintf;

/** @internal */
final class AttributeReader
{
    /** @var array<class-string<Annotation>,bool> */
    private array $isRepeatableAttribute = [];

    /**
     * @phpstan-return class-string-map<T, T|RepeatableAttributeCollection<T>>
     *
     * @template T of Annotation
     */
    public function getClassAttributes(ReflectionClass $class): array
    {
        return $this->convertToAttributeInstances($class->getAttributes());
    }

    /**
     * @return class-string-map<T, T|RepeatableAttributeCollection<T>>
     *
     * @template T of Annotation
     */
    public function getMethodAttributes(ReflectionMethod $method): array
    {
        return $this->convertToAttributeInstances($method->getAttributes());
    }

    /**
     * @return class-string-map<T, T|RepeatableAttributeCollection<T>>
     *
     * @template T of Annotation
     */
    public function getPropertyAttributes(ReflectionProperty $property): array
    {
        return $this->convertToAttributeInstances($property->getAttributes());
    }

    /**
     * @param class-string<T> $attributeName The name of the annotation.
     *
     * @return T|null
     *
     * @template T of Annotation
     */
    public function getPropertyAttribute(ReflectionProperty $property, $attributeName)
    {
        if ($this->isRepeatable($attributeName)) {
            throw new LogicException(sprintf(
                'The attribute "%s" is repeatable. Call getPropertyAttributeCollection() instead.',
                $attributeName
            ));
        }

        return $this->getPropertyAttributes($property)[$attributeName] ?? null;
    }

    /**
     * @param class-string<T> $attributeName The name of the annotation.
     *
     * @return RepeatableAttributeCollection<T>
     *
     * @template T of Annotation
     */
    public function getPropertyAttributeCollection(
        ReflectionProperty $property,
        string $attributeName
    ): RepeatableAttributeCollection {
        if (! $this->isRepeatable($attributeName)) {
            throw new LogicException(sprintf(
                'The attribute "%s" is not repeatable. Call getPropertyAttribute() instead.',
                $attributeName
            ));
        }

        return $this->getPropertyAttributes($property)[$attributeName] ?? new RepeatableAttributeCollection();
    }

    /**
     * @param array<ReflectionAttribute> $attributes
     *
     * @return class-string-map<T, T|RepeatableAttributeCollection<T>>
     *
     * @template T of Annotation
     */
    private function convertToAttributeInstances(array $attributes): array
    {
        $instances = [];

        foreach ($attributes as $attribute) {
            $attributeName = $attribute->getName();
            assert(is_string($attributeName));
            // Make sure we only get Doctrine Attributes
            if (! is_subclass_of($attributeName, Annotation::class)) {
                continue;
            }

            $instance = $attribute->newInstance();
            assert($instance instanceof Annotation);

            if ($this->isRepeatable($attributeName)) {
                if (! isset($instances[$attributeName])) {
                    $instances[$attributeName] = new RepeatableAttributeCollection();
                }

                $collection = $instances[$attributeName];
                assert($collection instanceof RepeatableAttributeCollection);
                $collection[] = $instance;
            } else {
                $instances[$attributeName] = $instance;
            }
        }

        return $instances;
    }

    /** @param class-string<Annotation> $attributeClassName */
    private function isRepeatable(string $attributeClassName): bool
    {
        if (isset($this->isRepeatableAttribute[$attributeClassName])) {
            return $this->isRepeatableAttribute[$attributeClassName];
        }

        $reflectionClass = new ReflectionClass($attributeClassName);
        $attribute       = $reflectionClass->getAttributes()[0]->newInstance();

        return $this->isRepeatableAttribute[$attributeClassName] = ($attribute->flags & Attribute::IS_REPEATABLE) > 0;
    }
}
