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
#define _DCC_CONTROL

//DCC Configuration items
#define MS1               185
#define DCC_PIN           5
#define DCC_QUEUE_SIZE    64

volatile byte           gInterruptTime[DCC_QUEUE_SIZE];
volatile unsigned long  gInterruptMicros;
volatile unsigned long  DCCms;
volatile unsigned int   DCCqueueHead;
volatile unsigned int   DCCqueueTail;
int                     bitCount;
volatile unsigned int   preAmbleCount;
unsigned long           RcvPacket;
unsigned int            RcvByte[5];
unsigned int            lastAdd;
unsigned int            lastGoodAdd;
unsigned int            lastDirection;
unsigned long           lastGoodTimeout;
unsigned long           lastDCCSend;
unsigned int            lastGoodDirection;
char                    DCCBank[20];
bool                    DivergeRoutes;
char                    OffsetBy4;
int                     sendPointer;
char                    payload[20];

void ICACHE_RAM_ATTR DCC_Interrupt()
{
    DCCms = micros();
    if ((DCCms - gInterruptMicros) < MS1)
    {
      gInterruptTime[DCCqueueHead] = 1;
    }
    else
    {
      gInterruptTime[DCCqueueHead] = 0;
    }
    DCCqueueHead = (DCCqueueHead + 1) % DCC_QUEUE_SIZE;
    gInterruptMicros = DCCms;
}

void StartDCCInterrupt()
{
    gInterruptTime[0] = gInterruptTime[1] = 0;
    gInterruptMicros = micros();
    
    attachInterrupt(digitalPinToInterrupt(DCC_PIN), DCC_Interrupt, RISING);
}

bool DCCReadStartupConfig()
{
char    temporaryConfigString[40];
uint8_t i;
  
  File f = LittleFS.open("/DCC.conf", "r");
  if (!f)
  {
      DEBUG_println("file open failed for DCC.conf");
      for (i=0; i<20; i++)
        DCCBank[i] = (char)('A' + i);
      DivergeRoutes = true;
      OffsetBy4 = 'Y';
  }
  else
  {
    DEBUG_println("DCC config file is present");
    
    String _temp = f.readStringUntil('\n');
    _temp.toCharArray(temporaryConfigString, 20);
    DEBUG_println(temporaryConfigString);
    if (temporaryConfigString[0] == 'R')
      DivergeRoutes = true;
    else
      DivergeRoutes = false;

    _temp = f.readStringUntil('\n');
    _temp.toCharArray(temporaryConfigString, 20);
    DEBUG_println(temporaryConfigString);
    OffsetBy4 = temporaryConfigString[0];

    _temp = f.readStringUntil('\n');
    _temp.toCharArray(temporaryConfigString, 20);
    DEBUG_println(temporaryConfigString);
    for (i=0; i<20; i++)
    {
      DCCBank[i] = temporaryConfigString[i];
    }
    
    f.close();
  }

  return true;
}

void saveDCCDefaultFile()
{
char    tempstring[20];
uint8_t i;

  File f = LittleFS.open("/DCC.conf", "w");
  if (!f)
  {
      DEBUG_println("failed to open DCC.conf for writing");
  }
  else
  {
    if (DivergeRoutes)
      f.println("R");
    else
      f.println("N");
      
    f.println(OffsetBy4);
    
    for (i=0; i<20; i++)
      f.print(DCCBank[i]);
      
    f.println("");
    f.close();
  }
}

void DCCSetup() 
{
  pinMode(DCC_PIN, INPUT_PULLUP);
  lastAdd = 9999;
  lastDirection = 9;
  lastGoodAdd = 9998;
  lastGoodDirection = 8;
  lastDCCSend = 0L;
  sendPointer = 0;
  DCCqueueHead = 0;
  DCCqueueTail = 0;
  bitCount = 0;
  DCCReadStartupConfig();
  StartDCCInterrupt();
}

void DCCSerial()
{
char    DCCpayload[20];
char    debug_string[80];
char    TempChar;
uint8_t i;

  if(ReceiveQueueSize() > 0)
  {
    TempChar = GetNextQueueCharacter();

    if (TempChar == SOM)
    {
      InCount = 0;
      InsideMessage = true;
    }

    InputStr[InCount] = (char)TempChar;
    InCount = (InCount + 1) % MAX_MESSAGE_LENGTH;

    if (TempChar == EOM)
    {
      InsideMessage = false;

      InputStr[InCount] = 0;

      if (InputStr[1]=='W' && InputStr[2]=='C')
      {        
        DEBUG_println(InputStr);
        if (InputStr[3] == 'R')
          DivergeRoutes = true;
        else
          DivergeRoutes = false;
        
        OffsetBy4 = InputStr[4];

        for (i=0; i<20; i++)
          DCCBank[i] = InputStr[5+i];

        DEBUG_println("Overwrite the config file");
        saveDCCDefaultFile();
      }

      if (InputStr[1]=='I' && InputStr[2]=='D')
      {
        flashID_LED();
      }

      if (InputStr[1]=='W' && InputStr[2]=='D')
      {
        sprintf(Line, "<S,%02d", boardType);
        publishStringAsMessage(Line);
        
        if (DivergeRoutes)
          sprintf(Line, "%02d,R,%c", boardType, OffsetBy4);
        else
          sprintf(Line, "%02d,N,%c", boardType, OffsetBy4);
          
        for (i=0; i<20; i++)
        {
          sprintf(Line, "%s,%c", Line, DCCBank[i]);
        }
        publishStringAsMessage(Line);
        
        // send EOM to show we've finished
        Line[0] = EOM;
        Line[1] = 0;
        publishStringAsMessage(Line);
      }      

      if (InputStr[1] == 'U')
      {
        int val = ((InputStr[2] - 'A') * 100) + ((InputStr[3] - '0') * 10) + (InputStr[4] - '0');
        sprintf (DCCpayload, "<q %d>\n", val);
        Serial.print(DCCpayload);
        sprintf (debug_string,"Sending %s", DCCpayload);
        publishUsageAsMessage(debug_string);
      }
      
      if (InputStr[1] == 'S')
      {
        int val = ((InputStr[2] - 'A') * 100) + ((InputStr[3] - '0') * 10) + (InputStr[4] - '0');
        sprintf (DCCpayload, "<Q %d>\n", val);
        Serial.print(DCCpayload);
        sprintf (debug_string,"Sending %s", DCCpayload);
        publishUsageAsMessage(debug_string);
      }
    }
  }
}

