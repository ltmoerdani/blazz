module.exports = {
  apps: [
    {
      name: 'whatsapp-service',
      script: 'src/server.js',
      instances: 1,
      exec_mode: 'fork',
      env: {
        NODE_ENV: 'production'
      },
      watch: false,
      max_memory_restart: process.env.SESSION_MEMORY_LIMIT || '150M',
      error_file: './logs/error.log',
      out_file: './logs/out.log',
      merge_logs: true,
      time: true
    }
  ]
};
