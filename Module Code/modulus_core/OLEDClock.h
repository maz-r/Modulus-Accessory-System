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
#define _OLEDCLOCK
#define ANALOG 1
#define DIGITAL 2

// char *number[12]={"6","5","4","3","2","1","12","11","10","9","8","7"};
const int SCREEN_WIDTH = 128;
const int SCREEN_HEIGHT = 64;
float radius = min(SCREEN_HEIGHT, SCREEN_WIDTH)/2-4;

const int X_CENTER = SCREEN_WIDTH / 2;
const int Y_CENTER = SCREEN_HEIGHT / 2;

#define OLED_RESET     -1 // Reset pin # (or -1 if sharing Arduino reset pin)
#define SCREEN_ADDRESS 0x3C ///< See datasheet for Address; 0x3D for 128x64, 0x3C for 128x32
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

int cx1, cy1, cx2, cy2;
int fx1, fy1, fx2, fy2;
double angle;
bool lastInverted = false;
int clockType;
int brightness;
bool inverted;
bool OLED_attached;
char afternoonText[10];
char morningText[10];

void saveOLEDConfigurationFile()
{
  File f = LittleFS.open("/oledclock.conf", "w");
  if (!f)
  {
      DEBUG_println("file open failed for oledclock.conf");
  }
  else
  {
    f.println(brightness);
    if (inverted)
      f.println("Y");
    else
      f.println("N");
    f.println(clockType);
    f.println(morningText);
    f.println(afternoonText);
    f.close();
  }
}


void oled_draw(bool invertedDraw) 
{
char tempString[40];
char ampm_text[10];
int  offset = 0;

  if (!OLED_attached)
    return;

  if (lastInverted != invertedDraw)
  {
    display.invertDisplay(invertedDraw);
    lastInverted = invertedDraw;
  }
  
  display.clearDisplay();

  if (clockType == ANALOG)
  {
    display.drawCircle(X_CENTER, Y_CENTER, 1, SSD1306_WHITE);
  
    //draw minute's ticks (60 lines)
    for(int j=1; j<=60; j++)
    {
      angle = j*6;
      angle = angle * 0.0174533;
  
      fx1 = X_CENTER + (sin(angle) * radius);
      fy1 = Y_CENTER + (cos(angle) * radius);
      display.drawLine(fx1,fy1,fx1,fy1, SSD1306_WHITE);
    }
    
    //draw hour's ticks (12 lines)
    for(int j=0; j<12; j++)
    {
      angle = j*30;
      angle = angle * 0.0174533;
  
      fx1 = X_CENTER + (sin(angle) * radius);
      fy1 = Y_CENTER + (cos(angle) * radius);
      fx2 = X_CENTER + (sin(angle) * (radius - 4));
      fy2 = Y_CENTER + (cos(angle) * (radius - 4));
      display.drawLine(fx1,fy1,fx2,fy2, SSD1306_WHITE);
    }
  
    angle = minutes*6 + (seconds / 12);
    angle = angle * 0.0174533;
    cx2 = X_CENTER + (sin(angle) * (radius - 6));
    cy2 = Y_CENTER - (cos(angle) * (radius - 6));
    display.drawLine(X_CENTER,  Y_CENTER,  cx2,  cy2,   SSD1306_WHITE);
  
    angle = hours*30 + ((minutes / 12) * 6);
    angle = angle * 0.0174533;
    cx2 = X_CENTER + (sin(angle) * (radius - 16));
    cy2 = Y_CENTER - (cos(angle) * (radius - 16));
    display.drawLine(X_CENTER,  Y_CENTER,  cx2,  cy2,   SSD1306_WHITE);
  }
  else
  {
    ampm_text[0] = 0;
    offset = 0;
    
    display.setTextSize(3);
    display.setTextColor(SSD1306_WHITE);

    if (ampm)
    {
      if (afternoon)
        sprintf(ampm_text, "%s", afternoonText);
      else
        sprintf(ampm_text, "%s", morningText);
        
      offset = 10*strlen(ampm_text);
      sprintf(tempString, "%d:%02d", hours, minutes);
      if (strlen(tempString) > 4)
        display.setCursor(0, 20);
      else
        display.setCursor(19, 20);
      display.println(tempString);
      display.setCursor(92, 20);
      display.println(ampm_text);
    }
    else
    {
      sprintf(tempString, "%02d:%02d", hours, minutes);
      display.setCursor(20, 20);
      display.println(tempString);
    }
  }

  display.display();
  delay(1);

}

