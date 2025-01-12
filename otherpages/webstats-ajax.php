<?php
// Does all of the AJAX for webstats.js
// The main program is webstats.php
// BLP 2024-07-12 - Trying API from ip2location.io

// https://ipinfo.io/account/home for access key etc.
// https://ipinfo.io/developers for developer api information.

use ipinfo\ipinfo\IPinfo; // This package was loaded via 'composer ipinfo/ipinfo'

$_site = require_once(getenv("SITELOADNAME")); // This will get all of the classes including any others loaded by composer. It also gets helper-function.php

// Don't track or geo!

$_site->noTrack = $_site->noGeo = true;

// Turn an ip address into a long. This is for the country lookup
// BLP 2024-07-12 - If I am using the ip2location API this is not needed!

function Dot2LongIP($IPaddr) {
  if(strpos($IPaddr, ":") === false) {
    if($IPaddr == "") {
      return 0;
    } else {
      $ips = explode(".", "$IPaddr");
      return ($ips[3] + $ips[2] * 256 + $ips[1] * 256 * 256 + $ips[0] * 256 * 256 * 256);
    }
  } else {
    $int = inet_pton($IPaddr);
    $bits = 15;
    $ipv6long = 0;

    while($bits >= 0) {
      $bin = sprintf("%08b", (ord($int[$bits])));
      if($ipv6long){
        $ipv6long = $bin . $ipv6long;
      } else {
        $ipv6long = $bin;
      }
      $bits--;
    }

    $ipv6long = gmp_strval(gmp_init($ipv6long, 2), 10);
    return $ipv6long;
  }
}

// Ajax via list='json string of ips', like ["123.123.123.123", "..."', ...].
// Given a list of ip addresses get a list of countries as $ar[$ip] = $name of country.

if($list = $_POST['list']) {
  $S = new Database($_site);
 
  $list = json_decode($list); // turn json string backinto an array.

  $ar = array();

  /* BLP 2024-07-12 - This is the old code that uses the database.
   *  The new code (below) uses an API from ip2location to do the same thing.
  */
  /*--------------
  foreach($list as $ip) {
    $iplong = Dot2LongIP($ip);
    if(strpos($ip, ":") === false) {
      $table = "ipcountry";
    } else {
      $table = "ipcountry6";
    }
    $sql = "select countryLONG from $S->masterdb.$table ".
           "where '$iplong' between ipFROM and ipTO";

    $S->sql($sql);
    
    list($name) = $S->fetchrow('num');
    
    $ar[$ip] = $name;
  }
  
  $ret = json_encode($ar);
  echo $ret;
  exit();
  -----------------*/

  // If for some reason this stops working the code above can be uncommented and this code can be
  // commented OUT. Also, the two tables, ipcontry and ipcontry6, must be up to date. See the two
  // programs at /var/www/ upload.sh and upload6.sh.
  
  $key = require_once "/var/www/PASSWORDS/Ip2Location-key";
  
  foreach($list as $ip) {
    if(($json = file_get_contents("https://api.ip2location.io/?key=$key&ip=$ip")) === false) exit("ip2location failed");
    $info = json_decode($json, true);

    $ar[$ip] = "{$info['country_code']}<p class='country-name'>{$info['country_name']}<br>region: {$info['region_name']}<br>city: {$info['city_name']}</p>";
  }
  
  $ret = json_encode($ar);
  echo $ret;
  exit();
}

// Ajax via page=curl, proxy for curl http://ipinfo.io/<ip>

if($_POST['page'] == 'curl') {
  $ip = $_POST['ip'];

  $access_token = '41bd05979892b1';
  $client = new IPinfo($access_token);
  $ip_address = "$ip";
  $loc = $client->getDetails($ip_address);

  //$loc = json_decode(file_get_contents("https://ipinfo.io/$ip")); // this works too if I lose the
  //class ipinfo\ipinfo\IPinfo; 

  $locstr = "Hostname: $loc->hostname<br>$loc->city, $loc->region $loc->postal $loc->country<br>Location: <span class='location'>$loc->loc</span><br>ISP: $loc->org<br>Timezone: $loc->timezone<br>";

  echo $locstr;
  exit();
}

// Ajax via page=findbot. Search the bots table looking for all the records with ip

