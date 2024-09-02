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
#define _SOUND_PLAYER

#include <SoftwareSerial.h>

char SOUND_CONFIG_FILE[40]="SOUND.CFG";
char SOUND_SETTINGS_FILE[40]="SOUNDSET.CFG";


#define SOUND_MODULE_RED

/************ Command byte **************************/
#define CMD_NEXT_SONG         0X01  // Play next song.
#define CMD_PREV_SONG         0X02  // Play previous song.
#define CMD_PLAY_W_INDEX      0X03
#define CMD_VOLUME_UP         0X04
#define CMD_VOLUME_DOWN       0X05
#define CMD_SET_VOLUME        0X06
#define CMD_SNG_CYCL_PLAY     0X08  // Single Cycle Play.
#define CMD_SEL_DEV           0X09
#define CMD_SLEEP_MODE        0X0A
#define CMD_WAKE_UP           0X0B
#define CMD_RESET             0X0C
#define CMD_PLAY              0X0D
#define CMD_PAUSE             0X0E
#define CMD_PLAY_FOLDER_FILE  0X0F
#define CMD_CHECK_STATUS      0x10
#define CMD_STOP_PLAY         0X16  // Stop playing continuously. 
#define CMD_FOLDER_CYCLE      0X17
#define CMD_SHUFFLE_PLAY      0x18 //
#define CMD_SET_SNGL_CYCL     0X19 // Set single cycle.
#define CMD_SET_DAC           0X1A
#define DAC_ON                0X00
#define DAC_OFF               0X01
#define CMD_PLAY_W_VOL        0X22
#define CMD_PLAYING_N         0x4C
#define CMD_QUERY_STATUS      0x42
#define CMD_QUERY_VOLUME      0x43
#define CMD_PLAY_COMBINE      0x45
#define CMD_QUERY_FLDR_TRACKS 0x4e
#define CMD_QUERY_TOT_TRACKS  0x48
#define CMD_QUERY_FLDR_COUNT  0x4f

#define DEV_TF                0X02

#define SONG_QUEUE_LENGTH     40

int  SoundFile;
int  SoundLoops;
int  anspointer;
bool notPlaying[2];
bool hasBeenPlaying[2];
long songStartTime[2];
long nextMillis[2];
int  Volume[2];
byte boardNumber = 0;

SoftwareSerial mp3[2];

typedef struct{
  int SongNumber;
  int SongCount;
}SONG_QUEUE;

static int8_t Send_buf[10] = {0}; // Buffer for Send commands
SONG_QUEUE  SongQueue[2][SONG_QUEUE_LENGTH];
int         SongQueueHead[2];
int         SongQueueTail[2];
char        Args[20][10];
char        tempReadString[256];

void sendCommand(byte boardNumber, byte command, byte dat1, byte dat2)
{
int i;
int j;

  i = 0;
  Send_buf[i++] = 0x7E;
  if (dat1 == 0)
    Send_buf[i++] = 0x02;    // Len
  else
    if (dat2 == 0)
      Send_buf[i++] = 0x03;    // Len
    else
      Send_buf[i++] = 0x04;    // Len

  Send_buf[i++] = command; //
  if (dat1 != 0)
    Send_buf[i++] = dat1;    // datal
  if (dat2 != 0)
    Send_buf[i++] = dat2;    // datal

  Send_buf[i++] = 0xEF;    //  

  for (j = 0; j < i; j++)
  {
    mp3[boardNumber].write((byte)Send_buf[j]);
  }
}

int shortestQueue()
{
int best;
int qlength[2];

  if (SongQueueHead[0] >= SongQueueTail[0])
    qlength[0] = SongQueueHead[0] - SongQueueTail[0];
  else
    qlength[0] = (40 + SongQueueTail[0]) - SongQueueHead[0];
   
  if (SongQueueHead[1] >= SongQueueTail[1])
    qlength[1] = SongQueueHead[1] - SongQueueTail[1];
  else
    qlength[1] = (40 + SongQueueTail[1]) - SongQueueHead[1];
  
  if (qlength[0] < qlength[1])
    best = 0;
  else
    best = 1;
  
  return best;
}

