<script src="js/paho-mqtt.js" type="text/javascript"></script>
<script src="js/jquery-min.js"></script>
<link   href="css/sms.css" rel="stylesheet" />

<style>
@font-face {
    font-family: sevenSegmentFont;
    src: url("fonts/DSEG7Classic-Regular.woff");
}

@font-face {
    font-family: icons;
    src: url("fonts/icofont.woff");
}

@font-face {
  font-family: typicons;
  src: url("fonts/typicons.woff");
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
var ampm=false;
var SOM = '<';
var EOM = '>';
var moduleName = "";
var MqttConnected = false;
var Bank;
var EndBank;
var editEventObject;
var editServoEventObject;
var editServoRow;
var editServoBoard;
var editLightingGroupObject;
var editLabelEventObject;
var editLabelEventRow;
var editSoundEventObject;
var fileModuleType;
var rowsReceived = 0;
var lastModuleType = "";
var Interfaces = 0;
var labelsExist = false;
var SignalHeadValue = [24,28,32,36,40,44,48,50,54,58,62,66,68,72,74,78]

var moduleTypeNames = [
  "Internal Clock",
  "",
  "",
  "Input Board",
  "Servo Controller",
  "Mimic Display",
  "Relay Controller",
  "Stepper Motor Controller",
  "",
  "Sound Module",
  "",
  "",
  "Lighting Controller",
  "",
  "",
  "",
  "Internal Sound Module"
];

var fieldRange = [
  [[["Trigger Event"],["85px"],['E']],
   [["Action"],["495px"],['T']],
  ],
  [],
  [],
  [[["Trigger Event"],["85px"],['W']],
   [["Type"],["125px"],['A'],[1],[100],[1],[0]],
   [["Pulse Length"],["85px"],['I'],[1],[100],[1],[0]],
  ],
  [[["Trigger Event"],["85px"],['E']],
   [["Channel No"],["50px"],['S']],
   [["Delay"],["50px"],['I'],[0],[999],[0]],
   [["Event Before"],["85px"],['D']],
   [["Type/Action"],["95px"],['Y'],[1],[180],[90]],
   [["Speed"],["50px"],['I'],[0],[200],[8]],
   [["Event After"],["85px"],['D']],
   [["Profile"],["90px"],['P']],
   [["Number of Bounces"],["75px"],['B']],
   [["Bounce Type"],["75px"],['O']],
   [["Bounce Amplitude"],["50px"],['I'],[-10],[10],[0]],
   [["Bounce Speed"],["50px"],['I'],[1],[50],[10]]
  ],
  [[["Trigger Event"],["85px"],['E']],
   [["Transition"],["160px"],['M']],
   [["LED Range"],["85px"],['L']],
   [["Colour 1"],["85px"],['C']],
   [[""],["20px"],['x']],
   [["Colour 2"],["85px"],['2']],
   [["Interval"],["85px"],['I'],[0],[200],[50],[0]],
   [["LED to duplicate"],["30px"],['I'],[0],[99],[0]]
  ],
  [
  ],
  [[["Trigger Event"],["85px"],['E']],
   [["Stepper No"],["50px"],['<']],
   [["Delay"],["50px"],['I'],[0],[999],[0]],
   [["Event Before"],["85px"],['D']],
   [["Position"],["100px"],['I'],[0],[359],[0]],
   [["Direction"],["100px"],['>']],
   [["Speed"],["100px"],['I'],[1],[200],[0]],
   [["Acceleration"],["100px"],['I'],[0],[1000],[0]],
   [["Event After"],["85px"],['D']]
  ],
  [],
  [[["Trigger Event"],["85px"],['E']],
   [["Queue mode"],["95px"],['Q']],
   [["Channel"],["90px"],['Z']],
   [["Sound"],["75px"],["H"]],
   [["Sound"],["75px"],["G"]],
   [["Sound"],["75px"],["J"]],
   [["Sound"],["75px"],["F"]],
   [["Sound"],["75px"],["F"]],
   [["Sound"],["75px"],["F"]],
   [["Sound"],["75px"],["F"]],
   [["Sound"],["75px"],["F"]],
  ],
  [],
  [],
  [[["Trigger Event"],["85px"],['E']],
   [["Light Number(s)"],["68px"],['K'],[0],[15],[0],[0]],
   [[""],["0px"],['U'],[0],[15],[0],[0]],
   [["Effect"],["85px"],['N']],
   [["Delay"],["50px"],['I'],[0],[999],[0]],
   [["High Brightness"],["85px"],['I'],[0],[100],[50],[0]],
   [["Speed Up"],["55px"],['I'],[0],[100],[50],[0]],
   [["Time High"],["60px"],['I'],[0],[200],[50],[0]],
   [["Low Brightness"],["85px"],['I'],[0],[100],[50],[0]],
   [["Speed Down"],["55px"],['I'],[0],[100],[50],[0]],
   [["Time Low"],["55px"],['I'],[0],[200],[50],[0]],
   [["Number of Flashes"],["85px"],['I'],[0],[200],[50],[0]],
   [["Interval"],["65px"],['I'],[0],[200],[50],[0]],
  ],
  [],
  [],
  [],
  [[["Trigger Event"],["85px"],['E']],
   [["Queue mode"],["95px"],['Q']],
   [["Channel"],["90px"],['V']],
   [["Sound"],["75px"],["H"]],
   [["Sound"],["75px"],["G"]],
   [["Sound"],["75px"],["J"]],
   [["Sound"],["75px"],["F"]],
   [["Sound"],["75px"],["F"]],
   [["Sound"],["75px"],["F"]],
   [["Sound"],["75px"],["F"]],
   [["Sound"],["75px"],["F"]],
  ],
];

function isDigit(aChar)
{
   myCharCode = aChar.charCodeAt(0);

   if((myCharCode > 47) && (myCharCode <  58))
   {
      return true;
   }

   return false;
}

function onConnect()
{
  console.log("onConnect");
  client.subscribe("/Devices/#");
  client.subscribe("/Messages/#");
  client.subscribe("/ConfigData/#");
  client.subscribe("/UsageData/#");

  var onlineButton = document.getElementById("onlineButton");
  onlineButton.style.backgroundColor = "green";
  onlineButton.value = "ONLINE";
  MqttOnline = true;
}

function onConnectionLost(responseObject)
{
  if (responseObject.errorCode !== 0)
  {
    MqttOnline = false;
    var onlineButton = document.getElementById("onlineButton");
    onlineButton.style.backgroundColor = "red";
    onlineButton.value = "OFFLINE";
    removeAllModules();
  	var x = document.getElementById("AllSensors").childNodes;
	  for(i=3; i< x.length; i++)
	  {
	    tempString = "<td class='deviceName'>";
  	  tempString += x[i].childNodes[0].innerHTML;
	    tempString += "</td><td class='deviceTD' style='color:white; background-color:red;'>OFFLINE</td><td class='deviceTD'><input type='button' value='Delete' onclick=\"deleteModule(this, \'"+x[i].childNodes[0].innerHTML+"\');\"></td></tr>";

      var elementID = "Sensors";
	    elementID += x[i].childNodes[0].innerHTML;
  	  var elementExists = document.getElementById(elementID);

	    elementExists.innerHTML = tempString;
	  }
    displaySubpage(null);
    displayModulesList();
  }
}

function reConnect()
{
  if (!MqttOnline)
  {
    removeAllModules();
    client.connect({onSuccess:onConnect});
  }
}

function remoteProgram(ipaddress, version)
{
  if (version == "V2")
   	var URL="http://"+ipaddress+"/";
  else
   	var URL="http://"+ipaddress+"/update";

	window.open(URL, "_blank", "toolbar=no,scrollbars=no,resizable=no,top=500,left=500,width=400,height=400");
}

function  displayModulesList()
{
  var x = document.getElementById("AllSensors");
  if (x.rows.length > 2)
    x.style.visibility = "visible";
  else
    x.style.visibility = "hidden";
}

function removeAllModules()
{
  var x = document.getElementById("Sensors");
  var y = x.length;
  for (var i=1; i<y;i++)
  {
    x.remove(1);
  }
}

function deleteModule(row, moduleName)
{
  var confirmString = "Are you sure you want to remove "+moduleName+" from the system?";
  if (confirm(confirmString))
  {
    var tempString = "/Devices/"+moduleName;
    client.publish(tempString, "", 2, true);
	  var i = row.parentNode.parentNode.rowIndex;
    document.getElementById("AllSensors").deleteRow(i);
    displayModulesList();
  }
}

function clearDebugArea()
{
	document.getElementById("DebugArea").value = "";
}

function clearConfigLines(tableNumber)
{
  if (tableNumber == "03" || tableNumber == "04" || tableNumber == "05" || tableNumber == "06" || tableNumber == "07" || tableNumber == "09" || tableNumber == "12" || tableNumber == "16")
  {
    var configTableName = "ConfigTable" + tableNumber;
    var configTable = document.getElementById(configTableName);
    while (configTable.rows.length > 0)
    {
      configTable.deleteRow(-1);
    }

    var tableID = parseInt(tableNumber);
    var row = configTable.insertRow(-1);
    var rowFields = fieldRange[tableID].length;
    var cell = row.insertCell();
    var cell = row.insertCell();
    for (var i=0; i<rowFields; i++)
    {
      var cell = row.insertCell();
      if (fieldRange[tableID][i].length > 0)
      {
        cell.innerHTML = fieldRange[tableID][i][0];
        cell.style.width = fieldRange[tableID][i][1][0];
      }
    }
  }
}

function usageLineHeadings(boardType, configTable, tableID)
{
  var tableID = parseInt(tableID);
  var row = configTable.insertRow(-1);
  
  if (boardType == 3)
  {
    var cell = row.insertCell();
    cell.innerHTML = "Number";
    cell.style.width = "85px";
  }
  
  var rowFields = fieldRange[tableID].length;

  var cell = row.insertCell();
  cell.innerHTML = fieldRange[tableID][0][0];
  cell.style.width = fieldRange[tableID][0][1][0];

  for (var i=1; i<rowFields; i++)
  {
    var cell = row.insertCell();
    cell.innerHTML = fieldRange[tableID][i][0];
    cell.style.width = fieldRange[tableID][i][1][0];
  }
}

function manipulateRows(currentRow, moduleType, action)
{
  var newRowNumber = 0;
  var moduleTypeString = "00" + moduleType;
  moduleTypeString = moduleTypeString.substr(-2);
  var configTableName = "ConfigTable" + moduleTypeString;
  var configTable = document.getElementById(configTableName);
  var cell = {};

  var currentRowObject = document.getElementById("configRow" + moduleTypeString + "_" + currentRow);
  var currentRowIndex = currentRowObject.rowIndex;

  switch(action)
  {
    case "Renumber":
        var count = configTable.rows.length;
      if (moduleType == 3)
      {
        count--;
        for(var i=0; i<count; i++)
        {
          var x = configTable.rows[i+1];
          x.childNodes[1].innerText = i;
        }
      }
      else
      {
        for(var i=1; i<count; i++)
        {
          var x = configTable.rows[i];
          x.childNodes[1].innerText = i;
        }
      }
      break;

    case "Delete":
      configTable.deleteRow(currentRowIndex);
      if(configTable.children[0].children.length > 1)
        configTable.children[0].children[1].firstChild.innerHTML = "";
      break;

    case "Insert":
      var count = configTable.rows.length;
      for(var i=1; i<count; i++)
      {
          if (parseInt(configTable.rows[i].id.substr(12)) > parseInt(newRowNumber))
            newRowNumber = parseInt(configTable.rows[i].id.substr(12));
      }
      newRowNumber++;
      var configString = buildConfigString(0, parseInt(moduleType), currentRow)
      var row = configTable.insertRow(currentRowIndex + 1);
      row.id = "configRow" + moduleTypeString + "_" +newRowNumber;
      populateRow(newRowNumber, parseInt(moduleType), configString, false)
      break;

    case "Swap":
      var previousRowObject = currentRowObject.previousSibling;
      var previousRowIndex = previousRowObject.rowIndex;
      var previousRow = previousRowObject.id.substr(12);
      var currentDetails = buildConfigString(0, moduleType, currentRow);
      var previousDetails = buildConfigString(0, moduleType, previousRow);

      while (currentRowObject.firstChild != undefined)
        currentRowObject.deleteCell(0);
      populateRow(currentRow, moduleType, previousDetails, false);

      while (previousRowObject.firstChild != undefined)
        previousRowObject.deleteCell(0);
      populateRow(previousRow, moduleType, currentDetails, false);
      break;
  }

  if (action != "Renumber")
  {
    var count = configTable.rows.length;
    for(var i=1; i<count; i++)
    {
      var x = configTable.rows[i];
      x.childNodes[1].innerText = i;
    }
  }
}

function buildConfigString(longshort, moduleType, rowNumber)
{
  var ConfigString = "";
  var moduleTypeString = "00"+moduleType;
  moduleTypeString = moduleTypeString.substr(-2);

  var cell;
  var configTableName = "ConfigTable" + moduleTypeString;
  var configTable = document.getElementById(configTableName);
  var currentRow = document.getElementById("configRow" + moduleTypeString + "_" + rowNumber);
  var rowFields = fieldRange[moduleType].length;

  if (currentRow.cells[3].firstChild.value == "=")
  {
    ConfigString = currentRow.cells[1].firstChild.value;
    ConfigString += ",=,";
    ConfigString += document.getElementById("Variable_"+moduleType+"_"+rowNumber).value;
    ConfigString += ",";
    ConfigString += document.getElementById("VariableValue_"+moduleType+"_"+rowNumber).value;
  }
  else
  {
    for (var i=0; i<rowFields; i++)
    {
      if (fieldRange[moduleType][i][2][0] == fieldRange[moduleType][i][2][0].toUpperCase())
      {
        if (i!=0)
          ConfigString += ",";
        ConfigString += currentRow.cells[i+2].firstChild.value;
      }
    }
  }
  return ConfigString;
}

function populateRow(rowNumber, boardType, configString, usage, tableToPopulate)
{
  var trConfigString = configString.replace(EOM, '');
  var ConfigDetails = trConfigString.split(",");
  if (!usage)
  {
    var configTableNameNumber = "00" + boardType;
    configTableNameNumber = configTableNameNumber.substr(-2)
    var configTableName = "ConfigTable" + configTableNameNumber;
    var configTable = document.getElementById(configTableName);
    var topRow = configTable.firstElementChild.children[1].id;
    var row = document.getElementById("configRow" + configTableNameNumber +"_"+ rowNumber);
  }
  else
  {
    var row = document.getElementById("configRow" + ConfigDetails[0] +"_"+ rowNumber);
    var configTable = tableToPopulate;
    var configTableNameNumber = "00" + boardType;
    configTableNameNumber = configTableNameNumber.substr(-2)
  }

  var rowFields = fieldRange[parseInt(boardType)].length;
  var fieldIndex = 0;
  var toolTipString = "";
  var disabledStr = "";
  var uniqueID = "";

  if (!usage)
  {
    if (!(boardType == 3))
    {
      cell = row.insertCell();
      cell.style.width = "20px";
      if (topRow != row.id)
      {
        cell.innerHTML = "<button title='Swap this row with the one above it' class='config_swap_button' id='swapRow_button"+rowNumber+"' onclick='manipulateRows("+rowNumber+","+boardType+", \"Swap\")'><span style=\"font-family:icons;font-size: 20px;\">&#xeac5;</span></button>";
      }
      cell = row.insertCell();
      cell.style.width = "20px";
      cell.innerHTML = '<input disabled id="row_number_'+boardType+'_rowLabel-'+rowNumber+'" style="width:100%">';
    }
    else
    {
      cell = row.insertCell(0);
      cell.style.width = "20px";
      cell.innerHTML = '<input disabled id="row_number_'+boardType+'_rowLabel-'+rowNumber+'" style="width:100%">';
      cell = row.insertCell(0);
    }
  }
  else
  {
    if (boardType == 3)
    {
      cell = row.insertCell(0);
      cell.style.width = "20px";
      cell.innerHTML = '<input disabled id="row_number_'+boardType+'_rowLabel-'+rowNumber+'" style="width:100%">';
      fieldIndex = 3;
      disabledStr = "disabled";
      uniqueID = tableToPopulate.id;
    }
    else
    {
      fieldIndex = 2;
      disabledStr = "disabled";
      uniqueID = tableToPopulate.id;
    }
  }

  for (var i=0; i<rowFields; i++)
  {
    if (fieldRange[boardType][i].length > 0)
    {
      var newCell = row.insertCell();

      switch(fieldRange[boardType][i][2][0])
      {
        case '#':
          newCell.innerHTML = rowNumber;
          break;

        case 'W':
          var newInnerHTML = '<input '+disabledStr+' id="editInputPopup_button'+uniqueID+rowNumber+'" style="width:40px;" onclick="openLabelEventPopup(\'Event to trigger on input\', this,'+rowNumber+');" value='+ConfigDetails[fieldIndex]+'>';
          newCell.innerHTML += newInnerHTML;
          fieldIndex++;
          break;

        case 'A':
          if (rowNumber % 2 == 1)
            newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="InputType'+uniqueID+rowNumber+'" onChange="updateInputRow('+rowNumber+')";><option value=0>Normal</option><option value=1>Toggle</option><option value=2>Pair</option><option value=3>Timed Pulse</option></select>';
          else
            newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="InputType'+uniqueID+rowNumber+'" onChange="updateInputRow('+rowNumber+')"><option value=0>Normal</option><option value=1>Toggle</option><option disabled value=2>Pair</option><option value=3>Timed Pulse</option></select>';
          if (typeof(ConfigDetails[fieldIndex]) == "undefined")
          {
            ConfigDetails[fieldIndex] = 0;
          }
          var option = $('#InputType'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;

        case 'D':
        case 'E':
          var ConfigString = "";
          if (ConfigDetails[fieldIndex] != "")
          {
            if (ConfigDetails[fieldIndex].charCodeAt(0,1) > 'a'.charCodeAt(0))
            {
              var LowRangeHoursTens = ConfigDetails[fieldIndex].charCodeAt(0,1) - 'a'.charCodeAt(0);
              var LowRangeHours = ("0" + LowRangeHoursTens).substr(-2);
              var HighRangeHoursTens = ConfigDetails[fieldIndex].charCodeAt(1,1) - 'a'.charCodeAt(0);
              var HighRangeHours = ("0" + HighRangeHoursTens).substr(-2);

              toolTipString = "";
              ConfigVal = ConfigDetails[fieldIndex];
              ConfigString = "" + LowRangeHours + "->";
              ConfigString += "" + HighRangeHours + ":";
              ConfigString += "" + ConfigDetails[fieldIndex].substr(2,2);
            }
            else
            {
              if (isDigit(ConfigDetails[fieldIndex].substr(0,1)) || ConfigDetails[fieldIndex].substr(0,1) == '?' || ConfigDetails[fieldIndex].substr(0,1) == '#')
              {
                toolTipString = ConfigDetails[fieldIndex].substr(1,3);
                if (parseInt(ConfigDetails[fieldIndex].substr(0,1)) >= 3)
                {
                  var ConfigVal = parseInt(ConfigDetails[fieldIndex].substr(0,2));
                  var ConfigStr = "00" + (ConfigVal - 30);
                  ConfigString += ConfigStr.substr(ConfigStr.length - 2);
                  ConfigString +="h->";

                  ConfigVal = parseInt(ConfigDetails[fieldIndex].substr(2,4));
                  ConfigStr = "00" + (ConfigVal - 30);
                  ConfigString += ConfigStr.substr(ConfigStr.length - 2);
                  ConfigString +="h";
                }
                else
                {
                  ConfigString = ConfigDetails[fieldIndex].substr(0,2)+":"+ConfigDetails[fieldIndex].substr(2,4);
                  toolTipString = ConfigDetails[fieldIndex].substr(1,3);
                }
              }
              else
              {
                if (ConfigDetails[fieldIndex] == "INIT")
                {
                  ConfigString =  "On Startup";
                  toolTipString = ConfigDetails[fieldIndex].substr(1,3);
                }
                else
                {
                  if (ConfigDetails[fieldIndex].substr(0,1) == "D")
                  {
                    ConfigString =  "[" + ConfigDetails[fieldIndex].substr(1,2) + "] " + ConfigDetails[fieldIndex].substr(3);
                    toolTipString = ConfigDetails[fieldIndex].substr(1,3);
                  }
                  else
                  {
                    ConfigString = ConfigDetails[fieldIndex].substr(1,3);
                    toolTipString = decodeLabel(ConfigString);
                    if (ConfigDetails[fieldIndex].substr(0,1) == 'S')
                    {
                      ConfigString +=  " On";
                    }
                    else
                    {
                      ConfigString +=  " Off";
                    }
                  }
                }
              }
            }
          }
          else
          {
            ConfigString = "";
            toolTipString = "";
          }

          var newInnerHTML = '<button '+disabledStr+' class="config_button tooltip" id="editEventPopup_button'+uniqueID+rowNumber+'" onclick="openEventPopup(\''+fieldRange[boardType][i][2][0]+'\', this);" value="' + ConfigDetails[i] + '">' + ConfigString;
          if (toolTipString != ConfigDetails[i].substr(1,3))
            newInnerHTML += '<span class="tooltiptext">' + toolTipString + '</span>';
          newInnerHTML += '</button>';
          newCell.innerHTML += newInnerHTML;
          fieldIndex++;
          break;

        case 'T':
          newCell.innerHTML = '<input '+disabledStr+' class="config_button" id="default_button_'+boardType+'_'+uniqueID+rowNumber+'-'+i+'" style="width:100%" value="'+ConfigDetails[fieldIndex]+'">';
          fieldIndex++;
          break;


        case 'B':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="ServoBounceNumber'+uniqueID+rowNumber+'" onchange="ConfigServoBounceTransitionChange('+boardType+','+rowNumber+');"><option value=\'0\'>None</option><option value=\'1\'>1</option><option value=\'2\'>2</option><option value=\'3\'>3</option><option value=\'4\'>4</option><option value=\'5\'">5</option><option value=\'R\'">Random</option></select>';
          if (typeof(ConfigDetails[fieldIndex]) == "undefined")
          {
            ConfigDetails[fieldIndex] = 0;
          }
          var option = $('#ServoBounceNumber'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;

        case 'P':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="ServoWaveform'+uniqueID+rowNumber+'"><option value=0>Linear</option><option value=1>Logrithmic</option><option value=2>Hyperbolic</option><option value=3>Gravity</option><option value=4>Slack</option></select>';
          if (typeof(ConfigDetails[fieldIndex]) == "undefined")
          {
            ConfigDetails[fieldIndex] = 0;
          }
          var option = $('#ServoWaveform'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;

        case 'Z':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="SoundChannel_'+boardType+"_"+uniqueID+rowNumber+'"><option value=0>0</option><option value=1>1</option><option value=99>Least Busy</option></select>';
          if (typeof(ConfigDetails[fieldIndex]) == "undefined")
          {
            ConfigDetails[fieldIndex] = 0;
          }
          var option = $('#SoundChannel_'+boardType+"_"+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          if (option.length == 1)
            option[0].selected = true;
          fieldIndex++;
          break;

        case 'V':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="SoundChannel_'+boardType+"_"+uniqueID+rowNumber+'"><option value=0>0</option><option value=1>1</option><option value=2>2</option><option value=3>3</option><option value=4>4</option><option value=5>5</option><option value=99>Least Busy</option></select>';
          if (typeof(ConfigDetails[fieldIndex]) == "undefined")
          {
            ConfigDetails[fieldIndex] = 0;
          }
          var option = $('#SoundChannel_'+boardType+"_"+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          if (option.length == 1)
            option[0].selected = true;
          fieldIndex++;
          break;

        case 'U':
          newCell.innerHTML = '<input style="display:none" type="text" id="LightingUnique'+uniqueID+rowNumber+'" >';
          if (typeof(ConfigDetails[fieldIndex]) == "undefined")
          {
            ConfigDetails[fieldIndex] = 'G';
          }

          newCell.firstChild.value = ConfigDetails[fieldIndex];

          fieldIndex++;
          break;

        case 'O':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="ServoBounceWaveform'+uniqueID+rowNumber+'"><option value=0>Bounce</option><option value=1>Settle</option></select>';
          if (typeof(ConfigDetails[fieldIndex]) == "undefined")
          {
            ConfigDetails[fieldIndex] = 0;
          }
          var option = $('#ServoBounceWaveform'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;

        case 'N':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="LightingEffect'+uniqueID+rowNumber+'" onchange="ConfigLightingTypeChange('+boardType+','+uniqueID+rowNumber+');"><option value=\'S\'>Direct</option><option value=\'F\'>Flicker</option><option value=\'R\'>Arc</option><option value=\'Q\'>Cycle</option><option value=\'P\'>Proportional</option></select>';
          if (typeof(ConfigDetails[fieldIndex]) == "undefined")
          {
            ConfigDetails[fieldIndex] = 0;
          }
          var option = $('#LightingEffect'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;

        case 'S':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="ServoNumber'+uniqueID+rowNumber+'"><option value="0">0</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option><option value="13">13</option><option value="14">14</option><option value="15">15</option></select>';
          var option = $('#ServoNumber'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;

        case '>':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="StepperDirection'+uniqueID+rowNumber+'"><option value="F">Forward</option><option value="B">Backward</option><option value="S">Shortest</option></select>';
          var option = $('#StepperDirection'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;

        case '<':
          newCell.innerHTML = '<select disabled style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="StepperNumber'+uniqueID+rowNumber+'"><option value="0">0</option></select>';
          fieldIndex++;
          break;
/*
        case 'R':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="RelayNumber'+uniqueID+rowNumber+'"><option value="0">0</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option></select>';
          var option = $('#RelayNumber'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;

        case 'A':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="RelayAction'+uniqueID+rowNumber+'" onchange="ConfigRelayChange('+boardType+','+uniqueID+rowNumber+');";><option value=0>Off</option><option value=1>On</option><option value="P">Pulse</option><option value="T">Toggle</option></select>';
          if (typeof(ConfigDetails[fieldIndex]) == "undefined")
          {
            ConfigDetails[fieldIndex] = 0;
          }
          var option = $('#RelayAction'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;
*/
        case 'M':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="ConfigMimicTransitionType'+uniqueID+rowNumber+'" onchange="ConfigMimicRangeChange(5, '+uniqueID+rowNumber+');" name="ConfigMimicTransitionType'+uniqueID+rowNumber+'"><option value="S">Direct</option><option value="F">Flash</option><option value="M">Fade</option><option value="C">Cycle</option><option value="D">Duplicate</option><option value="Y">Duplicate if EQUAL</option><option value="N">Duplicate if NOT equal</option></select>';
          var option = $('#ConfigMimicTransitionType'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;

        case 'W':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" id="ConfigServoWaveformType'+uniqueID+rowNumber+'" name="ConfigServoWaveformType'+uniqueID+rowNumber+'" onchange="ConfigServoWaveformChange('+uniqueID+rowNumber+');"><option value="S">Direct</option><option value="F">Flash</option><option value="M">Fade</option><option value="C">Cycle</option><option value="R">Replicate</option></select>';
          var option = $('#ConfigMimicTransitionType'+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          option[0].selected = true;
          fieldIndex++;
          break;

        case 'C':
          var ConfigString = "";
          newCell.innerHTML = "<button "+disabledStr+" class='config_button' name='"+uniqueID+rowNumber+"' id='editColour_button"+uniqueID+rowNumber+"-"+i+"' onclick='openColourPopup(this, true);' value='" + ConfigDetails[i] + "'></button>";
          newCell.firstChild.value = ConfigDetails[fieldIndex];

          if (ConfigDetails[fieldIndex] == "::")
            setColourButton(newCell.firstChild, true);
          else
            setColourButton(newCell.firstChild, false);

          fieldIndex++;
          break;

        case '2':
          var ConfigString = "";
          newCell.innerHTML = "<button "+disabledStr+" class='config_button' name='"+uniqueID+rowNumber+"' id='editColour_button"+uniqueID+rowNumber+"-"+i+"' onclick='openColourPopup(this, false);' value=''>";
          newCell.firstChild.value = ConfigDetails[fieldIndex];
          setColourButton(newCell.firstChild, false);
          fieldIndex++;
          break;

        case 'x':
          newCell.innerHTML = "<button "+disabledStr+" class='config_button' style='background:transparent; border:none;' id='swap_button"+uniqueID+rowNumber+"' onclick='swapColourInputFields("+uniqueID+rowNumber+");'><span style='font-family:icons;font-family:icons;font-size: 22px;color: #0000ff;margin-left: -7px;'>&#xef18;</span></button>";
          break;

        case 'L':
          var ConfigString = "";
          var visibleConfigString = "";
          var alternating = false;
          var ConfigDetailsSplitOuter = ConfigDetails[fieldIndex].split("+");

          if (ConfigDetailsSplitOuter[0] == "A")
            alternating = true;

          for (var k=1; k< ConfigDetailsSplitOuter.length; k++)
          {
            var ConfigDetailsSplit = ConfigDetailsSplitOuter[k].split(":");
            if (k>1)
            {
              ConfigString += "+";
            }
            if (ConfigDetailsSplit[0] == ConfigDetailsSplit[1])
              ConfigString += ConfigDetailsSplit[0];
            else
              ConfigString += ConfigDetailsSplit[0] + ":" + ConfigDetailsSplit[1];
          }

          if (ConfigString.length > 8)
          {
            visibleConfigString = ConfigString.substring(0,7) + "...";
            toolTipString = ConfigString.replace(/\+/g, "<br />");
          }
          else
          {
            visibleConfigString = ConfigString;
            toolTipString = "";
          }

          if (alternating)
            visibleConfigString += "<span style='font-family:icons'>&#xefcf;</span>";

          var newInnerHTML = '<button '+disabledStr+' style="color:black;" class="config_button tooltip" name="'+uniqueID+rowNumber+'" id="editMimicList_button'+uniqueID+rowNumber+'" onclick="openMimicListPopup(this,\'LED Range\');" value="' + ConfigDetails[i] + '">' + visibleConfigString;

          if (toolTipString != "")
            newInnerHTML += '<span class="tooltiptext">' + toolTipString + '</span>';
          newInnerHTML += '</button>';
          newCell.innerHTML += newInnerHTML;

          newCell.firstChild.value = ConfigDetails[fieldIndex];
          fieldIndex++;
          break;

        case 'K':
          var ConfigString = "";
          var ConfigDetailsSplit = ConfigDetails[fieldIndex].split(":");
          if (ConfigDetailsSplit.length == 2)
            ConfigString = ConfigDetailsSplit[0] + ":" + ConfigDetailsSplit[1];
          else
            ConfigString = ConfigDetailsSplit[0];
          var newText = '<button '+disabledStr+' class="config_button" name="'+uniqueID+rowNumber+'" id="editLightRange_button'+uniqueID+rowNumber+'" onclick="openLightRangePopup(this, '+uniqueID+rowNumber+', \'Light Range\');" value="' + ConfigDetails[i] + '">' + ConfigString;
          if (ConfigDetails[fieldIndex + 1] == 'I')
              newText += " <span style='font-family:icons'>&#xec60</span>";
          newText += '</button>';

          newCell.innerHTML = newText;
          newCell.firstChild.value = ConfigDetails[fieldIndex];
          fieldIndex++;
          break;

        case 'I':
          newCell.innerHTML = '<input '+disabledStr+' class="config_button" id="default_button_'+boardType+'_'+uniqueID+rowNumber+'-'+i+'" onclick="openIntegerPopup(this, '+fieldRange[boardType][i][3]+','+fieldRange[boardType][i][4]+',\''+fieldRange[boardType][i][0]+'\')">';
          if (ConfigDetails[fieldIndex] === undefined)
            newCell.firstChild.value = fieldRange[boardType][i][5];
          else
            newCell.firstChild.value = ConfigDetails[fieldIndex];
          fieldIndex++;
          break;

        case 'Y':
          newCell.innerHTML = '<button '+disabledStr+' class="config_button" id="default_button_'+boardType+'_'+uniqueID+rowNumber+'-'+i+'" onclick="openServoPopup(this,'+boardType+','+uniqueID+rowNumber+',\'Servo Action\');" value=""></button>';
          if (ConfigDetails[fieldIndex] === undefined)
            newCell.firstChild.value = fieldRange[boardType][i][5];
          else
          {
            newCell.firstChild.value = ConfigDetails[fieldIndex];
            newCell.firstChild.innerHTML = DecodeServoPosition(ConfigDetails[fieldIndex].substring(0,1), ConfigDetails[fieldIndex].substring(1));
          }
          fieldIndex++;
          break;

        case 'Q':
          newCell.innerHTML = '<select '+disabledStr+' style="height:26px;width:'+fieldRange[boardType][i][1][0]+'" onchange="ConfigSoundChange('+boardType+','+uniqueID+rowNumber+');" id="soundQueueType_'+boardType+"_"+uniqueID+rowNumber+'"><option value="N">Next</option><option value="Q">Queue</option><option value="I">Immediate</option><option value="R">Random</option><option value="=">Set variable</select>';
          if (typeof(ConfigDetails[fieldIndex]) == "undefined")
          {
            ConfigDetails[fieldIndex] = 0;
          }
          var option = $('#soundQueueType_'+boardType+"_"+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex] +'"]');
          if (option.length == 1)
            option[0].selected = true;
          fieldIndex++;
          break;

        case 'F':
          newCell.innerHTML = '<button '+disabledStr+' class="config_button" id="default_button_'+boardType+'_'+uniqueID+rowNumber+'-'+i+'" onclick="openSoundPopup(this);" value=""></button>';
          if (ConfigDetails[fieldIndex] === undefined)
            newCell.firstChild.value = "";
          else
         {
            newCell.firstChild.value = ConfigDetails[fieldIndex];
            newCell.firstChild.innerHTML = generateSoundText(ConfigDetails[fieldIndex]);
          }
          fieldIndex++;
          break;

        case 'G':
          newCell.innerHTML = '<button '+disabledStr+' class="config_button" id="default_button_'+boardType+'_'+uniqueID+rowNumber+'-'+i+'" onclick="openSoundPopup(this);" value=""></button>';
          newCell.innerHTML += '<span id="sound_label_'+boardType+"_"+uniqueID+rowNumber+'"> to <span>';
          if (ConfigDetails[fieldIndex] === undefined)
            newCell.firstChild.value = "";
          else
          {
            newCell.firstChild.value = ConfigDetails[fieldIndex];
            newCell.firstChild.innerHTML = generateSoundText(ConfigDetails[fieldIndex]);
          }
          // fieldIndex++;
          break;

        case 'H':
          newCell.innerHTML = '<button '+disabledStr+' class="config_button" id="default_button_'+boardType+'_'+uniqueID+rowNumber+'-'+i+'" onclick="openSoundPopup(this);" value=""></button>';
          newCell.innerHTML += "<select "+disabledStr+" id='Variable_"+boardType+"_"+uniqueID+rowNumber+"'><option value='$A'>A</option><option value='$B'>B</option><option value='$C'>C</option><option value='$D'>D</option><option value='$E'>E</option><option value='$F'>F</option><option value='$G'>G</option><option value='$H'>H</option><option value='$I'>I</option><option value='$J'>J</option><option value='$K'>K</option><option value='$L'>L</option><option value='$M'>M</option><option value='$N'>N</option><option value='$O'>O</option><option value='$P'>P</option><option value='$Q'>Q</option><option value='$R'>R</option><option value='$S'>S</option><option value='$T'>T</option><option value='$U'>U</option><option value='$V'>V</option><option value='$W'>W</option><option value='$X'>X</option><option value='$Y'>Y</option><option value='$Z'>Z</option></select>";
          if (ConfigDetails[fieldIndex-2] === undefined)
          {
            newCell.firstChild.value = "";
          }
          else
          {
            // document.getElementById("Variable"+uniqueID+rowNumber).value = "0";
            var option = $('#Variable_'+boardType+"_"+uniqueID+rowNumber).children('option[value="'+ ConfigDetails[fieldIndex-1] +'"]');
            var qType = document.getElementById('soundQueueType_'+boardType+"_"+uniqueID+rowNumber).value;
            if (qType == '=' && option.length > 0)
            {
              option[0].selected = true;
              newCell.firstChild.value = "";
            }
            else
            {
              newCell.firstChild.value = ConfigDetails[fieldIndex];
              newCell.firstChild.innerHTML = generateSoundText(ConfigDetails[fieldIndex]);
            }
          }
          fieldIndex++;
          break;

        case 'J':
          newCell.innerHTML = '<button '+disabledStr+' class="config_button" id="default_button_'+boardType+'_'+uniqueID+rowNumber+'-'+i+'" onclick="openSoundPopup(this);" value=""></button>';
          newCell.innerHTML += '<input '+disabledStr+' class="config_button" id="VariableValue_'+boardType+'_'+uniqueID+rowNumber+'" onclick="openIntegerPopup(this, '+fieldRange[boardType][i][3]+','+fieldRange[boardType][i][4]+',\'Set variable to value...\')">';
          if (ConfigDetails[fieldIndex-1] === undefined)
          {
            newCell.firstChild.value = "";
            document.getElementById("VariableValue_"+boardType+"_"+uniqueID+rowNumber).value = "0";
          }
          else
          {
            document.getElementById("VariableValue_"+boardType+"_"+uniqueID+rowNumber).value = ConfigDetails[fieldIndex-1];
            fieldIndex++;
            if (ConfigDetails[fieldIndex] === undefined)
              newCell.firstChild.value = "";
            else
            {
              newCell.firstChild.value = ConfigDetails[fieldIndex];
              newCell.firstChild.innerHTML = generateSoundText(ConfigDetails[fieldIndex]);
            }
          }
          fieldIndex++;
          break;

        default:
          break;
      }

      newCell.style.width = fieldRange[boardType][i][1][0];
    }
    else
    {
      fieldIndex++;
    }

  }

  if (!usage && !(boardType == 3))
  {
    cell = row.insertCell();
    cell.innerHTML = "<button title='Insert a new row below this one' class='config_image_button' style='' id='insertRow_button"+rowNumber+"' onclick='manipulateRows("+rowNumber+","+boardType+", \"Insert\")'><span style=\"font-family:icons;font-size: 17;color: darkblue;\"></span></button>";

    cell = row.insertCell();
    cell.innerHTML = "<button title='Delete this row' class='config_image_button' style='' id='duplicateRow_button"+rowNumber+"' onclick='manipulateRows("+rowNumber+","+boardType+", \"Delete\")'><span style=\"font-family:icons;font-size: 17;color: darkred;\"></span></button>";

    cell = row.insertCell();
    cell.innerHTML = "<button title='Try this' class='config_image_button' style='' id='tryItRow_button"+rowNumber+"' onclick='testMessageSend("+boardType+","+rowNumber+")' value='"+rowNumber+"'><span style=\"font-family:icons;font-size: 17;color: darkgreen;\"></span></button>";
  }

  switch (boardType)
  {
    case 3:
      updateInputRow(uniqueID+rowNumber);
      break;

    case 4:
      if (updateServoRow(boardType, uniqueID+rowNumber))
        ConfigServoBounceTransitionChange(boardType, uniqueID+rowNumber);
      break;

    case 5:
      ConfigMimicRangeChange(5, uniqueID+rowNumber);
      break;

    case 6:
      ConfigRelayChange(boardType, uniqueID+rowNumber);
      break;

    case 9:
    case 16:
      ConfigSoundChange(boardType, uniqueID+rowNumber);
      break;

    case 12:
      ConfigLightingTypeChange(boardType, uniqueID+rowNumber);
      break;
  }
}

function decodeLabel(switchlabel)
{
  var queryString = "http://" + "<?php echo $_SERVER['HTTP_HOST']?>" + "/api/label_api.php?function=lookup&data=" + switchlabel;
  var returnString = "";

  var jqxhr = $.ajax({url: queryString, async:false})
  .done(function( msg) {
    if (msg == '0')
      returnString = switchlabel;
    else
    {
      var newLabel = msg.split(",");
      returnString = newLabel[1];
    }
  })
  .fail(function() {
    alert( "Network error - couldn't contact the base station." );
  });

  return returnString;
}

function decodeMessageForDebug(payloadString)
{
  var returnString = "";
  var messageType = payloadString.substring(1, 2);
  switch (messageType)
  {
  	case "R":
  	  returnString = "Clock: Active: ";
  	  returnString += payloadString.substring(2,4);
  	  returnString += ":";
  	  returnString += payloadString.substring(4,6);
  	  break;

  	case "P":
  	  returnString = "Clock: Paused: ";
  	  returnString += payloadString.substring(2,4);
  	  returnString += ":";
  	  returnString += payloadString.substring(4,6);
  	  break;

  	case "S":
  	  returnString = decodeLabel(payloadString.substring(2,5));
  	  if (returnString != payloadString.substring(2,5))
  	    returnString += "(" + payloadString.substring(2,5) + ")";
  	  returnString += " On";
  	  break;

  	case "U":
  	  returnString = decodeLabel(payloadString.substring(2,5));
  	  if (returnString != payloadString.substring(2,5))
  	    returnString += "(" + payloadString.substring(2,5) + ")";
  	  returnString += " Off";
  	  break;

  	default:
  	  returnString = "Unknown";
  }

  return returnString;
}

function onMessageArrived(message)
{
var Processed;

  Processed = false;

  if (message.topic.substring(0,11) == "/ConfigData")
  {
    var ConfigDetails = message.payloadString.split(",");
    if (ConfigDetails[0] == "<S")
    {
      Processed = true;
      clearConfigLines(ConfigDetails[1]);
      rowsReceived = 0;
      lastModuleType = ConfigDetails[1];
    }

    if (ConfigDetails[0] == "<V")
    {
      Processed = true;
      if (ConfigDetails[1] == "16")
      {
          for (i=0; i< 6; i++)
          {
            Volumeid="Volume" + i + "_16";
            VolumeElement = document.getElementById(Volumeid);
            VolumeElement.value = parseInt(ConfigDetails[i+2]);
          }
      }
    }

    if (ConfigDetails[0] == "<I")
    {
      Processed = true;
      if (ConfigDetails[1] == "12")
      {
        for (i=0; i<16; i++)
        {
          LightInvertedid="Inverted" + i + "_12";
          LightInvertedElement = document.getElementById(LightInvertedid);
          if (ConfigDetails[i+2] == "1")
            LightInvertedElement.checked = true;
          else
            LightInvertedElement.checked = false;
        }
      }
    }

    if (ConfigDetails[0] == ">")
    {
      if (rowsReceived == 0)
      {
        Processed = true;
        switch(lastModuleType)
        {
          case "06":
            var cell;
            var configTableName = "ConfigTable" + lastModuleType;
            var configTable = document.getElementById(configTableName);
            var currentRow = 1;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + lastModuleType + "_" + currentRow;
            moduleType = parseInt(lastModuleType);
            var defaultString="SA00,0,0";
            populateRow(currentRow, moduleType, defaultString, false);
            break;

          case "05":
            var cell;
            var configTableName = "ConfigTable" + lastModuleType;
            var configTable = document.getElementById(configTableName);
            var currentRow = 1;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + lastModuleType + "_" + currentRow;
            moduleType = parseInt(lastModuleType);
            var defaultString="SA00,S,N+0:0,0:0:0,0:0:0,0,0,0,0";
            populateRow(currentRow, moduleType, defaultString, false);
            break;

          case "07":
            var cell;
            var configTableName = "ConfigTable" + lastModuleType;
            var configTable = document.getElementById(configTableName);
            var currentRow = 1;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + lastModuleType + "_" + currentRow;
            moduleType = parseInt(lastModuleType);
            var defaultString="SA00,0,,0,0,F,0,0,";
            populateRow(currentRow, moduleType, defaultString, false);
            break;

          case "09":
          case "16":
            var cell;
            var configTableName = "ConfigTable" + lastModuleType;
            var configTable = document.getElementById(configTableName);
            // var currentRow = configTable.rows.length - 1;
            var currentRow = 1;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + lastModuleType + "_" + currentRow;
            moduleType = parseInt(lastModuleType);
            var defaultString="SA00,Q,0,1";
            populateRow(currentRow, moduleType, defaultString, false);
            break;

          case "03":
            break;

          case "04":
            var cell;
            var configTableName = "ConfigTable" + lastModuleType;
            var configTable = document.getElementById(configTableName);
            var currentRow = 1;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + lastModuleType + "_" + currentRow;
            moduleType = parseInt(lastModuleType);
            var defaultString="SA00,0,0,,S90,1,,0,0,0,0,0,0,1";
            populateRow(currentRow, moduleType, defaultString, false);
            break;

          case "12":
            var cell;
            var configTableName = "ConfigTable" + lastModuleType;
            var configTable = document.getElementById(configTableName);
            var currentRow = 1;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + lastModuleType + "_" + currentRow;
            moduleType = parseInt(lastModuleType);
            var defaultString="SA00,0,0,S,0,0,0,0,0,0,0,0,0,0,0,0";
            populateRow(currentRow, moduleType, defaultString, false);
            break;
        }
      }

      switch(lastModuleType)
      {
        case "03":
          // add row numbers
          manipulateRows(1, lastModuleType, "Renumber");
          // and procees each row/fields so they show the right things :)
          var numRows = document.getElementById("ConfigTable03").rows.length;
          for (i=1; i<numRows;)
            i += updateInputRow(i);
          break;
        
        case "06":
        case "05":
        case "07":
        case "09":
        case "16":
        case "04":
        case "12":
          manipulateRows(1, lastModuleType, "Renumber");
          break;
      }
    }
    else
    {
      if (!Processed)
      {
        rowsReceived++;
        switch(ConfigDetails[0])
        {
          case "0":
            var option = $('#primaryClockRatio').children('option[value="'+ ConfigDetails[2] +'"]');
            option[0].selected = true;

            if (ConfigDetails[1] == 'P')
              ConfigDetails[1] = '24';
            else
              ConfigDetails[1] = '12';

            var option = $('#primaryClock1224').children('option[value="'+ ConfigDetails[1] +'"]');
            option[0].selected = true;

            document.getElementById('primaryClockStartTime').value = ConfigDetails[3]+ConfigDetails[4];
            document.getElementById('primaryClockStartTime').innerHTML = ConfigDetails[3]+":"+ConfigDetails[4];

            ConfigString = ConfigDetails[5].substr(1,3);
            if (ConfigDetails[5].substr(0,1) == 'S')
            {
              ConfigString +=  " On";
            }
            else
            {
              ConfigString +=  " Off";
            }
            document.getElementById('primaryClockPauseEvent').value = ConfigDetails[5];
            toolTipString = decodeLabel(ConfigDetails[5].substr(1,3));
            if (toolTipString != ConfigDetails[5].substr(1.3))
              ConfigString +=  '<span class="tooltiptext">' + toolTipString + '</span>';
            document.getElementById('primaryClockPauseEvent').innerHTML = ConfigString;

            ConfigString = ConfigDetails[6].substr(1,3);
            if (ConfigDetails[6].substr(0,1) == 'S')
            {
              ConfigString +=  " On";
            }
            else
            {
              ConfigString +=  " Off";
            }
            document.getElementById('primaryClockStartEvent').value = ConfigDetails[6];
            toolTipString = decodeLabel(ConfigDetails[6].substr(1,3));
            if (toolTipString != ConfigDetails[6].substr(1.3))
              ConfigString +=  '<span class="tooltiptext">' + toolTipString + '</span>';
            document.getElementById('primaryClockStartEvent').innerHTML = ConfigString;

            ConfigString = ConfigDetails[7].substr(1,3);
            if (ConfigDetails[7].substr(0,1) == 'S')
            {
              ConfigString +=  " On";
            }
            else
            {
              ConfigString +=  " Off";
            }
            document.getElementById('primaryClockResetEvent').value = ConfigDetails[7];
            toolTipString = decodeLabel(ConfigDetails[7].substr(1,3));
            if (toolTipString != ConfigDetails[7].substr(1.3))
              ConfigString +=  '<span class="tooltiptext">' + toolTipString + '</span>';
            document.getElementById('primaryClockResetEvent').innerHTML = ConfigString;

            ConfigString = ConfigDetails[8].substr(1,3);
            if (ConfigDetails[8].substr(0,1) == 'S')
            {
              ConfigString +=  " On";
            }
            else
            {
              ConfigString +=  " Off";
            }
            document.getElementById('primaryClockH+Event').value = ConfigDetails[8];
            toolTipString = decodeLabel(ConfigDetails[8].substr(1,3));
            if (toolTipString != ConfigDetails[8].substr(1.3))
              ConfigString +=  '<span class="tooltiptext">' + toolTipString + '</span>';
            document.getElementById('primaryClockH+Event').innerHTML = ConfigString;

            ConfigString = ConfigDetails[9].substr(1,3);
            if (ConfigDetails[9].substr(0,1) == 'S')
            {
              ConfigString +=  " On";
            }
            else
            {
              ConfigString +=  " Off";
            }
            document.getElementById('primaryClockH-Event').value = ConfigDetails[9];
            toolTipString = decodeLabel(ConfigDetails[9].substr(1,3));
            if (toolTipString != ConfigDetails[9].substr(1.3))
              ConfigString +=  '<span class="tooltiptext">' + toolTipString + '</span>';
            document.getElementById('primaryClockH-Event').innerHTML = ConfigString;

            ConfigString = ConfigDetails[10].substr(1,3);
            if (ConfigDetails[10].substr(0,1) == 'S')
            {
              ConfigString +=  " On";
            }
            else
            {
              ConfigString +=  " Off";
            }
            document.getElementById('primaryClockM+Event').value = ConfigDetails[10];
            toolTipString = decodeLabel(ConfigDetails[10].substr(1,3));
            if (toolTipString != ConfigDetails[10].substr(1.3))
              ConfigString +=  '<span class="tooltiptext">' + toolTipString + '</span>';
            document.getElementById('primaryClockM+Event').innerHTML = ConfigString;

            ConfigString = ConfigDetails[11].substr(1,3);
            if (ConfigDetails[11].substr(0,1) == 'S')
            {
              ConfigString +=  " On";
            }
            else
            {
              ConfigString +=  " Off";
            }
            document.getElementById('primaryClockM-Event').value = ConfigDetails[11];
            toolTipString = decodeLabel(ConfigDetails[11].substr(1,3));
            if (toolTipString != ConfigDetails[11].substr(1.3))
              ConfigString +=  '<span class="tooltiptext">' + toolTipString + '</span>';
            document.getElementById('primaryClockM-Event').innerHTML = ConfigString;

            document.getElementById("primaryClockConfigPage").style.display = "block";
            break;

          case "02":
            document.getElementById('secondaryClockBrightness').value = ConfigDetails[1];
            document.getElementById("secondaryClockConfigPage").style.display = "block";
            break;

          case "08":
            document.getElementById('matrixBrightness').value = ConfigDetails[1];
            var option = $('#matrixFont').children('option[value="'+ ConfigDetails[2] +'"]');
            option[0].selected = true;
            if (ConfigDetails[3] == 1)
              document.getElementById("matrixScroll").checked = true;
            else
              document.getElementById("matrixScroll").checked = false;

            document.getElementById("matrixConfigPage").style.display = "block";

            var option = $('#matrixAMPMstyle').children('option[value="'+ ConfigDetails[4] +'"]');
            option[0].selected = true;
            break;

          case "99":
            var BankDetails = ConfigDetails[1];
            var num = parseInt(ConfigDetails[2]);
            if (num < 10)
              BankDetails += "0";
            BankDetails += ConfigDetails[2];
            var currentRow = +rowsReceived - 1;
            inputPopupButtonNumber = "editInputPopup_button"+currentRow;
      	    document.getElementById(inputPopupButtonNumber).value = BankDetails;

      	    var inputTypeList = ["N","T","P","X"];
      	    var inputType = inputTypeList[parseInt(ConfigDetails[3])];
      	    if (inputType == "P" && (currentRow % 2 == 1))
      	      inputType =  "N";
            inputPopupButtonNumber = "manualinputtype"+currentRow+inputType;
      	    document.getElementById(inputPopupButtonNumber).checked = true;

            var x = "manualinputtype"+ (rowsReceived - 1) + "XT"
            if (inputType == "X")
            {
              document.getElementById(x).value = ConfigDetails[4];
            }
            else
            {
              document.getElementById(x).value = 0;
            }

            // inputSwitchType(document.getElementById(inputPopupButtonNumber), rowsReceived - 1);
            // document.getElementById("inputButtons").style.display = "block";
            populateRow(currentRow, 3, message.payloadString.substr(3), false);
            break;

          case "06":
            lastModuleType = ConfigDetails[0];
            var cell;
            var configTableName = "ConfigTable" + ConfigDetails[0];
            var configTable = document.getElementById(configTableName);
            var currentRow = configTable.rows.length;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + ConfigDetails[0] + "_" +currentRow;

            populateRow(currentRow, 6, message.payloadString.substr(3), false);

            break;

          case "05":
            lastModuleType = ConfigDetails[0];
            var cell;
            var configTableName = "ConfigTable" + ConfigDetails[0];
            var configTable = document.getElementById(configTableName);
            var currentRow = configTable.rows.length;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + ConfigDetails[0] + "_" +currentRow;

            populateRow(currentRow, 5, message.payloadString.substr(3), false);

            break;

          case "07":
            lastModuleType = ConfigDetails[0];
            var cell;
            var configTableName = "ConfigTable" + ConfigDetails[0];
            var configTable = document.getElementById(configTableName);
            var currentRow = configTable.rows.length;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + ConfigDetails[0] + "_" +currentRow;

            populateRow(currentRow, 7, message.payloadString.substr(3), false);

            break;

          case "04":
            lastModuleType = ConfigDetails[0];
            var cell;
            var configTableName = "ConfigTable" + ConfigDetails[0];
            var configTable = document.getElementById(configTableName);
            var currentRow = configTable.rows.length;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + ConfigDetails[0] + "_" +currentRow;
            populateRow(currentRow, 4, message.payloadString.substr(3), false);
            break;

          case "03":
            lastModuleType = ConfigDetails[0];
            var cell;
            var configTableName = "ConfigTable" + ConfigDetails[0];
            var configTable = document.getElementById(configTableName);
            var currentRow = configTable.rows.length;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + ConfigDetails[0] + "_" +currentRow;
            populateRow(currentRow, 3, message.payloadString.substr(3), false);
            break;

          case "09":
          case "12":
          case "16":
            lastModuleType = ConfigDetails[0];
            var cell;
            var configTableName = "ConfigTable" + ConfigDetails[0];
            var configTable = document.getElementById(configTableName);
            var currentRow = configTable.rows.length;
            var row = configTable.insertRow(-1);
            row.id = "configRow" + ConfigDetails[0] + "_" +currentRow;
            populateRow(currentRow, parseInt(ConfigDetails[0]), message.payloadString.substr(3), false);
            break;

          case "14": //DCC
            if (ConfigDetails[1] == 'R')
              document.getElementById("DCCReversed").checked = true;
            else
              document.getElementById("DCCReversed").checked = false;

            if (ConfigDetails[2] == 'Y')
              document.getElementById("OffsetBy4").checked = true;
            else
              document.getElementById("OffsetBy4").checked = false;

            for (var i=0; i<20; i++)
            {
              var BankRef = "DCCBank" + i;
              document.getElementById(BankRef).value = ConfigDetails[3+i];
            }
            break;
        }
      }
    }
  }

  if (message.topic.substring(0,10) == "/UsageData")
  {
    var ConfigDetails = message.payloadString.split(",");
    if (ConfigDetails[0] == "<Q")
    {
      lastModuleType = ConfigDetails[2];
      var moduleType = parseInt(lastModuleType);
      rowsReceived++;
      var configTableName = "UsageTable-" + ConfigDetails[1];
      var configTable = document.getElementById(configTableName);
      if (configTable == null)
      {
        var usageTableDiv = document.getElementById("usageTableDiv");

        var configTable = document.createElement('TABLE');
        configTable.style.borderStyle = "solid";
        configTable.id = "UsageTable-" + ConfigDetails[1];
        configTable.style.textAlign = "center";
        configTable.width = "100%";
        configTable.style.margin = "10px 0px";

        var tr = document.createElement('TR');

        configTable.appendChild(tr);

        var td = document.createElement('TD');

        td.appendChild(document.createTextNode(ConfigDetails[1]+" ("+ moduleTypeNames[moduleType] +")"));
        td.colSpan="999";
        td.style.borderStyle = "solid";
        td.style.fontSize = "x-large";
        tr.appendChild(td);

        usageTableDiv.appendChild(configTable);

        usageLineHeadings(moduleType, configTable, lastModuleType);
      }

      switch(ConfigDetails[2])
      {
        case "0":
        case "06":
        case "07":
          var cell;
          var currentRow = configTable.rows.length;
          var row = configTable.insertRow(-1);
          row.id = "configRow" + ConfigDetails[1] + "_" +currentRow;

          populateRow(currentRow, moduleType, message.payloadString.substr(3), true, configTable);

          break;

        case "05":
          var cell;
          var currentRow = configTable.rows.length;
          var row = configTable.insertRow(-1);
          row.id = "configRow" + ConfigDetails[1] + "_" +currentRow;

          populateRow(currentRow, moduleType, message.payloadString.substr(3), true, configTable);

          break;

        case "04":
          var cell;
          var currentRow = configTable.rows.length;
          var row = configTable.insertRow(-1);
          row.id = "configRow" + ConfigDetails[1] + "_" +currentRow;

          populateRow(currentRow, moduleType, message.payloadString.substr(3), true, configTable);

          break;

        case "03":
          var cell;
          var currentRow = configTable.rows.length;
          var row = configTable.insertRow(-1);
          row.id = "configRow" + ConfigDetails[1] + "_" +currentRow;

          populateRow(currentRow, moduleType, message.payloadString.substr(3), true, configTable);
          row.cells[0].innerHTML = ConfigDetails[3];

          break;

        case "09":
        case "12":
        case "16":
          lastModuleType = ConfigDetails[0];
          var cell;
          var currentRow = configTable.rows.length;
          var row = configTable.insertRow(-1);
          row.id = "configRow" + ConfigDetails[1] + "_" +currentRow;
          populateRow(currentRow, moduleType, message.payloadString.substr(3), true, configTable);
          break;
      }
    }
  }

  if (message.topic.substring(0,8) == "/Devices")
  {
  	var ConfigDetails = message.payloadString.split(",");
    if (message.payloadString == 'offline')
    {
      var x = document.getElementById("Sensors");
      var test = message.destinationName.substring(9);
      for (var i=0; i<x.length;i++)
      {
        var thisDevice = x[i].childNodes[0].nodeValue;
        if (thisDevice == test)
        {
          var pageType = x.selectedIndex;
          if (pageType == i)
          {
            displaySubpage(null);
          }
          x.remove(i);
        }
      }

      var elementID = "Sensors";
      elementID += test;
      var elementExists = document.getElementById(elementID);
      var x = document.getElementById("AllSensors");
      if (elementExists != null)
      {
        tempString = "<td class='deviceName'>";
        tempString += message.destinationName.substring(9);
        tempString += "</td><td class='deviceTD' style='color:white; background-color:red;'>OFFLINE</td><td class='deviceTD'><input type='button' value='Delete' onclick=\"deleteModule(this, \'"+message.destinationName.substring(9)+"\');\"></td></tr>";
        elementExists.innerHTML = tempString;
      }
      else
      {
        var x = document.getElementById("AllSensors");
        var tempString = "<tr id='Sensors";
        tempString += message.destinationName.substring(9);
        tempString += "'><td class='deviceName'>";
        tempString += message.destinationName.substring(9);
        tempString += "</td><td class='deviceTD' style='color:white; background-color:red;'>OFFLINE</td><td class='deviceTD'><input type='button' value='Delete' onclick='deleteModule(this, \""+message.destinationName.substring(9)+"\");'></td></tr>";
        x.innerHTML += tempString;
      }
    }
    else
    {
      if (message.payloadString != '')
  	  {
  	    if (ConfigDetails.length < 5)
  	    {
  	      ConfigDetails.push(4,ConfigDetails[3]);
  	      ConfigDetails[3] = ConfigDetails[2];
  	      ConfigDetails[2] = ConfigDetails[1];
  	      ConfigDetails[1] = "V1";
  	    }

  		  console.log(message.topic);
  		  var moduleName = message.topic.substring(9);
        var elementID = "Sensors";
        elementID += moduleName;

        if (ConfigDetails[0] != 17)
        {
          var found = false;
          var x = document.getElementById("Sensors");

          for (var i=0; i<x.length; i++)
          {
            if (x.options[i].label == moduleName)
              found = true;
          }

          if (!found)
          {
            var x = document.getElementById("Sensors");
            var option = document.createElement("option");
            option.text = moduleName;
            option.value = ConfigDetails[0];
            x.add(option);
          }
        }

    	  var elementExists = document.getElementById(elementID);
    	  var x = document.getElementById("AllSensors");
    	  if (elementExists != null)
    	  {
    			tempString = "<td class='deviceName'>";
    			tempString += moduleName;
    			tempString += "</td><td class='deviceTD' style='color:white; background-color:#4CAF50;'>ONLINE ";
    			if (ConfigDetails[4] == "0.0.0.0")
    			{
    			  tempString += '<span style="font-family:icons;">&#xf01f;</span>';
    			}
    			else
            if (ConfigDetails[4] == "localhost")
            {
              tempString += '<span style="font-family:icons;">&#xeef7;</span>';
            }
            else
            {
              tempString += '<span style="font-family:icons;">&#xf02b;</span>';
            }
    			tempString += "</td>";
    			tempString += "<td class='deviceTD'>"+ConfigDetails[1]+"</td>";
    			tempString += "<td class='deviceTD'>"+ConfigDetails[2]+"."+ConfigDetails[3]+"</td>";
    			if (ConfigDetails[0] != 17)
    			{
      			tempString += '<td class="deviceTD"><button onclick="switchConfigurePage(\'';
      			tempString += moduleName;
      			tempString += '\');"><span style="font-family:icons;">&#xefe2;</span> Configure</button></td>';
          }
          else
    			{
      			tempString += '<td class="deviceTD"></td>';
          }

    			if (ConfigDetails[1] != "")
    			{
            if ("<?php echo $_SERVER['SERVER_ADDR'] ?>" == "192.168.0.1" && ConfigDetails[4] != "0.0.0.0")
            {
    			    tempString += '<td class="deviceTD"><button onclick="remoteProgram(\'';
    		  	  tempString += ConfigDetails[4];
    		  	  tempString += "\',\'";
    		  	  tempString += ConfigDetails[1];
    			    tempString += '\');"><span style="font-family:icons;">&#xef1f;</span> Upgrade</button></td>';
            }
            else
      			{
        			tempString += '<td class="deviceTD"></td>';
            }
          }

          if (ConfigDetails[4] != "localhost")
          {
            tempString += '<td class="deviceTD"><button onclick="identifyModule(\'';
            tempString += moduleName;
            tempString += '\');"><span style="font-family:icons;">&#xefca;</span> Identify</button></td>';          
          }
          
          tempString += "</tr>";
          elementExists.innerHTML = tempString;
  		  }
  		  else
  		  {
    			var x = document.getElementById("AllSensors");
    			var tempString = "<tr id='Sensors";
    			tempString += moduleName;
    			tempString += "'><td class='deviceName'>";
    			tempString += moduleName;
    			tempString += "</td><td class='deviceTD' style='color:white; background-color:#4CAF50;'>ONLINE ";
    			if (ConfigDetails[4] == "0.0.0.0")
    			{
    			  tempString += '<span style="font-family:icons;">&#xf01f;</span>';
    			}
    			else
            if (ConfigDetails[4] == "localhost")
            {
              tempString += '<span style="font-family:icons;">&#xeef7;</span>';
            }
            else
            {
              tempString += '<span style="font-family:icons;">&#xf02b;</span>';
            }
    			tempString += "</td>";
    			tempString += "<td class='deviceTD'>"+ConfigDetails[1]+"</td>";
    			tempString += "<td class='deviceTD'>"+ConfigDetails[2]+"."+ConfigDetails[3]+"</td>";

    			if (ConfigDetails[0] != 17)
    			{
      			tempString += '<td class="deviceTD"><button onclick="switchConfigurePage(\'';
       			tempString += moduleName;
    	  		tempString += '\');"><span style="font-family:icons;">&#xefe2;</span> Configure</button></td>';
          }
          else
    			{
      			tempString += '<td class="deviceTD"></td>';
          }
    			if (ConfigDetails[1] != "")
    			{
    			  if ("<?php echo $_SERVER['SERVER_ADDR'] ?>" == "192.168.0.1" && ConfigDetails[4] != "0.0.0.0")
    			  {
    			    tempString += '<td class="deviceTD"><button onclick="remoteProgram(\'';
    		  	  tempString += ConfigDetails[4];
    		  	  tempString += "\',\'";
    		  	  tempString += ConfigDetails[1];
    			    tempString += '\');"><span style="font-family:icons;">&#xef1f;</span> Upgrade</button></td>';
    			  }
    			}

          if (ConfigDetails[4] != "localhost")
          {
            tempString += '<td class="deviceTD"><button onclick="identifyModule(\'';
            tempString += moduleName;
            tempString += '\');"><span style="font-family:icons;">&#xef1f;</span> Identify</button></td>';          
          }
          
    			tempString += "</tr>";
    			x.innerHTML += tempString;
  		  }
  		}
  	}
    displayModulesList();
  }

  if (message.topic.substring(0,9) == "/Messages")
  {
    var MessageType = message.payloadString.substring(0, 2);

    if (MessageType == "<R" || MessageType == "<P")
    {
      var messagehours = message.payloadString.substring(2,4);
      var messageminutes = message.payloadString.substring(4,6);
      var messageseconds = message.payloadString.substring(6,8);
      ampm = (message.payloadString.substring(8,9) == 'P');

      primaryhour = parseInt(messagehours);
      primaryminute = parseInt(messageminutes);
      primarysecond = parseInt(messageseconds);

      drawMenuClock(primaryhour, primaryminute, primarysecond, ampm);
  	  colonState = !colonState;
  	  if (MessageType == "<P")
  	    clockPaused = true;
  	  else
  	    clockPaused = false;

  	  runPause(false);
    }

    if ((MessageType == "<R" || MessageType == "<P") && (!document.getElementById("ignoreTimeMessages").checked))
    {
      var newDebugLine = decodeMessageForDebug(message.payloadString);
      var textlines = document.getElementById("DebugArea").value;
      var eachtextlines = textlines.split(/\n/);
      if (eachtextlines.length > 30)
      {
        document.getElementById("DebugArea").value = "";
        for (var i=1; i<30;i++)
        {
          document.getElementById("DebugArea").value += eachtextlines[i];
          document.getElementById("DebugArea").value += "\n";
        }
      }
      document.getElementById("DebugArea").value += newDebugLine;
      document.getElementById("DebugArea").value += "\n";
    }

    if (MessageType == "<S" || MessageType == "<U")
    {
      var newDebugLine = decodeMessageForDebug(message.payloadString);
      var textlines = document.getElementById("DebugArea").value;
      var eachtextlines = textlines.split(/\n/);
      if (eachtextlines.length > 30)
      {
        document.getElementById("DebugArea").value = "";
        for (var i=1; i<30;i++)
        {
          document.getElementById("DebugArea").value += eachtextlines[i];
          document.getElementById("DebugArea").value += "\n";
        }
      }
      document.getElementById("DebugArea").value += newDebugLine;
      document.getElementById("DebugArea").value += "\n";
    }
  }
}

function switchConfigurePage(selection)
{
  var elmnt = document.getElementById("Sensors");

  for(var i=0; i < elmnt.options.length; i++)
  {
    if(elmnt.options[i].text === selection) {
      elmnt.selectedIndex = i;
      break;
    }
  }
  displaySubpage(document.getElementById("Sensors"));
  pageSelected('UploadPage');
  return false;
}

function hideAllPages()
{
  var x = document.getElementsByClassName("page");
  var i;
  for (i = 0; i < x.length; i++)
  {
    x[i].style.display = "none";
    var oldMenu = x[i].id + "Menu";
    if ( document.getElementById(oldMenu).classList.contains('active') )
      document.getElementById(oldMenu).classList.remove('active');
  }
}

function showPage(pageID)
{
  document.getElementById(pageID).style.display = "block";
  document.getElementById(pageID + "Menu").classList.add("active");
}

function pageSelected(pageindex)
{
  var lastindex = pageindex.lastIndexOf("/");
  var selectedPage = pageindex.substr(lastindex+1);
  hideAllPages();
  showPage(selectedPage);
  return false;
}

function hideAllSettingsPages()
{
  var x = document.getElementsByClassName("settingspage");
  var i;
  for (i = 0; i < x.length; i++)
  {
    x[i].style.display = "none";
    var oldMenu = x[i].id + "Menu";
    if ( document.getElementById(oldMenu).classList.contains('active') )
      document.getElementById(oldMenu).classList.remove('active');
  }
}

function hideAllLabelsPages()
{
  var x = document.getElementsByClassName("labelspage");
  var i;
  for (i = 0; i < x.length; i++)
  {
    x[i].style.display = "none";
    var oldMenu = x[i].id + "Menu";
    if ( document.getElementById(oldMenu).classList.contains('active') )
      document.getElementById(oldMenu).classList.remove('active');
  }
}

function showSettingsPage(pageID)
{
  document.getElementById(pageID).style.display = "block";
  document.getElementById(pageID + "Menu").classList.add("active");
}

function showLabelsPage(pageID)
{
  document.getElementById(pageID).style.display = "block";
  document.getElementById(pageID + "Menu").classList.add("active");
}

function pageSettingsSelected(pageindex)
{
  var lastindex = pageindex.lastIndexOf("/");
  var selectedPage = pageindex.substr(lastindex+1);
  hideAllSettingsPages();
  showSettingsPage(selectedPage);
  return false;
}

function pagelabelsSelected(pageindex)
{
  var lastindex = pageindex.lastIndexOf("/");
  var selectedPage = pageindex.substr(lastindex+1);
  hideAllLabelsPages();
  showLabelsPage(selectedPage);
  return false;
}

function updateProgramButtonsLabels()
{
  var newBankObject = document.getElementById("programBank");
  var newBank = newBankObject.options[newBankObject.selectedIndex].value;
  var newOffsetObject = document.getElementById("programBankOffset");
  var newOffset = parseInt(newOffsetObject.options[newOffsetObject.selectedIndex].value);

  for (var i=0; i<=15; i++)
  {
    var newOffsetValue = i + newOffset;
    var tempString = "00" + newOffsetValue;
    tempString = newBank + tempString.substr(-2);
    var labelString = "manualInputTypeRow" + i;
    document.getElementById(labelString).innerHTML=tempString;
  }
}

function updateSwitchLabels()
{
  var newBankObject = document.getElementById("Bank");
  var newBank = newBankObject.options[newBankObject.selectedIndex].value;

  var newOffsetObject = document.getElementById("BankOffset");
  var newOffset = parseInt(newOffsetObject.options[newOffsetObject.selectedIndex].value);

  for (var i=0; i<=15; i++)
  {
    var newOffsetValue = i + newOffset;
    var tempString = "00" + newOffsetValue;
    var labelString = "switchId" + i;
    tempString = tempString.substr(-2);
    tempString = newBank + tempString;
    document.getElementById(labelString).innerHTML=tempString;

    labelString = "onButton" + i;
    document.getElementById(labelString).value="S" + tempString;

    labelString = "offButton" + i;
    document.getElementById(labelString).value="U" + tempString;

    labelString = "toggleButton" + i;
    document.getElementById(labelString).value="S" + tempString;
  }
}

function openEventPopup(eventType, x)
{
  document.getElementById("editEventPopup").style.display = "block";
  editEventObject = x;

  var blankButton = document.getElementById('_BLANK');

  var eventTypeID = x.value.substr(0,1);
  var eventBankID = x.value.substr(1,1);
  var eventTensID = x.value.substr(2,1);
  var eventUnitsID = x.value.substr(3,1);
  var eventIDTwo = x.value.substr(4,1);
  var eventIDThree = x.value.substr(5,1);
  var eventIDFour = x.value.substr(6,1);

  var labelDetails = document.getElementById("LabelSelect");
  var labelsExist = api_call_select_list(labelDetails);

  if (eventTypeID >= 'a' && eventTypeID <= 'x')
  {
    var eventFirstChar = eventTypeID;
    eventTypeID = 'R';
  }
  else
  {
    var decodeString = decodeLabel(x.value.substr(1,3));
    if (decodeString != x.value.substr(1,3))
    {
      eventTypeID = 'L';
    }
  }

  switch (eventType)
  {
    case 'W':
      document.getElementById("_SET").checked = true;

      if (eventTypeID == "")
      {
        var option = $('#triggerType').children('option[value="S"]');
        option[0].selected = true;

        var option = $('#EventBank').children('option[value="A"]');
        option[0].selected = true;

        var option = $('#EventTens').children('option[value="0"]');
        option[0].selected = true;

        var option = $('#EventUnits').children('option[value="0"]');
        option[0].selected = true;

        blankButton.checked = true;
      }
      else
      {
        var option = $('#triggerType').children('option[value="'+ eventTypeID +'"]');
        option[0].selected = true;

        var option = $('#EventBank').children('option[value="'+ eventBankID +'"]');
        option[0].selected = true;

        var option = $('#EventTens').children('option[value="'+ eventTensID +'"]');
        option[0].selected = true;

        var option = $('#EventUnits').children('option[value="'+ eventUnitsID +'"]');
        option[0].selected = true;

        blankButton.checked = false;
      }
      break;

    case 'L':
      blankButton.checked = false;
      document.getElementById("_LABEL").checked = true;

      var option = $('#LabelSelect').children('option[value="'+ x.value.substr(1,3) +'"]');
      option[0].selected = true;

      var option = $('#labelTriggerType').children('option[value="'+ x.value.substr(0,1) +'"]');
      option[0].selected = true;
      break;

    case 'I':
      document.getElementById("_INIT").checked = true;
      break;

    case 'S':
    case 'U':
      document.getElementById("_SET").checked = true;

      var option = $('#triggerType').children('option[value="'+ eventTypeID +'"]');
      option[0].selected = true;

      var option = $('#EventBank').children('option[value="'+ eventBankID +'"]');
      option[0].selected = true;

      var option = $('#EventTens').children('option[value="'+ eventTensID +'"]');
      option[0].selected = true;

      var option = $('#EventUnits').children('option[value="'+ eventUnitsID +'"]');
      option[0].selected = true;
      break;

    case 'E':
      switch (eventTypeID)
      {
        case 'S':
        case 'U':
          blankButton.checked = false;
          document.getElementById("_SET").checked = true;
          var option = $('#triggerType').children('option[value="'+ eventTypeID +'"]');
          option[0].selected = true;

          var option = $('#EventBank').children('option[value="'+ eventBankID +'"]');
          option[0].selected = true;

          var option = $('#EventTens').children('option[value="'+ eventTensID +'"]');
          option[0].selected = true;

          var option = $('#EventUnits').children('option[value="'+ eventUnitsID +'"]');
          option[0].selected = true;
          break;

        case 'D':
          blankButton.checked = false;
          document.getElementById("_ID").checked = true;
          var option = $('#EventStationID1').children('option[value="'+ eventBankID +'"]');
          option[0].selected = true;

          var option = $('#EventStationID2').children('option[value="'+ eventTensID +'"]');
          option[0].selected = true;

          var option = $('#EventIDOne').children('option[value="'+ eventUnitsID +'"]');
          option[0].selected = true;

          var option = $('#EventIDTwo').children('option[value="'+ eventIDTwo +'"]');
          option[0].selected = true;

          var option = $('#EventIDThree').children('option[value="'+ eventIDThree +'"]');
          option[0].selected = true;

          var option = $('#EventIDFour').children('option[value="'+ eventIDFour +'"]');
          option[0].selected = true;

          break;

        case 'R':
          blankButton.checked = false;
          document.getElementById("_TIMERANGE").checked = true;

          //var tempCharCode = eventFirstChar.charCodeAt(0) - 'a'.charCodeAt(0);
          var RangeLow = (eventFirstChar.charCodeAt(0) - 'a'.charCodeAt(0)) + 30;
          // var RangeLow =  30 + (((eventFirstChar.charCodeAt(0) - 128) >> 4) * 10) + ((eventFirstChar.charCodeAt(0) - 128) & 15)
          var option = $('#EventRange1').children('option[value="'+ RangeLow +'"]');
          option[0].selected = true;

          //var tempCharCode = eventBankID.charCodeAt(0);
          //var RangeHigh = 30 + (((eventBankID.charCodeAt(0) - 128) >> 4) * 10) + ((eventBankID.charCodeAt(0) - 128) & 15)
          var RangeHigh = (eventBankID.charCodeAt(0) - 'a'.charCodeAt(0)) + 30;
          var option = $('#EventRange2').children('option[value="'+ RangeHigh +'"]');
          option[0].selected = true;

          var option = $('#EventRangeMinuteTens').children('option[value="'+ eventTensID +'"]');
          option[0].selected = true;

          var option = $('#EventRangeMinuteUnits').children('option[value="'+ eventUnitsID +'"]');
          option[0].selected = true;
          break;

        case 'L':
          blankButton.checked = false;
          document.getElementById("_LABEL").checked = true;

          var option = $('#LabelSelect').children('option[value="'+ x.value.substr(1,3) +'"]');
          option[0].selected = true;

          var option = $('#labelTriggerType').children('option[value="'+ x.value.substr(0,1) +'"]');
          option[0].selected = true;
          break;

         case "":
           document.getElementById("_SET").checked = true;
           blankButton.checked = true;
    		   break;

		    case 'I':
          blankButton.checked = false;
          document.getElementById("_INIT").checked = true;
		      break;

    		default:
          blankButton.checked = false;
          document.getElementById("_CLOCK").checked = true;

          var option = $('#EventHourTens').children('option[value="'+ eventTypeID +'"]');
          option[0].selected = true;

          eventTimeHoursChange(document.getElementById("EventHoursTens"));

          var option = $('#EventHourUnits').children('option[value="'+ eventBankID +'"]');
          option[0].selected = true;

          var option = $('#EventMinuteTens').children('option[value="'+ eventTensID +'"]');
          option[0].selected = true;

          var option = $('#EventMinuteUnits').children('option[value="'+ eventUnitsID +'"]');
          option[0].selected = true;
          break;
  	  }
      break;

    case 'D':
      switch (eventTypeID)
      {
        case 'S':
        case 'U':
          blankButton.checked = false;
          document.getElementById("_SET").checked = true;
          var option = $('#triggerType').children('option[value="'+ eventTypeID +'"]');
          option[0].selected = true;

          var option = $('#EventBank').children('option[value="'+ eventBankID +'"]');
          option[0].selected = true;

          var option = $('#EventTens').children('option[value="'+ eventTensID +'"]');
          option[0].selected = true;

          var option = $('#EventUnits').children('option[value="'+ eventUnitsID +'"]');
          option[0].selected = true;
          break;

        case 'L':
          blankButton.checked = false;
          document.getElementById("_LABEL").checked = true;

          var option = $('#LabelSelect').children('option[value="'+ x.value.substr(1,3) +'"]');
          option[0].selected = true;

          var option = $('#labelTriggerType').children('option[value="'+ x.value.substr(0,1) +'"]');
          option[0].selected = true;
          break;

         case "":
           document.getElementById("_SET").checked = true;
           blankButton.checked = true;
    		   break;
  	  }
      break;

    case 'C':
      switch (eventTypeID)
      {
        case 'S':
        case 'U':
          blankButton.checked = false;
          document.getElementById("_SET").checked = true;
          var option = $('#triggerType').children('option[value="'+ eventTypeID +'"]');
          option[0].selected = true;

          var option = $('#EventBank').children('option[value="'+ eventBankID +'"]');
          option[0].selected = true;

          var option = $('#EventTens').children('option[value="'+ eventTensID +'"]');
          option[0].selected = true;

          var option = $('#EventUnits').children('option[value="'+ eventUnitsID +'"]');
          option[0].selected = true;
          break;

        case 'L':
          blankButton.checked = false;
          document.getElementById("_LABEL").checked = true;

          var option = $('#LabelSelect').children('option[value="'+ x.value.substr(1,3) +'"]');
          option[0].selected = true;

          var option = $('#labelTriggerType').children('option[value="'+ x.value.substr(0,1) +'"]');
          option[0].selected = true;
          break;
  	  }
      break;

    case 'T':
      document.getElementById("_ONLYCLOCK").checked = true;

      var option = $('#ClockHourTens').children('option[value="'+ eventTypeID +'"]');
      option[0].selected = true;

      var option = $('#ClockHourUnits').children('option[value="'+ eventBankID +'"]');
      option[0].selected = true;

      var option = $('#ClockMinuteTens').children('option[value="'+ eventTensID +'"]');
      option[0].selected = true;

      var option = $('#ClockMinuteUnits').children('option[value="'+ eventUnitsID +'"]');
      option[0].selected = true;
      break;

    case 'A':
      document.getElementById("_CLOCK").checked = true;

      var option = $('#EventHourTens').children('option[value="'+ eventTypeID +'"]');
      option[0].selected = true;

      var option = $('#EventHourUnits').children('option[value="'+ eventBankID +'"]');
      option[0].selected = true;

      var option = $('#EventMinuteTens').children('option[value="'+ eventTensID +'"]');
      option[0].selected = true;

      var option = $('#EventMinuteUnits').children('option[value="'+ eventUnitsID +'"]');
      option[0].selected = true;
      break;
  }

  var clockButton = document.getElementById('_CLOCK');
  var clockText = document.getElementById('_CLOCK_SPAN');
  var initButton = document.getElementById('_INIT');
  var initText = document.getElementById('_INIT_SPAN');
  var setButton = document.getElementById('_SET');
  var setText = document.getElementById('_SET_SPAN');
  var blankButton = document.getElementById('_BLANK');
  var blankText = document.getElementById('_BLANK_SPAN');
  var labelButton = document.getElementById('_LABEL');
  var labelText = document.getElementById('_LABEL_SPAN');
  var rangeButton = document.getElementById('_TIMERANGE');
  var rangeText = document.getElementById('_TIMERANGE_SPAN');

  switch (eventType)
  {
    case 'T':
      clockButton.style.display = "none";;
      clockText.style.display = "none";;
      setButton.style.display = "none";;
      setText.style.display = "none";;
      initButton.style.display = "none";;
      initText.style.display = "none";;
      blankButton.style.display = "none";;
      blankText.style.display = "none";;
      labelButton.style.display = "none";;
      labelText.style.display = "none";;
      rangeButton.style.display = "none";
      rangeText.style.display = "none";
      break;

    case 'E':
      clockButton.style.display = "inline";;
      clockText.style.display = "inline";;
      setButton.style.display = "inline";;
      setText.style.display = "inline";;
      initButton.style.display = "inline";;
      initText.style.display = "inline";;
      rangeButton.style.display = "inline";
      rangeText.style.display = "inline";
      blankButton.style.display = "none";;
      blankText.style.display = "none";;
      if (labelsExist)
      {
        labelButton.style.display = "inline";;
        labelText.style.display = "inline";;
      }
      else
      {
        labelButton.style.display = "none";;
        labelText.style.display = "none";;
      }
      break;

    case 'L':
      clockButton.style.display = "inline";;
      clockText.style.display = "inline";;
      setButton.style.display = "inline";;
      setText.style.display = "inline";;
      initButton.style.display = "inline";;
      initText.style.display = "inline";;
      blankButton.style.display = "none";;
      blankText.style.display = "none";;
      labelButton.style.display = "inline";;
      labelText.style.display = "inline";;
      rangeButton.style.display = "none";
      rangeText.style.display = "none";
      break;

    case 'D':
      clockButton.style.display = "none";;
      clockText.style.display = "none";;
      setButton.style.display = "inline";;
      setText.style.display = "inline";
      initButton.style.display = "none";
      initText.style.display = "none";
      rangeButton.style.display = "none";
      rangeText.style.display = "none";
      blankButton.style.display = "inline";
      blankText.style.display = "inline";
      if (labelsExist)
      {
        labelButton.style.display = "inline";
        labelText.style.display = "inline";
      }
      else
      {
        labelButton.style.display = "none";;
        labelText.style.display = "none";;
      }
      break;

    case 'C':
      clockButton.style.display = "none";;
      clockText.style.display = "none";;
      setButton.style.display = "inline";;
      setText.style.display = "inline";
      initButton.style.display = "none";
      initText.style.display = "none";
      rangeButton.style.display = "none";
      rangeText.style.display = "none";
      blankButton.style.display = "none";
      blankText.style.display = "none";
      if (labelsExist)
      {
        labelButton.style.display = "inline";
        labelText.style.display = "inline";
      }
      else
      {
        labelButton.style.display = "none";;
        labelText.style.display = "none";;
      }
      break;

    case 'W':
      clockButton.style.display = "none";;
      clockText.style.display = "none";;
      setButton.style.display = "none";;
      setText.style.display = "none";;
      initButton.style.display = "none";;
      initText.style.display = "none";;
      blankButton.style.display = "inline";;
      blankText.style.display = "inline";;
      labelButton.style.display = "none";;
      labelText.style.display = "none";;
      rangeButton.style.display = "none";
      rangeText.style.display = "none";
      break;

    case 'S':
      clockButton.style.display = "none";
      clockText.style.display = "none";
      setButton.style.display = "none";
      setText.style.display = "none";
      initButton.style.display = "none";
      initText.style.display = "none";
      blankButton.style.display = "none";
      blankText.style.display = "none";
      labelButton.style.display = "none";
      labelText.style.display = "none";
      rangeButton.style.display = "none";
      rangeText.style.display = "none";
      break;

    case 'A':
      clockButton.style.display = "inline";;
      clockText.style.display = "inline";;
      setButton.style.display = "inline";;
      setText.style.display = "inline";;
      initButton.style.display = "inline";;
      initText.style.display = "inline";;
      blankButton.style.display = "none";;
      blankText.style.display = "none";;
      rangeButton.style.display = "none";
      rangeText.style.display = "none";
      if (labelsExist)
      {
        labelButton.style.display = "inline";;
        labelText.style.display = "inline";;
      }
      else
      {
        labelButton.style.display = "none";;
        labelText.style.display = "none";;
      }
      break;
  }

  changeEventPopupType(eventType);
}

function closeEventPopup()
{
  document.getElementById("editEventPopup").style.display = "none";
}

function saveEventPopup()
{
  var ele = document.getElementsByName('eventType');
  var checkedVal;

  for(i = 0; i < ele.length; i++)
  {
    if(ele[i].checked)
      checkedVal = ele[i].value;
  }

  switch (checkedVal)
  {
    case "S":
      if (document.getElementById('_BLANK').checked)
      {
        editEventObject.value = "";
        editEventObject.innerHTML = "";
      }
      else
      {
        var e = document.getElementById("triggerType");
        var newValue = e.options[e.selectedIndex].value;
        var newInner = "";
        if (newValue == 'S')
        {
          var newInner = " On";
        }
        else
        {
          var newInner = " Off";
        }
        e = document.getElementById("EventBank");
        newValue += e.options[e.selectedIndex].value;
        e = document.getElementById("EventTens");
        newValue += e.options[e.selectedIndex].value;
        e = document.getElementById("EventUnits");
        newValue += e.options[e.selectedIndex].value;

        var toolTipString = decodeLabel(newValue.substr(1,3));

        editEventObject.value = newValue;

        if (toolTipString != newValue.substr(1.3))
          newInner +=  '<span class="tooltiptext">' + toolTipString + '</span>';
        editEventObject.innerHTML = newValue.substr(1,3) + newInner;
      }
      break;

    case "D":
      if (document.getElementById('_BLANK').checked)
      {
        editEventObject.value = "";
        editEventObject.innerHTML = "";
      }
      else
      {
        var newValue = 'D';
        var newInner = "[";
        e = document.getElementById("EventStationID1");
        newValue += e.options[e.selectedIndex].value;
        newInner += e.options[e.selectedIndex].value;
        e = document.getElementById("EventStationID2");
        newValue += e.options[e.selectedIndex].value;
        newInner += e.options[e.selectedIndex].value;
        newInner += "] ";
        e = document.getElementById("EventIDOne");
        newValue += e.options[e.selectedIndex].value;
        newInner += e.options[e.selectedIndex].value;
        e = document.getElementById("EventIDTwo");
        newValue += e.options[e.selectedIndex].value;
        newInner += e.options[e.selectedIndex].value;
        e = document.getElementById("EventIDThree");
        newValue += e.options[e.selectedIndex].value;
        newInner += e.options[e.selectedIndex].value;
        e = document.getElementById("EventIDFour");
        newValue += e.options[e.selectedIndex].value;
        newInner += e.options[e.selectedIndex].value;

        editEventObject.value = newValue;

        editEventObject.innerHTML = newInner;
      }
      break;

    case 'C':
      var e = document.getElementById("EventHourTens");
      var newValue = e.options[e.selectedIndex].value;
      e = document.getElementById("EventHourUnits");
      newValue += e.options[e.selectedIndex].value;
      newValue += ":";
      e = document.getElementById("EventMinuteTens");
      newValue += e.options[e.selectedIndex].value;
      e = document.getElementById("EventMinuteUnits");
      newValue += e.options[e.selectedIndex].value;

      hiddenValue = newValue.substr(0,2) + newValue.substr(3,2);

      editEventObject.value = hiddenValue;
      editEventObject.innerHTML = newValue;
      break;

    case 'O':
      var e = document.getElementById("ClockHourTens");
      var newValue = e.options[e.selectedIndex].value;
      e = document.getElementById("ClockHourUnits");
      newValue += e.options[e.selectedIndex].value;
      newValue += ":";
      e = document.getElementById("ClockMinuteTens");
      newValue += e.options[e.selectedIndex].value;
      e = document.getElementById("ClockMinuteUnits");
      newValue += e.options[e.selectedIndex].value;

      hiddenValue = newValue.substr(0,2) + newValue.substr(3,2);

      editEventObject.value = hiddenValue;
      editEventObject.innerHTML = newValue;
      break;

    case 'L':
      var e = document.getElementById("labelTriggerType");
      var newValue = e.options[e.selectedIndex].value;
      var newInner = "";
      if (newValue == 'S')
      {
        var newInner = " On";
      }
      else
      {
        var newInner = " Off";
      }
      e = document.getElementById("LabelSelect");
      newValue += e.options[e.selectedIndex].value;

      var toolTipString = decodeLabel(newValue.substr(1,3));

      editEventObject.value = newValue;

      if (toolTipString != newValue.substr(1.3))
        newInner +=  '<span class="tooltiptext">' + toolTipString + '</span>';
      editEventObject.innerHTML = newValue.substr(1,3) + newInner;
      break;

    case 'R':
      var e = document.getElementById("EventRange1");
      var toolTipString = "";

      var newValue = "0";
      newValue += e.options[e.selectedIndex].value - 30;
      newValue = newValue.substring(newValue.length - 2);
      newValue +="->";

      e = document.getElementById("EventRange2");
      var newValue2 = "0";
      newValue2 += e.options[e.selectedIndex].value - 30;
      newValue2 = newValue2.substring(newValue2.length - 2);
      newValue += newValue2;
      newValue += ":";

/*
      var hiddenValueA = 128;
      hiddenValueA |= (parseInt(newValue.substr(0,1)) << 4);
      hiddenValueA |= parseInt(newValue.substr(1,1));

      var hiddenValueB = 128;
      hiddenValueB |= (parseInt(newValue.substr(4,1)) << 4);
      hiddenValueB |= parseInt(newValue.substr(5,1));
      var hiddenValue = String.fromCharCode(hiddenValueA, hiddenValueB);

      e = document.getElementById("EventRangeMinuteTens");
      hiddenValue += e.options[e.selectedIndex].value;
      newValue += e.options[e.selectedIndex].value;

      e = document.getElementById("EventRangeMinuteUnits");
      hiddenValue += e.options[e.selectedIndex].value;
      newValue += e.options[e.selectedIndex].value;
*/
      var hiddenValueA = "a".charCodeAt(0);
      hiddenValueA += parseInt(newValue.substr(0,2));
//      hiddenValueA |= parseInt(newValue.substr(1,1));

      var hiddenValueB = "a".charCodeAt(0);
//      hiddenValueB |= (parseInt(newValue.substr(4,1)) << 4);
      hiddenValueB += parseInt(newValue.substr(4,2));
      var hiddenValue = String.fromCharCode(hiddenValueA, hiddenValueB);

      e = document.getElementById("EventRangeMinuteTens");
      hiddenValue += e.options[e.selectedIndex].value;
      newValue += e.options[e.selectedIndex].value;

      e = document.getElementById("EventRangeMinuteUnits");
      hiddenValue += e.options[e.selectedIndex].value;
      newValue += e.options[e.selectedIndex].value;
      
      editEventObject.value = hiddenValue;
      editEventObject.innerHTML = newValue;
      break;

    default:
      editEventObject.value = "INIT";
      editEventObject.innerHTML = "On Startup";
  }
  closeEventPopup();
}

function updateColourEditFields()
{
  if (document.getElementById("ColourPopupCheckbox").checked)
  {
    document.getElementById("Red1Value").disabled = true;
    document.getElementById("Red1").disabled = true;
    document.getElementById("Green1Value").disabled = true;
    document.getElementById("Green1").disabled = true;
    document.getElementById("Blue1Value").disabled = true;
    document.getElementById("Blue1").disabled = true;
  }
  else
  {
    document.getElementById("Red1Value").disabled = false;
    document.getElementById("Red1").disabled = false;
    document.getElementById("Green1Value").disabled = false;
    document.getElementById("Green1").disabled = false;
    document.getElementById("Blue1Value").disabled = false;
    document.getElementById("Blue1").disabled = false;
  }
}

function openTimePopup(x)
{
  document.getElementById("editTimePopup").style.display = "block";
  editEventObject = x;

  var eventHoursTens = x.value.substr(0,1);
  var eventHoursUnits = x.value.substr(1,1);
  var eventMinutesTens = x.value.substr(2,1);
  var EventMinutesUnits = x.value.substr(3,1);

  var option = $('#EventHoursTens').children('option[value="'+ eventHoursTens +'"]');
  option[0].selected = true;

  var option = $('#EventHoursUnits').children('option[value="'+ eventHoursUnits +'"]');
  option[0].selected = true;

  var option = $('#EventMinutesTens').children('option[value="'+ eventMinutesTens +'"]');
  option[0].selected = true;

  var option = $('#EventMinutesUnits').children('option[value="'+ EventMinutesUnits +'"]');
  option[0].selected = true;
}

function ConfigLightingTypeChange(boardType, rowNumber)
{
  var selectedVal = document.getElementById("LightingEffect"+rowNumber).value;
  switch (selectedVal)
  {
    case 'S':
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-6").style.display = "inline";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-7").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-8").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-9").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-10").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-11").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-12").style.visibility = "hidden";
      break;

    case 'F':
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-6").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-7").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-8").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-9").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-10").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-11").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-12").style.visibility = "visible";
      break;

    case 'R':
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-6").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-7").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-8").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-9").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-10").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-11").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-12").style.visibility = "visible";
      break;

    case 'Q':
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-6").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-7").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-8").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-9").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-10").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-11").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-12").style.visibility = "visible";
      break;

    case 'P':
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-6").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-7").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-8").style.visibility = "visible";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-9").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-10").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-11").style.visibility = "hidden";
      document.getElementById("default_button_"+boardType+"_"+rowNumber+"-12").style.visibility = "hidden";
      break;
  }
}

