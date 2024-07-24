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
import paho.mqtt.client as mqtt #import the client1
import pygame
from pygame import AUDIO_ALLOW_ANY_CHANGE
import time
import glob
from datetime import datetime
from datetime import timedelta
import random
import pyudev

card_present = False
card_has_been_present = False
card_never_present = True
soundInitialised = False

mqtt_connected = False

MAX_CHANNELS = 6
MAX_QUEUE_SIZE = 40
Volume = [75] * MAX_CHANNELS
LastTime = 0
ThisTime = 0
mqtt_client_id = "CentralSoundModule"
boardType = "16"

SongQueueHead = [0 for i in range(MAX_CHANNELS)]
SongQueueTail = [0 for i in range(MAX_CHANNELS)]
SongQueue = [["" for i in range(MAX_QUEUE_SIZE)] for j in range(MAX_CHANNELS)]
currentChannel = 0
Variables = [-1 for i in range(26)]

LastTime = 0
ThisTime = 0
start_time = datetime.now()

def on_connect(client, userdata, flags, rc):
  
  global mqtt_connected

  if rc == 0:
    mqtt_connected = True
    client.subscribe("/Messages/#")
    tempString="/Modules/"+mqtt_client_id
    client.subscribe(tempString)

def log_event(action, device):
  global card_present
  if "add" in action:
    if "card1" in str(device):
      card_present = True
  else:
    if "remove" in action:
      if "card1" in str(device):
        card_present = False

def millis():
  dt = datetime.now() - start_time
  ms = (dt.days * 24 * 60 * 60 + dt.seconds) * 1000 + dt.microseconds / 1000.0
  return ms

def queueIncrement(x):
  x += 1
  if x >= MAX_QUEUE_SIZE:
    x = 0
  return int(x)

def SendConfigurationFile():
  global content_list

  payload = "<S," + boardType
  Topic = "/ConfigData/" + mqtt_client_id

  client.publish(Topic, payload)

  for Line in content_list:
    payload = boardType + "," + Line
    client.publish(Topic, payload)

  payload = ">"
  client.publish(Topic, payload)

  payload = "<V," + boardType
  Topic = "/ConfigData/" + mqtt_client_id

  for Vols in range(6):
    payload = payload + "," + Volume[Vols]
  payload = payload + ">"

  client.publish(Topic, payload)

def readConfigurationFile():
  global content_list
  try:
    content_list.clear()
  except:
    print("Initiating content list")
  my_file = open("/SoundSystem/Configuration/config.txt", "r")
  content_list = my_file.read().splitlines()
  my_file.close()

def readVolumeFile():
  global Volume
  my_file = open("/SoundSystem/Configuration/volume.txt", "r")
  volume_list = my_file.read().splitlines()
  my_file.close()

  for i in range(6):
    Volume[i] = volume_list[i]
  if card_present:
    for i in range(6):
      pygame.mixer.Channel(i).set_volume((float)(Volume[i])/100.0)

def OverwriteConfigFile(configString):
  configString = configString.replace(">", "\n")
  my_file = open("/SoundSystem/Configuration/config.txt", "w")
  if len(configString) > 1:
    my_file.write(configString)
  my_file.close()
  readConfigurationFile()

def AddToConfigFile(configString):
  configString = configString.replace(">", "\n")
  my_file = open("/SoundSystem/Configuration/config.txt", "a")
  my_file.write(configString)
  my_file.close()
  readConfigurationFile()

def calcQueueLength(channel):
  if SongQueueHead[channel] >= SongQueueTail[channel]:
    length = SongQueueHead[channel] - SongQueueTail[channel]
  else:
    length = (40 + SongQueueTail[channel]) - SongQueueHead[channel]

  return length

def shortestQueue():
  lowQueue = 999
  for i in range(6):
    thisLength =  calcQueueLength(i)
    if thisLength < lowQueue:
      newChannel = i
      lowQueue = thisLength
  return newChannel

