# Attacking the lab

Run these from Kali, inside the DMZ (`172.16.10.20`). The idea is to play an
attacker who has already compromised a public-facing server and now tries to go
deeper — which is the realistic case, since DMZ hosts are the most exposed.

**Only ever point these at your own lab addresses.**

## 1. Look around

```bash
nmap -sn 172.16.10.0/24
```
Finds the three DMZ hosts (firewall, Docker host, Kali itself). Segmentation
doesn't hide hosts in your own zone — that's expected.

```bash
nmap -p- 172.16.10.10
```
Full port scan of the Docker host. You should see only SSH (22), the proxy (4180),
and Keycloak (8080). Small attack surface, and no database port.

## 2. Try to reach the database

```bash
nmap -p 5432 172.16.10.10
```
Closed. PostgreSQL only listens on Docker's internal network, so it's never exposed
to the DMZ — even from a host sitting right next to it.

## 3. Try to skip the login

```bash
curl -s -L http://172.16.10.10:4180/ | grep -o "Sign in to [a-z-]*"
```
You get the Keycloak login page, not the app. The proxy intercepts every request.

## 4. Try to reach the user zone

```bash
nmap -sn 192.168.56.0/24
```
After the firewall is locked down, this finds nothing (0 hosts). The attacker is
trapped in the DMZ.

To test the rule itself (rather than just "no host answered"), probe a user-zone
address directly:

```bash
nmap -Pn -p 22,80,443 192.168.56.2
```
All filtered. If 443 comes back **closed** instead of filtered, a packet reached
the far side — which means an allow rule is sitting above your block rule. Move the
block rule to the top.

## 5. Try the identity layer

Send a login straight to Keycloak's token endpoint, behind the proxy's back:

```bash
curl -s -X POST \
  http://172.16.10.10:8080/realms/ztna-lab/protocol/openid-connect/token \
  -d client_id=app-proxy -d username=stud1 -d password=wrong -d grant_type=password
```
You get `unauthorized_client` — without the client secret, Keycloak won't even
process the request, so the password is never checked.

Then in the browser: log in as a student and try to open the Admin Zone
(`:4182`), or ask for another student's record (`:4180/?student=stud2`). Both are
403. Role gets you through the gate; ownership decides your data.

## 6. Guess passwords

Type a wrong password for one user five times on the login page, then the correct
one. It's still refused — the account is locked. Keycloak's event log (admin
console > the user > Events) shows the failed attempts, the lockout, and the source
IP. The login page shows the same generic error throughout, so the attacker can't
tell the account exists or that they were detected.

## What it proves

| Attack | Result |
|---|---|
| Scan the DMZ | hosts visible (same zone) |
| Reach the database | closed |
| Reach the user zone | 0 hosts / filtered |
| App without login | login page only |
| Direct token request | 401, no client secret |
| Wrong role / other user's data | 403 |
| Password guessing | locked after 5 tries, logged |

Segmentation keeps the attacker in the DMZ; identity keeps them out of the apps and
each other's data. That's the point.
