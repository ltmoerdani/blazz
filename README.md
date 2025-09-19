<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

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
