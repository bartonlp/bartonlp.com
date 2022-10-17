<?php
// BLP 2022-05-01 - Major rework. This now is in https://bartonphillips.net/webstats.php. I no
// longer use symlinks and the cumbersom rerouting logic is gone. Now webstats.php is called with
// ?blp=8653&site={sitename}. The GET grabs the site and puts it into $site. The post is called via
// the <select> and grabs the site a location header call which in turn does a new GET.
// Once the site is setup by the GET we get $_site and set $_site->siteName to $site.
// This file still uses webstats.js and webstats-ajax.php.

// IMPORTANT: mysitemap.json sets 'noGeo' true so we do not load it in SiteClass::getPageHead()
// We use map.js instead of geo.js

//$DEBUG = true;

$_site = require_once(getenv("SITELOADNAME"));

// This function does a RAW mysqli insert (or what ever is in $sql) but it does not return anything.

function insertMysqli($sql):void {
  global $_site;
  
  $i = $_site->dbinfo;
  $p = require("/home/barton/database-password");
  $mysqli = new mysqli($i->host, $i->user, $p, 'barton');

  $mysqli->query($sql);
}

if($_GET['site']) {
  $site = $_GET['site'];
}

if(isset($_POST['submit'])) {
  $site = $_POST['site'];
  header("location: webstats.php?blp=8653&site=$site");
  exit();
}

// Now set siteName to $site from the GET.

$xsite = $_site->siteName;
$_site->siteName = $site;

$xagent = $_SERVER['HTTP_USER_AGENT'] ?? ''; // BLP 2022-01-28 -- CLI agent is NULL so make it blank ''
$xref = $_SERVER['HTTP_REFERER'];
$xip = $_SERVER['REMOTE_ADDR'];

if(empty($site)) {
  error_log("webstats.php ERROR: $xip, $_site->siteName, site=NONE, ref=$xref, agent=$xagent");

  // We do not have $S so we can't add this to the badplayer table.

  insertMysqli("insert into badplayer (ip, site, page, botAs, count, type, errno, errmsg, agent, created, lasttime) ".
               "values('$xip', '$xsite', 'webstats', 'counted', 1, 'NO_SITE', -200, 'NO site', '$xagent', now(), now()) ".
               "on duplicate key update count=count+1, lasttime=now()");
  
  echo <<<EOF
<h1>GO AWAY</h1>
EOF;
  exit();
}

if($DEBUG) $hrStart = hrtime(true);

require_once(SITECLASS_DIR . '/defines.php');

// Wrap this in a try to see if the constructor fails

try {
  $S = new $_site->className($_site);
} catch(Exception $e) {
  $errno = $e->getCode();
  $errmsg = $e->getMessage();
  $sql = dbMySqli::$lastQuery;
  error_log("webstat.php ERROR: $xip, $xsite, site=$site, sql=$sql, ref=$xref, errno=$errno, errmsg=$errmsg, agent=$xagent");

  // We do not have $S so we can't add this to the badplayer table.

  $sql = substr($sql, 0, 254); // Truncate just in case.
  
  insertMysqli("insert into badplayer (ip, site, page, botAs, count, type, errno, errmsg, agent, created, lasttime) ".
               "values('$xip', '$xsite', 'webstats', 'counted', 1, 'CONSTRUCTOR_ERROR', -200, 'sql=$sql', '$xagent', now(), now()) ".
               "on duplicate key update count=count+1, lasttime=now()");
  
  echo "<h1>Go Away</h1>";
  exit();
}

// Check for magic 'blp'. If not found check if one of my recent ips. If not justs 'Go Away'

if(empty($_GET['blp']) || $_GET['blp'] != '8653') { // If blp is empty or set but not '8653' then check $S->myIp
  // BLP 2021-12-20 -- $S->myIp is always an array from SiteClass.
  
  if(!array_intersect([$S->ip], $S->myIp)) {
    echo "<h1>Go Away</h1>";
    exit();
  }
} 

// At this point I know that blp was not empty and it equaled 8653.
// But is it is not me who is it?

if(!array_intersect([$S->ip], $S->myIp)) {
  error_log("webstats.php $S->siteName $S->self: blp=8653 but this is not me. IP=$S->ip, agent=$S->agent, line=" . __LINE__);
}

