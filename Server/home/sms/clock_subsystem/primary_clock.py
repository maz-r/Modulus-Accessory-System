# Required Notice: Copyright (C) 2024 Martin Randall - All Rights Reserved
#
# You may use, distribute and modify this code under the
# terms of the PolyForm Noncommercial 1.0.0 license.
#
# You should have received a copy of the PolyForm Noncommercial 1.0.0 license with
# this file. 
# If not, please visit: <https://polyformproject.org/licenses/noncommercial/1.0.0>
#
#
import paho.mqtt.client as mqtt  #import the client
import time

hours = 8
minutes = 0
seconds = 0
APmarker = 'P'

mqtt_client_id = "Internal Clock"
boardType = "0"
mqtt_connected = False
registered = False

def saveDefaultValues():
  global ratio
  global APmarker
  global defaultstarthours
  global defaultstartminutes
  global clock_start_string
  global clock_pause_string
  global clock_reset_string
  global clock_hous_plus
  global clock_hour_minus
  global clock_minute_plus
  global clock_minute_minus

  f = open("primary_clock_config.txt", "w")
  hoursstr = "0" + str(defaultstarthours)
  minutesstr = "0" + str(defaultstartminutes)
  hoursstr = hoursstr[-2:]
  minutesstr = minutesstr[-2:]
  f.write(f"{hoursstr}:{minutesstr}\n")
  f.write(f"{ratio}\n")
  f.write(f"{APmarker}\n")
  f.write(f"{clock_pause_string}\n")
  f.write(f"{clock_start_string}\n")
  f.write(f"{clock_reset_string}\n")
  f.write(f"{clock_hour_plus}\n")
  f.write(f"{clock_hour_minus}\n")
  f.write(f"{clock_minute_plus}\n")
  f.write(f"{clock_minute_minus}\n")

def registerCentrally():
  global registered

  topic="/Devices/"+mqtt_client_id
  tempString="0,,1,00,localhost"
  client.publish(topic, tempString, retain=True)
  registered = True
 
def on_connect(client, userdata, flags, rc):

  global mqtt_connected
  global JMRI_message_present
  global registered

  if rc == 0:
    print("Connected!")
    mqtt_connected = True
    registerCentrally()
    registered = True
  else:
    print ("There was error ",rc)

def publishStringAsMessage(payload):
  client.publish("/ConfigData/Internal Clock", payload, retain=False)

def publishStringAsUsage(payload):
  client.publish("/UsageData/Internal Clock", payload, retain=False)

