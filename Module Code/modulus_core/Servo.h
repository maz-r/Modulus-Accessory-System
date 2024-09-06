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
#define _SERVO

#define MAXSERVOS 16 
#define SERVOMIN  100  // this is the 'minimum' pulse length count (out of 4096)
#define SERVOMAX  600  // this is the 'maximum' pulse length count (out of 4096)

#define SERVO_STEPS 128

#define PROGRAM_CONF_PIN 3
#define PROGRAM_PIN 4
#define CHIPSELECT  10
#define POWER_CONTROL_PIN D8

char SERVO_CONFIG_FILE[]="SERVO.CFG";
char SERVO_DEFAULT_FILE[]="DEFAULT.CFG";

Adafruit_PWMServoDriver Servos = Adafruit_PWMServoDriver();

typedef struct {
  char     AccessoryType;
  int16_t  Target;
  int16_t  OldTarget;
  int16_t  Actual;
  int16_t  StartPos;
  int16_t  Difference;
  int16_t  Speed;
  uint32_t Delay;
  uint32_t NextStepTime;
  int16_t  NextWaveStep;
  uint8_t  Waveform;
  bool     Moved;
  bool     Matched;
  int8_t   BounceAmplitude;
  int8_t   BounceSpeed;
  int8_t   NumberOfBounces;
  int16_t  NextBounceStep;
  uint8_t  BounceWaveform;
  uint8_t  PulseDuration;
  char     Direction;
  char     ServoStartMessage[4];
  char     ServoEndMessage[4];
} SERVO_DETAILS;

uint8_t       CurrentServo;
long          nextPulseTime;
bool          SolenoidPowered;
bool          nothingMoved;
SERVO_DETAILS servoDetails[MAXSERVOS];
SERVO_DETAILS newServoDetails[MAXSERVOS];
uint8_t       bounceStarts[5] = {0,17,30,41,50};
int16_t       Waveforms[5][SERVO_STEPS]={
                    {0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,-1},
                    {0,0,0,0,0,1,1,2,2,3,4,4,5,6,7,8,9,10,12,13,14,15,17,18,20,22,23,25,27,29,31,33,35,37,39,42,44,46,49,51,54,57,59,62,65,68,70,73,76,78,81,83,85,88,90,92,94,96,98,100,102,104,105,107,109,110,112,113,114,115,117,118,119,120,121,122,123,123,124,125,125,126,126,127,127,127,127,127,128,-1},
                    {0,3,5,8,11,14,16,19,21,24,26,28,31,33,35,37,39,41,43,44,46,48,49,51,52,53,55,56,57,58,59,60,61,62,63,63,64,64,65,65,66,66,66,66,66,67,67,67,67,67,67,67,68,68,69,69,70,70,71,72,73,74,75,76,77,78,79,81,82,84,85,87,89,90,92,94,96,98,100,102,104,107,109,112,114,117,119,122,125,128,-1},
                    {0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,1,1,2,2,2,3,3,3,4,4,5,5,6,6,7,8,8,9,10,11,12,13,13,14,16,17,18,19,20,21,23,24,26,27,29,30,32,34,36,37,39,41,43,46,48,50,52,55,57,60,62,65,68,71,74,77,80,83,86,89,93,96,100,104,107,111,115,119,123,128,-1},
                    {0,1,1,2,2,4,4,4,6,8,8,8,10,12,14,16,18,20,22,26,28,32,36,40,44,48,52,56,52,52,46,40,34,34,34,34,34,34,34,34,34,42,44,46,48,50,52,54,56,60,64,68,72,78,84,90,98,106,114,124,132,140,144,140,136,134,132,130,128,-1}
              };

int8_t        BounceWaveforms[2][65]={
                    {0,15,28,39,48,55,60,63,64,63,60,55,48,39,28,15,0,0,11,20,27,32,35,36,35,32,27,20,11,0,0,9,16,21,24,25,24,21,16,9,0,0,7,12,15,16,15,12,7,0,0,5,8,9,8,5,0,-1,-1,-1,-1,-1,-1,-1,-1},
                    {0,15,28,39,48,55,60,63,64,63,60,55,48,39,28,15,0,-11,-20,-27,-32,-35,-36,-35,-32,-27,-20,-11,0,9,16,21,24,25,24,21,16,9,0,-7,-12,-15,-16,-15,-12,-7,0,5,8,9,8,5,0,-4,-6,-4,0,-1,-1,-1,-1,-1,-1,-1,-1}
              };

