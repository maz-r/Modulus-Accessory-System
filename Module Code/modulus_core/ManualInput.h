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
#define _MANUAL_INPUT

#define MAX_INPUTS                96
#define MAX_INPUT_BOARDS          6
#define MAX_DEBOUNCE_TIME         200L
// #define RECEIVED_MESSAGE_TIMEOUT  200L

#define NORMAL_PIN                0
#define TOGGLE_PIN                1
#define PAIRS_PIN                 2
#define TIMEOUT_PIN               3

char INPUT_CONFIG_FILE[]="INPUTS.CFG";

typedef struct inputstate {
  uint8_t currentStatus;
  uint8_t lastStatus;
  uint8_t sentStatus;
  int32_t lastDebounceTime;
  uint8_t pinType;
  long    timeout;
  long    timeoutCounter;
  char    BankAddress;
  uint8_t MessageNumber;
} INPUT_STATE;

Adafruit_MCP23017 mcpBoard[MAX_INPUT_BOARDS];
char        BoardAddress = 'A';
uint8_t     BoardOffset = 0;
INPUT_STATE inputs[MAX_INPUTS];
long        nextInitialiseMessage = 0L;
int         initialiseIndex;
int         nextInputPin;
String      _pinInputDef;
uint8_t     maxInputs;
uint8_t     numBoards;

void SendStatus(uint8_t Input, uint8_t Status)
{
char   Message[MESSAGE_LENGTH];
char   number[3];
int8_t i;

  digitalWrite(LED_BUILTIN, LOW);

//  Input += BoardOffset;

  DEBUG_print("Sending new status message :");
  Message[0] = SOM;
  if (Status == 1)
    Message[1] = 'U';
  else
    Message[1] = 'S';
//  Message[2] = BoardAddress;
  Message[2] = inputs[Input].BankAddress;
  Message[3] = (inputs[Input].MessageNumber / 10) + '0';
  number[0] = (inputs[Input].MessageNumber / 10) + '0';
  Message[4] = (inputs[Input].MessageNumber % 10) + '0';
  number[1] = (inputs[Input].MessageNumber % 10) + '0';
  Message[5] = EOM;
  Message[6] = 0;
  number[2] = 0;

  publishMessage(BoardAddress, number, Message, true);
  DEBUG_println(Message);
  digitalWrite(LED_BUILTIN, HIGH);
}

void readInputConfigFile()
{
uint8_t  i;
uint8_t  j;
char     tempString[40];
uint8_t  ArgCount;
uint8_t  ArgChar;
char     Args[20][10];

  Datafile = LittleFS.open(INPUT_CONFIG_FILE, "r");
  if (!Datafile)
  {
    DEBUG_println("file open failed for manualinput.conf");
//    saveBoardAddress();
  }
  else
  {
    DEBUG_println("board address config file is present");
    for (i=0; i< maxInputs && Datafile.available(); i++)
    {
      _pinInputDef = Datafile.readStringUntil('\n');

      _pinInputDef.toCharArray(tempString, 20);
      ArgChar = 0;
      ArgCount = 0;
      for (j=0; j<strlen(tempString); j++)
      {
        if (tempString[j] == ',')
        {
          Args[ArgCount][ArgChar] = 0;
          ArgChar = 0;
          ArgCount++;
        }
        else
          Args[ArgCount][ArgChar++] = tempString[j];
      }
      Args[ArgCount][ArgChar] = 0;

      inputs[i].BankAddress = Args[0][0];
      inputs[i].MessageNumber = atoi(&Args[0][1]);
      inputs[i].pinType = Args[1][0] - '0';
      inputs[i].timeout = atol(Args[2]);
//      DEBUG_print("Bank ");DEBUG_print(i);DEBUG_print(" = ");DEBUG_println(inputs[i].BankAddress);
//      DEBUG_print("Num  ");DEBUG_print(i);DEBUG_print(" = ");DEBUG_println(inputs[i].MessageNumber);
//      DEBUG_print("Type ");DEBUG_print(i);DEBUG_print(" = ");DEBUG_println(inputs[i].pinType);
//      DEBUG_print("time ");DEBUG_print(i);DEBUG_print(" = ");DEBUG_println(inputs[i].timeout);
    }
//    f.close();
  }
}

