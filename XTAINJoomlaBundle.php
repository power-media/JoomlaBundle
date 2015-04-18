<?php
/**
 * This file is part of the XTAIN Joomla package.
 *
 * (c) Maximilian Ruta <mr@xtain.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XTAIN\Bundle\JoomlaBundle;

use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use XTAIN\Bundle\JoomlaBundle\DependencyInjection\Pass\OverrideCompilerPass;
use XTAIN\Bundle\JoomlaBundle\DependencyInjection\Pass\RoutingCompilerPass;
use XTAIN\Bundle\JoomlaBundle\Security\Factory\JoomlaFactory;

/**
 * Class XTAINJoomlaBundle
 *
 * @author  Maximilian Ruta <mr@xtain.net>
 * @package XTAIN\Bundle\JoomlaBundle
 */
class XTAINJoomlaBundle extends Bundle
{
    /**
     * @var string
     */
    const STOPWATCH_CATEGORY_NAME = 'joomla';

    /**
     * @var string
     */
    const STOPWATCH_PREFIX = 'joomla ';

    /**
     * @param ContainerBuilder $container
     *
     * @return void
     * @author Maximilian Ruta <mr@xtain.net>
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new JoomlaFactory());

        $container->addCompilerPass(new OverrideCompilerPass());
        $container->addCompilerPass(new RoutingCompilerPass());
    }

    /**
     * @return void
     * @author Maximilian Ruta <mr@xtain.net>
     */
    protected function defineConstants()
    {
        // Defines.
        if (!defined('_JEXEC')) {
            define('_JEXEC', 1);
        }

        if (!defined('JSYMFONY')) {
            define('JSYMFONY', 1);
        }

        if (!defined('JPATH_ROOT')) {
            define('JPATH_ROOT', $this->container->getParameter('joomla.root_dir'));
        }

        if (!defined('JPATH_SITE')) {
            define('JPATH_SITE', JPATH_ROOT);
        }

        if (!defined('JPATH_CONFIGURATION')) {
            define('JPATH_CONFIGURATION', __DIR__ . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'config');
        }

        if (!defined('JPATH_ADMINISTRATOR')) {
            define('JPATH_ADMINISTRATOR', JPATH_ROOT . DIRECTORY_SEPARATOR . 'administrator');
        }

        if (!defined('JPATH_LIBRARIES')) {
            define('JPATH_LIBRARIES', JPATH_ROOT . DIRECTORY_SEPARATOR . 'libraries');
        }

        if (!defined('JPATH_PLUGINS')) {
            define('JPATH_PLUGINS', JPATH_ROOT . DIRECTORY_SEPARATOR . 'plugins');
        }

        if (!defined('JPATH_INSTALLATION')) {
            define('JPATH_INSTALLATION', JPATH_ROOT . DIRECTORY_SEPARATOR . 'installation');
        }

        if (!defined('JPATH_MANIFESTS')) {
            define('JPATH_MANIFESTS', JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'manifests');
        }

        if (!defined('JPATH_PLATFORM')) {
            define('JPATH_PLATFORM', JPATH_LIBRARIES);
        }
    }

    /**
     * @return void
     * @author Maximilian Ruta <mr@xtain.net>
     */
    protected function registerAutoloader()
    {
        $this->container->get('joomla.loader');
    }

