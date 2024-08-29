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
void flashID_LED()
{ 
int i;

  for (i=0;i<15;i++)
  {
    digitalWrite(LED_BUILTIN, HIGH);
    delay(300);
    digitalWrite(LED_BUILTIN, LOW);
    delay(100);
  }
}

void upgradeConfigFile(int majorVersion, int minorVersion)
{
// Do changes needed between old and new config files here
 
  File configFile = LittleFS.open("/module.conf", "w");
  if (!configFile)
  {
    DEBUG_println("file open failed");
  }
  else
  {
    // update the config file to relect the new version
    DEBUG_println ("Updating config file");
    configFile.println(modulename);
    configFile.println(ssid);
    configFile.println(password);
    configFile.println(boardType);
    configFile.println(MAJOR_VERSION);
    configFile.println(MINOR_VERSION);
    configFile.close();
  }
}

void publishMessage(char bank, char *number, char *payload, bool retain)
{
char PublishTopic[40];

  sprintf(PublishTopic, "/Messages/%c/%s", bank, number);
  if (OperatingMode == WIFI_COMMS)
    MQTTclient.publish(PublishTopic, payload, retain);
  else
  {
    Serial.print("[");
    Serial.print(PublishTopic);
    Serial.print("]");
    Serial.print("{");
    Serial.print(payload);
    Serial.print("}");
  }
}

void publishStringAsMessage(char *payload)
{
char PublishTopic[40];

  sprintf(PublishTopic, "/ConfigData/%s", modulename);
  if (OperatingMode == WIFI_COMMS)
    MQTTclient.publish(PublishTopic, payload, false);
  else
  {
    Serial.print("[");
    Serial.print(PublishTopic);
    Serial.print("]");
    Serial.print("{");
    Serial.print(payload);
    Serial.print("}");
  }
}

void publishUsageAsMessage(char *payload)
{
char PublishTopic[40];

  sprintf(PublishTopic, "/UsageData/%s", modulename);
  if (OperatingMode == WIFI_COMMS)
    MQTTclient.publish(PublishTopic, payload, false);
  else
  {
    Serial.print("[");
    Serial.print(PublishTopic);
    Serial.print("]");
    Serial.print("{");
    Serial.print(payload);
    Serial.print("}");
  }
}

void SendConfigurationFile()
{
unsigned char LineCount;
unsigned char CharCount;
char ReadByte;

  // rewind to the start of the data file
  Datafile.seek(0, SeekSet);

  // send start of retrieve config message
  sprintf(Line, "<S,%02d", boardType);
  publishStringAsMessage(Line);
  
  LineCount = 0;
  CharCount = 3;
  sprintf(Line, "%02d,", boardType);

  while (Datafile.available())
  {
    ReadByte = Datafile.read();
DEBUG_print(ReadByte);
    if (ReadByte == 10 || ReadByte == 13)
    {
      if (CharCount != 3)
      {
        // terminate the line
        Line[CharCount] = 0;
        LineCount++;
        CharCount = 0;
      }
    }
    else
      if (CharCount == 3 && ReadByte == '/')
        while (ReadByte != 10 && ReadByte != -1)
          ReadByte = Datafile.read();
      else
      {
        Line[CharCount++] = ReadByte;
       if (CharCount >= MAXLINELENGTH)
        CharCount = MAXLINELENGTH;
      }
      
    if (LineCount > 0)
    {
      DEBUG_println(Line);
      // send config line from file
      publishStringAsMessage(Line);
      LineCount = 0;
      CharCount = 3;
      sprintf(Line, "%02d,", boardType);
    }
  }

  // send EOM to show we've finished
  Line[0] = EOM;
  Line[1] = 0;
  publishStringAsMessage(Line);

  return;
}

