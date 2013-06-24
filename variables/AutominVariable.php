<?php
/**
 * Automin variables
 *
 * @author André Elvan
 */

namespace Craft;

class AutominVariable
{
  
  public function isEnabled() {
    return craft()->automin->getSetting('autominEnabled');
  }

  public function isCachingEnabled() {
    return craft()->automin->getSetting('autominCachingEnabled');
  }

  public function getPublicRoot() {
    return craft()->automin->getSetting('autominPublicRoot');
  }

  public function getCachePath() {
    return craft()->automin->getSetting('autominCachePath');
  }

  public function getCacheURL() {
    return craft()->automin->getSetting('autominCacheURL');
  }

  public function process($content, $type, $attr='') {
    return craft()->automin->process($content, $type, $attr);
  }
  
}