void GenerateFullMessage(char *fullmessage, char *message)
{
uint8_t i;
uint8_t j;

  i = 0;
  j = 0;
  fullmessage[i++] = SOM;
  fullmessage[i++] = message[j++];
  fullmessage[i++] = message[j++];
  fullmessage[i++] = message[j++];
  fullmessage[i++] = message[j++];
  fullmessage[i++] = EOM;
  fullmessage[i++] = 0;
  
  return;
}

void AddToOutputQueue(char *message, bool retain)
{
uint8_t i;
char    fullmessage[12];
char    bankNumber;
char    number[3];

  i = 0;

  bankNumber = message[1];
  number[0] = message[2];
  number[1] = message[3];
  number[2] = 0;
  GenerateFullMessage(fullmessage, message);
  publishMessage(bankNumber, number, fullmessage, retain);

  return;
}

bool ServoReadStartupConfig()
{
uint8_t CharCount;
uint8_t LineCount;
char    ReadByte;
uint8_t i;
uint8_t retval;
uint8_t ServoNum;
uint8_t ArgCount;
uint8_t ArgChar;
char    Args[4][10];
bool    endoffile;

  endoffile = false;

  Configfile = LittleFS.open(SERVO_DEFAULT_FILE, "r");

  CharCount = 0;

  if (Configfile)
  {
    DEBUG_println("Reading startup Servo pos...");
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
            if(Line[i] == ',' || Line[i] == 10 || Line[i] == 13 || Line[i] == 0)
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

          ServoNum = atoi(Args[0]);
          servoDetails[ServoNum].Actual = atoi(Args[1]);
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

    retval = false;
  }

  return retval;
}

void ServoWriteDefaultsFile()
{
uint8_t  i;
char     ThisChar;
uint16_t CurrentPos;
uint8_t  ThisServo;

  // open file for writing i.e. create new file
  Configfile = LittleFS.open(SERVO_DEFAULT_FILE, "w");

  // loop around writing every servo position as it stands
  for (i=0; i<MAXSERVOS; i++)
  {
    ThisServo = i;

    Configfile.write((ThisServo/10)+'0');
    ThisServo %= 10;
    Configfile.write(ThisServo+'0');

    Configfile.write(',');

   if (servoDetails[i].AccessoryType != 'S')
     CurrentPos == 0;
   else
      CurrentPos = servoDetails[i].Actual;

    Configfile.write((CurrentPos/1000)+'0');
    CurrentPos %= 1000;
    Configfile.write((CurrentPos/100)+'0');
    CurrentPos %= 100;
    Configfile.write((CurrentPos/10)+'0');
    CurrentPos %= 10;
    Configfile.write(CurrentPos+'0');
    Configfile.println();
  }

  // close the file to flush the written data out
  Configfile.close();

  return;
}

