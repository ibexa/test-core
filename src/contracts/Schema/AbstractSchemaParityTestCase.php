<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\DatabasePreparer;
use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use Ibexa\DoctrineSchema\Importer\SchemaImporter;
use Ibexa\Test\Core\Schema\Ddl\DdlRenderers;
use Ibexa\Test\Core\Schema\GitRepository;
use Ibexa\Test\Core\Schema\ParityReport;
use Ibexa\Test\Core\Schema\ReleasedSchemaFinder;
use Ibexa\Test\Core\Schema\ReleasedSchemaHistory;
use Ibexa\Test\Core\Schema\Scenario;
use Ibexa\Test\Core\Schema\ScenarioDatabase;
use Ibexa\Test\Core\Schema\SchemaComparer;
use Ibexa\Test\Core\Schema\SchemaSnapshotExporter;
use LogicException;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Checks that a package's Doctrine Migrations end with the same tables as its schema definition,
 * wherever the database starts:
 *  - fresh-install: an empty database;
 *  - current-legacy-install: a legacy (schema path) install of the current version;
 *  - package-added-to-legacy-install: a legacy install of every other bundle, without this package;
 *  - upgrade-from-<releases>: a legacy install where this package's tables are as an older release
 *    left them. A fresh install can't show a schema change that has no migration, because the
 *    baseline migration already contains everything; only a database from before the change can.
 *
 * Every scenario, and the schema path's own reference install, gets its own database, derived from
 * DATABASE_URL (e.g. "testdb_parity_fresh"), which is left in place for inspection. The tables are
 * compared through the database's own DDL (SHOW CREATE TABLE, PostgreSQL's catalog renderings,
 * SQLite's pragmas), normalized only for column order and counters.
 *
 * A package adds a subclass declaring its schema file, its own phpunit config setting
 * IBEXA_SCHEMA_PARITY=1 and KERNEL_CLASS, and a kernel with both Doctrine Migrations bundles:
 *
 *     final class SchemaParityTest extends AbstractSchemaParityTestCase
 *     {
 *         protected static function getSchemaFileHistory(): array
 *         {
 *             return ['v4.6.*' => 'src/bundle/Resources/config/storage/schema.yaml'];
 *         }
 *     }
 *
 * Older schemas come from the release tags (fetch them first: they aren't on any branch). Tables
 * defined elsewhere than in a schema file (ORM entities, PHP code) override getOwnTableNames() and
 * getReleasedSchemas() with committed snapshots, see {@see ReleasedSchema::fromFile()} and
 * {@see self::ENV_DUMP_DIR}.
 *
 * @experimental
 */
abstract class AbstractSchemaParityTestCase extends IbexaKernelTestCase
{
    /** Must be "1" for the test to run: it creates databases, so it has its own phpunit config. */
    public const ENV_ENABLED = 'IBEXA_SCHEMA_PARITY';

    /** "1" turns missing release tags or a missing migrations path into failures instead of skips (CI). */
    public const ENV_STRICT = 'IBEXA_SCHEMA_PARITY_STRICT';

    /** A directory to write this package's tables to as the schema path builds them, for snapshots. */
    public const ENV_DUMP_DIR = 'IBEXA_SCHEMA_PARITY_DUMP_DIR';

    /** ibexa/core's runner of the Doctrine Migrations path, by name: it exists only with the migrations bundles. */
    private const MIGRATIONS_RUNNER = 'Ibexa\Bundle\RepositoryInstaller\Migration\TaggedMigrationsRunner';

    private const CONNECTION = 'doctrine.dbal.default_connection';

    private const DEFAULT_DATABASE_URL = 'sqlite://i@i/var/test.db';

    /** What the ibexa/oss recipe's DATABASE_CHARSET and DATABASE_COLLATION default to. */
    private const RECIPE_CHARSET = 'utf8mb4';
    private const RECIPE_COLLATION = 'utf8mb4_unicode_520_ci';

    /**
     * @var array<string, array{
     *     url: string,
     *     platform: string,
     *     serverVersion: string,
     *     tables: array<string, list<string>|null>,
     *     inboundForeignKeys: list<string>,
     *     hasOtherTables: bool
     * }>
     */
    private static array $references = [];

    /** @var array<string, ReleasedSchemaHistory> */
    private static array $histories = [];

    private static ?string $baseDatabaseUrl = null;

    /**
     * The package's schema file at each series of release tags, oldest first; the last path is the
     * current file. E.g. ['v4.6.*' => 'src/bundle/Resources/config/storage/schema.yaml'].
     *
     * @return array<string, string> tag pattern => path relative to the package root
     */
    protected static function getSchemaFileHistory(): array
    {
        return [];
    }

    /**
     * The tables this package owns. Defaults to the tables of the current schema file.
     *
     * @return list<string>
     */
    protected static function getOwnTableNames(): array
    {
        $history = static::getSchemaFileHistory();
        if ($history === []) {
            throw new LogicException(sprintf(
                '%s must override getSchemaFileHistory(), or getOwnTableNames() and getReleasedSchemas().',
                static::class
            ));
        }

        return (new ReleasedSchema('current', self::readFile(self::getPackageRoot() . '/' . end($history))))->getTableNames();
    }

    /**
     * Older versions of this package's tables to upgrade from. Defaults to every distinct version
     * of the schema file at the release tags matching getSchemaFileHistory().
     *
     * @return iterable<ReleasedSchema>
     */
    protected static function getReleasedSchemas(): iterable
    {
        return self::getHistory()->getReleasedSchemas();
    }

    /**
     * @return iterable<ExpectedDifference>
     */
    protected static function getExpectedDifferences(): iterable
    {
        return [];
    }

    final public function testReleasedSchemasAreAvailable(): void
    {
        self::skipUnlessEnabled();

        $history = self::getHistory();
        if ($history->getProblems() === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $message = implode("\n", $history->getProblems());
        if ($history->areTagsMissing() && !self::isEnabled(self::ENV_STRICT)) {
            self::markTestSkipped($message);
        }

        self::fail($message);
    }

    /**
     * @return iterable<string, array{Scenario}>
     */
    final public static function provideScenarios(): iterable
    {
        yield Scenario::FRESH_INSTALL => [Scenario::freshInstall()];
        yield Scenario::CURRENT_LEGACY_INSTALL => [Scenario::currentLegacyInstall(self::getHistory()->getCurrentTags())];
        yield Scenario::PACKAGE_ADDED => [Scenario::packageAdded()];

        foreach (static::getReleasedSchemas() as $releasedSchema) {
            $scenario = Scenario::upgradeFrom($releasedSchema);

            yield $scenario->getId() => [$scenario];
        }
    }

    /**
     * @dataProvider provideScenarios
     */
    final public function testScenarioMatchesTheSchemaPath(Scenario $scenario): void
    {
        self::skipUnlessEnabled();

        $ownTables = static::getOwnTableNames();
        $reference = self::getReference($ownTables);

        if ($scenario->getKind() === Scenario::PACKAGE_ADDED) {
            if (!$reference['hasOtherTables']) {
                self::markTestSkipped('No other bundle in the kernel has tables, so this is the same as fresh-install.');
            }
            if ($reference['inboundForeignKeys'] !== []) {
                self::markTestSkipped(sprintf(
                    'Other tables reference this package\'s tables (%s), so a legacy install without them can\'t exist.',
                    implode(', ', $reference['inboundForeignKeys'])
                ));
            }
        }

        $releasedSchema = $scenario->getReleasedSchema();
        $tables = $releasedSchema === null
            ? $ownTables
            : array_values(array_unique(array_merge($ownTables, $releasedSchema->getTableNames())));

        $scenarioUrl = ScenarioDatabase::deriveUrl(self::getBaseDatabaseUrl(), $scenario->getDatabaseSlug());
        $actual = self::inDatabase(
            $scenarioUrl,
            static function (ContainerInterface $container, Connection $connection) use ($scenario, $ownTables, $tables): array {
                if ($scenario->startsFromLegacyInstall()) {
                    self::executeSchema($connection, self::buildStartingSchema($container, $scenario, $ownTables));
                }
                self::runMigrations($container, $scenario);

                return self::render($connection, $tables);
            }
        );

        $expectedDifferences = [];
        foreach (static::getExpectedDifferences() as $expectedDifference) {
            $expectedDifferences[] = $expectedDifference;
        }
        $platform = $reference['platform'];
        $differences = SchemaComparer::compare(
            array_intersect_key($reference['tables'], array_flip($tables)),
            $actual,
            static function (string $table, string $kind, string $line) use ($expectedDifferences, $platform, $scenario): bool {
                foreach ($expectedDifferences as $expectedDifference) {
                    if ($expectedDifference->matches($table, $kind, $line, $platform, $scenario->getId())) {
                        return true;
                    }
                }

                return false;
            }
        );

        if ($differences !== []) {
            self::fail(ParityReport::format(
                $scenario,
                $platform,
                $reference['serverVersion'],
                $reference['url'],
                $scenarioUrl,
                $differences
            ));
        }

        $this->addToAssertionCount(1);
    }

    /**
     * The schema path's install, built once per test class into its own database.
     *
     * @param list<string> $ownTables
     *
     * @return array{
     *     url: string,
     *     platform: string,
     *     serverVersion: string,
     *     tables: array<string, list<string>|null>,
     *     inboundForeignKeys: list<string>,
     *     hasOtherTables: bool
     * }
     */
    private static function getReference(array $ownTables): array
    {
        if (isset(self::$references[static::class])) {
            return self::$references[static::class];
        }

        $tables = $ownTables;
        foreach (static::getReleasedSchemas() as $releasedSchema) {
            $tables = array_merge($tables, $releasedSchema->getTableNames());
        }
        $tables = array_values(array_unique($tables));

        $url = ScenarioDatabase::deriveUrl(self::getBaseDatabaseUrl(), 'reference');

        return self::$references[static::class] = self::inDatabase(
            $url,
            static function (ContainerInterface $container, Connection $connection) use ($ownTables, $tables, $url): array {
                $schema = self::getSchemaBuilder($container)->buildSchema();
                foreach ($ownTables as $table) {
                    if (!$schema->hasTable($table)) {
                        throw new LogicException(sprintf(
                            'The schema path doesn\'t define the table "%s". Is the bundle that owns it registered in %s?',
                            $table,
                            static::getKernelClass()
                        ));
                    }
                }

                self::dumpSnapshot($schema, $ownTables);
                self::executeSchema($connection, $schema);

                return [
                    'url' => $url,
                    'platform' => DdlRenderers::getPlatformName($connection),
                    'serverVersion' => DdlRenderers::forConnection($connection)->getServerVersion($connection),
                    'tables' => self::render($connection, $tables),
                    'inboundForeignKeys' => self::findInboundForeignKeys($schema, $ownTables),
                    'hasOtherTables' => count($schema->getTables()) > count($ownTables),
                ];
            }
        );
    }

    /**
     * Boots the kernel against a database of its own, created fresh the way every integration suite
     * creates its database. Only $_ENV changes, so the compiled container is reused.
     *
     * @template T
     *
     * @param callable(ContainerInterface, Connection): T $callback
     *
     * @return T
     */
    private static function inDatabase(string $url, callable $callback)
    {
        $previousEnv = $_ENV['DATABASE_URL'] ?? null;
        $previousServer = $_SERVER['DATABASE_URL'] ?? null;
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $url;

        try {
            self::ensureKernelShutdown();
            $kernel = self::bootKernel();

            $sqliteFile = ScenarioDatabase::getSqliteFile($url);
            if ($sqliteFile !== null && !is_dir(dirname($sqliteFile))) {
                mkdir(dirname($sqliteFile), 0777, true);
            }
            (new DatabasePreparer())->prepareDatabase($kernel, false);

            $container = self::getContainer();
            $connection = $container->get(self::CONNECTION);
            if (!$connection instanceof Connection) {
                throw new LogicException(sprintf('"%s" isn\'t a %s.', self::CONNECTION, Connection::class));
            }

            return $callback($container, $connection);
        } finally {
            self::ensureKernelShutdown();
            self::restoreEnv($previousEnv, $previousServer);
        }
    }

    /**
     * @param list<string> $ownTables
     */
    private static function buildStartingSchema(ContainerInterface $container, Scenario $scenario, array $ownTables): Schema
    {
        $schema = self::getSchemaBuilder($container)->buildSchema();
        if ($scenario->getKind() === Scenario::CURRENT_LEGACY_INSTALL) {
            return $schema;
        }

        // This package's tables are left out of the schema the database is created from, and
        // replaced by their older definitions when the scenario has them. Nothing in the database
        // is ever dropped: it's created from this schema in one go.
        foreach ($ownTables as $table) {
            if ($schema->hasTable($table)) {
                $schema->dropTable($table);
            }
        }

        $releasedSchema = $scenario->getReleasedSchema();
        if ($releasedSchema !== null) {
            (new SchemaImporter())->importFromSource($releasedSchema->getYaml(), $schema);
        }

        return $schema;
    }

    private static function executeSchema(Connection $connection, Schema $schema): void
    {
        self::applyRecipeTableOptions($connection, $schema);

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    /**
     * A project configures the schema path's MySQL/MariaDB tables through ibexa_doctrine_schema's
     * table options: the recipe sets utf8mb4 and utf8mb4_unicode_520_ci (DATABASE_CHARSET and
     * DATABASE_COLLATION), which is also what the migrations' SQL creates. A test kernel usually
     * doesn't configure them, and DBAL then falls back to its own utf8 default, so this applies the
     * recipe's options to every table that has none of its own.
     */
    private static function applyRecipeTableOptions(Connection $connection, Schema $schema): void
    {
        if (!in_array(DdlRenderers::getPlatformName($connection), ['mysql', 'mariadb'], true)) {
            return;
        }

        $charset = $_ENV['DATABASE_CHARSET'] ?? getenv('DATABASE_CHARSET');
        $collation = $_ENV['DATABASE_COLLATION'] ?? getenv('DATABASE_COLLATION');
        $charset = is_string($charset) && $charset !== '' ? $charset : self::RECIPE_CHARSET;
        $collation = is_string($collation) && $collation !== '' ? $collation : self::RECIPE_COLLATION;

        foreach ($schema->getTables() as $table) {
            if (!$table->hasOption('charset') && !$table->hasOption('collate') && !$table->hasOption('collation')) {
                $table->addOption('charset', $charset);
                $table->addOption('collate', $collation);
            }
        }
    }

    private static function runMigrations(ContainerInterface $container, Scenario $scenario): void
    {
        if (!$container->has(self::MIGRATIONS_RUNNER)) {
            $message = sprintf(
                'The kernel has no Doctrine Migrations path. Register %s and %s in %s, with ibexa/doctrine-migrations '
                . 'and doctrine/doctrine-migrations-bundle installed, on an ibexa/core version that has %s.',
                'Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle',
                'Ibexa\Bundle\DoctrineMigrations\IbexaDoctrineMigrationsBundle',
                static::getKernelClass(),
                self::MIGRATIONS_RUNNER
            );
            if (self::isEnabled(self::ENV_STRICT)) {
                self::fail($message);
            }
            self::markTestSkipped($message);
        }

        $run = [$container->get(self::MIGRATIONS_RUNNER), 'run'];
        if (!is_callable($run)) {
            throw new LogicException(sprintf('%s has no run() method.', self::MIGRATIONS_RUNNER));
        }

        try {
            $run();
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf(
                "Scenario \"%s\": the migrations failed, starting from %s: %s\n"
                . 'If the failing statement creates something that already exists, that migration\'s guard '
                . '(hasTable/hasColumn/hasIndex) doesn\'t detect that it has nothing to do.',
                $scenario->getId(),
                $scenario->getStartingPoint(),
                $e->getMessage()
            ), 0, $e);
        }
    }

    /**
     * @param list<string> $tables
     *
     * @return array<string, list<string>|null>
     */
    private static function render(Connection $connection, array $tables): array
    {
        $renderer = DdlRenderers::forConnection($connection);
        $rendered = [];
        foreach ($tables as $table) {
            $rendered[$table] = $renderer->render($connection, $table);
        }

        return $rendered;
    }

    /**
     * @param list<string> $ownTables
     *
     * @return list<string> other tables with a foreign key to one of $ownTables
     */
    private static function findInboundForeignKeys(Schema $schema, array $ownTables): array
    {
        $own = array_map('strtolower', $ownTables);
        $inbound = [];
        foreach ($schema->getTables() as $table) {
            if (in_array(strtolower($table->getName()), $own, true)) {
                continue;
            }
            foreach ($table->getForeignKeys() as $foreignKey) {
                if (in_array(strtolower($foreignKey->getForeignTableName()), $own, true)) {
                    $inbound[] = $table->getName();
                }
            }
        }

        return array_values(array_unique($inbound));
    }

    /**
     * @param list<string> $ownTables
     */
    private static function dumpSnapshot(Schema $schema, array $ownTables): void
    {
        $directory = getenv(self::ENV_DUMP_DIR);
        if (!is_string($directory) || $directory === '') {
            return;
        }

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create "%s".', $directory));
        }

        $file = sprintf('%s/%s.yaml', rtrim($directory, '/'), (new ReflectionClass(static::class))->getShortName());
        file_put_contents($file, (new SchemaSnapshotExporter())->export($schema, $ownTables));
    }

    private static function getSchemaBuilder(ContainerInterface $container): SchemaBuilderInterface
    {
        $schemaBuilder = $container->get(SchemaBuilderInterface::class);
        if (!$schemaBuilder instanceof SchemaBuilderInterface) {
            throw new LogicException(sprintf('The kernel has no %s.', SchemaBuilderInterface::class));
        }

        return $schemaBuilder;
    }

    private static function getHistory(): ReleasedSchemaHistory
    {
        if (!isset(self::$histories[static::class])) {
            $packageRoot = self::getPackageRoot();
            self::$histories[static::class] = (new ReleasedSchemaFinder(GitRepository::locate($packageRoot), $packageRoot))
                ->find(static::getSchemaFileHistory());
        }

        return self::$histories[static::class];
    }

    /**
     * The nearest directory above the test class with a composer.json.
     */
    private static function getPackageRoot(): string
    {
        $file = (new ReflectionClass(static::class))->getFileName();
        $directory = $file === false ? false : dirname($file);
        while (is_string($directory) && $directory !== dirname($directory)) {
            if (is_file($directory . '/composer.json')) {
                return $directory;
            }
            $directory = dirname($directory);
        }

        throw new LogicException(sprintf('No composer.json found above %s.', static::class));
    }

    private static function getBaseDatabaseUrl(): string
    {
        if (self::$baseDatabaseUrl === null) {
            $url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
            self::$baseDatabaseUrl = is_string($url) && $url !== '' ? $url : self::DEFAULT_DATABASE_URL;
        }

        return self::$baseDatabaseUrl;
    }

    /**
     * @param mixed $previousEnv
     * @param mixed $previousServer
     */
    private static function restoreEnv($previousEnv, $previousServer): void
    {
        if ($previousEnv === null) {
            unset($_ENV['DATABASE_URL']);
        } else {
            $_ENV['DATABASE_URL'] = $previousEnv;
        }

        if ($previousServer === null) {
            unset($_SERVER['DATABASE_URL']);
        } else {
            $_SERVER['DATABASE_URL'] = $previousServer;
        }
    }

    private static function skipUnlessEnabled(): void
    {
        if (!self::isEnabled(self::ENV_ENABLED)) {
            self::markTestSkipped(sprintf(
                'Run this through the package\'s schema parity phpunit config (%s=1): it creates databases of its own.',
                self::ENV_ENABLED
            ));
        }
    }

    private static function isEnabled(string $variable): bool
    {
        return ($_ENV[$variable] ?? getenv($variable)) === '1';
    }

    private static function readFile(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read "%s".', $path));
        }

        return $contents;
    }
}
