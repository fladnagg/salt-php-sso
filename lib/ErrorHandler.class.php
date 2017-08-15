<?php
/**
 * ErrorHandler class
 *
 * @author     Richaud Julien "Fladnag"
 * @package    sso\lib
 */
namespace sso;

use salt\DBException;

/**
 * Handle error
 */
class ErrorHandler {

	/**
	 * @var string prefix for encapsuled errors */
	const SUBERROR_PREFIX = '  ';

	/**
	 *
	 * @var string[] all error messages */
	public static $ERRORS = array();
	/**
	 * @var boolean TRUE for ignore PHP errors */
	private static $ignoreError = FALSE;
	/**
	 * @var boolean TRUE for ignore PHP exception */
	private static $ignoreException = FALSE;
	/**
	 * @var boolean TRUE if ErrorHandler is active, FALSE otherwise */
	private static $active = FALSE;

	/**
	 * Set the ErrorHandler
	 */
	public static function init() {
		if (!self::$active) {
			set_exception_handler(array(__NAMESPACE__.'\ErrorHandler', 'handleException'));
			set_error_handler(array(__NAMESPACE__.'\ErrorHandler', 'handleError'), E_ALL | E_STRICT);
			self::$active = TRUE;
		}
	}

	/**
	 * Disable the ErrorHandler and restore previous handler
	 */
	public static function disable() {
		if (self::$active) {
			restore_exception_handler();
			restore_error_handler();
			self::$active = FALSE;
		}
	}

	/**
	 * Manually add a global error
	 * @param string $error Error message
	 */
	public static function addError($error) {
		self::$ERRORS[]=$error;
	}

	/**
	 * Disable error handling
	 */
	public static function ignoreError() {
		self::$ignoreError = TRUE;
	}

	/**
	 * Disable exception handling
	 */
	public static function ignoreException() {
		self::$ignoreException = TRUE;
	}

	/**
	 * Enabled error and exception handling
	 */
	public static function dontIgnoreExceptionAndError() {
		self::$ignoreError = FALSE;
		self::$ignoreException = FALSE;
	}

	/**
	 * Replace the 2nd parameter of all auth* functions for avoid display user password in error messages and logs
	 * @param Exception|mixed[] $exception Exception or stack returned by debug_backtrace()
	 */
	private static function hidePasswordInStack($exception) {

		if ($exception instanceof \Exception) {
			$cl = new \ReflectionClass($exception);
			while(($cl !== NULL) && !$cl->hasProperty('trace')) {
				$cl = $cl->getParentClass();
			}
			$p = $cl->getProperty('trace');
			$p->setAccessible(true);
			$trace = $p->getValue($exception);
		} else {
			$trace = $exception;
		}

		foreach($trace as $k => $t) {
			// Hide password : always 2nd argument of auth* methods
			if (isset($t['function']) && (strpos($t['function'], 'auth') === 0)) {
				if (isset($t['args']) && count($t['args']) > 1) {
					$t['args'][1] = '**HIDDEN**';
					$trace[$k] = $t;
				}
			}
		}

		if ($exception instanceof \Exception) {
			$p->setValue($exception, $trace);
		}
	}

	/**
	 * Handle exception
	 * @param \Exception $exception
	 */
	public static function handleException($exception) {
		self::hidePasswordInStack($exception);

		error_log($exception.' ('.__FILE__.':'.__LINE__.')');

		if (ini_get('error_reporting') == 0) {
			return;
		}

		$message='';
		if ($exception instanceof DBException) {
			$message.=L::error_db_label.' - '.L::error_db_query;
		} else if ($exception instanceof BusinessException) {
			$message.=$exception->getMessage();
		} else if ($exception instanceof \PDOException) {
			$message.=L::error_db_label.' - ';
			// Some errors on http://docstore.mik.ua/orelly/java-ent/jenut/ch08_06.htm
			switch(substr($exception->getCode(), 0, 2)) {
				case '08':
				case '10':
				case '20':
					$message.=L::error_db_connection;
					break;
				case '2A': $message.=L::error_db_syntax;
				break;
				case '22': $message.=L::error_db_data;
				break;
				default: $message.=L::error_technical.' '.$exception->getCode();
			}
			// 		$message.=' - '.$exception->getMessage();
		} else {
			$message.=L::error_technical;
		}
		self::$ERRORS[]=$message;

		if (ini_get('display_errors') === '1') {
			if ($exception instanceof DBException) {
				self::$ERRORS[]=self::SUBERROR_PREFIX.$exception->getQuery();
			}
			if ($exception instanceof \PDOException) {
				self::$ERRORS[]=self::SUBERROR_PREFIX.self::fixEncoding($exception->getMessage());
			}
			self::$ERRORS[]=self::SUBERROR_PREFIX.self::fixEncoding($exception);
		}

		if (!self::$ignoreException) {
			self::stopAndDisplayErrors();
		}
	}

