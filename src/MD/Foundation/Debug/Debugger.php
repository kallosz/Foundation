<?php
/**
 * Debugger class that helps with working with PHP code, variables, types and overall debugging.
 * 
 * @package Foundation
 * @subpackage Debug
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace MD\Foundation\Debug;

use MD\Foundation\Utils\StringUtils;
use MD\Foundation\Utils\ArrayUtils;
use MD\Foundation\Debug\Timer;
use MD\Foundation\Debug\Interfaces\Dumpable;

use MD\Foundation\Exceptions\NotUniqueException;
use MD\Foundation\Exceptions\NotFoundException;
use MD\Foundation\Exceptions\NotImplementedException;

/**
 * @static
 */
class Debugger
{

    /**
     * Checks if PHP is running from CLI (command line interface).
     * 
     * @return bool
     */
    public static function isCli() {
        return in_array(php_sapi_name(), array('cli'));
    }

    /**
     * Checks if PHP is running from a web request (as opposed to CLI).
     * 
     * @return bool
     */
    public static function isWebRequest() {
        return !static::isCli();
    }

    /**
     * Returns type of the given variable.
     * 
     * Similiar to PHP's gettype() function, but instead of "object" it will return an actual class name.
     * 
     * @param mixed $var Variable to be checked.
     * @return string
     */
    public static function getType($var) {
        $type = gettype($var);

        if ($type === 'object') {
            $type = self::getClass($var);
        }

        return $type;
    }

    /**
     * Returns the name of the passed object's class.
     * 
     * @param object|string $object Will also accept a string (name of a class) but will return it untouched.
     * @param bool $stripNamespace [optional] Should only base class name be returned, void of namespace? Default: false.
     * @return string
     */
    public static function getClass($object, $stripNamespace = false) {
        if (is_string($object)) {
            $class = $object;
        } else {
            $class = get_class($object);
        }

        if ($stripNamespace) {
            $namespace = explode(NS, $class);
            $class = array_pop($namespace);
        }
        
        return $class;
    }

    /**
     * Returns namespace for the given object or class, without the class name itself.
     * 
     * @param object|string Will also accept a string (name of a class).
     * @return string
     */
    public static function getNamespace($object) {
        $class = self::getClass($object);
        $namespace = explode(NS, $class);
        array_pop($namespace);
        return implode('\\', $namespace);
    }

    /**
     * Returns a path to a file where the class of the given object was defined.
     * 
     * @param object $object
     * @return string|bool False if failed.
     */
    public static function getClassFile($object) {
        $class = self::getClass($object);
        $reflector = new \ReflectionClass($class);
        return $reflector->getFileName();
    }

    /**
     * Returns a list of ancestors of the given object in an array.
     * 
     * @param object|string $object Object or string name of a class.
     * @return array
     */
    public static function getObjectAncestors($object) {
        $parents = class_parents($object);
        $parents = ArrayUtils::resetKeys($parents);
        return $parents;
    }
    
    /**
     * Checks whether the given class or object implements the interface.
     * 
     * @param object|string $class Either an object or a string name of the class.
     * @param string $interface Name of the interface to check.
     * @return bool
     */
    public static function isImplementing($class, $interface) {
        return in_array($interface, class_implements($class));
    }

    /**
     * Checks if the given class or object extends the given parent class.
     * 
     * @param object|string $class Either an object or a string name of the class.
     * @param string $parent Name of the parent class to check.
     * @param bool $includeSelf [optional] If set to true, it will also check if $class == $parent. Default: false.
     * @return bool
     */
    public static function isExtending($class, $parent, $includeSelf = false) {
        if ($includeSelf) {
            return is_a($class, $parent, true);
        }

        return in_array($parent, self::getObjectAncestors($class));
    }

    /**
     * Converts the given PHP callable to a string that should contain its' name.
     * 
     * @param callable $callable
     * @return string
     * 
     * @todo
     */
    public static function callableToString($callable) {
       throw new NotImplementedException();
    }

    /**
     * Friendly output of variables. It will print them out in <pre class="md-dump"> tag.
     * 
     * It will dump all arguments sent to this function.
     * 
     * If PHP is running from CLI then it will dump all the arguments in one line separated by spaces.
     * 
     * @param mixed $variable1 Variable to be dumped.
     * @param mixed $variable2 Another variable to be dumped.
     * @param mixed $variable3 Another variable to be dumped.
     * @param ...
     */
    public static function dump() {
        $arguments = func_get_args();

        foreach($arguments as $variable) {
            $dump = static::stringDump($variable);
            if (static::isCli()) {
                $dump = strip_tags($dump);
                $dump = htmlspecialchars_decode($dump);
                echo $dump .' ';
            } else {
                echo $dump . NL;
            }
        }

        if (static::isCli()) {
            echo NL;
        }
    }

