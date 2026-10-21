# Free hosting options (Stage 6 decision)

Checked on 21/10/2026 from the providers' own pages and two dated comparison articles
(sources at the end). Free tiers change often: re-check before you sign up.

## What the dashboard needs

| Need | Why |
|---|---|
| PHP 8.2+ and MySQL 8 or MariaDB 10.4+ | Tested on PHP 8.2/8.3, MariaDB 10.4/10.11, MySQL 8.0 |
| HTTPS | Secure cookie, service worker (offline mode) only works on HTTPS |
| Secrets in host settings, not files | NFR-11, master prompt |
| TLS to the database | NFR-11 |
| Inbound API calls from non-browsers | Scheduled backup endpoint, CI smoke checks |
| No credit card | Owner requirement for a free prototype |
| Git deploy | Every push to main redeploys |

## Options compared

| Option | Card | Always on | PHP + MySQL | Secrets as env vars | Git deploy | Main limits | Fit |
|---|---|---|---|---|---|---|---|
| Render free web service (Docker) + Aiven free MySQL | No | No: sleeps after 15 min idle, about 1 min to wake | Yes (Docker image) + MySQL 8, 1 GB RAM, 1 GB disk, backups included | Yes | Yes, auto on push | 750 instance hours a month; 512 MB RAM; Aiven may power off an idle service after notice; one free MySQL per organisation | Recommended |
| InfinityFree (shared PHP hosting) | No | Yes | Yes, PHP 8.3 + MySQL on the same host | No (upload .env by FTP) | No (FTP) | Its browser security check blocks non-browser API calls, so the scheduled backup and CI checks cannot reach it; 10 s PHP time limit; about 50,000 hits a day; no cron | Fallback for a quick always-on demo |
| Koyeb free web service + Aiven | Yes ($29 hold since 02/2026) | No: scales to zero after 1 h idle | Yes (Docker) | Yes | Yes | One free service; Frankfurt or Washington only | Not chosen: card hold |
| Oracle Cloud Always Free VM | Yes | Yes | You install everything | Yes | Over SSH | You patch and secure the server; ARM capacity often out of stock | Not chosen: card and server administration |
| Railway | No | Until credit ends | Yes | Yes | Yes | $5 one-time credit, then $1 a month | Not chosen: not lasting free |
| Render free Postgres | No | - | Postgres, not MySQL | - | - | Deleted after 30 days | Not suitable |

## Recommendation: Render (web) + Aiven (MySQL)

1. Matches the design. The app was built host-agnostic and stateless: database sessions, settings from environment variables, no local disk, no cron, TLS to the database (NFR-11). Render runs the Docker image unchanged, and the same image is built and smoke-tested by GitHub Actions on every push.
2. Security. Secrets live in Render's settings page and Aiven's console, never in the repository. Aiven requires TLS. The app account is least-privilege with an append-only audit trail (tested on MySQL 8.0 with `sql_require_primary_key` on, as Aiven uses).
3. Cost and access. Neither needs a card, and both sign in with GitHub or e-mail.
4. Backups. Aiven's free MySQL includes backups. The app's own encrypted backup is pulled off-site by a GitHub Actions workflow (manual until the repository is private).
5. Known cost of "free": the web service sleeps after 15 minutes without visitors. The first visit after a pause takes about a minute. The app already shows "Waking up the server" and the installed app shell opens from the phone's cache. For an MSc evaluation with 15 participants this is acceptable. For daily company use, a paid Render instance removes the sleep (check the current price first).

Region: choose Frankfurt on Render and the nearest European region Aiven offers for the free plan. Accra to Frankfurt is the shortest route among Render's regions.

Data protection: both providers store data outside Ghana. With synthetic data only this is fine. Before any real company or personal data, review Ghana's Data Protection Act, 2012 (Act 843), register with the Data Protection Commission and sign the providers' data processing terms (NFR-13).

## Sources

- Render, Deploy for Free: https://render.com/docs/free
- Render regions: https://render.com/docs/regions
- Aiven free plan: https://aiven.io/docs/platform/concepts/free-plan
- Aiven MySQL free tier: https://aiven.io/docs/products/mysql/concepts/mysql-free-tier
- Koyeb pricing: https://www.koyeb.com/pricing
- InfinityFree, API access and the browser security system: https://forum.infinityfree.com/t/why-isnt-api-access-working-on-my-website/115198/1
- DeployHQ, free PHP hosting options (updated 16/09/2026): https://deployhq.com/blog/how-to-deploy-a-php-website-for-free
- SnapDeploy, state of free hosting (verified 26 to 28/09/2026): https://snapdeploy.dev/state-of-free-hosting
