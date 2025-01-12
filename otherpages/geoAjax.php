<?php
// This is the Ajax for webstats.js, getcookie.php and geo.js
// BLP 2023-08-12 - We do not need to symlink this file!
// BLP 2023-09-10 - IMPORTANT: This file does noTrack=true so we do not do tracker.js and therefore
// no tracker.php or beacon.php.

// *** Remember, this does not happen untill the entire page has been rendored so the
// fingerprint and tracker info are not available for the PHP files!

if(!$_POST['mysitemap']) {
  error_log("geoAjax: No 'mysitemap' this is probably someone trying to run this by itself: {$_SERVER['REMOTE_ADDR']}");
  echo "<h1>Not Authorized</h1><p>This file is not to be run standalone.</p>";
  exit();
}
$_site = require_once(getenv("SITELOADNAME"));

$mysite = $_POST['mysitemap'];

//$ip = $_SERVER['REMOTE_ADDR'];
//if($ip != "195.252.232.86") error_log("*** geoAjax.php: mysite=$mysite, site=$_site->siteName, ip=$ip, host=$tmp");

$_site->noTrack = true; // BLP 2023-09-10 - DO NOT TRACK. We will not load tracker.js and therefore not do tracker.php or beacon.php

$S = new SiteClass($_site);

//$DEBUG = true;

// BLP 2021-10-07 -- AJAX for geo.js used in index.php for bartonphillips.com, tysonweb and
// newbernzig.com (also bartonphillips.org on HP).
/*
CREATE TABLE `geo` (
  `lat` varchar(50) NOT NULL,
  `lon` varchar(50) NOT NULL,
  `finger` varchar(100) DEFAULT NULL,
  `site` varchar(100) DEFAULT NULL,
  `ip` varchar(20) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `lasttime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
*/

if($_POST['page'] == 'geo') {
  $lat = $_POST['lat'];
  $lon = $_POST['lon'];
  $visitor = $_POST['visitor'];
  $id = $_POST['id'];
  $site = $_POST['site']; // This is the site that actually made the request
  $ip = $_POST['ip'];

  //error_log("geo: ip=$ip");
  
  $site = $site ?? $S->siteName;
  
  // We have the lat and long and visitor so set nogeo to false (the default is NULL).
  
  $S->sql("update $S->masterdb.tracker set nogeo=0 where id='$id'");
  
  $exp = time() + 60*60*24*365;

  if($S->setSiteCookie("BLP-geo", "$lat:$lon", $exp) === false) {
    error_log("geoAjax: setSiteCookie geo Error: lat:lon=$lat:$lon, exp=$exp");
    echo "geoAjax: setSiteCookie geo Error";
    exit();
  }

  $sql = "select lat, lon from $S->masterdb.geo where site='$site' and finger='$visitor' and ip='$ip'";
  $x = $S->sql($sql);

  // If lat and lon is the same as what we just found update
  
  while([$slat, $slon] = $S->fetchrow('num')) {
    $found = 0;
    if($slat === $lat && $slon === $lon) {
      ++$found;
      // We use $site instead of $S->siteName as they may be different.
      
      $sql = "update $S->masterdb.geo set lasttime=now(), ip='$ip' where lat=$slat and lon=$slon and site='$site' and finger='$visitor'";
      $S->sql($sql);

      //if($DEBUG) error_log("geoAjax $id, $site, $ip -- geo Updated");
      //echo "geo Update: $id, $site, $ip";
      //exit();
    }
    if($found) {
      if($DEBUG) error_log("geoAjax $id, $site, $ip -- geo Updated: found=$found");
      echo "Update: $id, $site, $ip";
      exit();
    }
  }

  // This is either a new visitor or the lat and lon are not the same as before. Insert.
  
  $sql = "insert into $S->masterdb.geo (lat, lon, finger, site, ip, created, lasttime) values('$lat', '$lon', '$visitor', '$site', '$ip', now(), now())";
  $S->sql($sql);

  if($DEBUG) error_log("geoAjax $id, $site, $ip -- geo Insert");
  echo "Insert: $id, $site, $ip";
  exit();
}

if($_POST['page'] == 'geoFail') {
  $id = $_POST['id'];
  
  $S->sql("update $S->masterdb.tracker set nogeo=1 where id='$id'");

  if($DEBUG) error_log("geoAjax $id, $site, $ip -- geoFail");
  echo "geoFail: id=$id";
  exit();
}

// Ajax for finger. Remember, this does not happen untill the entire page has been rendored so the
// fingerprint and tracker are not available for the PHP files!

if($_POST['page'] == 'finger') {
  $visitor = $_POST['visitor'];
  $id = $_POST['id'];
  
  $exp = time() + 60*60*24*365;

  if($S->setSiteCookie("BLP-Finger", $visitor, $exp) === false) {
    error_log("geoAjax: setSiteCookie Finger Error");
    echo "geoAjax: setSiteCookie Finger Error"; // This is returned to the javascript that called this.
    exit();
  }

  // Get the ip, site and agent so we can update logagent with the finger.
  
  $S->sql("select ip, site, agent from $S->masterdb.tracker where id=$id");
  [$ip, $site, $agent] = $S->fetchrow('num');

  //error_log("geoAjax.php: setSiteCookie BLP-Finger=$visitor, id=$id, ip=$ip, site=$site");
    
  // tracker table was created in SiteClass

  $sql = "update $S->masterdb.tracker set finger='$visitor' where id=$id";
  $S->sql($sql);

  // Update logagent with finger.
  
  $S->sql("update $S->masterdb.logagent set finger='$visitor' where ip='$ip' and site='$site' and agent='$agent'");
  
  if($DEBUG) error_log("geoAjax $id, $visitor -- finger Updated");
  echo "Updated: $id, $visitor"; // Returned to the javascript.
  exit();
}
