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
#define _MATRIX_CLOCK

#define MAX_QUEUE      40
#define NUMBER_OF_DEVICES 4
#define CS_PIN 15

#include <SPI.h>
#include "LedMatrix.h"

int  AMPMstyle;
LedMatrix ledMatrix = LedMatrix(NUMBER_OF_DEVICES, CS_PIN);
uint8_t inputBuffer[32];
uint8_t scrollingBuffer[32];
uint8_t outputBuffer[32];
int  scrollStep;
int  fontType;
int  millisCount;
bool scrollingDown;
uint8_t afternoon;

uint8_t roundFont[][5] = {
  {B00111110, B01000001, B01000001, B01000001, B00111110}, // '0'
  {B00000000, B01000010, B01111111, B01000000, B00000000}, // '1'
  {B01000010, B01100001, B01010001, B01001001, B01000110}, // '2'
  {B00100010, B01001001, B01001001, B01001001, B00110110}, // '3'
  {B00011000, B00010100, B00010010, B01111111, B00010000}, // '4'
  {B00100111, B01001001, B01001001, B01001001, B00110001}, // '5'
  {B00111100, B01001010, B01001001, B01001001, B00110000}, // '6'
  {B00000001, B01110001, B00001001, B00000101, B00000011}, // '7'
  {B00110110, B01001001, B01001001, B01001001, B00110110}, // '8'
  {B00000110, B01001001, B01001001, B00101001, B00011110}, // '9'
  {B11000000, B00101110, B11000101, B00101110, B11000000}, // 'am'
  {B11000000, B00101110, B11000101, B00100010, B11000000}, // 'pm'
  {B01111110, B00010001, B00010001, B00010001, B01111110}, // 'A'
  {B01111111, B00001001, B00001001, B00001001, B00000110}, // 'P'
  {B00000000, B00110000, B00110000, B00000000, B00000000}, // AM dot
  {B00000000, B00000110, B00000110, B00000000, B00000000}, // PM dot
  {B00000000, B00000000, B00000000, B00000000, B00000000}, // no dot
  {B00000000, B00000110, B00000110, B00000000, B00000000}, // PM dot
};

uint8_t squareFont[][5] = {
  {B01111111, B01000001, B01000001, B01000001, B01111111}, //'0'
  {B00000000, B00000000, B00000000, B00000000, B01111111}, //'1'
  {B01111001, B01001001, B01001001, B01001001, B01001111}, //'2'
  {B01001001, B01001001, B01001001, B01001001, B01111111}, //'3'
  {B00001111, B00001000, B00001000, B00001000, B01111111}, //'4'
  {B01001111, B01001001, B01001001, B01001001, B01111001}, //'5'
  {B01111111, B01001000, B01001000, B01001000, B01111000}, //'6'
  {B00000001, B00000001, B00000001, B00000001, B01111111}, //'7'
  {B01111111, B01001001, B01001001, B01001001, B01111111}, //'8'
  {B00001111, B00001001, B00001001, B00001001, B01111111}, //'9'
  {B11100000, B00101111, B11100101, B00101111, B11100000}, // 'am'
  {B11100000, B00101111, B11100101, B00100111, B11100000}, // 'pm'
  {B01111111, B00001001, B00001001, B00001001, B01111111}, // 'A'
  {B01111111, B00001001, B00001001, B00001001, B00001111}, // 'P'
  {B00000000, B00110000, B00110000, B00000000, B00000000}, // AM dot
  {B00000000, B00000110, B00000110, B00000000, B00000000}, // PM dot
  {B00000000, B00000000, B00000000, B00000000, B00000000}, // no dot
  {B00000000, B00000110, B00000110, B00000000, B00000000}, // PM dot
};

void clearBuffer()
{
int i;

  for (i=0; i<32; i++)
  {
    inputBuffer[i] = 0;
    outputBuffer[i] = 0;
  }  
}

void drawCharacter(int column, char character, int fontType)
{
int i;

  for(i=0; i<5; i++)
  {
    if (fontType == 0)
      if (character != ' ')
        inputBuffer[column+i] = roundFont[character][i];
      else
        inputBuffer[column+i] = 0;
    else
      if (character != ' ')
        inputBuffer[column+i] = squareFont[character][i];
      else
        inputBuffer[column+i] = 0;
  }
}

