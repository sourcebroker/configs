<?php

declare(strict_types=1);

namespace SourceBroker\Configs;

use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Utility\ArrayUtility;

class Config
{
    protected static Config $instance;
    protected ApplicationContext $context;
    protected Typo3Version $version;
    protected string $configPath = '';
    protected string $configUserPath = '';
    protected string $varPath = '';

    /**
     * @param string $contextFilesPath
     * @throws \Exception
     */
    public function __construct(string $contextFilesPath = 'context')
    {
        $this->context = Environment::getContext();
        $this->version = new Typo3Version();
        $this->configPath = Environment::getConfigPath();
        $this->varPath = Environment::getVarPath();

        // Based on TYPO3_CONTEXT we read files so let secure this part.
        $contextStringParts = explode('/', (string)$this->context);
        foreach ($contextStringParts as $contextStringPart) {
            if ($contextStringPart !== '' && !preg_match('/^[a-zA-Z0-9_,-]+$/', $contextStringPart)) {
                throw new \RuntimeException('TYPO3_CONTEXT parts can consists only from chars a-z, A-Z, 0-9, _, - but one of your part is: "' . $contextStringPart . '" ');
            }
        }
        $this->configUserPath = realpath($this->configPath . '/' . rtrim($contextFilesPath, '/'));
    }

    public static function initialize(): self
    {
        self::$instance = new static();

        return self::$instance;
    }

    public static function includeContextDependentConfigurationFiles(): self
    {
        $layerToFolderMapping = [];
        $layers = glob(self::$instance->configUserPath . '/*');
        foreach ($layers as $layer) {
            $layerParts = explode('_', basename($layer));
            $layerToFolderMapping[$layerParts[0] ?? null] = $layer;
        }
        $contextParts = explode('/', (string)self::$instance->context);

        foreach ($contextParts as $key => $contextPart) {
            $contextPartsComma = explode(',', $contextPart);
            foreach ($contextPartsComma as $contextPartComma) {
                if (empty($contextPartComma)) {
                    continue;
                }
                if (!isset($layerToFolderMapping[$key + 1])) {
                    throw new \RuntimeException('For the ' . ($key + 1) . 'th part ("' . $contextPart . '") of TYPO3_CONTEXT ("' .
                        (string)self::$instance->context . '" there is no corresponding folder. ' .
                        'The expected folder should be located inside folder: ' . self::$instance->configUserPath . '" and start with "' . ($key + 1) . '_",
                        example: "' . self::$instance->configUserPath . '/' . ($key + 1) . '_something"');
                }
                $requireFile = realpath($layerToFolderMapping[$key + 1] . '/' . $contextPartComma . '.php');
                if (!$requireFile) {
                    throw new \RuntimeException('File: "' . $layerToFolderMapping[$key + 1] . '/' . $contextPartComma . '.php" does not exists.');
                }
                if (!str_starts_with($requireFile, self::$instance->configUserPath)) {
                    throw new \RuntimeException('File "' . $requireFile . '" is not inside folder: "' . self::$instance->configUserPath . '"');
                }
                require_once $layerToFolderMapping[$key + 1] . '/' . $contextPartComma . '.php';
            }
        }
        self::loadConfigurationFromEnvironment();

        return self::$instance;
    }

    /**
     * Dynamically loads TYPO3 specific environment variable into TYPO3_CONF_VARS
     * Env vars must start with TYPO3__ and separate sections with __
     *
     * Example: TYPO3__DB__Connections__Default__dbname will be loaded into $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname']
     */
    public static function loadConfigurationFromEnvironment(): self
    {
        foreach ($_ENV as $name => $value) {
            if (strpos($name, 'TYPO3__') !== 0) {
                continue;
            }
            $GLOBALS['TYPO3_CONF_VARS'] = ArrayUtility::setValueByPath(
                $GLOBALS['TYPO3_CONF_VARS'],
                str_replace('__', '/', substr($name, 7)),
                $value
            );
        }

        return self::$instance;
    }

    public static function injectDdevSettings(): self
    {
        $GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive(
            $GLOBALS['TYPO3_CONF_VARS'],
            [
                'DB' => [
                    'Connections' => [
                        'Default' => [
                            'dbname' => 'db',
                            'host' => 'db',
                            'password' => 'db',
                            'port' => '3306',
                            'user' => 'db',
                        ],
                    ],
                ],
                // This GFX configuration allows processing by installed ImageMagick 6
                'GFX' => [
                    'processor' => 'ImageMagick',
                    'processor_path' => '/usr/bin/',
                    'processor_path_lzw' => '/usr/bin/',
                ],
                // This mail configuration sends all emails to mailhog
                'MAIL' => [
                    'transport' => 'smtp',
                    'transport_smtp_server' => 'localhost:1025',
                ],
                'SYS' => [
                    'trustedHostsPattern' => '.*.*',
                    'fileCreateMask' => '0666',
                    'folderCreateMask' => '2777',
                    'encryptionKey' => '034f32a5f27aaf8d7e4b437c6d10d5132b3b8bd451f507a8fc978ab2c9a7e24498a05137e80b917138ab0446bd6dabc4',
                ],
            ]
        );

        return self::$instance;
    }

    /**
     * Set the root page tree title in TYPO3 backend based on TYPO3_CONTEXT values
     */
    public static function appendContextToSiteName(bool $short = true): self
    {
        if ($short) {
            $context = implode('/', array_map(
                static fn ($contextPart) => $contextPart[0] ?? '',
                (array)explode('/', (string)self::$instance->context)
            ));
        } else {
            $context = (string)self::$instance->context;
        }
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] .= '(' . $context . ')';

        return self::$instance;
    }

    public static function get(): self
    {
        return self::$instance;
    }

    public static function useProductionPreset(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = false;
        $GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = false;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = -1;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['belogErrorReporting'] = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors'] = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
        self::disableDeprecationLogging();
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'] = array_replace_recursive(
            [
                LogLevel::DEBUG => [
                    FileWriter::class => ['disabled' => true],
                ],
                LogLevel::INFO => [
                    FileWriter::class => ['disabled' => true],
                ],
                LogLevel::WARNING => [
                    FileWriter::class => ['disabled' => true],
                ],
                LogLevel::ERROR => [
                    FileWriter::class => ['disabled' => false],
                ],
            ],
            $GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration']
        );

        return self::$instance;
    }

    public function useDevelopmentPreset(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = true;
        $GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = true;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '*';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = 1;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['belogErrorReporting'] = E_ALL;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors'] = E_ALL;
        self::enableDeprecationLogging();
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'][LogLevel::WARNING] = [
            FileWriter::class => ['disabled' => false],
        ];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['configs']['caching']['cacheConfigurations']['t3api']['uncache'] = false;

        return self::$instance;
    }

    public static function enableDeprecationLogging(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['TYPO3']['CMS']['deprecations']['writerConfiguration'][LogLevel::NOTICE]['TYPO3\CMS\Core\Log\Writer\FileWriter']['disabled'] = false;

        return self::$instance;
    }

    public static function disableDeprecationLogging(): self
    {
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['TYPO3']['CMS']['deprecations']['writerConfiguration'][LogLevel::NOTICE]['TYPO3\CMS\Core\Log\Writer\FileWriter']['disabled'] = true;

        return self::$instance;
    }
}
