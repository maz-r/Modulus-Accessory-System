<html>
<head>
</head>
<body>
<h1 style="text-align:center;">
Stopping the base station
</h1>
<?php
  $exec_string = "/home/sms/shutdown.sh &";
  exec($exec_string);
?>
<br />
</body>
<script>
function updateScreen()
{
  document.write("<h1 style='text-align:center;'>Base Station Stopped</h1>");
}

function offlineDetected()
{
  clearInterval(testInterval);
  setTimeout(updateScreen, 3000);
}

function checkServer() 
{
    let img = new Image();
    img.onerror = function() { offlineDetected(); };
    img.src = '/images/delete_row.png?r=' + Math.random(); /* avoids caching */
}

testInterval = setInterval(checkServer, 1000);

</script>
</html>
