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
#define _STEPPER_CONTROL
#define MAX_MOTORS        2
#define SEQ_STEPS         4
#define hallPin0          4
#define hallPin1          5
#define ACCEL_STEPS       100
#define FULL_CIRCLE_COUNT 3200L
#define MINIMUM_STEP_TIME 6
#define stepperDirPin     D6
#define stepperStepPin    D7
#define stepperEnablePin  D5

#define motorInterfaceType 1

// Define a stepper and the pins it will use
AccelStepper stepper = AccelStepper(motorInterfaceType, stepperStepPin, stepperDirPin);

char STEPPER_CONFIG_FILE[]="STEPPER.CFG";
char STEPPER_DEFAULTS_FILE[]="STEPPER_DEFAULTS.CFG";

enum MotorMode {
  InitialReset,
  ResetZero,
  Normal,
  TestBacklash,
  FullCircle
};

typedef struct {
  long      Delay;
  MotorMode Mode;
  int       subMode;
  long      ActualPosition;
  long      Target;
  float     StepsPerDegree;
  long      nextStepTime;
  int       Speed;
  int       SpeedAchieved;
  int       Acceleration;
  int       AccelerationCount;
  int       AccelerationStep;
  long      AccelerationNextTime;
  int       StepsToFull;
  int       StepsLeft;
  int       TargetDirection;
  int       CurrentDirection;
  int       hallPin;
  int       nextStepperStep;
  int       BackLash;
  long      ForwardCircleCount;
  long      CircleCount;
  bool      Moved;
  int       backlashCount;
  char      MotorStartMessage[4];
  char      MotorEndMessage[4];
} STEPPER_STRUCT;

STEPPER_STRUCT Motor[MAX_MOTORS];
byte bitPattern = 0;
byte lastBitPattern = 0;
int  currentMotor;
bool ActAsClock;
bool MotorsInitialised;
int  NumberOfMotors = 1;
int  TestBacklashCount;
int  TargetDegrees = 0;
int  ActualDegrees = 0;
long LastClockSeconds = -1L;

boolean digitalReadHallPin(int StepperNum, int HiLo)
{
int  i;

//  Serial.println(digitalRead(hallPin));
//  delay(250);
  for (i=0; i<3; i++)
  {
    if (digitalRead(Motor[StepperNum].hallPin) == HiLo)
    {
      return false;
    }
    delayMicroseconds(50);
  }

  return true;
}

bool ResetToZero()
{
int ZeroCount = 0;

  stepper.setAcceleration(1000000);
  stepper.setMaxSpeed(1);

  digitalWrite(stepperEnablePin, LOW);
  while (digitalRead(Motor[0].hallPin) == true && ZeroCount < 3200)
  {
    if (!stepper.run())
      stepper.move(1);
    delay(10);
    ZeroCount++;
  }
  digitalWrite(stepperEnablePin, HIGH);

  return true;
}

int calculateDirection()
{
int difference;

  ActualDegrees = (int)(359.0 * Motor[0].ActualPosition / FULL_CIRCLE_COUNT);
  TargetDegrees = (int)(359.0 * Motor[0].Target / FULL_CIRCLE_COUNT);
  difference = (TargetDegrees - ActualDegrees + 359) % 360;
  if (difference < 180)
    return 1;
  else
    return -1;
}

long calculateDifference()
{
int StepperNumber = 0;
int16_t  difference;

  Motor[StepperNumber].ActualPosition = stepper.currentPosition();
  DEBUG_print("Target = ");DEBUG_println(Motor[StepperNumber].Target);
  DEBUG_print("Actual = ");DEBUG_println(Motor[StepperNumber].ActualPosition);
  if (Motor[StepperNumber].Target > Motor[StepperNumber].ActualPosition && Motor[StepperNumber].TargetDirection > 0)
  {
    difference = (Motor[StepperNumber].Target - Motor[StepperNumber].ActualPosition + FULL_CIRCLE_COUNT) % FULL_CIRCLE_COUNT;
  }
  if (Motor[StepperNumber].Target > Motor[StepperNumber].ActualPosition && Motor[StepperNumber].TargetDirection < 0)
  {
    difference = (FULL_CIRCLE_COUNT - Motor[StepperNumber].Target + Motor[StepperNumber].ActualPosition) % FULL_CIRCLE_COUNT;
  }
  if (Motor[StepperNumber].Target < Motor[StepperNumber].ActualPosition && Motor[StepperNumber].TargetDirection > 0)
  {
    difference = (FULL_CIRCLE_COUNT - Motor[StepperNumber].ActualPosition + Motor[StepperNumber].Target) % FULL_CIRCLE_COUNT;
  }
  if (Motor[StepperNumber].Target < Motor[StepperNumber].ActualPosition && Motor[StepperNumber].TargetDirection < 0)
  {
    difference = (Motor[StepperNumber].ActualPosition - Motor[StepperNumber].Target) % FULL_CIRCLE_COUNT;
  }

  DEBUG_print("Difference = ");DEBUG_println(difference);
  Motor[StepperNumber].StepsLeft = difference;

  return difference;
  
}

