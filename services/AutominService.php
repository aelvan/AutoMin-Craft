<?php

/**
 * Automin service
 *
 * @author André Elvan
 */
namespace Craft;

use Leafo\ScssPhp\Compiler;

class AutominService extends BaseApplicationComponent
{
	const MARKUP_TYPE_JS = 'js';
	const MARKUP_TYPE_CSS = 'css';
	const MARKUP_TYPE_LESS = 'less';
	const MARKUP_TYPE_SCSS = 'scss';
  
  public $settings = array();
  
  /**
   * When compiling SCSS files, this is populated with the content of the source map file
   * This is later saved to disk during cache phase
   * @var string
   */
  private $sourceMapOutput = null;

  /**
   * Receives the parameters coming from the Craft Twig filter, initiates the compilation process,
   * then returns the final HTML referencing the compiled scripts
   * 
   * @param string $content block of HTML as captured by the Twig filter
   * @param string $type - one of 'js', 'css', 'less' or 'scss'
   * @param string $attr - additional attributes to be appended to final <link> or <script> tag
   * @return string of HTML containing compiled assets
   */
  public function process($content, $type, $attr) {
    $this->settings = $this->_init_settings();
    
    if (!$type) {
      return $this->content;
    }
    
    return $this->_process_markup($content, $type, $attr);    
  }

  
	/**
	 * Gets AutoMin settings, either from saved settings or from config
	 * 
	 * @return array Array containing all settings
	 * @author André Elvan
	*/
  private function _init_settings() {
    $plugin = craft()->plugins->getPlugin('automin');
    $plugin_settings = $plugin->getSettings();    
    
    $settings = array();
    $settings['autominEnabled'] = craft()->config->get('autominEnabled')!==null ? craft()->config->get('autominEnabled') : $plugin_settings['autominEnabled'];
    $settings['autominCachingEnabled'] = craft()->config->get('autominCachingEnabled')!==null ? craft()->config->get('autominCachingEnabled') : $plugin_settings['autominCachingEnabled'];
    $settings['autominMinifyEnabled'] = craft()->config->get('autominMinifyEnabled')!==null ? craft()->config->get('autominMinifyEnabled') : $plugin_settings['autominMinifyEnabled'];
    $settings['autominPublicRoot'] = craft()->config->get('autominPublicRoot')!==null ? craft()->config->get('autominPublicRoot') : $plugin_settings['autominPublicRoot'];
    $settings['autominCachePath'] = craft()->config->get('autominCachePath')!==null ? craft()->config->get('autominCachePath') : $plugin_settings['autominCachePath'];
    $settings['autominCacheURL'] = craft()->config->get('autominCacheURL')!==null ? craft()->config->get('autominCacheURL') : $plugin_settings['autominCacheURL'];
    $settings['autominSCSSIncludePaths'] = craft()->config->get('autominSCSSIncludePaths')!==null ? craft()->config->get('autominSCSSIncludePaths') : $plugin_settings['autominSCSSIncludePaths'];

    if ($settings['autominPublicRoot']=='') {
      $settings['autominPublicRoot'] = dirname($_SERVER['SCRIPT_FILENAME']);
    }
    
    if ($settings['autominSCSSIncludePaths']=='') {
      $settings['autominSCSSIncludePaths'] = dirname($_SERVER['SCRIPT_FILENAME']);
    }
    
    $settings['autominSCSSIncludePaths'] = explode(',', $settings['autominSCSSIncludePaths']);
    
    return $settings;
  }
  
	/**
	 * Gets a plugin setting
   * 
	 * @param $name Setting name
	 * @return mixed Setting value
	 * @author Jesse Bunch
	*/
  public function getSetting($name) {
    return $this->settings[$name];
  }
  

