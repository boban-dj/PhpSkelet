<?php

/**
 * Debugging utilities
 *
 * This file is part of the PhpSkelet Framework.
 *
 * @copyright Copyright (c) 2011 Pavel Lang (langpavel at gmail dot com)
 * @license This source file is subject to the PhpSkelet/LGPL license.
 */

// make this independent on PhpSkelet Framework
//require_once __DIR__.'/../PhpSkelet.php';
//require_once __DIR__.'/../Patterns/Singleton.php';

/**
 * Debugging visualization is gathered here
 *
 * @author langpavel
 */
class Debug extends SafeObject //extends Singleton
{
//	private static $id_generator = 1;
//
//	/**
//	 * Get html document unique id
//	 */
//	protected function genDomId()
//	{
//		return 'debug_'.(self::$id_generator++);
//	}

	private static $instance = null;
	
	/**
	 * Singleton method
	 * @return Debug get singleton instance
	 */
	public static function getInstance()
	{
		return (self::$instance !== null) ? self::$instance : (self::$instance = new Debug()); 
	}
	
	public static $max_recurse_depth = 8;
	
	private static $recurse_depth = 0;
	
	/**
	 * Dump variable into formatted XHTML string
	 * @param mixed $var
	 * @return string - formatted XHTML
	 */
	public function dump(&$var, $magic_helper = false)
	{
		if($var === null)
			$result = $this->dump_null();
		else if(is_bool($var))
			$result = $this->dump_bool($var);
		else if(is_string($var))
			$result = $this->dump_string($var);
		else if(is_int($var))
			$result = $this->dump_int($var);
		else if(is_float($var))
			$result = $this->dump_float($var);
		else if(is_array($var) || is_object($var))
		{
			if(self::$recurse_depth >= self::$max_recurse_depth)
				return 'Recursion depth limit reached';
			
			self::$recurse_depth++;
			
			if(is_array($var))
				$result = $this->dump_array($var, $magic_helper);
			else 
				$result = $this->dump_object($var);
				
			self::$recurse_depth--;
		}
		else if(is_resource($var))
			$result = 'resource';
		else 
			$result = '???';
		
		return $result;
	}
	
	/**
	 * Dump array values separated by comma
	 * @param unknown_type $var
	 */
	public function dump_array_comma(array &$var)
	{
		$parts = array();
		foreach($var as $key=>$val)
		{
			if(is_integer($key))
			{
				$parts[] = $this->dump($val);
			}
			else
			{
				$parts[] = $this->dump($key).'=>'.$this->dump($val);
			}
		}
		return join(', ', $parts);
	}
	
	protected function writeSpan($content, $color='000', $styles='')
	{
		return '<span style="font-family:Monospace;color:#'.$color.';'.$styles.'">'
			.str_replace(array("\n", ' '), array('<br/>', '&#160;'), htmlspecialchars($content))
			.'</span>';
	}

	protected function dump_null()
	{
		return $this->writeSpan('null', 'c60', 'font-weight:bold;');
	}

	protected function dump_bool($var)
	{
		return $this->writeSpan($var ? 'true' : 'false', $var ? '0a0' : 'f00', 'font-weight:bold;');
	}

	protected function dump_string($var)
	{
		/*
		if(strlen($var) > 80)
		{
			// TODO
		}
		else
		*/
			return $this->writeSpan(var_export($var, true), '060');
	}

	protected function dump_int($var)
	{
		return $this->writeSpan(var_export($var, true), '008', 'font-weight:bold;');
	}

	protected function dump_float($var)
	{
		return $this->writeSpan(var_export($var, true), '804', 'font-weight:bold;');
	}

	private $recurse_detection = array();

	public function dump_array_table(&$var, $magic_helper=false)
	{
		if(false !== array_search($var, $this->recurse_detection, true))
			return 'Recursive dependency detected';

		array_push($this->recurse_detection, $var);

		$result = '<table style="border:1px black solid; margin:0 0 3pt 10pt; padding:0 0 0 3pt; valign:top;">'."\r\n";

		foreach($var as $k=>$v)
		{
			if($magic_helper && substr($k, 0, 3) === '___')
				$result .= '<tr style="border: 1px black dashed"><td style="vertical-align:top; font-weight:bolder; font-family:Monospace;">'.substr($k, 3).'</td><td>'.$this->dump($v, true)."</td></tr>\r\n";
			else
				$result .= '<tr style="border: 1px black dashed"><td style="vertical-align:top">'.$this->dump($k).'</td><td>'.$this->dump($v, true)."</td></tr>\r\n";
		}

		if($var !== array_pop($this->recurse_detection))
			throw new RuntimeException('Inconsistent state');

		$result .= "</table>\r\n";
		return $result;
	}
	
