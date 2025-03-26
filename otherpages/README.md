# This directory holds all of the extra programs that are used in our main pages.

For example, __aboutwebsite.php__. This is called from most of my main pages.

File are symlinked to /var/www/vendor/bartonlp/site-class/includes. A list of these files follows:

```
    beacon.php
    tracker.php
    tracker.js -- symlinked to js director
    logger.php
    logger.js -- symlinked to js directory
    robots.php
    sitemap.php
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

// BLP 2021-10-22 -- To Remotely debug from my Tablet:  
// On my desktop browser: chrome://inspect/#devices  
// Plug the tablet into the USB port. You should see "Chrome" and each of the domains that are open on the tablet.  
// You can then debug the tablet if you click on "inspect". It will open the dev-tools.

Contact Me at [bartonphillips@gmail.com](mailto:bartonphillips@gmail.com)

