<html>
<body>
<h2>Shutdown Trigger</h2>
<br />
<br />
The base station shutdown event (<?php echo $_POST['ShutdownEvent'] ?>) is now saved.
<br />
<br />
<form method="post" action="/">
    <button type="submit">Return</button>
</form>
</body>
</html> 
<?php
shell_exec ("echo ".$_POST['ShutdownEvent']." > /home/sms/ShutdownEvent.cfg");
shell_exec ("sudo systemctl restart modulus_shutdown_watch");

?>
