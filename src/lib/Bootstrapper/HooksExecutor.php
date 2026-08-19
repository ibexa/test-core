<?php

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;

final class HooksExecutor implements HooksExecutorInterface
{
    private iterable $hooks;

    public function __construct(
        iterable $hooks
    ) {
        $this->hooks = $hooks;
    }

    #[\Override]
    public function execute(): void
    {
        foreach ($this->hooks as $hook) {
            $hook();
        }
    }
}
