<?php

namespace Uturakulov\LaravelPrometheus;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpFoundation\Request;

class NonBlockingPrometheusLumenRouteMiddleware extends NonBlockingPrometheusLaravelRouteMiddleware
{
    public function getMatchedRoute(Request $request)
    {
        if ($this->cachedRoute !== null) {
            return $this->cachedRoute;
        }

        $routeCollection = new RouteCollection();
        $routes = RouteFacade::getRoutes();

        foreach ($routes as $route) {
            $routeCollection->add(
                new Route(
                    $route['method'],
                    $route['uri'],
                    $route['action']
                )
            );
        }
        return $routeCollection->match($request);
    }
}
