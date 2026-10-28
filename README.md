# **EvoPIM – Community-Driven LTS Fork of Akeneo PIM Community Edition**

> ⚠️ **This is a community fork, not an official Akeneo project.** EvoPIM is maintained independently to provide long-term support for Akeneo PIM Community Edition users. For details, see [CONTRIBUTORS.md](./CONTRIBUTORS.md).

**EvoPIM** is an open-source, community-maintained fork of the **Akeneo PIM Community Edition**.
Its goal is to provide **Long-Term Support (LTS)**, continued improvements, security fixes, and compatibility updates for teams that rely on the community version of Akeneo for product information management.

EvoPIM is built on the foundation of Akeneo CE, extending its life through community contributions and modern package updates.

---

## 🚀 Key Highlights

### **🔒 Long-Term Support (LTS)**

Continuous improvements, bug fixes, dependency upgrades, and security patches.

### **🔧 Compatible Migration**

Users of Akeneo PIM CE can migrate to EvoPIM with minimal changes.

### **🤝 Community-Driven**

Contributions are welcome—from fixes and feature enhancements to documentation improvements.

### **⚡ Stability & Performance**

Focused on maintaining a stable and secure PIM experience for real-world workloads.

---

## 📦 Installation

### Prerequisites

| Requirement | Minimum version |
|-------------|----------------|
| Docker | 24.x |
| Docker Compose | v2.x (`docker compose`) |
| GNU Make | 4.x |
| Git | any |

> **Note:** `composer`, `php`, `yarn`, and `node` are **not** required on the host — everything runs inside Docker containers.

### Quick start (Docker)

One command installs the stack, or repairs an install that is already on disk:

```bash
# 1. Clone the repository
git clone https://github.com/firojahmad07/evo-pim
cd evo-pim

# 2. Configure your local environment
echo "SEARCH_ENGINE=opensearch" > .env.local

# 3. Install or repair
make dev
```

**App will be available at:** `http://localhost:8080`
Default credentials: `admin` / `admin`

**Total install time:** ~10–15 minutes the first time (the webpack build is the longest step). Running `make dev` again reuses dependencies that are already installed.

`make dev` fixes the two failures that leave port 8080 up but the UI broken:

* **HTTP 500, `Unable to create the "cache" directory`.** `var/` or `public/` is owned by the host `www-data` user (uid 33), or Docker Desktop is mapping your user to root inside the container while PHP-FPM runs as uid 1000. The command copies those directories back to your user and makes them writable by PHP-FPM.
* **HTTP 403 on `/bundles/...` images, icons, and fonts.** `public/bundles` symlinks point at a host path such as `/var/www/html/evo-pim`, which is not mounted in the container. The command reinstalls them as relative links under `/srv/pim`.

The Icecat demo catalog is loaded only when `akeneo_pim` has no tables, so a repair does not wipe a database that already has data. To load it again:

```bash
APP_ENV=dev make database O="--catalog src/Akeneo/Platform/Installer/back/src/Infrastructure/Symfony/Resources/fixtures/icecat_demo_dev"
```

### What `make dev` does

```
make prepare-runtime  → reclaim var/ and public/ if another user owns them,
                        make those trees writable, detect Docker Desktop uid remap
make dependencies     → composer install + yarn install
make pim-dev
  ├── make up           → docker compose up -d (all containers)
  ├── wait_docker_up.sh → waits for MySQL + OpenSearch to be ready
  ├── make cache        → clear + warmup Symfony cache
  ├── make assets       → bundle symlinks plus public/js/extensions.json (the file the UI loads after login)
  ├── make front-packages → build TypeScript front-end packages
  ├── make javascript-dev → webpack bundle (dev mode)
  ├── make css          → compile LESS → public/css/pim.css
  ├── make javascript-extensions → update form extensions manifest
  └── database          → load the Icecat demo catalog when the database is empty
```

### Building the Docker image from source

Only needed if you want to build the PHP image locally instead of pulling from the registry:

```bash
docker build --target dev -t evopim/php-dev:master .
```

---

## ⚙️ Local Environment Configuration

EvoPIM ships a `.env` file with safe defaults. **Do not edit `.env` directly.**
Instead, create a `.env.local` file in the project root and override only what you need.
`.env.local` is gitignored and never committed.

### Search Engine

Set which search backend to use:

