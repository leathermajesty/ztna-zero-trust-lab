# Architecture

Two ideas do the work here: segmentation limits **where** an attacker can go, and
identity limits **what** they can do. Neither is enough on its own — a firewall
can't tell who you are, and a login doesn't stop lateral movement. Put them
together and you get defence in depth.

## Zones

```
WAN (untrusted)  --  OPNsense  --  DMZ 172.16.10.0/24  (semi-trusted)
                         |
                         +------  User zone 192.168.56.0/24  (trusted, still verified)
```

The DMZ holds everything internet-facing: Keycloak, the proxies, the apps, the
database. The firewall lets the DMZ make only DNS and HTTPS connections out, and
blocks it from reaching the user zone at all. The database never publishes a port,
so even a fully compromised DMZ host can't reach it directly — that protection
comes from Docker, not the firewall.

## The login flow

Every request to an app goes through oauth2-proxy, which does the OpenID Connect
dance with Keycloak:

1. Browser hits the app. oauth2-proxy sees no session and redirects to Keycloak.
2. User enters their password (and a TOTP code, if MFA is on for them).
3. Keycloak returns a code; oauth2-proxy swaps it for a token using the client
   secret. The token carries the user's realm roles.
4. oauth2-proxy checks the role against its `--allowed-role` list. If it matches,
   the request goes upstream; if not, it's a 403.

The app behind the proxy receives trusted headers — `X-Forwarded-Preferred-Username`,
`X-Forwarded-Groups` (the roles), `X-Forwarded-Email`. The proxy strips any of
these that the client tried to send, so the app can trust them.

## Access control, on two levels

**Role tiers — enforced at the proxy (network level).** One proxy per service,
each with its own allowed roles:

| Service | Port | Who gets in |
|---|---|---|
| Portal | 4180 | director, faculty, student |
| Faculty Zone | 4181 | director, faculty |
| Admin Zone | 4182 | director |

A student can't even reach the Admin Zone — the proxy returns 403 before the app
runs. This is the strongest layer.

**Per-user ownership — enforced in the app.** Same role, different data:

- In the Faculty Zone, each lecturer sees only their own classes. `prof1` and
  `prof2` both have the `faculty` role, but `prof1` opening `prof2`'s class gets a
  403 from the app.
- In the Portal, each student sees only their own results. `stud1` asking for
  `stud2`'s record gets a 403.

The app does this by comparing the verified username in the header against the
owner of the resource. Role alone can't express "your own stuff" — that's why this
check lives in the app, not the proxy.

## The Zero Trust extras

- **MFA (TOTP).** Configure OTP as a required action in Keycloak; the user adds an
  authenticator app on first login. A stolen password alone then isn't enough.
- **Session timeout.** Short SSO idle/max in Keycloak plus `--cookie-expire` on the
  proxy, so access expires and has to be renewed.
- **Brute-force lockout.** Keycloak's brute-force detection locks an account after
  a few failures, and shows the same generic error so an attacker learns nothing.
- **Event logging.** Keycloak records every login attempt with the user, time and
  source IP — the audit trail.
- **Admin console hardening.** Delete the weak bootstrap admin, replace it with a
  named admin that has a strong password and MFA, and enable lockout on the master
  realm too.

## Why not just give faculty an admin role?

A tempting shortcut for "faculty can create students" is to hand faculty Keycloak's
`manage-users` role directly. Don't — that gives them the whole admin console and
lets them manage everyone. The right way is a scoped service account the app uses
on their behalf, with the role hard-coded to `student` so they can't escalate.
Least privilege, which is the whole point.