	/**
	 * Main processing routine, to be used for all types
	 * 
	 * @param $markup
	 * @param $markup_type One of the MARKUP_TYPE_X values
	 * @param $markup_attrs tag attributes string
	 * @return string The new markup
	 * @author Jesse Bunch
	*/
	private function _process_markup($markup, $markup_type, $markup_attrs) {
		
		// AutoMin disabled? Go no further...
		if (!$this->getSetting('autominEnabled')) {
			return $markup;
		}
    
		// Gather information
		$filename_array = $this->_extract_filenames($markup, $markup_type);
		$filename_array = $this->_prep_filenames($filename_array);
		$last_modified = $this->_find_last_modified_timestamp($filename_array);
    
		// File Extension
		// LESS files should have a .css extension
		$extension = (($markup_type == self::MARKUP_TYPE_LESS) || ($markup_type == self::MARKUP_TYPE_SCSS)) ? self::MARKUP_TYPE_CSS : $markup_type;
		$cache_key = Craft()->automin_cache->get_cache_key($markup, $extension);

    // Fetch and validate cache, if caching is enabled 
		if ($this->getSetting('autominCachingEnabled')) {
      $cache_filename = Craft()->automin_cache->fetch_cache(
        $cache_key, 
        $markup, 
        $last_modified
      );
      
      // Output cache file, if valid
      if (FALSE !== $cache_filename) {
        $this->_write_log("Cache found and valid");
        return $this->_format_output($cache_filename, $last_modified, $markup_type, $markup_attrs);
      }
    }
    
		// Combine/concatenate all the <script> or <link> tags within the block
		// Also parse @imports for CSS/LESS files (but not for SCSS which we'll tackle differently)
		if ($markup_type == self::MARKUP_TYPE_SCSS) {
			// For SCSS, we get better results (and accurate source maps)
			// if we generate a fake root file that then @include's all the others
			$combined_file_data = '';
			foreach ($filename_array as $file) {
				$combined_file_data .= '@import "' . ltrim($file['url_path'], '/') . "\";\n";
			}
			
		} else {
			$combined_file_data = $this->_combine_files(
				$filename_array,
				($markup_type == self::MARKUP_TYPE_CSS
					OR $markup_type == self::MARKUP_TYPE_LESS)
			);
		}
		
		// If we couldn't read some files, return original tags
		if (FALSE === $combined_file_data) {
			$this->_write_log("ERROR: One or more of your files couldn't be read.");
			return $markup;
		}
		
		// Attempt compilation and compression
		$data_length_before = strlen($combined_file_data) / 1024;
		$combined_file_data = $this->_compile_and_compress(
			$combined_file_data, 
			$markup_type
		);
		$data_length_after = strlen($combined_file_data) / 1024;

		// Log the savings
		// Note: this will not be accurate for SCSS files because of the @include's
		$data_savings_kb = $data_length_before - $data_length_after;
		$data_savings_percent = ($data_savings_kb / $data_length_before) * 100;
		$data_savings_message = sprintf(
			'(%s Compression) Before: %1.0fkb / After: %1.0fkb / Data reduced by %1.2fkb or %1.2f%%',
			strtoupper($markup_type),
			$data_length_before,
			$data_length_after,
			$data_savings_kb,
			$data_savings_percent
		);
		$this->_write_log($data_savings_message);

		// If compilation fails, return original tags
		if (FALSE === $combined_file_data) {
			$this->_write_log("ERROR: Compilation failed. Perhaps you have a syntax error?");
			return $markup;
		}

		// If a source map was generated during _compile_and_compress() then save source map output
		// to disk and add a link to it at the end of the compiled CSS
		if ($this->sourceMapOutput) {
			if (Craft()->automin_cache->write_cache($cache_key . '.map', $this->sourceMapOutput)) {
				$combined_file_data .= '/*# sourceMappingURL=' . $cache_key . '.map' . ' */';
			}
			// Clear source map output for future filter blocks in this request
			$this->sourceMapOutput = null;
		}
		
		// Cache output
		$cache_result = Craft()->automin_cache->write_cache($cache_key, $combined_file_data);
		
		// If caching failed, return original tags
		if (FALSE === $cache_result) {
			$this->_write_log("ERROR: Caching is disabled or we were unable to write to your cache directory.");
			return $markup;
		}
		
		// Return the markup output
		return $this->_format_output($cache_result, $last_modified, $markup_type, $markup_attrs);
	}

