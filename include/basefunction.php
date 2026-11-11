<?php
function keygen($para)
{
	ksort($para);
	if(! defined('SECRET_KEY'))define("SECRET_KEY","0273cdbfewy243744540c3gs26f74e4b");
	$str=SECRET_KEY;
	foreach($para as $k => $v){   
         $str .= $k.'='.$v;   
        }
        return sha1($str);
}
function genSalt ()
{
		$random = 0;
		$rand64 = "";
		$salt = "";

		$random=rand();	// Seeded via initialize()

		// Crypt(3) can only handle A-Z a-z ./

		$rand64= "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
		$salt=substr($rand64,$random  %  64,1).substr($rand64,($random/64) % 64,1);
		$salt=substr($salt,0,2); // Just in case

		return($salt);

}
function cryptMD5Pass($plainpasswd,$salt="") {
  if ($salt =="") {
	            $salt = substr(str_shuffle("./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"), 0, 8);
  } else {
            if (substr($salt, 0, 6) == '$apr1$') {
	                $salt = substr($salt, 6, 8);
            } else {
	                $salt = substr($salt, 0, 8);
            }
  }
  $len = strlen($plainpasswd);
  $text = $plainpasswd.'$apr1$'.$salt;
  $bin = pack("H32", md5($plainpasswd.$salt.$plainpasswd));
  for($i = $len; $i > 0; $i -= 16) { $text .= substr($bin, 0, min(16, $i)); }
  for($i = $len; $i > 0; $i >>= 1) { $text .= ($i & 1) ? chr(0) : $plainpasswd{0}; }
  $bin = pack("H32", md5($text));
  for($i = 0; $i < 1000; $i++) {
      $new = ($i & 1) ? $plainpasswd : $bin;
      if ($i % 3) $new .= $salt;
      if ($i % 7) $new .= $plainpasswd;
      $new .= ($i & 1) ? $bin : $plainpasswd;
      $bin = pack("H32", md5($new));
  }
  $tmp = "";
  for ($i = 0; $i < 5; $i++) {
      $k = $i + 6;
      $j = $i + 12;
      if ($j == 16) $j = 5;
      $tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
  }
  $tmp = chr(0).chr(0).$bin[11].$tmp;
  $tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
	        "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
	        "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
  return '$apr1$'.$salt.'$'.$tmp;
 }