void playSound(byte BoardNumber, int indexNumber)
{
  sendCommand(BoardNumber, 0x42, 1, indexNumber);  //Play mp3 file given by indexNumber
  notPlaying[BoardNumber] = false;
  hasBeenPlaying[BoardNumber] = false;
}

void SoundProcessLine(char *theLine, bool TimeMessage, bool Override, char *TimeRange, uint8_t CurrentHour, uint8_t CurrentMinutes)
{
int LineCount;
int CharCount;
char ReadByte;
char QueueMode;
int  i;
int  j;
int  ArgCount;
int  LargestArg;
int  ArgChar;
int  LastSongQueueHead;
bool eof;
int  hours;
int  minutes_tens;
int  minutes;
int  tempMinutes;
char soundDetails[2][4];
int  soundDetailsCount;
int  soundDetailsIndex;
int  RandomPick;

  CharCount = 0;
  for(i=0; i<20; i++)
  Args[i][0] = 0;
  
  // extract the SOUND details
  ArgCount = 0;
  ArgChar = 0;
  for (i=0; i < strlen(theLine); i++)
  {
    if(theLine[i] == ',' || theLine[i] == 0 || theLine[i] == 10 || theLine[i] == 13)
    {
      Args[ArgCount][ArgChar] = 0;
      ArgCount++;
      ArgChar = 0;
    }
    else
      if(theLine[i] != 10 && theLine[i] != 13)
      {
        Args[ArgCount][ArgChar] = theLine[i];
        ArgChar++;
      }
  }
  Args[ArgCount][ArgChar] = 0;

  QueueMode = Args[0][0];

  boardNumber = atoi(Args[1]);

  if (boardNumber == 99)
  {
    boardNumber = shortestQueue();
  }

  if (QueueMode == 'N' || QueueMode == 'I')
  {
    for (i=0; i<SONG_QUEUE_LENGTH; i++)
      SongQueue[boardNumber][i].SongCount = 0;

    SongQueueHead[boardNumber] = 0;
    SongQueueTail[boardNumber] = 0;

    if (QueueMode == 'I')
    {
      // send a stop command to cancel whatever is playing
      sendCommand(boardNumber, 0x0E, 0, 0);
      delay(50);
      notPlaying[boardNumber] = true;
    }
  }

  LargestArg = 2;
  for (i=2; i<= ArgCount; i++)
  {
    if (Args[i][0] != 0)
      LargestArg = i;
  }

  ArgCount = LargestArg;

  if (QueueMode == 'R')
  {
    RandomPick = random(1,ArgCount+1);
    for (i=0; i<=ArgCount; i++)
    {
      if (i != RandomPick)
      {
        Args[i][0] = 0;
      }
    }
    QueueMode = 'Q';
  }

  if (QueueMode == '=')
  {
    // remember the value we are told to
    if (Args[1][0] == '$')
      if ((Args[1][1] - 'A') >=0 && (Args[1][1] - 'A') <= 25)
        strcpy(Variables[Args[1][1] - 'A'], Args[2]);
  }
  
  if (QueueMode == 'N' || QueueMode == 'Q' || QueueMode == 'I')
  {
    for (i=2; i<=ArgCount; i++)
    {
      tempReadString[0] = 0;
      // do substitution if required....
      for(j=0; j<strlen(Args[i]); j++)
      {
        if (Args[i][j] == '$')
        {
          // substitute the letter for saved variable
//               DEBUG_print("Substituting ");DEBUG_print(Args[i][j+1]);DEBUG_print(" with ");DEBUG_println(Variables[Args[i][j+1] - 'A']);
          sprintf(tempReadString,"%s%s",tempReadString, Variables[Args[i][++j] - 'A']);
//               DEBUG_println(tempReadString);
        }
        else
          sprintf(tempReadString,"%s%c",tempReadString, Args[i][j]);
      }

      if (tempReadString[0] != 0)
        strcpy (Args[i], tempReadString);

      if (Args[i][0] != 0)
      {
        if (Args[i][0] != 'T' && Args[i][0] != '+' && Args[i][0] != '^')
        {
          soundDetails[0][0] = 0;
          soundDetails[1][0] = 0;
          soundDetailsCount = 0;
          soundDetailsIndex = 0;
          
          for (j=0; j<strlen(Args[i]); j++)
          {
            if (Args[i][j] == ':')
            {
              soundDetails[soundDetailsCount][soundDetailsIndex] = 0;
              soundDetailsCount = 1;
              soundDetailsIndex = 0;
            }
            else
            {
              soundDetails[soundDetailsCount][soundDetailsIndex++] = Args[i][j];
            }
            soundDetails[soundDetailsCount][soundDetailsIndex] = 0;
          }
          SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = atoi(soundDetails[0]);
          SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = atoi(soundDetails[1]);
          if (SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount == 0)
            SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = 1;
          LastSongQueueHead = SongQueueHead[boardNumber];
          SongQueueHead[boardNumber] = (SongQueueHead[boardNumber] + 1) % SONG_QUEUE_LENGTH;
        }
        else
        {
          hours = LastTime / 60;
          minutes = LastTime % 60;
  
          if (Args[i][0] == '+')
          {
            tempMinutes = atoi(&Args[i][1]);
            minutes += tempMinutes;
          }
  
          if (Args[i][0] == '^')
          {
            tempMinutes = atoi(&Args[i][1]);
            // now round to nearest n minutes
            minutes = ((minutes / tempMinutes)+1)*tempMinutes;
          }
  
          if (minutes >= 60)
          {
            hours += minutes/60;
            minutes = minutes % 60;
            if (hours >= 24)
            {
              hours = hours % 24;
            }
          }
  
          if (hours <= 20)
          {
            if (hours == 0)
            {
              SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = 22;                  
            }
            else
            {
              if (hours < 10)
              {
                SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = 21;
                SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = 1;
                SongQueueHead[boardNumber] = (SongQueueHead[boardNumber] + 1) % SONG_QUEUE_LENGTH;
                SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = hours - 20;                
              }
              SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = hours;
            }
          }
          else
          {
            SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = 20;
            SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = 1;
            SongQueueHead[boardNumber] = (SongQueueHead[boardNumber] + 1) % SONG_QUEUE_LENGTH;
            SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = hours - 20;                
          }
          SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = 1;
          LastSongQueueHead = SongQueueHead[boardNumber];
          SongQueueHead[boardNumber] = (SongQueueHead[boardNumber] + 1) % SONG_QUEUE_LENGTH;
  
          if (minutes <=20)
          {
            if (minutes < 10)
            {
              if (minutes == 0)
              {
                SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = 23;
                SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = 1;
                SongQueueHead[boardNumber] = (SongQueueHead[boardNumber] + 1) % SONG_QUEUE_LENGTH;
              }
              else
              {
                SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = 21;
                SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = 1;
                SongQueueHead[boardNumber] = (SongQueueHead[boardNumber] + 1) % SONG_QUEUE_LENGTH;
                
                SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = minutes;
                SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = 1;
                LastSongQueueHead = SongQueueHead[boardNumber];
                SongQueueHead[boardNumber] = (SongQueueHead[boardNumber] + 1) % SONG_QUEUE_LENGTH;
              }
            }
            else
            {
              SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = minutes;
              SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = 1;
              LastSongQueueHead = SongQueueHead[boardNumber];
              SongQueueHead[boardNumber] = (SongQueueHead[boardNumber] + 1) % SONG_QUEUE_LENGTH;
            }
          }
          else
          {
            minutes_tens = minutes / 10;
            minutes = minutes % 10;
  
            SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = (minutes_tens*10);
            SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = 1;
            LastSongQueueHead = SongQueueHead[boardNumber];
            SongQueueHead[boardNumber] = (SongQueueHead[boardNumber] + 1) % SONG_QUEUE_LENGTH;
  
            if (minutes > 0)
            {
              SongQueue[boardNumber][SongQueueHead[boardNumber]].SongNumber = minutes;
              SongQueue[boardNumber][SongQueueHead[boardNumber]].SongCount = 1;
              LastSongQueueHead = SongQueueHead[boardNumber];
              SongQueueHead[boardNumber] = (SongQueueHead[boardNumber] + 1) % SONG_QUEUE_LENGTH;
            }
          }
        }
      }
    }
  }
  
  return;
}

