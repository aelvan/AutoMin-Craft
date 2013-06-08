<?php 

class Automin_model {

	/**
	 * Constructor
	 * @return void
	 * @author Jesse Bunch
	*/
	public function __construct() {

	}

	/**
	 * Returns the AutoMin cache path for the current site
	 * @author Jesse Bunch
	 * @return string
	*/
	public function get_cache_path() {
		$settings_array = $this->get_settings();
		return $settings_array['cache_path'];
	}

	/**
	 * Returns the AutoMin cache URL for the current site
	 * @author Jesse Bunch
	 * @return string
	*/
	public function get_cache_url() {
		$settings_array = $this->get_settings();
		return $settings_array['cache_url'];
	}

	/**
	 * Is AutoMin enabled?
	 * @author Jesse Bunch
	 * @return bool
	*/
	public function is_automin_enabled() {
		$settings_array = $this->get_settings();
		return ($settings_array['enabled'] == 'yes');
	}

	/**
	 * Should we cache AutoMin results?
	 * @author Jesse Bunch
	 * @return bool
	*/
	public function is_caching_enabled() {
		$settings_array = $this->get_settings();
		return ($settings_array['caching_enabled'] == 'yes');
	}

	/**
	 * Retrieves an array of AutoMin's settings for the current site
	 * @return array
	 * @author André Elvan
	*/
	public function get_settings() {
		static $settings_array;
    
		// No sense in querying for settings
		// more than once per request.
		if (!$settings_array) {
      $settings_array = Spyc::YAMLLoad('_config/add-ons/automin.yaml');
		}

		return $settings_array;
	}

}