if($S->isBot) {
  error_log("webstats.php $S->siteName $S->self Bot Restricted, exit: $S->foundBotAs, IP=$S->ip, agent=$S->agent, line=" . __LINE__);
  echo <<< EOF
<h1>This Page is Restricted</h1>
EOF;
  exit();  
}

$h->link = <<<EOF
  <link rel="stylesheet" href="https://bartonphillips.net/css/newtblsort.css">
  <link rel="stylesheet" href="https://bartonphillips.net/css/webstats.css"> 
EOF;

// css for the gps location in ipinfo

$h->css = <<<EOF
.location, #tracker td:nth-of-type(3) { cursor: pointer; }
EOF;

// **********************
// START inlineScript
// Set up the JavaScript

$myIp = implode(",", $S->myIp); // BLP 2022-07-18 - $S->myIp is ALWAYS an array!

$homeIp = gethostbyname("bartonphillips.dyndns.org");

$mask = TRACKER_BOT | TRACKER_SCRIPT | TRACKER_NORMAL | TRACKER_NOSCRIPT | TRACKER_CSS | TRACKER_ME | TRACKER_GOTO | TRACKER_GOAWAY;

// Set up the javascript variables it needs from PHP

function setupjava($h) {
  global $myIp, $homeIp, $mask; //, $S;
  
  $robots = BOTS_ROBOTS;
  $sitemap = BOTS_SITEMAP;
  $siteclass = BOTS_SITECLASS;
  $zero = BOTS_CRON_ZERO;

  $h->inlineScript = <<<EOF
    var myIp = "$myIp"; 
    var homeIp = "$homeIp"; // my home ip
    //var doState = true; // This can be set to show the State info. See tracker.js and tracker.php.
    const robots = {"$robots": "Robots", "$siteclass": "BOT", "$sitemap": "Sitemap", "$zero": "Zero"};
  EOF;

  $start = TRACKER_START;
  $load = TRACKER_LOAD;
  $script = TRACKER_SCRIPT;
  $normal = TRACKER_NORMAL;
  $noscript = TRACKER_NOSCRIPT;
  $bvisibilitychange = BEACON_VISIBILITYCHANGE;
  $bpagehide = BEACON_PAGEHIDE;
  $bunload = BEACON_UNLOAD;
  $bbeforeunload = BEACON_BEFOREUNLOAD;
  $tbeforeunload = TRACKER_BEFOREUNLOAD;
  $tunload = TRACKER_UNLOAD;
  $tpagehide = TRACKER_PAGEHIDE;
  $tvisibilitychange = TRACKER_VISIBILITYCHANGE;
  $timer = TRACKER_TIMER;
  $bot = TRACKER_BOT;
  $css = TRACKER_CSS;
  $me = TRACKER_ME;
  $goto = TRACKER_GOTO; // Proxy
  $goaway = TRACKER_GOAWAY; // unusal tracker.

  $h->inlineScript .= <<<EOF
    const tracker = {
  "$start": "Start", "$load": "Load", "$script": "Script", "$normal": "Normal",
  "$noscript": "NoScript", "$bvisibilitychange": "B-VisChange", "$bpagehide": "B-PageHide", "$bunload": "B-Unload", "$bbeforeunload": "B-BeforeUnload",
  "$tbeforeunload": "T-BeforeUnload", "$tunload": "T-Unload", "$tpagehide": "T-PageHide", "$tvisibilitychange": "T-VisChange",
  "$timer": "Timer", "$bot": "BOT", "$css": "Csstest", "$me": "isMe", "$goto": "Proxy", "$goaway": "GoAway"
  };
    const mask = $mask;
    //var thepage = '$S->self', theip = '$S->ip', thesite = '$S->siteName';
  EOF;
}

setupjava($h);  

// FINISH inlineScript
// *******************

$h->script = <<<EOF
<script src="https://bartonphillips.net/tablesorter-master/dist/js/jquery.tablesorter.min.js"></script>
EOF;

$T = new dbTables($S); // My table class

// Only these sights use maps.js. 

