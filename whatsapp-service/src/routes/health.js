const express = require('express');
const os = require('os');
const manager = require('../services/WhatsAppManager');

const router = express.Router();

router.get('/', (req, res) => {
  const memory = process.memoryUsage();
  res.json({
    status: 'ok',
    uptime: process.uptime(),
    node: process.version,
    platform: os.platform(),
    sessions: manager.sessionsByWorkspace ? manager.sessionsByWorkspace.size : 0,
    memory: {
      rss: memory.rss,
      heapTotal: memory.heapTotal,
      heapUsed: memory.heapUsed
    }
  });
});

module.exports = router;
