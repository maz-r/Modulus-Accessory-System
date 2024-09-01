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
#define _MIMIC_DISPLAY

#include <Adafruit_NeoPixel.h>

#define MAXLEDS 100
#define DATA_PIN 13
char MIMIC_CONFIG_FILE[40]="MIMIC.CFG";
char MIMIC_DEFAULT_FILE[40]="DEFAULT.CFG";

Adafruit_NeoPixel mimic_leds(MAXLEDS, DATA_PIN, NEO_RGB + NEO_KHZ400);

#define MULTIPLIER  100000L

typedef struct {
  long Red;
  long Green;
  long Blue;
} RGB_type;

typedef struct {
  RGB_type Current;
  RGB_type Target;
  RGB_type Starting;
  RGB_type Step;
  long timeout;
  long step;
  char TransitionType;
  bool FlashType;
  bool CycleEnded;
  bool Moved;
} LEDSTATE;

LEDSTATE  LEDState[MAXLEDS];

long nextStep;
char Arguments[10][256];
char LEDRange[100][10];

void mimicDisplayReadStartupConfig()
{
byte CharCount;
byte LineCount;
char ReadByte;
byte i;
byte LEDNum;
byte ArgCount;
byte ArgChar;
char Args[2][5];
bool endoffile;

  endoffile = false;

  Datafile = LittleFS.open(MIMIC_DEFAULT_FILE, "r");

  if (Datafile)
  {
    while (Datafile.available())
    {
      ReadByte = Datafile.read();
      if (ReadByte == 10 || ReadByte == 13)
      {
        if (CharCount != 0)
        {
          // terminate the line
          Line[CharCount] = 0;
          LineCount++;
          CharCount = 0;
        }
      }
      else
        if (CharCount == 0 && ReadByte == '/')
          while (ReadByte != 10 )
            ReadByte = Datafile.read();
        else
          Line[CharCount++] = ReadByte;
  
      if (LineCount > 0)
      {  
        for(i=0; i<2; i++)
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
        
        LEDNum = atoi(Args[0]);

        LineCount=0;
      }
    }
    Datafile.close();
  }
  else
    DEBUG_println(F("Failed to open defaults"));
}

void mimicDisplayOverwriteDefaultsFile()
{
byte i;
char ThisChar;
byte CurrentState;
byte ThisLED;

  Datafile.close();

  // delete file
  LittleFS.remove(MIMIC_DEFAULT_FILE);
  delay(10);

  // open file for writing
  Datafile = LittleFS.open(MIMIC_DEFAULT_FILE, "w");

  // loop around writing every LED position as it stands
  for (i=0; i<MAXLEDS; i++)
  {
    ThisLED = i;

    Datafile.write((ThisLED/10)+'0');
    ThisLED %= 10;
    Datafile.write(ThisLED+'0');

    Datafile.write(',');
  }

  // close the file to flush the written data out
  Datafile.close();

  delay(10);

  Datafile = LittleFS.open(MIMIC_CONFIG_FILE, "r");
}

void parseColon(int results[], char Argument[])
{
int i;
int start;
int argc;
int copyLED;
int stringLength;

  for (i=0; i<3; i++)
  {
    results[i] = -1;
  }

  start = 0;
  argc = 0;

  if (Argument[0] == 'C')
  {
    copyLED = atoi(&Argument[2]);
    results[0] = LEDState[copyLED].Current.Red   / MULTIPLIER;
    results[1] = LEDState[copyLED].Current.Green / MULTIPLIER;
    results[2] = LEDState[copyLED].Current.Blue  / MULTIPLIER;
  }
  else
  {
    stringLength = strlen(Argument);
    for (i=0; i<=stringLength; i++)
    {
      if (Argument[i] == ':' || Argument[i] == 0)
      {
        Argument[i] = 0;
        if (start != i)
        {
          results[argc] = atoi(&Argument[start]);
        }
        argc++;
        start = i + 1;
      }
    }
  }
}

