#!/usr/bin/env python3
import os, datetime

ip = os.environ.get("REMOTE_ADDR", "unknown")
now = datetime.datetime.now().astimezone().strftime("%c %Z")
team = "Naina, Dante, Hisham"

print("Content-Type: text/html; charset=utf-8")
print()
print(f"""<!doctype html>
<html><head><script src="https://cdn.logr-in.com/LogRocket.min.js" crossorigin="anonymous"></script>
	<script>window.LogRocket && window.LogRocket.init('c2uo5x/bananabreadbar');</script>
        <meta charset="utf-8"><title>Hello HTML (Python)</title></head>
<body>
  <h1>Hello from {team}!</h1>
  <p>Language: <b>Python</b></p>
  <p>Generated at: <b>{now}</b></p>
  <p>Your IP address is: <b>{ip}</b></p>
</body></html>""")
