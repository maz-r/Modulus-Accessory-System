<h1>Modulus</h1>
<h2>A system for controlling accessories on a model railway/railroad</h2>
<p>
Modulus consists of 2 parts; a central base station which controls to flow of events between the modules, and ESP8266 modules which act as the inputs (switches/sensors etc) and outputs (servos/relays/LED lighting/sounds/clocks etc).
</p>
<p>
The files for the Modulus system central module (or base station) are under the /Server directory and the embedded source software for each module is under the /Module Code directory.
<br /><br />
The base station is hosted on a Raspberry Pi and has been tested on a 4+ with 1GB of RAM and a Zero W, running Raspberry Pi OS Lite
(Release date: July 4th 2024, System: 32-bit, Kernel version: 6.6, Debian version: 12 (bookworm))
</p>
<p>The Zero is a good option where the network interfaces, central sound aren't needed and only a few modules are required.
</p>
<p>
Each module is an ESP8266 microprocessor with associated ancillary electronics. The electronic modules needed are dependent upon the function that the module is configured to provide. There is only one software set for the modules and the functionality is set during the initial setup of the module. Changing functionality of the module is achieved by simply going through the initial setup process again.
<h3>Base Station</h3>
<p>
To create a Modulus base station, alongside the files in the /Server directory tree, you will need to install:
<br /><br />
Apache2 (2.4.59 (Raspbian))<br />
PHP8 (8.2.7)<br />
Python3 (3.11.2)<br />
mosquitto (2.0.11)<br />
mosquitto-clients (2.0.11)<br />
SQLite (3.40.1)<br />
pygame (2.6.0) (under python)<br />
paho-mqtt (2.1.0) (under python)<br />
<br />
The versions of these packages that are known to work in the context of the Modulus base station are shown within the brackets.
<br />
When setting up your Raspberry Pi to host the Modulus base station, create the initial user as 'sms'. Failing to do so will mean a whole heap edits to these files and their locations. The packages above should be installed before copying the Modulus files from this repository onto your Pi, as they overwrite some of the default configuration files created when the packages are installed. The files and folders need to be created in the proscribed layout. Permissions are fairly 'loose' as the index page executes system calls to update various configuration items, such as the WiFi credentials
<br />
<h3>Module Code</h3>
<br />
To compile the module code, the following development environment is known to work:
<br /><br />
Arduino 1.8.13 <br /><br />
Hardware package: esp8266 at version 3.1.1<br /><br />

Libraries:<br /><br />
ESP8266WiFi version 1.0 (included in the esp8266 v3.1.1 hardware package)<br /> 
LittleFS version 0.1.0  (included in the esp8266 v3.1.1 hardware package)<br />
ESP8266WebServer version 1.0  (included in the esp8266 v3.1.1 hardware package)<br />
ESP8266HTTPUpdateServer version 1.0  (included in the esp8266 v3.1.1 hardware package)<br />
Wire version 1.0  (included in the esp8266 v3.1.1 hardware package)<br />
SPI version 1.0  (included in the esp8266 v3.1.1 hardware package)<br />
SoftwareSerial version 7.0.0  (included in the esp8266 v3.1.1 hardware package)<br />
<br />
pubsubclient by Nick O'Leary  version 2.8 <br />
Adafruit MCP23017 Arduino Library  version 1.0.3 <br />
Adafruit LED Backpack version 1.1.6 <br />
Adafruit GFX_Graphics version 1.3.6 <br />
Adafruit PWM Servo Driver Library version 1.0.2 <br />
Adafruit Motor Shield V2 Library version 1.1.3 <br />
Adafruit BusIO version 1.16.0 <br />
Adafruit NeoPixel version 1.12.2 <br />
AccelStepper by Mike McCauley version 1.61 <br />
TM1637 (from <a href="https://github.com/avishorp/TM1637">here</a>)<br />
MAX7219LedMatrix (from <a href="https://github.com/squix78/MAX7219LedMatrix">here</a>) <br />
<br />
Step-by-step instructions on how to install and configure all of the relevant packages are available via support.
<h2>Licencing</h2>
<br />
You should note that this software is released under the PolyForm Noncommercial 1.0.0 license. Full wording of the licence is <a href='https://github.com/polyformproject/polyform-licenses/blob/1.0.0/PolyForm-Noncommercial-1.0.0.md'>here</a>. This is NOT an open source licence as defined by the OSS Foundation.
<br />
Licences apply to other software products as well as the Raspian OS, listed above, that you will need to install to create a complete installation of the base station software. They carry their own licence terms and it is your responsibility to ensure you are abiding by their terms.

