<?php
/**
 * This file is part of the XTAIN Joomla package.
 *
 * (c) Maximilian Ruta <mr@xtain.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XTAIN\Bundle\JoomlaBundle\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use XTAIN\Bundle\JoomlaBundle\Entity\Menu;
use XTAIN\Bundle\JoomlaBundle\Entity\MenuRepositoryInterface;

/**
 * Class PathMatcher
 *
 * @author Maximilian Ruta <mr@xtain.net>
 * @package XTAIN\Bundle\JoomlaBundle\Routing
 */
class PathMatcher implements PathMatcherInterface
{
    /**
     * @var array
     */
    protected $cachedRoutes = [];

    /**
     * @var array
     */
    protected $sortedRoutes = [];

    /**
     * @var JoomlaRouter
     */
    protected $router;

    /**
     * @var MenuRepositoryInterface
     */
    protected $menuRepository;

    /**
     * @param JoomlaRouter            $router
     * @param MenuRepositoryInterface $menuRepository
     */
    public function __construct(JoomlaRouter $router, MenuRepositoryInterface $menuRepository)
    {
        $this->router = $router;
        $this->menuRepository = $menuRepository;
    }

    /**
     * @return array
     * @author Maximilian Ruta <mr@xtain.net>
     */
    public function getSortedRoutes()
    {
        if (!empty($this->sortedRoutes)) {
            return $this->sortedRoutes;
        }

        $routeCollection = $this->router->getRouteCollection();

        foreach ($routeCollection as $name => $route) {
            $this->sortedRoutes[$route->getPath()] = $name;
        }

        uksort($this->sortedRoutes, function ($a, $b) {
            if (strlen($a) == strlen($b)) {
                return 0;
            }
            if (strlen($a) < strlen($b)) {
                return -1;
            }

            return 1;
        });

        return $this->sortedRoutes;
    }

    /**
     * @param Route $searchRoute
     *
     * @return null|string
     * @author Maximilian Ruta <mr@xtain.net>
     */
    public function findMatchingPaths(Route $searchRoute)
    {
        if (isset($this->cachedRoutes[$searchRoute->getPath()])) {
            return $this->cachedRoutes[$searchRoute->getPath()];
        }

        /** @var Route $route */
        foreach ($this->getSortedRoutes() as $routePath => $name) {
            if (preg_match('#^' . preg_quote(rtrim($routePath, '/') . '/', '#') . '#', $searchRoute->getPath())) {
                $this->cachedRoutes[$searchRoute->getPath()] = $name;

                return $name;
            }
        }

        return null;
    }

    /**
     * @param string $name
     * @param bool   $referenceType
     *
     * @return array
     * @author Maximilian Ruta <mr@xtain.net>
     */
    public function getBasePath($name, $referenceType = RouterInterface::ABSOLUTE_PATH)
    {
        $baseLink = null;
        $matchingRouteName = null;
        $matchingRoutePath = null;

        $baseRoute = $this->router->getRouteCollection()->get($name);
        if ($baseRoute === null) {
            return [
                null,
                null
            ];
        }
        $matchingRouteName = $this->findMatchingPaths($baseRoute);

        $item = $this->menuRepository->findByViewRoute($matchingRouteName);
        if ($item !== null) {
            $matchingRoutePath = $this->router->generate($matchingRouteName, [], $referenceType, false);

            $app = \JFactory::getApplication();
            $joomlaRouter = $app::getRouter();

            if ($item !== null) {
                if ($joomlaRouter->getMode() == \JROUTER_MODE_SEF) {
                    $baseLink = \JRoute::_('index.php?Itemid=' . $item->getId());
                } else {
                    $baseLink = \JRoute::_($item->getLink() . '&path=');
                }
            }
        }

        return [
            $baseLink,
            $matchingRoutePath
        ];
    }

    /**
     * @param string $link
     *
     * @return array
     * @author Maximilian Ruta <mr@xtain.net>
     */
    public static function parseParameters($link)
    {
        $pos = strpos($link, '?');
        if ($pos !== null) {
            $link = substr($link, $pos + 1);
        }

        $params = [];
        parse_str($link, $params);

        return $params;
    }

    /**
     * @param Request $request
     *
     * @return null|Menu
     * @author Maximilian Ruta <mr@xtain.net>
     */
    public function findMenuPointForRequest(Request $request)
    {
        $items = $this->menuRepository->findByComponentAndView('com_symfony', 'wrap');

        foreach ($items as $item) {
            $link = $item->getLink();

            $params = self::parseParameters($link);

            if (!isset($params['pattern'])) {
                continue;
            }

            $pattern = $params['pattern'];

            if (preg_match('#' . $pattern . '#i', $request->getPathInfo())) {
                return $item;
            }
        }

        return null;
    }
}