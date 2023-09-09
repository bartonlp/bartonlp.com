# This is the default for __www.bartonlp.com__

This directory has a *index.html* file with a very sparse message.  
It also has a sub-directory *otherpages* which contains several programs that are used by several other of my domains.  
These are the file in the *otherpages* directory:
```
    aboutwebsite.eval* -> aboutwebsite.php
    analysys.eval* -> analysis.php
    beacon.eval* -> beacon.php
    beacon.php* -> ../../vendor/bartonlp/site-class/includes/beacon.php
    geoAjax.eval* -> geoAjax.php
    getcookie.eval* -> getcookie.php
    js/
    robots.eval* -> robots.php
    sitemap.eval* -> sitemap.php
    tracker.eval* -> tracker.php
    tracker.php* -> ../../vendor/bartonlp/site-class/includes/tracker.php
    webstats-ajax.eval* -> webstats-ajax.php
    webstats.eval* -> webstats.php
    .htaccess
    aboutwebsite.php
    analysis.php
    fix.php
    geoAjax.php
    getcookie.php
    goto.php
    mysitemap.json
    robots.php
    sitemap.php
    webstats-ajax.php
    webstats.php
    index.php
    robots.txt
    Sitemap.xml
```

The files <code>/etc/apache2/sites-enabled/{bartonlp.conf | bartonlp-le-ssl.conf}</code>
both have a rewrite rule as follows:
<pre>
  RewriteEngine on
  RewriteRule ^/(otherpages/.*)$ "/$1" [L]
  RewriteRule ^/.\*/.\*$ /WHAT.html [L]
  RewriteRule "^/(PHP_ERRORS.\*|composer.*|package-lock.*)$"  "/WHAT.html" [L]
</pre>
This keeps visitors from looking into our other directories or important files. They can still see my _README.md_ file.

Our server IP Address is __157.245.129.4__. 
