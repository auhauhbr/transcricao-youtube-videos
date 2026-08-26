# Transcrev

Base técnica do projeto em Laravel, Inertia.js, Vue 3, JavaScript e Tailwind CSS, com PostgreSQL e Redis.

## Recursos

- extração assíncrona e visualização estruturada de transcrições do YouTube;
- biblioteca pessoal compacta com pastas e tags;
- busca por vídeo, canal e tag, com filtros e ordenação;
- seleção múltipla para mover, etiquetar ou remover itens da biblioteca;
- Workspace pessoal com documento editável em Tiptap, autosave concorrente seguro e histórico de versões;
- checkpoints automáticos espaçados, versões manuais e restauração com backup do estado anterior;
- downloads individuais em TXT e Markdown.

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

A suíte normal é totalmente offline. O smoke test externo do provider YouTube é opt-in e deve ser executado somente no worker:

```bash
docker compose exec -T -e RUN_EXTERNAL_YOUTUBE_TESTS=1 queue php artisan test tests/External/YouTubeTranscriptProviderExternalTest.php --do-not-cache-result --cache-directory=/tmp/pest-cache
```