void StepperProcessLine(char *LinetoProcess, bool TimeMessage, uint8_t NumRandom, bool Override)
{
uint8_t  StepperNumber;
uint16_t LineCount;
uint8_t  CharCount;
char     ReadByte;
char     EndMessage[5];
char     StartMessage[5];
uint16_t StepperTarget;
uint16_t StepperSpeed;
int16_t  difference;
uint8_t  i;
uint8_t  j;
uint8_t  ArgCount;
uint8_t  ArgChar;
char     Args[20][10];
char     tempString[40];

LineCount = 0;
CharCount = 0;

  for(i=0; i<20; i++)
    Args[i][0] = 0;
  
  EndMessage[0] = 0;
  // extract the servo details
  ArgCount = 0;
  ArgChar = 0;
  for (i=0; i < strlen(LinetoProcess); i++)
  {
    if(LinetoProcess[i] == ',' || LinetoProcess[i] == 0)
    {
      Args[ArgCount][ArgChar] = 0;
//      Serial.print(ArgCount);Serial.print(" = ");Serial.println(Args[ArgCount]);
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
//  Serial.print(ArgCount);Serial.print(" = ");Serial.println(Args[ArgCount]);

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
    StepperNumber = atoi(Args[0]);
    Motor[StepperNumber].Delay = atol(Args[1]);
    if (Motor[StepperNumber].Delay != 0)
      Motor[StepperNumber].Delay = millis() + (Motor[StepperNumber].Delay * 100L);
    else
      Motor[StepperNumber].Delay = 0L;  

    strcpy (StartMessage, Args[2]);

    if (StartMessage[0] != 0)
    {
      for (i=0; i<4; i++)
        Motor[StepperNumber].MotorStartMessage[i] = StartMessage[i];
    }
    else
      Motor[StepperNumber].MotorStartMessage[0] = 0;
      
    Motor[StepperNumber].Target = atof(Args[3]);
//    TargetDegrees = (int)Motor[StepperNumber].Target;
    DEBUG_print("Target = ");DEBUG_println(Motor[StepperNumber].Target);
    Motor[StepperNumber].Target = FULL_CIRCLE_COUNT * (float)((float)Motor[StepperNumber].Target / 360.0);
    DEBUG_print("Target = ");DEBUG_println(Motor[StepperNumber].Target);

    if (Args[4][0] == 'F')
      Motor[StepperNumber].TargetDirection = 1;

    if (Args[4][0] == 'B')
      Motor[StepperNumber].TargetDirection = -1;

    if (Args[4][0] == 'S')
      Motor[StepperNumber].TargetDirection = 0;
/*    {
      ActualDegrees = (int)(359.0 * Motor[StepperNumber].ActualPosition / FULL_CIRCLE_COUNT);
      difference = (TargetDegrees - ActualDegrees + 359) % 360;
      if (difference < 180)
        Motor[StepperNumber].TargetDirection = 1;
      else
        Motor[StepperNumber].TargetDirection =  -1;
    }
*/
    Motor[StepperNumber].Speed = atoi(Args[5]) * 10L;
    Motor[StepperNumber].Acceleration = atoi(Args[6]);
    strcpy (EndMessage, Args[7]);

    if (EndMessage[0] != 0)
    {
      for (i=0; i<4; i++)
        Motor[StepperNumber].MotorEndMessage[i] = EndMessage[i];
    }
    else
      Motor[StepperNumber].MotorEndMessage[0] = 0;

  }

  LineCount = 0;
  CharCount = 0;

  return;  
}

void StepperProcessFile(char *Trigger, bool TimeMessage)
{
uint16_t LineCount;
uint8_t  CharCount;
char     ReadByte;
uint8_t  ServoNum;
uint16_t ServoDelay;
char     StartMessage[5];
char     EndMessage[5];
uint16_t ServoTarget;
uint16_t ServoSpeed;
uint8_t  i;
uint8_t  j;
uint8_t  ArgCount;
uint8_t  ArgChar;
char     Args[20][10];
char     tempString[40];
bool     Matched;
bool     RequestedRandom;
uint8_t  TimeRange[4];
uint8_t  CurrentHour;
uint8_t  CurrentMinute;
uint8_t  NumRandom;

  // rewind to start of file
  Datafile.seek(0, SeekSet);

  LineCount = 0;
  CharCount = 0;

//  DEBUG_print("Looking for:");DEBUG_println(Trigger);

  while (Datafile.available()) 
  {
    // read a line
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
    {
      if (CharCount == 0 && ReadByte == '/')
        while (ReadByte != 10 && ReadByte != -1)
          ReadByte = Datafile.read();
      else
      {
        Line[CharCount++] = ReadByte;
       if (CharCount >= MAXLINELENGTH)
        CharCount = MAXLINELENGTH;
      }
    }

    if (LineCount > 0)
    {
      StartMessage[0] = 0;
      EndMessage[0] = 0;
//      Matched = 0;
//      DEBUG_println(Line);

      if (TimeMessage)
      {
        CurrentHour = ((Trigger[0] - '0') * 10) + (Trigger[1] - '0');
        CurrentMinute = ((Trigger[2] - '0') * 10) + (Trigger[3] - '0');

/*        if (MatchTimeTrigger(CurrentHour, Trigger, Line, &NumRandom))
        {
//          DEBUG_print("A) Matched on ");DEBUG_println(Trigger);
          StepperProcessLine(&Line[5], TimeMessage, NumRandom, false);
        }
      }
      else
      {
        if (strncmp(Trigger, Line, 4) == 0)
        {
//          DEBUG_print("B) Matched on ");DEBUG_println(Trigger);
          StepperProcessLine(&Line[5], TimeMessage, 0, false);
        }        
      }
*/
      LineCount = 0;
      CharCount = 0;
    }
  }
  
  return;  
}

bool stepperReadStartupConfig()
{
  return true;
}

void stepperSetup()
{
bool retval = false;
int  i;

  // Change these to suit your stepper if you want
  pinMode(stepperEnablePin, OUTPUT);
  stepper.setMaxSpeed(600);
  stepper.setAcceleration(40);
  stepper.setCurrentPosition(0);
 
  Motor[0].hallPin = hallPin0;
  pinMode(hallPin0, INPUT_PULLUP); 

  randomSeed(analogRead(3));

  for (i=0; i<NumberOfMotors; i++)
  {
    Motor[i].CurrentDirection = 1;
    Motor[i].ActualPosition = 0L;
    Motor[i].Target = 0L;
    Motor[i].Acceleration = 10;
    Motor[i].Speed = 2;
    Motor[i].Mode = InitialReset;
    Motor[i].subMode = 0;
    Motor[i].MotorEndMessage[0] = 0;
  }

  MotorsInitialised = true;

  currentMotor = 0;

//  stepperReadStartupConfig();
  NumberOfMotors = 1;
  Motor[0].ForwardCircleCount = 3200L;
  
  // open the config file to compare to incoming messages
  Datafile = LittleFS.open(STEPPER_CONFIG_FILE, "r");
  if (!Datafile)
  {
    DEBUG_println(F("Failed to open datafile"));
  }

  ResetToZero();
}

void stepperSerial()
{
int16_t  val;
char     ReadByte;
char     BestMatch[5];
char     TempChar;
uint32_t ThisTime;
uint32_t ClockSeconds;
uint32_t HourHand;
uint32_t MinuteHand;
int16_t  difference;
int      TargetDegrees = 0;
int      ActualDegrees = 0;
bool     RemoteConfigSet;
uint8_t  i;
uint8_t  j;
uint8_t  motorNum;
uint8_t  paramCount;
uint8_t  ArgCount;
uint8_t  ArgChar;
char     Args[20][10];

  if (!MotorsInitialised)
  {
    TempChar = PeekNextQueueMessageType();
    if (TempChar != 'C')
      return;
  }

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

      DEBUG_println(InputStr);

      if (InputStr[1] == 'R')
      {
        ThisTime = (InputStr[2] - '0') * 600L;
        ThisTime += (InputStr[3] - '0') * 60L;
        ThisTime += (InputStr[4] - '0') * 10L;
        ThisTime += (InputStr[5] - '0');
        if (LastTime != ThisTime)
        {
          LastTime = ThisTime;
          StepperProcessFile(&InputStr[2], true);
        }
      }
  
/*      // if it is a remote config message....
      if (InputStr[1] == 'C' && InputStr[2] == 'Z')
      {
        DEBUG_println ("Reset to zero requested");
        motorNum = InputStr[3] - '0';
        Motor[motorNum].Mode = ResetZero;
        Motor[motorNum].subMode = 0;
      }

      if (InputStr[1] == 'C' && InputStr[2] == 'B')
      {
        DEBUG_print ("Testing backlash : ");
        motorNum = InputStr[3] - '0';
        TestBacklashCount = atoi(&InputStr[4]);
        DEBUG_println (TestBacklashCount);
        if (TestBacklashCount != 0)
        {
          TestBacklashCount += Motor[motorNum].ForwardCircleCount * (float)((float)180.0 / 360.0);
          Motor[motorNum].Mode = TestBacklash;
          Motor[motorNum].backlashCount = Motor[motorNum].ForwardCircleCount * (float)((float)180.0 / 360.0);
          Motor[motorNum].subMode = 0;
        }
        else
          Motor[motorNum].Mode = Normal;
      }
*/
      if (InputStr[1] == 'C' && InputStr[2] == 'T')
      {
        StepperProcessLine(&InputStr[8], false, 0, true);
      }
/*
      if (InputStr[1] == 'C' && InputStr[2] == 'F')
      {
        motorNum = InputStr[3] - '0';
        Motor[motorNum].Mode = FullCircle;
        Motor[motorNum].CurrentDirection = 1;
      }

      if (InputStr[1] == 'C' && InputStr[2] == 'R')
      {
        motorNum = InputStr[3] - '0';
        Motor[motorNum].Mode = FullCircle;
        Motor[motorNum].CurrentDirection = -1;
      }
*/
      if (InputStr[1]=='W' && InputStr[2]=='C')
      {
        DEBUG_println("Overwrite the config file");
        OverwriteConfigFile(STEPPER_CONFIG_FILE);
      }

      if (InputStr[1]=='W' && InputStr[2]=='D')
      {
        SendConfigurationFile();
      }

      if (InputStr[1]=='W' && InputStr[2]=='+')
      {
        AddToConfigFile(STEPPER_CONFIG_FILE);
      }

      if (InputStr[1]=='W' && InputStr[2]=='E')
      {
        ReadConfigFileTriggers();
      }

      if (InputStr[1]=='I' && InputStr[2]=='D')
      {
        flashID_LED();
      }

      // if it is a search for trigger event message....
      if (InputStr[1] == 'C' && InputStr[2] == 'Q')
      {
        ProcessSearchLine(0, 4, &InputStr[3]);
      }

      if (InputStr[1]=='S' || InputStr[1]=='U')
      {
        StepperProcessFile(&InputStr[1], false);
      }
    }
  }
}