function ConfigServoBounceTransitionChange(boardType, rowNumber)
{
  var selectedVal = document.getElementById("ServoBounceNumber"+rowNumber).value;
  if (selectedVal == 0)
  {
    document.getElementById("ServoBounceWaveform"+rowNumber).style.visibility  = "hidden";
    document.getElementById("default_button_"+boardType+"_"+rowNumber+"-10").style.visibility = "hidden";
    document.getElementById("default_button_"+boardType+"_"+rowNumber+"-11").style.visibility = "hidden";
  }
  else
  {
    document.getElementById("ServoBounceWaveform"+rowNumber).style.visibility  = "visible";
    document.getElementById("default_button_"+boardType+"_"+rowNumber+"-10").style.visibility = "visible";
    document.getElementById("default_button_"+boardType+"_"+rowNumber+"-11").style.visibility = "visible";
  }
}

function updateServoRow(boardType, rowNumber)
{
var retVal = false;

  var selectedVal = document.getElementById("default_button_"+boardType+"_"+rowNumber+"-4").value.substring(0,1);
  if (selectedVal == 'S')
  {
    document.getElementById("default_button_"+boardType+"_"+rowNumber+"-5").style.visibility  = "visible";
    document.getElementById("ServoWaveform"+rowNumber).style.visibility = "visible";
    document.getElementById("ServoBounceNumber"+rowNumber).style.visibility = "visible";
    retVal = true;
  }
  else
  {
    document.getElementById("default_button_"+boardType+"_"+rowNumber+"-5").style.visibility  = "hidden";
    document.getElementById("ServoWaveform"+rowNumber).style.visibility = "hidden";
    document.getElementById("ServoBounceNumber"+rowNumber).style.visibility = "hidden";
    document.getElementById("ServoBounceWaveform"+rowNumber).style.visibility  = "hidden";
    document.getElementById("default_button_"+boardType+"_"+rowNumber+"-10").style.visibility = "hidden";
    document.getElementById("default_button_"+boardType+"_"+rowNumber+"-11").style.visibility = "hidden";
  }

  return retVal;
}

