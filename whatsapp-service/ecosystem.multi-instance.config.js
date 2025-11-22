module.exports = {
    apps: [
        {
            name: 'whatsapp-instance-1',
            script: 'server.js',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '1G',
            env: {
                NODE_ENV: 'development',
                PORT: 3001,
                INSTANCE_ID: 'whatsapp-instance-1',
                INSTANCE_INDEX: 0,
                SESSION_STORAGE_PATH: '/Applications/MAMP/htdocs/blazz/whatsapp-service/sessions-shared',
                LARAVEL_API_URL: 'http://127.0.0.1:8000',
                MAX_SESSIONS_PER_INSTANCE: 500,
                REDIS_URL: 'redis://127.0.0.1:6379',
                LOG_LEVEL: 'debug'
            },
            log_file: './logs/instance-1.log',
            out_file: './logs/instance-1-out.log',
            error_file: './logs/instance-1-error.log',
            log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
            merge_logs: false,
            max_restarts: 10,
            min_uptime: '10s'
        },
        {
            name: 'whatsapp-instance-2',
            script: 'server.js',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '1G',
            env: {
                NODE_ENV: 'development',
                PORT: 3002,
                INSTANCE_ID: 'whatsapp-instance-2',
                INSTANCE_INDEX: 1,
                SESSION_STORAGE_PATH: '/Applications/MAMP/htdocs/blazz/whatsapp-service/sessions-shared',
                LARAVEL_API_URL: 'http://127.0.0.1:8000',
                MAX_SESSIONS_PER_INSTANCE: 500,
                REDIS_URL: 'redis://127.0.0.1:6379',
                LOG_LEVEL: 'debug'
            },
            log_file: './logs/instance-2.log',
            out_file: './logs/instance-2-out.log',
            error_file: './logs/instance-2-error.log',
            log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
            merge_logs: false,
            max_restarts: 10,
            min_uptime: '10s'
        },
        {
            name: 'whatsapp-instance-3',
            script: 'server.js',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '1G',
            env: {
                NODE_ENV: 'development',
                PORT: 3003,
                INSTANCE_ID: 'whatsapp-instance-3',
                INSTANCE_INDEX: 2,
                SESSION_STORAGE_PATH: '/Applications/MAMP/htdocs/blazz/whatsapp-service/sessions-shared',
                LARAVEL_API_URL: 'http://127.0.0.1:8000',
                MAX_SESSIONS_PER_INSTANCE: 500,
                REDIS_URL: 'redis://127.0.0.1:6379',
                LOG_LEVEL: 'debug'
            },
            log_file: './logs/instance-3.log',
            out_file: './logs/instance-3-out.log',
            error_file: './logs/instance-3-error.log',
            log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
            merge_logs: false,
            max_restarts: 10,
            min_uptime: '10s'
        },
        {
            name: 'whatsapp-instance-4',
            script: 'server.js',
            instances: 1,
            autorestart: true,
            watch: false,
            max_memory_restart: '1G',
            env: {
                NODE_ENV: 'development',
                PORT: 3004,
                INSTANCE_ID: 'whatsapp-instance-4',
                INSTANCE_INDEX: 3,
                SESSION_STORAGE_PATH: '/Applications/MAMP/htdocs/blazz/whatsapp-service/sessions-shared',
                LARAVEL_API_URL: 'http://127.0.0.1:8000',
                MAX_SESSIONS_PER_INSTANCE: 500,
                REDIS_URL: 'redis://127.0.0.1:6379',
                LOG_LEVEL: 'debug'
            },
            log_file: './logs/instance-4.log',
            out_file: './logs/instance-4-out.log',
            error_file: './logs/instance-4-error.log',
            log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
            merge_logs: false,
            max_restarts: 10,
            min_uptime: '10s'
        }
    ]
};