void convertTime()
{
int i;
int offset;

  clearBuffer();
  if (!ampm || AMPMstyle == 0)
    offset = 3;
  else
    offset = 1;

  for (i=0; i<2; i++)
  {
    drawCharacter((i*6)+offset, data[i], fontType);
  }
  for (i=2; i<4; i++)
  {
    drawCharacter((i*6)+offset+2, data[i], fontType);
  }

  // draw colon
  if (colon)
    inputBuffer[12+offset] = B00010100;

  // draw am or pm in the style required
  if (ampm)
  {
    switch (AMPMstyle)
    {
      case 0:
        break;
        
      case 1:
        drawCharacter(27, (char)(10+afternoon), fontType);
        break;
        
      case 2:
        drawCharacter(27, (char)(12+afternoon), fontType);
        break;

      case 3:
        drawCharacter(27, (char)(14+afternoon), fontType);
        break;
       
      case 4:
        drawCharacter(27, (char)(16+afternoon), fontType);
        break;
    }
  }
}

void scrollDisplayDown(int scrollStep)
{
int i;
uint8_t mask;

  mask = 1<<(7-scrollStep);
  for (i=0; i<32; i++)
  {
    if (scrollingBuffer[i] != inputBuffer[i])
    {
      scrollingBuffer[i] = scrollingBuffer[i] << 1;
      if ((inputBuffer[i] & mask) != 0)
      {
        scrollingBuffer[i] = scrollingBuffer[i] + 1;
      }
    }
  }
}

void transposeBuffer(uint8_t bufferToDisplay[])
{
int row;
int column;
int mask;
int outMask;
int offset;

  for(column=0; column<32; column++)
  {
    outputBuffer[column] = 0;
  }
  
  for(column=0; column<32; column++)
  {
    outMask = 1<<(7 - (column%8));
    offset = (column/8) * 8;
    for (row=0; row<8; row++)
    {
      mask = 1<<row;
      if ((bufferToDisplay[column] & mask) != 0)
      {
        outputBuffer[offset+row] |= outMask;
      }
    }
  }
}

bool updateDisplay(bool overRide)
{
int i;

  ledMatrix.clear();

  // if we are scrolling, then work out what to scroll, else just copy the buffer straight over
  if (scrollingDown && !overRide)
  {
    scrollDisplayDown(scrollStep);
    transposeBuffer(scrollingBuffer);
    scrollStep++;
    if (scrollStep >= 8)
    {
      scrollStep = 0;
    }
  }
  else
    transposeBuffer(inputBuffer);
    
  for (i=0; i<32; i++)
    ledMatrix.setColumn(i,outputBuffer[i]);
  ledMatrix.commit();
  delay(15);

  if (scrollStep == 0)
    return true;
  else
    return false;
}

void saveMatrixConfigurationFile()
{
  File f = LittleFS.open("/matrixclock.conf", "w");
  if (!f)
  {
      DEBUG_println("file open failed for secondaryclock.conf");
      Brightness = 1;
  }
  else
  {
    f.println(Brightness);
    f.println(fontType);
    if (scrollingDown)
      f.println("Y");
    else
      f.println("N");
    f.println(AMPMstyle);
    f.close();
  }
}

void matrixClockSetup()
{
int  i;
char tempString[40];

  flashing = false;
  colon = true;
  Running = false;
  ampm = true;
  afternoon = 0;
  AMPMstyle = 1;
  secondCounter = 0;
  fontType = 0;
  scrollingDown = false;
  millisCount = 99;
//  newTimeReceived = true;
  
  File f = LittleFS.open("/matrixclock.conf", "r");
  if (!f)
  {
      DEBUG_println("file open failed for secondaryclock.conf");
      Brightness = 1;
  }
  else
  {
    DEBUG_println("brightness config file is present");
    String _brightness = f.readStringUntil('\n');
    _brightness.toCharArray(tempString, 40);
    Brightness = atoi(tempString);
    String _fontType = f.readStringUntil('\n');
    _fontType.toCharArray(tempString, 40);
    fontType = atoi(tempString);
    String _scrolling = f.readStringUntil('\n');
    _scrolling.toCharArray(tempString, 40);
    if (tempString[0] == 'Y')
      scrollingDown = true;
    else
      scrollingDown = false;
    String _ampmstyle = f.readStringUntil('\n');
    _ampmstyle.toCharArray(tempString, 40);
    AMPMstyle = atoi(tempString);
    f.close();
  }

  ledMatrix.init();
 
  scrollStep = 0;

  for (i=0; i<4; i++)
    data[i] = 0;

  for (i=0; i<32; i++)
  {
    inputBuffer[i] = 0;
    outputBuffer[i] = 0;
    scrollingBuffer[i] = 0;
  }
  updateDisplay(false);
}

