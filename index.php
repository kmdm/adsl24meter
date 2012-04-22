<?php
ob_start('ob_gzhandler');

require_once("securesession.class.php");
require_once("adsl24.class.php");

function weekdays_in_month() 
{
    $so_far = 0; $total = 0;
    $d = cal_days_in_month(CAL_GREGORIAN,intval(date('m')),intval(date('Y')));
    for($i=0; $i <= $d; $i++) {
        if(intval(date('N', strtotime(date(sprintf('Y-m-%02d', $i))))) < 6) {
            $total++;

            if(date(sprintf('Y-m-%02d', $i)) <= date('Y-m-d')) {
                $so_far++;
            }
        }
    }

    return array($so_far, $total); 
}

function generate_report_html($data)
{
    extract($data);
    $html = "";

    if($bw_credit >= 0) {
        $html .= "<h2>Good News!</h2>\n";
        $html .= "<p>You are in credit by ".number_format($bw_credit, 3)."GB ";
        $html .= "when compared to the amount of bandwidth you could have ";
        $html .= "used at this point in the month!</p>\n";
    
    } else {
        $html .= "<h2>Be Careful!</h2>\n";
        $html .= "<p>You have used too much bandwidth so far this month (";
        $html .= number_format($bw_used, 3)."GB) when compared to the amount ";
        $html .= "you should have used as per the estimated schedule.</p>\n";
        $html .= "<p>Reduce your bandwidth usage or you could exceed ";
        $html .= "your cap!</p>\n";
    }

    $html .= "<table id=\"bw_breakdown\">\n";
    $html .= "<thead>";
    $html .= "<tr><th colspan=\"2\">Bandwidth Breakdown</th></tr>";
    $html .= "</thead>";
    $html .= "<tbody>";
    $html .= "<tr><th>Bandwidth limit</th><td>${bw_total}GB</td></tr>\n";
    $html .= "<tr><th>Weekdays in month</th><td>$wd_total days</td></tr>\n";
    $html .= "<tr><th>Weekdays passed</th><td>$wd_so_far days</td></tr>\n";
    $html .= "<tr><th>Scheduled bandwidth per weekday</th>";
    $html .= "<td>".number_format($bw_per_day, 3)."GB</td></tr>\n";
    $html .= "<tr><th>Estimated scheduled bandwidth (to date)</th>";
    $html .= "<td>".number_format($bw_scheduled, 3)."GB</td></tr>\n";
    $html .= "<tr><th>Bandwidth used</th>";
    $html .= "<td>".number_format($bw_used, 3)."GB</td></tr>\n";
    
    if($bw_credit >= 0) {
        $html .= "<tr><th>Bandwidth credit</th>";
        $html .= "<td>".number_format($bw_credit, 3)."GB</td></tr>\n";
    } else {
        $html .= "<tr><th>Bandwidth deficit</th>";
        $html .= "<td>".number_format(-$bw_credit, 3)."GB</td></tr>\n";
    }
    
    $html .= "</tbody></table>\n";
    
    $html .= "<p><a href=\"?action=logout\">[Logout]</p>\n";

    return $html;
}

function ajax_not_logged_in()
{
    header("HTTP/1.1 403 Forbidden");
    exit;
}

function ajax_error($msg)
{
    header("HTTP/1.1 500 Internal Server Error");
    echo $msg;
    exit;
}

function ajax_usage() 
{
    if(!isset($_SESSION['adsl24cookie']))
        ajax_not_logged_in();

    try {
        
        if(isset($_SESSION['last_req_ts']) && isset($_SESSION['last_req'])) {
            if(time() - $_SESSION['last_req_ts'] <= 600) {
                $stats = unserialize($_SESSION['last_req']);
            }
        }

        if(!isset($stats)) {
            $a = new ADSL24Client($_SESSION['adsl24cookie']);
            $stats = $a->usage();
            $_SESSION['last_req_ts'] = time();
            $_SESSION['last_req'] = serialize($stats);
        }

        $total = array_sum($stats);
        list($wd_so_far, $wd_total) = weekdays_in_month();
        $bw_per_day = $total / $wd_total;
        $scheduled = $wd_so_far * $bw_per_day;
        $credit = $scheduled - $stats['used'];

        $data = array(
            'bw_total'=>$total,
            'bw_per_day'=>$bw_per_day,
            'wd_total'=>$wd_total,
            'wd_so_far'=>$wd_so_far,
            'bw_used'=>$stats['used'],
            'bw_remaining'=>$stats['remaining'],
            'bw_scheduled'=>$scheduled,
            'bw_credit'=>$credit
        );
        
        $data['bw_report'] = generate_report_html($data);

        header("Content-type: text/json");
        echo json_encode($data);
        exit;
    } catch(ADSL24ClientLoginException $e) {
        ajax_not_logged_in();
    } catch(ADSL24ClientException $e) {
        ajax_error_loading_stats($e->getMessage());
    }
}

register_shutdown_function('session_write_close');
session_start();

/* Handle requests. */
if(isset($_REQUEST['action'])) switch($_REQUEST['action']) {
    case 'usage':
        ajax_usage();
    break;
    case 'logout':
        unset($_SESSION['adsl24cookie']);
        header("Location: {$_SERVER['PHP_SELF']}");
        exit;
    break;
    default:
        header("HTTP/1.1 400 Bad Request");
        die("Unknown request: {$_REQUEST['action']}");
    break;
};

/* Handle login. */
if(isset($_POST['user']) && isset($_POST['pass'])) {
    $a = new ADSL24Client();

    try {
        $a->login($_POST['user'], $_POST['pass']);
        $_SESSION['adsl24cookie'] = $a->getCookies();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } catch(ADSL24ClientLoginException $e) {
        $error = $e->getMessage(); 
    } catch(ADSL24ClientException $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>ADSL24 Bandwidth Meter</title>
    <link rel="stylesheet" type="text/css" href="adsl24.css" />
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
    <script type="text/javascript" src="adsl24.js"></script>
</head>
<body>
<h1><img src="logo.png" alt="ADSL24" /><br />Bandwidth Meter</h1>
<?php if(isset($_SESSION['adsl24cookie'])): ?>
<div id="bw_chart" class="loading" style="width: 1000px; height: 200px;"></div>
<div id="bw_report"></div>
<script type="text/javascript">
<!--
ADSL24Client.init('<?php echo $_SERVER['PHP_SELF'];?>');
-->
</script>
<?php else: ?>
<div class="warning">
<h2>Warning!</h2>
<p>Please be aware that this is an <strong>unofficial</strong> site and is in no way affiliated with ADSL24.</p>
<p>This site requires that you enter and consent to your ADSL24 username and password being sent to our server which allows us to retrieve your account usage information.</p>
<p>Your username and password are handled with the greatest possible care and are <strong>not stored on our server</strong>. The connection from our server to ADSL24 is made securely over SSL and the certificates are verified.</p>
</div>
<form action="" method="post">
<?php if(isset($_POST['user'])): ?>
<p class="badlogin">Invalid username/password combination. Please try again.</p>
<?php endif; ?>
<p>
    ADSL24 Username: <input type="text" name="user" />
    Password: <input type="password" name="pass" />
    <input type="submit" value="Login" />
</p>
</form>
<?php endif; ?>
</body>
</html>
