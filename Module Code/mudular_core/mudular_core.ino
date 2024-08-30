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
// define all the different modules that this board could support
#define PRIMARYCLOCK          1
#define SECONDARYCLOCK        2
#define MANUALINPUT           3
#define SERVO                 4
#define MIMICDISPLAY          5
#define RELAY                 6
#define STEPPERMOTOR          7
#define MATRIXCLOCK           8
#define SOUNDMODULE           9
#define LARGESECONDARYCLOCK   10
#define ANALOGCLOCK           11
#define LIGHTINGCONTROLLER    12
#define DCCCONTROLLER         14
#define IRINPUT               15
// DON'T USE 0 or 16!

#ifdef ESP32
  #error TARGET IS ESP32
//#else
//  #error TARGET IS ESP8266
#endif

#define MAJOR_VERSION         "2"
#define MINOR_VERSION         "00"

#define HARDWARE_REVISION     "V1"

#define USE_MQTT

#define WIFI_COMMS            1
#define SERIAL_COMMS          2
#define SETUP                 3
#define RESET_CONFIG          0

#define RECEIVEQUEUESIZE      4096
#define MQTT_MAX_PACKET_SIZE  4096
#define OUTPUT_QUEUE_SIZE     1024
#define MAX_MESSAGE_LENGTH    4096
#define MAX_CONFIG_LINES      200
#define MAXLINELENGTH         255
#define QUEUE_SIZE            400
#define MESSAGE_LENGTH        10
#define MAX_QUEUE             40
#define SOM                   '<'         // predefined start of message character
#define EOM                   '>'         // predefined end of message character

char INITIALISE_STRING[]="INIT";

// uncomment the line below to enable debug output
//#define DEBUG

#ifdef DEBUG
  #define DEBUG_print(x)      Serial.print(x)
  #define DEBUG_println(x)    Serial.println(x)
#else
  #define DEBUG_print(x)
  #define DEBUG_println(x)
#endif

#include <ESP8266WiFi.h>
#include <WiFiClient.h>
#include <LittleFS.h>
#include <PubSubClient.h>
#include <ESP8266WebServer.h>
#include <Wire.h>
#include <Adafruit_MCP23017.h>
#include <Adafruit_LEDBackpack.h>
#include <LedControl.h>
#include <Adafruit_PWMServoDriver.h>
#include <ESP8266HTTPUpdateServer.h>
#include <TM1637Display.h>
#include <Adafruit_MotorShield.h>
#include <AccelStepper.h>

/********** webserver html 'files'***************/
#include "index.h"
#include "update.h"

int boardType = 0;
const byte DNS_PORT = 53;

IPAddress               local_IP(192,168,0,1);
IPAddress               subnet(255,255,255,0);
WiFiClient              client;
ESP8266WebServer        webServer(80);
ESP8266HTTPUpdateServer httpUpdater;

#ifdef USE_MQTT
  PubSubClient MQTTclient;
#endif

bool        InsideMessage;
bool        ResetPressed;
int         InCount;
//char        tempDebugString[80];
char        Line[MAXLINELENGTH];
char        BestMatch[MAXLINELENGTH];
char        ConfigTriggers[MAX_CONFIG_LINES][4];
uint8_t     NumberOfConfigLines;
int32_t     lastReceivedCharTimeout;
uint8_t     QueueHead;
uint8_t     QueueTail;
bool        StillInitialising;
const char  *fixed_ssid = "modulus-";
char        modulename[40];
char        boardTypeString[40];
char        configMajorVersionString[4];
char        configMinorVersionString[4];
char        ipAddress[20];
int         OperatingMode;
long        nextLEDFlash;
int         flashstate = 0;
char        uniqueID[7];
unsigned char ReceiveQueue[RECEIVEQUEUESIZE];
int         ReceiveQueueHead = 0;
int         ReceiveQueueTail = 0;
File        Datafile;
File        Configfile;
char        InputStr[MAX_MESSAGE_LENGTH];
uint32_t    LastTime;
const int   messagingPort = 1883;
char        ssid[40];
char        password[40];
int         MajorVersion;
int         MinorVersion;
char        Variables[26][40];
long        next_millis = 0L;
long        last_message_millis = 0L;
int         hours = 0;
int         minutes = 0;
int         seconds = 0;
int         secondCount = 0;
byte        RxQueue[MAX_QUEUE];
int         RxQueuePointer = 0;
bool        flashing;
bool        colon;
bool        Running;
bool        ampm;
bool        Clock24;
int         secondCounter;
int         Brightness;
uint8_t     data[5];

