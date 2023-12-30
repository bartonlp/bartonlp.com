<?php
// BLP 2022-05-01 - Major rework. This now is in https://bartonlp.com/otherpages/webstats.php. I no
// longer use symlinks and the cumbersom rerouting logic is gone. Now webstats.php is called with
// ?blp=8653&site={sitename}. The GET grabs the site and puts it into $site. The post is called via
// the <select> and grabs the site a location header call which in turn does a new GET.
// Once the site is setup by the GET we get $_site and set $_site->siteName to $site.
// This file still uses webstats.js and webstats-ajax.php.
// BLP 2023-10-20 - This uses setupjava.i.php to pass the variable needed to the javascript.
// setupjava.i.php loads defines.php which has all the defines.

// IMPORTANT: mysitemap.json sets 'noGeo' true so we do not load it in SiteClass::getPageHead()
// We use map.js instead of geo.js

//$DEBUG = true;

$_site = require_once(getenv("SITELOADNAME"));
ErrorClass::setDevelopment(true);

// This function does a RAW mysqli insert (or what ever is in $sql) but it does not return anything.

function insertMysqli($sql):void {
  global $_site;
  
  $i = $_site->dbinfo;
  $p = require("/home/barton/database-password");
  $mysqli = new mysqli($i->host, $i->user, $p, 'barton');

  $mysqli->sql($sql);
}

// The GET is set by the POST below or from another of my sites that calls
// webstats.php?site=sitename.

if($_GET['site']) {
  $site = $_GET['site'];
  $specialDate = $_GET['date'];
}

// If someone does a <select> below of a siteName it comes here. I then do a GET with the sitename.

if(isset($_POST['submit'])) {
  $site = $_POST['site'];
  header("location: webstats.php?blp=8653&site=$site");
  exit();
}

// Now set siteName to $site from the GET.

$_site->siteName = $site;

// Gather info in case of an error.

$xsite = $_site->siteName;
$xagent = $_SERVER['HTTP_USER_AGENT'] ?? ''; // BLP 2022-01-28 -- CLI agent is NULL so make it blank ''
$xref = $_SERVER['HTTP_REFERER'];
$xip = $_SERVER['REMOTE_ADDR'];

if(empty($site)) {
  error_log("webstats.php ERROR: $xip, $xsite, site=NONE, ref=$xref, agent=$xagent");

  // We do not have $S so we can't add this to the badplayer table.

  insertMysqli("insert into barton.badplayer (ip, site, page, botAs, count, type, errno, errmsg, agent, created, lasttime) ".
               "values('$xip', '$xsite', 'webstats', 'counted', 1, 'NO_SITE', -200, 'NO site', '$xagent', now(), now()) ".
               "on duplicate key update count=count+1, lasttime=now()");
  
  echo <<<EOF
<h1>GO AWAY</h1>
EOF;
  exit();
}

if($DEBUG) $hrStart = hrtime(true);

// Wrap this in a try to see if the constructor fails

$_site->noTrack = $_site->noGeo = true; // BLP 2023-11-22 - Don't track or do geolocation.

try {
  $S = new SiteClass($_site);
  // BLP 2023-10-18 - This require sets up the constants needed by webstats.js.
  // It requires the defines.php

  require_once("./setupjava.i.php");
  $S->h_inlineScript .= "var thedate = '$specialDate';";
} catch(Exception $e) {
  $errno = $e->getCode();
  $errmsg = $e->getMessage();
  $sql = dbMySqli::$lastQuery;
  error_log("webstat.php constructor FAILED: $xip, $xsite, site=$site, sql=$sql, ref=$xref, errno=$errno, errmsg=$errmsg, agent=$xagent");

  // We do not have $S so we can't add this to the badplayer table.

  $sql = substr($sql, 0, 254); // Truncate just in case.

  // We do not have a $S so use the database name here and the x* items.
  
  insertMysqli("insert into barton.badplayer (ip, site, page, botAs, count, type, errno, errmsg, agent, created, lasttime) ".
               "values('$xip', '$xsite', 'webstats', 'counted', 1, 'CONSTRUCTOR_ERROR', -200, 'sql=$sql', '$xagent', now(), now()) ".
               "on duplicate key update count=count+1, lasttime=now()");
  
  echo "<h1><i>This Page is Restricted.</i></h1>"; // These are all different so I can find them.
  exit();
}

