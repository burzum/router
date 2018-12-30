<?php
declare(strict_types=1);

namespace Lead\Router;

use Closure;
use Lead\Router\Exception\RouterException;

/**
 * The Route class.
 */
class Route
{
    const FOUND = 0;

    const NOT_FOUND = 404;

    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * The route's error.
     *
     * @var integer
     */
    protected $_error = 0;

    /**
     * The route's message.
     *
     * @var string
     */
    protected $_message = 'OK';

    /**
     * Route's name.
     *
     * @var string
     */
    public $name = '';

    /**
     * Named parameter.
     *
     * @var array
     */
    public $params = [];

    /**
     * List of parameters that should persist during dispatching.
     *
     * @var array
     */
    public $persist = [];

    /**
     * The attached namespace.
     *
     * @var string
     */
    public $namespace = '';

    /**
     * The attached request.
     *
     * @var mixed
     */
    public $request = null;

    /**
     * The attached response.
     *
     * @var mixed
     */
    public $response = null;

    /**
     * The dispatched instance (custom).
     *
     * @var object
     */
    public $dispatched = null;

    /**
     * The route scope.
     *
     * @var array
     */
    protected $_scope = null;

    /**
     * The route's host.
     *
     * @var object
     */
    protected $_host = null;

    /**
     * Route's allowed methods.
     *
     * @var array
     */
    protected $_methods = [];

    /**
     * Route's prefix.
     *
     * @var array
     */
    protected $_prefix = '';

    /**
     * Route's pattern.
     *
     * @var string
     */
    protected $_pattern = '';

    /**
     * The tokens structure extracted from route's pattern.
     *
     * @see Parser::tokenize()
     * @var array
     */
    protected $_token = null;

    /**
     * The route's regular expression pattern.
     *
     * @see Parser::compile()
     * @var string
     */
    protected $_regex = null;

    /**
     * The route's variables.
     *
     * @see Parser::compile()
     * @var array
     */
    protected $_variables = null;

    /**
     * The route's handler to execute when a request match.
     *
     * @var Closure
     */
    protected $_handler = null;

    /**
     * The middlewares.
     *
     * @var array
     */
    protected $_middleware = [];

    /**
     * Constructs a route
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        $defaults = [
        'error' => static::FOUND,
        'message' => 'OK',
        'scheme' => '*',
        'host' => null,
        'methods' => '*',
        'prefix' => '',
        'pattern' => '',
        'name' => '',
        'namespace' => '',
        'handler' => null,
        'params' => [],
        'persist' => [],
        'scope' => null,
        'middleware' => [],
        'classes' => [
        'parser' => 'Lead\Router\Parser',
        'host' => 'Lead\Router\Host'
        ]
        ];
        $config += $defaults;

        $this->name = $config['name'];
        $this->namespace = $config['namespace'];
        $this->params = $config['params'];
        $this->persist = $config['persist'];
        $this->handler($config['handler']);

        $this->_classes = $config['classes'];

        $this->_prefix = trim($config['prefix'], '/');
        if ($this->_prefix) {
            $this->_prefix = '/' . $this->_prefix;
        }

        $this->host($config['host'], $config['scheme']);
        $this->setMethods($config['methods']);

        $this->_scope = $config['scope'];
        $this->_middleware = (array)$config['middleware'];
        $this->_error = $config['error'];
        $this->_message = $config['message'];

        $this->setPattern($config['pattern']);
    }

    /**
     * Gets the routes name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets/sets the route host.
     *
     * @param  object $host   The host instance to set or none to get the set one.
     * @param  string $scheme HTTP Scheme
     * @return object|self       The current host on get or `$this` on set.
     */
    public function host($host = null, string $scheme = '*')
    {
        if (!func_num_args()) {
            return $this->_host;
        }

        if (!is_string($host)) {
            $this->_host = $host;

            return $this;
        }

        if ($host !== '*' || $scheme !== '*') {
            $class = $this->_classes['host'];
            $this->_host = new $class(['scheme' => $scheme, 'pattern' => $host]);
        }

        return $this;
    }

    /**
     * Gets allowed methods
     *
     * @return array
     */
    public function getMethods(): array
    {
        return array_keys($this->_methods);
    }

