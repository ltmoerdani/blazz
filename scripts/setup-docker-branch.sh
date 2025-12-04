#!/bin/bash

# ============================================================================
# Docker Implementation Setup Script
# ============================================================================
# This script sets up the branch structure for Docker implementation
# Following GitFlow best practices
#
# Usage: ./scripts/setup-docker-branch.sh
# ============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Branch names
FEATURE_BRANCH="feature/docker-setup"
BASE_BRANCH="staging"

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}   Docker Implementation Branch Setup${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""

# Check if we're in a git repository
if ! git rev-parse --is-inside-work-tree > /dev/null 2>&1; then
    echo -e "${RED}Error: Not in a git repository${NC}"
    exit 1
fi

# Get current branch
CURRENT_BRANCH=$(git branch --show-current)
echo -e "${YELLOW}Current branch: ${CURRENT_BRANCH}${NC}"

# Check for uncommitted changes
if ! git diff-index --quiet HEAD --; then
    echo -e "${YELLOW}Warning: You have uncommitted changes${NC}"
    read -p "Do you want to stash them? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git stash push -m "Pre-docker-setup stash"
        echo -e "${GREEN}Changes stashed${NC}"
    else
        echo -e "${RED}Please commit or stash your changes first${NC}"
        exit 1
    fi
fi

# Check if feature branch already exists
if git show-ref --verify --quiet refs/heads/${FEATURE_BRANCH}; then
    echo -e "${YELLOW}Branch '${FEATURE_BRANCH}' already exists${NC}"
    read -p "Do you want to switch to it? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git checkout ${FEATURE_BRANCH}
        echo -e "${GREEN}Switched to ${FEATURE_BRANCH}${NC}"
    fi
else
    # Fetch latest from remote
    echo -e "${BLUE}Fetching latest from remote...${NC}"
    git fetch origin

    # Checkout and update base branch
    echo -e "${BLUE}Switching to ${BASE_BRANCH}...${NC}"
    git checkout ${BASE_BRANCH}
    git pull origin ${BASE_BRANCH}

    # Create feature branch
    echo -e "${BLUE}Creating feature branch: ${FEATURE_BRANCH}${NC}"
    git checkout -b ${FEATURE_BRANCH}

    # Push to remote
    echo -e "${BLUE}Pushing branch to remote...${NC}"
    git push -u origin ${FEATURE_BRANCH}

    echo -e "${GREEN}✅ Feature branch created successfully!${NC}"
fi

# Create Docker directory structure
echo ""
echo -e "${BLUE}Creating Docker directory structure...${NC}"

# Create directories
mkdir -p docker/app
mkdir -p docker/nginx/conf.d
mkdir -p docker/mysql/init
mkdir -p docker/node
mkdir -p .github/workflows

echo -e "${GREEN}✅ Directory structure created${NC}"

# Create .dockerignore if it doesn't exist
if [ ! -f .dockerignore ]; then
    echo -e "${BLUE}Creating .dockerignore...${NC}"
    cat > .dockerignore << 'EOF'
# Git
.git
.gitignore

# Dependencies (will be installed in container)
node_modules
vendor

# IDE
.idea
.vscode
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Environment files (will be mounted)
.env
.env.*
!.env.example

# Logs
*.log
logs/

# Storage (will be mounted)
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
storage/logs/*

# Tests
tests/
phpunit.xml

# Documentation
docs/

# Build artifacts
public/hot
public/build

# WhatsApp sessions (will be mounted)
whatsapp-service/sessions/
whatsapp-service/session-backups/
whatsapp-service/.wwebjs_auth/
whatsapp-service/.wwebjs_cache/
EOF
    echo -e "${GREEN}✅ .dockerignore created${NC}"
fi

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}   Setup Complete!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Create Docker configuration files:"
echo "   - docker/app/Dockerfile"
echo "   - docker/nginx/nginx.conf"
echo "   - whatsapp-service/Dockerfile"
echo "   - compose.yaml"
echo ""
echo "2. Test locally:"
echo "   docker compose up -d"
echo ""
echo "3. Commit changes:"
echo "   git add ."
echo "   git commit -m 'feat(docker): initial Docker setup'"
echo ""
echo "4. Create PR to staging:"
echo "   Open GitHub and create PR: ${FEATURE_BRANCH} → ${BASE_BRANCH}"
echo ""
echo -e "${BLUE}Branch: ${FEATURE_BRANCH}${NC}"
echo -e "${BLUE}Target: ${BASE_BRANCH} → main${NC}"
