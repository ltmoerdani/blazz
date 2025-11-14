# Real-Time Chat Performance Optimization

**Purpose:** Technical optimization guide for WhatsApp Web-like performance
**Target:** 6x speed improvement (3s → <500ms)
**Focus:** Frontend rendering, backend processing, database queries

---

## ⚡ PERFORMANCE TRANSFORMATION GOALS

### **Speed Targets**
- **Message Send UI Response**: <100ms (currently 1-3 seconds)
- **Message Status Updates**: <500ms real-time
- **Conversation Loading**: <1s for 1000 messages
- **Scroll Performance**: 60fps smooth scrolling
- **Memory Usage**: <50MB per chat session
- **Network Efficiency**: 80% payload reduction

### **Performance Bottlenecks to Fix**
```
❌ Database-bound UI: User waits for database commits
❌ Synchronous processing: Blocking operations
❌ Excessive re-renders: Inefficient Vue.js updates
❌ Large DOM trees: Rendering performance issues
❌ Network overhead: Inefficient API calls
❌ Memory leaks: Poor resource management
```

---

## 🏎️ FRONTEND PERFORMANCE OPTIMIZATION

### **1. Instant UI Response (<100ms)**

#### **Optimistic Rendering Strategy**
```javascript
// Immediate UI update - no backend waiting
const instantMessageSend = (messageContent) => {
    const startTime = performance.now();

    // 1. Create optimistic message (5ms)
    const optimisticMessage = {
        id: `temp_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
        content: messageContent,
        type: 'outbound',
        status: 'sending',
        timestamp: new Date(),
        is_temp: true
    };

    // 2. Add to state immediately (1ms)
    messages.value.push(optimisticMessage);

    // 3. Force DOM update synchronously (10ms)
    nextTick(() => {
        // 4. Scroll to bottom instantly (5ms)
        scrollToBottomImmediate();

        // 5. Background processing starts (79ms remaining)
        processMessageInBackground(optimisticMessage);
    });

    // Total UI time: ~21ms (well under 100ms target)
    return optimisticMessage;
};

// Background processing (non-blocking)
const processMessageInBackground = async (optimisticMessage) => {
    try {
        // Send to backend without blocking UI
        const response = await axios.post('/api/messages/instant', {
            message: optimisticMessage.content,
            temp_id: optimisticMessage.id
        });

        // Update message with real data
        updateOptimisticMessage(optimisticMessage.id, {
            id: response.data.id,
            status: 'sent',
            whatsapp_id: response.data.whatsapp_id,
            is_temp: false
        });

    } catch (error) {
        // Handle error without blocking UI
        updateOptimisticMessage(optimisticMessage.id, {
            status: 'failed',
            error: error.message,
            retryable: true
        });
    }
};
```

#### **Virtual DOM Optimization**
```javascript
// Efficient Vue.js updates with minimal re-renders
const optimizedMessageList = {
    // Use shallowRef for large arrays to avoid deep reactivity
    messages: shallowRef([]),

    // Use computed for derived state
    visibleMessages: computed(() => {
        return this.messages.value.slice(this.visibleRange.start, this.visibleRange.end);
    }),

    // Batch updates to prevent layout thrashing
    batchUpdate(updates) {
        requestAnimationFrame(() => {
            // Batch all DOM updates in single frame
            updates.forEach(update => {
                const index = this.messages.value.findIndex(m => m.id === update.id);
                if (index !== -1) {
                    // Use spread for immutable update
                    this.messages.value = [
                        ...this.messages.value.slice(0, index),
                        { ...this.messages.value[index], ...update },
                        ...this.messages.value.slice(index + 1)
                    ];
                }
            });
        });
    }
};
```

### **2. Smooth Scrolling Performance (60fps)**

#### **Hardware-Accelerated Scrolling**
```css
.message-thread {
    /* Hardware acceleration */
    transform: translateZ(0);
    -webkit-transform: translateZ(0);

    /* Smooth scrolling */
    scroll-behavior: smooth;
    overflow-anchor: none;

    /* Optimize for frequent updates */
    contain: layout style paint;
}

.message-bubble {
    /* Prevent layout shifts */
    contain: layout;

    /* Hardware acceleration for animations */
    will-change: transform, opacity;

    /* Optimize rendering */
    backface-visibility: hidden;
    transform: translateZ(0);
}