    /**
     * Returns a string containing HTML formatted information about the passed variable.
     * 
     * @param mixed $variable1 Variable to be dumped.
     * @param mixed $variable2 Another variable to be dumped.
     * @param mixed $variable3 Another variable to be dumped.
     * @param ...
     * @return string
     */
    public static function stringDump() {
        $arguments = func_get_args();
        $dump = '';

        foreach($arguments as $variable) {
            $dump .= '<pre style="background-color: white; color: black;" class="md-dump">';
            if (is_array($variable)) {
                $dump .= self::_arrayToString($variable);
            } elseif (is_object($variable)) {
                $dump .= self::_objectToString($variable);
            } elseif (is_bool($variable)) {
                $dump .= ($variable) ? 'true' : 'false';
            } else {
                $dump .= print_r(htmlspecialchars($variable), true);
            }
            $dump .= '</pre>';
        }
        
        return $dump;
    }

    /**
     * Dumps all the arguments into browser's JavaScript console.
     * 
     * Attempts to convert all objects into arrays.
     * 
     * @param mixed $variable1 Variable to be dumped.
     * @param mixed $variable2 Another variable to be dumped.
     * @param mixed $variable3 Another variable to be dumped.
     * @param ...
     */
    public static function consoleDump() {
        $arguments = func_get_args();

        foreach($arguments as $variable) {
            echo '<script type="text/javascript">'. static::consoleStringDump($variable) .'</script>' . NL;
        }
    }

    /**
     * Returns a string containing JavaScript code that will log all the arguments into browser's JavaScript console.
     * 
     * Attempts to convert all objects into arrays.
     * 
     * @param mixed $variable1 Variable to be dumped.
     * @param mixed $variable2 Another variable to be dumped.
     * @param mixed $variable3 Another variable to be dumped.
     * @param ...
     * @return string
     */
    public static function consoleStringDump() {
        $arguments = func_get_args();

        $output = '(function(w,u) {';
        $output .= 'if (w.console===u) return;';

        foreach($arguments as $variable) {
            $output .= 'w.console.log('. json_encode(ArrayUtils::fromObject($variable)) .');';
        }

        $output .= '})(window);';

        return $output;
    }
    
    /**
     * "Prettifies" the stack trace array to include less fields combining some of them.
     * 
     * @param array $trace Original trace array.
     * @return array "Prettified" trace.
     */
    public static function getPrettyTrace($trace) {
        $prettyTrace = array();
        
        foreach($trace as &$item) {
            $type = isset($item['type']) ? $item['type'] : '';
            $class = isset($item['class']) ? $item['class'] : '';
            $function = isset($item['function']) ? $item['function'] : '';
            $file = isset($item['file']) ? $item['file'] : '';
            $line = isset($item['line']) ? $item['line'] : '';
            $args = isset($item['args']) ? $item['args'] : array();

            $prettyTrace[] = array(
                'function' => $class . $type . $function .'()',
                'file' => $file .' ('. $line .')',
                'arguments' => $args
            );
        }
        
        return $prettyTrace;
    }

    /*
     * ERROR HANDLING
     */
    /**
     * Handles an exception by printing it out nicely.
     * 
     * @param \Exception $e Exception that you want to handle.
     * @param array $log [optional] Any log to be attached to the error page.
     */
    public static function handleException(\Exception $e, array $log = array()) {
        header('HTTP/1.1 500 Internal Server Error');

        $type = 'exception';
        $message = $e->getMessage();
        $file = $e->getFile() .' ('. $e->getLine() .')';
        $code = $e->getCode();
        $name = get_class($e);
        $trace = self::getPrettyTrace($e->getTrace());

        $exceptionPage = realpath(dirname(__FILE__) .'/../../../') .'/error.php';
        include $exceptionPage;
        die();
    }

