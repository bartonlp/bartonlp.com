<?php
// This now is in https://bartonlp.com/otherpages/webstats.php. I no
// longer use symlinks and the cumbersom rerouting logic is gone. Now webstats.php is called with
// ?blp=8653&site={siteDomain}. The GET grabs the site and puts it into $site. The post is called via
// the <select> and grabs the site a location header call which in turn does a new GET.
// This file still uses webstats.js and webstats-ajax.php.
// This uses setupjava.i.php to pass the variable needed to the javascript.
// setupjava.i.php loads defines.php which has all the defines.

// IMPORTANT: we force 'noGeo' true so we do not load it in SiteClass::getPageHead()
// We use map.js instead of geo.js

//$DEBUG = true;

// This function does a RAW mysqli insert (or what ever is in $sql) but it does not return anything.

function insertPdo($sql):void {
  global $_site;

  if($_site) {
    // Get the information from dbinfo: $envine, $database, $host, $user.

    extract((array)$_site->dbinfo);
  } else {
    $engine = "mysql";
    $database = $user = "barton";
    $host = "localhost";
  }

  // Get the password from our secret location. This is the same location for the server and my HP
  // and rpi.

  $p = require("/home/barton/database-password");

  $pdo = new PDO("$engine:dbname=$database; host=$host; user=$user; password=$p");
  $pdo->query($sql);
}
// Gather info in case of an error.

$xsite = $_SERVER['HTTP_HOST'];
$xagent = $_SERVER['HTTP_USER_AGENT'] ?? ''; // BLP 2022-01-28 -- CLI agent is NULL so make it blank ''
$xref = $_SERVER['HTTP_REFERER'];
$xip = $_SERVER['REMOTE_ADDR'];

// From form. If someone does a <select> below of a siteName it comes here. I then do a GET with the sitename.

if(isset($_POST['submit'])) {
  $site = $_POST['site']; // site is the siteDomain!
  header("location: webstats.php?blp=8653&site=$site");
  exit();
}

// Check for $_GET['site'] 

if($site = $_GET['site']) { // $_GET['site'] is the siteDomain from mysitemap.json
  $_site = require_once getenv("SITELOADNAME"); // Get the $_site for bartonlp.com/otherpages
  //$_site = require_once "/var/www/site-class/includes/autoload.php";
  
  // Now we need to add https:// if it is not already there.
  
  if(!str_contains($site, "https://")) $site = "https://$site";

  // BLP 2024-05-03 - see .htaccess for removal of RewriteRule.
  // Get the mysitemap.json from the siteDomain that called webstats.

  $s = json_decode(stripComments(file_get_contents("$site/mysitemap.json")));

  $_site->siteName = $s->siteName; // Get the siteName
  $_site->memberTable = $s->memberTable; // and memberTable
  
  $specialDate = $_GET['date'];

  $_site->noGeo = true; // Don't do geo.js
} else {
  // $_GET['site'] not set. NO $site
  
  error_log("webstats.php \$site empty: sql=$sql, ref=$xref");

  // We do not have $S so we can't add this to the badplayer table.

  insertPdo("insert into barton.badplayer (ip, site, page, botAs, count, type, errno, errmsg, agent, created, lasttime) ".
            "values('$xip', '$xsite', 'webstats', 'counted', 1, 'NO_SITE', -200, 'NO site', '$xagent', now(), now()) ".
            "on duplicate key update count=count+1, lasttime=now()");
  
  $errmsg = "(site empty)";
  $error = true;
}

// Check for magic 'blp'. If not found check if one of my recent ips. If not justs 'Go Away'
// The magic comes only from adminsites.php or aboutwebsite.php

if($_GET['blp'] != '8653') {
  error_log("webstats.php ERROR_NOT_IN_MYIP: ip=$xip, site=$xsite, page=webstat, blp={$_GET['blp']}"); // BLP 2023-11-11 - 

  insertPdo("insert into barton.badplayer (ip, site, page, botAs, count, type, errno, errmsg, agent, created, lasttime) ".
            "values('$xip', '$xsite', 'webstats', 'counted', 1, 'ERROR_BLP', -300, 'sql=$sql', '$xagent', now(), now()) ".
            "on duplicate key update count=count+1, lasttime=now()");
    
  $errmsg .= "(secret error)"; 
  $error = true;
} 

if($error) {
  echo "<h1>This Page is Restricted $errmsg;</h1>";
  exit();
}

if($DEBUG) $hrStart = hrtime(true);

// Wrap this in a try to see if the constructor fails

