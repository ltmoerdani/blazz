module.exports = {
  apps: [{
    name: 'whatsapp-service',
    script: 'server.js',
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '2G',
    env: {
      NODE_ENV: 'development',
      PORT: 3000,
      LOG_LEVEL: 'info'
    },
    env_production: {
      NODE_ENV: 'production',
      PORT: 3000,
      LOG_LEVEL: 'warn'
    },
    // Logging
    log_file: './logs/combined.log',
    out_file: './logs/out.log',
    error_file: './logs/error.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z',

    // Restart policy
    min_uptime: '10s',
    max_restarts: 10,

    // Graceful shutdown
    kill_timeout: 5000,
    wait_ready: true,
    listen_timeout: 10000,

    // Health check
    health_check: {
      enabled: true,
      url: 'http://localhost:3000/health',
      interval: '30s',
      timeout: '5000ms'
    }
  }]
};
