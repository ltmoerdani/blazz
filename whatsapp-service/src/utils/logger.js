const { createLogger, format, transports } = require('winston');
const path = require('path');

const logLevel = process.env.LOG_LEVEL || 'info';
const logFile = process.env.LOG_FILE || path.join(__dirname, '../../logs/whatsapp-service.log');

const logger = createLogger({
  level: logLevel,
  format: format.combine(
    format.timestamp(),
    format.errors({ stack: true }),
    format.printf(({ timestamp, level, message, ...meta }) => {
      const rest = Object.keys(meta).length ? ` ${JSON.stringify(meta)}` : '';
      return `${timestamp} [${level.toUpperCase()}] ${message}${rest}`;
    })
  ),
  transports: [
    new transports.Console({
      format: format.combine(format.colorize(), format.simple())
    }),
    new transports.File({ filename: logFile })
  ]
});

module.exports = logger;
