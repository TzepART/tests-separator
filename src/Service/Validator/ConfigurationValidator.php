<?php
declare(strict_types=1);

namespace TestSeparator\Service\Validator;

use Psr\Log\LoggerInterface;
use TestSeparator\Configuration;
use TestSeparator\Exception\Strategy\AllDefaultSeparatingStrategiesAreInvalidException;
use TestSeparator\Exception\Strategy\CodeceptionReportsDirIsEmptyException;
use TestSeparator\Exception\DefaultSeparatingStrategiesNotFoundOrEmptyException;
use TestSeparator\Exception\InvalidPathToResultDirectoryException;
use TestSeparator\Exception\InvalidPathToTestsDirectoryException;
use TestSeparator\Exception\NotAvailableDepthLevelException;
use TestSeparator\Exception\Strategy\PathToCodeceptionReportsDirIsEmptyException;
use TestSeparator\Exception\Strategy\PathToDefaultGroupsIsEmptyException;
use TestSeparator\Exception\Strategy\SuitesDirectoriesCollectionIsEmptyException;
use TestSeparator\Exception\Strategy\ValidationOfStrategyConfigurationException;
use TestSeparator\Exception\UnknownSeparatingStrategyException;
use TestSeparator\Handler\ServicesSeparateTestsFactory;
use TestSeparator\Service\FileSystemHelper;

class ConfigurationValidator
{
    private const AVAILABLE_SEPARATING_STRATEGIES = [
        ServicesSeparateTestsFactory::CODECEPTION_SEPARATING_STRATEGY,
        ServicesSeparateTestsFactory::METHOD_SIZE_SEPARATING_STRATEGY,
    ];

    private const AVAILABLE_DEFAULT_SEPARATING_STRATEGIES = [
        ServicesSeparateTestsFactory::METHOD_SIZE_SEPARATING_STRATEGY,
        ServicesSeparateTestsFactory::DEFAULT_GROUP_STRATEGY,
    ];

    private const AVAILABLE_DEPTH_LEVELS = [
        ServicesSeparateTestsFactory::DIRECTORY_LEVEL,
        ServicesSeparateTestsFactory::CLASS_LEVEL,
        ServicesSeparateTestsFactory::METHOD_LEVEL,
    ];

    private const NOT_AVAILABLE_DEPTH_LEVEL_WAS_GOT = 'Not available depth level was got.';
    private const PATH_TO_TESTS_DIRECTORY_IS_INVALID = 'Path to tests directory is Invalid.';
    private const PATH_TO_RESULTS_DIRECTORY_IS_INVALID = 'Path to results directory is Invalid.';
    private const THERE_WAS_GOT_UNKNOWN_SEPARATING_STRATEGY = 'There was got unknown separating strategy.';
    private const TESTS_SUITES_DIRECTORIES_COLLECTION_IS_EMPTY = 'Tests suites directories Collection is empty.';
    private const PATH_TO_CODECEPTION_REPORTS_DIRECTORY_IS_EMPTY = 'Path to Codeception Reports directory is empty.';
    private const CODECEPTION_REPORTS_DIRECTORY_IS_EMPTY = 'Codeception Reports directory is empty.';
    private const DEFAULT_SEPARATING_STRATEGIES_NOT_FOUND_OR_EMPTY = 'Default separating strategies not found or empty';
    private const THERE_WAS_GOT_UNKNOWN_DEFAULT_SEPARATING_STRATEGY = 'There was got unknown default separating strategy - %s.';
    private const ALL_DEFAULT_SEPARATING_STRATEGIES_ARE_INVALID = 'All Default Separating Strategies are invalid';
    private const PATH_TO_DEFAULT_GROUPS_IS_EMPTY = 'Path to Default Groups is empty';
    private const DEFAULT_GROUPS_DIRECTORY_IS_EMPTY = 'Default groups directory is empty.';

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function validate(Configuration $configuration): bool
    {
        $this->validateTestsDirectory($configuration->getTestsDirectory());
        $this->validateResultPath($configuration->getResultPath());
        $this->validateDepthLevel($configuration->getDepthLevel());

        try {
            $this->validateStrategy($configuration->getSeparatingStrategy(), $configuration);
        } catch (ValidationOfStrategyConfigurationException $e) {
            // TODO move this logic in Service
            if ($configuration->isUseDefaultSeparatingStrategy()) {
                $initialStrategy = $configuration->getSeparatingStrategy();
                if (!is_array($configuration->getDefaultSeparatingStrategies()) || count($configuration->getDefaultSeparatingStrategies()) === 0) {
                    throw new DefaultSeparatingStrategiesNotFoundOrEmptyException(self::DEFAULT_SEPARATING_STRATEGIES_NOT_FOUND_OR_EMPTY);
                }

                foreach ($configuration->getDefaultSeparatingStrategies() as $defaultSeparatingStrategy) {
                    try {
                        $this->validateDefaultStrategy($defaultSeparatingStrategy, $configuration);
                        $configuration->setSeparatingStrategy($defaultSeparatingStrategy);
                        break;
                    } catch (ValidationOfStrategyConfigurationException $e) {
                        $this->logger->notice(
                            sprintf(
                                'Default strategy %s is invalid. With Exception - %s. Message: %s',
                                $defaultSeparatingStrategy,
                                get_class($e),
                                $e->getMessage()
                            )
                        );
                    }
                }

                if ($initialStrategy === $configuration->getSeparatingStrategy()) {
                    throw new AllDefaultSeparatingStrategiesAreInvalidException(self::ALL_DEFAULT_SEPARATING_STRATEGIES_ARE_INVALID);
                }
            } else {
                throw $e;
            }
        }

        return true;
    }

