# Docker Implementation - Git Branching Strategy

**Tanggal:** 4 Desember 2025  
**Project:** Blazz - Docker Adoption  
**Reference:** GitFlow + GitHub Flow Best Practices  
**Current Branch:** staging

---

## 📋 Executive Summary

Berdasarkan riset dari:
- [Atlassian GitFlow Workflow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow)
- [Vincent Driessen's Git Branching Model](https://nvie.com/posts/a-successful-git-branching-model/)
- [GitHub Flow](https://docs.github.com/en/get-started/using-github/github-flow)

**Rekomendasi:** Gunakan **Feature Branch dari Staging** untuk Docker implementation.

---

## 🔍 Current Repository Analysis

```
Current Branches:
* staging (current)
  main
  staging-broadcast
  staging-broadcast-arch
  staging-broadcast-campaign
  ... (many feature branches)

Recent History:
- staging sudah sync dengan main
- PR workflow sudah digunakan
- Pattern: staging-* untuk feature branches
```

**Observation:** Repository sudah menggunakan GitFlow-like workflow dengan staging sebagai integration branch.

---

## ✅ RECOMMENDED: Feature Branch Strategy

### Mengapa Feature Branch dari Staging?

Berdasarkan best practices dari Atlassian dan GitHub:

> "Feature branches are generally created off to the latest develop branch."
> — Atlassian GitFlow

> "A short, descriptive branch name enables your collaborators to see ongoing work at a glance."
> — GitHub Flow

### Keuntungan:

| Aspect | Direct to Staging | Feature Branch ✅ |
|--------|-------------------|-------------------|
| Code Review | ❌ No PR | ✅ PR Required |
| Rollback | ❌ Complex | ✅ Easy revert |
| Collaboration | ❌ Single point | ✅ Team can review |
| History | ❌ Mixed commits | ✅ Atomic changes |
| Testing | ❌ On staging | ✅ Local first |

---

## 🔄 Recommended Branching Strategy

### Branch Structure

```
main (production-ready)
  │
  └── staging (integration/testing)
        │
        └── feature/docker-setup (Docker implementation)
              ├── Dockerfile configurations
              ├── compose.yaml
              ├── CI/CD workflows
              └── Documentation
```

### Branch Roles

| Branch | Purpose | Protected | Deploys To |
|--------|---------|-----------|------------|
| `main` | Production-ready code | ✅ Yes | Production |
| `staging` | Integration testing | ✅ Yes | Staging Server |
| `feature/docker-setup` | Docker implementation | ❌ No | Local/Dev |

---

## 📝 Strategi yang Direkomendasikan: Feature Branch dari Staging

### Why This Approach?

1. **Main branch tetap clean** - Tidak ada experimental code
2. **Staging sebagai integration point** - Test sebelum ke production
3. **Easy rollback** - Jika gagal, staging bisa di-reset
4. **Clear history** - PR-based workflow dengan review

### Workflow Steps

```
┌─────────────────────────────────────────────────────────────────┐
│                         WORKFLOW                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Create feature branch from staging                          │
│     git checkout staging                                         │
│     git pull origin staging                                      │
│     git checkout -b feature/docker-setup                         │
│                                                                  │
│  2. Develop & commit Docker configurations                       │
│     - Dockerfiles                                                │
│     - compose.yaml                                               │
│     - .dockerignore                                              │
│     - CI/CD workflows                                            │
│                                                                  │
│  3. Test locally                                                 │
│     docker compose up -d                                         │
│     Run integration tests                                        │
│                                                                  │
│  4. Push to remote & create PR to staging                        │
│     git push origin feature/docker-setup                         │
│     Create Pull Request: feature/docker-setup → staging          │
│                                                                  │
│  5. Code review & merge to staging                               │
│     Review, approve, merge                                       │
│                                                                  │
│  6. Deploy & test on staging server                              │
│     Test all services in staging environment                     │
│                                                                  │
│  7. Create PR from staging to main                               │
│     After staging tests pass                                     │
│     Create Pull Request: staging → main                          │
│                                                                  │
│  8. Deploy to production                                         │
│     Tag release version                                          │
│     Deploy to production                                         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🛠️ Implementation Steps

### Step 1: Setup Feature Branch (Day 1)

```bash
# Ensure staging is up to date
git checkout staging
git pull origin staging

# Create feature branch for Docker
git checkout -b feature/docker-setup

# Push branch to remote
git push -u origin feature/docker-setup
```

### Step 2: Create Directory Structure (Day 1)

```bash
# Create Docker configuration directories
mkdir -p docker/app
mkdir -p docker/nginx/conf.d
mkdir -p docker/mysql/init
mkdir -p docker/node
mkdir -p .github/workflows
```

### Step 3: Implement Docker Files (Day 1-4)

Files to create in `feature/docker-setup`:

```
docker/
├── app/
│   ├── Dockerfile           # Laravel PHP-FPM
│   └── php.ini              # PHP configuration
├── nginx/
│   ├── nginx.conf           # Main Nginx config
│   └── conf.d/
│       └── default.conf     # Server blocks
├── mysql/
│   └── init/
│       └── init.sql         # Initial DB setup
└── node/
    └── Dockerfile           # Vite dev server

whatsapp-service/
└── Dockerfile               # WhatsApp + Puppeteer

compose.yaml                 # Main compose file
compose.dev.yaml             # Development overrides
compose.prod.yaml            # Production overrides
.dockerignore                # Docker ignore rules

.github/workflows/
├── docker-build.yml         # Build & test
└── docker-deploy.yml        # Deploy to staging/prod
```

### Step 4: Commit Strategy

```bash
# Use conventional commits for clarity
git commit -m "feat(docker): add Laravel app Dockerfile"
git commit -m "feat(docker): add compose.yaml with all services"
git commit -m "feat(docker): add WhatsApp service Dockerfile with Puppeteer"
git commit -m "feat(docker): add nginx reverse proxy config"
git commit -m "feat(docker): add GitHub Actions CI/CD workflows"
git commit -m "docs(docker): update implementation documentation"
```

### Step 5: Create PR to Staging (Day 4-5)

```bash
# Push all changes
git push origin feature/docker-setup

# Create Pull Request via GitHub
# Title: feat(docker): Docker containerization implementation
# Target: staging
```

**PR Template:**
```markdown
## Description
Implements Docker containerization for Blazz application.

## Changes
- [ ] Laravel App Dockerfile (PHP 8.3 + extensions)
- [ ] WhatsApp Service Dockerfile (Node 20 + Chromium)
- [ ] Docker Compose configuration
- [ ] Nginx reverse proxy
- [ ] GitHub Actions CI/CD

## Testing
- [ ] `docker compose up -d` works locally
- [ ] All services start successfully
- [ ] WhatsApp QR code generation works
- [ ] Laravel routes accessible
- [ ] Reverb WebSocket connected

## Checklist
- [ ] Code follows project conventions
- [ ] Documentation updated
- [ ] No secrets committed
```

### Step 6: Staging Deployment & Testing (Day 5-6)

```bash
# On staging server
git checkout staging
git pull origin staging

# Deploy Docker containers
docker compose -f compose.yaml -f compose.prod.yaml up -d

# Verify all services
docker compose ps
docker compose logs -f

# Run smoke tests
curl http://staging.example.com/health
curl http://staging.example.com:3001/health
```

### Step 7: PR to Main (Day 7)

After staging tests pass:

```bash
# Create PR: staging → main
# Title: release: Docker containerization v1.0
```

**Release Checklist:**
- [ ] All staging tests passed
- [ ] Performance benchmarks acceptable
- [ ] No regressions in functionality
- [ ] Documentation complete
- [ ] Team sign-off obtained

---

## 📊 Branch Protection Rules

### For `main` branch:

```yaml
# GitHub Branch Protection Settings
require_pull_request_reviews:
  required_approving_review_count: 1
  dismiss_stale_reviews: true
require_status_checks_to_pass: true
require_branches_to_be_up_to_date: true
enforce_admins: false
```

### For `staging` branch:

```yaml
require_pull_request_reviews:
  required_approving_review_count: 1
require_status_checks_to_pass: true
```

---

## 🚀 CI/CD Integration

### GitHub Actions: Build & Test

```yaml
# .github/workflows/docker-build.yml
name: Docker Build & Test

on:
  push:
    branches: [feature/docker-*, staging]
  pull_request:
    branches: [staging, main]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Build Docker images
        run: |
          docker compose build --no-cache
          
      - name: Start services
        run: |
          docker compose up -d
          sleep 30  # Wait for services
          
      - name: Health checks
        run: |
          curl -f http://localhost/health || exit 1
          curl -f http://localhost:3001/health || exit 1
          
      - name: Run tests
        run: |
          docker compose exec -T app php artisan test
```

### GitHub Actions: Deploy

```yaml
# .github/workflows/docker-deploy.yml
name: Docker Deploy

on:
  push:
    branches: [staging, main]

jobs:
  deploy-staging:
    if: github.ref == 'refs/heads/staging'
    runs-on: ubuntu-latest
    environment: staging
    steps:
      - name: Deploy to staging
        run: |
          # SSH and deploy to staging server
          
  deploy-production:
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment: production
    steps:
      - name: Deploy to production
        run: |
          # SSH and deploy to production server
```

---

## 📅 Timeline

| Day | Activity | Branch |
|-----|----------|--------|
| 1 | Setup branch, create structure | `feature/docker-setup` |
| 2-3 | Implement Dockerfiles | `feature/docker-setup` |
| 4 | Local testing, CI/CD setup | `feature/docker-setup` |
| 5 | PR to staging, code review | PR → `staging` |
| 6 | Staging deployment, testing | `staging` |
| 7 | PR to main, production deploy | PR → `main` |

---

## ✅ Advantages of This Approach

1. **Clean main branch** - Production code always stable
2. **Safe testing** - Staging isolates experimental code
3. **Easy rollback** - Can revert staging without affecting main
4. **Code review** - PRs ensure quality
5. **Audit trail** - Clear history of changes
6. **Team collaboration** - Others can review and contribute

---

## ⚠️ Alternative: Direct Staging (Not Recommended)

Some teams commit directly to staging, but this:
- ❌ Lacks code review
- ❌ No atomic changes
- ❌ Harder to rollback
- ❌ No PR history

**We recommend Feature Branch approach for Docker implementation.**

---

## 📚 References

- [Atlassian GitFlow Workflow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow)
- [A Successful Git Branching Model (nvie)](https://nvie.com/posts/a-successful-git-branching-model/)
- [GitHub Flow](https://docs.github.com/en/get-started/using-github/github-flow)

---

*Document created: 4 Desember 2025*
