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
#define _LIGHTING

#define MAXLIGHTS 16 

//#define OUTPUT_QUEUE_SIZE 18
#define PROGRAM_CONF_PIN 3
#define PROGRAM_PIN 4
#define CHIPSELECT  10
#define POWER_CONTROL_PIN D8

char LIGHT_CONFIG_FILE[40]="LIGHT.CFG";
char LIGHT_DEFAULT_FILE[40]="LIGHT_DEFAULT.CFG";

Adafruit_PWMServoDriver Lights = Adafruit_PWMServoDriver();

float sinCurve;

uint8_t LightSequence2[2] = {41, 8};
uint8_t LightSequence3[4] = {8, 41, 25, 8};
uint8_t LightSequence4[4] = {8, 41, 25, 8};

typedef struct {
  int16_t  Target;
  int16_t  Actual;
  int16_t  HighTarget;
  int16_t  SpeedUp;
  int32_t  TimeHigh;
  int16_t  LowTarget;
  int16_t  SpeedDown;
  int16_t  TimeLow;
  uint32_t NumOfFlashes;
  uint32_t Interval;
  uint32_t Delay;
  uint32_t OnTime;
  uint32_t OffTime;
  uint32_t NextStepTime;
  uint8_t  Effect;
  uint16_t CurrentCount;
  uint8_t  TempCount;
  bool     Moved;
  bool     Inverted;
} LIGHT_DETAILS;

uint8_t       CurrentLight;
LIGHT_DETAILS LightDetails[MAXLIGHTS];

void SendInvertedDetails()
{
unsigned char LineCount;
unsigned char CharCount;
char ReadByte;
int  i;

  sprintf(Line, "<I,%02d,", boardType);
//  publishStringAsMessage(Line);

  for (i=0; i< 16; i++)
  {
    if (LightDetails[i].Inverted)
      sprintf(Line, "%s1,", Line);
    else
      sprintf(Line, "%s0,", Line);
  }
  sprintf(Line, "%s>", Line);
  publishStringAsMessage(Line);

  return;
}

void LightOverwriteDefaultsFile()
{
uint8_t  i;
char     ThisChar;
uint16_t CurrentPos;
uint8_t  ThisLight;

  // open file for writing i.e. create new file
  Configfile = LittleFS.open(LIGHT_DEFAULT_FILE, "w");

  // loop around writing every Light position as it stands
  for (i=0; i<MAXLIGHTS; i++)
  {
    ThisLight = i;

    Configfile.write((ThisLight/10)+'0');
    ThisLight %= 10;
    Configfile.write(ThisLight+'0');

    Configfile.write(',');

    if (LightDetails[i].Inverted)
      Configfile.println("1");
    else
      Configfile.println("0");
  }

  // close the file to flush the written data out
  Configfile.close();

  return;
}

bool LightReadStartupConfig()
{
uint8_t CharCount;
uint8_t LineCount;
char    ReadByte;
uint8_t i;
uint8_t retval;
uint8_t LightNum;
uint8_t ArgCount;
uint8_t ArgChar;
char    Args[20][10];
bool    endoffile;

  endoffile = false;

  Configfile = LittleFS.open(LIGHT_DEFAULT_FILE, "r");

  CharCount = 0;

  if (Configfile)
  {
    retval = true;
    while (!endoffile)
    {
      ReadByte = Configfile.read();
      if (ReadByte == 255 || ReadByte == 10 || ReadByte == 13|| ReadByte == 0)
      {
        if (CharCount != 0)
        {
          // terminate the line
          Line[CharCount] = 0;
          CharCount = 0;
          for(i=0; i<4; i++)
            Args[i][0] = 0;
          
          ArgCount = 0;
          ArgChar = 0;
          for (i=0; i < strlen(Line); i++)
          {
            if(Line[i] == ',' || Line[i] == 0)
            {
              Args[ArgCount][ArgChar] = 0;
              ArgCount++;
              ArgChar = 0;
            }
            else
            {
              if(Line[i] != 10 && Line[i] != 13)
              {
                Args[ArgCount][ArgChar] = Line[i];
                ArgChar++;
              }
            }
          }

          Args[ArgCount][ArgChar] = 0;
          LightNum = atoi(Args[0]);
          if (Args[1][0] == '1')
          {
            LightDetails[LightNum].Inverted = true;
            DEBUG_print(LightNum);DEBUG_println(" : TRUE");
          }
          else
          {
            LightDetails[LightNum].Inverted = false;
          }
        }

        if (ReadByte == 255 || ReadByte == 0)
          endoffile = true;
      }
      else
        Line[CharCount++] = ReadByte;
    }

    Configfile.close();
  }
  else
  {
    DEBUG_println(F("Failed to open defaults"));

    for (i=0; i<MAXLIGHTS; i++)
      LightDetails[i].Inverted = false;

    LightOverwriteDefaultsFile();
    
    retval = false;
  }

  return retval;
}

