<html>
<head>
</head>
<body>
<h1 style="text-align:center;">
Restarting the base station
</h1>
<?php
  $exec_string = "/home/sms/reboot.sh &";
  exec($exec_string);
?>
<br />
<form method="post" action="index.php">
    <button type="submit">Return</button>
</form>
</body>
</html>
