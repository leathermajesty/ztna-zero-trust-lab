# Zero Trust Lab — DMZ segmentation + identity-based access control

A small Zero Trust network I built for my final-year cyber security project, and
then attacked to see if it actually holds up. Three VMs: a firewall that splits
the network into zones, a Docker host running the identity stack and a role-based
web portal, and a Kali box sitting inside the DMZ playing the attacker.

The whole point, in one line: **being on the right network is not enough.** A user
with a valid password, on the same subnet as everyone else, still gets a 403 if
their role doesn't allow it.

```
            Internet / attacker
                    |
              [ OPNsense ]            <- firewall, 3 zones, first-match rules
              /          \
   DMZ 172.16.10.0/24     User zone 192.168.56.0/24
   - Keycloak (IdP)        - workstations (blocked from the DMZ)
   - oauth2-proxy (gate)
   - portal / faculty / admin apps
   - PostgreSQL (never exposed)
   - Kali attacker 172.16.10.20
```

## What's inside

- An **OPNsense** firewall with three zones (WAN, DMZ, internal user network) and
  rules that stop the DMZ from reaching the user zone.
- **Keycloak** as the identity provider (OpenID Connect) and **oauth2-proxy** in
  front of every app as the policy gate — nothing reaches an app without a login
  and the right role.
- A university-style portal with real role-based access:
  - **students** see their own results, and only their own,
  - **faculty** manage their own classes (prof1 can't touch prof2's),
  - **directors** get an admin area nobody else can open.
- The usual Zero Trust extras: TOTP multi-factor auth, short session timeouts,
  brute-force lockout, and login event logging.
- A set of attacks from inside the DMZ (nmap / curl / netcat) with the results you
  should expect, so you can check your own build.

Access control works on two levels. The **proxy** decides which role can reach
which service — a student literally can't get past the gate to the admin area,
they get a 403 before the app even runs. The **app** then decides which resources
you own (your classes, your records), using the identity header the proxy sets and
the app can trust.

## The stack

OPNsense · Keycloak · oauth2-proxy · Docker / Docker Compose · PostgreSQL · PHP ·
Kali Linux · VirtualBox. Attacks with nmap, curl and netcat.

## Setup

The lab is three VirtualBox VMs, and you can't really automate that part, so
[docs/setup.md](docs/setup.md) walks through all of it: the VMs, the network, the
firewall rules, Keycloak, and the Docker stack. Once the VMs and Keycloak are up,
the app side is just:

```bash
cd docker
cp .env.example .env     # fill in the secrets
docker compose up -d
```

[docs/architecture.md](docs/architecture.md) explains the design and the access
model. [docs/attacks.md](docs/attacks.md) has the attack tests.

## A few things that will trip you up

- **Ping from the DMZ to the firewall fails on purpose.** The firewall only allows
  DNS and HTTPS out of the DMZ; ping is dropped. Check the link with `ip neigh`
  instead of `ping`.
- **There are two different logins.** The apps are at `:4180` / `:4181` / `:4182`
  and use the `ztna-lab` realm (dean, prof1, …). The Keycloak admin console is at
  `:8080/admin` and uses the master-realm admin. Don't mix them up — I did, more
  than once.
- **Firewall rule order matters.** A broad allow rule sitting above your block rule
  will silently defeat it. The block-DMZ-to-users rule has to be at the top. I only
  caught this by re-testing after I thought the lab was finished.

## Security notes

This is a lab, not production. It runs over plain HTTP, the few certificates are
self-signed, and the passwords in the setup guide are examples — change them. The
Keycloak client secret and the proxy cookie secret live in a `.env` file that is
**not** committed (see `.gitignore`). And only point the attack tools at your own
lab addresses.

## License

MIT — see [LICENSE](LICENSE). It's a learning project, so use it however you like.