void LightProcessLine(char *LineToProcess, bool TimeMessage, bool Override, char *TimeRange, uint8_t CurrentHour, uint8_t CurrentMinutes)
{
char     LightEffect;
uint8_t  LightNum;
int16_t  LightTarget1;
int16_t  LightTarget2;
int16_t  LightTarget3;
int16_t  LightTarget4;
int16_t  LightTarget5;
int16_t  LightTarget6;
int16_t  LightTarget7;
int16_t  LightTarget8;
int16_t  LightSpeed;
uint8_t  i;
uint8_t  j;
uint8_t  MustBeOneHit[MAXLIGHTS];
uint8_t  temp;
bool     proceed;
bool     RandomFound;
bool     MoreSpaces;
int8_t   PosNeg;
int8_t   NumRandom;
char     tempString[40];
uint8_t  ArgCount;
uint8_t  ArgChar;
char     Args[20][10];
int      values[2];
int      StartLightNumber;
int      EndLightNumber;
float    TimeLow;
float    TimeHigh;
uint16_t NumberOfMinutes;
uint16_t TimeDifference;
uint16_t LightDifference;
uint8_t  SignalHeadBits;

  for(i=0; i<20; i++)
    Args[i][0] = 0;
  
  // extract the Light details
  ArgCount = 0;
  ArgChar = 0;
  for (i=0; i < strlen(LineToProcess); i++)
  {
    if(LineToProcess[i] == ',' || LineToProcess[i] == 0)
    {
      Args[ArgCount][ArgChar] = 0;
      ArgChar = 0;
      ArgCount++;
    }
    else
      if(LineToProcess[i] != 10 && LineToProcess[i] != 13)
      {
        Args[ArgCount][ArgChar] = LineToProcess[i];
        ArgChar++;
      }
  }
  Args[ArgCount][ArgChar] = 0;

  for (i=0; i<ArgCount;i++)
  {
    tempString[0] = 0;
    // do substitution if required....
    for(j=0; j<strlen(Args[i]); j++)
    {
      if (Args[i][j] == '$')
      {
        // substitute the letter for saved variable
//        DEBUG_print("Substituting ");DEBUG_print(Args[i][j+1]);DEBUG_print(" with ");
        sprintf(tempString,"%s%s",tempString, Variables[Args[i][++j] - 'A']);
//        DEBUG_println(tempString);
      }
      else
        sprintf(tempString,"%s%c",tempString, Args[i][j]);
    }

    if (tempString[0] != 0)
      strcpy (Args[i], tempString);
  }

  if (Args[0][0] == '=')
  {
    // remember the value we are told to
//    DEBUG_print("Saving ");DEBUG_print(Args[2]);DEBUG_print(" as ");DEBUG_println(Args[1]);
    if ((Args[1][0] - 'A') >=0 && (Args[1][0] - 'A') <= 25)
      strcpy(Variables[Args[1][0] - 'A'], Args[2]);
  }
  else
  {
    values[0] = -1;
    values[1] = -1;
    
    parseColon(values, Args[0]);
    
    StartLightNumber = values[0];
    
    if (values[1] == -1)
    {
      EndLightNumber = StartLightNumber;
    }
    else
    {
      EndLightNumber = values[1];      
    }
/*
 *  THIS PART NEEDS REVISITING! NumRandom has been removed!!!!
 */
 
    DEBUG_println(Args[1][0]);

    NumRandom = 0;
    /* if the lights are being treated separately....*/
    if (Args[1][0] == 'I')
    {
      // minimum of 1, otherwise we wouldn't be here!
      NumRandom = 1;
      // work out how random!
      for (i=0; i<4; i++)
      {
        if (TimeRange[i] == '?')
        {
          NumRandom++;
        }
        if (NumRandom > 4)
          NumRandom = 4;
      }
      DEBUG_print("NumRandom = ");DEBUG_println(NumRandom);
    }

    if (NumRandom > 0)
    {
      for (i=0; i<MAXLIGHTS; i++)
        MustBeOneHit[i] = 0;
        
      if (StartLightNumber != EndLightNumber)
      {
        RandomFound = false;
        MoreSpaces = true;
        while (!RandomFound && MoreSpaces)
        {
          temp = random(StartLightNumber, EndLightNumber+1);
          if (MustBeOneHit[temp] == 0 && LightDetails[temp].Target != map(atoi(Args[4]),0,100,0,4095))
          {
            MustBeOneHit[temp] = 1;
            RandomFound = true;
          }
          else
          {
            MustBeOneHit[temp] = 2;
          }

          MoreSpaces = false;
          for (j=StartLightNumber; j< EndLightNumber; j++)
            if (MustBeOneHit[j] == 0)
              MoreSpaces = true;
        }
      }
      else
        MustBeOneHit[StartLightNumber] = 1;

//      DEBUG_print("MustBeOneHit = ");DEBUG_println(MustBeOneHit);
    }

    for (LightNum = StartLightNumber; LightNum <= EndLightNumber; LightNum++)
    {
      if ((TimeMessage && !LightDetails[LightNum].Moved) || !TimeMessage || Override)
      {
        LightDetails[LightNum].Moved = true;

        DEBUG_print(LightNum);DEBUG_print(" : ");

        if (MustBeOneHit[LightNum] == 0 && NumRandom > 0)
        {
          DEBUG_println("Not doing this one");
          continue;
        }
        else
          DEBUG_println("Doing this one");
      
        LightEffect  = Args[2][0];

        LightTarget1 = atoi(Args[4]); // TargetHigh
        LightTarget2 = atoi(Args[5]); // SpeedUp
        LightTarget3 = atoi(Args[6]); // TimeHigh
        LightTarget4 = atoi(Args[7]); // TargetLow
        LightTarget5 = atoi(Args[8]); // SpeedDown
        LightTarget6 = atoi(Args[9]); // TimeLow
        LightTarget7 = atoi(Args[10]); // NumOfFlashes
        LightTarget8 = atoi(Args[11]); // Interval
  
        proceed = true;

        if (proceed)
        {
          if (Override || LightEffect == 'P'  || LightEffect == 'H' || !((LightDetails[LightNum].Effect == LightEffect) && (LightDetails[LightNum].HighTarget == map(LightTarget1,0,100,0,4095))))
          {
            LightDetails[LightNum].Delay = millis() + (atoi(Args[3]) * 100L);

            switch(LightEffect)
            {
              case 'H':
                SignalHeadBits = 2;
                LightTarget1 = LightDetails[LightNum].HighTarget;
                
                for (i=1; i<7; i++)
                {
                  if (Args[4][i] == 'S')
                  {
                    LightTarget1 |= SignalHeadBits;
                  }
                  if (Args[4][i] == 'R')
                  {
                    LightTarget1 &= ~SignalHeadBits;
                  }
          
                  SignalHeadBits <<= 1;
                }

                LightDetails[LightNum].HighTarget = LightTarget1;
                LightDetails[LightNum].Target = LightDetails[LightNum].HighTarget;

                if (Args[4][0] == 'F')
                {
                  LightDetails[LightNum].Actual = -1;
                  if (LightTarget3 != 0L)
                    LightDetails[LightNum].TimeHigh = LightTarget3;
                  else
                    LightDetails[LightNum].TimeHigh = 10L;
                }
                else
                {
                  LightDetails[LightNum].TimeHigh = 0L;
                }
                break;

              case 'S':
                LightDetails[LightNum].SpeedUp    = LightTarget2;
                LightDetails[LightNum].SpeedDown  = LightTarget2;
                LightDetails[LightNum].Target     = map(LightTarget1,0,100,0,4095);
                LightDetails[LightNum].HighTarget = LightDetails[LightNum].Target;
                LightDetails[LightNum].OnTime = 0L;
                break;

              case 'F':
                LightDetails[LightNum].HighTarget = map(LightTarget1,0,100,0,4095);
                LightDetails[LightNum].LowTarget = map(LightTarget4,0,100,0,4095);
                LightDetails[LightNum].Actual = LightDetails[LightNum].LowTarget;
                if (LightDetails[LightNum].HighTarget == LightDetails[LightNum].LowTarget)
                  LightDetails[LightNum].LowTarget++;
                LightDetails[LightNum].Target = LightDetails[LightNum].HighTarget;
                LightDetails[LightNum].SpeedUp = LightTarget2 * 2L;
                LightDetails[LightNum].Interval   = LightTarget8 * 100L;
                LightDetails[LightNum].TimeLow   = LightTarget7 * 20L;
                LightDetails[LightNum].TimeHigh = millis() + LightTarget7 + (LightDetails[LightNum].TimeLow/2) + random((LightDetails[LightNum].TimeLow/2));
                break;
        
              case 'R':
                LightDetails[LightNum].HighTarget = map(LightTarget1,0,100,0,4095);
                LightDetails[LightNum].LowTarget  = map(LightTarget4,0,100,0,4095);
        
                if (LightDetails[LightNum].LowTarget == 0)
                  LightDetails[LightNum].LowTarget = 1;
        
                if (LightDetails[LightNum].LowTarget == LightDetails[LightNum].HighTarget)
                  if (LightDetails[LightNum].HighTarget <= 1)
                    LightDetails[LightNum].HighTarget += 1;
                  else
                    LightDetails[LightNum].LowTarget = LightDetails[LightNum].HighTarget - 1;              
                  
                LightDetails[LightNum].Actual     = 0;
                LightDetails[LightNum].Target     = LightDetails[LightNum].HighTarget;
                LightDetails[LightNum].SpeedUp    = LightTarget2 * 2L;
                LightDetails[LightNum].TimeHigh   = LightTarget3 * 10L;
                LightDetails[LightNum].TimeLow    = LightTarget6 * 10L;
                LightDetails[LightNum].NumOfFlashes=LightTarget7 * 50L;
                LightDetails[LightNum].Interval   = LightTarget8 * 100L;
                LightDetails[LightNum].OffTime    = 0L;
                LightDetails[LightNum].OnTime     = 0L;
                break;
        
              case 'Q':
                LightDetails[LightNum].HighTarget   = map(LightTarget1,0,100,0,4095);
                LightDetails[LightNum].SpeedUp      = LightTarget2;
                LightDetails[LightNum].TimeHigh     = LightTarget3 * 10;
                
                LightDetails[LightNum].LowTarget    = map(LightTarget4,0,100,0,4095);
                LightDetails[LightNum].SpeedDown    = LightTarget5;
                LightDetails[LightNum].TimeLow      = LightTarget6 * 10;
                
                LightDetails[LightNum].Target       = LightDetails[LightNum].HighTarget;
                LightDetails[LightNum].Actual       = LightDetails[LightNum].LowTarget;
                LightDetails[LightNum].NumOfFlashes = LightTarget7;
                LightDetails[LightNum].CurrentCount = LightTarget7;
                
                LightDetails[LightNum].Interval     = LightTarget8 * 100L;
                break;
                
              case 'P':
//                DEBUG_print(TimeRange[0]);DEBUG_print(TimeRange[1]);DEBUG_print(TimeRange[2]);DEBUG_println(TimeRange[3]);
                if (LightTarget4 > LightTarget1)
                {
                  LightDetails[LightNum].HighTarget   = map(LightTarget4,0,100,0,4095);
                  LightDetails[LightNum].LowTarget    = map(LightTarget1,0,100,0,4095);
                  PosNeg = 1;
                }
                else
                {
                  LightDetails[LightNum].HighTarget   = map(LightTarget1,0,100,0,4095);
                  LightDetails[LightNum].LowTarget    = map(LightTarget4,0,100,0,4095);
                  PosNeg = 2;
                }
                
//                DEBUG_print("High : ");DEBUG_println(LightDetails[LightNum].HighTarget);
//                DEBUG_print("Low  : ");DEBUG_println(LightDetails[LightNum].LowTarget);
                
                // work out how many minutes are between the 2 times in the trigger...
                if (CurrentHour != 255 && CurrentMinutes != 255)
                {
                  TimeLow = TimeRange[0] - 'a';
                  TimeHigh = TimeRange[1] - 'a';
                  
//                  DEBUG_print("TimeLow = ");DEBUG_println(TimeLow);
//                  DEBUG_print("TimeHigh = ");DEBUG_println(TimeHigh);
      
                  if (TimeHigh > TimeLow)
                  {
                    NumberOfMinutes = TimeHigh - TimeLow;
                  }
                  else
                  {
                    NumberOfMinutes = (TimeHigh + 24) - TimeLow;
                  }
      
//                  DEBUG_print("CurrentHour = ");DEBUG_println(CurrentHour);
//                  DEBUG_print("CurrentMinutes = ");DEBUG_println(CurrentMinutes);

                  if (CurrentHour < TimeLow)
                  {
                    TimeDifference = (24 - TimeLow) + CurrentHour;
                  }
                  else
                  {
                    TimeDifference = CurrentHour - TimeLow;
                  }
      
                  NumberOfMinutes *= 60;
//                  DEBUG_print("NumberOfMinutes = ");DEBUG_println(NumberOfMinutes);
      
                  TimeDifference *= 60;
                  TimeDifference += CurrentMinutes;
//                  DEBUG_print("TimeDifference = ");DEBUG_println(TimeDifference);
                
                  LightDifference = LightDetails[LightNum].HighTarget - LightDetails[LightNum].LowTarget;
//                  DEBUG_print("LightDifference = ");DEBUG_println(LightDifference);
      
                  if (PosNeg == 2)
                    LightDetails[LightNum].Target = (uint16_t)((float)((float)TimeDifference / (float)NumberOfMinutes) * (float)LightDifference) + LightDetails[LightNum].LowTarget;
                  else
                    LightDetails[LightNum].Target = LightDetails[LightNum].HighTarget - (uint16_t)((float)((float)TimeDifference / (float)NumberOfMinutes) * (float)LightDifference);
                  
//                  DEBUG_print("Target = ");DEBUG_println(LightDetails[LightNum].Target);
                  LightDetails[LightNum].HighTarget = LightDetails[LightNum].Target;
                  LightDetails[LightNum].LowTarget  = LightDetails[LightNum].Target;
                  LightDetails[LightNum].SpeedUp    = LightTarget2;
                }
                else
                {
                  if (PosNeg == 2)
                  {
                    LightDetails[LightNum].Actual     = LightDetails[LightNum].HighTarget;
                    LightDetails[LightNum].Target     = LightDetails[LightNum].LowTarget;
                    LightDetails[LightNum].HighTarget = LightDetails[LightNum].Target;              
                    LightDetails[LightNum].SpeedUp    = 200L;
                    LightDetails[LightNum].SpeedDown  = 200L;
                  }
                  else
                  {
                    LightDetails[LightNum].Actual    = LightDetails[LightNum].LowTarget;
                    LightDetails[LightNum].Target    = LightDetails[LightNum].HighTarget;
                    LightDetails[LightNum].LowTarget = LightDetails[LightNum].Target;              
                    LightDetails[LightNum].SpeedUp   = 200L;
                    LightDetails[LightNum].SpeedDown = 200L;
                  }
                }
                
                LightEffect = 'S';
                LightDetails[LightNum].OnTime     = 0L;
                LightDetails[LightNum].OffTime    = 0L;
                LightDetails[LightNum].Delay      = 0L;
                LightDetails[LightNum].Interval   = 0L;
                break;          
            }
            LightDetails[LightNum].Effect = LightEffect;
            LightDetails[LightNum].NextStepTime = 0L;
          } 
        }
      }
    }
  }
  return;
}

