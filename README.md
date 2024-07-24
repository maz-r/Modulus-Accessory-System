<h1>Modulus</h1>
<h2>A system for controlling accessories on a model railway/railroad</h2>
<p>
Modulus consists of 2 parts; a central base station which controls to flow of events between the modules, and ESP8266 modules which act as the inputs (switches/sensors etc) and outputs (servos/relays/LED lighting/sounds/clocks etc).
</p>
<p>
The files for the Modulus system central module (or base station) are under the /Server directory and the embedded source software for each module is under the /arduino directory.
<br /><br />
The base station is hosted on a Raspberry Pi and has been tested on a 4+ with 1GB of RAM, running Raspberry Pi OS Lite
(Release date: July 4th 2024, System: 32-bit, Kernel version: 6.6, Debian version: 12 (bookworm))
</p>
<p>
Each module is an ESP8266 microprocessor with associated ancillary electronics. The electronic modules needed are dependent upon the function that the module is configured to provide. There is only one software set for the modules and the functionality is set during the initial setup of the module. Changing functionality of the module is achieved by simply going through the initial setup process again
<p>
To create a Modulus base station, alongside the files in the /Server directory tree, you will need to install:
<br /><br />
Apache2<br />
PHP8<br />
Python3<br />
mosquitto<br />
mosquitto-clients<br />
SQLite<br />
pygame (under python)<br />
paho.mqtt.client (under python)<br />
pyudev (under python)<br />
<p>
The versions of these packages that are known to work in the context of the Modulus base station are shown within the brackets.
</p>
When setting up your Raspberry Pi to host the Modulus base station, create the initial user as 'sms'. Failing to do so will mean a whole heap edits to these files and their locations. The packages above should be installed before copying the Modulus files from this repository onto your Pi, as they overwrite some of the default configuration files created when the packages are installed. The files and folders need to be created in the proscribed layout. Permissions are fairly 'loose' as the index page executes system calls to update various configuration items, such as the WiFi credentials
</p>
<p>
Step-by-step instructions on how to install and configure all of the relevant packages are available via support.
</p>
<p>
You should note that this software is released under the PolyForm Noncommercial 1.0.0 license. Full wording of the licence is <a href='https://github.com/polyformproject/polyform-licenses/blob/1.0.0/PolyForm-Noncommercial-1.0.0.md'>here</a>. This is NOT an open source licence as defined by the OSS Foundation.
</p>
<p>
Licences apply to other software products as well as the Raspian OS, listed above, that you will need to install to create a complete installation of the base station software. They carry their own licence terms and it is your responsibility to ensure you are abiding by their terms.
</p>
