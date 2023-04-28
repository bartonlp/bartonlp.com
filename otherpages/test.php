<?php
$_site = require_once(getenv("SITELOADNAME"));
$S = new SiteClass($_site);
ErrorClass::setDevelopment(true);
require(SITECLASS_DIR .'/defines.php');

$S->siteName = $_GET['site'];
echo "Site=$S->siteName<br>";

//$mask = TRACKER_START | TRACKER_LOAD | TRACKER_TIMER | BEACON_VISIBILITYCHANGE | BEACON_PAGEHIDE | BEACON_UNLOAD | BEACON_BEFOREUNLOAD;
$mask1 = TRACKER_START | TRACKER_LOAD | TRACKER_TIMER | BEACON_VISIBILITYCHANGE | BEACON_PAGEHIDE | BEACON_UNLOAD | BEACON_BEFOREUNLOAD;

$meIp = null;

foreach($S->myIp as $v) { // myIp is always an IP.
  $meIp .= "'$v',";
}
$meIp .= "'" . DO_SERVER . "'"; // 157.245.129.4
$meIp = " and ip not in ($meIp)";

$real = 0;
$bots = 0;
$ajax = 0;
$countTot = 0;
$strAr = [];

for($i=0; $i<6; ++$i) {
  $dateStr = "date(lasttime)=current_date() - interval $i day";

  $cntBots = 0;
  $cntReal = 0;
  $cntNotAjax = 0;
  $cntAjax = 0;
  $str = '';
  $str2 = '';

  $hdr =<<<EOF
<table id='daycount' border='1'>
<thead>
<tr><th>Date</th><th>Count</th><th>Real</th><th>Bots</th><th>Ajax</th><th>Visitors</th><th>Visits</th></tr>
</thead>
<tbody>
EOF;
  
  $S->query("select difftime, date(lasttime), id, ip, isjavascript from $S->masterdb.tracker where site='$S->siteName' $meIp and $dateStr order by ip desc");

  while([$diff, $d, $id, $ip, $java] = $S->fetchrow('num')) {
    $dd = $d;
    if($java & $mask1) {
      ++$cntAjax;
      ++$ajax;
    }
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

$S->query("select date(starttime) ".
          "from $S->masterdb.tracker ".
          "where starttime>=current_date() - interval 5 day ".
          "and site='$S->siteName' and not isJavaScript & ". TRACKER_BOT . // 0x2000
          " and isJavaScript != 0 ". // TRACKER_ZERO
          "$meIp group by ip,date(starttime)"); // $meIp is ' and is not in($meIp)'

// There should be ONE UNIQUE ip per row. So count them into the date.

$Visitors = 0;
$visitorsAr = [];

while([$date] = $S->fetchrow('num')) {
  ++$visitorsAr[$date];
  ++$Visitors;
}

$i = 0;
$visitsTotal = 0;

$S->query("select date, visits from $S->masterdb.daycounts where site='$S->siteName' and date>=current_date() - interval 5 day order by date desc");
while([$date, $visits] = $S->fetchrow('num')) {
  //  echo "$date: visits=$visits, visitors={$visitorsAr[$date]}<br>";

  $strAr[$i++] .= "<td>$visitorsAr[$date]</td><td>$visits</td></tr>";
  $visitsTotal += $visits;
}

$str = implode("\n", $strAr);
$ftr =<<<EOF
</tbody>
<tfoot>
<tr><th>Totals</th><td>$countTotal</td><td>$real</td><td>$bots</td><td>$ajax</td><td>$Visitors</td><td>$visitsTotal</td></tr>
</tfoot>
</table>
EOF;

$tbl = $hdr . $str . $ftr;
echo $tbl;
  
$T = new dbTables($S);

function fix(&$row, &$desc) {
  if($row['count']) $row['count'] = $row['real'] + $row['bots'];
}

$sql = "select date, 'count', `real`, bots, visits from $S->masterdb.daycounts where site='$S->siteName' and date>=current_date() - interval 5 day ".
       "order by date desc";
$tbl = $T->maketable($sql, ['callback'=>'fix', 'attr'=>['border'=>'1']])[0];
echo "<br>$tbl";