    /**
     * Sets methods
     *
     * @param  array $methods
     * @return $this
     */
    public function setMethods($methods): self
    {
        $methods = $methods ? (array)$methods : [];
        $methods = array_map('strtoupper', $methods);
        $this->_methods = array_fill_keys($methods, true);

        return $this;
    }

    /**
     * Gets/sets the allowed methods.
     *
     * @deprecated Use setMethods() and getMethods() instead.
     * @param      string|array $allowedMethods The allowed methods set or none to get the setted one.
     * @return     array|self                   The allowed methods on get or `$this` on set.
     */
    public function methods($methods = null)
    {
        if (!func_num_args()) {
            return $this->getMethods();
        }

        return $this->setMethods($methods);
    }

    /**
     * Allows additional methods.
     *
     * @param  string|array $methods The methods to allow.
     * @return self
     */
    public function allow($methods = [])
    {
        $methods = $methods ? (array)$methods : [];
        $methods = array_map('strtoupper', $methods);
        $this->_methods = array_fill_keys($methods, true) + $this->_methods;

        return $this;
    }

    /**
     * Gets the routes Scope
     *
     * @return \Lead\Router\Scope
     */
    public function getScope(): ?Scope
    {
        return $this->_scope;
    }

    /**
     * Sets a routes scope
     *
     * @param  \Lead\Router\Scope|null $scope Scope
     * @return $this;
     */
    public function setScope(array $scope): self
    {
        $this->_scope = $scope;

        return $this;
    }

    /**
     * Gets/sets the route scope.
     *
     * @deprecated Use getScope() and setScope() instead
     * @param      object $scope The scope instance to set or none to get the setted one.
     * @return     object|self        The current scope on get or `$this` on set.
     */
    public function scope(?Scope $scope = null)
    {
        if ($scope === null) {
            return $this->getScope();
        }

        $this->_scope = $scope;

        return $this;
    }

    /**
     * Gets the routing error number
     *
     * @return int
     */
    public function getError(): int
    {
        return $this->_error;
    }

    /**
     * Gets the routing error number.
     *
     * @deprecated Use getError() instead
     * @return     integer The routing error number.
     */
    public function error()
    {
        return $this->getError();
    }

    /**
     * Gets the routing error message
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->_message;
    }

    /**
     * Gets the routing error message.
     *
     * @deprecated use getErrorMessage() instead
     * @return     string The routing error message.
     */
    public function message()
    {
        return $this->getErrorMessage();
    }

    /**
     * Gets the routes pattern
     *
     * @return string
     */
    public function getPattern(): string
    {
        return $this->_pattern;
    }

    /**
     * Sets the routes pattern
     *
     * @return $this
     */
    public function setPattern(string $pattern): self
    {
        $this->_token = null;
        $this->_regex = null;
        $this->_variables = null;

        if (!$pattern || $pattern[0] !== '[') {
            $pattern = '/' . trim($pattern, '/');
        }

        $this->_pattern = $this->_prefix . $pattern;

        return $this;
    }

    /**
     * Gets the route's pattern.
     *
     * @deprecated Use setPattern() and getPattern() instead.
     * @return     string The route's pattern.
     */
    public function pattern(?string $pattern = null)
    {
        if ($pattern === null) {
            return $this->getPattern();
        }

        return $this->setPattern($pattern);
    }
    /**
     * Returns the route's token structures.
     *
     * @return array A collection route's token structure.
     */
    public function getToken(): array
    {
        if ($this->_token === null) {
            $parser = $this->_classes['parser'];
            $this->_token = [];
            $this->_regex = null;
            $this->_variables = null;
            $this->_token = $parser::tokenize($this->_pattern, '/');
        }

        return $this->_token;
    }

    /**
     * Returns the route's token structures.
     *
     * @deprecated Use getToken() instead
     * @return     array A collection route's token structure.
     */
    public function token(): array
    {
        return $this->getToken();
    }

    /**
     * Gets the route's regular expression pattern.
     *
     * @deprecated Use getRegex() instead
     * @return     string the route's regular expression pattern.
     */
    public function regex(): string
    {
        return $this->getRegex();
    }
    /**
     * Gets the route's regular expression pattern.
     *
     * @return string the route's regular expression pattern.
     */
    public function getRegex(): string
    {
        if ($this->_regex !== null) {
            return $this->_regex;
        }
        $this->_compile();

        return $this->_regex;
    }

