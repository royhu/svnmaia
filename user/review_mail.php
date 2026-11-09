
<html>
<style type='text/css'>
 fieldset{border:2px solid #A4CDF2;padding:20px;background:#FFFFFF;width:60%}
 legend{color:#1E7ACE;padding:3px 20px;border:1px solid #A4CDF2;background:#FFFFFF;}
.st{margin-left:10px;line-height:30px;}
.ft{background:#B6C6D6;text-align:center;margin:20px 0 20px 0;}

</style>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../css/mobile.css" type="text/css">
<script language="javascript">
/*function showexpire(sw)
{
	if(sw==1)
	{
		document.getElementById('myexpire').style.display='block';
	}else
	{
		document.getElementById('myexpire').style.display='none';
	}
}
*/

</script>
</head>
<body>
<h1>处理组权限请求</h1>

<form action="../extension/autopriv/reviewAction.php" method="post">
<input type='hidden' name='user_id' value="<?php echo $_GET['user_id'] ?>">
<input type='hidden' name='group_id' value="<?php echo $_GET['group_id'] ?>">
<input type='hidden' name='group_name' value="<?php echo $_GET['group_name'] ?>">
<input type='hidden' name='sign' value="<?php echo $_GET['sign'] ?>">

<br><input type='radio' name='status' value='1' onclick='showexpire(1)' id='diradmin'><label for='diradmin'>同意</label>
<br><input type='radio' name='status' value='2' onclick='showexpire(0)' id='superadmin'><label for='superadmin'>拒绝</label>
<br>回执给申请人:<br><input type=text name='mail_back'  size='40' class='in'>
</div>
<div class='ft'>
<input type='submit' value='确定提交'>
</div>
</body>
</html>