void ProcessSearchLine(int startChar, int numChars, char *MatchString)
{
unsigned int  LineCount;
unsigned int  TotalLineCount;
unsigned char CharCount;
unsigned char InitialCharCount;
char ReadByte;

  DEBUG_println("Searching for usage!");

  // rewind to the start of the data file
  Datafile.seek(0, SeekSet);

  // send start of retrieve config message
  LineCount = 0;
  TotalLineCount = 0;

  if (boardType == 3)
    sprintf(Line, "<Q,%s,%02d,0,", modulename, boardType);
  else
    sprintf(Line, "<Q,%s,%02d,", modulename, boardType);
  InitialCharCount = strlen(Line);
  CharCount = InitialCharCount;
  
  while (Datafile.available())
  {
    ReadByte = Datafile.read();
    
    if (ReadByte == 10 || ReadByte == 13)
    {
      if (CharCount != InitialCharCount)
      {
        // terminate the line
 //       Line[CharCount++] = '>';
        Line[CharCount] = 0;
        LineCount++;
        TotalLineCount++;
        CharCount = 0;
      }
    }
    else
      if (CharCount == InitialCharCount && ReadByte == '/')
        while (ReadByte != 10 && ReadByte != -1)
          ReadByte = Datafile.read();
      else
      {
        Line[CharCount++] = ReadByte;
        if (CharCount >= MAXLINELENGTH)
          CharCount = MAXLINELENGTH;
      }
      
    if (LineCount > 0)
    {
      // send config line from file
      if (strncmp(&MatchString[startChar], &Line[InitialCharCount], numChars) == 0)
      {
        publishUsageAsMessage(Line);
      }
      LineCount = 0;
      // CharCount = InitialCharCount;
      if (boardType == 3)
        sprintf(Line, "<Q,%s,%02d,%d,", modulename, boardType, TotalLineCount);
      else
        sprintf(Line, "<Q,%s,%02d,", modulename, boardType);
      InitialCharCount = strlen(Line);
      CharCount = InitialCharCount;
    }
  }

  return;
}

int MatchTimeTrigger(bool ApplyRandom, uint8_t CurrentHour, char Trig[], char Line[], char MatchEvent[])
{
uint8_t  LowHour;
uint8_t  HighHour;
uint8_t  MatchCount;
uint8_t  i;
uint8_t  retval;
bool     continueMatching;

  retval = 0;
  continueMatching = true;

  if ((Line[0] >= '0' && Line[0] <= '9') || Line[0] == '#' || Line[0] == '?')
  {
//    DEBUG_print("Current Time      is ");DEBUG_print(Trig[0]);DEBUG_print(Trig[1]);DEBUG_print(":");DEBUG_print(Trig[2]);DEBUG_println(Trig[3]);

    for (i=0; i<4 && continueMatching; i++)
    {
      if (isdigit(Line[i]))
      {
        if (Line[i] == Trig[i])
        {
          retval+=5;
        }
        else
        {
          retval = 0;
          continueMatching = false;
        }
      }
      else
      {
        if (Line[i] == '#' && isdigit(Trig[i]) && continueMatching)
        {
          retval+=1;
        }
        else
        {
          if (ApplyRandom)
          {
            if (Line[i] == '?' && (random(3) == 1) && continueMatching)
            {
              retval+=1;
            }
          }
          else
          {
            if (Line[i] == '?' && continueMatching)
            {
              retval+=1;
            }
          }
        }
      }
    }
  }
  else
  {
    if (Line[0] >= 'a' && Line[0] <= 'x')
    {
      LowHour = Line[0] - 'a';
      HighHour = Line[1] - 'a';
  
      if (HighHour < LowHour)
      {
        if (!(CurrentHour >= HighHour && CurrentHour <= LowHour))
        {
          retval = 2;
        }
        else
        {
          retval = 0;
          continueMatching = false;
        }
      }
      else
      {
        if (CurrentHour >= LowHour && CurrentHour <= HighHour)
        {
          retval = 2;
        }
        else
        {
          retval = 0;
          continueMatching = false;
        }
      }

      for (i=2; i<4 && continueMatching; i++)
      {
        if (Line[i] == Trig[i])
        {
          retval+=2;
        }
        else
        {
          if (Line[i] == '#' && isdigit(Trig[i]))
          {
            retval+=1;
          }
          else
          {
            if (ApplyRandom)
            {
              if (Line[i] == '?' && (random(3) == 1))
              {
                retval+=1;
              }
              else
              {
                retval = 0;
                continueMatching = false;
              }
            }
            else
            {
              if (Line[i] == '?')
              {
                retval+=1;
              }
            }
          }
        }
      }
    }
  }

  if (retval > 0)
  {
    DEBUG_print("Match value = ");DEBUG_println(retval);
    for (i=0; i<4; i++)
    {
      MatchEvent[i] = Line[i];
      DEBUG_print(MatchEvent[i]);
    }
    DEBUG_println();
  }

  return retval;
}

