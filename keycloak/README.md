# Keycloak setup

Everything here is done in the admin console at `http://<HOST_IP>:8080/admin`,
logged in with the bootstrap admin from your `.env`. Switch the realm dropdown
(top-left) to `ztna-lab` after you create it — a lot of "it doesn't work" turns out
to be editing the wrong realm.

## 1. Realm

Create a realm named **`ztna-lab`**.

## 2. Client

Create a client:

- Client ID: **`app-proxy`**
- Client authentication: **On** (this makes it a confidential client with a secret)
- Standard flow: on
- Valid redirect URIs (add all three):
  ```
  http://<HOST_IP>:4180/oauth2/callback
  http://<HOST_IP>:4181/oauth2/callback
  http://<HOST_IP>:4182/oauth2/callback
  ```

Then two things that are easy to miss:

- **Credentials tab** → copy the **Client secret** into `CLIENT_SECRET` in your
  `docker/.env`.
- **Client scopes** → `app-proxy-dedicated` → **Add mapper** → **Audience**. Set the
  included client audience to `app-proxy`. Without this, the token has no matching
  `aud` and oauth2-proxy answers with a 500.

## 3. Roles

Under **Realm roles**, create three: `director`, `faculty`, `student`.

## 4. Users

Create each user (Users → Create new user), set a password on the **Credentials**
tab with **Temporary off**, then assign the role on **Role mapping** (filter by
realm roles):

| Username | Role |
|---|---|
| `dean` | director |
| `prof1` | faculty |
| `prof2` | faculty |
| `stud1` | student |
| `stud2` | student |

Give each a dummy email (e.g. `dean@example.local`) — oauth2-proxy expects an email
claim.

## 5. Optional — the Zero Trust extras

- **MFA:** Authentication → Required actions → enable **Configure OTP**. Then per
  user, add Configure OTP as a required action; they set it up on next login.
- **Brute-force lockout:** Realm settings → Security defenses → Brute force
  detection → Lockout temporarily, max 5 failures.
- **Event logging:** Realm settings → Events → turn on **Save events**.
- **Harden the admin:** in the `master` realm, create a named admin with a strong
  password and MFA, then delete the bootstrap admin.

## Sharing a ready-made realm (optional)

If you want others to skip the manual steps, export your working realm and commit
the file (it contains no live sessions, but **does** contain the client secret, so
regenerate the secret afterwards or scrub it from the export):

```bash
docker compose exec keycloak \
  /opt/keycloak/bin/kc.sh export --realm ztna-lab --file /tmp/ztna-lab-realm.json
docker compose cp keycloak:/tmp/ztna-lab-realm.json ./keycloak/
```

To import it on a fresh instance, mount it into `/opt/keycloak/data/import/` and
start Keycloak with `--import-realm`.
