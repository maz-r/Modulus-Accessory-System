#!/bin/sh
cd /home/sms/sound_subsystem
sleep 5
/usr/bin/python3 /home/sms/sound_subsystem/sound_player.py 2>&1 > /var/log/sound_subsystem.log