void ServoProcessLine(char *LinetoProcess, bool TimeMessage, bool Override, char *BestTimeMatch, uint8_t CurrentHour, uint8_t CurrentMinutes)
{
uint16_t LineCount;
uint8_t  CharCount;
char     ReadByte;
uint8_t  ServoNum;
uint16_t ServoDelay;
char     StartMessage[5];
char     EndMessage[5];
char     Direction;
uint16_t ServoTarget;
uint16_t ServoSpeed;
uint8_t  PulseDuration;
uint8_t  i;
uint8_t  j;
uint8_t  ArgCount;
uint8_t  ArgChar;
char     Args[20][10];
char     tempString[40];
char     Type;

LineCount = 0;
CharCount = 0;

  for(i=0; i<20; i++)
    Args[i][0] = 0;
  
  StartMessage[0] = 0;
  EndMessage[0] = 0;
  // extract the servo details
  ArgCount = 0;
  ArgChar = 0;

  // Serial.print("Message = ");Serial.println(LinetoProcess);
  
  for (i=0; i < strlen(LinetoProcess); i++)
  {
    if(LinetoProcess[i] == ',' || LinetoProcess[i] == 0)
    {
      Args[ArgCount][ArgChar] = 0;
      // Serial.print(ArgCount);Serial.print(" = ");Serial.println(Args[ArgCount]);
      ArgCount++;
      ArgChar = 0;
    }
    else
      if(LinetoProcess[i] != 10 && LinetoProcess[i] != 13)
      {
        Args[ArgCount][ArgChar] = LinetoProcess[i];
        ArgChar++;
      }
  }
  Args[ArgCount][ArgChar] = 0;

  for (i=1; i<=ArgCount;i++)
  {
    tempString[0] = 0;
    // do substitution if required....
    for(j=0; j<strlen(Args[i]); j++)
    {
      if (Args[i][j] == '$')
      {
        // substitute the letter for saved variable
        DEBUG_print("Substituting ");DEBUG_print(Args[i][j+1]);DEBUG_print(" with ");
        sprintf(tempString,"%s%s",tempString, Variables[Args[i][++j] - 'A']);
        DEBUG_println(tempString);
      }
      else
        sprintf(tempString, "%s%c", tempString, Args[i][j]);
    }

    if (tempString[0] != 0)
      strcpy (Args[i], tempString);
  }
  
  if (Args[0][0] == '=')
  {
    // remember the value we are told to
    DEBUG_print("Saving ");DEBUG_print(Args[2]);DEBUG_print(" as ");DEBUG_println(Args[1]);
    if ((Args[1][0] - 'A') >=0 && (Args[1][0] - 'A') <= 25)
      strcpy(Variables[Args[1][0] - 'A'], Args[2]);
  }
  else
  {
    ServoNum = atoi(Args[0]);
    ServoDelay = atoi(Args[1]);
    strcpy (StartMessage, Args[2]);

    Type = Args[3][0];
    if (Type == 'S')
      ServoTarget = map(atoi(&Args[3][1]),1,180,SERVOMIN,SERVOMAX);

    if (Type == 'T')
    {
      Direction = Args[3][1];
      if (Direction == 'L')
        ServoTarget = map(10,1,180,SERVOMIN,SERVOMAX);
      else
        ServoTarget = map(170,1,180,SERVOMIN,SERVOMAX);
      PulseDuration = atoi(&Args[3][2]);
    }
 
    if (Type == 'K' || Type == 'P')
    {
      ServoTarget = 4095;
      Direction = Args[3][1];
      PulseDuration = atoi(&Args[3][2]);
    }
 
    if (Type == 'R')
    {
      ServoTarget = 0;
      Direction = Args[3][1];
      if (Direction == '0' || Direction == '2')
      {
        ServoTarget = 4095;
      }
      if (Direction == '2')
        PulseDuration = atoi(&Args[3][2]);
      else
        PulseDuration = -1;
    }
 
    ServoSpeed = atoi(Args[4]);

    strcpy (EndMessage, Args[5]);

    servoDetails[ServoNum].Matched = true;

    if ((servoDetails[ServoNum].Actual != ServoTarget && newServoDetails[ServoNum].Target != ServoTarget) || Type == 'R')
    {
      DEBUG_println("New Target");
      newServoDetails[ServoNum].Waveform = atoi(Args[6]);
      if(Args[7][0] == 'R' || Args[7][0] == 'r')
        newServoDetails[ServoNum].NumberOfBounces = random(6);
      else
        newServoDetails[ServoNum].NumberOfBounces = atoi(Args[7]);
      newServoDetails[ServoNum].BounceWaveform = atoi(Args[8]);
      newServoDetails[ServoNum].BounceAmplitude = atoi(Args[9]) * 8;
      newServoDetails[ServoNum].BounceSpeed = atoi(Args[10]);
      newServoDetails[ServoNum].Direction = Direction;
      newServoDetails[ServoNum].AccessoryType = Type;
      newServoDetails[ServoNum].PulseDuration = PulseDuration;

      newServoDetails[ServoNum].Target = ServoTarget;

      newServoDetails[ServoNum].Speed = ServoSpeed;

      if (StartMessage[0] != 0)
      {
        for (i=0; i<4; i++)
          newServoDetails[ServoNum].ServoStartMessage[i] = StartMessage[i];
      }
      else
        newServoDetails[ServoNum].ServoStartMessage[0] = 0;

      if (EndMessage[0] != 0)
      {
        for (i=0; i<4; i++)
          newServoDetails[ServoNum].ServoEndMessage[i] = EndMessage[i];
      }
      else
        newServoDetails[ServoNum].ServoEndMessage[0] = 0;

      if (ServoDelay != 0)
        newServoDetails[ServoNum].Delay = millis() + (ServoDelay * 100L);
      else
        newServoDetails[ServoNum].Delay = 0L;
    }
    else
    {
      newServoDetails[ServoNum].Target = -1;
      servoDetails[ServoNum].Delay = 0L;
      if (EndMessage[0] != 0)
      {
        //send finish message
        AddToOutputQueue(EndMessage, true);
        EndMessage[0] = 0;
      }
    }
  }

  LineCount = 0;
  CharCount = 0;

  return;  
}

