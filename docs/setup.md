# Setup

This builds the whole lab from scratch: three VirtualBox VMs, the network, the
firewall, Keycloak, and the apps. Budget a few hours the first time — most of it
is waiting for installers and Keycloak to boot.

If you just want the apps and already have Keycloak running, skip to
[Step 5](#step-5--deploy-the-apps).

## What you need

- A host machine with virtualization enabled in the BIOS and ~60 GB free.
- VirtualBox.
- ISOs: OPNsense (amd64 dvd), Ubuntu Server 24.04 LTS, Kali Linux (the VirtualBox image is easiest).

## The three VMs

| VM | OS | Role | Address |
|---|---|---|---|
| `fw-opnsense` | OPNsense | Firewall between the zones | LAN 192.168.56.2, DMZ 172.16.10.1, WAN via NAT |
| `dmz-docker` | Ubuntu Server + Docker | Runs Keycloak, the proxies and the apps | 172.16.10.10 |
| `kali` | Kali Linux | Attacker and demo browser | 172.16.10.20 |

Three zones: the internet side (WAN), the DMZ (`172.16.10.0/24`) for public-facing
services, and the user zone (`192.168.56.0/24`) for workstations. The firewall is
the only thing connecting them.

### VirtualBox networks (make these first)

- **Host-only network** `192.168.56.0/24`, DHCP **off** — this is the user zone.
  (VirtualBox > Tools > Network Manager > Host-only Networks > Create.)
- **Internal network** named `dmz` — this is the DMZ. It appears on its own once
  a VM uses it; nothing to pre-create.
- **NAT** — the WAN / internet side.

## Step 1 — OPNsense firewall

Create the VM (FreeBSD 64-bit, ~4 GB RAM, 16 GB disk) with **three** network
adapters, in this order:

| Adapter | Attached to | Becomes |
|---|---|---|
| Adapter 1 | Host-only (192.168.56.x) | LAN (em0) |
| Adapter 2 | NAT | WAN (em1) |
| Adapter 3 | Internal network `dmz` | OPT1 / DMZ (em2) |

Install OPNsense to the disk. At the console, assign interfaces as **WAN=em1,
LAN=em0, OPT1=em2**, then set:

- LAN `192.168.56.2/24`
- OPT1 (DMZ) `172.16.10.1/24`, no DHCP
- WAN gets its address from NAT

> Never disable a firewall adapter once it's assigned. OPNsense names interfaces
> in the order it detects them, so removing one renumbers the rest and you lose
> the DMZ. (Ask me how I know.)

Open the web UI from the host at `https://192.168.56.2`, run the wizard (on the
WAN, untick "block private networks" and "block bogon networks", because the WAN
is itself a private NAT address), and enable the OPT1 interface as `DMZ`.

### Firewall rules (the important part)

Rules are evaluated top to bottom and the **first match wins**, so order matters.
On **Firewall > Rules > DMZ**, in this order:

| # | Action | Source | Destination | Port |
|---|---|---|---|---|
| 1 | Block | DMZ net | LAN net | any |
| 2 | Pass | DMZ net | any | 53 (DNS) |
| 3 | Pass | DMZ net | any | 443 (HTTPS) |

The block rule has to be **first**. If an allow rule sits above it, DMZ traffic
reaches the user zone on that port before the block is ever checked. Everything
not allowed is dropped — including ping, which is expected.

## Step 2 — Docker host (dmz-docker)

Ubuntu Server 24.04, ~4 GB RAM, 25 GB disk, **one** adapter on the internal
network `dmz`. During install set a static address `172.16.10.10/24`, gateway
`172.16.10.1`, nameserver `8.8.8.8`, and enable OpenSSH.

Install Docker (the official `get.docker.com` script) and add your user to the
`docker` group. Check the link to the firewall — ping won't answer (it's blocked),
so check the ARP entry instead:

```bash
ping -c 2 172.16.10.1          # will fail, that's fine
ip neigh show 172.16.10.1      # REACHABLE means the link is good
```

## Step 3 — Kali attacker

Import the Kali VirtualBox image. Give it **one** adapter on the internal network
`dmz` — nothing else (a second NAT or host-only adapter would let it bypass the
firewall and ruin the test). Kali forgets its IP on reboot, so set it each time:

```bash
sudo ip addr add 172.16.10.20/24 dev eth0
sudo ip route add default via 172.16.10.1
```

## Step 4 — Keycloak

Bring up just Keycloak and its database first (you'll add the apps in Step 5):

```bash
cd docker
cp .env.example .env     # fill in DB_PASSWORD and the KC_ADMIN_* values
docker compose up -d postgres keycloak
```

Give it a minute, then open `http://172.16.10.10:8080/admin` and log in with the
bootstrap admin from your `.env`. Now configure the realm — see
[../keycloak/README.md](../keycloak/README.md) for the full walk-through. In short:

1. Create a realm called **`ztna-lab`**.
2. Create a confidential client **`app-proxy`** (client authentication on). Set its
   **Valid redirect URIs** to all three:
   ```
   http://172.16.10.10:4180/oauth2/callback
   http://172.16.10.10:4181/oauth2/callback
   http://172.16.10.10:4182/oauth2/callback
   ```
   Add an **Audience** mapper for `app-proxy` (oauth2-proxy rejects the token
   without the right `aud`). Copy the **client secret** from the Credentials tab
   into `CLIENT_SECRET` in your `.env`.
3. Create three **realm roles**: `director`, `faculty`, `student`.
4. Create users and assign one role each (set a password, Temporary off):

   | User | Role |
   |---|---|
   | `dean` | director |
   | `prof1`, `prof2` | faculty |
   | `stud1`, `stud2` | student |

Optionally turn on MFA (Required action: Configure OTP), brute-force detection
(Security defenses), and event logging — see architecture.md.

## Step 5 — Deploy the apps

Generate a cookie secret and finish the `.env`:

```bash
openssl rand -base64 32 | tr -- '+/' '-_'   # paste into COOKIE_SECRET
```

Then bring everything up:

```bash
cd docker
docker compose up -d
docker compose ps
```

You should see `postgres`, `keycloak`, `portal`, `faculty`, `admin`, and the three
`oauth2-proxy*` services all up.

## Step 6 — Test it

From Kali's browser (use a fresh private window for each user):

| Log in as | Open | Expected |
|---|---|---|
| `dean` (director) | `http://172.16.10.10:4180` | portal, both zones unlocked |
| `dean` | `:4182` | Admin Zone opens |
| `prof1` (faculty) | `:4181` | sees only prof1's classes; `?class=EE201` → 403 |
| `prof1` | `:4182` | 403 (not director) |
| `stud1` (student) | `:4180` | portal; faculty/admin locked; own results only |
| `stud1` | `:4180/?student=stud2` | 403 (not your record) |
| `stud1` | `:4181` or `:4182` | 403 at the proxy |

If a login ever shows a 403 you didn't expect, check that user's role mapping in
Keycloak, and remember to use a fresh private window — a stale cookie causes
confusing 403s.

See [attacks.md](attacks.md) to attack the lab from Kali and confirm the firewall
and the proxies are doing their job.