try {
  ErrorClass::setDevelopment(true);
  
  $S = new SiteClass($_site);

  // BLP 2023-10-18 - This require sets up the constants needed by webstats.js.

  require_once("/var/www/bartonlp.com/otherpages/setupjava.i.php");

  // NOTE: $S->h_inlineScript has already been set in setupjava.i.php
  // so this must be an addition ".=".

  $S->h_inlineScript .= <<<EOF

var thedate = "$specialDate";
EOF;
} catch(Exception $e) {
  $errno = $e->getCode();
  $errmsg = $e->getMessage();
  $sql = dbMySqli::$lastQuery;
  error_log("webstat.php constructor FAILED: ip=$xip, site=$xsite, site=$site, page=webstats, sql=$sql, ref=$xref, errno=$errno, errmsg=$errmsg");

  // We do not have $S so we can't add this to the badplayer table.

  $sql = substr($sql, 0, 254); // Truncate just in case.

  // We do not have a $S so use the database name here and the x* items.
  
  insertPdo("insert into barton.badplayer (ip, site, page, botAs, count, type, errno, errmsg, agent, created, lasttime) ".
            "values('$xip', '$xsite', 'webstats', 'counted', 1, 'CONSTRUCTOR_ERROR', -200, 'sql=$sql', '$xagent', now(), now()) ".
            "on duplicate key update count=count+1, lasttime=now()");
  
  echo "<h1><i>This Page is Restricted (constructor FAILED).</i></h1>"; // These are all different so I can find them.
  exit();
}

// Now we finally have $S from SiteClass so we can check if this is a bot.
// At this point I know that blp==8653

if($S->isBot) {
  error_log("webstats.php BOT_RESTRICTED: ip=$xip,site=$xsite, page=webstat, blp={$_GET['blp']}, foundBotAs=$S->foundBotAs, line=" . __LINE__);
  echo "<h1>This Page is Restricted (isBot)</h1>"; // These are all different so I can find them.
  exit();  
}

// BLP 2025-03-06 - newtblsort.css has been modified to not screw up my header.

$S->link = <<<EOF
  <link rel="stylesheet" href="https://bartonphillips.net/css/newtblsort.css">
  <link rel="stylesheet" href="./css/webstats.css"> 
EOF;

// BLP 2025-03-06 - this is the most current tablesorter 2.32.0

$S->h_script = <<<EOF
<script src="https://bartonphillips.net/tablesorter-master/dist/js/jquery.tablesorter.min.js"></script>
EOF;

// IMPORTANT NOTE: BLP 2024-04-28 - 
//   We have restricted the google maps key to our websites. The information on the credentials
//   page is WRONG! You can NOT do '*.example.com". It doesn't seem to work. I have the base URL
//   and then the CNAME www.
//   The gooble maps page is: https://console.cloud.google.com/apis/credentials?project=barton-1324

$S->b_script = <<<EOF
<script src='https://bartonlp.com/otherpages/js/webstats.js'></script>
<script src="https://bartonphillips.net/js/maps.js"></script>
<!-- The gooble maps key is restricted to my websites. Therefore this is not a leaked secret -->  
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA6GtUwyWp3wnFH1iNkvdO9EO6ClRr_pWo&loading=async&callback=initMap&v=weekly" async></script>
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
  <div class="scrolling">
  $tbl
  </div>
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
} else {
  $tbl =<<<EOF
<div class="scrolling">
$tbl
</div>
EOF;
}

if($S->reset) {
  $reset = " <span style='font-size: 16px;'>(Reset Date: $S->reset)</span>";
}
    
$page .= <<<EOF
<h2 id="table4">From table <i>counter</i> for today</h2>
<a href="#table6">Next</a>
<h4>Showing $S->siteName grand TOTAL hits since last reset $reset for pages viewed today</h4>
<p>'real' is the number of non-bots and 'bots' is the number of robots.</p>
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

/**** Start make daycount. */

$S->sql("select date(lasttime) ".
        "from $S->masterdb.tracker ".
        "where starttime>=current_date() - interval 7 day ".
        "and site='$S->siteName' and not isJavaScript & ". TRACKER_BOT . // 0x200
        " and isJavaScript != ".  TRACKER_ZERO . // 0
        " and ip not in($meIp) group by ip,date(lasttime)");

// There should be ONE UNIQUE ip per row. So count them into the date.

$Visitors = 0;
$visitorsAr = [];
$i=0;

while([$date] = $S->fetchrow('num')) {
  ++$visitorsAr[$date];
  ++$Visitors;
}

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

  $visitor = $visitorsAr[$dd] ?? 0;

  $count = $cntBots + $cntReal;
  $countTotal += $count;
  $strAr[] = "<tr><td>$dd</td><td>$count</td><td>$cntReal</td><td>$cntBots</td><td>$cntAjax</td><td>$visitor</td></tr>";
}

$str = implode("\n", $strAr); // Turn the array into a string seperated by cr. This is the body of daycount.

$hdr =<<<EOF
<table id='daycount' border='1'>
<thead>
<tr><th>Date</th><th>COUNT</th><th>REAL</th><th>BOTS</th><th>AJAX</th><th>VISITORS</th></tr>
</thead>
<tbody>
EOF;

$ftr =<<<EOF
</tbody>
<tfoot>
<tr><th>Totals</th><td>$countTotal</td><td>$real</td><td>$bots</td><td>$ajax</td><td>$Visitors</td></tr>
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
<h2 id="table6">From table <i>tracker</i> for seven days</h2>
<a href="#table7">Next</a>