void moveLight(uint8_t LightNum, uint16_t newPosition, bool proportional)
{
int   i;
float sineCurve;

  if (LightDetails[LightNum].Effect == 'H')
  {
    // if we are showing the right lights, do nothing!
    if (LightDetails[LightNum].Target != LightDetails[LightNum].Actual)
    {
      LightDetails[LightNum].NextStepTime = 0L;

      // latch the signal head outputs AND clear the counter
      Lights.setPWM(LightNum, 4096, 0);
      delay(100);
      Lights.setPWM(LightNum, 0, 4096);
      delay(100);

      // and now count out the right (binary)
      for (i=0; i<LightDetails[LightNum].Target; i++)
      {
        // switch on
        Lights.setPWM(LightNum, 4096, 0);
        delay(2);
        // switch off
        Lights.setPWM(LightNum, 0, 4096);
        delay(2);
      }
/*
      // latch the signal head outputs AND clear the counter
      Servos.setPWM(CurrentServo, 4096, 0);
      delay(80);
      Servos.setPWM(CurrentServo, 0, 4096);
      delay(70);
*/
      if (LightDetails[LightNum].TimeHigh > 0)
      {
        if (LightDetails[LightNum].Target == 0)
        {
          LightDetails[LightNum].Target = LightDetails[LightNum].HighTarget;
          LightDetails[LightNum].Actual = -1;
          LightDetails[LightNum].Delay  = millis() + (LightDetails[LightNum].TimeHigh * 100L);
        }
        else
        {
          LightDetails[LightNum].Target = 0;
          LightDetails[LightNum].Actual = -1;
          LightDetails[LightNum].Delay  = millis() + (LightDetails[LightNum].TimeHigh * 100L);
        }
      }
      else
      {
        LightDetails[LightNum].Actual = LightDetails[LightNum].Target;
        LightDetails[LightNum].Delay = 0;
      }
    }
  }
  else
  {
    if (proportional)
    {
      if (newPosition != 0)
      {
        sineCurve = newPosition/4096.0;
        sineCurve = sineCurve * sineCurve;
        sineCurve = sineCurve*4095;
        if (!LightDetails[LightNum].Inverted)
          Lights.setPWM(LightNum, 0, 4095 - (long)sineCurve);
        else
          Lights.setPWM(LightNum, 0, (long)sineCurve);
      }
      else
      {
        if (!LightDetails[LightNum].Inverted)
          Lights.setPWM(LightNum, 0, 4095);
        else
          Lights.setPWM(LightNum, 0, 0);
      }
    }
    else
      if (!LightDetails[LightNum].Inverted)
        Lights.setPWM(LightNum, 0, 4095 - newPosition);
      else
        Lights.setPWM(LightNum, 0, newPosition);
  }
  return;
}

