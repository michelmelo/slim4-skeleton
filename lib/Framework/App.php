<?php

namespace Lib\Framework;

use App\Handlers\HttpErrorHandler;
use App\Handlers\ShutdownHandler;
use App\ServiceProviders\ProviderInterface;
use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Lib\Utils\DotNotation;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use Slim\Exception\HttpException;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Routing\RouteContext;

/**
 * Class App.
 *
 * @author  Jerfeson Guerreiro <jerfeson_guerreiro@hotmail.com>
 *
 * @since   1.0.0
 *
 * @version 1.0.0
 */
class App
{
    const DEVELOPMENT = 'development';

    const PRODUCTION = 'production';
    /**
     * @var null
     */
    private static $instance = null;
    /**
     * @var string
     */
    public $appType;
    /**
     * @var array
     */
    private $settings;
    /**
     * @var \Slim\App
     */
    private $app;

    /**
     * App constructor.
     *
     * @param string $appType
     * @param array $settings
     *
     * @throws Exception
     */
    public function __construct($appType = '', $settings = [])
    {
        $this->appType = $appType;
        $this->settings = $settings;

        // Instantiate PHP-DI ContainerBuilder
        $container = new Container();

        AppFactory::setContainer($container);
        $this->app = AppFactory::create();
    }

    /**
     *
     */
    public function prepare()
    {
        // Add Routing Middleware
        $this->app->addRoutingMiddleware();
        $this->errorHandlers();
    }

    /**
     * @return ContainerInterface|null
     */
    public function getContainer()
    {
        return $this->app->getContainer();
    }

