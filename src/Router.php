<?php

namespace bera\router;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use bera\router\exceptions\RouteClassNotFoundException;
use bera\router\exceptions\RouteHandlerMethodException;

/**
 * @author Joy Kumar Bera<joykumarbera@gmail.com>
 */
class Router
{
    /**
     * @var array $routes
     */
    private $routes;

    /**
     * @var string $controller_namespace
     */
    private $controller_namespace;

    /**
     * @var string $middleware_namespace
     */
    private $middleware_namespace;

    /**
     * @var mixed $not_found404_handler
     */
    private $not_found404_handler;

    /**
     * @var mixed $all_option_request_handler
     */
    private $all_option_request_handler;

    /**
     * @var bool $is_group_enable
     */
    private $is_group_enable;

    /**
     * @var array $route_group_config
     */
    private $route_group_config;

    /**
     * @var string $route_group_endpoint_prefix
     */
    private $route_group_endpoint_prefix;

    /**
     * Constructor
     * 
     * @param string|null $controller_namespace
     * @param string|null $middleware_namespace
     */
    public function __construct(?string $controller_namespace = null, ?string $middleware_namespace = null)
    {
        $this->routes = [];
        $this->controller_namespace = $controller_namespace ?? '\\app\\controllers\\';
        $this->middleware_namespace = $middleware_namespace ?? '\\app\\middlewares\\';
        $this->not_found404_handler = null;
        $this->is_group_enable = false;
        $this->route_group_config = [];
        $this->route_group_endpoint_prefix = '';
    }

    /**
     * Set 404 route handler
     * 
     * @param mixed $callback
     */
    public function set404Route($callback) 
    {
        $this->not_found404_handler = $callback;
    }

    /**
     * Set all OPTION request handler method
     * 
     * @param mixed $callback
     */
    public function setOptionRequestHandlerRoute($callback)
    {
        $this->all_option_request_handler = $callback;
    }

    /**
     * Add a GET request endpoint
     * 
     * @param string $endpoint
     * @param callback $callback
     * @param array $middlewares
     * @param string $controller_namespace
     */
    public function get(string $endpoint, $callback, array $middlewares = [], string $controller_namespace = '')
    {
        if($this->is_group_enable) {
           
            $endpoint = $this->route_group_endpoint_prefix . $endpoint;
            if( array_key_exists('middlewares', $this->route_group_config) ) {
                if( isset($this->route_group_config['middlewares']['before'] ) ) {
                    if(isset($middlewares['before'])) {
                        $middlewares['before'] = array_merge(
                            $this->route_group_config['middlewares']['before'],
                            $middlewares['before']
                        );
                    } else {
                        $middlewares['before'] = $this->route_group_config['middlewares']['before'];
                    }
                }

                if( isset($this->route_group_config['middlewares']['after'] ) ) {
                    if(isset($middlewares['after'])) {
                        $middlewares['after'] = array_merge(
                            $this->route_group_config['middlewares']['after'],
                            $middlewares['after']
                        );
                    } else {
                        $middlewares['after'] = $this->route_group_config['middlewares']['after'];
                    }
                }
            }

            $controller_namespace = $this->route_group_config['namespace'];
            $this->addRequset('GET', $endpoint, $callback, $middlewares, $controller_namespace);
        } else {
            $this->addRequset('GET', $endpoint, $callback, $middlewares, $controller_namespace);
        }
    }

