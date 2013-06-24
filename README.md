Disclaimer
---
*AutoMin for Craft is a port of Jesse Bunch's [AutoMin add-on for ExpressionEngine](https://github.com/bunchjesse/AutoMin). The Craft version wouldn't be possible without Jesse's fantastic work, and it is being published with his approval.*
  
*The main changes from the ExpressionEngine version is that the settings are ported to how Craft works. Also, HTML compression is removed and logging is disabled for now.*

*Apart from that, I hope that I've managed to not mess up Jesse's original code too much, and that it still works as intended. :)*


Introduction
---
AutoMin for [Craft](http://buildwithcraft.com/) is a plugin that automates the combination and compression of your source files and currently supports CSS, JavaScript, and LESS compression.

AutoMin is smart enough to know when you've changed your source files and will automatically regenerate it's cache when appropriate.

For support, please file a bug report or feature request at the repository on Github:    
https://github.com/aelvan/AutoMin-Craft/issues

Please note: I work on this project in my spare time so I may not be able to address your issues right away. This is why AutoMin is free. The code is well organized and documented so feel free to poke around the and submit pull requests for any improvements you make.

I've also made a port of [AutoMin for Statamic](https://github.com/aelvan/AutoMin-Statamic/).


Special Thanks
---
Thanks to the minify project for their CSS compressor and the JSMin project for their JavaScript minifiaction class. Also, thanks goes to leafo for the PHP LESS processor. 

 - Minify: http://code.google.com/p/minify/
 - JSMin: http://www.crockford.com/javascript/jsmin.html
 - LESS for PHP: http://leafo.net/lessphp/


Changelog
---
### Version 0.2
 - Added "Public web root path" as a configuration parameter. 
 
### Version 0.1
 - Initial Public Release


Installation
---
1. Download and extract the contents of the zip. Copy the /automin folder to your Craft plugin folder. 
2. Create the AutoMin cache directory somewhere below your document root. Make sure it is writable by Apache (most of the time this means giving the folder 777 permissions).
3. Enable the AutoMin plugin in Craft (Settings > Plugins).
4. Click on the AutoMin plugin to configure the plugin settings, or configure it via the general config file (see "Configuration" below).
5. Add AutoMin to your templates (see "Example Usage" below). 
6. Refresh your site. If all goes well, you'll see your CSS and JS code combined and compressed. Note: the first page load after changing your source files or the AutoMin template tags could take longer than usual while your code is compressed.


Configuration
---
AutoMin has to be configured to work. You can either do this through the plugins settings in the control panel, or 
by adding the settings to the general config file (usually found in /craft/config/general.php). Configuring it in
the settings file is more flexible, since you can set up the config file to have different settings depending on the 
environment.

####Example

    'autominEnabled' => true,
    'autominCachingEnabled' => true,
    'autominPublicRoot' => '/path/to/webroot/public',
    'autominCachePath' => '/path/to/webroot/public/cache',
    'autominCacheURL' => '/cache',

*The autominPublicRoot setting is only needed if your site's main index.php file is not at your webroot. For instance if you're running 
a multi-language site with the different languages as subfolders.*


Example Usage
---
AutoMin for Craft is made as a Twig extension filter. This gives you numerous ways of utilizing it, you choose what 
works the best for you. First, the way I prefer, mostly because it's most similar to the ExpressionEngine and 
Statamic way, and because it interfers the least with my plain HTML.

####JavaScript

    {% filter automin('js') %}
        <script src="/js/jquery.js"></script>
        <script src="/js/gsap/plugins/CSSPlugin.min.js"></script>
        <script src="/js/gsap/easing/EasePack.min.js"></script>
        <script src="/js/gsap/TweenLite.min.js"></script>
        <script src="/js/main.js"></script>
    {% endfilter %}

####CSS

    {% filter automin('css', 'rel="stylesheet" title="default"') %}
        <link rel="stylesheet" href="/css/normalize.css" />
        <link rel="stylesheet" href="/css/core.css" />
        <link rel="stylesheet" href="/css/main.css" />
    {% endfilter %}


####LESS

    {% filter automin('less', 'rel="stylesheet"') %}
        <link rel="stylesheet/less" href="/less/elements.less" />
        <link rel="stylesheet/less" href="/less/normalize.less" />
        <link rel="stylesheet/less" href="/less/core.less" />
        <link rel="stylesheet/less" href="/less/main.less" />
    {% endfilter %}

But, you can also do something like this:

    {% set jsincludes %}
        <script src="/js/jquery.js"></script>
        <script src="/js/gsap/plugins/CSSPlugin.min.js"></script>
        <script src="/js/gsap/easing/EasePack.min.js"></script>
        <script src="/js/gsap/TweenLite.min.js"></script>
        <script src="/js/main.js"></script>
    {% endset %}
    {{ jsincludes | automin('js') }}

Or something like this:

    {% includeJsFile "/js/jquery.js" %}
    {% includeJsFile "/js/gsap/plugins/CSSPlugin.min.js" %}
    {% includeJsFile "/js/gsap/easing/EasePack.min.js" %}
    {% includeJsFile "/js/gsap/TweenLite.min.js" %}
    {% includeJsFile "/js/main.js" %}
  
    {{ getFootHtml() | automin('js') }}


Or some kind of combination of these. As I (and almost noone else) don't have much experience with Craft so far,
I have no idea what will turn out to be the best way of using it. :)

