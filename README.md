# 🚀 Blazz - Enterprise WhatsApp Business Platform

<p align="center">
<img src="https://img.shields.io/badge/Laravel-12.29.0-red.svg" alt="Laravel Version">
<img src="https://img.shields.io/badge/PHP-8.2+-blue.svg" alt="PHP Version">
<img src="https://img.shields.io/badge/Vue.js-3.x-green.svg" alt="Vue.js Version">
<img src="https://img.shields.io/badge/Status-Production Ready-brightgreen.svg" alt="Status">
</p>

## About Blazz

**Blazz** adalah enterprise-grade multi-tenant chat platform yang mengintegrasikan WhatsApp Business API untuk komunikasi pelanggan yang efektif. Platform ini dirancang untuk business yang membutuhkan solusi komunikasi scalable dengan fitur real-time messaging, template management, campaign automation, dan analytics mendalam.

### 🎯 Core Features

- **🔄 Real-time Messaging**: Instant bidirectional communication dengan WhatsApp Business API
- **📝 Template Management**: Advanced template creation, approval tracking, dan optimization tools  
- **🚀 Campaign Automation**: Automated messaging campaigns dengan segmentation dan scheduling
- **📊 Analytics Dashboard**: Comprehensive metrics, engagement tracking, dan performance insights
- **👥 Multi-tenant Architecture**: Complete workspace isolation dengan role-based access control
- **🔒 Enterprise Security**: Advanced security protocols, audit logging, dan compliance features
- **🌍 Multilingual Support**: Built-in internationalization untuk global business operations

### 🏗️ Technical Architecture

**Backend Framework:**
- **Laravel 12.29.0** - Modern PHP framework dengan enterprise features
- **MySQL 8.0+** - Robust database dengan advanced indexing dan optimization
- **Redis** - High-performance caching dan session management
- **Queue Workers** - Background job processing untuk high-volume operations

**Frontend Stack:**
- **Vue.js 3.x** - Reactive user interface dengan modern development experience
- **Inertia.js** - Seamless SPA experience tanpa API complexity
- **Tailwind CSS** - Utility-first styling untuk responsive design
- **Vite** - Fast build tools dengan hot module replacement

---

## 📚 Architecture Documentation

Blazz menggunakan **Hybrid Service-Oriented Modular Architecture** yang menggabungkan:
- ✅ **Enhanced MVC Pattern** - Foundation layer
- ✅ **Service Layer Pattern** - Business logic isolation  
- ✅ **Job Queue System** - Asynchronous processing
- ✅ **Module Architecture** - Feature extensibility
- ✅ **Multi-tenancy Design** - Workspace isolation

**Complete Architecture Documentation:**
📖 [**View Complete Architecture Guide →**](./docs/architecture/README.md)

### Quick Links:
- [Architecture Overview](./docs/architecture/01-arsitektur-overview.md) - Complete architecture explanation
- [Component Connections](./docs/architecture/02-component-connections.md) - How components interact
- [Folder Structure](./docs/architecture/03-folder-structure.md) - Project organization guide
- [Feature Development](./docs/architecture/04-feature-development-guide.md) - Step-by-step new feature guide
- [Visual Diagrams](./docs/architecture/05-visual-diagrams.md) - Architecture visualization

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js & NPM
- MySQL 8.0+
- Redis (optional, for caching)

### Installation

```bash
# Clone repository
git clone https://github.com/yourusername/blazz.git
cd blazz

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env file
# Then run migrations
php artisan migrate --seed

# Build frontend assets
npm run build

# Start development server
php artisan serve
```

### Queue Worker (Required for campaigns)

```bash
# Start queue worker
php artisan queue:work --queue=campaigns,default
```

---

## 🎯 Development Guidelines

### Adding New Features

Follow the step-by-step guide: [Feature Development Guide](./docs/architecture/04-feature-development-guide.md)

**Quick Pattern:**
1. Create migration & model
2. Create service class dengan business logic
3. Create controller dengan thin methods
4. Define routes
5. Create Vue components & Inertia pages
6. Write tests

