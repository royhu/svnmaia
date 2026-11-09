<?php
	include('../config/config.php');
include('../include/charset.php');
include('../config.inc');
include('../include/dbconnect.php');
include('../include/basefunction.php');
//可选权限组
$group_options='';
$query="select group_id,group_name from svnauth_group order by ".groupOrderSql();
$result=mysql_query($query);
while(($result)and($row=mysql_fetch_array($result,MYSQL_BOTH)))
{
	$group_options.="<option value='{$row['group_id']}'>".htmlspecialchars($row['group_name'],ENT_QUOTES)."</option>";
}
?>
<!--
Author:lixuejiang
Site:http://www.scmbbs.com
Date:2009-02-19
-->
<html>
<head>
  <title>svn用户注册</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/mobile.css" type="text/css">
</head>
<style type='text/css'>
h1{text-align:center;}
p{margin-left:40px}
div{margin:15px}
fieldset{border:2px solid #A4CDF2;padding:20px;background:#FFFFFF;width:60%}
 legend{color:#1E7ACE;padding:3px 20px;border:2px solid #A4CDF2;background:#FFFFFF;}
 a{color:green;text-decoration:underline;}
.m{color:red;font-size:12px}
td{height:35px;}
.lb{width:122px;text-align:right;}
</style>
<body>

  <p>
  <noscript><strong>您的浏览器不支持script脚本。<br>用户注册功能将不能正常使用！<br></noscript>
  	<form name='regform' action=reg.php method=post onsubmit="return fCheck()">
	<fieldset>
  	 <legend>注册subversion用户</legend>
   	<table class='formtbl'>
	<tr>
		<td  class='lb'>用户名：</td>
		<td ><input type="text" name="username" size="20" autocomplete="username" autocapitalize="off" autocorrect="off" onblur="addemail()"> * <span class='m'>请与您的邮箱前缀保持一致，由字母组成</span></td>
 	</tr>
 
	<tr>
		<td  class='lb'>密码：</td>
		<td ><input type="text" name="passwd" size="20" autocomplete="off" onchange="syncPasswd()" onkeyup="syncPasswd()"> * <span class='m'>自动生成</span> <a href="javascript:regeneratePassword()">重新生成</a></td>
	</tr>
	<tr>
		<td  class='lb'>确认新密码：</td>
		<td><input type=text name="passwd0" size="20" autocomplete="off"> *</td>
	</tr>
	<tr>
		<td  class='lb'>中文姓名：</td>
		<td ><input type="text" name="fullname" size="20" > * <span class='m'>请填写真实姓名</span></td>
 	</tr>
 	<tr>
		<td  class='lb'>工号：</td>
		<td ><input type="text" name="staff_no" size="20" inputmode="numeric" autocomplete="off">  <span class='m'>实习生可不填</span></td>
 	</tr>	
 	<tr>
		<td  class='lb'>部门：</td>
		<td ><input type="text" name="department" size="20" >  <span class='m'></span></td>
	</tr>	
		<tr>
		<td  class='lb'>电子邮件：</td>
		<td ><input type="text" name="email" size="20" inputmode="email" autocomplete="email"> </td>
 	</tr>	
	<tr>
		<td  class='lb'>权限组：</td>
		<td>
			<input type="button" value="&lt;&lt; add group" onclick="addGroup(false)">&nbsp;
			<input type="button" value="&gt;&gt; del group" onclick="delGroup()">
			<div>
				<div id='groupPanel' style='display:none;float:left;margin-right:10px'>
					<span class='m'>可选权限组（可多选）</span><br>
					<select id='groupCandidate' multiple size=8 style='width:200px'>
						<?php echo $group_options;?>
					</select>
					<br><input type="button" value="添加选中组" onclick="addGroup(true)">
				</div>
				<div id='groupChosenDiv' style='float:left'>
					<span class='m'>已选权限组（可多选后移除）</span><br>
					<select id='groupChosen' multiple size=8 style='width:200px'></select>
				</div>
				<div style='clear:both'></div>
			</div>
		</td>
	</tr>
 </table>
	<input type="hidden" name="group_ids" value="">
	<table border="0" width="84%" id="table2" class='actbar'>
	<tr>
		<td width="104"><input type=button value="提交" onclick="return tCheck()"></td>
		<td><input type=reset value="取消" onclick="resetGroups()"></td>
		<td align=right><a href='./viewuser.php'>修改用户</a></td>
		</tr>
	</table>
</fieldset>
	</form>

</body>
</html>
<script language="javascript">
	<!--
	

//显示/隐藏可选权限组，并添加选中的组
function addGroup(withSelected)
{
	var panel=document.getElementById("groupPanel");
	if(panel.style.display!="block")
	{
		panel.style.display="block";
		if(!withSelected)return false;
	}
	if(!withSelected)return false;
	var canObj=document.getElementById("groupCandidate");
	var chosenObj=document.getElementById("groupChosen");
	var moved=false;
	for(var i=canObj.length-1;i>=0;i--)
	{
		if(canObj.options[i].selected)
		{
			var opt=document.createElement("option");
			opt.value=canObj.options[i].value;
			opt.text=canObj.options[i].text;
			chosenObj.appendChild(opt);
			canObj.remove(i);
			moved=true;
		}
	}
	if(!moved)alert("请先在左侧选择要添加的权限组！");
}

//从已选列表中移除权限组
function delGroup()
{
	var canObj=document.getElementById("groupCandidate");
	var chosenObj=document.getElementById("groupChosen");
	var moved=false;
	for(var i=chosenObj.length-1;i>=0;i--)
	{
		if(chosenObj.options[i].selected)
		{
			var opt=document.createElement("option");
			opt.value=chosenObj.options[i].value;
			opt.text=chosenObj.options[i].text;
			canObj.appendChild(opt);
			chosenObj.remove(i);
			moved=true;
		}
	}
	if(!moved)alert("请先在右侧选择要移除的权限组！");
}

//提交时把已选权限组拼成隐藏字段
function collectGroups()
{
	var chosenObj=document.getElementById("groupChosen");
	var gids=[];
	for(var i=0;i<chosenObj.length;i++)gids.push(chosenObj.options[i].value);
	regform.group_ids.value=gids.join(",");
}

//清空已选权限组，全部还原到可选列表
function resetGroups()
{
	var canObj=document.getElementById("groupCandidate");
	var chosenObj=document.getElementById("groupChosen");
	while(chosenObj.length>0)
	{
		var opt=document.createElement("option");
		opt.value=chosenObj.options[0].value;
		opt.text=chosenObj.options[0].text;
		canObj.appendChild(opt);
		chosenObj.remove(0);
	}
	document.getElementById("groupPanel").style.display="none";
	regform.group_ids.value="";
}

function addemail()
{
	regform.email.value=regform.username.value+"<?php echo $email_ext;?>";
}

//生成10~12位随机密码（大写字母、小写字母、数字）
function generatePassword()
{
	var upper="ABCDEFGHIJKLMNOPQRSTUVWXYZ";
	var lower="abcdefghijklmnopqrstuvwxyz";
	var digit="0123456789";
	var all=upper+lower+digit;
	var len=10+Math.floor(Math.random()*3);
	var pwd="";
	pwd+=upper.charAt(Math.floor(Math.random()*upper.length));
	pwd+=lower.charAt(Math.floor(Math.random()*lower.length));
	pwd+=digit.charAt(Math.floor(Math.random()*digit.length));
	while(pwd.length<len)
	{
		pwd+=all.charAt(Math.floor(Math.random()*all.length));
	}
	//打乱顺序
	var arr=pwd.split("");
	for(var i=arr.length-1;i>0;i--)
	{
		var j=Math.floor(Math.random()*(i+1));
		var t=arr[i];arr[i]=arr[j];arr[j]=t;
	}
	return arr.join("");
}

//新密码修改后自动同步到“确认新密码”
function syncPasswd()
{
	regform.passwd0.value=regform.passwd.value;
}

function regeneratePassword()
{
	var pwd=generatePassword();
	regform.passwd.value=pwd;
	regform.passwd0.value=pwd;
}

//复制内容到剪切板
function copyToClipboard(content)
{
	var ok=false;
	try{
		var ta=document.createElement("textarea");
		ta.value=content;
		ta.style.position="fixed";
		ta.style.left="-9999px";
		document.body.appendChild(ta);
		ta.select();
		ok=document.execCommand("copy");
		document.body.removeChild(ta);
	}catch(e){ok=false;}
	if(!ok&&navigator.clipboard&&navigator.clipboard.writeText)
	{
		navigator.clipboard.writeText(content);
	}
}
 function fCheck(){
 		 
 
 if( regform.username.value =="") 
 {
       alert("\用户名不能为空!");
       regform.username.focus();
       return false;
  }
 if( regform.passwd.value =="") 
 {
       alert("\密码不能为空!");
       regform.passwd.focus();
       return false;
  }

   if( ! isPassword( regform.passwd.value ) )
   {
	 alert("\密码过于简单,密码由至少6个英文字母和数字/符号组成 !"); 
        regform.passwd.select();
        regform.passwd.focus();
        return false;
   }
  if( regform.passwd0.value =="" )
  {
      alert("\请输入密码确认 !");
      regform.passwd0.select();
      regform.passwd0.focus();
      return false;
  }
  if(  regform.passwd0.value != regform.passwd.value ) {
     alert("\两次密码输入不一致 !");
     regform.passwd.focus();
     return false;
  }
 if( regform.fullname.value =="" )
  {
      alert("\请输入您的真实姓名!");
      regform.fullname.select();
      regform.fullname.focus();
      return false;
  }
function isPassword( password )
  {
      if(password.search(regform.username.value)>=0 ||/^hello1234$/i.test(password))return false;
      if(password.length < 6||!/[0-9\W]+/.test(password)||!/[a-zA-Z]+/.test(password))return false;
      return true;
  }
  return true;
}  
function tCheck()
{
	if(!fCheck())return false;
	loadTip();
	return true;
}

//用于创建XMLHttpRequest对象
function createXmlHttp() {
    //根据window.XMLHttpRequest对象是否存在使用不同的创建方式
    if (window.XMLHttpRequest) {
       xmlHttp = new XMLHttpRequest();                  //FireFox、Opera等浏览器支持的创建方式
    } else {
       xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");//IE浏览器支持的创建方式
    }
}
function displayTip(content) {
	alert(content);
}
//从服务器加载关键词的详细信息
function loadTip() {
    if(!fCheck())return false;
   collectGroups();
   var username="username="+regform.username.value
      +"&passwd="+regform.passwd.value+"&passwd0="+regform.passwd0.value
      +"&fullname="+regform.fullname.value
      +"&staff_no="+regform.staff_no.value
      +"&department="+regform.department.value
      +"&group_ids="+regform.group_ids.value
      +"&email="+regform.email.value;
    createXmlHttp();                                //创建XMLHttpRequest对象
    xmlHttp.onreadystatechange = loadTipCallBack;   //设置回调函数
    xmlHttp.open("POST", "./reg.php", true);
    xmlHttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlHttp.send(username);
}

//获取查询选项的回调函数
function loadTipCallBack() {
    if (xmlHttp.readyState == 4) {
        var tip=xmlHttp.responseText;
        if(tip=="用户注册成功！")
        {
            var content="hi, svn 账号：\n"+regform.username.value+"\n"+regform.passwd.value;
            copyToClipboard(content);
            alert(tip+"\n账号信息已复制到剪切板：\n"+content);
            regform.reset();
            regeneratePassword();
            resetGroups();
        }
        else
        {
            alert(tip);                                //显示加载完毕的详细信息
        }
        regform.username.focus();
    }
}

//页面加载时自动生成密码
regeneratePassword();

	-->
</script>

