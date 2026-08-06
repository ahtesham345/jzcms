# Next Steps for JZCMS Development

## Immediate Actions Required

### 1. Database Setup
```bash
# Create database
mysql -u root -p
CREATE DATABASE jzcms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;

# Update .env with your credentials
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Run migrations
php artisan migrate
```

### 2. Create Role Middleware

The routes are protected with a `role` middleware that doesn't exist yet. Create it:

```bash
php artisan make:middleware CheckRole
```

**File: app/Http/Middleware/CheckRole.php**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!$request->user()->hasRole($role)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
```

**Register in bootstrap/app.php:**

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => \App\Http\Middleware\CheckRole::class,
    ]);
})
```

### 3. Update User Model

Add role functionality to the User model:

**File: app/Models/User.php**

```php
// Add to User model
protected $fillable = [
    'name',
    'email',
    'password',
    'role', // Add this
];

public function hasRole(string $role): bool
{
    return $this->role === $role;
}

public function isAdmin(): bool
{
    return $this->role === 'admin' || $this->role === 'super_admin';
}

public function isTeacher(): bool
{
    return $this->role === 'teacher';
}

public function isParent(): bool
{
    return $this->role === 'parent';
}

public function isAccountant(): bool
{
    return $this->role === 'accountant';
}
```

### 4. Create Migration for Role Column

```bash
php artisan make:migration add_role_to_users_table
```

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->default('student')->after('email');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('role');
    });
}
```

Run migration:
```bash
php artisan migrate
```

### 5. Create Core Models

```bash
php artisan make:model Student -m
php artisan make:model Teacher -m
php artisan make:model Parent -m
php artisan make:model Fee -m
php artisan make:model Payment -m
php artisan make:model Attendance -m
php artisan make:model Subject -m
php artisan make:model ClassRoom -m
php artisan make:model AcademicYear -m
```

### 6. Create Controllers

```bash
# Admin Controllers
php artisan make:controller Admin/DashboardController
php artisan make:controller Admin/StudentController --resource
php artisan make:controller Admin/TeacherController --resource
php artisan make:controller Admin/ParentController --resource

# Teacher Controllers
php artisan make:controller Teacher/DashboardController
php artisan make:controller Teacher/AttendanceController
php artisan make:controller Teacher/ClassController

# Parent Controllers
php artisan make:controller Parent/DashboardController
php artisan make:controller Parent/ChildrenController
php artisan make:controller Parent/FeeController

# Accounts Controllers
php artisan make:controller Accounts/DashboardController
php artisan make:controller Accounts/FeeCollectionController
php artisan make:controller Accounts/ReportController
```

### 7. Install Authentication Package

Choose one of these options:

**Option A: Laravel Breeze (Simple)**
```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install
npm run build
php artisan migrate
```

**Option B: Custom Authentication**
```bash
php artisan make:controller Auth/LoginController
php artisan make:controller Auth/RegisterController
php artisan make:controller Auth/LogoutController
```

### 8. Create Seeders

```bash
php artisan make:seeder RoleSeeder
php artisan make:seeder AdminUserSeeder
php artisan make:seeder AcademicYearSeeder
```

**Example: database/seeders/AdminUserSeeder.php**

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

public function run(): void
{
    User::create([
        'name' => 'Admin User',
        'email' => 'admin@jzcms.edu.pk',
        'password' => Hash::make('password'),
        'role' => 'super_admin',
    ]);
}
```

Run seeders:
```bash
php artisan db:seed
```

## Development Workflow

### Daily Development
```bash
# Terminal 1 - Watch assets
npm run dev

# Terminal 2 - Run server
php artisan serve

# Terminal 3 - Available for commands
```

### Before Committing
```bash
# Format code
./vendor/bin/pint

# Run tests (when created)
php artisan test

# Check for errors
php artisan about
```

## Recommended Packages

### Forms & Validation
```bash
composer require laravel/precognition  # Real-time validation
```

### Excel Import/Export
```bash
composer require maatwebsite/excel
```

### PDF Generation
```bash
composer require barryvdh/laravel-dompdf
```

### Image Manipulation
```bash
composer require intervention/image
```

### Activity Logging
```bash
composer require spatie/laravel-activitylog
```

### Permissions (Alternative to role middleware)
```bash
composer require spatie/laravel-permission
```

## Project Structure to Create

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   ├── Teacher/
│   │   ├── Parent/
│   │   ├── Accounts/
│   │   └── Auth/
│   ├── Middleware/
│   │   └── CheckRole.php
│   └── Requests/
│       ├── StoreStudentRequest.php
│       └── UpdateStudentRequest.php
├── Models/
│   ├── Student.php
│   ├── Teacher.php
│   ├── Parent.php
│   ├── Fee.php
│   ├── Payment.php
│   ├── Attendance.php
│   └── ...
└── Services/
    ├── FeeService.php
    ├── AttendanceService.php
    └── ReportService.php
```

## Testing Setup

```bash
# Create tests
php artisan make:test StudentTest
php artisan make:test Admin/StudentControllerTest

# Run tests
php artisan test
```

## Production Deployment Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Update `APP_URL`
- [ ] Set strong database credentials
- [ ] Run `composer install --optimize-autoloader --no-dev`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Run `npm run build`
- [ ] Set proper file permissions (775 for storage and bootstrap/cache)
- [ ] Setup SSL certificate
- [ ] Configure backup system
- [ ] Setup queue workers if needed
- [ ] Configure MAIL settings
- [ ] Setup monitoring and logging

## Useful Artisan Commands

```bash
# View routes
php artisan route:list

# View config
php artisan config:show jzcms

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Database
php artisan migrate:status
php artisan db:show
php artisan migrate:fresh --seed

# Queue (if implemented)
php artisan queue:work
php artisan queue:failed
php artisan queue:retry all

# Maintenance mode
php artisan down
php artisan up
```

## Environment Variables to Configure

Add these to your `.env` as needed:

```env
# JZCMS Specific
ACADEMIC_YEAR=2024
STUDENT_ID_PREFIX=STD
STUDENT_ID_LENGTH=6

# Currency
CURRENCY_CODE=PKR
CURRENCY_SYMBOL=Rs.

# Fees
LATE_FEE_ENABLED=true
LATE_FEE_DAYS=10
LATE_FEE_AMOUNT=100

# Date Format
DATE_FORMAT=d-m-Y
DATE_TIME_FORMAT=d-m-Y H:i:s

# SMS (when ready)
SMS_ENABLED=false
SMS_PROVIDER=

# Email
EMAIL_ENABLED=true
```

## Resources

- Laravel Documentation: https://laravel.com/docs
- Tailwind CSS: https://tailwindcss.com/docs
- Laravel Best Practices: https://github.com/alexeymezenin/laravel-best-practices

## Support

For questions or issues during development, check:
1. Laravel logs: `storage/logs/laravel.log`
2. Browser console for frontend errors
3. `php artisan about` for system information
