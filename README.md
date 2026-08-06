# Jamia Zahidia Campus Management System (JZCMS)

A production-ready Laravel 12 ERP system for managing campus operations including students, teachers, parents, fees, attendance, and reports.

## Tech Stack

- **Framework**: Laravel 12.x
- **PHP**: 8.2+
- **Database**: MySQL
- **Frontend**: Blade Templates + Tailwind CSS
- **Build Tool**: Vite
- **Code Style**: Laravel Pint

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js 18+ and NPM
- MySQL 5.7+ or MariaDB 10.3+

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd jzcms
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node Dependencies

```bash
npm install
```

### 4. Environment Configuration

Copy the `.env.example` to `.env` (or use the existing `.env`):

```bash
cp .env.example .env
```

Update the following database credentials in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jzcms
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password
```

### 5. Generate Application Key

```bash
php artisan key:generate
```

### 6. Create Database

Create a MySQL database named `jzcms`:

```sql
CREATE DATABASE jzcms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 7. Run Migrations

```bash
php artisan migrate
```

### 8. Build Frontend Assets

For development:
```bash
npm run dev
```

For production:
```bash
npm run build
```

**Important:** During development, keep `npm run dev` running in a separate terminal for hot reload.

### 9. Start Development Server

```bash
php artisan serve
```

Visit: `http://localhost:8000`

## Project Structure

```
jzcms/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # Controllers (to be created)
│   │   └── Middleware/           # Middleware (to be created)
│   ├── Models/
│   │   └── User.php              # User model (needs role column)
│   └── Providers/                # Service providers
├── bootstrap/
│   └── app.php                   # ✓ Routes configured
├── config/
│   ├── app.php                   # ✓ Timezone configured
│   ├── jzcms.php                 # ✓ Project settings
│   └── ...                       # Other configs
├── database/
│   ├── migrations/               # Database migrations
│   └── seeders/                  # Database seeders
├── public/
│   ├── build/                    # ✓ Compiled assets
│   └── storage/                  # ✓ Symlinked to storage/app/public
├── resources/
│   ├── css/
│   │   └── app.css               # ✓ Tailwind CSS
│   ├── js/
│   │   └── app.js                # ✓ JavaScript
│   └── views/
│       ├── layouts/
│       │   ├── app.blade.php     # ✓ Main layout
│       │   └── guest.blade.php   # ✓ Guest layout
│       ├── auth/                 # Authentication views
│       ├── dashboard/            # Dashboard views
│       ├── students/             # Student management
│       ├── teachers/             # Teacher management
│       ├── parents/              # Parent portal
│       ├── fees/                 # Fee management
│       ├── attendance/           # Attendance tracking
│       ├── reports/              # Reports
│       ├── components/           # Reusable components
│       └── welcome.blade.php     # ✓ Welcome page
├── routes/
│   ├── web.php                   # ✓ Main web routes
│   ├── admin.php                 # ✓ Admin routes
│   ├── teacher.php               # ✓ Teacher routes
│   ├── parent.php                # ✓ Parent routes
│   ├── accounts.php              # ✓ Accounts routes
│   └── console.php               # ✓ Console commands
├── storage/
│   └── app/public/               # ✓ Public file storage
├── .editorconfig                 # ✓ Editor configuration
├── .env                          # ✓ Environment (timezone set)
├── .gitignore                    # ✓ Git ignore rules
├── pint.json                     # ✓ Code style rules
├── tailwind.config.js            # ✓ Tailwind configuration
├── postcss.config.js             # ✓ PostCSS configuration
├── vite.config.js                # ✓ Vite build config
├── README.md                     # This file
├── SETUP_SUMMARY.md              # ✓ Setup completion summary
└── NEXT_STEPS.md                 # ✓ Development guide
```

## Development

### Code Formatting

Run Laravel Pint to format code:

```bash
./vendor/bin/pint
```

### Compile Assets

Watch for changes (development):
```bash
npm run dev
```

Build for production:
```bash
npm run build
```

### Clear Caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### Run Tests

```bash
php artisan test
```

## Features to Implement

- [ ] Authentication & Authorization with Role-based Access
- [ ] Student Management
- [ ] Teacher Management
- [ ] Parent Portal
- [ ] Fee Management & Collection
- [ ] Attendance Tracking
- [ ] Report Generation
- [ ] User Roles & Permissions (Super Admin, Admin, Teacher, Parent, Student, Accountant)
- [ ] Academic Year Management
- [ ] Class & Section Management
- [ ] Exam & Grades Management
- [ ] Notifications & Alerts
- [ ] SMS Integration
- [ ] Online Payment Gateway

## Configuration

### JZCMS Config File

The project includes a comprehensive configuration file at `config/jzcms.php`:

```php
// Access configuration values
config('jzcms.short_name')           // JZCMS
config('jzcms.roles')                // Array of user roles
config('jzcms.currency.symbol')      // Rs.
config('jzcms.date_format')          // d-m-Y
config('jzcms.pagination.per_page')  // 15
```

### Environment Configuration

The `.env` file includes:
- **Timezone**: Asia/Karachi (Pakistan Standard Time)
- **Database**: MySQL (configure your credentials)
- **Currency**: PKR (Pakistani Rupee)

### Route Files

Routes are organized by user role:
- `routes/web.php` - Public routes
- `routes/admin.php` - Admin panel routes (`/admin/*`)
- `routes/teacher.php` - Teacher portal (`/teacher/*`)
- `routes/parent.php` - Parent portal (`/parent/*`)
- `routes/accounts.php` - Accounts/Finance (`/accounts/*`)

All routes are loaded automatically via `bootstrap/app.php`.

## Production Deployment

### Optimize for Production

```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```

### File Permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Environment Variables

Ensure these are properly set in production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Run `./vendor/bin/pint` to format code
5. Push to the branch
6. Create a Pull Request

## License

This project is proprietary software for Jamia Zahidia.

## Support

For support, contact your system administrator.