void setContrast(uint8_t contrast)
{
    display.ssd1306_command(SSD1306_SETCONTRAST);
    display.ssd1306_command(contrast);
}

void oledclockSetup()
{
int  i;
char tempString[40];

  int major = atoi(MAJOR_VERSION);
  int minor = atoi(MINOR_VERSION);
  sprintf(tempString, "V%2d.%02d", major, minor);
  
  DEBUG_println(tempString);
  delay(2000);

  File f = LittleFS.open("/oledclock.conf", "r");
  if (!f)
  {
      DEBUG_println("file open failed for secondaryclock.conf");
      brightness = 0x7F;
      inverted = false;
      clockType = DIGITAL;
      strcpy(morningText,"AM");
      strcpy(afternoonText,"PM");
  }
  else
  {
    DEBUG_println("brightness config file is present");

    String _brightness = f.readStringUntil('\n');
    _brightness.toCharArray(tempString, 40);
    brightness = atoi(tempString);
    
    String _inverted = f.readStringUntil('\n');
    _inverted.toCharArray(tempString, 40);
    if (tempString[0] == 'Y')
      inverted = true;
    else
      inverted = false;
    lastInverted = !inverted;
    
    String _clockType = f.readStringUntil('\n');
    _clockType.toCharArray(tempString, 40);
     clockType = atoi(tempString);
     
    String _morningText = f.readStringUntil('\n');
    _morningText.toCharArray(morningText, 40);
    
    String _afternoonText = f.readStringUntil('\n');
    _afternoonText.toCharArray(afternoonText, 40);
    
    f.close();
  }

  if(!display.begin(SSD1306_SWITCHCAPVCC, SCREEN_ADDRESS)) 
  {
    Serial.println(F("SSD1306 allocation failed"));
    OLED_attached = false; // Don't proceed, loop forever
  }
  else
  {
    OLED_attached = true;

    display.invertDisplay(inverted);
    display.clearDisplay();
    display.setTextSize(2); // Draw 2X-scale text
    display.setTextColor(SSD1306_WHITE);
    display.setCursor(10, 10);
    display.println("MODULUS");

    int major = atoi(MAJOR_VERSION);
    int minor = atoi(MINOR_VERSION);
    sprintf(tempString, "%2d.%02d", major, minor);
  
    display.setCursor(10, 30);
    display.println(tempString);
    display.display();      // Show initial text
    
    delay(1);
  
    setContrast(brightness);
  
    delay(10);
  }
}

void oledclockSerial()
{
byte incomingByte;
long x;
int i;
int j;
int paramCount;
char tempString[40];

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

          sprintf(Line, "%02d,%02d,%c,%d,%s,%s", boardType, brightness, (inverted ? 'Y' : 'N'), clockType, morningText, afternoonText);
          DEBUG_println(Line);
          publishStringAsMessage(Line);

          // send EOM to show we've finished
          Line[0] = EOM;
          Line[1] = 0;
          publishStringAsMessage(Line);  
        }

        if (RxQueue[0]=='I' && RxQueue[1]=='D')
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
                  brightness = atoi((char *)tempString);
                  break;
                  
                case 1:
                  if (tempString[0] == 'Y')
                    inverted = true;
                  else
                    inverted = false;
                  break;
                  
                case 2:
                  clockType = atoi((char *)tempString);
                  break;
      
                case 3:
                  strcpy(morningText,(char *)tempString);
                  break;
                  
                case 4:
                  strcpy(afternoonText,(char *)tempString);
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

          setContrast(brightness);

          saveOLEDConfigurationFile();
        }
        else
        {
          if (RxQueue[0] == 'R' || RxQueue[0] == 'P')
          {
            hours = (RxQueue[1] - '0') * 10;
            hours += RxQueue[2] - '0';
  
            if (clockType == DIGITAL)
            {
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
              }
              else
              {
                ampm = false;  
              }
            }
            else
            {
              DEBUG_println(RxQueue[7]);
              if (hours > 12)
                hours -= 12;
            }
            
            minutes = (RxQueue[3] - '0') * 10;
            minutes += RxQueue[4] - '0';
    
            seconds = (RxQueue[5] - '0') * 10;
            seconds += RxQueue[6] - '0';
    
            if (RxQueue[0] == 'R')
            {
              oled_draw(inverted);
            }
            else
            {
              if (flashing)
              {
                flashing = false;
              }
              else
              {
                flashing = true;
              }            
              oled_draw(flashing);
            }
          }
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

void oledclockLoop()
{
}