    /**
     * Add a POST request endpoint
     * 
     * @param string $endpoint
     * @param callback $callback
     * @param array $middlewares
     * @param string $controller_namespace
     */
    public function post(string $endpoint, $callback, array $middlewares = [], string $controller_namespace = '')
    {
        if($this->is_group_enable) {
            $endpoint = $this->route_group_endpoint_prefix . $endpoint;
            if( array_key_exists('middlewares', $this->route_group_config) ) {
                if( isset($this->route_group_config['middlewares']['before'] ) ) {
                    if(isset($middlewares['before'])) {
                        $middlewares['before'] = array_merge(
                            $this->route_group_config['middlewares']['before'],
                            $middlewares['before']
                        );
                    } else {
                        $middlewares['before'] = $this->route_group_config['middlewares']['before'];
                    }
                }

                if( isset($this->route_group_config['middlewares']['after'] ) ) {
                    if(isset($middlewares['after'])) {
                        $middlewares['after'] = array_merge(
                            $this->route_group_config['middlewares']['after'],
                            $middlewares['after']
                        );
                    } else {
                        $middlewares['after'] = $this->route_group_config['middlewares']['after'];
                    }
                }
            }
            $controller_namespace = $this->route_group_config['namespace'];

            $this->addRequset('POST', $endpoint, $callback, $middlewares, $controller_namespace);
        } else {
            $this->addRequset('POST', $endpoint, $callback, $middlewares, $controller_namespace);
        }
    }

    /**
     * Add a route group
     * 
     * @param string $endpoint_prefix
     * @param callable $callback
     * @param array $options
     */
    public function group(string $endpoint_prefix, callable $callback, $options = [])
    {
        $this->is_group_enable = true;
        $this->route_group_endpoint_prefix = $endpoint_prefix;
        $this->route_group_config = $options;

        call_user_func($callback, $this);

        $this->is_group_enable = false;
        $this->route_group_endpoint_prefix = '';
        $this->route_group_config = [];
    }

    /**
     * Add request to the router
     * 
     * @param string $type
     * @param string $endpoint
     * @param callback $callback
     * @param array $middlewares
     */
    private function addRequset(string $type, string $endpoint, $callback, array $middlewares, string $controller_namespace)
    {
        $route_handler = [];
        $route_handler['callback'] = $callback;
        $route_handler['type'] = $type;
        $route_handler['controller_namespace'] = !empty($controller_namespace) ? $controller_namespace : $this->controller_namespace;

        if(array_key_exists('before', $middlewares)) {
            if( !empty($middlewares['before']) ) {
                $route_handler['before_middlewares'] = $middlewares['before'];
            }
        }

        if(array_key_exists('after', $middlewares)) {
            if( !empty($middlewares['after']) ) {
                $route_handler['after_middlewares'] = $middlewares['after'];
            }
        }
        
        $replacement = '([\w\-_]+)'; // allow all slug for now
        $url_params = [];
        $decorated_string = preg_replace_callback(
            '/\{(.*?)\}/',
            function ($matches) use (&$url_params, $replacement) {
                $url_params[] = $matches[1];
                return $replacement;
            },
            $endpoint
        );

        $final_endpoint = '#^' . $type . '|' . $decorated_string . '$#';
        $route_handler['params'] = $url_params;
        
        $this->routes[$final_endpoint] = $route_handler;
    }

    /**
     * Get current request method and route
     * 
     * @return array
     */
    private function getRequestMethodAndRoute()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $request_uri = $_SERVER['REQUEST_URI'];

        if(strpos($request_uri, '?') !== false) {
            $endpoint = explode('?', $request_uri)[0];
        } else {
            $endpoint = $request_uri;
        }

