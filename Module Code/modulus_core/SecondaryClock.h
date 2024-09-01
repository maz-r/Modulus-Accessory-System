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
#define _SECONDARY_CLOCK
// Module connection pins (Digital Pins)
#define TINY_CLOCK_CLK 15
#define TINY_CLOCK_DIO 13

TM1637Display myDisplay(TINY_CLOCK_CLK, TINY_CLOCK_DIO);
Adafruit_7segment matrix = Adafruit_7segment();

void SetBrightness(int brightness, int boardType)
{
  if (boardType == LARGESECONDARYCLOCK)
  {
    matrix.setBrightness(brightness);
  }
  else
  {
    myDisplay.setBrightness(brightness);
  }
}

void setSegments(uint8_t data[], int boardType)
{
int     i;
uint8_t dots;
uint8_t new_data[5];
  
  if (boardType == LARGESECONDARYCLOCK)
  {
    data[4] = 0;
    if (data[0] == ' ' && Clock24)
      matrix.writeDigitRaw(0,0);
    else
      matrix.writeDigitNum(0, data[0]);
    matrix.writeDigitNum(1, data[1]);
    matrix.writeDigitNum(3, data[2]);
    matrix.writeDigitNum(4, data[3]);

    dots = 0;
    if (colon)
    {
      dots |= 0x02;
    }

    if (Clock24)
    {
      if (ampm)
      {
        dots |= 0x04;
      }
      else
      {
        dots |= 0x08;
      }
    }
    matrix.writeDigitRaw(2,dots);
    matrix.writeDisplay();
  }
  else
  {
    for (i=0; i<5; i++)
    {
      new_data[i] = myDisplay.encodeDigit(data[i]);
    }

    if (Clock24 && data[0] == 0)
    {
      new_data[0] = 0;
    }

    if (colon)
    {
      new_data[1] |= 0x80;
    }
      
    myDisplay.setSegments(new_data);
  }
}

void secondaryClockSetup(int boardType)
{
int  i;
char tempString[40];
  
  if (boardType == LARGESECONDARYCLOCK)
  {
//    Wire.pins(4, 5);
    Wire.begin(4, 5);
    delay(100);
    matrix.begin(0x70);
    Wire.setClock(100000L);
  }
  else
  {
    myDisplay.initialisePins();
  }
  
  flashing = false;
  colon = true;
  Running = false;
  ampm = true;
  Clock24 = false;

  File f = LittleFS.open("/secondaryclock.conf", "r");
  if (!f)
  {
      DEBUG_println("file open failed for secondaryclock.conf");
      Brightness = 12;
  }
  else
  {
    DEBUG_println("brightness config file is present");
    String _brightness = f.readStringUntil('\n');
    _brightness.toCharArray(tempString, 40);
    Brightness = atoi(tempString);
    f.close();
  }

  SetBrightness(Brightness, boardType);

  int major = atoi(MAJOR_VERSION);
  int minor = atoi(MINOR_VERSION);
  sprintf(tempString, "%2d%02d", major, minor);
  
  DEBUG_println(tempString);
  
  for(i=0; i<4; i++)
    if (tempString[i] == ' ')
    {
      if (boardType != LARGESECONDARYCLOCK)
      {
        data[i] = 20;
      }
    }
    else
      data[i] = tempString[i] - '0';

  // display version
  setSegments(data, boardType);
  delay(2000);

  // clear the display buffer
  for (i=0; i<4; i++)
    data[i] = 0;

  next_millis = 0L;
  last_message_millis = 0L;
}

void secondaryClockSerial(int boardType)
{
byte incomingByte;
long x;

  if (ReceiveQueueSize() > 0)
  {
    incomingByte = GetNextQueueCharacter();
    if (incomingByte == SOM)
    {
      RxQueuePointer = 0;
    }
    else
    {
      if (incomingByte == EOM)
      {
        if (RxQueue[0] == 'W' && RxQueue[1] == 'D')
        {
          sprintf(Line, "<S,%02d", boardType);
          publishStringAsMessage(Line);
  
          sprintf(Line, "%02d,%02d", boardType, Brightness);
          DEBUG_println(Line);
          publishStringAsMessage(Line);
          
          // send EOM to show we've finished
          Line[0] = EOM;
          Line[1] = 0;
          publishStringAsMessage(Line);
  
        }

        if (InputStr[1]=='I' && InputStr[2]=='D')
        {
          flashID_LED();
        }
        
        if (RxQueue[0] == 'W' && RxQueue[1] == 'C')
        {
          RxQueue[RxQueuePointer] = 0;
          Brightness = atoi((char *)&RxQueue[2]);
          DEBUG_print("Brightness = ");
          DEBUG_println(Brightness);
          File f = LittleFS.open("/secondaryclock.conf", "w");
          if (!f)
          {
              DEBUG_println("failed to open secondaryclock.conf for writing");
              Brightness = 8;
          }
          else
          {
            DEBUG_println("board address config file is present");
            f.println(Brightness);
            f.close();
          }
          SetBrightness(Brightness, boardType);
        }
        else
        {
          if (RxQueuePointer == 8)
          {
            if (RxQueue[0] == 'R' || RxQueue[0] == 'P')
            {
              last_message_millis = millis()+4000L;

              if (RxQueue[0] == 'R')
                Running = true;
              else
                Running = false;
                  
              hours = (RxQueue[1] - '0') * 10;
              hours += RxQueue[2] - '0';
              if (hours > 11)
                ampm = true;
              else
                ampm = false;
                  
              if (RxQueue[7] == 'A')
              {
                Clock24 = true;
                if (hours > 11)
                  hours %= 12;
                  
                if (hours == 0)
                  hours = 12;

                if (hours / 10 == 0)
                  data[0] = ' ';
                else
                  data[0] = 1; //myDisplay.encodeDigit(1);

                data[1] = hours % 10;
              }
              else
              {
                Clock24 = false;
                data[0] = RxQueue[1] - '0';
                data[1] = RxQueue[2] - '0';
              }
  
              data[2] = RxQueue[3] - '0';
              data[3] = RxQueue[4] - '0';

              if (Running)
              {
                SetBrightness(Brightness, boardType);

                if (colon)
                {
                  colon = false;
                }
                else
                {
                  colon = true;
                }
              }

              setSegments(data, boardType);
            }
          }
          else
            DEBUG_println("Wrong length");
        }
      }
      else
      {
        RxQueue[RxQueuePointer++] = incomingByte;
        if (RxQueuePointer >= MAX_QUEUE)
          RxQueuePointer = 0;
      }
    }
  }
}

void secondaryClockLoop(int boardType)
{
  if (millis() >= next_millis)
  {
    if (!Running)
    {
      if (flashing)
      {
        flashing = false;
        SetBrightness(0, boardType);
      }
      else
      {
        flashing = true;
        SetBrightness(Brightness, boardType);
      }
      next_millis = millis()+500L;
      setSegments(data, boardType);
    }
  }
  
  if (millis() >= last_message_millis)
  {
    if (flashing)
    {
      flashing = false;
      SetBrightness(0, boardType);
    }
    else
    {
      flashing = true;
      SetBrightness(Brightness, boardType);
    }
    setSegments(data, boardType);
    last_message_millis = millis()+250L;
    next_millis = millis()+500L;
  }
}