    /**
     * @return void
     * @author Maximilian Ruta <mr@xtain.net>
     */
    protected function bootstrapCms()
    {
        global $_PROFILER;

        // Register the library base path for CMS libraries.
        \JLoader::registerPrefix('J', JPATH_PLATFORM . '/cms', false, true);

        // Register the class aliases for Framework classes that have replaced their Platform equivilents
        require_once JPATH_LIBRARIES . '/classmap.php';

        // Define the Joomla version if not already defined.
        if (!defined('JVERSION')) {
            $jversion = new \JVersion;
            define('JVERSION', $jversion->getShortVersion());
        }

        // Ensure FOF autoloader included
        // needed for things like content versioning where we need to get an FOFTable Instance
        if (!class_exists('FOFAutoloaderFof')) {
            include_once JPATH_LIBRARIES . '/fof/include.php';
        }

        // Register a handler for uncaught exceptions that shows a pretty error page when possible
        set_exception_handler(['JErrorPage', 'render']);

        // Set up the message queue logger for web requests
        if (array_key_exists('REQUEST_METHOD', $_SERVER)) {
            \JLog::addLogger(['logger' => 'messagequeue'], \JLog::ALL, ['jerror']);
        }

        // Register JArrayHelper due to JRegistry moved to composer's vendor folder
        \JLoader::register('JArrayHelper', JPATH_PLATFORM . '/joomla/utilities/arrayhelper.php');

        // Register classes where the names have been changed to fit the autoloader rules
        // @deprecated  4.0
        \JLoader::register('JToolBar', JPATH_PLATFORM . '/cms/toolbar/toolbar.php');
        \JLoader::register('JButton', JPATH_PLATFORM . '/cms/toolbar/button.php');
        \JLoader::register('JInstallerComponent', JPATH_PLATFORM . '/cms/installer/adapter/component.php');
        \JLoader::register('JInstallerFile', JPATH_PLATFORM . '/cms/installer/adapter/file.php');
        \JLoader::register('JInstallerLanguage', JPATH_PLATFORM . '/cms/installer/adapter/language.php');
        \JLoader::register('JInstallerLibrary', JPATH_PLATFORM . '/cms/installer/adapter/library.php');
        \JLoader::register('JInstallerModule', JPATH_PLATFORM . '/cms/installer/adapter/module.php');
        \JLoader::register('JInstallerPackage', JPATH_PLATFORM . '/cms/installer/adapter/package.php');
        \JLoader::register('JInstallerPlugin', JPATH_PLATFORM . '/cms/installer/adapter/plugin.php');
        \JLoader::register('JInstallerTemplate', JPATH_PLATFORM . '/cms/installer/adapter/template.php');
        \JLoader::register('JExtension', JPATH_PLATFORM . '/cms/installer/extension.php');
        \JLoader::registerAlias('JAdministrator', 'JApplicationAdministrator');
        \JLoader::registerAlias('JSite', 'JApplicationSite');
    }

    /**
     * @return void
     * @author Maximilian Ruta <mr@xtain.net>
     */
    protected function bootstrapFramework()
    {
        // Joomla system checks.
        @ini_set('magic_quotes_runtime', 0);

        // System includes
        require_once JPATH_LIBRARIES . '/import.legacy.php';

        // Pre-Load configuration. Don't remove the Output Buffering due to BOM issues, see JCode 26026
        ob_start();
        require_once JPATH_CONFIGURATION . '/configuration.php';
        ob_end_clean();

        // Set system error handling
        \JError::setErrorHandling(E_NOTICE, 'message');
        \JError::setErrorHandling(E_WARNING, 'message');
        \JError::setErrorHandling(E_ERROR, 'callback', ['JError', 'customErrorPage']);

        // Bootstrap the CMS libraries.
        $this->bootstrapCms();

        // System profiler
        if (JDEBUG) {
            $_PROFILER = $this->container->get('joomla.profiler');
        }
    }

    /**
     * @return void
     * @author Maximilian Ruta <mr@xtain.net>
     */
    protected function registerAlias()
    {
    }

    /**
     * @return void
     * @author Maximilian Ruta <mr@xtain.net>
     */
    public function boot()
    {
        if ($this->container->getParameter('kernel.debug')) {
            define('JDEBUG', 2);
        } else {
            define('JDEBUG', 0);
        }

        if ($this->container->has('debug.stopwatch')) {
            $stopwatch = $this->container->get('debug.stopwatch');
            $stopwatch->start(self::STOPWATCH_PREFIX . 'bootstrap', self::STOPWATCH_CATEGORY_NAME);
        }

        $this->defineConstants();
        $this->registerAutoloader();
        $this->registerAlias();
        $this->bootstrapFramework();

        // restore the symfony error handle
        restore_error_handler();
        restore_exception_handler();

        if ($this->container->has('debug.stopwatch')) {
            $stopwatch = $this->container->get('debug.stopwatch');
            $stopwatch->stop(self::STOPWATCH_PREFIX . 'bootstrap', self::STOPWATCH_CATEGORY_NAME);
        }
    }
}