void stepperLoop()
{
long stepsToMove;

  if (stepper.distanceToGo() == 0)
  {
    if (stepper.currentPosition() >= FULL_CIRCLE_COUNT)
    {
      stepper.setCurrentPosition(stepper.currentPosition() % FULL_CIRCLE_COUNT);
    }
    
    if (stepper.currentPosition() < 0L)
    {
      stepper.setCurrentPosition(FULL_CIRCLE_COUNT + stepper.currentPosition());
    }
    
    Motor[0].ActualPosition = stepper.currentPosition();
    
    if (Motor[0].Target != stepper.currentPosition())
    {
      if (millis() > Motor[0].Delay)
      {
        DEBUG_print("New target = ");
        DEBUG_println(Motor[0].Target);
        DEBUG_print("New speed = ");
        DEBUG_println(Motor[0].Speed);
        DEBUG_print("New accel = ");
        DEBUG_println(Motor[0].Acceleration);
        
        digitalWrite(stepperEnablePin, LOW);
  
        stepper.setAcceleration(Motor[0].Acceleration);
        stepper.setMaxSpeed(Motor[0].Speed);

        if (Motor[0].TargetDirection == 0)
          Motor[0].TargetDirection = calculateDirection();

        stepsToMove = calculateDifference();
        
        if (Motor[0].TargetDirection == 1)
          stepper.move(stepsToMove);
        else
          stepper.move(-stepsToMove);

        if (Motor[0].MotorStartMessage[0] != 0)
        {
          //send finish message
          AddToOutputQueue(Motor[0].MotorStartMessage, true);
          Motor[0].MotorStartMessage[0] = 0;
        }

        Motor[0].Moved = false;
      }
    }
    else
    {
      if (Motor[0].MotorEndMessage[0] != 0)
      {
        //send finish message
        AddToOutputQueue(Motor[0].MotorEndMessage, true);
        Motor[0].MotorEndMessage[0] = 0;
      }
      digitalWrite(stepperEnablePin, HIGH);
    }
  }
  else
    Motor[0].Moved = true;
  
  stepper.run();
}