        return [$method, $endpoint];
    }

    /**
     * Handle option request
     * 
     * @param Request $request
     * @param Response $response
     */
    public function handleOptionRequest($request, $response)
    {
        if( !is_null($this->all_option_request_handler) && is_callable($this->all_option_request_handler) ) {
            call_user_func($this->all_option_request_handler);
        } else if(!is_null($this->all_option_request_handler) && is_string($this->all_option_request_handler) && strpos($this->all_option_request_handler, '@') !== false ) {
            list($className, $actionName) = explode('@', $this->all_option_request_handler);
            $class = $this->controller_namespace . $className;
            $classInstance = new $class();
            call_user_func([$classInstance, $actionName]);
        } else {
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
            return $response->send();
        }
    }

    /**
     * Handle 404 routes
     */
    private function handle404()
    {
        if( !is_null($this->not_found404_handler) && is_callable($this->not_found404_handler) ) {
            call_user_func($this->not_found404_handler);
        } else if(!is_null($this->not_found404_handler) && is_string($this->not_found404_handler) && strpos($this->not_found404_handler, '@') !== false ) {
            list($className, $actionName) = explode('@', $this->not_found404_handler);
            $class = $this->controller_namespace . $className;
            $classInstance = new $class();
            call_user_func([$classInstance, $actionName]);
        } else {
            http_response_code(404);
            die("No route found");
        }
    }

    /**
     * Get all routes
     * 
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Dispatch the router
     */
    public function dispatch()
    {
        list($method, $endpoint) = $this->getRequestMethodAndRoute();

        $url_params_values = [];
        $callback = '';
        $matched_route = '';
        $controller_namespace = '';
        foreach($this->routes as $route => $route_info) {
            if(preg_match(str_replace(['GET|', 'POST|'], '', $route), $endpoint, $matches) && $route_info['type'] == $method) {
                array_shift($matches);
                $url_params_values = $matches;
                $callback = $route_info['callback'];
                $controller_namespace = $route_info['controller_namespace'];
                $matched_route = $route;
                break;
            }
        }

        $request = Request::createFromGlobals();
        $response = new Response(
            '',
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );

        if($method == 'OPTIONS') {
            $this->handleOptionRequest($request, $response);
            return;
        }

        if($callback == '') {
            $this->handle404();
            return;
        }

        $before_middleware_status = true;

        if(isset($this->routes[$matched_route]['before_middlewares'])) {
            $before_middleware_status = $this->applyMiddlewares(
                'before',
                $matched_route,
                $request,
                $response
            );
        }

        if($before_middleware_status) {
            $this->fireAction($controller_namespace, $callback, $url_params_values, $request, $response);
        }
        
        if(isset($this->routes[$matched_route]['after_middlewares'])) {
            $this->applyMiddlewares(
                'after',
                $matched_route,
                $request,
                $response
            );
        }
    }

    /**
     * Call actual action
     * 
     * @param string $controller_namespace
     * @param string $callback
     * @param array $url_params_values
     * @param Request $request
     * @param Response $response
     */
    private function fireAction($controller_namespace, $callback, $url_params_values, $request, $response) 
    {
        $final_params = !empty($url_params_values) ?
            array_merge(array_values($url_params_values), [$request, $response])
            :
            [$request, $response];

        if(is_callable($callback)) {
            call_user_func_array($callback, $final_params);
        } else {
            if(str_contains($callback, '@')) {
                list($className, $actionName) = explode('@', $callback);
                $class = $controller_namespace . $className;
        
                if(!class_exists($class)) {
                    throw new RouteClassNotFoundException(
                        sprintf("the class %s not defined", $class)
                    );
                }
        
                $classInstance = new $class();
        
                if( !method_exists($classInstance, $actionName) ) {
                    throw new RouteHandlerMethodException(
                        sprintf("no action method found in %s", get_class($classInstance))
                    );
                }

                call_user_func_array([$classInstance, $actionName], $final_params);
            } else {
                throw new RouteHandlerMethodException(
                    "no action method found for the route"
                );
            }
        }
    }

    /**
     * Apply middleware
     * 
     * @param string $type
     * @param string $endpoint
     * @param Request $request
     * @param Response $response
     * 
     * @return bool
     */
    private function applyMiddlewares($type, $endpoint, $request, $response)
    {
        $middleware_type = $type . '_middlewares';

        foreach($this->routes[$endpoint][$middleware_type] as $middleware) {
            
            $middleware_status = true;

            if( is_callable($middleware) ) {
                call_user_func($middleware);
            } else {
                $middlewareClass = $this->middleware_namespace . $middleware;
                $middlewareInstance = new $middlewareClass();

                if(!method_exists($middlewareInstance, 'handle')) {
                    throw new RouteHandlerMethodException(
                        sprintf("no handle method found in %s", get_class($middlewareInstance))
                    );
                }

                $middleware_status = call_user_func_array([$middlewareInstance, 'handle'], [
                    $request, $response
                ]);

                if($middleware_status !== true) {
                    return false;
                    break;
                }
            }
        }

        return true;
    }
}
