# ⚡ SwiftChats Quick Start Guide

## 🚀 30-Second Setup

```bash
# Prerequisites: PHP 8.2+, Node.js 18+, MySQL
composer install && npm install
cp .env.example .env && php artisan key:generate
npm run dev && php artisan serve
# Open: http://127.0.0.1:8000
```

## 📋 Essential Commands

### Development Servers
```bash
# Start development (run in 2 terminals)
npm run dev        # Terminal 1: Vite (keep running)
php artisan serve  # Terminal 2: Laravel (keep running)
```

### First Time Setup
```bash
# 1. Dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database (create 'swiftchats' database first)
php artisan migrate
php artisan db:seed  # optional

# 4. Storage
php artisan storage:link
```

### Daily Development
```bash
# Start working
npm run dev && php artisan serve

# Clear caches (if issues)
php artisan optimize:clear

# Update dependencies
composer update && npm update

# Reset database (if needed)
php artisan migrate:fresh --seed
```

## 🔧 Quick Fixes

### Problem: Black & White App
```bash
# Solution: Start both servers
npm run dev        # Missing this causes styling issues
php artisan serve  # Then this
```

### Problem: ERR_CONNECTION_CLOSED
```bash
# Fix .env file
APP_ENV=local      # NOT production
APP_DEBUG=true     # NOT false
# Restart servers
```

### Problem: Permission Errors
```bash
chmod -R 775 storage bootstrap/cache
```

### Problem: Database Connection
```bash
# Check MySQL running in MAMP
# Update .env database settings
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=your_password
```

## ✅ Health Check

### Verify Installation
```bash
php artisan --version    # Laravel Framework 12.29.0
npm --version           # 9+
mysql --version         # 5.7+/8.0+
curl http://127.0.0.1:8000  # 200 OK
curl http://localhost:5173   # 200 OK
```

### Test Database
```bash
php artisan tinker
>>> DB::select('SELECT 1 as test');
>>> exit
```

### Test Authentication
```bash
php artisan tinker
>>> $user = App\Models\User::first();
>>> $token = $user->createToken('test');
>>> echo 'Sanctum working!';
>>> exit
```

## 🎯 Cheat Sheet

### Most Used Commands
| Command | Purpose |
|---------|---------|
| `npm run dev` | Start Vite development server |
| `php artisan serve` | Start Laravel server |
| `php artisan optimize:clear` | Clear all caches |
| `php artisan migrate` | Run database migrations |
| `php artisan tinker` | Laravel REPL/debugging |
| `composer update` | Update PHP dependencies |
| `npm update` | Update Node.js dependencies |

### Important Ports
| Service | Port | URL |
|---------|------|-----|
| Laravel | 8000 | http://127.0.0.1:8000 |
| Vite | 5173 | http://localhost:5173 |
| MySQL | 3306 | localhost:3306 |

### Key Files
| File | Purpose |
|------|---------|
| `.env` | Environment configuration |
| `vite.config.js` | Frontend build configuration |
| `composer.json` | PHP dependencies |
| `package.json` | Node.js dependencies |

### Environment Values
```bash
# Development settings
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
```

## 🆘 Emergency Recovery

### Complete Reset
```bash
# If everything breaks
composer clear-cache
npm cache clean --force
rm -rf vendor/ node_modules/
composer install && npm install
php artisan optimize:clear
npm run dev && php artisan serve
```

### Database Reset
```bash
# Careful: This deletes all data
php artisan migrate:fresh --seed
```

### Asset Rebuild
```bash
# If assets won't load
npm run build
php artisan optimize:clear
```

## 📊 Performance Benchmarks

### Expected Performance (Local)
- **App Boot**: < 1s
- **Page Load**: < 500ms
- **Asset Load**: < 200ms per file
- **Database Query**: < 50ms average

### Framework Versions
- **Laravel**: 12.29.0
- **Sanctum**: 4.2.0  
- **Inertia.js**: 2.0.6
- **Vue.js**: 3.2.36
- **Vite**: 4.5.14

## 💡 Pro Tips

### Development Efficiency
1. **Keep both terminals open** with Vite and Laravel servers
2. **Use hard refresh** (Ctrl+Shift+R) when assets don't update
3. **Monitor browser console** for asset loading errors
4. **Clear caches regularly** during development
5. **Use `php artisan about`** to check system status

### Common Patterns
```bash
# Daily startup routine
cd /path/to/swiftchats
npm run dev &          # Background
php artisan serve      # Foreground

# Before committing code
php artisan test
npm run build
php artisan optimize:clear
```

### Troubleshooting Shortcuts
```bash
# Quick diagnosis
php artisan about              # System info
tail storage/logs/laravel.log  # Recent errors
ps aux | grep -E "php|node"    # Running processes
```

## 🔗 Quick Links

- **Application**: http://127.0.0.1:8000
- **Vite Dev**: http://localhost:5173
- **Logs**: `storage/logs/laravel.log`
- **Docs**: `/docs/development/`

---

**🎉 You're ready to develop with SwiftChats + Laravel 12!**

**Need help?** Check `/docs/development/troubleshooting.md` for detailed solutions.