void matrixClockSerial()
{
byte incomingByte;
long x;
int i;
int j;
int paramCount;
char tempString[40];

  while (ReceiveQueueSize() > 0)
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

          if (scrollingDown == true)
            sprintf(Line, "%02d,%02d,%d,1,%d", boardType, Brightness, fontType, AMPMstyle);
          else
            sprintf(Line, "%02d,%02d,%d,0,%d", boardType, Brightness, fontType, AMPMstyle);
          DEBUG_println(Line);
  //        delay(1000);
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
          RxQueue[RxQueuePointer++] = EOM;
          RxQueue[RxQueuePointer] = 0;
          DEBUG_println((char *)RxQueue);
          i = 2;
          j = 0;
          paramCount = 0;
          DEBUG_println("config file is open for writing");
          while (RxQueue[i] != 0)
          {
            if (RxQueue[i] == ',' || RxQueue[i] == '>')
            {
              tempString[j] = 0;
              // f.println(tempstring);
              DEBUG_print("Saving : ");
              DEBUG_println((char *)tempString);
              j=0;
              i++;
              switch (paramCount)
              {
                case 0:
                  Brightness = atoi((char *)tempString);
                  break;
                  
                case 1:
                  fontType =  atoi((char *)tempString);
                  break;
                  
                case 2:
                  if (tempString[0] == '1')
                    scrollingDown = true;
                  else
                    scrollingDown = false;
                  break;
      
                case 3:
                  AMPMstyle = atoi((char *)tempString);
                  break;
                  
                default:
                  break;
              }
              paramCount++;
              DEBUG_print("paramCount = ");DEBUG_println(paramCount);
            }
            else
              tempString[j++] = RxQueue[i++];
          }
          ledMatrix.setIntensity(Brightness);

          saveMatrixConfigurationFile();
          convertTime();
          while (!updateDisplay(false))
              delay(5);

        }
        else
        {
          DEBUG_println(Line);
          if (RxQueuePointer == 8)
          {
            if (RxQueue[0] == 'R' || RxQueue[0] == 'P')
            {
              if (RxQueue[0] == 'R')
                Running = true;
              else
                Running = false;
                  
              hours = (RxQueue[1] - '0') * 10;
              hours += RxQueue[2] - '0';
                  
              if (RxQueue[7] == 'A')
              {
                ampm = true;
                if (hours > 11)
                  afternoon = 1;
                else
                  afternoon = 0;
                                    
                hours %= 12;
                if (hours == 0)
                  hours = 12;
                  
                if (hours / 10 == 0)
                  data[0] = ' ';
                else
                  data[0] = 1;
                    
                data[1] = hours % 10;
              }
              else
              {
                data[0] = RxQueue[1] - '0';
                data[1] = RxQueue[2] - '0';
                ampm = false;
              }
  
              minutes = (RxQueue[3] - '0') * 10;
              data[2] = RxQueue[3] - '0';
              minutes += RxQueue[4] - '0';
              data[3] = RxQueue[4] - '0';

              if (Running)
              {
                ledMatrix.setIntensity(Brightness);

                if (colon)
                {
                  colon = false;
                }
                else
                {
                  colon = true;
                }
              }
             
              convertTime();
              while (!updateDisplay(false))
                delay(5);
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

void matrixClockLoop()
{
int i;

  if (millis() >= next_millis)
  {
    if (!Running)
    {
      if (flashing)
      {
        flashing = false;
        if (Brightness == 0)
          i = 1;
        else
          i = 0;
        ledMatrix.setIntensity(i);
      }
      else
      {
        flashing = true;
        ledMatrix.setIntensity(Brightness);
      }
      next_millis = millis()+500L;
    }
  }  
}
