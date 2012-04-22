<?php
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>ADSL24 Bandwidth Meter</title>
    <link rel="stylesheet" type="text/css" src="adsl24.css" />
</head>
<body>
<h1><img src="logo.png" alt="ADSL24" /><br />Bandwidth Meter</h1>
<?php 
    if(isset($_POST['user']) && isset($_POST['pass'])): 
    
    $a = new ADSL24Client();
    $a->login($_POST['user'], $_POST['pass']);
    $stats = $a->usage();
    // $stats = array('used'=>25, 'remaining'=>5);
    // $stats = array('used'=>13, 'remaining'=>17);
    
    $total = array_sum($stats);
    list($wd_so_far, $wd_total) = weekdays_in_month();
    $bw_per_day = $total / $wd_total;
    $scheduled = $wd_so_far * $bw_per_day;
    $credit = $scheduled - $stats['used'];
    
?>
<div id="chart_div" style="width: 1000px; height: 200px;"></div>
<div class="report">

</div>
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
<script type="text/javascript" src="adsl24.js"></script>
google.load('visualization', '1', {packages:['corechart']});
google.setOnLoadCallback(drawChart);
function drawChart() {
    var data = google.visualization.arrayToDataTable([
        <?php if($credit >= 0): ?> 
        ['NULL', 'Used', 'Credit'],
        ['Bandwidth', <?=$stats['used'];?>, <?=$credit;?> ]
        <?php else: ?>
        ['NULL', 'Scheduled', 'Deficit'],
        ['Bandwidth', <?=$scheduled;?>, <?=-$credit;?> ]
        <?php endif; ?>
    ]);

    var options = {
        enableInteractivity: true,
        isStacked: true,
        chartArea: { left: 0, width: '100%' },
        hAxis: { 
            gridlines: { color: '#FFF', count: <?=$wd_total;?> },
            viewWindow: { min: 0, max: <?=$total;?> }
        },
        backgroundColor: '#222',
        <?php if($credit >= 0): ?> 
        series: [ { color: '#2EFF29' }, { color: '#333' } ]
        <?php else: ?>
        series: [ { color: '#2EFF29' }, { color: '#FF2E29' } ]
        <?php endif; ?>
    };

    var chart = new google.visualization.BarChart(
        document.getElementById('chart_div')
    );

    chart.draw(data, options);
};
</script>
<?php else: ?>
<div class="warning">
<h2>Warning!</h2>
<p>Please be aware that this is an <strong>unofficial</strong> site and is in no way affiliated with ADSL24.</p>
<p>This site requires that you enter and consent to your ADSL24 username and password being sent to our server which allows us to retrieve your account usage information.</p>
<p>Your username and password are handled with the greatest possible care and are <strong>not stored on our server</strong>. The connection from our server to ADSL24 is made securely over SSL and the certificates are verified.</p>
</div>
<form action="" method="post">
<p>
    Username: <input type="text" name="user" />
    Password: <input type="password" name="pass" />
    <input type="submit" value="Login" />
</p>
</form>
<?php endif; ?>
</body>
</html>