void ProcessFile(char *Trigger, bool TimeMessage, void (*ProcessLineFunction)(char *a, bool b, bool c, char *d, uint8_t e, uint8_t f))
{
uint8_t  i;
uint8_t  j;
bool     Matched;
uint8_t  BestMatch;
uint8_t  thisMatch;
char     TimeMatch[4];
char     BestTimeMatch[4];
uint8_t  CurrentHour;
uint8_t  CurrentMinute;
uint8_t  bytesRead;

  // rewind to start of file
  Datafile.seek(0, SeekSet);

//  LineCount = 0;
//  CharCount = 0;

  CurrentHour = ((Trigger[0] - '0') * 10) + (Trigger[1] - '0');
  CurrentMinute = ((Trigger[2] - '0') * 10) + (Trigger[3] - '0');

  Matched = false;
  BestMatch = 0;

  for(i=0; i<NumberOfConfigLines && !Matched; i++)
  {
    if (TimeMessage)
    {
      if (ConfigTriggers[i][0] != 'S' && ConfigTriggers[i][0] != 'U' && ConfigTriggers[i][0] != 'I')
      {
/*        
        thisMatch = MatchTimeTrigger(CurrentHour, Trigger, ConfigTriggers[i], TimeMatch);
        if (thisMatch > 0)
        {
          if (thisMatch > BestMatch)
          {
            BestMatch = thisMatch;
            for (j=0;j<4;j++)
            {
              BestTimeMatch[j] = TimeMatch[j];
            }
          }
          Matched = true;
        }
      }
*/
        if (MatchTimeTrigger(false, CurrentHour, Trigger, ConfigTriggers[i], TimeMatch) > 0)
        {
          Matched = true;
        }
      }
    }
    else
    {
      if (strncmp(Trigger, ConfigTriggers[i], 4) == 0)
      {
        Matched = true;
      }
    }
  }

  if (Matched)
  {
    while (Datafile.available()) 
    {    
      bytesRead = Datafile.readBytesUntil(10, Line, 255);
      Line[bytesRead] = 0;
      Line[4] = 0;

      if (TimeMessage)
      {
//        if (strncmp(BestTimeMatch, Line, 4) == 0)
        if (MatchTimeTrigger(true, CurrentHour, Trigger, Line, TimeMatch) > 0)
        {
          DEBUG_print("Found it! ");DEBUG_println(Line);
          (*ProcessLineFunction)(&Line[5], TimeMessage, false, Line, CurrentHour, CurrentMinute);
        }
      }
      else
      {
        if (strncmp(Trigger, Line, 4) == 0)
        {
          (*ProcessLineFunction)(&Line[5], TimeMessage, false, BestTimeMatch, CurrentHour, CurrentMinute);
        }
      }
    }
  }
  return;  
}

