<?php
//把 branches 下某个分支目录（或 branches 本身的所有子目录）的权限，同步为 trunk 对应目录的权限
session_start();
include('../include/charset.php');
if(file_exists('../config/config.php'))
{
	include('../config/config.php');
}else
{
	echo "window.alert('请先进行系统设置!')";
	exit;
}
error_reporting(0);
if (!isset($_SESSION['username'])){
	echo "请先<a href='../user/loginfrm.php'>登录</a> ！";
	echo" <script>setTimeout('document.location.href=\"../user/loginfrm.php\"',0)</script>";
	exit;
}
if (($_SESSION['role'] !='admin')and($_SESSION['role'] !='diradmin'))
{
	echo "您无权进行此操作！";
	exit;
}
include('../config.inc');
include('../include/basefunction.php');
include('../include/dbconnect.php');

$repos_in=trim($_POST['repos']);
$path_in=trim($_POST['path']);
if($path_in!='/' and substr($path_in,-1)=='/')$path_in=substr($path_in,0,-1);
$all=isset($_POST['all']);

//校验参数：签名按表单提交的原始值计算（与 dirpriv.php / platpriv.php 一致）
if(keygen(array($repos_in,$path_in)) != $_POST['sig'])
{
	echo "参数非法！请勿越权操作！";
	exit;
}

$repos=mysql_real_escape_string($repos_in);
$path=mysql_real_escape_string($path_in);
$url="./dirpriv.php?d=$repos_in{$path_in}";
//标定 branches 根目录
if($all)
{
	//当前必须是项目的 branches 目录，例如 /branches
	if(basename($path_in)!='branches')
	{
		echo "当前目录不是 branches 目录，无法批量同步。";
		exit;
	}
	$branches_dir=$path_in;
}else
{
	//当前目录的上一级必须是 branches
	$pdir=dirname($path_in);
	if($pdir=='\\')$pdir='/';
	if(basename($pdir)!='branches')
	{
		echo "当前目录不在 branches 下，无需同步。";
		exit;
	}
	$branches_dir=$pdir;
}
//校验目录管理员权限
$authz=($_SESSION['role']=='admin');
if(!$authz)
{
	$uname=mysql_real_escape_string($_SESSION['username']);
	$t=$path;
	for($ii=0;$ii<20;$ii++)
	{
		$query="select svnauth_dir_admin.user_id from svnauth_dir_admin,svnauth_user where svnauth_dir_admin.user_id=svnauth_user.user_id and repository='$repos' and path='$t' and user_name='$uname'";
		$result=mysql_query($query);
		if($result and mysql_num_rows($result)>0){$authz=true;break;}
		if(($t=='/')or(empty($t)))break;
		if(strlen($t)>1)$t=dirname($t);
		if($t=='\\')$t='/';
	}
}
if(!$authz)
{
	echo "您无权同步该目录的权限！";
	exit;
}

//平级的 trunk 目录
$parent=dirname($branches_dir);
if($parent=='\\')$parent='/';
$trunk_root=($parent=='/'?'':$parent).'/trunk';

$report='';
$ok=0;
$fail=0;
$cancel=isset($_POST['cancel']);
if($cancel)
{
	//取消自动同步：只关闭开关，不改变已有分支的权限
	if(basename($branches_dir)!='branches')
	{
		echo "当前目录不是 branches 目录，无法取消自动同步。";
		exit;
	}
	setBranchSync($repos_in,false);
	echo <<<HTML
<p style='text-align:center;line-height:2;border:solid 1px;background:#ecf0e1;margin-top:60px;'>
<br><strong>已取消 branches 自动同步</strong>：以后 branches 下新增的子目录不再自动同步 trunk 权限，已有分支的权限保持不变。
<br>
<br><a href="$url">返回继续操作</a>
</p>
HTML;
	exit;
}
if($all)
{
	//branches 下的所有子目录，逐个同步
	$subs=listBranchDirs($repos_in,$branches_dir);
	if(empty($subs))
	{
		$report.="<br>branches 目录下没有找到任何子目录。";
	}else
	{
		foreach($subs as $sub)
		{
			$bpath=$branches_dir.'/'.$sub;
			$res=syncBranchFromTrunk($repos_in,$bpath,$trunk_root);
			if($res['ok'])
			{
				$ok++;
				$report.="<br>已同步：{$bpath}（用户 {$res['user']} 条，组 {$res['group']} 条）";
			}else
			{
				$fail++;
				$report.="<br>未能同步：{$bpath}（{$res['msg']}）";
			}
		}
	}
	//开启自动同步，后续新增的子目录也会自动同步
	setBranchSync($repos_in,true);
}else
{
	$res=syncBranchFromTrunk($repos_in,$path_in,$trunk_root);
	if($res['ok'])
	{
		$ok++;
		$report.="<br>已同步：{$path_in}（用户 {$res['user']} 条，组 {$res['group']} 条）";
	}else
	{
		$fail++;
		$report.="<br>未能同步：{$path_in}（{$res['msg']}）";
	}
}

if($fail>0)
{
	echo "<p style='text-align:center;line-height:2;border:solid 1px;background:#fff1f0;margin-top:60px;'>";
	echo "<br>同步完成，成功 {$ok} 个，失败 {$fail} 个。{$report}";
	echo "<br><br><a href=\"$url\">返回</a></p>";
	exit;
}
echo <<<HTML
<p style='text-align:center;line-height:2;border:solid 1px;background:#ecf0e1;margin-top:60px;'>
<br>已从 {$trunk_root} 同步权限，共 {$ok} 个目录。{$report}
HTML;
if($all)echo "<br><br><strong>已开启 branches 自动同步</strong>：以后 branches 下新增的子目录会自动同步 trunk 权限。";
echo <<<HTML
<br>
<br><a href="$url">返回继续操作</a> <br>或者：
<a href="./gen_access.php?fromurl=$url">立刻生效（生成access文件)</a>?
</p>
HTML;
?>
