<style>
code { color: red }
</style>
# This is the default for __www.bartonlp.com__

This directory has a *index.html* file with a very sparse message.  
It also has a sub-directory *otherpages* which contains several programs that are used by several other of my domains.  
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
