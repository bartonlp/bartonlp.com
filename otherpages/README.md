# This directory holds all of the extra programs that are used in our main pages.

For example, __aboutwebsite.php__. This is called from most of my main pages.
It was in __bartonphillips.net__ but I want that directory to remain *cookieless* so I moved all of the PHP files here.

The *eval* files are symlinked to the corresponding PHP file. The *eval* files are used by my sites that are not on my server at __DigitalOcean.com__.
For example, the file *robots.php* on a remote host would look like this:  
<code>
\<?php  
\$page = file_get_contents("http://www.bartonlp.com/otherpages/robots.eval");  
return eval("?>". \$page);
</code>

The following *eval* files are symlinked to the corresponding PHP file.
```
    aboutwebsite.eval -> aboutwebsite.php
    analysys.eval -> analysis.php
    beacon.eval -> beacon.php
    geoAjax.eval -> geoAjax.php
    getcookie.eval -> getcookie.php
    webstats-ajax.eval -> webstats-ajax.php
    webstats.eval -> webstats.php
    robots.eval -> robots.php
    sitemap.eval -> sitemap.php
    tracker.eval -> tracker.php
```
The __beacon.php__ and __tracker.php__ files are symlinked to the SiteClass directory under *vendor/bartonlp/site-class*. 
I do this because I need a real URL so I can get to these file. I would use <code>https://bartonlp.com/otherpages</code>.

The tracker.php file also the *mysitemap* variable to find the __mysitemap.json__ file for the parent of the __tracker.js__ file.

```
    beacon.php -> ../../vendor/bartonlp/site-class/includes/beacon.php
    tracker.php -> ../../vendor/bartonlp/site-class/includes/tracker.php
    js/tracker.js -> ../../../vendor/bartonlp/site-class/includes/tracker.js
```
The following are files that are used by my other sites. The files marked in <span class='red'>red</span>
must be symlinked into the parent's directory because they need information in the host's __mysitemap.json__ file.

<style>
.red { color: red; }
</style>
<pre>
<code>
    webstats.php
    webstats-ajax.php
    js/webstats.js
    aboutwebsite.php
    analysis.php
    goto.php
    <span class='red'>geoAjax.php
    getcookie.php
    robots.php
    sitemap.php</span>
    mysitemap.json  **contains information needed by the above files.
</code>
</pre>
These files are for this directory:
```
    index.php
    robots.txt
    Sitemap.xml
```

Contact Me at [bartonphillips@gmail.com](mailto:bartonphillips@gmail.com)