void AddNextQueueChar(unsigned char charToAdd)
{
  // DEBUG_print(charToAdd);
  ReceiveQueue[ReceiveQueueHead++] = charToAdd;
  if (ReceiveQueueHead >= RECEIVEQUEUESIZE)
    ReceiveQueueHead = 0;
}

void OverwriteConfigFile(char *filename)
{
char    ThisChar;
bool    eof;
int16_t ReturnCode;
bool    IgnoreMessage;

  // close file
  Datafile.close();
  delay(10);

  // open file for writing i.e. create a new file
  Datafile = LittleFS.open(filename, "w");
  DEBUG_println("Opening config to write");

  // write out the whole buffer to the file
  DEBUG_println(InputStr);
  Datafile.println(&InputStr[3]);

  // close the file to flush the written data out
  Datafile.close();
  
//  delay(100);

  // and then reopen it....
  Datafile = LittleFS.open(filename, "r");

//  ReadConfigFileTriggers(Datafile);

  return;
}

void AddToConfigFile(char *filename)
{
char    ThisChar;
bool    eof;
int16_t ReturnCode;
bool    IgnoreMessage;

  // close file
  Datafile.close();
  delay(10);

  // open file for appending
  Datafile = LittleFS.open(filename, "a");
  DEBUG_println("Opening config to append");

  // write out the whole buffer to the file
  DEBUG_println(&InputStr[3]);
  Datafile.println(&InputStr[3]);

  // close the file to flush the written data out
  Datafile.close();
  
  // delay(100);

  // and then reopen it....
  Datafile = LittleFS.open(filename, "r");

  return;
}

void ReadConfigFileTriggers()
{
unsigned char LineCount;
unsigned char CharCount;
unsigned char InitialCharCount;
char ReadByte;

  NumberOfConfigLines = 0;

  // rewind to the start of the data file
  Datafile.seek(0, SeekSet);

  LineCount = 0;
  CharCount = 0;

//  Serial.println();
  while (Datafile.available())
  {
    ReadByte = Datafile.read();

    Serial.print(ReadByte,HEX);Serial.print(":");

    if (ReadByte == 10 || ReadByte == 13)
    {
      if (CharCount > 0)
      {
        // ConfigTriggers[LineCount][CharCount] = 0;
        LineCount++;
        CharCount = 0;
      }
    }
    else
    {
      if (CharCount < 4)
      {
        ConfigTriggers[LineCount][CharCount] = ReadByte;
      }
      CharCount++;
    }
  }

  if (CharCount > 4)
  {
    // ConfigTriggers[LineCount][CharCount] = 0;
    LineCount++;
  }
  
  NumberOfConfigLines = LineCount;

  return;
}

bool connectToWiFi()
{
int       retryCount;
IPAddress ip;
long      connectTime;
long      nextAttempt;
int       i;

  DEBUG_print("Connecting to ");DEBUG_println(ssid);

  ip[0] = 0;

  retryCount = 0;

  WiFi.mode(WIFI_STA);
  WiFi.begin((char *)ssid, password);
  delay(1000);

  while (ip[0] == 0)
  {
    while (WiFi.status() != WL_CONNECTED && !ResetPressed)
    {
      connectTime = millis() + 30000L;
      nextAttempt = millis() + 10000L;
      while (WiFi.status() != WL_CONNECTED && millis() < connectTime && !ResetPressed)
      {
        if (digitalRead(RESET_CONFIG) == 0)
        {
          ResetPressed = true;
        }
//        if (WiFi.status() == WL_DISCONNECTED && millis() > nextAttempt)
//        {
//          DEBUG_print("x");delay(200);
//          WiFi.begin((char *)ssid, password);
//          nextAttempt = millis() + 10000L;
//        }
        delay(100);
      }

      DEBUG_print(".");DEBUG_print(WiFi.status());

      if (WiFi.status() != WL_CONNECTED)
      {
        ESP.restart();
      }
    }

    if (WiFi.status() == WL_CONNECTED  && !ResetPressed)
    {
      // now get our IP address...
      ip = WiFi.localIP();
      sprintf(ipAddress, "%d.%d.%d.%d", ip[0], ip[1], ip[2], ip[3]);
      DEBUG_print("My ip is ");DEBUG_println(ipAddress);
      delay(250);
      httpUpdater.setup(&webServer);
      webServer.begin();
    }
  }

  return ResetPressed;
}

