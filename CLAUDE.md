# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP CLI application that fetches population data from Falkenberg municipality's API and saves it to Directus CMS. The application retrieves the number of registered residents ("folkbokförda") in Falkenberg, stores it in the Directus database, and sends health check signals to a self-hosted healthchecks.io instance.

## Running the Application

Execute the main script:
```bash
php befolkning.php
```

## Configuration

The application uses a `.env` file for API credentials and configuration. Copy `.env.example` to `.env` and fill in your credentials:

```bash
cp .env.example .env
```

Required configuration:
```
FB_DATABASE=Falkenberg
FB_USER=your-username
FB_PASSWORD=your-password
FB_API_URL=https://fb.falkenberg.se/FBservice/Befolkning/folkbokforing/antal

DIRECTUS_URL=https://nav.utvecklingfalkenberg.se/items/befolkning_fb_webb
DIRECTUS_TOKEN=your-directus-bearer-token

HEALTHCHECK_URL=https://healthchecks.utvecklingfalkenberg.se/ping/your-check-uuid
```

The `.env` file is git-ignored for security. Use `.env.example` as a template.

## Project Structure

- `befolkning.php` - Main script that fetches population data and saves to Directus
- `.env` - Configuration file with API credentials (not in version control)
- `.env.example` - Template for environment variables (safe to commit)
- `.gitignore` - Excludes sensitive files from git

## Technical Details

- Uses native PHP cURL for API requests (no external dependencies)
- Custom `.env` file parser
- Fetches data from Falkenberg's population API
- Parses JSON response to extract population count
- Saves data to Directus CMS via REST API with Bearer token authentication
- Integrates with self-hosted healthchecks.io for monitoring
- Sends failure notifications with error messages in request body
- Provides clear progress feedback during execution
- Project uses Swedish naming conventions

## Workflow

1. Loads environment variables from `.env`
2. Sends healthcheck start signal
3. Makes POST request to Falkenberg's population API
4. Parses JSON response to extract `antalFolkbokforda`
5. Creates POST request to Directus with population data
6. Sends healthcheck success signal on completion
7. On error: sends healthcheck failure signal with error message

## Health Check Integration

The application uses healthchecks.io for monitoring:
- **Start signal**: Sent at the beginning of execution (`/start`)
- **Success signal**: Sent when all operations complete successfully
- **Failure signal**: Sent with error message in body if any operation fails (`/fail`)

This allows monitoring the script's execution and receiving alerts if it fails to run or encounters errors.