def on_message(client, userdata, message):

  global registered
  global running
  global hours
  global minutes
  global seconds
  global ratio
  global APmarker
  global defaultstarthours
  global defaultstartminutes
  global clock_start_string
  global clock_pause_string
  global clock_reset_string
  global clock_hour_plus
  global clock_hour_minus
  global clock_minute_plus
  global clock_minute_minus

  InputStr = ""
  InputStr = str(message.payload.decode("utf-8"))
  payload = message.topic.split("/")

  if message.topic.find("Modules") != -1:
    messageValues = InputStr.split(",")

    if (messageValues[0] == "<WD>"):
      Line = "<S,0"
      publishStringAsMessage(Line)
      starthours = "0"+str(defaultstarthours)
      starthours = starthours[-2:]
      startminutes = "0"+str(defaultstartminutes)
      startminutes = startminutes[-2:]

      Line = (f"{boardType},{APmarker},{ratio},{starthours},{startminutes},{clock_pause_string},{clock_start_string},{clock_reset_string},{clock_hour_plus},{clock_hour_minus},{clock_minute_plus},{clock_minute_minus}")
      publishStringAsMessage(Line)
        
      Line = ">"
      publishStringAsMessage(Line)

    if (messageValues[0] == "<WC"):
      if (messageValues[1] == "12"):
        APmarker = 'A'
      else:
        APmarker = 'P' 
      ratio = int(messageValues[2])
      defaultstarthours  = int(messageValues[3])
      defaultstartminutes= int(messageValues[4])
      clock_pause_string = messageValues[5] 
      clock_start_string = messageValues[6]
      clock_reset_string = messageValues[7]
      clock_hour_plus    = messageValues[8]
      clock_hour_minus   = messageValues[9]
      clock_minute_plus  = messageValues[10]
      clock_minute_minus = messageValues[11][:-1]
      saveDefaultValues()

    if(messageValues[0] == "<PAUSE>"):
      running = False

    if(messageValues[0] == "<RUN>"):
      running = True

    if(messageValues[0] == "<H+>"):
      hours += 1
      seconds = 0
      if hours > 23:
        hours = 0

    if(messageValues[0] == "<H->"):
      hours -= 1
      seconds = 0
      if hours < 0:
        hours = 23

    if(messageValues[0] == "<M+>"):
      minutes += 1
      seconds = 0
      if minutes > 59:
        minutes = 0

    if(messageValues[0] == "<M->"):
      minutes -= 1
      seconds = 0
      if minutes < 0:
        minutes = 59

    hoursstr = "0" + str(hours)
    minutesstr = "0" + str(minutes)
    secondsstr = "0" + str(seconds)
    hoursstr = hoursstr[-2:]
    minutesstr = minutesstr[-2:]
    secondsstr = secondsstr[-2:]
    if running:
      payload = f"<R{hoursstr}{minutesstr}{secondsstr}{APmarker}>"
    else:
      payload = f"<P{hoursstr}{minutesstr}{secondsstr}{APmarker}>"

    topic="/Messages"
    client.publish(topic, payload, retain=False)

  if message.topic.find("Messages") != -1:
    if (InputStr[:3] == "<CQ"):
      compareStr = InputStr[3:7]
      if (compareStr == clock_hour_plus):
        Line = "<Q,Clock,0,"+compareStr+",Increase Hours>"
        publishStringAsUsage(Line)
      if (compareStr == clock_hour_minus):
        Line = "<Q,Clock,0,"+compareStr+"Decrease ,Hours>"
        publishStringAsUsage(Line)
      if (compareStr == clock_minute_plus):
        Line = "<Q,Clock,0,"+compareStr+",Increase Minutes>"
        publishStringAsUsage(Line)
      if (compareStr == clock_minute_minus):
        Line = "<Q,Clock,0,"+compareStr+",Decrease Minutes>"
        publishStringAsUsage(Line)
      if (compareStr == clock_start_string):
        Line = "<Q,Clock,0,"+compareStr+",Start Clock>"
        publishStringAsUsage(Line)
      if (compareStr == clock_pause_string):
        Line = "<Q,Clock,0,"+compareStr+",Pause Clock>"
        publishStringAsUsage(Line)
      if (compareStr == clock_reset_string):
        Line = "<Q,Clock,0,"+compareStr+",Reset Clock>"
        publishStringAsUsage(Line)

    if len(InputStr) >= 2:
      if (InputStr[1]=='S' or InputStr[1]=='U'):
        if (InputStr[1:5] == clock_hour_plus and not running):
          hours += 1
          if hours > 23:
            hours = 0
        
        if (InputStr[1:5] == clock_hour_minus and not running):
          hours -= 1
          if hours < 0:
            hours = 23

        if (InputStr[1:5] == clock_minute_plus and not running):
          minutes += 1
          if minutes > 59:
            minutes = 0

        if (InputStr[1:5] == clock_minute_minus and not running):
          minutes -= 1
          if minutes < 0:
            minutes = 59

        if (InputStr[1:5] == clock_start_string and not running):
          running = True 
        else:
          if (InputStr[1:5] == clock_pause_string and running):
            running = False 

        if (InputStr[1:5] == clock_reset_string and not running):
          hours = defaultstarthours
          minutes = defaultstartminutes
          seconds = 0

      if (InputStr[1]=='R' or InputStr[1]=='P'):
          temphours = int(InputStr[2:4])
          tempminutes = int(InputStr[4:6])
          if temphours != hours or tempminutes != minutes:
              hours = temphours
              minutes = tempminutes

def deregisterCentrally():
  global registered

  topic="/Devices/"+mqtt_client_id
  tempString="offline"
  client.publish(topic, tempString, retain=True)
  registered = False

############

running = True
broker_address="127.0.0.1"
client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION1, mqtt_client_id) #create new instance
client.on_connect=on_connect
client.on_message=on_message #attach function to callback
topic="/Devices/"+mqtt_client_id
client.will_set(topic, payload="offline", qos=0, retain=True)
client.connect(broker_address) #connect to broker
client.subscribe("/Messages/#")
client.subscribe("/Modules/Internal Clock/#")
client.loop_start() #start the loop

lines = []
with open('primary_clock_config.txt') as f:
    lines = f.readlines()

hours = int(lines[0][:2].rstrip())
defaultstarthours = hours
minutes = int(lines[0][3:].rstrip())
defaultstartminutes = minutes
ratio = int(lines[1].rstrip())
APmarker = lines[2].rstrip()
clock_pause_string = lines[3].rstrip()
clock_start_string = lines[4].rstrip()
clock_reset_string = lines[5].rstrip()
clock_hour_plus    = lines[6].rstrip()
clock_hour_minus   = lines[7].rstrip()
clock_minute_plus  = lines[8].rstrip()
clock_minute_minus = lines[9].rstrip()

while not mqtt_connected:
  print ("Waiting...")
  time.sleep(1)

#client.loop_forever()
while True:
  time.sleep(1)
  if running:
    seconds += ratio
    if seconds > 59:
        seconds = 0
        minutes += 1
        if minutes > 59:
            minutes = 0
            hours += 1
            if hours > 23:
                hours = 0
    hoursstr = "0" + str(hours)
    minutesstr = "0" + str(minutes)
    secondsstr = "0" + str(seconds)
    hoursstr = hoursstr[-2:]
    minutesstr = minutesstr[-2:]
    secondsstr = secondsstr[-2:]
    payload = f"<R{hoursstr}{minutesstr}{secondsstr}{APmarker}>"
  else:
    hoursstr = "0" + str(hours)
    minutesstr = "0" + str(minutes)
    secondsstr = "0" + str(seconds)
    hoursstr = hoursstr[-2:]
    minutesstr = minutesstr[-2:]
    secondsstr = secondsstr[-2:]
    payload = f"<P{hoursstr}{minutesstr}{secondsstr}{APmarker}>"

  topic="/Messages"
  client.publish(topic, payload, retain=False)

