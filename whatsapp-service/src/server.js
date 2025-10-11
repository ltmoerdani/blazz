require('dotenv').config({ path: require('path').join(__dirname, '../.env') });
const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const morgan = require('morgan');
const logger = require('./utils/logger');

const app = express();

// Capture raw body for HMAC: use verify option
app.use(express.json({
  limit: '1mb',
  verify: (req, res, buf) => {
    req.rawBody = buf.toString('utf8');
  }
}));

app.use(cors({ origin: process.env.SOCKETIO_CORS_ORIGIN || '*', credentials: false }));
app.use(helmet());
app.use(morgan('combined'));

// Routes
app.use('/health', require('./routes/health'));
app.use('/api/sessions', require('./routes/sessions'));
app.use('/api/messages', require('./routes/messages'));

const PORT = parseInt(process.env.PORT || '3000', 10);
const HOST = process.env.HOST || '0.0.0.0';

app.listen(PORT, HOST, () => {
  logger.info(`WhatsApp service listening on http://${HOST}:${PORT}`);
});