char serverIndex[512];
char firstServerIndex[]  = "<html><body><h2>Update ";
char secondServerIndex[] = "</h2>Choose a new firmware file:<br><br><form method='POST' action='' enctype='multipart/form-data'><input type='file' name='update'><input type='submit' value='Update'></form></body></html>";
char MysuccessResponse[] = "<META http-equiv=\"refresh\" content=\"15;URL=/\">Update Success! Rebooting...\nPlease close this window";

#include "mudular_core.h"
#include "Servo.h"
#include "ManualInput.h"
#include "MimicDisplay.h"
#include "SecondaryClock.h"
#include "MatrixClock.h"
#include "Lighting.h"
#include "Sound.h"
// #include "StepperControl.h"
#include "AnalogClock.h"
#include "DCC.h"

void setup()
{
int i;

  pinMode(RESET_CONFIG, INPUT_PULLUP);
  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, LOW);
  ResetPressed = false;

  LittleFS.begin();
  delay(100);

  WiFi.setOutputPower(10);

  // delay(1000);

  Serial.begin(115200);
  while (!Serial)
    ;
    
  Serial.println("Starting...");

  File f = LittleFS.open("/module.conf", "r");
  DEBUG_print("Reset = ");DEBUG_println(digitalRead(RESET_CONFIG));
  if (!f || digitalRead(RESET_CONFIG) == 0)
  {
    DEBUG_println("file open failed or reset selected");
    DEBUG_println(digitalRead(RESET_CONFIG));
    strcpy (modulename, "");
    strcpy (ssid, "");
    strcpy (password, "");
    boardType = 0;
    OperatingMode = SETUP;
    ResetPressed = initialiseComms();
  }
  else
  {
    DEBUG_println("YES! My config file is there!!");
    String _modulename = f.readStringUntil('\n');
    _modulename.toCharArray(modulename, 40);
    String _ssid = f.readStringUntil('\n');
    _ssid.toCharArray(ssid, 40);
    String _password = f.readStringUntil('\n');
    _password.toCharArray(password, 40);
    String _boardTypeString = f.readStringUntil('\n');
    _boardTypeString.toCharArray(boardTypeString, 40);
    String _configMajorVersionString = f.readStringUntil('\n');
    _configMajorVersionString.toCharArray(configMajorVersionString, 40);
    String _configMinorVersionString = f.readStringUntil('\n');
    _configMinorVersionString.toCharArray(configMinorVersionString, 40);
    ssid[strlen(ssid) - 1] = 0;
    password[strlen(password) - 1] = 0;
    modulename[strlen(modulename) - 1] = 0;
    boardType = atoi(boardTypeString);
    MajorVersion = atoi(configMajorVersionString);
    MinorVersion = atoi(configMinorVersionString);
    DEBUG_println(modulename);
    DEBUG_println(ssid);
    DEBUG_println(password);
    DEBUG_println(boardType);
    DEBUG_println(MajorVersion);
    DEBUG_println(MinorVersion);

    strcpy(serverIndex, firstServerIndex);
    strcat(serverIndex, modulename);
    strcat(serverIndex, secondServerIndex);

    OperatingMode = WIFI_COMMS;

    if (MajorVersion != atoi(MAJOR_VERSION) || MinorVersion != atoi(MINOR_VERSION))
      upgradeConfigFile(MajorVersion, MinorVersion);
  }

  if (OperatingMode != SETUP)
  {
    switch(boardType)
    {
      case LARGESECONDARYCLOCK:
      case SECONDARYCLOCK:
        #ifdef _SECONDARY_CLOCK
          secondaryClockSetup(boardType);
        #endif
        initialiseComms();
        delay(100);
        break;

      case MATRIXCLOCK:
        #ifdef _MATRIX_CLOCK
          matrixClockSetup();
        #endif
        initialiseComms();
        break;

      case PRIMARYCLOCK:
        #ifdef _PRIMARY_CLOCK
          primaryClockSetup();
        #endif
        initialiseComms();
        delay(100);
        break;

      case MANUALINPUT:
        initialiseComms();
        delay(100);
        #ifdef _MANUAL_INPUT
          manualInputSetup();
        #endif
        break;

      case SERVO:
        #ifdef _SERVO
          servoSetup();
        #endif
        initialiseComms();
        break;

      case LIGHTINGCONTROLLER:
        ResetPressed = initialiseComms();
        #ifdef _LIGHTING
          LightSetup();
        #endif
        break;

      case MIMICDISPLAY:
        ResetPressed = initialiseComms();
        #ifdef _MIMIC_DISPLAY
          mimicDisplaySetup();
        #endif
        break;

      case ANALOGCLOCK:
        #ifdef _ANALOG_CLOCK
        analogClockSetup();
        #endif
        initialiseComms();
        break;

      case SOUNDMODULE:
        #ifdef _SOUND_PLAYER
          soundSetup();
        #endif
        initialiseComms();
        break;

      case STEPPERMOTOR:
        #ifdef _STEPPER_CONTROL
          stepperSetup();
        #endif
        initialiseComms();
        break;

      case DCCCONTROLLER:
        initialiseComms();
        delay(100);
        #ifdef _DCC_CONTROL
          DCCSetup();
        #endif
        break;

    }

    ReadConfigFileTriggers();

    switch(boardType)
    {
      case LARGESECONDARYCLOCK:
      case SECONDARYCLOCK:
      case MATRIXCLOCK:
      case PRIMARYCLOCK:
      case MANUALINPUT:
      case ANALOGCLOCK:
      case DCCCONTROLLER:
        break;

      case SERVO:
        ProcessFile(INITIALISE_STRING, false, ServoProcessLine);
        break;

      case LIGHTINGCONTROLLER:
        ProcessFile(INITIALISE_STRING, false, LightProcessLine);
        break;

      case MIMICDISPLAY:
        ProcessFile(INITIALISE_STRING, false, MimicProcessLine);
        break;

      case SOUNDMODULE:
        ProcessFile(INITIALISE_STRING, false, SoundProcessLine);
        break;

      case STEPPERMOTOR:
//        ProcessFile(INITIALISE_STRING, false, ServoProcessLine);
        break;
    }
  }
}