	/**
	 * Compress and compile (if necessary) the code.
	 * 
	 * @param string $code raw uncompiled source
	 * @param string $markup_type One of the MARKUP_TYPE_X values
	 * @param string $filePath used for generating source maps and processing @includes
	 * @return mixed string of compiled code if success, or FALSE if failure
	 * @author Jesse Bunch
	*/
	private function _compile_and_compress($code, $markup_type, $filePath = null) {

		@ini_set('memory_limit', '50M');
		@ini_set('memory_limit', '128M');
		@ini_set('memory_limit', '256M');
		@ini_set('memory_limit', '512M');
		@ini_set('memory_limit', '1024M');

		try {
			
			switch($markup_type) {

				case self::MARKUP_TYPE_LESS:

					// Compile with LESS
					require_once(CRAFT_PLUGINS_PATH.'automin/vendor/lessphp/lessc.inc.php');
					$less_obj = new \lessc();
					$code = $less_obj->parse($code);

          if ($this->settings['autominMinifyEnabled']) {
            // Compress CSS
            require_once(CRAFT_PLUGINS_PATH.'automin/vendor/class.minify_css_compressor.php');
            $code = Automin_Minify_CSS_Compressor::process($code);	
          }
					break;
        
        case self::MARKUP_TYPE_SCSS:
          
					// Compile with SCSS
					require_once(CRAFT_PLUGINS_PATH.'automin/vendor/scssphp/scss.inc.php');
					$scss_parser = new Compiler();
					
					// TODO: Also add the path of the current script, so that relative paths may be used
          $scss_parser->setImportPaths($this->settings['autominSCSSIncludePaths']);
          
          // Output line numbers in the compiled code. This is required for generating a source map.
          $scss_parser->setLineNumberStyle(Compiler::LINE_COMMENTS);
          
          // echo "Initial code: $code<br>";
          
          // Compile the SCSS
					$code = $scss_parser->compile($code);
					
          // echo "Compiled code: $code<br>";
           
        	// Minify source, if this is enabled
          if ($this->settings['autominMinifyEnabled']) {
            // Compress the CSS *but* leave the line number comments in place,
            // because we need these to generate the source maps in the next step
            $code = Automin_Minify_CSS_Compressor::process($code, ['keepLineNumbers' => true]);
            
            // Add in a line return prior to each line number comment	
            // (fixes a bug in the generation of source maps)
            $code = preg_replace('`}/\\* line`', "}\r\n/* line", $code);
            
            // But remove line returns *after* each line number comment, to improve compression
            $code = preg_replace('`\\*/\\s+`', "*/", $code);
          }
          
					// Generate a source map file
					// (Saves it inside $this->sourceMapOutput for later processing)
					$this->generateMap($code);
          
          // Now that we have our source map, we can remove those line number comments
          // (but NOT the surrounding line returns, otherwise the sourcemap line numbers will no longer be accurate)
          if ($this->settings['autominMinifyEnabled']) {
          	$code = preg_replace('`/\\*(?!!).+?\\*/`s', '', $code);
          }
          // echo $code;
          
					break;

				case self::MARKUP_TYPE_CSS:

          if ($this->settings['autominMinifyEnabled']) {
  					// Compress CSS
	  				require_once(CRAFT_PLUGINS_PATH.'automin/vendor/class.minify_css_compressor.php');
		  			$code = \Minify_CSS_Compressor::process($code);	
          }
					break;

				case self::MARKUP_TYPE_JS:
					
          if ($this->settings['autominMinifyEnabled']) {
  					// Compile JS
	  				require_once(CRAFT_PLUGINS_PATH.'automin/vendor/class.jsmin.php');
		  			$code = \JSMin::minify($code);
          }

					// require_once('libraries/class.minify_js_closure.php');
					// $code = Minify_JS_ClosureCompiler::minify($code);
					
					break;

			}

		} catch (Exception $e) {
			exit($e->getMessage());
			$this->_write_log('Compilation Exception: ' . $e->getMessage());
			return FALSE;

		}

		return $code;

	}
	
