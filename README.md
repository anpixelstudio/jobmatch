# Jobmatch — AI Resume Matcher

Laravel 13 + Inertia React + shadcn. HR chat kriteria → matching CV (Kaggle + unggahan).

## Setup

```bash
cd "13. Project Aira/06. Aplikasi"
composer install
bun install
cp .env.example .env   # jika belum ada
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan jobmatch:import-kaggle
bun run build
php artisan serve
```

Dev (cek dulu port 8000 / vite):

```bash
composer run dev
# atau: php artisan serve  +  bun run dev
```

## Login demo

- Email: `hr@jobmatch.test`
- Password: `password`

## Google AI Studio (Gemini)

Isi di `.env`:

```env
GEMINI_API_KEY=your_key_here
GEMINI_MODEL=gemini-2.5-flash
```

Chatbot memakai Gemini untuk ekstrak kriteria + balasan natural language. Ranking CV tetap dari korpus lokal (Kaggle/upload). Tanpa key, fallback ke mode lokal.

## Dataset

Default path import: `../04. Data/dataset-cv` (relatif dari folder app = `../../04. Data/dataset-cv` dari `base_path()`).

```bash
php artisan jobmatch:import-kaggle
php artisan jobmatch:import-kaggle --limit=200   # sample cepat
```

## Halaman

| URL | Fitur |
| --- | --- |
| `/dashboard` | Metrik, kategori, lowongan |
| `/uploads` | Parameter job + unggah CV |
| `/chat` | Jobmatch Bot: kriteria → list CV match |
| `/candidates` | Bank kandidat + detail + status ATS |

## Deploy

- Live: https://hr.maheskanoko.com
- Repo: https://github.com/anpixelstudio/saas-hr
- CI/CD: push `main` → GitHub Actions → VM website-mahes