/* Custom scrollbar for performance */
.message-thread::-webkit-scrollbar {
    width: 6px;
}

.message-thread::-webkit-scrollbar-track {
    background: transparent;
}

.message-thread::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
    /* Optimize thumb rendering */
    will-change: background-color;
}
```

#### **Efficient Scroll Implementation**
```javascript
// High-performance scroll management
const scrollManager = {
    container: null,
    scrollTimer: null,
    autoScroll: true,
    lastScrollTop: 0,

    init(container) {
        this.container = container;

        // Use passive event listeners for better performance
        this.container.addEventListener('scroll', this.handleScroll.bind(this), {
            passive: true
        });

        // Monitor scroll performance
        this.monitorPerformance();
    },

    handleScroll() {
        const currentScrollTop = this.container.scrollTop;
        const isScrollingUp = currentScrollTop < this.lastScrollTop;
        this.lastScrollTop = currentScrollTop;

        // Disable auto-scroll when user scrolls up
        if (isScrollingUp) {
            this.autoScroll = false;
        }

        // Re-enable auto-scroll when at bottom
        const isAtBottom = this.container.scrollTop + this.container.clientHeight >=
                           this.container.scrollHeight - 50;

        if (isAtBottom) {
            this.autoScroll = true;
        }

        // Debounce performance monitoring
        this.schedulePerformanceCheck();
    },

    scrollToBottom(smooth = true) {
        if (!this.autoScroll) return;

        if (smooth) {
            // Use requestAnimationFrame for smooth scrolling
            requestAnimationFrame(() => {
                this.container.scrollTo({
                    top: this.container.scrollHeight,
                    behavior: 'smooth'
                });
            });
        } else {
            // Instant scroll for new messages
            this.container.scrollTop = this.container.scrollHeight;
        }
    },

    monitorPerformance() {
        let frameCount = 0;
        let lastTime = performance.now();

        const measureFrame = () => {
            frameCount++;
            const currentTime = performance.now();

            if (currentTime - lastTime >= 1000) {
                const fps = frameCount;
                frameCount = 0;
                lastTime = currentTime;

                // Performance warning
                if (fps < 30) {
                    console.warn(`Scroll performance warning: ${fps}fps`);
                    this.optimizePerformance();
                }
            }

            requestAnimationFrame(measureFrame);
        };

        requestAnimationFrame(measureFrame);
    },

    optimizePerformance() {
        // Reduce DOM updates during performance issues
        this.debounceUpdates = true;

        // Simplify animations
        document.body.classList.add('reduced-motion');

        // Clear pending animations
        if (this.scrollTimer) {
            clearTimeout(this.scrollTimer);
        }
    }
};
```

### **3. Memory Management**

#### **Message Limiting Strategy**
```javascript
// Keep only recent messages in DOM for performance
const memoryManager = {
    DOM_MESSAGE_LIMIT: 100,
    CACHE_MESSAGE_LIMIT: 1000,

    pruneMessages(messages) {
        if (messages.length <= this.DOM_MESSAGE_LIMIT) {
            return messages;
        }

        // Keep most recent messages in DOM
        return messages.slice(-this.DOM_MESSAGE_LIMIT);
    },

    // Virtual scrolling for very large conversations
    createVirtualMessageList(messages) {
        return {
            allMessages: messages,
            visibleStart: 0,
            visibleEnd: 50,
            itemHeight: 80, // Estimated height
            containerHeight: 600,

            get visibleMessages() {
                return this.allMessages.slice(this.visibleStart, this.visibleEnd);
            },

            updateVisibleRange(scrollTop) {
                const start = Math.floor(scrollTop / this.itemHeight);
                const visibleCount = Math.ceil(this.containerHeight / this.itemHeight);

                this.visibleStart = Math.max(0, start - 5); // 5 item buffer
                this.visibleEnd = Math.min(
                    this.allMessages.length,
                    start + visibleCount + 5
                );
            }
        };
    },

    // Cleanup unused resources
    cleanup() {
        // Clear message cache for inactive conversations
        const inactiveChats = this.getInactiveChats();
        inactiveChats.forEach(chatId => {
            this.clearMessageCache(chatId);
        });

        // Force garbage collection
        if (window.gc) {
            window.gc();
        }
    }
};
```

#### **Image Optimization**
```javascript
// Lazy loading and optimization for images
const imageOptimizer = {
    // Intersection Observer for lazy loading
    setupImageObserver() {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    this.loadImage(img);
                    imageObserver.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px' // Load 50px before visible
        });

        // Observe all lazy images
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    },

    loadImage(img) {
        const src = img.dataset.src;
        const lowQualitySrc = img.dataset.lowQualitySrc;

        // Load low-quality first, then high quality
        if (lowQualitySrc) {
            img.src = lowQualitySrc;
            img.classList.add('loaded');

            // Load high quality after low quality loads
            const highQualityImg = new Image();
            highQualityImg.onload = () => {
                img.src = src;
                img.classList.add('high-quality-loaded');
            };
            highQualityImg.src = src;
        } else {
            img.src = src;
        }

        img.classList.remove('lazy');
    },

    // Compress images before sending
    async compressImage(file) {
        const maxSize = 1200;
        const quality = 0.8;

        return new Promise((resolve) => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();

            img.onload = () => {
                // Calculate new dimensions
                let { width, height } = img;
                if (width > height) {
                    if (width > maxSize) {
                        height *= maxSize / width;
                        width = maxSize;
                    }
                } else {
                    if (height > maxSize) {
                        width *= maxSize / height;
                        height = maxSize;
                    }
                }

                // Resize and compress
                canvas.width = width;
                canvas.height = height;
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(resolve, 'image/jpeg', quality);
            };

            img.src = URL.createObjectURL(file);
        });
    }
};
```

---

## 🚀 BACKEND PERFORMANCE OPTIMIZATION

### **1. Database Query Optimization**

#### **Critical Indexes for Speed**
```sql
-- Primary indexes for message performance
CREATE INDEX idx_chats_contact_timestamp_desc ON chats(contact_id, created_at DESC);
CREATE INDEX idx_chats_status_created_at ON chats(status, created_at DESC);
CREATE INDEX idx_chats_whatsapp_message_id ON chats(whatsapp_message_id);
CREATE INDEX idx_chats_contact_type_created ON chats(contact_id, type, created_at DESC);

