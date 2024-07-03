#!/bin/sh
cd /home/sms/jmri_subsystem
sleep 5
/usr/bin/python3 /home/sms/jmri_subsystem/jmri_mqtt.py 2>&1 > /var/log/sound_subsystem.log
