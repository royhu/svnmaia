<?php
session_start();
 error_reporting(0);
include('../../include/charset.php');
if (!isset($_SESSION['username'])){
	echo "请先<a href='../user/loginfrm.php'>登录</a> ！";
	echo" <script>setTimeout('document.location.href=\"../../user/loginfrm.php\"',0)</script>";
	exit;
}
include('../../config.inc');
// include('../../../../config.inc');
include('../../include/dbconnect.php');
echo <<<HTML
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../../css/mobile.css" type="text/css">
<style type='text/css'>
.trc2{background: #d7d7d7;font-size:10pt;}
.trc1{font-size:10pt
</style>
<script src="../../js/jquery-1.4.2.min.js"></script>
<script>
function submit(status,id,permission,path) {
         var mail_back = prompt("请输入回执信息");
	$.get("request.php",{"status":status,"id":id,"permission":permission,"path":path,"mail_back":mail_back},function(data){
        alert(data);
        location.reload() ;
		});
}
</script>
HTML;
echo "<table class='detail'><tr><th>id</th><th>申请者</th><th>申请路径</th><th>申请权限</th><th>申请日期</th><th>处理人</th><th>处理结果</th><th>手动处理</th></tr>";
$query="select id ,`username`,`repository`,`path`,`permission`,`rtdate`,`ops`, `optype` from rt_svnpriv ORDER BY id DESC limit 200;";
$result=mysql_query($query);
while (($result)and($row= mysql_fetch_array($result, MYSQL_BOTH))) {
	$repos=$row['repository'];
	$path=$row['path'];
	$permission=$row['permission'];
	$rtdate=$row['rtdate'];
	$ops=$row['ops'];
	$optype=$row['optype'];
	$un=$row['username'];
	$id = $row['id'];
	$path=$repos.$path;
	switch($permission){
	case 'w':
		$permission='write';
		break;
	case 'r':
		$permission='readOnly';
		break;
	case 'n':
		$permission='none';
		break;
	}
	($tr_class=="trc1")?($tr_class="trc2"):($tr_class="trc1");
	if(empty($optype)){

	echo "<tr class=$tr_class><td>$id</td><td>$un</td><td>$path</td><td>$permission</td><td>$rtdate</td><td>$ops</td><td>$optype</td><td><input type='button' id='yes' onClick=submit(\"agreed\",\"{$id}\",\"{$permission}\",\"{$path}\") value='同意' /><input type='button' id='no'onClick='submit(\"denied\",\"{$id}\",\"{$permission}\",\"{$path}\")' value='拒绝' /><input type='button' id='other' onClick='submit(\"other\",\"{$id}\",\"{$permission}\",\"{$path}\")' value='手动处理' /></td></tr>";
	}
	else {
	    echo "<tr class=$tr_class><td>$id</td><td>$un</td><td>$path</td><td>$permission</td><td>$rtdate</td><td>$ops</td><td>$optype</td><td></td></tr>";
	}
}
echo "</table>";