void manualInputSetup()
{
uint8_t  i;
uint8_t  j;
uint8_t  i2cError;
uint8_t  tempStatus;
int32_t  sendNextStartupMessage;
char     tempString[40];
uint8_t  ArgCount;
uint8_t  ArgChar;
char     Args[20][10];

  DEBUG_println("Initialising");

//  Wire.pins(4, 5);
  Wire.begin(4, 5);

  delay(100);

  maxInputs = 0;
  numBoards = 0;
  i2cError = 0;

  for (int wireAddr = 0x20; wireAddr<0x27 && i2cError == 0; wireAddr++) 
  {
    Wire.beginTransmission(wireAddr);
    i2cError = Wire.endTransmission();

    DEBUG_print("Trying : ");DEBUG_print(wireAddr);DEBUG_print(" = ");DEBUG_println(i2cError);

    if (i2cError == 0)
    {
      numBoards++;
      maxInputs += 16;
    }
  }

  DEBUG_print("Board Count : ");DEBUG_println(numBoards);
  DEBUG_print("Num inputs  : ");DEBUG_println(maxInputs);

  for (i=0; i< numBoards; i++)
    mcpBoard[i].begin(i);

  for (i=0; i< maxInputs; i++)
  {
    inputs[i].pinType = NORMAL_PIN;
    inputs[i].timeout = 0;
    inputs[i].BankAddress = 'A';
    inputs[i].MessageNumber = i;
  }

  readInputConfigFile();
  
  for (i=0; i< maxInputs; i++)
  {
    mcpBoard[i/16].pinMode(i%16, INPUT);
    mcpBoard[i/16].pullUp(i%16, HIGH);  // turn on a 100K pullup internally
  }

  for (i=0; i < maxInputs; i++)
  {
    inputs[i].currentStatus = 1;
    inputs[i].lastStatus = 1;
  }

  for (i=0; i < maxInputs; i++)
  {
    tempStatus = mcpBoard[i/16].digitalRead(i%16);
    inputs[i].lastStatus = tempStatus;
    if (inputs[i].pinType == NORMAL_PIN || inputs[i].pinType == TOGGLE_PIN || inputs[i].pinType == TIMEOUT_PIN)
      inputs[i].currentStatus = tempStatus;
    else
    {
      if ((i%2)==1)
      {
        if (tempStatus == 0)
          inputs[i-1].currentStatus = 1;
      }
      else
      {
        inputs[i].currentStatus = tempStatus;
      }
    }
  }

  StillInitialising = true;
  initialiseIndex = 0;
  nextInputPin = 0;
  nextInitialiseMessage = millis() + 2000L; // let things settle down for 2 seconds before we send out our initial switch settings

  return;
}

