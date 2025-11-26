/**
 * Session Cleanup Routes
 * 
 * Admin routes for manual session cleanup operations.
 * 
 * Endpoints:
 * - POST /cleanup/run          Run full cleanup cycle
 * - POST /cleanup/:sessionId   Cleanup specific session
 * - GET  /cleanup/stats        Get cleanup statistics
 * 
 * @author Blazz Platform
 * @date November 20, 2025
 * @follows docs/architecture/07-development-patterns-guidelines.md
 */

const express = require('express');
const router = express.Router();

/**
 * Run full cleanup cycle
 * 
 * POST /cleanup/run
 * 
 * Response:
 * {
 *   "success": true,
 *   "results": {
 *     "checked": 15,
 *     "cleaned": 3,
 *     "failed": 0,
 *     "skipped": 12
 *   }
 * }
 */
router.post('/run', async (req, res) => {
    try {
        const cleanupService = req.app.get('sessionCleanupService');
        
        if (!cleanupService) {
            return res.status(503).json({
                success: false,
                error: 'Cleanup service not initialized'
            });
        }

        const results = await cleanupService.runCleanup();

        res.json({
            success: true,
            message: 'Cleanup cycle completed',
            results,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

/**
 * Cleanup specific session
 * 
 * POST /cleanup/:sessionId
 * 
 * Body:
 * {
 *   "reason": "Manual cleanup by admin"
 * }
 */
router.post('/:sessionId', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const { reason = 'Manual cleanup triggered via API' } = req.body;

        const cleanupService = req.app.get('sessionCleanupService');
        
        if (!cleanupService) {
            return res.status(503).json({
                success: false,
                error: 'Cleanup service not initialized'
            });
        }

        const success = await cleanupService.manualCleanup(sessionId, reason);

        if (success) {
            res.json({
                success: true,
                message: `Session ${sessionId} cleaned up successfully`,
                sessionId,
                reason,
                timestamp: new Date().toISOString()
            });
        } else {
            res.status(500).json({
                success: false,
                error: 'Cleanup failed',
                sessionId,
                timestamp: new Date().toISOString()
            });
        }

    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

/**
 * Get cleanup statistics
 * 
 * GET /cleanup/stats
 * 
 * Response:
 * {
 *   "success": true,
 *   "stats": {
 *     "totalCleanups": 42,
 *     "lastCleanup": "2025-11-20T...",
 *     "staleCount": 5
 *   }
 * }
 */
router.get('/stats', async (req, res) => {
    try {
        const cleanupService = req.app.get('sessionCleanupService');
        
        if (!cleanupService) {
            return res.status(503).json({
                success: false,
                error: 'Cleanup service not initialized'
            });
        }

        const stats = await cleanupService.getStats();

        res.json({
            success: true,
            stats,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

module.exports = router;