-- Composite index for conversation loading
CREATE INDEX idx_chats_conversation_load ON chats(contact_id, created_at DESC, id);

-- Index for message status updates
CREATE INDEX idx_chats_status_update ON chats(whatsapp_message_id, status, updated_at);

-- Covering index for recent messages
CREATE INDEX idx_chats_recent_covering ON chats(contact_id, created_at DESC, message, type, status);

-- Partitioning for very large chat tables
ALTER TABLE chats PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

#### **Efficient Query Strategies**
```php
<?php
// Optimized chat loading with proper indexing
class ChatRepository
{
    public function getRecentMessages(int $contactId, int $limit = 50): Collection
    {
        // Use index-friendly query with LIMIT instead of WHERE id > X
        return Chat::where('contact_id', $contactId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['id', 'message', 'type', 'status', 'created_at', 'whatsapp_message_id'])
            ->reverse(); // Reverse for correct order
    }

    public function getMessageByWhatsAppId(string $whatsappId): ?Chat
    {
        // Use specific index for WhatsApp ID lookups
        return Chat::where('whatsapp_message_id', $whatsappId)
            ->first(['id', 'status', 'contact_id']);
    }

    public function updateMessageStatus(string $whatsappId, string $status): bool
    {
        // Direct database update for speed (no model events)
        return DB::table('chats')
            ->where('whatsapp_message_id', $whatsappId)
            ->update([
                'status' => $status,
                'updated_at' => now()
            ]) > 0;
    }

    public function getConversationStats(int $contactId): array
    {
        // Single query for multiple aggregations
        $stats = Chat::where('contact_id', $contactId)
            ->selectRaw('
                COUNT(*) as total_messages,
                COUNT(CASE WHEN type = "inbound" THEN 1 END) as inbound_messages,
                COUNT(CASE WHEN type = "outbound" THEN 1 END) as outbound_messages,
                MAX(created_at) as last_message_at,
                MIN(created_at) as first_message_at
            ')
            ->first();

        return $stats->toArray();
    }
}
```

### **2. Caching Strategy**