void manualInputSerial()
{
uint8_t    i;
uint8_t    j;
uint8_t    ArgCount;
uint8_t    ArgChar;
uint8_t    lineCount;
uint8_t    InputNumber;
char       Args[20][10];
char       TempChar;
char       tempString[40];
char       number[4];

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
      InputStr[InCount] = (char)TempChar;
      InCount = (InCount + 1) % MAX_MESSAGE_LENGTH;
//      DEBUG_print(InputStr[1]);
//      DEBUG_println(InputStr[2]);
      
      InsideMessage = false;

      InputStr[InCount] = 0;

      if (InputStr[1]=='W' && InputStr[2]=='S')
      {
        lineCount = 0;
        InputNumber = 0;
        DEBUG_print(InputStr);
        ArgChar = 0;
        ArgCount = 0;
        for (i=3; i < strlen(InputStr) && InputNumber < 16; i++)
        {
          
          DEBUG_print(ArgCount);
          DEBUG_print(ArgChar);
          if (ArgChar > 0) DEBUG_print(Args[ArgCount][ArgChar - 1]);
          
          if (InputStr[i] == 13 || InputStr[i] == 10 || InputStr[i] == '>')
          {
            ArgCount = 0;
            ArgChar = 0;
            inputs[InputNumber].BankAddress = Args[0][0];
            inputs[InputNumber].MessageNumber = atoi(Args[1]);
            inputs[InputNumber].pinType = Args[2][0] - '0';
            inputs[InputNumber].timeout = atoi(Args[3]);
//            DEBUG_print("Bank ");DEBUG_print(InputNumber);DEBUG_print(" = ");DEBUG_println(inputs[InputNumber].BankAddress);
//            DEBUG_print("Num  ");DEBUG_print(InputNumber);DEBUG_print(" = ");DEBUG_println(inputs[InputNumber].MessageNumber);
//            DEBUG_print("Type ");DEBUG_print(InputNumber);DEBUG_print(" = ");DEBUG_println(inputs[InputNumber].pinType);
//            DEBUG_print("time ");DEBUG_print(InputNumber);DEBUG_print(" = ");DEBUG_println(inputs[InputNumber].timeout);
            InputNumber++;
          }
          else
            if (InputStr[i] == ',')
            {
              Args[ArgCount][ArgChar] = 0;
              ArgChar = 0;
              ArgCount++;
            }
            else
              Args[ArgCount][ArgChar++] = InputStr[i];
        }
//        saveBoardAddress();
      }

      // if it is a remote config message....
      if (InputStr[1]=='W' && InputStr[2]=='C')
      {
        OverwriteConfigFile(INPUT_CONFIG_FILE);
      }

      if (InputStr[1]=='W' && InputStr[2]=='+')
      {
        AddToConfigFile(INPUT_CONFIG_FILE);
      }

      if (InputStr[1]=='W' && InputStr[2]=='E')
      {
        readInputConfigFile();
      }

      if (InputStr[1]=='W' && InputStr[2]=='D')
      {
        sprintf(Line, "<S,%02d", boardType);
        publishStringAsMessage(Line);

        for (i=0; i<maxInputs; i++)
        {
          number[0] = inputs[i].BankAddress;
          number[1] = (inputs[i].MessageNumber/10) + '0';
          number[2] = (inputs[i].MessageNumber%10) + '0';
          number[3] = 0;
          sprintf(Line, "03,%s,%d,%d", number, inputs[i].pinType, inputs[i].timeout);
          publishStringAsMessage(Line);
        }
        
        // send EOM to show we've finished
        Line[0] = EOM;
        Line[1] = 0;
        publishStringAsMessage(Line);
      }

      // if it is a search for trigger event message....
      if (InputStr[1] == 'C' && InputStr[2] == 'Q')
      {
        ProcessSearchLine(1, 3, &InputStr[3]);
      }
      
      if (InputStr[1]=='I' && InputStr[2]=='D')
      {
        flashID_LED();
      }
    }
  }
}

