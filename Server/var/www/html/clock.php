<script src="js/paho-mqtt.js" type="text/javascript"></script>
<style>
@font-face {
    font-family: sevenSegmentFont;
    src: url("fonts/DSEG7Classic-Regular.woff");
}

/* Add a black background color to the top navigation */
.menu {
    background-color: #333;
    overflow: hidden;
}

/* Style the links inside the navigation bar */
.menu a {
    float: left;
    color: #f2f2f2;
    text-align: center;
    padding: 14px 0px;
    text-decoration: none;
    font-size: 17px;
    width: 120px;
}

/* Change the color of links on hover */
.menu a:hover {
    background-color: #ddd;
    color: black;
}

/* Add a color to the active/current link */
.menu a.active {
    background-color: black;
    color: white;
}

.page {
    position: absolute;
    margin-top: 48px;
    width: 100%;
    display: none;
    padding-top: 14px;
    background: black;
}

.subpage {
    display: block;
    width: 100%;
    padding-top:16px;
    padding-bottom:20px;
}

.digitalClock {
  color: red;
  font-size:115px;
  position: absolute;
  height: 375px;
  top: 65px;
  font-family: sevenSegmentFont;
  test-align: center;
}

.digitalclockoutline {
  border: 2px solid #0000;
  padding: 20px;
  border-radius: 20px;
  border-width: 6px;
  border-color: steelblue;
  background-color:#333;
  margin-left: auto;
  margin-right: auto;
  margin-top: 124px;
  text-align: center;
}

.menuclockcanvasclass {
  background-color: #555555;
  float: right;
  margin-right: 10px;
  margin-top: 4px;
}

</style>

<script>

var clientId = "clientID"+~~(Math.random()*1000);
client = new Paho.MQTT.Client("<?php print $_SERVER['HTTP_HOST']; ?>", Number(9001), clientId);
client.onConnectionLost = onConnectionLost;
client.onMessageArrived = onMessageArrived;
client.connect({onSuccess:onConnect});


var menuclockcanvas;
var menuclockctx;
var menuclockradius;
var primaryhour = 6;
var primaryminute = 0;
var primarysecond = 0;
var timeScale = 5;
var ClockStyle = "analog";
var HourClockType = 24;
var intmessagehour = 6;
var intmessageminute = 0;
var intmessagesecond = 0;
var clockPaused=false;
var colonState=true;
var SOM = '<';
var EOM = '>';

var Name = '<?php echo $_GET["name"] ?>';

var spade_minute_image=document.createElement("img");
spade_minute_image.src="images/spade-minute.png";
  
var spade_hour_image=document.createElement("img");
spade_hour_image.src="images/spade-hour.png";
  

function onConnect()
{
  console.log("onConnect");
//  client.subscribe("/Devices/#");
  client.subscribe("/Messages/#");
//  client.subscribe("/ConfigData/#");

//  var onlineButton = document.getElementById("onlineButton");
//  onlineButton.style.backgroundColor = "green";
//  onlineButton.value = "ONLINE";
}

function onConnectionLost(responseObject)
{
  if (responseObject.errorCode !== 0)
  {
  }
}

function reConnect()
{
  client.connect({onSuccess:onConnect});
}

function onMessageArrived(message)
{
	var MessageType = message.payloadString.substring(0, 2);

	if (MessageType == "<R" || MessageType == "<P")
	{
		var messagehours = message.payloadString.substring(2,4);
		var messageminutes = message.payloadString.substring(4,6);
		var messageseconds = message.payloadString.substring(6,8);
		var ampm = (message.payloadString.substring(8,9) == 'P');

		intmessagehour = parseInt(messagehours);
		intmessageminute = parseInt(messageminutes);
		intmessagesecond = parseInt(messageseconds);
		
		drawClock(intmessagehour, intmessageminute, intmessagesecond,ampm);

	}
}

function initialisePage()
{
  clockcanvas = document.getElementById("clockcanvas");
  clockctx = clockcanvas.getContext("2d");
  clockradius = clockcanvas.height / 2;
  clockctx.translate(clockradius, clockradius);
  clockradius = clockradius * 0.90;
}

