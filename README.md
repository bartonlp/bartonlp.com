# This is the default for __www.bartonlp.com__

This directory has a *index.html* file with a very sparse message.
There are two other directories, *usingpdo* and *otherpages*.

The *usingpdo* drectory has a number of examples using my **SimpleSiteClass** with the **SimpledbPdo** class. The subdirectory
*simple-site-class* has an *examples* directory with two additional subdirectories, *IfComposer* and *IfDownloadedZip*.
These directories run the **SimpleSiteClass** using the standard *composer* setup (IfComposer), or a version that can be run
from a downloaded zip file (IfDownloadedZip). The downloaded examples can be run via *php -S localhost:&lt;port&gt;*. Or if they were 
downloaded into a real active **Apache** domain, from that domain. There are examples that use the *mysql* and the *sqlite* PDO drivers.
The *sqlite* driver uses a file database (mysqlite.db).

The second sub-directory *otherpages* contains several programs that are used by my other domains.  
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


Our server IP Address is __157.245.129.4__. 