*Because the filter outputs raw HTML, output escaping has been turned off. You should under no circumstances run this 
filter on user generated content.*

Also, see the template variable section below for an alternative way to use AutoMin.


Tag Parameters
---
As shown in the above examples you can specify tag attributes using the sectond parameter in the automin filter. For example:

This:

    {% filter automin('js', 'type="text/javascript"') %}
    
Outputs something similar to:

    <script src="/cache/7dc66e1b2104b40a9992a3652583f509.js?modified=8832678882928" type="text/javascript"></script>

And this:

    {% filter automin('css', 'rel="stylesheet" title="default" media="screen, projection"') %}

Outputs something similar to:

    <link href="/cache/55ed34446f3eac6f869f3fe5b375d311.css?modified=8832678882928" type="text/css" title="default" rel="stylesheet" media="screen, projection">


Template Variable
---
All the settings are exposed through the automin template variable, so you can do this:
 
    {% if craft.automin.isEnabled() %}
        Automin is enabled
    {% else %}
        Automin is disabled
    {% endif %}
    
    {% if craft.automin.isCachingEnabled() %}
        Automin caching is enabled
    {% else %}
        Automin caching is enabled
    {% endif %}
    
    Public root path: {{ craft.automin.getPublicRoot() }}
    Cache path: {{ craft.automin.getCachePath() }}
    Cache URL: {{ craft.automin.getCacheURL() }}

The main processing function is also exposed, you can use it like this:
 
     {% set jsincludes %}
        <script src="/js/jquery.js"></script>
        <script src="/js/gsap/plugins/CSSPlugin.min.js"></script>
        <script src="/js/gsap/easing/EasePack.min.js"></script>
        <script src="/js/gsap/TweenLite.min.js"></script>
        <script src="/js/main.js"></script>
    {% endset %}
    
    {{ craft.automin.process(jsincludes, 'js') | raw }}


Compiling LESS
---
If you use AutoMin to compile your LESS source files, you DO NOT need to include the less.js parser file. AutoMin will parse your LESS source file and then compress the CSS output before sending it to your browser.


Troubleshooting
---
Make sure your cache directory is set in the module's settings and that the directory is writeable by PHP. In most cases, you'll need to assign that directory writable permissions. Usually this is 777.

If AutoMin breaks your CSS or JS code, make sure that your code contains no syntax errors. In your JS, you need to make sure that you always terminate JS statements with a semi-colon. Try running your source code through the relevant lint program for a validity check.

Make sure that your CSS urls are web-root relative. Use URLs like: `url('/css/img/myimage.jpg')` instead of `url('img/myimage.jpg')`


"Save some CPU cycles and precompile it instead!"
---
(I know this will come up)

NO! Precompiling is the mother of all f-ups. Or, not really... But. I don't have the need for a build tool except for compiling LESS, minification and combining of JS and CSS. Using an add-on like this, I don't need it at all. 

Also, this removes all sources for mistakes and/or misunderstandigs regarding where the source code resides, since the same files will be in all environments. We're a tiny shop, and this comes in handy when handing off projects to other developers and/or clients, who doesn't necessarily use git.
         
But of course... Feel free to precompile all you want! ;)         