function genPass ($para_arr,$hash='DES')
{
	if ($hash == 'SHA') {
		return ('{SHA}'.base64_encode(pack('H*',sha1($para_arr['passwd']))));
		//return ('{SHA}' . base64_encode(sha1($para_arr['passwd'], TRUE)) );
	}
	if ($hash == 'MD5') {
	  return (cryptMD5Pass($para_arr['passwd'],$para_arr['salt']));
	}
	if($hash == 'DES'){
		return md5($para_arr['user'] . ':' . $para_arr['realm'] . ':' .$para_arr['passwd']);
	}


		if (!($passwd))
		{
			// Return what we were given

			// If calling this directly, do something like
			// $enc_pass = $Htpasswd->cryptPass($pass);
			// if (empty($enc_pass)) { BARF! }

			// You should really verify the data before calling
			// this though - I do.			

			return "";

		}

		if (!empty($salt))
		{
			//# Make sure only use 2 chars

			$salt = substr ($salt, 0, 2);
		}
		else
		{
			// If no salt, generate a (pseudo) random one

			$salt =genSalt();
		}

		return (crypt($passwd, $salt));

} // end cryptPass
function verifyPasswd($inputPass,$correctPass)
{
	if(empty($correctPass))return false;
	if (substr($correctPass, 0, 6) == '$apr1$') {
	            if (cryptMD5Pass($inputPass, $correctPass) == $correctPass) {
	                return true;
	            }
	            return false;
	}
	if (substr($correctPass, 0, 6) == '{SHA}') {
		$para_arr=array('passwd'=>$inputPass);
		if(genPass($para_arr,'SHA') == $correctPass)
			return true;
		return false;
	}
	if (crypt($inputPass, $correctPass) == $correctPass) {
	            return true;
	}
	return false;	
}
function checkUserGroup($str)
{
	$p="/^[\w._\-\/]{2,50}$/";
	if(preg_match($p,$str))
	{
		return true;
	}else
		return false;
}
function isSamplePassword($password,$username=null)
{
        if(strpos($password,$username) !== false)return true;
        if (strlen($password)<6) return true;
        if(preg_match('/^hello1234$/i',$password))return true;
        if(!preg_match('/[0-9\W]+/',$password)||!preg_match('/[a-zA-Z]+/',$password))return true;
        return false;
       
}
//确保权限组表有 last_used（最近使用时间）字段，返回字段是否可用
function ensureGroupLastUsedField()
{
	static $available=null;
	if($available!==null)return $available;
	$available=false;
	$result=mysql_query("show columns from svnauth_group like 'last_used'");
	if(!$result)return $available;
	if(mysql_num_rows($result)==0)
	{
		mysql_query("ALTER TABLE svnauth_group ADD last_used int(11) default 0");
		$result=mysql_query("show columns from svnauth_group like 'last_used'");
		if(!$result or mysql_num_rows($result)==0)return $available;
	}
	$available=true;
	return $available;
}
//更新权限组的最近使用时间
function updateGroupLastUsed($gids)
{
	if(empty($gids))return;
	if(!ensureGroupLastUsedField())return;
	$ids=array();
	foreach((array)$gids as $gid)
	{
		$gid=trim($gid);
		if(is_numeric($gid))$ids[]=$gid;
	}
	if(empty($ids))return;
	mysql_query("update svnauth_group set last_used=".time()." where group_id in (".implode(',',$ids).")");
}
//权限组排序：最近使用的在前，最久未使用的在后（配合 order by 使用）
function groupOrderSql()
{
	if(!ensureGroupLastUsedField())return "group_name asc";
	return "last_used desc, group_name asc";
}
//确保分支自动同步表存在（记录每个项目是否开启 branches 自动同步）
function ensureBranchSyncTable()
{
	static $available=null;
	if($available!==null)return $available;
	$available=false;
	mysql_query("CREATE TABLE IF NOT EXISTS svnauth_branch_sync (
		repository varchar(120) NOT NULL default '',
		enabled tinyint(1) NOT NULL default 1,
		last_sync int(11) NOT NULL default 0,
		PRIMARY KEY (repository)
	)");
	$result=mysql_query("show tables like 'svnauth_branch_sync'");
	if($result and mysql_num_rows($result)>0)$available=true;
	return $available;
}
//项目是否开启了 branches 自动同步
function branchSyncEnabled($repos)
{
	if(!ensureBranchSyncTable())return false;
	$r=mysql_real_escape_string($repos);
	$result=mysql_query("select enabled from svnauth_branch_sync where repository='$r'");
	if($result and ($row=mysql_fetch_array($result,MYSQL_BOTH)))
		return intval($row['enabled'])==1;
	return false;
}
//设置项目的 branches 自动同步开关
function setBranchSync($repos,$enabled)
{
	if(!ensureBranchSyncTable())return false;
	$r=mysql_real_escape_string($repos);
	$e=($enabled)?1:0;
	mysql_query("replace into svnauth_branch_sync(repository,enabled,last_sync) values('$r',$e,".time().")");
	return true;
}
//记录项目的最近同步时间
function touchBranchSync($repos)
{
	if(!ensureBranchSyncTable())return;
	$r=mysql_real_escape_string($repos);
	mysql_query("update svnauth_branch_sync set last_sync=".time()." where repository='$r'");
}
//已开启自动同步的项目列表
function branchSyncRepos()
{
	$arr=array();
	if(!ensureBranchSyncTable())return $arr;
	$result=mysql_query("select repository from svnauth_branch_sync where enabled=1 order by repository");
	while($result and ($row=mysql_fetch_array($result,MYSQL_BOTH)))
		$arr[]=$row['repository'];
	return $arr;
}
//列出目录下的直接子目录（服务器文件系统）
function listSubDirs($base)
{
	$arr=array();
	if(empty($base) or !is_dir($base))return $arr;
	$sp=@opendir($base);
	if(!$sp)return $arr;
	while(($d=readdir($sp))!==false)
	{
		if($d=='.' or $d=='..')continue;
		if(is_dir($base.'/'.$d))$arr[]=$d;
	}
	closedir($sp);
	sort($arr);
	return $arr;
}
//列出 branches 目录下的分支目录：优先用服务器文件系统，取不到时用 svn list
function listBranchDirs($repos,$branches_dir)
{
	global $svnparentpath,$svn;
	if(empty($svnparentpath))
	{
		if(file_exists(dirname(__FILE__).'/../config/config.php'))
			include(dirname(__FILE__).'/../config/config.php');
	}
	$base=rtrim($svnparentpath,'/').'/'.$repos.$branches_dir;
	$arr=listSubDirs($base);
	if(!empty($arr))return $arr;
	$root=rtrim($svnparentpath,'/');
	if(empty($root))return $arr;
	$localurl=(substr($root,0,1)=='/')?("file://$root/$repos$branches_dir"):("file:///$root/$repos$branches_dir");
	$out=array();
	@exec("{$svn}svn list \"$localurl\"",$out);
	foreach($out as $line)
	{
		$line=trim($line);
		if($line=='' or substr($line,-1)!='/')continue;
		$arr[]=substr($line,0,-1);
	}
	sort($arr);
	return $arr;
}
//目录及其子目录是否已经设置过权限（用户权限或组权限）
function pathHasPermission($repos,$path)
{
	$r=mysql_real_escape_string($repos);
	$b=mysql_real_escape_string(($path=='/')?'/':rtrim($path,'/'));
	$like=$b.'/%';
	$result=mysql_query("select count(*) as cnt from svnauth_permission where repository='$r' and (path='$b' or path like '$like')");
	$row=$result?mysql_fetch_array($result,MYSQL_BOTH):array();
	if(intval($row['cnt'])>0)return true;
	$result=mysql_query("select count(*) as cnt from svnauth_g_permission where repository='$r' and (path='$b' or path like '$like')");
	$row=$result?mysql_fetch_array($result,MYSQL_BOTH):array();
	return intval($row['cnt'])>0;
}
//取某个目录的“生效权限”：本级已设的优先，本级没有的用户/组向上级继承
//返回 array('user'=>array(uid=>array('permission'=>..,'expire'=>..)), 'group'=>array(gid=>permission))
function effectiveDirPermissions($repos_raw,$path)
{
	$repos=mysql_real_escape_string($repos_raw);
	$users=array();
	$groups=array();
	$p=($path=='/')?'/':rtrim($path,'/');
	for($ii=0;$ii<50;$ii++)
	{
		$e=mysql_real_escape_string($p);
		$result=mysql_query("select user_id,permission,expire from svnauth_permission where repository='$repos' and path='$e'");
		while($result and ($row=mysql_fetch_array($result,MYSQL_BOTH)))
		{
			$uid=intval($row['user_id']);
			if($uid<=0 or isset($users[$uid]))continue;
			$users[$uid]=array('permission'=>$row['permission'],'expire'=>$row['expire']);
		}
		$result=mysql_query("select group_id,permission from svnauth_g_permission where repository='$repos' and path='$e'");
		while($result and ($row=mysql_fetch_array($result,MYSQL_BOTH)))
		{
			$gid=intval($row['group_id']);
			if($gid<=0 or isset($groups[$gid]))continue;
			$groups[$gid]=$row['permission'];
		}
		if($p=='/' or $p=='')break;
		$p=str_replace('\\','/',dirname($p));
		if($p=='')$p='/';
	}
	return array('user'=>$users,'group'=>$groups);
}
//把 trunk_root（含其子目录）的权限复制到 branch_root（含其子目录），覆盖分支原有权限
function syncBranchFromTrunk($repos_raw,$branch_root,$trunk_root)
{
	$repos=mysql_real_escape_string($repos_raw);
	$branch_root=($branch_root=='/')?'/':rtrim($branch_root,'/');
	$trunk_root=($trunk_root=='/')?'/':rtrim($trunk_root,'/');
	$b=mysql_real_escape_string($branch_root);
	$t=mysql_real_escape_string($trunk_root);
	$t_like=$t.'/%';
	$b_like=$b.'/%';
	//trunk 目录的生效权限（本级 + 从上级继承），用于保证分支目录本身的权限与 trunk 相同
	$eff=effectiveDirPermissions($repos_raw,$trunk_root);
	//trunk 本级、子目录、继承链上都没有任何权限时，拒绝执行，避免误清空分支权限
	$result=mysql_query("select count(*) as cnt from svnauth_permission where repository='$repos' and (path='$t' or path like '$t_like')");
	$row=$result?mysql_fetch_array($result,MYSQL_BOTH):array();
	$cnt=intval($row['cnt']);
	$result=mysql_query("select count(*) as cnt from svnauth_g_permission where repository='$repos' and (path='$t' or path like '$t_like')");
	$row=$result?mysql_fetch_array($result,MYSQL_BOTH):array();
	$cnt+=intval($row['cnt']);
	if($cnt==0 and empty($eff['user']) and empty($eff['group']))
		return array('ok'=>false,'msg'=>"trunk 目录 {$trunk_root} 没有任何权限（含从上级继承的），无法同步。",'user'=>0,'group'=>0);
	$err='';
	//清空分支目录及其子目录的权限
	mysql_query("delete from svnauth_permission where repository='$repos' and (path='$b' or path like '$b_like')");
	$err.=mysql_error();
	mysql_query("delete from svnauth_g_permission where repository='$repos' and (path='$b' or path like '$b_like')");
	$err.=mysql_error();
	$uc=0;
	$gc=0;
	$used_gids=array();
	//trunk 子目录的权限：按相对路径搬到分支对应子目录
	$result=mysql_query("select user_id,path,permission,expire from svnauth_permission where repository='$repos' and path like '$t_like'");
	while($result and ($row=mysql_fetch_array($result,MYSQL_BOTH)))
	{
		$rel=substr($row['path'],strlen($trunk_root));
		$newpath=mysql_real_escape_string($branch_root.$rel);
		$uid=intval($row['user_id']);
		if($uid<=0)continue;
		$permission=mysql_real_escape_string($row['permission']);
		$expire=mysql_real_escape_string($row['expire']);
		mysql_query("insert into svnauth_permission(user_id,repository,path,permission,expire)values($uid,'$repos','$newpath','$permission','$expire')");
		$err.=mysql_error();
		$uc++;
	}
	$result=mysql_query("select group_id,path,permission from svnauth_g_permission where repository='$repos' and path like '$t_like'");
	while($result and ($row=mysql_fetch_array($result,MYSQL_BOTH)))
	{
		$rel=substr($row['path'],strlen($trunk_root));
		$newpath=mysql_real_escape_string($branch_root.$rel);
		$gid=intval($row['group_id']);
		if($gid<=0)continue;
		$permission=mysql_real_escape_string($row['permission']);
		$used_gids[]=$gid;
		mysql_query("insert into svnauth_g_permission(group_id,repository,path,permission)values($gid,'$repos','$newpath','$permission')");
		$err.=mysql_error();
		$gc++;
	}
	//分支目录本身：用 trunk 的“生效权限”（本级 + 从上级继承）重写，保证与 trunk 完全相同
	foreach($eff['user'] as $uid=>$info)
	{
		$permission=mysql_real_escape_string($info['permission']);
		$expire=mysql_real_escape_string($info['expire']);
		mysql_query("insert into svnauth_permission(user_id,repository,path,permission,expire)values($uid,'$repos','$b','$permission','$expire')");
		$err.=mysql_error();
		$uc++;
	}
	foreach($eff['group'] as $gid=>$permission)
	{
		$permission=mysql_real_escape_string($permission);
		$used_gids[]=$gid;
		mysql_query("insert into svnauth_g_permission(group_id,repository,path,permission)values($gid,'$repos','$b','$permission')");
		$err.=mysql_error();
		$gc++;
	}
	updateGroupLastUsed($used_gids);
	if($err!='')return array('ok'=>false,'msg'=>$err,'user'=>$uc,'group'=>$gc);
	return array('ok'=>true,'msg'=>'','user'=>$uc,'group'=>$gc);
}
//把 branches 下新出现（还没有设置过权限）的子目录自动同步为 trunk 的权限
function autoSyncNewBranchDirs($repos)
{
	$done=0;
	$failed=0;
	$subs=listBranchDirs($repos,'/branches');
	foreach($subs as $sub)
	{
		$path='/branches/'.$sub;
		if(pathHasPermission($repos,$path))continue;
		$res=syncBranchFromTrunk($repos,$path,'/trunk');
		if($res['ok'])$done++;else $failed++;
	}
	return array($done,$failed);
}

?>
