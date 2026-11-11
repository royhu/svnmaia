<?php
/*
 * 权限同步的 web 接口：供 hook/syncbranch.sh 在目标机器没有 php 命令行时通过 curl 调用
 *
 * POST 参数：
 *   repos     项目（仓库）名，必填
 *   token     令牌，必填，值 = sha1(SECRET_KEY.'|syncbranch_api|'.repos)
 *   branch    只同步 branches/<branch>，可重复；不传则处理 branches 下所有分支
 *   force     1 时，分支已有权限也用 trunk 覆盖
 *   enable    1 时，顺带开启网页端“自动同步trunk权限”开关
 *   noaccess  1 时，同步后不重新生成 access 权限文件
 *   quiet     1 时，不输出成功信息
 *
 * 输出：纯文本，最后一行 RESULT:OK 或 RESULT:FAIL
 */
error_reporting(0);
$root=dirname(dirname(__FILE__));
include($root.'/include/charset.php');
header('Content-Type: text/plain; charset=utf-8');

function api_out($msg)
{
	echo $msg."\n";
}
function api_fail($msg)
{
	api_out($msg);
	api_out('RESULT:FAIL');
	exit;
}

if(isset($_SERVER['REQUEST_METHOD']) and strtoupper($_SERVER['REQUEST_METHOD'])!='POST')
	api_fail('本接口只接受 POST 请求。');

if(!file_exists($root.'/config/config.php'))
	api_fail('缺少 config/config.php，请先在网页端完成系统设置。');
include($root.'/config/config.php');
include($root.'/config.inc');
include($root.'/include/basefunction.php');
include($root.'/include/dbconnect.php');

$repos_in=isset($_POST['repos'])?trim($_POST['repos']):'';
if($repos_in=='')api_fail('缺少参数 repos。');
if(strpos($repos_in,'/')!==false or strpos($repos_in,'..')!==false or strpos($repos_in,"\0")!==false)
	api_fail('参数 repos 不合法。');

$token=isset($_POST['token'])?trim($_POST['token']):'';
if(!defined('SECRET_KEY'))api_fail('缺少 SECRET_KEY 配置。');
if($token==='' or $token!==sha1(SECRET_KEY.'|syncbranch_api|'.$repos_in))
	api_fail('令牌校验失败。');

$only=array();
if(isset($_POST['branch']))
{
	foreach((array)$_POST['branch'] as $b)
	{
		$b=trim($b," \t\r\n/");
		if($b!='')$only[]=$b;
	}
}
$force=!empty($_POST['force']);
$enable=!empty($_POST['enable']);
$gen_access=empty($_POST['noaccess']);
$quiet=!empty($_POST['quiet']);

/* ---------------- 计算要同步的分支 ---------------- */
$branches=listBranchDirs($repos_in,'/branches');
if(empty($branches))
{
	if(!$quiet)api_out("项目 $repos_in 的 branches 目录下没有找到分支，无需同步。");
	api_out('RESULT:OK');
	exit;
}
$targets=$branches;
if($only)
{
	foreach(array_diff($only,$branches) as $m)
		api_out("分支不存在，已跳过: $repos_in/branches/$m");
	$targets=array_values(array_intersect($branches,$only));
	if(empty($targets))api_fail('没有可同步的分支。');
}

/* ---------------- 逐个分支同步 ---------------- */
$ok=0;
$skip=0;
$fail=0;
foreach($targets as $b)
{
	$bpath='/branches/'.$b;
	if(!$force and pathHasPermission($repos_in,$bpath))
	{
		$skip++;
		if(!$quiet)api_out("跳过 $repos_in$bpath：已设置过权限（如需强制覆盖请加 --force）");
		continue;
	}
	$res=syncBranchFromTrunk($repos_in,$bpath,'/trunk');
	if($res['ok'])
	{
		$ok++;
		if(!$quiet)api_out("已同步 $repos_in$bpath：用户 {$res['user']} 条，组 {$res['group']} 条");
	}else
	{
		$fail++;
		api_out("同步失败 $repos_in$bpath：{$res['msg']}");
	}
}

if($enable and ($ok>0 or $skip>0))setBranchSync($repos_in,true);
if($ok>0)touchBranchSync($repos_in);

/* ---------------- 重新生成 access 权限文件 ---------------- */
if($gen_access and $ok>0)
{
	//gen_access.php 使用相对路径引入文件，且依赖全局变量，这里必须切到 priv 目录并在全局作用域引入
	$scheme=true;
	$old_cwd=getcwd();
	@chdir($root.'/priv');
	ob_start();
	include($root.'/priv/gen_access.php');
	$access_out=ob_get_clean();
	@chdir($old_cwd);
	if(strpos($access_out,'权限生效成功')!==false)
	{
		if(!$quiet)api_out('已重新生成 access 权限文件。');
	}else
	{
		api_out('重新生成 access 权限文件失败：'.trim(strip_tags($access_out)));
	}
}

api_out("项目 $repos_in：成功 $ok 个，跳过 $skip 个，失败 $fail 个");
api_out($fail>0?'RESULT:FAIL':'RESULT:OK');