int parsePlus(char results[21][10], char Argument[])
{
int i;
int j;
int start;
int argc;
int copyLED;
int stringLength;

  start = 0;
  argc = 0;
  j = 0;

  stringLength = strlen(Argument);

  for (i=0; i<=stringLength; i++)
  {
    if (Argument[i] == '+' || Argument[i] == 0)
    {
      results[argc][j] = 0;
      argc++;
      j = 0;
    }
    else
      results[argc][j++] = Argument[i];
  }

  return argc;
}

int parseComma(char inputString[])
{
int start;
int index;
int i;
int j;
int stringLength;
char tempString[80];

  start = 0;
  index = 0;

  stringLength = strlen(inputString);
  for (i=0; i<=stringLength; i++)
  {
    if (inputString[i] == ',' || inputString[i] == 0 || inputString[i] == '>')
    {
      inputString[i] = 0;
      strcpy(Arguments[index], &inputString[start]);
      start = i+1;
      index++;
    }
  }

  for (i=1; i<=index;i++)
  {
    tempString[0] = 0;
    // do substitution if required....
    for(j=0; j<strlen(Arguments[i]); j++)
    {
      if (Arguments[i][j] == '$')
      {
        // substitute the letter for saved variable
        DEBUG_print("Substituting ");DEBUG_print(Arguments[i][j+1]);DEBUG_print(" with ");
        sprintf(tempString,"%s%s",tempString, Variables[Arguments[i][++j] - 'A']);
        DEBUG_println(tempString);
      }
      else
        sprintf(tempString,"%s%c",tempString, Arguments[i][j]);
    }

    if (tempString[0] != 0)
      strcpy (Arguments[i], tempString);
  }

  return index;
}

void duplicateLEDDetails(int from, int to)
{

  if (LEDState[from].TransitionType == ' ')
    LEDState[to].TransitionType = 'S';
  else
    LEDState[to].TransitionType = LEDState[from].TransitionType;

  LEDState[to].timeout = LEDState[from].timeout;
  LEDState[to].step = LEDState[from].step;
  LEDState[to].FlashType = LEDState[from].FlashType;
  LEDState[to].CycleEnded = LEDState[from].CycleEnded;
  LEDState[to].Target.Red   = LEDState[from].Target.Red;
  LEDState[to].Target.Green = LEDState[from].Target.Green;
  LEDState[to].Target.Blue  = LEDState[from].Target.Blue;
  LEDState[to].Current.Red   = LEDState[from].Current.Red;
  LEDState[to].Current.Green = LEDState[from].Current.Green;
  LEDState[to].Current.Blue  = LEDState[from].Current.Blue;
  LEDState[to].Starting.Red   = LEDState[from].Starting.Red;
  LEDState[to].Starting.Green = LEDState[from].Starting.Green;
  LEDState[to].Starting.Blue  = LEDState[from].Starting.Blue;
  LEDState[to].Step.Red   = LEDState[from].Step.Red;
  LEDState[to].Step.Green = LEDState[from].Step.Green;
  LEDState[to].Step.Blue  = LEDState[from].Step.Blue;

  return;
}