bool connectToMQTT()
{
int retryCount;
char topic[80];
char tempString[80];

// return false;

  while (!MQTTclient.connected() && !ResetPressed) 
  {
    if (digitalRead(RESET_CONFIG) == 0)
    {
      ResetPressed = true;
    }
    else
    {
      MQTTclient.setClient(client);
      MQTTclient.setServer("192.168.0.1",1883);

      DEBUG_print("Attempting MQTT connection...");
      // Attempt to connect
      // boolean connect (clientID, willTopic, willQoS, willRetain, willMessage)
      sprintf(topic, "/Devices/%s", modulename);
      if (MQTTclient.connect(modulename, topic, 1, true, "offline"))
      {
//        DEBUG_println("connected");
        // Once connected, publish an announcement...
  //      MQTTclient.publish("outTopic", "hello world");
        // ... and resubscribe
        MQTTclient.subscribe("/Messages/#");
        sprintf (tempString, "/Modules/%s", modulename);
        MQTTclient.subscribe(tempString);
      }
      else
      {
        DEBUG_print("failed, rc=");
        DEBUG_print(MQTTclient.state());
        DEBUG_println(" try again in 1 seconds");
        // Wait 1 seconds before retrying
        delay(1000);
      }
    }
  }

  if (!ResetPressed)
  {
  //char MajorVersion[4];
  //char MinorVersion[4];
  
    delay(10);
    sprintf(topic, "/Devices/%s", modulename);
//    sprintf(MajorVersion, "%d", MAJOR_VERSION);
//    sprintf(MinorVersion, "%02d", MINOR_VERSION);
    sprintf(tempString, "%02i,%s,%i,%02i,%s", boardType, HARDWARE_REVISION, atoi(MAJOR_VERSION), atoi(MINOR_VERSION), ipAddress);
    DEBUG_println(tempString);
    MQTTclient.publish(topic, tempString, true);
  }

  return ResetPressed;
}

/*void connectToMessagingService()
{
int retryCount;
char tempString[80];

  DEBUG_println("Attempting to make connection to server");
  retryCount = 0;
  while (!client.connect("192.168.0.1", messagingPort)) 
  {
    retryCount++;
    if (!client.connected())
    {
      delay(100);
      DEBUG_print(".");
    }
    if (retryCount > 10)
      ESP.restart();
  }

  synSent = false;
  delay(500);
  sprintf(tempString, "R%s,%02i,%i,%02i,%s>", modulename, boardType, MAJOR_VERSION, MINOR_VERSION, ipAddress);
  DEBUG_println(tempString);
  client.print(tempString);

}
*/

bool reconnect()
{
IPAddress ip;
long      connectTime;
int       i;

  DEBUG_print("Connecting to ");DEBUG_println(ssid);

  ip[0] = 0;

  digitalWrite(LED_BUILTIN, LOW);
  while (WiFi.status() != WL_CONNECTED && !ResetPressed)
  {
    ResetPressed = connectToWiFi();
    delay(250);
  }
  DEBUG_println("Wifi ok");

  // now get our IP address...
  ip = WiFi.localIP();
  sprintf(ipAddress, "%d.%d.%d.%d", ip[0], ip[1], ip[2], ip[3]);
  DEBUG_print("My ip is ");DEBUG_println(ipAddress);
  delay(250);
  httpUpdater.setup(&webServer);
  webServer.begin();

  if (!client.connected() && !ResetPressed)
  {
    #ifdef USE_MQTT
      ResetPressed = connectToMQTT();
    #else
      connectToMessagingService();
    #endif
    
//    lastServerMessage = millis();
  }
  digitalWrite(LED_BUILTIN, HIGH);

  return ResetPressed;
}