<h4>Showing $S->siteName for seven days</h4>
<p>Webmaster (me) is <span class="red">NEVER</span> counted.</p>
<ul>
<li><b>COUNT</b> is the sum of <b>REAL</b> and <b>BOTS</b>, the total number of accesses (HITS).
<li><b>REAL</b> is the number of accesses that actually spent some time on our site (\$diff not empty).
<li><b>BOTS</b> is the number of robots.
<li><b>AJAX</b> is the number of accesses that came via <i>JavaScript</i> (via the 'tracker' table). This does not count <b>BOTS</b>.
<li><b>VISITORS</b> is the number of distinct IP addresses that are not <b>BOTS</b> (via the 'tracker' table <b>AJAX</b>).
</ul>
<p><b>AJAX</b> counts only non <b>BOTS</b> accesses (POST) from <i>JavaScript</i>. <b>Visitors</b>
 is the numbere of unique IP addresses that are not <b>BOTS</b>.</p>
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
    <option value="https://bartonlp.org">BartonlpOrg</option>
    <option value="https://bartonphillips.com">Bartonphillips</option>
    <option value="https://newbern-nc.info">Tysonweb</option>
    <option value="https://newbernzig.com">Newbernzig</option>
    <option value="https://swam.us">Swam</option>
    <option value="https://bonnieburch.com">Bonnieburch</option>
    <option value="https://bonnieburch.com/marathon">Marathon</option>
    <option value="https://bartonphillips.net">Bartonphillipsnet</option>
    <option value="https://jt-lawnservice.com">JT-Lawnservice</option>
  </select>

  <button type="submit" name='submit'>Submit</button>
</form>
EOF;

// BLP 2021-06-23 -- Only bartonphillips.com has a members table.

if($S->siteName == "Bartonphillips") {
  $sql = "select name, email, ip, finger, count, created, lasttime from bartonphillips.members";

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
<div id="memberstable" class="scrolling">
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
   <li><a href="#table6">Goto daycount Info</a></li>
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
<div>The header column <b>js</b> is hex. <span class="red">Red</span> indicates that items were via <i>JavaScript</i>.
<ul>
<li>1=<b>Start</b>, 2=<b>Load</b> : via <i>JavaScript</i>
<li>4=<b>Normal</b> : <i>JavaScript</i> puts the image into the &lt;header&gt; which causes a GET of the image.
<li>8=<b>NoScript</b> : the user or browser has restricted the use of <i>JavaScript</i>. The &lt;noscript&gt; tag has an image that triggers tracker.php
<li>0x10=<b>PageHide</b>, 0x20=<b>Unload</b>, 0x40=<b>BeforeUnload</b> : 0x80=<b>VisChange</b> : via <i>JavaScript</i> (beacon.php)
<li>0x100=<b>Timer</b> hits once every 10 seconds via ajax : via <i>JavaScript</i>
<li>0x200=<b>BOT</b> : <i>via SiteClass</i>
<li>0x400=<b>Csstest</b> : <i>via .htaccess RewriteRule</i>
<li>0x800=<b>isMe</b> : <i>via SiteClass</i>
<li>0x1000=<b>Proxy</b> : <i>via goto.php</i>
<li>0x2000=<b>GoAway</b> : <i>via tracker.php</i> (Unexpected event)
<li>0x8000=<b>ADDED</b> : <i>via CRON</i> (checktracker2.php. Always with <b>BOT</b>)
<li>0x10000=<b>ROBOT</b> : <i>via robots.php</i>
<li>0x20000=<b>SITEMAP</b> : <i>via sitemap.php</i>
</ul>
<p>All of the items marked (via <i>JavaScript</i>) are events.<br>
The 'starttime' field is done via PHP when the file is loaded.<br>
The 'botAs' field has the following values:</p>
<ul>
<li><b>match</b>: the User Agent info or the bots table info was used to determin that the client was a ROBOT.
<li><b>good-bot</b>: the User Agent listed a web page where one can go to for information.
<li><b>robot</b>: the robots.php file was called by a client looking at the robots.txt file.
<li><b>sitemap</b>: the sitemap.php file was called by a client looking at the Sitemap.xml file.
<li><b>zero</b>: the client is in the 'bots' table as a 0x100 (BOTS_CRON_ZERO) this causes the Database class to set the <b>js</b>
field as 0x200 (TRACKER_BOT).
<li><b>counted</b>: the tracker.php or beacon.php files counted the client.
</ul>
<p>The above can be a comma seperated list like: 'robot,sitemap,counted'.<br>
If the Database class does not find that the client was a robot (and the client was not ME) it sets the 'isJavaScript' field in the database
as TRACKER_ZERO (0). Every 15 minutes a cron job, checktracker.php, looks at the tracker table to see if there are any TRACKER_ZEROs.
If there are it changes them to CHECKTRACKER ored with TRACKER_BOT (0x8000 | 0x200).<br>
Header column <b>js</b> with TRACKER_ZERO will be changed to 0x8200 after 15 minutes.
These are show as <b>ADDED</b> items in the <b>js</b> columb and are <b>curl</b> or something like <b>curl</b> (wget, lynx, etc)
and counted as a <b>BOT</b>.
Such items have no header image or csstest interaction. They simply grab the
file and disect it. They don't try to get images or any css and they definetly don't use <i>JavaScript</i>.
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
