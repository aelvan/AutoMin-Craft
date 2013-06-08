<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

class Minification_library {

	private $EE;


	public function __construct() {
		$this->EE = &get_instance();
		@ini_set("memory_limit","12M");
		@ini_set("memory_limit","16M");
		@ini_set("memory_limit","32M");
		@ini_set("memory_limit","64M");
		@ini_set("memory_limit","128M");
		@ini_set("memory_limit","256M");
	}

	public function minify_css_string($final_string){

		require_once('class.minify_css_compressor.php');
		$final_string = Minify_CSS_Compressor::process($final_string);
		
		return $final_string;

	}

	public function minify_js_string($final_string){

		require_once('class.jsmin.php');
		$final_string = JSMin::minify($final_string);

		return $final_string;

	}
	
	public function compile_less_string($final_string) {

		require_once('lessphp/lessc.inc.php');
		$less_parser = new lessc();

		try {
			$final_string = $less_parser->parse($final_string);		
		} catch (Exception $e){}

		return $final_string;
		
	}

}

/* End of file minification_library.php */
/* Location: /system/expressionengine/third_party/automin/libraries/minification_library.php */