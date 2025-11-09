# dion.coach — Slim + Cloud Run starter

## Quick start
1. Install dependencies locally:
   ```bash
   composer install
   ```
2. Build & run locally (Docker):
   ```bash
   docker build -t dion-coach .
   docker run -p 8080:8080 -e SENDGRID_API_KEY=YOUR_KEY dion-coach
   ```
3. Deploy to Cloud Run once via gcloud or push to main and let GitHub Actions deploy.

## Required env
- `SENDGRID_API_KEY` (or `BREVO_API_KEY`)

## DNS / Domain mapping
Map `dion.coach` in Cloud Run Domain mappings; then create the shown records in Cloudflare.
