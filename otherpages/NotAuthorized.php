<?php
// BLP 2023-10-18 - This is the catch all file when someone tries to run one of my include file
// directly from the browser.
/*
CREATE TABLE `badplayer` (
  `primeid` int NOT NULL AUTO_INCREMENT,
  `id` int DEFAULT NULL,
  `ip` varchar(20) NOT NULL,
  `site` varchar(50) DEFAULT NULL,
  `page` varchar(255) DEFAULT NULL,
  `botAs` varchar(50) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `errno` varchar(100) DEFAULT NULL,
  `errmsg` varchar(255) NOT NULL,
  `agent` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `lasttime` datetime DEFAULT NULL,
  PRIMARY KEY (`primeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci*/

$_site = require_once getenv("SITELOADNAME");
$S = new SiteClass($_site);

// Some times these are supplied as parameters, but not always.
// $_GET['site'] should be the $S->siteDomain not $S->siteName!

$site = $_GET['site'] ?? $_GET['host'] ?? $S->siteDomain;
$page = $_GET['page'] ?? $S->self;
$ip = $_GET['ip'] ?? $S->ip;
$agent = $_GET['agent'] ?? $S->agent;
if(empty($agent)) $agent = "NO_AGENT";
$id = $S->LAST_ID;

error_log("NotAuthorized.php Call from browser, Go Away: id=$id, ip=$ip, site=$site, page=$page, agent=$agent, type=$type, errno=$errno, errmsg=$errmsg, line=". __LINE__);

$S->sql("insert into $S->masterdb.badplayer (id, ip, site, page, agent, type, errno, errmsg, created, lasttime) ".
        "values($id, '$ip', '$site', '$page', '$agent', 'NOT_AUTH', 910, 'NOT_AUTHORIZED', now(), now())");

echo <<<EOF
<h1>Not Authorized</h1>
<p>This page should only be referenced from another page on our site.</p>
<a href="https://bartonphillips.com">Return to our home page</a>
EOF;
exit();
