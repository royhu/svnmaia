<?php
session_start();
include('../include/charset.php');
include('../config.inc');
include('../include/dbconnect.php');
//从用户列表点“修改密码”进来时，带上用户ID：自动填好用户名
//管理员进入重置模式（不验证原密码），普通用户仍需输入原密码
$reset_id=isset($_GET['u'])?trim($_GET['u']):'';
$reset_mode=false;
$reset_username='';
if(($reset_id!='')and is_numeric($reset_id))
{
	$query="select user_name from svnauth_user where user_id=$reset_id";
	$result=mysql_query($query);
	if(($result)and($row=mysql_fetch_array($result,MYSQL_BOTH)))
	{
		$reset_username=$row['user_name'];
		$reset_mode=(isset($_SESSION['role'])and($_SESSION['role']=='admin'));
		//非管理员只能修改自己的密码
		if((!$reset_mode)and((!isset($_SESSION['username']))or($_SESSION['username']!=$reset_username)))
		{
			$reset_username='';
		}
	}
}
$prefill=($reset_username!='');
?>
<html>
<head>
<title>svn用户权限管理系统---密码修改</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../css/mobile.css" type="text/css">
<style type="text/css">
form{margin:100px 170px;padding:20px;}
.in{width:140px}
fieldset{border:2px solid #A4CDF2;padding:20px;background:#FFFFFF;width:400px;}
 legend{color:#1E7ACE;padding:3px 20px;border:2px solid #A4CDF2;background:#FFFFFF;}
.tp{margin:40px 40px 0px 0px;}
.m{color:red;font-size:12px}
</style>
</head>
<body>
</p>
  <form id="chpasswd" method="post" action="pwdch.php" onSubmit="return pcheck()">
<fieldset>
	<legend><?php echo $reset_mode?'重置svn密码':'修改svn密码';?></legend>
	<table class='formtbl'>
		<tr>
			<td width="122" height="19">用户名：</td>
			<td height="19"><input type="text" name="username" size="20" autocomplete="username" autocapitalize="off" autocorrect="off" value="<?php echo htmlspecialchars($reset_username,ENT_QUOTES);?>" <?php echo $prefill?'readonly':'';?>> *</td>
  	</tr>

<?php if($reset_mode){ ?>
		<tr>
			<td width="122" height="19">忽略原密码：</td>
			<td height="19"><input type="checkbox" name="ignore_old" id="ignore_old" value="1" checked onclick="toggleOld()"> <span class='m'>默认不验证原密码</span></td>
	</tr>
<?php } ?>
		<tr>
			<td width="122" height="19">原密码：</td>
			<td height="19"><input type="password" name="oldpasswd" size="20" id="oldpasswd"> * </td>
	</tr>
	<tr>
		<td width="122">新密码：</td>
		<td><input type="text" name="newpasswd" size="20" autocomplete="off" autocapitalize="off" autocorrect="off" onchange="syncPasswd()" onkeyup="syncPasswd()"> * <?php if($reset_mode){?><a href="javascript:regeneratePassword()">重新生成</a><?php }?></td>
	</tr>
	<tr>
		<td width="122">确认新密码：</td>
		<td><input type="text" name="newpaswd0" size="20" autocomplete="off" autocapitalize="off" autocorrect="off"> *</td>
	</tr>
	</table>
	
	
	<table border="0" width="84%" id="table2" class='actbar'>
	<tr>
		<td width="104"><input type=submit value="确定" ></td>
		<td><input type=reset value="取消"></td>
		<td><a href='topwd.php'>找回密码</a></td>
	</tr>
</table>
</fieldset>
</form>
<script language="javascript">
	<!--
var resetMode=<?php echo $reset_mode?'true':'false';?>;
var uid=<?php echo $reset_mode?$reset_id:'0';?>;

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
	chpasswd.newpaswd0.value=chpasswd.newpasswd.value;
}

function regeneratePassword()
{
	var pwd=generatePassword();
	chpasswd.newpasswd.value=pwd;
	chpasswd.newpaswd0.value=pwd;
}

//忽略原密码勾选状态切换
function toggleOld()
{
	var cb=document.getElementById('ignore_old');
	var op=document.getElementById('oldpasswd');
	if(!cb||!op)return;
	op.disabled=cb.checked;
	if(cb.checked)op.value='';
}

//复制内容到剪切板；iOS Safari 在异步回调中不允许写剪贴板，
//失败时回调 done(false)，由调用方展示手动复制面板
function copyToClipboard(content,done)
{
	done=done||function(){};
	var ta=document.createElement("textarea");
	ta.value=content;
	//iOS 上 readonly 的 textarea 无法被 select()，因此不加 readonly
	var top=(window.pageYOffset||document.documentElement.scrollTop||0);
	ta.style.cssText="position:absolute;left:0;top:"+top+"px;width:1px;height:1px;padding:0;border:none;outline:none;opacity:0;font-size:16px;";
	document.body.appendChild(ta);
	var ok=false;
	try{
		ta.focus();
		ta.select();
		try{ta.setSelectionRange(0,content.length);}catch(e){}
		ok=document.execCommand("copy");
	}catch(e){ok=false;}
	document.body.removeChild(ta);
	if(ok){done(true);return;}
	if(navigator.clipboard&&navigator.clipboard.writeText)
	{
		try{
			var p=navigator.clipboard.writeText(content);
			if(p&&p.then){p.then(function(){done(true);},function(){done(false);});return;}
			done(true);return;
		}catch(e){}
	}
	done(false);
}

//手动复制面板：iPhone 上自动复制失败时，让用户点一下按钮（属于用户手势）再复制
function showCopyPanel(content,title)
{
	var old=document.getElementById("copypanel");
	if(old&&old.parentNode)old.parentNode.removeChild(old);
	var box=document.createElement("div");
	box.id="copypanel";
	box.style.cssText="position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:99999;";
	var inner=document.createElement("div");
	inner.style.cssText="position:absolute;left:5%;right:5%;top:15%;background:#fff;border-radius:8px;padding:14px;";
	var head=document.createElement("div");
	head.style.cssText="font-size:16px;font-weight:bold;color:#1a9c3d;margin-bottom:6px;";
	head.innerHTML=title?title:"新密码";
	var tip=document.createElement("div");
	tip.style.cssText="font-size:15px;line-height:1.5;margin-bottom:8px;color:#333;";
	tip.innerHTML="iOS 不允许自动写入剪切板，请点“复制”按钮，或长按文本框选择“拷贝”。";
	var ta=document.createElement("textarea");
	ta.value=content;
	ta.style.cssText="width:100%;height:80px;font-size:16px;box-sizing:border-box;padding:6px;";
	inner.appendChild(head);
	inner.appendChild(tip);
	inner.appendChild(ta);
	var wrap=document.createElement("div");
	wrap.style.cssText="margin-top:10px;text-align:center;";
	var btnCopy=document.createElement("button");
	btnCopy.type="button";
	btnCopy.innerHTML="复制";
	btnCopy.style.cssText="min-height:42px;min-width:110px;font-size:16px;margin:0 6px;";
	btnCopy.onclick=function(){
		ta.focus();
		ta.select();
		try{ta.setSelectionRange(0,content.length);}catch(e){}
		var done=false;
		try{done=document.execCommand("copy");}catch(e){done=false;}
		if(!done&&navigator.clipboard&&navigator.clipboard.writeText)
		{
			try{navigator.clipboard.writeText(content);done=true;}catch(e){done=false;}
		}
		alert(done?"已复制到剪切板":"复制失败，请长按文本框选择“拷贝”");
	};
	var btnClose=document.createElement("button");
	btnClose.type="button";
	btnClose.innerHTML="关闭";
	btnClose.style.cssText="min-height:42px;min-width:90px;font-size:16px;margin:0 6px;";
	btnClose.onclick=function(){if(box.parentNode)box.parentNode.removeChild(box);};
	wrap.appendChild(btnCopy);
	wrap.appendChild(btnClose);
	inner.appendChild(wrap);
	box.appendChild(inner);
	document.body.appendChild(box);
	ta.focus();
	ta.select();
	try{ta.setSelectionRange(0,content.length);}catch(e){}
}

function pcheck(){

   if( chpasswd.username.value =="") 
 {
       alert("\请输入用户名 !");
       chpasswd.username.focus();
       return false;
  }
   if( ! resetMode || ! document.getElementById('ignore_old').checked )
   {
	   if( chpasswd.oldpasswd.value =="") 
	 {
	       alert("\请输入原密码 !");
	       chpasswd.oldpasswd.focus();
	       return false;
	  }
   }

   if( ! isPassword( chpasswd.newpasswd.value ) )
   {
        alert("\请重新输入密码,密码由至少6个英文字母或数字组成 !"); 
        chpasswd.newpasswd.select();
        chpasswd.newpasswd.focus();
        return false;
   }
  if( chpasswd.newpaswd0.value =="" )
  {
      alert("\请输入密码确认 !");
      chpasswd.newpaswd0.select();
      chpasswd.newpaswd0.focus();
      return false;
  }
  if(  chpasswd.newpaswd0.value != chpasswd.newpasswd.value ) {
     alert("\两次密码输入不一致 !");
     chpasswd.newpasswd.focus();
     return false;
  }
  if(resetMode)
  {
	  submitReset();
	  return false;
  }
  return true;
}

function isPassword( password )
  {
      if(password.search(chpasswd.username.value)>=0 ||/^hello1234$/i.test(password))return false;
      if(password.length < 6||!/[0-9\W]+/.test(password)||!/[a-zA-Z]+/.test(password))return false;
	return true;
  }

//重置模式下用异步提交，成功后把新密码复制到剪切板
function submitReset()
{
	var params="username="+encodeURIComponent(chpasswd.username.value)
		+"&oldpasswd="+encodeURIComponent(chpasswd.oldpasswd.value)
		+"&newpasswd="+encodeURIComponent(chpasswd.newpasswd.value)
		+"&newpaswd0="+encodeURIComponent(chpasswd.newpaswd0.value)
		+"&ignore_old="+(document.getElementById('ignore_old').checked?'1':'')
		+"&ajax=1";
	var req=null;
	if(window.XMLHttpRequest)req=new XMLHttpRequest();
	else req=new ActiveXObject("Microsoft.XMLHTTP");
	req.onreadystatechange=function(){
		if(req.readyState!=4)return;
		var tip=req.responseText;
		if(tip.indexOf("OK:")==0)
		{
			var pwd=chpasswd.newpasswd.value;
			copyToClipboard(pwd,function(ok){
				if(ok)
				{
					alert(tip.substr(3)+"\n新密码已复制到剪切板："+pwd);
				}else
				{
					showCopyPanel(pwd);
				}
			});
			regeneratePassword();
		}
		else if(tip.indexOf("ERR:")==0)
		{
			alert(tip.substr(4));
		}
		else
		{
			alert(tip);
		}
	};
	req.open("POST","./pwdch.php",true);
	req.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
	req.send(params);
}

//重置模式下页面打开即生成新密码并默认忽略原密码
if(resetMode)
{
	toggleOld();
	regeneratePassword();
}
	-->
</script>
</body>
</html>
