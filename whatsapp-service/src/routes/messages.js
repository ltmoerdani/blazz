const express = require('express');
const manager = require('../services/WhatsAppManager');
const { hmacAuthMiddleware } = require('../middleware/auth');

const router = express.Router();

// Send message (protected by HMAC)
router.post('/send', hmacAuthMiddleware, async (req, res) => {
  const { session_id, workspace_id, phone_number, message } = req.body;
  if (!workspace_id || !phone_number || !message) {
    return res.status(400).json({ error: 'workspace_id, phone_number and message are required' });
  }
  try {
    const result = await manager.sendMessage(parseInt(workspace_id, 10), phone_number, message, req.body.options || {});
    return res.json(result);
  } catch (err) {
    return res.status(400).json({ success: false, error: err.message });
  }
});

module.exports = router;
