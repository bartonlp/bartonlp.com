# This directory holds all of the extra programs that are used in our main pages.

For example, aboutwebsite.php. This is called from most of my main pages but should never be called from the outside.
It was in bartonphillips.net but I want that directory to remain 'cookieless' so I moved all of the extra files here.

The *eval* files are symlinked to the corresponding PHP file. The *eval* files are used by my sites that are not on this server.
For example, the file *robots.php* on the remote host would look like this:  
<code>
\<?php  
\$page = file_get_contents("http://www.bartonlp.com/otherpages/robots.eval");  
return eval("?>". \$page);
</code>

```
    aboutwebsite.eval* -> aboutwebsite.php
    analysys.eval* -> analysis.php
    beacon.eval* -> beacon.php
    geoAjax.eval* -> geoAjax.php
    getcookie.eval* -> getcookie.php
    webstats-ajax.eval* -> webstats-ajax.php
    webstats.eval* -> webstats.php
    robots.eval* -> robots.php
    sitemap.eval* -> sitemap.php
    tracker.eval* -> tracker.php
```
The beacon.php and tracker.php files are symlinked to the SiteClass directory under *vendor*.

```
    beacon.php* -> ../../vendor/bartonlp/site-class/includes/beacon.php
    tracker.php* -> ../../vendor/bartonlp/site-class/includes/tracker.php
    js/tracker.js -> ../../../vendor/bartonlp/site-class/includes/tracker.js
```
The following are files that are used by my other sites. The ones marked must be symlinked because they need information in the host's
*mysitemap.json* file.
```
    webstats.php
    webstats-ajax.php
    js/webstats.js
    aboutwebsite.php
    analysis.php
    goto.php
    geoAjax.php     // symlinked from the host directory
    getcookie.php   // symlinked form the host directory
    robots.php      // symlinked from the host directory
    sitemap.php     // symlinked from the host directory
    mysitemap.json  // contains information needed by the above files.
```
These files are for this directory:
```
    index.php
    robots.txt
    Sitemap.xml
```