def processLine(Args):
  QueueMode = Args[1][0]

  ArgCount = len(Args)

  if QueueMode == '=':
    if Args[2][0] == '$':
      if ord(Args[2][1]) - ord('A') >=0 and ord(Args[2][1]) - ord('A') <= 25:
        Variables[ord(Args[2][1]) - ord('A')] = Args[3]
  else:
    boardNumber = int(Args[2])

    if boardNumber == 99:
      boardNumber = shortestQueue()

    if QueueMode == 'N' or QueueMode == 'I':
      for q in range(MAX_QUEUE_SIZE):
        SongQueue[boardNumber][q] = ""
        
      SongQueueHead[boardNumber] = 0
      SongQueueTail[boardNumber] = 0

    if QueueMode == 'I':
      # stop playback
      if soundInitialised:
        pygame.mixer.Channel(boardNumber).stop()

    LargestArg = 3
    for i in range(3, ArgCount):
      if Args[i] != "":
        LargestArg = i

    ArgCount = LargestArg
    if QueueMode == 'R':
      RandomPick = random.randint(3,ArgCount)
      for i in range(3, ArgCount+1):
        if i != RandomPick:
          Args[i] = ""
      QueueMode = 'Q'
  
  if QueueMode == 'N' or QueueMode == 'Q' or QueueMode == 'I':
    for i in range(3, ArgCount+1):
      if Args[i] != "":
        if '$' in Args[i]:
          Pieces = Args[i].split(":")
          for j in range(len(Pieces)):
            if '$' in Pieces[j]:
              if Variables[ord(Pieces[j][1]) - ord('A')] != -1:
                Pieces[j] = Variables[ord(Pieces[j][1]) - ord('A')]
              else:
                Pieces[j] = "0"
          if len(Pieces) > 1:
            Args[i] = Pieces[0] + ":" + Pieces[1]
          else:
            Args[i] = Pieces[0]

        if Args[i][0] != 'T' and Args[i][0] != '+' and Args[i][0] != '^':
          SongQueue[boardNumber][SongQueueHead[boardNumber]] = Args[i]
          LastSongQueueHead = SongQueueHead[boardNumber]
          SongQueueHead[boardNumber] = queueIncrement(SongQueueHead[boardNumber])
        else:
          hours = int(LastTime // 60)
          minutes = int(LastTime % 60)
  
          if Args[i][0] == '+':
            tempMinutes = int(Args[i][1:])
            minutes += int(tempMinutes)
  
          if Args[i][0] == '^':
            tempMinutes = int(Args[i][1:])
            minutes = int(((minutes // tempMinutes)+1)*tempMinutes)
  
          if minutes >= 60:
            hours += int(minutes/60)
            minutes = int(minutes % 60)
            if hours >= 24:
              hours = int(hours % 24)

          if hours <= 20:
            if hours == 0:
              SongQueue[boardNumber][SongQueueHead[boardNumber]] = "22"
            else:
              if hours < 10:
                SongQueue[boardNumber][SongQueueHead[boardNumber]] = "21"
                SongQueueHead[boardNumber] = queueIncrement(SongQueueHead[boardNumber])
                SongQueue[boardNumber][SongQueueHead[boardNumber]] = hours
              else:
                SongQueue[boardNumber][SongQueueHead[boardNumber]] = hours
          else:
            SongQueue[boardNumber][SongQueueHead[boardNumber]] = "20"
            SongQueueHead[boardNumber] = queueIncrement(SongQueueHead[boardNumber])
            hours = int(hours - 20)
            SongQueue[boardNumber][SongQueueHead[boardNumber]] = hours
          SongQueueHead[boardNumber] = queueIncrement(SongQueueHead[boardNumber])
  
          if minutes <=20:
            if minutes < 10:
              if minutes == 0:
                SongQueue[boardNumber][SongQueueHead[boardNumber]] = "23"
                SongQueueHead[boardNumber] = queueIncrement(SongQueueHead[boardNumber])
              else:
                SongQueue[boardNumber][SongQueueHead[boardNumber]] = "21"
                SongQueueHead[boardNumber] = queueIncrement(SongQueueHead[boardNumber])
                
                SongQueue[boardNumber][SongQueueHead[boardNumber]] = minutes
                SongQueueHead[boardNumber] = queueIncrement(SongQueueHead[boardNumber])
            else:
              SongQueue[boardNumber][SongQueueHead[boardNumber]] = minutes
              SongQueueHead[boardNumber] = queueIncrement(SongQueueHead[boardNumber])
          else:
            minutes_tens = minutes // 10
            minutes = (minutes % 10)
            minutes_tens = minutes_tens * 10
            SongQueue[boardNumber][SongQueueHead[boardNumber]] = minutes_tens
            SongQueueHead[boardNumber] = queueIncrement(SongQueueHead[boardNumber])
  
            if minutes > 0:
              SongQueue[boardNumber][SongQueueHead[boardNumber]] = minutes
              LastSongQueueHead = SongQueueHead[boardNumber]
              SongQueueHead[boardNumber] = queueIncrement(SongQueueHead[boardNumber])

def MatchTimeTrigger(Match, CurrentHour, TimeRange, Trigger, NumRandom):

  retval = False;
  NumRandom = 0;

  TimeRange[0] = ord(Match[0]) - ord('0')
  if (Match[0] >= '0' and Match[0] <= '9') and (TimeRange[0] >=3 and TimeRange[0] < 6):
    TimeRange[1] = ord(Match[1]) - ord('0')
    TimeRange[2] = ord(Match[2]) - ord('0')
    TimeRange[3] = ord(Match[3]) - ord('0')

    LowHour = TimeRange[0] * 10
    LowHour += TimeRange[1]
    LowHour = LowHour - 30
    HighHour = TimeRange[2] * 10
    HighHour += TimeRange[3]
    HighHour = HighHour - 30

    if HighHour < LowHour:
      if  not (CurrentHour >= HighHour and CurrentHour < LowHour):
          retval = True
    else:
      if CurrentHour >= LowHour and CurrentHour < HighHour:
          retval = True
  else:
    if Trigger[0].isdigit or (Trigger[0] =='#' or Trigger[0] =='?'):
      MatchCount = 0
      
      for i in range(4):
        if Match[i] == Trigger[i] or (Match[i] == '#' and Trigger[i].isdigit()) or (Match[i] == '?' and (random.randint(0,4) == 1)):
          MatchCount+=1
        else:
          MatchCount = 0

      if MatchCount == 4:
        retval = True
  return retval

def SoundProcessFile(trigger, TimeMessage):
  global content_list

  if TimeMessage:
    CurrentHour = ((ord(trigger[0]) - ord('0')) * 10) + (ord(trigger[1]) - ord('0'))

    TimeRange = [0 for i in range(4)]

    NumRandom = 0

    for match in content_list:
      args = match.split(",")
      if MatchTimeTrigger(args[0], CurrentHour, TimeRange, trigger, NumRandom):
        processLine(args);
      
  else:
    for match in content_list:
      args = match.split(",")
      if trigger == args[0]:
        if card_present:
          processLine(args)

def ProcessSearchLine(trigger):
  global content_list

  for match in content_list:
    args = match.split(",")
    if trigger == args[0]:
      payload = "<Q," + mqtt_client_id + ","+ boardType
      for nextArg in args:
        payload = payload + "," + nextArg  
      Topic = "/UsageData/" + mqtt_client_id

      client.publish(Topic, payload)

def on_message(client, userdata, message):

  global LastTime
  
  InputStr = ""
  InputStr = str(message.payload.decode("utf-8"))
  if len(InputStr) >= 2:
    if InputStr[1] == 'R' or InputStr[1] == 'P':
      ThisTime =  int(InputStr[2]) * 600
      ThisTime += int(InputStr[3]) * 60
      ThisTime += int(InputStr[4]) * 10
      ThisTime += int(InputStr[5])
      if LastTime != ThisTime and InputStr[1] == 'R':
        LastTime = ThisTime
        SoundProcessFile(InputStr[2:6], True)

    if (InputStr[1] == 'W' and InputStr[2] == 'S'):
      InputStr = InputStr.replace(">", "")
      VolArgs = InputStr[3:].split(",")
      for i in range(6):
        Volume[i] = VolArgs[i]

      if card_present:
        for i in range(6):
          pygame.mixer.Channel(i).set_volume((float)(Volume[i])/100.0)

      my_file = open("/SoundSystem/Configuration/volume.txt", "w")
      my_file.write(Volume[0]+'\n')
      my_file.write(Volume[1]+'\n')
      my_file.write(Volume[2]+'\n')
      my_file.write(Volume[3]+'\n')
      my_file.write(Volume[4]+'\n')
      my_file.write(Volume[5]+'\n')
      my_file.close()

    if (InputStr[1] == 'C' and InputStr[2] == 'T'):
        InputStr = InputStr.replace(">", "")
        args = InputStr[7:].split(",")
        if card_present:
          processLine(args)

    if (InputStr[1] == 'C' and InputStr[2] == 'Q'):
        ProcessSearchLine(InputStr[3:7])

    if (InputStr[1]=='W' and InputStr[2]=='C'):
        OverwriteConfigFile(InputStr[3:])

    if (InputStr[1]=='W' and InputStr[2]=='+'):
        AddToConfigFile(InputStr[3:])
 
    if (InputStr[1]=='W' and InputStr[2]=='D'):
      SendConfigurationFile()

    if (InputStr[1]=='S' or InputStr[1]=='U'):
        SoundProcessFile(InputStr[1:5], False)
  else:
    if InputStr[0] == '>':
      readConfigurationFile()

def playSong(channel, filename):
  filename = str(filename)
  args = filename.split(":")
  args[0] = "000" + args[0]
  args[0] = args[0][-3:]
  partialFilename = "/SoundSystem/Sounds/" + args[0] + "*.*"
  filelist = glob.glob(partialFilename) 
  if len(filelist) >= 1:
    if len(args) > 1:
      numloops = int(args[1]) - 1
      pygame.mixer.Channel(channel).play(pygame.mixer.Sound(filelist[0]), loops=numloops)
    else:
      pygame.mixer.Channel(channel).play(pygame.mixer.Sound(filelist[0]))

def stopSoundSystem():

  global soundInitialised

  try:
    pygame.mixer.quit()
  except:
    print("Can't close mixer")
  soundInitialised = False

def startSoundSystem():

  global soundInitialised

  try:
    pygame.mixer.init(allowedchanges=AUDIO_ALLOW_ANY_CHANGE, buffer=2048)
    pygame.mixer.Channel(0)
    pygame.mixer.Channel(1)
    pygame.mixer.Channel(2)
    pygame.mixer.Channel(3)
    pygame.mixer.Channel(4)
    pygame.mixer.Channel(5)

    pygame.mixer.Channel(0).set_volume(1.0)
    pygame.mixer.Channel(1).set_volume(1.0)
    pygame.mixer.Channel(2).set_volume(1.0)
    pygame.mixer.Channel(3).set_volume(1.0)
    pygame.mixer.Channel(4).set_volume(1.0)
    pygame.mixer.Channel(5).set_volume(1.0)
  
    currentChannel = 0

    soundInitialised = True
  except:
    print("No card")
    soundInitialised = False
    card_present = False

def registerCentrally():
  print("registerCentrally")
  topic="/Devices/"+mqtt_client_id
  tempString="16,,1,00,localhost"
  client.publish(topic, tempString, retain=True)
  client.will_set(topic, payload="Offline", qos=0, retain=True)
  
def deregisterCentrally():
  print("deregisterCentrally")
  topic="/Devices/"+mqtt_client_id
  tempString="offline"
  client.publish(topic, tempString, retain=True)

############

readConfigurationFile()
readVolumeFile()

context = pyudev.Context()

for device in context.list_devices(SUBSYSTEM='sound'):
  if "card1" in str(device):
    card_present = True

monitor = pyudev.Monitor.from_netlink(context)
monitor.filter_by('sound')

time.sleep(0.5)

observer = pyudev.MonitorObserver(monitor, log_event)
observer.start()

broker_address="127.0.0.1"
client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION1, mqtt_client_id) #create new instance
client.on_message=on_message #attach function to callback
client.on_connect=on_connect
client.connect(broker_address) #connect to broker
client.loop_start() #start the loop
time.sleep(1)

while not mqtt_connected:
  time.sleep(1)

while (True):
  if not card_present:
    if card_has_been_present:
      stopSoundSystem()
      card_has_been_present = False
      if card_never_present:
        deregisterCentrally()
  else:
    if not card_has_been_present:
      registerCentrally()
      startSoundSystem()
      readVolumeFile()
      card_has_been_present = True
      card_never_present = True
      SoundProcessFile("INIT", False)
    try:
      if soundInitialised:
        if pygame.mixer.Channel(currentChannel).get_busy() == False:
          if SongQueueHead[currentChannel] != SongQueueTail[currentChannel]:
            playSong(currentChannel, SongQueue[currentChannel][SongQueueTail[currentChannel]])
            SongQueueTail[currentChannel] = queueIncrement(SongQueueTail[currentChannel])

        currentChannel += 1
        if currentChannel >= MAX_CHANNELS:
          currentChannel = 0
    except:
      time.sleep(0.1)

  time.sleep(0.1) # wait
