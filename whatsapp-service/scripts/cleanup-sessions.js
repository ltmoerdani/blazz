#!/usr/bin/env node

/**
 * WhatsApp Session Cleanup Script
 * 
 * Cleans up old/orphaned WhatsApp sessions to reclaim disk space.
 * 
 * Usage:
 *   node scripts/cleanup-sessions.js           # Dry run (show what would be deleted)
 *   node scripts/cleanup-sessions.js --execute # Actually delete files
 * 
 * @author Blazz Platform
 * @date December 2025
 */

const fs = require('fs-extra');
const path = require('path');

// Configuration
const CONFIG = {
    // Paths to clean
    sessionsPaths: [
        './sessions',
        './sessions-shared',
        './.wwebjs_auth',
        './.wwebjs_cache'
    ],
    // Keep sessions newer than this (in days)
    maxAgeDays: 7,
    // Keep at least this many sessions per user
    keepLatestPerUser: 1,
    // Dry run by default (set --execute to actually delete)
    dryRun: !process.argv.includes('--execute'),
};

// Statistics
const stats = {
    scannedFolders: 0,
    deletedFolders: 0,
    deletedSizeMB: 0,
    keptFolders: 0,
    errors: []
};

/**
 * Get directory size in bytes (recursive)
 */
async function getDirectorySize(dirPath) {
    let size = 0;
    try {
        const files = await fs.readdir(dirPath, { withFileTypes: true });
        for (const file of files) {
            const filePath = path.join(dirPath, file.name);
            if (file.isDirectory()) {
                size += await getDirectorySize(filePath);
            } else {
                const stat = await fs.stat(filePath);
                size += stat.size;
            }
        }
    } catch (err) {
        // Ignore permission errors
    }
    return size;
}

/**
 * Format bytes to human readable
 */
function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    return (bytes / 1024 / 1024 / 1024).toFixed(2) + ' GB';
}

/**
 * Parse session folder name to extract user ID and timestamp
 */
function parseSessionFolder(folderName) {
    // Pattern: session-webjs_{userId}_{timestamp}_{random}
    const webJsMatch = folderName.match(/session-webjs_(\d+)_(\d+)_(\w+)/);
    if (webJsMatch) {
        return {
            type: 'webjs',
            userId: webJsMatch[1],
            timestamp: parseInt(webJsMatch[2]) * 1000,
            random: webJsMatch[3]
        };
    }
    
    // Pattern: session-{userId}-{uuid}
    const legacyMatch = folderName.match(/session-(\d+)-([a-f0-9-]+)/i);
    if (legacyMatch) {
        return {
            type: 'legacy',
            userId: legacyMatch[1],
            timestamp: 0, // Unknown timestamp
            uuid: legacyMatch[2]
        };
    }
    
    // Pattern: instancewhatsapp-instance-{id}
    const instanceMatch = folderName.match(/instancewhatsapp-instance-(\d+)/);
    if (instanceMatch) {
        return {
            type: 'instance',
            instanceId: instanceMatch[1],
            timestamp: 0
        };
    }
    
    return null;
}

/**
 * Clean sessions in a workspace directory
 */
async function cleanWorkspace(workspacePath, workspaceName) {
    console.log(`\n📂 Scanning workspace: ${workspaceName}`);
    
    let sessions;
    try {
        sessions = await fs.readdir(workspacePath);
    } catch (err) {
        console.log(`   ⚠️  Cannot read directory: ${err.message}`);
        return;
    }
    
    // Group sessions by user
    const sessionsByUser = {};
    const unknownSessions = [];
    
    for (const sessionFolder of sessions) {
        if (sessionFolder.startsWith('.')) continue; // Skip hidden files
        
        const sessionPath = path.join(workspacePath, sessionFolder);
        const stat = await fs.stat(sessionPath).catch(() => null);
        if (!stat || !stat.isDirectory()) continue;
        
        stats.scannedFolders++;
        
        const parsed = parseSessionFolder(sessionFolder);
        const size = await getDirectorySize(sessionPath);
        
        const sessionInfo = {
            folder: sessionFolder,
            path: sessionPath,
            size: size,
            mtime: stat.mtime,
            parsed: parsed
        };
        
        if (parsed && parsed.userId) {
            if (!sessionsByUser[parsed.userId]) {
                sessionsByUser[parsed.userId] = [];
            }
            sessionsByUser[parsed.userId].push(sessionInfo);
        } else {
            unknownSessions.push(sessionInfo);
        }
    }
    
    // Process sessions by user - keep only the latest
    for (const [userId, userSessions] of Object.entries(sessionsByUser)) {
        // Sort by timestamp (newest first)
        userSessions.sort((a, b) => {
            const tsA = a.parsed?.timestamp || a.mtime.getTime();
            const tsB = b.parsed?.timestamp || b.mtime.getTime();
            return tsB - tsA;
        });
        
        console.log(`   👤 User ${userId}: ${userSessions.length} session(s)`);
        
        for (let i = 0; i < userSessions.length; i++) {
            const session = userSessions[i];
            const isOld = session.mtime < new Date(Date.now() - CONFIG.maxAgeDays * 24 * 60 * 60 * 1000);
            const shouldDelete = i >= CONFIG.keepLatestPerUser || isOld;
            
            if (shouldDelete) {
                console.log(`      🗑️  DELETE: ${session.folder} (${formatSize(session.size)})`);
                
                if (!CONFIG.dryRun) {
                    try {
                        await fs.remove(session.path);
                        stats.deletedFolders++;
                        stats.deletedSizeMB += session.size / 1024 / 1024;
                    } catch (err) {
                        console.log(`         ❌ Error: ${err.message}`);
                        stats.errors.push({ path: session.path, error: err.message });
                    }
                } else {
                    stats.deletedFolders++;
                    stats.deletedSizeMB += session.size / 1024 / 1024;
                }
            } else {
                console.log(`      ✅ KEEP: ${session.folder} (${formatSize(session.size)})`);
                stats.keptFolders++;
            }
        }
    }
    
    // Handle unknown sessions (delete if old)
    for (const session of unknownSessions) {
        const isOld = session.mtime < new Date(Date.now() - CONFIG.maxAgeDays * 24 * 60 * 60 * 1000);
        
        if (isOld || session.size === 0) {
            console.log(`   🗑️  DELETE (unknown/old): ${session.folder} (${formatSize(session.size)})`);
            
            if (!CONFIG.dryRun) {
                try {
                    await fs.remove(session.path);
                    stats.deletedFolders++;
                    stats.deletedSizeMB += session.size / 1024 / 1024;
                } catch (err) {
                    stats.errors.push({ path: session.path, error: err.message });
                }
            } else {
                stats.deletedFolders++;
                stats.deletedSizeMB += session.size / 1024 / 1024;
            }
        } else {
            console.log(`   ⚠️  SKIP (unknown): ${session.folder} (${formatSize(session.size)})`);
            stats.keptFolders++;
        }
    }
}