void moveServo(uint8_t servoNum, char Type, uint16_t newPosition)
{
  if (newPosition >= SERVOMIN && newPosition <= SERVOMAX)
    Servos.setPWM(servoNum, 0 , newPosition);
  else
    if (newPosition == 0)
      Servos.setPWM(servoNum, 0, 4096);
    else
      Servos.setPWM(servoNum, 4096, 0);

  return;
}

void servoSetup()
{
int     i;
long    pwmDelay;
bool    StartupReadOK;

  CurrentServo = 0;

  LastTime = 0;
  InCount = 0;
  QueueHead = 0;
  QueueTail = 0;
  nextPulseTime = 0L;
  SolenoidPowered = false;

  Servos.begin();
  
  Servos.setPWMFreq(60);  // Analog servos run at ~60 Hz updates
  strcpy(SERVO_CONFIG_FILE,"SERVO.CFG");
  strcpy(SERVO_DEFAULT_FILE,"DEFAULT.CFG");

  // set up random seed
  randomSeed(millis());

  // now read the DEFAULTS file from the config file
  StartupReadOK = ServoReadStartupConfig();

  for(i=0; i<SERVO_STEPS; i++)
  {
    Waveforms[0][i]=i;
  }

  for (i=0; i<MAXSERVOS; i++)
  {
    servoDetails[i].Delay = 0L;
    // if we failed to read the startup file or we've never used this servo before
    if (!StartupReadOK)
      // then set it to a middle position
      servoDetails[i].Actual = map(90,1,180,SERVOMIN,SERVOMAX);

    servoDetails[i].Target = servoDetails[i].Actual;
    servoDetails[i].OldTarget = servoDetails[i].Target;
    servoDetails[i].NextStepTime = 0L;
    servoDetails[i].BounceAmplitude = 0;
    servoDetails[i].NumberOfBounces = 0;
    servoDetails[i].BounceSpeed = 0;
    servoDetails[i].Moved = false;
    servoDetails[i].NextWaveStep = -1;
    servoDetails[i].NextBounceStep = -1;

    newServoDetails[i].Delay = servoDetails[i].Delay;
    newServoDetails[i].Actual = servoDetails[i].Actual;
    newServoDetails[i].Target = -1;
    newServoDetails[i].OldTarget = servoDetails[i].Target;
    newServoDetails[i].NextStepTime = 0L;
    newServoDetails[i].BounceAmplitude = 0;
    newServoDetails[i].NumberOfBounces = 0;
    newServoDetails[i].BounceSpeed = 0;
    newServoDetails[i].Moved = false;
    newServoDetails[i].NextWaveStep = -1;
    newServoDetails[i].NextBounceStep = -1;

    // and then start sending the pwm signal
    moveServo(i, 'S', servoDetails[i].Actual);
  }

  delay(100);
  
  // open the config file to compare to incoming messages
  Datafile = LittleFS.open(SERVO_CONFIG_FILE, "r");
  if (!Datafile)
  {
    DEBUG_println(F("Failed to open datafile"));
  }

  return;
}