void soundSetup()
{
int i;
int j;
byte tempval;
char tempString[40];
int16_t folderAndIndex[10];

  mp3[0].begin(9600, SWSERIAL_8N1, 5, 4, false, 64, 11);
  mp3[1].begin(9600, SWSERIAL_8N1, 15, 13, false, 64, 11);
  delay(500);

  sendCommand(0, 0x0E, 0, 0);
  delay(5);
  sendCommand(0, 0x0E, 0, 0);
  delay(5);
  sendCommand(1, 0x0E, 0, 0);
  delay(5);
  sendCommand(1, 0x0E, 0, 0);
  delay(5);

  LastTime = 0;
  InCount = 0;
  anspointer = 0;

  SongQueueHead[0] = 0;
  SongQueueTail[0] = 0;
  notPlaying[0] = true;
  hasBeenPlaying[0] = false;
  songStartTime[0] = 0L;
  
  SongQueueHead[1] = 0;
  SongQueueTail[1] = 0;
  notPlaying[1] = true;
  hasBeenPlaying[1] = false;
  songStartTime[1] = 0L;

  File f = LittleFS.open(SOUND_SETTINGS_FILE, "r");
  if (!f)
  {
      DEBUG_println("file open failed for sound.conf");
      Volume[0] = 22;
      Volume[1] = 22;
  }
  else
  {
    DEBUG_println("Sound config file is present");
    String _Volume = f.readStringUntil('\n');
    _Volume.toCharArray(tempString, 40);
    Volume[0] = atoi(tempString);
    _Volume = f.readStringUntil('\n');
    _Volume.toCharArray(tempString, 40);
    Volume[1] = atoi(tempString);
    DEBUG_println(Volume[0]);
    DEBUG_println(Volume[1]);
    f.close();
  }
  
  // send volume command
  sendCommand(0, 0x31, Volume[0], 0);
  delay(250);
  sendCommand(0, 0x31, Volume[0], 0);
  delay(250);
  sendCommand(0, 0x0E, 0, 0);

  // send volume command
  sendCommand(1, 0x31, Volume[1], 0);
  delay(250);
  sendCommand(1, 0x31, Volume[1], 0);
  delay(250);
  sendCommand(1, 0x0E, 0, 0);

  for (i=0; i<26; i++)
    Variables[i][0] = 0;

  // and finally, open the config file to compare to incoming messages
  Datafile = LittleFS.open(SOUND_CONFIG_FILE, "r");
  if (!Datafile)
  {
    DEBUG_println(F("Failed to open datafile"));
  }

  nextMillis[0] = millis()+2000L;
  nextMillis[1] = nextMillis[0];

  return;
}

