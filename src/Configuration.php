<?php
declare(strict_types=1);

namespace TestSeparator;

class Configuration
{
    /**
     * @var string
     */
    private $strategy;

    /**
     * @var string
     */
    private $allureReportsDirectory;

    /**
     * @var string
     */
    private $codeceptionFile;

    /**
     * @var string
     */
    private $testsDirectory;

    /**
     * @var string
     */
    private $resultPath;

    /**
     * @var string
     */
    private $depthLevel;

    /**
     * Configuration constructor.
     *
     * @param string $configPath
     */
    public function __construct(string $configPath)
    {
        $config = json_decode(file_get_contents($configPath), true);
        $this->strategy = $config['strategy'];
        $this->allureReportsDirectory = $config['allure-reports-directory'];
        $this->codeceptionFile = $config['codeception-file'];
        $this->testsDirectory = $config['tests-directory'];
        $this->resultPath = $config['result-path'];
        $this->depthLevel = $config['level'];
    }


    /**
     * @return string
     */
    public function getStrategy(): string
    {
        return $this->strategy;
    }

    /**
     * @return string
     */
    public function getAllureReportsDirectory(): string
    {
        return $this->allureReportsDirectory;
    }

    /**
     * @return string
     */
    public function getCodeceptionFile(): string
    {
        return $this->codeceptionFile;
    }

    /**
     * @return string
     */
    public function getTestsDirectory(): string
    {
        return $this->testsDirectory;
    }

    /**
     * @return string
     */
    public function getResultPath(): string
    {
        return $this->resultPath;
    }

    /**
     * @return string
     */
    public function getDepthLevel(): string
    {
        return $this->depthLevel;
    }

}
