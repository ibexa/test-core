<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\DecimalType;
use Ibexa\DoctrineSchema\Exporter\Table\SchemaTableExporter;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes some tables of a built schema in the doctrine-schema YAML format, for packages that keep
 * snapshots of their released schemas (tables defined by ORM entities or PHP code have no schema
 * file at the release tags).
 *
 * doctrine-schema's own exporter leaves out a few column properties; they're added here as column
 * options, which its importer passes straight to Table::addColumn().
 *
 * @internal
 */
final class SchemaSnapshotExporter
{
    /**
     * @param list<string> $tables
     */
    public function export(Schema $schema, array $tables): string
    {
        $exporter = new SchemaTableExporter();
        $definition = [];
        foreach ($tables as $tableName) {
            if (!$schema->hasTable($tableName)) {
                continue;
            }

            $table = $schema->getTable($tableName);
            $exported = $exporter->export($table);
            $tableDefinition = $exported[$table->getName()];

            // The exporter keys foreign keys by DBAL's lowercased array keys; keep their real names
            // (e.g. ORM's "FK_74706FD6727ACA70"), which MySQL reports as they were given.
            if (isset($tableDefinition['foreignKeys'])) {
                $foreignKeys = [];
                foreach ($table->getForeignKeys() as $key => $foreignKey) {
                    $foreignKeys[$foreignKey->getName()] = $tableDefinition['foreignKeys'][$key];
                }
                $tableDefinition['foreignKeys'] = $foreignKeys;
            }

            foreach ($table->getColumns() as $column) {
                $group = isset($tableDefinition['id'][$column->getName()]) ? 'id' : 'fields';
                $tableDefinition[$group][$column->getName()] = self::addColumnOptions(
                    $tableDefinition[$group][$column->getName()],
                    $column
                );
            }
            $definition[$table->getName()] = $tableDefinition;
        }

        return Yaml::dump(['tables' => $definition], 6, 4);
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private static function addColumnOptions(array $field, Column $column): array
    {
        if ($column->getType() instanceof DecimalType) {
            $field['precision'] = $column->getPrecision();
            $field['scale'] = $column->getScale();
        }

        $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
        if ($column->getFixed()) {
            $options['fixed'] = true;
        }
        if ($column->getUnsigned()) {
            $options['unsigned'] = true;
        }
        if ($column->getComment() !== null && $column->getComment() !== '') {
            $options['comment'] = $column->getComment();
        }
        $platformOptions = array_intersect_key($column->getPlatformOptions(), array_flip(['charset', 'collation']));
        if ($platformOptions !== []) {
            $options['platformOptions'] = $platformOptions;
        }
        if ($options !== []) {
            $field['options'] = $options;
        }

        return $field;
    }
}