if(array_intersect([$S->siteName], ['Bartonphillips', 'Tysonweb', 'Newbernzig', 'BartonlpOrg', 'BartonphillipsOrg',
  'Allnatural', 'bartonhome', 'Bonnieburch', 'Bridgeclub', 'Marathon', 'Swam', 'Rpi', 'Bartonphillipsnet'])[0]) {
  // For these add maps.js and the maps api and key.

  $b->script = <<<EOF
<script src="https://bartonphillips.net/js/maps.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA6GtUwyWp3wnFH1iNkvdO9EO6ClRr_pWo&callback=initMap&v=weekly" async></script>
<script src='https://bartonlp.com/otherpages/js/webstats.js'></script>
EOF;
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
} else {
  $botsnext = "<a href='#analysis-info'>Next</a>";
}

if($DEBUG) {
  $b->inlineScript = <<<EOF
try {
  // Create the performance observer.
  const po = new PerformanceObserver((list) => {
    //console.log("list: ", list);
  
    for(const entry of list.getEntries()) {
      // Logs all server timing data for this response
      //console.log("entry: ", entry);
      let date = entry.serverTiming[0];
      let time = entry.serverTiming[1];
      console.log('Server Timing: date='+ date.description + ', time=' + time.duration / 1e6);
    }
  });
  // Start listening for navigation entries to be dispatched.
  
  po.observe({type: 'navigation', buffered: true});
} catch (e) {
  // Do nothing if the browser doesn't support this API.
  console.log("ERROR: ", e);
}
try {
  const po1 = new PerformanceObserver(list => {
    for(const entry of list.getEntries()) {
      console.log("Name: " + entry.name + `
  Type: `
                  + entry.entryType +
                  ", Start: " + entry.startTime +
                  ", Duration: " + entry.duration
                 );
    }
  });
  po1.observe({type: 'resource', buffered: true});
} catch(e) {}
EOF;
}
// END $DEBUG

$h->title = "Web Statistics";

$h->banner = "<h1>Web Stats For <b>$S->siteName</b></h1>";

[$top, $footer] = $S->getPageTopBottom($h, $b);

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
  
if($S->siteName == 'Tysonweb') {
  $g = glob("*.php");

  $del = ['analysis.php', 'phpinfo.php', 'robots.php', 'sitemap.php']; 
  $S->query($sql);

  while([$filename, $count, $bots, $lasttime] = $S->fetchrow('num')) {
    $ar[trim($filename, '/')] = [$count, $bots, $lasttime];
  }

  foreach($g as $name) {
    if(array_intersect([$name], $del)) {
      continue;
    }
    $a = $ar[$name];
    $tbl .= "<tr><td>$name</td><td>$a[0]</td><td>$a[1]</td><td>$a[2]</td></tr>";
  }

  $tbl .= <<<EOF
<tbody>
</table>
EOF;
} else {
  $tbl = $T->maketable($sql, array('attr'=>array('border'=>'1', 'id'=>'counter')))[0];
}

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

$today = date("Y-m-d");

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

// Get the footer line for daycounts.
  
$sql = "select sum(`real`+bots) as Count, sum(`real`) as 'Real', sum(bots) as 'Bots', ".
"sum(visits) as Visits " .
"from $S->masterdb.daycounts ".
"where site='$S->siteName' and date >= current_date() - interval 6 day";

$S->query($sql);
[$Count, $Real, $Bots, $Visits] = $S->fetchrow('num');

// Use 'tracker' to get the number of Visitors ie unique ip accesses.

$meIp = null;

foreach($S->myIp as $v) { // myIp is always an IP.
  $meIp .= "'$v',";
}
$meIp .= "'" . DO_SERVER . "'"; // 157.245.129.4
$meIp = " and ip not in ($meIp)";

// Get all of the dates from tracker grouped by ip and date(lasttime).

$S->query("select date(starttime) ".
          "from $S->masterdb.tracker where starttime>=current_date() - interval 6 day ".
          "and site='$S->siteName' and not isJavaScript & ". TRACKER_BOT .
          " and isJavaScript != 0 ". // TRACKER_ZERO
          "$meIp group by ip,date(starttime)"); // $meIp is ' and is not in($meIp)'

// There should be ONE UNIQUE ip per row. So count them into the date.

$Visitors = 0;
$visitorsAr = [];

while([$date] = $S->fetchrow('num')) {
  ++$visitorsAr[$date];
  ++$Visitors;
}

// I am looking for the number of 'AJAX'. The mask will be zero if these are the only things in
// isJavaScript or isJavaScript == 0.
// Looking for isJavaScript that does not have the bots, script, normal, noscript and csstest bits
// set.