	/**
	 * Generate source map from a string of compiled CSS
	 *
	 * @param string $compiledCss - string of compiled CSS code containing line number comments
	 * @param string $sourceFile - path to original (root) SCSS file (relative to DOCUMENT_ROOT)
	 * @param string $compiledFile - path to compiled CSS file (relative to DOCUMENT_ROOT) (we use the same name for the .map file)
	 * @return bool|string - Path to map or false if not able to generate.
	 */
	public function generateMap($compiledCss) {
		
		// echo $compiledCss;
	  
	  // Initialise Koala source map library
	  include_once(CRAFT_PLUGINS_PATH . 'automin/vendor/sourcemaps/Kwf/SourceMaps/SourceMap.php');
	  include_once(CRAFT_PLUGINS_PATH . 'automin/vendor/sourcemaps/Kwf/SourceMaps/Base64VLQ.php');
	  $sourceMapGenerator = \Kwf_SourceMaps_SourceMap::createEmptyMap($compiledCss);
	  
	  // Loop through code 1 line at a time
	  foreach (explode("\n", $compiledCss) as $lNumber => $line) {
	  	
	  	// Search for the string /* line X */ or /* line X, /path/to/file.css */
	    preg_match_all('#[[:space:]]*/\* line ([0-9]+)(, )?([^*]+) \*/#', $line, $matches);
	    // $lNumber++;
	    
	    // Did the regex match on this line?
	    if (count($matches) < 2) {
	      continue;
	    }
	    
	    // I don't think we normally get multiple comments on the same line? But we do a loop here in case
	    for ($i = 0; $i < count($matches[1]); $i++) {
	      if (!isset($matches[2][$i])) {
	        break;
	      }
	      $originalLine = $matches[1][$i];
	      $originalFile = $matches[3][$i];
	      
	      // Lines from the root SCSS file do not display a path, so we'll populate that with the $sourceFile
	      // if (empty($originalFile))
	      // 	$originalFile = $sourceFile;
	      
	      // $generatedColumnIndex = $currentIndex - $sizeOfPrecedingCommentBlocks;
	      
	      $craftPublicFolder = dirname($_SERVER['SCRIPT_FILENAME']);
	      $originalFile = str_replace($craftPublicFolder, '', $originalFile);
	      $sourceMapGenerator->addMapping(
	      	$lNumber + 1, 			// Generated line
	      	0, 							// Generated column
	      	$originalLine,  // Original line
	      	0, 							// Original column
	      	$originalFile   // Original file
	      );
	    }
	  }
	  // echo dirname($_SERVER['SCRIPT_FILENAME']) . '<br>';
	  // TODO: Make dynamic
	  $this->sourceMapOutput = $sourceMapGenerator->getMapContents();
	  // echo $output;
	  return; // (false === file_put_contents($outputFile, $output)) ? false : $outputFile;
	}

	/**
	 * Formats the output into valid markup
	 * 
	 * @param string $cache_filename The url path to the cache file.
	 * @param integer $last_modified Timestamp of the latest-modified file
	 * @param string $markup_type One of the MARKUP_TYPE_X values
	 * @param $markup_attrs tag attributes string
	 * @return string
	 * @author Jesse Bunch
	 */
	private function _format_output($cache_filename, $last_modified, $markup_type, $markup_attrs) {

		$markup_output = '';
		
		// Append modified time to the filename
		$cache_filename = "$cache_filename?modified=$last_modified";

		// Format attributes
    $attributes_string = $markup_attrs;


		// Create tag
		switch($markup_type) {

			case self::MARKUP_TYPE_CSS:
			case self::MARKUP_TYPE_LESS:
			case self::MARKUP_TYPE_SCSS:
				$markup_output = sprintf(
					'<link href="%s" %s>', 
					$cache_filename, 
					$attributes_string
				);
				break;

			case self::MARKUP_TYPE_JS:
				$markup_output = sprintf(
					'<script src="%s" %s></script>', 
					$cache_filename, 
					$attributes_string
				);
				break;

		}

		return $markup_output;

	}