    /**
     *
     */
    private function errorHandlers()
    {
        $displayErrorDetails = $this->getConfig('default.debug');
        $callableResolver = $this->app->getCallableResolver();
        $responseFactory = $this->app->getResponseFactory();
        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);
        $errorMiddleware = $this->app->addErrorMiddleware($displayErrorDetails, false, false);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);
    }

    /**
     * @param $settings
     *
     * @return bool
     */
    public function isProduction($settings)
    {
        if ($this->getConfig('default.env') == self::PRODUCTION) {
            return true;
        }

        return false;
    }

    /**
     * Application Singleton Factory.
     *
     * @param string $appName
     * @param array $settings
     *
     * @return App
     * @throws Exception
     *
     */
    final public static function instance($appName = '', $settings = [])
    {
        if (null === static::$instance) {
            static::$instance = new static($appName, $settings);
        }

        return static::$instance;
    }

    /**
     * @param $fn
     * @param array $args
     *
     * @return mixed
     * @throws Exception
     *
     */
    public function __call($fn, $args = [])
    {
        if (method_exists($this->app, $fn)) {
            return call_user_func_array([
                $this->app,
                $fn,
            ], $args);
        }
        throw new Exception('Method not found :: ' . $fn);
    }


    /**
     * register providers.
     *
     * @return void
     */
    public function registerProviders()
    {
        $providers = (array)$this->getConfig('providers');
        array_walk($providers, function ($appName, $provider) {
            if (strpos($appName, $this->appType) !== false) {
                /** @var $provider ProviderInterface */
                $provider::register();
            }
        });
    }

    /**
     * get configuration param.
     *
     * @param string $param
     * @param string $defaultValue
     *
     * @return mixed
     */
    public function getConfig($param, $defaultValue = null)
    {
        $dn = new DotNotation($this->settings);

        return $dn->get($param, $defaultValue);
    }

    /**
     * register providers.
     *
     * @return void
     */
    public function registerMiddleware()
    {
        $middlewares = array_reverse((array)$this->getConfig('middleware'));
        array_walk($middlewares, function ($appType, $middleware) {
            if (strpos($appType, $this->appType) !== false) {
                $this->app->add(new $middleware());
            }
        });
    }

    /**
     * resolve and call a given class / method.
     *
     * @param Request $request
     * @param Response $response
     * @param string $className
     * @param string $methodName
     * @param array $requestParams
     * @param string $namespace
     *
     * @return Response
     * @throws HttpException
     * @throws ReflectionException
     */
    public function resolveRoute(Request $request, Response $response, $className, $methodName, $requestParams = [], $namespace = "\App\Http")
    {
        $this->app->getContainer()->set(Request::class, $request);
        $this->app->getContainer()->set(Response::class, $response);

        try {
            $class = new ReflectionClass($namespace . '\\' . $className);

            if (!$class->isInstantiable() || !$class->hasMethod($methodName)) {
                throw new ReflectionException('route class is not instantiable or method does not exist');
            }
        } catch (ReflectionException $e) {
            throw new HttpException(
                $this->getContainer()->get(
                    Request::class
                ),
                $e->getMessage(),
                404
            );
        }

        $constructorArgs = $this->resolveMethodDependencies($class->getConstructor());
        $controller = $class->newInstanceArgs($constructorArgs);

        $method = $class->getMethod($methodName);
        $args = $this->resolveMethodDependencies($method, $requestParams);

        $ret = $method->invokeArgs($controller, $args);

        return $this->sendResponse($ret);
    }

    /**
     * resolve dependencies for a given class method.
     *
     * @param ReflectionMethod $method
     * @param array $urlParams
     *
     * @return array
     */
    private function resolveMethodDependencies(ReflectionMethod $method, $urlParams = [])
    {
        return array_map(function ($dependency) use ($urlParams) {
            return $this->resolveDependency($dependency, $urlParams);
        }, $method->getParameters());
    }

    /**
     * resolve a dependency parameter.
     *
     * @param ReflectionParameter $param
     * @param array $urlParams
     *
     * @return mixed
     */
    private function resolveDependency(ReflectionParameter $param, $urlParams = [])
    {
        $resolve = null;
        // try to resolve from container
        try {
            // for controller method para injection from $_GET
            if (count($urlParams) && array_key_exists($param->name, $urlParams)) {
                return $urlParams[$param->name];
            }

            // param is instantiable
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            if (!$param->getClass()) {
                throw new ReflectionException("Unable to resolve method param {$param->name}");
            }

            $resolve = $this->resolve($param->getClass()->name);
        } catch (DependencyException $e) {
        } catch (NotFoundException $e) {
        } catch (ReflectionException $e) {
        }

        return $resolve;
    }

    /**
     * resolve a dependency from the container.
     *
     * @param string $name
     * @param array $params
     *
     * @return mixed
     * @throws ReflectionException
     *
     */
    public function resolve($name, $params = [])
    {
        $c = $this->getContainer();
        if ($c->has($name)) {
            return is_callable($c->get($name)) ? call_user_func_array($c->get($name), $params) : $c->get($name);
        }

        if (!class_exists($name)) {
            throw new ReflectionException("Unable to resolve {$name}");
        }

        $reflector = new ReflectionClass($name);

        if (!$reflector->isInstantiable()) {
            throw new ReflectionException("Class {$name} is not instantiable");
        }

        if ($constructor = $reflector->getConstructor()) {
            $dependencies = $this->resolveMethodDependencies($constructor);

            return $reflector->newInstanceArgs($dependencies);
        }

        return new $name();
    }

    /**
     * return a response object.
     *
     * @param mixed $resp
     *
     * @return mixed|Response
     * @throws ReflectionException
     *
     */
    public function sendResponse($resp)
    {
        $response = $this->resolve(Response::class);

        if ($resp instanceof Response) {
            $response = $resp;
        } elseif (is_array($resp) || is_object($resp)) {
            $response = $response->withJson(json_encode($resp));
        } else {
            $response = $response->write($resp);
        }

        return $response;
    }

    /**
     * get if running application is console.
     *
     * @return bool
     */
    public function isConsole()
    {
        return php_sapi_name() == 'cli';
    }

    /**
     * @return string
     */
    public static function getAppEnv()
    {
        $env = self::DEVELOPMENT;
        if ($appEnv = getenv('APP_ENV')) {
            $env = $appEnv;
        }

        return $env;
    }
}