    public function validateTestsDirectory(string $testsDirectory): void
    {
        if (!is_dir($testsDirectory)) {
            throw new InvalidPathToTestsDirectoryException(self::PATH_TO_TESTS_DIRECTORY_IS_INVALID);
        }
    }

    public function validateResultPath(string $resultPath): void
    {
        if (!is_dir($resultPath)) {
            throw new InvalidPathToResultDirectoryException(self::PATH_TO_RESULTS_DIRECTORY_IS_INVALID);
        }
    }

    public function validateDepthLevel(string $depthLevel): void
    {
        if (!in_array($depthLevel, self::AVAILABLE_DEPTH_LEVELS, true)) {
            throw new NotAvailableDepthLevelException(self::NOT_AVAILABLE_DEPTH_LEVEL_WAS_GOT);
        }
    }

    public function validateStrategy(string $separatingStrategy, Configuration $configuration): void
    {
        if (!in_array($separatingStrategy, self::AVAILABLE_SEPARATING_STRATEGIES, true)) {
            throw new UnknownSeparatingStrategyException(self::THERE_WAS_GOT_UNKNOWN_SEPARATING_STRATEGY);
        }

        if ($separatingStrategy === ServicesSeparateTestsFactory::METHOD_SIZE_SEPARATING_STRATEGY) {
            $this->validateMethodSizeStrategy($configuration);
        }

        if ($separatingStrategy === ServicesSeparateTestsFactory::CODECEPTION_SEPARATING_STRATEGY) {
            $this->validateCodeceptionSeparatingStrategy($configuration);
        }
    }

    public function validateDefaultStrategy(string $separatingStrategy, Configuration $configuration): void
    {
        if (!in_array($separatingStrategy, self::AVAILABLE_DEFAULT_SEPARATING_STRATEGIES, true)) {
            throw new UnknownSeparatingStrategyException(sprintf(self::THERE_WAS_GOT_UNKNOWN_DEFAULT_SEPARATING_STRATEGY, $separatingStrategy));
        }

        if ($separatingStrategy === ServicesSeparateTestsFactory::METHOD_SIZE_SEPARATING_STRATEGY) {
            $this->validateMethodSizeStrategy($configuration);
        }

        if ($separatingStrategy === ServicesSeparateTestsFactory::DEFAULT_GROUP_STRATEGY) {
            $this->validateDefaultGroupStrategy($configuration);
        }
    }

    private function validateMethodSizeStrategy(Configuration $configuration): void
    {
        if (count($configuration->getTestSuitesDirectories()) === 0) {
            throw new SuitesDirectoriesCollectionIsEmptyException(self::TESTS_SUITES_DIRECTORIES_COLLECTION_IS_EMPTY);
        }
        //TODO add validation that all Tests Suites Directories contain tests (?)
    }

    private function validateCodeceptionSeparatingStrategy(Configuration $configuration): void
    {
        if ($configuration->getCodeceptionReportsDir() === '') {
            throw new PathToCodeceptionReportsDirIsEmptyException(self::PATH_TO_CODECEPTION_REPORTS_DIRECTORY_IS_EMPTY);
        }

        if (!FileSystemHelper::checkNotEmptyFilesInDir($configuration->getCodeceptionReportsDir())) {
            throw new CodeceptionReportsDirIsEmptyException(self::CODECEPTION_REPORTS_DIRECTORY_IS_EMPTY);
        }
    }

    private function validateDefaultGroupStrategy(Configuration $configuration): void
    {
        if ($configuration->getDefaultGroupsDir() === '') {
            throw new PathToDefaultGroupsIsEmptyException(self::PATH_TO_DEFAULT_GROUPS_IS_EMPTY);
        }

        if (!FileSystemHelper::checkNotEmptyFilesInDir($configuration->getDefaultGroupsDir())) {
            throw new CodeceptionReportsDirIsEmptyException(self::DEFAULT_GROUPS_DIRECTORY_IS_EMPTY);
        }
    }
}