// Check for magic 'blp'. If not found check if one of my recent ips. If not justs 'Go Away'
// The magic comes only from adminsites.php or aboutwebsite.php

if(empty($_GET['blp']) || $_GET['blp'] != '8653') { // If blp is empty or set but not '8653' then check $S->myIp
  // BLP 2021-12-20 -- $S->myIp is always an array from SiteClass.

  if(!array_intersect([$S->ip], $S->myIp)) {
    error_log("*** webstats.php: $S->ip, $S->siteName, ERROR Not in myIp, blp={$_GET['blp']}"); // BLP 2023-11-11 - 
    insertMysqli("insert into $S->masterdb.badplayer (ip, site, page, botAs, count, type, errno, errmsg, agent, created, lasttime) ".
                 "values('$S->ip', '$S->siteName', 'webstats', 'counted', 1, 'ERROR_BLP', -300, 'sql=$sql', '$S->agent', now(), now()) ".
                 "on duplicate key update count=count+1, lasttime=now()");
    
    echo "<h1>This Page is Restricted (myIp)</h1>"; // These are all different so I can find them.
    exit();
  }
} 

// BLP 2023-11-11 - Not sure how this can happen?

if($_GET['blp'] != '8653') error_log("*** webstats.php: ip=$S->ip, site=$S->siteName, page=$S->self -- \$S->ip is in \$S->myIp but blp={$_GET['blp']}");

// At this point I know that blp was not empty. It does not have 8653 but but the ip is one of my ips (in $S->myIp).

if($S->isBot) {
  error_log("webstats.php: $S->siteName $S->self Bot Restricted, blp={$_GET['blp']} exit: $S->foundBotAs, IP=$S->ip, agent=$S->agent, line=" . __LINE__);
  echo "<h1>This Page is Restricted</h1>"; // These are all different so I can find them.
  exit();  
}

$S->link = <<<EOF
  <link rel="stylesheet" href="https://bartonphillips.net/css/newtblsort.css">
  <link rel="stylesheet" href="https://bartonphillips.net/css/webstats.css"> 
EOF;

// css for the gps location in ipinfo

$S->css = <<<EOF
.location, #tracker td:nth-of-type(3) { cursor: pointer; }
#tracker td:nth-of-type(2),#tracker td:nth-of-type(3),#tracker td:nth-of-type(4) {
  overflow-x: auto;
  max-width: 150px;
  white-space: pre;
}
EOF;

// BLP 2023-10-17 - add these in the <head>

$S->h_script = <<<EOF
<script src="https://bartonphillips.net/tablesorter-master/dist/js/jquery.tablesorter.min.js"></script>
EOF;

// BLP 2023-10-17 - add these after <footer>

$S->b_script = <<<EOF
<script src='https://bartonlp.com/otherpages/js/webstats.js'></script>
<script src="https://bartonphillips.net/js/maps.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA6GtUwyWp3wnFH1iNkvdO9EO6ClRr_pWo&callback=initMap&v=weekly" async></script>
EOF;

$today = date("Y-m-d");

// Get the fingerprints from the myfingerprints.php file.
// BLP 2023-10-18 - because webstats.php runs from bartonlp.com/otherpages and does not need
// symlinks, I can use a require_once here. In other places, like getcookie.php which do need to be
// symlinked into the director (and server) I use getfinger.php also in bartonphillips.net.

$myfingerprints = require_once("/var/www/bartonphillipsnet/myfingerprints.php");

$T = new dbTables($S); // My table class

