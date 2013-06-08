<?php
/**
 * Automin Twig Extension
 *
 * @author André Elvan
 */

namespace Craft;

use Twig_Extension;
use Twig_Filter_Method;

class AutominTwigExtension extends Twig_Extension
{
    public function getName()
    {
        return 'automin';
    }

    public function getFilters()
    {
        return array(
            'automin' => new Twig_Filter_Method($this, 'autominFilter', array('is_safe'=>array('html'))),
        );
    }

    /**
     * The automin filter compiles, combines and minifies javascript, css and less.
     */
  
    public function autominFilter($content, $type, $attr='')
    {
      return craft()->automin->process($content, $type, $attr);
    }
}
