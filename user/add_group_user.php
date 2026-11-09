<?php
    include('../config.inc');
    //include('../../../../config.inc');
    include('../config/config.php');
    include('../include/dbconnect.php');
    include("../include/email.php");
    //$link = mysql_connect('127.0.0.1:3306','root','1q2w3e4rr4e3w2q1')or die("数据库链接失败！请联系管理员");
    //mysql_set_charset('utf8', $link);
    //$db_selected = mysql_select_db('svn', $link) or die ("数据库选择失败");
    $user_id = $_GET['user'];
    $group = $_GET['group'];
    $apply_time = time();
    // 插入数据库
    $query = "INSERT into svnauth_groupuser (group_id,user_id,status,apply_time) VALUES ('$group','$user_id','0',$apply_time)";
    $result = mysql_query($query);
    $row = mysql_affected_rows();
    //return $row;

    //查询申请人信息
    $query_apply_user = "SELECT user_name,full_name FROM svnauth_user where user_id = $user_id";
    $result_apply_user = mysql_query($query_apply_user);
    $apply_user = mysql_fetch_array($result_apply_user,MYSQL_ASSOC);
    $apply_user = $apply_user['user_name'];
    $full_name = $apply_user['full_name'];

    // 组织发邮件需要的信息
    // 根据组id查询组名
    $group_name = "SELECT group_name FROM svnauth_group where group_id = $group";
    $result_group_name = mysql_query($group_name);
    $group_name = mysql_fetch_array($result_group_name,MYSQL_ASSOC);
    $group_name = $group_name['group_name'];

    // 查询该组的组管理员
    $groupAdmin = "SELECT a.*,b.email,b.user_name FROM svnauth_groupuser a LEFT JOIN svnauth_user b ON a.user_id = b.user_id where a.group_id = $group and a.isowner=1";
    $result_groupadmin = mysql_query($groupAdmin);
    $i = 0;
    $groupAdminData = array();
    while($list = mysql_fetch_array($result_groupadmin,MYSQL_ASSOC)) {
        $groupAdminData[] = $list;
        $i++;
    }
    $addr=$_SERVER['REMOTE_ADDR'];
    // 生成处理链接
    $user_id2 = urlencode(base64_encode($user_id));
    $group2 = urlencode(base64_encode($group));
    $group_name2 = urlencode(base64_encode($group_name));
    $sign = $user_id2.$group2.$group_name2;
    $url = "http://".$_SERVER['HTTP_HOST']. rtrim(dirname($_SERVER['PHP_SELF']))."/review_mail.php?user_id=$user_id2&group_id=$group2&group_name=$group_name2&sign=$sign";

    for($i = 0; $i<count($groupAdminData); $i++){
    //生成头信息
    $user_name = $groupAdminData[$i]['user_name'];
    $body="Hi,$user_name\n
    $apply_user $full_name 申请svn访问权限，需要您的处理，详情如下：
                申请组名：$group_name

                无论您是同意还是拒绝，请点击如下链接进行处理：
               $url

                如果通过点击以上链接无法访问，请将该网址复制并粘贴至新的浏览器窗口中。

                这只是一封系统自动发出的邮件，我们不监控它的发送。

                触发此邮件来自IP:$addr

                --------------------
                配置管理组\n
    ";

    $subject="权限组申请";
    //发送邮件
    $mail = $groupAdminData[$i]['email'];
    $sendinfo =send_mail($mail,$subject,$body);
    if ($sendinfo === true) {
        echo "<br>申请已发出至 $mail";
    }else {
        echo(is_string($sendinfo) ? $sendinfo : ' 发送失败:'.$mail);
    }
    }

