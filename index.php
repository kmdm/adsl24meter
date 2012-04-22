<?php
/* Copyright (C) 2012 Kenny Millington
 *
 * This file is part of adsl24meter.
 * 
 * adsl24meter is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *  
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
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
    
    $html .= "<p><a href=\"?action=logout\">[Logout]</a></p>\n";
    
    $html .= "<p class=\"lastgen\">Last generated: ";
    
    $secs = time() - $_SESSION['last_req_ts'];
    $mins = floor($secs / 60);
    $secs %= 60;

    $html .= "$mins minutes, $secs seconds ago ";
    $html .= "(updates after 10 minutes).</p>";

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
        
        list($wd_so_far, $wd_total) = weekdays_in_month();

        if(!isset($stats)) {
            if(isset($_SESSION['demo'])) {
                $scheduled = $wd_so_far * 30 / $wd_total;
                if($_SESSION['demo'] == 'over') {
                    $stats = array('used'=>mt_rand($scheduled, 30));
                } else {
                    $stats = array('used'=>mt_rand(1, $scheduled - 1));
                }

                $stats['remaining'] = 30 - $stats['used'];
            } else {
                $a = new ADSL24Client($_SESSION['adsl24cookie']);
                $stats = $a->usage();
            }

            $_SESSION['last_req_ts'] = time();
            $_SESSION['last_req'] = serialize($stats);
        }

        $total = array_sum($stats);
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
        $_SESSION = array();
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
    if($_POST['user'] == 'demo') {
        if($_POST['pass'] == 'under' || $_POST['pass'] == 'over') {
            $_SESSION['demo'] = $_POST['pass'];
            $_SESSION['adsl24cookie'] = 'FAKECOOKIE';
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    } else {
        unset($_SESSION['demo']);
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

<p>If you wish to try this site without providing your ADSL24 username/password combination then you may use the demo account with the username of 'demo' and a password of either 'over' or 'under' depending whether you want to see a meter that's over the scheduled usage or under it.</p>
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
<div id="footer">
    <p class="source">
        <strong>Don't trust this site?</strong>
        <a href="https://github.com/kmdm/adsl24meter">Get the source!</a>
    </p>
    <p class="copyright">
        Copyright &copy; 2012 
        <a href="http://www.kennynet.co.uk/">Kenny Millington</a>
    </p>
</div>
</body>
</html>