/*
 void processMessagingService()
{
char tempChar;

  if (millis() - lastServerMessage > 4000L && !synSent)
  {
    client.print(SYN);
    Serial.println("Sending a SYN, cos we haven't sent anything for a while....");    
  }

  if (millis() - lastServerMessage > 10000L)
  {
    client.stop();
    // we haven't had anything from the server for a while, so lets try and reconnect...
    reconnect();
  }

  while (client.available())
  {
    digitalWrite(LED_BUILTIN, LOW);
    lastServerMessage = millis();
    tempChar = client.read();
    if (tempChar == SYN)
    {
      Serial.println("Got a syn, so sending an ack...");
      client.print(ACK);
    }
    else
    {
      if (tempChar != ACK)
      {
        AddNextQueueChar(tempChar);
      }
    }
  }
}
*/

int ReceiveQueueSize()
{
  if (ReceiveQueueHead != ReceiveQueueTail)
  {
    if (ReceiveQueueTail > ReceiveQueueHead)
      return (RECEIVEQUEUESIZE - 1 - ReceiveQueueTail) + ReceiveQueueHead;
    else
      return ReceiveQueueHead - ReceiveQueueTail;
  }
  else
  {
    digitalWrite(LED_BUILTIN, HIGH);
    return 0;
  }
}

unsigned char GetNextQueueCharacter()
{
unsigned char retVal;

  retVal = ReceiveQueue[ReceiveQueueTail++];
  if (ReceiveQueueTail >= RECEIVEQUEUESIZE)
    ReceiveQueueTail = 0;

//  if (ReceiveQueueHead == ReceiveQueueTail)
//    digitalWrite(LED_BUILTIN, HIGH);

  return retVal;
}

unsigned char PeekNextQueueMessageType()
{
unsigned char retVal;
int i;

  i = ReceiveQueueTail + 1;
  if (i >= RECEIVEQUEUESIZE)
    i = 0;
  retVal = ReceiveQueue[ReceiveQueueTail];
  return retVal;
}

void callback(char* topic, byte* payload, unsigned int length) 
{
  digitalWrite(LED_BUILTIN, LOW);
//  DEBUG_print("Message arrived [");
//  DEBUG_print(topic);
//  DEBUG_print("] ");
  for (int i = 0; i < length; i++)
  {
//    DEBUG_print((char)payload[i]);
    AddNextQueueChar(payload[i]);
  }
//  DEBUG_println();
}

void handleRoot() 
{
  webServer.send_P(200, "text/html", INDEX_page);
}

void handleUpdate() 
{
File configFile;

  if( webServer.hasArg("ssid") && webServer.hasArg("module") && webServer.hasArg("password")) 
  {
    if (webServer.arg("ssid") != NULL && webServer.arg("module") != NULL && webServer.arg("password") != NULL) 
    {
      configFile = LittleFS.open("/module.conf", "w");
     
      if (!configFile)
      {
        DEBUG_println("file open failed");
//        webServer.send_P(200, "text/html", ERROR_page);
      }
      else
      {
        configFile.println(webServer.arg("module"));
        DEBUG_println(webServer.arg("module"));
        configFile.println(webServer.arg("ssid"));
        DEBUG_println(webServer.arg("ssid"));
        configFile.println(webServer.arg("password"));
        DEBUG_println(webServer.arg("password"));
        configFile.println(webServer.arg("boardtype"));
        DEBUG_println(webServer.arg("boardtype"));
        configFile.println(MAJOR_VERSION);
        DEBUG_println(MAJOR_VERSION);
        configFile.println(MINOR_VERSION);
        DEBUG_println(MINOR_VERSION);
        configFile.close();
        webServer.send_P(200, "text/html", UPDATE_page);
        delay(1000);

        ESP.restart();
      }
    }
    else
    {
      webServer.send(400, "text/plain", "400: Invalid Request");         // The request is invalid, so send HTTP status 400
    }
  }
  else
  {
      webServer.send(400, "text/plain", "400: Invalid Request");         // The request is invalid, so send HTTP status 400
  }
}

