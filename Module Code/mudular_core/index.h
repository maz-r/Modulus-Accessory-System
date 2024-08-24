/***************************************************
# Required Notice: Copyright (C) 2024 Martin Randall - All Rights Reserved
#
# You may use, distribute and modify this code under the
# terms of the PolyForm Noncommercial 1.0.0 license.
#
# You should have received a copy of the PolyForm Noncommercial 1.0.0 license with
# this file. 
# If not, please visit: <https://polyformproject.org/licenses/noncommercial/1.0.0>
#
****************************************************/
const char INDEX_page[] PROGMEM = R"=====(
<html>
<head>
</head>
<body style='background:white; color: slategray'>
<span style='font-size:80px; color:darkorange; font-family:sans-serif'>M</span>
<span style='font-size:80px; color:slategray; font-family:sans-serif'>odulus</span>
<form action='/update.html'>
<table>
<tr><td>Module Name </td><td><input type='text' name='module'></td></td>
<tr><td>Network Name</td><td><input type='text' name='ssid'></td></td>
<tr><td>Password    </td><td><input type='text' name='password'></td></td>
<tr><td></td><td></td></tr>
<tr><td>Board Type   </td><td>
<select name="boardtype">
  <option value="3">Input Board</option>
  <option value="4">Servo Controller</option>
  <option value="5">Mimic Display</option>
  <option value="12">Lighting Controller</option>
  <option value="9">Sound Module</option>
  <option value="2">Digital Clock</option>
  <option value="8">Matrix Clock</option>
  <option value="11">Analogue Clock</option>
  <option value="14">DCC Interface</option>
<!--
  <option value="7">Stepper Motor Controller</option>
-->
</select>
</td></td>
<tr><td></td><td></td></tr>
<tr><td></td><td><input type="submit"></td></tr>
</table>
</form>
</body>
</html>
)=====";