### Service Layer Pattern

```php
// app/Services/{Entity}Service.php
class EntityService
{
    protected $workspaceId;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    public function create(array $data)
    {
        // Business logic here
        // Always scope by workspace
        return Entity::create([
            'workspace_id' => $this->workspaceId,
            ...$data,
        ]);
    }
}
```

### Multi-Tenancy Pattern

**Always scope queries by workspace:**
```php
// ✅ GOOD
$contacts = Contact::where('workspace_id', $this->workspaceId)->get();

// ❌ BAD
$contacts = Contact::all();
```

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=ContactTest

# Run with coverage
php artisan test --coverage
```

---

## 📖 API Documentation

API endpoints available at `/api/*`. Authentication via Bearer token.

**Example:**
```bash
curl -X POST https://your-domain.com/api/send \
  -H "Authorization: Bearer your_api_token" \
  -H "Content-Type: application/json" \
  -d '{
    "contact_uuid": "abc-123",
    "message": "Hello from API!"
  }'
```

---

## 🔐 Security

### Security Features:
- Multi-guard authentication (User & Admin)
- Role-based access control (RBAC)
- Two-factor authentication (2FA)
- API token authentication
- CSRF protection
- XSS prevention
- SQL injection protection
- Rate limiting
- Audit logging

### Reporting Security Issues

If you discover a security vulnerability, please email security@yourdomain.com. All security vulnerabilities will be promptly addressed.

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Follow architecture patterns (see [Architecture Guide](./docs/architecture/README.md))
4. Write tests untuk new features
5. Commit changes (`git commit -m 'Add AmazingFeature'`)
6. Push to branch (`git push origin feature/AmazingFeature`)
7. Open Pull Request

---

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
# Blazz - Security Hardened Version

## Security Notice
This Blazz hardened version prioritizes security in production environments.

## Security Changes Made

### 1. External API Dependencies Removed
- ✅ **UpdateController.php**: Removed obfuscated axis96.com update calls
- ✅ **InstallerController.php**: Disabled external installation verification
- ✅ **ModuleService.php**: Disabled external addon downloads/updates
- ✅ **CheckModuleUpdates Command**: Disabled external update checking
- ✅ **Vue.js Components**: Removed axis96.com purchase code validation

### 2. Security Features Implemented
- ✅ **SecurityDisabledException**: Custom exception for disabled external features
- ✅ **Production Environment**: APP_DEBUG=false, APP_ENV=production
- ✅ **Code Cleanup**: Removed all obfuscated base64-encoded functions
- ✅ **Asset Rebuild**: Recompiled frontend assets without external references

### 3. Manual Operations Required
Since external automation has been disabled for security, the following operations now require manual intervention:

#### Application Updates
- Download updates manually from official sources
- Apply database migrations: `php artisan migrate`
- Clear cache: `php artisan optimize:clear`

#### Module Management
- Install modules manually by extracting to `/modules/` directory
- Update module database records manually
- Configure module settings through admin panel

#### Security Monitoring
- Check for updates manually from official project repository
- Monitor security advisories independently
- Implement your own update notification system if needed

## Installation
1. Copy project to web server
2. Run `composer install --no-dev --optimize-autoloader`
3. Run `npm install && npm run build`
4. Configure `.env` file with your database credentials
5. Run `php artisan migrate --seed`
6. Set proper file permissions (755 for directories, 644 for files)

## Security Recommendations
1. **Change APP_KEY**: Generate new key with `php artisan key:generate`
2. **Database Security**: Use strong database credentials
3. **File Permissions**: Ensure proper web server permissions
4. **Firewall**: Configure firewall rules for production
5. **SSL/TLS**: Enable HTTPS in production
6. **Regular Backups**: Implement automated backup strategy

## Support
For security-related questions or custom development needs, consult with qualified Laravel security specialists.

**Note**: This hardened version prioritizes security over convenience. All external automation features have been intentionally disabled to eliminate potential attack vectors.