void servoSerial()
{
int16_t  val;
char     ReadByte;
char     BestMatch[40];
char     TempChar;
uint32_t ThisTime;
uint8_t  ServoNum;
int16_t  ServoTarget;
int16_t  ServoSpeed;
bool     RemoteConfigSet;
uint8_t  i;
uint8_t  ArgCount;
uint8_t  ArgChar;
char     Args[MAXSERVOS][4];

  if (ReceiveQueueSize() > 0)
  {
    TempChar = GetNextQueueCharacter();
//    DEBUG_print(TempChar);

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
          for (i=0; i<MAXSERVOS; i++)
            servoDetails[i].Matched = false;
            
          LastTime = ThisTime;
          ProcessFile(&InputStr[2], true, ServoProcessLine);
        }
      }

      // if it is a search for trigger event message....
      if (InputStr[1] == 'C' && InputStr[2] == 'Q')
      {
        ProcessSearchLine(0, 4, &InputStr[3]);
      }
      
      // if it is a remote config message....
      if (InputStr[1] == 'C' && InputStr[2] == 'T')
      {
        ServoProcessLine(&InputStr[8], false, true, (char *)NULL, 0, 0);
      }
      
      // if it is a remote config message....
      if (InputStr[1]=='W' && InputStr[2]=='C')
      {
        OverwriteConfigFile(SERVO_CONFIG_FILE);
      }

      if (InputStr[1]=='W' && InputStr[2]=='+')
      {
        AddToConfigFile(SERVO_CONFIG_FILE);
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
        ProcessFile(&InputStr[1], false, ServoProcessLine);
      }
    }
  }
}

