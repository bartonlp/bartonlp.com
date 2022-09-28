<?php
// This file is a substitute for Sitemap.xml. This file is RewriteRuled in
// .htaccess to read Sitemap.xml and output it. It also writes a record into the bots tables and
// logagent.

$_site = require_once(getenv("SITELOADNAME"));
require_once(SITECLASS_DIR . "/defines.php");

$S = new Database($_site);

if(!file_exists($S->path . "/Sitemap.xml")) {
  echo "<h1>404 - FILE NOT FOUND</h1>";
  exit();
}

$sitemap = file_get_contents($S->path."/Sitemap.xml");
echo $sitemap;

if($S->isMe()) return;

// Get info from myip
// Check to see if this ip is in the myip table.

$ip = $S->ip;
$agent = $S->agent;

try {
  // BLP 2021-12-26 -- robots is 4 for insert and robots=robots|8 for update.

  $S->query("insert into $S->masterdb.bots (ip, agent, count, robots, site, creation_time, lasttime) ".
            "values('$ip', '$agent', 1, " . BOTS_SITEMAP . ", '$S->siteName', now(), now())");
} catch(Exception $e) {
  if($e->getCode() == 1062) { // duplicate key
    $S->query("select site from $S->masterdb.bots where ip='$ip'");

    list($who) = $S->fetchrow('num');

    if(!$who) {
      $who = $S->siteName;
    }
    if(strpos($who, $S->siteName) === false) {
      $who .= ", $S->siteName";
    }
    $S->query("update $S->masterdb.bots set robots=robots|" . BOTS_SITEMAP . ", count=count+1, site='$who', lasttime=now() ".
              "where ip='$ip'");
  } else {
    error_log("robots: ".print_r($e, true));
  }
}

// BLP 2021-11-12 -- 4 for sitemap
// BLP 2021-12-26 -- bots2 primary key is 'ip, agent, date, site, which'.

$S->query("insert into $S->masterdb.bots2 (ip, agent, date, site, which, count, lasttime) ".
          "values('$ip', '$agent', now(), '$S->siteName', " . BOTS_SITEMAP . ", 1, now()) ".
          "on duplicate key update count=count+1, lasttime=now()");

// Insert or update logagent

$S->query("insert into $S->masterdb.logagent (site, ip, agent, count, created, lasttime) values('$S->siteName', '$ip', '$agent', 1, now(), now()) ".
          "on duplicate key update count=count+1, lasttime=now()");