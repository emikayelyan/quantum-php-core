<?php

/**
 * Quantum PHP Framework
 *
 * An open source software development framework for PHP
 *
 * @package Quantum
 * @author Arman Ag. <arman.ag@softberg.org>
 * @copyright Copyright (c) 2018 Softberg LLC (https://softberg.org)
 * @link http://quantum.softberg.org/
 * @since 1.0.0
 */

namespace Quantum\Mvc;

use Quantum\Exceptions\ExceptionMessages;
use Quantum\Middleware\MiddlewareManager;
use Quantum\Exceptions\RouteException;
use Quantum\Factory\ServiceFactory;
use Quantum\Factory\ModelFactory;
use Quantum\Factory\ViewFactory;
use Quantum\Libraries\Csrf\Csrf;
use Quantum\Http\Response;
use Quantum\Http\Request;

/**
 * MvcManager Class
 *
 * MvcManager class determine the controller, action of current module based on
 * current route
 *
 * @package Quantum
 * @subpackage MVC
 * @category MVC
 */
class MvcManager
{

    /**
     * @var object
     */
    private $controller;

    /**
     * @var string
     */
    private $action;

    /**
     * Run MVC
     * @param Request $request
     * @param Response $response
     * @throws RouteException
     * @throws \ReflectionException
     */
    public function runMvc(Request $request, Response $response)
    {

        if ($request->getMethod() != 'OPTIONS') {

            if (current_middlewares()) {
                list($request, $response) = (new MiddlewareManager())->applyMiddlewares($request, $response);
            }

            $this->controller = $this->getController();

            $this->action = $this->getAction();

            if ($this->controller->csrfVerification ?? true) {
                Csrf::checkToken($request, \session());
            }

            $routeArgs = current_route_args();

            if (method_exists($this->controller, '__before')) {
                call_user_func_array(array($this->controller, '__before'), $this->getArgs($routeArgs, '__before', $request, $response));
            }

            call_user_func_array(array($this->controller, $this->action), $this->getArgs($routeArgs, $this->action, $request, $response));

            if (method_exists($this->controller, '__after')) {
                call_user_func_array(array($this->controller, '__after'), $this->getArgs($routeArgs, '__after', $request, $response));
            }
        }
    }

    /**
     * Get Controller
     *
     * @return object
     * @throws RouteException
     */
    private function getController()
    {
        $controllerPath = modules_dir() . DS . current_module() . DS . 'Controllers' . DS . current_controller() . '.php';

        if (!file_exists($controllerPath)) {
            throw new RouteException(_message(ExceptionMessages::CONTROLLER_NOT_FOUND, current_controller()));
        }

        require_once $controllerPath;

        $controllerClass = '\\Modules\\' . current_module() . '\\Controllers\\' . current_controller();

        if (!class_exists($controllerClass, false)) {
            throw new RouteException(_message(ExceptionMessages::CONTROLLER_NOT_DEFINED, current_controller()));
        }

        return new $controllerClass();
    }

    /**
     * Get Action
     *
     * @return string
     * @throws RouteException
     */
    private function getAction()
    {
        $action = current_action();

        if (!method_exists($this->controller, $action)) {
            throw new RouteException(_message(ExceptionMessages::ACTION_NOT_DEFINED, $action));
        }

        return $action;
    }

    /**
     * Get Args
     *
     * @param  array $routeArgs
     * @param Request $request
     * @return array
     * @throws \ReflectionException
     */
    private function getArgs($routeArgs, $action, Request $request, Response $response)
    {
        $args = [];

        $reflaction = new \ReflectionMethod($this->controller, $action);
        $params = $reflaction->getParameters();

        if (count($params)) {
            foreach ($params as $param) {
                $paramType = $param->getType();

                if ($paramType) {
                    switch ($paramType) {
                        case 'Quantum\Http\Request':
                            array_push($args, $request);
                            break;
                        case 'Quantum\Http\Response':
                            array_push($args, $response);
                            break;
                        case 'Quantum\Factory\ServiceFactory':
                            array_push($args, new ServiceFactory());
                            break;
                        case 'Quantum\Factory\ModelFactory':
                            array_push($args, new ModelFactory());
                            break;
                        case 'Quantum\Factory\ViewFactory':
                            array_push($args, new ViewFactory());
                            break;
                        default :
                            array_push($args, current($routeArgs));
                            next($routeArgs);
                    }
                } else {
                    array_push($args, current($routeArgs));
                    next($routeArgs);
                }
            }
        }

        return $args;
    }

}