void MimicProcessLine(char inputString[], bool TimeMessage, bool Override, char TimeRange[], uint8_t CurrentHour, uint8_t CurrentMinutes)
{
int  i;
int  j;
int  ArgCount;
int  values[3];
int  numRanges;
RGB_type ColourSet1;
RGB_type ColourSet2;
RGB_type Colour1;
RGB_type Colour2;
long duplicateTargetRed;
long duplicateTargetGreen;
long duplicateTargetBlue;
int  startLED;
int  endLED;
bool stepLED;
bool alternateLEDs;
long timeout;
int  duplicateLED;
char TransitionType;
long numberofsteps;

  ArgCount = parseComma(inputString);

  if (Arguments[0][0] == '=')
  {
    // remember the value we are told to
    if ((Arguments[1][0] - 'A') >=0 && (Arguments[1][0] - 'A') <= 25)
      strcpy(Variables[Arguments[1][0] - 'A'], Arguments[2]);
  }
  else
  {
    TransitionType = Arguments[0][0];

    // get colour 1
    parseColon(values, Arguments[2]);
    ColourSet1.Red = values[0];
    ColourSet1.Green = values[1];
    ColourSet1.Blue = values[2];
  
    Colour1.Red = ColourSet1.Red;
    Colour1.Green = ColourSet1.Green;
    Colour1.Blue = ColourSet1.Blue;
  
    // get colour 2
    parseColon(values, Arguments[3]);
    ColourSet2.Red = values[0];
    ColourSet2.Green = values[1];
    ColourSet2.Blue = values[2];
  
    Colour2.Red = ColourSet2.Red;
    Colour2.Green = ColourSet2.Green;
    Colour2.Blue = ColourSet2.Blue;

    timeout = atoi(Arguments[4]);
    if (timeout == -1)
        timeout = 10;
  
    duplicateLED = atoi(Arguments[5]);

    //circulate the LEDs in the list
    // get LED Range
    numRanges = parsePlus(LEDRange, Arguments[1]);

    if (LEDRange[0][0] == 'A')
      alternateLEDs = true;
    else
      alternateLEDs = false;

    stepLED = true;
    
    for (j=1; j<numRanges; j++)
    {
      parseColon(values, LEDRange[j]);
      startLED = values[0];
      endLED = values[1];
      
      if (endLED == -1)
        endLED = startLED;
        
      for (i=startLED; i<=endLED; i++)
      {
        if (!TimeMessage || (TimeMessage && !LEDState[i].Moved) || Override)
        {
          LEDState[i].Moved = true;
          if (alternateLEDs)
          {
            if (stepLED)
            {
              Colour1.Red = ColourSet1.Red;
              Colour1.Green = ColourSet1.Green;
              Colour1.Blue = ColourSet1.Blue;
            
              Colour2.Red = ColourSet2.Red;
              Colour2.Green = ColourSet2.Green;
              Colour2.Blue = ColourSet2.Blue;
    
              stepLED = false;
            }
            else
            {
              Colour1.Red = ColourSet2.Red;
              Colour1.Green = ColourSet2.Green;
              Colour1.Blue = ColourSet2.Blue;
            
              Colour2.Red = ColourSet1.Red;
              Colour2.Green = ColourSet1.Green;
              Colour2.Blue = ColourSet1.Blue;
    
              stepLED = true;
            }
          }
    
          switch (TransitionType)
          {
            case 'D':
              if (i != duplicateLED && duplicateLED != -1)
              {
                duplicateLEDDetails(duplicateLED, i);
              }
              break;
              
            case 'Y':
              if (i != duplicateLED && duplicateLED != -1)
              {
                duplicateTargetRed = Colour1.Red * MULTIPLIER;
                duplicateTargetGreen = Colour1.Green * MULTIPLIER;
                duplicateTargetBlue = Colour1.Blue * MULTIPLIER;
      
                if (LEDState[i].Target.Red == duplicateTargetRed &&
                    LEDState[i].Target.Green == duplicateTargetGreen &&
                    LEDState[i].Target.Blue == duplicateTargetBlue)
                {
                  duplicateLEDDetails(duplicateLED, i);
                }
              }
              break;
              
            case 'N':
              if (i != duplicateLED && duplicateLED != -1)
              {
                duplicateTargetRed = Colour1.Red * MULTIPLIER;
                duplicateTargetGreen = Colour1.Green * MULTIPLIER;
                duplicateTargetBlue = Colour1.Blue * MULTIPLIER;
      
                if (LEDState[i].Target.Red != duplicateTargetRed ||
                    LEDState[i].Target.Green != duplicateTargetGreen ||
                    LEDState[i].Target.Blue != duplicateTargetBlue)
                {
                  duplicateLEDDetails(duplicateLED, i);
                }
              }
              break;
              
            case 'S':
              LEDState[i].TransitionType = TransitionType;
              if (Colour1.Red != -1)
                LEDState[i].Target.Red = Colour1.Red * MULTIPLIER;
      
             if (Colour1.Green != -1)
                LEDState[i].Target.Green = Colour1.Green * MULTIPLIER;
              
              if (Colour1.Blue != -1)
                LEDState[i].Target.Blue = Colour1.Blue * MULTIPLIER;
              break;
      
            case 'F':
              LEDState[i].TransitionType = TransitionType;
              
              if (Colour1.Red != -1)
                LEDState[i].Current.Red = Colour1.Red * MULTIPLIER;
              
              if (Colour1.Green != -1)
                LEDState[i].Current.Green = Colour1.Green * MULTIPLIER;
              
              if (Colour1.Blue != -1)
                LEDState[i].Current.Blue = Colour1.Blue * MULTIPLIER;
              
              if (Colour2.Red != -1)
                LEDState[i].Target.Red = Colour2.Red * MULTIPLIER;
              else
                LEDState[i].Target.Red = LEDState[i].Current.Red;
              
              if (Colour2.Green != -1)
                LEDState[i].Target.Green = Colour2.Green * MULTIPLIER;
              else
                LEDState[i].Target.Green = LEDState[i].Current.Green;
              
              if (Colour2.Blue != -1)
                LEDState[i].Target.Blue = Colour2.Blue * MULTIPLIER;
              else
                LEDState[i].Target.Blue = LEDState[i].Current.Blue;
    
              LEDState[i].step = timeout;
              LEDState[i].timeout = 0L;
              
              break;
    
            case 'R':
              if (LEDState[i].TransitionType == ' ')
              {
                if (LEDState[i].Current.Red == Colour1.Red * MULTIPLIER &&
                    LEDState[i].Current.Green == Colour1.Green * MULTIPLIER &&
                    LEDState[i].Current.Blue == Colour1.Blue * MULTIPLIER
                   )
                {
                  LEDState[i].TransitionType = TransitionType;
      
                  if (Colour2.Red != -1)
                    LEDState[i].Target.Red = Colour2.Red * MULTIPLIER;
                  else
                    LEDState[i].Target.Red = LEDState[i].Current.Red;
                  
                  if (Colour2.Green != -1)
                    LEDState[i].Target.Green = Colour2.Green * MULTIPLIER;
                  else
                    LEDState[i].Target.Green = LEDState[i].Current.Green;
                  
                  if (Colour2.Blue != -1)
                    LEDState[i].Target.Blue = Colour2.Blue * MULTIPLIER;
                  else
                    LEDState[i].Target.Blue = LEDState[i].Current.Blue;
        
                  LEDState[i].step = timeout;
                  LEDState[i].timeout = 0L;
                }
              }
              break;
              
            case 'M':
              LEDState[i].TransitionType = TransitionType;
              
              if (Colour1.Red != -1)
                LEDState[i].Current.Red = Colour1.Red * MULTIPLIER;
              
              if (Colour1.Green != -1)
                LEDState[i].Current.Green = Colour1.Green * MULTIPLIER;
              
              if (Colour1.Blue != -1)
                LEDState[i].Current.Blue = Colour1.Blue * MULTIPLIER;
              
              if (Colour2.Red != -1)
                LEDState[i].Target.Red = Colour2.Red * MULTIPLIER;
              else
                LEDState[i].Target.Red = LEDState[i].Current.Red;
              
              if (Colour2.Green != -1)
                LEDState[i].Target.Green = Colour2.Green * MULTIPLIER;
              else
                LEDState[i].Target.Green = LEDState[i].Current.Green;
              
              if (Colour2.Blue != -1)
                LEDState[i].Target.Blue = Colour2.Blue * MULTIPLIER;
              else
                LEDState[i].Target.Blue = LEDState[i].Current.Blue;
      
              // now do the maths...
              numberofsteps = timeout;
              if (timeout > 0L)
              {
                LEDState[i].Step.Red   = (int)((long)(LEDState[i].Target.Red   - LEDState[i].Current.Red)   / numberofsteps);
                LEDState[i].Step.Green = (int)((long)(LEDState[i].Target.Green - LEDState[i].Current.Green) / numberofsteps);
                LEDState[i].Step.Blue  = (int)((long)(LEDState[i].Target.Blue  - LEDState[i].Current.Blue)  / numberofsteps);
              }
              
              LEDState[i].step = timeout;
              LEDState[i].timeout = 0L;
      
              break;
              
            case 'C':
              LEDState[i].TransitionType = TransitionType;
              LEDState[i].CycleEnded = false;
              
              if (Colour1.Red != -1)
                LEDState[i].Current.Red = Colour1.Red * MULTIPLIER;
              
              if (Colour1.Green != -1)
                LEDState[i].Current.Green = Colour1.Green * MULTIPLIER;
              
              if (Colour1.Blue != -1)
                LEDState[i].Current.Blue = Colour1.Blue * MULTIPLIER;
    
              
              LEDState[i].Target.Red = Colour2.Red * MULTIPLIER;
              LEDState[i].Target.Green = Colour2.Green * MULTIPLIER;
              LEDState[i].Target.Blue = Colour2.Blue * MULTIPLIER;
      
              // now do the maths...
      
              // we want the number of steps to get from a to b in n 10ths of a second
              // n = timeout in 10ths, so (B-A) * (timeout * 10L)
              numberofsteps = timeout;
              if (timeout > 0L)
              {
                LEDState[i].Step.Red   = (LEDState[i].Target.Red   - LEDState[i].Current.Red)   / numberofsteps;
                LEDState[i].Step.Green = (LEDState[i].Target.Green - LEDState[i].Current.Green) / numberofsteps;
                LEDState[i].Step.Blue  = (LEDState[i].Target.Blue  - LEDState[i].Current.Blue)  / numberofsteps;
                LEDState[i].Starting.Red   = LEDState[i].Current.Red;
                LEDState[i].Starting.Green = LEDState[i].Current.Green;
                LEDState[i].Starting.Blue  = LEDState[i].Current.Blue;
              }
                
              LEDState[i].step = timeout;
              LEDState[i].timeout = 0L;
      
              break;
          }
        }
      }
    }
  }
}