function drawClock(hour,minute,second,ampm)
{
	if (ClockStyle == "analog")
	{
	  drawFace(clockctx, clockradius, hour, minute, second);
//	  drawNumbers(clockctx, clockradius);
//	  drawTime(clockctx, clockradius, hour, minute, second);
	}
	else
	{
	  if (ampm)
	  {
			var tempHoursString = "00" + hour;
			tempHoursString = tempHoursString.substr(-2);
			var tempMinutesString = "00" + minute;
			tempMinutesString = tempMinutesString.substr(-2);
			if (colonState || clockPaused)
			{
					colonState = false;
				document.getElementById("digitalClock").innerHTML=tempHoursString+":"+tempMinutesString;
			}
			else
			{
					colonState = true;
				document.getElementById("digitalClock").innerHTML=tempHoursString+"&nbsp"+tempMinutesString;
			}
	  }
	  else
	  {
			var tempHoursString = "" + (hour % 12);
			if (tempHoursString.length < 2)
				tempHoursString = "&nbsp" + tempHoursString;
			if(tempHoursString == "&nbsp0")
				tempHoursString = "12";
			var tempMinutesString = "00" + minute;
			tempMinutesString = tempMinutesString.substr(-2);
			var ampm = " a";
			if (hour >= 12)
				ampm=" p";
			if (colonState || clockPaused)
			{
				colonState = false;
				document.getElementById("digitalClock").innerHTML=tempHoursString+":"+tempMinutesString+"&nbsp"+ampm;
			}
			else
			{
				colonState = true;
				document.getElementById("digitalClock").innerHTML=tempHoursString+"&nbsp"+tempMinutesString+"&nbsp"+ampm;
			}
	  }
	}
}

function drawFace(ctx, radius, hour, minute, second)
{
  var grad;
  ctx.beginPath();

	var img     = new Image();
	img.onload  = function() {
	    var imgWidth = img.width;
	    var canWidth = clockcanvas.width;
			ctx.drawImage(img, 0,0, imgWidth, imgWidth, -canWidth/2, -canWidth/2, canWidth, canWidth);
			ctx.fillStyle = 'black';
			ctx.textBaseline="middle";
			ctx.textAlign="center";
			ctx.font = "35px serif";
			ctx.fillText(Name, 0, -canWidth/6);
			drawTime(ctx, clockradius, hour, minute, second);
			ctx.beginPath();
      ctx.arc(0, 0, radius*0.07, 0, 2*Math.PI);
      ctx.fillStyle = 'black';
      ctx.fill();
	}
	img.src     = 'images/clock_face_1.jpg';

}

function drawNumbers(ctx, radius)
{
  var ang;
  var num;
  ctx.font = radius*0.15 + "px arial";
  ctx.textBaseline="middle";
  ctx.textAlign="center";
  for(num = 1; num < 13; num++)
  {
    ang = num * Math.PI / 6;
    ctx.rotate(ang);
    ctx.translate(0, -radius*0.85);
    ctx.rotate(-ang);
    ctx.fillText(num.toString(), 0, 0);
    ctx.rotate(ang);
    ctx.translate(0, radius*0.85);
    ctx.rotate(-ang);
  }
}

function drawTime(ctx, radius, hour, minute, second)
{
    hour=hour%12;
    hourhand=(hour*Math.PI/6)+
    (minute*Math.PI/(6*60))+
    (second*Math.PI/(360*60));
    hourhand += Math.PI;
    drawHourHand(ctx, hourhand, radius*0.5, radius*0.05);
    minutehand=(minute*Math.PI/30)+(second*Math.PI/(30*60));
    minutehand += Math.PI;
    drawMinuteHand(ctx, minutehand, radius*0.8, radius*0.03);
}

function drawMinuteHand(ctx, pos, length, width)
{
	ctx.save();
	ctx.rotate(pos);
	ctx.translate(-21,-21);
	ctx.drawImage(spade_minute_image, 0, 0);
	ctx.restore();
}

function drawHourHand(ctx, pos, length, width)
{
	ctx.save();
	ctx.rotate(pos);
	ctx.translate(-22,-28);
	ctx.drawImage(spade_hour_image, 0, 0);
	ctx.restore();
}

function analogClockSelected()
{
	document.getElementById("clockcanvas").style.visibility = "visible";
	document.getElementById("digitalClock").style.display = "none";
	ClockStyle = "analog";
}

function digitalClockSelected()
{
	document.getElementById("clockcanvas").style.visibility = "hidden";
	document.getElementById("digitalClock").style.display = "block";
	ClockStyle = "digital";
}

</script>

<body onload="initialisePage()" style="position: relative; min-width: 1080px; overflow-x:scroll;color: white; font-family: sans-serif;background:black;">
<div class="outercontainer">
	<div style="min-width:1024px">
		<div class="menu" id="menu" style="position:absolute; width:100%; background:#555555">
			<form>
				<input type="radio" id="analog" value="analog" name="clock_style" onclick="analogClockSelected()" checked>
				<label for="analog">Analog</label>
				<input type="radio" id="digital" value="digital" name="clock_style" onclick="digitalClockSelected()">
				<label for="digital">Digital</label>
			</form>
		</div>
		<div class="page" style="display:block;" id="ClockPage">
		  <div class="innerPage">
			<canvas id="clockcanvas" width="600px" height="600px" style="">
			</canvas>
			<div class="digitalClock">
			  <div class="digitalclockoutline" id="digitalClock" style="display:none;">
			  </div>
			</div>
		  </div>
		</div>
	</div>
</div>
</body>
