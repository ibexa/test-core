<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
final class TestDataTest extends TestCase
{
    private const string TEST_DATA_FILE = __DIR__ . '/../../src/contracts/Resources/test_data.yaml';

    private const string IMAGE_FIELD_TYPE_IDENTIFIER = 'ibexa_image';

    private const string EXPECTED_STORAGE_PREFIX = 'var/storage/';

    public function testImageFilePathsUseDefaultStoragePrefix(): void
    {
        $rows = $this->getTable('ibexa_image_file');
        self::assertNotEmpty($rows);

        foreach ($rows as $row) {
            self::assertIsArray($row);
            self::assertIsString($row['filepath'] ?? null);

            $this->assertHasDefaultStoragePrefix(
                $row['filepath'],
                sprintf('ibexa_image_file row %s', json_encode($row['id'] ?? null)),
            );
        }
    }

    public function testImageFieldPathsUseDefaultStoragePrefix(): void
    {
        $assertedPathCount = 0;

        foreach ($this->getTable('ibexa_content_field') as $row) {
            self::assertIsArray($row);
            if (($row['data_type_string'] ?? null) !== self::IMAGE_FIELD_TYPE_IDENTIFIER) {
                continue;
            }

            $dataText = $row['data_text'] ?? null;
            if (!is_string($dataText) || trim($dataText) === '') {
                continue;
            }

            $image = simplexml_load_string($dataText);
            self::assertInstanceOf(SimpleXMLElement::class, $image);
            if ((string) $image['is_valid'] !== '1') {
                continue;
            }

            $context = sprintf('ibexa_content_field row %s', json_encode($row['id'] ?? null));
            foreach (['dirpath', 'url'] as $attribute) {
                $this->assertHasDefaultStoragePrefix(
                    (string) $image[$attribute],
                    sprintf('%s attribute "%s"', $context, $attribute),
                );
                ++$assertedPathCount;
            }
        }

        self::assertGreaterThan(0, $assertedPathCount);
    }

    private function assertHasDefaultStoragePrefix(
        string $path,
        string $context
    ): void {
        self::assertStringStartsWith(
            self::EXPECTED_STORAGE_PREFIX,
            $path,
            sprintf(
                '%s must store files under the default "%s" prefix, got "%s".',
                $context,
                self::EXPECTED_STORAGE_PREFIX,
                $path,
            ),
        );
    }

    /**
     * @return array<mixed>
     */
    private function getTable(string $name): array
    {
        $testData = Yaml::parseFile(self::TEST_DATA_FILE);
        self::assertIsArray($testData);
        self::assertArrayHasKey($name, $testData);
        self::assertIsArray($testData[$name]);

        return $testData[$name];
    }
}