	/**
	 * Returns a string of all the files combined. If a file cannot be read,
	 * this function will return FALSE.
	 * 
	 * @param array $files_array Pass in the output of _prep_filenames
	 * @return mixed string or FALSE
	 * @author Jesse Bunch
	*/
	private function _combine_files($files_array, $should_parse_imports = FALSE) {
		
		$combined_output = '';
		foreach ($files_array as $file_array) {
			
			if (!file_exists($file_array['server_path'])
				OR !is_readable($file_array['server_path'])) {
				return FALSE;
			}

			// Get file contents, and place a comment at the top of each included file
			// This may help with debugging (when minification is turned off)
			// and is also required for SCSS source maps to work correctly
			// (otherwise we have no way of locating which file the original statement came from)
			$combined_output .= 
				"/* Source file: \"$file_array[server_path]\" */\n" 
				. file_get_contents($file_array['server_path']);

			// Parse @imports
			if ($should_parse_imports) {
				$combined_output = $this->_parse_css_imports(
					$combined_output, 
					$file_array['url_path']
				);
			}

		}

		return $combined_output;

	}

	/**
	 * Returns the timestamp of the latest modified file
	 * @param array $files_array Pass in the output of _prep_filenames
	 * @return int
	 * @author Jesse Bunch
	*/
	private function _find_last_modified_timestamp($files_array) {
		
		$last_modified_timestamp = 0;
		foreach ($files_array as $file_array) {
			if ($file_array['last_modified']
				AND $file_array['last_modified'] > $last_modified_timestamp) {
				$last_modified_timestamp = $file_array['last_modified'];
			}
		}

		return $last_modified_timestamp;

	}

	/**
	 * Gathers information about each file and normalizes
	 * the filename and path.
	 * @param array $filenames_array
	 * @return array
	 * 	- url_path
	 * 	- server_path
	 * 	- last_modified
	 * @author Jesse Bunch
	*/
	private function _prep_filenames($filenames_array) {
		
		$information_array = array();

		foreach($filenames_array as $index => $filename) {

			// Path for URLs
			$information_array[$index]['url_path'] = $this->_normalize_file_path(
				$filename
			);

			// Path for reading
			$information_array[$index]['server_path'] = $this->_normalize_file_path(
				$filename, 
				'', 
				TRUE
			);

			// Last modified
			$information_array[$index]['last_modified'] = @filemtime(
				$information_array[$index]['server_path']
			);
			
		} 

		return $information_array;

	}

	/**
	 * Extracts the filenames from the markup based on the provided
	 * markup type.
	 * @param string $markup
	 * @param string $markup_type Use one of the constants MARKUP_TYPE_X
	 * @return array (of filenames)
	 * @author Jesse Bunch
	*/
	private function _extract_filenames($markup, $markup_type) {

		$matches_array;
		switch($markup_type) {
			case self::MARKUP_TYPE_CSS:
			case self::MARKUP_TYPE_LESS:
				preg_match_all(
					"/href\=\"([A-Za-z0-9\.\/\_\-\?\=\:]+.[css|less])\"/",
					$markup,
					$matches_array
				);
				break;
			case self::MARKUP_TYPE_SCSS:
				preg_match_all(
					"/href\=\"([A-Za-z0-9\.\/\_\-\?\=\:]+.[css|scss])\"/",
					$markup,
					$matches_array
				);
				break;
			case self::MARKUP_TYPE_JS:
				preg_match_all(
					"/src\=\"([A-Za-z0-9\.\/\_\-\?\=\:]+.js)\"/",
					$markup,
					$matches_array
				);
				break;
		}

		// Matches?
		if (count($matches_array) >= 2) {
			return $matches_array[1];
		}

		return FALSE;

	}