void servoLoop()
{
int16_t  val;
int16_t  Adj;
int8_t   WaveType;
int8_t   i;

  if (millis() > servoDetails[CurrentServo].Delay)
  {
    // if we get here and the target etc has changed, then we should use the new target....
    
    if (servoDetails[CurrentServo].ServoStartMessage[0] != 0)
    {
      //send before message
      AddToOutputQueue(servoDetails[CurrentServo].ServoStartMessage, false);
      servoDetails[CurrentServo].ServoStartMessage[0] = 0;
    }

    if(servoDetails[CurrentServo].NextWaveStep != -1 && servoDetails[CurrentServo].AccessoryType == 'S')
    {
      if (millis() >= servoDetails[CurrentServo].NextStepTime || servoDetails[CurrentServo].NextWaveStep == 0)
      {
        // if we are in the right place, do nothing!
        if (servoDetails[CurrentServo].Target != servoDetails[CurrentServo].Actual)
        {
          servoDetails[CurrentServo].NextStepTime = millis() + servoDetails[CurrentServo].Speed;
        
          // ********************************************************
          // do the maths to work out the next position to move to...
          // ********************************************************
          if (servoDetails[CurrentServo].NextWaveStep == 0)
          {
            servoDetails[CurrentServo].Difference = servoDetails[CurrentServo].Target - servoDetails[CurrentServo].Actual;
            servoDetails[CurrentServo].NextWaveStep = 0;
            servoDetails[CurrentServo].StartPos = servoDetails[CurrentServo].Actual;
          }

          val = servoDetails[CurrentServo].StartPos;
          WaveType = servoDetails[CurrentServo].Waveform;
          val = val+((Waveforms[WaveType][servoDetails[CurrentServo].NextWaveStep]*servoDetails[CurrentServo].Difference)>>7);
          moveServo(CurrentServo, servoDetails[CurrentServo].AccessoryType, val);
          if (servoDetails[CurrentServo].Actual != val)
          {
            servoDetails[CurrentServo].Actual = val;
          }
          servoDetails[CurrentServo].NextWaveStep++;
          if(servoDetails[CurrentServo].NextWaveStep >= SERVO_STEPS || Waveforms[WaveType][servoDetails[CurrentServo].NextWaveStep] == -1)
          {
            // we have reached the end of the movement...
            servoDetails[CurrentServo].Actual = servoDetails[CurrentServo].Target;
            moveServo(CurrentServo, servoDetails[CurrentServo].AccessoryType, servoDetails[CurrentServo].Target);
            servoDetails[CurrentServo].Moved = true;
  
            servoDetails[CurrentServo].NextWaveStep = -1;
            
            if (servoDetails[CurrentServo].NumberOfBounces != 0)
            {
              servoDetails[CurrentServo].NextBounceStep = bounceStarts[5 - servoDetails[CurrentServo].NumberOfBounces];
              servoDetails[CurrentServo].StartPos = servoDetails[CurrentServo].Actual;
              servoDetails[CurrentServo].NextStepTime = 0L;
            }
          }
        }
      }
    }
    else
    {
      if (servoDetails[CurrentServo].Moved)
      {
        if (servoDetails[CurrentServo].NextBounceStep != -1)
        {
          // now do the bounce stuff if necessary...
          if (millis() >= servoDetails[CurrentServo].NextStepTime)
          {
            servoDetails[CurrentServo].NextStepTime = millis() + (long)(servoDetails[CurrentServo].BounceSpeed * 2L);
            
            val = servoDetails[CurrentServo].StartPos;
            WaveType = servoDetails[CurrentServo].BounceWaveform;
    
            val = val+((BounceWaveforms[WaveType][servoDetails[CurrentServo].NextBounceStep]*servoDetails[CurrentServo].BounceAmplitude)>>4);
            
            servoDetails[CurrentServo].NextBounceStep++;
            if(servoDetails[CurrentServo].NextBounceStep > 56 || BounceWaveforms[WaveType][servoDetails[CurrentServo].NextBounceStep] == -1)
            {
              servoDetails[CurrentServo].NextBounceStep = -1;
            }

            moveServo(CurrentServo, servoDetails[CurrentServo].AccessoryType, val);

          }
        }
        else
          servoDetails[CurrentServo].Moved = false;
      }
    }

    if (servoDetails[CurrentServo].AccessoryType == 'P' && !SolenoidPowered)
    {
      if (millis() >= nextPulseTime && servoDetails[CurrentServo].Target != -1)
      {
        int thisServo = CurrentServo;
        moveServo(thisServo, servoDetails[CurrentServo].AccessoryType, 4095);
        delay(servoDetails[CurrentServo].PulseDuration * 10L);
        moveServo(thisServo, servoDetails[CurrentServo].AccessoryType, 0);
        servoDetails[CurrentServo].Actual = -1;
        servoDetails[CurrentServo].Target = -1;
        nextPulseTime = millis() + 500L;
        servoDetails[CurrentServo].Moved = true;
        ServoWriteDefaultsFile();
      }
    }

    if (servoDetails[CurrentServo].AccessoryType == 'K')
    {
      if ( (servoDetails[CurrentServo].Target == 0 && SolenoidPowered) || (!SolenoidPowered && millis() >= nextPulseTime && servoDetails[CurrentServo].Target > 0))
      {
        int thisServo = CurrentServo;

        moveServo(thisServo, servoDetails[CurrentServo].AccessoryType, servoDetails[CurrentServo].Target);

        if (servoDetails[CurrentServo].Target != 0)
        {
          servoDetails[CurrentServo].Delay = millis() + (servoDetails[CurrentServo].Speed * 10L);
          SolenoidPowered = true;
        }
        else
        {
          nextPulseTime = millis() + 250L;
          SolenoidPowered = false;
        }

        if (servoDetails[CurrentServo].Target == 0)
        {
          servoDetails[CurrentServo].Target = -1;
          servoDetails[CurrentServo].Actual = -1;
          servoDetails[CurrentServo].Moved = true;
          servoDetails[CurrentServo].NextBounceStep = -1;
          servoDetails[CurrentServo].NextWaveStep = -1;
        }
        else
        {
          servoDetails[CurrentServo].Target = 0;
          servoDetails[CurrentServo].Actual = 4095;
        }
      }
    }

    if (servoDetails[CurrentServo].AccessoryType == 'T')
    {
      if (servoDetails[CurrentServo].Target != -1)
      {
        if (servoDetails[CurrentServo].Target != map(90,1,180,SERVOMIN,SERVOMAX))
        {
          moveServo(CurrentServo, servoDetails[CurrentServo].AccessoryType, servoDetails[CurrentServo].Target);
          servoDetails[CurrentServo].Target = map(90,1,180,SERVOMIN,SERVOMAX);
          servoDetails[CurrentServo].Delay = millis() + (servoDetails[CurrentServo].PulseDuration * 100L);
        }
        else
        {
          moveServo(CurrentServo, servoDetails[CurrentServo].AccessoryType, servoDetails[CurrentServo].Target);
          servoDetails[CurrentServo].Target = -1;
          servoDetails[CurrentServo].Actual = -1;
          servoDetails[CurrentServo].Moved = true;
          servoDetails[CurrentServo].NextBounceStep = -1;
          servoDetails[CurrentServo].NextWaveStep = -1;
        }
      }
    }

    if (servoDetails[CurrentServo].AccessoryType == 'R')
    {
      if (servoDetails[CurrentServo].Actual != servoDetails[CurrentServo].Target || servoDetails[CurrentServo].Direction == '2')
      {
        // int thisServo = CurrentServo;
        if (servoDetails[CurrentServo].Direction == '0')
        {
          moveServo(CurrentServo, servoDetails[CurrentServo].AccessoryType, 4095);
          servoDetails[CurrentServo].Actual = servoDetails[CurrentServo].Target;
          servoDetails[CurrentServo].NextBounceStep = -1;
          servoDetails[CurrentServo].Moved = false;
          ServoWriteDefaultsFile();
          
        }

        if (servoDetails[CurrentServo].Direction == '1')
        {
          moveServo(CurrentServo, servoDetails[CurrentServo].AccessoryType, 0);
          servoDetails[CurrentServo].Actual = servoDetails[CurrentServo].Target;
          servoDetails[CurrentServo].NextBounceStep = -1;
          servoDetails[CurrentServo].Moved = false;
          ServoWriteDefaultsFile();
        }
  
        if (servoDetails[CurrentServo].Direction == '2')
        {
          moveServo(CurrentServo, servoDetails[CurrentServo].AccessoryType, 0);
          servoDetails[CurrentServo].Delay = millis() + (servoDetails[CurrentServo].PulseDuration * 10L);
          servoDetails[CurrentServo].Direction = '0';
          servoDetails[CurrentServo].Actual = 0;
          servoDetails[CurrentServo].Target = 4095;
          servoDetails[CurrentServo].NextBounceStep = -1;
          servoDetails[CurrentServo].Moved = false;
        }
      }
    }

    if (servoDetails[CurrentServo].Target == servoDetails[CurrentServo].Actual && servoDetails[CurrentServo].NextBounceStep == -1)
    {
      if (servoDetails[CurrentServo].ServoEndMessage[0] != 0)
      {
        //send finish message
        AddToOutputQueue(servoDetails[CurrentServo].ServoEndMessage, true);
        servoDetails[CurrentServo].ServoEndMessage[0] = 0;
      }

      // if there is a new target to move to, then copy it over
      if (newServoDetails[CurrentServo].Target != -1)
      {
        if ((newServoDetails[CurrentServo].Target != servoDetails[CurrentServo].Actual) || newServoDetails[CurrentServo].AccessoryType == 'R')
        {
          servoDetails[CurrentServo].Target = newServoDetails[CurrentServo].Target;
          servoDetails[CurrentServo].OldTarget = newServoDetails[CurrentServo].Target;
          servoDetails[CurrentServo].Delay = newServoDetails[CurrentServo].Delay;
          servoDetails[CurrentServo].NextStepTime = 0L;
          servoDetails[CurrentServo].Waveform = newServoDetails[CurrentServo].Waveform;
          servoDetails[CurrentServo].BounceAmplitude = newServoDetails[CurrentServo].BounceAmplitude;
          servoDetails[CurrentServo].NumberOfBounces = newServoDetails[CurrentServo].NumberOfBounces;
          servoDetails[CurrentServo].BounceWaveform = newServoDetails[CurrentServo].BounceWaveform;
          servoDetails[CurrentServo].BounceSpeed = newServoDetails[CurrentServo].BounceSpeed;
          servoDetails[CurrentServo].NextWaveStep = 0;
          servoDetails[CurrentServo].PulseDuration = newServoDetails[CurrentServo].PulseDuration;
          servoDetails[CurrentServo].AccessoryType = newServoDetails[CurrentServo].AccessoryType;
          servoDetails[CurrentServo].Direction = newServoDetails[CurrentServo].Direction;
  
          if (servoDetails[CurrentServo].AccessoryType == 'S')
            servoDetails[CurrentServo].Speed = newServoDetails[CurrentServo].Speed;
          else
            servoDetails[CurrentServo].Speed = 0;

          for (i=0; i<4; i++)
            servoDetails[CurrentServo].ServoStartMessage[i] = newServoDetails[CurrentServo].ServoStartMessage[i];
  
          for (i=0; i<4; i++)
            servoDetails[CurrentServo].ServoEndMessage[i] = newServoDetails[CurrentServo].ServoEndMessage[i];
            
          if (newServoDetails[CurrentServo].Delay != 0)
          {
            servoDetails[CurrentServo].Delay = newServoDetails[CurrentServo].Delay;
            DEBUG_println(servoDetails[CurrentServo].Delay);
          }
          else
            servoDetails[CurrentServo].Delay = 0L;
            
          newServoDetails[CurrentServo].Target = -1;
        }
      }
    }
  }

  CurrentServo = (CurrentServo + 1) % MAXSERVOS;

  return;
}
