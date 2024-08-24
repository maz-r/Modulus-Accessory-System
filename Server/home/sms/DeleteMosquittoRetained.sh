#!/bin/bash
sudo systemctl stop mosquitto
sleep 1
sudo rm /var/lib/mosquitto/mosquitto.db
sudo systemctl start  mosquitto
sleep 1
sudo systemctl restart modulus_clock 
sleep 1
sudo systemctl restart modulus_sounds
sleep 1
sudo systemctl restart modulus_jmri
sleep 1
sudo systemctl restart modulus_activity
sleep 1