    /**
     * Custom error handler.
     * 
     * @param int $code Error code.
     * @param string $message Error message.
     * @param string $file Path to the file in which the error occurred.
     * @param int $line Line number of the file in which the error occurred.
     * @param array $context The error's context.
     * @return bool True if managed to handle the error, false if not.
     */
    public static function handleError($code, $message, $file, $line, $context) {
        $errorReportingLevel = error_reporting();
        if ($errorReportingLevel === 0) {
            return true;
        }

        $type = 'error';
        $name = '';
        $file = $file .' ('. $line .')';
        $trace = self::getPrettyTrace(debug_backtrace());
        $log = array();

        switch($code) {
            case E_ERROR:           $name = 'Fatal Error';          break;
            case E_CORE_ERROR:      $name = 'Core Error';           break;
            case E_CORE_WARNING:    $name = 'Core Warning';         break;
            case E_COMPILE_ERROR:   $name = 'Compile Error';        break;
            case E_STRICT:          $name = 'Strict Error';         break;
            case E_PARSE:           $name = 'Parse Error';          break;
            case E_USER_ERROR:      $name = 'User Error';           break;
            case E_RECOVERABLE_ERROR: $name = 'Recoverable Error';  break;
            case E_NOTICE:          $name = 'Notice';               break;
            case E_USER_NOTICE:     $name = 'Notice (User)';        break;
            case E_WARNING:         $name = 'Warning';              break;
            case E_USER_WARNING:    $name = 'Warning (User)';       break;
            case E_DEPRECATED:      $name = 'Deprecated';           break;
            case E_USER_DEPRECATED: $name = 'Deprecated (User)';    break;
        }

        // nicely print out shell error
        if (static::isCli()) {
            echo NL . NL . NL;
            echo 'Exiting with error!'. NL;
            echo $message . NL;
            echo $file . NL;
            echo ($code ? $code .' ' : '') . $name . NL;
            die();
        }

        // normal web error
        header('HTTP/1.1 500 Internal Server Error');
        $errorPage = realpath(dirname(__FILE__) .'/../../../') .'/error.php';
        include $errorPage;
        die();
    }

    /**
     * Handles a fatal error.
     */
    public static function handleFatalError() {
        $error = error_get_last();
        if ($error !== null) {
            self::handleError($error['type'], $error['message'], $error['file'], $error['line'], array());
        }
    }
    
    /*
     * HELPERS
     */
    /**
     * Helper function for dump() that will nicely output an array or object.
     * 
     * @param array $array Array print.
     * @param int $level [optional] Indentation level.
     * @param bool $object [optional] Should it output "object formatting"
     * @return string Array formatted as a string.
     */
    private static function _arrayToString($array, $level = 0, $objectFormat = false) {
        $indentation = str_repeat(TAB, $level);
        $string = '';
        
        $keyLeft  = ($objectFormat) ? '' : '[';
        $keyRight = ($objectFormat) ? '' : ']';
        $assign   = ($objectFormat) ? ' = ' : ' => ';
        
        if (empty($array) AND (!$level)) {
            $array = array($array);
        }
        
        foreach($array as $key => $row) {
            $rowType = gettype($row);
            
            $string .= $indentation . '<b>'. $keyLeft . $key . $keyRight .'</b>'. $assign;
            
            switch ($rowType) {
                case 'array':
                    $string .= '<i>array('. count($row) .')'. NL . $indentation .'(</i>'. NL;
                    $string .= self::_arrayToString($row, $level + 1);          
                    $string .= $indentation .'<i>)</i>';
                break;
                
                case 'object':
                    $string .= self::_objectToString($row, $level);
                break;
                
                case 'string':
                    $string .= '"'. htmlspecialchars($row) .'" <i>('. strlen($row) .')</i>';
                break;
                
                case 'boolean':
                    $string .= '<i>'. (($row) ? 'true' : 'false') .'</i>';
                break;
                
                default:
                    $string .= htmlspecialchars($row);
            }
            
            $string .= NL;
        }
        
        return $string;
    }
    
    /**
     * Converts an object to a clean representation in a string, to be used for debug output.
     * 
     * @param object $object
     * @param int $level [optional] Level of indentation.
     * @return string
     */
    private static function _objectToString($object, $level = 0) {
        $indentation = str_repeat(TAB, $level);
        
        if (!is_object($object)) {
            self::_arrayToString($object, $level);
        }
        
        $variables = ($object instanceof Dumpable) ? $object->toDumpableArray() : get_object_vars($object);
        $className = self::getClass($object);
        $ancestors = self::getObjectAncestors($object);
        $ancestorsString = (!empty($ancestors)) ? implode(' &lt; ', $ancestors) : '';

        $string = '<i>Object of </i>'. $className . (!empty($ancestorsString) ? ' &lt; '. $ancestorsString .' ' : null) .'<i> with properties:'. NL . $indentation .'(</i>'. NL;
        $string .= self::_arrayToString($variables, $level + 1, true);
        $string .= $indentation .'<i>)</i>';
        
        return $string;
    }

}