// BLP 2021-10-08 -- add geo

function mygeofixup(&$row, &$rowdesc) {
global $today, $myfingerprints;

foreach($myfingerprints as $key=>$val) {
  if($row['finger'] == $key) {
    $row['finger'] .= "<span class='ME' style='color: red'> : $val</span>";
  }
}

if(strpos($row['lasttime'], $today) === false) {
  $row['lasttime'] = "<span class='OLD'>{$row['lasttime']}</span>";
} else {
  $row['lasttime'] = "<span class='TODAY'>{$row['lasttime']}</span>";
}
return false;
}

$sql = "select lat, lon, finger, ip, created, lasttime from $S->masterdb.geo where site = '$S->siteName' order by lasttime desc";
[$tbl] = $T->maketable($sql, ['callback'=>'mygeofixup', 'attr'=>['id'=>'mygeo', 'border'=>'1']]);

// BLP 2021-10-12 -- add geo logic
$geotbl = <<<EOF
<h2 id="table11">From table <i>geo</i></h2>
<a href="#analysis-info">Next</a>
<div id="geotable">
  <div id="outer">
    <div id="geocontainer"></div>
    <button id="removemsg">Click to remove map image</button>
  </div>
  <p id="geomsg"></p>
  $tbl
</div>
EOF;

$geoTable = "<li><a href='#table11'>Goto Table: geo</a></li>";

$S->title = "Web Statistics";

$S->banner = "<h1>Web Stats For <b>$S->siteName</b></h1>";

[$top, $footer] = $S->getPageTopBottom();

function blphome(&$row, &$rowdesc) {
  global $homeIp;

  $ip = $row['myIp'];

  if($row['myIp'] == $homeIp) {
    $row['myIp'] = "<span class='home'>$ip</span>";
  } else {
    $row['myIp'] = "<span class='inmyip'>$ip</span>";
  }
  return false;
}

$sql = "select myip as myIp, createtime as Created, lasttime as Last from $S->masterdb.myip order by lasttime";

[$tbl] = $T->maketable($sql, array('callback'=>'blphome', 'attr'=>array('id'=>'blpid', 'border'=>'1')));
  
$creationDate = date("Y-m-d H:i:s T");

$page = <<<EOF
<hr/>
<h2>From table <i>myip</i></h2>
<p>These are the IP Addresses used by the Webmaster.<br>
When these addresses appear in the other tables they are in
<span style="color: black; background: lightgreen; padding: 0 5px;">BLACK</span> or <span style="color: white; background: green; padding: 0 5px;">WHITE</span> if my home IP.</p>
$tbl
EOF;

function logagentCallback(&$row, &$desc) {
  global $S;

  $ip = $S->escape($row['IP']);

  $row['IP'] = "<span>$ip</span>";
}

$sql = "select ip as IP, agent as Agent, finger as Finger, count as Count, lasttime as LastTime " .
"from $S->masterdb.logagent ".
"where site='$S->siteName' and lasttime >= current_date() order by lasttime desc";

$tbl = $T->maketable($sql, array('callback'=>'logagentCallback', 'attr'=>array('id'=>"logagent", 'border'=>"1")))[0];
if(!$tbl) {
  $tbl = "<h3 class='noNewData'>No New Data Today</h2>";
} else {
  $tbl = <<<EOF
<div class="scrolling">
$tbl
</div>
EOF;
}

$page .= <<<EOF
<h2 id="table3">From table <i>logagent</i> for today</h2>
<a href="#table4">Next</a>
<h4>Showing $S->siteName for today</h4>
$tbl
EOF;

// BLP 2021-08-20 -- 
// Here 'count' is total number of hits (bots and real) so count-realcnt is the number of Bots.
// 'realcnt' is used in $this->hitCount which is the hit counter at the bottom of some pages.
// We do not count BOTS in the hitCount.
// Also we do NOT count me! If isMe() is true we do not count. See myUri.json and mysitemap.json.
// In myUri.json "/ HOME" is bartonphillips.dyndns.org. I have added the DynDns updater to my
// home computer's systemd so the IP address should always be the current IP at DynDns.
  
