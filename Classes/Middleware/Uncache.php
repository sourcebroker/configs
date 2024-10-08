<?php

declare(strict_types=1);

namespace SourceBroker\Configs\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;

class Uncache implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     * @throws \Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (Environment::getContext()->isDevelopment()) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'] as $configurationName => $configuration) {
                if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['configs']['caching']['cacheConfigurations'][$configurationName]['uncache'])
                    && $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['configs']['caching']['cacheConfigurations'][$configurationName]['uncache'] === false) {
                    continue;
                }
                if (isset($configuration['backend']) && is_a(
                        $configuration['backend'],
                        \TYPO3\CMS\Core\Cache\Backend\PhpCapableBackendInterface::class,
                        true
                    )) {
                    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$configurationName]['backend']
                        = \TYPO3\CMS\Core\Cache\Backend\NullBackend::class;
                } elseif (isset($configuration['frontend']) && is_a(
                        $configuration['frontend'],
                        \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
                        true
                    )) {
                    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$configurationName]['backend']
                        = \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class;
                    unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$configurationName]['options']);
                } else {
                    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$configurationName]['frontend']
                        = \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
                    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$configurationName]['backend']
                        = \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class;
                    unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$configurationName]['options']);
                }
            }
        }
        return $handler->handle($request);
    }
}
