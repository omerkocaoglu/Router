<?php

namespace Fabstract\Component\Router;

use Fabstract\Component\Assert\Assert;

class Router implements RouterInterface
{
    /** @var string[] */
    private $compiled_pattern_lookup = [];
    /** @var string[] */
    private $shortcut_pattern_lookup = [];

    /**
     * @param string $shortcut
     * @param string $pattern
     * @return $this
     */
    public function defineShortcut($shortcut, $pattern)
    {
        Assert::isString($shortcut, 'shortcut');
        Assert::isRegexMatches($shortcut, '/^#\w+$/', 'shortcut');
        Assert::isString($pattern, 'pattern');
        Assert::isRegexPattern('/' . $pattern . '/', 'pattern');

        $this->shortcut_pattern_lookup[$shortcut] = $pattern;
        return $this;
    }

    /**
     * @param string $raw_route
     * @return string
     */
    private function compile($raw_route)
    {
        if (array_key_exists($raw_route, $this->compiled_pattern_lookup) === true) {
            return $this->compiled_pattern_lookup[$raw_route];
        }

        $replaced_route = $this->replaceShortcuts($raw_route);
        $compiled_pattern = preg_replace('/\{\w+\}/', '([\w\-]+)', $replaced_route);

        $this->compiled_pattern_lookup[$raw_route] = $compiled_pattern;
        return $compiled_pattern;
    }

    /**
     * @param string $pattern
     * @return string
     */
    private function replaceShortcuts($pattern)
    {
        return str_replace(
            array_keys($this->shortcut_pattern_lookup),
            array_values($this->shortcut_pattern_lookup),
            $pattern
        );
    }

    /**
     * @param string $uri
     * @param RouteAwareInterface[] $route_aware_list
     * @param bool $is_exact
     * @return RouterMatchResult|null
     */
    public function match($uri, $route_aware_list, $is_exact = false)
    {
        Assert::startsWith($uri, '/', 'uri');
        Assert::isArrayOfType($route_aware_list, RouteAwareInterface::class, 'route_aware_list');

        foreach ($route_aware_list as $route_aware) {
            $matched = $this->internalMatch($uri, $route_aware->getRoute(), $rest_of_uri, $parameters, $is_exact);
            if ($matched === true) {
                return new RouterMatchResult($route_aware, $rest_of_uri, $parameters);
            }
        }
        return null;

    }

    /**
     * @param string $uri
     * @param string $route
     * @param string $rest_of_uri
     * @param string[] $parameters
     * @param bool $is_exact
     * @return bool
     */
    private function internalMatch($uri, $route, &$rest_of_uri, &$parameters, $is_exact = false)
    {
        if ($route === '/') {
            if ($is_exact === false || $uri === '/') {
                $rest_of_uri = $uri;
                $parameters = [];
                return true;
            }
        }

        Assert::isRegexMatches($route, '/^(?:\/|\/[\w\-]+|\/#\w+|\/\{\w+\})+$/', 'route');

        $uri = preg_replace("/\/\/+/", "/", $uri);
        $route = str_replace('/', '\/', $route);

        $compiled_pattern = $this->compile($route);
        if ($is_exact === false) {
            $compiled_pattern = sprintf('/^%s(?<uri>\\/.*)?$/', $compiled_pattern);
        } else {
            $compiled_pattern = sprintf('/^%s\/?$/', $compiled_pattern);
        }

        if (preg_match($compiled_pattern, $uri, $matches) === 1) {

            $rest_of_uri = '/';
            $parameters = [];

            if (array_key_exists('uri', $matches)) {
                $rest_of_uri = $matches['uri'];
            }

            if (count($matches) > 1) {
                $key_list = array_keys($matches);
                $last_key = $key_list[count($key_list) - 1];

                foreach ($matches as $key => $value) {
                    if ($key === 0) {
                        continue;
                    }

                    if (!is_int($key)) {
                        continue;
                    }

                    if ($rest_of_uri !== '/') {
                        if ($last_key === $key) {
                            continue;
                        }
                    }

                    $parameters[] = $value;
                }
            }

            return true;
        }
        return false;
    }
}
