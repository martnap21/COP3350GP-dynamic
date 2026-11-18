#!/usr/bin/env python3
"""End-to-end demo against a locally running PHP server (two separate cookie jars)."""
import http.cookiejar
import json
import os
import re
import urllib.error
import urllib.request

BASE = os.environ.get("POKER_TEST_URL", "http://127.0.0.1:18987")

class Client:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
        with self.opener.open(BASE + "/") as resp:
            page = resp.read().decode("utf-8")
        match = re.search(r'name="csrf-token" content="([a-f0-9]+)"', page)
        assert match, "CSRF token not in page"
        self.token = match.group(1)

    def state(self):
        with self.opener.open(BASE + "/api.php") as resp:
            return json.load(resp)

    def action(self, action, **kwargs):
        data = json.dumps({"action": action, **kwargs}).encode("utf-8")
        req = urllib.request.Request(
            BASE + "/api.php",
            data=data,
            headers={"Content-Type": "application/json", "X-CSRF-Token": self.token},
            method="POST",
        )
        with self.opener.open(req) as resp:
            return json.load(resp)

alice = Client()
bob = Client()
a = alice.action("join", name="Alice")
b = bob.action("join", name="Bob")
assert len(b["players"]) == 2
a = alice.action("start")
assert a["phase"] == "preflop"
assert a["pot"] == 15
assert a["toCall"] == 5
assert len(a["board"]) == 0

b = bob.state()
assert len(b["players"][0]["hand"]) == 0
assert len(b["players"][1]["hand"]) == 2
assert len(a["players"][0]["hand"]) == 2
assert len(a["players"][1]["hand"]) == 0
assert "deck" not in a

ids = {a["you"]: alice, b["you"]: bob}
assert len(ids) == 2

# Reject an out-of-turn action.
not_actor = next(client for pid, client in ids.items() if pid != a["actor"])
try:
    not_actor.action("check")
    raise AssertionError("Out-of-turn action was accepted")
except urllib.error.HTTPError as err:
    assert err.code == 400

saw = set()
raised = False
for _ in range(40):
    current = alice.state()
    phase = current["phase"]
    if phase == "showdown":
        break
    assert phase in ("preflop", "flop", "turn", "river")
    saw.add(phase)
    expected_board = {"preflop": 0, "flop": 3, "turn": 4, "river": 5}[phase]
    assert len(current["board"]) == expected_board
    actor = current["actor"]
    player = ids[actor]
    view = player.state()
    if phase == "preflop" and not raised:
        current = player.action("raise")
        raised = True
    else:
        current = player.action("call" if view["toCall"] else "check")
else:
    raise AssertionError("Betting rounds did not finish")

assert saw == {"preflop", "flop", "turn", "river"}, saw
assert current["phase"] == "showdown"
assert len(current["board"]) == 5
assert current["winners"]
assert all(len(p["hand"]) == 2 for p in current["players"])
assert sum(p["stack"] for p in current["players"]) == 1000

# Make a second hand and fold, which awards the blinds.
alice.action("lobby")
alice.action("start")
state = alice.state()
winner = ids[state["actor"]].action("fold")
assert winner["phase"] == "showdown"
assert winner["showdownByFold"]
assert sum(p["stack"] for p in winner["players"]) == 1000

print("Two-browser simulated Texas Hold'em game passed.")
