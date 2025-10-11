const logger = require('../utils/logger');
const { generateSignature, constantTimeEquals } = require('../utils/crypto');

// Capture raw body in express.json verify to support HMAC
function hmacAuthMiddleware(req, res, next) {
  const apiTokenHeader = req.header('X-API-Token');
  const signature = req.header('X-HMAC-Signature');
  const timestampHeader = req.header('X-Timestamp');
  const workspaceId = req.header('X-Workspace-ID');

  const expectedToken = process.env.API_TOKEN; // Optional token; if absent, skip token check
  const secret = process.env.HMAC_SECRET;

  // Allow GET status endpoint to pass with API token only (no body)
  const isGetStatus = req.method === 'GET' && /\/api\/sessions\/.*\/status$/.test(req.path);

  if (!secret) {
    logger.error('HMAC secret not configured');
    return res.status(500).json({ error: 'Server misconfigured' });
  }

  if (expectedToken && apiTokenHeader !== expectedToken) {
    return res.status(401).json({ error: 'Invalid API token' });
  }

  if (isGetStatus) {
    // GET status does not require HMAC signature because no body; proceed
    return next();
  }

  if (!signature || !timestampHeader || !workspaceId) {
    return res.status(401).json({ error: 'Missing authentication headers' });
  }

  const timestamp = parseInt(timestampHeader, 10);
  const now = Math.floor(Date.now() / 1000);
  if (Number.isNaN(timestamp) || Math.abs(now - timestamp) > 300) {
    return res.status(401).json({ error: 'Request timestamp expired' });
  }

  const bodyString = req.rawBody || JSON.stringify(req.body || {});
  const expected = generateSignature(bodyString, timestamp, secret);

  if (!constantTimeEquals(expected, signature)) {
    logger.warn('Invalid HMAC signature', { workspace_id: workspaceId });
    return res.status(403).json({ error: 'Invalid signature' });
  }

  // Attach workspace_id for downstream use
  req.workspaceId = parseInt(workspaceId, 10);
  return next();
}

// Simple token-only auth for GET status if desired
function tokenOnlyAuth(req, res, next) {
  const apiTokenHeader = req.header('X-API-Token');
  const expectedToken = process.env.API_TOKEN;
  if (expectedToken && apiTokenHeader !== expectedToken) {
    return res.status(401).json({ error: 'Invalid API token' });
  }
  return next();
}

module.exports = { hmacAuthMiddleware, tokenOnlyAuth };
