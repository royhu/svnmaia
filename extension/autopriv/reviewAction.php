<?php
header("content-type:text/html; charset=utf-8");
session_start();
    include("../../include/charset.php");
    include('../../config.inc');
    include('../../include/dbconnect.php');
    include('../../include/basefunction.php');
    include('../../config/config.php');
    include("../../include/email.php");



    $status = $_REQUEST['status'];
    $sign = $_REQUEST['sign'];
    $mail_back = $_REQUEST['mail_back'];
    // 如果通过邮件发来的信息进行一系列验证
    if ($sign){
    $user_id = urldecode(base64_decode($_REQUEST['user_id']));
    $group_id = urldecode(base64_decode($_REQUEST['group_id']));
    $group_name = urldecode(base64_decode($_REQUEST['group_name']));

    // 判断操作员是否登录
    if (empty($_SESSION)){
        $host =$_SERVER['HTTP_HOST'];
        echo "请先登录系统然后进行操作";
        echo <<<HTML
        <a href="http://$host" target="_blank" >点击： $host 去登陆</a>;
HTML;
        exit;
    }
    // 判断登录用户是否为该组管理员
    $group_admin = "SELECT user_id FROM svnauth_groupuser where group_id = '$group_id' and isowner='1'";
    $group_admin_reslut = mysql_query($group_admin);
    //$groupAdminData = mysql_fetch_array($group_admin_reslut,MYSQL_NUM);

    $groupAdminData = array();

    while($list = mysql_fetch_array($group_admin_reslut,MYSQL_NUM)) {
        $groupAdminData[] = $list;
        $i++;
    }
    foreach ($groupAdminData as $key => $vaule) {
          if (in_array($_SESSION['uid'],$vaule)){
              $role = "ture";
          }
    }

    if ($role != "ture") {
        if ($_SESSION['role'] != 'admin'){
            die("您无权进行此操作.");
        }

    }

    // 验证参数是否被改变
    if ($sign != urldecode(urlencode(base64_encode($user_id)).urlencode(base64_encode($group_id)).urlencode(base64_encode($group_name)))) {
        die("请求参数非法！");
    }
    }
    else {
         $user_id = $_REQUEST['user_id'];
         $group_id = $_REQUEST['group_id'];
         $group_name = $_REQUEST['group_name'];
         // 判断登录用户是否为该组管理员
         $group_admin = "SELECT user_id FROM svnauth_groupuser where group_id = '$group_id' and isowner='1'";
         $group_admin_reslut = mysql_query($group_admin);
         //$groupAdminData = mysql_fetch_array($group_admin_reslut,MYSQL_NUM);

         $groupAdminData = array();

         while($list = mysql_fetch_array($group_admin_reslut,MYSQL_NUM)) {
             $groupAdminData[] = $list;
             $i++;
         }
         foreach ($groupAdminData as $key => $vaule) {
             if (in_array($_SESSION['uid'],$vaule)){
                 $role = "ture";
             }
         }

         if ($role != "ture" && $_SESSION['role'] != 'admin') {
             die("您无权进行此操作.");
         }

    }
    // 验证申请是否已经被处理
    $query_findapply ="SELECT `status` FROM svnauth_groupuser where user_id = $user_id and group_id = $group_id";
    $isapply = mysql_fetch_array($query_findapply,MYSQL_ASSOC);
    $isapply = $isapply['status'];
    if ($isapply) {
        $row = "请求已被处理！";
        return $row;
    }
    if($status ==1) {
    $query = "update svnauth_groupuser set status = $status,mail_back = '$mail_back' where user_id=$user_id and group_id=$group_id";
    }
    if($status ==2) {
        $query = "delete from svnauth_groupuser where user_id=$user_id and group_id=$group_id";
    }
    $result = mysql_query($query);
    $row = mysql_affected_rows();

    if ($status ==1 && $row ==1)
    {
        $reviews = "同意";
    }
    if ($status ==2 && $row ==1)
    {
        $reviews = "拒绝";
    }
    if($row ==1){
        $row ="操作成功！";
        updateGroupLastUsed($group_id);
        @include('../../priv/gen_access.php');
    }
    else {
        $row = "操作失败请重试";
        return $row;
    };
    // 通过邮件发送结果
    // 查询申请人的email
    $user_email = "SELECT email from svnauth_user where user_id = $user_id";
    $user_email = mysql_query($user_email);
    $email_result = mysql_fetch_array($user_email,MYSQL_ASSOC);
    $email = $email_result['email'];

    $body="Hello\n
            你对权限组：$group_name 的申请已被 管理员 $reviews ，回执如下：$mail_back

            这只是一封系统自动发出的邮件，请勿回复。
            --------------------
            配置管理组
    ";
    $subject="权限组审核结果";
    $sendinfo =send_mail($email,$subject,$body);
    exit;


