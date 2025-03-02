<?php
// Log CSP violation reports
$ip = $_SERVER['REMOTE_ADDR'];
$host = $_SERVER['HTTP_HOST'];
$page = $_SERVER['PHP_SELF'];
$agent = $_SERVER['HTTP_USER_AGENT'];

// Check for authorization
$exists = class_exists("Database");

// Check if the request contains JSON data (CSP report)
$input = file_get_contents("php://input");

if (empty($input)) {
  error("cspreport.php: No input received, ip=$ip");
  if ($exists === false) {
    header("location: https://bartonlp.com/otherpages/NotAuthorized.php?ip=$ip&host=$host&page=$page&agent=$agent");
    exit();
  }

  die("cspreport.php: No input received.");
}

// Verify JSON data
$data = json_decode($input);

if ($data === null) {
    error("cspreport.php: Invalid JSON received from $ip");
    die("cspreport.php: Invalid JSON received.");
}

// Check if the request is a CSP report by inspecting keys
if (!property_exists($data, 'csp-report')) {
    error("cspreport.php: Received non-CSP report data from $ip");
    die("cspreport.php: Not a valid CSP report.");
}
$date = date("Y-m-d H:i:s");

// Log the CSP violation
error("$date: ip=$ip, site=$host, page=$page, agent=$agent " . print_r($data, true));

echo "CSP report received.\n";

function error($msg) {
  if(file_put_contents("csp-errors.log", $msg, FILE_APPEND) === false) {
    error_log("cspreport: put contents " . print_r(error_get_last(), true));
  }
}
