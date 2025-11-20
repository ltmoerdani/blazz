/**
 * PM2 Ecosystem Configuration
 *
 * Production-ready configuration for WhatsApp Node.js Service
 * Supports clustering for scalability and high availability
 *
 * TASK-ARCH-4: Setup PM2 configuration for production deployment
 */

module.exports = {
  apps: [
    {
      name: 'whatsapp-service',
      script: 'server.js',
      instances: 'max', // Use all available CPU cores for clustering
      exec_mode: 'cluster', // Enable cluster mode for load balancing

      // Environment configuration
      env: {
        NODE_ENV: 'development',
        PORT: 3001,
        LOG_LEVEL: 'info'
      },

      // Production environment
      env_production: {
        NODE_ENV: 'production',
        PORT: 3001,
        LOG_LEVEL: 'warn',

        // API Security
        API_KEY: process.env.API_KEY,
        HMAC_SECRET: process.env.HMAC_SECRET,

        // Laravel Integration
        LARAVEL_URL: process.env.LARAVEL_URL,
        LARAVEL_API_TOKEN: process.env.LARAVEL_API_TOKEN,

        // WhatsApp Configuration
        WHATSAPP_SYNC_BATCH_SIZE: process.env.WHATSAPP_SYNC_BATCH_SIZE || '50',
        WHATSAPP_SYNC_MAX_CONCURRENT: process.env.WHATSAPP_SYNC_MAX_CONCURRENT || '3',
        WHATSAPP_SYNC_WINDOW_DAYS: process.env.WHATSAPP_SYNC_WINDOW_DAYS || '30',
        WHATSAPP_SYNC_MAX_CHATS: process.env.WHATSAPP_SYNC_MAX_CHATS || '500',
        WHATSAPP_SYNC_RETRY_ATTEMPTS: process.env.WHATSAPP_SYNC_RETRY_ATTEMPTS || '3',
        WHATSAPP_SYNC_RETRY_DELAY_MS: process.env.WHATSAPP_SYNC_RETRY_DELAY_MS || '1000',

        // Logging Configuration
        LOG_FILE: './logs/whatsapp-service.log',
        LOG_MAX_SIZE: process.env.LOG_MAX_SIZE || '10485760',
        LOG_MAX_FILES: process.env.LOG_MAX_FILES || '7'
      },

      // Staging environment
      env_staging: {
        NODE_ENV: 'staging',
        PORT: 3001,
        LOG_LEVEL: 'debug',

        // API Security
        API_KEY: process.env.STAGING_API_KEY,
        HMAC_SECRET: process.env.STAGING_HMAC_SECRET,

        // Laravel Integration
        LARAVEL_URL: process.env.STAGING_LARAVEL_URL,
        LARAVEL_API_TOKEN: process.env.STAGING_LARAVEL_API_TOKEN,

        // WhatsApp Configuration (conservative for testing)
        WHATSAPP_SYNC_BATCH_SIZE: process.env.WHATSAPP_SYNC_BATCH_SIZE || '25',
        WHATSAPP_SYNC_MAX_CONCURRENT: process.env.WHATSAPP_SYNC_MAX_CONCURRENT || '2',
        WHATSAPP_SYNC_WINDOW_DAYS: process.env.WHATSAPP_SYNC_WINDOW_DAYS || '7',
        WHATSAPP_SYNC_MAX_CHATS: process.env.WHATSAPP_SYNC_MAX_CHATS || '100'
      },

      // Application Configuration
      watch: false, // Disable watch in production
      ignore_watch: [
        'node_modules',
        'logs',
        'sessions',
        '*.tmp',
        '.git'
      ],

      // Memory and Performance
      max_memory_restart: '500M', // Restart if memory exceeds 500MB per instance
      min_uptime: '10s', // Minimum uptime before considering app as started

      // Restart Configuration
      restart_delay: 4000, // Delay between restarts
      max_restarts: 10, // Maximum restart attempts per timeframe
      autorestart: true, // Auto-restart on failure

      // Logging Configuration
      log_file: './logs/pm2-combined.log',
      out_file: './logs/pm2-out.log',
      error_file: './logs/pm2-error.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',

      // Process Management
      kill_timeout: 5000, // Time to wait before force killing
      wait_ready: true, // Wait for ready signal
      listen_timeout: 10000, // Time to wait for server to start

      // Graceful Shutdown and Reload
      graceful_reload: true,
      graceful_shutdown: true,

      // Environment-specific settings
      node_args: process.env.NODE_OPTIONS || '--max-old-space-size=1024',

      // Timezone
      timezone: 'Asia/Jakarta',

      // Monitoring
      pmx: true, // Enable PM2 monitoring
      vizion: false // Disable version control monitoring for performance

      // Note: Health check is now handled by the HealthController endpoints
    }
  ],

  // Deployment Configuration (for automated deployments)
  deploy: {
    production: {
      user: 'node',
      host: ['your-production-server-ip'], // Update with actual server IP
      ref: 'origin/main',
      repo: 'git@github.com:your-username/whatsapp-service.git', // Update with actual repo
      path: '/var/www/whatsapp-service',
      'pre-deploy-local': '',
      'post-deploy': 'npm install && pm2 reload ecosystem.config.js --env production',
      'pre-setup': '',
      'ssh_options': 'StrictHostKeyChecking=no'
    },

    staging: {
      user: 'node',
      host: ['your-staging-server-ip'], // Update with actual server IP
      ref: 'origin/staging-chats-fix-arch',
      repo: 'git@github.com:your-username/whatsapp-service.git', // Update with actual repo
      path: '/var/www/whatsapp-service-staging',
      'post-deploy': 'npm install && pm2 reload ecosystem.config.js --env staging'
    }
  }
};
