<?php
include('../../config.inc');
//include('../../../../config.inc');
include('../../config/config.php');
include('../../include/dbconnect.php');
session_start();
//request();
/*$toemail = 'lixinnan@com4loves.com';
$subject = '您的svn权限申请已处理';
$body = 'Hi,lixinnan

        你对http://203.195.189.235//ops// 的svn(只读)权限申请已被 root 拒绝，回执如下：申请诶手动拒绝

        这只是一封系统自动发出的邮件，请勿回复。
        --------------------
        配置管理组 ';
include("../../include/email.php");
$sendinfo =send_mail($toemail,$subject,$body);
echo $sendinfo;
exit;
*/
    $optype = $_GET['status'];
    $permission = $_GET['permission'];
    $path = $_GET['path'];
    $id =$_GET['id'];
    $ops = $_SESSION['username'];
    $mail_back = $_GET['mail_back'];
    //有效期默认为不设置
    $expire_t='none';
    //*****记录过程
    $query="delete from svn_hex where id=$id";
    $result =mysql_query($query);
    if (mysql_affected_rows() == 0){
        echo "请求已被他人处理完毕";
        exit;
    }

    include("../../include/email.php");
    $query_priv="update rt_svnpriv set ops='$ops',optype='$optype' where id=$id";

    $relust_priv = mysql_query($query_priv);
    $row_priv = mysql_affected_rows();
    if($row_priv == '0') {
        echo "处理失败，请重试";
        exit;
    }
    $query_detail="select username,repository,path,permission,email,ops from  rt_svnpriv where id=$id";
    $result_detail=mysql_query($query_detail);

    if($result_detail)
    {
        $row= mysql_fetch_array($result_detail, MYSQL_BOTH);
        $b_email=$row['email'];
        $us=$row['username'];
        $repos=$row['repository'];
        $path=$row['path'];
        $wpriv=$row['permission'];
        ('w'==$wpriv)?($priv='读写'):($priv='只读');
        $ops=$row['ops'];
    }
    $subject="您的svn权限申请已处理";
    $windid="svn-rt";
    // 发送邮件
    if('denied'==$optype)
    {
        echo "处理成功！";
        //发邮件通知
        $body="Hi,$us\n
        你对$svnurl/$repos/$path 的svn($priv)权限申请已被 $ops 拒绝，回执如下：$mail_back

        这只是一封系统自动发出的邮件，请勿回复。
        --------------------
        配置管理组
        ";
        $sendinfo =send_mail($b_email,$subject,$body);

        exit;
    }

    if('other'==$optype)
    {
        echo "已给申请者发送回执，请登录权限系统进行处理！";
        //echo "<script>setTimeout('document.location.href=\"../../default.htm\"',5)</script>";//跳转
        $body="Hi,$us\n
        你对$svnurl/$repos/$path 的svn($priv)权限申请已被 $ops 手工处理，回执如下：$mail_back

        这只是一封系统自动发出的邮件，请勿回复。
        --------------------
        配置管理组
        ";
              $sendinfo =send_mail($b_email,$subject,$body);
    	  exit;
      }

      //*****开始处理权限
      $query="select user_id from svnauth_user where user_name='$us'";
      $result=mysql_query($query);
      if($result)
      {
          $row= mysql_fetch_array($result, MYSQL_BOTH);
          $uid=$row['user_id'];
      }

      if('none'==$expire_t)
      {
          switch($wpriv)
          {
           case 'r':
               $expire=mktime(0, 0, 0, date("m")  , date("d")+$read_t, date("Y"));
               break;
           case 'w':
               $expire=mktime(0, 0, 0, date("m")  , date("d")+$write_t, date("Y"));
               break;
           default:
               $expire=mktime(0, 0, 0, date("m")  , date("d"), date("Y")+2);
          }
      }else if('other'==$expire_t)
      {
          $my_t=trim($_POST['expire_o']);
          if(! is_numeric($my_t))
          {
              echo "有效期输入错误，必须输入数字！";
              exit;
          }
          $expire=mktime(0, 0, 0, date("m")  , date("d")+$my_t, date("Y"));
      }else
      {
          if(! is_numeric($expire_t))
          {
              echo "有效期不是数字，参数获取错误！请重试！";
              exit;
          }
          $expire=mktime(0, 0, 0, date("m")  , date("d")+$expire_t, date("Y"));
      }

      $expire=strftime("%Y-%m-%d",$expire);
      $query="update svnauth_permission set permission='$wpriv',expire='$expire' where  repository='$repos' and path = '$path' and user_id=$uid";
      mysql_query($query);
      if (mysql_affected_rows() == 0){
          $query="insert into svnauth_permission (user_id,repository,path,permission,expire) values($uid,'$repos','$path','$wpriv','$expire'); ";
          mysql_query($query);
      }
      $scheme=true;
      $backpath="$svnurl/$repos/$path";
      @include('../../priv/gen_access.php');
      //echo "处理成功！";
      //发邮件通知
      $body="Hi,$us\n
      你对 $backpath 的svn($priv)权限申请已被 $ops 批准，回执如下：$mail_back

      这只是一封系统自动发出的邮件，请勿回复。
      --------------------
      配置管理组
      ";

      $sendinfo =send_mail($b_email,$subject,$body);
      exit;
