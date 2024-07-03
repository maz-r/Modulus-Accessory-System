<html>
<head>
</head>
<body>
<h1 style="text-align:center;">
Deleted all saved messages
</h1>
<?php
  $exec_string = "/home/sms/DeleteMosquittoRetained.sh &";
  exec($exec_string);
?>
<br />
<form method="post" action="index.php">
    <button type="submit">Return</button>
</form>
</body>
</html>