void LightSetup()
{
int     i;
long    pwmDelay;
uint8_t tempval;

  CurrentLight = 0;

  LastTime = 0;
  InCount = 0;
  QueueHead = 0;
  QueueTail = 0;

  Lights.begin();

  Lights.setPWMFreq(1000);  // Analog Lights run at ~100 Hz updates

  LightReadStartupConfig();
  
  for (i=0; i<MAXLIGHTS; i++)
  {
    LightDetails[i].Delay = 0L;
    // if we failed to read the startup file or we've never used this Light before
    if (tempval == 255 || LightDetails[i].Actual == 0)
      // then set it to off
      LightDetails[i].Actual = map(0,0,100,0,4095);

    LightDetails[i].Target = LightDetails[i].Actual;
    LightDetails[i].NextStepTime = 0L;
    LightDetails[i].Moved = false;
    LightDetails[i].NextStepTime = -1;
//    LightDetails[i].Inverted = true;

    // and then start sending the pwm signal
    moveLight(i, LightDetails[i].Actual, true);
  }

//  LightReadStartupConfig();

//  delay(100);
  
  // open the config file to compare to incoming messages
  Datafile = LittleFS.open(LIGHT_CONFIG_FILE, "r");
  if (!Datafile)
  {
    DEBUG_println(F("Failed to open datafile"));
  }

  return;
}

