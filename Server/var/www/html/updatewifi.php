<?php
if (isset($_POST['ssid']) and isset($_POST['password']))
{
  if ($_POST['ssid'] != "" and $_POST['password'] != "")
  {
?>
Changing settings to:
<br />
<br />
<table>
<tr><td>LAYOUT NAME = </td><td>
<?php

echo $_POST['ssid'];
?>
</td></tr>
<tr><td>PASSWORD = </td><td>
<?php
echo $_POST['password'];
?>
</td></tr>
</table>
<br />
<?php
    exec ("sudo /home/sms/updatewifi.sh ".$_POST['ssid']." ".$_POST['password']." ".$_POST['channel']." >/dev/null 2>/dev/null &");
?>
Please reconnect to the access point called <strong style="color:red;"><?php echo $_POST['ssid']?></strong> using password <strong style="color:red;"><?php echo $_POST['password']?></strong> to continue.
<br />
<br />
<span style="color:red">Please note, the password must be entered exactly as shown above</span>
<?php
  }
  else
  {
    header("Location: /index.php"); 
  }
}
else
{
    header("Location: /index.php"); 
}
?>
