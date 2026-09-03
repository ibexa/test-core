<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    // In-memory SQLite ("sqlite://:memory:") is deliberately unsupported as a default: it can't
    // participate in the per-test DAMA transaction/rollback most consumers rely on, and
    // doctrine:database:drop can't act on it at all (its connection params carry neither a "path"
    // nor a "dbname"). A real file, cleaned up via doctrine:database:drop on every bootstrap, is the
    // supported default — matches the DATABASE_URL every consuming package already sets explicitly.
    $_ENV['DATABASE_URL'] ??= 'sqlite://i@i/var/test.db';
    $container->parameters()->set('env(DATABASE_URL)', $_ENV['DATABASE_URL']);

    $container->extension('doctrine', [
        'dbal' => [
            'url' => '%env(DATABASE_URL)%',
            'logging' => false,
            'use_savepoints' => true,
        ],
    ]);
};