$sql = "select filename as Page, realcnt as 'Real', (count-realcnt) as 'Bots', lasttime as LastTime ".
"from $S->masterdb.counter ".
"where site='$S->siteName' and lasttime>=current_date() order by lasttime desc";

$tbl = <<<EOF
<table id="counter" border="1">
<thead>
<tr><th>Page</th><th>Real</th><th>Bots</th><th>Lasttime</th></tr>
</thead>
<tbody>
EOF;

$tbl = $T->maketable($sql, array('attr'=>array('border'=>'1', 'id'=>'counter')))[0];

if(!$tbl) {
  $tbl = "<h3 class='noNewData'>No New Data Today</h2>";
}

if($S->reset) {
  $reset = " <span style='font-size: 16px;'>(Reset Date: $S->reset)</span>";
}
    
$page .= <<<EOF
<h2 id="table4">From table <i>counter</i> for today</h2>
<a href="#table5">Next</a>
<h4>Showing $S->siteName grand TOTAL hits since last reset $reset for pages viewed today</h4>
<p>'real' is the number of non-bots and 'bots' is the number of robots.</p>
<div class="scrolling">
$tbl
</div>
EOF;

// 'count' is actually the number of 'Real' vs 'Bots'. A true 'count' would be Real + Bots.

$sql = "select filename as Page, `real` as 'Real', bots as Bots, lasttime as LastTime ".
"from $S->masterdb.counter2 ".
"where site='$S->siteName' and lasttime >= current_date() order by lasttime desc";

$tbl = $T->maketable($sql, array('attr'=>array('border'=>'1', 'id'=>'counter2')))[0];

if(!$tbl) {
  $tbl = "<h3 class='noNewData'>No New Data Today</h2>";
}
  
$page .= <<<EOF
<h2 id="table5">From table <i>counter2</i> for today</h2>
<a href="#table6">Next</a>
<h4>Showing $S->siteName  number of hits TODAY</h4>
$tbl
EOF;

/**** Start make daycount */
// mask are the things that are done via AJAX.

$mask = TRACKER_START | TRACKER_LOAD | TRACKER_TIMER | BEACON_VISIBILITYCHANGE | BEACON_PAGEHIDE | BEACON_UNLOAD | BEACON_BEFOREUNLOAD;

$meIp = null;
$ipAr = $S->myIp;

foreach($ipAr as $v) {
  $meIp .= "'$v',";
}
$meIp = rtrim($meIp, ',');

$real = $bots = $ajax = $countTot = 0; // Total accumulators.
$strAr = [];

// Make the daycount.

for($i=0; $i<7; ++$i) {
  $cntBots = $cntReal = $cntAjax = 0; // Local accumulators are reset each iteration.

  // Get the info for day number $i.
  
  $sql = "select difftime, date(lasttime), id, ip, isJavaScript from $S->masterdb.tracker where site='$S->siteName' ".
         "and ip not in($meIp) and date(lasttime)=current_date() - interval $i day order by ip desc";
  
  $S->sql($sql);

  while([$diff, $d, $id, $ip, $java] = $S->fetchrow('num')) {
    $dd = $d; // NOTE: save $d in $dd because when $S->fetchrow('num') returns NULL all of the array items are zero.
    
    if($java & $mask) {
      ++$cntAjax; // Ajax for date
      ++$ajax; // Total Ajax
    }

    // If $diff then count as real else count as bot.
    // $bots and $real are the footer totals.
    
    if(!$diff) {
      ++$cntBots;
      ++$bots;
    } else {
      ++$cntReal;
      ++$real;
    }
  }

  $count = $cntBots + $cntReal;
  $countTotal += $count;
  $strAr[] = "<tr><td>$dd</td><td>$count</td><td>$cntReal</td><td>$cntBots</td><td>$cntAjax</td>";
}

