<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

use Ibexa\CodeStyle\PhpCsFixer\InternalConfigFactory;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->files()->name('*.php');

return (new InternalConfigFactory())
    ->withRules([
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
                'property' => 'one',
            ],
        ],
        'class_definition' => [
            'single_item_single_line' => true,
            'inline_constructor_arguments' => false,
        ],
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
    ])
    ->buildConfig()
    ->setFinder(
        $finder
    );

