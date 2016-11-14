<?php namespace sso;

use salt\DBException;

class ErrorHandler {

	const SUBERROR_PREFIX = '  ';

	public static $ERRORS = array();

	private static $ignoreError = FALSE;
	private static $ignoreException = FALSE;

	public static function init() {
		set_exception_handler(array(__NAMESPACE__.'\ErrorHandler', 'handleException'));
		set_error_handler(array(__NAMESPACE__.'\ErrorHandler', 'handleError'), E_ALL | E_STRICT);
	}
	
	public static function disable() {
		restore_exception_handler();
		restore_error_handler();
	}

	public static function addError($error) {
		self::$ERRORS[]=$error;
	}

	public static function ignoreError() {
		self::$ignoreError = TRUE;
	}

	public static function ignoreException() {
		self::$ignoreException = TRUE;
	}

	public static function dontIgnoreExceptionAndError() {
		self::$ignoreError = FALSE;
		self::$ignoreException = FALSE;
	}

	/**
	 * Handle exception
	 * @param \Exception $exception
	 */
	public static function handleException($exception) {
		error_log($exception);

		if (ini_get('error_reporting') == 0) {
			return;
		}

		$message='';
		if ($exception instanceof DBException) {
			$message.='Base de données - Exécution d\'une requête';
		} else if ($exception instanceof BusinessException) {
			$message.=$exception->getMessage();
		} else if ($exception instanceof \PDOException) {
			$message.='Base de données - ';
			// Some errors on http://docstore.mik.ua/orelly/java-ent/jenut/ch08_06.htm
			switch(substr($exception->getCode(), 0, 2)) {
				case '08':
				case '10':
				case '20':
					$message.='Connexion';
					break;
				case '2A': $message.='Syntaxe';
				break;
				case '22': $message.='Données';
				break;
				default: $message.='Erreur technique '.$exception->getCode();
			}
			// 		$message.=' - '.$exception->getMessage();
		} else {
			$message.='Erreur technique';
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
	 * Permet de convertir la chaine passée en paramètre dans le bon Charset.
	 * Il est possible de récupérer un charset différent lorsque l'extension qui génère la chaine propage un message natif du système.
	 * @param string $s
	 * @return string
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
		
		if (!headers_sent($f, $l) && !defined(__NAMESPACE__.'\SSO_TITLE')) {
			define(__NAMESPACE__.'\SSO_TITLE', 'ERROR');
			include(SSO_RELATIVE.'pages/layout/header.php');
		}

		include(SSO_RELATIVE.'pages/layout/footer.php');
		die(); // we stop on errors
	}



	/**
	 * Handle PHP error
	 * @param unknown $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param number $errline
	 * @param array $errcontext
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

		self::$ERRORS[]='Erreur technique : '.$errstr;

		if (ini_get('display_errors') === '1') {
			self::$ERRORS[]=self::SUBERROR_PREFIX.$errno.' '.$errfile.':'.$errline;
			
			$stack = debug_backtrace();
			array_shift($stack); // we remove handleError
			$message = '';
			foreach($stack as $row) {
				if (isset($row['file'])) {
					$message.=$row['file'].':'.$row['line'].' ';
				}
				if (isset($row['function'])) {
					$args=array();
					foreach($row['args'] as $arg) {
						$args[]=self::dumpArg($arg);
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

	private static function dumpArg($arg) {
		if ($arg === NULL) {
			return 'NULL';
		} else if (is_scalar($arg)) {
			if (is_bool($arg)) {
				 return $arg?'TRUE':FALSE;
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