void ReadSerial()
{
  switch (boardType)
  {
    case MANUALINPUT:
      #ifdef _MANUAL_INPUT
      manualInputSerial();
      #endif
      break;

    case LARGESECONDARYCLOCK:
    case SECONDARYCLOCK:
      #ifdef _SECONDARY_CLOCK
      secondaryClockSerial(boardType);
      #endif
      break;

    case MATRIXCLOCK:
      #ifdef _MATRIX_CLOCK
      matrixClockSerial();
      #endif
      break;

    case SERVO:
      #ifdef _SERVO
      servoSerial();
      #endif
      break;

    case LIGHTINGCONTROLLER:
      #ifdef _LIGHTING
      LightSerial();
      #endif
      break;

    case MIMICDISPLAY:
      #ifdef _MIMIC_DISPLAY
      mimicDisplaySerial();
      #endif
      break;

    case ANALOGCLOCK:
      #ifdef _ANALOG_CLOCK
      analogClockSerial();
      #endif
      break;

    case SOUNDMODULE:
      #ifdef _SOUND_PLAYER
      soundSerial();
      #endif
      break;

    case STEPPERMOTOR:
      #ifdef _STEPPER_CONTROL
      stepperSerial();
      #endif
      break;

    case DCCCONTROLLER:
      #ifdef _DCC_CONTROL
      DCCSerial();
      #endif
      break;

    default:
      break;
  }
}

