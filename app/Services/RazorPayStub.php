<?php

/**
 * Stub class for RazorPay API to prevent ESLint errors when RazorPay SDK is not installed
 * This file provides the necessary namespace and class structure for type hinting
 */

namespace Razorpay\Api;

/**
 * Stub implementation of RazorPay API class
 * This is used only when the actual RazorPay SDK is not installed
 */
class Api
{
    /**
     * Stub constructor
     */
    public function __construct($publicKey = null, $secretKey = null)
    {
        // Stub implementation - does nothing
    }
    
    /**
     * Stub property access
     */
    public function __get($name)
    {
        // Return a stub object to prevent errors
        return new \stdClass();
    }
    
    /**
     * Stub method calls
     */
    public function __call($method, $arguments)
    {
        // Return a stub object to prevent errors
        return new \stdClass();
    }
}