void DCCLoop() 
{
char Banknumber[5];
char Status;
char MessageBank;
char tempChar;
char debug_string[80];

  if (millis() - lastGoodTimeout > 500L)
  {
    lastGoodAdd = 9999;
    lastGoodDirection = 9;
  }

  while (Serial.available())
  {
    char x = (char)Serial.read();

    if (x == '<')
    {
      sendPointer = 0;
    }
    
    payload[sendPointer++] = x;
    
    if (x == '>')
    {
      payload[sendPointer] = 0;
      
      sprintf(debug_string, "Received '%s'", payload);
      publishUsageAsMessage(debug_string);
      
      if (payload[1] == 's')
        Serial.print("<iDCC-EX V-3.0.4 / MEGA / STANDARD_MOTOR_SHIELD G-75ab2ab><H 1 0><H 2 0><H 3 0><H 4 0><Y 52 0>\n");
      if (payload[1] == 'S')
        Serial.print("<Q 00>\n");
      if (payload[1] == 'Q')
        Serial.print("<q 00>\n");
      if (payload[1] == '1')
        Serial.print("<p1>");
      if (payload[1] == '0')
        Serial.print("<p0>");
      sprintf(debug_string, "Sending %c response", payload[1]);
      publishUsageAsMessage(debug_string);

      sendPointer = 0;
    }
  }
  
  while (DCCqueueTail != DCCqueueHead)
  {
    if (gInterruptTime[DCCqueueTail] == 1 && bitCount == 0)
    {
      preAmbleCount++;
      if (preAmbleCount > 99)
        preAmbleCount = 99;
    }
    else
    {
      if (preAmbleCount > 14)
      {
        bitCount++;
        RcvPacket <<= 1;
        if (gInterruptTime[DCCqueueTail] == 1)
          RcvPacket += 1;

        if (bitCount > 27)
        {
          unsigned long tempPacket = (RcvPacket >> 25);
          if (tempPacket  == 2)
          {
            RcvByte[0] = (byte)((RcvPacket >> 19) & 0xFF);
            RcvByte[1] = (byte)((RcvPacket >> 10) & 0xFF);
            RcvByte[2] = (byte)((RcvPacket >> 1) & 0xFF);

            if (RcvByte[2] == (RcvByte[0] ^ RcvByte[1]))
            {
              unsigned int Add1 = RcvByte[0] & 63;
              unsigned int Add2 = 448 - ((RcvByte[1] & 112) << 2);
              int pair = (RcvByte[1] & 6) >> 1;
              unsigned int Add = ((Add1 + Add2) * 4) + pair + 1;
              if (OffsetBy4 == 'Y' && Add >= 4)
                Add -= 4;

              unsigned int Direction = RcvByte[1] & 1;
              if (Add == lastGoodAdd && Direction == lastGoodDirection)
              {
                if (Add != lastAdd || Direction != lastDirection || millis() > lastDCCSend)
                {
                  lastAdd = Add;
                  lastDirection = Direction;
                  Add1 = Add / 100;
                  MessageBank = DCCBank[Add1];

                  if (MessageBank != '0')
                  {
                    Add2 = Add % 100;
                    sprintf(Banknumber,"%02d", Add2);

                    if (Direction == 0)
                      if (DivergeRoutes)
                        Status = 'S';
                      else
                        Status = 'U';
                    else
                      if (DivergeRoutes)
                        Status = 'U';
                      else
                        Status = 'S';
                    lastDCCSend = millis() + 500L;
                    sprintf (payload,"<%c%c%s>",Status,MessageBank,Banknumber);
                    noInterrupts();
                    publishMessage(MessageBank, Banknumber, payload, true);
                    delay(250);
                    interrupts();
                  }
                }
              }
              lastGoodAdd = Add;
              lastGoodDirection = Direction;
              lastGoodTimeout = millis();
            }
          }
          bitCount = 0;
          preAmbleCount = 0;
          RcvPacket = 0L;
        }
      }
    }
    DCCqueueTail = (DCCqueueTail + 1) % DCC_QUEUE_SIZE;
  }
}