#### **Multi-Level Caching**
```php
<?php
class ChatCacheService
{
    private $redis;
    private $localCache = [];

    public function __construct()
    {
        $this->redis = Redis::connection('chat');
    }

    // Level 1: In-memory cache (fastest)
    public function getRecentMessagesLocal(int $contactId): ?array
    {
        $key = "recent_messages_{$contactId}";
        return $this->localCache[$key] ?? null;
    }

    // Level 2: Redis cache (fast)
    public function getRecentMessagesRedis(int $contactId, int $limit = 50): ?array
    {
        $key = "chat_messages_{$contactId}_{$limit}";

        $cached = $this->redis->get($key);
        if ($cached) {
            $data = json_decode($cached, true);
            // Also cache locally
            $this->localCache["recent_messages_{$contactId}"] = $data;
            return $data;
        }

        return null;
    }

    // Level 3: Database (slowest, cached after)
    public function getRecentMessagesDatabase(int $contactId, int $limit = 50): array
    {
        $messages = ChatRepository::getRecentMessages($contactId, $limit)->toArray();

        // Cache in Redis for 5 minutes
        $this->redis->setex(
            "chat_messages_{$contactId}_{$limit}",
            300, // 5 minutes
            json_encode($messages)
        );

        // Cache locally
        $this->localCache["recent_messages_{$contactId}"] = $messages;

        return $messages;
    }

    // Invalidate cache when new message arrives
    public function invalidateContactCache(int $contactId): void
    {
        // Clear local cache
        unset($this->localCache["recent_messages_{$contactId}"]);

        // Clear Redis cache patterns
        $pattern = "chat_messages_{$contactId}_*";
        $keys = $this->redis->keys($pattern);
        if ($keys) {
            $this->redis->del($keys);
        }
    }
}
```

### **3. Queue Optimization**

#### **High-Performance Message Processing**
```php
<?php
class ProcessMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [1, 3, 5]; // Exponential backoff
    public $maxExceptions = 3;

    // High-priority queue for instant messaging
    public $queue = 'chat-instant';

    public function __construct(
        private array $messageData,
        private string $tempId
    ) {}

    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            // Step 1: Save to database (fast with proper indexes)
            $chat = $this->saveMessageToDatabase();

            // Step 2: Send via WhatsApp Web.js (non-blocking)
            $whatsappResult = $this->sendToWhatsApp($chat);

            // Step 3: Update with WhatsApp result
            $this->updateWithWhatsAppResult($chat, $whatsappResult);

            // Step 4: Broadcast status update
            $this->broadcastStatusUpdate($chat, 'sent');

            $processingTime = (microtime(true) - $startTime) * 1000;
            Log::info('Message processed', [
                'temp_id' => $this->tempId,
                'processing_time_ms' => round($processingTime, 2)
            ]);

        } catch (Exception $e) {
            $this->handleFailure($e);
        }
    }

    private function saveMessageToDatabase(): Chat
    {
        // Use direct database insert for speed (no model events)
        $id = DB::table('chats')->insertGetId([
            'contact_id' => $this->messageData['contact_id'],
            'message' => $this->messageData['message'],
            'type' => 'outbound',
            'status' => 'pending',
            'temp_id' => $this->tempId,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Load minimal model for further processing
        return Chat::find($id, ['id', 'contact_id', 'message', 'status', 'temp_id']);
    }

    private function sendToWhatsApp(Chat $chat): array
    {
        // Use existing WhatsApp service with timeout
        $timeout = config('whatsapp.send_timeout', 10);

        return Http::timeout($timeout)
            ->post(config('whatsapp.node_service_url') . '/send-message', [
                'contact_id' => $chat->contact_id,
                'message' => $chat->message,
                'message_id' => $chat->id
            ])
            ->throw()
            ->json();
    }

    private function broadcastStatusUpdate(Chat $chat, string $status): void
    {
        broadcast(new MessageStatusUpdated(
            $chat->id,
            $status,
            $chat->contact_id,
            $this->tempId
        ));
    }
}
```

---

## 🌐 NETWORK OPTIMIZATION

### **1. WebSocket Efficiency**

