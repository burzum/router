<?php
namespace Lead\Router;

use Closure;

/**
 * The Route class.
 */
class Route
{
    const FOUND = 0;

    const NOT_FOUND = 404;

    const METHOD_NOT_ALLOWED = 405;

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
     * The matching HTTP method.
     *
     * @var string
     */
    public $method = '*';

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
     * Route's prefix.
     *
     * @var array
     */
    protected $_prefix = '';

    /**
     * Route's patterns.
     *
     * @var array
     */
    protected $_patterns = [];

    /**
     * Collection of tokens structures extracted from route's patterns.
     *
     * @see Parser::tokenize()
     * @var array
     */
    protected $_tokens = null;

    /**
     * Rules extracted from route's tokens structures.
     *
     * @see Parser::compile()
     * @var array
     */
    protected $_rules = null;

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
            'error'    => static::FOUND,
            'message'  => 'OK',
            'scheme'     => '*',
            'host'       => null,
            'method'     => '*',
            'prefix'     => '',
            'patterns'   => [],
            'name'       => '',
            'namespace'  => '',
            'handler'    => null,
            'params'     => [],
            'persist'    => [],
            'scope'      => null,
            'middleware' => [],
            'classes'    => [
                'parser' => 'Lead\Router\Parser',
                'host'   => 'Lead\Router\Host'
            ]
        ];
        $config += $defaults;

        $this->method = $config['method'];
        $this->name = $config['name'];
        $this->namespace = $config['namespace'];
        $this->params = $config['params'];
        $this->persist = $config['persist'];
        $this->handler($config['handler']);

        $this->_classes = $config['classes'];

        if ($config['prefix'] && $config['prefix'][0] !== '[') {
            $this->_prefix = trim($config['prefix'], '/');
            $this->_prefix = $this->_prefix ? '/' . $this->_prefix : '';
        }

        if (is_string($config['host']) && $config['host'] !== '*') {
            $host = $this->_classes['host'];
            $this->_host = new $host(['scheme' => $config['scheme'], 'host' => $config['host']]);
        } else {
            $this->_host = $config['host'];
        }
        $this->_scope = $config['scope'];
        $this->_middleware = (array) $config['middleware'];
        $this->_error = $config['error'];
        $this->_message = $config['message'];

        foreach ((array) $config['patterns'] as $pattern) {
            $this->append($pattern);
        }
    }

    /**
     * Gets/sets the route host.
     *
     * @param  object      $host The host instance to set or none to get the setted one.
     * @return object|self       The current host on get or `$this` on set.
     */
    public function host($host = null)
    {
        if (!func_num_args()) {
            return $this->_host;
        }
        $this->_host = $host;
        return $this;
    }

    /**
     * Gets/sets the route scope.
     *
     * @param  object      $scope The scope instance to set or none to get the setted one.
     * @return object|self        The current scope on get or `$this` on set.
     */
    public function scope($scope = null)
    {
        if (!func_num_args()) {
            return $this->_scope;
        }
        $this->_scope = $scope;
        return $this;
    }

    /**
     * Gets the routing error number.
     *
     * @return integer The routing error number.
     */
    public function error()
    {
        return $this->_error;
    }

    /**
     * Gets the routing error message.
     *
     * @return string The routing error message.
     */
    public function message()
    {
        return $this->_message;
    }

    /**
     * Returns route's patterns.
     *
     * @return array The route's patterns.
     */
    public function patterns()
    {
        return $this->_patterns;
    }

    /**
     * Appends a pattern to the route.
     *
     * @param  string $pattern The pattern to append.
     * @return self
     */
    public function append($pattern)
    {
        $this->_tokens = null;
        $this->_rules = null;
        if ($pattern && $pattern[0] !== '[') {
            $pattern = '/' . ltrim($pattern, '/');
        }
        $this->_patterns[] = $this->_prefix . $pattern;
        return $this;
    }

    /**
     * Prepends a pattern to the route.
     *
     * @param  string $pattern The pattern to prepend.
     * @return self
     */
    public function prepend($pattern)
    {
        $this->_tokens = null;
        $this->_rules = null;
        if ($pattern && $pattern[0] !== '[') {
            $pattern = '/' . ltrim($pattern, '/');
        }
        array_unshift($this->_patterns, $this->_prefix . $pattern);
        return $this;
    }

    /**
     * Returns the route's tokens structures.
     *
     * @return array A collection route's tokens structure.
     */
    public function tokens()
    {
        if ($this->_tokens === null) {
            $parser = $this->_classes['parser'];
            $this->_tokens = [];
            $this->_rules = null;
            foreach ($this->_patterns as $pattern) {
                $this->_tokens[] = $parser::tokenize($pattern, '/');
            }
        }
        return $this->_tokens;
    }

    /**
     * Returns the compiled route.
     *
     * @return array A collection of route regex and their associated variable names.
     */
    public function rules()
    {
        if ($this->_rules !== null) {
            return $this->_rules;
        }

        $parser = $this->_classes['parser'];

        foreach ($this->tokens() as $token) {
            $this->_rules[] = $parser::compile($token);
        }

        return $this->_rules;
    }

    /**
     * Gets/sets the route's handler.
     *
     * @param  array      $handler The route handler.
     * @return array|self
     */
    public function handler($handler = null)
    {
        if (func_num_args() === 0) {
            return $this->_handler;
        }
        $this->_handler = $handler;
        return $this;
    }

    /**
     * Checks if the route instance matches a request.
     *
     * @param  array   $request a request.
     * @return boolean
     */
    public function match($request, &$variables = null, &$hostVariables = null)
    {
        $hostVariables = [];

        if (($host = $this->host()) && !$host->match($request, $hostVariables)) {
            return false;
        }

        $path = isset($request['path']) ? $request['path'] : '/';
        $method = isset($request['method']) ? $request['method'] : '*';

        if ($this->method !== '*' && $method !== '*' && $method !== $this->method) {
            if ($method !== 'HEAD' && $this->method !== 'GET') {
                return false;
            }
        }

        $rules = $this->rules();

        foreach ($rules as $rule) {
            if (!preg_match('~^' . $rule[0] . '$~', $path, $matches)) {
                continue;
            }
            $variables = $this->_buildVariables($rule[1], $matches);
            $this->params = $hostVariables + $variables;
            return true;
        }
        return false;
    }

    /**
     * Combines route's variables names with the regex matched route's values.
     *
     * @param  array $varNames The variable names array with their corresponding pattern segment when applicable.
     * @param  array $values   The matched values.
     * @return array           The route's variables.
     */
    protected function _buildVariables($varNames, $values)
    {
        $variables = [];
        $parser = $this->_classes['parser'];

        $values = $this->_cleanMatches($values);

        foreach ($values as $value) {
            list($name, $pattern) = each($varNames);
            if (!$pattern) {
                $variables[$name] = $value;
            } else {
                $parsed = $parser::tokenize($pattern, '/');
                $rule = $parser::compile($parsed);
                if (preg_match_all('~' . $rule[0] . '~', $value, $parts)) {
                    $variables[$name] = $parts[1];
                }
            }
        }
        return $variables;
    }

    /**
     * Filters out all empty values of not found groups.
     *
     * @param  array $matches Some regex matched values.
     * @return array          The real matched values.
     */
    protected function _cleanMatches($matches)
    {
        $result = [];
        $len = count($matches);
        while ($len > 1 && !$matches[$len - 1]) {
            $len--;
        }
        for ($i = 1; $i < $len; $i++)
        {
            $result[] = $matches[$i];
        }
        return $result;
    }

    /**
     * Dispatches the route.
     *
     * @param  mixed $response The outgoing response.
     * @return mixed           The handler return value.
     */
    public function dispatch($response = null)
    {
        if ($error = $this->error()) {
            throw new RouterException($this->message(), $error);
        }
        $this->response = $response;
        $request = $this->request;

        $generator = $this->middleware();

        $next = function() use ($request, $response, $generator, &$next) {
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

        yield function() {
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
     * @param  array  $params  The route parameters.
     * @param  array  $options Options for generating the proper prefix. Accepted values are:
     *                         - `'absolute'` _boolean_: `true` or `false`.
     *                         - `'scheme'`   _string_ : The scheme.
     *                         - `'host'`     _string_ : The host name.
     *                         - `'basePath'` _string_ : The base path.
     *                         - `'query'`    _string_ : The query string.
     *                         - `'fragment'` _string_ : The fragment string.
     * @return string          The link.
     */
    public function link($params = [], $options = [])
    {
        $defaults = [
            'absolute' => false,
            'scheme'   => 'http',
            'host'     => 'localhost',
            'basePath' => '',
            'query'    => '',
            'fragment' => ''
        ];
        if ($host = $this->host()) {
            $options += [
                'scheme' => $host->scheme,
                'host'   => $host->host
            ];
        }

        $options = array_filter($options, function($value) { return $value !== '*'; });
        $options += $defaults;

        $params = $params + $this->params;

        $tokens = $this->tokens();

        $link = '';

        foreach ($tokens as $token) {
            $missing = null;
            $link = $this->_link($token, $params, false, $missing);
            if (!$missing) {
                break;
            }
        }

        if (!empty($missing)) {
            $patterns = join(',', $this->_patterns);
            throw new RouterException("Missing parameters `'{$missing}'` for route: `'{$this->name}#{$patterns}'`.");
        }
        $basePath = trim($options['basePath'], '/');
        if ($basePath) {
            $basePath = '/' . $basePath;
        }
        $link = isset($link) ? ltrim($link, '/') : '';
        $link = $basePath . ($link ? '/' . $link : $link);
        $query = $options['query'] ? '?' . $options['query'] : '';
        $fragment = $options['fragment'] ? '#' . $options['fragment'] : '';

        if ($options['absolute']) {
            $scheme = $options['scheme'] ? $options['scheme'] . '://' : '//';
            $link = "{$scheme}{$options['host']}{$link}";
        }

        return $link . $query . $fragment;
    }

    /**
     * Helper for `Route::link()`.
     *
     * @param  array  $token    The token structure array.
     * @param  array  $params   The route parameters.
     * @param  array  $optional Indicates if the parameters are optionnal or not.
     * @param  array  $missing  Will be populated with the missing parameter name when applicable.
     * @return string           The URL path representation of the token structure array.
     */
    public function _link($token, $params, $optional = false, &$missing)
    {
        $link = '';
        foreach ($token['tokens'] as $child) {
            if (is_string($child)) {
                $link .= $child;
                continue;
            }
            if (isset($child['tokens'])) {
                $link .= $this->_link($child, $params, $child['optional'], $missing);
                continue;
            }
            if (!array_key_exists($child['name'], $params)) {
                if (!$optional) {
                    $missing = $child['name'];
                }
                return '';
            }
            $link .= $params[$child['name']];
        }
        return $link;
    }
}