void soundSerial()
{
long ThisTime;
int  val;
int  tempval;
char TempChar;
int  SoundFile;
int  ArgCount;
int  ArgChar;
int  i;

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
      InputStr[InCount++] = (char)TempChar;
    }
    
    if (TempChar == EOM)
    {
      InsideMessage = false;

      InputStr[InCount] = 0;

      if (InputStr[1] == 'R')
      {
        ThisTime =  (InputStr[2] - '0') * 600L;
        ThisTime += (InputStr[3] - '0') * 60L;
        ThisTime += (InputStr[4] - '0') * 10L;
        ThisTime += (InputStr[5] - '0');
        if (LastTime != ThisTime)
        {
          LastTime = ThisTime;

          ProcessFile(&InputStr[2], true, SoundProcessLine);            
        }
      }

      // if it is a remote config message....
      if (InputStr[1] == 'W' && InputStr[2] == 'S')
      {
        ArgCount = 0;
        ArgChar = 0;
      
        for (i=3; i < strlen(InputStr); i++)
        {
          if(InputStr[i] == ',' || InputStr[i] == 0 || InputStr[i] == 10 || InputStr[i] == 13)
          {
            Args[ArgCount][ArgChar] = 0;
            ArgCount++;
            ArgChar = 0;
          }
          else
            if(InputStr[i] != 10 && InputStr[i] != 13)
            {
              Args[ArgCount][ArgChar] = InputStr[i];
              ArgChar++;
            }
        }
        Args[ArgCount][ArgChar] = 0;

        Volume[0] = atoi(Args[0]);
        Volume[1] = atoi(Args[1]);
        
        File f = LittleFS.open(SOUND_SETTINGS_FILE, "w");
        if (f)
        {
          f.println(Volume[0]);
          f.println(Volume[1]);
          f.close();
        }

        DEBUG_println(Volume[0]);
        DEBUG_println(Volume[1]);

        // send volume command
        sendCommand(0, 0x31, Volume[0], 0);
        delay(100);
        // send volume command
        sendCommand(0, 0x31, Volume[0], 0);
        delay(100);
        // send volume command
        sendCommand(1, 0x31, Volume[1], 0);
        delay(100);
        // send volume command
        sendCommand(1, 0x31, Volume[1], 0);
        delay(100);
      }
      
      if (InputStr[1] == 'C' && InputStr[2] == 'T')
      {
        SoundProcessLine(&InputStr[8], false, false, (char *)NULL, 0, 0);
      }

      // if it is a remote config message....
      if (InputStr[1] == 'C' && InputStr[2] == 'Q')
      {
        ProcessSearchLine(0, 4, &InputStr[3]);
      }
      
      if (InputStr[1]=='W' && InputStr[2]=='C')
      {
        OverwriteConfigFile(SOUND_CONFIG_FILE);
      }

      if (InputStr[1]=='W' && InputStr[2]=='+')
      {
        AddToConfigFile(SOUND_CONFIG_FILE);
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
        ProcessFile(&InputStr[1], false, SoundProcessLine);
      }
    }
  }
}