#### **Message Batching**
```javascript
// Batch WebSocket messages to reduce overhead
const websocketBatcher = {
    pending: [],
    timer: null,
    batchSize: 10,
    flushInterval: 50, // 50ms

    add(message) {
        this.pending.push({
            ...message,
            id: generateId(),
            timestamp: Date.now()
        });

        if (this.pending.length >= this.batchSize) {
            this.flush();
        } else {
            this.scheduleFlush();
        }
    },

    scheduleFlush() {
        if (this.timer) return;

        this.timer = setTimeout(() => {
            this.flush();
        }, this.flushInterval);
    },

    flush() {
        if (this.pending.length === 0) return;

        const batch = {
            type: 'message_batch',
            messages: this.pending.splice(0),
            batch_id: generateId(),
            timestamp: Date.now()
        };

        // Send compressed batch
        this.sendCompressed(batch);

        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    },

    async sendCompressed(data) {
        const compressed = await this.compress(JSON.stringify(data));

        websocket.send(compressed);
    },

    async compress(data) {
        if ('CompressionStream' in window) {
            const stream = new CompressionStream('gzip');
            const writer = stream.writable.getWriter();
            const reader = stream.readable.getReader();

            writer.write(new TextEncoder().encode(data));
            writer.close();

            const result = await reader.read();
            return result.value;
        }

        return data; // Fallback for unsupported browsers
    }
};
```

#### **Connection Pool Management**
```javascript
// Efficient WebSocket connection management
class WebSocketManager {
    connections = new Map();
    maxConnections = 5;
    connectionTimeout = 30000; // 30 seconds

    getConnection(chatId) {
        if (this.connections.has(chatId)) {
            return this.connections.get(chatId);
        }

        // Clean up old connections
        this.cleanupOldConnections();

        // Create new connection if under limit
        if (this.connections.size < this.maxConnections) {
            const ws = this.createConnection(chatId);
            this.connections.set(chatId, ws);
            return ws;
        }

        // Reuse oldest connection
        const oldestChatId = this.connections.keys().next().value;
        return this.connections.get(oldestChatId);
    }

    createConnection(chatId) {
        const ws = new WebSocket(`${wsUrl}/chat/${chatId}`);

        ws.onopen = () => {
            console.log(`Connected to chat ${chatId}`);
        };

        ws.onclose = () => {
            this.connections.delete(chatId);
        };

        ws.onerror = (error) => {
            console.error(`WebSocket error for chat ${chatId}:`, error);
        };

        // Auto-close connection after timeout
        setTimeout(() => {
            if (ws.readyState === WebSocket.OPEN) {
                ws.close();
            }
        }, this.connectionTimeout);

        return ws;
    }

    cleanupOldConnections() {
        for (const [chatId, ws] of this.connections.entries()) {
            if (ws.readyState !== WebSocket.OPEN) {
                this.connections.delete(chatId);
            }
        }
    }
}
```

### **2. API Optimization**

#### **Efficient Payload Design**
```php
<?php
// Optimized API responses with minimal data
class MessageApiController extends Controller
{
    public function sendInstant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer',
            'message' => 'required|string|max:4096',
            'temp_id' => 'required|string'
        ]);

        // Dispatch job immediately (non-blocking)
        ProcessMessageJob::dispatch($validated, $validated['temp_id']);

        // Return minimal response for speed
        return response()->json([
            'success' => true,
            'temp_id' => $validated['temp_id'],
            'status' => 'queued',
            'timestamp' => now()->toISOString()
        ]);
    }

    public function getMessages(Request $request): JsonResponse
    {
        $contactId = $request->input('contact_id');
        $limit = min($request->input('limit', 50), 100); // Max 100 for performance

        // Use cache-first approach
        $cacheService = app(ChatCacheService::class);
        $messages = $cacheService->getRecentMessagesRedis($contactId, $limit) ??
                    $cacheService->getRecentMessagesDatabase($contactId, $limit);

        // Optimize response format
        return response()->json([
            'messages' => $this->optimizeMessageData($messages),
            'has_more' => count($messages) === $limit,
            'total_count' => $this->getTotalMessageCount($contactId),
            'cache_hit' => $cacheService->getRecentMessagesRedis($contactId, $limit) !== null
        ]);
    }

    private function optimizeMessageData(array $messages): array
    {
        // Include only essential fields
        return array_map(function ($message) {
            return [
                'id' => $message['id'],
                'content' => $message['message'],
                'type' => $message['type'],
                'status' => $message['status'],
                'timestamp' => $message['created_at'],
                'temp_id' => $message['temp_id'] ?? null
            ];
        }, $messages);
    }
}
```