void loop() 
{
long lastReconnectAttempt = 0L;
bool AllStill;
uint8_t i;
char nextChar;

  if (!ResetPressed)
  {
    if (digitalRead(RESET_CONFIG) == 0)
      ResetPressed = true;
  }
  
  if (OperatingMode != SETUP && ResetPressed)
  {
      DEBUG_println("Reset selected");
      DEBUG_println(digitalRead(RESET_CONFIG));
      DEBUG_print("OperatingMode = ");
      DEBUG_println(OperatingMode);
      strcpy (modulename, "");
      strcpy (ssid, "");
      strcpy (password, "");
      boardType = 0;
      OperatingMode = SETUP;
      setupAccessPoint();
      nextLEDFlash = 0L;
  }
  
  if (OperatingMode == SETUP)
  {
    webServer.handleClient();
    if (millis() > nextLEDFlash)
    {
      digitalWrite(LED_BUILTIN, flashstate);
      if (flashstate == 1)
        flashstate = 0;
      else
        flashstate = 1;
      nextLEDFlash = millis() + 500L;
    }
  }
  else
  {
    if (OperatingMode == WIFI_COMMS)
    {
      while (Serial.available())
      {
        nextChar = Serial.read();
        if (nextChar == '@')
        {
          connectToSerial();
        }
      }

      // if we are still on Wifi...
      if (OperatingMode == WIFI_COMMS)
      {
        // check that we are still connected to wifi and mqtt...
        if (WiFi.status() != WL_CONNECTED)
        {
          DEBUG_print("No Wifi connectivity...");DEBUG_println(WiFi.status());
          ResetPressed = reconnect();
        }
        else
        {
          if (!MQTTclient.connected())
          {
            DEBUG_println("mqtt NOT connected");
            digitalWrite(LED_BUILTIN, LOW);
            ResetPressed = connectToMQTT();
            digitalWrite(LED_BUILTIN, HIGH);
          }
          else
          {
            if (boardType == DCCCONTROLLER)
            {
              noInterrupts();
              delay(1);
            }
            MQTTclient.loop();
            if (boardType == DCCCONTROLLER)
            {
              delay(1);
              interrupts();
            }
          }
        }
      }
    }
    
    if (OperatingMode == SERIAL_COMMS)
    {
      while (Serial.available())
      {
        digitalWrite(LED_BUILTIN, LOW);
        AddNextQueueChar(Serial.read());   // add any characters to the input queue
      }
    }
 
    // and now do normal processing!
    ReadSerial();

    switch (boardType)
    {
      case LARGESECONDARYCLOCK:
      case SECONDARYCLOCK:
        #ifdef _SECONDARY_CLOCK
        secondaryClockLoop(boardType);
        #endif
        break;

      case MATRIXCLOCK:
        #ifdef _MATRIX_CLOCK
        matrixClockLoop();
        #endif
        break;

    case MANUALINPUT:
        #ifdef _MANUAL_INPUT
        manualInputLoop();
        #endif
        break;

      case SERVO:
        #ifdef _SERVO
        servoLoop();
        #endif
        break;

      case LIGHTINGCONTROLLER:
        #ifdef _LIGHTING
        LightLoop();
        #endif
        break;

      case MIMICDISPLAY:
        #ifdef _MIMIC_DISPLAY
        mimicDisplayLoop();
        #endif
        break;

      case ANALOGCLOCK:
        #ifdef _ANALOG_CLOCK
        analogClockLoop();
        #endif
        break;

      case SOUNDMODULE:
        #ifdef _SOUND_PLAYER
        soundLoop();
        #endif
        break;

      case STEPPERMOTOR:
        #ifdef _STEPPER_CONTROL
        stepperLoop();
        #endif
        break;          

      case DCCCONTROLLER:
        #ifdef _DCC_CONTROL
        DCCLoop();
        #endif
        break;          
    }
    
    webServer.handleClient();
  }
}
