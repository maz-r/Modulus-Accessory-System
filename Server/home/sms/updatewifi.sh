#!/bin/bash
sudo nmcli connection modify hotspot ssid $1 802-11-wireless-security.psk $2 wifi.band bg wifi.channel $3
sleep 1
sudo nmcli connection down hotspot
sleep 1
sudo nmcli connection up hotspot