$S->sql("select date(lasttime) ".
          "from $S->masterdb.tracker ".
          "where starttime>=current_date() - interval 6 day ".
          "and site='$S->siteName' and not isJavaScript & ". TRACKER_BOT . // 0x200
          " and isJavaScript != ".  TRACKER_ZERO . // 0
          " and ip not in($meIp) group by ip,date(lasttime)");

// There should be ONE UNIQUE ip per row. So count them into the date.

$Visitors = 0;
$visitorsAr = [];

while([$date] = $S->fetchrow('num')) {
  ++$visitorsAr[$date];
  ++$Visitors;
}

$i = 0;
$visitsTotal = 0;

// Get visits from daycounts. This is actually the only thing we get from that table. 'visits' is
// updated in tracker.js as the 'mytime' cookie (see tracker.js)

$S->sql("select date, visits from $S->masterdb.daycounts where site='$S->siteName' and date>=current_date() - interval 6 day order by date desc");

while([$date, $visits] = $S->fetchrow('num')) {
  $strAr[$i++] .= "<td>$visitorsAr[$date]</td><td>$visits</td></tr>";
  $visitsTotal += $visits;
}

$str = implode("\n", $strAr); // Turn the array into a string seperated by cr. This is the body of daycount.

$hdr =<<<EOF
<table id='daycount' border='1'>
<thead>
<tr><th>Date</th><th>Count</th><th>Real</th><th>Bots</th><th>Ajax</th><th>Visitors</th><th>Visits</th></tr>
</thead>
<tbody>
EOF;

$ftr =<<<EOF
</tbody>
<tfoot>
<tr><th>Totals</th><td>$countTotal</td><td>$real</td><td>$bots</td><td>$ajax</td><td>$Visitors</td><td>$visitsTotal</td></tr>
</tfoot>
</table>
EOF;

// Make the table from the head, body, and footer.

$tbl = $hdr . $str . $ftr;

/**** End make daycount */

if($S->siteName == "Bartonphillipsnet") {
  $page .= <<<EOF
<h2 id="table6">We Do Not Count <i>daycount</i> For $S->siteName</h2>
<a href="#table7">Next</a>
EOF;
} else {
  $page .= <<<EOF
<h2 id="table6">From table <i>daycount</i> for seven days</h2>
<a href="#table7">Next</a>

<h4>Showing $S->siteName for seven days</h4>
<p>Webmaster (me) is not counted.</p>
<ul>
<li>'Visitors' is the number of distinct (AJAX) IP addresses (via 'tracker' table).
<li>'Count' is the sum of 'Real' and 'Bots', the total number of HITS.
<li>'Real' is the number of accesses that actually spent some time on our site (\$diff not empty).
<li>'AJAX' is the number of accesses with AJAX via tracker.js (from the 'tracker' table).
<li>'Bots' is the number of robots.
<li>'Visits' is the number of non-robots outside of a 10 minutes window.
</ul>

<p>So if you come to the site from two different IP addresses you would be two 'Visitors'.<br>
If you hit our site 10 times the sum of 'Real' and 'Bots' would be 10.<br>
If you hit our site 5 time within 10 minutes you will have only one 'Visits'.<br>
If you hit our site again after 10 minutes you would have two 'Visits'.</p>
$tbl
EOF;
}

$analysis = file_get_contents("https://bartonphillips.net/analysis/$S->siteName-analysis.i.txt");

if(!$analysis) {
  $errMsg = "<p>https://bartonphillips.net/analysis/$S->siteName-analysis.i.txt: NOT FOUND</p>";
  $analysis = null;
} else {
  $analysisGoto = "<li><a href='#analysis-info'>Goto Analysis Info</a></li>";
}            

$tracker = <<<EOF
<div id='trackerdiv' class="scrolling">
</div>
EOF;
  
