<?php

namespace Ibexa\Contracts\Test\Core\Translation;

use JMS\TranslationBundle\Translation\Comparison\ChangeSet;
use JMS\TranslationBundle\Translation\ConfigFactory;
use JMS\TranslationBundle\Translation\Updater;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class Translation
{
    private ConfigFactory $configFactory;

    private Updater $updater;

    private Environment $twigEnvironment;

    private bool $twigConfigured = false;

    /** @var array<string>|null */
    private ?array $filters;

    /** @var array<string>|null */
    private ?array $functions;

    /**
     * @param array<string>|null $filters
     * @param array<string>|null $functions
     */
    public function __construct(
        ConfigFactory $configFactory,
        Updater $updater,
        Environment $twigEnvironment,
        ?array $filters = null,
        ?array $functions = null
    ) {
        $this->configFactory = $configFactory;
        $this->updater = $updater;
        $this->twigEnvironment = $twigEnvironment;
        $this->filters = $filters;
        $this->functions = $functions;
    }

    public function getChangeSet(string $configName): ChangeSet
    {
        $config = $this->getConfig($configName);

        return $this->updater->getChangeSet($config);
    }

    public function process(string $configName): void
    {
        $config = $this->getConfig($configName);

        $this->updater->process($config);
    }

    private function getConfig(string $configName): \JMS\TranslationBundle\Translation\Config
    {
        $this->configureHandlerForMissingTwig();

        $builder = $this->configFactory->getBuilder($configName);
        $builder->setLocale('en');

        return $builder->getConfig();
    }

    private function configureHandlerForMissingTwig(): void
    {
        if ($this->twigConfigured) {
            return;
        }

        $this->twigEnvironment->registerUndefinedFunctionCallback(function (string $name): ?TwigFunction {
            if ($this->functions === null || in_array($name, $this->functions, true)) {
                return new TwigFunction($name, static fn(): string => '');
            }

            return null;
        });

        $this->twigEnvironment->registerUndefinedFilterCallback(function (string $name): ?TwigFilter {
            if ($this->filters === null || in_array($name, $this->filters, true)) {
                return new TwigFilter($name, static fn($value) => $value);
            }

            return null;
        });

        $this->twigConfigured = true;
    }
}
