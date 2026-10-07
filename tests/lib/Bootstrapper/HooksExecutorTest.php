<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\HookInterface;
use Ibexa\Test\Core\Bootstrapper\HooksExecutor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Ibexa\Test\Core\Bootstrapper\HooksExecutor
 */
final class HooksExecutorTest extends TestCase
{
    public function testConfigureOptionsRejectsUnrecognizedTopLevelKey(): void
    {
        $executor = new HooksExecutor(['some.hook.id' => $this->hookWithOptions(static function (OptionsResolver $resolver): void {
            $resolver->define('enabled')->default(true)->allowedTypes('bool');
        })]);

        $resolver = new OptionsResolver();
        $executor->configureOptions($resolver);

        $this->expectException(UndefinedOptionsException::class);

        $resolver->resolve(['some.unknown.key' => []]);
    }

    public function testConfigureOptionsResolvesEachHookSOwnOptions(): void
    {
        $executor = new HooksExecutor(['some.hook.id' => $this->hookWithOptions(static function (OptionsResolver $resolver): void {
            $resolver->define('enabled')->default(true)->allowedTypes('bool');
        })]);

        $resolver = new OptionsResolver();
        $executor->configureOptions($resolver);

        $resolved = $resolver->resolve(['some.hook.id' => ['enabled' => false]]);

        self::assertSame(['some.hook.id' => ['enabled' => false]], $resolved);
    }

    public function testConfigureOptionsDefaultsAMissingHookKeyToItsOwnDefaults(): void
    {
        $executor = new HooksExecutor(['some.hook.id' => $this->hookWithOptions(static function (OptionsResolver $resolver): void {
            $resolver->define('enabled')->default(true)->allowedTypes('bool');
        })]);

        $resolver = new OptionsResolver();
        $executor->configureOptions($resolver);

        $resolved = $resolver->resolve([]);

        self::assertSame(['some.hook.id' => ['enabled' => true]], $resolved);
    }

    public function testExecuteInvokesEachHookWithItsOwnSubArray(): void
    {
        $invokedWith = null;
        $hook = $this->hookWithOptions(static function (OptionsResolver $resolver): void {
            $resolver->define('enabled')->default(true)->allowedTypes('bool');
        });
        $hook->method('__invoke')->willReturnCallback(static function (array $options) use (&$invokedWith): void {
            $invokedWith = $options;
        });

        $executor = new HooksExecutor(['some.hook.id' => $hook]);
        $executor->execute(['some.hook.id' => ['enabled' => false]]);

        self::assertSame(['enabled' => false], $invokedWith);
    }

    /**
     * @param callable(OptionsResolver): void $configureOptions
     *
     * @return HookInterface&MockObject
     */
    private function hookWithOptions(callable $configureOptions): HookInterface
    {
        $hook = $this->createMock(HookInterface::class);
        $hook->method('configureOptions')->willReturnCallback($configureOptions);

        return $hook;
    }
}
