<?php
mb_parse_str($_SERVER["QUERY_STRING"], $qs);
$rid = array_key_exists('rid', $qs) ? $qs["rid"] : "";
$all = array_key_exists('all', $qs) ? $qs["all"] : "1";
$timeformat = array_key_exists('timeformat', $qs) ? $qs["timeformat"] : "0";
$refresh = array_key_exists('refresh', $qs) ? $qs["refresh"] : "0";

?>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />	
<?php 
if ($refresh=="1" || $refresh=="2" || $refresh=="3" || $refresh=="4" || $refresh=="5" || $refresh=="6"){
	print '    <meta http-equiv="refresh" content="'. $refresh*10 . '">'."\n";
}
?>
    <style type="text/css">
	form, input { font-size: 18px; }
	table { border-collapse: collapse; }
	th, td { padding: 3px 10px 3px 3px; }
	div.time_table { float: left; margin: 8px; }
	div.footer { clear: both; margin: 8px; }
	.hilight { background: #ff8; }
    </style>

<?php

$routelist = htmlentities(file_get_contents("http://citybus.taichung.gov.tw/tcbus2/"));
$a1=strpos($routelist,"routelist");
$a2=strpos($routelist,"];",$a1);
$routelist = substr($routelist,$a1+22,$a2-$a1-32);
$routelist = explode("'],['",$routelist);
$routes0=array();
foreach ($routelist as $r) {	array_push($routes0,explode("','",$r)); }
$routes=array();
$ro="";
foreach ($routes0 as $r) {
	if ($r[0]!=$ro){
		array_push($routes,$r);
		$ro=$r[0];
	}
}


for ($r = 0; $r<count($routes); $r++) {
	if ($routes[$r][0]==$rid) $ro="99999";
}
if ($ro!="99999") $rid = "";


if ($rid == ""){

	print "<title>臺中市公車預估到站時間</title>";


}else{

	$table = file_get_contents("http://citybus.taichung.gov.tw/tcbus2/GetEstimateTime.php?routeIds=$rid");
	foreach ($routes as $r) {
		if ($r[0]==$rid){ print "<title>路線 $r[1] - ".date("H").":".date("i").":".date("s")."</title>"; }
	}

}
?>

</head>

<body>


<?php

if ($rid == ""){

	echo "<h1>臺中市公車預估到站時間</h1>";


}else{

	$table = json_decode($table);
	$table = $table->$rid;

	function to_number($ts) {
		return substr($ts,0,2)*60 + substr($ts,3,2)*1;
	}

	function to_string($tn) {
		if ($tn/60 >= 24){
			return sprintf("%02d:%02d", $tn/60 - 24, $tn%60);
		}else{
			return sprintf("%02d:%02d", $tn/60, $tn%60);
		}
	}

	$now = date("H")*60+date("i");

	$db[2] = $db[1] = array(
		'stops' => array(),
		'carId' => '',
	);
	# $db[1]: 去程
	# $db[2]: 回程
	foreach ($table as $stop) {
		$dir = $stop->GoBack;
		$arriving = '';
		if ($stop->carId != $db[$dir]['carId']) {
			$arriving = 'class="hilight"';
			$db[$dir]['carId'] = $stop->carId;
		}

		$caridlist = $estlist = $car = array();
		foreach ($stop->ests as $es) {
			array_push($caridlist, $es->carid);
			array_push($estlist, $es->est);
		}
		$car = array_combine($caridlist, $estlist);

		$time = "";
		if($stop->Value === "null" && $stop->comeCarid == ""){
			$time = '<font color="gray">離站</font>';
		}elseif(($stop->Value === "null") && ($db[$dir][stops][0][comecar]=="") && ($time != "")){ // 20150603-1
			$time = '<font color="gray">末班駛離</font>';
		}elseif($stop->Value === "null"){
			if ($timeformat == 1){
				$time = '<font color="blue">'. (to_number($stop->comeTime) - date("H")*60 - date("i")) .'  分</font>';
			}else{
				$time = '<font color="blue">'. $stop->comeTime.'</font>';
			}
		}elseif($stop->Value == 0){
			$time = '<font color="red">進站中</font>';
		}elseif($stop->Value == -3){
			$time = '<font color="gray">末班駛離</font>';
		}elseif($stop->Value < 3){
			$time = '<font color="red">即將到站</font>';
		}else{
			if ($timeformat == 2){
				$time = '<font color="blue">'. to_string(date("H")*60 + date("i") + $stop->Value) .'</font>';
			}else{
				$time = '<font color="blue">'. $stop->Value.'  分</font>';
			}
		}
	
	
		array_push($db[$dir]['stops'], array(
		'name' => $stop->StopName,
		'cars' => $car,
		'cometime' => $stop->comeTime,
		'comecar' => $stop->comeCarid,
		'time' => $time,
		'arriving' => $arriving
		) );
		
	}

	foreach ($routes as $r) {
		if ($r[0]==$rid){
			echo "<h1>路線 ".$r[1]." 預估到站時間</h1>";
			echo "<h2>".$r[2]." >>更新時間: ".date("H").":".date("i").":".date("s")."</h2>";
		}
	}

}

?>


<form method ="GET" action = "timing2.php">
輸入路線編號: <input type="text" name="rid" size="6" value=<?php echo "'$rid'"; ?> /><br>
時間顯示格式:
<input type="radio" name="timeformat" value="0" <?php if ($timeformat!=1 && $timeformat!=2) echo 'checked="checked"'; ?> />預設
<input type="radio" name="timeformat" value="1" <?php if ($timeformat==1) echo 'checked="checked"'; ?> />幾分鐘
<input type="radio" name="timeformat" value="2" <?php if ($timeformat==2) echo 'checked="checked"'; ?> />幾點幾分

<input type="submit" value="查詢路線 / 重新整理" />

<br>
自動更新間隔: <select name="refresh">
	<option value="0" >關閉</option>
	<option value="1" <?php if ($refresh==1) echo 'selected="selected"'; ?> >10 秒</option>
	<option value="2" <?php if ($refresh==2) echo 'selected="selected"'; ?> >20 秒</option>
	<option value="3" <?php if ($refresh==3) echo 'selected="selected"'; ?> >30 秒</option>
	<option value="4" <?php if ($refresh==4) echo 'selected="selected"'; ?> >40 秒</option>
	<option value="5" <?php if ($refresh==5) echo 'selected="selected"'; ?> >50 秒</option>
	<option value="6" <?php if ($refresh==6) echo 'selected="selected"'; ?> >60 秒</option>
</select> 
<br>
不顯示全部公車時間: <input type="checkbox" name="all" value="0" <?php if ($all==0) echo 'checked'; ?> /><br>

</form>





<?php
if ($rid == ""){

	echo '<div class="time_table">';
	echo "<table border=1>";
	echo "<tr><th>路線編號</th><th>路線名稱</th><th>起訖站</th></tr>";
	foreach ($routes as $r) {
		print '<tr><td><a href="http://v.im.cyut.edu.tw/~doofenshmirtz/timing2.php?rid='.$r[0].'">'.$r[0]."</a><td>".$r[1]."<td>".$r[2]."\n";
	}

	echo "</table>";
	echo "</div>";


}else{


	echo '<h3><a href="http://v.im.cyut.edu.tw/~doofenshmirtz/timing2.php">回首頁</a></h3>';

	# $db[1]: 去程


	echo '<div class="time_table">';

	foreach ($routes as $r) {
		if ($r[0]==$rid){ echo "<h2>往 ".$r[4]."</h2>"; }
	}


	echo "<table border=1>";
	echo "<tr><th>抵達時間<th>站名";
	
	if ($all==0){
		echo "<th>車牌";
	}else{
		foreach (array_keys(end($db[1][stops])[cars]) as $c) { print "<th>".$c;	}
		if ($db[1][stops][0][comecar]!="") echo "<th>".$db[1][stops][0][comecar];
	} 


	foreach ($db[1]['stops'] as $stop) {
		print "<tr $stop[arriving]><th>$stop[time]<td>$stop[name]";
	
		if ($all!=0){
			foreach (array_keys(end($db[1][stops])[cars]) as $c) {
				if ($timeformat==2 && ($stop[cars][$c]!="" || $stop[cars][$c]=="0")){
					print "<td>".to_string(date("H")*60 + date("i") + $stop[cars][$c]);
				}else{
					print "<td>".$stop[cars][$c];
				}
			}
	
			if ($stop[cometime]!="" && $db[1][stops][0][comecar]!="") {      // 20150603-1
				if ($timeformat == 1){
					print "<td>".(to_number($stop[cometime]) - date("H")*60 - date("i"))."\n";
				}else{
					print "<td>$stop[cometime]\n";
				}
			}
	
		}else{
			if(array_keys($stop[cars])[0]!=""){
				print "<td>".array_keys($stop[cars])[0]."\n";
			}else{
				print "<td>".$stop[comecar]."\n";
				
			}
		}
	
	}


	echo "</table>";
	echo "</div>";


	# $db[2]: 回程


	echo '<div class="time_table">';

	foreach ($routes as $r) {
		if ($r[0]==$rid){ echo "<h2>往 ".$r[5]."</h2>"; }
	}


	echo "<table border=1>";
	echo "<tr><th>抵達時間<th>站名";
	
	if ($all==0){
		echo "<th>車牌";
	}else{
		foreach (array_keys(end($db[2][stops])[cars]) as $c) { print "<th>".$c;	}
		if ($db[2][stops][0][comecar]!="") echo "<th>".$db[2][stops][0][comecar];
	} 


	foreach ($db[2]['stops'] as $stop) {
		print "<tr $stop[arriving]><th>$stop[time]<td>$stop[name]";
	
		if ($all!=0){
			foreach (array_keys(end($db[2][stops])[cars]) as $c) {
				if ($timeformat==2 && ($stop[cars][$c]!="" || $stop[cars][$c]=="0")){
					print "<td>".to_string(date("H")*60 + date("i") + $stop[cars][$c]);
				}else{
					print "<td>".$stop[cars][$c];
				}
			}
	
			if ($stop[cometime]!="" && $db[2][stops][0][comecar]!="") {      // 20150603-1
				if ($timeformat == 1){
					print "<td>".(to_number($stop[cometime]) - date("H")*60 - date("i"))."\n";
				}else{
					print "<td>$stop[cometime]\n";
				}
			}
		
		}else{
			if(array_keys($stop[cars])[0]!=""){
				print "<td>".array_keys($stop[cars])[0]."\n";
			}else{
				print "<td>".$stop[comecar]."\n";
				
			}
		}
	
	}

	echo "</table>";
	echo "</div>";


}


?>


<div class="footer">
<p>作者： 064023； 詳見 <a href=
"http://doofenshmirtzevilincorporated.blogspot.tw/2015/05/tcbus-timing2.html" target="_blank">
臺中市公車預估到站時間 終結者</a><br />

</div>

</body>

