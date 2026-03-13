#!/usr/bin/env python3
import os, json, datetime

out = {
  "message": "Hello from the team!",
  "team": ["Naina", "Dante", "Hisham"],
  "language": "Python",
  "generated_at": datetime.datetime.now().astimezone().isoformat(),
  "ip": os.environ.get("REMOTE_ADDR", "unknown"),
}

print("Content-Type: application/json; charset=utf-8")
print()
print(json.dumps(out, indent=2))