void LightSerial()
{
int      val;
char     ReadByte;
char     BestMatch[40];
char     TempChar;
char     LightEffect;
uint32_t ThisTime;
uint8_t  LightNum;
uint8_t  Offset;
int16_t  LightTarget1;
int16_t  LightTarget2;
int16_t  LightTarget3;
int16_t  LightTarget4;
int16_t  LightTarget5;
int16_t  LightTarget6;
int16_t  LightTarget7;
int16_t  LightTarget8;
int16_t  LightTarget9;
int16_t  LightSpeed;
bool     RemoteConfigSet;
uint8_t  i;
uint8_t  ArgCount;
uint8_t  ArgChar;
char     Args[10][10];

  if(ReceiveQueueSize() > 0)
  {
    TempChar = GetNextQueueCharacter();

    if (TempChar == SOM)
    {
      InCount = 0;
      InsideMessage = true;
    }

    if (TempChar != EOM)
    {
      InputStr[InCount] = (char)TempChar;
      InCount = (InCount + 1) % MAX_MESSAGE_LENGTH;
    }

    if (TempChar == EOM)
    {
      InsideMessage = false;

      InputStr[InCount] = 0;

      if (InputStr[1] == 'R')
      {
        ThisTime = (InputStr[2] - '0') * 600L;
        ThisTime += (InputStr[3] - '0') * 60L;
        ThisTime += (InputStr[4] - '0') * 10L;
        ThisTime += (InputStr[5] - '0');
        if (LastTime != ThisTime)
        {
          for (i=0; i<MAXLIGHTS; i++)
            LightDetails[i].Moved = false;

          LastTime = ThisTime;
          
          ProcessFile(&InputStr[2], true, LightProcessLine);
        }
      }

      // if it is a remote config message....
      if (InputStr[1] == 'C' && InputStr[2] == 'T')
      {
//        DEBUG_println(InputStr);
        LightProcessLine(&InputStr[8], false, true, (char *)NULL, 255, 255);
      }

      // if it is a remote config message....
      if (InputStr[1] == 'C' && InputStr[2] == 'Q')
      {
        ProcessSearchLine(0, 4, &InputStr[3]);
      }

      if (InputStr[1]=='W' && InputStr[2]=='C')
      {
        DEBUG_println("Overwrite the config file");
        OverwriteConfigFile(LIGHT_CONFIG_FILE);
      }

      if (InputStr[1]=='W' && InputStr[2]=='S')
      {
        DEBUG_println("Set invert details");
        DEBUG_println(InputStr);
        for (i=0; i< MAXLIGHTS; i++)
        {
          LightDetails[i].NextStepTime = 0L;
          LightDetails[i].Actual += 1;
        
          if (InputStr[(i*2)+3] == '1')
          {
            LightDetails[i].Inverted = true;
            DEBUG_print(i);DEBUG_println(" : TRUE");
          }
          else
          {
            LightDetails[i].Inverted = false;
            DEBUG_print(i);DEBUG_println(" : FALSE");
          }
        }
        LightOverwriteDefaultsFile();
      }

      if (InputStr[1]=='W' && InputStr[2]=='+')
      {
        AddToConfigFile(LIGHT_CONFIG_FILE);
      }

      if (InputStr[1]=='W' && InputStr[2]=='E')
      {
        ReadConfigFileTriggers();
      }

      if (InputStr[1]=='W' && InputStr[2]=='D')
      {
        SendInvertedDetails();
        SendConfigurationFile();
      }

      if (InputStr[1]=='I' && InputStr[2]=='D')
      {
        flashID_LED();
      }

      if (InputStr[1]=='S' || InputStr[1]=='U')
      {
        ProcessFile(&InputStr[1], false, LightProcessLine);
      }
    }
  }
}