```bash
# .env.local

# Use OpenSearch (recommended for Docker setup — see docker-compose.yml)
SEARCH_ENGINE=opensearch
APP_INDEX_HOSTS=elasticsearch:9200

# Or use Elasticsearch 8
# SEARCH_ENGINE=elasticsearch
# APP_INDEX_HOSTS=<your-elasticsearch-url>
```

If `SEARCH_ENGINE` is unset or empty, Elasticsearch is used by default.

### Docker host user (HOST_UID / HOST_GID)

`make dev` detects your user with `id -u` / `id -g` and writes `docker/.runtime-user.mk`.

On Docker Engine, PHP runs as that uid, so files created in the container belong to you.

On Docker Desktop the same files appear as uid 0 inside the container, while the dev image runs PHP-FPM as uid 1000. `make dev` then runs console and Yarn as uid 1000 and opens `var/` and `public/` so both the web process and Apache can use them. Apache serves `/css` and `/dist` directly, and follows the relative symlinks in `/bundles`.

Override the uid only for CI or a shared agent:

```bash
# .env.local
HOST_UID=1005
HOST_GID=500
```

`sudo` is not required. If a directory is owned by another account and is not readable, the command stops and prints the `chown` to run.

---

## 🗄️ Database (MySQL)

EvoPIM supports **MySQL 8.0.30 and later, up to (but not including) 9.0**,
which covers both the 8.0 series and **MySQL 8.4 LTS**. The Docker setup ships
`mysql:8.4.9`.

Running on 8.4 needs no configuration change. Doctrine is configured with
`server_version: '8.4'` and a dedicated `MySQL84Platform`, which detects the
datetime format from the returned value, so `DATETIME` and `DATETIME(6)`
columns are both parsed correctly on either server version.

> **Note for custom queries:** MySQL 8.4 changed `GREATEST()` so that mixing a
> `DATETIME` column with a non-datetime fallback — for example
> `GREATEST(a.updated, COALESCE(b.updated, 0))` — promotes the result to
> `DATETIME(6)` and yields values with microseconds. Code that parses those
> values with a fixed `'Y-m-d H:i:s'` format then fails. Use a datetime column
> as the fallback instead: `GREATEST(a.updated, COALESCE(b.updated, a.updated))`.

---

## 🔍 Search Engine (Elasticsearch or OpenSearch)

EvoPIM runs on **Elasticsearch 8** by default and also supports
**OpenSearch 2.x** as an alternative. The choice is made by the
`SEARCH_ENGINE` environment variable; the rest of the codebase is
engine-agnostic.

### Selecting the engine