function updateInputRow(rowNumber)
{
  var retval = 1;

  var selectedVal = document.getElementById("InputType"+rowNumber).value;
  if (selectedVal == 3)
  {
    // display the timeout field
    document.getElementById("default_button_3_"+rowNumber+"-2").style.visibility  = "visible";
  }
  else
  {
    // hide it
    document.getElementById("default_button_3_"+rowNumber+"-2").style.visibility  = "hidden";
  }

  if (selectedVal == 2)
  {
    // grey out the next row and disable it
    var nextRow = document.getElementById("configRow03_"+(rowNumber+1));
    if (nextRow !== null)
    {
      // disable all the child input eements
      var numChildElements = nextRow.children.length;
      for (var i=0; i< numChildElements; i++)
        if (nextRow.children[i].children.length > 0)
          nextRow.children[i].children[0].disabled = true;
      // make the event and the pin type the same on the next row...
      document.getElementById("editInputPopup_button"+(rowNumber+1)).value = document.getElementById("editInputPopup_button"+rowNumber).value;
      document.getElementById("InputType"+(rowNumber+1)).value = document.getElementById("InputType"+rowNumber).value;
      retval = 2;
    }
  }
  else
  {
    // make it editble
    var nextRow = document.getElementById("configRow03_"+(rowNumber+1));
    if (nextRow !== null)
    {
      // disable all the child input eements
      var numChildElements = nextRow.children.length;
      for (var i=0; i< numChildElements; i++)
        if (nextRow.children[i].children.length > 0)
          nextRow.children[i].children[0].disabled = false;
      // if the values are from a previous 'pair' setting, then change them to something else!
      if (document.getElementById("InputType"+(rowNumber+1)).value == 2 && (rowNumber % 2) == 1)
        document.getElementById("InputType"+(rowNumber+1)).value = 0;
    }
  }

  return retval;
}