/**
 * Clean a sessions directory
 */
async function cleanSessionsDir(sessionsPath) {
    const fullPath = path.resolve(sessionsPath);
    
    if (!await fs.pathExists(fullPath)) {
        console.log(`\n📁 ${sessionsPath} - Not found, skipping`);
        return;
    }
    
    console.log(`\n${'='.repeat(60)}`);
    console.log(`📁 Cleaning: ${sessionsPath}`);
    console.log(`${'='.repeat(60)}`);
    
    const contents = await fs.readdir(fullPath, { withFileTypes: true });
    
    // Check if this directory contains workspaces or sessions directly
    const hasWorkspaces = contents.some(c => c.isDirectory() && c.name.startsWith('workspace_'));
    
    if (hasWorkspaces) {
        // Process each workspace
        for (const item of contents) {
            if (item.isDirectory() && item.name.startsWith('workspace_')) {
                await cleanWorkspace(path.join(fullPath, item.name), item.name);
            }
        }
    } else {
        // Process as single workspace
        await cleanWorkspace(fullPath, path.basename(fullPath));
    }
}

/**
 * Clean empty directories
 */
async function cleanEmptyDirs(dirPath) {
    const contents = await fs.readdir(dirPath);
    
    for (const item of contents) {
        const itemPath = path.join(dirPath, item);
        const stat = await fs.stat(itemPath).catch(() => null);
        
        if (stat && stat.isDirectory()) {
            await cleanEmptyDirs(itemPath);
            
            // Check if directory is now empty
            const subContents = await fs.readdir(itemPath);
            if (subContents.length === 0) {
                console.log(`   🗑️  Remove empty: ${itemPath}`);
                if (!CONFIG.dryRun) {
                    await fs.remove(itemPath);
                }
            }
        }
    }
}

/**
 * Main function
 */
async function main() {
    console.log('='.repeat(60));
    console.log('🧹 WhatsApp Session Cleanup Script');
    console.log('='.repeat(60));
    console.log(`Mode: ${CONFIG.dryRun ? '🔍 DRY RUN (no files deleted)' : '🗑️  EXECUTE (files will be deleted)'}`);
    console.log(`Max age: ${CONFIG.maxAgeDays} days`);
    console.log(`Keep per user: ${CONFIG.keepLatestPerUser}`);
    
    const startTime = Date.now();
    
    // Clean each sessions path
    for (const sessionsPath of CONFIG.sessionsPaths) {
        await cleanSessionsDir(sessionsPath);
    }
    
    // Clean empty directories
    console.log('\n📁 Cleaning empty directories...');
    for (const sessionsPath of CONFIG.sessionsPaths) {
        const fullPath = path.resolve(sessionsPath);
        if (await fs.pathExists(fullPath)) {
            await cleanEmptyDirs(fullPath);
        }
    }
    
    // Print summary
    const duration = ((Date.now() - startTime) / 1000).toFixed(1);
    
    console.log('\n' + '='.repeat(60));
    console.log('📊 Summary');
    console.log('='.repeat(60));
    console.log(`Scanned: ${stats.scannedFolders} folders`);
    console.log(`Deleted: ${stats.deletedFolders} folders`);
    console.log(`Kept: ${stats.keptFolders} folders`);
    console.log(`Space ${CONFIG.dryRun ? 'to reclaim' : 'reclaimed'}: ${stats.deletedSizeMB.toFixed(1)} MB`);
    console.log(`Duration: ${duration}s`);
    
    if (stats.errors.length > 0) {
        console.log(`\n⚠️  Errors: ${stats.errors.length}`);
        stats.errors.forEach(e => console.log(`   - ${e.path}: ${e.error}`));
    }
    
    if (CONFIG.dryRun) {
        console.log('\n💡 To actually delete files, run:');
        console.log('   node scripts/cleanup-sessions.js --execute');
    }
    
    console.log('');
}

// Run
main().catch(err => {
    console.error('Fatal error:', err);
    process.exit(1);
});
