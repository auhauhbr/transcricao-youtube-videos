# Plataforma de Transcrições

Base técnica do projeto em Laravel, Inertia.js, Vue 3, JavaScript e Tailwind CSS, com PostgreSQL e Redis.

## Desenvolvimento com Docker

```bash
cp .env.example .env
npm ci
npm run build
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

A aplicação ficará disponível em `http://localhost:8000`.

## Qualidade

```bash
composer test
composer lint
composer analyse
npm run build
```