### **3. CDN and Static Asset Optimization**

#### **Frontend Asset Optimization**
```javascript
// Asset loading optimization
const assetLoader = {
    // Preload critical assets
    preloadCriticalAssets() {
        const criticalAssets = [
            '/js/components/chat-bubble.js',
            '/js/components/message-status.js',
            '/css/chat-thread.css'
        ];

        criticalAssets.forEach(asset => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = asset.endsWith('.js') ? 'script' : 'style';
            link.href = asset;
            document.head.appendChild(link);
        });
    },

    // Lazy load non-critical assets
    loadAssetOnDemand(assetPath) {
        return new Promise((resolve, reject) => {
            const element = document.createElement(
                assetPath.endsWith('.js') ? 'script' : 'link'
            );

            element.onload = resolve;
            element.onerror = reject;

            if (assetPath.endsWith('.js')) {
                element.src = assetPath;
                element.async = true;
            } else {
                element.rel = 'stylesheet';
                element.href = assetPath;
            }

            document.head.appendChild(element);
        });
    },

    // Service Worker for caching
    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('SW registered:', registration);
                })
                .catch(error => {
                    console.error('SW registration failed:', error);
                });
        }
    }
};
```

---

## 📊 PERFORMANCE MONITORING

### **1. Real-time Performance Metrics**

#### **Frontend Performance Monitoring**
```javascript
class PerformanceMonitor {
    constructor() {
        this.metrics = {
            messageSendTime: [],
            scrollPerformance: [],
            memoryUsage: [],
            renderTime: []
        };

        this.startMonitoring();
    }

    startMonitoring() {
        // Monitor message send performance
        this.measureMessageSendPerformance();

        // Monitor scroll performance
        this.measureScrollPerformance();

        // Monitor memory usage
        this.measureMemoryUsage();

        // Report metrics every 30 seconds
        setInterval(() => {
            this.reportMetrics();
        }, 30000);
    }

    measureMessageSendPerformance() {
        // Hook into message sending
        const originalSend = window.sendMessage;
        window.sendMessage = function(...args) {
            const startTime = performance.now();

            return originalSend.apply(this, args).finally(() => {
                const endTime = performance.now();
                const duration = endTime - startTime;

                window.performanceMonitor.metrics.messageSendTime.push({
                    duration: Math.round(duration),
                    timestamp: Date.now()
                });

                // Warn if too slow
                if (duration > 100) {
                    console.warn(`Slow message send: ${duration}ms`);
                }
            });
        };
    }

    measureScrollPerformance() {
        let frameCount = 0;
        let lastTime = performance.now();
        let isScrolling = false;

        const measureFrame = () => {
            frameCount++;
            const currentTime = performance.now();

            if (currentTime - lastTime >= 1000) {
                const fps = frameCount;
                frameCount = 0;
                lastTime = currentTime;

                if (isScrolling) {
                    window.performanceMonitor.metrics.scrollPerformance.push({
                        fps,
                        timestamp: Date.now()
                    });

                    if (fps < 30) {
                        console.warn(`Poor scroll performance: ${fps}fps`);
                    }
                }
            }

            requestAnimationFrame(measureFrame);
        };

        requestAnimationFrame(measureFrame);

        // Track scrolling state
        document.addEventListener('scroll', () => {
            isScrolling = true;
            clearTimeout(this.scrollTimer);
            this.scrollTimer = setTimeout(() => {
                isScrolling = false;
            }, 150);
        });
    }

    measureMemoryUsage() {
        if ('memory' in performance) {
            setInterval(() => {
                const memory = performance.memory;
                this.metrics.memoryUsage.push({
                    used: Math.round(memory.usedJSHeapSize / 1024 / 1024), // MB
                    total: Math.round(memory.totalJSHeapSize / 1024 / 1024), // MB
                    limit: Math.round(memory.jsHeapSizeLimit / 1024 / 1024), // MB
                    timestamp: Date.now()
                });

                // Warn if memory usage is high
                const usedMB = memory.usedJSHeapSize / 1024 / 1024;
                if (usedMB > 50) {
                    console.warn(`High memory usage: ${usedMB}MB`);
                }
            }, 10000); // Check every 10 seconds
        }
    }

    reportMetrics() {
        const metrics = {
            messageSendTime: this.calculateStats(this.metrics.messageSendTime),
            scrollPerformance: this.calculateStats(this.metrics.scrollPerformance),
            memoryUsage: this.getLatestMemoryUsage(),
            timestamp: Date.now()
        };

        // Send to analytics
        fetch('/api/performance/metrics', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(metrics)
        }).catch(error => {
            console.warn('Failed to report metrics:', error);
        });

        // Clear old metrics
        this.clearOldMetrics();
    }

    calculateStats(values) {
        if (values.length === 0) return null;

        const sorted = values.map(v => v.duration || v.fps).sort((a, b) => a - b);
        const len = sorted.length;

        return {
            avg: Math.round(sorted.reduce((a, b) => a + b, 0) / len),
            min: sorted[0],
            max: sorted[len - 1],
            p50: sorted[Math.floor(len * 0.5)],
            p95: sorted[Math.floor(len * 0.95)],
            p99: sorted[Math.floor(len * 0.99)]
        };
    }

    clearOldMetrics() {
        const cutoff = Date.now() - (5 * 60 * 1000); // Keep last 5 minutes

        Object.keys(this.metrics).forEach(key => {
            this.metrics[key] = this.metrics[key].filter(
                metric => metric.timestamp > cutoff
            );
        });
    }
}

// Initialize performance monitor
new PerformanceMonitor();
```

