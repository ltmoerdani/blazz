const express = require('express');
const manager = require('../services/WhatsAppManager');
const { hmacAuthMiddleware, tokenOnlyAuth } = require('../middleware/auth');

const router = express.Router();

// Create session (protected by HMAC)
router.post('/create', hmacAuthMiddleware, async (req, res) => {
  const { workspace_id } = req.body;
  if (!workspace_id) return res.status(400).json({ error: 'workspace_id is required' });
  try {
    const result = await manager.createSession(parseInt(workspace_id, 10));
    return res.json(result);
  } catch (err) {
    return res.status(400).json({ success: false, error: err.message });
  }
});

// Disconnect session (protected)
router.post('/disconnect', hmacAuthMiddleware, async (req, res) => {
  const { workspace_id } = req.body;
  if (!workspace_id) return res.status(400).json({ error: 'workspace_id is required' });
  const result = await manager.destroySession(parseInt(workspace_id, 10));
  return res.json(result);
});

// Refresh QR: reinitialize session (protected)
router.post('/refresh-qr', hmacAuthMiddleware, async (req, res) => {
  const { workspace_id } = req.body;
  if (!workspace_id) return res.status(400).json({ error: 'workspace_id is required' });
  try {
    await manager.destroySession(parseInt(workspace_id, 10));
    const result = await manager.createSession(parseInt(workspace_id, 10));
    return res.json(result);
  } catch (err) {
    return res.status(400).json({ success: false, error: err.message });
  }
});

// Status by sessionId (token-only)
router.get('/:sessionId/status', tokenOnlyAuth, async (req, res) => {
  const { sessionId } = req.params;
  const status = manager.getSessionStatusById(sessionId);
  return res.json(status);
});

module.exports = router;
