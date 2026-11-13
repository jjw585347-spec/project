# بخش بک‌اند (API) — سیستم نوبت‌دهی پیرایش عارف

این پوشه شامل سرور بک‌اند پروژه است که با **Laravel** و دیتابیس **PostgreSQL** ساخته شده است.

## راه‌اندازی اولیه

۱. نصب پکیج‌های PHP:
```powershell
composer install
```

۲. ساخت فایل تنظیمات `.env` از روی نمونه:
```powershell
copy .env.example .env
php artisan key:generate
```

۳. بررسی تنظیمات دیتابیس PostgreSQL در فایل `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=barbershop
DB_USERNAME=postgres
DB_PASSWORD=postgres
```
*(اگر نام کاربری یا رمز PostgreSQL روی سیستم شما متفاوت است، مقدار `DB_USERNAME` و `DB_PASSWORD` را در فایل `.env` تغییر دهید.)*

۴. ساخت جداول دیتابیس و افزودن لیست خدمات اولیه:
```powershell
php artisan migrate --seed
```

## اجرای سرور بک‌اند

```powershell
php artisan serve
```

پس از اجرا، سرور روی آدرس `http://localhost:8000` در دسترس خواهد بود.

## ساختار کدهای بک‌اند

- `routes/api.php`: تعریف مسیرهای API
- `app/Http/Controllers/ServiceController.php`: دریافت لیست خدمات آرایشگاه
- `app/Http/Controllers/CustomerController.php`: ثبت‌نام، ورود (با رمز ۴ رقمی عددی) و دریافت اطلاعات مشتری
- `app/Http/Controllers/AppointmentController.php`: ثبت نوبت (متصل به حساب مشتری و بررسی ساعت کاری ۱۰:۰۰ تا ۲۰:۰۰)، مشاهده نوبت‌ها و لغو نوبت توسط صاحب آرایشگاه
