<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\Bootstrapper;
use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\Bootstrapper
 */
final class BootstrapperTest extends TestCase
{
    public function testDefaultsItsOwnOptionsWhenNotProvided(): void
    {
        $resolved = $this->resolveOptions($this->hooksExecutorDefiningNothingExtra(), []);

        self::assertSame([
            Bootstrapper::class => [
                Bootstrapper::OPTION_SCHEMA_UPDATE => true,
                Bootstrapper::OPTION_SHUTDOWN_KERNEL => true,
            ],
        ], $resolved);
    }

    public function testResolvesItsOwnOptionsAlongsideHookOptions(): void
    {
        $hooksExecutor = $this->createMock(HooksExecutorInterface::class);
        $hooksExecutor->method('configureOptions')->willReturnCallback(static function (OptionsResolver $resolver): void {
            $resolver->define('some.hook.id')->default([])->allowedTypes('array');
        });

        $resolved = $this->resolveOptions($hooksExecutor, [
            Bootstrapper::class => [
                Bootstrapper::OPTION_SCHEMA_UPDATE => false,
                Bootstrapper::OPTION_SHUTDOWN_KERNEL => false,
            ],
            'some.hook.id' => ['enabled' => false],
        ]);

        self::assertSame([
            Bootstrapper::class => [
                Bootstrapper::OPTION_SCHEMA_UPDATE => false,
                Bootstrapper::OPTION_SHUTDOWN_KERNEL => false,
            ],
            'some.hook.id' => ['enabled' => false],
        ], $resolved);
    }

    public function testRejectsAnUnrecognizedTopLevelKey(): void
    {
        $this->expectException(UndefinedOptionsException::class);

        $this->resolveOptions($this->hooksExecutorDefiningNothingExtra(), ['some.unknown.key' => []]);
    }

    public function testRejectsAnUnrecognizedOptionUnderItsOwnKey(): void
    {
        $this->expectException(UndefinedOptionsException::class);

        $this->resolveOptions($this->hooksExecutorDefiningNothingExtra(), [
            Bootstrapper::class => ['some_unknown_option' => true],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(HooksExecutorInterface $hooksExecutor, array $options): array
    {
        $method = new ReflectionMethod(Bootstrapper::class, 'resolveOptions');
        $method->setAccessible(true);

        return $method->invoke(null, $hooksExecutor, $options);
    }

    private function hooksExecutorDefiningNothingExtra(): HooksExecutorInterface
    {
        $hooksExecutor = $this->createMock(HooksExecutorInterface::class);
        $hooksExecutor->method('configureOptions')->willReturnCallback(static function (OptionsResolver $resolver): void {
        });

        return $hooksExecutor;
    }
}
