#!/usr/bin/php
<?php
// BLP 2021-02-26 -- NOT USED currently in all-cron.sh
  
echo "checktracker.php\n";
$_site = require_once("/var/www/vendor/bartonlp/site-class/includes/siteload.php");

// BLP 2021-02-26 -- NOTE this was Database() but Database does not include $S->myIp!!!
$S = new SiteClass($_site); //Database($_site);
//vardumpNoEscape("S", $S);

$db = $S->masterdb;

foreach($S->myIp as $v) {
  $ips[] = "'" . gethostbyname($v) . "'";
}
$myIps = implode(',', $ips);

//echo "myIps: $myIps\n";
//exit();

// Get the current days records
// the key is id so there is one per session.
// This is actually yesterday as the CRON job is run at the beginning of the day.

$sql = "select ip, agent, site, isJavaScript " .
       "from $db.tracker ".
       "where ip not in ($myIps) and isJavaScript = 0 ".
       "and lasttime >= current_date() - interval 1 day order by ip";

// Are there any Zeros

if($S->query($sql)) {
  $r = $S->getResult();
  while(list($ip, $agent, $site, $isJava) = $S->fetchrow($r, 'num')) {
    echo "Tracker, ip: $ip, agent: $agent, site: $site, ".dechex($isJava)."\n";
    
    $sql = "select ip, agent, site, which from $db.bots2 ".
           "where ip='$ip' and agent='$agent' and date=current_date";

    if($S->query($sql)) {
      while(list($bip, $bagent, $bsite, $which) = $S->fetchrow('num')) {
        echo "Bots2, ip: $bip, agent: $bagent, site: $bsite, which: $which\n";
      }
    }
  }
}

echo "\nDONE\n";