void mimicDisplaySetup()
{
int i;
int j;

  mimic_leds.begin();
  mimic_leds.clear();

  nextStep = millis() + 10L;

  LastTime = 0;
  InCount = 0;

  // ALL OFF BY DEFAULT
  for (i=0;i<MAXLEDS;i++)
  {
    LEDState[i].Target.Red = 0L;
    LEDState[i].Target.Green = 0L;
    LEDState[i].Target.Blue = 0L;
    LEDState[i].Current.Red = 0L;
    LEDState[i].Current.Green = 0L;
    LEDState[i].Current.Blue = 0L;
    LEDState[i].TransitionType = ' ';
    LEDState[i].timeout = 0L;
    LEDState[i].step = 0L;
  }

  // and finally, open the config file to compare to incoming messages
  Datafile = LittleFS.open(MIMIC_CONFIG_FILE, "r");
  if (!Datafile)
  {
    DEBUG_println("Failed to open datafile");
  }

  nextStep = 0L;

  return;
}

void mimicDisplaySerial()
{
int  val;
char TempChar;
long ThisTime;
int  LEDNum;
int  LEDTarget;
int  i;

  while(ReceiveQueueSize() > 0)
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

          LastTime = ThisTime;
          for (i=0; i<MAXLEDS; i++)
            LEDState[i].Moved = false;

          ProcessFile(&InputStr[2], true, MimicProcessLine);
        }
      }

      // if it is a remote config message....
      if (InputStr[1] == 'C' && InputStr[2] == 'T')
      {
        MimicProcessLine(&InputStr[8], false, true, (char *)NULL, 0, 0);
      }

      // if it is a remote config message....
      if (InputStr[1] == 'C' && InputStr[2] == 'Q')
      {
        ProcessSearchLine(0, 4, &InputStr[3]);
      }
      
      if (InputStr[1]=='W' && InputStr[2]=='C')
      {
        OverwriteConfigFile(MIMIC_CONFIG_FILE);
      }

      if (InputStr[1]=='W' && InputStr[2]=='+')
      {
        AddToConfigFile(MIMIC_CONFIG_FILE);
      }

      if (InputStr[1]=='W' && InputStr[2]=='E')
      {
        ReadConfigFileTriggers();
      }

      if (InputStr[1]=='W' && InputStr[2]=='D')
      {
        SendConfigurationFile();
      }

      if (InputStr[1]=='I' && InputStr[2]=='D')
      {
        flashID_LED();
      }

      if (InputStr[1]=='S' || InputStr[1]=='U')
      {
        ProcessFile(&InputStr[1], false, MimicProcessLine);
      }
    }
  }
}

