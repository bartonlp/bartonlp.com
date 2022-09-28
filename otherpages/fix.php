#!/bin/bash

cd /var/www

for x in allnaturalcleaningcompany bartonphillips.com tysonweb newbernzig.com
do
cd $x
rm aboutwebsite.php analysis.php geoAjax.php getcookie.php robots.php sitemap.php

ln -s ../otherpages/aboutwebsite.php
ln -s ../otherpages/analysis.php
ln -s ../otherpages/geoAjax.php 
ln -s ../otherpages/getcookie.php 
ln -s ../otherpages/robots.php 
ln -s ../otherpages/sitemap.php 
cd -
done;
