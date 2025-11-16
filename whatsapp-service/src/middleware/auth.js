/**
 * Authentication Middleware
 * 
 * @module middleware/auth
 * @description Validates API key and optional HMAC signature for secure communication
 *              between Laravel backend and Node.js WhatsApp service
 * @since v2.0.0
 * @author Blazz Development Team
 * 
 * Features:
 * - API key validation from header or body (backward compatible)
 * - Optional HMAC signature validation for enhanced security
 * - Replay attack prevention (5-minute window)
 * - Timing-safe comparison for security
 * 
 * @example
 * // Use in routes
 * const { authenticate } = require('./middleware/auth');
 * router.post('/api/messages/send', authenticate, messageController.sendMessage);
 */

const ApiResponse = require('../utils/ApiResponse');

/**
 * Validate API Key
 * 
 * Checks X-API-Key header or api_key in body against configured API_KEY or LARAVEL_API_TOKEN
 * Supports both modern header-based and legacy body-based authentication
 *
 * @function validateApiKey
 * @param {express.Request} req - Express request object
 * @param {express.Response} res - Express response object
 * @param {express.NextFunction} next - Express next middleware function
 * @returns {void|Response} Returns 401 error if validation fails, otherwise calls next()
 * 
 * @throws {401} Missing API key - When no API key provided
 * @throws {401} Invalid API key - When API key doesn't match configured value
 * 
 * @example
 * // Valid request with header
 * curl -H "X-API-Key: your-api-key" http://localhost:3001/api/messages/send
 * 
 * @example
 * // Valid request with body (legacy)
 * curl -d '{"api_key": "your-api-key"}' http://localhost:3001/api/messages/send
 */
function validateApiKey(req, res, next) {
    // Extract API key from header (preferred) or body (legacy)
    const headerApiKey = req.headers['x-api-key'];
    const bodyApiKey = req.body?.api_key;
    const apiKey = headerApiKey || bodyApiKey;

    // Get configured API key from environment
    const validApiKey = process.env.API_KEY || process.env.LARAVEL_API_TOKEN || process.env.API_TOKEN;

    // Check if API key is provided
    if (!apiKey) {
        return res.status(401).json(
            ApiResponse.unauthorized('API key must be provided via X-API-Key header or api_key in body')
        );
    }

    // Validate API key
    if (apiKey !== validApiKey) {
        return res.status(401).json(
            ApiResponse.unauthorized('The provided API key is invalid')
        );
    }

    // API key is valid, proceed
    next();
}

/**
 * Validate HMAC Signature (Optional)
 * 
 * Validates X-Signature header using HMAC-SHA256 with X-Timestamp for enhanced security
 * Only validates if HMAC_SECRET is configured and headers are present
 * 
 * Security Features:
 * - Prevents request tampering
 * - Replay attack prevention (5-minute window)
 * - Timing-safe comparison to prevent timing attacks
 *
 * @function validateHmacSignature
 * @param {express.Request} req - Express request object
 * @param {express.Response} res - Express response object
 * @param {express.NextFunction} next - Express next middleware function
 * @returns {void|Response} Returns 401 error if validation fails, otherwise calls next()
 * 
 * @throws {401} Missing HMAC signature - When HMAC required but headers missing
 * @throws {401} Invalid timestamp - When timestamp outside 5-minute window
 * @throws {401} Invalid signature - When HMAC signature doesn't match
 * 
 * @example
 * // Calculate HMAC signature in Laravel
 * $timestamp = time();
 * $signature = hash_hmac('sha256', $timestamp, env('WHATSAPP_HMAC_SECRET'));
 * 
 * // Send with headers
 * Http::withHeaders([
 *     'X-API-Key' => env('WHATSAPP_API_KEY'),
 *     'X-Timestamp' => $timestamp,
 *     'X-Signature' => $signature
 * ])->post('http://localhost:3001/api/messages/send', [...]);
 */
function validateHmacSignature(req, res, next) {
    const hmacSecret = process.env.HMAC_SECRET;

    // Skip HMAC validation if not configured
    if (!hmacSecret) {
        return next();
    }

    const signature = req.headers['x-signature'];
    const timestamp = req.headers['x-timestamp'];

    // Check if HMAC headers are provided
    if (!signature || !timestamp) {
        return res.status(401).json(
            ApiResponse.unauthorized('X-Signature and X-Timestamp headers are required for HMAC validation')
        );
    }

    // Validate timestamp (prevent replay attacks - 5 minute window)
    const now = Math.floor(Date.now() / 1000);
    const requestTime = parseInt(timestamp);
    const timeDiff = Math.abs(now - requestTime);

    if (timeDiff > 300) { // 5 minutes
        return res.status(401).json(
            ApiResponse.unauthorized('Request timestamp is too old or in the future (max 5 minutes)')
        );
    }

    // Calculate expected signature
    const crypto = require('crypto');
    const algorithm = process.env.HMAC_ALGORITHM || 'sha256';
    const expectedSignature = crypto
        .createHmac(algorithm, hmacSecret)
        .update(timestamp.toString())
        .digest('hex');

    // Compare signatures (timing-safe comparison)
    if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expectedSignature))) {
        return res.status(401).json(
            ApiResponse.unauthorized('HMAC signature verification failed')
        );
    }

    // HMAC signature is valid, proceed
    next();
}

/**
 * Combined Authentication Middleware (API Key + Optional HMAC)
 * 
 * Main authentication middleware that validates both API key and optional HMAC signature
 * This is the recommended middleware to use for all protected routes
 * 
 * Flow:
 * 1. Validate API key (required)
 * 2. Validate HMAC signature (optional, only if HMAC_ENABLED=true and headers present)
 * 
 * @function authenticate
 * @param {express.Request} req - Express request object
 * @param {express.Response} res - Express response object
 * @param {express.NextFunction} next - Express next middleware function
 * @returns {void} Calls next middleware if authentication succeeds
 * 
 * @example
 * // Apply to all routes in router
 * const { authenticate } = require('../middleware/auth');
 * 
 * router.post('/api/messages/send', authenticate, messageController.sendMessage);
 * router.post('/api/sessions', authenticate, sessionController.createSession);
 * 
 * @example
 * // Apply to route group
 * router.use('/api', authenticate);
 * router.post('/api/messages/send', messageController.sendMessage);
 */
function authenticate(req, res, next) {
    // First validate API key
    validateApiKey(req, res, (err) => {
        if (err) return next(err);

        // Check if HMAC validation is explicitly enabled via environment variable
        const hmacEnabled = process.env.HMAC_ENABLED === 'true' || process.env.HMAC_ENABLED === '1';

        // If HMAC is enabled AND headers are present, validate signature
        if (hmacEnabled && req.headers['x-signature'] && req.headers['x-timestamp']) {
            return validateHmacSignature(req, res, next);
        }

        // No HMAC required or not provided, proceed with API key only
        next();
    });
}

module.exports = {
    validateApiKey,
    validateHmacSignature,
    authenticate
};