void mimicDisplayLoop()
{
int  i;
int  val;
int  LEDrow;
int  LEDcol;
bool changed;
long startTime;

  if (millis() > nextStep)
  {
    nextStep = millis() + 10L;

    changed = false;
    for (i=0; i<MAXLEDS;i++)
    {
      switch (LEDState[i].TransitionType)
      {
        case 'S':
        case 'R':
          LEDState[i].Current.Red = LEDState[i].Target.Red;
          LEDState[i].Current.Green = LEDState[i].Target.Green;
          LEDState[i].Current.Blue = LEDState[i].Target.Blue;
          LEDState[i].TransitionType = ' ';
          changed = true;
          break;
          
        case 'F':
          if (millis() > LEDState[i].timeout)
          {
            LEDState[i].Starting.Red = LEDState[i].Current.Red;
            LEDState[i].Starting.Green = LEDState[i].Current.Green;
            LEDState[i].Starting.Blue = LEDState[i].Current.Blue;
            LEDState[i].Current.Red = LEDState[i].Target.Red;
            LEDState[i].Current.Green = LEDState[i].Target.Green;
            LEDState[i].Current.Blue = LEDState[i].Target.Blue;
            LEDState[i].Target.Red = LEDState[i].Starting.Red;
            LEDState[i].Target.Green = LEDState[i].Starting.Green;
            LEDState[i].Target.Blue = LEDState[i].Starting.Blue;
            LEDState[i].timeout = millis() + LEDState[i].step * 10L;
            changed = true;
          }
          break;
          
        case 'M':
          if (((LEDState[i].Step.Red < 0L) && (LEDState[i].Current.Red > LEDState[i].Target.Red)) ||
              ((LEDState[i].Step.Red > 0L) && (LEDState[i].Current.Red < LEDState[i].Target.Red)))
          {
              LEDState[i].Current.Red += LEDState[i].Step.Red;
              changed = true;
          }
          else
          {
              LEDState[i].Current.Red = LEDState[i].Target.Red;
          }
          
          if (((LEDState[i].Step.Green < 0L) && (LEDState[i].Current.Green > LEDState[i].Target.Green)) ||
              ((LEDState[i].Step.Green > 0L) && (LEDState[i].Current.Green < LEDState[i].Target.Green)))
          {
              LEDState[i].Current.Green += LEDState[i].Step.Green;
              changed = true;
          }
          else
          {
              LEDState[i].Current.Green = LEDState[i].Target.Green;
          }
          
          if (((LEDState[i].Step.Blue < 0L) && (LEDState[i].Current.Blue > LEDState[i].Target.Blue)) ||
              ((LEDState[i].Step.Blue > 0L) && (LEDState[i].Current.Blue < LEDState[i].Target.Blue)))
          {
              LEDState[i].Current.Blue += LEDState[i].Step.Blue;
              changed = true;
          }
          else
          {
              LEDState[i].Current.Blue = LEDState[i].Target.Blue;
          }

          LEDState[i].Current.Red   = constrain(LEDState[i].Current.Red,   0L, (255L * MULTIPLIER));
          LEDState[i].Current.Green = constrain(LEDState[i].Current.Green, 0L, (255L * MULTIPLIER));
          LEDState[i].Current.Blue  = constrain(LEDState[i].Current.Blue,  0L, (255L * MULTIPLIER));
 
          if (!changed)
          {
            LEDState[i].TransitionType = ' ';
            changed = true;
          }
          break;

        case 'C':
          LEDState[i].Current.Red += LEDState[i].Step.Red;
          if (LEDState[i].Step.Red > 0)
          {
            if (LEDState[i].Current.Red > LEDState[i].Target.Red)
              LEDState[i].Current.Red = LEDState[i].Target.Red;
          }
          else
          {
            if (LEDState[i].Current.Red < LEDState[i].Target.Red)
              LEDState[i].Current.Red = LEDState[i].Target.Red;            
          }
          
          LEDState[i].Current.Green += LEDState[i].Step.Green;
          if (LEDState[i].Step.Green > 0)
          {
            if (LEDState[i].Current.Green > LEDState[i].Target.Green)
              LEDState[i].Current.Green = LEDState[i].Target.Green;
          }
          else
          {
            if (LEDState[i].Current.Green < LEDState[i].Target.Green)
              LEDState[i].Current.Green = LEDState[i].Target.Green;            
          }
          
          LEDState[i].Current.Blue += LEDState[i].Step.Blue;
          if (LEDState[i].Step.Blue > 0)
          {
            if (LEDState[i].Current.Blue > LEDState[i].Target.Blue)
              LEDState[i].Current.Blue = LEDState[i].Target.Blue;
          }
          else
          {
            if (LEDState[i].Current.Blue < LEDState[i].Target.Blue)
              LEDState[i].Current.Blue = LEDState[i].Target.Blue;            
          }
          
          changed = true;

          if (LEDState[i].Current.Red/MULTIPLIER   == LEDState[i].Target.Red/MULTIPLIER &&
              LEDState[i].Current.Green/MULTIPLIER == LEDState[i].Target.Green/MULTIPLIER &&
              LEDState[i].Current.Blue/MULTIPLIER  == LEDState[i].Target.Blue/MULTIPLIER)
          {
            if (!LEDState[i].CycleEnded)
            {
              // we've made it to one end of the cyle, so swap everything around so we now go the other way
              LEDState[i].CycleEnded = true;
              LEDState[i].Target.Red = LEDState[i].Starting.Red;
              LEDState[i].Starting.Red = LEDState[i].Current.Red;
              LEDState[i].Step.Red = 0L - LEDState[i].Step.Red;
              LEDState[i].Target.Green = LEDState[i].Starting.Green;
              LEDState[i].Starting.Green = LEDState[i].Current.Green;
              LEDState[i].Step.Green = 0L - LEDState[i].Step.Green;
              LEDState[i].Target.Blue = LEDState[i].Starting.Blue;
              LEDState[i].Starting.Blue = LEDState[i].Current.Blue;
              LEDState[i].Step.Blue = 0L - LEDState[i].Step.Blue;
            }
          }
          else
            LEDState[i].CycleEnded = false;
          break;

        default:
          break;
      }
    }

    if (changed)
    {
      for(int i = 0; i < MAXLEDS; i++) 
      {
        mimic_leds.setPixelColor(i, mimic_leds.Color(LEDState[i].Current.Red/MULTIPLIER, LEDState[i].Current.Green/MULTIPLIER, LEDState[i].Current.Blue/MULTIPLIER));
      }

      for(int i = 0; i < 3; i++) 
      {
        mimic_leds.show();
        delay(1);
      }
    }
  }

  return;
}
