"""
ONE-TIME setup script. Run this once per Facebook login you use to manage
your pages (often once covers all 5 pages if they're all under one Business
Manager / one Facebook login).

What it does:
  1. Opens your browser to Meta's OAuth dialog
  2. Spins up a tiny local server on localhost:8765 to catch the redirect
  3. Exchanges the returned code for a short-lived user token
  4. Exchanges that for a long-lived user token (~60 days)
  5. Calls /me/accounts to list every Facebook Page you admin
  6. For each Page, resolves the linked Instagram Business Account ID
  7. Lets you map each IG account to a content/<channel_folder> name
  8. Saves everything to accounts.json (long-lived tokens don't need to be
     re-entered every run -- see README for the 60-day refresh routine)

Run: python auth_setup.py
"""

import os
import json
import webbrowser
import http.server
import threading
import urllib.parse
import requests
from dotenv import load_dotenv

load_dotenv()

APP_ID = os.environ["META_APP_ID"]
APP_SECRET = os.environ["META_APP_SECRET"]
REDIRECT_URI = os.environ["META_REDIRECT_URI"]
GRAPH_VERSION = "v21.0"
ACCOUNTS_PATH = os.path.join(os.path.dirname(__file__), "accounts.json")

SCOPES = [
    "instagram_basic",
    "instagram_content_publish",
    "pages_show_list",
    "pages_read_engagement",
    "business_management",
]

_auth_code = {}


class _CallbackHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        parsed = urllib.parse.urlparse(self.path)
        qs = urllib.parse.parse_qs(parsed.query)
        if "code" in qs:
            _auth_code["code"] = qs["code"][0]
            body = b"<h2>Login received. You can close this tab and return to the terminal.</h2>"
        else:
            body = b"<h2>No code received. Something went wrong -- check the terminal.</h2>"
        self.send_response(200)
        self.send_header("Content-Type", "text/html")
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass  # keep terminal quiet


def _run_local_server():
    server = http.server.HTTPServer(("localhost", 8765), _CallbackHandler)
    while "code" not in _auth_code:
        server.handle_request()


def get_auth_code() -> str:
    scope_str = ",".join(SCOPES)
    auth_url = (
        f"https://www.facebook.com/{GRAPH_VERSION}/dialog/oauth?"
        f"client_id={APP_ID}&redirect_uri={urllib.parse.quote(REDIRECT_URI)}"
        f"&scope={scope_str}&response_type=code"
    )
    print("Opening browser for Meta login. If it doesn't open, paste this URL manually:\n")
    print(auth_url, "\n")
    webbrowser.open(auth_url)
    _run_local_server()
    return _auth_code["code"]


def exchange_code_for_short_token(code: str) -> str:
    r = requests.get(
        f"https://graph.facebook.com/{GRAPH_VERSION}/oauth/access_token",
        params={
            "client_id": APP_ID,
            "client_secret": APP_SECRET,
            "redirect_uri": REDIRECT_URI,
            "code": code,
        },
    )
    r.raise_for_status()
    return r.json()["access_token"]


def exchange_for_long_lived_token(short_token: str) -> str:
    r = requests.get(
        f"https://graph.facebook.com/{GRAPH_VERSION}/oauth/access_token",
        params={
            "grant_type": "fb_exchange_token",
            "client_id": APP_ID,
            "client_secret": APP_SECRET,
            "fb_exchange_token": short_token,
        },
    )
    r.raise_for_status()
    return r.json()["access_token"]  # valid ~60 days


def list_pages_and_ig_accounts(user_token: str) -> list:
    r = requests.get(
        f"https://graph.facebook.com/{GRAPH_VERSION}/me/accounts",
        params={"access_token": user_token, "fields": "name,access_token,instagram_business_account"},
    )
    r.raise_for_status()
    pages = r.json().get("data", [])

    results = []
    for page in pages:
        ig = page.get("instagram_business_account")
        if not ig:
            continue  # this Page has no linked IG Business/Creator account
        # Fetch the IG username for display purposes
        r2 = requests.get(
            f"https://graph.facebook.com/{GRAPH_VERSION}/{ig['id']}",
            params={"access_token": page["access_token"], "fields": "username"},
        )
        username = r2.json().get("username", "?") if r2.ok else "?"
        results.append({
            "page_name": page["name"],
            "ig_user_id": ig["id"],
            "ig_username": username,
            # Page access tokens inherit the long-lived-ness of the user token
            # that generated them, so this is effectively long-lived too.
            "page_access_token": page["access_token"],
        })
    return results


def main():
    if os.path.exists(ACCOUNTS_PATH):
        with open(ACCOUNTS_PATH, "r", encoding="utf-8") as f:
            accounts = json.load(f)
    else:
        accounts = {}

    code = get_auth_code()
    short_token = exchange_code_for_short_token(code)
    long_token = exchange_for_long_lived_token(short_token)

    ig_accounts = list_pages_and_ig_accounts(long_token)
    if not ig_accounts:
        print("No Instagram Business/Creator accounts found. Make sure each Page")
        print("has a linked Instagram professional account (Page Settings > Linked Accounts).")
        return

    print(f"\nFound {len(ig_accounts)} linked Instagram account(s):\n")
    for i, acc in enumerate(ig_accounts):
        print(f"  [{i}] @{acc['ig_username']}  (Page: {acc['page_name']})")

    print(f"\nYour content/ folder should have one subfolder per account.")
    for acc in ig_accounts:
        folder = input(f"\nFolder name in content/ for @{acc['ig_username']} (e.g. channel1): ").strip()
        if not folder:
            continue
        accounts[folder] = {
            "ig_user_id": acc["ig_user_id"],
            "ig_username": acc["ig_username"],
            "page_access_token": acc["page_access_token"],
        }
        os.makedirs(os.path.join("content", folder, "done"), exist_ok=True)

    with open(ACCOUNTS_PATH, "w", encoding="utf-8") as f:
        json.dump(accounts, f, indent=2)

    print(f"\nSaved {len(accounts)} account(s) to accounts.json.")
    print("Tokens are valid ~60 days. Re-run this script when main.py warns of an expired token.")


if __name__ == "__main__":
    main()