void manualInputLoop()
{
int i;
int j;
uint16_t bitDetail;  
  
  if (!StillInitialising)
  {
    if (nextInputPin == 0)
    {
      for (i=0; i < numBoards; i++)
      {
        bitDetail = mcpBoard[i].readGPIOAB();
        for (j=0; j < 16; j++)
        {
            inputs[(i*16)+j].currentStatus = bitDetail & 0x0001;
            // DEBUG_print((i*16)+j);DEBUG_print(" = ");DEBUG_println(inputs[(i*16)+j].currentStatus);
            bitDetail >>= 1;
        }
      }
    }
    
    if (inputs[nextInputPin].currentStatus != inputs[nextInputPin].lastStatus)
    {
      inputs[nextInputPin].lastDebounceTime = millis();
      inputs[nextInputPin].lastStatus = inputs[nextInputPin].currentStatus;
//      DEBUG_print(nextInputPin);DEBUG_print(" = ");DEBUG_println(inputs[nextInputPin].currentStatus);
    }

    if (inputs[nextInputPin].pinType == TIMEOUT_PIN)
    {
      if (inputs[nextInputPin].lastStatus == 1)
      {
        if (millis() > inputs[nextInputPin].timeoutCounter && inputs[nextInputPin].timeoutCounter != 0L)
        {
          inputs[nextInputPin].timeoutCounter = 0L;
          SendStatus(nextInputPin, inputs[nextInputPin].currentStatus);
          inputs[nextInputPin].sentStatus = inputs[nextInputPin].currentStatus;            
        }
      }
    }
  
    if(inputs[nextInputPin].lastDebounceTime > 0L)
    {
      if ((millis() - inputs[nextInputPin].lastDebounceTime) > MAX_DEBOUNCE_TIME)
      {
//        DEBUG_print("PIN CHANGED = ");DEBUG_println(nextInputPin);
//        DEBUG_print("PIN TYPE    = ");DEBUG_println(inputs[nextInputPin].pinType);
        switch (inputs[nextInputPin].pinType)
        {
          case NORMAL_PIN:
            if (inputs[nextInputPin].sentStatus != inputs[nextInputPin].currentStatus)
            {
              SendStatus(nextInputPin, inputs[nextInputPin].currentStatus);
              inputs[nextInputPin].sentStatus = inputs[nextInputPin].currentStatus;
            }
            break;
  
          case TOGGLE_PIN:
            if (inputs[nextInputPin].currentStatus == 0 && inputs[nextInputPin].sentStatus == 1)
            {
              SendStatus(nextInputPin, 0);
              inputs[nextInputPin].sentStatus = 0;
            }
            else
            {
              if (inputs[nextInputPin].currentStatus == 0 && inputs[nextInputPin].sentStatus == 0)
              {
                SendStatus(nextInputPin, 1);
                inputs[nextInputPin].sentStatus = 1;
              }
            }
            break;
  
          case PAIRS_PIN:
            if(inputs[nextInputPin].currentStatus == 0)
            {
//              DEBUG_println("PAIR CHANGE");
              if (nextInputPin%2 == 1)
              {
                inputs[nextInputPin-1].currentStatus = 1;
                SendStatus(nextInputPin-1, inputs[nextInputPin-1].currentStatus);
              }
              else
              {
                inputs[nextInputPin].currentStatus = 0;
                SendStatus(nextInputPin, inputs[nextInputPin].currentStatus);                  
              }
            }
            break;
  
          case TIMEOUT_PIN:
            if(inputs[nextInputPin].currentStatus == 0)
            {
              SendStatus(nextInputPin, 0);
              inputs[nextInputPin].sentStatus = 1;
              inputs[nextInputPin].timeoutCounter = 0L;
            }
            else
            {
              inputs[nextInputPin].timeoutCounter = millis() + (inputs[nextInputPin].timeout * 100L);
            }
            break;
        }
        inputs[nextInputPin].lastDebounceTime = 0L;
      }
    }
  
    nextInputPin = (nextInputPin+1) % maxInputs;
  }
  else
  {
    if (millis() - nextInitialiseMessage > 50L)
    {
      DEBUG_println("Still initialising...");
      if (!((initialiseIndex%2) == 1 && inputs[initialiseIndex].pinType == PAIRS_PIN))
      {
        SendStatus(initialiseIndex, inputs[initialiseIndex].currentStatus);
      }
      inputs[initialiseIndex].sentStatus = inputs[initialiseIndex].currentStatus;
      initialiseIndex++;
      if (initialiseIndex >= maxInputs)
        StillInitialising = false;
      nextInitialiseMessage = millis();
    }
  }
}