void soundLoop()
{
byte mp3Status;
long startWait;
byte boardNumber;

  for (boardNumber = 0; boardNumber < 2; boardNumber++)
  {
    if (notPlaying[boardNumber])
    {
      if (SongQueue[boardNumber][SongQueueTail[boardNumber]].SongCount > 0)
      {
        SongQueue[boardNumber][SongQueueTail[boardNumber]].SongCount--;
  
        playSound(boardNumber, SongQueue[boardNumber][SongQueueTail[boardNumber]].SongNumber);
        songStartTime[boardNumber] = millis();
        nextMillis[boardNumber] = songStartTime[boardNumber] + 250L;
      }
      else
      {
        if (SongQueue[boardNumber][SongQueueTail[boardNumber]].SongCount < 0)
        {
          playSound(boardNumber, SongQueue[boardNumber][SongQueueTail[boardNumber]].SongNumber);
          songStartTime[boardNumber] = millis();
          nextMillis[boardNumber] = songStartTime[boardNumber] + 250L;
        }
        else
        {
          if (SongQueueTail[boardNumber] != SongQueueHead[boardNumber])
          {
            SongQueueTail[boardNumber] = (SongQueueTail[boardNumber] + 1) % SONG_QUEUE_LENGTH;
          }
        }
      }
    }
    else
    {
      if (millis() > nextMillis[boardNumber])
      {
        while(mp3[boardNumber].available())
          mp3[boardNumber].read();
  
        sendCommand(boardNumber, 0x10, 0, 0);
  
        startWait = millis();
        while(mp3[boardNumber].available()<9 && (millis()-startWait) < 50L)
        {
          delay(2);
        }
  
        if (mp3[boardNumber].available() >= 9)
        {
          startWait = millis();
          mp3Status = 255;
          while(mp3Status != CMD_CHECK_STATUS && (millis()-startWait) < 50L)
          {
            if (mp3[boardNumber].available() >= 1)
              mp3Status = mp3[boardNumber].read();
            delay(2);
          }
  
          mp3Status = mp3[boardNumber].read();
  
          if (mp3Status == 1)
            hasBeenPlaying[boardNumber] = true;
  
          if (mp3Status == 0 && hasBeenPlaying[boardNumber])
            notPlaying[boardNumber] = true;
    
          if (mp3Status == 0 && !hasBeenPlaying[boardNumber])
            if ((millis()-songStartTime[boardNumber]) > 1000L)
              notPlaying[boardNumber] = true;
        }
        nextMillis[boardNumber] = millis() + 50L;
      }
    }
  }
}