Set `SEARCH_ENGINE` in your `.env.local` file (see [Local Environment Configuration](#️-local-environment-configuration)):

```bash
# .env.local

# Default. Native Elasticsearch 8 client.
SEARCH_ENGINE=elasticsearch
APP_INDEX_HOSTS=<your-elasticsearch-url>

# Or opt into OpenSearch.
# SEARCH_ENGINE=opensearch
# APP_INDEX_HOSTS=<your-opensearch-url>
```

`SEARCH_ENGINE` and `APP_INDEX_HOSTS` must point at the same engine. If
`SEARCH_ENGINE` is unset or empty, Elasticsearch is used.

After switching engines, clear the Symfony cache and re-index:

```bash
rm -rf var/cache/*
php bin/console akeneo:elasticsearch:reset-indexes --env=prod
php bin/console pim:product:index --all --env=prod
php bin/console pim:product-model:index --all --env=prod
```

### How it works

When `SEARCH_ENGINE=opensearch`, the `akeneo_elasticsearch.client_builder`
service returns an `OpenSearchClientBuilderAdapter` instead of the native
`Elastic\Elasticsearch\ClientBuilder`. The adapter wraps the real
`OpenSearch\Client` so calls like `->index()`, `->search()`, and
`->indices()->putMapping()` route to OpenSearch, and translates
`OpenSearch\Common\Exceptions\OpenSearchException` (and its subclasses)
into `Elastic\Elasticsearch\Exception\ClientResponseException` or
`ServerResponseException`, preserving the HTTP status on the attached
PSR-7 response. Existing `catch (ElasticsearchException $e)` blocks
across EvoPIM keep working unchanged.

The adapter layer lives at
`src/Akeneo/Tool/Bundle/ElasticsearchBundle/SearchEngine/` and is the
only place that references both vendor SDKs. The Elasticsearch path is
unchanged — when `SEARCH_ENGINE` is `elasticsearch` (or unset),
`SearchEngineClientBuilderFactory` returns the native
`Elastic\Elasticsearch\ClientBuilder` and the rest of the codebase runs
against Elasticsearch exactly as before.

### Spec coverage

The OS→ES exception translator is unit-tested at
`src/Akeneo/Tool/Bundle/ElasticsearchBundle/spec/SearchEngine/SearchEngineExceptionTranslatorSpec.php`.

```bash
vendor/bin/phpspec run src/Akeneo/Tool/Bundle/ElasticsearchBundle/spec
```

### Pinning notes

`opensearch-project/opensearch-php` is pinned at `~2.3.1`. Newer 2.x
releases require `psr/http-message ^2.0`, which conflicts with other
EvoPIM dependencies.

### Common pitfalls

| Symptom | Cause | Fix |
|---|---|---|
| `ProductCheckException: server is not Elasticsearch` | The Elasticsearch client is talking to an OpenSearch server | Set `SEARCH_ENGINE=opensearch`, then `rm -rf var/cache/*` |
| `alias missing` (404) on a fresh OpenSearch instance | First-run reset before any indexes exist | Re-run `akeneo:elasticsearch:reset-indexes` — it is idempotent and will create the missing aliases |
| UI shows zero products after switching | The search index for the new engine is empty (MySQL data is shared, the search index is per-engine) | Run `pim:product:index --all` and `pim:product-model:index --all` |
| `cache:clear` fails | The compiled cache references stale class names | `rm -rf var/cache/*` instead of `cache:clear` |
| `composer install` drops `opensearch-php` | The lock file may not track it | `composer require opensearch-project/opensearch-php:~2.3.1` |

---

## 🔄 Upgrading

EvoPIM follows Akeneo CE’s upgrade flow, making transitions simple and predictable.

👉 **Upgrade Guide (Akeneo process compatible)**
[https://docs.akeneo.com/master/migrate_pim/index.html](https://docs.akeneo.com/master/migrate_pim/index.html)

---

## 🧪 Testing

Testing workflows are the same as Akeneo CE.
You can follow their testing documentation to run unit, integration, and end-to-end tests.

👉 **Testing Guide**
[https://github.com/akeneo/pim-community-dev/blob/master/internal_doc/tests/running_the_tests.md](https://github.com/akeneo/pim-community-dev/blob/master/internal_doc/tests/running_the_tests.md)

---

## 🤝 Contributing to EvoPIM

We encourage the community to participate and help improve the project.

Please read our **Contributing Guidelines** before submitting a pull request:

👉 [CONTRIBUTING.md](./CONTRIBUTING.md)

Contributions may include:

* Bug fixes
* Documentation improvements
* Dependency updates
* Feature enhancements
* Security improvements

A respectful and collaborative environment is expected from everyone involved.

---

## 📄 License

EvoPIM is distributed under the **Open Software License (OSL 3.0)**, the same license used by Akeneo PIM CE.

👉 [LICENSE.txt](./LICENSE.txt)

---

## 🎯 Why EvoPIM?

* **Continued Support:** Ongoing maintenance for users of Akeneo CE
* **Security First:** Regular patches and dependency updates
* **Predictable Migration:** Drop-in replacement for Akeneo CE
* **Community Ownership:** Built and improved by open-source contributors
* **Open & Transparent:** 100% free and community-governed

---

## ℹ️ About the Project

**EvoPIM** aims to ensure a long-term, stable future for the Akeneo Community Edition ecosystem by:

* Providing LTS
* Ensuring modern PHP, Symfony, and package compatibility
* Maintaining open-source availability for businesses and developers
* Encouraging community collaboration and contributions

Whether you're using Akeneo CE for small catalogs or managing large-scale product data, EvoPIM helps you continue with confidence.

---

## 🔗 Useful Links

* **Akeneo PIM Documentation**
  [https://docs.akeneo.com/](https://docs.akeneo.com/)

* **EvoPIM Repository**
  [https://github.com/firojahmad07/evo-pim](https://github.com/firojahmad07/evo-pim)

* **Contributing Guide**
  ./CONTRIBUTING.md

* **Issue Tracker**
  [https://github.com/firojahmad07/evo-pim/issues](https://github.com/firojahmad07/evo-pim/issues)

---

## 💬 Final Note

EvoPIM is built for the community and maintained by the community.
If you're looking for a stable, secure, and continuously maintained version of Akeneo CE, EvoPIM is the ideal choice.

Join us, contribute, and help keep the open-source PIM ecosystem strong.
 
