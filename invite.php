<?php
require "include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();
$id = 0 + $_GET["id"];
$type = unesc($_GET["type"]);
$tadmin = get_user_class() >= UC_ADMINISTRATOR;

registration_check('invitesystem',true,false);

if (($CURUSER[id] != $id && get_user_class() < $viewinvite_class) || !is_valid_id($id))
stderr($lang_invite['std_sorry'],$lang_invite['std_permission_denied']);
if (get_user_class() < $sendinvite_class)
stderr($lang_invite['std_sorry'],$lang_invite['std_only'].get_user_class_name($sendinvite_class,false,true,true).$lang_invite['std_or_above_can_invite'],false);
$res = sql_query("SELECT username FROM users WHERE id = ".sql_real_escape_string($id)) or sqlerr();
$user =  mysqli_fetch_assoc($res);
stdhead($lang_invite['head_invites']);
print("<table width=700 class=main border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>");

print("<h1 align=center><a href=\"invite.php?id=".$id."\">".$user['username'].$lang_invite['text_invite_system']."</a></h1>");
	$sent = htmlspecialchars($_GET['sent']);
	if ($sent == 1){
		$msg = $lang_invite['text_invite_code_sent'];
		print("<p align=center><font color=red>".$msg."</font></p>");
	}

$res = sql_query("SELECT invites FROM users WHERE id = ".sql_real_escape_string($id)) or sqlerr();
$inv = mysqli_fetch_assoc($res);

