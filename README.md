
Hosts the files for the Modulus system central module (or base station) under the /Server directory and the embedded software for each module under the /arduino directory.
<br /><br />
To create a Modulus base station, alongside the files in the /Server directory tree, you will need to install:
<br /><br />
Apache2<br />
PHP8<br />
Python3<br />
mosquitto<br />
mosquitto-clients<br />
pygame (under python)<br />
paho.mqtt.client (under python)<br />
pyudev (under python)<br />

<p>
These files need to be stored into the proscribed folders. Permissions are fairly 'loose' as the index page executes system calls to update various feature such as the WiFi credentials
</p>
<p>
Instructions of how to install and configure all of the relevant packages are available via support.
</p>
<p>
You should note that this software is released under the PolyForm Noncommercial 1.0.0 license. Full wording of the licence is <a href='https://github.com/polyformproject/polyform-licenses/blob/1.0.0/PolyForm-Noncommercial-1.0.0.md'>here</a>. This is NOT an open source licence as defined by the OSS Foundation.
</p>
<p>
Licences apply to other software products, listed above, that you will need to install to create a complete installation of the 'base station' software. They carry their own licence terms and it is your responsibility to ensure you are abiding by their terms.
</p>