	/**
	 * File paths may be in different formats. This function will take
	 * any file path and normalize it to on of two formats depending on the
	 * parameters you pass.
	 * @param string $file_path The path to normalize.
	 * @param string $relative_path If $file_path is a relative path, we need
	 * the path to the relative file. If no path is supplied, the dirname of 
	 * the current URI is used.
	 * @param bool $include_root If TRUE, the full server path is returned. If
	 * FALSE, the path returned is relative to the document root.
	 * @return string
	 * @author Jesse Bunch
	*/
	private function _normalize_file_path($file_path, $relative_path='', $include_root = FALSE) {

		// If the path is a full URL, return it
		// We don't currently fetch remote files
		if (0 === stripos($file_path, 'http')
			OR 0 === stripos($file_path, '//')) {
			return $file_path;
		}

		// Get the relative path
		if (!$relative_path) {
			$relative_path = $_SERVER['REQUEST_URI'];
		}

		// Relative path should leave out the document root
		$relative_path = str_replace($this->settings['autominPublicRoot'], '', $relative_path);
    
		// Parse the path
		$path_parts = pathinfo($relative_path);
		$dirname = $path_parts['dirname'].'/';

		// If not document-root relative, we must add the URI
		// of the calling page to make it document-root relative
		if (substr($file_path, 0, 1) != '/') {
			$file_path = $dirname.$file_path;
		}
	
		// Include full root path?
		if ($include_root) {
			$file_path = $this->settings['autominPublicRoot'] . $file_path;
		}

		return $this->remove_double_slashes($file_path);

	}
	
	/**
	 * Reads a file from the filesystem
	 * @param string $file_path Full server path to the file to read
	 * @return string
	 * @author Jesse Bunch
	*/
	private function _read_file($file_path) {
		
		return @file_get_contents($file_path);

	}

	/**
	 * Looks for and parses @imports in the provided string.
	 * @param string $string
	 * @param string $relative_path Passed to _normalize_file_path(). See
	 * that function's documentation for details on this param.
	 * @return string
	 * @author Jesse Bunch
	*/
	private function _parse_css_imports(&$string, $relative_path = '') {
		
		// Get all @imports
		$matches = array();
		preg_match_all('/\@import\s[url\(]?[\'\"]{1}([A-Za-z0-9\.\/\_\-]+)[\'\"]{1}[\)]?[;]?/', $string, $matches);
		$matched_lines = $matches[0];
		$matched_filenames = $matches[1];
		$count = 0;

		// Iterate and parse
		foreach($matched_filenames as $filename) {

			$filename = $this->_normalize_file_path($filename, $relative_path, TRUE);

			// Read the file
			$file_data = $this->_read_file($filename);

			// If we have data, replace the @import
			if ($file_data) {
				$string = str_replace($matched_lines[$count], $file_data, $string);
			}

			$count++;
		}

		return $string;
	}
  
	/**
	 * Removes double slashes from string
	 * @param string $str
	 * @return string
	 * @author André Elvan
	*/
  private function remove_double_slashes($str) {
		return preg_replace("#([^/:])/+#", "\\1/", $str);
	}

  

	/**
	 * Writes the message to the template log
	 * @param string $message
	 * @return void
	 * @author Jesse Bunch
	*/
	private function _write_log($message) {
		//$this->EE->TMPL->log_item("AutoMin Module: $message");
	}
  
    
  
}

require_once(CRAFT_PLUGINS_PATH.'automin/vendor/class.minify_css_compressor.php');

/**
 * This class extends the default Minify CSS compression class
 * to allow us to NOT strip line number comments from SCSS. 
 * We need these comments to be able to generate source maps.
 */
class Automin_Minify_CSS_Compressor extends \Minify_CSS_Compressor {
	
	/**
	 * Override the default process() function to ensure
	 * we instatiate our own class, not the default one
	 * 
	 * The option ['keepLineNumbers' => true] must be passed in
	 * for source maps to work correctly
	 * 
	 * @param $css uncompressed source code
	 * @param array $options
	 * @return compressed/minified source
	 */
	public static function process($css, $options = array())
	{
	    $obj = new Automin_Minify_CSS_Compressor($options);
	    return $obj->_process($css);
	}
	
  /**
   * Override the default comment handling function
   * A comment string is passed in. If 'keepLineNumbers' == true
   * and it looks like a line number, we'll return the original 
   * unmodified comment, otherwise pass it onto the default
   * comment handler
   * 
   * @param array $m regex matches
   * @return string
   */
  protected function _commentCB($m) {
      if (!empty($this->_options['keepLineNumbers']))
      	if (strpos($m[1], 'line ') !== false)
      		return $m[0];
      return parent::_commentCB($m);
  }
}