void LightLoop()
{
int16_t  val;
int16_t  Adj;
int16_t  Speed;
int8_t   WaveType;
int8_t   i;
bool     Changed;

  Changed = false;
  if (LightDetails[CurrentLight].OffTime != 0L)
  {
    if (millis() > LightDetails[CurrentLight].OffTime)
    {
      LightDetails[CurrentLight].Target = LightDetails[CurrentLight].LowTarget;
      LightDetails[CurrentLight].NextStepTime = millis() + LightDetails[CurrentLight].SpeedDown;
      LightDetails[CurrentLight].SpeedUp =  LightDetails[CurrentLight].SpeedDown;
      LightDetails[CurrentLight].OnTime = 0L;
      LightDetails[CurrentLight].OffTime = 0L;
      Changed = true;
    }
  }
  
  if (millis() > LightDetails[CurrentLight].Delay)
  {
    if (millis() >= LightDetails[CurrentLight].NextStepTime)
    {
//      if ((LightDetails[CurrentLight].Target != LightDetails[CurrentLight].Actual) || (LightDetails[CurrentLight].Effect == 'H' && LightDetails[CurrentLight].TimeHigh != 0L))
      if (LightDetails[CurrentLight].Target != LightDetails[CurrentLight].Actual)
      {
        switch (LightDetails[CurrentLight].Effect)
        {
          case 'H':
            moveLight(CurrentLight, LightDetails[CurrentLight].Target, true);
            break;
            
          case 'S':
            LightDetails[CurrentLight].NextStepTime = millis() + LightDetails[CurrentLight].SpeedUp;

            if (LightDetails[CurrentLight].SpeedUp == 0)
            {
              LightDetails[CurrentLight].Actual = LightDetails[CurrentLight].Target;
              if (LightDetails[CurrentLight].OnTime > 0)
                LightDetails[CurrentLight].OffTime = LightDetails[CurrentLight].OnTime + millis();
            }
            else
            {
              if (LightDetails[CurrentLight].Target > LightDetails[CurrentLight].Actual)
              {
                LightDetails[CurrentLight].Actual+=100;
                if (LightDetails[CurrentLight].Actual > LightDetails[CurrentLight].Target)
                {
                  LightDetails[CurrentLight].Actual = LightDetails[CurrentLight].Target;
                  if (LightDetails[CurrentLight].OnTime > 0L)
                    LightDetails[CurrentLight].OffTime = LightDetails[CurrentLight].OnTime + millis();
                }
              }
              else
              {
                LightDetails[CurrentLight].Actual-=100;
                if (LightDetails[CurrentLight].Actual < LightDetails[CurrentLight].Target)
                {
                  LightDetails[CurrentLight].Actual = LightDetails[CurrentLight].Target;
                  if (LightDetails[CurrentLight].OnTime > 0L)
                    LightDetails[CurrentLight].OffTime = LightDetails[CurrentLight].OnTime + millis();
                }
              }
            }
            
            moveLight(CurrentLight, LightDetails[CurrentLight].Actual, true);
            break;

          case 'F':
            if (millis() < LightDetails[CurrentLight].TimeHigh || LightDetails[CurrentLight].Interval == 0)
            {
                LightDetails[CurrentLight].NextStepTime = millis() + LightDetails[CurrentLight].SpeedUp + random(LightDetails[CurrentLight].SpeedUp);
//              DEBUG_print("Flicker Act : ");DEBUG_print(LightDetails[CurrentLight].Actual);DEBUG_print(" Tgt : ");DEBUG_println(LightDetails[CurrentLight].Target);

              LightDetails[CurrentLight].Actual = LightDetails[CurrentLight].Target;
              moveLight(CurrentLight, LightDetails[CurrentLight].Actual, true);
              while (LightDetails[CurrentLight].Target == LightDetails[CurrentLight].Actual)
                if (LightDetails[CurrentLight].HighTarget <= LightDetails[CurrentLight].LowTarget)
                  LightDetails[CurrentLight].Target = random(LightDetails[CurrentLight].HighTarget, LightDetails[CurrentLight].LowTarget);
                else
                  LightDetails[CurrentLight].Target = random(LightDetails[CurrentLight].LowTarget, LightDetails[CurrentLight].HighTarget);
            }
            else
            {
              LightDetails[CurrentLight].Delay = millis() + (LightDetails[CurrentLight].Interval/2) + random((LightDetails[CurrentLight].Interval/2));
              LightDetails[CurrentLight].TimeHigh = LightDetails[CurrentLight].Delay + LightDetails[CurrentLight].TimeLow + random(LightDetails[CurrentLight].TimeLow);
//              LightDetails[CurrentLight].Delay = LightDetails[CurrentLight].NextStepTime + (LightDetails[CurrentLight].Interval/2) + random((LightDetails[CurrentLight].Interval/2));
//              DEBUG_print("TH : ");DEBUG_println(LightDetails[CurrentLight].TimeHigh);
              LightDetails[CurrentLight].Actual = 0;
              if(LightDetails[CurrentLight].HighTarget != 0)
                LightDetails[CurrentLight].Target = LightDetails[CurrentLight].HighTarget;
              else
                LightDetails[CurrentLight].Target = LightDetails[CurrentLight].LowTarget;
              moveLight(CurrentLight, LightDetails[CurrentLight].Actual, true);
            }
            break;

          case 'R':
            if (LightDetails[CurrentLight].Target == LightDetails[CurrentLight].HighTarget)
            {
                LightDetails[CurrentLight].NextStepTime = millis() + LightDetails[CurrentLight].TimeHigh;
                LightDetails[CurrentLight].Actual = LightDetails[CurrentLight].Target;              
                LightDetails[CurrentLight].Target = LightDetails[CurrentLight].LowTarget;
                moveLight(CurrentLight, LightDetails[CurrentLight].Actual, true);
//                LightDetails[CurrentLight].OnTime = LightDetails[CurrentLight].NextStepTime + (LightDetails[CurrentLight].NumOfFlashes/2) + random((LightDetails[CurrentLight].NumOfFlashes/2));
                LightDetails[CurrentLight].OnTime = LightDetails[CurrentLight].NextStepTime + random((LightDetails[CurrentLight].NumOfFlashes));
//               DEBUG_print(millis());DEBUG_print(" : ");DEBUG_println(LightDetails[CurrentLight].OnTime);
            }
            else
            {
              if (millis() < LightDetails[CurrentLight].OnTime)
              {
                LightDetails[CurrentLight].NextStepTime = millis() + (LightDetails[CurrentLight].TimeLow/2) + random((LightDetails[CurrentLight].TimeLow/2));
                LightDetails[CurrentLight].Actual = LightDetails[CurrentLight].Target;
                moveLight(CurrentLight, LightDetails[CurrentLight].Actual, true);
                if (LightDetails[CurrentLight].Target == 0)
                  LightDetails[CurrentLight].Target = LightDetails[CurrentLight].LowTarget;
                else
                  LightDetails[CurrentLight].Target = 0;
              }
              else
              {
                LightDetails[CurrentLight].Delay = millis() + (LightDetails[CurrentLight].Interval/2) + random((LightDetails[CurrentLight].Interval/2));
//                LightDetails[CurrentLight].Delay = millis() + (random(LightDetails[CurrentLight].Interval));
                LightDetails[CurrentLight].Actual = 0;
                LightDetails[CurrentLight].Target = LightDetails[CurrentLight].HighTarget;
                moveLight(CurrentLight, LightDetails[CurrentLight].Actual, true);
              }
            }
            break;

          case 'Q':
//            DEBUG_print("Act : ");DEBUG_print(LightDetails[CurrentLight].Actual);DEBUG_print(" Tgt : ");DEBUG_println(LightDetails[CurrentLight].Target);
            if (LightDetails[CurrentLight].Actual < LightDetails[CurrentLight].Target)
            {
               if (LightDetails[CurrentLight].SpeedUp == 0)
               {
                 LightDetails[CurrentLight].Actual = LightDetails[CurrentLight].Target;
               }
               else
               {
                 LightDetails[CurrentLight].Actual+=100;
               }
               
               if (LightDetails[CurrentLight].Actual >= LightDetails[CurrentLight].Target)
               {
                 LightDetails[CurrentLight].Actual = LightDetails[CurrentLight].Target;
                 if (LightDetails[CurrentLight].LowTarget < LightDetails[CurrentLight].HighTarget)
                   LightDetails[CurrentLight].Target = LightDetails[CurrentLight].LowTarget;
                 else
                   LightDetails[CurrentLight].Target = LightDetails[CurrentLight].HighTarget;
                   
                 LightDetails[CurrentLight].NextStepTime = millis() + LightDetails[CurrentLight].TimeHigh;
               }
               else
               {
                 LightDetails[CurrentLight].NextStepTime = millis() + LightDetails[CurrentLight].SpeedUp;
               }
            }
            else
            {
               if (LightDetails[CurrentLight].SpeedDown == 0)
               {
                 LightDetails[CurrentLight].Actual = LightDetails[CurrentLight].Target;
               }
               else
               {
                 if (LightDetails[CurrentLight].Actual > 100)
                   LightDetails[CurrentLight].Actual-=100;
                 else
                   LightDetails[CurrentLight].Actual = 0;
               }
                                
               if (LightDetails[CurrentLight].Actual <= LightDetails[CurrentLight].Target)
               {
                 // decrement the flashes counter
                 if (LightDetails[CurrentLight].NumOfFlashes > 0)
                 {
                   LightDetails[CurrentLight].CurrentCount--;
//                   DEBUG_print("Flashes : ");DEBUG_print(LightDetails[CurrentLight].NumOfFlashes);DEBUG_print(" Count : ");DEBUG_println(LightDetails[CurrentLight].CurrentCount);
                   if (LightDetails[CurrentLight].CurrentCount == 0)
                   {
                     LightDetails[CurrentLight].CurrentCount = LightDetails[CurrentLight].NumOfFlashes;
                     LightDetails[CurrentLight].NextStepTime = millis() + LightDetails[CurrentLight].Interval;
                   }
                   else
                   {
                     LightDetails[CurrentLight].NextStepTime = millis() + LightDetails[CurrentLight].TimeLow;
                   }
                 }
                 else
                 {
                   LightDetails[CurrentLight].NextStepTime = millis() + LightDetails[CurrentLight].TimeLow;                   
                 }
                 
                 LightDetails[CurrentLight].Actual = LightDetails[CurrentLight].Target;
                 if (LightDetails[CurrentLight].LowTarget < LightDetails[CurrentLight].HighTarget)
                   LightDetails[CurrentLight].Target = LightDetails[CurrentLight].HighTarget;
                 else
                   LightDetails[CurrentLight].Target = LightDetails[CurrentLight].LowTarget;
               }
               else
               {
                 LightDetails[CurrentLight].NextStepTime = millis() + LightDetails[CurrentLight].SpeedDown;
               }
            }

            moveLight(CurrentLight, LightDetails[CurrentLight].Actual, true);

            break;
        }
      }
    }
  }

  CurrentLight = (CurrentLight + 1) % MAXLIGHTS;

  return;
}