// What is left is 'start', 'load', beacon exits, tracker exits, and timer.

$sql = "select date(starttime)".
       "from $S->masterdb.tracker ".
       "where starttime>=current_date() - interval 6 day and site='$S->siteName' ".
       "and (isJavaScript&~$mask)!=0". // Not just the above bits.
       "$meIp"; // $meIp is ' and is not in($meIp)'
  
$S->query($sql);

$jsvalue = 0;
$jsEnabled = [];

// For each date that has some AJAX info in isJavaScript add 1 to the $jsEnabled[$date] and add
// 1 to $jsvalue1 (the accumulator).

while([$date] = $S->fetchrow('num')) {
  ++$jsEnabled[$date]; // total per date
  ++$jsvalue; // grand total
}

// Count, Real, Bots, Visits are from select for the footer. Visitors is from the
// select for ip & date which is made into Visitors.
// jsenabled is from the select with the mask.

$ftr = "<tr><th>Totals</th><th>$Visitors</th><th>$Count</th><th>$Real</th>".
"<th>$jsvalue</th><th>$Bots</th><th>$Visits</th></tr>";

// Get the table lines
  
$sql = "select `date` as Date, 'Visitors', `real`+bots as Count, `real` as 'Real', 'AJAX', ".
"bots as 'Bots', visits as Visits ".
"from $S->masterdb.daycounts where site='$S->siteName' and ".
"date >= current_date() - interval 6 day order by date desc";

// callback for maketable daycounts.

function visit(&$row, &$rowdesc) { // callback from maketable()
  global $visitorsAr, $jsEnabled;
  $row['Visitors'] = $visitorsAr[$row['Date']];
  $row['AJAX'] = $jsEnabled[$row['Date']];
}

// $tbl is the full table with header and footer. The return is an array 0-3 and 'table', 'result',
// 'num' and 'header'. We only need the zero element which is table. We could have done ['table']
// just as well.

$tbl = $T->maketable($sql, array('callback'=>'visit', 'footer'=>$ftr, 'attr'=>array('border'=>"1", 'id'=>"daycount")))[0];

if(!$tbl) {
  $tbl = "<h3 class='noNewData'>No New Data Today</h2>";
}

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
<li>'Visitors' is the number of distinct (NON Bot) IP addresses (via 'tracker' table).
<li>'Count' is the sum of 'Real' and 'Bots', the total number of HITS.
<li>'Real' is the number of non-robots.
<li>'AJAX' is the number of accesses with AJAX via tracker.js (from the 'tracker' table).
<li>'Bots' is the number of robots.
<li>'Visits' are the number of non-robots outside of a 10 minutes window.
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
  </select>

  <button type="submit" name='submit'>Submit</button>
</form>
EOF;

// BLP 2021-10-08 -- add geo

$today = date("Y-m-d");

$me = json_decode(file_get_contents("https://bartonphillips.net/myfingerprints.json"));

function mygeofixup(&$row, &$rowdesc) {
  global $today, $me;
  
  foreach($me as $key=>$val) {
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

// Render page

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
<li>4=Script, 8=Normal, 0x10=NoScript : via javascript (image in header)
<li>0x20=B-PageHide, 0x40=B-Unload, 0x80=B-BeforeUnload : via javascript (beacon)
<li>0x100=T-BeforeUnload, 0x200=T-Unload, 0x400=T-PageHide, 0x800=T-VisChange : via javascript (tracker)
<li>0x1000=Timer hits once every 10 seconds via ajax : via javascript
<li>0x2000=BOT : via SiteClass
<li>0x4000=Csstest : via .htaccess RewriteRule (tracker)
<li>0x8000=isMe : via SiteClass
<li>0x10000=Proxy : via goto.php
<li>0x20000=GoAway (Unexpected Tracker) : via tracker
<li>0x40000=B-VisChange : 'visablilitychange', via javascript<br>
This happens when the user moves to another tab or closes the site.
</ul>
<p>All of the items marked (via javascript) are events.<br>
The 'starttime' is done by SiteClass (PHP) when the file is loaded.<br>
Rows with 'js' zero (0) are <b>curl</b> or something like <b>curl</b> (wget, lynx, etc) and counted as 'bots'</b>.
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
