/**
 * API Response Helper
 * 
 * @module utils/ApiResponse
 * @description Standardized API response format for consistent error/success handling
 * @since v2.0.0
 * @author Blazz Development Team
 * 
 * Provides consistent response structure across all API endpoints:
 * - success: boolean indicating operation success
 * - message: human-readable message
 * - data: response payload (success only)
 * - errors: error details (error only)
 * - timestamp: ISO 8601 timestamp
 */

class ApiResponse {
    /**
     * Create success response
     * 
     * @param {*} data - Response data payload
     * @param {string} message - Success message
     * @param {number} statusCode - HTTP status code (default: 200)
     * @returns {Object} Standardized success response
     * 
     * @example
     * return res.status(200).json(
     *     ApiResponse.success({ id: 123, name: 'John' }, 'User created successfully')
     * );
     * 
     * @example
     * // Returns:
     * {
     *     success: true,
     *     message: 'User created successfully',
     *     data: { id: 123, name: 'John' },
     *     timestamp: '2025-11-16T03:30:45.123Z'
     * }
     */
    static success(data = null, message = 'Success', statusCode = 200) {
        return {
            success: true,
            message,
            data,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Create error response
     * 
     * @param {string} message - Error message
     * @param {number} statusCode - HTTP status code (default: 500)
     * @param {Object|Array} errors - Detailed error information
     * @returns {Object} Standardized error response
     * 
     * @example
     * return res.status(400).json(
     *     ApiResponse.error('Validation failed', 400, { field: 'email', message: 'Invalid email' })
     * );
     * 
     * @example
     * // Returns:
     * {
     *     success: false,
     *     message: 'Validation failed',
     *     errors: { field: 'email', message: 'Invalid email' },
     *     timestamp: '2025-11-16T03:30:45.123Z'
     * }
     */
    static error(message, statusCode = 500, errors = null) {
        return {
            success: false,
            message,
            errors,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Create validation error response
     * 
     * @param {Object|Array} errors - Validation error details
     * @param {string} message - Error message (default: 'Validation failed')
     * @returns {Object} Standardized validation error response
     * 
     * @example
     * return res.status(422).json(
     *     ApiResponse.validationError({
     *         email: ['Email is required', 'Email must be valid'],
     *         password: ['Password must be at least 8 characters']
     *     })
     * );
     */
    static validationError(errors, message = 'Validation failed') {
        return {
            success: false,
            message,
            errors,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Create unauthorized error response
     * 
     * @param {string} message - Error message (default: 'Unauthorized')
     * @returns {Object} Standardized unauthorized response
     * 
     * @example
     * return res.status(401).json(ApiResponse.unauthorized('Invalid API key'));
     */
    static unauthorized(message = 'Unauthorized') {
        return {
            success: false,
            message,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Create forbidden error response
     * 
     * @param {string} message - Error message (default: 'Forbidden')
     * @returns {Object} Standardized forbidden response
     * 
     * @example
     * return res.status(403).json(ApiResponse.forbidden('Insufficient permissions'));
     */
    static forbidden(message = 'Forbidden') {
        return {
            success: false,
            message,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Create not found error response
     * 
     * @param {string} message - Error message (default: 'Not found')
     * @returns {Object} Standardized not found response
     * 
     * @example
     * return res.status(404).json(ApiResponse.notFound('Session not found'));
     */
    static notFound(message = 'Not found') {
        return {
            success: false,
            message,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Create paginated success response
     * 
     * @param {Array} data - Array of items
     * @param {Object} pagination - Pagination metadata
     * @param {string} message - Success message
     * @returns {Object} Standardized paginated response
     * 
     * @example
     * return res.status(200).json(
     *     ApiResponse.paginated(users, {
     *         page: 1,
     *         perPage: 10,
     *         total: 100,
     *         totalPages: 10
     *     })
     * );
     * 
     * @example
     * // Returns:
     * {
     *     success: true,
     *     message: 'Success',
     *     data: [...users],
     *     pagination: {
     *         page: 1,
     *         perPage: 10,
     *         total: 100,
     *         totalPages: 10
     *     },
     *     timestamp: '2025-11-16T03:30:45.123Z'
     * }
     */
    static paginated(data, pagination, message = 'Success') {
        return {
            success: true,
            message,
            data,
            pagination,
            timestamp: new Date().toISOString()
        };
    }
}

module.exports = ApiResponse;