function botsCallback(&$row, &$desc) {
  global $S;

  $ip = $S->escape($row['ip']);

  $row['ip'] = "<span class='bots-ip'>$ip</span>";
}
  
$sql = "select ip, agent, count, hex(robots) as bots, site, creation_time as 'created', lasttime ".
"from $S->masterdb.bots ".
"where site like('%$S->siteName%') and lasttime >= current_date() and count !=0 order by lasttime desc";

$bots = $T->maketable($sql, array('callback'=>'botsCallback', 'attr'=>array('id'=>'robots', 'border'=>'1')))[0];

$bots = <<<EOF
<div class="scrolling">
$bots
</div>
EOF;
  
function bots2Callback(&$row, &$desc) {
  global $S;

  $ip = $S->escape($row['ip']);

  $row['ip'] = "<span class='bots2-ip'>$ip</span>";
}

// BLP 2021-10-10 -- remove site from select for everyone

$sql = "select ip, agent, page, which, count from $S->masterdb.bots2 ".
"where site='$S->siteName' and date >= current_date() order by lasttime desc";

$bots2 = $T->maketable($sql, array('callback'=>'bots2Callback', 'attr'=>array('id'=>'robots2', 'border'=>'1')))[0];

$bots2 = <<<EOF
<div class="scrolling">
$bots2
</div>
EOF;
  
$date = date("Y-m-d H:i:s T");

// BLP 2021-10-10 -- Display even for Tysonweb

$form = <<<EOF
<form action="webstats.php" method="post">
  Select Site:
  <select id="select" name='site'>
    <option>Allnatural</option>
    <option>BartonlpOrg</option>
    <option>Bartonphillips</option>
    <option>Tysonweb</option>
    <option>Newbernzig</option>
    <option>Swam</option>
    <option>BartonphillipsOrg</option>
    <option>Rpi</option>
    <option>Bonnieburch</option>
    <option>Bridgeclub</option>
    <option>Marathon</option>
    <option>Bartonphillipsnet</option>
    <option>JT-Lawnservice</option>
    <option>Littlejohnplumbing</option>
  </select>

  <button type="submit" name='submit'>Submit</button>
</form>
EOF;

// BLP 2021-06-23 -- Only bartonphillips.com has a members table.

if($S->memberTable) {
  $sql = "select name, email, ip, agent, count, created, lasttime from $S->memberTable";

  $tbl = $T->maketable($sql, array('attr'=>array('id'=>'members', 'border'=>'1')))[0];

  if($geotbl) {
    $mTable = "<li><a href='#table10'>Goto Table: $S->memberTable</a></li>";
    $botsnext = "<a href='#table10'>Next</a>";
    $togeo = "<a href='#table11'>Next</a>";
  } else {
    $togeo = "<a href='#analysis-info'>Next</a>";
  }
  
  $mtbl = <<<EOF
<h2 id="table10">From table <i>$S->memberTable</i></h2>
$togeo
<div id="memberstable">
$tbl
</div>
EOF;
} else {
  $botsnext = $geotbl ? "<a href='#table11'>Next</a>" : "<a href='#analysis-info'>Next</a>";
}

if($DEBUG) {
  $hrEnd = hrtime(true);
  $serverdate = date("Y-m-d_H_i_s");
  header("Server-Timing: date;desc=$serverdate");
  header("Server-Timing: time;desc=Test_Timing;dur=" . ($hrEnd - $hrStart), false);
}

// At this point $page has everything up to tracker info.
// Render the page.

echo <<<EOF
$top
<div id="content">
$errMsg
$form
<main>
<p>$date</p>
<ul>
   <li><a href="#table3">Goto Table: logagent</a></li>
   <li><a href="#table4">Goto Table: counter</a></li>
   <li><a href="#table5">Goto Table: counter2</a></li>
   <li><a href="#table6">Goto Table: daycounts</a></li>
   <li><a href="#table7">Goto Table: tracker</a></li>
   <li><a href="#table8">Goto Table: bots</a></li>
   <li><a href="#table9">Goto Table: bots2</a></li>