    /**
     * Gets the route's variables and their associated pattern in case of array variables.
     *
     * @return array The route's variables and their associated pattern.
     */
    public function getVariables(): array
    {
        if ($this->_variables !== null) {
            return $this->_variables;
        }
        $this->_compile();

        return $this->_variables;
    }
    /**
     * Gets the route's variables and their associated pattern in case of array variables.
     *
     * @deprecated use getVariables() instead
     * @return     array The route's variables and their associated pattern.
     */
    public function variables(): array
    {
        return $this->getVariables();
    }

    /**
     * Compiles the route's patten.
     */
    protected function _compile()
    {
        $parser = $this->_classes['parser'];
        $rule = $parser::compile($this->getToken());
        $this->_regex = $rule[0];
        $this->_variables = $rule[1];
    }

    /**
     * Gets the routes handler
     *
     * @return mixed
     */
    public function getHandler()
    {
        return $this->_handler;
    }

    /**
     * Gets/sets the route's handler.
     *
     * @param  mixed $handler The route handler.
     * @return self
     */
    public function setHandler($handler)
    {
        $this->_handler = $handler;

        return $this;
    }

    /**
     * Gets/sets the route's handler.
     *
     * @deprecated Use getHandler() and setHandler() instead
     * @param      array $handler The route handler.
     * @return     array|self
     */
    public function handler($handler = null)
    {
        if ($handler === null) {
            return $this->getHandler();
        }

        return $this->setHandler($handler);
    }

    /**
     * Checks if the route instance matches a request.
     *
     * @param  array $request a request.
     * @return boolean
     */
    public function match($request, &$variables = null, &$hostVariables = null): bool
    {
        $hostVariables = [];

        if (($host = $this->host()) && !$host->match($request, $hostVariables)) {
            return false;
        }

        $path = isset($request['path']) ? $request['path'] : '';
        $method = isset($request['method']) ? $request['method'] : '*';

        if (!isset($this->_methods['*']) && $method !== '*' && !isset($this->_methods[$method])) {
            if ($method !== 'HEAD' && !isset($this->_methods['GET'])) {
                return false;
            }
        }

        $path = '/' . trim($path, '/');

        if (!preg_match('~^' . $this->regex() . '$~', $path, $matches)) {
            return false;
        }
        $variables = $this->_buildVariables($matches);
        $this->params = $hostVariables + $variables;

        return true;
    }

    /**
     * Combines route's variables names with the regex matched route's values.
     *
     * @param  array $varNames The variable names array with their corresponding pattern segment when applicable.
     * @param  array $values   The matched values.
     * @return array           The route's variables.
     */
    protected function _buildVariables(array $values): array
    {
        $variables = [];
        $parser = $this->_classes['parser'];

        $i = 1;
        foreach ($this->getVariables() as $name => $pattern) {
            if (!isset($values[$i])) {
                $variables[$name] = !$pattern ? null : [];
                continue;
            }
            if (!$pattern) {
                $variables[$name] = $values[$i] ?: null;
            } else {
                $token = $parser::tokenize($pattern, '/');
                $rule = $parser::compile($token);
                if (preg_match_all('~' . $rule[0] . '~', $values[$i], $parts)) {
                    foreach ($parts[1] as $value) {
                        if (strpos($value, '/') !== false) {
                            $variables[$name][] = explode('/', $value);
                        } else {
                            $variables[$name][] = $value;
                        }
                    }
                } else {
                    $variables[$name] = [];
                }
            }
            $i++;
        }

        return $variables;
    }

    /**
     * Dispatches the route.
     *
     * @param  mixed $response The outgoing response.
     * @return mixed           The handler return value.
     */
    public function dispatch($response = null)
    {
        if ($error = $this->getError()) {
            throw new RouterException($this->message(), $error);
        }
        $this->response = $response;
        $request = $this->request;

        $generator = $this->middleware();

        $next = function () use ($request, $response, $generator, &$next) {
            $handler = $generator->current();
            $generator->next();

            return $handler($request, $response, $next);
        };

        return $next();
    }

