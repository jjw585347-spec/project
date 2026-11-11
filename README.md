# سیستم نوبت دهی آرایشگاه

این پروژه دو بخش دارد:

| پوشه | چیست | آدرس |
|---|---|---|
| `backend` | بک اند (Laravel / PHP) | http://localhost:8000 |
| `frontend` | فرانت اند (React) | http://localhost:5173 |

---

## پیش‌نیازها (فقط یک بار)

- **Node.js** (برای فرانت اند)
- **PHP** و **Composer** (برای بک اند)

> بعد از نصب PHP، یک بار ترمینال را ببندید و دوباره باز کنید تا دستور `php` شناخته شود.

---

## اجرای بک اند

یک ترمینال باز کنید:

```bash
cd backend
php artisan serve
```

تست: در مرورگر بروید به http://localhost:8000/api/test

---

## اجرای فرانت اند

یک ترمینال **دیگر** باز کنید (ترمینال بک اند باید باز بماند):

```bash
cd frontend
npm run dev
```

سپس در مرورگر بروید به http://localhost:5173

باید پیام «سلام من بک اند هستم و به درستی کار میکنم» را ببینید.

---

## اگر پروژه را تازه از گیت گرفته‌اید (فقط بار اول)

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

```bash
cd frontend
npm install
```

---

## خاموش کردن

در هر ترمینال کلیدهای `Ctrl + C` را بزنید.
