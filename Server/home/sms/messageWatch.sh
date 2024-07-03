#!/bin/bash
#
# subscribes to the message queue and flash an attached LED every time a message is received
#
# old style....gpio -g mode 19 out
sleep 5
pinctrl set 22 op
mosquitto_sub -t /Messages/# |while read line ; do pinctrl set 22 dh ; pinctrl set 22 dl ; done