if($_POST['page'] == 'findbot') {
  $S = new Database($_site);
  
  $ip = $_POST['ip'];

  $human = [BOTS_ROBOTS=>"robots", BOTS_SITECLASS=>"BOT",
            BOTS_SITEMAP=>"sitemap", BOTS_CRON_ZERO=>"Zero"];

  $S->sql("select agent, site, robots, count, creation_time from $S->masterdb.bots where ip='$ip'");

  $ret = '';

  while(list($agent, $who, $robots, $count, $created) = $S->fetchrow('num')) {
    $h = '';
    
    foreach($human as $k=>$v) {
      $h .= $robots & $k ? "$v " : '';
    }

    $bot = sprintf("%X", $robots);
    $ret .= "<tr><td>$who</td><td>$agent</td><td>$h</td><td>$created</td><td>$count</td></tr>";
  }

  if(empty($ret)) {
    $ret = "<div style='background-color: pink; padding: 10px'>$ip Not In Bots</div>";
  } else {
    $ret = <<<EOF
<style>
#FindBot table {
  width: 100%;
}
#FindBot table td:first-child {
  width: 10rem;
}
#FindBot table td:nth-child(2) {
  word-break: break-all;
}
#FindBot table td:nth-child(3) {
  width: 5rem;
}
#FindBot table td:nth-child(4) {
  width: 7rem;
}
#FindBot table * {
  border: 1px solid black;
}
</style>
<table>
<thead>
  <tr><th>$ip</th><th>Agent</th><th>Human</th><th>Created</th><th>Count</th></tr>
</thead>
<tbody>
$ret
</tbody>
</table>
EOF;
  }
  echo $ret; 
  exit();
}

// AJAX via page=gettrackedr. site=thesite ($S->siteName)
// Get the info form the tracker table again.

if($_POST['page'] == 'gettracker') {
  $S = new Database($_site);
  $T = new dbTables($S);
  $site = $_POST['site'];
  $mask = $_POST['mask'];
  $thedate = $_POST['thedate'];
  
  $mask = (int)$mask; // This is passed as a string

  // Callback function for maketable()

  $me = json_decode(file_get_contents("https://bartonphillips.net/myfingerprints.json"));

  function callback1(&$row, &$desc) {
    global $S, $me, $mask;

    foreach($me as $key=>$val) {
      if($row['finger'] == $key) {
        $row['finger'] .= "<span class='ME' style='color: red'> : $val</span>";
      }
    }

    $ip = $S->escape($row['ip']);

    $row['ip'] = "<span class='co-ip'>$ip</span>";

    if($row['js'] != TRACKER_GOTO && $row['js'] != TRACKER_ME && ($row['js'] == TRACKER_ZERO || $row['js'] & TRACKER_BOT)) {
      $desc = preg_replace("~<tr>~", "<tr class='bots'>", $desc);
    }

    // Show in AJAX

    $x = (int)$row['js']; // $row['js'] is a string make it an int for test below.
    
    if(($x &~ $mask) != 0) {
      $row['js'] = "<span id='ajax'>" . dechex($row['js']) . "</span>";
    } else {
      $row['js'] = dechex($row['js']);
    }
    
    $t = $row['difftime'];
    if(is_null($t)) {
      return;
    }
    
    $hr = $t/3600;
    $min = ($t%3600)/60;
    $sec = ($t%3600)%60;
    
    $row['difftime'] = sprintf("%u:%02u:%02u", $hr, $min, $sec);
  } // End callback

   if(empty($thedate)) {
    $thedate = "current_date()";
  } else {
     $thedate = "'$thedate'";
  }
    
  $sql = "select ip, page, finger, agent, botAs, starttime, endtime, difftime, isJavaScript as js, id, browser ".
         "from $S->masterdb.tracker " .
         "where site='$site' and lasttime >=$thedate " .
         "order by lasttime desc";

  $tracker = $T->maketable($sql, array('callback'=>'callback1', 'attr'=>array('id'=>'tracker', 'border'=>'1')))[0];
  echo $tracker;
  exit();
}

if($_POST['page'] == "fingerToGps") {
  $S = new Database($_site);
  $finger = $_POST['finger'];
  $ip = $_POST['ip'];
  $site = $_POST['site'];

  if($S->sql("select lat, lon from $S->masterdb.geo where finger='$finger' and site='$site' and ip='$ip' order by lasttime")) {
    while([$lat, $lon] = $S->fetchrow('num')) {
      $ar[] = "$lat,$lon";
    }
    echo json_encode($ar);
    exit();
  }
  echo "NOT FOUND";
  exit();
}