### **2. Backend Performance Monitoring**

#### **Laravel Performance Tracking**
```php
<?php
class PerformanceMiddleware
{
    public function handle($request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $metrics = [
            'duration_ms' => round(($endTime - $startTime) * 1000, 2),
            'memory_mb' => round(($endMemory - $startMemory) / 1024 / 1024, 2),
            'route' => $request->route()->getName(),
            'method' => $request->method(),
            'timestamp' => now()->toISOString()
        ];

        // Log slow requests
        if ($metrics['duration_ms'] > 500) {
            Log::warning('Slow request detected', $metrics);
        }

        // Add to response headers for debugging
        $response->headers->set('X-Response-Time', $metrics['duration_ms'] . 'ms');
        $response->headers->set('X-Memory-Usage', $metrics['memory_mb'] . 'MB');

        // Send to monitoring service
        $this->reportMetrics($metrics);

        return $response;
    }

    private function reportMetrics(array $metrics): void
    {
        // Send to monitoring service asynchronously
        dispatch(function () use ($metrics) {
            Http::timeout(2)->post(config('monitoring.url'), $metrics);
        });
    }
}
```

---

## ✅ PERFORMANCE SUCCESS CRITERIA

### **Frontend Performance Targets**
- ✅ **Message Send**: <100ms for 95% of messages
- ✅ **Status Updates**: <500ms real-time delivery
- ✅ **Scroll Performance**: ≥55fps on all devices
- ✅ **Memory Usage**: <50MB per chat session
- ✅ **Initial Load**: <2s for full conversation

### **Backend Performance Targets**
- ✅ **API Response**: <200ms for 95% of requests
- ✅ **Database Queries**: <50ms for indexed queries
- ✅ **Queue Processing**: <1s for message processing
- ✅ **Cache Hit Rate**: >80% for frequently accessed data
- ✅ **WebSocket Latency**: <100ms message delivery

### **Network Optimization**
- ✅ **Payload Size**: 80% reduction through optimization
- ✅ **Connection Reuse**: Persistent WebSocket connections
- ✅ **Caching Strategy**: Multi-level caching implementation
- ✅ **CDN Integration**: Static assets served from CDN

### **Quality Assurance**
- ✅ **Cross-browser**: Chrome, Firefox, Safari, Edge optimization
- ✅ **Mobile Performance**: Smooth on all devices
- ✅ **Error Handling**: Graceful degradation on failures
- ✅ **Monitoring**: Real-time performance tracking
- ✅ **Alerting**: Automated performance alerts

---

This comprehensive performance optimization guide provides the technical foundation needed to achieve WhatsApp Web-like speed and responsiveness in the Blazz chat system.