	/**
	 * Convert an error message in a valid charset.
	 * Some errors are in a different charset if the error came from a native system error.
	 * @param string $s the message in unknown charset
	 * @return string the message in SSO charset
	 */
	private static function fixEncoding($s) {
		if (FALSE === mb_detect_encoding($s, array(SSO_CHARSET), true)) {
			$charsets=array('Windows-1252', 'ISO-8859-15');
			foreach($charsets as $charset) {
				if (mb_check_encoding($s, $charset)) {
					return mb_convert_encoding($s, SSO_CHARSET, $charset);
				}
			}
		}
		return $s;
	}

	/**
	 * End a page with errors display
	 */
	private static function stopAndDisplayErrors() {
		global $Input, $sso, $page;

		if (ob_get_level() > 0) {
			ob_end_clean(); // we don't display the page in progress
		}

		if (!headers_sent($f, $l) && !defined('sso\SSO_TITLE')) {
			/**
			 * @ignore */
			define('sso\SSO_TITLE', L::error_title);
			include(SSO_RELATIVE.'pages/layout/header.php');
		}

		include(SSO_RELATIVE.'pages/layout/footer.php');
		die(); // we stop on errors
	}

	/**
	 * Handle PHP error
	 * @param int $errno error number
	 * @param string $errstr error message
	 * @param string $errfile file of the error
	 * @param number $errline line number of the error
	 * @param array $errcontext context of the error
	 */
	public static function handleError($errno, $errstr, $errfile, $errline, $errcontext) {

		// find the error level constant
		$a = array_filter(array_keys(get_defined_constants(), $errno, true), function($key) {
			return strpos($key, 'E_') === 0;
		});
		if (count($a) > 0) {
			$errno = reset($a);
		}

		error_log("Error $errno in $errfile:$errline : $errstr");

		if (ini_get('error_reporting') == 0) {
			return;
		}

		self::$ERRORS[]=L::error_technical.' : '.$errstr;

		if (ini_get('display_errors') === '1') {
			self::$ERRORS[]=self::SUBERROR_PREFIX.$errno.' '.$errfile.':'.$errline;

			$stack = debug_backtrace();
			array_shift($stack); // we remove handleError
			self::hidePasswordInStack($stack);

			$message = '';
			foreach($stack as $row) {
				if (isset($row['file'])) {
					$message.=$row['file'].':'.$row['line'].' ';
				}
				if (isset($row['function'])) {
					$args=array();
					if (isset($row['args'])) {
						foreach($row['args'] as $arg) {
							$args[]=self::dumpArg($arg);
						}
					}
					$message.=$row['function'].'('.implode(', ', $args).')';
				}
				$message.="\n";
			}
			self::$ERRORS[]=self::SUBERROR_PREFIX.$message;
		}

		if (!self::$ignoreError) {
			self::stopAndDisplayErrors();
		}
	}

	/**
	 * Convert arguments to string
	 * @param mixed $arg can be anything
	 * @return string a string that represent the argument
	 */
	private static function dumpArg($arg) {
		if ($arg === NULL) {
			return 'NULL';
		} else if (is_scalar($arg)) {
			if (is_bool($arg)) {
				 return $arg?'TRUE':'FALSE';
			} else if (is_int($arg)) {
				return $arg;
			} else {
				return '\''.$arg.'\'';
			}
		} else if (is_array($arg)) {
			return '['.implode(', ', array_map(array(get_called_class(), 'dumpArg'), $arg)).']';
		} else if (is_object($arg)) {
			return get_class($arg);
		}
	}
}