	public function dump_object_table(&$var)
	{
		$array = (array)$var;
		$cls = get_class($var);
		$result = array();
		$result['___protected'] = array();
		$privatestr = '___private&#160;'.$cls.' ';
		$result[$privatestr] = array();
		foreach($array as $k=>&$v)
		{
			$expl = explode("\0", $k);
			if(count($expl) == 1)
				$result['___'.$k]=&$v;
			else if ($expl[1] == '*')
				$result['___protected']['___'.$expl[2]]=&$v;
			else if ($cls === $expl[1])
				$result[$privatestr]['___'.$expl[2]]=&$v;
			else
				$result['___private&#160;'.$expl[1]]['___'.$expl[2]]=&$v;
		}
		ksort($array);
		return $this->dump_array_table($result, true);
	}
	
	public function dump_array(&$var, $magic_helper = false)
	{
		$len = count($var);

		if($len === 0 && $var === array())
			return '<span style="font-family:Monospace;color:#004;font-weight:bold;">array<small>(empty)</small></span>';
			
		$js = 'javascript:this.nextSibling.style.display = (this.nextSibling.style.display == "none")?"block":"none"';
		$result = '<span onclick="'.htmlentities($js).'" style="font-family:Monospace;cursor:pointer;"><span style="color:#004;font-weight:bold;">array</span><small>('.$len.')</small></span>';
		$result .= '<div style="display:none;">'.$this->dump_array_table($var, $magic_helper).'</div>';
		return $result;
	}

	public function dump_object(&$var)
	{
		$classname = get_class($var);
		$js = 'javascript:this.nextSibling.style.display = (this.nextSibling.style.display == "none")?"block":"none"';
		$result = '<span onclick="'.htmlentities($js).'" style="font-family:Monospace;cursor:pointer;"><span style="color:#004;font-weight:bold;"><small>class </small>'.$classname.'</span></span>';
		$result .= '<div style="display:none;">'.$this->dump_object_table($var).'</div>';
		return $result;
	}

	/**
	 * Register handlers via set_error_handler and set_exception_handler
	 */
	public function registerErrorHandlers()
	{
		set_error_handler(array($this,'errorHandler'), 0x7fffffff);
		set_exception_handler(array($this,'exceptionHandler'));
	}
	
	/**
	 * Default error handler
	 * @param int $errno The first parameter, errno, contains the level of the error raised, as an integer.
	 * @param string $errstr The second parameter, errstr, contains the error message, as a string.
	 * @param string[optional] $errfile The third parameter is optional, errfile, which contains the filename that the error was raised in, as a string.
	 * @param int[optional] $errline The fourth parameter is optional, errline, which contains the line number the error was raised at, as an integer.
	 * @param array[optional] $errcontext The fifth parameter is optional, errcontext, which is an array that points to the active symbol table at the point the error occurred. In other words, errcontext will contain an array of every variable that existed in the scope the error was triggered in. User error handler must not modify error context.
	 */
	public function errorHandler($errno, $errstr, $errfile = null, $errline = null, array $errcontext = null)
	{
		echo "<div class=\"error$errno\" style=\"font-family:Monospace;\">ERROR $errno: \"$errstr\" in $errfile at line $errline<br /><code><pre>";
		//echo htmlspecialchars(print_r($errcontext, true));
		echo '</pre></code></div>';
		// fallback must be set to false when you want to run default handler
		$fallback = !((ini_get('error_reporting') & $errno) | ($errno & (E_ERROR | E_RECOVERABLE_ERROR | E_USER_ERROR)));
		return $fallback;
	}

	/**
	 * Default exception handler
	 * @param Exception $exception
	 */
	public function exceptionHandler(Exception $exception)
	{
		echo '<div class="error" style="font-family:Monospace;">'.$this->dump($exception).': '.$exception->getMessage().'</div>';
	}
	
}