    /**
     * Middleware generator.
     *
     * @return callable
     */
    public function middleware()
    {
        foreach ($this->_middleware as $middleware) {
            yield $middleware;
        }

        if ($scope = $this->scope()) {
            foreach ($scope->middleware() as $middleware) {
                yield $middleware;
            }
        }

        yield function () {
            $handler = $this->handler();

            return $handler($this, $this->response);
        };
    }

    /**
     * Adds a middleware to the list of middleware.
     *
     * @param object|Closure A callable middleware.
     */
    public function apply($middleware)
    {
        foreach (func_get_args() as $mw) {
            array_unshift($this->_middleware, $mw);
        }

        return $this;
    }

    /**
     * Returns the route's link.
     *
     * @param  array $params  The route parameters.
     * @param  array $options Options for generating the proper prefix. Accepted values are:
     *                        - `'absolute'` _boolean_: `true` or `false`. - `'scheme'`
     *                        _string_ : The scheme. - `'host'`     _string_ : The host
     *                        name. - `'basePath'` _string_ : The base path. - `'query'`
     *                        _string_ : The query string. - `'fragment'` _string_ : The
     *                        fragment string.
     * @return string          The link.
     */
    public function link(array $params = [], array $options = []): string
    {
        $defaults = [
        'absolute' => false,
        'basePath' => '',
        'query' => '',
        'fragment' => ''
        ];

        $options = array_filter(
            $options, function ($value) {
                return $value !== '*';
            }
        );
        $options += $defaults;

        $params = $params + $this->params;

        $link = $this->_link($this->getToken(), $params);

        $basePath = trim($options['basePath'], '/');
        if ($basePath) {
            $basePath = '/' . $basePath;
        }
        $link = isset($link) ? ltrim($link, '/') : '';
        $link = $basePath . ($link ? '/' . $link : $link);
        $query = $options['query'] ? '?' . $options['query'] : '';
        $fragment = $options['fragment'] ? '#' . $options['fragment'] : '';

        if ($options['absolute']) {
            if ($host = $this->host()) {
                $link = $host->link($params, $options) . "{$link}";
            } else {
                $scheme = !empty($options['scheme']) ? $options['scheme'] . '://' : '//';
                $host = isset($options['host']) ? $options['host'] : 'localhost';
                $link = "{$scheme}{$host}{$link}";
            }
        }

        return $link . $query . $fragment;
    }

    /**
     * Helper for `Route::link()`.
     *
     * @param  array $token  The token structure array.
     * @param  array $params The route parameters.
     * @return string           The URL path representation of the token structure array.
     */
    protected function _link($token, $params)
    {
        $link = '';
        foreach ($token['tokens'] as $child) {
            if (is_string($child)) {
                $link .= $child;
                continue;
            }
            if (isset($child['tokens'])) {
                if ($child['repeat']) {
                    $name = $child['repeat'];
                    $values = isset($params[$name]) && $params[$name] !== null ? (array)$params[$name] : [];
                    if (!$values && !$child['optional']) {
                        throw new RouterException("Missing parameters `'{$name}'` for route: `'{$this->name}#{$this->_pattern}'`.");
                    }
                    foreach ($values as $value) {
                        $link .= $this->_link($child, [$name => $value] + $params);
                    }
                } else {
                    $link .= $this->_link($child, $params);
                }
                continue;
            }

            if (!isset($params[$child['name']])) {
                if (!$token['optional']) {
                    throw new RouterException("Missing parameters `'{$child['name']}'` for route: `'{$this->name}#{$this->_pattern}'`.");
                }

                return '';
            }

            if ($data = $params[$child['name']]) {
                $parts = is_array($data) ? $data : [$data];
            } else {
                $parts = [];
            }
            foreach ($parts as $key => $value) {
                $parts[$key] = rawurlencode((string)$value);
            }
            $value = join('/', $parts);

            if (!preg_match('~^' . $child['pattern'] . '$~', $value)) {
                throw new RouterException("Expected `'" . $child['name'] . "'` to match `'" . $child['pattern'] . "'`, but received `'" . $value . "'`.");
            }
            $link .= $value;
        }

        return $link;
    }
}
