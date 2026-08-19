import json
import websocket
import ssl  # added import for ssl

def on_message(ws, message):
    print("Received:", message)
    try:
        data = json.loads(message)
    except Exception:
        return
    # Example: reply to a ping
    if data.get("handler") == "ping":
        ws.send(json.dumps({"handler": "pong", "data": "pong"}))
    elif data.get("set") == "startRecord":
        ws.send(json.dumps({"handler": "startRecordingConfirmed", "data": "startRecordingConfirmed"}))
    elif data.get("set") == "stopRecord":
        ws.send(json.dumps({"handler": "stopRecordingConfirmed", "data": "stopRecordingConfirmed"}))
    elif data.get("handler") == "broadcastGlos":
        ws.send(json.dumps({"handler": "broadcastGlosConfirmed", "data": "broadcastGlosConfirmed"}))

def on_error(ws, error):
    print("Error:", error)

def on_close(ws, status, msg):
    print("Closed:", status, msg)

def on_open(ws):
    print("Connection opened")
    # Send a test broadcast message
    ws.send(json.dumps({"handler": "broadcastGlosConfirmed", "glos": "TestGloss"}))

if __name__ == "__main__":
    websocket.enableTrace(True)
    ws_url = "wss://leffe.science.uva.nl:8043/unrealServer/"
    ws = websocket.WebSocketApp(ws_url,
                                on_message=on_message,
                                on_error=on_error,
                                on_close=on_close)
    ws.on_open = on_open
    ws.run_forever(sslopt={"cert_reqs": ssl.CERT_NONE})  # disable ssl cert check
