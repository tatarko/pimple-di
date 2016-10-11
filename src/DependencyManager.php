<?php

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * PHP Version 5.3
 *
 * @category DI
 * @package  DependencyManager
 * @author   Tomáš Tatarko <tomas@tatarko.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/tatarko/pimple-di Official repository
 */

namespace Tatarko\DependencyManager;

use ErrorException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use Pimple\Container;

/**
 * Dependency injector built upon Pimple container
 *
 * @category DI
 * @package  DependencyManager
 * @author   Tomáš Tatarko <tomas@tatarko.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/tatarko/pimple-di Official repository
 */
class DependencyManager extends Container
{
    /**
     * Getting instance by class name
     *
     * @param string  $className Name of the class to initiate
     * @param array   $data      List of optional arguments to past to constructor
     * @param boolean $optional  If set to true and object can't be initiated
     * then null is returned instead of throwing exception {default behaviour}
     *
     * @return object Requested instance
     */
    public function build($className, array $data = array(), $optional = false)
    {
        try {
            $class = $this->_resolveClassName($className);
        } catch(ErrorException $ex) {
            if ($optional) {
                return null;
            }
            throw $ex;
        }
        $constructor = $class->getConstructor();
        $classNameResolved = $class->getName();
        return $constructor
         ? $class->newInstanceArgs($this->_resolveArguments($constructor, $data))
         : new $classNameResolved();
    }

    /**
     * Invoke method upon given object by injectig arguments
     *
     * @param object $object     Instance of object to invoke method upon
     * @param string $methodName Name of the method to invoke
     * @param array  $data       List of optional arguments to pass to method
     *
     * @return mixed Result of the method's execution
     */
    public function invokeMethod($object, $methodName, array $data = array())
    {
        $method = new ReflectionMethod($object, $methodName);
        return $method->invokeArgs(
            $object,
            $this->_resolveArguments($method, $data)
        );
    }

    /**
     * Invoke function by injectig arguments
     *
     * @param string $functionName Name of the function to invoke
     * @param array  $data         List of optional arguments to pass to function
     *
     * @return mixed Result of the function's execution
     */
    public function invokeFunction($functionName, array $data = array())
    {
        $function = new ReflectionFunction($functionName);
        return $function->invokeArgs($this->_resolveArguments($function, $data));
    }

    /**
     * Preparing arguments for method/function by its Reflection class
     * and given optional arguments
     *
     * @param \ReflectionFunctionAbstract $function Instance of method definition
     * @param array                       $data     Optional arguments
     *
     * @return array Final list of arguments to pass
     */
    private function _resolveArguments(
        ReflectionFunctionAbstract $function,
        array $data = array()
    ) {
        $arguments = array();
        foreach ($function->getParameters() as $param) {
            if (key_exists($param->getPosition(), $data)) {
                $arguments[] = $data[$param->getPosition()];
            } elseif (key_exists($param->getName(), $data)) {
                $arguments[] = $data[$param->getName()];
            } elseif ($param->getClass()) {
                $arguments[] = $this->build(
                    $param->getClass()->getName(),
                    array(),
                    $param->isOptional()
                );
            } elseif ($param->isOptional()) {
                $arguments[] = $param->getDefaultValue();
            } else {
                throw new InvalidArgumentException(
                    'Missing argument value for "' . $param->getName() . '"'
                );
            }
        }
        return $arguments;
    }

    /**
     * Gets class' reflection instance by given class name with lookup for
     * pre-defined class aliases
     *
     * @param string $className Name of the class to get reflection of
     *
     * @return ReflectionClass
     */
    private function _resolveClassName($className)
    {
        if (key_exists($className, $this['config']['aliases'])) {
            $className = $this['config']['aliases'][$className];
        }

        if (!class_exists($className)) {
            throw new ErrorException('Class "' . $className . '" was not found');
        }

        return new ReflectionClass($className);
    }
}