if ($type == 'new' && $id == $CURUSER['id']){
	$obj_i = new Invitation();
	if (!$obj_i->canInvite()) {
		stdmsg($lang_invite['std_sorry'],$lang_invite['std_no_invites_left'].
		"<a class=altlink href=invite.php?id=$CURUSER[id]>".$lang_invite['here_to_go_back'],false);
		print("</td></tr></table>");
		stdfoot();
		die;
	}
	$left = $obj_i->getPermanentCount() + $obj_i->getTemporaryCount();
	$invitation_body =  $lang_invite['text_invitation_body'].$CURUSER[username];
	//$invitation_body_insite = str_replace("<br />","\n",$invitation_body);
	print("<form method=post action=takeinvite.php?id=".htmlspecialchars($id).">".
	'<table border="1" width="100%" cellspacing="0" cellpadding="5">'.
	"<tr align=center><td colspan=2><b>".$lang_invite['text_invite_someone'].sprintf('%s (%u%s%s%s)', $SITENAME, $left, $lang_invite['text_invitation'], add_s($left), $lang_invite['text_left']) ."</b></td></tr>".
	"<tr><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\">".$lang_invite['text_email_address']."</td><td align=left><input type=text size=40 name=email><br /><font align=left class=small>".$lang_invite['text_email_address_note']."</font>".($restrictemaildomain == 'yes' ? "<br />".$lang_invite['text_email_restriction_note'].allowedemails() : "")."</td></tr>".
	"<tr><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\">".$lang_invite['text_message']."</td><td align=left><textarea name=body rows=8 cols=120>" .$invitation_body.
	"</textarea></td></tr>".
	"<tr><td align=center colspan=2><input type=submit value='".$lang_invite['submit_invite']."'></td></tr>".
	"</form></table></td></tr></table>");

} else {
	$obj_i = new Invitation($id == $CURUSER['id'] ? 0 : $id);
	$canInvite = $obj_i->canInvite();
	if($canInvite){
		if($tadmin) printf('<form action="massinvite.php" method="post"><input type="hidden" name="uid" value="%u" />', $id);
		echo '<ul style="text-align: left">';
		if($permanent = $obj_i->getPermanentCount()) printf('<li>'.$lang_invite['text_permanent'].'</li>', $permanent);
		if($temporary = $obj_i->getTemporaryInvites()){
			foreach($temporary as $t_invite){
				printf('<li>'.$lang_invite['text_temporary'].'</li>', $tadmin ? sprintf('<input type="number" name="tinvite[%u]" value="%u" min="0" style="width: 2.5em" />', $t_invite['id'], $t_invite['q']) : $t_invite['q'], date('Y-m-d H:i:s', $t_invite['e']));
			}
		}
		echo '</ul>';
		if($tadmin) echo '<input type="submit" name="MOD" value="EDIT" /></form>';
	}

	$rel = sql_query("SELECT COUNT(*) FROM users WHERE invited_by = ".sql_real_escape_string($id)) or sqlerr(__FILE__, __LINE__);
	$arro = sql_fetch_row($rel);
	$number = $arro[0];

	$ret = sql_query("SELECT id, username, email, uploaded, downloaded, status, warned, enabled, donor, email FROM users WHERE invited_by = ".sql_real_escape_string($id)) or sqlerr();
	$num = sql_num_rows($ret);

	echo '<table border="1" width="100%" cellspacing="0" cellpadding="5"><h2 align="center">'.$lang_invite['text_invite_status']." ($number)</h2><form method=post action=takeconfirm.php?id=".htmlspecialchars($id).">";

	if(!$num){
		print("<tr><td colspan=7 align=center>".$lang_invite['text_no_invites']."</tr>");
	} else {

		print("<tr><td class=colhead><b>".$lang_invite['text_username']."</b></td><td class=colhead><b>".$lang_invite['text_email']."</b></td><td class=colhead><b>".$lang_invite['text_uploaded']."</b></td><td class=colhead><b>".$lang_invite['text_downloaded']."</b></td><td class=colhead><b>".$lang_invite['text_ratio']."</b></td><td class=colhead><b>".$lang_invite['text_status']."</b></td>");
		if ($CURUSER[id] == $id || get_user_class() >= UC_SYSOP)
		print("<td class=colhead><b>".$lang_invite['text_confirm']."</b></td>");

		print("</tr>");
		for ($i = 0; $i < $num; ++$i)
		{
			$arr = mysqli_fetch_assoc($ret);
			$user = "<td class=rowfollow>" . get_username($arr[id]) . "</td>";

			if ($arr["downloaded"] > 0) {
				$ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
				$ratio = "<font color=" . get_ratio_color($ratio) . ">$ratio</font>";
			} else {
				if ($arr["uploaded"] > 0) {
					$ratio = "Inf.";
				}
				else {
					$ratio = "---";
				}
			}
			if ($arr["status"] == 'confirmed')
			$status = "<a href=userdetails.php?id=$arr[id]><font color=#1f7309>".$lang_invite['text_confirmed']."</font></a>";
			else
			$status = "<a href=checkuser.php?id=$arr[id]><font color=#ca0226>".$lang_invite['text_pending']."</font></a>";

			print("<tr class=rowfollow>$user<td>$arr[email]</td><td class=rowfollow>" . mksize($arr[uploaded]) . "</td><td class=rowfollow>" . mksize($arr[downloaded]) . "</td><td class=rowfollow>$ratio</td><td class=rowfollow>$status</td>");
			if ($CURUSER[id] == $id || get_user_class() >= UC_SYSOP){
				print("<td>");
				if ($arr[status] == 'pending')
				print("<input type=\"checkbox\" name=\"conusr[]\" value=\"" . $arr[id] . "\" />");
				print("</td>");
			}

			print("</tr>");
		}
	}

	if ($CURUSER[id] == $id || get_user_class() >= UC_SYSOP)
	{

		$pendingcount = number_format(get_row_count("users", "WHERE  status='pending' AND invited_by=$CURUSER[id]"));
		if ($pendingcount){
		print("<input type=hidden name=email value=$arr[email]>");
		print("<tr><td colspan=7 align=right><input type=submit style='height: 20px' value=".$lang_invite['submit_confirm_users']."></td></tr>");
		}
		print("</form>");
		print("<tr><td colspan=7 align=center><form method=post action=invite.php?id=".htmlspecialchars($id)."&type=new><input type=submit ".(!$canInvite ? "disabled " : "")." value='".$lang_invite['sumbit_invite_someone']."'></form></td></tr>");
	}
	print("</table>");

	$rul = sql_query("SELECT COUNT(*) FROM invites WHERE inviter =".sql_real_escape_string($id)) or sqlerr();
	$arre = sql_fetch_row($rul);
	$number1 = $arre[0];


	$rer = sql_query("SELECT invitee, hash, time_invited FROM invites WHERE inviter = ".sql_real_escape_string($id)) or sqlerr();
	$num1 = sql_num_rows($rer);


	print('<table border="1" width="100%" cellspacing="0" cellpadding="5">'.
	"<h2 align=center>".$lang_invite['text_sent_invites_status']." ($number1)</h2>");

	if(!$num1){
		print("<tr align=center><td colspan=6>".$lang_invite['text_no_invitation_sent']."</tr>");
	} else {

		printf("<tr><td class=colhead>".$lang_invite['text_email']."</td><td class=colhead>".$lang_invite['text_hash']."</td><td class=colhead>".$lang_invite['text_send_date']."</td></tr>");
		for ($i = 0; $i < $num1; ++$i)
		{
			$arr1 = mysqli_fetch_assoc($rer);
			printf('<tr><td class="rowfollow">%s<td class="rowfollow"><input type="text" value="http%s://%s/signup.php?type=invite&invitenumber=%s" style="width: 100%%" readonly /></td><td class="rowfollow">%s</td></tr>', htmlspecialchars($arr1['invitee']), $securelogin == 'no' ? '' : 's', $BASEURL, htmlspecialchars($arr1['hash']), $arr1['time_invited']);
		}
	}
	print("</table>");
	print("</td></tr></table>");
	?>
<script>
	jQuery(function($){
		$('input[type=text]').mouseenter(function(){
			$(this).select();
		});
	});
</script>	
<?php
}
stdfoot();
