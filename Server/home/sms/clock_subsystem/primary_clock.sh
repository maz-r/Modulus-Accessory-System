#!/bin/sh
cd /home/sms/clock_subsystem
sleep 5
/usr/bin/python3 /home/sms/clock_subsystem/primary_clock.py 2>&1 > /var/log/primary_clock.log
