#!/bin/bash
#
# subscribes to the message queue and flash an attached LED every time a message is received
#
shutdown_event=\<S$(< /home/sms/ShutdownEvent.cfg)\>
mosquitto_sub -t /Messages/# |while read line ; do if [ $line == $shutdown_event ]; then shutdown now; fi; done
