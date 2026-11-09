<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../../css/mobile.css" type="text/css">
<script type="text/javascript" src="../../js/jquery-1.4.2.min.js"></script>
<script>
$(document).ready(function(){
	$.get("review.php",{},function(data){
		});
});

function submit(status,user_id,group_id,group_name) {
	var mail_back = prompt("请输入回执信息");
	$.get("reviewAction.php",{"status":status,"user_id":user_id,"group_id":group_id,"mail_back":mail_back,"group_name":group_name},function(data){
		alert(data);
		location.reload() ;
		});
}
</script>

</head>
<table cellspacing="0" bordercolordark="#efefef" width="20%"

bordercolorlight="#333333" border="0">
    <tbody id="table">
        <tr bgcolor="#cccccc">
            <td>申请者</td>
            <td>申请加入组</td>
            <td>
            </td>
        </tr>
        <?php
        session_start();
        include("../../include/charset.php");
        include('../../config.inc');
        include('../../include/dbconnect.php');

        $query = "SELECT a.* ,b.user_name FROM svnauth_user b LEFT JOIN svnauth_groupuser a on a.user_id = b.user_id where a.status=0 group by a.apply_time desc";
        $query_grop_name = "SELECT a.* ,b.group_name FROM svnauth_group b LEFT JOIN svnauth_groupuser a on a.group_id = b.group_id where a.status=0 group by a.apply_time desc";
        $result = mysql_query($query);
        $result_grop_name = mysql_query($query_grop_name);
        $i=0;
        $list_arr_username=array();
        while($list=mysql_fetch_array($result,MYSQL_ASSOC)){//这一句，就是判断是否还有数据没有取完，如果取完，会由数据库返回false，停止while循环，如果有，则继续;
            $list_arr_username[]=$list;
            $i++;
        }
        $i=0;
        $list_arr_group_name=array();
        while($list=mysql_fetch_array($result_grop_name,MYSQL_ASSOC)){//这一句，就是判断是否还有数据没有取完，如果取完，会由数据库返回false，停止while循环，如果有，则继续;
            $list_arr_group_name[]=$list;
            $i++;
        }

        for($i=0; $i<count($list_arr_username); $i++) {
            echo "<tr bgcolor=''>
            <td>".$list_arr_username[$i]['user_name']."</td>
            <td>".$list_arr_group_name[$i]['group_name']."</td>
            <td><input type='button' id='yes' onClick='submit(1,".$list_arr_username[$i]['user_id'].",".$list_arr_username[$i]['group_id'].",\"{$list_arr_group_name[$i]['group_name']}\")' value='同意' /><input type='button' id='no'onClick='submit(2,".$list_arr_username[$i]['user_id'].",".$list_arr_username[$i]['group_id'].",\"{$list_arr_group_name[$i]['group_name']}\")' value='拒绝' /></td>
        </tr>";
        }
    ?>

    </tbody>
</table>
<p>&nbsp;</p>
</html>
