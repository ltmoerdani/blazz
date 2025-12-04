# ============================================================================
# WhatsApp Service Dockerfile
# ============================================================================
# Base: Node.js 20 on Debian Bookworm (NOT Alpine - Puppeteer compatibility)
# Features: Chromium for Puppeteer, whatsapp-web.js, Redis integration
# ============================================================================

FROM node:20-bookworm-slim

LABEL maintainer="Blazz Team"
LABEL description="WhatsApp Web.js Service with Puppeteer"

# Environment variables
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Jakarta
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium
ENV NODE_ENV=production

# Install Chromium and dependencies
# Using Debian packages for better stability
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Chromium browser
    chromium \
    # Chromium dependencies
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libasound2 \
    libpango-1.0-0 \
    libcairo2 \
    # Fonts for proper rendering
    fonts-liberation \
    fonts-noto-color-emoji \
    fonts-noto-cjk \
    # Utilities
    curl \
    wget \
    procps \
    dumb-init \
    # Clean up
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /tmp/* \
    && rm -rf /var/tmp/*

# Create non-root user for security
RUN groupadd -r pptruser && useradd -r -g pptruser -G audio,video pptruser \
    && mkdir -p /home/pptruser/Downloads \
    && chown -R pptruser:pptruser /home/pptruser

# Set working directory
WORKDIR /app

# Copy package files first (for better caching)
COPY package*.json ./

# Install dependencies
RUN npm ci --omit=dev \
    && npm cache clean --force

# Copy application code
COPY --chown=pptruser:pptruser . .

# Create directories for sessions and logs
RUN mkdir -p /app/sessions \
    && mkdir -p /app/logs \
    && mkdir -p /app/.wwebjs_auth \
    && mkdir -p /app/.wwebjs_cache \
    && chown -R pptruser:pptruser /app

# Switch to non-root user
USER pptruser

# Expose WhatsApp service port
EXPOSE 3001

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:3001/health || exit 1

# Use dumb-init to properly handle signals (important for Chromium)
ENTRYPOINT ["dumb-init", "--"]

# Start the WhatsApp service
CMD ["node", "src/index.js"]