void macToStr(char *result, const uint8_t* mac)
{
char tempChar;
char *ptr;

  ptr = result;

  for (int i = 3; i < 6; i++)
  {
    tempChar = (mac[i]) & 0x0F;
    if (tempChar < 10)
      tempChar += '0';
    else
      tempChar = tempChar - 10 + 'A';
    *ptr = tempChar;
    ptr++;
    tempChar = ((mac[i]) & 0xF0) >> 4;
    if (tempChar < 10)
      tempChar += '0';
    else
      tempChar = tempChar - 10 + 'A';
    *ptr = tempChar;
    ptr++;
  }
  *ptr = 0;
}

void setupAccessPoint()
{
unsigned char mac[6];

  WiFi.macAddress(mac);
  macToStr(uniqueID, mac);
  
  DEBUG_println(uniqueID);

  // Wifi.off
  DEBUG_print("Configuring access point...");
  WiFi.mode(WIFI_AP);
  delay(100);
  WiFi.mode(WIFI_AP);
  delay(100);

  WiFi.softAPConfig(local_IP, local_IP, subnet);

//  DEBUG_print("Setting soft-AP ... ");
  
  strcpy (ssid,"modulus-");
  WiFi.macAddress(mac);
  macToStr(&ssid[8], mac);
//  DEBUG_print("SSID = ");
//  DEBUG_println(ssid);
  
  // add last 4 digits of the mac address
  WiFi.softAP(ssid);

//  dnsServer.start(DNS_PORT, "*", local_IP);

  // reply to all requests with same HTML
  webServer.onNotFound(handleRoot);

  webServer.on("/", handleRoot);
  webServer.on("/update.html", handleUpdate);
  webServer.begin();
  DEBUG_println("HTTP server started");
}

bool connectToSerial()
{
uint8_t i;
uint8_t HashCount;
long    SerialTimeout;
char    tempString[80];
bool    serialConnected;

  serialConnected = false;
  return false;

/*
  HashCount = 0;
  while (Serial.available())
  {
    Serial.read();
  }

  for (i=0; i<5 && HashCount < 10; i++)
  {
    // try serial comms first to see if this is available...
    HashCount = 0;
    SerialTimeout = millis() + 1000;
    Serial.print("(!)");
    while (millis() < SerialTimeout && HashCount < 10)
    {
      if (Serial.available())
        if (Serial.read() == '#')
          HashCount++;
    }
  }

//  Serial.println(HashCount);

  if (HashCount >= 10)
  {
    OperatingMode = SERIAL_COMMS;
    serialConnected = true;

    if (MQTTclient.connected())
    {
      MQTTclient.disconnect();
    }

    delay(500);

    if (WiFi.status() == WL_CONNECTED)
      WiFi.disconnect();

    // let comms channel know about ourselves....
    sprintf(tempString, "<CONFIG:%s,%02i,%s,%i,%02i>", modulename, boardType, HARDWARE_REVISION, atoi(MAJOR_VERSION), atoi(MINOR_VERSION));
    Serial.print(tempString);

    while(Serial.available())
      Serial.read();

    serialConnected = true;
  }

  return serialConnected;
*/
}

bool initialiseComms() 
{
  if (OperatingMode == SETUP)
  {
    setupAccessPoint();
  }
  else
  {
    if (!connectToSerial())
    {
      // so try wifi connection instead
      MQTTclient.setCallback(callback);

      ResetPressed = reconnect();
      OperatingMode = WIFI_COMMS;
    }
  }

  return ResetPressed;
}