function swapColourInputFields(row)
{
  var colour1 = document.getElementById("editColour_button"+row+"-3").value;
  var colour2 = document.getElementById("editColour_button"+row+"-5").value;

  document.getElementById("editColour_button"+row+"-3").value = colour2;
  document.getElementById("editColour_button"+row+"-5").value = colour1;

  setColourButton(document.getElementById("editColour_button"+row+"-3"), false);
  setColourButton(document.getElementById("editColour_button"+row+"-5"), false);

  return;
}

function ConfigMimicRangeChange(boardType, x)
{

  var transition = document.getElementById("ConfigMimicTransitionType"+x).value;
  var colour1 = document.getElementById("editColour_button"+x+"-3");
  var colour2 = document.getElementById("editColour_button"+x+"-5");
  var range = document.getElementById("editMimicList_button"+x).value;
  var timing = document.getElementById("default_button_"+boardType+"_"+x+"-6");
  var duplicate = document.getElementById("default_button_"+boardType+"_"+x+"-7");
  var swapButton = document.getElementById("swap_button"+x);

  var rangeSplit = range.split("+");

  if (rangeSplit[0] == "N" && transition == "S")
  {
    colour1.style.visibility="visible";
    colour2.style.visibility="hidden";
    swapButton.style.visibility="hidden";
    duplicate.style.visibility="hidden";
  }
  else
  {
    if (transition == "D")
    {
      colour1.style.visibility="hidden";
      colour2.style.visibility="hidden";
      swapButton.style.visibility="hidden";
      duplicate.style.visibility="visible";
    }
    else
    {
      if (transition == "N" || transition == "Y")
      {
        colour1.style.visibility="visible";
        colour2.style.visibility="hidden";
        swapButton.style.visibility="hidden";
        duplicate.style.visibility="visible";
      }
      else
      {
        colour1.style.visibility="visible";
        colour2.style.visibility="visible";
        swapButton.style.visibility="visible";
        duplicate.style.visibility="hidden";
      }
    }
  }

	if (colour1 == "::")
	{
	  swapButton.style.visibility="hidden";
	}

  switch (transition)
  {
    case 'S':
    case 'D':
    case 'Y':
    case 'N':
      timing.style.visibility="hidden";
      break;

    default:
      timing.style.visibility="visible";
      break;
  }

  return;
}

function openColourPopup(x, useCurrent)
{
  document.getElementById("editColourPopup").style.display = "block";
  editEventObject = x;

  var colourValues = x.value.split(":");
  if (colourValues[0].length == 0)
  {
    document.getElementById("ColourPopupCheckbox").checked = true;
    document.getElementById("Red1Value").value=64;
    document.getElementById("Red1").value=64;
    document.getElementById("Green1Value").value=64;
    document.getElementById("Green1").value=64;
    document.getElementById("Blue1Value").value=64;
    document.getElementById("Blue1").value=64;
  }
  else
  {
    document.getElementById("ColourPopupCheckbox").checked = false;
    document.getElementById("Red1Value").value=colourValues[0];
    document.getElementById("Red1").value=colourValues[0];
    document.getElementById("Green1Value").value=colourValues[1];
    document.getElementById("Green1").value=colourValues[1];
    document.getElementById("Blue1Value").value=colourValues[2];
    document.getElementById("Blue1").value=colourValues[2];
  }

  if (useCurrent)
  {
    document.getElementById("ColourPopupCheckboxLabel").style.color = "black";
    document.getElementById("ColourPopupCheckbox").disabled = false;
  }
  else
  {
    document.getElementById("ColourPopupCheckboxLabel").style.color = "grey";
    document.getElementById("ColourPopupCheckbox").disabled = true;
  }

  updateColourEditFields();
  MimicUpdateSample1();
}

function closeColourPopup()
{
  document.getElementById("editColourPopup").style.display = "none";
}

function setColourButton(button, useCurrent)
{
  if (useCurrent)
  {
    var newColour = "rgb(127,127,127)";
  	button.style.background = newColour;
    button.innerHTML = "Current"
  }
  else
  {
    var colourValues = button.value.split(":");
    var red = parseInt(mapVal(0, 128, 0, 255, colourValues[0]));
  	var green = parseInt(mapVal(0, 128, 0, 255, colourValues[1]));
  	var blue = parseInt(mapVal(0, 128, 0, 255, colourValues[2]));

  	var newColour = "rgb("+red+","+green+","+blue+")";
  	button.style.background = newColour;
    button.innerHTML = button.value;

    if (red+green+blue > 375)
      button.style.color = 'black';
    else
      button.style.color = 'white';
  }
  return;
}

function saveColourPopup()
{
  var UseCurrent = document.getElementById("ColourPopupCheckbox").checked;

  if (UseCurrent)
  {
    editEventObject.value = "::";
    editEventObject.innerHTML = "Current";

    setColourButton(editEventObject, true)
  }
  else
  {
    var e = document.getElementById("Red1Value");
    var newValue = e.value;

    e = document.getElementById("Green1Value");
    newValue += ":"+e.value;

    e = document.getElementById("Blue1Value");
    newValue += ":"+e.value;

    editEventObject.value = newValue;

    setColourButton(editEventObject, false);
  }

  ConfigMimicRangeChange(5, editEventObject.name);

  closeColourPopup();
}

function openMimicRangePopup(x)
{
  var popupbox = document.getElementById("editMimicRangePopup");
  popupbox.style.display = "block";
  editEventObject = x;

  document.getElementById("popupMimicRangeTitle").innerHTML = "LED Range";

  var thisLEDRange = x.value;
  var LEDRange = thisLEDRange.split(":");

  document.getElementById("popupMimicRangeFrom").value = LEDRange[0];
  document.getElementById("popupMimicRangeTo").value = LEDRange[1];
  document.getElementById("popupMimicInputFrom").value = LEDRange[0];
  document.getElementById("popupMimicInputTo").value = LEDRange[1];

}

function closeMimicRangePopup()
{
  document.getElementById("editMimicRangePopup").style.display = "none";
}

function saveMimicRangePopup()
{
  var newValue = document.getElementById("popupMimicRangeFrom").value + ":";
  newValue += document.getElementById("popupMimicInputTo").value;

  if (document.getElementById("popupMimicRangeFrom").value == document.getElementById("popupMimicRangeTo").value)
    var newText = document.getElementById("popupMimicRangeFrom").value;
  else
    var newText = newValue;

  editEventObject.value = newValue;
  editEventObject.innerHTML = newText;

  closeMimicRangePopup();
}

function openLightRangePopup(x, y, heading)
{
  var popupbox = document.getElementById("editLightRangePopup");
  popupbox.style.display = "block";
  editEventObject = x;
  editLightingGroupObject = y;

  var LightingUnique = document.getElementById("LightingUnique"+y).value;

  document.getElementById("popupLightRangeTitle").innerHTML = heading;

  var thisLightRange = x.value;
  var LightRange = thisLightRange.split(":");

  if (LightRange.length == 1)
  {
    LightRange[1] = LightRange[0];
  }

  document.getElementById("popupLightRangeFrom").value = LightRange[0];
  document.getElementById("popupLightRangeTo").value = LightRange[1];
  document.getElementById("popupLightInputFrom").value = LightRange[0];
  document.getElementById("popupLightInputTo").value = LightRange[1];

  if (LightingUnique == 'I')
    document.getElementById("LightingGroupCheckbox").checked = true;
  else
    document.getElementById("LightingGroupCheckbox").checked = false;

}

function closeLightRangePopup()
{
  document.getElementById("editLightRangePopup").style.display = "none";
}

function saveLightRangePopup()
{
  if (document.getElementById("popupLightRangeFrom").value != document.getElementById("popupLightInputTo").value)
  {
    var newValue = document.getElementById("popupLightRangeFrom").value + ":";
    newValue += document.getElementById("popupLightInputTo").value;
    var newText = newValue;
    if(document.getElementById("LightingGroupCheckbox").checked)
    {
      document.getElementById("LightingUnique"+editLightingGroupObject).value = "I";
      newText += " <span style='font-family:icons'>&#xec60</span>";
    }
    else
      document.getElementById("LightingUnique"+editLightingGroupObject).value = "G";
  }
  else
  {
    var newValue = document.getElementById("popupLightRangeFrom").value;
    document.getElementById("LightingUnique"+editLightingGroupObject).value = "G";
    var newText = newValue;
  }

  editEventObject.value = newValue;
  editEventObject.innerHTML = newText;

  closeLightRangePopup();
}

function openIntegerPopup(x, min, max, heading)
{
  var popupbox = document.getElementById("editIntegerPopup");
  popupbox.style.display = "block";
  editEventObject = x;

  document.getElementById("popupIntegerTitle").innerHTML = heading;

  document.getElementById("popupIntegerRange").min = min;
  document.getElementById("popupIntegerRange").max = max;

  var thisInteger = x.value;
  document.getElementById("popupIntegerInput").value = thisInteger;
  document.getElementById("popupIntegerRange").value = thisInteger;

}

function closeIntegerPopup()
{
  document.getElementById("editIntegerPopup").style.display = "none";
}

function saveIntegerPopup()
{
  var newValue = document.getElementById("popupIntegerInput").value;

  if (parseInt(newValue) < parseInt(document.getElementById("popupIntegerRange").min))
    newValue = document.getElementById("popupIntegerRange").min;

  var max = document.getElementById("popupIntegerRange").max;
  if (parseInt(newValue) > parseInt(max))
    newValue = document.getElementById("popupIntegerRange").max;

  editEventObject.value = newValue;
  editEventObject.innerHTML = newValue;

  closeIntegerPopup();
}

function openServoPopup(x, board, row, heading)
{
  var popupbox = document.getElementById("editServoPopup");
  popupbox.style.display = "block";
  editServoEventObject = x;
  editServoEventBoard = board;
  editServoEventRow = row;

  document.getElementById("popupServoTitle").innerHTML = heading;

  var servoType = x.value.substring(0,1);
  var thisValue = x.value.substring(1);

  document.getElementById("popupServoRange").value = 90;
  document.getElementById("popupServoInput").value = 90;
  document.getElementById("popupSolenoidPulse").value = 10;
  document.getElementById("popupKatoPulse").value = 50;
  document.getElementById("popupStallPulse").value = 50;
  document.getElementById("popupServoInteractive").checked = false;

  switch (servoType)
  {
    case 'S':
      var x = document.getElementById("_SERVO");
      document.getElementById("popupServoRange").value = parseInt(thisValue);
      document.getElementById("popupServoInput").value = parseInt(thisValue);
      x.checked = true;
      break;

    case 'H':
      var x = document.getElementById("_SIGNAL_HEAD");
      x.checked = true;
      var servoVal = parseInt(thisValue);
      if (servoVal > 100)
      {
        servoVal = servoVal - 100;
        document.getElementById("poupSignalHeadFlashing").checked = true;
      }
      else
        document.getElementById("poupSignalHeadFlashing").checked = false;

	    var option = $('#poupSignalHeadPattern').children('option[value="'+servoVal+'"]');
	    option[0].selected = true;
      break;

    case 'P':
      var x = document.getElementById("_SOLENOID");
      x.checked = true;
	    var option = $('#popupSolenoidDirection').children('option[value="'+thisValue.substring(0,1)+'"]');
	    option[0].selected = true;
	    document.getElementById("popupSolenoidPulse").value = parseInt(thisValue.substring(1));
      break;

    case 'K':
      var x = document.getElementById("_KATO");
      x.checked = true;
	    var option = $('#popupKatoDirection').children('option[value="'+thisValue.substring(0,1)+'"]');
	    option[0].selected = true;
	    document.getElementById("popupKatoPulse").value = parseInt(thisValue.substring(1));
      break;

    case 'T':
      var x = document.getElementById("_STALL");
      x.checked = true;
	    var option = $('#popupStallDirection').children('option[value="'+thisValue.substring(0,1)+'"]');
	    option[0].selected = true;
	    document.getElementById("popupStallPulse").value = parseInt(thisValue.substring(1));
      break;

    case 'R':
      var x = document.getElementById("_RELAY");
      x.checked = true;
	    var option = $('#popupRelayAction').children('option[value="'+thisValue.substring(0,1)+'"]');
	    option[0].selected = true;
	    if (thisValue.length > 1)
  	    document.getElementById("popupRelayPulse").value = parseInt(thisValue.substring(1));
  	  else
  	    document.getElementById("popupRelayPulse").value = 50;
  	  
	    showRelayPulseTime();
      break;
  }
  changeServoPopupType(x);

}

function changeServoPopupType(x)
{
  var Servo = document.getElementById('popupServo');
  var Signal = document.getElementById('popupSignalHead');
  var Solenoid = document.getElementById('popupSolenoid');
  var Kato = document.getElementById('popupKato');
  var Stall = document.getElementById('popupStallMotor');
  var Relay = document.getElementById('popupRelay');

  Servo.style.display = 'none';
  Signal.style.display = 'none';
  Solenoid.style.display = 'none';
  Kato.style.display = 'none';
  Stall.style.display = 'none';
  Relay.style.display = 'none';

  switch (x.value)
  {
    case 'S':
      Servo.style.display = 'block';
      break;

    case 'H':
      Signal.style.display = 'block';
      break;

    case 'P':
      Solenoid.style.display = 'block';
      break;

    case 'K':
      Kato.style.display = 'block';
      break;

    case 'T':
      Stall.style.display = 'block';
      break;
      
    case 'R':
      Relay.style.display = 'block';
      showRelayPulseTime();
      break;
  }
}

function closeServoPopup()
{
  document.getElementById("editServoPopup").style.display = "none";
}

function sendInteractiveServo(x)
{
  if (document.getElementById("popupServoInteractive").checked)
  {
    sendInteractiveServoMessage(x.value, editServoEventRow);
  }
}

function showRelayPulseTime()
{
  var currentOption = document.getElementById('popupRelayAction').value;
  if (currentOption != "2")
  {
    document.getElementById("relayPulseTimeTitle").style.visibility = "hidden";
    document.getElementById("relayPulseTime").style.visibility = "hidden";
  }
  else
  {
    document.getElementById("relayPulseTimeTitle").style.visibility = "visible";
    document.getElementById("relayPulseTime").style.visibility = "visible";
  }
}

function DecodeServoPosition(checkedVal, newValue)
{
var returnVal = "<span style='font-family:icons;'>";

  switch (checkedVal)
  {
    case 'S':
      returnVal = returnVal + "&#xeff3;</span> ";
      returnVal = returnVal + newValue;
      break;

    case 'H':
      returnVal = returnVal + "&#xf016;</span> ";
      returnVal = returnVal + "<span style='font-family:typicons;'>";
      if (newValue > 100)
      {
        newValue -= 100;
        var onChar = "&#xe000;";
      }
      else
        var onChar = "&#xe0B2;";

//      var pos = newValue / 4;
      for (var i = 0; i< 16; i++)
      {
        if (newValue == SignalHeadValue[i])
        {
          pos = i;
        }
      }

      for (i = 1; i <= 10; i = i*2)
      {
//        var fred = pos & i;
        if ((pos & i) >= 1)
        {
          returnVal = returnVal + onChar;
        }
        else
        {
          returnVal = returnVal + "&#xe0B1;";
        }
      }
      returnVal = returnVal + "</span>";
      break;

    case 'P':
      returnVal = returnVal + "&#xee84;</span> ";
      returnVal = returnVal + "<span style='font-family:typicons;'>";
      if (newValue.substring(0,1) == 'L')
      {
        returnVal = returnVal + "&#xe00D;"
      }
      else
      {
        returnVal = returnVal + "&#xe01A;"
      }
      returnVal = returnVal + "</span> (";
      returnVal = returnVal + newValue.substring(1);
      returnVal = returnVal + ")";
      break;

    case 'K':
      returnVal = returnVal + "&#xefae;</span> ";
      returnVal = returnVal + "<span style='font-family:typicons;'>";
      if (newValue.substring(0,1) == 'L')
      {
        returnVal = returnVal + "&#xe00D;"
      }
      else
      {
        returnVal = returnVal + "&#xe01A;"
      }
      returnVal = returnVal + "</span> (";
      returnVal = returnVal + newValue.substring(1);
      returnVal = returnVal + ")";
      break;

    case 'T':
      returnVal = "<span style='font-family:icons;'>&#xe892;</span> ";
      returnVal = returnVal + "<span style='font-family:typicons;'>";
      if (newValue.substring(0,1) == 'L')
      {
        returnVal = returnVal + "&#xe00D;"
      }
      else
      {
        returnVal = returnVal + "&#xe01A;"
      }
      returnVal = returnVal + "</span> (";
      returnVal = returnVal + newValue.substring(1);
      returnVal = returnVal + ")";
      returnVal = returnVal + "</span>";
      break;
 
    case 'R':
      returnVal = "<span style='font-family:icons;'>&#xeed9;</span> ";
      if (newValue.substring(0,1) == '0')
      {
        returnVal = returnVal + " OFF "
      }
      if (newValue.substring(0,1) == '1')
      {
        returnVal = returnVal + " ON "
      }
      if (newValue.substring(0,1) == '2')
      {
        returnVal = returnVal + " MOM "
        returnVal = returnVal + "(" + newValue.substring(1);
        returnVal = returnVal + ")";
      }
      break;
  }

  return returnVal;
}

function saveServoPopup()
{
var newValue = 0;
var newText = "";

  var ele = document.getElementsByName('servoType');
  var checkedVal;

  for(i = 0; i < ele.length; i++)
  {
    if(ele[i].checked)
      checkedVal = ele[i].value;
  }

  switch (checkedVal)
  {
    case 'S':
      newValue = document.getElementById("popupServoInput").value;

      if (parseInt(newValue) < parseInt(document.getElementById("popupServoRange").min))
        newValue = document.getElementById("popupServoRange").min;

      var max = document.getElementById("popupServoRange").max;
      if (parseInt(newValue) > parseInt(max))
        newValue = document.getElementById("popupServoRange").max;
      break;

    case 'H':
      var e = document.getElementById("poupSignalHeadPattern");
      newValue = parseInt(e.options[e.selectedIndex].value);
      e = document.getElementById("poupSignalHeadFlashing");
      if (e.checked)
      {
        newValue = parseInt(newValue) + 100;
      }
      break;

    case 'P':
      var e = document.getElementById("popupSolenoidDirection");
      newValue = e.options[e.selectedIndex].value;
      newValue += document.getElementById("popupSolenoidPulse").value;
      break;

    case 'K':
      var e = document.getElementById("popupKatoDirection");
      newValue = e.options[e.selectedIndex].value;
      newValue += document.getElementById("popupKatoPulse").value;
      break;

    case 'T':
      var e = document.getElementById("popupStallDirection");
      newValue = e.options[e.selectedIndex].value;
      newValue += document.getElementById("popupStallPulse").value;
      break;

    case 'R':
      var e = document.getElementById("popupRelayAction");
      newValue = e.options[e.selectedIndex].value;
      newValue += document.getElementById("popupRelayPulse").value;
      break;
  }

  editServoEventObject.value = checkedVal + newValue;
  newText = DecodeServoPosition(checkedVal, newValue);
  editServoEventObject.innerHTML = newText;

  closeServoPopup();

  if (updateServoRow(editServoEventBoard, editServoEventRow))
    ConfigServoBounceTransitionChange(editServoEventBoard, editServoEventRow);

}

function generateSoundText(input)
{
var out = "";

  switch (input.substring(0,1))
  {
    case 'T':
      out = "Time";
      break;

    case '^':
      out = "T > " + input.substring(1);
      break;

    case '+':
      out = "T + " + input.substring(1);
      break;

    default:
      out = input;
      break;
  }

  var loops=input.split(":")
  if (loops.length > 1)
  {
    if (loops[1] == "-1")
      out = loops[0] + " <span style='font-family:icons'>&#xef4d;</span>";
    else
      out = loops[0] + " <span style='font-family:icons'>&#xef80;</span> " + loops[1];
  }

  return out;
}

function openSoundPopup(x)
{
  var popupbox = document.getElementById("editSoundPopup");
  popupbox.style.display = "block";
  editSoundEventObject = x;

  var thisInteger = x.value;
  var values = thisInteger.split(":");
  switch (values[0].substr(0,1))
  {
    case '$':
  	  var option = $('#popupSoundType').children('option[value="V"]');
	    option[0].selected = true;
	    var option = $('#popupSoundVariableNumber').children('option[value="'+values[0]+'"]');
	    option[0].selected = true;
      break;

    case 'T':
  	  var option = $('#popupSoundType').children('option[value="T"]');
	    option[0].selected = true;
	    var option = $('#popupSoundTimeType').children('option[value="T"]');
  	  option[0].selected = true;
      break;

    case '+':
  	  var option = $('#popupSoundType').children('option[value="T"]');
	    option[0].selected = true;
	    var timeval = parseInt(values[0].substr(1,));
  	  document.getElementById("popupSoundTimePlusValue").value = timeval;
	    var option = $('#popupSoundTimeType').children('option[value="+"]');
	    option[0].selected = true;
      break;

    case '^':
  	  var option = $('#popupSoundType').children('option[value="T"]');
	    option[0].selected = true;
	    var timeval = parseInt(values[0].substr(1));
  	  document.getElementById("popupSoundTimeNearestValue").value = timeval;
	    var option = $('#popupSoundTimeType').children('option[value="^"]');
	    option[0].selected = true;
      break;

    default:
      var option = $('#popupSoundType').children('option[value="I"]');
      document.getElementById("popupSoundValue").value = values[0];
      option[0].selected = true;
      break;
  }

  if (values[1] === undefined)
    values[1] = "S";

  if (values[1] == "-1")
    values[1] = "F";

  switch (values[1].substr(0,1))
  {
    case '$':
      var option = $('#popupSoundRepeatType').children('option[value="V"]');
      option[0].selected = true;
      var option = $('#popupSoundRepeatVariableNumber').children('option[value="'+values[1]+'"]');
      option[0].selected = true;
      break;

    case 'S':
  	  var option = $('#popupSoundRepeatType').children('option[value="S"]');
	    option[0].selected = true;
	    document.getElementById("popupSoundRepeatValue").value = 2;
      break;

    case 'F':
  	  var option = $('#popupSoundRepeatType').children('option[value="F"]');
	    option[0].selected = true;
	    document.getElementById("popupSoundRepeatValue").value = -1;
      break;

    default:
	    var option = $('#popupSoundRepeatType').children('option[value="I"]');
	    option[0].selected = true;
	    document.getElementById("popupSoundRepeatValue").value = values[1];
      break;
  }

  if (values[0].substr(0,1) == '')
  {
    document.getElementById("popupSoundBlank").checked = true;
  }
  else
  {
    document.getElementById("popupSoundBlank").checked = false;
  }
  changeSoundPopupBlank(document.getElementById("popupSoundBlank"));
}

function closeSoundPopup()
{
  document.getElementById("editSoundPopup").style.display = "none";
}

function saveSoundPopup()
{
  var newValue = "";
  var newText = "";

  if (document.getElementById("popupSoundBlank").checked == false)
  {
    var newType = document.getElementById("popupSoundType").value;

    switch (newType)
    {
      case 'I':
        var newValue = document.getElementById("popupSoundValue").value;
        break;

      case 'V':
        var newValue = document.getElementById("popupSoundVariableNumber").value;
        break;

      case 'T':
        var newValue = document.getElementById("popupSoundTimeType").value;
        switch (newValue)
        {
          case "T":
            break;

          case "+":
            newValue = "+" + document.getElementById("popupSoundTimePlusValue").value;
            break;

          case "^":
            newValue = "^" + document.getElementById("popupSoundTimeNearestValue").value;
            break;
        }
        break;
    }

    if (newType != 'T')
    {
      var newRepeatType = document.getElementById("popupSoundRepeatType").value;

      switch (newRepeatType)
      {
        case 'I':
          newValue += ":" + document.getElementById("popupSoundRepeatValue").value;
          break;

        case 'V':
          newValue += ":" + document.getElementById("popupSoundRepeatVariableNumber").value;
          break;

        case 'F':
          newValue += ":-1";
          break;

        case 'S':
          break;
      }
    }
  }
  else
  {
    newValue = "";
  }

  newText = generateSoundText(newValue);
  editSoundEventObject.value = newValue;
  editSoundEventObject.innerHTML = newText;

  closeSoundPopup();
}

function openImportFilePopup(x, moduleType, max, heading)
{
  var popupbox = document.getElementById("editImportFilePopup");
  popupbox.style.display = "block";
  fileModuleType = moduleType;

  document.getElementById("popupFileTitle").innerHTML = heading;

}

function closeImportFilePopup()
{
  document.getElementById("editImportFilePopup").style.display = "none";
}

function saveImportFilePopup()
{
  var newValue = document.getElementById("fileImportInput").value;
  closeImportFilePopup();
  if (fileModuleType != -1)
    importConfigFile(fileModuleType);
  else
    importLabelsFile(fileModuleType);
}

function openSoundFilePopup(heading)
{
  var popupbox = document.getElementById("editImportSoundPopup");
  popupbox.style.display = "block";

  var statusP = document.getElementById('upload_progress');

  statusP.innerHTML = '';
  statusP.style.width = "0%";

  document.getElementById("popupFileTitle").innerHTML = heading;
}

function closeImportSoundPopup()
{
  document.getElementById("editImportSoundPopup").style.display = "none";
}

function saveImportSoundPopup()
{
  var newValue = document.getElementById("soundImportInput").value;
  closeImportSoundPopup();
  importSoundFile(fileModuleType);
}

function exportToCsv(filename, csvFile)
{
	var blob = new Blob([csvFile], { type: 'text/csv;charset=utf-8;' });
	if (navigator.msSaveBlob)
	{
		navigator.msSaveBlob(blob, filename);
	}
	else
	{
		var link = document.createElement("a");
		if (link.download !== undefined)
		{
			var url = URL.createObjectURL(blob);
			link.setAttribute("href", url);
			link.setAttribute("download", filename);
			link.style.visibility = 'hidden';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
		}
	}
}

function exportConfigFile(moduleType)
{
  var SystemName = document.getElementById("SystemName").innerText;

  var y = document.getElementById("Sensors");
  var moduleName = SystemName + "_" + y.selectedOptions[0].text;

  var nextRow = "";

  var allRows = "'"+ moduleType + "'";
  allRows += '\n';

  var configTableName = "ConfigTable" + moduleType;
  var configTable = document.getElementById(configTableName);
  var numRows = configTable.rows.length;

  for (var i=1; i<numRows; i++)
  {
	  var nextRowID = parseInt(configTable.rows[i].id.substr(12))
	  allRows += buildConfigString(0, parseInt(moduleType), nextRowID);
	  allRows += '\n';
  }

  moduleName += ".csv";

  exportToCsv(moduleName, allRows);
}

function exportLabelsFile()
{
  var SystemName = document.getElementById("SystemName").innerText;

  var moduleName = SystemName + "_labels";

  var nextRow = "";
  var allRows = "Labels";
  allRows += '\n';

  var configTableName = "LabelTable";
  var configTable = document.getElementById(configTableName);
  var numRows = configTable.rows.length;

  for (var i=0; i<numRows; i++)
  {
	  allRows += buildLabelString(i, true);
	  allRows += '\n';
  }

  moduleName += ".csv";

  exportToCsv(moduleName, allRows);
}

function closeTimePopup()
{
  document.getElementById("editTimePopup").style.display = "none";
}

function saveTimePopup()
{
  var e = document.getElementById("EventHoursTens");
  var newValue = e.options[e.selectedIndex].value;
  e = document.getElementById("EventHoursUnits");
  newValue += e.options[e.selectedIndex].value;
  newValue += ':';
  e = document.getElementById("EventMinutesTens");
  newValue += e.options[e.selectedIndex].value;
  e = document.getElementById("EventMinutesUnits");
  newValue += e.options[e.selectedIndex].value;

  editEventObject.value = newValue.substr(0,2) + newValue.substr(3,2);
  editEventObject.innerHTML = newValue;

  closeTimePopup();
}

function resetTimedButton(label)
{
  document.getElementById('offButton' + label).checked = true;
  var message = document.getElementById('offButton' + label).value;
  PublishMessage(message);
}

function setTimedButton(label)
{
  var message = document.getElementById('onButton' + label).value;
  PublishMessage(message);
  setTimeout(resetTimedButton, 1000, label);
}

function initialisePage()
{
  var btn = document.getElementById("editEventPopup_button");

  var SystemName = document.getElementById("SystemName")
  <?php  $WiFiName = exec("nmcli c show hotspot|grep wireless.ssid|tr -s ' ' ','|cut -f2 -d,"); ?>
  SystemName.innerHTML = "<h1><?php echo $WiFiName?></h1>"
  <?php  $ShutdownEvent = exec("cat /home/sms/ShutdownEvent.cfg") ?>
  document.getElementById("ShutdownEvent").value = "<?php echo $ShutdownEvent ?>";
  Interfaces=<?php echo exec("ifconfig eth0 |grep eth0:|grep RUNNING|wc -l"); ?>;

  var table = document.getElementById("switchtable");
  var boardAddress = 'A';

  for (var i = 0; i<=15; i++)
  {
	var row = table.insertRow(i);

	var cell = row.insertCell();

	cell.className = "switchTableCell";

	var thisrow = i;
	var thisLabel = "00" + thisrow;
	thisLabel=thisLabel.substr(-2);
	thisLabel=boardAddress+thisLabel;

	cell.innerHTML = "<label id='switchId" + thisrow + "' style='margin-right: 20px'>" + thisLabel + "</label>";
	cell.innerHTML += "<input id='offButton" + thisrow + "' onChange='PublishMessage(this.value)') type='radio' name='" + thisrow + "' value='U"+thisLabel+"' checked> Off";
	cell.innerHTML += "<input id='onButton"  + thisrow + "' onchange='PublishMessage(this.value)') type='radio' name='" + thisrow + "' value='S"+thisLabel+"'> On";
	cell.innerHTML += "<input id='toggleButton"  + thisrow + "' onchange='setTimedButton(this.name)') type='radio' name='" + thisrow + "' value='S"+thisLabel+"'> Timed";
  }

  var fromLEDNum = document.getElementById("fromLEDNumber");
  var toLEDNum = document.getElementById("toLEDNumber");

  for (var i = 0; i<50; i++)
  {
	  var option1 = document.createElement("option");
	  var option2 = document.createElement("option");
	  option1.text = i;
	  option1.value = i;
	  option2.text = i;
	  option2.value = i;
  }

  menuclockcanvas = document.getElementById("menuclockcanvas");
  menuclockctx = menuclockcanvas.getContext("2d");
  menuclockradius = menuclockcanvas.height / 2;
  menuclockctx.translate(menuclockradius, menuclockradius);
  menuclockradius = menuclockradius * 0.90

  clockcanvas = document.getElementById("clockcanvas");
  clockctx = clockcanvas.getContext("2d");
  clockradius = clockcanvas.height / 2;
  clockctx.translate(clockradius, clockradius);
  clockradius = clockradius * 0.90;

  displaySubpage(null);

  ampm = false;
  enableClockChange();
}

function PublishMessage(payload)
{
  var payload = SOM + payload + EOM;
  client.publish("/Messages", payload, 2);
}

function purgeMessage(topic)
{
  client.publish(topic, "", 2, true);
}

function PublishDevices(device, payload)
{
  var payload = SOM + payload + EOM;
  var tempString = "/Modules/" + device;
  client.publish(tempString, payload, 0);
}

function sendAdjustedTime()
{
  var hourString = "00" + primaryhour;
  hourString = hourString.substr(-2);
  var minuteString = "00" + primaryminute;
  minuteString = minuteString.substr(-2);
  var secondString = "00" + primarysecond;
  secondString = secondString.substr(-2);

  var TimeMessage = "P" + hourString + minuteString + secondString;

  if (ampm)
    TimeMessage = TimeMessage + "P";
  else
    TimeMessage = TimeMessage + "A";

  PublishMessage(TimeMessage);
}

