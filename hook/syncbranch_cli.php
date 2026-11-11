<?php
/*
 * 命令行脚本：把指定项目 branches 下分支目录的权限，同步为 trunk 对应目录的权限
 * 供 svn post-commit 钩子（hook/syncbranch.sh）或 crontab 调用
 *
 * 示例：
 *   php hook/syncbranch_cli.php mysvn
 *   php hook/syncbranch_cli.php --repos-path /svn/mysvn --branch feat1
 *   php hook/syncbranch_cli.php mysvn --force --enable
 */
if(php_sapi_name()!='cli')
{
	header('HTTP/1.1 403 Forbidden');
	echo "本脚本只允许在命令行下运行。";
	exit;
}
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root=dirname(dirname(__FILE__));

function cli_usage()
{
	echo <<<TXT
用法: php syncbranch_cli.php [选项] <项目名>

  --project <名称>     项目名（也可直接写在最后，如：syncbranch_cli.php mysvn）
  --repos-path <路径>  仓库在服务器上的绝对路径，如 /svn/mysvn（未指定项目名时取目录名）
  --branch <分支名>    只同步 branches/<分支名>，可重复指定；不指定则处理 branches 下所有分支
  --force              分支已有权限时也用 trunk 覆盖（默认跳过，避免冲掉手工授权）
  --enable             同步成功后顺带开启网页端“自动同步trunk权限”开关
  --no-access          同步后不重新生成 access 权限文件
  --quiet              不输出成功信息，只输出失败信息

权限来源固定为项目的 /trunk 目录，目标为 /branches/<分支>（含其子目录）。
分支目录本身也会被设置为与 trunk 相同的权限（trunk 从上级继承来的权限一并落到分支目录上）。

TXT;
}
function cli_log($msg,$quiet=false)
{
	if(!$quiet)echo "[".date('Y-m-d H:i:s')."] $msg\n";
}
function cli_err($msg)
{
	fwrite(STDERR,"[".date('Y-m-d H:i:s')."] $msg\n");
}

/* ---------------- 解析参数 ---------------- */
$project='';
$repos_path='';
$only=array();
$force=false;
$enable=false;
$gen_access=true;
$quiet=false;
$args=$argv;
array_shift($args);
while(count($args)>0)
{
	$a=array_shift($args);
	switch($a)
	{
		case '-h':
		case '--help':
			cli_usage();
			exit(0);
		case '--project':
			$project=trim((string)array_shift($args));
			break;
		case '--repos-path':
			$repos_path=trim((string)array_shift($args));
			break;
		case '--branch':
			$b=trim((string)array_shift($args)," \t/");
			if($b!='')$only[]=$b;
			break;
		case '--force':
			$force=true;
			break;
		case '--enable':
			$enable=true;
			break;
		case '--no-access':
			$gen_access=false;
			break;
		case '--quiet':
			$quiet=true;
			break;
		default:
			if(substr($a,0,2)=='--')
			{
				cli_err("未知选项: $a");
				exit(2);
			}
			if($project=='')$project=$a;
			else
			{
				cli_err("多余的参数: $a");
				exit(2);
			}
	}
}
if($project=='' and $repos_path=='')
{
	cli_usage();
	exit(2);
}

/* ---------------- 解析仓库路径 ---------------- */
if($repos_path!='')
{
	$repos_path=rtrim($repos_path,'/');
	if($repos_path=='')
	{
		cli_err("仓库路径不合法。");
		exit(2);
	}
	if(!is_dir($repos_path))
	{
		cli_err("仓库目录不存在: $repos_path");
		exit(2);
	}
	if($project=='')$project=basename($repos_path);
}

/* ---------------- 加载配置与函数 ---------------- */
if(!file_exists($root.'/config/config.php'))
{
	cli_err("缺少 config/config.php，请先在网页端完成系统设置。");
	exit(1);
}
include($root.'/config/config.php');
include($root.'/config.inc');
include($root.'/include/basefunction.php');
//钩子会把仓库真实路径传进来，用它覆盖 svn 父目录，保证能正确枚举分支目录
if($repos_path!='')$svnparentpath=dirname($repos_path);
include($root.'/include/dbconnect.php');

/* ---------------- 计算要同步的分支 ---------------- */
$branches=listBranchDirs($project,'/branches');
if(empty($branches))
{
	cli_log("项目 $project 的 branches 目录下没有找到分支，无需同步。",$quiet);
	exit(0);
}
$targets=$branches;
if($only)
{
	$missing=array_diff($only,$branches);
	foreach($missing as $m)cli_err("分支不存在，已跳过: $project/branches/$m");
	$targets=array_intersect($branches,$only);
	$targets=array_values($targets);
	if(empty($targets))
	{
		cli_err("没有可同步的分支。");
		exit(1);
	}
}

/* ---------------- 逐个分支同步 ---------------- */
$ok=0;
$skip=0;
$fail=0;
foreach($targets as $b)
{
	$bpath='/branches/'.$b;
	if(!$force and pathHasPermission($project,$bpath))
	{
		$skip++;
		cli_log("跳过 $project$bpath：已设置过权限（如需强制覆盖请加 --force）",$quiet);
		continue;
	}
	$res=syncBranchFromTrunk($project,$bpath,'/trunk');
	if($res['ok'])
	{
		$ok++;
		cli_log("已同步 $project$bpath：用户 {$res['user']} 条，组 {$res['group']} 条",$quiet);
	}else
	{
		$fail++;
		cli_err("同步失败 $project$bpath：{$res['msg']}");
	}
}

if($enable and ($ok>0 or $skip>0))setBranchSync($project,true);
if($ok>0)touchBranchSync($project);

/* ---------------- 重新生成 access 权限文件，使改动生效 ---------------- */
if($gen_access and $ok>0)
{
	//gen_access.php 依赖全局变量与全局作用域，这里必须在全局作用域中引入
	$scheme=true;
	$old_cwd=getcwd();
	@chdir($root.'/priv');
	ob_start();
	include($root.'/priv/gen_access.php');
	$access_out=ob_get_clean();
	@chdir($old_cwd);
	if(strpos($access_out,'权限生效成功')!==false)
	{
		cli_log("已重新生成 access 权限文件",$quiet);
	}else
	{
		cli_err("重新生成 access 权限文件失败：".trim(strip_tags($access_out)));
	}
}

$summary="项目 $project：成功 $ok 个，跳过 $skip 个，失败 $fail 个";
if($fail>0)cli_err($summary);
else cli_log($summary,$quiet);
exit($fail>0?1:0);
