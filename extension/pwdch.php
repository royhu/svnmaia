<?php
session_start();
include('../config/config.php');
include('../config.inc');
include('../include/dbconnect.php');
$usr= mysql_real_escape_string(isset($_POST['username'])?$_POST['username']:'',$mlink);
$oldpwd= mysql_real_escape_string(isset($_POST['oldpasswd'])?$_POST['oldpasswd']:'',$mlink);
$passwd0= mysql_real_escape_string(isset($_POST['newpaswd0'])?$_POST['newpaswd0']:'',$mlink);
$passwd= mysql_real_escape_string(isset($_POST['newpasswd'])?$_POST['newpasswd']:'',$mlink);
//管理员重置密码时可忽略原密码
$ignore_old=(isset($_POST['ignore_old'])and($_POST['ignore_old']=='1'));
$ajax=(isset($_POST['ajax'])and($_POST['ajax']=='1'));
if($ignore_old and !((isset($_SESSION['username']))and($_SESSION['role']=='admin')))
{
	$ignore_old=false;
}
//异步提交时的结果输出
function ajaxOut($flag,$msg)
{
	echo $flag.$msg;
	exit;
}
include('../include/basefunction.php');
$pwdpath=$passwdfile;
$cmdpath=$htpasswd;
//phpinfo();
//$authed_username = $_SERVER["PHP_AUTH_USER"]; //经过 AuthType Basic 认证的用户名
//$authed_pass = $_SERVER["PHP_AUTH_PW"]; //经过 AuthType Basic 认证的密码
//echo "username.passwd:".$authed_username.$authed_pass;
if((!(($oldpwd == "")||$ignore_old))||($usr ==""))
{
	if($ajax)ajaxOut("ERR:","原密码和用户名不能为空，请输入!");
	echo " <script>window.alert(\"原密码和用户名不能为空，请输入!\")</script>";
  echo " <a href='javascript:history.back()'>点击这里返回</a>";
  echo "<script>history.go(-1);</script>";
  exit;
}
if ($passwd != $passwd0)
{
	if($ajax)ajaxOut("ERR:","两次输入的新密码不一致，请重新输入!");
 echo " <script>window.alert(\"两次输入的新密码不一致，请重新输入!\")</script>";
  echo " <a href='javascript:history.back()'>点击这里返回</a>";
  echo "<script>history.go(-1);</script>";
  exit;
}
if(isSamplePassword($passwd,$usr))
{
		if($ajax)ajaxOut("ERR:","密码过于简单,密码由至少6个英文字母和数字/符号组成，且不能包含用户名。");
		echo "<script>window.alert(\"密码过于简单,密码由至少6个英文字母和数字/符号组成，且不能包含用户名。\")</script>";
		exit;
}

//SQL查询语句;
$query = "SELECT user_name,password FROM svnauth_user WHERE user_name ='$usr'";
$result =mysql_query($query);
if($result)$totalnum=mysql_num_rows($result);
if($totalnum>0){
  while (($result)and($row= mysql_fetch_array($result, MYSQL_BOTH))) {
	  $fpasswd=$row['password'];
	  if($ignore_old or verifyPasswd($oldpwd,$fpasswd))
	  {
		$passwd=cryptMD5Pass($passwd);
		$query = "update svnauth_user set password='$passwd' WHERE user_name ='$usr'";
// 执行查询
		mysql_query($query);
		$err=mysql_error();
		if (empty($err)){
			$passwd0=escapeshellcmd($passwd0);
			$usr=escapeshellcmd($usr);
	          exec($cmdpath.' -m -b '. $pwdpath . ' '.$usr.' '.$passwd0);
	//echo  ($cmdpath.' -m -b '. $pwdpath . ' '.$passwd);
	         if($ajax)ajaxOut("OK:","密码重置成功！");
  	         echo "<script>window.alert(\"密码更改成功！　\")</script>";
	          echo "    <script>setTimeout('document.location.href=\"javascript:history.back()\"',5)</script>";
		mysql_close($mlink);
	        exit;
		}
	  }else
	  {
		if($ajax)ajaxOut("ERR:","原密码输入错误！");
		echo "<script>window.alert(\"原密码输入错误！\")</script>";
		echo "<script>history.go(-1);</script>";
	  }
	}
}
else{
		if($ajax)ajaxOut("ERR:","用户名不存在！");
		echo "<script>window.alert(\"用户名不存在！\")</script>";
		echo "<script>history.go(-1);</script>";
		mysql_close($mlink);
		exit;
}



?>
