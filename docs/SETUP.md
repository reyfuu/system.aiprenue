# Setup Lokal — Pipeline

Catatan setup untuk dev lokal. **File ini tidak di-commit ke GitHub** (lihat `.gitignore`).

## Fresh install

```bash
composer install
cp .env.example .env
php artisan key:generate          # fix: "No application encryption key has been specified"
touch database/database.sqlite    # DB default pakai sqlite
php artisan migrate --force
php artisan db:seed --force        # bikin akun admin
npm install
npm run build
```

## Menjalankan dev

```bash
npm run dev        # Laravel serve + queue + pail + Vite (via concurrently)
npm run dev:vite   # Vite saja
```

## Akun admin (dari seeder)

- Email: `admin@example.com`
- Password: `password123`

Seeder (`database/seeders/DatabaseSeeder.php`) idempotent (`updateOrCreate`) —
aman dijalankan berkali-kali. Data pipeline diimpor via SQL dump, bukan seeder.

## Troubleshooting

| Error | Fix |
|-------|-----|
| `No application encryption key has been specified` | `php artisan key:generate` |
| `Database file ... does not exist` | `touch database/database.sqlite` lalu `php artisan migrate` |
| Env jalan sebagai `production` | Pastikan `.env` ada + `APP_ENV=local` |