function drawMenuClock(hour,minute,second,ampm)
{
	if (ClockStyle == "analog")
	{
	  drawFace(menuclockctx, menuclockradius);
	  drawNumbers(menuclockctx, menuclockradius);
	  drawTime(menuclockctx, menuclockradius, hour, minute, second);

	  drawFace(clockctx, clockradius);
	  drawNumbers(clockctx, clockradius);
	  drawTime(clockctx, clockradius, hour, minute, second);
	}
	else
	{
	  if (ampm)
	  {
      var tempHoursString = "00" + hour;
      tempHoursString = tempHoursString.substr(-2);
      var tempMinutesString = "00" + minute;
      tempMinutesString = tempMinutesString.substr(-2);
      if (colonState || !clockPaused)
      {
        document.getElementById("digitalClock").innerHTML=tempHoursString+":"+tempMinutesString;
        document.getElementById("menudigitalClockContent").innerHTML=tempHoursString+":"+tempMinutesString;
      }
      else
      {
        document.getElementById("digitalClock").innerHTML=tempHoursString+"&nbsp"+tempMinutesString;
        document.getElementById("menudigitalClockContent").innerHTML=tempHoursString+"&nbsp"+tempMinutesString;
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
      if (colonState || !clockPaused)
      {
        document.getElementById("digitalClock").innerHTML=tempHoursString+":"+tempMinutesString+"&nbsp"+ampm;
        document.getElementById("menudigitalClockContent").innerHTML=tempHoursString+":"+tempMinutesString+"&nbsp"+ampm;
      }
      else
      {
        document.getElementById("digitalClock").innerHTML=tempHoursString+"&nbsp"+tempMinutesString+"&nbsp"+ampm;
        document.getElementById("menudigitalClockContent").innerHTML=tempHoursString+"&nbsp"+tempMinutesString+"&nbsp"+ampm;
      }
	  }
	}
}

function drawFace(ctx, radius)
{
  var grad;
  ctx.beginPath();
  ctx.arc(0, 0, radius, 0, 2*Math.PI);
  ctx.fillStyle = 'ivory';
  ctx.fill();
  grad = ctx.createRadialGradient(0,0,radius*0.95, 0,0,radius*1.05);
  grad.addColorStop(0, '#333');
  grad.addColorStop(0.5, 'white');
  grad.addColorStop(1, '#333');
  ctx.strokeStyle = grad;
  ctx.lineWidth = radius*0.1;
  ctx.stroke();
  ctx.beginPath();
  ctx.arc(0, 0, radius*0.1, 0, 2*Math.PI);
  ctx.fillStyle = '#555555';
  ctx.fill();
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
  hourhand=hour%12;
  hourhand=(hour*Math.PI/6)+
  (minute*Math.PI/(6*60))+
  (second*Math.PI/(360*60));
  drawHand(ctx, hourhand, radius*0.5, radius*0.07);
  minutehand=(minute*Math.PI/30)+(second*Math.PI/(30*60));
  drawHand(ctx, minutehand, radius*0.8, radius*0.07);
}

function drawHand(ctx, pos, length, width)
{
  ctx.beginPath();
  ctx.lineWidth = width;
  ctx.lineCap = "round";
  ctx.moveTo(0,0);
  ctx.rotate(pos);
  ctx.lineTo(0, -length);
  ctx.stroke();
  ctx.rotate(-pos);
}

function HourStyleSelected()
{
  if (document.getElementById("24h").checked)
    HourClockStyle = 24;
  else
    HourClockStyle = 12;
  drawMenuClock(intmessagehour, intmessageminute, intmessagesecond,document.getElementById("24h").checked);
}

function analogClockSelected()
{
	if (document.getElementById("analog").checked)
  {
		document.getElementById("clockcanvas").style.display = "block";
		document.getElementById("menuclockcanvas").style.display = "block";
		document.getElementById("digitalClockContainer").style.display = "none";
		document.getElementById("menudigitalClock").style.display = "none";
		ClockStyle = "analog";
  }
  else
  {
		document.getElementById("clockcanvas").style.display = "none";
		document.getElementById("menuclockcanvas").style.display = "none";
		document.getElementById("digitalClockContainer").style.display = "block";
		document.getElementById("menudigitalClock").style.display = "block";
		ClockStyle = "digital";
  }
  drawMenuClock(primaryhour, primaryminute, primarysecond, ampm);
}

function runPause(internal)
{
  var buttonObject = document.getElementById("RunPauseButton");
  if (clockPaused)
  {
    buttonObject.value = "RUN";
    buttonObject.style.background = "green";
    clockPaused = false;
  	document.getElementById("Hour+Button").style.visibility = "visible";
	  document.getElementById("Minute+Button").style.visibility = "visible";
	  document.getElementById("Hour-Button").style.visibility = "visible";
	  document.getElementById("Minute-Button").style.visibility = "visible";
    if (internal)
  	  sendClockPauseMessage();
  }
  else
  {
    buttonObject.value = "PAUSE";
    buttonObject.style.background = "red";
    clockPaused = true;
	  document.getElementById("Hour+Button").style.visibility = "hidden";
	  document.getElementById("Minute+Button").style.visibility = "hidden";
	  document.getElementById("Hour-Button").style.visibility = "hidden";
	  document.getElementById("Minute-Button").style.visibility = "hidden";
	  if (internal)
	    sendClockRunMessage();
  }
}

function clockAdjust(buttonObject)
{
  switch (buttonObject.value)
  {
    case "Hour +":
      sendClockAdjustMessage("H+");
      break;

    case "Hour -":
      sendClockAdjustMessage("H-");
      break;

    case "Minute +":
      sendClockAdjustMessage("M+");
      break;

    case "Minute -":
      sendClockAdjustMessage("M-");
      break;
  }
}

function enableClockChange()
{
  document.getElementById("analogl").style.visibility = "visible";
  if (ClockStyle == "analog")
  {
    document.getElementById("clockcanvas").style.display = "block";
    document.getElementById("menuclockcanvas").style.display = "block";
    document.getElementById("digitalClockContainer").style.display = "none";
    document.getElementById("menudigitalClock").style.display = "none";
  }
  else
  {
    document.getElementById("clockcanvas").style.display = "none";
    document.getElementById("menuclockcanvas").style.display = "none";
    document.getElementById("digitalClockContainer").style.display = "block";
    document.getElementById("menudigitalClock").style.display = "block";
  }
  drawMenuClock(intmessagehour, intmessageminute, intmessagesecond, ampm);
}

function ClockRatio(Button)
{
  timeScale = parseInt(Button.value);
}

function displaySubpage(selectedObject)
{
  var x = document.getElementsByClassName("subPage");
  var i;
  for (i = 0; i < x.length; i++)
  {
    x[i].style.display = "none";
  }

  if (selectedObject != null)
  {
    var pageType = selectedObject.selectedOptions[0].value;
    if (pageType != "-1")
      document.getElementById(pageType).style.display = "block";
  }
}
<?php
/*
function programInputModule()
{
	var selectedObject=document.getElementById("Sensors");
	var moduleName = selectedObject.selectedOptions[0].text;
  if (confirm("Select OK to send the configuration to "+moduleName+"?"))
  {
	  var switchConfigurations = ""
	  var numberOfRows = document.getEleentById("").
	  for (var i = 0; i < numberOfRows; i++)
	  {
	    switchConfigurations += document.getElementById("editInputPopup_button"+i).value.substr(0,1);
	    switchConfigurations += ",";
	    switchConfigurations += document.getElementById("editInputPopup_button"+i).value.substr(1,3);
	    switchConfigurations += ",";
		  var switchTarget = 'input[name="manualinputtype' + i + '"]:checked';
      switchConfigurations += document.querySelector(switchTarget).value;
      switchConfigurations += ",";
      if (document.querySelector(switchTarget).value == 3)
      {
        var x = "manualinputtype"+ i + "XT"
        var timeoutValue = document.getElementById(x);
        switchConfigurations += timeoutValue.value;
      }
      else
        switchConfigurations += "0";

      if (document.querySelector(switchTarget).value == 2)
      {
        switchConfigurations += "\n";
      	switchConfigurations += document.getElementById("editInputPopup_button"+i).value.substr(0,1);
        switchConfigurations += ",";
        switchConfigurations += document.getElementById("editInputPopup_button"+i).value.substr(1,3);
        switchConfigurations += ",2,0\n";
        i++;
      }
      else
        if (i < (numberOfRows - 1))
          switchConfigurations += "\n";
    }

    var payload = "WS" + switchConfigurations;
    PublishDevices(moduleName, payload);
	}
}
*/
?>
function programDCCModule()
{
	var selectedObject=document.getElementById("Sensors");
	var moduleName = selectedObject.selectedOptions[0].text;
  if (confirm("Select OK to send the configuration to "+moduleName+"?"))
  {
  	  var DCCreversed = document.getElementById("DCCReversed").checked;
  	  var OffsetBy4 = document.getElementById("OffsetBy4").checked;

	  var payload = "WC";

	  if (DCCreversed)
	  {
	    payload = payload + "R";
	  }
	  else
	  {
	    payload = payload + "N";
	  }

	  if (OffsetBy4)
	  {
	    payload = payload + "Y";
	  }
	  else
	  {
	    payload = payload + "N";
    }

	  for (var i=0; i<20; i++)
	  {
  	  var DCCBank=document.getElementById("DCCBank" + i);
	    var DCCBoardBank = DCCBank.selectedOptions[0].value;
  	  payload = payload + DCCBoardBank;
	  }

	  PublishDevices(moduleName, payload);
	}
}

function programClockModule(id)
{
	var selectedObject=document.getElementById("Sensors");
	var moduleName = selectedObject.selectedOptions[0].text;
  if (confirm("Select OK to send the configuration to "+moduleName+"?"))
  {
    var Brightness=document.getElementById(id).value;
    var payload = "WC" + Brightness;
    PublishDevices(moduleName, payload);
	}
}

function programMatrixClockModule()
{
	var selectedObject=document.getElementById("Sensors");
	var moduleName = selectedObject.selectedOptions[0].text;
  if (confirm("Select OK to send the configuration to "+moduleName+"?"))
  {
    var Brightness=document.getElementById("matrixBrightness").value;
    var Font = document.getElementById("matrixFont").value
	  var Scroll=document.getElementById("matrixScroll").checked;
	  var AMPMstyle=document.getElementById("matrixAMPMstyle").value;
	  var payload = "WC" + Brightness + "," + Font + ",";
	  if (Scroll)
		  payload += "1";
	  else
		  payload += "0";

	  payload += "," + AMPMstyle
	  PublishDevices(moduleName, payload);
  }
}

function programSoundVolume(moduleType)
{
  if (moduleType == "09")
  {
	  var selectedObject=document.getElementById("Sensors");
  	var moduleName = selectedObject.selectedOptions[0].text;
    var Volume0=document.getElementById("Volume0").value;
    var Volume1=document.getElementById("Volume1").value;
    var payload = "WS" + Volume0 + "," + Volume1;
    PublishDevices(moduleName, payload);
  }
  else
  {
	  var selectedObject=document.getElementById("Sensors");
  	var moduleName = selectedObject.selectedOptions[0].text;
    var Volume0=document.getElementById("Volume0_16").value;
    var Volume1=document.getElementById("Volume1_16").value;
    var Volume2=document.getElementById("Volume2_16").value;
    var Volume3=document.getElementById("Volume3_16").value;
    var Volume4=document.getElementById("Volume4_16").value;
    var Volume5=document.getElementById("Volume5_16").value;
    var payload = "WS" + Volume0 + "," + Volume1 + "," + Volume2 + "," + Volume3 + "," + Volume4 + "," + Volume5;
    PublishDevices(moduleName, payload);
  }
}

function programPrimaryClockModule()
{
  var selectedObject=document.getElementById("Sensors");
  var moduleName = selectedObject.selectedOptions[0].text;
  if (confirm("Select OK to send the configuration to "+moduleName+"?"))
  {
    var Ratio=document.getElementById("primaryClockRatio").value;
    var HourStyle=document.getElementById("primaryClock1224").value;
    var defaultHours=document.getElementById("primaryClockStartTime").value.substr(0,2);
    var defaultMinutes=document.getElementById("primaryClockStartTime").value.substr(2,2);
    var PauseEvent=document.getElementById("primaryClockPauseEvent").value;
    var StartEvent=document.getElementById("primaryClockStartEvent").value;
    var ResetEvent=document.getElementById("primaryClockResetEvent").value;
    var HPEvent=document.getElementById("primaryClockH+Event").value;
    var HMEvent=document.getElementById("primaryClockH-Event").value;
    var MPEvent=document.getElementById("primaryClockM+Event").value;
    var MMEvent=document.getElementById("primaryClockM-Event").value;
    var payload = "WC,";
    payload = payload + HourStyle + ",";
    payload = payload + Ratio + ",";
    payload = payload + defaultHours + ",";
    payload = payload + defaultMinutes + ",";
    payload = payload + PauseEvent + ",";
    payload = payload + StartEvent + ",";
    payload = payload + ResetEvent + ",";
    payload = payload + HPEvent + ",";
    payload = payload + HMEvent + ",";
    payload = payload + MPEvent + ",";
    payload = payload + MMEvent;
    PublishDevices(moduleName, payload);
  }
}

function centreAllServos()
{
  var moduleName = document.getElementById("Sensors").selectedOptions[0].text;
  if (confirm("Centre all servos on "+moduleName+"?"))
  {
    var moduleName = document.getElementById("Sensors").selectedOptions[0].text;

    for (var i=0; i< 16; i++)
    {
      var payload = "CTSA00,"+i+",0,,S90,1,,0,0,0,0,0,0,1";

      PublishDevices(moduleName, payload);
    }
  }
  return false;
}

function sendConfigFile(idOfFile)
{
  var y = document.getElementById("Sensors");
	moduleName = y.selectedOptions[0].text;

    if (confirm("Select OK to send the configuration to "+moduleName)+"?")
    {
      var tempString = "/Modules/" + moduleName;
      var targetString = SOM + "WC" + EOM;
      client.publish(tempString, targetString, 2);

  	  var x = document.getElementById(idOfFile).files[0];
	    var y = document.getElementById("Sensors");
	    var reader = new FileReader();

  	  reader.onload = (function(theFile) {
	  	return function(e) {
		  var configData = e.target.result;
		  var start = 0;
		  var end = 80;
		  var tempString = "/ConfigData/" + moduleName;

		  fetch('parseConfigFile.php', {
				 method: 'post',
				 headers: {
						 "Content-type": "application/x-www-form-urlencoded; charset=UTF-8"
				 },
				 body: 'ConfigData='+configData
			   })
		  .then(function(response) {
			return response.text();
		  })
		  .then(function(parsedData) {
			  console.log('Request successful', parsedData);
			  if (parsedData == "NO DATA")
			  	alert ("Error processing the file - please check the syntax");
			  else
			  {
				  var tempString = "/Modules/" + moduleName;
				  var targetString = SOM + "W+";
				  client.publish(tempString, targetString, 2);
				  while (start < parsedData.length)
				  {
            var nextChunk = parsedData.substring(start, end);
            start += 80;
            end = start+80;
            var tempString = "/Modules/" + moduleName;
            console.log(nextChunk);
            client.publish(tempString, nextChunk, 2);
            tempString = "/Modules/" + moduleName;
            client.publish(tempString, EOM, 2);
				  }
			  }
			})

		  .catch(function(error) {
		  	alert ("Error processing the file - please check the syntax");
		  });
		};
	  })(x);

	  reader.readAsText(x);
	}
}

function uploadConfigFile(moduleType)
{
  var nextRow = "";
  var y = document.getElementById("Sensors");
  moduleName = y.selectedOptions[0].text;

  var configTableName = "ConfigTable" + moduleType;
  var configTable = document.getElementById(configTableName);
  var numRows = configTable.rows.length;

  if (confirm("Select OK to send the configuration to "+moduleName+"?"))
  {
	  var tempString = "/Modules/" + moduleName;

    if (numRows > 1)
    {
      for (var i=1; i<numRows; i++)
      {
        var nextRowID = parseInt(configTable.rows[i].id.substr(12))
        if (i == 1)
          nextRow = SOM + "WC";
        else
          nextRow = SOM + "W+";
        nextRow += buildConfigString(0, parseInt(moduleType), nextRowID);
        nextRow += EOM;
        console.log(nextRow);
        var err = client.publish(tempString, nextRow, 0);
        console.log(err);
      }
      nextRow = SOM + "WE";
      nextRow += EOM;
      var err = client.publish(tempString, nextRow, 0);
      console.log(err);
    }
    else
    {
      nextRow = SOM + "WC";
      nextRow += EOM;
      console.log(nextRow);
      var err = client.publish(tempString, nextRow, 0);
      console.log(err);
      nextRow = SOM + "WE";
      nextRow += EOM;
      var err = client.publish(tempString, nextRow, 0);
      console.log(err);
    }
    return true;
  }
  else
    return false;
}

function uploadInvertedLighting()
{
var lightElementId = "";
var nextRow = "";

  var y = document.getElementById("Sensors");
  moduleName = y.selectedOptions[0].text;
  var tempString = "/Modules/" + moduleName;

  nextRow = SOM + "WS";
  for (var i=0; i<16; i++)
  {
    lightElementId = document.getElementById("Inverted"+i+"_12");
    if (lightElementId.checked)
      nextRow += "1,";
    else
      nextRow += "0,";
  }
  nextRow += EOM;
  console.log(nextRow);
  var err = client.publish(tempString, nextRow, 0);
  console.log(err);
}

function retrieveConfigFile(moduleType)
{
  var y = document.getElementById("Sensors");
  var device = y.selectedOptions[0].text;
  if (confirm("Selecting OK will overwrite and details below"))
  {
//    if (moduleType == '03')
//      document.getElementById("inputButtons").style.display = "none";

    if (moduleType == '08')
      document.getElementById("matrixConfigPage").style.display = "none";

    if (moduleType == '01')
      document.getElementById("primaryClockConfigPage").style.display = "none";

    if (moduleType == '02')
      document.getElementById("secondaryClockConfigPage").style.display = "none";

    var payload = "WD";

    PublishDevices(device, payload);
  }
}

function readConfigFile(e)
{
  var file = e.target.files[0];
  if (!file) {
    return;
  }
  var reader = new FileReader();
  reader.onload = function(e) {
    var contents = e.target.result;
  };
  reader.readAsText(file);
}

function processFileContents(moduleType, t)
{
  var s = t.replace('\r', '');
  var fileStrings = s.split("\n");
  var moduleInFile = fileStrings[0].substr(1,2);
  if (moduleType != moduleInFile)
  {
    alert ("Incorrect file type");
  }
  else
  {
    clearConfigLines(moduleType);
    var rowsReceived = fileStrings.length;

    if (rowsReceived == 0)
    {
      var cell;
      var configTableName = "ConfigTable" + moduleType;
      var configTable = document.getElementById(configTableName);
      var currentRow = configTable.rows.length - 1;
      var row = configTable.insertRow(-1);
      row.id = "configRow" + moduleType + "_" +currentRow;
      moduleType = parseInt(moduleType);
      var defaultString="SA00,0,0,,90,1,,0,0,0,0,0,0,1"
      populateRow(currentRow, moduleType, defaultString, false)
    }
    else
    {
      for (var i=1; i<rowsReceived; i++)
      {
        if (fileStrings[i] != "")
        {
          var cell;
          var configTableName = "ConfigTable" + moduleType;
          var configTable = document.getElementById(configTableName);
          var currentRow = configTable.rows.length;
          var row = configTable.insertRow(-1);
          row.id = "configRow" + moduleType + "_" +currentRow;

          populateRow(currentRow, parseInt(moduleType), fileStrings[i], false, "");
        }
      }
    }
    manipulateRows(1, moduleType, "Renumber");

    if (moduleType == 3)
    {
      // and procees each row/fields so they show the right things :)
      var numRows = document.getElementById("ConfigTable03").rows.length;
      for (i=1; i<numRows;)
        i += updateInputRow(i);
    }
  }
}

function processLabelFileContents(t)
{
  var fileStrings = t.split("\n");
  if ("Labels" != fileStrings[0])
  {
    alert ("Incorrect file type");
  }
  else
  {
    fileStrings.splice(0,1);
    var rowsReceived = fileStrings.length;

    if (rowsReceived > 0)
    {
      deleteLabelList();
      fileStrings.forEach (addLabelToList);
    }
  }
}

function importConfigFile(moduleType)
{

  var y = document.getElementById("Sensors");
  var device = y.selectedOptions[0].text;
  var e = document.getElementById('fileImportInput');

  var file = e.files[0];
  if (file) 
  {
    if (confirm("Selecting OK will overwrite and details below"))
    {
        var reader = new FileReader();
        reader.onload = function(e) {
          var contents = e.target.result;
          processFileContents(moduleType, contents);
        };
        reader.readAsText(file);
    }
  }
}

function processLabelFileContents(t)
{
  var fileStrings = t.split("\n");
  if ("Labels" != fileStrings[0])
  {
    alert ("Incorrect file type");
  }
  else
  {
    fileStrings.splice(0,1);
    var rowsReceived = fileStrings.length;

    if (rowsReceived > 0)
    {
      deleteLabelList();
      fileStrings.forEach (addLabelToList);
    }
  }
}

function importLabelsFile(moduleType)
{
  if (confirm("Selecting OK will overwrite and details below"))
  {
    var e = document.getElementById('fileImportInput');

      var file = e.files[0];
      if (!file) {
        return;
      }
      var reader = new FileReader();
      reader.onload = function(e) {
        var contents = e.target.result;
        processLabelFileContents(contents);
      };
      reader.readAsText(file);
  }
}

function ConfigRelayChange(boardType, row)
{
  var selectedVal = document.getElementById("RelayAction"+row).value;
  if (selectedVal == 0 || selectedVal == 1 || selectedVal == 'T')
  {
    document.getElementById("default_button_"+boardType+"_"+row+"-3").style.visibility = "hidden";
  }
  else
  {
    document.getElementById("default_button_"+boardType+"_"+row+"-3").style.visibility = "visible";
  }
}

function ConfigSoundChange(boardType, row)
{
  var selectedVal = document.getElementById("soundQueueType_"+boardType+"_"+row).value;

  if (selectedVal == "=")
  {
    for (var i=3; i<11; i++)
      document.getElementById("default_button_"+boardType+"_"+row+"-"+i).style.display = "none";
    document.getElementById("SoundChannel_"+boardType+"_"+row).style.display = "none";
    document.getElementById("sound_label_"+boardType+"_"+row).style.display = "";
    document.getElementById("Variable_"+boardType+"_"+row).style.display = "";
    document.getElementById("VariableValue_"+boardType+"_"+row).style.display = "";
  }
  else
  {
    for (var i=3; i<11; i++)
      document.getElementById("default_button_"+boardType+"_"+row+"-"+i).style.display = "";
    document.getElementById("SoundChannel_"+boardType+"_"+row).style.display = "";
    document.getElementById("sound_label_"+boardType+"_"+row).style.display = "none";
    document.getElementById("Variable_"+boardType+"_"+row).style.display = "none";
    document.getElementById("VariableValue_"+boardType+"_"+row).style.display = "none";
  }
}

function MimicColour1Change()
{
  if(document.getElementById("UseExistingColour").checked)
  {
  	document.getElementById("Red1").disabled = true;
  	document.getElementById("Green1").disabled = true;
  	document.getElementById("Blue1").disabled = true;
  	document.getElementById("Red1Label").style.color = "lightgray";
  	document.getElementById("Green1Label").style.color = "lightgray";
  	document.getElementById("Blue1Label").style.color = "lightgray";
  	document.getElementById("Red1Value").disabled = true;
  	document.getElementById("Green1Value").disabled = true;
  	document.getElementById("Blue1Value").disabled = true;
  }
  else
  {
  	document.getElementById("Red1").disabled = false;
  	document.getElementById("Green1").disabled = false;
  	document.getElementById("Blue1").disabled = false;
  	document.getElementById("Red1Label").style.color = "black";
  	document.getElementById("Green1Label").style.color = "black";
  	document.getElementById("Blue1Label").style.color = "black";
  	document.getElementById("Red1Value").disabled = false;
  	document.getElementById("Green1Value").disabled = false;
  	document.getElementById("Blue1Value").disabled = false;
  }
}

function changeEventPopupType(x)
{
  var blankSelected = document.getElementById('_BLANK').checked;
  var ele = document.getElementsByName('eventType');
  var checkedVal;

  if (blankSelected)
  {
	  checkedVal = x;
  }

  for(i = 0; i < ele.length; i++)
  {
    if(ele[i].checked)
	    checkedVal = ele[i].value;
  }

  var srow = document.getElementById("eventRowEvent");

  var trow = document.getElementById("eventRowTime");

  var crow = document.getElementById("eventRowClock");

  var lrow = document.getElementById("eventRowLabel");

  var rrowh = document.getElementById("eventRowRangeHours");
  var rrowm = document.getElementById("eventRowRangeMinutes");

  var IDrowHeader = document.getElementById("eventRowIDHeader");
  var IDrow = document.getElementById("eventRowID");

  srow.style.display = 'none';
  trow.style.display = 'none';
  crow.style.display = 'none';
  lrow.style.display = 'none';
  rrowh.style.display = 'none';
  rrowm.style.display = 'none';
  IDrowHeader.style.display = 'none';
  IDrow.style.display = 'none';

  var blankButton = document.getElementById('_BLANK');
  var blankText = document.getElementById('_BLANK_SPAN');

  switch (checkedVal)
  {
    case 'C':
      trow.style.display = '';
      eventTimeHoursChange('C');
      blankButton.style.visibility = "hidden";
      blankText.style.visibility = "hidden";
      break;

    case 'R':
      rrowh.style.display = '';
      rrowm.style.display = '';
      blankButton.style.visibility = "hidden";
      blankText.style.visibility = "hidden";
      break;

    case "S":
      blankButton.style.visibility = "visible";
      blankText.style.visibility = "visible";
      if (!blankSelected)
      {
        srow.style.display = '';
      }
      break;

    case "I":
      blankButton.style.visibility = "hidden";
      blankText.style.visibility = "hidden";
      break;

    case "D":
      blankButton.style.visibility = "hidden";
      blankText.style.visibility = "hidden";

      IDrowHeader.style.display = '';
      IDrow.style.display = '';
      break;

    case "L":
      blankButton.style.visibility = "hidden";
      blankText.style.visibility = "hidden";

      lrow.style.display = '';
      break;

    case "O":
      crow.style.display = '';
 	    eventClockHoursChange('O');
      break;
  }
}

function eventTimeHoursChange(x)
{
  var newVal = x.value;
  var HourUnits = document.getElementById('EventHourUnits');
  if (newVal == "2")
  {
    for (var i=0; i<6; i++)
      HourUnits.options[4].remove();
  }
  else
  {
    if (HourUnits.length < 12)
    {
      var option = document.createElement("option");
      option.text = "4";
      option.value = "4";
      HourUnits.add(option, HourUnits[4]);
      option = document.createElement("option");
      option.text = "5";
      option.value = "5";
      HourUnits.add(option, HourUnits[5]);
      option = document.createElement("option");
      option.text = "6";
      option.value = "6";
      HourUnits.add(option, HourUnits[6]);
      option = document.createElement("option");
      option.text = "7";
      option.value = "7";
      HourUnits.add(option, HourUnits[7]);
      option = document.createElement("option");
      option.text = "8";
      option.value = "8";
      HourUnits.add(option, HourUnits[8]);
      option = document.createElement("option");
      option.text = "9";
      option.value = "9";
      HourUnits.add(option, HourUnits[9]);
    }
  }
}

function eventClockHoursChange(x)
{
  var newVal = x.value;
  var HourUnits = document.getElementById('ClockHourUnits');
  if (newVal == "2")
  {
    for (var i=0; i<6; i++)
      HourUnits.options[4].remove();
  }
  else
  {
    if (HourUnits.length < 10)
    {
      var option = document.createElement("option");
      option.text = "4";
      option.value = "4";
      HourUnits.add(option, HourUnits[4]);
      option = document.createElement("option");
      option.text = "5";
      option.value = "5";
      HourUnits.add(option, HourUnits[5]);
      option = document.createElement("option");
      option.text = "6";
      option.value = "6";
      HourUnits.add(option, HourUnits[6]);
      option = document.createElement("option");
      option.text = "7";
      option.value = "7";
      HourUnits.add(option, HourUnits[7]);
      option = document.createElement("option");
      option.text = "8";
      option.value = "8";
      HourUnits.add(option, HourUnits[8]);
      option = document.createElement("option");
      option.text = "9";
      option.value = "9";
      HourUnits.add(option, HourUnits[9]);
    }
  }
}

function testMessageSend(moduleType, num)
{
  var moduleName = document.getElementById("Sensors").selectedOptions[0].text;

  var payload = "CT";
  payload += buildConfigString(0, moduleType, num);

  PublishDevices(moduleName, payload);
}

function sendClockPauseMessage()
{
  var moduleName = "Internal Clock";

  var payload = "PAUSE";
  PublishDevices(moduleName, payload);
}

function sendClockAdjustMessage(payload)
{
  var moduleName = "Internal Clock";

  PublishDevices(moduleName, payload);
}

function sendClockRunMessage()
{
  var moduleName = "Internal Clock";

  var payload = "RUN";
  PublishDevices(moduleName, payload);
}

function backlashTestMessageSend(num)
{
  var moduleName = document.getElementById("Sensors").selectedOptions[0].text;
  var StepperNum = document.getElementById("StepperSetup").selectedOptions[0].text;

  if (num == 0)
    var backlashCount = 0;
  else
    var backlashCount = document.getElementById("stepperBacklash").value;

  var payload = "CB" + StepperNum + backlashCount;
  PublishDevices(moduleName, payload);
}

function backlashSaveMessageSend(moduleType, num)
{
  var moduleName = document.getElementById("Sensors").selectedOptions[0].text;
  var NumberOfMotors = document.getElementById("NumberOfSteppers").selectedOptions[0].text;
  if (NumberOfMotors == 1)
    StepperNum = 0;
  else
    var StepperNum = document.getElementById("StepperSetup").selectedOptions[0].text;
  var fullCircleCount = document.getElementById("stepperCircleCount").value;
  var backlashCount = document.getElementById("stepperBacklash").value;
  var ActAsClock = document.getElementById("StepperActAsClock").checked;
  if (ActAsClock)
    ActAsClock = "C";
  else
    ActAsClock = "N";

  var payload = "CC" + NumberOfMotors + ',' + StepperNum + ',' + fullCircleCount + ',' + ActAsClock + ',' + backlashCount;
  PublishDevices(moduleName, payload);
}

function zeroStepperMessageSend(x)
{
  var moduleName = document.getElementById("Sensors").selectedOptions[0].text;
  var StepperNum = document.getElementById("StepperSetup").selectedOptions[0].text;

  var payload = "CZ"+StepperNum;
  PublishDevices(moduleName, payload);
}

function fullCircleStepperMessageSend(x)
{
  var moduleName = document.getElementById("Sensors").selectedOptions[0].text;
  var StepperNum = document.getElementById("StepperSetup").selectedOptions[0].text;

  var payload = "CF"+StepperNum;
  PublishDevices(moduleName, payload);
}

function sendInteractiveServoMessage(position, row)
{
  var moduleName = document.getElementById("Sensors").selectedOptions[0].text;
  var servoNumber = document.getElementById("ServoNumber"+row).selectedOptions[0].text;

  var payload = "CTSA00,"+servoNumber+",0,,S"+position+",1,,0,0,0,0,0,0,1";

  PublishDevices(moduleName, payload);
}

function sendSearchRequest(x)
{
  var payload = "CQ";
  payload += document.getElementById("editEventPopup_button_usage").value;
  PublishMessage(payload);

  var usageDiv = document.getElementById("usageTableDiv");

  while (usageDiv.firstChild)
  {
    usageDiv.removeChild(usageDiv.firstChild);
  }
}

function mapVal(lowermin, lowermax, uppermin, uppermax, val)
{
	var originalrange = (lowermax - lowermin);
	var newrange = (uppermax - uppermin);
	var offset = (val - lowermin) / originalrange;
	offset = (offset * newrange) + uppermin;

	return offset;
}

function MimicUpdateSample1()
{
	var red = parseInt(mapVal(0, 128, 0, 255, parseInt(document.getElementById("Red1").value)));
	var green = parseInt(mapVal(0, 128, 0, 255, parseInt(document.getElementById("Green1").value)));
	var blue = parseInt(mapVal(0, 128, 0, 255, parseInt(document.getElementById("Blue1").value)));

	var newColour = "rgb("+red+","+green+","+blue+")";
	document.getElementById("sampleColour1").style.background = newColour;
}

function noSpaces(input, lengthCheck)
{
  var len;

  len = input.value.length;
	input.value = input.value.replace(/\s/gi,"");
  if (lengthCheck != 0)
    if (input.value.length >= 8)
    {
      document.getElementById("WiFiChangeSubmitButton").disabled = false;
      document.getElementById("WiFiChangeSubmitButton").style.color = "black";
    }
    else
    {
      document.getElementById("WiFiChangeSubmitButton").disabled = true;
      document.getElementById("WiFiChangeSubmitButton").style.color = "gray";
    }
  if (input.value.length == len)
    return true;
  else
  	return false;
}

function confirmWiFiChanges(formdetails)
{
  var confirmString;

    confirmString = "Are you sure you with to change the details to:\n\nLayout Name: ";
    confirmString += formdetails[0].value;
    confirmString += "\nPassword      : ";
    confirmString += formdetails[1].value;
    confirmString += "\n\nThis will cause your base station to restart with these new details\n\nSelect 'OK' to continue";
    return confirm(confirmString);
}

function confirmShutdown(formdetails)
{
  var confirmString;

    confirmString = "Are you sure you wish to shutdown the system now?";
    return confirm(confirmString);
}

function confirmShutdownTrigger(formdetails)
{
  var confirmString;

    confirmString = "This will cause the base station to shutdown whenever it receives this event. Are you sure?";
    return confirm(confirmString);
}

function confirmReboot(formdetails)
{
  var confirmString;

    confirmString = "Are you sure you wish to restart the system now?";
    return confirm(confirmString);
}

function checkTimeoutValue(obj)
{
  if (obj.value > 60)
    obj.value = 60;

  if (obj.value < 1)
    obj.value = 1;
}

function inputSwitchType(obj, thisObject)
{
  var currentlyDisabled = obj.disabled;
  if (thisObject < 15)
  {
    if (obj.value == 2)
    {
      var nextObject="manualinputtype" + (thisObject + 1);
    	var x = document.getElementsByName(nextObject);
    	for (var i=0; i<x.length - 1; i++)
    	{
    		x[i].disabled = true;
    		x[i].nextElementSibling.style.color = "grey";
    	}

      nextObject="timeoutlabel"+thisObject;
      x = document.getElementById(nextObject);
      x.style.visibility = "hidden";

      nextObject="timeoutlabelCell"+thisObject;
      x = document.getElementById(nextObject);
      x.style.background = "transparent";

      nextObject="timeoutlabel"+ (thisObject + 1);
      x = document.getElementById(nextObject);
      x.style.visibility = "hidden";

      nextObject="editInputPopup_button"+ (thisObject + 1);
      x = document.getElementById(nextObject);
      x.style.visibility = "hidden";

      nextObject="timeoutlabelCell"+ (thisObject + 1);
      x = document.getElementById(nextObject);
      x.style.background = "transparent";
    }
    else
      if (obj.value == 3)
      {
        var nextObject="manualinputtype" + (thisObject + 1);
        var x = document.getElementById(nextObject);
        nextValue = $("input[name="+nextObject+"]:checked").val();

        var nextObject="manualinputtype" + (thisObject + 1);
        var x = document.getElementsByName(nextObject);

      	for (var i=0; i<x.length - 1; i++)
      	{
      		x[i].disabled = false;
      		x[i].nextElementSibling.style.color = "black";
      	}

        if (!currentlyDisabled)
        {
          nextObject="timeoutlabel"+ (thisObject);
          x = document.getElementById(nextObject);
          x.style.visibility = "visible";

          nextObject="timeoutlabelCell"+thisObject;
          x = document.getElementById(nextObject);
          x.style.background = "white";
        }

        if (nextValue == 3)
        {
          nextObject="editInputPopup_button"+ (thisObject + 1);
          x = document.getElementById(nextObject);
          x.style.visibility = "visible";

          nextObject="timeoutlabel"+ (thisObject + 1);
          x = document.getElementById(nextObject);
          x.style.visibility = "visible";

          nextObject="timeoutlabelCell"+ (thisObject + 1);
          x = document.getElementById(nextObject);
          x.style.background = "white";
        }
      }
      else
      {
        var nextObject="manualinputtype" + (thisObject + 1);
        var x = document.getElementById(nextObject);
        nextValue = $("input[name="+nextObject+"]:checked").val();

        var nextObject="manualinputtype" + (thisObject + 1);
        var x = document.getElementsByName(nextObject);

      	for (var i=0; i<x.length - 1; i++)
      	{
      		x[i].disabled = false;
      		x[i].nextElementSibling.style.color = "black";
      	}
        nextObject="timeoutlabel"+ (thisObject);
        x = document.getElementById(nextObject);
        x.style.visibility = "hidden";

        nextObject="timeoutlabelCell"+thisObject;
        x = document.getElementById(nextObject);
        x.style.background = "transparent";

        nextObject="editInputPopup_button"+ (thisObject + 1);
        x = document.getElementById(nextObject);
        x.style.visibility = "visible";

        if (nextValue == 3 && !currentlyDisabled)
        {
          nextObject="timeoutlabel"+ (thisObject + 1);
          x = document.getElementById(nextObject);
          x.style.visibility = "visible";

          nextObject="timeoutlabelCell"+ (thisObject + 1);
          x = document.getElementById(nextObject);
          x.style.background = "white";
        }
  	}
  }
  else
  {
    if (obj.value == 3 && !currentlyDisabled)
    {
      nextObject="timeoutlabel"+ (thisObject);
      x = document.getElementById(nextObject);
      x.style.visibility = "visible";

      nextObject="timeoutlabelCell"+thisObject;
      x = document.getElementById(nextObject);
      x.style.background = "white";
    }
    else
    {
      nextObject="timeoutlabel"+ (thisObject);
      x = document.getElementById(nextObject);
      x.style.visibility = "hidden";

      nextObject="timeoutlabelCell"+thisObject;
      x = document.getElementById(nextObject);
      x.style.background = "transparent";
    }
  }
}

function changeSoundPopupType(x)
{
  var type = x.value;

  document.getElementById('soundPopupIntro').style.display = '';

  switch (type)
  {
    case 'I':
      document.getElementById('Sound_value').style.display = '';
      document.getElementById('Sound_variable').style.display = 'none';
      document.getElementById('Sound_time').style.display = 'none';
      document.getElementById('Sound_Repeat_type').style.visibility="visible";
      document.getElementById('Sound_Repeat_value').style.visibility="visible";
      document.getElementById('Sound_Repeat_variable').style.visibility="visible";
      break;

    case 'V':
      document.getElementById('Sound_value').style.display = 'none';
      document.getElementById('Sound_variable').style.display = '';
      document.getElementById('Sound_time').style.display = 'none';
      document.getElementById('Sound_Repeat_type').style.visibility="visible";
      document.getElementById('Sound_Repeat_value').style.visibility="visible";
      document.getElementById('Sound_Repeat_variable').style.visibility="visible";
      break;

    case 'T':
      document.getElementById('Sound_value').style.display = 'none';
      document.getElementById('Sound_variable').style.display = 'none';
      document.getElementById('Sound_time').style.display = '';
      document.getElementById('Sound_Repeat_type').style.visibility="hidden";
      document.getElementById('Sound_Repeat_value').style.visibility="hidden";
      document.getElementById('Sound_Repeat_variable').style.visibility="hidden";
      break;
  }
}

function changeSoundPopupTimeType(x)
{
  var type = x.value;

  switch (type)
  {
    case 'T':
      document.getElementById('Sound_time_plus_value').style.display = 'none';
      document.getElementById('Sound_time_nearest_value').style.display = 'none';
      break;

    case '+':
      document.getElementById('Sound_time_plus_value').style.display = '';
      document.getElementById('Sound_time_nearest_value').style.display = 'none';
      break;

    case '^':
      document.getElementById('Sound_time_plus_value').style.display = 'none';
      document.getElementById('Sound_time_nearest_value').style.display = '';
      break;
  }
}

function changeSoundRepeatPopupType(x)
{
  switch (x.value)
  {
    case 'I':
      document.getElementById('Sound_Repeat_value').style.display = '';
      document.getElementById('Sound_Repeat_variable').style.display = 'none';
      break;

    case 'V':
      document.getElementById('Sound_Repeat_value').style.display = 'none';
      document.getElementById('Sound_Repeat_variable').style.display = '';
      break;

    case 'S':
      document.getElementById('Sound_Repeat_value').style.display = 'none';
      document.getElementById('Sound_Repeat_variable').style.display = 'none';
      break;
  }
}

function changeSoundPopupBlank(x)
{
  if (x.checked)
  {
    document.getElementById('soundPopupIntro').style.display = 'none';
    document.getElementById('Sound_Repeat_value').style.display = 'none';
    document.getElementById('Sound_Repeat_variable').style.display = 'none';
    document.getElementById('Sound_value').style.display = 'none';
    document.getElementById('Sound_variable').style.display = 'none';
    document.getElementById('Sound_time').style.display = 'none';
    document.getElementById('Sound_Repeat_type').style.visibility="hidden";
    document.getElementById('Sound_Repeat_value').style.visibility="hidden";
    document.getElementById('Sound_Repeat_variable').style.visibility="hidden";
    document.getElementById('Sound_time_plus_value').style.display = 'none';
    document.getElementById('Sound_time_nearest_value').style.display = 'none';
    document.getElementById('Sound_Repeat_value').style.display = 'none';
    document.getElementById('Sound_Repeat_variable').style.display = 'none';
  }
  else
  {
    changeSoundPopupType(document.getElementById("popupSoundType"));
    changeSoundPopupTimeType(document.getElementById("popupSoundTimeType"));
    changeSoundRepeatPopupType(document.getElementById("popupSoundRepeatType"));
  }
}

function identifyModule(moduleName)
{
  var tempString = "/Modules/" + moduleName;
  var targetString = SOM + "ID" + EOM;
  client.publish(tempString, targetString, 2);
}

function requestConfiguration()
{
  var y = document.getElementById("Sensors");
  var tempString = "/Modules/" + moduleName;
  var targetString = SOM + "RC" + EOM;
  client.publish(tempString, targetString, 2);
}

function checkBounds(x)
{
  if(parseInt(x.value)>parseInt(x.form.popupIntegerRange.max))
    x.value=x.form.popupIntegerRange.max;
  if(parseInt(x.value)<parseInt(x.form.popupIntegerRange.min))
    x.value=x.form.popupIntegerRange.min;
  x.form.popupIntegerRange.value=x.value;
}

function checkServoBounds(x)
{
  if(parseInt(x.value)>parseInt(document.getElementById("popupServoRange").max))
    x.value=document.getElementById("popupServoRange").max;
  if(parseInt(x.value)<parseInt(document.getElementById("popupServoRange").min))
    x.value=document.getElementById("popupServoRange").min;
  document.getElementById("popupServoRange").value=x.value;
}

function check(popupName, e)
{
  if(e.key === "Enter")
  {
    saveIntegerPopup();
    e.preventDefault();
  }
}

function checkMimicBounds(x)
{
  if (parseInt(x.form.popupMimicInputFrom.value) > parseInt(x.form.popupMimicInputFrom.max))
  {
    x.form.popupMimicInputFrom.value = x.form.popupMimicInputFrom.max;
    x.form.popupMimicRangeFrom.value = x.form.popupMimicInputFrom.value;
  }

  if (parseInt(x.form.popupMimicInputTo.value) > parseInt(x.form.popupMimicInputTo.max))
  {
    x.form.popupMimicInputTo.value = x.form.popupMimicInputTo.max;
    x.form.popupMimicRangeTo.value = x.form.popupMimicInputTo.value;
  }

  if (parseInt(x.form.popupMimicInputFrom.value) < parseInt(x.form.popupMimicInputFrom.min))
  {
    x.form.popupMimicInputFrom.value = x.form.popupMimicInputFrom.min;
    x.form.popupMimicRangeFrom.value = x.form.popupMimicInputFrom.value;
  }

  if (parseInt(x.form.popupMimicInputTo.value) < parseInt(x.form.popupMimicInputTo.min))
  {
    x.form.popupMimicInputTo.value = x.form.popupMimicInputTo.min;
    x.form.popupMimicRangeTo.value = x.form.popupMimicInputTo.value;
  }

  if (parseInt(x.form.popupMimicRangeTo.value) < parseInt(x.form.popupMimicRangeFrom.value))
  {
    x.form.popupMimicRangeTo.value = x.form.popupMimicRangeFrom.value;
    x.form.popupMimicInputTo.value = x.form.popupMimicRangeFrom.value;
  }
}

function checkLightBounds(x)
{
  if (parseInt(x.form.popupLightInputFrom.value) > parseInt(x.form.popupLightInputFrom.max))
  {
    x.form.popupLightInputFrom.value = x.form.popupLightInputFrom.max;
    x.form.popupLightRangeFrom.value = x.form.popupLightInputFrom.value;
  }

  if (parseInt(x.form.popupLightInputTo.value) > parseInt(x.form.popupLightInputTo.max))
  {
    x.form.popupLightInputTo.value = x.form.popupLightInputTo.max;
    x.form.popupLightRangeTo.value = x.form.popupLightInputTo.value;
  }

  if (parseInt(x.form.popupLightInputFrom.value) < parseInt(x.form.popupLightInputFrom.min))
  {
    x.form.popupLightInputFrom.value = x.form.popupLightInputFrom.min;
    x.form.popupLightRangeFrom.value = x.form.popupLightInputFrom.value;
  }

  if (parseInt(x.form.popupLightInputTo.value) < parseInt(x.form.popupLightInputTo.min))
  {
    x.form.popupLightInputTo.value = x.form.popupLightInputTo.min;
    x.form.popupLightRangeTo.value = x.form.popupLightInputTo.value;
  }

  if (parseInt(x.form.popupLightRangeTo.value) < parseInt(x.form.popupLightRangeFrom.value))
  {
    x.form.popupLightRangeTo.value = x.form.popupLightRangeFrom.value;
    x.form.popupLightInputTo.value = x.form.popupLightRangeFrom.value;
  }
}

/***********************************************************/

function openMimicListPopup(x)
{
  document.getElementById("editMimicListPopup").style.display = "block";
  editMimicListObject = x;

  deleteMimicListList();

  var configTableName = "MimicListTable";

  var ConfigDetails = x.value.split("+");

  if (ConfigDetails[0] == "A")
    document.getElementById("popupMimicRangeAlternate").checked = true;
  else
    document.getElementById("popupMimicRangeAlternate").checked = false;

  for (var i = 1; i<ConfigDetails.length; i++)
  {
    var configTable = document.getElementById(configTableName);
    var cell = {};

    var row = configTable.insertRow();
    var index = row.rowIndex;
    row.id = "configRowMimicList_" +index;

    populateMimicListRow(index, ConfigDetails[i]);
  }

  if (configTable.rows.length > 20)
  {
    $('.mimic_add_button').css('visibility', 'hidden');
  }
}

function closeMimicListPopup()
{
  document.getElementById("editMimicListPopup").style.display = "none";
}

function saveMimicListPopup()
{
var configTableName = "MimicListTable";
var configTable = document.getElementById(configTableName);
var row;
var newValue = "";
var newText = "";
var toolTipString = "";

  if (document.getElementById("popupMimicRangeAlternate").checked)
    newValue = "A+";
  else
    newValue = "N+";

  for (var i=0; i<configTable.rows.length; i++)
  {
    if (i != 0)
    {
      newValue += "+";
      newText  += "+";
    }
    configRow = configTable.rows[i].cells[1].firstChild;
    newValue += configRow.value;

    newText += configRow.innerHTML;
  }

  editMimicListObject.value = newValue;

  if (newText.length > 8)
  {
    toolTipString = newText.replace(/\+/g, "<br />");
    newText = newText.substring(0,7) + "...";
  }
  else
  {
    toolTipString = "";
  }

  if (document.getElementById("popupMimicRangeAlternate").checked)
    newText += "<span style='font-family:icons'>&#xefcf;</span>";

  if (toolTipString != "")
    newText += '<span class="tooltiptext">' + toolTipString + '</span>';

  editMimicListObject.innerHTML = newText;

  ConfigMimicRangeChange(5, editMimicListObject.name);

  closeMimicListPopup();
}

function buildMimicListString(rowNumber)
{
  var cell;
  var configTableName = "MimicListTable";
  var configTable = document.getElementById(configTableName);
  var currentRow = document.getElementById("configRowMimicList_" + rowNumber);
  var ConfigString = "";
  ConfigString += currentRow.cells[1].firstChild.value;

  return ConfigString;
}

function populateMimicListRow(rowNumber, configString)
{
  var configTableName = "MimicListTable";
  var configTable = document.getElementById(configTableName);
  var topRow = configTable.firstElementChild.children[0].id;
  var row = document.getElementById("configRowMimicList_"+ rowNumber);

  cell = row.insertCell();
  cell.style.width = "20px";
  if (topRow != row.id)
  {
    cell.innerHTML = "<button title='Swap this row with the one above it' class='config_swap_button' id='swapRow_button"+rowNumber+"' onclick='manipulateMimicListRows("+rowNumber+", \"Swap\")'><span style=\"font-family:icons;font-size: 20px;\">&#xeac5;</span></button>";
  }
  else
  {
    cell.innerHTML = "&nbsp";
  }

  cell = row.insertCell();
  cell.style.width = "80px";

  var ConfigString = "";
  var visibleConfigString = "";
  var alternating = false;
  var ConfigDetailsSplitOuter = configString.split("+");
  for (var k=0; k< ConfigDetailsSplitOuter.length; k++)
  {
    var ConfigDetailsSplit = ConfigDetailsSplitOuter[k].split(":");
    if (k!=0)
    {
      ConfigString += "+";
    }
    if (ConfigDetailsSplit[0] == ConfigDetailsSplit[1])
      ConfigString += ConfigDetailsSplit[0];
    else
      ConfigString += ConfigDetailsSplit[0] + ":" + ConfigDetailsSplit[1];
  }

  if (ConfigString.length > 8)
    visibleConfigString = ConfigString.substring(0,8) + "...";
  else
    visibleConfigString = ConfigString;

  var heading = "LED Range"
  cell.innerHTML = '<button class="config_button" id="editMimicListPopup_button'+rowNumber+'" onclick="openMimicRangePopup(this);" value="' + configString + '">' + visibleConfigString + '</button>';

  cell = row.insertCell();
  cell.innerHTML = "<button title='Insert a new row below this one' class='config_image_button mimic_add_button' style='' id='insertRow_button"+rowNumber+"' onclick='manipulateMimicListRows("+rowNumber+", \"Insert\")'><span style=\"font-family:icons;font-size: 17;color: darkblue;\"></span></button>";

  cell = row.insertCell();
  if (topRow != row.id)
  {
    cell.innerHTML = "<button title='Delete this row' class='config_image_button' style='' id='duplicateRow_button"+rowNumber+"' onclick='manipulateMimicListRows("+rowNumber+", \"Delete\")'><span style=\"font-family:icons;font-size: 17;color: darkred;\"></span></button>";
  }
  else
  {
    cell.innerHTML = "&nbsp";
  }
}

function addMimicListToList(label)
{
  if (label != "")
  {
    var configTableName = "MimicListTable";
    var configTable = document.getElementById(configTableName);
    var cell = {};

    var row = configTable.insertRow();
    var index = row.rowIndex;
    row.id = "configRowMimicList" + "_" +index;
    populateMimicListRow(index, label);
  }
}

function deleteMimicListList()
{
  var configTableName = "MimicListTable";
  var configTable = document.getElementById(configTableName);

  while (configTable.rows.length > 0)
    configTable.deleteRow(0);
}

function manipulateMimicListRows(currentRow, action)
{
var newRowNumber = 0;
var configTableName = "MimicListTable";
var configTable = document.getElementById(configTableName);
var cell = {};

var currentRowObject = document.getElementById("configRowMimicList_" + currentRow);
var currentRowIndex = currentRowObject.rowIndex;

  switch(action)
  {
    case "Delete":
      configTable.deleteRow(currentRowIndex);
      if (configTable.rows.length > 0)
        configTable.children[0].children[0].firstChild.innerHTML = "";

      if (configTable.rows.length <= 20)
      {
        $('.mimic_add_button').css('visibility','visible');
      }
      break;

    case "Insert":
      var count = configTable.rows.length;
      for(var i=0; i<count; i++)
      {
          if (parseInt(configTable.rows[i].id.substr(19)) > parseInt(newRowNumber))
            newRowNumber = parseInt(configTable.rows[i].id.substr(19));
      }
      newRowNumber++;
      var configString = buildMimicListString(currentRow)
      var row = configTable.insertRow(currentRowIndex + 1);
      row.id = "configRowMimicList" + "_" +newRowNumber;
      populateMimicListRow(newRowNumber, configString);

      if (configTable.rows.length > 20)
      {
        $('.mimic_add_button').css('visibility', 'hidden');
      }
      else
      {
        $('.mimic_add_button').css('visibility','visible');
      }
      break;

    case "Swap":
      var previousRowObject = currentRowObject.previousSibling;
      var previousRowIndex = previousRowObject.rowIndex;
      var previousRow = previousRowObject.id.substr(19);
      var currentDetails = buildMimicListString(currentRow);
      var previousDetails = buildMimicListString(previousRow);

      while (currentRowObject.firstChild != undefined)
        currentRowObject.deleteCell(0);
      populateMimicListRow(currentRow, previousDetails);

      while (previousRowObject.firstChild != undefined)
        previousRowObject.deleteCell(0);
      populateMimicListRow(previousRow, currentDetails);
      break;
  }
}

function StepperChoiceDisplay(x)
{
  if (x.selectedIndex == 0)
    document.getElementById('StepperSetupRow').style.visibility = "hidden";
  else
    document.getElementById('StepperSetupRow').style.visibility = "visible";
}

function StepperClockSelected(x)
{
  if (x.checked)
    document.getElementById('ConfigArea07').style.visibility = "hidden";
  else
    document.getElementById('ConfigArea07').style.visibility = "visible";
}

/***********************************************************/

function openLocalSoundFilesPopup(x)
{
  document.getElementById("editLocalSoundFileListPopup").style.display = "block";
  editLabelEventObject = x;
  api_call_sound_list();
}

function closeLocalSoundFilesPopup()
{
  document.getElementById("editLocalSoundFileListPopup").style.display = "none";
}

function openLabelEventPopup(Title, x, rowNumber)
{
  document.getElementById("editLabelEventPopup").style.display = "block";
  editLabelEventObject = x;
  editLabelEventRow = rowNumber;

  document.getElementById("labelEventPopupTitle").innerHTML = Title;

  var blankButton = document.getElementById('_BLANK');

  var eventBankID = x.value.substr(0,1);
  var eventTensID = x.value.substr(1,1);
  var eventUnitsID = x.value.substr(2,1);

  var option = $('#LabelEventBank').children('option[value="'+ eventBankID +'"]');
  option[0].selected = true;

  var option = $('#LabelEventTens').children('option[value="'+ eventTensID +'"]');
  option[0].selected = true;

  var option = $('#LabelEventUnits').children('option[value="'+ eventUnitsID +'"]');
  option[0].selected = true;

  return;
}

function closeLabelEventPopup()
{
  document.getElementById("editLabelEventPopup").style.display = "none";
}

function saveLabelEventPopup()
{
  var e = document.getElementById("LabelEventBank");
  var newValue = e.options[e.selectedIndex].value;
  e = document.getElementById("LabelEventTens");
  newValue += e.options[e.selectedIndex].value;
  e = document.getElementById("LabelEventUnits");
  newValue += e.options[e.selectedIndex].value;

  editLabelEventObject.value = newValue;
  editLabelEventObject.innerHTML = newValue;

  if (editLabelEventRow >= 0)
    updateInputRow(editLabelEventRow);

  closeLabelEventPopup();
}

function buildLabelString(rowNumber, includeRowNumber)
{
  var cell;
  var configTableName = "LabelTable";
  var configTable = document.getElementById(configTableName);
  var currentRow = document.getElementById("configRowLabel_" + rowNumber);
  var ConfigString = "";
  if (includeRowNumber)
    ConfigString += rowNumber + ",";
  ConfigString += currentRow.cells[1].firstChild.value;
  ConfigString += "," + currentRow.cells[2].firstChild.value;

  return ConfigString;
}

function populateLabelRow(rowNumber, configString)
{
  var ConfigDetails = configString.split(",");
  var configTableName = "LabelTable";
  var configTable = document.getElementById(configTableName);
  var topRow = configTable.firstElementChild.children[0].id;
  var row = document.getElementById("configRowLabel_"+ rowNumber);

  cell = row.insertCell();
  cell.style.width = "20px";
  if (topRow != row.id)
  {
    cell.innerHTML = "<button title='Swap this row with the one above it' class='config_swap_button' id='swapRow_button"+rowNumber+"' onclick='manipulateLabelRows("+rowNumber+", \"Swap\")'><span style=\"font-family:icons;font-size: 20px;\">&#xeac5;</span></button>";
  }

  var newCell = row.insertCell();
  var ConfigString = "";
  ConfigString = ConfigDetails[1];

  newCell.innerHTML = '<button class="config_button" id="editLabelPopup_button'+rowNumber+'" onclick="openLabelEventPopup(\'Event to label\', this, -1);" value="' + ConfigString + '">' + ConfigString + '</button>';

  newCell = row.insertCell();
  ConfigString = ConfigDetails[2];

  newCell.innerHTML = '<input type="text" class="config_button" maxlength="25" style="width:325px;" id="editLabelPopup_button'+rowNumber+'" value="' + ConfigString + '"></input>';

  cell = row.insertCell();
  cell.innerHTML = "<button title='Insert a new row below this one' class='config_image_button' style='' id='insertRow_button"+rowNumber+"' onclick='manipulateLabelRows("+rowNumber+", \"Insert\")'><span style=\"font-family:icons;font-size: 17;color: darkblue;\"></span></button>";

  cell = row.insertCell();
  cell.innerHTML = "<button title='Delete this row' class='config_image_button' style='' id='duplicateRow_button"+rowNumber+"' onclick='manipulateLabelRows("+rowNumber+", \"Delete\")'><span style=\"font-family:icons;font-size: 17;color: darkred;\"></span></button>";
}

function addLabelToList(label)
{
  if (label != "")
  {
    var configTableName = "LabelTable";
    var configTable = document.getElementById(configTableName);
    var cell = {};

    var row = configTable.insertRow();
    var index = row.rowIndex;
    row.id = "configRowLabel" + "_" +index;
    populateLabelRow(index, label);
  }
}

function deleteLabelList()
{
  var configTableName = "LabelTable";
  var configTable = document.getElementById(configTableName);

  while (configTable.rows.length > 0)
    configTable.deleteRow(0);
}

function manipulateLabelRows(currentRow, action)
{
  var newRowNumber = 0;
  var configTableName = "LabelTable";
  var configTable = document.getElementById(configTableName);
  var cell = {};

  var currentRowObject = document.getElementById("configRowLabel_" + currentRow);
  var currentRowIndex = currentRowObject.rowIndex;

  switch(action)
  {
    case "Delete":
      configTable.deleteRow(currentRowIndex);
      if (configTable.rows.length > 0)
        configTable.children[0].children[0].firstChild.innerHTML = "";
      break;

    case "Insert":
      var count = configTable.rows.length;
      for(var i=0; i<count; i++)
      {
          if (parseInt(configTable.rows[i].id.substr(15)) > parseInt(newRowNumber))
            newRowNumber = parseInt(configTable.rows[i].id.substr(15));
      }
      newRowNumber++;
      var configString = buildLabelString(currentRow, true)
      var row = configTable.insertRow(currentRowIndex + 1);
      row.id = "configRowLabel" + "_" +newRowNumber;
      populateLabelRow(newRowNumber, configString);
      break;

    case "Swap":
      var previousRowObject = currentRowObject.previousSibling;
      var previousRowIndex = previousRowObject.rowIndex;
      var previousRow = previousRowObject.id.substr(15);
      var currentDetails = buildLabelString(currentRow, true);
      var previousDetails = buildLabelString(previousRow, true);

      while (currentRowObject.firstChild != undefined)
        currentRowObject.deleteCell(0);
      populateLabelRow(currentRow, previousDetails);

      while (previousRowObject.firstChild != undefined)
        previousRowObject.deleteCell(0);
      populateLabelRow(previousRow, currentDetails);
      break;
  }
}

function manipulateSoundRows(Filename, action)
{
  switch(action)
  {
    case "Delete":
      if (confirm("Are you sure you wish to delete "+Filename))
      {
        api_call_delete_sound(Filename);
      }
      break;
  }
}

function uploadLabelConfigFile()
{
  var nextRow = "";
  var configTableName = "LabelTable";
  var configTable = document.getElementById(configTableName);
  var numRows = configTable.rows.length;

  if (confirm("Select OK to upload the label configuration"))
  {
    nextRow = "?function=create";

    console.log(nextRow);
    api_call_upload(nextRow);

    if (numRows > 0)
    {
      for (var i=0; i<numRows; i++)
      {
        var nextRowID = parseInt(configTable.rows[i].id.substr(15))
        nextRow = "?function=add&";

        nextRow += "data=" + buildLabelString(nextRowID, false);
        console.log(nextRow);
        api_call_upload(nextRow);
      }
    }
  }
}

function addLabelToSelect(x, y)
{
  var ConfigDetails = x.split(",");
  var option = document.createElement("option");
  option.text = ConfigDetails[2] + " (" + ConfigDetails[1] + ")";
  option.value = ConfigDetails[1];
  y.add(option);
}

function api_call_select_list(selectObject)
{
  var queryString = "http://" + "<?php echo $_SERVER['HTTP_HOST']?>" + "/api/label_api.php?function=list";
  var returnVal = true;

  var L = selectObject.options.length - 1;
  for(var i = L; i >= 0; i--)
    selectObject.remove(i);

  var jqxhr = $.ajax({url: queryString, async:false})
  .done(function( msg) {
    if (msg == '0')
      returnVal = false;
    else
    {
      var labelList = msg.split("|");
      labelList.forEach (function (item) {addLabelToSelect(item, this)}, selectObject);
    }
  })
  .fail(function() {
    alert( "Network error - couldn't contact the base station." );
  });

  return returnVal;
}

function api_call_list()
{
  if (confirm("Selecting OK will overwrite and details below"))
  {
    var queryString = "http://" + "<?php echo $_SERVER['HTTP_HOST']?>" + "/api/label_api.php?function=list";
    var returnString = "";

    var jqxhr = $.ajax({url: queryString, async:false})
    .done(function( msg) {
      if (msg != '0')
      {
        deleteLabelList();
        if (msg)
        var labelList = msg.split("|");
        labelList.forEach (addLabelToList);
      }
      else
      {
        deleteLabelList();
        addLabelToList("1,A00,Blank Label")
      }
    })
    .fail(function() {
      alert( "Network error - couldn't contact the base station." );
    });
  }
}

function addSoundToList(label)
{
  if (label != "")
  {
    var configTableName = "SoundListTable";
    var configTable = document.getElementById(configTableName);
    var cell = {};

    var row = configTable.insertRow();
    var index = row.rowIndex;
    row.id = "configRowSound" + "_" +index;
    populateSoundRow(index, label);
  }
}

function deleteSoundList()
{
  var configTableName = "SoundListTable";
  var configTable = document.getElementById(configTableName);

  while (configTable.rows.length > 0)
    configTable.deleteRow(0);
}

function populateSoundRow(rowNumber, configString)
{
  var configTableName = "SoundTable";
  var configTable = document.getElementById(configTableName);
  var row = document.getElementById("configRowSound_"+ rowNumber);

  var cell = row.insertCell();
  var randomAudioID = Math.random()*100000;
  cell.innerHTML = "<audio id='Audio"+rowNumber+"'><source src='Sounds/"+configString+"?"+randomAudioID+"' type='audio/wav'></audio><button title='Play' class='config_image_button' style='' id='duplicateRow_button"+rowNumber+"' onclick='var x = document.getElementById(\"Audio"+rowNumber+"\"); x.play();'><span style=\"font-family:icons;font-size: 14;color:darkgreen;\">&#xec74;</span></button>";
  var cell = row.insertCell();
  cell.innerHTML = "<button title='Pause' class='config_image_button' style='' id='duplicateRow_button"+rowNumber+"' onclick='var x = document.getElementById(\"Audio"+rowNumber+"\"); x.pause(); x.currentTime = 0;'><span style=\"font-family:icons;font-size: 14;color:#e5a900;\">&#xeffc;</span></button>";

  var newCell = row.insertCell();
  var result = "";
  if (configString.length <= 40)
  {
    result = configString;
  }
  else
  {
    result = configString.slice(0, 34);
    result += "&hellip;";
    result += configString.slice(-4);
  };
  newCell.innerHTML = result;
  newCell.style.width = "90%";

  var cell = row.insertCell();
  cell.innerHTML = "<button title='Delete this row' class='config_image_button' style='' id='duplicateRow_button"+rowNumber+"' onclick='manipulateSoundRows(\""+configString+"\", \"Delete\")'><span style=\"font-family:icons;font-size: 17;color: darkred;\"></span></button>";
}

function api_call_sound_list()
{
  var queryString = "http://" + "<?php echo $_SERVER['HTTP_HOST']?>" + "/api/label_api.php?function=sound_list";
  var returnString = "";

  var jqxhr = $.ajax({url: queryString, async:false})
  .done(function( msg) {
    if (msg != '0')
    {
      deleteSoundList();
      if (msg)
      var soundList = msg.split(",");
      soundList.forEach (addSoundToList);
    }
    else
    {
      deleteSoundList();
    }
  })
  .fail(function() {
    alert( "Network error - couldn't contact the base station." );
  });
}

function api_call_delete_sound(Filename)
{
  var queryString = "http://" + "<?php echo $_SERVER['HTTP_HOST']?>" + "/api/label_api.php?function=sound_delete&Filename="+Filename;
  var returnString = "";

  var jqxhr = $.ajax({url: queryString, async:false})
  .done(function( msg) {
    if (msg == '0')
    {
      api_call_sound_list();
    }
    else
    {
      alert ("Failed to delete file - unknown error");
    }
  })
  .fail(function() {
    alert( "Network error - couldn't contact the base station." );
  });
}

function api_call_upload(dataString)
{
  var queryString = "http://" + "<?php echo $_SERVER['HTTP_HOST']?>" + "/api/label_api.php" + dataString;

  var jqxhr = $.ajax({url: queryString, async:false})
  .fail(function() {
    alert( "Network error - couldn't contact the base station." );
  });
}

function ajax_upload_sound(event)
{
  var myForm = document.getElementById('formAjax');  // Our HTML form's ID
  var myFile = document.getElementById('fileAjax');  // Our HTML files' ID
  var statusP = document.getElementById('upload_progress');

  var files = myFile.files;

  var formData = new FormData();

  var file = files[0];

 var validFilename = new RegExp('[^0-9a-zA-Z_.]');

  if (!validFilename.test(file.name))
  {
    statusP.innerHTML = 'Uploading...';
    statusP.style.width = "0%";

    formData.append('fileAjax', file, file.name);

    var xhr = new XMLHttpRequest();

    xhr.upload.onprogress = (event) => {
      var progress = Math.ceil(((event.loaded) / event.total) * 100);
      if (progress > 100)
        progress = 100;
      statusP.style.width = progress+"%";
    }

    xhr.open('POST', 'upload_sound.php', true);
    xhr.onload = function () {
      if (xhr.status == 200) {
        statusP.innerHTML = 'Complete!';
        statusP.style.width = "0%";
        api_call_sound_list();
      } else {
        statusP.innerHTML = 'Upload error. Try again.';
        statusP.style.width = "0%";
      }
    };

    xhr.send(formData);
  }
  else
    alert ("The filename selected is invalid. Please rename the file and try again.");

  return false;
}

$(document).ready(function(){
  displayModulesList();
});
</script>

<body onload="initialisePage()">
<div class="outercontainer">

<div id="editEventPopup" class="modal" style="color:black">
  <div class="event-modal-content">
    <input type="radio" id="_CLOCK" style="margin-left: 8px" name="eventType" value="C" onchange="changeEventPopupType(this)"><span id="_CLOCK_SPAN"> Clock</span>
    <input type="radio" id="_SET" name="eventType" value="S" onchange="changeEventPopupType(this)"><span id="_SET_SPAN"> Switch</span>
    <input type="radio" id="_TIMERANGE" name="eventType" value="R" onchange="changeEventPopupType(this)"><span id="_TIMERANGE_SPAN"> Hour Range</span>
    <input type="radio" id="_LABEL" name="eventType" value="L" onchange="changeEventPopupType(this)"><span id="_LABEL_SPAN"> By Label</span>
    <input type="radio" id="_INIT" name="eventType" value="I" onchange="changeEventPopupType(this)"><span id="_INIT_SPAN"> On Startup</span>
    <input type="radio" id="_ONLYCLOCK" style="display:none;" name="eventType" value="O" onchange="changeEventPopupType(this)">
    <br><input type="checkbox" id="_BLANK" onchange="changeEventPopupType(this)"><span id="_BLANK_SPAN">Blank</span>
    <br>
    <table>
      <tr id="eventRowEvent">
        <td>
          <select id="EventBank" style="width:75px">
            <option value='A'>A</option>
            <option value='B'>B</option>
            <option value='C'>C</option>
            <option value='D'>D</option>
            <option value='E'>E</option>
            <option value='F'>F</option>
            <option value='G'>G</option>
            <option value='H'>H</option>
            <option value='I'>I</option>
            <option value='J'>J</option>
            <option value='K'>K</option>
            <option value='L'>L</option>
            <option value='M'>M</option>
            <option value='N'>N</option>
            <option value='O'>O</option>
            <option value='P'>P</option>
            <option value='Q'>Q</option>
            <option value='R'>R</option>
            <option value='S'>S</option>
            <option value='T'>T</option>
            <option value='U'>U</option>
            <option value='V'>V</option>
            <option value='W'>W</option>
            <option value='X'>X</option>
            <option value='Y'>Y</option>
            <option value='Z'>Z</option>
          </select>
        </td>
        <td>
          <select id="EventTens" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
          </select>
        </td>
        <td>
          <select id="EventUnits" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
          </select>
        </td>
        <td>
          <select id="triggerType" style="width:75px">
            <option value='S'>On</option>
            <option value='U'>Off</option>
          </select>
        </td>
      </tr>
      <tr id="eventRowIDHeader">
        <td colspan=2 style="border-top-style: solid; border-left-style: solid; border-right-style: solid; text-align: center;">
        Detector
        </td>
        <td></td>
        <td colspan=4 style="border-top-style: solid; border-left-style: solid; border-right-style: solid; text-align: center;">
        Tag
        </td>
      </tr>
      <tr id="eventRowID">
        <td>
          <select id="EventStationID1" style="width:75px">
            <option value='A'>A</option>
            <option value='B'>B</option>
            <option value='C'>C</option>
            <option value='D'>D</option>
            <option value='E'>E</option>
            <option value='F'>F</option>
            <option value='G'>G</option>
            <option value='H'>H</option>
            <option value='I'>I</option>
            <option value='J'>J</option>
            <option value='K'>K</option>
            <option value='L'>L</option>
            <option value='M'>M</option>
            <option value='N'>N</option>
            <option value='O'>O</option>
            <option value='P'>P</option>
            <option value='Q'>Q</option>
            <option value='R'>R</option>
            <option value='S'>S</option>
            <option value='T'>T</option>
            <option value='U'>U</option>
            <option value='V'>V</option>
            <option value='W'>W</option>
            <option value='X'>X</option>
            <option value='Y'>Y</option>
            <option value='Z'>Z</option>
            <option value='?'>Any</option>
          </select>
        </td>
        <td>
          <select id="EventStationID2" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
            <option value='?'>Any</option>
          </select>
        </td>
        <td>
        &nbsp
        </td>
        <td>
          <select id="EventIDOne" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
            <option value='A'>A</option>
            <option value='B'>B</option>
            <option value='C'>C</option>
            <option value='D'>D</option>
            <option value='E'>E</option>
            <option value='F'>F</option>
            <option value='G'>G</option>
            <option value='H'>H</option>
            <option value='I'>I</option>
            <option value='J'>J</option>
            <option value='K'>K</option>
            <option value='L'>L</option>
            <option value='M'>M</option>
            <option value='N'>N</option>
            <option value='O'>O</option>
            <option value='P'>P</option>
            <option value='Q'>Q</option>
            <option value='R'>R</option>
            <option value='S'>S</option>
            <option value='T'>T</option>
            <option value='U'>U</option>
            <option value='V'>V</option>
            <option value='W'>W</option>
            <option value='X'>X</option>
            <option value='Y'>Y</option>
            <option value='Z'>Z</option>
            <option value='?'>Any</option>
          </select>
        </td>
        <td>
          <select id="EventIDTwo" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
            <option value='A'>A</option>
            <option value='B'>B</option>
            <option value='C'>C</option>
            <option value='D'>D</option>
            <option value='E'>E</option>
            <option value='F'>F</option>
            <option value='G'>G</option>
            <option value='H'>H</option>
            <option value='I'>I</option>
            <option value='J'>J</option>
            <option value='K'>K</option>
            <option value='L'>L</option>
            <option value='M'>M</option>
            <option value='N'>N</option>
            <option value='O'>O</option>
            <option value='P'>P</option>
            <option value='Q'>Q</option>
            <option value='R'>R</option>
            <option value='S'>S</option>
            <option value='T'>T</option>
            <option value='U'>U</option>
            <option value='V'>V</option>
            <option value='W'>W</option>
            <option value='X'>X</option>
            <option value='Y'>Y</option>
            <option value='Z'>Z</option>
            <option value='?'>Any</option>
          </select>
        </td>
        <td>
          <select id="EventIDThree" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
            <option value='A'>A</option>
            <option value='B'>B</option>
            <option value='C'>C</option>
            <option value='D'>D</option>
            <option value='E'>E</option>
            <option value='F'>F</option>
            <option value='G'>G</option>
            <option value='H'>H</option>
            <option value='I'>I</option>
            <option value='J'>J</option>
            <option value='K'>K</option>
            <option value='L'>L</option>
            <option value='M'>M</option>
            <option value='N'>N</option>
            <option value='O'>O</option>
            <option value='P'>P</option>
            <option value='Q'>Q</option>
            <option value='R'>R</option>
            <option value='S'>S</option>
            <option value='T'>T</option>
            <option value='U'>U</option>
            <option value='V'>V</option>
            <option value='W'>W</option>
            <option value='X'>X</option>
            <option value='Y'>Y</option>
            <option value='Z'>Z</option>
            <option value='?'>Any</option>
          </select>
        </td>
        <td>
          <select id="EventIDFour" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
            <option value='A'>A</option>
            <option value='B'>B</option>
            <option value='C'>C</option>
            <option value='D'>D</option>
            <option value='E'>E</option>
            <option value='F'>F</option>
            <option value='G'>G</option>
            <option value='H'>H</option>
            <option value='I'>I</option>
            <option value='J'>J</option>
            <option value='K'>K</option>
            <option value='L'>L</option>
            <option value='M'>M</option>
            <option value='N'>N</option>
            <option value='O'>O</option>
            <option value='P'>P</option>
            <option value='Q'>Q</option>
            <option value='R'>R</option>
            <option value='S'>S</option>
            <option value='T'>T</option>
            <option value='U'>U</option>
            <option value='V'>V</option>
            <option value='W'>W</option>
            <option value='X'>X</option>
            <option value='Y'>Y</option>
            <option value='Z'>Z</option>
            <option value='?'>Any</option>
          </select>
        </td>
      </tr>
      <tr id="eventRowLabel">
        <td>
          <select id="LabelSelect" style="width:300px">
          </select>
        </td>
        <td>
          <select id="labelTriggerType" style="width:75px">
            <option value='S'>On</option>
            <option value='U'>Off</option>
          </select>
        </td>
      </tr>
      <tr id="eventRowTime">
        <td>
          <select id="EventHourTens" style="width:75px" onchange="eventTimeHoursChange(this);">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='#'>Any</option>
            <option value='?'>Random</option>
          </select>
        </td>
        <td>
          <select id="EventHourUnits" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
            <option value='#'>Any</option>
            <option value='?'>Random</option>
          </select>
        </td>
        <td>
          <span style="font-weight: bold;">:</span>
        </td>
        <td>
          <select id="EventMinuteTens" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='#'>Any</option>
            <option value='?'>Random</option>
          </select>
        </td>
        <td>
          <select id="EventMinuteUnits" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
            <option value='#'>Any</option>
            <option value='?'>Random</option>
          </select>
        </td>
      </tr>
      <tr id="eventRowRangeHours">
        <td>
          When hours are between
        </td>
        <td>
          <select id="EventRange1" style="width:75px" onchange="eventTimeHoursChange(this);">
            <option value=30>00:00</option>
            <option value=31>01:00</option>
            <option value=32>02:00</option>
            <option value=33>03:00</option>
            <option value=34>04:00</option>
            <option value=35>05:00</option>
            <option value=36>06:00</option>
            <option value=37>07:00</option>
            <option value=38>08:00</option>
            <option value=39>09:00</option>
            <option value=40>10:00</option>
            <option value=41>11:00</option>
            <option value=42>12:00</option>
            <option value=43>13:00</option>
            <option value=44>14:00</option>
            <option value=45>15:00</option>
            <option value=46>16:00</option>
            <option value=47>17:00</option>
            <option value=48>18:00</option>
            <option value=49>19:00</option>
            <option value=50>20:00</option>
            <option value=51>21:00</option>
            <option value=52>22:00</option>
            <option value=53>23:00</option>
          </select>
        </td>
        <td>
          and
        </td>
        <td>
          <select id="EventRange2" style="width:75px">
            <option value=30>00:00</option>
            <option value=31>01:00</option>
            <option value=32>02:00</option>
            <option value=33>03:00</option>
            <option value=34>04:00</option>
            <option value=35>05:00</option>
            <option value=36>06:00</option>
            <option value=37>07:00</option>
            <option value=38>08:00</option>
            <option value=39>09:00</option>
            <option value=40>10:00</option>
            <option value=41>11:00</option>
            <option value=42>12:00</option>
            <option value=43>13:00</option>
            <option value=44>14:00</option>
            <option value=45>15:00</option>
            <option value=46>16:00</option>
            <option value=47>17:00</option>
            <option value=48>18:00</option>
            <option value=49>19:00</option>
            <option value=50>20:00</option>
            <option value=51>21:00</option>
            <option value=52>22:00</option>
            <option value=53>23:00</option>
          </select>
        </td>
      </tr>
      <tr id="eventRowRangeMinutes">
        <td> And minutes match </td>
        <td>
          <select id="EventRangeMinuteTens" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='#'>Any</option>
            <option value='?'>Random</option>
          </select>
        </td>
        <td>
          <select id="EventRangeMinuteUnits" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
            <option value='#'>Any</option>
            <option value='?'>Random</option>
          </select>
        </td>
      </tr>
      <tr id="eventRowClock">
        <td>
          <select id="ClockHourTens" style="width:75px" onchange="eventClockHoursChange(this);">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
          </select>
        </td>
        <td>
          <select id="ClockHourUnits" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
          </select>
        </td>
        <td>
          <span style="font-weight: bold;">:</span>
        </td>
        <td>
          <select id="ClockMinuteTens" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
          </select>
        </td>
        <td>
          <select id="ClockMinuteUnits" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
          </select>
        </td>
      </tr>
    </table>
    <br />
    <button class="editEventPopup_save" onclick="saveEventPopup()">Save</button>
    <button class="editEventPopup_cancel" onclick="closeEventPopup()">Cancel</button>
  </div>
</div>

<div id="editLabelEventPopup" class="modal" style="color:black">
  <div class="event-modal-content">
    <H1 style="font-size:24px">
    <span id="labelEventPopupTitle">Title goes here</span>
   </H1>

    <table>
      <tr>
        <td>
          <select id="LabelEventBank" style="width:75px">
            <option value='A'>A</option>
            <option value='B'>B</option>
            <option value='C'>C</option>
            <option value='D'>D</option>
            <option value='E'>E</option>
            <option value='F'>F</option>
            <option value='G'>G</option>
            <option value='H'>H</option>
            <option value='I'>I</option>
            <option value='J'>J</option>
            <option value='K'>K</option>
            <option value='L'>L</option>
            <option value='M'>M</option>
            <option value='N'>N</option>
            <option value='O'>O</option>
            <option value='P'>P</option>
            <option value='Q'>Q</option>
            <option value='R'>R</option>
            <option value='S'>S</option>
            <option value='T'>T</option>
            <option value='U'>U</option>
            <option value='V'>V</option>
            <option value='W'>W</option>
            <option value='X'>X</option>
            <option value='Y'>Y</option>
            <option value='Z'>Z</option>
          </select>
        </td>
        <td>
          <select id="LabelEventTens" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
          </select>
        </td>
        <td>
          <select id="LabelEventUnits" style="width:75px">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
          </select>
        </td>
      </tr>
    </table>
    <br />
    <button class="editEventPopup_save" onclick="saveLabelEventPopup()">Save</button>
    <button class="editEventPopup_cancel" onclick="closeLabelEventPopup()">Cancel</button>
  </div>
</div>

<div id="editTimePopup" class="modal" style="color:black">

  <div class="time-modal-content">
    <table>
      <tr>
        <td>
          <select id="EventHoursTens" onchange="checkTimePopup()">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='#'>Any digit</option>
            <option value='?'>Random</option>
          </select>
        </td>
        <td>
          <select id="EventHoursUnits" onchange="checkTimePopup()">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
            <option value='#'>Any digit</option>
            <option value='?'>Random</option>
          </select>
        </td>
        <td>
          :
        </td>
        <td>
          <select id="EventMinutesTens">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='#'>Any digit</option>
            <option value='?'>Random</option>
          </select>
        </td>
        <td>
          <select id="EventMinutesUnits">
            <option value='0'>0</option>
            <option value='1'>1</option>
            <option value='2'>2</option>
            <option value='3'>3</option>
            <option value='4'>4</option>
            <option value='5'>5</option>
            <option value='6'>6</option>
            <option value='7'>7</option>
            <option value='8'>8</option>
            <option value='9'>9</option>
            <option value='#'>Any digit</option>
            <option value='?'>Random</option>
          </select>
        </td>
      </table>
    <br />
    <button class="editTimePopup_save" onclick="saveTimePopup()">Save</button>
    <button class="editTimePopup_cancel" onclick="closeTimePopup()">Cancel</button>
  </div>
</div>

<div id="editIntegerPopup" class="modal" style="color:black; z-index:100;">
  <div class="integer-modal-content">
    <h3 id="popupIntegerTitle"></h3>
    <form onsubmit="saveIntegerPopup();event.preventDefault();">
      <table>
        <tr>
          <td>
            <input id="popupIntegerRange" type="range" name="popupIntegerRange" min="0" max="127" value="64" oninput="this.form.popupIntegerInput.value=this.value" />
          </td>
          <td>
            <input id="popupIntegerInput" style="width:55px" type="number" onblur="checkBounds(this)" onchange="checkBounds(this)" onkeypress="check('editIntegerPopup', event)">
          </td>
        </tr>
      </table>
      <br />
      <button type='submit' class="editIntegerPopup_save" onclick="saveIntegerPopup();">Save</button>
      <button class="editIntegerPopup_cancel" onclick="closeIntegerPopup();return false;">Cancel</button>
    </form>
  </div>
</div>

<div id="editServoPopup" class="modal" style="color:black; z-index:50;">
  <div class="servo-modal-content" style="width:650px;">
    <h3 id="popupServoTitle"></h3>
    <input type="radio" id="_SERVO" style="margin-left: 8px" name="servoType" value="S" onchange="changeServoPopupType(this)">Servo <span style="font-family:icons;">&#xeff3;</span>
    <input type="radio" id="_RELAY" style="margin-left: 8px" name="servoType" value="R" onchange="changeServoPopupType(this)">Relay <span style="font-family:icons;">&#xeed9;</span>
    <input type="radio" id="_SIGNAL_HEAD" name="servoType" value="H" onchange="changeServoPopupType(this)">Signal <span style="font-family:icons;">&#xf016;</span>
    <input type="radio" id="_SOLENOID" name="servoType" value="P" onchange="changeServoPopupType(this)">Solenoid <span style="font-family:icons;">&#xee84;</span>
    <input type="radio" id="_KATO" name="servoType" value="K" onchange="changeServoPopupType(this)">Kato <span style="font-family:icons;">&#xefae;</span>
    <input type="radio" id="_STALL" name="servoType" value="T" onchange="changeServoPopupType(this)">Stall Motor <span style="font-family:icons;">&#xe892;</span>
    <br />
    <br />
      <table>
        <tr id = 'popupServo'>
          <td>
            Interactive? <input type="checkbox" id="popupServoInteractive">
          </td>
          <td id = 'ServoRange'>
            <input id="popupServoRange" type="range" name="popupServoRange" min="0" max="180" value="90" oninput="document.getElementById('popupServoInput').value=this.value;sendInteractiveServo(this);" />
          </td>
          <td>
            <input id="popupServoInput" style="width:50px" type="number" onblur="checkServoBounds(this);sendInteractiveServo(this);" onchange="checkServoBounds(this);sendInteractiveServo(this);">
          </td>
        </tr>
        <tr id = 'popupSignalHead'>
          <td>
            Pattern
          </td>
          <td>
            <select id="poupSignalHeadPattern">
              <option id="_S1" name="SignalType" value="24">- - - -</option>
              <option id="_S2" name="SignalType" value="28">O - - -</option>
              <option id="_S3" name="SignalType" value="32">- O - -</option>
              <option id="_S4" name="SignalType" value="36">O O - -</option>
              <option id="_S5" name="SignalType" value="40">- - O -</option>
              <option id="_S6" name="SignalType" value="44">O - O -</option>
              <option id="_S7" name="SignalType" value="48">- O O -</option>
              <option id="_S8" name="SignalType" value="50">O O O -</option>
              <option id="_S9" name="SignalType" value="54">- - - O</option>
              <option id="_S10" name="SignalType" value="58">O - - O</option>
              <option id="_S11" name="SignalType" value="62">- O - O</option>
              <option id="_S12" name="SignalType" value="66">O O - O</option>
              <option id="_S13" name="SignalType" value="68">- - O O</option>
              <option id="_S14" name="SignalType" value="72">O - O O</option>
              <option id="_S15" name="SignalType" value="74">- O O O</option>
              <option id="_S16" name="SignalType" value="78">O O O O</option>
            </select>
          </td>
          <td>
            Flash? <input type="checkbox" id="poupSignalHeadFlashing">
          </td>
        </tr>
        <tr id = 'popupSolenoid'>
          <td>
            Direction
          </td>
          <td>
            <select id="popupSolenoidDirection">
  	        <option value='L'>LEFT</option>
	          <option value='R'>RIGHT</option>
            </select>
          </td>
          <td>
            Pulse Length
          </td>
          <td>
        	  <input id="popupSolenoidPulse" style="width:50px" onclick="openIntegerPopup(this,1,100,'Solenoid Pulse Length')">
        	</td>
        </tr>
        <tr id = 'popupKato'>
          <td>
            Direction
          </td>
          <td>
            <select id="popupKatoDirection">
  	        <option value='L'>LEFT</option>
	          <option value='R'>RIGHT</option>
            </select>
          </td>
          <td>
            Energise Time
          </td>
          <td>
        	  <input id="popupKatoPulse" style="width:50px" onclick="openIntegerPopup(this,1,100,'Kato Energise Time')">
        	</td>
        </tr>
        <tr id = 'popupStallMotor'>
          <td style="width:70px;">
            Direction
          </td>
          <td>
            <select id="popupStallDirection">
  	        <option value='L'>LEFT</option>
	          <option value='R'>RIGHT</option>
            </select>
          </td>
          <td>
            Energise Time
          </td>
          <td>
        	  <input id="popupStallPulse" style="width:50px" onclick="openIntegerPopup(this,1,100,'Stall Motor Energise Time')">
        	</td>
        </tr>
        <tr id = 'popupRelay'>
          <td style="width:70px;">
            Action
          </td>
          <td>
            <select id="popupRelayAction" onchange="showRelayPulseTime()">
	          <option value='0'>OFF</option>
  	        <option value='1'>ON</option>
	          <option value='2'>MOMENTARY</option>
            </select>
          </td>
          <td id="relayPulseTimeTitle">
            Pulse Time
          </td>
          <td id="relayPulseTime">
        	  <input id="popupRelayPulse" style="width:50px" onclick="openIntegerPopup(this,1,99,'Relay Pulse Time')">
        	</td>
        </tr>
      </table>
      <br />
      <button type='submit' class="editServoPopup_save" onclick="saveServoPopup();">Save</button>
      <button class="editServoPopup_cancel" onclick="closeServoPopup()">Cancel</button>
  </div>
</div>

<div id="editMimicListPopup" class="modal" style="color:black; z-index:50;">
  <div class="mimiclist-modal-content">
    <h3 id="popupMimicListTitle">Mimic Range</h3>
      <div class="scrollbox">
        <table id="MimicListTable">
        </table>
      </div>
      <br />
      Alternate Colours every LED? <input id="popupMimicRangeAlternate" style="width:30px" type="checkbox">
      <br />
      <br />
      <button type='submit' class="editMimicListPopup_save" onclick="saveMimicListPopup();">Save</button>
      <button class="editMimicListPopup_cancel" onclick="closeMimicListPopup()">Cancel</button>
  </div>
</div>

<div id="editLocalSoundFileListPopup" class="modal" style="color:black; z-index:50;">
  <div class="soundlist-modal-content">
    <h3 id="popupSoundListTitle">Sound Files</h3>
      <div class="scrollbox">
        <table id="SoundListTable">
        </table>
      </div>
      <br />
      <button onclick="openSoundFilePopup('Upload Sound File')">Upload New Sounds</button>
      <br />
      <br />
      <button class="editSoundListPopup_cancel" onclick="closeLocalSoundFilesPopup()">Close</button>
  </div>
</div>

<div id="editSoundPopup" class="modal" style="color:black; z-index:50;">
  <div class="sound-modal-content">
    <h3 id="popupSoundTitle"></h3>
    <br />
    No sound <input type="checkbox" id="popupSoundBlank" onchange="changeSoundPopupBlank(this);">
    <br />
    <span id="soundPopupIntro">
      Play the sound
      <select id="popupSoundType" onchange="changeSoundPopupType(this);">
  	  <option value='I'>which has value</option>
	    <option value='V'>whose value is in variable</option>
	    <option value='T'>using the current time</option>
      </select>
    </span>
	<span id="Sound_variable">
	  <select id="popupSoundVariableNumber">
		<option value='$A'>A</option>
		<option value='$B'>B</option>
		<option value='$C'>C</option>
		<option value='$D'>D</option>
		<option value='$E'>E</option>
		<option value='$F'>F</option>
		<option value='$G'>G</option>
		<option value='$H'>H</option>
		<option value='$I'>I</option>
		<option value='$J'>J</option>
		<option value='$K'>K</option>
		<option value='$L'>L</option>
		<option value='$M'>M</option>
		<option value='$N'>N</option>
		<option value='$O'>O</option>
		<option value='$P'>P</option>
		<option value='$Q'>Q</option>
		<option value='$R'>R</option>
		<option value='$S'>S</option>
		<option value='$T'>T</option>
		<option value='$U'>U</option>
		<option value='$V'>V</option>
		<option value='$W'>W</option>
		<option value='$X'>X</option>
		<option value='$Y'>Y</option>
		<option value='$Z'>Z</option>
	  </select>
	</span>

	<span id="Sound_time">
	  <select id="popupSoundTimeType"  onchange="changeSoundPopupTimeType(this);">
		<option value='T'>exactly</option>
		<option value='+'>plus</option>
		<option value='^'>to the next nearest</option>
	  </select>
	  <span id="Sound_time_plus_value">
	    <input id="popupSoundTimePlusValue" style="width:40px" onclick="openIntegerPopup(this,1,59,'Minutes')">
	     minutes
	  </span>
	  <span id="Sound_time_nearest_value">
	    <input id="popupSoundTimeNearestValue" style="width:40px" onclick="openIntegerPopup(this,2,30,'Minutes')">
	     minutes
	  </span>
	</span>
	<span id="Sound_value">
	  <input id="popupSoundValue" style="width:50px" onclick="openIntegerPopup(this,0,255,'Sound file number')">
	</span>
    <br />
    <br />
    <span id="Sound_Repeat_type">
      and
	  <select id="popupSoundRepeatType"  onchange="changeSoundRepeatPopupType(this);">
		<option value='S'>play only once </option>
		<option value='I'>repeat this sound </option>
		<option value='F'>forever </option>
		<option value='V'>repeat this sound using the number in the variable </option>
	  </select>
	    <span id="Sound_Repeat_variable">
          <select id="popupSoundRepeatVariableNumber" style="width:75px">
            <option value='$A'>A</option>
            <option value='$B'>B</option>
            <option value='$C'>C</option>
            <option value='$D'>D</option>
            <option value='$E'>E</option>
            <option value='$F'>F</option>
            <option value='$G'>G</option>
            <option value='$H'>H</option>
            <option value='$I'>I</option>
            <option value='$J'>J</option>
            <option value='$K'>K</option>
            <option value='$L'>L</option>
            <option value='$M'>M</option>
            <option value='$N'>N</option>
            <option value='$O'>O</option>
            <option value='$P'>P</option>
            <option value='$Q'>Q</option>
            <option value='$R'>R</option>
            <option value='$S'>S</option>
            <option value='$T'>T</option>
            <option value='$U'>U</option>
            <option value='$V'>V</option>
            <option value='$W'>W</option>
            <option value='$X'>X</option>
            <option value='$Y'>Y</option>
            <option value='$Z'>Z</option>
          </select>
    	</span>
    	<span id="Sound_Repeat_value">
		  <input id="popupSoundRepeatValue" style="width:50px" type="number" onclick="openIntegerPopup(this,2,99,'Repeat sound...')"> times
		</span>
	</span>
    <br />
    <br />
    <button class="editSoundPopup_save" onclick="saveSoundPopup()">Save</button>
    <button class="editSoundPopup_cancel" onclick="closeSoundPopup()">Cancel</button>
  </div>
</div>

<div id="editImportFilePopup" class="modal" style="color:black">
  <div class="file-modal-content">
    <h3 id="popupFileTitle"></h3>
    <table>
      <tr>
        <td colspan=2>
          <input type="file" id="fileImportInput" style="color:black;"/>
        </td>
      </tr>
      <tr style="height:25px">
      </tr>
      <tr>
        <td>
          <button class="editFilePopup_save"   onclick="saveImportFilePopup()">Import</button>
        </td>
        <td>
          <button class="editFilePopup_cancel" onclick="closeImportFilePopup()">Cancel</button>
        </td>
      </tr>
    </table>
  </div>
</div>

<div id="editImportSoundPopup" class="modal" style="color:black">
  <div class="file-modal-content">
    <h3 id="popupSoundTitle"></h3>
    <form id="formAjax"  method="POST">
        <input type="file" id="fileAjax" name="fileAjax" /><br /><br />
        <br />
        <button class="editSoundPopup_cancel" onClick="ajax_upload_sound(); return false;">Upload</button>
    </form>
    <div id="upload_progress" class="br" style="height:20px; background:lightblue"></div>
    <br />
    <br />
    <button class="editSoundPopup_cancel" onclick="closeImportSoundPopup()">Close</button>
  </div>
</div>

<div id="editExportFilePopup" class="modal" style="color:black">
  <div class="file-modal-content">
    <h3 id="popupFileTitle"></h3>
    <form>
      <table>
        <tr>
          <td>
            <input type="file" id="fileExportInput" />
          </td>
          <td>
                <button class="editFilePopup_save" onclick="fileReader()">Open</button>
          </td>
        </tr>
      </table>
    </form>
    <br />
    <button class="editFilePopup_save"   onclick="saveExportFilePopup()">Save</button>
    <button class="editFilePopup_cancel" onclick="closeExportFilePopup()">Cancel</button>
  </div>
</div>

<div id="editMimicRangePopup" class="modal" style="color:black">

  <div class="mimic-range-modal-content" style="color:black">
    <h3 id="popupMimicRangeTitle"></h3>
      <form>
        <table style="color:black !important">
          <tr>
            <td>
              Start:
            </td>
            <td>
              <input id="popupMimicRangeFrom" type="range" name="popupMimicRangeFrom" min="0" max="99" value="0" oninput="this.form.popupMimicInputFrom.value=this.value" onblur="checkMimicBounds(this)" onchange="checkMimicBounds(this)" />
            </td>
            <td>
              <input id="popupMimicInputFrom" style="width:40px" type="number" min="0" max="99" value="0" oninput="this.form.popupMimicRangeFrom.value=this.value" onblur="checkMimicBounds(this)" onchange="checkMimicBounds(this)">
            </td>
          </tr>
          <tr>
            <td>
              End:
            </td>
            <td>
              <input id="popupMimicRangeTo" type="range" name="popupMimicRangeTo" min="0" max="99" value="0" oninput="this.form.popupMimicInputTo.value=this.value" onblur="checkMimicBounds(this)" onchange="checkMimicBounds(this)" oninput="this.form.popupMimicInput2.value=this.value" />
            </td>
            <td>
              <input id="popupMimicInputTo" style="width:40px" type="number" min="0" max="99" value="0" oninput="this.form.popupMimicRangeTo.value=this.value" onblur="checkMimicBounds(this)" onchange="checkMimicBounds(this)">
            </td>
          </tr>
        </table>
      </form>
    <br />
    <button class="editMimicRangePopup_save" onclick="saveMimicRangePopup()">Save</button>
    <button class="editMimicRangePopup_cancel" onclick="closeMimicRangePopup()">Cancel</button>
  </div>
</div>

<div id="editLightRangePopup" class="modal" style="color:black">
  <div class="light-range-modal-content" style="color:black">
    <h3 id="popupLightRangeTitle"></h3>
    <form>
      <table style="color:black !important">
        <tr>
          <td>
            Start:
          </td>
          <td>
            <input id="popupLightRangeFrom" type="range" name="popupLightRangeFrom" min="0" max="15" value="0" oninput="this.form.popupLightInputFrom.value=this.value" onblur="checkLightBounds(this)" onchange="checkLightBounds(this)" />
          </td>
          <td>
            <input id="popupLightInputFrom" style="width:40px" type="number" min="0" max="15" value="0" oninput="this.form.popupLightRangeFrom.value=this.value" onblur="checkLightBounds(this)" onchange="checkLightBounds(this)">
          </td>
        </tr>
        <tr>
          <td>
            End:
          </td>
          <td>
            <input id="popupLightRangeTo" type="range" name="popupLightRangeTo" min="0" max="15" value="0" oninput="this.form.popupLightInputTo.value=this.value" onblur="checkLightBounds(this)" onchange="checkLightBounds(this)" oninput="this.form.popupLightInput2.value=this.value" />
          </td>
          <td>
            <input id="popupLightInputTo" style="width:40px" type="number" min="0" max="15" value="0" oninput="this.form.popupLightRangeTo.value=this.value" onblur="checkLightBounds(this)" onchange="checkLightBounds(this)">
          </td>
        </tr>
        <tr>
        </tr>
      </table>
      <br />
      <span id="LightingUniqueCheckboxLabel">Treat as individual lights </span>
      <input type="checkbox" id="LightingGroupCheckbox">
    </form>
    <br />
    <button class="editLightRangePopup_save" onclick="saveLightRangePopup()">Save</button>
    <button class="editLightRangePopup_cancel" onclick="closeLightRangePopup()">Cancel</button>
  </div>
</div>

<div id="editColourPopup" class="modal" style="color:black">

  <div class="colour-modal-content">
    <form>
      <table style="color:black">
        <tr>
          <td colspan=2>
            <span id="ColourPopupCheckboxLabel">Use current colour?</span>
            <input type="checkbox" id="ColourPopupCheckbox" onchange="updateColourEditFields();">
          </td>
        </tr>
        <tr>
          <td>
            Red
          </td>
          <td>
            <input id="Red1" type="range" name="Red1" min="0" max="127" value="64" oninput="this.form.Red1Value.value=this.value;MimicUpdateSample1();" />
          </td>
          <td>
            <input id="Red1Value" type="number" name="Red1Value" min="0" max="127" value="64" oninput="this.form.Red1.value=this.value;MimicUpdateSample1();" />
          </td>
        </tr>
        <tr>
          <td>
            Green
          </td>
          <td>
            <input id="Green1" type="range" name="Green1" min="0" max="127" value="64" oninput="this.form.Green1Value.value=this.value;MimicUpdateSample1();" />
          </td>
          <td>
            <input id="Green1Value" type="number" name="Green1Value" min="0" max="127" value="64" oninput="this.form.Green1.value=this.value;MimicUpdateSample1();" />
          </td>
        </tr>
        <tr>
          <td>
            Blue
          </td>
          <td>
            <input id="Blue1" type="range" name="Blue1" min="0" max="127" value="64" oninput="this.form.Blue1Value.value=this.value;MimicUpdateSample1();" />
          </td>
          <td>
            <input id="Blue1Value" type="number" name="Blue1Value" min="0" max="127" value="64" oninput="this.form.Blue1.value=this.value;MimicUpdateSample1();" />
          </td>
        </tr>
      </table>
    </form>
    <div>
      <span id="sampleColour1" style="width: 50px; height: 50px; background-color: rgb(192,192,192); border-width: 2px; border-style: solid; float: right; margin-top: -83px;"></span>
    </div>

    <br />
    <button class="editColourPopup_save" onclick="saveColourPopup()">Save</button>
    <button class="editColourPopup_cancel" onclick="closeColourPopup()">Cancel</button>
  </div>
</div>

<div style="min-width:1024px">
	<div class="menu" id="menu" style="width:100%; background:#555555">
	  <a id="HomePageMenu" class="menuitem" href="HomePage" onclick="return pageSelected('HomePage');" class="active"><span style="font-family:icons;">&#xef47;</span> Home</a>
	  <a id="SettingsPageMenu" class="menuitem" style='float: right;' href="Settings" onclick="return pageSelected('SettingsPage');" ><span style="font-family:icons;">&#xefe1;</span> Settings</a>
	  <a id="UploadPageMenu" class="menuitem" href="UploadPage" onclick="return pageSelected('UploadPage');" ><span style="font-family:icons;">&#xefe2;</span> Configure</a>
	  <a id="ClockPageMenu" class="menuitem" href="ClockPage" onclick="return pageSelected('ClockPage');" ><span style="font-family:icons;">&#xf022;</span> Clock</a>
	  <a id="LabelsPageMenu" class="menuitem" href="LabelsPage" onclick="return pageSelected('LabelsPage');" ><span style="font-family:icons;">&#xef74;</span> Labels</a>
	  <a id="UsagePageMenu" class="menuitem" href="UsagePage" onclick="return pageSelected('UsagePage');" ><span style="font-family:icons;">&#xed13;</span> Usage</a>
	  <a id="DebugPageMenu" class="menuitem" href="DebugPage" onclick="return pageSelected('DebugPage');" ><span style="font-family:icons;">&#xed12;</span> Test</a>
	  <input id="onlineButton" style="background:orange;" value="Connecting" class="inputButton" onclick="reConnect();" type="button">
	  <canvas id="menuclockcanvas" class="menuclockcanvasclass" width="40" height="40">
	  </canvas>
	  <div class="menudigitalclock" id="menudigitalClock">
	    <div class="menudigitalclockoutline" id="menudigitalClockContent">
      </div>
	  </div>
	</div>
	<div>
    <div class="page" id="HomePage" style="display: block">
      <div class="innerPage">
        <div style="font-family:sans-serif; height: 100px;">
          <div id="SystemName" style="float:left;">
          </div>
        </div>
        <div>
          <hr>
          <table style="margin-left: 16px">
            <tbody id="AllSensors">
              <tr>
                <td style="padding-top: 20px; padding-bottom: 20px;">Name</td><td style="text-align: center;">Status</td><td style="width: 185px; text-align: center;">Hardware Version</td><td>Software Version</td><td></td><td></td>
              <tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="page" id="SettingsPage">
        <?php $a = exec("ifconfig eth0: |grep RUNNING|wc -l");
          if ($a == 1)
          {
        ?>
            <h3 style="font-family:sans-serif;">LAN Address : <?php echo exec("if [ $(ifconfig eth0 |grep RUNNING|wc -l) -eq 1 ]; then ifconfig eth0 |grep 'inet '|tr -s ' ' |cut -d ' ' -f 3; fi") ?></h3>
          <?php
            }
          ?>
      <div class="settingsmenu" id="settingsmenu" style="width:100%; background:#555555">
        <a id="Settings01Menu" class="menuitem" onclick="return pageSettingsSelected('Settings01');" class="active"><span style="font-family:icons;font-size: 17;">&#xef41;</span> Restart/Shutdown</a>
        <a id="Settings02Menu" class="menuitem" onclick="return pageSettingsSelected('Settings02');" ><span style="font-family:icons;font-size: 17;">&#xec96;</span> Reset WiFi</a>
        <a id="Settings03Menu" class="menuitem" onclick="return pageSettingsSelected('Settings03');" ><span style="font-family:icons;font-size: 17;">&#xef1f;</span> Upgrade</a>
        <a id="Settings04Menu" class="menuitem" onclick="return pageSettingsSelected('Settings04');" ><span style="font-family:icons;font-size: 17;">&#xee09;</span> Delete Saved Events</a>
      </div>
      <div class="innerPage">
        <div class="settingspage" id="Settings01">
          <h1>Restart</h1>
          Restart the base station.
          <br />
          <br />
          <form onsubmit="return confirmReboot(this);" action="reboot.php" method="post">
            <input type="submit" value="Restart"></td></tr>
          </form>
          <hr>
          <h1>Shutdown</h1>
          It is always best to shutdown the Base Station before powering it off.
          <br />
          <br />
          <form onsubmit="return confirmShutdown(this);" action="shutdown.php" method="post">
            <input type="submit" value="Shutdown"></td></tr>
          </form>
          <hr>
          <h1>Shutdown on Event</h1>
          Setting this field will cause the Base Station to shut down whenever it receives this event from anywhere in the system. It is then safe to power the system off.
          <br />
          <br />
          <form onsubmit="return confirmShutdownTrigger(this);" action="shutdown_trigger.php" method="post">
            Shutdown on this event : <input name="ShutdownEvent" id="ShutdownEvent" style="width:40px;" onclick="openLabelEventPopup('Event to trigger shutdown', this, -1);" value="Z99">
            <input type="submit" value="Update shutdown trigger event"></td></tr>
          </form>
        </div>
        <div class="settingspage" id="Settings02" style="display:none">
          <h1>Reset Wifi</h1>
          Changing these values will reset the WiFi and you will need to reconfigure all of your modules.<br />
          <strong>Please only do this if you are starting afresh.</strong><br />
          <span style="color: white; background:red;">Be aware that the layout name and password may NOT contain spaces! Any spaces wil be removed.</span><br />
          <br />
          <span style="color: white; background:red;">Be aware that the password must be at least 8 characters.</span><br />
          <br />
          <form onsubmit="return confirmWiFiChanges(this);" action="updatewifi.php" method="post">
            <table>
              <tr>
                <td>Layout Name</td>
                <td><input id="LayoutName" type="text" name="ssid" onkeyup="noSpaces(thisi, 0);" onchange="noSpaces(this, 0);"></td>
              </tr>
              <tr>
                <td>Password</td>
                <td><input id="LayoutPassword" type="text" name="password" onkeyup="noSpaces(this, 1);" onchange="noSpaces(this, 1);""></td>
              </tr>
              <tr>
                <td>WiFi Channel</td>
                <td><select id='channel' name="channel">
                  <option value='1'>1</option>
                  <option value='2'>2</option>
                  <option value='3'>3</option>
                  <option value='4'>4</option>
                  <option value='5'>5</option>
                  <option value='6'>6</option>
                  <option value='7'>7</option>
                  <option value='8'>8</option>
                  <option value='9'>9</option>
                  <option value='10'>10</option>
                  <option value='11'>11</option>
                  </select>
                </td>
              </tr>
              <tr>
                <td></td>
                <td><input disabled style="color:gray;" id="WiFiChangeSubmitButton" type="submit" value="Apply Changes"></td>
              </tr>
            </table>
          </form>
        </div>
        <div class="settingspage" id="Settings03" style="display:none">
          <h1>Update control module</h1>
          <span style="color: white; background:red;">Do not power down or reset your control module whilst the upgrade is in progress.</span><br />
          <br />
          <form action="updatesystem.php" method="post" enctype="multipart/form-data">
            Select upgrade file:
            <input type="file" name="fileToUpload" id="fileToUpload">
            <input type="submit" value="UPGRADE" name="submit">
          </form>
        </div>
        <div class="settingspage" id="Settings04" style="display:none">
          <h1>Delete all saved events</h1>
          <button onclick="if(confirm('Click OK to delete all saved events')) window.location.href='purge.php'"><span style="font-family:icons;font-size: 17;color: darkblue;\">&#xef1f</span> Delete all saved events</button>
          <br />
        </div>
      </div>
    </div>
    <div class="page" id="LabelsPage">
      <div class="labelsmenu" id="labelsmenu" style="width:100%; background:#555555">
        <a class="menuitem" onclick="api_call_list();" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
        <a class="menuitem" onclick="uploadLabelConfigFile();" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
        <a class="menuitem" onclick="openImportFilePopup(this, -1, 0, 'Import Labels from File');" ><span style="font-family:icons;font-size: 17;">&#xef7b;</span> Import</a>
        <a class="menuitem" onclick="exportLabelsFile()" ><span style="font-family:icons;font-size: 17;">&#xef05;</span> Save</a>
      </div>
      <div class="innerPage">
        <div class="labelspage" id="labelspage">
          <table id="LabelTable">
          </table>
        </div>
      </div>
    </div>
    <div class="page" id="UsagePage">
      <div class="innerPage">
        <div class="usagepage" id="usagepage">
          <table id="usageTable">
			<tr>
			  <td>
                <button class="config_button tooltip" id="editEventPopup_button_usage" onclick="openEventPopup('E', this);" value="SA00">A00 On
                </button>
			  </td>
			  <td>
			    <input type="button" id="searchUsageButton" onclick="sendSearchRequest(this);" value="Search">
			  </td>
			</tr>
          </table>
          <hr>
          <div id="usageTableDiv">
          </div>
        </div>
      </div>
    </div>
    <div class="page" id="ClockPage">
      <div class="innerPage">
        <form>
        <label id="analogl"><input id="analog" type="checkbox" onclick="analogClockSelected();" checked>Analog</label>
        <table>
          <tr style="width: 20em;">
          <td style="width: 20em;">
          </td>
          <td>
            <input id="Hour+Button" type="button" value="Hour +" style="width: 185px; height: 53px; font-size: 35px; background: #fffffe;; border-radius: 10px;" onclick="clockAdjust(this);">
            <input id="Minute+Button" type="button" value="Minute +" style="width: 185px; height: 53px; font-size: 35px; background: #fffffe;; border-radius: 10px;" onclick="clockAdjust(this);">
          </td>
          <td>
          </td>
          <tr>
          <td>
          </td>
          <td rowspan="4" style="width:450px;">
            <canvas id="clockcanvas" width="375" height="375" style="">
            </canvas>
            <div class="digitalClock" id="digitalClockContainer">
              <div class="digitalclockoutline" id="digitalClock">
              </div>
            </div>
          </td>
          </tr>
          <tr>
          <td>
          </td>
          </tr>
          <tr>
          <td>
          </td>
          </tr>
          <tr>
          <td>
          </td>
          </tr>
          <tr>
          <td>
          </td>
          <td>
            <input id="Hour-Button" type="button" value="Hour -" style="width: 185px; height: 53px; font-size: 35px; background: #fffffe;; border-radius: 10px;" onclick="clockAdjust(this);">
            <input id="Minute-Button" type="button" value="Minute -" style="width: 185px; height: 53px; font-size: 35px; background: #fffffe; border-radius: 10px;" onclick="clockAdjust(this);">
          </td>
          <td>
          </td>
          </tr>
          <tr>
          <td>
          </td>
          <td>
            <input id="RunPauseButton" type="button" value="RUN" style="height: 47px; width: 375px; background: red; color: white; font-size: 36px; border-radius: 10px;" onclick="runPause(true);">
          </td>
          <td>
          </td>
          </tr>
        </table>
        </form>
      </div>
    </div>
    <div class="page" id="UploadPage">
      <div class="innerPage">
        <label>
          Select Board :
          <select id="Sensors" onchange="displaySubpage(this);">
          <option value=-1>Select...</option>
          </select>
        </label>
        <div id="99" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
          <a class="menuitem" onclick="retrieveConfigFile('02');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
          <a class="menuitem" onclick="programSecondaryClockModule();" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
        </div>
          <table id="secondaryClockConfigPage" style = "display: none";>
          <tr>
          <td>
            Brightness:
          </td>
          <td>
                <input onclick="openIntegerPopup(this, 0, 15, 'Brightness...');"  style="width: 32px;" name="secondaryClockBrightness" value="8" id="secondaryClockBrightness" >
          </td>
          </tr>
        </table>
        </div>

        <div id="02" class="subpage" style="display:none;">
          Set brightness:&nbsp
          <input value="8" max="15" min="1" type="range" id="clockBrightness">
          <br />
          <br />
          <input value="Program" onclick="programClockModule('clockBrightness');" type="button">
        </div>

        <div id="10" class="subpage" style="display:none;">
          Set brightness:&nbsp
          <input value="8" max="15" min="1" type="range" id="largeBrightness">
          <br />
          <br />
          <input value="Program" onclick="programClockModule('largeBrightness');" type="button">
        </div>

        <div id="08" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
          <a class="menuitem" onclick="retrieveConfigFile('08');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
          <a class="menuitem" onclick="programMatrixClockModule();" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
        </div>
          <table id="matrixConfigPage" style="display:none;">
            <tr>
              <td>
              Brightness
            </td>
            <td>
                <input onclick="openIntegerPopup(this, 0, 15, 'Brightness...');"  style="width: 32px;" name="matrixBrightness" value="8" id="matrixBrightness" >
            </td>
          </tr>
          <tr>
            <td>
              Font Style
            </td>
            <td>
            <select id="matrixFont">
            <option value='0'>Round font</option>
            <option value='1'>Square font</option>
            </select>
          </td>
          </tr>
          <tr>
            <td>
              Scroll Text
            </td>
            <td>
            <input id="matrixScroll" type="checkbox">
          </td>
            </tr>
            <tr>
              <td>
                AM/PM indication
              </td>
            <td>
            <select id="matrixAMPMstyle">
            <option value='0'>No indication</option>
            <option value='1'>am/pm</option>
            <option value='2'>A/P</option>
            <option value='3'>AM/PM dot</option>
            <option value='4'>PM dot only</option>
            </select>
          </td>
          </tr>
          </table>
        </div>

      <div id="04" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
        <a class="menuitem" onclick="retrieveConfigFile('04');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
        <a class="menuitem" onclick="uploadConfigFile('04');" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
        <a class="menuitem" onclick="openImportFilePopup(this, '04', '', 'Import Configuration File');" ><span style="font-family:icons;font-size: 17;">&#xef7b;</span> Import</a>
        <a class="menuitem" onclick="exportConfigFile('04')" ><span style="font-family:icons;font-size: 17;">&#xef05;</span> Save</a>
              <a class="menuitem" style='float: right;' onclick="return centreAllServos();" ><span style="font-family:icons;">&#xefe1;</span> Centre All</a>
        </div>
        <br />
        <div id="ConfigArea04">
        <table id="ConfigTable04" style="text-align: center">
        </table>
        </div>
      </div>

      <div id="12" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
        <a class="menuitem" onclick="retrieveConfigFile('12');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
        <a class="menuitem" onclick="if (uploadConfigFile('12')){uploadInvertedLighting();}" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
        <a class="menuitem" onclick="openImportFilePopup(this, '12', '', 'Import Configuration File');" ><span style="font-family:icons;font-size: 17;">&#xef7b;</span> Import</a>
        <a class="menuitem" onclick="exportConfigFile('12')" ><span style="font-family:icons;font-size: 17;">&#xef05;</span> Save</a>
        </div>
        <br />
        <fieldset style="margin:8px; border: 1px solid; padding: 8px;">
        <legend>Invert Output</legend>
          <table>
            <tr>
              <td></td><td>0</td><td>1</td><td>2</td><td>3</td><td>4</td><td>5</td><td>6</td><td>7</td><td>8</td><td>9</td><td>10</td><td>11</td><td>12</td><td>13</td><td>14</td><td>15</td>
            </tr>
            <tr>
              <td>Invert?</td>
              <td><input id="Inverted0_12" type="checkbox"></td>
              <td><input id="Inverted1_12" type="checkbox"></td>
              <td><input id="Inverted2_12" type="checkbox"></td>
              <td><input id="Inverted3_12" type="checkbox"></td>
              <td><input id="Inverted4_12" type="checkbox"></td>
              <td><input id="Inverted5_12" type="checkbox"></td>
              <td><input id="Inverted6_12" type="checkbox"></td>
              <td><input id="Inverted7_12" type="checkbox"></td>
              <td><input id="Inverted8_12" type="checkbox"></td>
              <td><input id="Inverted9_12" type="checkbox"></td>
              <td><input id="Inverted10_12" type="checkbox"></td>
              <td><input id="Inverted11_12" type="checkbox"></td>
              <td><input id="Inverted12_12" type="checkbox"></td>
              <td><input id="Inverted13_12" type="checkbox"></td>
              <td><input id="Inverted14_12" type="checkbox"></td>
              <td><input id="Inverted15_12" type="checkbox"></td>
            </tr>
          </table>
        </fieldset>
        <br />
        <div id="ConfigArea12">
        <table id="ConfigTable12" style="text-align: center">
        </table>
        </div>
      </div>

      <div id="05" class="subpage" style="display: none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
          <a class="menuitem" onclick="retrieveConfigFile('05');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
          <a class="menuitem" onclick="uploadConfigFile('05');" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
          <a class="menuitem" onclick="openImportFilePopup(this, '05', '', 'Import Configuration File');" ><span style="font-family:icons;font-size: 17;">&#xef7b;</span> Import</a>
          <a class="menuitem" onclick="exportConfigFile('05')" ><span style="font-family:icons;font-size: 17;">&#xef05;</span> Save</a>
        </div>
        <br />
        <div id="ConfigArea05">
        <table id="ConfigTable05" style="text-align: center">
        </table>
        </div>
      </div>

      <div id="06" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
          <a class="menuitem" onclick="retrieveConfigFile('06');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
          <a class="menuitem" onclick="uploadConfigFile('06');" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
          <a class="menuitem" onclick="openImportFilePopup(this, '06', '', 'Import Configuration File');" ><span style="font-family:icons;font-size: 17;">&#xef7b;</span> Import</a>
          <a class="menuitem" onclick="exportConfigFile('06')" ><span style="font-family:icons;font-size: 17;">&#xef05;</span> Save</a>
        </div>
        <br />
        <div id="ConfigArea06">
        <table id="ConfigTable06" style="text-align: center">
        </table>
        </div>
      </div>

      <div id="07" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
          <a class="menuitem" onclick="retrieveConfigFile('07');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
          <a class="menuitem" onclick="uploadConfigFile('07');" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
          <a class="menuitem" onclick="openImportFilePopup(this, '07', '', 'Import Configuration File');" ><span style="font-family:icons;font-size: 17;">&#xef7b;</span> Import</a>
          <a class="menuitem" onclick="exportConfigFile('07')" ><span style="font-family:icons;font-size: 17;">&#xef05;</span> Save</a>
        </div>
        <br />
        <div id="ConfigArea07">
          <table id="ConfigTable07" style="text-align: center">
          </table>
        </div>
      </div>

      <div id="14" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
          <a class="menuitem" onclick="retrieveConfigFile('14');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
          <a class="menuitem" onclick="programDCCModule();" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
        </div>
        <br />
        <table>
          <tr>
            <td>
              Reverse Accessory Direction
            </td>
            <td>
              <input type="checkbox" id="DCCReversed">
            </td>
          </tr>
          <tr>
            <td>
              Offset Accessory by 4
            </td>
            <td>
              <input type="checkbox" id="OffsetBy4">
            </td>
          </tr>
          <tr height="20px">
          </tr>
<?php
  for ($i=0; $i<20; $i++)
  {
?>
          <tr>
            <td>
              Map DCC accessory <?php if ($i == 0) echo $i*100 + 1; else echo $i*100; echo "..";echo ($i+1)*100 - 1; ?> to bank
            </td>
            <td>
              <select id="DCCBank<?php echo $i ?>">
                <option value=0>None</option>
                <?php if ($i == 0)
                        $ZeroOrOne = "1";
                      else
                        $ZeroOrOne = "0";
                ?>
                <option value=A>A0<?php echo $ZeroOrOne ?> - A99</option>
                <option value=B>B0<?php echo $ZeroOrOne ?> - B99</option>
                <option value=C>C0<?php echo $ZeroOrOne ?> - C99</option>
                <option value=D>D0<?php echo $ZeroOrOne ?> - D99</option>
                <option value=E>E0<?php echo $ZeroOrOne ?> - E99</option>
                <option value=F>F0<?php echo $ZeroOrOne ?> - F99</option>
                <option value=G>G0<?php echo $ZeroOrOne ?> - G99</option>
                <option value=H>H0<?php echo $ZeroOrOne ?> - H99</option>
                <option value=I>I0<?php echo $ZeroOrOne ?> - I99</option>
                <option value=J>J0<?php echo $ZeroOrOne ?> - J99</option>
                <option value=K>K0<?php echo $ZeroOrOne ?> - K99</option>
                <option value=L>L0<?php echo $ZeroOrOne ?> - L99</option>
                <option value=M>M0<?php echo $ZeroOrOne ?> - M99</option>
                <option value=N>N0<?php echo $ZeroOrOne ?> - N99</option>
                <option value=O>O0<?php echo $ZeroOrOne ?> - O99</option>
                <option value=P>P0<?php echo $ZeroOrOne ?> - P99</option>
                <option value=Q>Q0<?php echo $ZeroOrOne ?> - Q99</option>
                <option value=R>R0<?php echo $ZeroOrOne ?> - R99</option>
                <option value=S>S0<?php echo $ZeroOrOne ?> - S99</option>
                <option value=T>T0<?php echo $ZeroOrOne ?> - T99</option>
                <option value=U>U0<?php echo $ZeroOrOne ?> - U99</option>
                <option value=V>V0<?php echo $ZeroOrOne ?> - V99</option>
                <option value=W>W0<?php echo $ZeroOrOne ?> - W99</option>
                <option value=X>X0<?php echo $ZeroOrOne ?> - X99</option>
                <option value=Y>Y0<?php echo $ZeroOrOne ?> - Y99</option>
                <option value=Z>Z0<?php echo $ZeroOrOne ?> - Z99</option>
              </select>
            </td>
          </tr>
<?php
  }
?>
        </table>
      </div>

      <div id="09" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
          <a class="menuitem" onclick="retrieveConfigFile('09');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
          <a class="menuitem" onclick="uploadConfigFile('09');" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
          <a class="menuitem" onclick="openImportFilePopup(this, '09', '', 'Import Configuration File');" ><span style="font-family:icons;font-size: 17;">&#xef7b;</span> Import</a>
          <a class="menuitem" onclick="exportConfigFile('09')" ><span style="font-family:icons;font-size: 17;">&#xef05;</span> Save</a>
        </div>
        <br />
        <fieldset style="margin:8px; border: 1px solid; padding: 8px;">
          <legend>Volume</legend>
          Volume - Channel 0 <input type="range" name="Volume0" id="Volume0" min="1" max="30" value="10" oninput="programSoundVolume('09')" /><br />
          Volume - Channel 1 <input type="range" name="Volume1" id="Volume1" min="1" max="30" value="10" oninput="programSoundVolume('09')" /><br />
        </fieldset>
        <div id="ConfigArea09">
          <table id="ConfigTable09" style="text-align: center">
          </table>
        </div>
      </div>

      <div id="16" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
        <a class="menuitem" onclick="retrieveConfigFile('16');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
        <a class="menuitem" onclick="uploadConfigFile('16');" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
        <a class="menuitem" onclick="openImportFilePopup(this, '16', '', 'Import Configuration File');" ><span style="font-family:icons;font-size: 17;">&#xef7b;</span> Import</a>
        <a class="menuitem" onclick="exportConfigFile('16')" ><span style="font-family:icons;font-size: 17;">&#xef05;</span> Save</a>
        <a class="menuitem" style="width:200px" onclick="openLocalSoundFilesPopup('16');" ><span style="font-family:icons;font-size: 17;">&#xecb7;</span> Manage Sounds</a>
        </div>
        <br />
        <fieldset style="margin:8px; border: 1px solid; padding: 8px;">
        <legend>Volume</legend>
          Channel 0 <input type="range" name="Volume0" id="Volume0_16" min="1" max="100" value="75" oninput="programSoundVolume('16')" /><br />
          Channel 1 <input type="range" name="Volume1" id="Volume1_16" min="1" max="100" value="75" oninput="programSoundVolume('16')" /><br />
          Channel 2 <input type="range" name="Volume2" id="Volume2_16" min="1" max="100" value="75" oninput="programSoundVolume('16')" /><br />
          Channel 3 <input type="range" name="Volume3" id="Volume3_16" min="1" max="100" value="75" oninput="programSoundVolume('16')" /><br />
          Channel 4 <input type="range" name="Volume4" id="Volume4_16" min="1" max="100" value="75" oninput="programSoundVolume('16')" /><br />
          Channel 5 <input type="range" name="Volume5" id="Volume5_16" min="1" max="100" value="75" oninput="programSoundVolume('16')" /><br />
        </fieldset>
        <div id="ConfigArea16">
          <table id="ConfigTable16" style="text-align: center">
          </table>
        </div>
      </div>

      <div id="03" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
        <a class="menuitem" onclick="retrieveConfigFile('03');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
        <a class="menuitem" onclick="uploadConfigFile('03');" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
        <a class="menuitem" onclick="openImportFilePopup(this, '03', '', 'Import Configuration File');" ><span style="font-family:icons;font-size: 17;">&#xef7b;</span> Import</a>
        <a class="menuitem" onclick="exportConfigFile('03')" ><span style="font-family:icons;font-size: 17;">&#xef05;</span> Save</a>
        </div>
        <br />
        <div id="ConfigArea03">
          <table id="ConfigTable03" style="text-align: center">
          </table>
        </div>
      </div>
      
      <div id="0" class="subpage" style="display:none;">
        <div class="menu" id="menu" style="width:100%; background:#555555">
        <a class="menuitem" onclick="retrieveConfigFile('01');" class="active"><span style="font-family:icons;font-size: 17;">&#xef08;</span> Download</a>
        <a class="menuitem" onclick="programPrimaryClockModule();" ><span style="font-family:icons;font-size: 17;">&#xf01c;</span> Upload</a>
        </div>
          <table id="primaryClockConfigPage" style="display: none">
          <tr>
          <td>
            Default start time
          </td>
          <td>
            <button class="config_button" id="primaryClockStartTime" onclick="openEventPopup('T', this);" value="0730">07:30</button>
          </td>
          </tr>
          <tr>
          <td>
            Display as 12 or 24 hour clock
          </td>
          <td>
            <select id="primaryClock1224">
            <option value=12>12</option>
            <option value=24 selected>24</option>
            </select>
          </td>
          </tr>
          <tr>
          <td>
            Ratio
          </td>
          <td>
            <select id="primaryClockRatio">
            <option value=1>1:1</option>
            <option value=2>1:2</option>
            <option value=3>1:3</option>
            <option value=4>1:4</option>
            <option value=5>1:5</option>
            <option value=6>1:6</option>
            <option value=10>1:10</option>
            <option value=12 selected>1:12</option>
            <option value=15>1:15</option>
            <option value=20>1:20</option>
            <option value=30>1:30</option>
            </select>
          </td>
          </tr>
          <tr>
            <td>
              Pause clock on this event
            </td>
            <td>
              <button class="config_button tooltip" style="width:70px;" id="primaryClockPauseEvent" onclick="openEventPopup('C', this);" value="SC00">C00 On</button>
            </td>
          </tr>
          <tr>
            <td>
              Start clock on this event
            </td>
            <td>
              <button class="config_button tooltip" style="width:70px;" id="primaryClockStartEvent" onclick="openEventPopup('C', this);" value="SC00">C00 On</button>
            </td>
          </tr>
          <tr>
            <td>
              Reset clock on this event
            </td>
            <td>
              <button class="config_button tooltip" style="width:70px;" id="primaryClockResetEvent" onclick="openEventPopup('C', this);" value="SC01">C01 On</button>
            </td>
          </tr>
          <tr>
            <td>
              Add an hour
            </td>
            <td>
              <button class="config_button tooltip" style="width:70px;" id="primaryClockH+Event" onclick="openEventPopup('C', this);" value="SC02">C02 On</button>
            </td>
          </tr>
          <tr>
            <td>
              Subtract an hour
            </td>
            <td>
              <button class="config_button tooltip" style="width:70px;" id="primaryClockH-Event" onclick="openEventPopup('C', this);" value="SC03">C03 On</button>
            </td>
          </tr>
          <tr>
            <td>
              Add a minute
            </td>
            <td>
              <button class="config_button tooltip" style="width:70px;" id="primaryClockM+Event" onclick="openEventPopup('C', this);" value="SC04">C04 On</button>
            </td>
          </tr>
          <tr>
            <td>
              Subtract a minute
            </td>
            <td>
              <button class="config_button tooltip" style="width:70px;" id="primaryClockM-Event" onclick="openEventPopup('C', this);" value="SC05">C05 On</button>
            </td>
          </tr>
        </table>
      </div>
    </div>
    </div>
    <div class="page" id = "DebugPage">
      <div class="debugpage">
        <div class="innerPage" style="float: left;width:343px; border: solid; margin-left: 10px; height: 500px;">
          <select id="Bank" onChange="updateSwitchLabels();">
            <option value=A>Bank A</option>
            <option value=B>Bank B</option>
            <option value=C>Bank C</option>
            <option value=D>Bank D</option>
            <option value=E>Bank E</option>
            <option value=F>Bank F</option>
            <option value=G>Bank G</option>
            <option value=H>Bank H</option>
            <option value=I>Bank I</option>
            <option value=J>Bank J</option>
            <option value=K>Bank K</option>
            <option value=L>Bank L</option>
            <option value=M>Bank M</option>
            <option value=N>Bank N</option>
            <option value=O>Bank O</option>
            <option value=P>Bank P</option>
            <option value=Q>Bank Q</option>
            <option value=R>Bank R</option>
            <option value=S>Bank S</option>
            <option value=T>Bank T</option>
            <option value=U>Bank U</option>
            <option value=V>Bank V</option>
            <option value=W>Bank W</option>
            <option value=X>Bank X</option>
            <option value=Y>Bank Y</option>
            <option value=Z>Bank Z</option>
          </select>
          <select id="BankOffset" onChange="updateSwitchLabels();">
            <option value=0>0..15</option>
            <option value=16>16..31</option>
            <option value=32>32..47</option>
            <option value=48>48..63</option>
            <option value=64>64..79</option>
            <option value=80>80..95</option>
          </select>
          <table id="switchtable">
          </table>
        </div>
        <div class="innerPage" style="float: left;width:500px; margin-left:10px; border: solid; height: 500px;">
          <label><input type="checkbox" id="ignoreTimeMessages">Don't show Clock Events</label>
          </br>
          <textarea id="DebugArea" rows="30" cols="60" style="resize: none"></textarea>
          <br />
          <div style="text-align: center; padding: 10px;">
            <input type="button" id="clearDebugAreaButton" onclick="clearDebugArea();" value="Clear Debug Event Display">
          </div>
        </div>
      </div>
    </div>
  </div>
	<div class="footer">
	  <hr>
	  <img src="images/modulus_logo.png" style="width: 48px; float: left; margin-right: 10px;">
		<h2 style="float: left;font-family:sans-serif;font-size: 18px;">MODULUS V1.10 &nbsp;&nbsp;&nbsp;&nbsp;&copy; 2022,2023,2024</h2>
		<div style="float:right; width:290px; display:flex">
		  <h2 style="font-family:sans-serif;font-size: 18px;">Proudly supported by </h2><img class="frontpage_logo" style="" src="images/sms_small.png">
		</div>
	</div>
</div>
</body>
