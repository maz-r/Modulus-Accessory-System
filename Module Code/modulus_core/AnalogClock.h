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
#define _ANALOG_CLOCK

Adafruit_MotorShield AFMS = Adafruit_MotorShield();
Adafruit_StepperMotor *hoursMotor = AFMS.getStepper(200, 2);
Adafruit_StepperMotor *minutesMotor = AFMS.getStepper(200, 1);

#define MAX_STEPS 2000

const int     Hours_hallPin = 14;
const int     Minutes_hallPin = 15;
int           Hours_hallState = 0;          // variable for reading the hall sensor status
int           Minutes_hallState = 0;          // variable for reading the hall sensor status
unsigned long HourhandPosition = 0L;
unsigned long MinutehandPosition = 0L;
unsigned long HourhandTarget = 0L;
unsigned long MinutehandTarget = 0L;
int           HourhandDirection;
int           MinutehandDirection;

long ConvertTimeToHandPositions(int Type, long targetHours, long targetMinutes, long targetSeconds)
{
unsigned long timeInSteps = 0L;

//  DEBUG_print("Hours  : ");DEBUG_println(targetHours);
//  DEBUG_print("Minutes: ");DEBUG_println(targetMinutes);
//  DEBUG_print("Seconds: ");DEBUG_println(targetSeconds);

  if (Type == 0)
  {
    // convert to seconds
    timeInSteps = targetHours * 3600;
    timeInSteps += targetMinutes * 60;
    timeInSteps += targetSeconds;

    timeInSteps = (timeInSteps * MAX_STEPS) / 43200L;

    DEBUG_print("Target  :");DEBUG_println(timeInSteps);
  }
  else
  {
    // convert to seconds
    timeInSteps = targetMinutes * 60;
    timeInSteps += targetSeconds;

    timeInSteps = (timeInSteps * MAX_STEPS) / 3600L;

//    DEBUG_print("Target  :");DEBUG_println(timeInSteps);
  }
  
  return timeInSteps;
}

void ZeroHands()
{
  DEBUG_println("Who is Starting...");

  while (digitalRead(Hours_hallPin) == LOW && digitalRead(RESET_CONFIG) == 1)
  {
    hoursMotor->onestep(FORWARD, DOUBLE);
    delay(10);
  }  

  while (digitalRead(Hours_hallPin) == HIGH  && digitalRead(RESET_CONFIG) == 1)
  {
    hoursMotor->onestep(FORWARD, DOUBLE);
    delay(10);
  }
  
/*  
   while (digitalRead(Hours_hallPin) == LOW)
  {
    StepForwards(Hour_motorPin, delayTime);
  }
  
  while (digitalRead(Minutes_hallPin) == HIGH)
  {
    StepForwards(Minute_motorPin, delayTime);
  }
*/
}  

void analogClockSetup()
{
  if (!AFMS.begin()) 
  {
    DEBUG_println("Could not find Motor Shield. Check wiring.");
    while (1);
  }
  DEBUG_println("Motor Shield found.");

  hoursMotor->setSpeed(10);  // 10 rpm
  minutesMotor->setSpeed(10);  // 10 rpm

  pinMode(Hours_hallPin, INPUT);     
  pinMode(Minutes_hallPin, INPUT);     

  randomSeed(analogRead(3));

  InCount = 0;

  ZeroHands();
}

void UpdateTargets (long targetHours, long targetMinutes, long targetSeconds)
{
int difference;

  HourhandTarget =   ConvertTimeToHandPositions(0, targetHours, targetMinutes, targetSeconds);
  MinutehandTarget = ConvertTimeToHandPositions(1, targetHours, targetMinutes, targetSeconds);

  if (HourhandTarget != HourhandPosition)
  {
    difference = (HourhandTarget - HourhandPosition + MAX_STEPS - 1) % MAX_STEPS;
    if (difference < (MAX_STEPS / 2))
      HourhandDirection = FORWARD;
    else
      HourhandDirection = BACKWARD;
  }
  
  if (MinutehandTarget != MinutehandPosition)
  {
    difference = (MinutehandTarget - MinutehandPosition + MAX_STEPS - 1) % MAX_STEPS;
    if (difference < (MAX_STEPS / 2))
      MinutehandDirection = FORWARD;
    else
      MinutehandDirection = BACKWARD;
  }
}

void MoveHands (int HandChoice)
{
  if (HandChoice == 0)
  {
    hoursMotor->onestep(HourhandDirection, DOUBLE);

    if (HourhandDirection == FORWARD)
      HourhandPosition++;
    else
      HourhandPosition--;

    if (HourhandPosition > MAX_STEPS)
      HourhandPosition = 0L;

    if (HourhandPosition < 0L)
      HourhandPosition = MAX_STEPS;
  
    hoursMotor->release();
  }
  
  if (HandChoice == 1)
  {
    minutesMotor->onestep(MinutehandDirection, DOUBLE);

    if (MinutehandDirection == FORWARD)
      MinutehandPosition++;
    else
      MinutehandPosition--;

    if (MinutehandPosition > MAX_STEPS)
      MinutehandPosition = 0L;

    if (MinutehandPosition < 0L)
      MinutehandPosition = MAX_STEPS;
    
    minutesMotor->release();
  }
}

void analogClockSerial()
{
char     TempChar;
uint32_t ThisTime;
long     target_hours;
long     target_minutes;
long     target_seconds;

  if (ReceiveQueueSize() > 0)
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
      
      if (InputStr[1]=='I' && InputStr[2]=='D')
      {
        flashID_LED();
      }

      if (InputStr[1] == 'R' || InputStr[1] == 'P')
      {
        target_hours = (InputStr[2] - '0') * 10;
        target_hours += InputStr[3] - '0';

        if (target_hours > 12)
          target_hours -= 12;
        
        target_minutes = (InputStr[4] - '0') * 10;
        target_minutes += InputStr[5] - '0';

        target_seconds = (InputStr[6] - '0') * 10;
        target_seconds += InputStr[7] - '0';

        UpdateTargets(target_hours, target_minutes, target_seconds);
      }
    }
  }
}

void analogClockLoop()
{
  if (MinutehandTarget != MinutehandPosition)
  {
    MoveHands(1);
  }

  if (HourhandTarget != HourhandPosition)
  {
    MoveHands(0);
  }
}