$mTable
$geoTable
$analysisGoto
</ul>
<tables>
$page
<h2 id="table7">From table <i>tracker</i> today</h2>
<a href="#table8">Next</a>
<h4>Only Showing $S->siteName</h4>
<div>'js' is hex.
<ul>
<li>1=Start, 2=Load : via javascript
<li>4=Normal, 8=NoScript : via javascript (image in header)
<li>0x10=B-PageHide, 0x20=B-Unload, 0x40=B-BeforeUnload : via javascript (beacon), 0x80=B-VisChange
<li>0x100=Timer hits once every 10 seconds via ajax : via javascript
<li>0x200=BOT : via SiteClass
<li>0x400=Csstest : via .htaccess RewriteRule (tracker)
<li>0x800=isMe : via SiteClass
<li>0x1000=Proxy : via goto.php
<li>0x2000=GoAway (Unexpected Tracker) : via tracker
<li>0x8000=ADDED by the cron checktracker2.php
</ul>
<p>All of the items marked (via javascript) are events.<br>
The 'starttime' field is done by SiteClass (PHP) when the file is loaded.<br>
The 'botAs' field has the following values:</p>
<ul>
<li>'match', the User Agent info or the bots table info was used to determin that the client was a ROBOT.
<li>'robot', the robots.php file was called by a client looking at the robots.txt file.
<li>'sitemap', the sitemap.php file was called by a client looking at the Sitemap.xml file.
<li>'zero', the client is in the 'bots' table as a 0x100 (BOTS_CRON_ZERO) this causes the Database class to set 'js' field as 0x200 (TRACKER_BOT).
<li>'counted', the tracker.php or beacon.php files counted the client.
</ul>
<p>The above can be a comma seperated list like: 'robot,sitemap,counted'.<br>
If the Database class does not find that the client was a robot (and the client was not ME) it sets the 'isJavaScript' field in the database
as TRACKER_ZERO (0). Every 15 minutes a cron job, checktracker.php, looks at the tracker table to see if there are any TRACKER_ZEROs.
If there are it changes them to CHECKTRACKER ord with TRACKER_BOT (0x8000 | 0x200) which is 0x8200.<br>
Rows with 'js' with TRACKER_ZERO will be changed to 0x8200 after 15 minutes.
These rows are <b>curl</b> or something like <b>curl</b> (wget, lynx, etc) and counted as 'bots'.
These programs have no JavaScript interaction, no header image and no csstest interaction. They simply grab the
file and disect it. They don't try to get images or any css and they definetly don't use JavaScript.
</p>
</div>
$tracker
<h2 id="table8">From table <i>bots</i> for Today</h2>
<a href="#table9">Next</a>
<h4>Showing ALL <i>bots</i> for today</h4>
<div>The 'bots' field is hex.
<ul>
<li>The 'count' field is the total count since 'created'.
<li>From 'rotots.php': Robots.
<li>From 'Sitemap.php': Sitemap.
<li>From 'Database::checkIfBot(): BOT.
<li>From 'crontab' indicates a Zero in the 'tracker' table: Zero.<br>
This can be curl, wget, lynx and several others.
</ul>
$bots
<h2 id="table9">From table <i>bots2</i> for Today</h2>
$botsnext
<h4>Showing ALL <i>bots2</i> for today</h4>
<div>The 'which' filed    
<ul>
<li>'robots.txt': Robots
<li>'Sitemap.xml': Sitemap
<li>'Database::checkIfBot()': BOT
<li>'crontab': Zero<br>
This can be curl, wget, lynx and several others.
</ul>
The 'count' field is the number of hits today.</div>
$bots2
$mtbl
$geotbl
</tables>
<div id="analysis-info">
<hr>
$analysis
</div>
<hr>
</main>
</div>
$footer
EOF;
