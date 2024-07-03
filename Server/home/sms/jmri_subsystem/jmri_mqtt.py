import paho.mqtt.client as mqtt  #import the client
import time

mqtt_client_id = "JMRI_Interface"
boardType = "17"
JMRI_message_present = False
mqtt_connected = False
registered = False

def on_connect(client, userdata, flags, rc):

  global mqtt_connected
  global JMRI_message_present
  global registered

  if rc == 0:
    print("Connected!")
    mqtt_connected = True
    client.subscribe("/Devices/#")
    client.subscribe("/Messages/#")
    client.subscribe("/trains/track/#")
    registered = False
    JMRI_message_present = False
  else:
    print ("There was error ",rc)

def on_message(client, userdata, message):

  global JMRI_message_present
  global registered

#  print("message received ", str(message.payload.decode("utf-8")))
#  print("message topic=",message.topic)

  InputStr = ""
  InputStr = str(message.payload.decode("utf-8"))
  payload = message.topic.split("/")
#  print(payload)

  if message.topic.find("JMRI") != -1:
    if not JMRI_message_present:
#      print("We've been here before!")
      deregisterCentrally()

  if message.topic.find("sensor") == -1:
    if len(InputStr) >= 2:
      if (InputStr[1]=='S' or InputStr[1]=='U'):
        topic = "/trains/track/sensor/" + InputStr[2:5]
        if InputStr[1] == 'S':
          newPayload = "ACTIVE"
        if InputStr[1] == 'U':
          newPayload = "INACTIVE"
        client.publish(topic, newPayload, True)

      if InputStr == "OFF" or InputStr == "CLOSED":
        JMRI_message_present = True
        topic = "/Messages"
        newPayload = "<U" + payload[-1] + ">"
        client.publish(topic, newPayload, True)
        if not registered:
          registerCentrally()

      if InputStr == "ON" or InputStr == "THROWN":
        JMRI_message_present = True
        topic = "/Messages"
        newPayload = "<S" + payload[-1] + ">"
        client.publish(topic, newPayload, True)
        if not registered:
          registerCentrally()

def registerCentrally():
  global registered

#  print("registerCentrally")
  topic="/Devices/"+mqtt_client_id
  tempString="17,,1,00,localhost"
  client.publish(topic, tempString, retain=True)
  client.will_set(topic, payload="offline", qos=0, retain=True)
  registered = True
 
def deregisterCentrally():
  global registered
  global JMRI_message_present

#  print("deregisterCentrally")
  topic="/Devices/"+mqtt_client_id
  tempString="offline"
  client.publish(topic, tempString, retain=True)
  registered = False
  JMRI_message_present = True

############

broker_address="127.0.0.1"
client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION1, mqtt_client_id) #create new instance
client.on_connect=on_connect
client.on_message=on_message #attach function to callback
client.connect(broker_address) #connect to broker
client.subscribe("/Devices/#")
client.subscribe("/Messages/#")
client.subscribe("/trains/track/#")
client.loop_start() #start the loop

while not mqtt_connected:
  print ("Waiting...")
  time.sleep(1)

#client.loop_forever()
while True:
    time.sleep(1)
